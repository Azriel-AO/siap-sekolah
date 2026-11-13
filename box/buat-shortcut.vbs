Option Explicit
Dim fso, shell, startupDir, boxDir, rootDir, sc1, sc2
Set fso = CreateObject("Scripting.FileSystemObject")
Set shell = CreateObject("WScript.Shell")

boxDir = fso.GetParentFolderName(WScript.ScriptFullName)
rootDir = fso.GetParentFolderName(boxDir)
startupDir = shell.SpecialFolders("Startup")

Set sc1 = shell.CreateShortcut(fso.BuildPath(startupDir, "E-PRESKNBI-Mulai-Sistem.lnk"))
sc1.TargetPath = fso.BuildPath(boxDir, "jalankan-mulai-sistem.vbs")
sc1.WorkingDirectory = rootDir
sc1.Description = "Autostart E-PRESKNBI: Laravel Serve + FingerBridge + Chrome Kiosk"
sc1.Save

Set sc2 = shell.CreateShortcut(fso.BuildPath(startupDir, "E-PRESKNBI-Watchdog.lnk"))
sc2.TargetPath = fso.BuildPath(boxDir, "jalankan-watchdog.vbs")
sc2.WorkingDirectory = rootDir
sc2.Description = "Watchdog E-PRESKNBI: Pemantau Port 8000 dan 8787"
sc2.Save