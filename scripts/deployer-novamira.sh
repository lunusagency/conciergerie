#!/bin/bash
# Déploie le thème et l'extension sur le site WordPress via Novamira
# (méthode Claude to WordPress, phase 5), puis installe le contenu.
#
# Prérequis : l'extension Novamira active sur le site, et ses accès :
#   export WP_API_URL=https://homea-conciergerie.fr/wp-json/mcp/novamira
#   export WP_API_USERNAME=...   WP_API_PASSWORD=...   (mot de passe d'application)
# Ne jamais committer ces accès.
set -e
cd "$(dirname "$0")/.."
./scripts/empaqueter.sh > /dev/null
W=./scripts/wp.sh

champ() { python3 -c "
import json,re,sys
t=sys.stdin.read()
try:
    d=json.loads(t)
except Exception:
    m=re.search(r'\"%s\"\s*:\s*\"([^\"]+)\"' % sys.argv[1], t); print(m.group(1) if m else ''); sys.exit()
def cherche(x):
    if isinstance(x, dict):
        for k, v in x.items():
            if k == sys.argv[1]: return v
            r = cherche(v)
            if r: return r
    if isinstance(x, list):
        for v in x:
            r = cherche(v)
            if r: return r
print(cherche(d) or '')
" "$1"; }

for z in homea homea-contact; do
  echo "Téléversement de $z.zip…"
  R=$($W novamira/create-upload-link "{\"path\":\"wp-content/uploads/$z.zip\",\"overwrite\":true}")
  URL=$(printf '%s' "$R" | champ upload_url)
  JETON=$(printf '%s' "$R" | champ upload_token)
  [ -z "$URL" ] && { echo "Lien de téléversement introuvable : $R"; exit 1; }
  curl -sS -X PUT -H "X-Novamira-Upload-Token: $JETON" --data-binary @"dist/$z.zip" "$URL" > /dev/null
done

echo "Décompression et activation…"
$W novamira/execute-php '{"code":"require_once ABSPATH . \"wp-admin/includes/file.php\"; require_once ABSPATH . \"wp-admin/includes/plugin.php\"; WP_Filesystem(); $up = wp_upload_dir()[\"basedir\"]; foreach (array(\"patterns\",\"templates\",\"parts\",\"contenu\",\"contenu/sections\") as $d) { foreach ((array) glob(get_theme_root() . \"/homea/\" . $d . \"/*\") as $f) { if (is_file($f)) { @unlink($f); } } } $t = unzip_file($up . \"/homea.zip\", get_theme_root()); $p = unzip_file($up . \"/homea-contact.zip\", WP_PLUGIN_DIR); wp_clean_themes_cache(); $a = activate_plugin(\"homea-contact/homea-contact.php\"); switch_theme(\"homea\"); return array(\"theme\" => is_wp_error($t) ? $t->get_error_message() : \"ok\", \"extension\" => is_wp_error($p) ? $p->get_error_message() : \"ok\", \"activation\" => is_wp_error($a) ? $a->get_error_message() : \"ok\", \"theme_actif\" => get_stylesheet());"}'

echo "Installation du contenu…"
$W novamira/execute-php '{"code":"$r = homea_installer(); @unlink(wp_upload_dir()[\"basedir\"] . \"/homea.zip\"); @unlink(wp_upload_dir()[\"basedir\"] . \"/homea-contact.zip\"); wp_clean_themes_cache(true); if (class_exists(\"WP_Theme_JSON_Resolver\")) { WP_Theme_JSON_Resolver::clean_cached_data(); } do_action(\"litespeed_purge_all\"); return $r;"}'
echo "Terminé. Vérifier le site avec ?frais=$(date +%s)"
