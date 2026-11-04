@echo off
chcp 65001 >nul
title Farias Araujo - sistema no computador
cd /d "%~dp0"
where php >nul 2>nul
if errorlevel 1 (
  echo.
  echo O PHP ainda nao esta instalado neste computador.
  echo Abra o PowerShell e rode:  winget install PHP.PHP.8.3
  echo Depois feche esta janela e de dois cliques neste arquivo de novo.
  echo.
  pause
  exit /b
)
for /f "delims=" %%i in ('php -r "echo dirname(realpath(PHP_BINARY));"') do set PHPDIR=%%i
echo.
echo  Sistema Farias Araujo rodando neste computador.
echo  Site:   http://127.0.0.1:8080
echo  Painel: http://127.0.0.1:8080/painel
echo.
echo  Para desligar, feche esta janela.
echo.
start "" http://127.0.0.1:8080
php -d extension_dir="%PHPDIR%\ext" -d extension=pdo_sqlite -d extension=sqlite3 -d extension=gd -d extension=mbstring -d extension=fileinfo -d display_errors=0 -S 127.0.0.1:8080 -t site
pause
