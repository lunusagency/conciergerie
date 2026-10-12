#!/bin/bash
# Reconstruit la maquette et le thème, puis les copie dans le WordPress local
# de test (/home/user/wp-local) et réinstalle le contenu.
set -e
cd "$(dirname "$0")/.."
python3 scripts/assembler.py > /dev/null
python3 scripts/construire-theme.py > /dev/null
W=${WP_LOCAL:-/home/user/wp-local}
rm -rf "$W/wordpress/wp-content/themes/homea" "$W/wordpress/wp-content/plugins/homea-contact"
cp -r wordpress/themes/homea "$W/wordpress/wp-content/themes/"
cp -r wordpress/plugins/homea-contact "$W/wordpress/wp-content/plugins/"
"$W/wp" eval 'homea_installer(); echo "ok\n";'
