<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin-Hilfswerkzeug: findet Vereine + deren Team-IDs über
 * game_operations/{id}/clubs[/{season_id}], damit Team-IDs für die
 * Vereinskonfiguration nicht mehr manuell im Saisonmanager-Frontend
 * herausgesucht werden müssen.
 */
class SMF_TeamFinder {

    /**
     * Bekannte Spielbetriebsstellen-IDs (game_operation_id). Deckt alle dem
     * Autor bekannten Verbände auf saisonmanager.de ab (gleiche Liste wie im
     * separaten Floorball-Dashboard-iOS-Projekt, dort SM_OPERATIONS). Falls
     * ein neuer Verband dort auftaucht und hier fehlt, einfach ergänzen.
     */
    const OPERATION_IDS = array( 1, 2, 3, 4, 5, 6, 8, 9, 10, 11 );

    /**
     * Vereine (und deren Teams) suchen, deren Name den Suchbegriff enthält
     * oder deren Club-ID exakt passt - über alle bekannten
     * Spielbetriebsstellen hinweg. Nutzt den bestehenden SMF_API-Cache, ein
     * erneuter Aufruf mit denselben Parametern kostet also innerhalb der
     * Cache-Dauer keinen zusätzlichen Request.
     *
     * @param SMF_API  $api
     * @param string   $query      Vereinsname (Teilstring, case-insensitive) oder numerische Club-ID
     * @param int|null $season_id  Optional, leer = aktuelle Saison
     * @return array[] Liste von { club_id, club_name, operation_id, teams: [{id, name}] }
     */
    public static function search( SMF_API $api, $query, $season_id = null ) {
        $query = trim( (string) $query );
        if ( $query === '' ) {
            return array();
        }

        $is_numeric = ctype_digit( $query );
        $needle     = strtolower( $query );
        $results    = array();

        foreach ( self::OPERATION_IDS as $operation_id ) {
            $endpoint = "game_operations/{$operation_id}/clubs";
            if ( $season_id ) {
                $endpoint .= '/' . absint( $season_id );
            }

            $clubs = $api->get_raw( $endpoint );
            if ( is_wp_error( $clubs ) || ! is_array( $clubs ) ) {
                continue; // einzelne Spielbetriebsstelle überspringen statt die ganze Suche scheitern zu lassen
            }

            foreach ( $clubs as $club ) {
                if ( ! is_array( $club ) ) continue;

                $club_id   = isset( $club['id'] ) ? (int) $club['id'] : 0;
                $club_name = isset( $club['name'] ) ? (string) $club['name'] : '';

                $matches = $is_numeric
                    ? ( $club_id === (int) $query )
                    : ( $club_name !== '' && strpos( strtolower( $club_name ), $needle ) !== false );

                if ( ! $matches ) continue;

                $teams     = array();
                $raw_teams = isset( $club['teams'] ) && is_array( $club['teams'] ) ? $club['teams'] : array();
                foreach ( $raw_teams as $team ) {
                    if ( ! is_array( $team ) ) continue;
                    $team_id   = isset( $team['id'] )   ? (int) $team['id']    : ( isset( $team['team_id'] )   ? (int) $team['team_id']   : 0 );
                    $team_name = isset( $team['name'] ) ? (string) $team['name'] : ( isset( $team['team_name'] ) ? (string) $team['team_name'] : '' );
                    if ( $team_id ) {
                        $teams[] = array( 'id' => $team_id, 'name' => $team_name );
                    }
                }

                $results[] = array(
                    'club_id'      => $club_id,
                    'club_name'    => $club_name,
                    'operation_id' => $operation_id,
                    'teams'        => $teams,
                );
            }
        }

        return $results;
    }

    /**
     * Alle Teams einer Club-ID ermitteln (über alle Spielbetriebsstellen
     * gemergt und nach Team-ID dedupliziert), jeweils angereichert mit den
     * Liga-IDs, in denen das Team aktuell spielt - praktisch, um diese
     * direkt in andere Shortcodes ([sm_tabelle liga_id="…"]) zu übernehmen.
     * Ein zusätzlicher Request pro gefundenem Team (get_team_matches) - in
     * Ordnung, da diese Methode nur bei manuellem Admin-Klick läuft, nicht
     * bei jedem Seitenaufruf.
     *
     * @param SMF_API $api
     * @param int     $club_id
     * @return array[] Liste von { id, name, leagues: [{id, name, short_name}] }
     */
    public static function teams_for_club( SMF_API $api, $club_id ) {
        $club_id = absint( $club_id );
        if ( ! $club_id ) {
            return array();
        }

        $found = self::search( $api, (string) $club_id );

        $teams = array();
        foreach ( $found as $club ) {
            foreach ( $club['teams'] as $team ) {
                $teams[ $team['id'] ] = $team; // nach Team-ID deduplizieren
            }
        }

        foreach ( $teams as $team_id => &$team ) {
            $matches = $api->get_team_matches( $team_id );
            $team['leagues'] = ( ! is_wp_error( $matches ) && isset( $matches['leagues'] ) && is_array( $matches['leagues'] ) )
                ? $matches['leagues']
                : array();
        }
        unset( $team );

        return array_values( $teams );
    }
}
