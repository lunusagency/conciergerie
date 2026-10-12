#!/bin/bash
# Construit le thème depuis la maquette puis crée les deux archives à
# téléverser dans WordPress :
#   dist/homea.zip          thème   (Apparence → Thèmes → Ajouter → Téléverser)
#   dist/homea-contact.zip  extension (Extensions → Ajouter → Téléverser)
set -e
cd "$(dirname "$0")/.."
python3 scripts/assembler.py > /dev/null
python3 scripts/construire-theme.py > /dev/null
mkdir -p dist
rm -f dist/homea.zip dist/homea-contact.zip
(cd wordpress/themes && zip -rqX ../../dist/homea.zip homea -x 'homea/src/*' '*.DS_Store')
(cd wordpress/plugins && zip -rqX ../../dist/homea-contact.zip homea-contact -x '*.DS_Store')
ls -la dist
