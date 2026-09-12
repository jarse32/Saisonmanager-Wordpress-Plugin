<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Aggregiert Spielplandaten aller Teams eines Vereins
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
     * Team-IDs kommen aus zwei Quellen, die vereinigt werden:
     * 1. Automatisch erkannt über die Club-ID (siehe SMF_TeamFinder::teams_for_club(),
     *    zwischengespeichert in der Option smf_club_teams_cache über den
     *    "Teams laden"-Button in den Einstellungen).
     * 2. Manuell ergänzte Team-ID-Zeilen (Fallback für Sonderfälle, z.B. wenn
     *    die Club-ID-Erkennung ein Team nicht findet).
     *
     * @param array $club    Vereins-Konfiguration (aus get_club())
     * @param int   $anzahl  Max. Spiele pro Kategorie
     * @return array {
     *     @type array         $upcoming
     *     @type array         $played
     *     @type int|null      $stale_since         Ältester Notreserve-Zeitstempel
     *                                               über alle Team-Requests, oder null
     *     @type WP_Error|null $error               Gesetzt nur, wenn AUSNAHMSLOS alle
     *                                               Teams gescheitert sind (weder frisch
     *                                               noch Notreserve) - Aufrufer soll dann
     *                                               einen Hinweis statt leerer Spalten zeigen.
     *     @type int           $partial_error_count Anzahl gescheiterter Teams, wenn NICHT
     *                                               alle gescheitert sind (Rest wird trotzdem
     *                                               angezeigt, dazu ein dezenter Hinweis)
     * }
     */
    public static function get_games( array $club, $anzahl = 4 ) {
        $team_ids = array();

        $club_id = (int) ( isset( $club['club_id'] ) ? $club['club_id'] : 0 );
        if ( $club_id ) {
            $cache = get_option( 'smf_club_teams_cache', array() );
            $cached_teams = isset( $cache[ $club_id ]['teams'] ) && is_array( $cache[ $club_id ]['teams'] )
                ? $cache[ $club_id ]['teams']
                : array();
            foreach ( $cached_teams as $team ) {
                $id = isset( $team['id'] ) ? (int) $team['id'] : 0;
                if ( $id ) $team_ids[] = $id;
            }
        }

        foreach ( ( isset( $club['teams'] ) ? $club['teams'] : array() ) as $team_cfg ) {
            $id = isset( $team_cfg['team_id'] ) ? (int) $team_cfg['team_id'] : 0;
            if ( $id ) $team_ids[] = $id;
        }

        $team_ids = array_values( array_unique( $team_ids ) );

        $api        = new SMF_API();
        $all_games  = array();
        $team_count = count( $team_ids );
        $fail_count = 0;
        $last_error = null;

        foreach ( $team_ids as $team_id ) {
            $result = $api->get_team_matches( $team_id );
            if ( is_wp_error( $result ) ) {
                // Ein kaputtes Team soll die anderen nicht mitreißen - wird
                // übersprungen, der Fehler aber mitgezählt und unten nach
                // oben gereicht (siehe $error/$partial_error_count).
                $fail_count++;
                $last_error = $result;
                continue;
            }

            $matches = isset( $result['matches'] ) && is_array( $result['matches'] ) ? $result['matches'] : array();
            foreach ( $matches as &$game ) {
                $game['_liga_name'] = isset( $game['league_name'] ) ? $game['league_name'] : ( isset( $game['league_short_name'] ) ? $game['league_short_name'] : '' );
            }
            unset( $game );

            $all_games = array_merge( $all_games, $matches );
        }

        // Duplikate anhand game_id entfernen (z.B. wenn zwei eigene Teams gegeneinander spielen)
        $seen      = array();
        $all_games = array_values( array_filter( $all_games, function ( $g ) use ( &$seen ) {
            $id = isset( $g['game_id'] ) ? $g['game_id'] : null;
            if ( $id === null ) return true;
            if ( isset( $seen[ $id ] ) ) return false;
            $seen[ $id ] = true;
            return true;
        } ) );

        // Anstehende Spiele: aufsteigend nach Datum
        $upcoming = $api->filter_upcoming_games( $all_games );
        usort( $upcoming, function ( $a, $b ) use ( $api ) {
            return $api->parse_game_date( $a ) - $api->parse_game_date( $b );
        } );
        $upcoming = array_slice( $upcoming, 0, $anzahl );

        // Gespielte Spiele: absteigend nach Datum (neueste zuerst)
        $played = $api->filter_past_games( $all_games );
        usort( $played, function ( $a, $b ) use ( $api ) {
            return $api->parse_game_date( $b ) - $api->parse_game_date( $a );
        } );
        $played = array_slice( $played, 0, $anzahl );

        // "Alle Teams gescheitert" nur, wenn überhaupt Teams konfiguriert
        // waren - eine leere Team-Liste ist ein Konfigurationsproblem, kein
        // Ausfall, und wird von SMF_Shortcodes weiterhin normal (leere
        // Spalten) behandelt.
        $all_failed = ( $team_count > 0 && $fail_count === $team_count );

        return array(
            'upcoming'             => $upcoming,
            'played'               => $played,
            // Erst hier, nach dem letzten get_team_matches()-Aufruf, lesen.
            'stale_since'          => $api->stale_since(),
            'error'                => $all_failed ? $last_error : null,
            'partial_error_count'  => ( ! $all_failed && $fail_count > 0 ) ? $fail_count : 0,
        );
    }
}
