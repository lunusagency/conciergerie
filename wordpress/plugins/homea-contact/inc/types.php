<?php
/**
 * Types de contenu privés : rendez-vous et messages reçus.
 *
 * @package homea-contact
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'init',
	function () {
		$commun = array(
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => HOMEA_MENU,
			'show_in_rest'        => false,
			'exclude_from_search' => true,
			'supports'            => array( 'title' ),
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			// les entrées naissent uniquement depuis le site
			'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
		);
		register_post_type(
			'homea_rdv',
			array_merge(
				$commun,
				array(
					'labels' => array(
						'name'               => 'Rendez-vous',
						'singular_name'      => 'Rendez-vous',
						'menu_name'          => 'Rendez-vous',
						'all_items'          => 'Rendez-vous',
						'edit_item'          => 'Rendez-vous téléphonique',
						'search_items'       => 'Rechercher un rendez-vous',
						'not_found'          => 'Aucun rendez-vous pour le moment.',
						'not_found_in_trash' => 'Aucun rendez-vous dans la corbeille.',
					),
				)
			)
		);
		register_post_type(
			'homea_message',
			array_merge(
				$commun,
				array(
					'labels' => array(
						'name'               => 'Messages',
						'singular_name'      => 'Message',
						'menu_name'          => 'Messages',
						'all_items'          => 'Messages',
						'edit_item'          => 'Message reçu',
						'search_items'       => 'Rechercher un message',
						'not_found'          => 'Aucun message pour le moment.',
						'not_found_in_trash' => 'Aucun message dans la corbeille.',
					),
				)
			)
		);
	}
);

/* --------------------------------------------------------------------------
   Colonnes des listes
   -------------------------------------------------------------------------- */

add_filter(
	'manage_homea_rdv_posts_columns',
	function () {
		return array(
			'cb'           => '<input type="checkbox" />',
			'title'        => 'Contact',
			'homea_quand'  => 'Créneau',
			'homea_tel'    => 'Téléphone',
			'homea_email'  => 'E-mail',
			'homea_profil' => 'Profil',
			'date'         => 'Réservé le',
		);
	}
);

add_filter(
	'manage_homea_message_posts_columns',
	function () {
		return array(
			'cb'           => '<input type="checkbox" />',
			'title'        => 'Contact',
			'homea_email'  => 'E-mail',
			'homea_tel'    => 'Téléphone',
			'homea_profil' => 'Profil',
			'homea_extrait' => 'Message',
			'date'         => 'Reçu le',
		);
	}
);

/**
 * Contenu des colonnes personnalisées.
 *
 * @param string $col Colonne.
 * @param int    $id  Identifiant.
 */
function homea_colonne( $col, $id ) {
	$profils = homea_profils();
	switch ( $col ) {
		case 'homea_quand':
			$ts = (int) get_post_meta( $id, '_homea_debut', true );
			if ( $ts ) {
				$d = ( new DateTimeImmutable( '@' . $ts ) )->setTimezone( wp_timezone() );
				echo '<strong>' . esc_html( ucfirst( homea_date_fr( $d ) ) ) . '</strong>';
				if ( $ts < time() ) {
					echo '<br><span style="color:#8c8f94">passé</span>';
				}
			}
			break;
		case 'homea_tel':
			$t = get_post_meta( $id, '_homea_telephone', true );
			echo $t ? '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $t ) ) . '">' . esc_html( $t ) . '</a>' : '—';
			break;
		case 'homea_email':
			$e = get_post_meta( $id, '_homea_email', true );
			echo $e ? '<a href="mailto:' . esc_attr( $e ) . '">' . esc_html( $e ) . '</a>' : '—';
			break;
		case 'homea_profil':
			$p = get_post_meta( $id, '_homea_profil', true );
			echo esc_html( $profils[ $p ] ?? '—' );
			break;
		case 'homea_extrait':
			echo esc_html( wp_trim_words( get_post_field( 'post_content', $id ), 18 ) );
			break;
	}
}
add_action( 'manage_homea_rdv_posts_custom_column', 'homea_colonne', 10, 2 );
add_action( 'manage_homea_message_posts_custom_column', 'homea_colonne', 10, 2 );

// rendez-vous triés par créneau, le plus proche en premier
add_action(
	'pre_get_posts',
	function ( $q ) {
		if ( is_admin() && $q->is_main_query() && 'homea_rdv' === $q->get( 'post_type' ) && ! $q->get( 'orderby' ) ) {
			$q->set( 'meta_key', '_homea_debut' );
			$q->set( 'orderby', 'meta_value_num' );
			$q->set( 'order', 'DESC' );
		}
	}
);

/* --------------------------------------------------------------------------
   Fiche détaillée (lecture seule)
   -------------------------------------------------------------------------- */

add_action(
	'add_meta_boxes',
	function () {
		foreach ( array( 'homea_rdv', 'homea_message' ) as $type ) {
			add_meta_box( 'homea_details', 'Détails', 'homea_boite_details', $type, 'normal', 'high' );
		}
		remove_meta_box( 'slugdiv', array( 'homea_rdv', 'homea_message' ), 'normal' );
	}
);

/**
 * Boîte de détails.
 *
 * @param WP_Post $post Entrée.
 */
function homea_boite_details( $post ) {
	$lignes = array();
	if ( 'homea_rdv' === $post->post_type ) {
		ob_start();
		homea_colonne( 'homea_quand', $post->ID );
		$lignes['Créneau'] = ob_get_clean();
	}
	foreach ( array( 'homea_tel' => 'Téléphone', 'homea_email' => 'E-mail', 'homea_profil' => 'Profil' ) as $col => $libelle ) {
		ob_start();
		homea_colonne( $col, $post->ID );
		$lignes[ $libelle ] = ob_get_clean();
	}
	echo '<table class="form-table" role="presentation">';
	foreach ( $lignes as $libelle => $html ) {
		echo '<tr><th scope="row">' . esc_html( $libelle ) . '</th><td>' . wp_kses_post( $html ) . '</td></tr>';
	}
	echo '<tr><th scope="row">Message</th><td>' . ( $post->post_content ? wp_kses_post( wpautop( esc_html( $post->post_content ) ) ) : '—' ) . '</td></tr>';
	echo '</table>';
	if ( 'homea_rdv' === $post->post_type ) {
		echo '<p class="description">Pour annuler ce rendez-vous et libérer le créneau, mettez-le à la corbeille.</p>';
	}
}

/* --------------------------------------------------------------------------
   Tableau de bord : prochains rendez-vous
   -------------------------------------------------------------------------- */

add_action(
	'wp_dashboard_setup',
	function () {
		wp_add_dashboard_widget(
			'homea_prochains_rdv',
			'Prochains rendez-vous téléphoniques',
			function () {
				$rdv = get_posts(
					array(
						'post_type'      => 'homea_rdv',
						'posts_per_page' => 6,
						'meta_key'       => '_homea_debut',
						'orderby'        => 'meta_value_num',
						'order'          => 'ASC',
						'meta_query'     => array(
							array(
								'key'     => '_homea_debut',
								'value'   => time(),
								'compare' => '>=',
								'type'    => 'NUMERIC',
							),
						),
					)
				);
				if ( ! $rdv ) {
					echo '<p>Aucun rendez-vous à venir.</p>';
				}
				echo '<ul>';
				foreach ( $rdv as $p ) {
					$d = ( new DateTimeImmutable( '@' . (int) get_post_meta( $p->ID, '_homea_debut', true ) ) )->setTimezone( wp_timezone() );
					echo '<li><strong>' . esc_html( ucfirst( homea_date_fr( $d, false ) ) ) . '</strong> — <a href="' . esc_url( get_edit_post_link( $p->ID ) ) . '">' . esc_html( $p->post_title ) . '</a> · ' . esc_html( get_post_meta( $p->ID, '_homea_telephone', true ) ) . '</li>';
				}
				echo '</ul><p><a href="' . esc_url( admin_url( 'admin.php?page=homea-disponibilites' ) ) . '">Modifier mes disponibilités</a></p>';
			}
		);
	}
);
