<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\SchoolClass;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminClassController extends Controller
{
    /**
     * Daftar kelas (hanya admin).
     */
    public function index(Request $request): View
    {

        $classes = SchoolClass::withCount('students')->orderBy('name')->get();

        return view('admin.classes.index', [
            'classes' => $classes,
        ]);
    }

    /**
     * Form tambah kelas (hanya admin).
     */
    public function create(Request $request): View
    {

        return view('admin.classes.create');
    }

    /**
     * Simpan kelas baru. Slug diisi otomatis dari nama bila dikosongkan.
     */
    public function store(Request $request): RedirectResponse
    {

        $validated = $this->validasi($request);
        $validated['slug'] = $this->selesaikanSlug($validated['slug'] ?: $validated['name']);

        SchoolClass::create($validated);

        return redirect()
            ->route('admin.kelas.index')
            ->with('status', 'Kelas "' . $validated['name'] . '" berhasil ditambahkan (slug: ' . $validated['slug'] . ').');
    }

    /**
     * Form ubah kelas (hanya admin).
     */
    public function edit(Request $request, SchoolClass $class): View
    {

        return view('admin.classes.edit', [
            'class' => $class,
        ]);
    }

    /**
     * Perbarui kelas. Slug dikosongkan → dibuat ulang dari nama.
     */
    public function update(Request $request, SchoolClass $class): RedirectResponse
    {

        $validated = $this->validasi($request, $class);
        $validated['slug'] = $this->selesaikanSlug($validated['slug'] ?: $validated['name'], $class->id);

        $class->update($validated);

        return redirect()
            ->route('admin.kelas.index')
            ->with('status', 'Kelas "' . $validated['name'] . '" berhasil diperbarui.');
    }

    /**
     * Hapus kelas. DITOLAK bila masih ada siswa atau catatan absensi,
     * agar data historis tidak ikut terhapus (cascade di DB).
     */
    public function destroy(Request $request, SchoolClass $class): RedirectResponse
    {

        $jumlahSiswa = $class->students()->count();
        $jumlahAbsensi = Attendance::where('school_class_id', $class->id)->count();

        if ($jumlahSiswa > 0 || $jumlahAbsensi > 0) {
            return redirect()
                ->route('admin.kelas.index')
                ->with('error', 'Kelas "' . $class->name . '" tidak bisa dihapus: masih ada ' . $jumlahSiswa . ' siswa dan ' . $jumlahAbsensi . ' catatan absensi. Pindahkan dulu siswanya ke kelas lain.');
        }

        $class->delete();

        return redirect()
            ->route('admin.kelas.index')
            ->with('status', 'Kelas "' . $class->name . '" berhasil dihapus.');
    }

    /**
     * Validasi input form kelas (Bahasa Indonesia).
     */
    private function validasi(Request $request, ?SchoolClass $class = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'slug' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-]+$/', Rule::unique('school_classes', 'slug')->ignore($class?->id)],
            'department' => ['required', 'string', 'max:50'],
            'entry_time' => ['required', 'date_format:H:i'],
            'exit_time' => ['required', 'date_format:H:i'],
        ], [
            'name.required' => 'Nama kelas wajib diisi.',
            'slug.regex' => 'Slug hanya boleh huruf, angka, dan tanda minus (contoh: X-TKJ-1). Pola diubah otomatis ke kapital.',
            'slug.unique' => 'Slug tersebut sudah dipakai kelas lain.',
            'department.required' => 'Jurusan wajib diisi.',
            'entry_time.required' => 'Jam masuk wajib diisi.',
            'exit_time.required' => 'Jam pulang wajib diisi.',
        ]);
    }

    /**
     * Normalisasi slug (huruf besar + minus). Bila bentrok, tambah akhiran -2, -3, dst.
     * $ignoreId dipakai saat update agar slug milik sendiri tidak dianggap bentrok.
     */
    private function selesaikanSlug(string $nilai, ?int $ignoreId = null): string
    {
        $dasar = strtoupper(trim($nilai));
        $dasar = preg_replace('/[^A-Z0-9]+/', '-', $dasar);
        $dasar = trim((string) $dasar, '-');
        $dasar = substr($dasar, 0, 50);

        $slug = $dasar;
        $i = 2;
        while (SchoolClass::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = substr($dasar, 0, 45) . '-' . $i++;
        }

        return $slug;
    }
}

