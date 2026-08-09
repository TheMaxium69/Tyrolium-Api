@echo off
REM Mise a jour quotidienne : recupere le code, reinstalle les dependances si besoin,
REM et surtout rejoue les migrations creees par les autres - jamais "diff"/"make:migration"
REM ici, seulement "migrate". Voir la discussion sur le workflow de migrations partagees.
cd /d "%~dp0.."

echo ==^> git pull
call git pull
if errorlevel 1 exit /b 1

echo ==^> Composer install ^(no-op si rien n'a change^)
call composer install
if errorlevel 1 exit /b 1

echo ==^> Application des migrations
call php bin\console doctrine:migrations:migrate --no-interaction
if errorlevel 1 exit /b 1

echo ==^> Cache clear ^(dev^)
call php bin\console cache:clear --env=dev

echo.
echo ==^> A jour.
