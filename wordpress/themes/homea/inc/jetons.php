<?php
/**
 * Remplacement des jetons écrits par scripts/construire-theme.py dans les
 * contenus du thème : {{site}}, {{img:slug}}, {{id:slug}}, {{page:slug}}.
 *
 * @package homea
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remplace les jetons par l'adresse du site, les médias et les pages.
 *
 * @param string $balisage Contenu en blocs.
 */
function homea_jetons( $balisage ) {
	$medias   = (array) get_option( 'homea_medias', array() );
	$pages    = (array) get_option( 'homea_pages', array() );
	$balisage = str_replace( '{{site}}', untrailingslashit( home_url() ), $balisage );
	return preg_replace_callback(
		'/\{\{(img|id|page):([\w-]+)\}\}/',
		function ( $m ) use ( $medias, $pages ) {
			if ( 'page' === $m[1] ) {
				return (string) (int) ( $pages[ $m[2] ] ?? 0 );
			}
			$id = (int) ( $medias[ $m[2] ] ?? 0 );
			if ( 'id' === $m[1] ) {
				return (string) $id;
			}
			$url = $id ? wp_get_attachment_url( $id ) : '';
			if ( ! $url ) {
				foreach ( array( 'jpg', 'png' ) as $ext ) {
					if ( file_exists( get_theme_file_path( "assets/img/{$m[2]}.{$ext}" ) ) ) {
						$url = get_theme_file_uri( "assets/img/{$m[2]}.{$ext}" );
						break;
					}
				}
			}
			return esc_url( $url );
		},
		$balisage
	);
}
