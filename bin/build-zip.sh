#!/usr/bin/env bash
# Bygger cv-kanalpublicering.zip för uppladdning i WordPress (Tillägg → Lägg till nytt → Ladda upp).
# Zip-filen innehåller mappen cv-kanalpublicering/ så att WordPress känner igen uppdateringar.
set -euo pipefail
cd "$(dirname "$0")/.."
out="${1:-$PWD/cv-kanalpublicering.zip}"
tmp="$(mktemp -d)"
mkdir "$tmp/cv-kanalpublicering"
git ls-files -z --cached --others --exclude-standard | grep -zvE '^(\.github/|bin/|docs/|tests/|CLAUDE\.md|\.gitignore)' \
  | xargs -0 -I{} cp --parents {} "$tmp/cv-kanalpublicering/"
rm -f "$out"
(cd "$tmp" && zip -qr "$out" cv-kanalpublicering)
rm -rf "$tmp"
echo "$out"
