@echo off
REM Cree une nouvelle branche a jour avec origin/main et prepare l'environnement
REM pour coder tout de suite (composer install + migrations + cache). Usage :
REM   scripts\new-branch.bat ma-nouvelle-branche
REM ou sans argument, il demande le nom.
cd /d "%~dp0.."

for /f "delims=" %%s in ('git status --porcelain') do (
    echo Tu as des changements non commites. Commit ou stash-les avant de creer une branche.
    exit /b 1
)

set BRANCH_NAME=%~1
if "%BRANCH_NAME%"=="" (
    set /p BRANCH_NAME=Nom de la nouvelle branche:
)

if "%BRANCH_NAME%"=="" (
    echo Nom de branche vide, annule.
    exit /b 1
)

echo ==^> git fetch origin main
call git fetch origin main
if errorlevel 1 exit /b 1

echo ==^> Creation de '%BRANCH_NAME%' depuis origin/main
call git checkout -b %BRANCH_NAME% origin/main
if errorlevel 1 exit /b 1

echo ==^> Composer install
call composer install
if errorlevel 1 exit /b 1

echo ==^> Application des migrations
call php bin\console doctrine:migrations:migrate --no-interaction
if errorlevel 1 exit /b 1

echo ==^> Cache clear (dev)
call php bin\console cache:clear --env=dev

echo.
echo ==^> Branche '%BRANCH_NAME%' prete, a jour avec origin/main.
