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
        add_action( 'admin_post_smf_flush_cache',  array( $this, 'flush_cache' ) );
        add_action( 'admin_post_smf_save_vereine', array( $this, 'save_vereine' ) );
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

        wp_localize_script( 'smf-admin-js', 'smf_admin_data', array(
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
     * Vereine-Liste speichern (POST-Handler)
     */
    public function save_vereine(): void {
        check_admin_referer( 'smf_save_vereine' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Nicht erlaubt.' );

        $names    = isset( $_POST['smf_v_name'] )    ? (array) $_POST['smf_v_name']    : array();
        $slugs    = isset( $_POST['smf_v_slug'] )    ? (array) $_POST['smf_v_slug']    : array();
        $anzahls  = isset( $_POST['smf_v_anzahl'] )  ? (array) $_POST['smf_v_anzahl']  : array();
        $club_ids = isset( $_POST['smf_v_club_id'] ) ? (array) $_POST['smf_v_club_id'] : array();

        $t_team_ids = isset( $_POST['smf_v_t_team_id'] ) ? (array) $_POST['smf_v_t_team_id'] : array();

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

        wp_redirect( add_query_arg( array( 'page' => 'smf-settings', 'saved_verein' => '1' ), admin_url( 'admin.php' ) ) );
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
        $flushed      = isset( $_GET['flushed'] );
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
                <div class="notice notice-success is-dismissible"><p>Cache wurde erfolgreich geleert.</p></div>
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
                                                <div class="smf-club-teams-results"><?php echo self::render_club_teams_list( $cached_teams ); ?></div>
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
                                <td><code>liga_id</code>, <code>titel</code>, <code>logos</code> (true/false, Standard: false)</td>
                            </tr>
                            <tr>
                                <td><code>[sm_spiele liga_id="123"]</code></td>
                                <td>Spielplan einer Liga</td>
                                <td><code>liga_id</code>, <code>anzahl</code>, <code>team</code>, <code>modus</code> (alle/vergangen/kommend), <code>titel</code>, <code>logos</code> (true/false, Standard: false)</td>
                            </tr>
                            <tr>
                                <td><code>[sm_naechstes_spiel liga_id="123"]</code></td>
                                <td>Nächstes kommendes Spiel</td>
                                <td><code>liga_id</code>, <code>team</code>, <code>logos</code> (true/false, Standard: true)</td>
                            </tr>
                            <tr>
                                <td><code>[sm_letztes_spiel liga_id="123"]</code></td>
                                <td>Letztes gespieltes Spiel</td>
                                <td><code>liga_id</code>, <code>team</code>, <code>logos</code> (true/false, Standard: true)</td>
                            </tr>
                            <tr>
                                <td><code>[sm_vereinsuebersicht verein="hannover"]</code></td>
                                <td>Vereinsübersicht: anstehende &amp; gespielte Spiele aller Teams</td>
                                <td><code>verein</code> (Slug oder Name, Standard: erster Verein), <code>anzahl</code> (überschreibt Backend-Einstellung)</td>
                            </tr>
                        </tbody>
                    </table>
                    <p class="description">
                        Die <code>liga_id</code> für <code>sm_tabelle</code>/<code>sm_spiele</code>/etc. findest du
                        über den Team-Finder oben (Spalte "Liga(en)" bei den gefundenen Teams) oder über den
                        "Teams laden"-Button bei einem Verein.
                    </p>
                </div>

            </div>
        </div>
        <?php
    }
}
