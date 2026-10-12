<?php
/**
 * Petites fonctions partagées : dates en français, lecture des réglages.
 *
 * @package homea-contact
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formate une date en français, indépendamment de la langue du site.
 * Exemple : « lundi 13 octobre 2026 à 10 h 00 ».
 *
 * @param DateTimeInterface $d      Date.
 * @param bool              $annee  Afficher l'année.
 * @param bool              $heure  Afficher l'heure.
 */
function homea_date_fr( DateTimeInterface $d, $annee = true, $heure = true ) {
	$jours = array( 1 => 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche' );
	$mois  = array( 1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre' );
	$texte = $jours[ (int) $d->format( 'N' ) ] . ' ' . $d->format( 'j' ) . ( '1' === $d->format( 'j' ) ? 'er' : '' ) . ' ' . $mois[ (int) $d->format( 'n' ) ];
	if ( $annee ) {
		$texte .= ' ' . $d->format( 'Y' );
	}
	if ( $heure ) {
		$texte .= ' à ' . $d->format( 'G' ) . ' h ' . $d->format( 'i' );
	}
	return $texte;
}

/**
 * Libellés des profils proposés dans les formulaires.
 */
function homea_profils() {
	return array(
		'proprietaire' => 'Propriétaire',
		'voyageur'     => 'Voyageur',
		'autre'        => 'Autre demande',
	);
}

/**
 * Limite le nombre d'envois par adresse IP (anti-abus simple).
 *
 * @param string $action Nom de l'action.
 * @param int    $max    Envois autorisés par fenêtre de 10 minutes.
 */
function homea_trop_d_envois( $action, $max = 8 ) {
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0';
	$cle = 'homea_' . $action . '_' . md5( $ip );
	$n   = (int) get_transient( $cle );
	if ( $n >= $max ) {
		return true;
	}
	set_transient( $cle, $n + 1, 10 * MINUTE_IN_SECONDS );
	return false;
}
