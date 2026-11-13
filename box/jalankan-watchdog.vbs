Option Explicit
Dim fso, shell, wmi, procs, proc, boxDir, watchdogBat
Set fso = CreateObject("Scripting.FileSystemObject")
Set shell = CreateObject("WScript.Shell")
boxDir = fso.GetParentFolderName(WScript.ScriptFullName)
watchdogBat = fso.BuildPath(boxDir, "watchdog.bat")

On Error Resume Next
Set wmi = GetObject("winmgmts:\\.\root\cimv2")
If Err.Number = 0 Then
    Set procs = wmi.ExecQuery("SELECT CommandLine FROM Win32_Process WHERE Name = 'cmd.exe'")
    For Each proc In procs
        If Not IsNull(proc.CommandLine) Then
            If InStr(1, LCase(proc.CommandLine), "box\watchdog.bat", vbTextCompare) > 0 Or _
               InStr(1, LCase(proc.CommandLine), "watchdog.bat", vbTextCompare) > 0 Then
                WScript.Quit 0
            End If
        End If
    Next
End If
On Error GoTo 0

shell.CurrentDirectory = boxDir
shell.Run "cmd.exe /c """ & watchdogBat & """", 0, False