<?php
/**
 * Calcul des créneaux libres à partir des horaires, des fermetures et des
 * rendez-vous déjà pris.
 *
 * @package homea-contact
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Transforme « 09:00-12:30, 14h-18h » en plages en minutes depuis minuit.
 *
 * @param string $texte Saisie.
 * @return array<int, array{0:int,1:int}>
 */
function homea_plages( $texte ) {
	$plages = array();
	foreach ( preg_split( '/[,;\n]+/', (string) $texte ) as $morceau ) {
		if ( preg_match( '/(\d{1,2})\s*[:hH]?\s*(\d{2})?\s*[-–à>]+\s*(\d{1,2})\s*[:hH]?\s*(\d{2})?/u', $morceau, $m ) ) {
			$a = (int) $m[1] * 60 + (int) ( $m[2] ?? 0 );
			$b = (int) $m[3] * 60 + (int) ( $m[4] ?? 0 );
			if ( $b > $a && $b <= 24 * 60 ) {
				$plages[] = array( $a, $b );
			}
		}
	}
	usort(
		$plages,
		function ( $x, $y ) {
			return $x[0] - $y[0];
		}
	);
	return $plages;
}

/**
 * Lit une date « 24/12/2026 » ou « 2026-12-24 ».
 *
 * @param string $t Texte.
 * @return DateTimeImmutable|null
 */
function homea_lire_date( $t ) {
	$t  = trim( $t );
	$tz = wp_timezone();
	if ( preg_match( '#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$#', $t, $m ) ) {
		return DateTimeImmutable::createFromFormat( '!Y-n-j', "{$m[3]}-{$m[2]}-{$m[1]}", $tz ) ?: null;
	}
	if ( preg_match( '#^(\d{4})-(\d{1,2})-(\d{1,2})$#', $t, $m ) ) {
		return DateTimeImmutable::createFromFormat( '!Y-n-j', "{$m[1]}-{$m[2]}-{$m[3]}", $tz ) ?: null;
	}
	return null;
}

/**
 * Ensemble des jours fermés (clé Y-m-d).
 *
 * @param string $texte Une date ou une période « a > b » par ligne.
 */
function homea_jours_fermes( $texte ) {
	$fermes = array();
	foreach ( preg_split( '/\R/', (string) $texte ) as $ligne ) {
		$bornes = array_map( 'homea_lire_date', preg_split( '/\s*(?:>|→|au|-{2,})\s*/u', trim( $ligne ) ) );
		if ( ! $bornes || ! $bornes[0] ) {
			continue;
		}
		$debut = $bornes[0];
		$fin   = isset( $bornes[1] ) && $bornes[1] ? $bornes[1] : $debut;
		for ( $d = $debut, $n = 0; $d <= $fin && $n < 400; $d = $d->modify( '+1 day' ), $n++ ) {
			$fermes[ $d->format( 'Y-m-d' ) ] = true;
		}
	}
	return $fermes;
}

/**
 * Intervalles [début, fin] (timestamps) des rendez-vous à venir.
 */
function homea_intervalles_pris() {
	$ids = get_posts(
		array(
			'post_type'      => 'homea_rdv',
			'post_status'    => array( 'publish', 'private', 'future', 'draft', 'pending' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array(
				array(
					'key'     => '_homea_fin',
					'value'   => time(),
					'compare' => '>',
					'type'    => 'NUMERIC',
				),
			),
		)
	);
	$pris = array();
	foreach ( $ids as $id ) {
		$pris[] = array( (int) get_post_meta( $id, '_homea_debut', true ), (int) get_post_meta( $id, '_homea_fin', true ) );
	}
	return $pris;
}

/**
 * Jours et heures réservables.
 *
 * @return array<int, array{date:string, creneaux:string[]}>
 */
function homea_creneaux_disponibles() {
	$r      = homea_reglages();
	$tz     = wp_timezone();
	$duree  = (int) $r['duree'];
	$now    = new DateTimeImmutable( 'now', $tz );
	$limite = $now->getTimestamp() + (int) $r['delai'] * HOUR_IN_SECONDS;
	$fermes = homea_jours_fermes( $r['fermetures'] );
	$pris   = homea_intervalles_pris();
	$jours  = array();
	$minuit = $now->setTime( 0, 0 );

	for ( $i = 0; $i <= (int) $r['horizon']; $i++ ) {
		$jour = $minuit->modify( "+{$i} days" );
		$cle  = $jour->format( 'Y-m-d' );
		if ( isset( $fermes[ $cle ] ) ) {
			continue;
		}
		$liste = array();
		foreach ( homea_plages( $r['jours'][ (int) $jour->format( 'N' ) ] ?? '' ) as $plage ) {
			for ( $m = $plage[0]; $m + $duree <= $plage[1]; $m += $duree ) {
				$debut = $jour->setTime( intdiv( $m, 60 ), $m % 60 )->getTimestamp();
				$fin   = $debut + $duree * MINUTE_IN_SECONDS;
				if ( $debut < $limite ) {
					continue;
				}
				foreach ( $pris as $p ) {
					if ( $debut < $p[1] && $fin > $p[0] ) {
						continue 2;
					}
				}
				$liste[] = sprintf( '%02d:%02d', intdiv( $m, 60 ), $m % 60 );
			}
		}
		if ( $liste ) {
			$jours[] = array(
				'date'     => $cle,
				'creneaux' => $liste,
			);
		}
	}
	return $jours;
}

/**
 * Vérifie qu'un créneau précis est encore libre.
 *
 * @param string $date  Y-m-d.
 * @param string $heure H:i.
 */
function homea_creneau_libre( $date, $heure ) {
	foreach ( homea_creneaux_disponibles() as $j ) {
		if ( $j['date'] === $date ) {
			return in_array( $heure, $j['creneaux'], true );
		}
	}
	return false;
}
