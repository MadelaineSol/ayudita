@echo off
setlocal
cd /d "%~dp0"
echo.
echo ================================================
echo  AYUDITA - REPARAR DESCARGA DE GRADLE
echo ================================================
echo.
echo Cerrando procesos Java/Gradle que puedan bloquear archivos...
taskkill /F /IM java.exe >nul 2>&1
taskkill /F /IM javaw.exe >nul 2>&1

echo Eliminando descargas incompletas de Gradle 8.7...
rmdir /S /Q "%USERPROFILE%\.gradle\wrapper\dists\gradle-8.7-all" 2>nul
rmdir /S /Q "%USERPROFILE%\.gradle\wrapper\dists\gradle-8.7-bin" 2>nul

echo Verificando configuracion...
findstr /C:"gradle-8.7-bin.zip" "android\gradle\wrapper\gradle-wrapper.properties" >nul
if errorlevel 1 (
  echo ERROR: gradle-wrapper.properties no apunta a gradle-8.7-bin.zip
  echo Abrilo y corregi distributionUrl.
  pause
  exit /b 1
)

echo OK: configurado gradle-8.7-bin.zip
echo.
echo Ahora abri Android Studio y usa:
echo File ^> Sync Project with Gradle Files
echo.
echo Si vuelve a aparecer Tag mismatch, cambia temporalmente a la conexion
 echo compartida del celular y desactiva VPN/proxy antes de sincronizar.
pause
endlocal
