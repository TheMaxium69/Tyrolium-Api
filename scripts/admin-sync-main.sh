#!/usr/bin/env bash
# ADMIN UNIQUEMENT (Maxime) — synchronise "main" depuis GitHub (origin, la
# source de vérité, seul remote où les PR sont mergées après CI) vers TOUS les
# autres remotes Git configurés (ex: repo.tyrolium.fr). Les autres devs ne
# doivent jamais lancer ce script : ils publient leur branche avec push.sh,
# jamais main directement.
#
# Ce script ne fait qu'exécuter des commandes Git — le vrai contrôle d'accès
# est côté serveur (permissions sur chaque remote). S'il n'a pas les droits
# d'écriture sur "main" là-bas, le push échouera simplement.
set -e
cd "$(dirname "$0")/.."

if [ -n "$(git status --porcelain)" ]; then
  echo "Tu as des changements non commités. Commit ou stash-les avant de changer de branche."
  exit 1
fi

echo "==> git checkout main"
git checkout main

echo "==> git pull origin main"
git pull origin main

OTHER_REMOTES=$(git remote | grep -vx "origin" || true)

if [ -z "$OTHER_REMOTES" ]; then
  echo "Aucun autre remote que 'origin' configuré — rien à synchroniser."
  exit 0
fi

echo ""
echo "main va être poussée vers :"
echo "$OTHER_REMOTES" | sed 's/^/  - /'
echo ""
read -r -p "Confirmer ? [y/N] " CONFIRM
if [ "$CONFIRM" != "y" ] && [ "$CONFIRM" != "Y" ]; then
  echo "Annulé."
  exit 1
fi

for remote in $OTHER_REMOTES; do
  echo "==> Push de main vers '$remote'"
  git push "$remote" main
done

echo ""
echo "==> main synchronisée sur tous les remotes."
