# HOMËA Conciergerie — site WordPress

Site vitrine de HOMËA, conciergerie en Loire-Atlantique. Le site est construit selon la méthode *Claude to WordPress* :

1. une maquette HTML locale, fidèle à la référence visuelle fournie ;
2. sa conversion en **thème bloc WordPress**, 100 % modifiable dans l'éditeur (blocs natifs, aucun bloc HTML brut) ;
3. une **extension** qui gère le formulaire de contact et la prise de rendez-vous téléphonique selon les disponibilités.

Le mot « gestion » n'apparaît nulle part sur le site, comme demandé.

## Pages

| Page | Adresse | Contenu |
|---|---|---|
| Accueil | `/` | Maquette de référence : héros avec diaporama (01 · 02 · 03), mission, trois piliers, territoire, Nina, pied de page marron |
| Nos services | `/nos-services/` | Accompagnement à l'année (6 services), prestations ponctuelles, voyageurs, méthode en 4 étapes |
| Nos propriétés | `/nos-proprietes/` | La Villa Nantaise, avec les textes fournis par le client : 5 photos, description, caractéristiques, équipements, accueil HOMËA, Nantes, boutons « Réserver sur Airbnb » |
| À propos | `/a-propos/` | La création de la marque : naissance, nom et emblème, palette et matières, valeurs, positionnement |
| Contact | `/contact/` | Prise de rendez-vous téléphonique (créneaux libres) et formulaire de contact |

## Livrables

| Fichier | Rôle |
|---|---|
| `dist/homea.zip` | Thème à téléverser : **Apparence → Thèmes → Ajouter → Téléverser** |
| `dist/homea-contact.zip` | Extension à téléverser : **Extensions → Ajouter → Téléverser** |
| `maquette/` | Maquette HTML validée (source du design), 5 pages |
| `wordpress/themes/homea/` | Sources du thème |
| `wordpress/plugins/homea-contact/` | Sources de l'extension |
| `scripts/` | Construction, empaquetage, déploiement |

## Mise en ligne

### Méthode simple (depuis wp-admin, 5 minutes)

1. **Extensions → Ajouter → Téléverser** `dist/homea-contact.zip`, puis activer.
2. **Apparence → Thèmes → Ajouter → Téléverser** `dist/homea.zip`, puis activer.
3. **Apparence → Installation HOMËA → Installer le contenu**.

L'installation importe les 15 visuels dans la médiathèque, crée les 5 pages, définit l'accueil comme page d'accueil et règle le fuseau horaire sur Paris (les créneaux en dépendent). Le contenu existant est sauvegardé au préalable dans `wp-content/uploads/homea-sauvegarde-*.json`. L'ancien thème reste installé : revenir en arrière tient en un clic.

### Méthode automatique (Novamira)

Avec l'extension Novamira active sur le site et son bundle de connexion `.mcpb` :

```bash
./scripts/deployer-novamira.sh chemin/vers/novamira-homea-conciergerie.com.mcpb
```

La machine qui lance le script doit pouvoir joindre `homea-conciergerie.com`.

Ne jamais committer ces accès.

## Rendez-vous téléphoniques

Le visiteur choisit un jour, puis une heure libre, puis laisse ses coordonnées. Le créneau disparaît aussitôt pour les autres visiteurs.

- **Réglages** : wp-admin → **HOMËA → Disponibilités** : horaires de chaque jour (plusieurs plages possibles), durée d'un créneau (30 min par défaut), délai minimum, horizon de réservation, jours de fermeture ou congés, adresse qui reçoit les demandes, adresse d'envoi des e-mails.
- **Horaires par défaut** : du lundi au jeudi 9 h–12 h 30 et 14 h–18 h 30, le vendredi jusqu'à 18 h, le samedi 9 h 30–12 h 30, fermé le dimanche. À ajuster selon les disponibilités réelles de Nina.
- **À chaque réservation** : un e-mail à `contact.homeaconciergerie@gmail.com`, avec le rendez-vous en pièce jointe (.ics) pour l'ajouter à l'agenda d'un clic ; une confirmation au client.
- **Suivi** : HOMËA → Rendez-vous (et un encart « Prochains rendez-vous » sur le tableau de bord). Mettre un rendez-vous à la corbeille libère le créneau.
- **Messages** du formulaire de contact : HOMËA → Messages, et par e-mail.
- **Anti-spam** : champ piège invisible, délai minimum de saisie, accord obligatoire, limite d'envois par adresse IP.

> L'envoi des e-mails passe par la messagerie de l'hébergeur (`wp_mail`), avec `contact@homea-conciergerie.fr` comme expéditeur : ce domaine a ses enregistrements SPF et DKIM chez Hostinger, contrairement à `homea-conciergerie.com`, qui n'a pas de messagerie. Si les e-mails n'arrivent toujours pas, installer une extension SMTP (par exemple WP Mail SMTP) avec la boîte `contact@homea-conciergerie.fr`.

## Direction artistique

**Relevé de la référence** (capture fournie) et vérification sur le site livré :

| Invariant | Référence | Site | Verdict |
|---|---|---|---|
| Classe typographique | Serif pour titres et textes, sans-serif en capitales espacées pour surtitres, menu et boutons, aucune italique | Newsreader + Jost, 0 italique dans les titres | conforme |
| Système de couleur | Bruns sur beige, aucun accent saturé | Marron profond `#5B4231`, ivoire, aucun accent | conforme |
| Fond clair | Beige poudré chaud | `#F1E7DE` | conforme |
| Découpe | Bandes bord à bord, photos à fond perdu | Toutes les sections en pleine largeur | conforme |
| Géométrie | Angles droits, pictogrammes cerclés | Rayon 0, aucune rotation | conforme |
| Alignement | Titres à gauche, grilles asymétriques | 0 titre centré, grilles 38/62 et 51/49 | conforme |
| Densité | Peu de grands éléments, beaucoup d'air | Corps 18 px, titre principal 80 px | conforme |

**Palette** (brief, palette bis) : marron profond `#5B4231`, marron cognac `#9E7852`, beige sable `#E7D4BD`, lin beige `#DECBB6`, ivoire poudré `#F5EDE1`, et le fond beige poudré `#F1E7DE` relevé sur la référence.

**Typographies** : Newsreader (titres et textes) et Jost (surtitres, menu, boutons), hébergées avec le thème : aucun appel à Google Fonts, donc pas de souci RGPD. Le brief proposait Instrument Serif pour les titres, mais la maquette de référence montre une serif plus large ; Newsreader en est bien plus proche. Revenir à Instrument Serif ne demande qu'une ligne dans `theme.json`.

**Ajouts par rapport à la référence** : le pied de page marron (demandé), la section « Nina, à vos côtés » et le module de rendez-vous (demandés).

## Recette (WordPress 7.1 local)

| Porte | Contrôle | Résultat |
|---|---|---|
| 1 | Blocs côté serveur | 0 `core/html`, 0 bloc hors `core/` sur les 5 pages, l'en-tête et le pied |
| 2 | Validité dans l'éditeur | 0 bloc invalide, 18 images sur 18 liées à la médiathèque |
| 3 | Rendu | Images en HTTP 200, polices appliquées, diaporama et menu mobile OK déconnecté, aucun défilement horizontal à 390 px |
| 4 | Fidélité au pixel | Maquette et site identiques (hauteur de page et ±2 px par composant) sur les 5 pages en 1440 px |
| 5 | Fidélité de l'éditeur | Canevas pleine largeur, couches photo en place, boutons normaux, polices du site |

Rendez-vous testés : réservation, double réservation refusée (409), dimanche et heures hors plages refusés, fermetures exclues, pot de miel, envoi trop rapide, accord manquant, limite d'envois, e-mails et pièce jointe .ics.

## À faire valider par Nina

Comme l'indique le brief (section 07), ces éléments sont rédigés au plus juste mais doivent être confirmés :

- **Prestations** : liste des services à l'année et ponctuels (page Nos services).
- **Propriété** : la Villa Nantaise reprend les textes fournis par le client et 5 photos de son annonce Airbnb, légèrement réchauffées. La piscine est chauffée, pas à l'eau salée.
- **Photos** : les visuels viennent de la maquette de référence et des mockups de la marque (agrandis et retouchés : la baseline affiche « CONCIERGERIE » seule, comme retenu au brief). Les remplacer par de vraies photos se fait en un clic dans l'éditeur.
- **Photo du port de Pornic** (accueil) : Kev22, licence CC BY-SA 4.0 (Wikimedia Commons), légèrement réchauffée ; le crédit est affiché en bas du pied de page, il doit y rester tant que la photo est utilisée.
- **À propos** : le texte sur la naissance de la marque et la lecture du monogramme (deux arches, l'étoile de l'accueil).
- **Horaires de rendez-vous** par défaut.
- **Mentions légales** : obligatoires en France (raison sociale, SIRET, hébergeur, directeur de publication). Informations non fournies, page non créée.
- Numéro de téléphone, réseaux sociaux et avis Google : non fournis, donc non affichés.

## Modifier le design

Le design vit dans la maquette. Pour une évolution de mise en page :

```bash
python3 scripts/assembler.py       # reconstruit maquette/*.html depuis maquette/src
node scripts/serve.js              # (RACINE=maquette) aperçu local
python3 scripts/construire-theme.py  # régénère le thème (blocs, style.css, compositions)
./scripts/empaqueter.sh            # régénère dist/*.zip
```

Le texte et les images, eux, se modifient directement dans WordPress. Les sections de chaque page sont aussi proposées comme **compositions** (catégorie « HOMËA » de l'insertion de blocs), pour en ajouter de nouvelles.
