<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Aggregiert Spielplandaten aller Teams eines Vereins aus mehreren Ligen/Verbänden
 */
class SMF_ClubOverview {

    /**
     * Verein-Konfiguration anhand von Name oder Slug laden.
     * Gibt den ersten konfigurierten Verein zurück wenn $identifier leer ist.
     *
     * @param string $identifier  Name oder Slug
     * @return array|null
     */
    public static function get_club( $identifier = '' ) {
        $vereine = get_option( 'smf_vereine', array() );
        if ( empty( $vereine ) ) return null;

        $id = strtolower( trim( $identifier ) );

        if ( $id !== '' ) {
            foreach ( $vereine as $verein ) {
                if ( strtolower( $verein['slug'] ?? '' ) === $id ) return $verein;
                if ( strtolower( $verein['name'] ?? '' ) === $id ) return $verein;
            }
        }

        // Fallback: ersten Verein zurückgeben
        return $vereine[0];
    }

    /**
     * Alle Spiele eines Vereins laden, aufgeteilt in upcoming/played.
     *
     * @param array $club    Vereins-Konfiguration (aus get_club())
     * @param int   $anzahl  Max. Spiele pro Kategorie
     * @return array { upcoming: array, played: array }
     */
    public static function get_games( array $club, $anzahl = 4 ) {
        $all_games = array();
        $teams     = isset( $club['teams'] ) ? $club['teams'] : array();

        foreach ( $teams as $team_cfg ) {
            $liga_id   = (int) ( isset( $team_cfg['liga_id'] )   ? $team_cfg['liga_id']   : 0 );
            $team_name = trim( isset( $team_cfg['team'] )        ? $team_cfg['team']        : '' );
            $verband   = trim( isset( $team_cfg['verband'] )     ? $team_cfg['verband']     : '' );
            $liga_name = trim( isset( $team_cfg['liga_name'] )   ? $team_cfg['liga_name']   : '' );

            if ( ! $liga_id ) continue;

            // API-Instanz für diesen Verband ermitteln
            if ( $verband ) {
                $api_url = SMF_API::url_for_verband( $verband );
                if ( ! $api_url ) continue; // Verband nicht konfiguriert
                $api = new SMF_API( $api_url );
            } else {
                $api_url = rtrim( get_option( 'smf_api_base_url', 'https://saisonmanager.de/api/v2' ), '/' );
                $api     = new SMF_API();
            }

            $schedule = $api->get_schedule( $liga_id );
            if ( is_wp_error( $schedule ) ) continue;

            $games = self::normalize_schedule( $schedule );

            if ( $team_name !== '' ) {
                $games = $api->filter_by_team( $games, $team_name );
            }

            // Metadaten zu jedem Spiel hinzufügen
            foreach ( $games as &$game ) {
                $game['_liga_name'] = $liga_name !== '' ? $liga_name : ( 'Liga ' . $liga_id );
                $game['_api_url']   = $api_url;
            }
            unset( $game );

            $all_games = array_merge( $all_games, $games );
        }

        // Duplikate anhand game_id entfernen
        $seen      = array();
        $all_games = array_values( array_filter( $all_games, function ( $g ) use ( &$seen ) {
            $id = isset( $g['game_id'] ) ? $g['game_id'] : null;
            if ( $id === null ) return true;
            if ( isset( $seen[ $id ] ) ) return false;
            $seen[ $id ] = true;
            return true;
        } ) );

        // Für Sortierung/Filterung: API-Instanz (URL ist für diese Methoden irrelevant)
        $api_ref = new SMF_API();

        // Anstehende Spiele: aufsteigend nach Datum
        $upcoming = $api_ref->filter_upcoming_games( $all_games );
        usort( $upcoming, function ( $a, $b ) use ( $api_ref ) {
            return $api_ref->parse_game_date( $a ) - $api_ref->parse_game_date( $b );
        } );
        $upcoming = array_slice( $upcoming, 0, $anzahl );

        // Gespielte Spiele: absteigend nach Datum (neueste zuerst)
        $played = $api_ref->filter_past_games( $all_games );
        usort( $played, function ( $a, $b ) use ( $api_ref ) {
            return $api_ref->parse_game_date( $b ) - $api_ref->parse_game_date( $a );
        } );
        $played = array_slice( $played, 0, $anzahl );

        return array(
            'upcoming' => $upcoming,
            'played'   => $played,
        );
    }

    /**
     * Spielplan-Antwort normalisieren (API kann verschiedene Strukturen liefern)
     *
     * @param mixed $schedule
     * @return array
     */
    private static function normalize_schedule( $schedule ) {
        if ( ! is_array( $schedule ) )                                        return array();
        if ( isset( $schedule['games'] ) && is_array( $schedule['games'] ) ) return $schedule['games'];
        if ( isset( $schedule['data'] )  && is_array( $schedule['data'] ) )  return $schedule['data'];
        if ( isset( $schedule[0] ) )                                          return array_values( $schedule );
        return array();
    }
}
