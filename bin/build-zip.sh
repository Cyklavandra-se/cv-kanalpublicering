#!/usr/bin/env bash
# Bygger cv-kanalpublicering.zip på samma sätt som releasen (.github/workflows/release.yml):
# git archive av senaste commit, med mappen cv-kanalpublicering/ i roten. Det som är
# markerat export-ignore i .gitattributes (tester, docs m.m.) följer inte med.
# Obs: bara committade ändringar kommer med.
#
# Vanligtvis behövs skriptet inte: höj Version i cv-kanalpublicering.php och pusha till
# main, så skapar GitHub Actions releasen och WordPress erbjuder uppdateringen.
set -euo pipefail
cd "$(dirname "$0")/.."
out="${1:-$PWD/cv-kanalpublicering.zip}"
rm -f "$out"
git archive --format=zip --prefix=cv-kanalpublicering/ -o "$out" HEAD
echo "$out"
