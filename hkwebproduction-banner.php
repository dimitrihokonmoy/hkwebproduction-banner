<?php
/**
 * Plugin Name:       HKWebProduction Banner
 * Plugin URI:        https://hkwebproduction.com/
 * Description:       Affiche un message promotionnel en haut du site (juste après l'ouverture de la balise body). Le message, les couleurs et le bouton de fermeture se configurent depuis l'administration WordPress.
 * Version:           1.0.5
 * Requires at least: 6.0
 * Tested up to:      7.0
 * Requires PHP:      7.4
 * Author:            HKWebProduction
 * Author URI:        https://hkwebproduction.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       hkwebproduction-banner
 * Domain Path:       /languages
 *
 * @package HKWebProduction_Banner
 */

// Sécurité : empêche l'accès direct au fichier (aucune exécution hors WordPress).
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * -------------------------------------------------------------------------
 * Constantes du plugin
 *
 * Préfixe unique "HKWP_BANNER_" pour éviter tout conflit de nom avec le
 * core WordPress ou d'autres extensions.
 * -------------------------------------------------------------------------
 */

/** Version du plugin (sert aussi au versionnage du cache CSS/JS). */
define( 'HKWP_BANNER_VERSION', '1.0.5' );

/** Clé unique de l'option stockée en base (table wp_options). */
define( 'HKWP_BANNER_OPTION', 'hkwp_banner_settings' );

/** URL de base du plugin (pour charger les fichiers assets). */
define( 'HKWP_BANNER_URL', plugin_dir_url( __FILE__ ) );

/** Chemin disque de base du plugin. */
define( 'HKWP_BANNER_PATH', plugin_dir_path( __FILE__ ) );

/*
 * -------------------------------------------------------------------------
 * Réglages : valeurs par défaut et lecture
 * -------------------------------------------------------------------------
 */

/**
 * Retourne les réglages par défaut du plugin.
 *
 * Utilisé à l'activation et comme repli lorsqu'une valeur est absente.
 *
 * @since 1.0.0
 * @return array{enabled:int,message:string,bg_color:string,text_color:string,closable:int,start_date:string,end_date:string}
 */
function hkwp_banner_default_settings() {
	return array(
		'enabled'    => 0,          // Bannière désactivée par défaut.
		'message'    => '',         // Message vide par défaut.
		'bg_color'   => '#000000',  // Noir par défaut.
		'text_color' => '#ffffff',  // Texte blanc par défaut.
		'closable'   => 1,          // Bouton de fermeture actif par défaut.
		'start_date' => '',         // Date/heure de début (vide = pas de début imposé).
		'end_date'   => '',         // Date/heure de fin (vide = pas de fin imposée).
	);
}

/**
 * Récupère les réglages enregistrés, fusionnés avec les valeurs par défaut.
 *
 * Garantit que toutes les clés attendues existent toujours, même si
 * l'option en base est incomplète ou corrompue.
 *
 * @since 1.0.0
 * @return array Réglages complets.
 */
function hkwp_banner_get_settings() {
	$saved = get_option( HKWP_BANNER_OPTION, array() );

	// Repli défensif si l'option n'est pas un tableau (données corrompues).
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	return wp_parse_args( $saved, hkwp_banner_default_settings() );
}

/*
 * -------------------------------------------------------------------------
 * Activation
 * -------------------------------------------------------------------------
 */

/**
 * Callback d'activation du plugin.
 *
 * Crée l'option de réglages avec les valeurs par défaut uniquement si
 * elle n'existe pas encore (préserve les réglages en cas de réactivation).
 *
 * @since 1.0.0
 * @return void
 */
function hkwp_banner_activate() {
	if ( false === get_option( HKWP_BANNER_OPTION, false ) ) {
		add_option( HKWP_BANNER_OPTION, hkwp_banner_default_settings() );
	}
}
register_activation_hook( __FILE__, 'hkwp_banner_activate' );

/*
 * -------------------------------------------------------------------------
 * Chargement des traductions
 * -------------------------------------------------------------------------
 */

/**
 * Charge le domaine de traduction du plugin.
 *
 * Permet la traduction des chaînes via les fichiers .mo/.po du dossier
 * /languages.
 *
 * @since 1.0.0
 * @return void
 */
function hkwp_banner_load_textdomain() {
	load_plugin_textdomain(
		'hkwebproduction-banner',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
}
add_action( 'init', 'hkwp_banner_load_textdomain' );

/*
 * -------------------------------------------------------------------------
 * Programmation d'affichage (date de début / fin)
 * -------------------------------------------------------------------------
 */

/**
 * Normalise une valeur de champ datetime-local au format 'Y-m-d\TH:i'.
 *
 * Accepte le format avec ou sans secondes. Retourne une chaîne vide si la
 * valeur est absente ou invalide.
 *
 * @since 1.0.4
 * @param mixed $value Valeur brute (ex. "2026-07-14T15:30").
 * @return string Date normalisée "Y-m-d\TH:i" ou "".
 */
function hkwp_banner_normalize_datetime( $value ) {
	$value = is_string( $value ) ? trim( $value ) : '';
	if ( '' === $value ) {
		return '';
	}

	$tz = wp_timezone();

	// Tente le format sans secondes, puis avec secondes.
	foreach ( array( 'Y-m-d\TH:i', 'Y-m-d\TH:i:s' ) as $format ) {
		$dt = DateTime::createFromFormat( $format, $value, $tz );
		if ( $dt instanceof DateTime ) {
			return $dt->format( 'Y-m-d\TH:i' );
		}
	}

	return '';
}

/**
 * Indique si la bannière doit être affichée à l'instant présent,
 * selon les dates de début et de fin programmées.
 *
 * Les dates sont interprétées dans le fuseau horaire du site. Une date
 * vide signifie "pas de borne" (début ou fin non imposé).
 *
 * @since 1.0.4
 * @param array $settings Réglages du plugin.
 * @return bool True si la bannière est dans sa période d'affichage.
 */
function hkwp_banner_is_within_schedule( $settings ) {
	$tz  = wp_timezone();
	$now = new DateTime( 'now', $tz );

	// Date de début : si définie et pas encore atteinte, on n'affiche pas.
	$start = hkwp_banner_normalize_datetime( isset( $settings['start_date'] ) ? $settings['start_date'] : '' );
	if ( '' !== $start ) {
		$start_dt = DateTime::createFromFormat( 'Y-m-d\TH:i', $start, $tz );
		if ( $start_dt instanceof DateTime && $now < $start_dt ) {
			return false;
		}
	}

	// Date de fin : si définie et dépassée, on n'affiche plus.
	$end = hkwp_banner_normalize_datetime( isset( $settings['end_date'] ) ? $settings['end_date'] : '' );
	if ( '' !== $end ) {
		$end_dt = DateTime::createFromFormat( 'Y-m-d\TH:i', $end, $tz );
		if ( $end_dt instanceof DateTime && $now > $end_dt ) {
			return false;
		}
	}

	return true;
}

/*
 * -------------------------------------------------------------------------
 * Front-office : affichage de la bannière
 * -------------------------------------------------------------------------
 */

/**
 * Affiche la bannière juste après l'ouverture de la balise <body>.
 *
 * Accroché au hook `wp_body_open` (WordPress 5.2+), ce qui place la
 * bannière tout en haut du contenu de la page, avant l'en-tête du thème.
 *
 * @since 1.0.0
 * @return void
 */
function hkwp_banner_render_banner() {
	// Ne jamais afficher la bannière dans l'administration.
	if ( is_admin() ) {
		return;
	}

	$settings = hkwp_banner_get_settings();

	// Rien à faire si la bannière est désactivée.
	if ( empty( $settings['enabled'] ) ) {
		return;
	}

	// Rien à afficher si le message est vide.
	$message = trim( (string) $settings['message'] );
	if ( '' === $message ) {
		return;
	}

	// Programmation : hors de la période de début/fin, on n'affiche pas.
	if ( ! hkwp_banner_is_within_schedule( $settings ) ) {
		return;
	}

	$closable   = ! empty( $settings['closable'] );
	$bg_color   = sanitize_hex_color( $settings['bg_color'] );
	$text_color = sanitize_hex_color( $settings['text_color'] );

	// Construction de l'attribut style en ligne (avec repli sur les couleurs par défaut).
	$style = sprintf(
		'background-color:%s;color:%s;',
		esc_attr( $bg_color ? $bg_color : '#000000' ),
		esc_attr( $text_color ? $text_color : '#ffffff' )
	);

	// Clé liée au contenu du message. Si le message change, la clé change,
	// et la bannière réapparaît chez les visiteurs qui l'avaient fermée.
	$banner_key = substr( md5( $message ), 0, 12 );

	// role="region" + aria-label : repère de navigation pour l'accessibilité (RGAA/WCAG).
	?>
	<div id="hkwp-banner"
		class="hkwp-banner<?php echo $closable ? ' hkwp-banner--closable' : ''; ?>"
		style="<?php echo esc_attr( $style ); ?>"
		data-hkwp-banner-key="<?php echo esc_attr( $banner_key ); ?>"
		role="region"
		aria-label="<?php esc_attr_e( 'Message promotionnel', 'hkwebproduction-banner' ); ?>">
		<div class="hkwp-banner__inner">
			<div class="hkwp-banner__message">
				<?php
				// Le message est nettoyé à l'enregistrement (wp_kses_post) : on l'affiche tel quel.
				echo wp_kses_post( $message );
				?>
			</div>
			<?php if ( $closable ) : ?>
				<button type="button"
					id="hkwp-banner-close"
					class="hkwp-banner__close"
					aria-label="<?php esc_attr_e( 'Fermer le message promotionnel', 'hkwebproduction-banner' ); ?>">
					<span aria-hidden="true">&times;</span>
				</button>
			<?php endif; ?>
		</div>
	</div>
	<?php
}
add_action( 'wp_body_open', 'hkwp_banner_render_banner' );

/**
 * Charge le CSS et le JS du front-office.
 *
 * Les ressources ne sont chargées que si la bannière est réellement
 * active et non vide, afin de ne pas alourdir les pages inutilement.
 *
 * @since 1.0.0
 * @return void
 */
function hkwp_banner_enqueue_assets() {
	// Aucun asset dans l'administration.
	if ( is_admin() ) {
		return;
	}

	$settings = hkwp_banner_get_settings();

	// Ne rien charger si la bannière est désactivée, vide, ou hors période.
	if ( empty( $settings['enabled'] )
		|| '' === trim( (string) $settings['message'] )
		|| ! hkwp_banner_is_within_schedule( $settings ) ) {
		return;
	}

	// Feuille de style de la bannière.
	wp_enqueue_style(
		'hkwp-banner',
		HKWP_BANNER_URL . 'assets/banner.css',
		array(),
		HKWP_BANNER_VERSION
	);

	// Script toujours chargé quand la bannière est active : il mesure la hauteur
	// de la bannière (pour décaler le menu fixe) et gère le bouton de fermeture.
	wp_enqueue_script(
		'hkwp-banner',
		HKWP_BANNER_URL . 'assets/banner.js',
		array(),
		HKWP_BANNER_VERSION,
		true // Chargé dans le pied de page.
	);
}
add_action( 'wp_enqueue_scripts', 'hkwp_banner_enqueue_assets' );

/*
 * -------------------------------------------------------------------------
 * Back-office : page de réglages
 * -------------------------------------------------------------------------
 */

/**
 * Ajoute la page de réglages dans le menu "Réglages" de l'administration.
 *
 * @since 1.0.0
 * @return void
 */
function hkwp_banner_add_admin_menu() {
	add_options_page(
		__( 'HKWebProduction Banner', 'hkwebproduction-banner' ), // Titre de la page.
		__( 'HKWP Banner', 'hkwebproduction-banner' ),            // Libellé du menu.
		'manage_options',                                          // Capacité requise (administrateurs).
		'hkwebproduction-banner',                                  // Slug de la page.
		'hkwp_banner_settings_page'                                // Callback d'affichage.
	);
}
add_action( 'admin_menu', 'hkwp_banner_add_admin_menu' );

/**
 * Enregistre l'option et son callback de nettoyage via l'API Settings.
 *
 * L'API Settings gère automatiquement le nonce et la vérification de
 * sécurité du formulaire.
 *
 * @since 1.0.0
 * @return void
 */
function hkwp_banner_register_settings() {
	register_setting(
		'hkwp_banner_group',                 // Groupe de réglages.
		HKWP_BANNER_OPTION,                  // Nom de l'option.
		array(
			'type'              => 'array',
			'sanitize_callback' => 'hkwp_banner_sanitize_settings',
			'default'           => hkwp_banner_default_settings(),
		)
	);
}
add_action( 'admin_init', 'hkwp_banner_register_settings' );

/**
 * Nettoie et valide les données du formulaire avant enregistrement en base.
 *
 * Toutes les entrées sont validées : cases à cocher normalisées en 0/1,
 * message filtré via wp_kses_post (autorise liens et HTML de base),
 * couleurs validées en hexadécimal strict avec repli sur les défauts.
 *
 * @since 1.0.0
 * @param mixed $input Données brutes issues du formulaire.
 * @return array Réglages nettoyés.
 */
function hkwp_banner_sanitize_settings( $input ) {
	$output   = hkwp_banner_default_settings();
	$defaults = hkwp_banner_default_settings();

	// Repli défensif si l'entrée n'est pas un tableau.
	if ( ! is_array( $input ) ) {
		return $output;
	}

	// Cases à cocher : normalisées en entier 0 ou 1.
	$output['enabled']  = empty( $input['enabled'] ) ? 0 : 1;
	$output['closable'] = empty( $input['closable'] ) ? 0 : 1;

	// Message : HTML de base autorisé (liens, gras, etc.) via wp_kses_post.
	$output['message'] = isset( $input['message'] ) ? wp_kses_post( $input['message'] ) : '';

	// Couleur de fond : validation hexadécimale stricte, repli sur le défaut.
	$bg                 = isset( $input['bg_color'] ) ? sanitize_hex_color( $input['bg_color'] ) : '';
	$output['bg_color'] = $bg ? $bg : $defaults['bg_color'];

	// Couleur du texte : validation hexadécimale stricte, repli sur le défaut.
	$text                 = isset( $input['text_color'] ) ? sanitize_hex_color( $input['text_color'] ) : '';
	$output['text_color'] = $text ? $text : $defaults['text_color'];

	// Programmation : dates de début et de fin normalisées ("" si vide/invalide).
	$output['start_date'] = hkwp_banner_normalize_datetime( isset( $input['start_date'] ) ? $input['start_date'] : '' );
	$output['end_date']   = hkwp_banner_normalize_datetime( isset( $input['end_date'] ) ? $input['end_date'] : '' );

	return $output;
}

/**
 * Affiche le contenu HTML de la page de réglages.
 *
 * @since 1.0.0
 * @return void
 */
function hkwp_banner_settings_page() {
	// Double contrôle de capacité (défense en profondeur).
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings = hkwp_banner_get_settings();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'HKWebProduction Banner', 'hkwebproduction-banner' ); ?></h1>
		<p><?php esc_html_e( 'Affiche un message promotionnel tout en haut du site.', 'hkwebproduction-banner' ); ?></p>

		<form action="options.php" method="post">
			<?php
			// Génère les champs cachés de sécurité (nonce, action, referer).
			settings_fields( 'hkwp_banner_group' );
			?>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="hkwp_banner_enabled"><?php esc_html_e( 'Activer la bannière', 'hkwebproduction-banner' ); ?></label>
						</th>
						<td>
							<input type="checkbox"
								id="hkwp_banner_enabled"
								name="<?php echo esc_attr( HKWP_BANNER_OPTION ); ?>[enabled]"
								value="1"
								<?php checked( 1, (int) $settings['enabled'] ); ?> />
							<span class="description"><?php esc_html_e( 'Cochez pour afficher la bannière sur le site.', 'hkwebproduction-banner' ); ?></span>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="hkwp_banner_message"><?php esc_html_e( 'Message', 'hkwebproduction-banner' ); ?></label>
						</th>
						<td>
							<textarea id="hkwp_banner_message"
								name="<?php echo esc_attr( HKWP_BANNER_OPTION ); ?>[message]"
								rows="4"
								class="large-text"
								placeholder="<?php esc_attr_e( 'Ex. : Promo de rentrée -20% jusqu\'au 30 septembre !', 'hkwebproduction-banner' ); ?>"><?php echo esc_textarea( $settings['message'] ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Vous pouvez inclure un lien HTML, par exemple :', 'hkwebproduction-banner' ); ?>
								<code>&lt;a href="/promo"&gt;<?php esc_html_e( 'Voir l\'offre', 'hkwebproduction-banner' ); ?>&lt;/a&gt;</code>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="hkwp_banner_bg_color"><?php esc_html_e( 'Couleur de fond', 'hkwebproduction-banner' ); ?></label>
						</th>
						<td>
							<input type="color"
								id="hkwp_banner_bg_color"
								name="<?php echo esc_attr( HKWP_BANNER_OPTION ); ?>[bg_color]"
								value="<?php echo esc_attr( $settings['bg_color'] ); ?>" />
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="hkwp_banner_text_color"><?php esc_html_e( 'Couleur du texte', 'hkwebproduction-banner' ); ?></label>
						</th>
						<td>
							<input type="color"
								id="hkwp_banner_text_color"
								name="<?php echo esc_attr( HKWP_BANNER_OPTION ); ?>[text_color]"
								value="<?php echo esc_attr( $settings['text_color'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Veillez à un contraste suffisant entre le texte et le fond (accessibilité RGAA/WCAG).', 'hkwebproduction-banner' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="hkwp_banner_closable"><?php esc_html_e( 'Bouton de fermeture', 'hkwebproduction-banner' ); ?></label>
						</th>
						<td>
							<input type="checkbox"
								id="hkwp_banner_closable"
								name="<?php echo esc_attr( HKWP_BANNER_OPTION ); ?>[closable]"
								value="1"
								<?php checked( 1, (int) $settings['closable'] ); ?> />
							<span class="description"><?php esc_html_e( 'Permet au visiteur de fermer la bannière (mémorisé sur son navigateur).', 'hkwebproduction-banner' ); ?></span>
						</td>
					</tr>

					<tr>
						<td colspan="2" style="padding-left:0;">
							<div class="notice notice-info inline" style="margin:0;">
								<p style="margin:.6em 0;">
									<strong><?php esc_html_e( 'Programmation d\'affichage', 'hkwebproduction-banner' ); ?></strong><br />
									<?php
									/* translators: %s: identifiant du fuseau horaire du site (ex. Europe/Paris). */
									printf(
										esc_html__( 'Les dates ci-dessous utilisent le fuseau horaire de WordPress (%s), et non l\'heure brute du serveur.', 'hkwebproduction-banner' ),
										'<code>' . esc_html( wp_timezone_string() ) . '</code>'
									);
									?>
									<br />
									<?php
									/* translators: %s: date et heure courantes du site. */
									printf(
										esc_html__( 'Heure actuelle du site : %s.', 'hkwebproduction-banner' ),
										'<strong>' . esc_html( wp_date( 'd/m/Y H:i' ) ) . '</strong>'
									);
									?>
									<a href="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>"><?php esc_html_e( 'Modifier le fuseau horaire', 'hkwebproduction-banner' ); ?></a>
								</p>
							</div>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="hkwp_banner_start_date"><?php esc_html_e( 'Date de début', 'hkwebproduction-banner' ); ?></label>
						</th>
						<td>
							<input type="datetime-local"
								id="hkwp_banner_start_date"
								name="<?php echo esc_attr( HKWP_BANNER_OPTION ); ?>[start_date]"
								value="<?php echo esc_attr( $settings['start_date'] ); ?>" />
							<span class="description"><?php esc_html_e( 'Optionnel. Avant cette date, la bannière ne s\'affiche pas. Laissez vide pour un affichage immédiat.', 'hkwebproduction-banner' ); ?></span>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="hkwp_banner_end_date"><?php esc_html_e( 'Date de fin', 'hkwebproduction-banner' ); ?></label>
						</th>
						<td>
							<input type="datetime-local"
								id="hkwp_banner_end_date"
								name="<?php echo esc_attr( HKWP_BANNER_OPTION ); ?>[end_date]"
								value="<?php echo esc_attr( $settings['end_date'] ); ?>" />
							<span class="description"><?php esc_html_e( 'Optionnel. Après cette date, la bannière ne s\'affiche plus. Laissez vide pour un affichage sans fin.', 'hkwebproduction-banner' ); ?></span>
						</td>
					</tr>
				</tbody>
			</table>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/**
 * Ajoute un lien "Réglages" sur la ligne du plugin (page des extensions).
 *
 * Améliore l'ergonomie : accès direct aux réglages depuis la liste des extensions.
 *
 * @since 1.0.0
 * @param array $links Liens d'action existants.
 * @return array Liens d'action avec "Réglages" ajouté en premier.
 */
function hkwp_banner_action_links( $links ) {
	$url           = admin_url( 'options-general.php?page=hkwebproduction-banner' );
	$settings_link = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Réglages', 'hkwebproduction-banner' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'hkwp_banner_action_links' );
