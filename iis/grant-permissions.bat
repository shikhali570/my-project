@echo off
REM ===========================================================================
REM  Grant the IIS user write access to the site folder (run as Administrator).
REM  Use this when PHP cannot create parssaze.db or tmp\sessions.
REM
REM  HOW TO USE
REM    1. Copy this file to the server (or run it from a Command Prompt).
REM    2. Edit SITE_PATH below so it points to your real site folder.
REM    3. Right-click -> "Run as administrator".
REM    4. Reopen check.php and confirm the write tests are green.
REM
REM  If you do not have RDP/Command Prompt access, ask the hosting support to
REM  give IIS_IUSRS "Modify" permission on the site folder.
REM ===========================================================================

REM >>> EDIT THIS LINE <<<
set SITE_PATH=C:\inetpub\vhosts\example.com\httpdocs

if not exist "%SITE_PATH%" (
  echo [ERROR] Folder not found: %SITE_PATH%
  echo         Edit the SITE_PATH line in this file and try again.
  pause
  exit /b 1
)

echo Granting IIS_IUSRS (Modify) on %SITE_PATH% ...
icacls "%SITE_PATH%" /grant "IIS_IUSRS:(OI)(CI)M" /T /C

echo.
echo Granting IUSR (Modify) on %SITE_PATH% ...
icacls "%SITE_PATH%" /grant "IUSR:(OI)(CI)M" /T /C

echo.
echo Granting the default application pool identity (Modify) ...
icacls "%SITE_PATH%" /grant "IIS AppPool\DefaultAppPool:(OI)(CI)M" /T /C

echo.
echo --- Current permission list for the site folder ---
icacls "%SITE_PATH%"

echo.
echo Done. Now reopen check.php - the write tests must be green.
pause
