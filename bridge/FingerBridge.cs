// FingerBridge — jembatan HTTP minimal untuk sensor ZKTeco ZK9500 (ZKFinger SDK).
//
// Membungkus SDK RESMI lewat libzkfpcsharp.dll (wrapper C# bawaan ZKFinger SDK, yang
// memanggil libzkfp.dll native). Signature fungsi dibaca langsung dari assembly SDK.
//
// Kontrak HTTP (dipanggil server Laravel — FingerprintService):
//   GET  /estado      -> {"conectado":bool,"modelo":"ZK9500"}
//   POST /enrolar     -> {"ok":true,"huellaId":"<GUID>","template":"<base64>"}
//   GET  /identificar -> {"ok":true,"huellaId":"<GUID>"}  |  {"ok":false}
//
// Bind HANYA 127.0.0.1:8787. Template disimpan di templates.json (samping exe) —
// itu data biometrik: JANGAN di-commit.
//
// Build (32-bit, csc bawaan Windows): lihat README bagian "Build FingerBridge dari SDK".

using System;
using System.Collections.Generic;
using System.IO;
using System.Net;
using System.Text;
using System.Threading;
using System.Web.Script.Serialization;
using libzkfpcsharp;

namespace FingerBridge
{
    internal class TemplateRecord
    {
        public string huellaId { get; set; }
        public string template { get; set; }
    }

    internal static class Program
    {
        private const string Prefix = "http://127.0.0.1:8787/";
        private const int TemplateBufferSize = 2048;
        private const int EnrolWaitMs = 8000;     // < FINGERPRINT_TIMEOUT Laravel (10 dtk)
        private const int IdentifyWaitMs = 3000;  // kiosk memanggil berulang (loop otomatis)
        private const int MatchMinScore = 40;

        private static readonly object DeviceLock = new object();
        private static readonly JavaScriptSerializer Json = new JavaScriptSerializer();
        private static readonly string TemplatesPath = Path.Combine(AppDomain.CurrentDomain.BaseDirectory, "templates.json");

        private static IntPtr devHandle = IntPtr.Zero;
        private static IntPtr dbHandle = IntPtr.Zero;
        private static bool libraryInitialised = false;
        private static int imageWidth = 0;
        private static int imageHeight = 0;
        private static string lastError = "";

        private static readonly List<TemplateRecord> Records = new List<TemplateRecord>();
        private static readonly Dictionary<int, string> FidToHuella = new Dictionary<int, string>();
        private static int nextFid = 1;

        private static int Main(string[] args)
        {
            Console.WriteLine("FingerBridge (ZKFinger SDK) — mendengarkan di " + Prefix);

            lock (DeviceLock)
            {
                EnsureDevice();
            }
            Console.WriteLine(devHandle != IntPtr.Zero ? "Sensor: TERHUBUNG" : "Sensor: belum terdeteksi (" + lastError + ") — akan dicoba lagi saat /estado dipanggil.");

            HttpListener listener = new HttpListener();
            listener.Prefixes.Add(Prefix);
            try
            {
                listener.Start();
            }
            catch (Exception ex)
            {
                Console.WriteLine("Gagal membuka " + Prefix + " : " + ex.Message);
                Console.WriteLine("Pastikan port 8787 tidak dipakai program lain (mis. FingerBridge lama).");
                return 1;
            }

            Console.CancelKeyPress += delegate { Shutdown(listener); };
            Console.WriteLine("Siap. Tekan Ctrl+C untuk berhenti.");

            while (listener.IsListening)
            {
                HttpListenerContext context;
                try
                {
                    context = listener.GetContext();
                }
                catch (Exception)
                {
                    break;
                }

                ThreadPool.QueueUserWorkItem(delegate { Handle(context); });
            }

            return 0;
        }

        private static void Shutdown(HttpListener listener)
        {
            try { listener.Stop(); } catch (Exception) { }
            lock (DeviceLock)
            {
                CloseDevice();
            }
        }

        private static void Handle(HttpListenerContext context)
        {
            string body;
            int status = 200;

            try
            {
                string path = context.Request.Url.AbsolutePath.TrimEnd('/').ToLowerInvariant();
                string method = context.Request.HttpMethod.ToUpperInvariant();

                if (path == "/estado" && method == "GET")
                {
                    body = Estado();
                }
                else if (path == "/enrolar" && method == "POST")
                {
                    body = Enrolar();
                }
                else if (path == "/identificar" && method == "GET")
                {
                    body = Identificar();
                }
                else
                {
                    status = 404;
                    body = "{\"ok\":false,\"error\":\"Rute tidak dikenal\"}";
                }
            }
            catch (Exception ex)
            {
                status = 500;
                body = "{\"ok\":false,\"error\":" + Json.Serialize("Kesalahan bridge: " + ex.Message) + "}";
            }

            try
            {
                byte[] bytes = Encoding.UTF8.GetBytes(body);
                context.Response.StatusCode = status;
                context.Response.ContentType = "application/json; charset=utf-8";
                context.Response.ContentLength64 = bytes.Length;
                context.Response.OutputStream.Write(bytes, 0, bytes.Length);
                context.Response.OutputStream.Close();
            }
            catch (Exception)
            {
                // klien memutus koneksi — abaikan
            }
        }

        // ---- Endpoint ------------------------------------------------------------

        private static string Estado()
        {
            bool connected;
            lock (DeviceLock)
            {
                EnsureDevice();
                connected = devHandle != IntPtr.Zero;
            }

            return "{\"conectado\":" + (connected ? "true" : "false") + ",\"modelo\":\"ZK9500\"}";
        }

        private static string Enrolar()
        {
            lock (DeviceLock)
            {
                if (!EnsureDevice())
                {
                    return Fail("Sensor belum terdeteksi: " + lastError);
                }

                byte[] sample = CaptureTemplate(EnrolWaitMs);
                if (sample == null)
                {
                    return Fail(lastError.Length > 0 ? lastError : "Tidak ada jari terdeteksi. Letakkan jari pada sensor.");
                }

                // Template pendaftaran: gabungkan 3 sampel (identik) bila SDK mau; kalau tidak, pakai sampel mentah.
                byte[] registered = sample;
                byte[] merged = new byte[TemplateBufferSize];
                int mergedLength = TemplateBufferSize;
                if (zkfp2.DBMerge(dbHandle, sample, sample, sample, merged, ref mergedLength) == 0 && mergedLength > 0)
                {
                    registered = new byte[mergedLength];
                    Array.Copy(merged, registered, mergedLength);
                }

                string huellaId = Guid.NewGuid().ToString();
                if (!AddToCache(huellaId, registered))
                {
                    return Fail("Gagal menyimpan template ke cache SDK.");
                }

                string base64 = Convert.ToBase64String(registered);
                Records.Add(new TemplateRecord { huellaId = huellaId, template = base64 });
                SaveRecords();

                return "{\"ok\":true,\"huellaId\":" + Json.Serialize(huellaId) + ",\"template\":" + Json.Serialize(base64) + "}";
            }
        }

        private static string Identificar()
        {
            lock (DeviceLock)
            {
                if (!EnsureDevice())
                {
                    return Fail("Sensor belum terdeteksi: " + lastError);
                }

                byte[] sample = CaptureTemplate(IdentifyWaitMs);
                if (sample == null)
                {
                    return "{\"ok\":false}";
                }

                int fid = 0;
                int score = 0;
                int result = zkfp2.DBIdentify(dbHandle, sample, ref fid, ref score);
                string huellaId;
                if (result == 0 && score >= MatchMinScore && FidToHuella.TryGetValue(fid, out huellaId))
                {
                    return "{\"ok\":true,\"huellaId\":" + Json.Serialize(huellaId) + "}";
                }

                return "{\"ok\":false}";
            }
        }

        private static string Fail(string message)
        {
            return "{\"ok\":false,\"error\":" + Json.Serialize(message) + "}";
        }

        // ---- Perangkat (selalu dipanggil di dalam DeviceLock) ----------------------

        private static bool EnsureDevice()
        {
            if (devHandle != IntPtr.Zero)
            {
                return true;
            }

            try
            {
                if (!libraryInitialised)
                {
                    int init = zkfp2.Init();
                    if (init != 0 && init != 1) // 1 = ZKFP_ERR_ALREADY_INIT (libzkfperrdef.h)
                    {
                        lastError = "Init SDK gagal (kode " + init + ")";
                        return false;
                    }
                    libraryInitialised = true;
                }

                if (zkfp2.GetDeviceCount() <= 0)
                {
                    lastError = "Tidak ada perangkat USB ZK9500";
                    return false;
                }

                IntPtr handle = zkfp2.OpenDevice(0);
                if (handle == IntPtr.Zero)
                {
                    lastError = "OpenDevice gagal";
                    return false;
                }

                devHandle = handle;
                dbHandle = zkfp2.DBInit();
                ReadImageSize();
                LoadRecords();
                lastError = "";

                return true;
            }
            catch (Exception ex)
            {
                // mis. libzkfp.dll tidak ketemu / bitness salah
                lastError = ex.GetType().Name + ": " + ex.Message;
                devHandle = IntPtr.Zero;

                return false;
            }
        }

        private static void CloseDevice()
        {
            try
            {
                if (dbHandle != IntPtr.Zero) { zkfp2.DBFree(dbHandle); }
                if (devHandle != IntPtr.Zero) { zkfp2.CloseDevice(devHandle); }
                if (libraryInitialised) { zkfp2.Terminate(); }
            }
            catch (Exception)
            {
            }

            dbHandle = IntPtr.Zero;
            devHandle = IntPtr.Zero;
            libraryInitialised = false;
        }

        private static void ReadImageSize()
        {
            imageWidth = ReadIntParameter(1);
            imageHeight = ReadIntParameter(2);
        }

        private static int ReadIntParameter(int code)
        {
            byte[] buffer = new byte[4];
            int size = 4;
            int value = 0;
            if (zkfp2.GetParameters(devHandle, code, buffer, ref size) == 0)
            {
                zkfp2.ByteArray2Int(buffer, ref value);
            }

            return value;
        }

        /// <summary>Tunggu jari lalu ambil template. null bila timeout / error (lastError terisi untuk error).</summary>
        private static byte[] CaptureTemplate(int waitMs)
        {
            int imageSize = Math.Max(imageWidth * imageHeight, 640 * 480);
            byte[] image = new byte[imageSize];
            DateTime deadline = DateTime.UtcNow.AddMilliseconds(waitMs);
            lastError = "";

            while (DateTime.UtcNow < deadline)
            {
                byte[] template = new byte[TemplateBufferSize];
                int size = TemplateBufferSize;
                int result = zkfp2.AcquireFingerprint(devHandle, image, template, ref size);

                if (result == 0 && size > 0)
                {
                    byte[] exact = new byte[size];
                    Array.Copy(template, exact, size);

                    return exact;
                }

                if (result != -8) // -8 = belum ada jari (normal); selain itu error perangkat
                {
                    lastError = "Gagal membaca sensor (kode " + result + ")";
                    if (result == -3 || result == -7 || result == -23 || result == -24)
                    {
                        // sensor dicabut / handle rusak: reset agar dicoba buka ulang
                        CloseDevice();
                    }

                    return null;
                }

                Thread.Sleep(100);
            }

            return null;
        }

        // ---- templates.json --------------------------------------------------------

        private static bool AddToCache(string huellaId, byte[] template)
        {
            int fid = nextFid++;
            if (zkfp2.DBAdd(dbHandle, fid, template) != 0)
            {
                return false;
            }

            FidToHuella[fid] = huellaId;

            return true;
        }

        private static void LoadRecords()
        {
            Records.Clear();
            FidToHuella.Clear();
            nextFid = 1;

            if (!File.Exists(TemplatesPath))
            {
                return;
            }

            try
            {
                TemplateRecord[] saved = Json.Deserialize<TemplateRecord[]>(File.ReadAllText(TemplatesPath, Encoding.UTF8));
                foreach (TemplateRecord record in saved ?? new TemplateRecord[0])
                {
                    if (record == null || string.IsNullOrEmpty(record.huellaId) || string.IsNullOrEmpty(record.template))
                    {
                        continue;
                    }

                    if (AddToCache(record.huellaId, Convert.FromBase64String(record.template)))
                    {
                        Records.Add(record);
                    }
                }

                Console.WriteLine("Memuat " + Records.Count + " template dari templates.json");
            }
            catch (Exception ex)
            {
                Console.WriteLine("templates.json tidak terbaca: " + ex.Message);
            }
        }

        private static void SaveRecords()
        {
            File.WriteAllText(TemplatesPath, Json.Serialize(Records), new UTF8Encoding(false));
        }
    }
}
