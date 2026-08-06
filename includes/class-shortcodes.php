<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Alle Shortcodes des Plugins
 *
 * [sm_tabelle liga_id="123" verband="fvd"]
 * [sm_spiele liga_id="123" anzahl="10" team="Eichehorn" verband="flv-sh"]
 * [sm_naechstes_spiel liga_id="123" team="Eichehorn" verband="fvd"]
 * [sm_letztes_spiel liga_id="123" team="Eichehorn"]
 */
class SMF_Shortcodes {

    public function register() {
        add_shortcode( 'sm_tabelle',            array( $this, 'shortcode_tabelle' ) );
        add_shortcode( 'sm_spiele',             array( $this, 'shortcode_spiele' ) );
        add_shortcode( 'sm_naechstes_spiel',    array( $this, 'shortcode_naechstes_spiel' ) );
        add_shortcode( 'sm_letztes_spiel',      array( $this, 'shortcode_letztes_spiel' ) );
        add_shortcode( 'sm_vereinsuebersicht',  array( $this, 'shortcode_vereinsuebersicht' ) );
    }

    // ----------------------------------------------------------------
    // [sm_tabelle liga_id="123" verband="fvd" titel="true"]
    // ----------------------------------------------------------------
    public function shortcode_tabelle( $atts ) {
        $atts = shortcode_atts( array(
            'liga_id' => get_option( 'smf_default_league_id', '' ),
            'verband' => '',
            'titel'   => 'true',
            'logos'   => 'false',
        ), $atts, 'sm_tabelle' );

        $liga_id = (int) $atts['liga_id'];
        if ( ! $liga_id ) {
            return $this->error( 'Bitte liga_id angeben, z.B. [sm_tabelle liga_id="123"]' );
        }

        $api = $this->make_api( $atts['verband'] );
        if ( is_string( $api ) ) return $api;

        $table = $api->get_table( $liga_id );
        if ( is_wp_error( $table ) ) {
            return $this->error( $table->get_error_message() );
        }

        $league      = $api->get_league( $liga_id );
        $league_name = is_wp_error( $league ) ? '' : ( isset( $league['name'] ) ? $league['name'] : '' );

        ob_start();
        smf_render_template( 'table', array(
            'table'       => $table,
            'league_name' => $league_name,
            'show_title'  => $atts['titel'] !== 'false',
            'show_logos'  => $atts['logos'] === 'true',
        ) );
        return ob_get_clean();
    }

    // ----------------------------------------------------------------
    // [sm_spiele liga_id="123" verband="fvd" anzahl="10" team="Eichehorn" modus="alle|vergangen|kommend"]
    // ----------------------------------------------------------------
    public function shortcode_spiele( $atts ) {
        $atts = shortcode_atts( array(
            'liga_id' => get_option( 'smf_default_league_id', '' ),
            'verband' => '',
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

        $api = $this->make_api( $atts['verband'] );
        if ( is_string( $api ) ) return $api;

        $schedule = $api->get_schedule( $liga_id );
        if ( is_wp_error( $schedule ) ) {
            return $this->error( $schedule->get_error_message() );
        }

        $games = $this->normalize_schedule( $schedule );

        if ( ! empty( $atts['team'] ) ) {
            $games = $api->filter_by_team( $games, $atts['team'] );
        }

        $self = $this;
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

        ob_start();
        smf_render_template( 'games-list', array(
            'games'       => $games,
            'league_name' => $league_name,
            'show_title'  => $atts['titel'] !== 'false',
            'modus'       => $atts['modus'],
            'show_logos'  => $atts['logos'] === 'true',
        ) );
        return ob_get_clean();
    }

    // ----------------------------------------------------------------
    // [sm_naechstes_spiel liga_id="123" verband="fvd" team="Eichehorn"]
    // ----------------------------------------------------------------
    public function shortcode_naechstes_spiel( $atts ) {
        $atts = shortcode_atts( array(
            'liga_id' => get_option( 'smf_default_league_id', '' ),
            'verband' => '',
            'team'    => '',
            'logos'   => 'true',
        ), $atts, 'sm_naechstes_spiel' );

        $liga_id = (int) $atts['liga_id'];
        if ( ! $liga_id ) {
            return $this->error( 'Bitte liga_id angeben.' );
        }

        $api = $this->make_api( $atts['verband'] );
        if ( is_string( $api ) ) return $api;

        $schedule = $api->get_schedule( $liga_id );
        if ( is_wp_error( $schedule ) ) {
            return $this->error( $schedule->get_error_message() );
        }

        $games = $this->normalize_schedule( $schedule );

        if ( ! empty( $atts['team'] ) ) {
            $games = $api->filter_by_team( $games, $atts['team'] );
        }

        $game = $api->get_next_game( $games );
        if ( ! $game ) {
            return '<div class="smf-notice">Kein kommendes Spiel gefunden.</div>';
        }

        $league      = $api->get_league( $liga_id );
        $league_name = is_wp_error( $league ) ? '' : ( isset( $league['name'] ) ? $league['name'] : '' );

        ob_start();
        smf_render_template( 'single-game', array(
            'game'        => $game,
            'league_name' => $league_name,
            'label'       => 'Nächstes Spiel',
            'show_logos'  => $atts['logos'] === 'true',
        ) );
        return ob_get_clean();
    }

    // ----------------------------------------------------------------
    // [sm_letztes_spiel liga_id="123" verband="fvd" team="Eichehorn"]
    // ----------------------------------------------------------------
    public function shortcode_letztes_spiel( $atts ) {
        $atts = shortcode_atts( array(
            'liga_id' => get_option( 'smf_default_league_id', '' ),
            'verband' => '',
            'team'    => '',
            'logos'   => 'true',
        ), $atts, 'sm_letztes_spiel' );

        $liga_id = (int) $atts['liga_id'];
        if ( ! $liga_id ) {
            return $this->error( 'Bitte liga_id angeben.' );
        }

        $api = $this->make_api( $atts['verband'] );
        if ( is_string( $api ) ) return $api;

        $schedule = $api->get_schedule( $liga_id );
        if ( is_wp_error( $schedule ) ) {
            return $this->error( $schedule->get_error_message() );
        }

        $games = $this->normalize_schedule( $schedule );

        if ( ! empty( $atts['team'] ) ) {
            $games = $api->filter_by_team( $games, $atts['team'] );
        }

        $game = $api->get_last_game( $games );
        if ( ! $game ) {
            return '<div class="smf-notice">Kein gespieltes Spiel gefunden.</div>';
        }

        $league      = $api->get_league( $liga_id );
        $league_name = is_wp_error( $league ) ? '' : ( isset( $league['name'] ) ? $league['name'] : '' );

        ob_start();
        smf_render_template( 'single-game', array(
            'game'        => $game,
            'league_name' => $league_name,
            'label'       => 'Letztes Spiel',
            'show_logos'  => $atts['logos'] === 'true',
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

        ob_start();
        smf_render_template( 'club-overview', array(
            'club_name' => isset( $club['name'] ) ? $club['name'] : '',
            'upcoming'  => $games['upcoming'],
            'played'    => $games['played'],
        ) );
        return ob_get_clean();
    }

    // ----------------------------------------------------------------
    // Hilfsfunktionen
    // ----------------------------------------------------------------

    /**
     * SMF_API-Instanz für den angegebenen Verbands-Slug erstellen.
     * Gibt einen HTML-Fehlerstring zurück wenn der Slug unbekannt ist.
     *
     * @param string $slug
     * @return SMF_API|string
     */
    private function make_api( $slug ) {
        if ( $slug === '' ) {
            return new SMF_API();
        }

        $url = SMF_API::url_for_verband( $slug );
        if ( $url === null ) {
            return $this->error(
                "Unbekannter Verband \"{$slug}\". Bitte zuerst unter SM Floorball \xe2\x86\x92 Verb\xc3\xa4nde anlegen."
            );
        }

        return new SMF_API( $url );
    }

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
     * @param string $msg
     * @return string
     */
    private function error( $msg ) {
        return '<div class="smf-error"><strong>Saisonmanager Fehler:</strong> ' . esc_html( $msg ) . '</div>';
    }
}
