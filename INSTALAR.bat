@echo off
setlocal
chcp 65001 >nul 2>&1
cd /d "%~dp0"
title BO-r4 - Instalar runtime ThreeJS (sem Python)
echo.
echo ============================================================
echo   BO-r4 - Instalar runtime 3D no ReamurCMS (Windows)
echo   Nao precisa de Python, Git nem terminal.
echo ============================================================
echo.
echo Informe a pasta RAIZ do davidcms, aquela que contem a pasta "dev".
echo Exemplo: C:\xampp\htdocs\davidcms
echo.
set "REPO="
set /p "REPO=Caminho: "
if not defined REPO (
  echo.
  echo Cancelado: nenhum caminho informado.
  echo.
  pause
  exit /b 1
)
if not exist "%REPO%\dev" (
  echo.
  echo ATENCAO: "%REPO%\dev" nao existe.
  echo Confira o caminho. A pasta raiz deve conter "dev", "admin", etc.
  echo.
  pause
  exit /b 1
)
echo.
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0instalar-bo.ps1" -Repo "%REPO%" -Apply
set "RC=%ERRORLEVEL%"
echo.
if "%RC%"=="0" (
  echo SUCESSO. Agora limpe o cache do CMS, ative o tema no Admin
  echo e abra verificar-bo.html na raiz do site.
) else (
  echo FALHOU. Copie a tela toda e me envie.
)
echo.
pause
exit /b %RC%
