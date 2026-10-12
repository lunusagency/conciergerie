#!/usr/bin/env python3
"""Assemble les pages de la maquette à partir d'une ossature commune.

En-tête et pied de page viennent d'une source unique (maquette/src), ce qui
garantit qu'ils sont strictement identiques sur toutes les pages. Les
gabarits PHP de l'extension (formulaires) sont rendus ici avec php-cli, pour
que la maquette affiche exactement le balisage que WordPress servira.

Usage : python3 scripts/assembler.py
"""
import os, re, shutil, subprocess, sys

RACINE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
M = os.path.join(RACINE, 'maquette')
EXT = os.path.join(RACINE, 'wordpress', 'plugins', 'homea-contact')

def lire(p):
    with open(p, encoding='utf-8') as f:
        return f.read()

def rendre_php(gabarit):
    """Rend un gabarit de l'extension hors WordPress (fonctions simulées)."""
    script = os.path.join(RACINE, 'scripts', 'rendu-hors-wp.php')
    return subprocess.run(['php', script, gabarit], capture_output=True, text=True, check=True).stdout

def main():
    entete = lire(os.path.join(M, 'src', 'entete.html'))
    pied = lire(os.path.join(M, 'src', 'pied.html'))
    # ressources partagées avec l'extension WordPress
    if os.path.isdir(EXT):
        shutil.copy(os.path.join(EXT, 'assets', 'homea-contact.css'), os.path.join(M, 'css', 'homea-contact.css'))
        shutil.copy(os.path.join(EXT, 'assets', 'homea-contact.js'), os.path.join(M, 'js', 'homea-contact.js'))
    pages = sorted(os.listdir(os.path.join(M, 'src', 'pages')))
    for nom in pages:
        src = lire(os.path.join(M, 'src', 'pages', nom))
        titre = re.search(r'<!--titre: (.*?)-->', src).group(1)
        desc = re.search(r'<!--description: (.*?)-->', src).group(1)
        corps = re.search(r'(<main.*</main>)', src, re.S).group(1)
        # [[gabarit:xxx]] -> rendu PHP du gabarit de l'extension
        corps = re.sub(r'\[\[gabarit:([\w-]+)\]\]', lambda m: rendre_php(m.group(1)), corps)
        a_formulaire = 'homea-' in corps and 'data-homea' in corps
        nav = re.sub(r'<nav.*?</nav>', lambda m: m.group(0).replace(f'href="{nom}"', f'href="{nom}" class="actif" aria-current="page"', 1), entete, flags=re.S)
        html = f'''<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{titre}</title>
<meta name="description" content="{desc}">
<link rel="icon" href="img/icone-homea.png">
<link rel="stylesheet" href="fonts/fonts.css">
<link rel="stylesheet" href="css/styles.css">
{'<link rel="stylesheet" href="css/homea-contact.css">' if a_formulaire else ''}
</head>
<body>

{nav}
{corps}

{pied}
<script src="js/app.js"></script>
{'<script>window.HomeaContact = { demo: true };</script>' + chr(10) + '<script src="js/homea-contact.js"></script>' if a_formulaire else ''}
</body>
</html>
'''
        html = re.sub(r'\n{3,}', '\n\n', html)
        with open(os.path.join(M, nom), 'w', encoding='utf-8') as f:
            f.write(html)
        print('page', nom)

if __name__ == '__main__':
    main()
