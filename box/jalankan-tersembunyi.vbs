Option Explicit
Dim shell, cmd, workDir
Set shell = CreateObject("WScript.Shell")
If WScript.Arguments.Count >= 1 Then
    cmd = WScript.Arguments(0)
    If WScript.Arguments.Count >= 2 Then
        workDir = WScript.Arguments(1)
        shell.CurrentDirectory = workDir
    End If
    shell.Run cmd, 0, False
End If