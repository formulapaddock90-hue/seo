@echo off
title GitHub Folder Uploader
setlocal

:: Ottieni la directory in cui si trova lo script bat
set "SCRIPT_DIR=%~dp0"

:: Controlla se Python è installato
where python >nul 2>nul
if %ERRORLEVEL% NEQ 0 (
    echo [ERRORE] Python non e' stato trovato nel sistema.
    echo Assicurati che Python sia installato e aggiunto al PATH.
    echo.
    pause
    exit /b 1
)

:: Se una cartella è stata trascinata sopra il file .bat o passata come primo argomento
if not "%~1"=="" (
    python "%SCRIPT_DIR%github_uploader.py" --folder "%~1" %*
) else (
    python "%SCRIPT_DIR%github_uploader.py" %*
)

echo.
echo Premi un tasto qualsiasi per uscire...
pause >nul
