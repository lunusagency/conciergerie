<?php
/**
 * Installation du contenu HOMËA : médias, pages, page d'accueil, réglages.
 *
 * Deux façons de lancer l'installation :
 *  - wp-admin → Apparence → Installation HOMËA → « Installer le contenu » ;
 *  - en PHP / WP-CLI : homea_installer().
 *
 * L'installation est rejouable : les médias déjà importés sont réutilisés,
 * les pages HOMËA sont mises à jour. Les contenus existants sont d'abord
 * sauvegardés dans wp-content/uploads/homea-sauvegarde-*.json.
 *
 * @package homea
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sauvegarde les pages et articles existants avant toute écriture.
 */
function homea_sauvegarder() {
	global $wpdb;
	$lignes  = $wpdb->get_results( "SELECT ID, post_name, post_title, post_status, post_type, post_content FROM {$wpdb->posts} WHERE post_type IN ('page','post','wp_template','wp_template_part','wp_navigation') AND post_status NOT IN ('auto-draft','inherit')", ARRAY_A ); // phpcs:ignore
	$dossier = wp_upload_dir();
	$chemin  = trailingslashit( $dossier['basedir'] ) . 'homea-sauvegarde-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 6, false ) . '.json';
	file_put_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions
		$chemin,
		wp_json_encode(
			array(
				'date'          => current_time( 'mysql' ),
				'theme_avant'   => get_option( 'stylesheet' ),
				'show_on_front' => get_option( 'show_on_front' ),
				'page_on_front' => get_option( 'page_on_front' ),
				'blogname'      => get_option( 'blogname' ),
				'contenus'      => $lignes,
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
		)
	);
	return $chemin;
}

/**
 * Importe les visuels du thème dans la médiathèque (une seule fois chacun).
 *
 * @return array<string,int> slug => identifiant.
 */
function homea_importer_medias() {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$alts   = json_decode( (string) file_get_contents( get_theme_file_path( 'contenu/medias.json' ) ), true ); // phpcs:ignore
	$medias = (array) get_option( 'homea_medias', array() );
	$fichiers = array_merge( (array) glob( get_theme_file_path( 'assets/img/' ) . '*.jpg' ), (array) glob( get_theme_file_path( 'assets/img/' ) . '*.png' ) );
	foreach ( $fichiers as $fichier ) {
		$slug = pathinfo( $fichier, PATHINFO_FILENAME );
		if ( ! empty( $medias[ $slug ] ) && get_post( $medias[ $slug ] ) ) {
			continue;
		}
		$existant = get_posts(
			array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'meta_key'    => '_homea_slug',
				'meta_value'  => $slug,
				'fields'      => 'ids',
				'numberposts' => 1,
			)
		);
		if ( $existant ) {
			$medias[ $slug ] = (int) $existant[0];
			continue;
		}
		$tmp = wp_tempnam( $slug );
		copy( $fichier, $tmp );
		$alt = isset( $alts[ $slug ] ) ? $alts[ $slug ] : '';
		$id  = media_handle_sideload(
			array(
				'name'     => basename( $fichier ),
				'tmp_name' => $tmp,
			),
			0,
			$alt ? $alt : null
		);
		if ( is_wp_error( $id ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			continue;
		}
		update_post_meta( $id, '_homea_slug', $slug );
		update_post_meta( $id, '_wp_attachment_image_alt', $alt );
		$medias[ $slug ] = (int) $id;
	}
	update_option( 'homea_medias', $medias, false );
	return $medias;
}

/**
 * Crée ou met à jour les cinq pages du site.
 *
 * @return array<string,int> slug => identifiant.
 */
function homea_creer_pages() {
	$liste = json_decode( (string) file_get_contents( get_theme_file_path( 'contenu/pages.json' ) ), true ); // phpcs:ignore
	$pages = (array) get_option( 'homea_pages', array() );

	// 1) s'assurer que toutes les pages existent (les liens internes en ont besoin)
	foreach ( $liste as $p ) {
		$page = get_page_by_path( $p['slug'] );
		if ( ! $page ) {
			$id = wp_insert_post(
				array(
					'post_type'   => 'page',
					'post_status' => 'publish',
					'post_title'  => $p['titre'],
					'post_name'   => $p['slug'],
				)
			);
		} else {
			$id = $page->ID;
		}
		$pages[ $p['slug'] ] = (int) $id;
	}
	update_option( 'homea_pages', $pages, false );

	// 2) écrire les contenus, jetons remplacés (sans filtrage kses : le
	//    contenu vient du thème, pas d'un visiteur)
	kses_remove_filters();
	foreach ( $liste as $i => $p ) {
		$contenu = homea_jetons( (string) file_get_contents( get_theme_file_path( 'contenu/' . $p['slug'] . '.html' ) ) ); // phpcs:ignore
		wp_update_post(
			wp_slash(
				array(
					'ID'           => $pages[ $p['slug'] ],
					'post_title'   => $p['titre'],
					'post_status'  => 'publish',
					'post_content' => $contenu,
					'menu_order'   => $i,
				)
			)
		);
		update_post_meta( $pages[ $p['slug'] ], '_homea_page', 1 );
		update_post_meta( $pages[ $p['slug'] ], '_homea_description', $p['description'] );
	}
	kses_init();
	return $pages;
}

/**
 * Installation complète.
 *
 * @return array Rapport.
 */
function homea_installer() {
	$rapport               = array();
	$rapport['sauvegarde'] = homea_sauvegarder();
	$medias                = homea_importer_medias();
	$rapport['medias']     = count( $medias );
	$pages                 = homea_creer_pages();
	$rapport['pages']      = $pages;

	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $pages['accueil'] );
	update_option( 'blogname', 'HOMËA Conciergerie' );
	update_option( 'blogdescription', 'L’art de prendre soin des lieux et de ceux qui les habitent.' );
	if ( ! empty( $medias['icone-homea'] ) ) {
		update_option( 'site_icon', $medias['icone-homea'] );
	}
	// les créneaux d'appel sont calculés à l'heure de Paris
	if ( ! get_option( 'timezone_string' ) ) {
		update_option( 'timezone_string', 'Europe/Paris' );
	}
	if ( ! get_option( 'permalink_structure' ) ) {
		update_option( 'permalink_structure', '/%postname%/' );
	}
	flush_rewrite_rules();
	if ( function_exists( 'wp_cache_flush' ) ) {
		wp_cache_flush();
	}
	if ( ! defined( 'HOMEA_CONTACT_VERSION' ) ) {
		$rapport['attention'] = 'L’extension « HOMËA Contact & Rendez-vous » n’est pas active : le formulaire et la prise de rendez-vous ne s’afficheront pas.';
	}
	update_option( 'homea_installe', current_time( 'mysql' ), false );
	return $rapport;
}

/* --------------------------------------------------------------------------
   Écran d'administration
   -------------------------------------------------------------------------- */

add_action(
	'admin_menu',
	function () {
		add_theme_page( 'Installation HOMËA', 'Installation HOMËA', 'manage_options', 'homea-installation', 'homea_ecran_installation' );
	}
);

add_action(
	'admin_notices',
	function () {
		if ( get_option( 'homea_installe' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="notice notice-info"><p><strong>Thème HOMËA activé.</strong> Dernière étape : <a href="' . esc_url( admin_url( 'themes.php?page=homea-installation' ) ) . '">installer les pages et les photos du site</a>.</p></div>';
	}
);

/**
 * Écran « Installation HOMËA ».
 */
function homea_ecran_installation() {
	$rapport = null;
	if ( isset( $_POST['homea_installer'] ) && check_admin_referer( 'homea_installer' ) && current_user_can( 'manage_options' ) ) {
		$rapport = homea_installer();
	}
	$deja = get_option( 'homea_installe' );
	?>
	<div class="wrap">
		<h1>Installation du site HOMËA</h1>
		<?php if ( $rapport ) : ?>
			<div class="notice notice-success"><p>Installation terminée : <?php echo esc_html( $rapport['medias'] ); ?> visuels dans la médiathèque, <?php echo esc_html( count( $rapport['pages'] ) ); ?> pages publiées. <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Voir le site</a></p></div>
			<?php if ( ! empty( $rapport['attention'] ) ) : ?>
				<div class="notice notice-warning"><p><?php echo esc_html( $rapport['attention'] ); ?></p></div>
			<?php endif; ?>
			<p>Sauvegarde du contenu précédent : <code><?php echo esc_html( str_replace( ABSPATH, '', $rapport['sauvegarde'] ) ); ?></code></p>
		<?php endif; ?>
		<p>Ce bouton importe les photos du thème dans la médiathèque et crée les pages <strong>Accueil, Nos services, Nos propriétés, À propos et Contact</strong>, puis définit l’accueil comme page d’accueil du site.</p>
		<p>Si ces pages existent déjà, leur contenu est remplacé par celui du thème. Le contenu actuel est sauvegardé au préalable dans le dossier <code>wp-content/uploads</code>.</p>
		<?php if ( $deja ) : ?>
			<p><em>Dernière installation : <?php echo esc_html( $deja ); ?>.</em> Relancer l’installation remet les pages dans leur version d’origine.</p>
		<?php endif; ?>
		<form method="post">
			<?php wp_nonce_field( 'homea_installer' ); ?>
			<?php submit_button( $deja ? 'Réinstaller le contenu d’origine' : 'Installer le contenu', $deja ? 'secondary' : 'primary', 'homea_installer' ); ?>
		</form>
	</div>
	<?php
}
