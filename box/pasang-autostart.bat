@echo off
setlocal
cd /d "%~dp0.."

rem ============================================================================
rem pasang-autostart.bat - Jalankan SEKALI saat deploy di Box PC
rem Menaruh shortcut mulai-sistem.bat & watchdog.bat ke folder Startup Windows
rem ============================================================================

cscript.exe //NoLogo "%~dp0buat-shortcut.vbs"
if errorlevel 1 (
    echo [GAGAL] Tidak dapat memasang shortcut autostart di folder Startup Windows.
    exit /b 1
)

echo ========================================================================
echo [BERHASIL] Autostart E-PRESKNBI berhasil dipasang ke Startup Windows!
echo Folder Startup : %APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup
echo Shortcut       : E-PRESKNBI-Mulai-Sistem.lnk dan E-PRESKNBI-Watchdog.lnk
echo Setelah PC di-restart, server Laravel (port 8000), sensor sidik jari
echo (port 8787), dan Chrome Kiosk (/kiosk) akan menyala otomatis tanpa
echo perlu membuka PowerShell atau terminal.
echo ========================================================================
exit /b 0