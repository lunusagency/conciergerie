<?php
/**
 * Fonctions du thème HOMËA.
 *
 * @package homea
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once get_theme_file_path( 'inc/jetons.php' );
require_once get_theme_file_path( 'inc/installation.php' );

add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'editor-styles' );
		add_theme_support( 'html5', array( 'style', 'script' ) );
		// editor.css corrige ce que le canevas de Gutenberg modifie (position
		// relative forcée sur chaque bloc) : l'éditeur reste fidèle au site.
		add_editor_style( array( 'style.css', 'assets/editor.css' ) );
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		$version = wp_get_theme()->get( 'Version' );
		wp_enqueue_style( 'homea', get_stylesheet_uri(), array(), $version );
		wp_enqueue_script( 'homea', get_theme_file_uri( 'assets/app.js' ), array(), $version, array( 'strategy' => 'defer', 'in_footer' => true ) );
	}
);

add_action(
	'init',
	function () {
		register_block_pattern_category( 'homea', array( 'label' => 'HOMËA' ) );
	}
);

// Description de chaque page pour les moteurs de recherche, sauf si une
// extension de référencement s'en charge déjà.
add_action(
	'wp_head',
	function () {
		if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || ! is_singular( 'page' ) ) {
			return;
		}
		$desc = get_post_meta( get_queried_object_id(), '_homea_description', true );
		if ( $desc ) {
			echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
		}
	},
	1
);

// Pas de script d'émojis : inutile ici et coûteux.
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
