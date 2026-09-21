<?php
/**
 * Plugin Name: SM Floorball
 * Plugin URI:  https://github.com/jarse32/Saisonmanager-Wordpress-Plugin
 * Description: Zeigt Floorball-Spiele, Tabellen und Ligen aus der Saisonmanager-API via Shortcodes an. Inoffizielles Community-Projekt, nicht von Saisonmanager/FVD betrieben.
 * Version:     1.9.0
 * Author:      Kasche
 * Text Domain: saisonmanager-floorball
 * License:     GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SMF_VERSION', '1.9.0' );
define( 'SMF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SMF_PLUGIN_DIR . 'includes/class-cache.php';
require_once SMF_PLUGIN_DIR . 'includes/class-api.php';
require_once SMF_PLUGIN_DIR . 'includes/class-game-status.php';
require_once SMF_PLUGIN_DIR . 'includes/class-stream.php';
require_once SMF_PLUGIN_DIR . 'includes/class-design.php';
require_once SMF_PLUGIN_DIR . 'includes/class-team-finder.php';
require_once SMF_PLUGIN_DIR . 'includes/class-club-overview.php';
require_once SMF_PLUGIN_DIR . 'includes/class-highlight.php';
require_once SMF_PLUGIN_DIR . 'includes/class-scorer.php';
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
 * Migrations-Routine für die Design-Einstellungen (smf_design). Läuft bei
 * jedem Request, bricht aber sofort ab, sobald smf_version = SMF_VERSION
 * ist - macht also nur nach einer Versionsänderung tatsächlich etwas.
 *
 * Erkennt anhand von smf_verbaende (vor 1.3.0 genutzt) oder smf_vereine,
 * ob es sich um eine bestehende Installation handelt: dann werden die
 * heutigen Eichehorn-Werte übernommen, damit sich am Erscheinungsbild
 * nichts ändert. Ansonsten (Neuinstallation) greifen die neutralen
 * Defaults aus SMF_Design::get_defaults().
 */
function smf_maybe_migrate_design() {
    if ( get_option( 'smf_version', '' ) === SMF_VERSION ) {
        return;
    }

    if ( get_option( 'smf_design', false ) === false ) {
        $is_existing_install = get_option( 'smf_verbaende', false ) !== false
            || get_option( 'smf_vereine', false ) !== false;

        update_option(
            'smf_design',
            $is_existing_install ? SMF_Design::get_legacy_values() : SMF_Design::get_defaults()
        );
    }

    update_option( 'smf_version', SMF_VERSION );
}
add_action( 'plugins_loaded', 'smf_maybe_migrate_design' );

/**
 * Einmalige Migration für die neue Option "Personennamen anzeigen"
 * (smf_show_player_names, siehe SMF_Scorer::player_names_enabled()).
 * Bestandsinstallationen zeigten Spielernamen im Spieldetail-Modal bisher
 * immer an - für sie bleibt dieses Verhalten erhalten. Neuinstallationen
 * starten mit ausgeschalteter Option (datensparsamer Default).
 *
 * Nutzt dieselbe Erkennung wie smf_maybe_migrate_design() (vorhandene
 * Vereins-/Verbandskonfiguration = Bestandsinstallation), unabhängig von
 * smf_version, damit die Reihenfolge der beiden plugins_loaded-Hooks keine
 * Rolle spielt. Läuft dank eigenem Merker (smf_player_names_migrated) nur
 * einmal, nicht bei jeder Versionsänderung.
 */
function smf_maybe_migrate_player_names() {
    if ( get_option( 'smf_player_names_migrated', false ) ) {
        return;
    }

    $is_existing_install = get_option( 'smf_verbaende', false ) !== false
        || get_option( 'smf_vereine', false ) !== false;

    if ( $is_existing_install ) {
        update_option( 'smf_show_player_names', '1' );
    }

    update_option( 'smf_player_names_migrated', '1' );
}
add_action( 'plugins_loaded', 'smf_maybe_migrate_player_names' );

/**
 * Einmalige Migration für die neue Option "Livestream-Einbettung"
 * (smf_stream_embed_mode, siehe Etappe D / SMF_Stream, Version 1.8.0).
 * Eigener Merker statt des smf_version-Gates in smf_maybe_migrate_design():
 * dieser Merker greift unabhängig davon, wann/ob sich die Plugin-Version
 * ändert - robuster als an den Zeitpunkt eines Versionssprungs gekoppelt zu
 * sein (analog zu smf_maybe_migrate_player_names() oben). Default ist für
 * Bestands- und Neuinstallationen identisch ("nur_link", die
 * datensparsamste Stufe) - anders als bei Design/Personennamen oben gibt es
 * hier keinen unterschiedlichen Bestandswert zu übernehmen.
 */
function smf_maybe_migrate_stream_embed_mode() {
    if ( get_option( 'smf_stream_embed_mode', false ) !== false ) {
        return;
    }
    update_option( 'smf_stream_embed_mode', 'nur_link' );
}
add_action( 'plugins_loaded', 'smf_maybe_migrate_stream_embed_mode' );

/**
 * Einmalige Migration für die neue Option "Einwilligung merken erlauben"
 * (smf_stream_remember_consent, v1.9.0 Teil 1) - gleiches Muster wie
 * smf_maybe_migrate_stream_embed_mode() direkt darüber. Default "aus" für
 * alle Installationen (Bestand wie neu) - datensparsamster Zustand, siehe
 * README.
 */
function smf_maybe_migrate_stream_remember_consent() {
    if ( get_option( 'smf_stream_remember_consent', false ) !== false ) {
        return;
    }
    update_option( 'smf_stream_remember_consent', 'aus' );
}
add_action( 'plugins_loaded', 'smf_maybe_migrate_stream_remember_consent' );

/**
 * Admin-Einstellungen + POST-Handler registrieren.
 * Läuft auf admin_init, damit admin_post_* Actions rechtzeitig verfügbar sind.
 */
function smf_admin_setup() {
    $admin = new SMF_Admin();
    $admin->register_settings();
    $admin->register_post_handlers();

    smf_maybe_cleanup_stale_cache();
}
add_action( 'admin_init', 'smf_admin_setup' );

/**
 * Räumt abgelaufene Notreserve-Einträge (SMF_Cache::cleanup_stale()) auf -
 * gedrosselt auf einmal pro Tag, damit nicht bei jedem Admin-Seitenaufruf
 * ein voller Table-Scan über wp_options läuft. Bewusst kein eigener
 * Cron-Job, sondern an den ohnehin auf admin_init laufenden Hook gehängt.
 */
function smf_maybe_cleanup_stale_cache() {
    $last = (int) get_option( 'smf_stale_cleanup_last_run', 0 );
    if ( ( time() - $last ) < DAY_IN_SECONDS ) {
        return;
    }

    ( new SMF_Cache() )->cleanup_stale();
    update_option( 'smf_stale_cleanup_last_run', time(), false );
}

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
        'ajax_url'               => admin_url( 'admin-ajax.php' ),
        'nonce'                  => wp_create_nonce( 'smf_nonce' ),
        // v1.9.0, Teil 1: Globales Ein/Aus für "Einwilligung merken" - bei
        // "aus" ignoriert main.js auch eine noch vorhandene alte
        // localStorage-Einwilligung (siehe maybeAutoRevealStreams()), statt
        // nur künftige Einwilligungen zu verhindern. Kein zusätzlicher
        // Modus-Check nötig: die Checkbox, die eine Einwilligung überhaupt
        // erst erzeugen kann, rendert PHP-seitig ohnehin nur bei
        // smf_stream_embed_mode = "zwei_klick" (siehe smf_render_stream_embed()).
        'stream_remember_allowed' => get_option( 'smf_stream_remember_consent', 'aus' ) === 'an',
        'stream_forget_label'     => smf_label( 'stream_forget_link', 'Automatisches Laden beenden' ),
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
        wp_send_json_error( SMF_Shortcodes::error_message_for_current_user( $game ) );
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
    // Nur einfache Slugs zulassen (Templates heißen z.B. "table",
    // "games-list") - verhindert Path-Traversal (../), falls diese
    // global aufrufbare Funktion jemals mit nicht vertrauenswürdiger
    // Eingabe aufgerufen wird. Alle aktuellen Aufrufstellen im Plugin
    // übergeben ohnehin feste Literale.
    $template = preg_replace( '/[^a-z0-9\-]/', '', (string) $template );

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
 * HTML-Fragment für das LIVE-/Abgesagt-Abzeichen einer Spielkarte. Leerstring
 * für 'upcoming'/'ended' - dort gibt es kein Abzeichen. Reine Darstellung pro
 * Status, keine Policy: Aufrufer (Templates) entscheiden selbst, OB das
 * Abzeichen überhaupt gezeigt werden soll (Option smf_live_badge_enabled,
 * Shortcode-Attribut live_badge, Staleness-Gate über stale_since()) - siehe
 * README, Abschnitt "LIVE-Kennzeichnung".
 *
 * An einer zentralen Stelle statt in jedem der drei Templates (single-game,
 * games-list, club-overview) einzeln, damit Markup/Klassen nicht auseinanderlaufen.
 *
 * @param string $status Rückgabe von SMF_Game_Status::status()
 * @return string
 */
function smf_game_status_badge_html( $status ) {
    if ( $status === 'running' ) {
        return '<span class="smf-badge smf-badge--live"><span class="smf-badge__dot" aria-hidden="true"></span>'
            . esc_html( smf_label( 'live_badge_label', 'Live' ) ) . '</span>';
    }
    if ( $status === 'canceled' ) {
        return '<span class="smf-badge smf-badge--canceled">' . esc_html( smf_label( 'canceled_badge_label', 'Abgesagt' ) ) . '</span>';
    }
    return '';
}

/**
 * HTML-Fragment für einen Livestream-/Aufzeichnungs-Player oder -Link -
 * gemeinsam genutzt vom Spieldetail-Modal (game-detail.php) UND dem
 * Shortcode sm_livestream (livestream.php, v1.9.0), damit Markup/Klassen
 * für Platzhalter, Zwei-Klick-Reveal und "Einwilligung merken" nicht an
 * zwei Stellen auseinanderlaufen. Reine Darstellung - OB überhaupt etwas
 * übergeben wird (Option smf_stream_embed_mode, Link-Wahl je Spielstatus),
 * entscheiden die Aufrufer selbst (siehe dort).
 *
 * Die Überschrift ("Livestream"/"Aufzeichnung") ist bewusst NICHT Teil
 * dieser Funktion - die beiden Aufrufer platzieren/beschriften sie
 * unterschiedlich (Modal: feste Abschnittsüberschrift; sm_livestream:
 * zusätzlich zu einem optionalen freien Titel-Attribut).
 *
 * @param array{kind: string, parsed: array, embed_url: string} $stream Siehe game-detail.php/livestream.php für den Aufbau
 * @param string $embed_title Wert für das title-Attribut des iframes (Screenreader/Tab-Titel), z.B. "Livestream: Heim – Gast"
 * @return string
 */
function smf_render_stream_embed( array $stream, $embed_title ) {
    $provider       = isset( $stream['parsed']['provider'] ) ? $stream['parsed']['provider'] : '';
    $provider_label = ( ! empty( $stream['parsed']['provider_label'] ) ) ? $stream['parsed']['provider_label'] : ( isset( $stream['parsed']['host'] ) ? $stream['parsed']['host'] : '' );
    $link_url       = isset( $stream['parsed']['link_url'] ) ? $stream['parsed']['link_url'] : '';
    $embed_url      = isset( $stream['embed_url'] ) ? $stream['embed_url'] : '';

    $external_link_html = '<a class="smf-btn smf-stream-link" href="' . esc_url( $link_url ) . '" target="_blank" rel="noopener noreferrer">'
        . esc_html( sprintf( smf_label( 'stream_external_link', 'Auf %s ansehen' ), $provider_label ) )
        . '</a>';

    if ( $embed_url === '' ) {
        return '<p>' . $external_link_html . '</p>';
    }

    $remember_allowed = get_option( 'smf_stream_remember_consent', 'aus' ) === 'an';

    ob_start();
    ?>
    <div class="smf-stream-embed" data-provider="<?php echo esc_attr( $provider ); ?>">
        <div class="smf-stream-embed__placeholder">
            <svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M10 8l6 4-6 4V8z" fill="currentColor" stroke="none"/></svg>
            <p class="smf-stream-embed__notice">
                <?php
                echo esc_html( sprintf(
                    /* translators: %1$s: Anbieter, z.B. YouTube */
                    smf_label( 'stream_privacy_notice', 'Wird von %1$s eingebettet. Beim Laden werden Daten an %1$s übertragen.' ),
                    $provider_label
                ) );
                ?>
            </p>
            <?php if ( $remember_allowed ) : ?>
                <label class="smf-stream-embed__remember">
                    <input type="checkbox" class="smf-stream-remember-checkbox">
                    <?php
                    echo esc_html( sprintf(
                        /* translators: %s: Anbieter, z.B. YouTube */
                        smf_label( 'stream_remember_checkbox', '%s-Inhalte künftig immer laden' ),
                        $provider_label
                    ) );
                    ?>
                </label>
            <?php endif; ?>
            <button type="button" class="smf-btn smf-stream-reveal"
                    data-embed-url="<?php echo esc_url( $embed_url ); ?>"
                    data-embed-title="<?php echo esc_attr( $embed_title ); ?>">
                <?php echo esc_html( smf_label( 'stream_reveal_btn', 'Video laden' ) ); ?>
            </button>
            <a class="smf-stream-link" href="<?php echo esc_url( $link_url ); ?>" target="_blank" rel="noopener noreferrer">
                <?php echo esc_html( sprintf( smf_label( 'stream_external_link', 'Auf %s ansehen' ), $provider_label ) ); ?>
            </a>
        </div>
    </div>
    <?php
    return ob_get_clean();
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
        update_option( 'smf_cache_duration', 600 );
    }
}
register_activation_hook( __FILE__, 'smf_activate' );
