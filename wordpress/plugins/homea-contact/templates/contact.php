<?php
/**
 * Formulaire de contact.
 *
 * @package homea-contact
 * @var string $api URL de base de l'API.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<form class="homea-form homea-contact" data-homea="contact" data-api="<?php echo $api; // phpcs:ignore -- déjà échappée ?>" novalidate>
	<div class="homea-champs">
		<label class="homea-champ"><span>Prénom et nom *</span><input type="text" name="nom" required autocomplete="name"></label>
		<label class="homea-champ"><span>E-mail *</span><input type="email" name="email" required autocomplete="email"></label>
		<label class="homea-champ"><span>Téléphone</span><input type="tel" name="telephone" autocomplete="tel" inputmode="tel"></label>
		<label class="homea-champ"><span>Vous êtes</span><select name="profil"><?php foreach ( homea_profils() as $cle => $libelle ) : ?><option value="<?php echo esc_attr( $cle ); ?>"><?php echo esc_html( $libelle ); ?></option><?php endforeach; ?></select></label>
		<label class="homea-champ homea-champ-large"><span>Votre message *</span><textarea name="message" rows="5" required></textarea></label>
	</div>
	<p class="homea-piege" aria-hidden="true"><label>Ne pas remplir <input type="text" name="site" tabindex="-1" autocomplete="off"></label></p>
	<label class="homea-accord"><input type="checkbox" name="accord" required><span>J’accepte que ces informations soient utilisées pour répondre à ma demande.</span></label>
	<p class="homea-envoi"><button class="homea-bouton" type="submit">Envoyer le message</button></p>
	<p class="homea-retour" role="status" aria-live="polite"></p>
</form>
