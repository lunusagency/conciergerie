<?php
/**
 * Rend un gabarit de l'extension homea-contact hors de WordPress, pour la
 * maquette (scripts/assembler.py). Les fonctions WordPress utilisées par les
 * gabarits sont simulées au plus simple.
 */
define( 'ABSPATH', __DIR__ );
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); }
function homea_profils() {
	return array( 'proprietaire' => 'Propriétaire', 'voyageur' => 'Voyageur', 'autre' => 'Autre demande' );
}
function homea_reglages() { return array( 'duree' => 30 ); }
$api = '#demo';
$gabarit = basename( $argv[1] );
include dirname( __DIR__ ) . '/wordpress/plugins/homea-contact/templates/' . $gabarit . '.php';
