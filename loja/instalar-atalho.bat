@echo off
rem Cria o atalho "Nana Comanda - Balcao + Cozinha". Clique duas vezes neste arquivo.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0instalar-atalho.ps1" %*
echo.
pause
