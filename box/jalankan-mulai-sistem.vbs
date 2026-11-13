Option Explicit
Dim fso, shell, boxDir, batPath
Set fso = CreateObject("Scripting.FileSystemObject")
Set shell = CreateObject("WScript.Shell")
boxDir = fso.GetParentFolderName(WScript.ScriptFullName)
batPath = fso.BuildPath(boxDir, "mulai-sistem.bat")
shell.CurrentDirectory = boxDir
shell.Run "cmd.exe /c """ & batPath & """", 0, False