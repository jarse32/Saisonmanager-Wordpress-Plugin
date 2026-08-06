<?php
/**
 * Plugin Name: Saisonmanager Floorball
 * Plugin URI:  https://github.com/
 * Description: Zeigt Floorball-Spiele, Tabellen und Ligen aus der Saisonmanager-API via Shortcodes an.
 * Version:     1.1.0
 * Author:      Kasche
 * Text Domain: saisonmanager-floorball
 * License:     GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SMF_VERSION', '1.1.0' );
define( 'SMF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SMF_PLUGIN_DIR . 'includes/class-cache.php';
require_once SMF_PLUGIN_DIR . 'includes/class-api.php';
require_once SMF_PLUGIN_DIR . 'includes/class-club-overview.php';
require_once SMF_PLUGIN_DIR . 'includes/class-shortcodes.php';
require_once SMF_PLUGIN_DIR . 'includes/class-admin.php';

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

    // Optionale API-URL (für Spiele aus Verbands-spezifischen Endpunkten)
    $api_url = isset( $_POST['api_url'] ) ? esc_url_raw( wp_unslash( $_POST['api_url'] ) ) : '';
    $api     = $api_url ? new SMF_API( $api_url ) : new SMF_API();

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
 * Template rendern
 *
 * @param string $template
 * @param array  $data
 */
function smf_render_template( $template, $data = array() ) {
    $file = SMF_PLUGIN_DIR . 'templates/' . $template . '.php';
    if ( file_exists( $file ) ) {
        extract( $data, EXTR_SKIP );
        include $file;
    }
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
