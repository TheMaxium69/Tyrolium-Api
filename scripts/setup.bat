@echo off
REM Premiere installation du projet (nouveau poste, nouveau dev). Voir scripts\pull.bat
REM pour la mise a jour au quotidien une fois le setup fait.
cd /d "%~dp0.."

echo ==^> Installation des dependances Composer
call composer install
if errorlevel 1 exit /b 1

if not exist .env.local (
    echo ==^> Creation de .env.local depuis le template
    copy .env.local.template .env.local
    echo.
    echo !! .env.local vient d'etre cree avec des identifiants d'exemple.
    echo !! Edite-le avec ta vraie config DB locale ^(voir .doc\env-files.md^), puis relance ce script.
    exit /b 0
)

echo ==^> Creation de la base de donnees ^(si elle n'existe pas deja^)
call php bin\console doctrine:database:create --if-not-exists
if errorlevel 1 exit /b 1

echo ==^> Application des migrations
call php bin\console doctrine:migrations:migrate --no-interaction
if errorlevel 1 exit /b 1

if not exist config\jwt\private.pem (
    echo ==^> Generation des cles JWT ^(dev^)
    call php bin\console lexik:jwt:generate-keypair --env=dev --no-interaction
)

if not exist config\jwt\private-test.pem (
    echo ==^> Generation des cles JWT ^(test^)
    call php bin\console lexik:jwt:generate-keypair --env=test --no-interaction
)

echo.
echo ==^> Setup termine. Tu peux lancer le serveur avec : symfony server:start
