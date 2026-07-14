<?php
/**
 * Plugin Name:       HKWebProduction Banner
 * Plugin URI:        https://hkwebproduction.com/
 * Description:       Affiche un message promotionnel en haut du site (juste après l'ouverture de la balise body). Le message, les couleurs et le bouton de fermeture se configurent depuis l'administration WordPress.
 * Version:           1.0.2
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
define( 'HKWP_BANNER_VERSION', '1.0.2' );

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
 * @return array{enabled:int,message:string,bg_color:string,text_color:string,closable:int}
 */
function hkwp_banner_default_settings() {
	return array(
		'enabled'    => 0,          // Bannière désactivée par défaut.
		'message'    => '',         // Message vide par défaut.
		'bg_color'   => '#000000',  // Noir (cohérent avec la charte JDB Secrets).
		'text_color' => '#ffffff',  // Texte blanc par défaut.
		'closable'   => 1,          // Bouton de fermeture actif par défaut.
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

	// Ne rien charger si la bannière est désactivée ou vide.
	if ( empty( $settings['enabled'] ) || '' === trim( (string) $settings['message'] ) ) {
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
