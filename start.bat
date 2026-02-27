@echo off
title Cancha Sintética — Servidor de desarrollo
color 0A

echo.
echo  ============================================
echo    CANCHA SINTETICA - Iniciando servicios...
echo  ============================================
echo.

REM ── Terminal 1: Laravel (php artisan serve) ──
echo  [1/4] Iniciando Laravel (php artisan serve)...
start "Laravel Server" cmd /k "color 0B && title Laravel Server && php artisan serve"

REM Esperar un momento para que Laravel arranque primero
timeout /t 2 /nobreak >nul

REM ── Terminal 2: Vite (npm run dev) ──
echo  [2/4] Iniciando Vite (npm run dev)...
start "Vite Dev Server" cmd /k "color 0E && title Vite Dev Server && npm run dev"

REM ── Terminal 3: Laravel Reverb (WebSockets) ──
echo  [3/4] Iniciando Reverb (WebSockets)...
start "Reverb WebSockets" cmd /k "color 0D && title Reverb WebSockets && php artisan reverb:start"

REM ── Terminal 4: Queue Worker (Jobs: correos + push) ──
echo  [4/4] Iniciando Queue Worker (correos + push)...
start "Queue Worker" cmd /k "color 09 && title Queue Worker && php artisan queue:work --tries=3 --timeout=60"

echo.
echo  ============================================
echo    Todos los servicios iniciados!
echo.
echo    Laravel:   http://localhost:8000
echo    Vite:      http://localhost:5173
echo    Reverb:    ws://localhost:8080
echo    Queue:     corriendo en background
echo.
echo    Admin:     admin@cancha.com / password
echo    Cliente:   cliente@cancha.com / password
echo  ============================================
echo.
echo  Presiona cualquier tecla para cerrar esta ventana...
echo  (Los servidores seguiran corriendo en sus terminales)
echo.
pause >nul
