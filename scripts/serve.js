#!/usr/bin/env node
/**
 * Serveur statique sans dépendance, pour la phase 2.
 *
 * Usage :  cd /tmp && nohup node /chemin/serve.js > /tmp/serve.log 2>&1 &
 *
 * Le "cd /tmp" n'est pas décoratif : sous macOS, un processus dont le
 * REPERTOIRE COURANT est protégé par TCC échoue sur
 * "EPERM: operation not permitted, uv_cwd".
 *
 * En revanche RACINE peut pointer n'importe où, y compris dans ~/Desktop ou
 * ~/Documents : le serveur lit sans difficulté un dossier que son cwd ne
 * pourrait pas occuper. Il n'y a donc aucune raison de déplacer le projet
 * hors du dossier de travail de l'utilisateur.
 *
 * Adapter RACINE au dossier qui contient index.html.
 */
const http = require('http');
const fs = require('fs');
const path = require('path');

const RACINE = process.env.RACINE || path.join(__dirname, '..', 'site');
const PORT = Number(process.env.PORT || 4321);

const TYPES = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.webp': 'image/webp',
  '.woff2': 'font/woff2'
};

const RACINE_ABS = path.resolve(RACINE);

const serveur = http.createServer((req, res) => {
  // un pourcentage mal formé dans l'URL ne doit pas tuer le serveur
  let rel;
  try { rel = decodeURIComponent(req.url.split('?')[0]); }
  catch (e) { res.writeHead(400, { 'Content-Type': 'text/plain' }); return res.end('400'); }

  const fichier = path.join(RACINE_ABS, rel === '/' ? 'index.html' : rel);

  // ne jamais servir hors de la racine — le séparateur final évite
  // qu'un dossier frère au nom préfixé (site vs siteX) passe le contrôle
  if (fichier !== RACINE_ABS && !fichier.startsWith(RACINE_ABS + path.sep)) {
    res.writeHead(403, { 'Content-Type': 'text/plain' });
    return res.end('403');
  }

  fs.readFile(fichier, (err, data) => {
    if (err) {
      res.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
      return res.end('404 — ' + rel);
    }
    res.writeHead(200, {
      'Content-Type': TYPES[path.extname(fichier)] || 'application/octet-stream',
      'Cache-Control': 'no-store'
    });
    res.end(data);
  });
});

// Un port occupé (souvent l'ancien projet qui tourne encore) ne doit ni
// planter ni pousser à tuer le processus voisin : on essaie le suivant.
// Le port annoncé est lu sur le serveur lui-même, jamais déduit.
serveur.on('listening', () => {
  console.log('Maquette servie sur http://127.0.0.1:' + serveur.address().port + '  (racine : ' + RACINE + ')');
});
function ecouter(port, essaisRestants) {
  serveur.once('error', (err) => {
    if (err.code === 'EADDRINUSE' && essaisRestants > 0) {
      console.log('Port ' + port + ' occupé, essai sur ' + (port + 1));
      ecouter(port + 1, essaisRestants - 1);
    } else { throw err; }
  });
  serveur.listen(port, '127.0.0.1');
}
ecouter(PORT, 20);
