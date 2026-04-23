@echo off
setlocal

set "PROJECT_DIR=C:\xampp\htdocs\Sandbox\ProjectEcho"
set "PHP_EXE=C:\xampp\php\php.exe"
set "PORT=8000"
set "IP="

if not exist "%PROJECT_DIR%\artisan" (
    echo Could not find the Laravel artisan file in:
    echo %PROJECT_DIR%
    pause
    exit /b 1
)

if not exist "%PHP_EXE%" (
    echo XAMPP PHP was not found at:
    echo %PHP_EXE%
    echo.
    echo Update PHP_EXE in this file or install XAMPP in the default location.
    pause
    exit /b 1
)

for /f "usebackq delims=" %%i in (`powershell -NoProfile -Command "Get-NetIPAddress -AddressFamily IPv4 ^| Where-Object { $_.IPAddress -notlike '127.*' -and $_.InterfaceAlias -match 'Wi-Fi|Ethernet' -and $_.PrefixOrigin -ne 'WellKnown' } ^| Select-Object -First 1 -ExpandProperty IPAddress"`) do (
    set "IP=%%i"
)

if "%IP%"=="" (
    for /f "usebackq delims=" %%i in (`powershell -NoProfile -Command "Get-NetIPAddress -AddressFamily IPv4 ^| Where-Object { $_.IPAddress -notlike '127.*' -and $_.PrefixOrigin -ne 'WellKnown' } ^| Select-Object -First 1 -ExpandProperty IPAddress"`) do (
        set "IP=%%i"
    )
)

if "%IP%"=="" (
    echo Could not detect a local IPv4 address.
    pause
    exit /b 1
)

echo Starting Laravel on http://%IP%:%PORT%
cd /d "%PROJECT_DIR%"
"%PHP_EXE%" artisan serve --host=%IP% --port=%PORT%

endlocal
