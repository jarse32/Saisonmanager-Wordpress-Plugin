<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Alle Shortcodes des Plugins
 *
 * [sm_tabelle liga_id="123"]
 * [sm_spiele liga_id="123" anzahl="10" team="Eichehorn"]
 * [sm_naechstes_spiel liga_id="123" team="Eichehorn"]
 * [sm_letztes_spiel liga_id="123"]
 * [sm_scorer team_id="6754" anzahl="10"]
 */
class SMF_Shortcodes {

    public function register() {
        add_shortcode( 'sm_tabelle',            array( $this, 'shortcode_tabelle' ) );
        add_shortcode( 'sm_spiele',             array( $this, 'shortcode_spiele' ) );
        add_shortcode( 'sm_naechstes_spiel',    array( $this, 'shortcode_naechstes_spiel' ) );
        add_shortcode( 'sm_letztes_spiel',      array( $this, 'shortcode_letztes_spiel' ) );
        add_shortcode( 'sm_vereinsuebersicht',  array( $this, 'shortcode_vereinsuebersicht' ) );
        add_shortcode( 'sm_scorer',             array( $this, 'shortcode_scorer' ) );
    }

    // ----------------------------------------------------------------
    // [sm_tabelle liga_id="123" titel="true"]
    // ----------------------------------------------------------------
    public function shortcode_tabelle( $atts ) {
        $atts = shortcode_atts( array(
            'liga_id' => get_option( 'smf_default_league_id', '' ),
            'titel'   => 'true',
            'logos'   => 'false',
        ), $atts, 'sm_tabelle' );

        $liga_id = (int) $atts['liga_id'];
        if ( ! $liga_id ) {
            return $this->error( 'Bitte liga_id angeben, z.B. [sm_tabelle liga_id="123"]' );
        }

        $api = new SMF_API();

        $table = $api->get_table( $liga_id );
        if ( is_wp_error( $table ) ) {
            return $this->error( $table );
        }

        $league      = $api->get_league( $liga_id );
        $league_name = is_wp_error( $league ) ? '' : ( isset( $league['name'] ) ? $league['name'] : '' );

        // Erst nach dem letzten API-Aufruf lesen, direkt vor dem Rendern -
        // ein Shortcode macht hier zwei Requests (Tabelle + Liganame), der
        // Hinweis soll den konservativeren (älteren) Stand zeigen.
        $stale_since = $api->stale_since();

        ob_start();
        smf_render_template( 'table', array(
            'table'       => $table,
            'league_name' => $league_name,
            'show_title'  => $atts['titel'] !== 'false',
            'show_logos'  => $atts['logos'] === 'true',
            'stale_since' => $stale_since,
        ) );
        return ob_get_clean();
    }

    // ----------------------------------------------------------------
    // [sm_spiele liga_id="123" anzahl="10" team="Eichehorn" modus="alle|vergangen|kommend"]
    // ----------------------------------------------------------------
    public function shortcode_spiele( $atts ) {
        $atts = shortcode_atts( array(
            'liga_id' => get_option( 'smf_default_league_id', '' ),
            'anzahl'  => 0,
            'team'    => '',
            'modus'   => 'alle',
            'titel'   => 'true',
            'logos'   => 'false',
        ), $atts, 'sm_spiele' );

        $liga_id = (int) $atts['liga_id'];
        if ( ! $liga_id ) {
            return $this->error( 'Bitte liga_id angeben, z.B. [sm_spiele liga_id="123"]' );
        }

        $api = new SMF_API();

        $schedule = $api->get_schedule( $liga_id );
        if ( is_wp_error( $schedule ) ) {
            return $this->error( $schedule );
        }

        $games = $this->normalize_schedule( $schedule );

        if ( ! empty( $atts['team'] ) ) {
            $games = $api->filter_by_team( $games, $atts['team'] );
        }

        if ( $atts['modus'] === 'vergangen' ) {
            $games = $api->filter_past_games( $games );
            usort( $games, function( $a, $b ) use ( $api ) {
                return $api->parse_game_date( $b ) - $api->parse_game_date( $a );
            } );
        } elseif ( $atts['modus'] === 'kommend' ) {
            $games = $api->filter_upcoming_games( $games );
            usort( $games, function( $a, $b ) use ( $api ) {
                return $api->parse_game_date( $a ) - $api->parse_game_date( $b );
            } );
        }

        $anzahl = (int) $atts['anzahl'];
        if ( $anzahl > 0 ) {
            $games = array_slice( $games, 0, $anzahl );
        }

        $league      = $api->get_league( $liga_id );
        $league_name = is_wp_error( $league ) ? '' : ( isset( $league['name'] ) ? $league['name'] : '' );

        // Erst nach dem letzten API-Aufruf lesen, direkt vor dem Rendern.
        $stale_since = $api->stale_since();

        ob_start();
        smf_render_template( 'games-list', array(
            'games'       => $games,
            'league_name' => $league_name,
            'show_title'  => $atts['titel'] !== 'false',
            'modus'       => $atts['modus'],
            'show_logos'  => $atts['logos'] === 'true',
            'stale_since' => $stale_since,
        ) );
        return ob_get_clean();
    }

    // ----------------------------------------------------------------
    // [sm_naechstes_spiel liga_id="123" team="Eichehorn"]
    // ----------------------------------------------------------------
    public function shortcode_naechstes_spiel( $atts ) {
        $atts = shortcode_atts( array(
            'liga_id' => get_option( 'smf_default_league_id', '' ),
            'team'    => '',
            'logos'   => 'true',
        ), $atts, 'sm_naechstes_spiel' );

        $liga_id = (int) $atts['liga_id'];
        if ( ! $liga_id ) {
            return $this->error( 'Bitte liga_id angeben.' );
        }

        $api = new SMF_API();

        $schedule = $api->get_schedule( $liga_id );
        if ( is_wp_error( $schedule ) ) {
            return $this->error( $schedule );
        }

        $games = $this->normalize_schedule( $schedule );

        if ( ! empty( $atts['team'] ) ) {
            $games = $api->filter_by_team( $games, $atts['team'] );
        }

        $game = $api->get_next_game( $games );
        if ( ! $game ) {
            return '<div class="smf-notice">Kein kommendes Spiel gefunden.</div>' . self::stale_notice( $api->stale_since() );
        }

        $league      = $api->get_league( $liga_id );
        $league_name = is_wp_error( $league ) ? '' : ( isset( $league['name'] ) ? $league['name'] : '' );

        // Erst nach dem letzten API-Aufruf lesen, direkt vor dem Rendern.
        $stale_since = $api->stale_since();

        ob_start();
        smf_render_template( 'single-game', array(
            'game'        => $game,
            'league_name' => $league_name,
            'label'       => smf_label( 'next_game_label', 'Nächstes Spiel' ),
            'show_logos'  => $atts['logos'] === 'true',
            'stale_since' => $stale_since,
        ) );
        return ob_get_clean();
    }

    // ----------------------------------------------------------------
    // [sm_letztes_spiel liga_id="123" team="Eichehorn"]
    // ----------------------------------------------------------------
    public function shortcode_letztes_spiel( $atts ) {
        $atts = shortcode_atts( array(
            'liga_id' => get_option( 'smf_default_league_id', '' ),
            'team'    => '',
            'logos'   => 'true',
        ), $atts, 'sm_letztes_spiel' );

        $liga_id = (int) $atts['liga_id'];
        if ( ! $liga_id ) {
            return $this->error( 'Bitte liga_id angeben.' );
        }

        $api = new SMF_API();

        $schedule = $api->get_schedule( $liga_id );
        if ( is_wp_error( $schedule ) ) {
            return $this->error( $schedule );
        }

        $games = $this->normalize_schedule( $schedule );

        if ( ! empty( $atts['team'] ) ) {
            $games = $api->filter_by_team( $games, $atts['team'] );
        }

        $game = $api->get_last_game( $games );
        if ( ! $game ) {
            return '<div class="smf-notice">Kein gespieltes Spiel gefunden.</div>' . self::stale_notice( $api->stale_since() );
        }

        $league      = $api->get_league( $liga_id );
        $league_name = is_wp_error( $league ) ? '' : ( isset( $league['name'] ) ? $league['name'] : '' );

        // Erst nach dem letzten API-Aufruf lesen, direkt vor dem Rendern.
        $stale_since = $api->stale_since();

        ob_start();
        smf_render_template( 'single-game', array(
            'game'        => $game,
            'league_name' => $league_name,
            'label'       => smf_label( 'last_game_label', 'Letztes Spiel' ),
            'show_logos'  => $atts['logos'] === 'true',
            'stale_since' => $stale_since,
        ) );
        return ob_get_clean();
    }

    // ----------------------------------------------------------------
    // [sm_vereinsuebersicht verein="hannover" anzahl="4"]
    // ----------------------------------------------------------------
    public function shortcode_vereinsuebersicht( $atts ) {
        $atts = shortcode_atts( array(
            'verein' => '',
            'anzahl' => 0,
        ), $atts, 'sm_vereinsuebersicht' );

        $club = SMF_ClubOverview::get_club( $atts['verein'] );

        if ( ! $club ) {
            return $this->error(
                'Kein Verein konfiguriert. Bitte zuerst unter SM Floorball &rarr; Vereine anlegen.'
            );
        }

        // Anzahl: Shortcode-Parameter überschreibt Backend-Einstellung
        $anzahl = (int) $atts['anzahl'];
        if ( $anzahl <= 0 ) {
            $anzahl = (int) ( isset( $club['anzahl'] ) ? $club['anzahl'] : 4 );
        }
        if ( $anzahl <= 0 ) {
            $anzahl = 4;
        }

        $games = SMF_ClubOverview::get_games( $club, $anzahl );

        // "Alle Teams gescheitert": keine Daten vorhanden, weder frisch
        // noch aus der Notreserve - Hinweis statt leerer Spalten.
        if ( is_wp_error( $games['error'] ) ) {
            return $this->error( $games['error'] );
        }

        ob_start();
        smf_render_template( 'club-overview', array(
            'club_name'            => isset( $club['name'] ) ? $club['name'] : '',
            'upcoming'             => $games['upcoming'],
            'played'               => $games['played'],
            'stale_since'          => $games['stale_since'],
            'partial_error_count'  => $games['partial_error_count'],
        ) );
        return ob_get_clean();
    }

    // ----------------------------------------------------------------
    // [sm_scorer team_id="6754" anzahl="10" spalten="voll|kompakt" namen="voll|abgekuerzt" titel="true" summe="false"]
    // ----------------------------------------------------------------
    public function shortcode_scorer( $atts ) {
        $atts = shortcode_atts( array(
            'team_id' => '',
            'anzahl'  => 0,
            'titel'   => 'true',
            'spalten' => 'voll',
            'namen'   => '',
            'summe'   => 'false',
        ), $atts, 'sm_scorer' );

        $team_id = (int) $atts['team_id'];
        if ( ! $team_id ) {
            return $this->error( 'Bitte team_id angeben, z.B. [sm_scorer team_id="6754"]' );
        }

        // Enums strikt: unbekannte Werte fallen auf den Standard zurück,
        // statt sie unverändert ins Template durchzureichen.
        $spalten = ( $atts['spalten'] === 'kompakt' ) ? 'kompakt' : 'voll';
        $namen   = in_array( $atts['namen'], array( 'voll', 'abgekuerzt' ), true ) ? $atts['namen'] : '';

        // Globaler Schalter "Personennamen anzeigen" ist eine
        // Vereinsentscheidung und per Shortcode nicht übersteuerbar - ist
        // er aus, wird kein API-Request für die Liste ausgelöst.
        $names_disabled = ! SMF_Scorer::player_names_enabled();

        $result = array( 'status' => 'empty', 'error' => '', 'rows' => array(), 'columns' => array(), 'totals' => null );
        if ( ! $names_disabled ) {
            $result = SMF_Scorer::get( $team_id, array(
                'anzahl' => (int) $atts['anzahl'],
            ) );

            if ( $result['status'] === 'error' ) {
                return $this->error( $result['error'] ); // WP_Error, siehe SMF_Scorer::get()
            }
        }

        ob_start();
        smf_render_template( 'scorer', array(
            'result'         => $result,
            'show_title'     => $atts['titel'] !== 'false',
            'namen'          => $namen,
            'spalten'        => $spalten,
            'show_totals'    => $atts['summe'] === 'true',
            'names_disabled' => $names_disabled,
        ) );
        return ob_get_clean();
    }

    // ----------------------------------------------------------------
    // Hilfsfunktionen
    // ----------------------------------------------------------------

    /**
     * Spielplan normalisieren (API kann verschiedene Strukturen liefern)
     *
     * @param array $schedule
     * @return array
     */
    private function normalize_schedule( $schedule ) {
        if ( ! is_array( $schedule ) )                                  return array();
        if ( isset( $schedule['games'] ) && is_array( $schedule['games'] ) ) return $schedule['games'];
        if ( isset( $schedule['data'] )  && is_array( $schedule['data'] ) )  return $schedule['data'];
        // Direkte JSON-Array-Antwort: erstes Element mit Index 0 vorhanden
        if ( isset( $schedule[0] ) )                                    return array_values( $schedule );
        return array();
    }

    /**
     * Fehlerausgabe, rollengetrennt: Administrator:innen sehen die
     * technischen Details (HTTP-Code, URL, Breaker-/Rate-Limit-Status aus
     * den WP_Error-Daten), alle anderen einen freundlichen, allgemeinen
     * Hinweis ohne technische Interna.
     *
     * @param string|WP_Error $error Einfache Validierungsmeldung (String,
     *                                z.B. fehlende liga_id) oder ein
     *                                WP_Error aus SMF_API (API-/Ausfall-Fehler).
     * @return string
     */
    private function error( $error ) {
        if ( $error instanceof WP_Error ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                return '<div class="smf-notice">' . esc_html( self::error_message_for_current_user( $error ) ) . '</div>';
            }
            return '<div class="smf-error"><strong>Saisonmanager Fehler (nur für Administrator:innen sichtbar):</strong> '
                . esc_html( self::error_message_for_current_user( $error ) ) . '</div>';
        }

        // Einfache Validierungsmeldung (Shortcode-Attribute) - unkritisch,
        // enthält keine Serverdetails, darf also für alle sichtbar bleiben.
        return '<div class="smf-error"><strong>Saisonmanager Fehler:</strong> ' . esc_html( $error ) . '</div>';
    }

    /**
     * Baut aus einem WP_Error die für die aktuelle Rolle passende
     * Fehlermeldung. Wird auch außerhalb dieser Klasse genutzt (AJAX-Route
     * smf_ajax_game_detail() im Hauptplugin), deshalb public/static.
     *
     * @param WP_Error $error
     * @return string
     */
    public static function error_message_for_current_user( WP_Error $error ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return smf_label(
                'api_unavailable_visitor',
                'Aktuell sind keine aktuellen Daten verfügbar. Bitte versuche es in Kürze erneut.'
            );
        }

        $message = $error->get_error_message();
        $data    = $error->get_error_data();

        $parts = array();
        if ( is_array( $data ) ) {
            if ( ! empty( $data['http_code'] ) ) $parts[] = 'HTTP ' . $data['http_code'];
            if ( ! empty( $data['url'] ) )       $parts[] = $data['url'];
            if ( ! empty( $data['detail'] ) )    $parts[] = $data['detail'];
        }

        return $parts ? ( $message . ' (' . implode( ' · ', $parts ) . ')' ) : $message;
    }

    /**
     * Dezenter Hinweis auf den Stand der Notreserve-Daten, gemeinsam
     * genutzt von den Templates (table, games-list, single-game,
     * club-overview) und den Inline-Ausgaben oben (kein kommendes/
     * gespieltes Spiel gefunden) - damit die Formatierung nicht an
     * mehreren Stellen dupliziert wird.
     *
     * @param int|null $timestamp Rückgabe von SMF_API::stale_since()
     * @return string Leerstring, wenn $timestamp null ist
     */
    public static function stale_notice( $timestamp ) {
        if ( ! $timestamp ) {
            return '';
        }

        $date = date_i18n( 'l, d.m.Y', $timestamp ) . ', ' . date_i18n( 'H:i', $timestamp ) . ' ' . smf_label( 'time_suffix', 'Uhr' );

        $text = sprintf(
            /* translators: %s: Datum/Uhrzeit des letzten erfolgreichen Datenabrufs */
            smf_label( 'stale_notice', 'Stand: %s – der Verbandsserver liefert gerade keine aktuellen Daten.' ),
            $date
        );

        return '<p class="smf-notice smf-notice--stale">' . esc_html( $text ) . '</p>';
    }
}
