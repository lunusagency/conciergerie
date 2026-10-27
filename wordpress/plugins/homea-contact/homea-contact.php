<?php
/**
 * Plugin Name:       HOMËA Contact & Rendez-vous
 * Description:       Formulaire de contact et réservation de créneaux d'appel selon les disponibilités, pour le site HOMËA Conciergerie. Shortcodes : [homea_rendez_vous] et [homea_contact].
 * Version:           1.1.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            HOMËA Conciergerie
 * License:           GPL-2.0-or-later
 * Text Domain:       homea-contact
 *
 * @package homea-contact
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HOMEA_CONTACT_VERSION', '1.1.0' );
define( 'HOMEA_CONTACT_DIR', plugin_dir_path( __FILE__ ) );
define( 'HOMEA_CONTACT_URL', plugin_dir_url( __FILE__ ) );

require_once HOMEA_CONTACT_DIR . 'inc/outils.php';
require_once HOMEA_CONTACT_DIR . 'inc/reglages.php';
require_once HOMEA_CONTACT_DIR . 'inc/types.php';
require_once HOMEA_CONTACT_DIR . 'inc/creneaux.php';
require_once HOMEA_CONTACT_DIR . 'inc/courriels.php';
require_once HOMEA_CONTACT_DIR . 'inc/rest.php';
require_once HOMEA_CONTACT_DIR . 'inc/shortcodes.php';

register_activation_hook(
	__FILE__,
	function () {
		if ( false === get_option( 'homea_contact_reglages' ) ) {
			add_option( 'homea_contact_reglages', homea_reglages_defaut() );
		}
	}
);
