@echo off
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File setup.ps1
if errorlevel 1 goto failed
docker compose up -d --build
if errorlevel 1 goto failed
echo Site: http://localhost:8080
echo Admin: http://localhost:8080/wp-admin/
echo Mailbox: http://localhost:8025
pause
exit /b 0
:failed
echo Startup failed. Check the message above and README.md.
pause
exit /b 1
