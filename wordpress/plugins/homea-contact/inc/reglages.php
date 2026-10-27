<?php
/**
 * Page « Disponibilités » : horaires d'appel, durée des créneaux, fermetures.
 *
 * @package homea-contact
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HOMEA_MENU', 'edit.php?post_type=homea_rdv' );

/**
 * Réglages par défaut.
 */
function homea_reglages_defaut() {
	return array(
		'jours'       => array(
			1 => '09:00-12:30, 14:00-18:30',
			2 => '09:00-12:30, 14:00-18:30',
			3 => '09:00-12:30, 14:00-18:30',
			4 => '09:00-12:30, 14:00-18:30',
			5 => '09:00-12:30, 14:00-18:00',
			6 => '09:30-12:30',
			7 => '',
		),
		'duree'       => 30,
		'horizon'     => 21,
		'delai'       => 3,
		'fermetures'  => '',
		'destinataire' => 'contact.homeaconciergerie@gmail.com',
		// adresse d'envoi : un domaine dont la messagerie est configurée (SPF,
		// DKIM), sinon Gmail et les autres messageries rejettent les e-mails
		'expediteur'   => 'contact@homea-conciergerie.fr',
	);
}

/**
 * Réglages courants, complétés par les valeurs par défaut.
 */
function homea_reglages() {
	$r = get_option( 'homea_contact_reglages', array() );
	$d = homea_reglages_defaut();
	$r = wp_parse_args( is_array( $r ) ? $r : array(), $d );
	// array_replace et non wp_parse_args : les clés des jours (1 à 7) sont
	// numériques et doivent être conservées telles quelles
	$r['jours'] = array_replace( $d['jours'], array_intersect_key( is_array( $r['jours'] ) ? $r['jours'] : array(), $d['jours'] ) );
	if ( ! is_email( $r['destinataire'] ) ) {
		$r['destinataire'] = get_option( 'admin_email' );
	}
	return $r;
}

add_action(
	'admin_menu',
	function () {
		// le menu « HOMËA » ouvre directement la liste des rendez-vous
		add_menu_page( 'HOMËA', 'HOMËA', 'edit_posts', HOMEA_MENU, '', 'dashicons-calendar-alt', 26 );
	},
	9
);

add_action(
	'admin_menu',
	function () {
		// après les listes Rendez-vous et Messages, et sans doublon « HOMËA »
		add_submenu_page( HOMEA_MENU, 'Disponibilités', 'Disponibilités', 'edit_pages', 'homea-disponibilites', 'homea_page_reglages' );
		remove_submenu_page( HOMEA_MENU, HOMEA_MENU );
	},
	20
);

add_action(
	'admin_init',
	function () {
		register_setting(
			'homea_contact',
			'homea_contact_reglages',
			array(
				'type'              => 'array',
				'sanitize_callback' => 'homea_nettoyer_reglages',
			)
		);
	}
);

/**
 * Normalise une saisie de plages horaires : « 9h-12h30, 14:00-18:00 ».
 *
 * @param string $texte Saisie.
 */
function homea_normaliser_plages( $texte ) {
	$propres = array();
	foreach ( homea_plages( $texte ) as $p ) {
		$propres[] = sprintf( '%02d:%02d-%02d:%02d', intdiv( $p[0], 60 ), $p[0] % 60, intdiv( $p[1], 60 ), $p[1] % 60 );
	}
	return implode( ', ', $propres );
}

/**
 * Nettoie les réglages envoyés par le formulaire.
 *
 * @param array $v Valeurs brutes.
 */
function homea_nettoyer_reglages( $v ) {
	$d   = homea_reglages_defaut();
	$out = array( 'jours' => array() );
	for ( $j = 1; $j <= 7; $j++ ) {
		$out['jours'][ $j ] = homea_normaliser_plages( isset( $v['jours'][ $j ] ) ? sanitize_text_field( $v['jours'][ $j ] ) : '' );
	}
	$out['duree']        = in_array( (int) ( $v['duree'] ?? 0 ), array( 15, 20, 30, 45, 60 ), true ) ? (int) $v['duree'] : $d['duree'];
	$out['horizon']      = max( 1, min( 90, (int) ( $v['horizon'] ?? $d['horizon'] ) ) );
	$out['delai']        = max( 0, min( 168, (int) ( $v['delai'] ?? $d['delai'] ) ) );
	$out['fermetures']   = sanitize_textarea_field( $v['fermetures'] ?? '' );
	$out['destinataire'] = is_email( $v['destinataire'] ?? '' ) ? sanitize_email( $v['destinataire'] ) : $d['destinataire'];
	$out['expediteur']   = is_email( $v['expediteur'] ?? '' ) ? sanitize_email( $v['expediteur'] ) : $d['expediteur'];
	return $out;
}

/**
 * Affiche la page des disponibilités.
 */
function homea_page_reglages() {
	$r     = homea_reglages();
	$noms  = array( 1 => 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche' );
	$champ = 'homea_contact_reglages';
	?>
	<div class="wrap">
		<h1>Disponibilités pour les rendez-vous téléphoniques</h1>
		<p>Les visiteurs du site choisissent un créneau parmi ces horaires. Un créneau déjà réservé disparaît automatiquement ; mettre un rendez-vous à la corbeille le libère.</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'homea_contact' ); ?>
			<h2>Horaires de la semaine</h2>
			<p class="description">Une ou plusieurs plages par jour, séparées par une virgule. Exemple : <code>09:00-12:30, 14:00-18:30</code>. Laisser vide pour un jour sans rendez-vous.</p>
			<table class="form-table" role="presentation">
				<?php foreach ( $noms as $n => $nom ) : ?>
					<tr>
						<th scope="row"><label for="homea-jour-<?php echo esc_attr( $n ); ?>"><?php echo esc_html( $nom ); ?></label></th>
						<td><input type="text" class="regular-text" id="homea-jour-<?php echo esc_attr( $n ); ?>" name="<?php echo esc_attr( $champ ); ?>[jours][<?php echo esc_attr( $n ); ?>]" value="<?php echo esc_attr( $r['jours'][ $n ] ); ?>" placeholder="Pas de rendez-vous"></td>
					</tr>
				<?php endforeach; ?>
			</table>
			<h2>Créneaux</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="homea-duree">Durée d'un créneau</label></th>
					<td><select id="homea-duree" name="<?php echo esc_attr( $champ ); ?>[duree]">
						<?php foreach ( array( 15, 20, 30, 45, 60 ) as $m ) : ?>
							<option value="<?php echo esc_attr( $m ); ?>" <?php selected( $r['duree'], $m ); ?>><?php echo esc_html( $m ); ?> minutes</option>
						<?php endforeach; ?>
					</select></td>
				</tr>
				<tr>
					<th scope="row"><label for="homea-horizon">Réservable jusqu'à</label></th>
					<td><input type="number" min="1" max="90" id="homea-horizon" name="<?php echo esc_attr( $champ ); ?>[horizon]" value="<?php echo esc_attr( $r['horizon'] ); ?>" class="small-text"> jours à l'avance</td>
				</tr>
				<tr>
					<th scope="row"><label for="homea-delai">Délai minimum</label></th>
					<td><input type="number" min="0" max="168" id="homea-delai" name="<?php echo esc_attr( $champ ); ?>[delai]" value="<?php echo esc_attr( $r['delai'] ); ?>" class="small-text"> heures entre la réservation et l'appel</td>
				</tr>
			</table>
			<h2>Fermetures et congés</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="homea-fermetures">Jours sans rendez-vous</label></th>
					<td>
						<textarea id="homea-fermetures" name="<?php echo esc_attr( $champ ); ?>[fermetures]" rows="6" cols="40" class="large-text code" placeholder="24/12/2026&#10;01/08/2026 > 21/08/2026"><?php echo esc_textarea( $r['fermetures'] ); ?></textarea>
						<p class="description">Une date par ligne (<code>24/12/2026</code>) ou une période (<code>01/08/2026 &gt; 21/08/2026</code>).</p>
					</td>
				</tr>
			</table>
			<h2>Notifications</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="homea-destinataire">Adresse qui reçoit les demandes</label></th>
					<td><input type="email" class="regular-text" id="homea-destinataire" name="<?php echo esc_attr( $champ ); ?>[destinataire]" value="<?php echo esc_attr( $r['destinataire'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="homea-expediteur">Adresse d'envoi des e-mails</label></th>
					<td><input type="email" class="regular-text" id="homea-expediteur" name="<?php echo esc_attr( $champ ); ?>[expediteur]" value="<?php echo esc_attr( $r['expediteur'] ); ?>">
					<p class="description">Doit appartenir à un domaine dont la messagerie est configurée chez l'hébergeur (ici homea-conciergerie.fr), sinon les e-mails risquent d'être rejetés.</p></td>
				</tr>
			</table>
			<?php submit_button( 'Enregistrer les disponibilités' ); ?>
		</form>
		<h2>Aperçu des prochains créneaux libres</h2>
		<?php
		$jours = array_slice( homea_creneaux_disponibles(), 0, 5 );
		if ( ! $jours ) {
			echo '<p>Aucun créneau libre avec ces réglages.</p>';
		}
		foreach ( $jours as $j ) {
			$d = new DateTimeImmutable( $j['date'], wp_timezone() );
			echo '<p><strong>' . esc_html( ucfirst( homea_date_fr( $d, false, false ) ) ) . '</strong> : ' . esc_html( implode( ' · ', $j['creneaux'] ) ) . '</p>';
		}
		?>
	</div>
	<?php
}
