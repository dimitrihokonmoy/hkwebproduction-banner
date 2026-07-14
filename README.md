# HKWebProduction Banner

Plugin WordPress qui affiche un message promotionnel **tout en haut du site** (bandeau plein largeur), configurable depuis l'administration : texte/HTML, couleurs, et bouton de fermeture.

- **Auteur :** HKWebProduction
- **Version :** 1.0.0
- **Licence :** GPL-2.0-or-later
- **Text Domain :** `hkwebproduction-banner`

---

## 1. Compatibilité

| Élément            | Version                                   | Statut          |
| ------------------ | ----------------------------------------- | --------------- |
| PHP                | 7.4 minimum — **testé sur 8.4.15**        | ✅ Compatible   |
| WordPress          | 6.0 minimum — **testé sur 7.0**           | ✅ Compatible   |
| Dépendance thème   | Le thème doit appeler `wp_body_open()`    | ✅ `jdb-secrets` OK (header.php ligne 22) |
| Autres extensions  | Aucun conflit détecté (préfixe unique)    | ✅ Vérifié      |

Validé avec `php -l` (aucune erreur de syntaxe) et un test runtime sous `E_ALL`
(aucune dépréciation PHP 8.4, aucune erreur fatale).

---

## 2. Installation et activation

Le plugin est déjà présent dans :

```
www/wp-content/plugins/hkwebproduction-banner/
```

1. Admin WordPress → **Extensions**.
2. Activer **HKWebProduction Banner**.
3. Aller dans **Réglages → HKWP Banner** (ou cliquer « Réglages » sur la ligne du plugin).

---

## 3. Utilisation

Dans **Réglages → HKWP Banner** :

| Réglage               | Description                                                                 |
| --------------------- | --------------------------------------------------------------------------- |
| **Activer la bannière** | Affiche ou masque la bannière sur tout le site.                           |
| **Message**           | Texte affiché. Accepte le HTML de base (liens, gras…). Ex. `<a href="/promo">Voir l'offre</a>`. |
| **Couleur de fond**   | Couleur d'arrière-plan du bandeau.                                          |
| **Couleur du texte**  | Couleur du texte. Veiller au **contraste** (accessibilité RGAA/WCAG).       |
| **Bouton de fermeture** | Autorise le visiteur à fermer la bannière (mémorisé dans son navigateur). |

> **Astuce :** si vous modifiez le message, la bannière **réapparaît** automatiquement
> chez les visiteurs qui l'avaient fermée (la mémorisation est liée au contenu du message).

---

## 4. Architecture des fichiers

```
hkwebproduction-banner/
├── hkwebproduction-banner.php   # Plugin principal : réglages, sécurité, affichage
├── assets/
│   ├── banner.css               # Styles de la bannière (préfixe BEM hkwp-banner)
│   └── banner.js                # Bouton fermer + mémorisation localStorage
└── README.md                    # Cette documentation
```

### Point d'accroche (où s'affiche la bannière)

La bannière est injectée via le hook **`wp_body_open`**, déclenché juste après la
balise `<body>`. Elle se place donc avant l'en-tête du thème, tout en haut de la page.

> ⚠️ **Prérequis :** le thème actif doit appeler `wp_body_open()` dans son `header.php`.
> Le thème `jdb-secrets` le fait déjà. Si vous changez de thème, vérifiez ce point.

### Cohabitation avec le menu fixe (important)

Le thème `jdb-secrets` a un menu `.site-header` en `position: fixed; top: 0`.
La bannière étant elle aussi fixée en haut, un mécanisme de décalage évite tout
chevauchement :

1. La bannière est en `position: fixed; top: 0` (au-dessus du menu, z-index 9999).
2. Le script `banner.js` mesure la hauteur réelle de la bannière et la publie dans
   une variable CSS globale : `--hkwp-banner-h` (recalculée au redimensionnement,
   remise à `0px` quand la bannière est fermée).
3. Le thème (`css/header.css`) consomme cette variable pour décaler le menu et le
   contenu :
   - `.site-header { top: var(--hkwp-banner-h, 0px); }`
   - `.site-main { padding-top: calc(var(--jdb-header-h) + 30px + var(--hkwp-banner-h, 0px)); }`
   - version accueil et menu mobile ajustés de la même manière.

> Le **repli `0px`** garantit que le thème reste correct **même si le plugin est
> désactivé** : sans la variable, le menu revient simplement en haut (`top: 0`).
> Si vous changez de thème, reportez ces 3 règles dans le nouveau thème.

---

## 5. Détail technique du code

### Conventions de nommage (préfixes uniques)

Pour éviter tout conflit avec le core WordPress et les autres extensions
(WooCommerce, WPBakery, Yoast, Jetpack…), tous les identifiants sont préfixés :

| Type                | Préfixe                | Exemple                        |
| ------------------- | ---------------------- | ------------------------------ |
| Constantes          | `HKWP_BANNER_`         | `HKWP_BANNER_VERSION`          |
| Fonctions PHP       | `hkwp_banner_`         | `hkwp_banner_render_banner()`  |
| Option en base      | `hkwp_banner_settings` | (table `wp_options`)           |
| Classes/ID CSS      | `hkwp-banner`          | `.hkwp-banner__message`        |
| Clés localStorage   | `hkwpBannerClosed:`    | `hkwpBannerClosed:ab12cd34`    |

### Fonctions principales

| Fonction                          | Rôle                                                        |
| --------------------------------- | ----------------------------------------------------------- |
| `hkwp_banner_default_settings()`  | Retourne les réglages par défaut.                           |
| `hkwp_banner_get_settings()`      | Lit les réglages en base, fusionnés avec les défauts.       |
| `hkwp_banner_activate()`          | Crée l'option à l'activation (sans écraser l'existant).     |
| `hkwp_banner_render_banner()`     | Affiche le HTML de la bannière (`wp_body_open`).            |
| `hkwp_banner_enqueue_assets()`    | Charge CSS/JS **uniquement** si la bannière est active.     |
| `hkwp_banner_add_admin_menu()`    | Ajoute la page de réglages sous « Réglages ».               |
| `hkwp_banner_register_settings()` | Enregistre l'option via l'API Settings (nonce automatique). |
| `hkwp_banner_sanitize_settings()` | Nettoie/valide toutes les entrées avant enregistrement.     |
| `hkwp_banner_settings_page()`     | Affiche le formulaire de réglages.                          |
| `hkwp_banner_action_links()`      | Ajoute le lien « Réglages » sur la page des extensions.     |

### Hooks WordPress utilisés

| Hook                        | Type   | Fonction accrochée               |
| --------------------------- | ------ | -------------------------------- |
| `register_activation_hook`  | action | `hkwp_banner_activate`           |
| `init`                      | action | `hkwp_banner_load_textdomain`    |
| `wp_body_open`              | action | `hkwp_banner_render_banner`      |
| `wp_enqueue_scripts`        | action | `hkwp_banner_enqueue_assets`     |
| `admin_menu`                | action | `hkwp_banner_add_admin_menu`     |
| `admin_init`                | action | `hkwp_banner_register_settings`  |
| `plugin_action_links_{...}` | filter | `hkwp_banner_action_links`       |

---

## 6. Sécurité

Le plugin applique les bonnes pratiques WordPress, essentielles pour un site institutionnel :

- **Accès direct bloqué** : `if ( ! defined( 'ABSPATH' ) ) exit;`
- **Capacité requise** : `manage_options` (administrateurs uniquement) pour les réglages.
- **Nonce / CSRF** : géré automatiquement par l'API Settings (`settings_fields()`).
- **Nettoyage des entrées** :
  - `wp_kses_post()` sur le message (retire les balises dangereuses comme `<script>`).
  - `sanitize_hex_color()` sur les couleurs (repli sur le défaut si invalide).
  - Cases à cocher normalisées en `0`/`1`.
- **Échappement des sorties** : `esc_attr()`, `esc_html()`, `esc_url()`, `esc_textarea()`.

---

## 7. Accessibilité (RGAA / WCAG)

- La bannière est un repère de navigation : `role="region"` + `aria-label`.
- Le bouton de fermeture a un `aria-label` explicite ; le symbole `×` est `aria-hidden`.
- Focus clavier visible sur le bouton (`:focus-visible`).
- **À surveiller côté rédacteur** : le contraste entre la couleur du texte et celle
  du fond doit respecter un ratio d'au moins **4.5:1** (texte normal).

---

## 8. Données et désinstallation

- Le plugin crée **une seule option** en base : `hkwp_banner_settings` (table `wp_options`).
- La désactivation ne supprime pas cette option (les réglages sont conservés).
- Pour tout supprimer manuellement (via WP-CLI par exemple) :

  ```bash
  wp option delete hkwp_banner_settings
  ```

  > Aucun fichier `uninstall.php` n'est fourni volontairement, pour éviter une perte
  > accidentelle des réglages. À ajouter si un nettoyage automatique est souhaité.

---

## 9. Maintenance et évolutions possibles

- **Tester après mise à jour de WordPress** : vérifier que le thème appelle toujours
  `wp_body_open()` et que la bannière s'affiche.
- **Cohabitation avec l'ancien plugin** : penser à désactiver l'affichage en bas de
  page de l'ancien plugin promotionnel pour éviter un double bandeau.
- **Évolutions envisageables** :
  - Programmation d'affichage (date de début / fin).
  - Ciblage par page ou par modèle.
  - Plusieurs bannières / rotation de messages.
  - Fichier de traduction `/languages` (le domaine est déjà prêt).

---

## 10. Journal des versions

### 1.0.2
- Bannière responsive : media queries mobile (< 768px) et petit mobile (< 480px),
  ajustement de la police et des paddings.
- Typographie alignée sur le thème (`--jdb-font-body`).
- Bouton de fermeture agrandi à 40px (cible tactile, WCAG 2.5.5).
- Prise en compte de `prefers-reduced-motion`.
- Couleurs par défaut alignées sur la charte : fond noir, texte blanc.

### 1.0.1
- Correction du chevauchement avec le menu fixe du thème : la bannière est
  désormais fixée en haut et publie sa hauteur via `--hkwp-banner-h`, que le
  thème utilise pour décaler le menu et le contenu.
- Le script est chargé dès que la bannière est active (mesure de hauteur),
  même sans bouton de fermeture.

### 1.0.0
- Version initiale : bannière en haut du site via `wp_body_open`.
- Page de réglages (message HTML, couleurs, activation, bouton fermer).
- Mémorisation de la fermeture liée au contenu du message.
- Compatible PHP 8.4 / WordPress 7.0, sans conflit avec les extensions installées.
