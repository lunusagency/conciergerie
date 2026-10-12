<?php
/**
 * Title: Pied de page HOMËA
 * Slug: homea/pied
 * Categories: footer
 * Block Types: core/template-part/footer
 * Inserter: false
 *
 * @package homea
 */

echo homea_jetons( file_get_contents( get_theme_file_path( 'contenu/pied.html' ) ) ); // phpcs:ignore
