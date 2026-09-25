@echo off
cd /d "%~dp0"
where py >nul 2>nul
if errorlevel 1 (
  echo No encuentro Python. Instala Python 3 y vuelve a intentarlo.
  pause
  exit /b 1
)
if not exist .venv py -m venv .venv
call .venv\Scripts\python.exe -m pip install -r requirements.txt
if errorlevel 1 (
  echo No se pudo instalar la busqueda. Revisa tu conexion y vuelve a intentarlo.
  pause
  exit /b 1
)
start "" http://127.0.0.1:8765
.venv\Scripts\python.exe server.py
pause
