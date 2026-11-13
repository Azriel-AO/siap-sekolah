@echo off
rem FingerBridge watchdog: jalankan exe; bila berhenti/crash, nyalakan lagi setelah 5 detik.
rem Jalankan lewat start-fingerbridge.vbs (hidden). Log: fingerbridge.log (samping exe).
cd /d "%~dp0"
if not exist fingerbridge.exe exit /b 1
:loop
fingerbridge.exe >> fingerbridge.log 2>&1
ping -n 6 127.0.0.1 >nul
goto loop