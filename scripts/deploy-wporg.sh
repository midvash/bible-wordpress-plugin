#!/usr/bin/env bash
#
# Publica o plugin no SVN do wordpress.org: o ZIP vira o trunk e uma tag nova,
# e .wordpress-org/ (sem src/) vira o /assets da página do plugin.
#
#   scripts/deploy-wporg.sh <versão> <caminho do zip>
#
# DRY_RUN=true monta tudo e mostra o que mudaria, sem enviar (e sem senha).
# Sem DRY_RUN, precisa de SVN_PASSWORD. O usuário é fixo: midvash.
set -euo pipefail

VERSION="$1"
ZIP="$(cd "$(dirname "$2")" && pwd)/$(basename "$2")"
DRY_RUN="${DRY_RUN:-false}"
SLUG="midvash-bible-references"
SVN_URL="https://plugins.svn.wordpress.org/$SLUG"
ASSETS_SRC="$(pwd)/.wordpress-org"

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

# O SVN é canal de release: uma tag publicada não se reescreve.
if svn ls "$SVN_URL/tags/$VERSION" >/dev/null 2>&1; then
  echo "::error::A tag $VERSION já existe no wordpress.org. Suba a versão antes de publicar."
  exit 1
fi

svn checkout --quiet --depth immediates "$SVN_URL" "$WORK/svn"
cd "$WORK/svn"
svn update --quiet --set-depth infinity trunk assets

unzip -q "$ZIP" -d "$WORK/zip"
rsync -rc --delete "$WORK/zip/$SLUG/" trunk/
rsync -rc --delete --exclude=src "$ASSETS_SRC/" assets/

svn add --quiet --force trunk assets
svn status | { grep '^!' || true; } | sed 's/^! *//' | while IFS= read -r gone; do
  svn rm --quiet "$gone"
done

# Sem isso o navegador baixa as imagens em vez de mostrar.
for f in assets/*.png; do svn propset --quiet svn:mime-type image/png "$f"; done
for f in assets/*.svg; do svn propset --quiet svn:mime-type image/svg+xml "$f"; done

svn copy --quiet trunk "tags/$VERSION"

echo "== O que vai pro wordpress.org"
svn status

if [ "$DRY_RUN" = "true" ]; then
  echo "== Ensaio: nada foi enviado."
  exit 0
fi

svn commit --non-interactive --no-auth-cache \
  --username midvash --password "$SVN_PASSWORD" \
  -m "Publica a versão $VERSION"
echo "== Publicado: https://wordpress.org/plugins/$SLUG/"
