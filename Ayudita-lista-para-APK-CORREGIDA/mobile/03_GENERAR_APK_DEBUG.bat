@echo off
set "JAVA_HOME=C:\Program Files\Android\Android Studio\jbr"
set "PATH=%JAVA_HOME%\bin;%PATH%"
chcp 65001 >nul
cd /d "%~dp0"
echo.
echo ================================================
echo  AYUDITA - GENERAR APK DE PRUEBA
echo ================================================
echo.
where node >nul 2>nul || (
  echo ERROR: Node.js no esta instalado o no esta en PATH.
  pause
  exit /b 1
)
if not exist node_modules (
  call npm install
  if errorlevel 1 goto :error
)
call npm run apk:debug
if errorlevel 1 goto :error
echo.
echo La APK quedo en: mobile\dist\Ayudita-debug.apk
pause
exit /b 0
:error
echo.
echo Ocurrio un error. Verifica que Android Studio y Android SDK esten instalados.
pause
exit /b 1
