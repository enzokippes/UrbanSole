@echo off
echo ==========================================
echo    Iniciando UrbanSole (Full-Stack)
echo ==========================================

REM Iniciar Backend Laravel en segundo plano
start "UrbanSole - Backend Laravel (Port 8000)" cmd /k "cd backend && php artisan serve --host=127.0.0.1 --port=8000"

REM Iniciar Frontend React en segundo plano
start "UrbanSole - Frontend Vite (Port 5173)" cmd /k "cd frontend && npm run dev"

echo.
echo Servidores iniciados en ventanas separadas:
echo - Frontend: http://localhost:5173
echo - Backend:  http://127.0.0.1:8000
echo.
pause
