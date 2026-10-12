<?php
/**
 * Prise de rendez-vous téléphonique. Les jours et les heures sont chargés
 * par homea-contact.js depuis /wp-json/homea/v1/creneaux.
 *
 * @package homea-contact
 * @var string $api URL de base de l'API.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="homea-rdv" data-homea="rendez-vous" data-api="<?php echo $api; // phpcs:ignore -- déjà échappée ?>">
	<div class="homea-rdv-choix">
		<div class="homea-etape">
			<div class="homea-etape-tete">
				<p class="homea-etiquette"><span class="homea-num">1</span>Choisissez un jour</p>
				<p class="homea-defile"><button type="button" class="homea-fleche homea-prec" aria-label="Jours précédents"></button><button type="button" class="homea-fleche homea-suiv" aria-label="Jours suivants"></button></p>
			</div>
			<div class="homea-jours" role="group" aria-label="Jours disponibles"><p class="homea-info">Chargement des disponibilités…</p></div>
		</div>
		<div class="homea-etape">
			<div class="homea-etape-tete">
				<p class="homea-etiquette"><span class="homea-num">2</span>Choisissez une heure</p>
			</div>
			<div class="homea-heures" role="group" aria-label="Heures disponibles"><p class="homea-info">Sélectionnez d’abord un jour.</p></div>
		</div>
		<p class="homea-note">Échange d’environ <?php echo esc_html( (int) homea_reglages()['duree'] ); ?> minutes, à l’heure de Paris. Une confirmation vous est envoyée par e-mail.</p>
	</div>
	<form class="homea-form homea-rdv-form" novalidate>
		<div class="homea-etape-tete">
			<p class="homea-etiquette"><span class="homea-num">3</span>Vos coordonnées</p>
		</div>
		<p class="homea-recap" aria-live="polite"><span class="homea-recap-titre">Votre créneau</span><span class="homea-recap-valeur">À choisir dans le calendrier</span></p>
		<div class="homea-champs">
			<label class="homea-champ homea-champ-large"><span>Prénom et nom *</span><input type="text" name="nom" required autocomplete="name"></label>
			<label class="homea-champ"><span>Téléphone *</span><input type="tel" name="telephone" required autocomplete="tel" inputmode="tel"></label>
			<label class="homea-champ"><span>E-mail *</span><input type="email" name="email" required autocomplete="email"></label>
			<label class="homea-champ homea-champ-large"><span>Vous êtes</span><select name="profil"><?php foreach ( homea_profils() as $cle => $libelle ) : ?><option value="<?php echo esc_attr( $cle ); ?>"><?php echo esc_html( $libelle ); ?></option><?php endforeach; ?></select></label>
			<label class="homea-champ homea-champ-large"><span>Objet de l’appel</span><textarea name="message" rows="3"></textarea></label>
		</div>
		<p class="homea-piege" aria-hidden="true"><label>Ne pas remplir <input type="text" name="site" tabindex="-1" autocomplete="off"></label></p>
		<label class="homea-accord"><input type="checkbox" name="accord" required><span>J’accepte que ces informations soient utilisées pour organiser cet échange.</span></label>
		<p class="homea-envoi"><button class="homea-bouton" type="submit">Confirmer le créneau</button></p>
		<p class="homea-retour" role="status" aria-live="polite"></p>
	</form>
	<div class="homea-confirmation" hidden>
		<p class="homea-etiquette">C’est noté</p>
		<p class="homea-confirmation-titre">Votre rendez-vous est réservé.</p>
		<p class="homea-confirmation-texte"></p>
	</div>
</div>
