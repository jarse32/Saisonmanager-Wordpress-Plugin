<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin-Einstellungsseite
 */
class SMF_Admin {

    /** @var string Hook-Suffix der Design-Unterseite, siehe register_menu()/enqueue_design_assets() */
    private $design_hook = '';

    /**
     * Menüseite registrieren (auf admin_menu aufgerufen)
     */
    public function register_menu() {
        add_menu_page(
            'SM Floorball',
            'SM Floorball',
            'manage_options',
            'smf-settings',
            array( $this, 'render_settings_page' ),
            'dashicons-awards',
            30
        );

        $this->design_hook = add_submenu_page(
            'smf-settings',
            'SM Floorball – Design',
            'Design',
            'manage_options',
            'smf-design',
            array( $this, 'render_design_page' )
        );

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_design_assets' ) );
    }

    /**
     * admin_post_* Handler registrieren (auf admin_init aufgerufen,
     * damit sie bei admin-post.php-Requests rechtzeitig verfügbar sind)
     */
    public function register_post_handlers() {
        add_action( 'admin_post_smf_flush_cache',     array( $this, 'flush_cache' ) );
        add_action( 'admin_post_smf_flush_all_cache', array( $this, 'flush_all_cache' ) );
        add_action( 'admin_post_smf_save_vereine',    array( $this, 'save_vereine' ) );
    }

    /**
     * Erlaubte Werte für die Cache-Dauer (Sekunden => Anzeige-Label).
     * 10 Minuten Standard: der Saisonmanager-API-Key hat serverseitig
     * ohnehin ~10 Minuten Verzögerung, kürzer zu cachen liefert keine
     * aktuelleren Daten, nur mehr Anfragen an den Verbandsserver.
     *
     * @return array<int,string>
     */
    public static function cache_duration_options() {
        return array(
            300  => '5 Minuten',
            600  => '10 Minuten',
            900  => '15 Minuten',
            1800 => '30 Minuten',
            3600 => '60 Minuten',
        );
    }

    /**
     * Rundet einen Wert auf die nächstgelegene erlaubte Option. Wichtig für
     * Bestandsinstallationen: War smf_cache_duration vor der Umstellung auf
     * ein <select> ein freier Zahlenwert außerhalb der neuen Liste, würde
     * ohne diese Rundung beim ersten Speichern des Formulars stillschweigend
     * der Browser-Default (erste <option>) greifen, statt einer sinnvollen
     * Näherung an den bisherigen Wert.
     *
     * @param mixed $value
     * @param int[] $allowed
     * @return int
     */
    public static function round_to_nearest( $value, array $allowed ) {
        $value = (int) $value;
        if ( in_array( $value, $allowed, true ) ) {
            return $value;
        }

        $closest = $allowed[0];
        $diff    = abs( $value - $closest );
        foreach ( $allowed as $candidate ) {
            $d = abs( $value - $candidate );
            if ( $d < $diff ) {
                $diff    = $d;
                $closest = $candidate;
            }
        }
        return $closest;
    }

    public function register_settings() {
        register_setting( 'smf_settings_group', 'smf_api_base_url', [
            'sanitize_callback' => 'esc_url_raw',
            'default'           => 'https://saisonmanager.de/api/v2',
        ] );
        register_setting( 'smf_settings_group', 'smf_default_league_id', [
            'sanitize_callback' => 'absint',
        ] );
        register_setting( 'smf_settings_group', 'smf_cache_duration', [
            'sanitize_callback' => static function ( $val ) {
                return SMF_Admin::round_to_nearest( $val, array_keys( SMF_Admin::cache_duration_options() ) );
            },
            'default'           => 600,
        ] );
        register_setting( 'smf_settings_group', 'smf_api_timeout', [
            'sanitize_callback' => static function ( $val ) {
                $allowed = array( 3, 5, 6, 8, 10 );
                $val     = (int) $val;
                return in_array( $val, $allowed, true ) ? $val : 6;
            },
            'default'           => 6,
        ] );
        register_setting( 'smf_settings_group', 'smf_api_key', [
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ] );
        register_setting( 'smf_settings_group', 'smf_show_player_names', [
            'sanitize_callback' => static function ( $val ) {
                return ! empty( $val ) ? '1' : '';
            },
            'default'           => '',
        ] );
        register_setting( 'smf_settings_group', 'smf_player_name_format', [
            'sanitize_callback' => static function ( $val ) {
                return in_array( $val, array( 'voll', 'abgekuerzt' ), true ) ? $val : 'abgekuerzt';
            },
            'default'           => 'abgekuerzt',
        ] );

        register_setting( 'smf_design_group', 'smf_design', [
            'type'              => 'array',
            'sanitize_callback' => [ 'SMF_Design', 'sanitize' ],
            'default'           => SMF_Design::get_defaults(),
        ] );
    }

    public function enqueue_admin_assets( $hook ): void {
        if ( $hook !== 'toplevel_page_smf-settings' ) return;
        wp_enqueue_style( 'smf-admin', SMF_PLUGIN_URL . 'assets/css/admin.css', array(), SMF_VERSION );
        wp_enqueue_script( 'smf-admin-js', SMF_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), SMF_VERSION, true );

        wp_localize_script( 'smf-admin-js', 'smf_admin_data', array(
            'verein_count' => count( get_option( 'smf_vereine', array() ) ),
            'ajax_url'     => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'smf_admin_nonce' ),
        ) );
    }

    /**
     * Assets für die Design-Unterseite: Farb-Picker + das Frontend-
     * Stylesheet selbst (für die Live-Vorschau, die dasselbe Markup wie
     * dev/preview.html nutzt) + das gemeinsame Kontrast-Modul aus Phase 1b.
     */
    public function enqueue_design_assets( $hook ): void {
        if ( $hook !== $this->design_hook ) return;

        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_style( 'smf-admin', SMF_PLUGIN_URL . 'assets/css/admin.css', array(), SMF_VERSION );
        wp_enqueue_style( 'smf-style', SMF_PLUGIN_URL . 'assets/css/style.css', array(), SMF_VERSION );

        wp_enqueue_script(
            'smf-design-contrast',
            SMF_PLUGIN_URL . 'assets/js/design-contrast.js',
            array(),
            SMF_VERSION,
            true
        );
        wp_enqueue_script(
            'smf-admin-design',
            SMF_PLUGIN_URL . 'assets/js/admin-design.js',
            array( 'jquery', 'wp-color-picker', 'smf-design-contrast' ),
            SMF_VERSION,
            true
        );

        // Eichehorn/Neutral kommen aus SMF_Design, damit diese Werte nicht
        // zusätzlich in JS gepflegt werden müssen. "Dark" ist ein reines
        // UI-Preset ohne PHP-Pendant, siehe admin-design.js.
        wp_localize_script( 'smf-admin-design', 'smf_design_presets', array(
            'eichehorn' => SMF_Design::get_legacy_values(),
            'neutral'   => SMF_Design::get_defaults(),
        ) );
    }

    public function flush_cache(): void {
        check_admin_referer( 'smf_flush_cache' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Nicht erlaubt.' );

        ( new SMF_API() )->flush_cache();

        wp_safe_redirect( add_query_arg( [ 'page' => 'smf-settings', 'flushed' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Löscht zusätzlich die Notreserve (Langzeit-Spiegel) - eigener,
     * bewusst getrennter Knopf, damit ein alltägliches "Cache leeren" die
     * Versicherung gegen einen Serverausfall nicht versehentlich mitreißt.
     */
    public function flush_all_cache(): void {
        check_admin_referer( 'smf_flush_all_cache' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Nicht erlaubt.' );

        ( new SMF_API() )->flush_all_cache();

        wp_safe_redirect( add_query_arg( [ 'page' => 'smf-settings', 'flushed_all' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Vereine-Liste speichern (POST-Handler)
     */
    public function save_vereine(): void {
        check_admin_referer( 'smf_save_vereine' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Nicht erlaubt.' );

        $names    = isset( $_POST['smf_v_name'] )    ? (array) wp_unslash( $_POST['smf_v_name'] )    : array();
        $slugs    = isset( $_POST['smf_v_slug'] )    ? (array) wp_unslash( $_POST['smf_v_slug'] )    : array();
        $anzahls  = isset( $_POST['smf_v_anzahl'] )  ? (array) wp_unslash( $_POST['smf_v_anzahl'] )  : array();
        $club_ids = isset( $_POST['smf_v_club_id'] ) ? (array) wp_unslash( $_POST['smf_v_club_id'] ) : array();

        $t_team_ids = isset( $_POST['smf_v_t_team_id'] ) ? (array) wp_unslash( $_POST['smf_v_t_team_id'] ) : array();

        $vereine = array();
        foreach ( $names as $i => $name ) {
            $name    = sanitize_text_field( $name );
            $slug    = sanitize_key( isset( $slugs[ $i ] ) ? $slugs[ $i ] : '' );
            $anzahl  = max( 1, min( 20, absint( isset( $anzahls[ $i ] ) ? $anzahls[ $i ] : 4 ) ) );
            $club_id = absint( isset( $club_ids[ $i ] ) ? $club_ids[ $i ] : 0 );

            if ( $slug === '' ) {
                $slug = sanitize_title( $name );
            }

            // Manuelle Team-ID-Ergänzung (Fallback für Sonderfälle, die die
            // Club-ID-Erkennung nicht abdeckt).
            $teams = array();
            foreach ( (array) ( $t_team_ids[ $i ] ?? array() ) as $team_id ) {
                $team_id = absint( $team_id );
                if ( $team_id ) {
                    $teams[] = array( 'team_id' => $team_id );
                }
            }

            if ( $name !== '' || $club_id || ! empty( $teams ) ) {
                $vereine[] = array(
                    'name'    => $name,
                    'slug'    => $slug,
                    'anzahl'  => $anzahl,
                    'club_id' => $club_id,
                    'teams'   => $teams,
                );
            }
        }

        update_option( 'smf_vereine', $vereine );

        wp_safe_redirect( add_query_arg( array( 'page' => 'smf-settings', 'saved_verein' => '1' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * HTML-Fragment mit den zwischengespeicherten Teams einer Club-ID
     * rendern (Team-Name, Team-ID, Liga(en) mit ID). Wird sowohl beim
     * initialen Seitenaufbau (aus dem Cache) als auch als Vorlage für das
     * per AJAX aktualisierte Ergebnis genutzt (siehe admin.js).
     *
     * @param array $teams [{id, name, leagues:[{id,name,short_name}]}, ...]
     * @return string
     */
    public static function render_club_teams_list( array $teams ): string {
        if ( empty( $teams ) ) {
            return '<p class="smf-notice">Noch keine Teams geladen.</p>';
        }

        $html = '<ul class="smf-club-teams">';
        foreach ( $teams as $team ) {
            $html .= '<li><strong>' . esc_html( $team['name'] ?? '(ohne Namen)' ) . '</strong> ';
            $html .= '<code>' . esc_html( $team['id'] ?? '' ) . '</code>';

            $leagues = is_array( $team['leagues'] ?? null ) ? $team['leagues'] : array();
            if ( ! empty( $leagues ) ) {
                $parts = array();
                foreach ( $leagues as $league ) {
                    $parts[] = esc_html( ( $league['name'] ?? $league['short_name'] ?? 'Liga' ) . ' (' . ( $league['id'] ?? '?' ) . ')' );
                }
                $html .= '<br><small>Liga(en): ' . implode( ', ', $parts ) . '</small>';
            }
            $html .= '</li>';
        }
        $html .= '</ul>';

        return $html;
    }

    public function render_settings_page(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flags (presence check only, no form data processed); the actual actions (flush_cache(), save_vereine()) are nonce-checked in their own handlers.
        $flushed      = isset( $_GET['flushed'] );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- same as above, read-only display flag.
        $flushed_all  = isset( $_GET['flushed_all'] );
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- same as above, read-only display flag.
        $saved_verein = isset( $_GET['saved_verein'] );
        $vereine      = get_option( 'smf_vereine', array() );
        $teams_cache  = get_option( 'smf_club_teams_cache', array() );
        ?>
        <div class="wrap smf-admin-wrap">
            <h1>
                <span class="smf-logo">⚽</span>
                SM Floorball – Einstellungen
            </h1>

            <?php if ( $flushed ) : ?>
                <div class="notice notice-success is-dismissible"><p>Frische-Cache wurde erfolgreich geleert.</p></div>
            <?php endif; ?>
            <?php if ( $flushed_all ) : ?>
                <div class="notice notice-success is-dismissible"><p>Cache inklusive Notreserve wurde vollständig zurückgesetzt.</p></div>
            <?php endif; ?>
            <?php if ( $saved_verein ) : ?>
                <div class="notice notice-success is-dismissible"><p>Vereine gespeichert.</p></div>
            <?php endif; ?>

            <?php settings_errors( 'smf_settings_group' ); ?>

            <div class="smf-admin-grid">

                <!-- Allgemeine Einstellungen -->
                <div class="smf-admin-card">
                    <h2>Allgemeine Einstellungen</h2>
                    <form method="post" action="options.php">
                        <?php settings_fields( 'smf_settings_group' ); ?>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="smf_api_base_url">API-URL</label></th>
                                <td>
                                    <input type="url" id="smf_api_base_url" name="smf_api_base_url"
                                           value="<?php echo esc_attr( get_option( 'smf_api_base_url', 'https://saisonmanager.de/api/v2' ) ); ?>"
                                           class="regular-text">
                                    <p class="description">Basis-URL für alle Saisonmanager-Anfragen. Im Normalfall <code>https://saisonmanager.de/api/v2</code>.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="smf_api_key">Saisonmanager API-Key</label></th>
                                <td>
                                    <input type="text" id="smf_api_key" name="smf_api_key"
                                           value="<?php echo esc_attr( get_option( 'smf_api_key', '' ) ); ?>"
                                           class="regular-text" autocomplete="off">
                                    <p class="description">
                                        Eigener Key für dieses Projekt, beantragt unter
                                        <code>saisonmanager.de/api-zugang</code>. Wird nur serverseitig
                                        (WordPress-Backend) als <code>X-Api-Key</code>-Header mitgeschickt –
                                        erscheint nie im Seitenquelltext oder Browser der Besucher:innen.
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="smf_default_league_id">Standard-Liga-ID</label></th>
                                <td>
                                    <input type="number" id="smf_default_league_id" name="smf_default_league_id"
                                           value="<?php echo esc_attr( get_option( 'smf_default_league_id', '' ) ); ?>"
                                           class="small-text">
                                    <p class="description">Fallback wenn im Shortcode keine <code>liga_id</code> angegeben ist.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="smf_cache_duration">Cache-Dauer</label></th>
                                <td>
                                    <?php
                                    $cache_options  = self::cache_duration_options();
                                    $current_cache  = self::round_to_nearest( get_option( 'smf_cache_duration', 600 ), array_keys( $cache_options ) );
                                    ?>
                                    <select id="smf_cache_duration" name="smf_cache_duration">
                                        <?php foreach ( $cache_options as $val => $label ) : ?>
                                            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current_cache, $val ); ?>><?php echo esc_html( $label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">
                                        Der Saisonmanager-API-Key hat serverseitig ohnehin ~10 Minuten
                                        Verzögerung – kürzer zu cachen liefert keine aktuelleren Daten, nur
                                        mehr Anfragen an den Verbandsserver.
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="smf_api_timeout">Timeout</label></th>
                                <td>
                                    <?php $current_timeout = (int) get_option( 'smf_api_timeout', 6 ); ?>
                                    <select id="smf_api_timeout" name="smf_api_timeout">
                                        <?php foreach ( array( 3, 5, 6, 8, 10 ) as $val ) : ?>
                                            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $current_timeout, $val ); ?>><?php echo esc_html( $val ); ?> Sekunden</option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">
                                        Wie lange auf eine Antwort des Verbandsservers gewartet wird, bevor
                                        abgebrochen wird. Bewusst kurz halten: Ist der Server nicht
                                        erreichbar, soll die eigene Seite trotzdem schnell laden – bei
                                        mehreren Shortcodes auf einer Seite summiert sich sonst die
                                        Wartezeit, bis die Seite selbst in ein Timeout läuft.
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Personennamen anzeigen</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="smf_show_player_names" value="1"
                                               <?php checked( get_option( 'smf_show_player_names', '' ), '1' ); ?>>
                                        Namen von Spieler:innen und Schiedsrichter:innen anzeigen
                                    </label>
                                    <p class="description">
                                        Betrifft das Spieldetail-Modal (Spielverlauf) <strong>und</strong> den
                                        Shortcode <code>[sm_scorer]</code>. Standard: aus. Der Verein ist für die
                                        Veröffentlichung dieser personenbezogenen Daten selbst verantwortlich und
                                        benötigt dafür eine Rechtsgrundlage (Art. 6 DSGVO) – das kann auch
                                        minderjährige Spieler:innen betreffen.
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="smf_player_name_format">Format der Personennamen</label></th>
                                <td>
                                    <select id="smf_player_name_format" name="smf_player_name_format">
                                        <option value="abgekuerzt" <?php selected( get_option( 'smf_player_name_format', 'abgekuerzt' ), 'abgekuerzt' ); ?>>Abgekürzt (z.&nbsp;B. „Max M.“)</option>
                                        <option value="voll" <?php selected( get_option( 'smf_player_name_format', 'abgekuerzt' ), 'voll' ); ?>>Voller Name</option>
                                    </select>
                                    <p class="description">
                                        Greift, sobald „Personennamen anzeigen“ oben aktiviert ist. Im Shortcode
                                        <code>[sm_scorer]</code> pro Einbindung über <code>namen="voll"</code> bzw.
                                        <code>namen="abgekuerzt"</code> übersteuerbar – der Schalter „Personennamen
                                        anzeigen“ selbst nicht.
                                    </p>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button( 'Einstellungen speichern' ); ?>
                    </form>
                </div>

                <!-- Status -->
                <div class="smf-admin-card">
                    <h2>Status</h2>
                    <?php
                    $breaker      = SMF_API::get_breaker_status();
                    $last_outage  = (int) get_option( 'smf_api_last_outage', 0 );
                    $stale_stats  = ( new SMF_Cache() )->get_stale_stats();
                    ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Verbandsserver</th>
                            <td>
                                <?php if ( $breaker['open'] ) : ?>
                                    🔴 Gesperrt (<code><?php echo esc_html( $breaker['host'] ); ?></code>) –
                                    nächster Versuch ab <?php echo esc_html( date_i18n( 'd.m.Y H:i', $breaker['open_until'] ) ); ?> Uhr.
                                <?php else : ?>
                                    🟢 Erreichbar
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Letzter Ausfall</th>
                            <td>
                                <?php echo $last_outage ? esc_html( date_i18n( 'd.m.Y H:i', $last_outage ) . ' Uhr' ) : 'Bisher keiner erfasst.'; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Notreserve</th>
                            <td>
                                <?php echo esc_html( $stale_stats['count'] ); ?> Einträge
                                <?php if ( $stale_stats['oldest_age'] !== null ) : ?>
                                    , ältester Stand vor <?php echo esc_html( human_time_diff( time() - $stale_stats['oldest_age'], time() ) ); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Cache -->
                <div class="smf-admin-card">
                    <h2>Cache</h2>
                    <p>API-Antworten werden gecacht, um Server-Last zu reduzieren. Nach Änderungen an der API-Konfiguration den Frische-Cache leeren.</p>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="smf_flush_cache">
                        <?php wp_nonce_field( 'smf_flush_cache' ); ?>
                        <?php submit_button( 'Frische-Cache leeren', 'secondary' ); ?>
                    </form>

                    <p style="margin-top:1.5rem;">
                        <strong>Cache vollständig zurücksetzen</strong> löscht zusätzlich die Notreserve
                        (Langzeit-Spiegel) – die Versicherung gegen einen Ausfall des Verbandsservers.
                        Danach gibt es bis zum nächsten erfolgreichen Abruf keine Ersatzdaten mehr. Nur
                        nutzen, wenn der Verbandsserver aktuell sicher erreichbar ist.
                    </p>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                          onsubmit="return confirm('Wirklich den kompletten Cache inkl. Notreserve löschen? Bei einem Ausfall des Verbandsservers gibt es bis zum nächsten erfolgreichen Abruf dann keine Ersatzdaten mehr.');">
                        <input type="hidden" name="action" value="smf_flush_all_cache">
                        <?php wp_nonce_field( 'smf_flush_all_cache' ); ?>
                        <?php submit_button( 'Cache vollständig zurücksetzen', 'secondary' ); ?>
                    </form>
                </div>

                <!-- Team-Finder -->
                <div class="smf-admin-card smf-admin-card--full">
                    <h2>Team-Finder</h2>
                    <p>
                        Findet Club-IDs und Team-IDs: Vereinsname (Teilstring reicht) oder Club-ID eingeben,
                        optional eine Saison-ID für historische Daten (leer = aktuelle Saison). Durchsucht
                        alle bekannten Spielbetriebsstellen – kann beim ersten Aufruf ein paar Sekunden
                        dauern. Die gefundene Club-ID kannst du direkt unten bei einem Verein eintragen.
                    </p>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="smf-tf-query">Vereinsname oder Club-ID</label></th>
                            <td><input type="text" id="smf-tf-query" class="regular-text" placeholder="z.B. Eiche Horn"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smf-tf-season">Saison-ID <small>(optional)</small></label></th>
                            <td>
                                <input type="number" id="smf-tf-season" class="small-text" placeholder="leer = aktuell">
                                <button type="button" class="button" id="smf-tf-season-current">Aktuelle Saison</button>
                                <button type="button" class="button" id="smf-tf-season-prev">Vorsaison</button>
                                <p class="description" id="smf-tf-season-hint">Aktuelle Saison-ID wird ermittelt…</p>
                            </td>
                        </tr>
                    </table>
                    <p><button type="button" class="button button-primary" id="smf-tf-search">Suchen</button></p>
                    <div id="smf-tf-results"></div>
                </div>

                <!-- Vereine -->
                <div class="smf-admin-card smf-admin-card--full">
                    <h2>Vereine</h2>
                    <p>
                        Lege hier Vereine an. Über den Shortcode <code>[sm_vereinsuebersicht]</code> kannst du
                        die Übersicht (alle anstehenden &amp; gespielten Spiele) dann auf jeder Seite einbinden.
                    </p>

                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="smf-vereine-form">
                        <input type="hidden" name="action" value="smf_save_vereine">
                        <?php wp_nonce_field( 'smf_save_vereine' ); ?>

                        <div id="smf-vereine-container">
                            <?php foreach ( $vereine as $vi => $verein ) :
                                $club_id      = (int) ( $verein['club_id'] ?? 0 );
                                $cached_teams = isset( $teams_cache[ $club_id ]['teams'] ) ? $teams_cache[ $club_id ]['teams'] : array();
                                $updated_at   = isset( $teams_cache[ $club_id ]['updated_at'] ) ? $teams_cache[ $club_id ]['updated_at'] : 0;
                            ?>
                                <div class="smf-verein-block" data-verein-index="<?php echo esc_attr( $vi ); ?>">
                                    <div class="smf-verein-block__header">
                                        <strong class="smf-verein-block__title">
                                            <?php echo esc_html( $verein['name'] ?: 'Verein ' . ( $vi + 1 ) ); ?>
                                        </strong>
                                        <button type="button" class="button smf-remove-verein">&#10005; Verein entfernen</button>
                                    </div>

                                    <table class="form-table smf-verein-fields">
                                        <tr>
                                            <th><label>Vereinsname</label></th>
                                            <td>
                                                <input type="text"
                                                       name="smf_v_name[<?php echo esc_attr( $vi ); ?>]"
                                                       value="<?php echo esc_attr( $verein['name'] ); ?>"
                                                       class="regular-text smf-verein-name-input"
                                                       placeholder="z.B. Floorball Hannover">
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label>Slug <small>(für Shortcode)</small></label></th>
                                            <td>
                                                <input type="text"
                                                       name="smf_v_slug[<?php echo esc_attr( $vi ); ?>]"
                                                       value="<?php echo esc_attr( $verein['slug'] ); ?>"
                                                       class="regular-text"
                                                       placeholder="z.B. hannover"
                                                       pattern="[a-z0-9\-]+"
                                                       title="Nur Kleinbuchstaben, Zahlen und Bindestriche">
                                                <p class="description">Wird im Shortcode genutzt: <code>[sm_vereinsuebersicht verein="<?php echo esc_attr( $verein['slug'] ?: 'slug' ); ?>"]</code></p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label>Anzahl Spiele</label></th>
                                            <td>
                                                <input type="number"
                                                       name="smf_v_anzahl[<?php echo esc_attr( $vi ); ?>]"
                                                       value="<?php echo esc_attr( $verein['anzahl'] ?? 4 ); ?>"
                                                       class="small-text" min="1" max="20">
                                                <p class="description">Wie viele anstehende / gespielte Spiele pro Spalte angezeigt werden.</p>
                                            </td>
                                        </tr>
                                        <tr>
                                            <th><label>Club-ID</label></th>
                                            <td>
                                                <input type="number"
                                                       name="smf_v_club_id[<?php echo esc_attr( $vi ); ?>]"
                                                       value="<?php echo esc_attr( $club_id ?: '' ); ?>"
                                                       class="small-text smf-club-id-input" placeholder="z.B. 163">
                                                <button type="button" class="button smf-sync-club-teams" data-verein-index="<?php echo esc_attr( $vi ); ?>">Teams laden</button>
                                                <p class="description">
                                                    Saisonmanager-Club-ID (per Team-Finder oben herausfinden). "Teams
                                                    laden" ermittelt automatisch alle Teams dieses Vereins inkl. ihrer
                                                    aktuellen Liga-IDs.
                                                    <?php if ( $updated_at ) : ?>
                                                        Zuletzt geladen: <?php echo esc_html( date_i18n( 'd.m.Y H:i', $updated_at ) ); ?>.
                                                    <?php endif; ?>
                                                </p>
                                                <div class="smf-club-teams-results"><?php echo self::render_club_teams_list( $cached_teams ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_club_teams_list() escapes all dynamic values internally (esc_html()); echoing its return value here would double-encode nothing but wrapping it in esc_html() would destroy the returned markup. ?></div>
                                            </td>
                                        </tr>
                                    </table>

                                    <h4 style="margin: 1rem 0 0.5rem;">Manuelle Team-IDs <small>(optional, Fallback)</small></h4>
                                    <p class="description">
                                        Nur nötig, wenn "Teams laden" ein Team nicht findet (z.B. bei
                                        Spielgemeinschaften). Normalerweise reicht die Club-ID oben.
                                    </p>
                                    <table class="widefat smf-teams-table">
                                        <thead><tr><th>Team-ID</th><th></th></tr></thead>
                                        <tbody class="smf-teams-body">
                                            <?php foreach ( $verein['teams'] ?? array() as $team ) : ?>
                                                <tr class="smf-team-row">
                                                    <td>
                                                        <input type="number"
                                                               name="smf_v_t_team_id[<?php echo esc_attr( $vi ); ?>][]"
                                                               value="<?php echo esc_attr( $team['team_id'] ?? '' ); ?>"
                                                               class="small-text" placeholder="z.B. 6754">
                                                    </td>
                                                    <td><button type="button" class="button smf-remove-team">&#10005;</button></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <p>
                                        <button type="button" class="button smf-add-team"
                                                data-verein-index="<?php echo esc_attr( $vi ); ?>">
                                            + Team-ID hinzufügen
                                        </button>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <p>
                            <button type="button" class="button" id="smf-add-verein">+ Verein hinzufügen</button>
                        </p>

                        <?php submit_button( 'Vereine speichern', 'primary', 'smf_save_vereine_btn' ); ?>
                    </form>

                    <!-- Versteckte Templates für JavaScript -->
                    <div id="smf-verein-template" style="display:none;">
                        <div class="smf-verein-block" data-verein-index="__VI__">
                            <div class="smf-verein-block__header">
                                <strong class="smf-verein-block__title">Neuer Verein</strong>
                                <button type="button" class="button smf-remove-verein">&#10005; Verein entfernen</button>
                            </div>
                            <table class="form-table smf-verein-fields">
                                <tr>
                                    <th><label>Vereinsname</label></th>
                                    <td><input type="text" name="smf_v_name[__VI__]" class="regular-text smf-verein-name-input" placeholder="z.B. Floorball Hannover"></td>
                                </tr>
                                <tr>
                                    <th><label>Slug <small>(für Shortcode)</small></label></th>
                                    <td>
                                        <input type="text" name="smf_v_slug[__VI__]" class="regular-text" placeholder="z.B. hannover" pattern="[a-z0-9\-]+" title="Nur Kleinbuchstaben, Zahlen und Bindestriche">
                                        <p class="description">Wird im Shortcode genutzt: <code>[sm_vereinsuebersicht verein="slug"]</code></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label>Anzahl Spiele</label></th>
                                    <td>
                                        <input type="number" name="smf_v_anzahl[__VI__]" value="4" class="small-text" min="1" max="20">
                                        <p class="description">Wie viele anstehende / gespielte Spiele pro Spalte angezeigt werden.</p>
                                    </td>
                                </tr>
                                <tr>
                                    <th><label>Club-ID</label></th>
                                    <td>
                                        <input type="number" name="smf_v_club_id[__VI__]" class="small-text smf-club-id-input" placeholder="z.B. 163">
                                        <button type="button" class="button smf-sync-club-teams" data-verein-index="__VI__">Teams laden</button>
                                        <p class="description">Saisonmanager-Club-ID (per Team-Finder oben herausfinden).</p>
                                        <div class="smf-club-teams-results"><p class="smf-notice">Noch keine Teams geladen.</p></div>
                                    </td>
                                </tr>
                            </table>
                            <h4 style="margin: 1rem 0 0.5rem;">Manuelle Team-IDs <small>(optional, Fallback)</small></h4>
                            <table class="widefat smf-teams-table">
                                <thead><tr><th>Team-ID</th><th></th></tr></thead>
                                <tbody class="smf-teams-body"></tbody>
                            </table>
                            <p>
                                <button type="button" class="button smf-add-team" data-verein-index="__VI__">+ Team-ID hinzufügen</button>
                            </p>
                        </div>
                    </div>

                    <div id="smf-team-row-template" style="display:none;">
                        <table><tbody>
                            <tr class="smf-team-row">
                                <td><input type="number" name="smf_v_t_team_id[__VI__][]" class="small-text" placeholder="z.B. 6754"></td>
                                <td><button type="button" class="button smf-remove-team">&#10005;</button></td>
                            </tr>
                        </tbody></table>
                    </div>
                </div>

                <!-- Shortcode-Referenz -->
                <div class="smf-admin-card smf-admin-card--full">
                    <h2>Shortcode-Referenz</h2>
                    <table class="widefat smf-shortcode-table">
                        <thead>
                            <tr><th>Shortcode</th><th>Beschreibung</th><th>Parameter</th></tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>[sm_tabelle liga_id="123"]</code></td>
                                <td>Liga-Tabelle anzeigen</td>
                                <td>
                                    <code>liga_id</code>, <code>titel</code>, <code>logos</code> (true/false, Standard: false),
                                    <code>logo_groesse</code> (klein/mittel/gross/sehr_gross, Standard: mittel),
                                    <code>hervorheben</code> (leer = eigene Vereine automatisch, <code>false</code> = aus,
                                    Team-ID/Namensfragment = genau dieses Team)
                                </td>
                            </tr>
                            <tr>
                                <td><code>[sm_spiele liga_id="123"]</code></td>
                                <td>Spielplan einer Liga</td>
                                <td>
                                    <code>liga_id</code>, <code>anzahl</code>, <code>team</code>, <code>modus</code> (alle/vergangen/kommend),
                                    <code>titel</code>, <code>logos</code> (true/false, Standard: false),
                                    <code>namen</code> (true/false, Standard: true), <code>logo_groesse</code>
                                    (klein/mittel/gross/sehr_gross, Standard: mittel), <code>hervorheben</code>
                                    (leer = eigene Vereine automatisch, <code>false</code> = aus,
                                    Team-ID/Namensfragment = genau dieses Team)
                                </td>
                            </tr>
                            <tr>
                                <td><code>[sm_naechstes_spiel liga_id="123"]</code></td>
                                <td>Nächstes kommendes Spiel</td>
                                <td>
                                    <code>liga_id</code>, <code>team</code>, <code>logos</code> (true/false, Standard: true),
                                    <code>namen</code> (true/false, Standard: true), <code>logo_groesse</code>
                                    (klein/mittel/gross/sehr_gross, Standard: mittel). <code>hervorheben</code> wird
                                    akzeptiert, hat hier aber keinen sichtbaren Effekt (nur zwei Teams gleichzeitig,
                                    kein "unter vielen" wie bei Tabelle/Spielplan)
                                </td>
                            </tr>
                            <tr>
                                <td><code>[sm_letztes_spiel liga_id="123"]</code></td>
                                <td>Letztes gespieltes Spiel</td>
                                <td>
                                    <code>liga_id</code>, <code>team</code>, <code>logos</code> (true/false, Standard: true),
                                    <code>namen</code> (true/false, Standard: true), <code>logo_groesse</code>
                                    (klein/mittel/gross/sehr_gross, Standard: mittel). <code>hervorheben</code> wird
                                    akzeptiert, hat hier aber keinen sichtbaren Effekt (wie bei <code>sm_naechstes_spiel</code>)
                                </td>
                            </tr>
                            <tr>
                                <td><code>[sm_spiel_duo liga_id="123"]</code></td>
                                <td>Nächstes und letztes Spiel nebeneinander in einem gemeinsamen Grid (bricht auf
                                    schmalem Platz automatisch untereinander um)</td>
                                <td>
                                    Dieselben Parameter wie <code>sm_naechstes_spiel</code>/<code>sm_letztes_spiel</code>,
                                    zusätzlich <code>reihenfolge</code> (<code>naechstes-zuerst</code>/<code>letztes-zuerst</code>,
                                    Standard: naechstes-zuerst)
                                </td>
                            </tr>
                            <tr>
                                <td><code>[sm_vereinsuebersicht verein="hannover"]</code></td>
                                <td>Vereinsübersicht: anstehende &amp; gespielte Spiele aller Teams</td>
                                <td>
                                    <code>verein</code> (Slug oder Name, Standard: erster Verein), <code>anzahl</code>
                                    (überschreibt Backend-Einstellung), <code>namen</code> (true/false, Standard: true),
                                    <code>logo_groesse</code> (klein/mittel/gross/sehr_gross, Standard: mittel),
                                    <code>hervorheben</code> (Standard: <code>false</code> - hier ist ohnehin jedes
                                    Spiel eins der eigenen Teams; per Team-ID/Namensfragment trotzdem gezielt
                                    nutzbar, z.B. um nur die 1. Mannschaft zu markieren)
                                </td>
                            </tr>
                            <tr>
                                <td><code>[sm_scorer team_id="6754"]</code></td>
                                <td>Scorerliste (Punkteliste) eines Teams, über alle Wettbewerbe der Saison</td>
                                <td>
                                    <code>team_id</code> (Pflicht), <code>anzahl</code> (0 = alle), <code>spalten</code>
                                    (voll/kompakt), <code>namen</code> (voll/abgekuerzt, überschreibt nur das Format,
                                    nicht ob Namen überhaupt erscheinen), <code>titel</code>, <code>summe</code>
                                    (true/false, Summenzeile aus Team-Gesamtwerten)
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="description">
                        Die <code>liga_id</code> für <code>sm_tabelle</code>/<code>sm_spiele</code>/etc. findest du
                        über den Team-Finder oben (Spalte "Liga(en)" bei den gefundenen Teams) oder über den
                        "Teams laden"-Button bei einem Verein. Die <code>team_id</code> für
                        <code>[sm_scorer]</code>/<code>[sm_vereinsuebersicht]</code> findest du an derselben Stelle.
                    </p>
                    <p class="description">
                        Beispiel: <code>[sm_scorer team_id="6754" anzahl="10" spalten="kompakt" namen="abgekuerzt"]</code>
                    </p>
                    <p class="description">
                        Normalfall Team-Highlighting: <code>[sm_tabelle liga_id="123"]</code> ohne
                        <code>hervorheben</code> markiert automatisch die Zeile(n) der oben konfigurierten Vereine -
                        kein zusätzliches Attribut nötig. Für eine Seite, die nur die 1. Mannschaft markieren soll
                        (z.B. wenn mehrere eigene Teams in dieselbe Tabelle einsortiert sind), stattdessen die
                        konkrete Team-ID setzen: <code>[sm_tabelle liga_id="123" hervorheben="9667"]</code>.
                    </p>
                    <p class="description">
                        Beispiel für eine Startseite mit größeren Logos:
                        <code>[sm_vereinsuebersicht verein="hannover" logo_groesse="gross"]</code>. Wichtig, wenn
                        mehrere eigene Teams dasselbe Vereinslogo tragen (z.B. 1. und 2. Mannschaft): dann bitte
                        <code>namen</code> auf dem Standard <code>true</code> belassen, sonst sind Begegnungen der
                        beiden Teams am identischen Logo nicht mehr unterscheidbar. <code>namen="false"</code> eignet
                        sich nur, wenn die gegenüberstehenden Teams tatsächlich unterschiedliche Logos haben.
                    </p>
                    <p class="description">
                        <strong>Datenschutz:</strong> <code>[sm_scorer]</code> zeigt – anders als die übrigen
                        Shortcodes – dauerhaft die Klarnamen von Spieler:innen an, ggf. auch von Minderjährigen. Die
                        Liste erscheint nur, wenn oben unter "Allgemeine Einstellungen" die Option "Personennamen
                        anzeigen" aktiviert ist. Der jeweilige Verein ist für die Veröffentlichung dieser
                        personenbezogenen Daten selbst verantwortlich und benötigt dafür eine Rechtsgrundlage;
                        <code>namen="abgekuerzt"</code> bzw. die Einstellung "Format der Personennamen" reduzieren den
                        Personenbezug, ersetzen aber keine eigene rechtliche Prüfung.
                    </p>
                </div>

            </div>
        </div>
        <?php
    }

    /**
     * Design-Unterseite: Farben, Form und Schrift für die Frontend-Ausgabe
     * dieser Installation. Ein Formular über die WordPress-Settings-API
     * (register_setting mit SMF_Design::sanitize als Callback) - kein
     * eigener admin_post-Handler, damit das Sanitizing nur an einer Stelle
     * existiert.
     */
    public function render_design_page(): void {
        $cfg = SMF_Design::get();
        ?>
        <div class="wrap smf-admin-wrap">
            <h1>
                <span class="smf-logo">🎨</span>
                SM Floorball – Design
            </h1>
            <p class="description">
                Farben, Formen und Schrift für die Shortcode-Ausgabe dieser Installation.
                Gilt global für alle hier konfigurierten Vereine/Teams - für API-Key und
                Vereine siehe <a href="<?php echo esc_url( admin_url( 'admin.php?page=smf-settings' ) ); ?>">Allgemeine Einstellungen</a>.
            </p>

            <?php settings_errors( 'smf_design_group' ); ?>

            <div class="smf-design-layout">

                <div class="smf-admin-card smf-design-form-card">

                    <h2>Presets</h2>
                    <p class="description">
                        Befüllt die Felder unten mit Beispielwerten - wirksam erst nach dem
                        Speichern, überschreibt ungespeicherte Eingaben in den Feldern darunter.
                    </p>
                    <p>
                        <button type="button" class="button" data-smf-preset="eichehorn">Eichehorn</button>
                        <button type="button" class="button" data-smf-preset="neutral">Neutral</button>
                        <button type="button" class="button" data-smf-preset="dark">Dark</button>
                    </p>

                    <form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" id="smf-design-form">
                        <?php settings_fields( 'smf_design_group' ); ?>

                        <h2>Farben</h2>
                        <table class="form-table">
                            <?php
                            $this->render_design_color_row( 'color_primary', 'Primary', $cfg['color_primary'] );
                            $this->render_design_color_row( 'color_accent', 'Accent', $cfg['color_accent'] );
                            $this->render_design_color_row( 'color_surface', 'Surface (Kartenhintergrund)', $cfg['color_surface'] );
                            $this->render_design_color_row( 'color_surface_alt', 'Surface Alt (z.B. Zeilenwechsel)', $cfg['color_surface_alt'] );
                            $this->render_design_color_row( 'color_border', 'Rahmen', $cfg['color_border'] );
                            $this->render_design_color_row( 'color_border_strong', 'Rahmen (kräftig)', $cfg['color_border_strong'] );
                            $this->render_design_color_row( 'color_text', 'Text', $cfg['color_text'] );
                            $this->render_design_color_row( 'color_text_muted', 'Text (gedämpft)', $cfg['color_text_muted'] );
                            ?>
                        </table>

                        <h2>Kontrast</h2>
                        <table class="form-table">
                            <?php
                            $this->render_design_contrast_row( 'contrast_on_primary', 'Textfarbe auf Primary', $cfg['contrast_on_primary'] );
                            $this->render_design_contrast_row( 'contrast_on_accent', 'Textfarbe auf Accent', $cfg['contrast_on_accent'] );
                            ?>
                        </table>
                        <p class="description">
                            "Automatisch" berechnet den Kontrast anhand der relativen Luminanz
                            (WCAG) der jeweiligen Fläche und wählt hellen oder dunklen Text.
                        </p>

                        <h2>Form</h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="smf-design-radius">Eckenradius</label></th>
                                <td>
                                    <input type="number" id="smf-design-radius" name="smf_design[radius]"
                                           value="<?php echo esc_attr( $cfg['radius'] ); ?>"
                                           min="0" max="24" class="small-text"> px
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="smf-design-shadow">Schatten-Intensität</label></th>
                                <td>
                                    <select id="smf-design-shadow" name="smf_design[shadow_intensity]">
                                        <?php foreach ( array( 'none' => 'Keine', 'soft' => 'Dezent', 'normal' => 'Normal', 'strong' => 'Stark' ) as $val => $label ) : ?>
                                            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $cfg['shadow_intensity'], $val ); ?>><?php echo esc_html( $label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </table>

                        <h2>Schrift</h2>
                        <table class="form-table">
                            <?php
                            $this->render_design_font_row( 'font_mode', 'font_custom', 'Fließtext', $cfg['font_mode'], $cfg['font_custom'] );
                            $this->render_design_font_row( 'font_title_mode', 'font_title_custom', 'Titel', $cfg['font_title_mode'], $cfg['font_title_custom'] );
                            ?>
                            <tr>
                                <th scope="row"><label for="smf-design-fontsize">Schriftgröße</label></th>
                                <td>
                                    <select id="smf-design-fontsize" name="smf_design[font_size]">
                                        <?php foreach ( array( 14 => 'Klein (14px)', 16 => 'Normal (16px)', 18 => 'Groß (18px)', 20 => 'Sehr groß (20px)' ) as $val => $label ) : ?>
                                            <option value="<?php echo esc_attr( $val ); ?>" <?php selected( (int) $cfg['font_size'], $val ); ?>><?php echo esc_html( $label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        <p class="description">
                            Es werden keine externen Schriften nachgeladen. "Theme-Schrift
                            übernehmen" erbt die Schrift der restlichen Seite; die System-Optionen
                            und ein eigener Font-Stack greifen nur auf Schriften zu, die das
                            Theme oder Betriebssystem der Besucher:innen bereits mitbringt.
                        </p>

                        <?php submit_button( 'Design speichern' ); ?>
                    </form>
                </div>

                <div class="smf-admin-card smf-design-preview-card">
                    <h2>Live-Vorschau</h2>
                    <p class="description">Reagiert sofort auf Änderungen oben, noch ungespeichert.</p>

                    <div class="smf" id="smf-design-preview-root">

                        <div class="smf-table-wrapper">
                            <div class="smf-header">
                                <h3 class="smf-title">Regionalliga Nord – Tabelle</h3>
                            </div>
                            <div class="smf-table-scroll">
                                <table class="smf-table">
                                    <thead>
                                        <tr>
                                            <th class="smf-col-rank">#</th>
                                            <th class="smf-col-team">Team</th>
                                            <th class="smf-col-num">Sp</th>
                                            <th class="smf-col-num smf-col-pts">Pkt</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr class="smf-table-row smf-row-even">
                                            <td class="smf-col-rank">1</td>
                                            <td class="smf-col-team smf-team-name">Beispiel SV Nord</td>
                                            <td class="smf-col-num">18</td>
                                            <td class="smf-col-num smf-col-pts"><strong>46</strong></td>
                                        </tr>
                                        <tr class="smf-table-row">
                                            <td class="smf-col-rank">2</td>
                                            <td class="smf-col-team smf-team-name">TSV Musterstadt</td>
                                            <td class="smf-col-num">18</td>
                                            <td class="smf-col-num smf-col-pts"><strong>37</strong></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="smf-games-list" style="margin-top:1rem;">
                            <div class="smf-game-card smf-game--played" role="button" tabindex="0">
                                <div class="smf-game-meta">
                                    <span class="smf-game-date">14.09.2026</span>
                                    <span class="smf-game-day">Spieltag 5</span>
                                </div>
                                <div class="smf-game-matchup">
                                    <div class="smf-game-team smf-game-team--home">
                                        <span class="smf-team-name">Beispiel SV Nord</span>
                                    </div>
                                    <div class="smf-game-score"><span class="smf-score">7 : 4</span></div>
                                    <div class="smf-game-team smf-game-team--away">
                                        <span class="smf-team-name">TSV Musterstadt</span>
                                    </div>
                                </div>
                                <div class="smf-game-action">
                                    <span class="smf-detail-link">Spielbericht</span>
                                </div>
                            </div>
                        </div>

                        <div class="smf-single-game smf-game--upcoming" style="margin-top:1rem;" role="button" tabindex="0">
                            <div class="smf-single-game__label">
                                <span>Nächstes Spiel</span>
                                <span class="smf-single-game__league">Regionalliga Nord</span>
                            </div>
                            <div class="smf-single-game__date-row">
                                <span class="smf-single-game__date-day">Montag</span>
                                <span class="smf-single-game__date-full">21. September 2026</span>
                                <span class="smf-single-game__date-time">19:30 Uhr</span>
                            </div>
                            <div class="smf-single-game__matchup">
                                <div class="smf-single-game__team smf-single-game__team--home">
                                    <span class="smf-single-game__team-name">Beispielhausen</span>
                                </div>
                                <div class="smf-single-game__center">
                                    <div class="smf-single-game__score smf-single-game__score--upcoming">vs.</div>
                                </div>
                                <div class="smf-single-game__team smf-single-game__team--away">
                                    <span class="smf-single-game__team-name">Beispiel SV Nord</span>
                                </div>
                            </div>
                            <div class="smf-single-game__action">
                                <span class="smf-btn">Details ansehen</span>
                            </div>
                        </div>

                        <p class="smf-attribution">Daten: Saisonmanager / Floorball Verband Deutschland e. V. – inoffizielles Community-Projekt.</p>
                    </div>
                </div>

            </div>
        </div>
        <?php
    }

    /**
     * Eine Farbfeld-Zeile (wp-color-picker) für die Design-Seite.
     */
    private function render_design_color_row( string $key, string $label, string $value ): void {
        ?>
        <tr>
            <th scope="row"><label for="smf-design-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
            <td>
                <input type="text"
                       id="smf-design-<?php echo esc_attr( $key ); ?>"
                       name="smf_design[<?php echo esc_attr( $key ); ?>]"
                       value="<?php echo esc_attr( $value ); ?>"
                       class="smf-design-color-field"
                       data-default-color="<?php echo esc_attr( $value ); ?>">
            </td>
        </tr>
        <?php
    }

    /**
     * Eine Kontrast-Radiogroup (Automatisch/Hell/Dunkel) für die Design-Seite.
     */
    private function render_design_contrast_row( string $key, string $label, string $value ): void {
        ?>
        <tr>
            <th scope="row"><?php echo esc_html( $label ); ?></th>
            <td>
                <?php foreach ( array( 'auto' => 'Automatisch', 'light' => 'Hell', 'dark' => 'Dunkel' ) as $mode => $mode_label ) : ?>
                    <label style="margin-right:1.25rem;">
                        <input type="radio"
                               name="smf_design[<?php echo esc_attr( $key ); ?>]"
                               value="<?php echo esc_attr( $mode ); ?>"
                               <?php checked( $value, $mode ); ?>>
                        <?php echo esc_html( $mode_label ); ?>
                    </label>
                <?php endforeach; ?>
            </td>
        </tr>
        <?php
    }

    /**
     * Eine Schrift-Zeile (Modus-Select + optionales Freitextfeld für den
     * eigenen Font-Stack) für die Design-Seite.
     */
    private function render_design_font_row( string $mode_key, string $custom_key, string $label, string $mode_value, string $custom_value ): void {
        ?>
        <tr>
            <th scope="row"><label for="smf-design-<?php echo esc_attr( $mode_key ); ?>"><?php echo esc_html( $label ); ?></label></th>
            <td>
                <select id="smf-design-<?php echo esc_attr( $mode_key ); ?>"
                        name="smf_design[<?php echo esc_attr( $mode_key ); ?>]"
                        class="smf-design-font-mode"
                        data-target="smf-design-<?php echo esc_attr( $custom_key ); ?>-row">
                    <option value="inherit"      <?php selected( $mode_value, 'inherit' ); ?>>Theme-Schrift übernehmen</option>
                    <option value="system-sans"  <?php selected( $mode_value, 'system-sans' ); ?>>System Sans</option>
                    <option value="system-serif" <?php selected( $mode_value, 'system-serif' ); ?>>System Serif</option>
                    <option value="custom"       <?php selected( $mode_value, 'custom' ); ?>>Eigener Font-Stack</option>
                </select>
                <div id="smf-design-<?php echo esc_attr( $custom_key ); ?>-row"
                     class="smf-design-font-custom-row"
                     style="margin-top:0.5rem;<?php echo $mode_value === 'custom' ? '' : ' display:none;'; ?>">
                    <input type="text"
                           id="smf-design-<?php echo esc_attr( $custom_key ); ?>"
                           name="smf_design[<?php echo esc_attr( $custom_key ); ?>]"
                           value="<?php echo esc_attr( $custom_value ); ?>"
                           class="regular-text"
                           placeholder='z.B. "Helvetica Neue", Arial, sans-serif'>
                </div>
            </td>
        </tr>
        <?php
    }
}
