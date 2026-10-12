<?php
/**
 * Courriels envoyés à HOMËA et aux visiteurs.
 *
 * @package homea-contact
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Envoie un courriel texte, expéditeur affiché « HOMËA Conciergerie ».
 *
 * @param string   $a        Destinataire.
 * @param string   $sujet    Sujet.
 * @param string   $texte    Corps.
 * @param string   $repondre Adresse de réponse.
 * @param string[] $pj       Pièces jointes.
 */
function homea_envoyer( $a, $sujet, $texte, $repondre = '', $pj = array() ) {
	$nom = function () {
		return 'HOMËA Conciergerie';
	};
	add_filter( 'wp_mail_from_name', $nom );
	$entetes = array( 'Content-Type: text/plain; charset=UTF-8' );
	if ( $repondre && is_email( $repondre ) ) {
		$entetes[] = 'Reply-To: ' . $repondre;
	}
	$ok = wp_mail( $a, $sujet, $texte, $entetes, $pj );
	remove_filter( 'wp_mail_from_name', $nom );
	return $ok;
}

/**
 * Fichier .ics du rendez-vous, pour l'ajouter d'un clic à l'agenda.
 *
 * @param int    $debut Timestamp.
 * @param int    $fin   Timestamp.
 * @param string $titre Titre.
 * @param string $desc  Description.
 */
function homea_fichier_ics( $debut, $fin, $titre, $desc ) {
	$f   = function ( $ts ) {
		return gmdate( 'Ymd\THis\Z', $ts );
	};
	$esc = function ( $t ) {
		return str_replace( array( '\\', ';', ',', "\r\n", "\n" ), array( '\\\\', '\;', '\,', '\n', '\n' ), $t );
	};
	$ics  = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//HOMEA//Rendez-vous//FR\r\nMETHOD:PUBLISH\r\nBEGIN:VEVENT\r\n";
	$ics .= 'UID:' . wp_generate_uuid4() . "@homea\r\n";
	$ics .= 'DTSTAMP:' . $f( time() ) . "\r\nDTSTART:" . $f( $debut ) . "\r\nDTEND:" . $f( $fin ) . "\r\n";
	$ics .= 'SUMMARY:' . $esc( $titre ) . "\r\nDESCRIPTION:" . $esc( $desc ) . "\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
	$chemin = trailingslashit( get_temp_dir() ) . 'homea-rendez-vous-' . wp_generate_password( 8, false ) . '.ics';
	file_put_contents( $chemin, $ics ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	return $chemin;
}

/**
 * Notifie un nouveau rendez-vous.
 *
 * @param array $rdv Données du rendez-vous.
 */
function homea_courriels_rendez_vous( $rdv ) {
	$r       = homea_reglages();
	$profils = homea_profils();
	$d       = ( new DateTimeImmutable( '@' . $rdv['debut'] ) )->setTimezone( wp_timezone() );
	$quand   = homea_date_fr( $d );

	$texte  = "Nouveau rendez-vous téléphonique réservé depuis le site.\n\n";
	$texte .= 'Créneau : ' . $quand . ' (' . (int) $r['duree'] . " min)\n";
	$texte .= 'Nom : ' . $rdv['nom'] . "\n";
	$texte .= 'Téléphone : ' . $rdv['telephone'] . "\n";
	$texte .= 'E-mail : ' . $rdv['email'] . "\n";
	$texte .= 'Profil : ' . ( $profils[ $rdv['profil'] ] ?? '—' ) . "\n";
	$texte .= "\nObjet de l'appel :\n" . ( $rdv['message'] ? $rdv['message'] : '—' ) . "\n\n";
	$texte .= "Le fichier joint ajoute le rendez-vous à votre agenda.\n";
	$texte .= "Liste des rendez-vous : " . admin_url( HOMEA_MENU ) . "\n";

	$ics = homea_fichier_ics( $rdv['debut'], $rdv['fin'], 'Appel ' . $rdv['nom'] . ' — ' . $rdv['telephone'], $rdv['message'] );
	homea_envoyer( $r['destinataire'], 'Rendez-vous téléphonique — ' . ucfirst( $quand ) . ' — ' . $rdv['nom'], $texte, $rdv['email'], array( $ics ) );
	@unlink( $ics ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

	$prenom  = trim( strtok( $rdv['nom'], ' ' ) );
	$client  = 'Bonjour ' . $prenom . ",\n\n";
	$client .= 'Votre rendez-vous téléphonique avec HOMËA est bien réservé pour le ' . $quand . ".\n";
	$client .= 'Nous échangerons au ' . $rdv['telephone'] . ".\n\n";
	$client .= "Pour déplacer ou annuler ce rendez-vous, répondez simplement à ce message.\n\n";
	$client .= "À très bientôt,\nNina — HOMËA Conciergerie\n" . home_url( '/' ) . "\n";
	homea_envoyer( $rdv['email'], 'Votre rendez-vous avec HOMËA — ' . ucfirst( $quand ), $client, $r['destinataire'] );
}

/**
 * Notifie un nouveau message.
 *
 * @param array $msg Données du message.
 */
function homea_courriels_message( $msg ) {
	$r       = homea_reglages();
	$profils = homea_profils();
	$texte   = "Nouveau message envoyé depuis le site.\n\n";
	$texte  .= 'Nom : ' . $msg['nom'] . "\n";
	$texte  .= 'E-mail : ' . $msg['email'] . "\n";
	$texte  .= 'Téléphone : ' . ( $msg['telephone'] ? $msg['telephone'] : '—' ) . "\n";
	$texte  .= 'Profil : ' . ( $profils[ $msg['profil'] ] ?? '—' ) . "\n\n";
	$texte  .= "Message :\n" . $msg['message'] . "\n\n";
	$texte  .= 'Répondre à ce courriel écrit directement à ' . $msg['email'] . ".\n";
	homea_envoyer( $r['destinataire'], 'Message du site — ' . $msg['nom'], $texte, $msg['email'] );

	$prenom  = trim( strtok( $msg['nom'], ' ' ) );
	$client  = 'Bonjour ' . $prenom . ",\n\n";
	$client .= "Merci pour votre message : il est bien arrivé et nous vous répondons personnellement dans les meilleurs délais.\n\n";
	$client .= "À très bientôt,\nNina — HOMËA Conciergerie\n" . home_url( '/' ) . "\n";
	homea_envoyer( $msg['email'], 'Nous avons bien reçu votre message — HOMËA', $client, $r['destinataire'] );
}
