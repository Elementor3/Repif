
@echo off
setlocal EnableExtensions EnableDelayedExpansion

set "ROOT=%~1"
if not defined ROOT set "ROOT=%cd%"
for %%A in ("%ROOT%") do set "ROOT=%%~fA"

set "OUT=%~2"
if not defined OUT set "OUT=%ROOT%\output.txt"
for %%A in ("%OUT%") do set "OUT=%%~fA"

echo [INFO] Root:  "%ROOT%"
echo [INFO] Output: "%OUT%"
echo.

echo [INFO] Writing tree...
tree "%ROOT%" /a /f > "%OUT%"
echo.>>"%OUT%"
echo ===BEGIN_FILES===>>"%OUT%"
echo.

echo [INFO] Appending raw file contents...
for /r "%ROOT%" %%F in (*) do (
    if /I not "%%~fF"=="%OUT%" (
        set "REL=%%~fF"
        set "REL=!REL:%ROOT%\=!"
        echo [ADD] !REL!
        >> "%OUT%" echo ===!REL!===
        type "%%~fF" >> "%OUT%"
        >> "%OUT%" echo ===
    )
)

echo [INFO] Done. Output: "%OUT%"
