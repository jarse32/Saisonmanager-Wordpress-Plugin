<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin-Einstellungsseite
 */
class SMF_Admin {

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

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    /**
     * admin_post_* Handler registrieren (auf admin_init aufgerufen,
     * damit sie bei admin-post.php-Requests rechtzeitig verfügbar sind)
     */
    public function register_post_handlers() {
        add_action( 'admin_post_smf_flush_cache',    array( $this, 'flush_cache' ) );
        add_action( 'admin_post_smf_save_verbaende', array( $this, 'save_verbaende' ) );
        add_action( 'admin_post_smf_save_vereine',   array( $this, 'save_vereine' ) );
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
            'sanitize_callback' => 'absint',
            'default'           => 300,
        ] );
        register_setting( 'smf_settings_group', 'smf_api_key', [
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ] );
    }

    public function enqueue_admin_assets( $hook ): void {
        if ( $hook !== 'toplevel_page_smf-settings' ) return;
        wp_enqueue_style( 'smf-admin', SMF_PLUGIN_URL . 'assets/css/admin.css', array(), SMF_VERSION );
        wp_enqueue_script( 'smf-admin-js', SMF_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), SMF_VERSION, true );

        $verbaende = get_option( 'smf_verbaende', array() );
        $verband_options = array_map( function( $v ) {
            return array( 'slug' => $v['slug'], 'name' => $v['name'] );
        }, $verbaende );

        wp_localize_script( 'smf-admin-js', 'smf_admin_data', array(
            'verbaende'    => $verband_options,
            'verein_count' => count( get_option( 'smf_vereine', array() ) ),
            'ajax_url'     => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'smf_admin_nonce' ),
        ) );
    }

    public function flush_cache(): void {
        check_admin_referer( 'smf_flush_cache' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Nicht erlaubt.' );

        ( new SMF_API() )->flush_cache();

        wp_redirect( add_query_arg( [ 'page' => 'smf-settings', 'flushed' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Verbände-Liste speichern (POST-Handler)
     */
    public function save_verbaende(): void {
        check_admin_referer( 'smf_save_verbaende' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Nicht erlaubt.' );

        $slugs    = $_POST['smf_v_slug']    ?? [];
        $names    = $_POST['smf_v_name']    ?? [];
        $urls     = $_POST['smf_v_url']     ?? [];
        $api_keys = $_POST['smf_v_api_key'] ?? [];

        $verbaende = [];
        foreach ( $slugs as $i => $slug ) {
            $slug    = sanitize_key( $slug );
            $name    = sanitize_text_field( $names[ $i ] ?? '' );
            $url     = esc_url_raw( $urls[ $i ] ?? '' );
            $api_key = sanitize_text_field( $api_keys[ $i ] ?? '' );

            if ( $slug && $url ) {
                $verbaende[] = [ 'slug' => $slug, 'name' => $name, 'url' => $url, 'api_key' => $api_key ];
            }
        }

        update_option( 'smf_verbaende', $verbaende );

        wp_redirect( add_query_arg( array( 'page' => 'smf-settings', 'saved_v' => '1' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Vereine-Liste speichern (POST-Handler)
     */
    public function save_vereine(): void {
        check_admin_referer( 'smf_save_vereine' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Nicht erlaubt.' );

        $names   = isset( $_POST['smf_v_name'] )   ? (array) $_POST['smf_v_name']   : array();
        $slugs   = isset( $_POST['smf_v_slug'] )   ? (array) $_POST['smf_v_slug']   : array();
        $anzahls = isset( $_POST['smf_v_anzahl'] ) ? (array) $_POST['smf_v_anzahl'] : array();

        $t_team_ids   = isset( $_POST['smf_v_t_team_id'] )   ? (array) $_POST['smf_v_t_team_id']   : array();
        $t_liga_ids   = isset( $_POST['smf_v_t_liga_id'] )   ? (array) $_POST['smf_v_t_liga_id']   : array();
        $t_teams      = isset( $_POST['smf_v_t_team'] )      ? (array) $_POST['smf_v_t_team']      : array();
        $t_verbaende  = isset( $_POST['smf_v_t_verband'] )   ? (array) $_POST['smf_v_t_verband']   : array();
        $t_liga_names = isset( $_POST['smf_v_t_liga_name'] ) ? (array) $_POST['smf_v_t_liga_name'] : array();

        $vereine = array();
        foreach ( $names as $i => $name ) {
            $name   = sanitize_text_field( $name );
            $slug   = sanitize_key( isset( $slugs[ $i ] ) ? $slugs[ $i ] : '' );
            $anzahl = max( 1, min( 20, absint( isset( $anzahls[ $i ] ) ? $anzahls[ $i ] : 4 ) ) );

            if ( $slug === '' ) {
                $slug = sanitize_title( $name );
            }

            // Teams für diesen Verein. Jede Zeile hat entweder eine Team-ID
            // (bevorzugt, ein Aufruf deckt alle Wettbewerbe der Saison ab)
            // oder eine Liga-ID + Team-Filter (Legacy, eine Zeile pro
            // Wettbewerb). Beide Feld-Arrays teilen sich denselben Zeilen-
            // Index $j, da sie aus derselben Tabelle stammen.
            $teams_id_for_club   = isset( $t_team_ids[ $i ] ) ? (array) $t_team_ids[ $i ] : array();
            $liga_ids_for_club   = isset( $t_liga_ids[ $i ] ) ? (array) $t_liga_ids[ $i ] : array();
            $row_indices         = array_unique( array_merge( array_keys( $teams_id_for_club ), array_keys( $liga_ids_for_club ) ) );

            $teams = array();
            foreach ( $row_indices as $j ) {
                $team_id = absint( isset( $teams_id_for_club[ $j ] ) ? $teams_id_for_club[ $j ] : 0 );
                $liga_id = absint( isset( $liga_ids_for_club[ $j ] ) ? $liga_ids_for_club[ $j ] : 0 );
                if ( ! $team_id && ! $liga_id ) continue;

                $teams[] = array(
                    'team_id'   => $team_id,
                    'liga_id'   => $liga_id,
                    'team'      => sanitize_text_field( isset( $t_teams[ $i ][ $j ] )      ? $t_teams[ $i ][ $j ]      : '' ),
                    'verband'   => sanitize_key( isset( $t_verbaende[ $i ][ $j ] )         ? $t_verbaende[ $i ][ $j ]  : '' ),
                    'liga_name' => sanitize_text_field( isset( $t_liga_names[ $i ][ $j ] ) ? $t_liga_names[ $i ][ $j ] : '' ),
                );
            }

            if ( $name !== '' || ! empty( $teams ) ) {
                $vereine[] = array(
                    'name'   => $name,
                    'slug'   => $slug,
                    'anzahl' => $anzahl,
                    'teams'  => $teams,
                );
            }
        }

        update_option( 'smf_vereine', $vereine );

        wp_redirect( add_query_arg( array( 'page' => 'smf-settings', 'saved_verein' => '1' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public function render_settings_page(): void {
        $flushed      = isset( $_GET['flushed'] );
        $saved_v      = isset( $_GET['saved_v'] );
        $saved_verein = isset( $_GET['saved_verein'] );
        $verbaende    = get_option( 'smf_verbaende', array() );
        $vereine      = get_option( 'smf_vereine', array() );
        ?>
        <div class="wrap smf-admin-wrap">
            <h1>
                <span class="smf-logo">⚽</span>
                SM Floorball – Einstellungen
            </h1>

            <?php if ( $flushed ) : ?>
                <div class="notice notice-success is-dismissible"><p>Cache wurde erfolgreich geleert.</p></div>
            <?php endif; ?>
            <?php if ( $saved_v ) : ?>
                <div class="notice notice-success is-dismissible"><p>Verbände gespeichert.</p></div>
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
                                <th scope="row"><label for="smf_api_base_url">Standard API-URL</label></th>
                                <td>
                                    <input type="url" id="smf_api_base_url" name="smf_api_base_url"
                                           value="<?php echo esc_attr( get_option( 'smf_api_base_url', 'https://saisonmanager.de/api/v2' ) ); ?>"
                                           class="regular-text">
                                    <p class="description">Wird genutzt, wenn im Shortcode kein <code>verband</code> angegeben ist.</p>
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
                                        erscheint nie im Seitenquelltext oder Browser der Besucher:innen. Gilt
                                        als Standard-Key für alle Verbände, sofern unten kein abweichender
                                        Verbands-Key hinterlegt ist.
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
                                <th scope="row"><label for="smf_cache_duration">Cache-Dauer (Sekunden)</label></th>
                                <td>
                                    <input type="number" id="smf_cache_duration" name="smf_cache_duration"
                                           value="<?php echo esc_attr( get_option( 'smf_cache_duration', 300 ) ); ?>"
                                           class="small-text" min="60" max="86400">
                                    <p class="description">300 = 5 Min., 3600 = 1 Std.</p>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button( 'Einstellungen speichern' ); ?>
                    </form>
                </div>

                <!-- Cache -->
                <div class="smf-admin-card">
                    <h2>Cache</h2>
                    <p>API-Antworten werden gecacht, um Server-Last zu reduzieren. Nach Änderungen an der API-Konfiguration den Cache leeren.</p>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="smf_flush_cache">
                        <?php wp_nonce_field( 'smf_flush_cache' ); ?>
                        <?php submit_button( 'Cache leeren', 'secondary' ); ?>
                    </form>
                </div>

                <!-- Verbände -->
                <div class="smf-admin-card smf-admin-card--full">
                    <h2>Verbände</h2>
                    <p>
                        Hinterlege hier alle Verbände, die du nutzen möchtest. Im Shortcode kannst du dann einfach
                        <code>verband="slug"</code> angeben – z.B. <code>[sm_tabelle liga_id="123" verband="fvd"]</code>.
                        Der API-Key-Spalte nur einen Wert geben, wenn dieser Verband einen eigenen Key
                        benötigt – sonst greift der Standard-Key aus den Allgemeinen Einstellungen.
                    </p>

                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="smf_save_verbaende">
                        <?php wp_nonce_field( 'smf_save_verbaende' ); ?>

                        <table class="widefat smf-verbaende-table" id="smf-verbaende-table">
                            <thead>
                                <tr>
                                    <th>Slug <small>(für Shortcode)</small></th>
                                    <th>Name <small>(zur Anzeige)</small></th>
                                    <th>API-URL</th>
                                    <th>API-Key <small>(optional)</small></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="smf-verbaende-body">
                                <?php foreach ( $verbaende as $v ) : ?>
                                    <tr class="smf-verband-row">
                                        <td><input type="text" name="smf_v_slug[]"
                                                   value="<?php echo esc_attr( $v['slug'] ); ?>"
                                                   class="regular-text" placeholder="z.B. fvd"
                                                   pattern="[a-z0-9\-]+" title="Nur Kleinbuchstaben, Zahlen und Bindestriche"></td>
                                        <td><input type="text" name="smf_v_name[]"
                                                   value="<?php echo esc_attr( $v['name'] ); ?>"
                                                   class="regular-text" placeholder="z.B. Floorball Verband Deutschland"></td>
                                        <td><input type="url" name="smf_v_url[]"
                                                   value="<?php echo esc_attr( $v['url'] ); ?>"
                                                   class="regular-text" placeholder="https://fvd.saisonmanager.de/api/v2"></td>
                                        <td><input type="text" name="smf_v_api_key[]"
                                                   value="<?php echo esc_attr( $v['api_key'] ?? '' ); ?>"
                                                   class="regular-text" autocomplete="off" placeholder="(Standard-Key)"></td>
                                        <td><button type="button" class="button smf-remove-row">&#10005; Entfernen</button></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <p>
                            <button type="button" class="button" id="smf-add-verband">+ Verband hinzufügen</button>
                        </p>

                        <?php submit_button( 'Verbände speichern', 'primary', 'smf_save_verbaende_btn' ); ?>
                    </form>

                    <?php if ( ! empty( $verbaende ) ) : ?>
                        <h3 style="margin-top:1.5rem;">Bekannte API-URLs</h3>
                        <table class="widefat" style="margin-top:.5rem;">
                            <thead><tr><th>Verband</th><th>Slug-Empfehlung</th><th>API-URL</th></tr></thead>
                            <tbody>
                                <tr><td>Floorball Verband Deutschland (FVD)</td><td><code>fvd</code></td><td><code>https://saisonmanager.de/api/v2</code></td></tr>
                                <tr><td>Floorball-Verband Norddeutschland (FVN)</td><td><code>fvn</code></td><td><code>https://fvn.saisonmanager.de/api/v2</code> <em>(bitte prüfen)</em></td></tr>
                                <tr><td>Floorball-Verband Schleswig-Holstein</td><td><code>flv-sh</code></td><td><code>https://flv-sh.saisonmanager.de/api/v2</code> <em>(bitte prüfen)</em></td></tr>
                            </tbody>
                        </table>
                        <p class="description">
                            <strong>Hinweis:</strong> Subdomains wie <code>fvd.saisonmanager.de</code> leiten oft auf eine HTML-Seite weiter und funktionieren nicht als API-URL.
                            Verwende stattdessen <code>https://saisonmanager.de/api/v2</code> für FVD-Ligen.
                            Für regionale Verbände die korrekte URL bitte direkt beim Verband erfragen.
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Team-Finder -->
                <div class="smf-admin-card smf-admin-card--full">
                    <h2>Team-Finder</h2>
                    <p>
                        Findet Team-IDs für die Vereinskonfiguration unten: Vereinsname (Teilstring reicht)
                        oder Club-ID eingeben, optional eine Saison-ID für historische Daten (leer = aktuelle
                        Saison). Durchsucht alle bekannten Spielbetriebsstellen – kann beim ersten Aufruf ein
                        paar Sekunden dauern.
                    </p>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="smf-tf-query">Vereinsname oder Club-ID</label></th>
                            <td><input type="text" id="smf-tf-query" class="regular-text" placeholder="z.B. Eiche Horn"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smf-tf-verband">Verband</label></th>
                            <td>
                                <select id="smf-tf-verband">
                                    <option value="">(Standard)</option>
                                    <?php foreach ( $verbaende as $vb ) : ?>
                                        <option value="<?php echo esc_attr( $vb['slug'] ); ?>"><?php echo esc_html( $vb['name'] ?: $vb['slug'] ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="smf-tf-season">Saison-ID <small>(optional)</small></label></th>
                            <td><input type="number" id="smf-tf-season" class="small-text" placeholder="leer = aktuell"></td>
                        </tr>
                    </table>
                    <p><button type="button" class="button button-primary" id="smf-tf-search">Suchen</button></p>
                    <div id="smf-tf-results"></div>
                </div>

                <!-- Vereine -->
                <div class="smf-admin-card smf-admin-card--full">
                    <h2>Vereine</h2>
                    <p>
                        Lege hier Vereine an und weise ihnen die Ligen/Teams zu, die in der Vereinsübersicht
                        angezeigt werden sollen. Über den Shortcode <code>[sm_vereinsuebersicht]</code> kannst du die
                        Übersicht dann auf jeder Seite einbinden.
                    </p>

                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="smf-vereine-form">
                        <input type="hidden" name="action" value="smf_save_vereine">
                        <?php wp_nonce_field( 'smf_save_vereine' ); ?>

                        <div id="smf-vereine-container">
                            <?php foreach ( $vereine as $vi => $verein ) : ?>
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
                                    </table>

                                    <h4 style="margin: 1rem 0 0.5rem;">Teams &amp; Ligen</h4>
                                    <p class="description">
                                        <strong>Team-ID</strong> (empfohlen): ein Aufruf deckt alle Wettbewerbe
                                        des Teams in der Saison ab – benötigt nur einen konfigurierten
                                        Saisonmanager API-Key (siehe Allgemeine Einstellungen).
                                        <strong>Liga-ID</strong> (Legacy): eine Zeile pro Wettbewerb, Zuordnung
                                        per Team-Filter-Namen. Pro Zeile nur eines von beidem ausfüllen.
                                    </p>
                                    <table class="widefat smf-teams-table">
                                        <thead>
                                            <tr>
                                                <th>Team-ID <small>(empfohlen)</small></th>
                                                <th>Liga-ID <small>(Legacy)</small></th>
                                                <th>Team-Filter <small>(nur Legacy)</small></th>
                                                <th>Verband</th>
                                                <th>Liga-Name <small>(Anzeige, nur Legacy)</small></th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody class="smf-teams-body">
                                            <?php foreach ( $verein['teams'] as $team ) : ?>
                                                <tr class="smf-team-row">
                                                    <td>
                                                        <input type="number"
                                                               name="smf_v_t_team_id[<?php echo esc_attr( $vi ); ?>][]"
                                                               value="<?php echo esc_attr( $team['team_id'] ?? '' ); ?>"
                                                               class="small-text" placeholder="z.B. 6754">
                                                    </td>
                                                    <td>
                                                        <input type="number"
                                                               name="smf_v_t_liga_id[<?php echo esc_attr( $vi ); ?>][]"
                                                               value="<?php echo esc_attr( $team['liga_id'] ); ?>"
                                                               class="small-text" placeholder="z.B. 123">
                                                    </td>
                                                    <td>
                                                        <input type="text"
                                                               name="smf_v_t_team[<?php echo esc_attr( $vi ); ?>][]"
                                                               value="<?php echo esc_attr( $team['team'] ); ?>"
                                                               class="regular-text" placeholder="z.B. Eichehorn">
                                                    </td>
                                                    <td>
                                                        <select name="smf_v_t_verband[<?php echo esc_attr( $vi ); ?>][]">
                                                            <option value="">(Standard-URL)</option>
                                                            <?php foreach ( $verbaende as $vb ) : ?>
                                                                <option value="<?php echo esc_attr( $vb['slug'] ); ?>"
                                                                    <?php selected( $team['verband'], $vb['slug'] ); ?>>
                                                                    <?php echo esc_html( $vb['name'] ?: $vb['slug'] ); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <input type="text"
                                                               name="smf_v_t_liga_name[<?php echo esc_attr( $vi ); ?>][]"
                                                               value="<?php echo esc_attr( $team['liga_name'] ); ?>"
                                                               class="regular-text" placeholder="z.B. Bundesliga Herren">
                                                    </td>
                                                    <td>
                                                        <button type="button" class="button smf-remove-team">&#10005;</button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <p>
                                        <button type="button" class="button smf-add-team"
                                                data-verein-index="<?php echo esc_attr( $vi ); ?>">
                                            + Team / Liga hinzufügen
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
                            </table>
                            <h4 style="margin: 1rem 0 0.5rem;">Teams &amp; Ligen</h4>
                            <table class="widefat smf-teams-table">
                                <thead>
                                    <tr>
                                        <th>Team-ID <small>(empfohlen)</small></th>
                                        <th>Liga-ID <small>(Legacy)</small></th>
                                        <th>Team-Filter <small>(nur Legacy)</small></th>
                                        <th>Verband</th>
                                        <th>Liga-Name <small>(Anzeige, nur Legacy)</small></th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody class="smf-teams-body"></tbody>
                            </table>
                            <p>
                                <button type="button" class="button smf-add-team" data-verein-index="__VI__">+ Team / Liga hinzufügen</button>
                            </p>
                        </div>
                    </div>

                    <div id="smf-team-row-template" style="display:none;">
                        <table><tbody>
                            <tr class="smf-team-row">
                                <td><input type="number" name="smf_v_t_team_id[__VI__][]" class="small-text" placeholder="z.B. 6754"></td>
                                <td><input type="number" name="smf_v_t_liga_id[__VI__][]" class="small-text" placeholder="z.B. 123"></td>
                                <td><input type="text"   name="smf_v_t_team[__VI__][]"    class="regular-text" placeholder="z.B. Eichehorn"></td>
                                <td>
                                    <select name="smf_v_t_verband[__VI__][]">
                                        <option value="">(Standard-URL)</option>
                                        <?php foreach ( $verbaende as $vb ) : ?>
                                            <option value="<?php echo esc_attr( $vb['slug'] ); ?>">
                                                <?php echo esc_html( $vb['name'] ?: $vb['slug'] ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="text" name="smf_v_t_liga_name[__VI__][]" class="regular-text" placeholder="z.B. Bundesliga Herren"></td>
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
                                <td><code>liga_id</code>, <code>verband</code>, <code>titel</code>, <code>logos</code> (true/false, Standard: false)</td>
                            </tr>
                            <tr>
                                <td><code>[sm_spiele liga_id="123"]</code></td>
                                <td>Spielplan einer Liga</td>
                                <td><code>liga_id</code>, <code>verband</code>, <code>anzahl</code>, <code>team</code>, <code>modus</code> (alle/vergangen/kommend), <code>titel</code>, <code>logos</code> (true/false, Standard: false)</td>
                            </tr>
                            <tr>
                                <td><code>[sm_naechstes_spiel liga_id="123"]</code></td>
                                <td>Nächstes kommendes Spiel</td>
                                <td><code>liga_id</code>, <code>verband</code>, <code>team</code>, <code>logos</code> (true/false, Standard: true)</td>
                            </tr>
                            <tr>
                                <td><code>[sm_letztes_spiel liga_id="123"]</code></td>
                                <td>Letztes gespieltes Spiel</td>
                                <td><code>liga_id</code>, <code>verband</code>, <code>team</code>, <code>logos</code> (true/false, Standard: true)</td>
                            </tr>
                            <tr>
                                <td><code>[sm_vereinsuebersicht verein="hannover"]</code></td>
                                <td>Vereinsübersicht: anstehende &amp; gespielte Spiele aller Teams</td>
                                <td><code>verein</code> (Slug oder Name, Standard: erster Verein), <code>anzahl</code> (überschreibt Backend-Einstellung)</td>
                            </tr>
                        </tbody>
                    </table>

                    <h3>Beispiele mit mehreren Verbänden</h3>
                    <pre class="smf-code-block"><?php echo esc_html(
'// Tabelle aus dem FVD (Bundesliga)
[sm_tabelle liga_id="100" verband="fvd"]

// Spielplan aus dem FLV-SH (Regionalliga)
[sm_spiele liga_id="250" verband="flv-sh" modus="kommend" anzahl="5"]

// Nächstes Spiel von Eichehorn im FVN
[sm_naechstes_spiel liga_id="300" verband="fvn" team="Eichehorn"]

// Letztes Spiel ohne Verband-Parameter (nutzt Standard-URL)
[sm_letztes_spiel liga_id="456" team="Eichehorn"]'
                    ); ?></pre>
                </div>

            </div>
        </div>
        <?php
    }
}
