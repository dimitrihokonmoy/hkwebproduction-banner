# HKWebProduction Banner

Plugin WordPress qui affiche un message promotionnel **tout en haut du site** (bandeau plein largeur), configurable depuis l'administration : texte/HTML, couleurs, et bouton de fermeture.

- **Auteur :** HKWebProduction
- **Version :** 1.0.6
- **Licence :** GPL-2.0-or-later
- **Text Domain :** `hkwebproduction-banner`

---

## 1. Compatibilité

| Élément            | Version                                   | Statut          |
| ------------------ | ----------------------------------------- | --------------- |
| PHP                | 7.4 minimum — **testé sur 8.4.15**        | ✅ Compatible   |
| WordPress          | 6.0 minimum — **testé sur 7.0**           | ✅ Compatible   |
| Dépendance thème   | Le thème doit appeler `wp_body_open()`    | Voir section 4  |
| Autres extensions  | Aucun conflit (préfixe unique `hkwp_`)    | ✅ Vérifié      |

Validé avec `php -l` (aucune erreur de syntaxe) et un test runtime sous `E_ALL`
(aucune dépréciation PHP 8.4, aucune erreur fatale).

---

## 2. Installation et activation

**Option A — via l'administration WordPress :**

1. Télécharger l'archive `hkwebproduction-banner-x.y.z.zip` (page *Releases* du dépôt).
2. Admin WordPress → **Extensions → Ajouter → Téléverser une extension**.
3. Sélectionner le zip, installer, puis **Activer**.

**Option B — via Git / dépôt :**

```bash
cd wp-content/plugins/
git clone https://github.com/dimitrihokonmoy/hkwebproduction-banner.git
```

Puis activer l'extension dans **Extensions**.

Une fois activé : **Réglages → HKWP Banner** (ou lien « Réglages » sur la ligne du plugin).

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
| **Date de début**     | Optionnel. Avant cette date/heure, la bannière ne s'affiche pas. Vide = affichage immédiat. |
| **Date de fin**       | Optionnel. Après cette date/heure, la bannière ne s'affiche plus. Vide = sans fin. |

> **Astuce :** si vous modifiez le message, la bannière **réapparaît** automatiquement
> chez les visiteurs qui l'avaient fermée (la mémorisation est liée au contenu du message).

### Programmation d'affichage

Les champs **Date de début** et **Date de fin** permettent d'afficher la bannière
uniquement pendant une période donnée (ex. une promo limitée dans le temps) :

- Les deux champs sont **optionnels** et indépendants (on peut ne renseigner que l'un).
- Les dates sont interprétées dans le **fuseau horaire de WordPress** (Réglages → Général),
  et **non** l'heure brute du serveur (souvent en UTC). La page de réglages rappelle le
  fuseau actif et affiche l'heure actuelle du site pour lever toute ambiguïté.
- Hors période, la bannière n'est pas rendue **et** ses fichiers CSS/JS ne sont pas
  chargés (aucun impact sur les performances).

> **Note technique :** le plugin utilise `wp_timezone()` (fuseau du site) pour construire
> à la fois « maintenant » et les bornes de début/fin. La comparaison est donc toujours
> cohérente, quel que soit le fuseau réel du serveur.

---

## 4. Point d'accroche et intégration au thème

### Où s'affiche la bannière

La bannière est injectée via le hook **`wp_body_open`**, déclenché juste après la
balise `<body>`. Elle se place donc avant l'en-tête du thème, tout en haut de la page.

> ⚠️ **Prérequis :** le thème actif doit appeler `wp_body_open()` dans son `header.php`
> (standard depuis WordPress 5.2). La plupart des thèmes modernes le font. Si ce n'est
> pas le cas, ajoutez `<?php wp_body_open(); ?>` juste après la balise `<body>`.

### Cohabitation avec un en-tête fixe (`position: fixed` / `sticky`)

Si votre thème a un en-tête **fixe ou sticky**, il flotte au-dessus du flux de la page
et **recouvre** la bannière. Le plugin fournit tout le nécessaire pour corriger cela
sans le coder en dur pour un thème donné :

1. La bannière est en `position: fixed; top: 0` (z-index 9999, au-dessus de l'en-tête).
2. Le script `banner.js` mesure la hauteur réelle de la bannière et la publie dans une
   variable CSS globale : **`--hkwp-banner-h`** (recalculée au redimensionnement,
   remise à `0px` quand la bannière est fermée ou absente).
3. Il suffit alors que le thème **consomme cette variable** pour décaler son en-tête
   fixe et son contenu.

**Exemple générique** à adapter aux sélecteurs de votre thème :

```css
/* En-tête fixe décalé vers le bas de la hauteur de la bannière */
.mon-header-fixe {
    top: var(--hkwp-banner-h, 0px);
}

/* Contenu décalé d'autant pour ne pas passer sous l'en-tête */
.mon-contenu-principal {
    padding-top: calc(<hauteur-header> + var(--hkwp-banner-h, 0px));
}
```

> Le **repli `0px`** est essentiel : si le plugin est désactivé (ou la variable absente),
> le thème retrouve exactement son comportement d'origine. Ces règles sont donc sûres à
> laisser en place en permanence.

---

## 5. Détail technique du code

### Conventions de nommage (préfixes uniques)

Pour éviter tout conflit avec le core WordPress et les autres extensions, tous les
identifiants sont préfixés :

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
| `hkwp_banner_normalize_datetime()`| Normalise/valide une date datetime-local (fuseau du site).  |
| `hkwp_banner_is_within_schedule()`| Indique si l'instant présent est dans la période programmée.|
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

### Note sur la typographie

La bannière utilise `font-family: inherit` : elle hérite donc automatiquement de la
police du site (celle appliquée au `body` par le thème). Aucune dépendance à un thème
particulier, tout en restant harmonisée visuellement.

---

## 6. Sécurité

Le plugin applique les bonnes pratiques WordPress :

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
- Cible tactile du bouton de fermeture à 40px (recommandation WCAG 2.5.5).
- Respect de `prefers-reduced-motion`.
- **À surveiller côté rédacteur** : le contraste entre la couleur du texte et celle
  du fond doit respecter un ratio d'au moins **4.5:1** (texte normal).

---

## 8. Responsive

- Largeur fluide, mise en page en flexbox, le message passe à la ligne proprement.
- Media queries mobile (< 768px) et petit mobile (< 480px) : police et paddings ajustés.
- La hauteur réelle étant mesurée en JS (`--hkwp-banner-h`), le décalage de l'en-tête
  reste correct même si le message occupe plusieurs lignes.

---

## 9. Données et désinstallation

- Le plugin crée **une seule option** en base : `hkwp_banner_settings` (table `wp_options`).
- La désactivation ne supprime pas cette option (les réglages sont conservés).
- Pour tout supprimer manuellement (via WP-CLI par exemple) :

  ```bash
  wp option delete hkwp_banner_settings
  ```

  > Aucun fichier `uninstall.php` n'est fourni volontairement, pour éviter une perte
  > accidentelle des réglages. À ajouter si un nettoyage automatique est souhaité.

---

## 10. Maintenance et évolutions possibles

- **Tester après mise à jour de WordPress** : vérifier que le thème appelle toujours
  `wp_body_open()` et que la bannière s'affiche.
- **En-tête fixe** : si vous changez de thème, reportez les règles CSS de la section 4
  (consommation de `--hkwp-banner-h`) dans le nouveau thème.
- **Doublon de bannière** : si un autre outil affiche déjà un bandeau promo, désactivez-le
  pour éviter deux bannières.
- **Évolutions envisageables** :
  - Ciblage par page ou par modèle.
  - Plusieurs bannières / rotation de messages.
  - Fichier de traduction `/languages` (le domaine est déjà prêt).

---

## 11. Journal des versions

### 1.0.6
- Page de réglages : précision que le champ Message accepte le HTML (liens, gras,
  balises stylées), avec exemples. Rappel que les balises non sûres sont retirées.

### 1.0.5
- Page de réglages : encadré d'information sur la programmation, précisant que les dates
  suivent le fuseau horaire de WordPress (pas celui du serveur), avec l'heure actuelle
  du site et un lien vers le réglage du fuseau.

### 1.0.4
- Programmation d'affichage : champs **date de début** et **date de fin** (optionnels),
  interprétés dans le fuseau horaire du site. Hors période, la bannière et ses assets
  ne sont pas chargés.

### 1.0.3
- Typographie : `font-family: inherit` (héritage automatique de la police du site,
  plus aucune référence à une variable de thème spécifique).

### 1.0.2
- Bannière responsive : media queries mobile (< 768px) et petit mobile (< 480px),
  ajustement de la police et des paddings.
- Typographie héritée du thème via `--jdb-font-body` (avec repli système).
- Bouton de fermeture agrandi à 40px (cible tactile, WCAG 2.5.5).
- Prise en compte de `prefers-reduced-motion`.
- Couleurs par défaut : fond noir, texte blanc.

### 1.0.1
- Correction du chevauchement avec un en-tête fixe : la bannière est désormais fixée
  en haut et publie sa hauteur via `--hkwp-banner-h`, que le thème peut utiliser pour
  décaler son en-tête et son contenu.
- Le script est chargé dès que la bannière est active (mesure de hauteur),
  même sans bouton de fermeture.

### 1.0.0
- Version initiale : bannière en haut du site via `wp_body_open`.
- Page de réglages (message HTML, couleurs, activation, bouton fermer).
- Mémorisation de la fermeture liée au contenu du message.
- Compatible PHP 8.4 / WordPress 7.0, sans conflit avec les extensions installées.
