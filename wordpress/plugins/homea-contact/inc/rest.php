<?php
/**
 * Points d'accès REST utilisés par les formulaires du site.
 *
 *   GET  /wp-json/homea/v1/creneaux     jours et heures libres
 *   POST /wp-json/homea/v1/rendez-vous  réserve un créneau
 *   POST /wp-json/homea/v1/message      envoie un message
 *
 * @package homea-contact
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'homea/v1',
			'/creneaux',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => function () {
					$r   = homea_reglages();
					$rep = rest_ensure_response(
						array(
							'duree' => (int) $r['duree'],
							'jours' => homea_creneaux_disponibles(),
						)
					);
					$rep->header( 'Cache-Control', 'no-store, max-age=0' );
					return $rep;
				},
			)
		);
		register_rest_route(
			'homea/v1',
			'/rendez-vous',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => 'homea_rest_rendez_vous',
			)
		);
		register_rest_route(
			'homea/v1',
			'/message',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true',
				'callback'            => 'homea_rest_message',
			)
		);
	}
);

/**
 * Contrôles communs : pot de miel, durée de saisie, débit.
 *
 * @param WP_REST_Request $req    Requête.
 * @param string          $action Nom de l'action.
 * @return true|WP_Error
 */
function homea_controles( WP_REST_Request $req, $action ) {
	if ( '' !== trim( (string) $req->get_param( 'site' ) ) ) {
		return new WP_Error( 'homea_refus', 'Envoi refusé.', array( 'status' => 400 ) );
	}
	if ( (int) $req->get_param( 'duree' ) < 2500 ) {
		return new WP_Error( 'homea_trop_rapide', 'Merci de vérifier vos informations avant l’envoi.', array( 'status' => 400 ) );
	}
	if ( ! rest_sanitize_boolean( $req->get_param( 'accord' ) ) ) {
		return new WP_Error( 'homea_accord', 'Merci de cocher la case d’accord pour que nous puissions vous répondre.', array( 'status' => 400 ) );
	}
	if ( homea_trop_d_envois( $action ) ) {
		return new WP_Error( 'homea_debit', 'Trop d’envois successifs. Merci de réessayer dans quelques minutes.', array( 'status' => 429 ) );
	}
	return true;
}

/**
 * Lit et nettoie les champs de contact.
 *
 * @param WP_REST_Request $req Requête.
 */
function homea_champs( WP_REST_Request $req ) {
	$profil = sanitize_key( (string) $req->get_param( 'profil' ) );
	return array(
		'nom'       => mb_substr( sanitize_text_field( (string) $req->get_param( 'nom' ) ), 0, 120 ),
		'email'     => sanitize_email( (string) $req->get_param( 'email' ) ),
		'telephone' => mb_substr( sanitize_text_field( (string) $req->get_param( 'telephone' ) ), 0, 40 ),
		'profil'    => array_key_exists( $profil, homea_profils() ) ? $profil : 'autre',
		'message'   => mb_substr( sanitize_textarea_field( (string) $req->get_param( 'message' ) ), 0, 4000 ),
	);
}

/**
 * Réserve un créneau.
 *
 * @param WP_REST_Request $req Requête.
 */
function homea_rest_rendez_vous( WP_REST_Request $req ) {
	$ok = homea_controles( $req, 'rdv' );
	if ( is_wp_error( $ok ) ) {
		return $ok;
	}
	$c     = homea_champs( $req );
	$date  = (string) $req->get_param( 'date' );
	$heure = (string) $req->get_param( 'heure' );
	if ( '' === $c['nom'] || ! is_email( $c['email'] ) || strlen( preg_replace( '/\D/', '', $c['telephone'] ) ) < 9 ) {
		return new WP_Error( 'homea_champs', 'Merci d’indiquer votre nom, un e-mail valide et un numéro de téléphone.', array( 'status' => 400 ) );
	}
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || ! preg_match( '/^\d{2}:\d{2}$/', $heure ) ) {
		return new WP_Error( 'homea_creneau', 'Merci de choisir un jour et une heure.', array( 'status' => 400 ) );
	}

	// verrou : deux visiteurs ne peuvent pas prendre le même créneau en même temps
	$verrou = 'homea_verrou_' . str_replace( array( '-', ':' ), '', $date . $heure );
	if ( ! add_option( $verrou, time(), '', false ) ) {
		$age = time() - (int) get_option( $verrou );
		if ( $age < 30 ) {
			return new WP_Error( 'homea_pris', 'Ce créneau vient d’être réservé. Merci d’en choisir un autre.', array( 'status' => 409 ) );
		}
		update_option( $verrou, time(), false );
	}
	try {
		if ( ! homea_creneau_libre( $date, $heure ) ) {
			return new WP_Error( 'homea_pris', 'Ce créneau n’est plus disponible. Merci d’en choisir un autre.', array( 'status' => 409 ) );
		}
		$r     = homea_reglages();
		$debut = ( new DateTimeImmutable( $date . ' ' . $heure, wp_timezone() ) )->getTimestamp();
		$fin   = $debut + (int) $r['duree'] * MINUTE_IN_SECONDS;
		$quand = ( new DateTimeImmutable( '@' . $debut ) )->setTimezone( wp_timezone() );
		$id    = wp_insert_post(
			array(
				'post_type'    => 'homea_rdv',
				'post_status'  => 'publish',
				'post_title'   => $c['nom'],
				'post_content' => $c['message'],
				'meta_input'   => array(
					'_homea_debut'     => $debut,
					'_homea_fin'       => $fin,
					'_homea_telephone' => $c['telephone'],
					'_homea_email'     => $c['email'],
					'_homea_profil'    => $c['profil'],
				),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return new WP_Error( 'homea_enregistrement', 'Une erreur est survenue. Merci de réessayer ou de nous écrire.', array( 'status' => 500 ) );
		}
	} finally {
		delete_option( $verrou );
	}

	homea_courriels_rendez_vous( array_merge( $c, array( 'debut' => $debut, 'fin' => $fin ) ) );

	return rest_ensure_response(
		array(
			'ok'      => true,
			'quand'   => homea_date_fr( $quand, false ),
			'message' => 'Votre rendez-vous est réservé pour le ' . homea_date_fr( $quand, false ) . '. Une confirmation vient de vous être envoyée par e-mail.',
		)
	);
}

/**
 * Enregistre un message.
 *
 * @param WP_REST_Request $req Requête.
 */
function homea_rest_message( WP_REST_Request $req ) {
	$ok = homea_controles( $req, 'message' );
	if ( is_wp_error( $ok ) ) {
		return $ok;
	}
	$c = homea_champs( $req );
	if ( '' === $c['nom'] || ! is_email( $c['email'] ) || mb_strlen( trim( $c['message'] ) ) < 5 ) {
		return new WP_Error( 'homea_champs', 'Merci d’indiquer votre nom, un e-mail valide et votre message.', array( 'status' => 400 ) );
	}
	$id = wp_insert_post(
		array(
			'post_type'    => 'homea_message',
			'post_status'  => 'publish',
			'post_title'   => $c['nom'],
			'post_content' => $c['message'],
			'meta_input'   => array(
				'_homea_telephone' => $c['telephone'],
				'_homea_email'     => $c['email'],
				'_homea_profil'    => $c['profil'],
			),
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return new WP_Error( 'homea_enregistrement', 'Une erreur est survenue. Merci de réessayer.', array( 'status' => 500 ) );
	}
	homea_courriels_message( $c );
	return rest_ensure_response(
		array(
			'ok'      => true,
			'message' => 'Merci, votre message est bien envoyé. Nous vous répondons personnellement dans les meilleurs délais.',
		)
	);
}
