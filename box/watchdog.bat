@echo off
setlocal
cd /d "%~dp0.."

rem ============================================================================
rem watchdog.bat - Loop pemantau tiap ~20 detik untuk Box PC E-PRESKNBI
rem Jika port 8000 (Laravel serve) atau 8787 (FingerBridge) mati -> nyalakan lagi
rem ============================================================================

:loop
rem Cek port 8000 (Laravel serve)
netstat -ano | findstr /R /C:":8000 .*LISTENING" >nul 2>&1
if errorlevel 1 (
    wscript.exe //B //NoLogo "%~dp0jalankan-tersembunyi.vbs" "cmd.exe /c php artisan serve --host=0.0.0.0 --port=8000" "%CD%"
)

rem Cek port 8787 (FingerBridge ZK9500)
netstat -ano | findstr /R /C:":8787 .*LISTENING" >nul 2>&1
if errorlevel 1 (
    if exist "%CD%\bridge\start-fingerbridge.vbs" (
        wscript.exe //B //NoLogo "%CD%\bridge\start-fingerbridge.vbs"
    ) else if exist "%CD%\bridge\fingerbridge.exe" (
        wscript.exe //B //NoLogo "%~dp0jalankan-tersembunyi.vbs" "cmd.exe /c bridge\fingerbridge.exe" "%CD%\bridge"
    )
)

ping -n 21 127.0.0.1 >nul 2>&1
goto loop