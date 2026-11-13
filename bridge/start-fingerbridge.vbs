' FingerBridge autostart: jalankan watchdog TANPA jendela (hidden).
' Aman dipanggil berkali-kali: bila watchdog sudah jalan, skrip ini langsung keluar.
Option Explicit
Dim sh, fso, dir, wmi, procs, p
Set sh = CreateObject("WScript.Shell")
Set fso = CreateObject("Scripting.FileSystemObject")
dir = fso.GetParentFolderName(WScript.ScriptFullName)

Set wmi = GetObject("winmgmts:\\.\root\cimv2")
Set procs = wmi.ExecQuery("SELECT CommandLine FROM Win32_Process WHERE Name='cmd.exe'")
For Each p In procs
    If Not IsNull(p.CommandLine) Then
        If InStr(LCase(p.CommandLine), "fingerbridge-watchdog.bat") > 0 Then
            WScript.Quit 0
        End If
    End If
Next

sh.CurrentDirectory = dir
sh.Run "cmd /c """ & dir & "\fingerbridge-watchdog.bat""", 0, False