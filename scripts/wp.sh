#!/bin/bash
# Appelle une "ability" Novamira en HTTP direct, sans installer le serveur MCP.
#
# Usage :  ./wp.sh <nom-de-l-ability> '<json des parametres>'
# Exemple : ./wp.sh hostinger-ai-assistant/wp-settings-get '{}'
#
# Renseigner les trois variables ci-dessous depuis le manifest du bundle .mcpb.
# Ne jamais committer ce fichier une fois rempli.

A="${WP_API_URL:-https://SITE/wp-json/mcp/novamira}"
U="${WP_API_USERNAME:-}"
P="${WP_API_PASSWORD:-}"
SID_FILE="$(dirname "$0")/.mcp-sid"

if [ -z "$U" ] || [ -z "$P" ]; then
  echo "Renseigner WP_API_URL, WP_API_USERNAME et WP_API_PASSWORD." >&2
  exit 1
fi

ouvrir_session() {
  H=$(curl -s -D - -o /dev/null --max-time 30 -u "$U:$P" -X POST "$A" \
    -H 'Content-Type: application/json' -H 'Accept: application/json, text/event-stream' \
    -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{
         "protocolVersion":"2024-11-05","capabilities":{},
         "clientInfo":{"name":"maquette-dabord","version":"1"}}}')
  SID=$(printf '%s' "$H" | grep -i '^mcp-session-id:' | tr -d '\r' | awk '{print $2}')
  [ -z "$SID" ] && { echo "Pas d'identifiant de session renvoyé." >&2; return 1; }
  printf '%s' "$SID" > "$SID_FILE"
  curl -s -o /dev/null --max-time 20 -u "$U:$P" -X POST "$A" \
    -H 'Content-Type: application/json' -H "Mcp-Session-Id: $SID" \
    -d '{"jsonrpc":"2.0","method":"notifications/initialized"}'
  echo "$SID"
}

[ -s "$SID_FILE" ] && SID=$(cat "$SID_FILE") || SID=$(ouvrir_session) || exit 1

appeler() {
  BODY=$(python3 -c "
import json,sys
print(json.dumps({'jsonrpc':'2.0','id':2,'method':'tools/call','params':{
  'name':'mcp-adapter-execute-ability',
  'arguments':{'ability_name':sys.argv[1],'parameters':json.loads(sys.argv[2])}}}))
" "$1" "$2")
  curl -s --max-time 180 -u "$U:$P" -X POST "$A" \
    -H 'Content-Type: application/json' -H 'Accept: application/json, text/event-stream' \
    -H "Mcp-Session-Id: $SID" -d "$BODY"
}

OUT=$(appeler "$1" "${2:-{\}}")

# une session expirée se rouvre toute seule
if printf '%s' "$OUT" | grep -q 'Missing Mcp-Session-Id\|Invalid session'; then
  SID=$(ouvrir_session) || exit 1
  OUT=$(appeler "$1" "${2:-{\}}")
fi

# la réponse peut arriver en text/event-stream : lire la ligne "data:"
printf '%s' "$OUT" | python3 -c "
import json,sys
raw = sys.stdin.read().strip()
for ligne in raw.splitlines():
    if ligne.startswith('data:'):
        raw = ligne[5:].strip()
try:
    d = json.loads(raw)
except Exception:
    print(raw[:900]); sys.exit()
if 'error' in d:
    print('ERREUR :', json.dumps(d['error'], ensure_ascii=False)[:700]); sys.exit(1)
for bloc in d.get('result', {}).get('content', []):
    t = bloc.get('text', '')
    try:
        print(json.dumps(json.loads(t), ensure_ascii=False, indent=1)[:5000])
    except Exception:
        print(t[:5000])
"
