<?php
/**
 * Shortcodes [homea_rendez_vous] et [homea_contact].
 *
 * @package homea-contact
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Charge le style et le script des formulaires.
 */
function homea_charger_ressources() {
	wp_enqueue_style( 'homea-contact', HOMEA_CONTACT_URL . 'assets/homea-contact.css', array(), HOMEA_CONTACT_VERSION );
	wp_enqueue_script( 'homea-contact', HOMEA_CONTACT_URL . 'assets/homea-contact.js', array(), HOMEA_CONTACT_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
}

/**
 * Rend un gabarit du dossier templates/.
 *
 * @param string $nom Nom du gabarit.
 */
function homea_gabarit( $nom ) {
	$api = esc_url( rest_url( 'homea/v1/' ) );
	ob_start();
	include HOMEA_CONTACT_DIR . 'templates/' . $nom . '.php';
	return ob_get_clean();
}

add_shortcode(
	'homea_rendez_vous',
	function () {
		homea_charger_ressources();
		return homea_gabarit( 'rendez-vous' );
	}
);

add_shortcode(
	'homea_contact',
	function () {
		homea_charger_ressources();
		return homea_gabarit( 'contact' );
	}
);

// pas de wpautop parasite autour des formulaires
add_filter(
	'no_texturize_shortcodes',
	function ( $s ) {
		$s[] = 'homea_rendez_vous';
		$s[] = 'homea_contact';
		return $s;
	}
);
