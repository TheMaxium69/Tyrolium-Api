#!/usr/bin/env bash
# Push la branche courante vers TOUS les remotes Git configurés (utile si le
# projet est répliqué sur deux serveurs de repo) — pas d'URL en dur ici,
# on push vers ce que "git remote" renvoie sur ce poste.
set -e
cd "$(dirname "$0")/.."

BRANCH=$(git rev-parse --abbrev-ref HEAD)
REMOTES=$(git remote)

if [ -z "$REMOTES" ]; then
  echo "Aucun remote Git configuré (git remote -v)."
  exit 1
fi

for remote in $REMOTES; do
  echo "==> Push vers '$remote' ($BRANCH)"
  git push "$remote" "$BRANCH"
done
