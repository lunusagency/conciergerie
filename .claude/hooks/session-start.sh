#!/bin/bash
# Prépare HyperFrames (montages vidéo en HTML) dans les sessions Claude Code cloud :
# la CLI et le Chrome de rendu. Les skills sont versionnés dans .claude/skills.
set -euo pipefail

if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
  exit 0
fi

# Garder la même version que les skills de .claude/skills (mettre les deux à jour ensemble).
HYPERFRAMES_VERSION="0.8.186"

if [ "$(hyperframes --version 2>/dev/null || true)" != "$HYPERFRAMES_VERSION" ]; then
  npm install -g "hyperframes@$HYPERFRAMES_VERSION" --no-fund --no-audit >&2
fi

# Chrome Headless Shell utilisé pour le rendu (téléchargé une seule fois, puis en cache).
hyperframes browser ensure >&2

# Les skills du dépôt font foi : ne pas en réinstaller une copie globale à chaque « init ».
if [ -n "${CLAUDE_ENV_FILE:-}" ]; then
  echo 'export HYPERFRAMES_SKIP_SKILLS=1' >> "$CLAUDE_ENV_FILE"
fi
