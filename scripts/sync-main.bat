@echo off
REM A lancer sur SA branche de feature (jamais sur main) : recupere les derniers
REM changements de main (typiquement apres qu'une PR a ete mergee) et les
REM fusionne dans la branche courante. Ne touche jamais a main directement,
REM ne force-push jamais.
cd /d "%~dp0.."

for /f "delims=" %%b in ('git rev-parse --abbrev-ref HEAD') do set CURRENT_BRANCH=%%b

if "%CURRENT_BRANCH%"=="main" (
    echo Tu es deja sur main - utilise scripts\pull.bat, pas celui-ci.
    exit /b 1
)

for /f "delims=" %%s in ('git status --porcelain') do (
    echo Tu as des changements non commites. Commit ou stash-les avant de synchroniser avec main.
    exit /b 1
)

set REMOTE=origin
git remote | findstr /x "origin" >nul 2>&1
if errorlevel 1 (
    echo Le remote "origin" n'existe pas sur ce poste.
    exit /b 1
)

echo ==^> git fetch %REMOTE% main
call git fetch %REMOTE% main
if errorlevel 1 exit /b 1

echo ==^> Fusion de %REMOTE%/main dans '%CURRENT_BRANCH%'
call git merge %REMOTE%/main
if errorlevel 1 (
    echo.
    echo !! Conflit de fusion. Resous les conflits ^(git status pour voir les fichiers concernes^),
    echo !! puis 'git add' + 'git commit' pour terminer la fusion.
    exit /b 1
)

echo ==^> Composer install ^(au cas ou main a ajoute des dependances^)
call composer install
if errorlevel 1 exit /b 1

echo ==^> Application des migrations ^(au cas ou main en a ajoute^)
call php bin\console doctrine:migrations:migrate --no-interaction
if errorlevel 1 exit /b 1

echo.
echo ==^> '%CURRENT_BRANCH%' est a jour avec %REMOTE%/main.
