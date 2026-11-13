@echo off
setlocal
cd /d "%~dp0.."

rem ============================================================================
rem mulai-sistem.bat - Nyalakan seluruh sistem E-PRESKNBI secara tersembunyi
rem (idempotent: cek port 8000 & 8787 dulu sebelum start, tanpa jendela console)
rem ============================================================================

rem 1. Laravel serve di port 8000 (0.0.0.0:8000 agar PC guru/admin di LAN bisa akses)
netstat -ano | findstr /R /C:":8000 .*LISTENING" >nul 2>&1
if errorlevel 1 (
    wscript.exe //B //NoLogo "%~dp0jalankan-tersembunyi.vbs" "cmd.exe /c php artisan serve --host=0.0.0.0 --port=8000" "%CD%"
)

rem 2. FingerBridge di port 8787 (via start-fingerbridge.vbs bila exe tersedia)
netstat -ano | findstr /R /C:":8787 .*LISTENING" >nul 2>&1
if errorlevel 1 (
    if exist "%CD%\bridge\start-fingerbridge.vbs" (
        wscript.exe //B //NoLogo "%CD%\bridge\start-fingerbridge.vbs"
    ) else if exist "%CD%\bridge\fingerbridge.exe" (
        wscript.exe //B //NoLogo "%~dp0jalankan-tersembunyi.vbs" "cmd.exe /c bridge\fingerbridge.exe" "%CD%\bridge"
    )
)

rem 3. Watchdog pemantau port 8000 & 8787 (tersembunyi, tunggal)
wscript.exe //B //NoLogo "%~dp0jalankan-watchdog.vbs"

rem 4. Tunggu sejenak agar port 8000 siap, lalu buka Chrome Kiosk ke /kiosk
ping -n 4 127.0.0.1 >nul 2>&1

set "CHROME_EXE="
if exist "%ProgramFiles%\Google\Chrome\Application\chrome.exe" set "CHROME_EXE=%ProgramFiles%\Google\Chrome\Application\chrome.exe"
if not defined CHROME_EXE if exist "%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe" set "CHROME_EXE=%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe"
if not defined CHROME_EXE if exist "%LocalAppData%\Google\Chrome\Application\chrome.exe" set "CHROME_EXE=%LocalAppData%\Google\Chrome\Application\chrome.exe"

if defined CHROME_EXE (
    tasklist /FI "IMAGENAME eq chrome.exe" 2>nul | find /I "chrome.exe" >nul 2>&1
    if errorlevel 1 (
        wscript.exe //B //NoLogo "%~dp0jalankan-tersembunyi.vbs" """%CHROME_EXE%"" --kiosk --app=http://localhost:8000/kiosk" "%CD%"
    )
)

exit /b 0