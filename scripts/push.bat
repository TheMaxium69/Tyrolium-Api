@echo off
REM Push la branche courante vers TOUS les remotes Git configures (utile si le
REM projet est replique sur deux serveurs de repo) - pas d'URL en dur ici,
REM on push vers ce que "git remote" renvoie sur ce poste.
cd /d "%~dp0.."

for /f "delims=" %%b in ('git rev-parse --abbrev-ref HEAD') do set BRANCH=%%b

set FOUND_REMOTE=0
for /f "delims=" %%r in ('git remote') do (
    set FOUND_REMOTE=1
    echo ==^> Push vers '%%r' ^(%BRANCH%^)
    call git push %%r %BRANCH%
    if errorlevel 1 exit /b 1
)

if %FOUND_REMOTE%==0 (
    echo Aucun remote Git configure ^(git remote -v^).
    exit /b 1
)
