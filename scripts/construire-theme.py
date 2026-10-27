#!/usr/bin/env python3
"""Construit le thème bloc HOMËA à partir de la maquette validée.

  maquette/src/pages/*.html  ->  wordpress/themes/homea/contenu/<slug>.html  (blocs natifs)
  maquette/src/entete.html   ->  wordpress/themes/homea/contenu/entete.html
  maquette/src/pied.html     ->  wordpress/themes/homea/contenu/pied.html
  maquette/css/styles.css    ->  wordpress/themes/homea/style.css (+ adaptations Gutenberg)
  maquette/img/*             ->  wordpress/themes/homea/assets/img/
  maquette/fonts/*.woff2     ->  wordpress/themes/homea/assets/fonts/

Principe (méthode Claude to WordPress) : chaque élément devient un bloc natif
portant les classes CSS de la maquette (className). Aucun bloc core/html.
Les images et les liens internes sont écrits avec des jetons
({{img:slug}}, {{id:slug}}, {{site}}) que l'installateur du thème remplace par
les identifiants de la médiathèque et l'adresse réelle du site.

Usage : python3 scripts/construire-theme.py
"""
import html
import json
import os
import re
import shutil
from html.parser import HTMLParser

RACINE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
M = os.path.join(RACINE, 'maquette')
T = os.path.join(RACINE, 'wordpress', 'themes', 'homea')

PAGES = {
    'index.html': ('accueil', 'Accueil'),
    'services.html': ('nos-services', 'Nos services'),
    'proprietes.html': ('nos-proprietes', 'Nos propriétés'),
    'a-propos.html': ('a-propos', 'À propos'),
    'contact.html': ('contact', 'Contact'),
}
LIENS = {
    'index.html': '{{site}}/',
    'services.html': '{{site}}/nos-services/',
    'proprietes.html': '{{site}}/nos-proprietes/',
    'a-propos.html': '{{site}}/a-propos/',
    'contact.html': '{{site}}/contact/',
}
GABARITS = {'rendez-vous': '[homea_rendez_vous]', 'contact': '[homea_contact]'}
VIDES = {'img', 'br', 'hr', 'input', 'meta', 'link', 'source'}


# --------------------------------------------------------------------------
# Arbre HTML minimal
# --------------------------------------------------------------------------

class Noeud:
    def __init__(self, tag, attrs=None, parent=None):
        self.tag, self.attrs, self.parent, self.enfants = tag, dict(attrs or {}), parent, []

    @property
    def classes(self):
        return self.attrs.get('class', '').split()

    def elements(self):
        return [e for e in self.enfants if isinstance(e, Noeud)]


class Constructeur(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.racine = Noeud('racine')
        self.courant = self.racine

    def handle_starttag(self, tag, attrs):
        n = Noeud(tag, attrs, self.courant)
        self.courant.enfants.append(n)
        if tag not in VIDES:
            self.courant = n

    def handle_startendtag(self, tag, attrs):
        self.courant.enfants.append(Noeud(tag, attrs, self.courant))

    def handle_endtag(self, tag):
        n = self.courant
        while n is not self.racine and n.tag != tag:
            n = n.parent
        if n is not self.racine:
            self.courant = n.parent

    def handle_data(self, data):
        self.courant.enfants.append(data)


def analyser(texte):
    c = Constructeur()
    c.feed(texte)
    return c.racine


# --------------------------------------------------------------------------
# Sérialisation
# --------------------------------------------------------------------------

def lien(href):
    if href.startswith(('mailto:', 'tel:', 'http', '#', '{{')):
        return href
    page, _, ancre = href.partition('#')
    base = LIENS.get(page)
    if base is None:
        raise ValueError('lien inconnu : ' + href)
    return base + ('#' + ancre if ancre else '')


def image(src):
    m = re.match(r'img/([\w-]+)\.(jpg|png)$', src)
    if not m:
        raise ValueError('image inconnue : ' + src)
    return m.group(1)


def esc_texte(t):
    return t.replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;')


def esc_attr(t):
    return t.replace('&', '&amp;').replace('"', '&quot;')


def interieur(n):
    """HTML en ligne (RichText) d'un paragraphe, titre ou élément de liste."""
    out = []
    for e in n.enfants:
        if isinstance(e, str):
            out.append(esc_texte(e))
        elif e.tag == 'a':
            out.append('<a href="%s">%s</a>' % (esc_attr(lien(e.attrs['href'])), interieur(e)))
        elif e.tag in ('em', 'strong', 'br'):
            out.append('<br>' if e.tag == 'br' else '<%s>%s</%s>' % (e.tag, interieur(e), e.tag))
        else:
            raise ValueError('balise en ligne non prise en charge : ' + e.tag)
    return ''.join(out).strip()


def attrs_json(d):
    return (' ' + json.dumps(d, ensure_ascii=False, separators=(',', ':'))) if d else ''


def classe(n, *extra):
    return ' '.join(list(extra) + n.classes)


ALTS = {}


def bloc_image(img, classes, lien_href=None):
    slug = image(img.attrs['src'])
    ALTS.setdefault(slug, img.attrs.get('alt', ''))
    a = {'id': '{{id:%s}}' % slug, 'sizeSlug': 'full', 'linkDestination': 'custom' if lien_href else 'none'}
    if classes:
        a['className'] = ' '.join(classes)
    j = attrs_json(a).replace('"{{id:%s}}"' % slug, '{{id:%s}}' % slug)
    figure_cls = ' '.join(['wp-block-image', 'size-full'] + classes)
    balise = '<img src="{{img:%s}}" alt="%s" class="wp-image-{{id:%s}}"/>' % (slug, esc_attr(img.attrs.get('alt', '')), slug)
    if lien_href:
        balise = '<a href="%s">%s</a>' % (esc_attr(lien(lien_href)), balise)
    return '<!-- wp:image%s -->\n<figure class="%s">%s</figure>\n<!-- /wp:image -->' % (j, figure_cls, balise)


def convertir(n):
    """Convertit un élément de la maquette en bloc(s) natif(s)."""
    if isinstance(n, str):
        t = n.strip()
        if not t:
            return ''
        m = re.fullmatch(r'\[\[gabarit:([\w-]+)\]\]', t)
        if m:
            return '<!-- wp:shortcode -->\n%s\n<!-- /wp:shortcode -->' % GABARITS[m.group(1)]
        raise ValueError('texte hors bloc : ' + t[:60])

    tag, cls = n.tag, n.classes

    if tag == 'button' and 'burger' in cls:
        return ''  # core/navigation fournit son propre bouton de menu

    if tag in ('h1', 'h2', 'h3', 'h4'):
        niveau = int(tag[1])
        a = {}
        if niveau != 2:
            a['level'] = niveau
        if cls:
            a['className'] = ' '.join(cls)
        return '<!-- wp:heading%s -->\n<%s class="%s">%s</%s>\n<!-- /wp:heading -->' % (
            attrs_json(a), tag, classe(n, 'wp-block-heading'), interieur(n), tag)

    if tag == 'p':
        el = n.elements()
        # paragraphe ne contenant qu'un lien vers une image : logo -> core/image lié
        if len(el) == 1 and el[0].tag == 'a' and el[0].elements() and el[0].elements()[0].tag == 'img':
            return bloc_image(el[0].elements()[0], cls, el[0].attrs['href'])
        a = {'className': ' '.join(cls)} if cls else {}
        ouv = '<p class="%s">' % ' '.join(cls) if cls else '<p>'
        return '<!-- wp:paragraph%s -->\n%s%s</p>\n<!-- /wp:paragraph -->' % (attrs_json(a), ouv, interieur(n))

    if tag == 'figure':
        img = [e for e in n.elements() if e.tag == 'img'][0]
        return bloc_image(img, cls)

    if tag == 'ul':
        a = {'className': ' '.join(cls)} if cls else {}
        items = ''.join('<!-- wp:list-item -->\n<li>%s</li>\n<!-- /wp:list-item -->' % interieur(li) for li in n.elements())
        return '<!-- wp:list%s -->\n<ul class="%s">%s</ul>\n<!-- /wp:list -->' % (attrs_json(a), classe(n, 'wp-block-list'), items)

    if tag == 'blockquote':
        corps = ''.join(convertir(e) for e in n.elements() if e.tag == 'p')
        cite = [e for e in n.elements() if e.tag == 'cite']
        cite_html = '<cite>%s</cite>' % interieur(cite[0]) if cite else ''
        return '<!-- wp:quote -->\n<blockquote class="wp-block-quote">%s%s</blockquote>\n<!-- /wp:quote -->' % (corps, cite_html)

    if tag == 'nav' and 'menu' in cls:
        def nav_lien(a):
            page = PAGES[a.attrs['href'].partition('#')[0]][0]
            j = json.dumps({'label': html.unescape(interieur(a)), 'type': 'page', 'id': 'ID', 'url': lien(a.attrs['href']),
                            'kind': 'post-type', 'isTopLevelLink': True}, ensure_ascii=False, separators=(',', ':'))
            return '<!-- wp:navigation-link %s /-->' % j.replace('"ID"', '{{page:%s}}' % page)
        liens = ''.join(nav_lien(a) for a in n.elements() if a.tag == 'a')
        a = {'overlayMenu': 'mobile', 'className': ' '.join(cls), 'layout': {'type': 'flex', 'justifyContent': 'right'}}
        return '<!-- wp:navigation%s -->\n%s\n<!-- /wp:navigation -->' % (attrs_json(a), liens)

    if tag == 'div' and 'boutons' in cls:
        boutons = []
        for a in n.elements():
            ba = {'className': ' '.join(a.classes)}
            # ouverture dans un nouvel onglet : linkTarget et rel sont lus dans le HTML du bloc
            cible = ''
            if a.attrs.get('target'):
                cible = ' target="%s" rel="%s"' % (esc_attr(a.attrs['target']), esc_attr(a.attrs.get('rel', 'noreferrer noopener')))
            boutons.append('<!-- wp:button%s -->\n<div class="wp-block-button %s"><a class="wp-block-button__link wp-element-button" href="%s"%s>%s</a></div>\n<!-- /wp:button -->' % (
                attrs_json(ba), ' '.join(a.classes), esc_attr(lien(a.attrs['href'])), cible, interieur(a)))
        return '<!-- wp:buttons%s -->\n<div class="%s">%s</div>\n<!-- /wp:buttons -->' % (
            attrs_json({'className': ' '.join(cls)}), classe(n, 'wp-block-buttons'), ''.join(boutons))

    if tag == 'div' and 'aplat' in cls:
        return '<!-- wp:spacer%s -->\n<div style="height:180px" aria-hidden="true" class="%s"></div>\n<!-- /wp:spacer -->' % (
            attrs_json({'height': '180px', 'className': ' '.join(cls)}), classe(n, 'wp-block-spacer'))

    if tag in ('section', 'div', 'header', 'footer', 'main'):
        balise = 'section' if tag == 'section' else 'div'
        a = {}
        if 'id' in n.attrs:
            a['anchor'] = n.attrs['id']
        if balise != 'div':
            a['tagName'] = balise
        if cls:
            a['className'] = ' '.join(cls)
        a['layout'] = {'type': 'default'}
        ident = ' id="%s"' % n.attrs['id'] if 'id' in n.attrs else ''
        corps = '\n'.join(filter(None, (convertir(e) for e in n.enfants)))
        return '<!-- wp:group%s -->\n<%s%s class="%s">%s</%s>\n<!-- /wp:group -->' % (
            attrs_json(a), balise, ident, classe(n, 'wp-block-group'), '\n' + corps + '\n' if corps else '', balise)

    raise ValueError('élément non pris en charge : <%s class="%s">' % (tag, ' '.join(cls)))


def convertir_fragment(texte):
    racine = analyser(texte)
    return '\n\n'.join(filter(None, (convertir(e) for e in racine.enfants)))


# --------------------------------------------------------------------------
# Construction
# --------------------------------------------------------------------------

def ecrire(chemin, contenu):
    os.makedirs(os.path.dirname(chemin), exist_ok=True)
    with open(chemin, 'w', encoding='utf-8') as f:
        f.write(contenu)


def titre_section(n):
    sur = None
    def chercher(x):
        nonlocal sur
        for e in x.elements():
            if sur is None and e.tag == 'p' and 'surtitre' in e.classes:
                sur = html.unescape(interieur(e))
            chercher(e)
    chercher(n)
    return sur


def construire_contenus():
    pages = []
    sections = {}
    for fichier, (slug, titre) in PAGES.items():
        src = open(os.path.join(M, 'src', 'pages', fichier), encoding='utf-8').read()
        corps = re.search(r'<main>(.*)</main>', src, re.S).group(1)
        desc = re.search(r'<!--description: (.*?)-->', src).group(1)
        blocs = convertir_fragment(corps)
        ecrire(os.path.join(T, 'contenu', slug + '.html'), blocs + '\n')
        # une composition par type de section, réutilisable depuis l'éditeur
        for e in analyser(corps).elements():
            if e.tag == 'section':
                cle = '-'.join(c for c in e.classes if c not in ('ancre',))
                if cle not in sections:
                    sections[cle] = (titre_section(e) or cle, titre, convertir(e))
        pages.append({'slug': slug, 'titre': titre, 'description': html.unescape(desc)})
        print('contenu', slug, len(blocs), 'octets')
    ecrire(os.path.join(T, 'contenu', 'pages.json'), json.dumps(pages, ensure_ascii=False, indent=1) + '\n')
    # compositions (patterns) : un fichier PHP par type de section
    for f in os.listdir(os.path.join(T, 'patterns')):
        if f.startswith('section-'):
            os.remove(os.path.join(T, 'patterns', f))
    dossier_sections = os.path.join(T, 'contenu', 'sections')
    if os.path.isdir(dossier_sections):
        shutil.rmtree(dossier_sections)
    for cle, (libelle, page, blocs) in sections.items():
        ecrire(os.path.join(T, 'contenu', 'sections', cle + '.html'), blocs + '\n')
        nom = libelle[0].upper() + libelle[1:].lower()
        ecrire(os.path.join(T, 'patterns', 'section-' + cle + '.php'), '''<?php
/**
 * Title: %s (%s)
 * Slug: homea/section-%s
 * Categories: homea
 * Inserter: true
 *
 * Généré par scripts/construire-theme.py.
 *
 * @package homea
 */

echo homea_jetons( file_get_contents( get_theme_file_path( 'contenu/sections/%s.html' ) ) ); // phpcs:ignore
''' % (nom, page, cle, cle))
    print(len(sections), 'compositions')
    for nom in ('entete', 'pied'):
        src = open(os.path.join(M, 'src', nom + '.html'), encoding='utf-8').read()
        ecrire(os.path.join(T, 'contenu', nom + '.html'), convertir_fragment(src) + '\n')
        print('contenu', nom)
    # textes alternatifs des images, repris par l'installateur
    for f in os.listdir(os.path.join(M, 'img')):
        m = re.match(r'([\w-]+)\.(jpg|png)$', f)
        if m:
            ALTS.setdefault(m.group(1), '')
    ecrire(os.path.join(T, 'contenu', 'medias.json'), json.dumps(ALTS, ensure_ascii=False, indent=1, sort_keys=True) + '\n')


def construire_styles():
    css = open(os.path.join(M, 'css', 'styles.css'), encoding='utf-8').read()
    css = css.replace('url(../img/', 'url(assets/img/')
    tete = open(os.path.join(T, 'src', 'entete-theme.css'), encoding='utf-8').read()
    adapt = open(os.path.join(T, 'src', 'adaptations.css'), encoding='utf-8').read()
    ecrire(os.path.join(T, 'style.css'), tete.rstrip() + '\n\n' + css.rstrip() + '\n\n' + adapt)
    print('style.css')


def copier_ressources():
    for sous, motif in (('img', r'\.(jpg|png|svg)$'), ('fonts', r'\.woff2$')):
        dest = os.path.join(T, 'assets', sous)
        os.makedirs(dest, exist_ok=True)
        for f in os.listdir(os.path.join(M, sous)):
            if re.search(motif, f):
                shutil.copy(os.path.join(M, sous, f), os.path.join(dest, f))
    print('ressources copiées')


if __name__ == '__main__':
    construire_contenus()
    construire_styles()
    copier_ressources()
