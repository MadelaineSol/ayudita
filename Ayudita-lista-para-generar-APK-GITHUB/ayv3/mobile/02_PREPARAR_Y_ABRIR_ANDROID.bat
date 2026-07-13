@echo off
chcp 65001 >nul
cd /d "%~dp0"
echo.
echo ================================================
echo  AYUDITA - PREPARAR PROYECTO ANDROID
echo ================================================
echo.
where node >nul 2>nul || (
  echo ERROR: Node.js no esta instalado o no esta en PATH.
  echo Descargalo desde nodejs.org y reinicia la terminal.
  pause
  exit /b 1
)

echo Configurando el registro publico de npm...
call npm config set registry https://registry.npmjs.org/
if errorlevel 1 goto :error

if exist package-lock.json (
  findstr /I /C:"applied-caas-gateway" package-lock.json >nul 2>nul
  if not errorlevel 1 (
    echo Se encontro un package-lock generado con un registro interno.
    echo Se eliminara para regenerarlo correctamente.
    del /F /Q package-lock.json
  )
)

echo Instalando y reparando dependencias...
call npm install --registry=https://registry.npmjs.org/
if errorlevel 1 goto :error

call npm run android:open
if errorlevel 1 goto :error
exit /b 0

:error
echo.
echo Ocurrio un error. Copia el mensaje completo si necesitas ayuda.
pause
exit /b 1
