@echo off
setlocal enabledelayedexpansion
REM ADMIN UNIQUEMENT (Maxime) - synchronise "main" depuis GitHub (origin, la
REM source de verite, seul remote ou les PR sont mergees apres CI) vers TOUS
REM les autres remotes Git configures (ex: repo.tyrolium.fr). Les autres devs
REM ne doivent jamais lancer ce script : ils publient leur branche avec
REM push.bat, jamais main directement.
REM
REM Ce script ne fait qu'executer des commandes Git - le vrai controle d'acces
REM est cote serveur (permissions sur chaque remote). S'il n'a pas les droits
REM d'ecriture sur "main" la-bas, le push echouera simplement.
cd /d "%~dp0.."

for /f "delims=" %%s in ('git status --porcelain') do (
    echo Tu as des changements non commites. Commit ou stash-les avant de changer de branche.
    exit /b 1
)

echo ==^> git checkout main
call git checkout main
if errorlevel 1 exit /b 1

echo ==^> git pull origin main
call git pull origin main
if errorlevel 1 exit /b 1

set OTHER_REMOTES=
for /f "delims=" %%r in ('git remote') do (
    if /i not "%%r"=="origin" set OTHER_REMOTES=!OTHER_REMOTES! %%r
)

if "!OTHER_REMOTES!"=="" (
    echo Aucun autre remote que 'origin' configure - rien a synchroniser.
    exit /b 0
)

echo.
echo main va etre poussee vers :
for %%r in (!OTHER_REMOTES!) do echo   - %%r
echo.
set /p CONFIRM=Confirmer ? [y/N]
if /i not "!CONFIRM!"=="y" (
    echo Annule.
    exit /b 1
)

for %%r in (!OTHER_REMOTES!) do (
    echo ==^> Push de main vers '%%r'
    call git push %%r main
    if errorlevel 1 exit /b 1
)

echo.
echo ==^> main synchronisee sur tous les remotes.
