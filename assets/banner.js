/**
 * HKWebProduction Banner - gestion du bouton de fermeture.
 *
 * La fermeture est mémorisée dans le localStorage du visiteur.
 * La clé de stockage dépend du contenu du message (attribut
 * data-hkwp-banner-key) : si l'administrateur modifie le message,
 * la clé change et la bannière réapparaît automatiquement.
 *
 * Script autonome, sans dépendance (pas de jQuery).
 */
( function () {
	'use strict';

	// Préfixe des clés de stockage, propre au plugin.
	var STORAGE_PREFIX = 'hkwpBannerClosed:';

	/**
	 * Exécute une fonction dès que le DOM est prêt.
	 *
	 * @param {Function} fn Fonction à exécuter.
	 */
	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	/**
	 * Lecture sécurisée du localStorage (peut être indisponible : mode privé, etc.).
	 *
	 * @param {string} key Clé à lire.
	 * @return {?string} Valeur ou null.
	 */
	function safeGet( key ) {
		try {
			return window.localStorage.getItem( key );
		} catch ( e ) {
			return null;
		}
	}

	/**
	 * Écriture sécurisée dans le localStorage.
	 *
	 * @param {string} key   Clé à écrire.
	 * @param {string} value Valeur à stocker.
	 */
	function safeSet( key, value ) {
		try {
			window.localStorage.setItem( key, value );
		} catch ( e ) {
			/* localStorage indisponible : on ignore silencieusement. */
		}
	}

	/**
	 * Publie la hauteur de la bannière dans la variable CSS --hkwp-banner-h.
	 * Le thème s'en sert pour décaler le menu fixe et le contenu.
	 *
	 * @param {HTMLElement} banner Élément de la bannière.
	 */
	function updateHeightVar( banner ) {
		var height = banner.offsetHeight || 0;
		document.documentElement.style.setProperty( '--hkwp-banner-h', height + 'px' );
	}

	/**
	 * Remet la hauteur à zéro (bannière fermée ou masquée).
	 */
	function clearHeightVar() {
		document.documentElement.style.setProperty( '--hkwp-banner-h', '0px' );
	}

	ready( function () {
		var banner = document.getElementById( 'hkwp-banner' );
		if ( ! banner ) {
			return;
		}

		// Clé de stockage liée au contenu du message.
		var key        = banner.getAttribute( 'data-hkwp-banner-key' ) || 'default';
		var storageKey = STORAGE_PREFIX + key;

		// Si la bannière a déjà été fermée pour ce message, on la masque
		// et on laisse la hauteur à zéro (menu collé en haut).
		if ( '1' === safeGet( storageKey ) ) {
			banner.classList.add( 'hkwp-banner--hidden' );
			clearHeightVar();
			return;
		}

		// Publie la hauteur initiale et la recalcule au redimensionnement
		// (le message peut passer sur plusieurs lignes en mobile).
		updateHeightVar( banner );
		window.addEventListener( 'resize', function () {
			updateHeightVar( banner );
		} );

		// Le bouton de fermeture est optionnel (selon les réglages).
		var closeBtn = document.getElementById( 'hkwp-banner-close' );
		if ( closeBtn ) {
			closeBtn.addEventListener( 'click', function () {
				banner.classList.add( 'hkwp-banner--hidden' );
				clearHeightVar();
				safeSet( storageKey, '1' );
			} );
		}
	} );
} )();
