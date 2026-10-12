<?php
/**
 * Title: En-tête HOMËA
 * Slug: homea/entete
 * Categories: header
 * Block Types: core/template-part/header
 * Inserter: false
 *
 * @package homea
 */

echo homea_jetons( file_get_contents( get_theme_file_path( 'contenu/entete.html' ) ) ); // phpcs:ignore
