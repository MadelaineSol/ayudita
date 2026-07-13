@echo off
chcp 65001 >nul
cd /d "%~dp0"
echo.
echo ================================================
echo  AYUDITA - CONFIGURAR URL DEL BACKEND
echo ================================================
echo.
echo Emulador Android: http://10.0.2.2:8080
echo Celular fisico:   http://IP-DE-TU-PC:8080
echo Produccion:       https://api.tudominio.com
echo.
set /p API_URL=Escribi la URL sin /api/v1: 
if "%API_URL%"=="" (
  echo No se ingreso ninguna URL.
  pause
  exit /b 1
)
node set-api.js "%API_URL%"
pause
