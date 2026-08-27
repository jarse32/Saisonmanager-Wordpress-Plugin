<?php
/**
 * Plugin Name: SM Floorball
 * Plugin URI:  https://github.com/jarse32/Saisonmanager-Wordpress-Plugin
 * Description: Zeigt Floorball-Spiele, Tabellen und Ligen aus der Saisonmanager-API via Shortcodes an. Inoffizielles Community-Projekt, nicht von Saisonmanager/FVD betrieben.
 * Version:     1.4.0
 * Author:      Kasche
 * Text Domain: saisonmanager-floorball
 * License:     GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SMF_VERSION', '1.4.0' );
define( 'SMF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SMF_PLUGIN_DIR . 'includes/class-cache.php';
require_once SMF_PLUGIN_DIR . 'includes/class-api.php';
require_once SMF_PLUGIN_DIR . 'includes/class-design.php';
require_once SMF_PLUGIN_DIR . 'includes/class-team-finder.php';
require_once SMF_PLUGIN_DIR . 'includes/class-club-overview.php';
require_once SMF_PLUGIN_DIR . 'includes/class-shortcodes.php';
require_once SMF_PLUGIN_DIR . 'includes/class-admin.php';

/**
 * Update-Checker: lässt WordPress "Update verfügbar" direkt gegen den
 * main-Branch dieses GitHub-Repos prüfen (kein WordPress.org-Eintrag nötig).
 * Neue Version ausliefern = Version-Header oben + SMF_VERSION erhöhen und
 * nach main pushen, siehe README "Für Plugin-Maintainer".
 */
require_once SMF_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$smf_update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/jarse32/Saisonmanager-Wordpress-Plugin/',
    __FILE__,
    'saisonmanager-floorball'
);
$smf_update_checker->setBranch( 'main' );

/**
 * Shortcodes registrieren
 */
function smf_init() {
    $shortcodes = new SMF_Shortcodes();
    $shortcodes->register();
}
add_action( 'init', 'smf_init' );

/**
 * Admin-Einstellungen + POST-Handler registrieren.
 * Läuft auf admin_init, damit admin_post_* Actions rechtzeitig verfügbar sind.
 */
function smf_admin_setup() {
    $admin = new SMF_Admin();
    $admin->register_settings();
    $admin->register_post_handlers();
}
add_action( 'admin_init', 'smf_admin_setup' );

/**
 * Admin-Menü registrieren (separater Hook)
 */
function smf_admin_menu() {
    $admin = new SMF_Admin();
    $admin->register_menu();
}
add_action( 'admin_menu', 'smf_admin_menu' );

/**
 * Frontend-Assets einbinden
 */
function smf_enqueue_assets() {
    wp_enqueue_style(
        'smf-style',
        SMF_PLUGIN_URL . 'assets/css/style.css',
        array(),
        SMF_VERSION
    );

    // Vereinsindividuelle Design-Werte direkt hinter dem Stylesheet einhängen -
    // gescopet auf .smf/.smf-modal, überschreibt per Kaskade die neutralen
    // Fallback-Werte aus style.css. Reine String-Interpolation aus einer
    // bereits geladenen Option, kein zusätzliches Caching nötig.
    wp_add_inline_style( 'smf-style', SMF_Design::build_css() );

    wp_enqueue_script(
        'smf-script',
        SMF_PLUGIN_URL . 'assets/js/main.js',
        array( 'jquery' ),
        SMF_VERSION,
        true
    );

    wp_localize_script( 'smf-script', 'smf_ajax', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'smf_nonce' ),
    ) );
}
add_action( 'wp_enqueue_scripts', 'smf_enqueue_assets' );

/**
 * AJAX: Spieldetails laden
 */
function smf_ajax_game_detail() {
    check_ajax_referer( 'smf_nonce', 'nonce' );

    $game_id = isset( $_POST['game_id'] ) ? intval( $_POST['game_id'] ) : 0;
    if ( ! $game_id ) {
        wp_send_json_error( 'Ungültige Spiel-ID' );
    }

    $api  = new SMF_API();
    $game = $api->get_game( $game_id );

    if ( is_wp_error( $game ) ) {
        wp_send_json_error( $game->get_error_message() );
    }

    ob_start();
    smf_render_template( 'game-detail', array( 'game' => $game ) );
    $html = ob_get_clean();

    wp_send_json_success( array( 'html' => $html ) );
}
add_action( 'wp_ajax_smf_game_detail',        'smf_ajax_game_detail' );
add_action( 'wp_ajax_nopriv_smf_game_detail', 'smf_ajax_game_detail' );

/**
 * AJAX (nur eingeloggte Admins): Team-Finder für die Vereinskonfiguration.
 * Sucht Vereine per Name oder Club-ID über die bekannten Spielbetriebsstellen
 * und listet deren Team-IDs auf. Bewusst ohne _nopriv-Variante - nicht für
 * Besucher:innen gedacht (löst pro Suche bis zu 10 Upstream-Requests aus).
 */
function smf_ajax_find_teams() {
    check_ajax_referer( 'smf_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Nicht erlaubt.' );
    }

    $query = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';
    if ( $query === '' ) {
        wp_send_json_error( 'Bitte einen Vereinsnamen oder eine Club-ID angeben.' );
    }

    $season_id = isset( $_POST['season_id'] ) ? absint( $_POST['season_id'] ) : 0;

    $results = SMF_TeamFinder::search( new SMF_API(), $query, $season_id ?: null );

    wp_send_json_success( array( 'results' => $results ) );
}
add_action( 'wp_ajax_smf_find_teams', 'smf_ajax_find_teams' );

/**
 * AJAX (nur eingeloggte Admins): aktuelle Saison-ID ermitteln, damit man im
 * Team-Finder nicht raten muss, welche Saison-ID "letzte Saison" ist
 * (Saisons sind fortlaufend nummeriert, Vorsaison = aktuelle ID - 1).
 */
function smf_ajax_current_season() {
    check_ajax_referer( 'smf_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Nicht erlaubt.' );
    }

    $season_id = ( new SMF_API() )->get_current_season_id();
    if ( $season_id === null ) {
        wp_send_json_error( 'Aktuelle Saison-ID konnte nicht ermittelt werden.' );
    }

    wp_send_json_success( array( 'current_season_id' => $season_id ) );
}
add_action( 'wp_ajax_smf_current_season', 'smf_ajax_current_season' );

/**
 * AJAX (nur eingeloggte Admins): Teams + deren Liga-IDs für eine Club-ID
 * laden. Speichert das Ergebnis in smf_club_teams_cache (genutzt von
 * SMF_ClubOverview::get_games()) und gibt es zur Anzeige zurück, inkl. der
 * Liga-IDs jedes Teams - praktisch zum direkten Übernehmen in andere
 * Shortcodes ([sm_tabelle liga_id="…"], [sm_spiele liga_id="…"]).
 */
function smf_ajax_sync_club_teams() {
    check_ajax_referer( 'smf_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Nicht erlaubt.' );
    }

    $club_id = isset( $_POST['club_id'] ) ? absint( $_POST['club_id'] ) : 0;
    if ( ! $club_id ) {
        wp_send_json_error( 'Bitte eine gültige Club-ID angeben.' );
    }

    $teams = SMF_TeamFinder::teams_for_club( new SMF_API(), $club_id );

    $cache = get_option( 'smf_club_teams_cache', array() );
    $cache[ $club_id ] = array(
        'teams'      => $teams,
        'updated_at' => time(),
    );
    update_option( 'smf_club_teams_cache', $cache );

    wp_send_json_success( array( 'teams' => $teams, 'updated_at' => $cache[ $club_id ]['updated_at'] ) );
}
add_action( 'wp_ajax_smf_sync_club_teams', 'smf_ajax_sync_club_teams' );

/**
 * Template rendern. Suchreihenfolge: Child-Theme, dann Parent-Theme
 * (jeweils im Unterordner "saisonmanager-floorball"), dann der
 * Plugin-eigene Default. Themes können also z.B. templates/table.php
 * durch eine eigene Datei unter
 * wp-content/themes/<theme>/saisonmanager-floorball/table.php ersetzen,
 * ohne das Plugin zu verändern.
 *
 * @param string $template Template-Name ohne .php, z.B. "table"
 * @param array  $data     Variablen, die dem Template zur Verfügung stehen
 */
function smf_render_template( $template, $data = array() ) {
    $file = SMF_PLUGIN_DIR . 'templates/' . $template . '.php';

    $child_file = get_stylesheet_directory() . '/saisonmanager-floorball/' . $template . '.php';
    if ( file_exists( $child_file ) ) {
        $file = $child_file;
    } else {
        $parent_file = get_template_directory() . '/saisonmanager-floorball/' . $template . '.php';
        if ( file_exists( $parent_file ) ) {
            $file = $parent_file;
        }
    }

    /**
     * Erlaubt das vollständige Überschreiben des ermittelten Template-Pfads,
     * z.B. um Templates aus einem anderen Verzeichnis oder Plugin zu laden.
     *
     * @param string $file     Ermittelter Pfad (Child-Theme > Parent-Theme > Plugin-Default)
     * @param string $template Template-Name ohne .php
     * @param array  $data     Variablen, die dem Template zur Verfügung stehen
     */
    $file = apply_filters( 'smf_template_path', $file, $template, $data );

    if ( file_exists( $file ) ) {
        extract( $data, EXTR_SKIP );
        include $file;
    }
}

/**
 * Übersetzbares/überschreibbares UI-Label ausgeben. Keine eigene
 * Textdomain (siehe Umsetzungsplan) - Vereine, die Bezeichnungen anpassen
 * oder in eine andere Sprache übersetzen möchten, hängen sich stattdessen
 * per smf_labels-Filter ein, z.B. im Child-Theme:
 *
 *     add_filter( 'smf_labels', function ( $labels ) {
 *         $labels['no_games_found'] = 'No games scheduled.';
 *         return $labels;
 *     } );
 *
 * Die verfügbaren Schlüssel samt Standardtext stehen in der README
 * (Abschnitt "Hooks für Theme-Entwickler:innen") - $fallback am Aufrufort
 * ist jeweils die maßgebliche Default-Quelle, damit derselbe Text nicht
 * zusätzlich in einem zentralen Array gepflegt werden muss.
 *
 * @param string $key
 * @param string $fallback
 * @return string
 */
function smf_label( $key, $fallback ) {
    static $overrides = null;
    if ( $overrides === null ) {
        $overrides = apply_filters( 'smf_labels', array() );
    }
    return ( isset( $overrides[ $key ] ) && $overrides[ $key ] !== '' ) ? $overrides[ $key ] : $fallback;
}

/**
 * Pflicht-Quellenangabe gemäß Saisonmanager-Nutzungsvereinbarung ausgeben
 * (Datenquelle + Kennzeichnung als inoffizielles Community-Projekt).
 */
function smf_render_attribution() {
    echo '<p class="smf-attribution">Daten: Saisonmanager / Floorball Verband Deutschland e. V. – inoffizielles Community-Projekt.</p>';
}

/**
 * Jetpack Photon für Saisonmanager-Logos deaktivieren.
 * Zwei Filter, da verschiedene Jetpack-Versionen unterschiedliche Hooks nutzen.
 */
function smf_skip_jetpack_photon( $skip, $image_url ) {
    if ( strpos( (string) $image_url, 'saisonmanager.de' ) !== false ) {
        return true;
    }
    return $skip;
}
add_filter( 'jetpack_photon_skip_for_url', 'smf_skip_jetpack_photon', 10, 2 );

// Fallback: URL direkt zurückgeben statt Photon-Version
function smf_fix_jetpack_photon_url( $photon_url, $image_url ) {
    if ( strpos( (string) $image_url, 'saisonmanager.de' ) !== false ) {
        return $image_url;
    }
    return $photon_url;
}
add_filter( 'jetpack_photon_url', 'smf_fix_jetpack_photon_url', 10, 2 );

/**
 * Aktivierung: Standard-Optionen setzen
 */
function smf_activate() {
    if ( ! get_option( 'smf_api_base_url' ) ) {
        update_option( 'smf_api_base_url', 'https://saisonmanager.de/api/v2' );
    }
    if ( ! get_option( 'smf_cache_duration' ) ) {
        update_option( 'smf_cache_duration', 300 );
    }
}
register_activation_hook( __FILE__, 'smf_activate' );
