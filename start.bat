@echo off
title Cancha Sintética — Servidor de desarrollo
color 0A

REM PHP de WAMP necesita OPENSSL_CONF para que web-push pueda generar claves locales.
REM Debe estar seteado ANTES de invocar php.exe (la extensión OpenSSL se inicializa antes
REM de que se ejecute código PHP, así que putenv() en runtime no sirve).
set "OPENSSL_CONF=C:\wamp64\bin\php\php8.3.28\extras\ssl\openssl.cnf"

REM cURL necesita un CA bundle para verificar certificados al hablar con FCM/APNs.
REM Reusamos el que trae phpMyAdmin con WAMP (suele estar actualizado).
set "CURL_CA_BUNDLE=C:\wamp64\apps\phpmyadmin5.2.3\vendor\composer\ca-bundle\res\cacert.pem"
set "SSL_CERT_FILE=%CURL_CA_BUNDLE%"

echo.
echo  ============================================
echo    CANCHA SINTETICA - Iniciando servicios...
echo  ============================================
echo.

REM ── Terminal 1: Laravel (php artisan serve) ──
echo  [1/4] Iniciando Laravel (php artisan serve)...
start "Laravel Server" cmd /k "color 0B && title Laravel Server && set OPENSSL_CONF=%OPENSSL_CONF% && set CURL_CA_BUNDLE=%CURL_CA_BUNDLE% && set SSL_CERT_FILE=%SSL_CERT_FILE% && php artisan serve"

REM Esperar un momento para que Laravel arranque primero
timeout /t 2 /nobreak >nul

REM ── Terminal 2: Vite (npm run dev) ──
echo  [2/4] Iniciando Vite (npm run dev)...
start "Vite Dev Server" cmd /k "color 0E && title Vite Dev Server && npm run dev"

REM ── Terminal 3: Laravel Reverb (WebSockets) ──
echo  [3/4] Iniciando Reverb (WebSockets)...
start "Reverb WebSockets" cmd /k "color 0D && title Reverb WebSockets && set OPENSSL_CONF=%OPENSSL_CONF% && set CURL_CA_BUNDLE=%CURL_CA_BUNDLE% && set SSL_CERT_FILE=%SSL_CERT_FILE% && php artisan reverb:start"

REM ── Terminal 4: Queue Worker (Jobs: correos + push) ──
echo  [4/4] Iniciando Queue Worker (correos + push)...
start "Queue Worker" cmd /k "color 09 && title Queue Worker && set OPENSSL_CONF=%OPENSSL_CONF% && set CURL_CA_BUNDLE=%CURL_CA_BUNDLE% && set SSL_CERT_FILE=%SSL_CERT_FILE% && php artisan queue:work --tries=3 --timeout=60"

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
