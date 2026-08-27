<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Kommunikation mit der Saisonmanager-API
 */
class SMF_API {

    /** @var string */
    private $base_url;

    /** @var string API-Key, wird als X-Api-Key-Header mitgeschickt (serverseitig, nie an Besucher ausgeliefert) */
    private $api_key;

    /** @var SMF_Cache */
    private $cache;

    /**
     * @param string|null $base_url Optionale URL-Überschreibung (Sonderfälle/Tests)
     */
    public function __construct( $base_url = null ) {
        $this->base_url = $base_url
            ? rtrim( $base_url, '/' )
            : rtrim( get_option( 'smf_api_base_url', 'https://saisonmanager.de/api/v2' ), '/' );

        $this->api_key = trim( get_option( 'smf_api_key', '' ) );

        $this->cache = new SMF_Cache();
    }

    /**
     * Relativen Logo-Pfad der API in eine absolute URL umwandeln.
     * API liefert z.B. "/api/storage/blobs/redirect/..." → "https://saisonmanager.de/api/storage/..."
     *
     * @param string      $path     Relativer Pfad oder bereits absolute URL
     * @param string|null $base_url Basis-URL (optional, nutzt sonst gespeicherte Option)
     * @return string
     */
    public static function get_logo_url( $path, $base_url = null ) {
        if ( ! $path ) return '';
        if ( strpos( $path, 'http' ) === 0 ) return $path; // bereits absolut

        if ( ! $base_url ) {
            $base_url = get_option( 'smf_api_base_url', 'https://saisonmanager.de/api/v2' );
        }

        $parsed = parse_url( $base_url );
        $domain = $parsed['scheme'] . '://' . $parsed['host'];

        return $domain . '/' . ltrim( $path, '/' );
    }

    /**
     * Generischer GET-Request mit Caching
     *
     * @param string $endpoint
     * @return array|WP_Error
     */
    private function request( $endpoint ) {
        $url    = $this->base_url . '/' . ltrim( $endpoint, '/' );
        $cached = $this->cache->get( $url );

        if ( false !== $cached ) {
            return $cached;
        }

        $args = array(
            'timeout'    => 15,
            'user-agent' => 'WordPress/SMF-Plugin ' . SMF_VERSION,
        );

        // Key wird ausschließlich hier, serverseitig, angehängt - taucht nie
        // im Browser oder in an Besucher ausgelieferten Seiteninhalten auf.
        if ( $this->api_key !== '' ) {
            $args['headers'] = array( 'X-Api-Key' => $this->api_key );
        }

        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return new WP_Error( 'api_error', "API Fehler: HTTP $code für $url" );
        }

        $content_type = wp_remote_retrieve_header( $response, 'content-type' );
        if ( strpos( $content_type, 'json' ) === false ) {
            return new WP_Error(
                'invalid_response',
                "Die URL liefert kein JSON (Content-Type: {$content_type}). Bitte die Standard-API-URL in den Einstellungen prüfen – korrekt ist https://saisonmanager.de/api/v2"
            );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'json_error', 'Ungültige JSON-Antwort von: ' . $url );
        }

        $this->cache->set( $url, $data );
        return $data;
    }

    /** @return array|WP_Error */
    public function get_table( $league_id ) {
        return $this->request( "leagues/{$league_id}/table.json" );
    }

    /** @return array|WP_Error */
    public function get_schedule( $league_id ) {
        return $this->request( "leagues/{$league_id}/schedule.json" );
    }

    /** @return array|WP_Error */
    public function get_game( $game_id ) {
        return $this->request( "games/{$game_id}.json" );
    }

    /** @return array|WP_Error */
    public function get_league( $league_id ) {
        return $this->request( "leagues/{$league_id}.json" );
    }

    /** @return array|WP_Error */
    public function get_leagues() {
        return $this->request( 'leagues.json' );
    }

    /**
     * Alle Spiele EINES Teams über alle Wettbewerbe der Saison abrufen (Heim
     * und Auswärts, inkl. Liga-Zugehörigkeit je Spiel) - ein Request statt
     * eines Requests pro Liga/Wettbewerb.
     *
     * @param int $team_id
     * @return array|WP_Error Rohantwort mit u.a. 'team' und 'matches'
     */
    public function get_team_matches( $team_id ) {
        return $this->request( "teams/{$team_id}/matches" );
    }

    /**
     * Generischer, öffentlicher Zugriff auf beliebige (bekannte) Endpunkte -
     * für Hilfswerkzeuge wie SMF_TeamFinder, die keine eigene Wrapper-Methode
     * rechtfertigen.
     *
     * @param string $endpoint
     * @return array|WP_Error
     */
    public function get_raw( $endpoint ) {
        return $this->request( $endpoint );
    }

    /**
     * Aktuelle Saison-ID ermitteln (über init.json). Saison-IDs sind
     * fortlaufend nummeriert (z.B. 18 = aktuelle Saison, 17 = Vorsaison) -
     * hilft im Team-Finder dabei, ohne Rätselraten die richtige Saison-ID
     * für historische Team-Suchen einzutragen. Mehrere Feldnamen werden
     * defensiv probiert, da die genaue init.json-Struktur nicht dokumentiert
     * ist. Gibt null zurück, wenn nichts Verwertbares gefunden wird.
     *
     * @return int|null
     */
    public function get_current_season_id() {
        $data = $this->get_raw( 'init.json' );
        if ( is_wp_error( $data ) || ! is_array( $data ) ) {
            return null;
        }

        if ( isset( $data['current_season_id'] ) ) {
            return (int) $data['current_season_id'];
        }
        if ( isset( $data['current_season']['id'] ) ) {
            return (int) $data['current_season']['id'];
        }
        if ( isset( $data['seasons'] ) && is_array( $data['seasons'] ) ) {
            foreach ( $data['seasons'] as $season ) {
                if ( is_array( $season ) && ! empty( $season['current'] ) && isset( $season['id'] ) ) {
                    return (int) $season['id'];
                }
            }
            // Fallback: höchste Saison-ID als aktuelle annehmen (Saisons sind
            // laut Doku fortlaufend nummeriert).
            $ids = array_filter( array_map( function ( $s ) {
                return isset( $s['id'] ) ? (int) $s['id'] : null;
            }, $data['seasons'] ) );
            if ( ! empty( $ids ) ) {
                return max( $ids );
            }
        }

        return null;
    }

    /**
     * Unix-Timestamp aus Spieldaten extrahieren.
     * API liefert Datum und Zeit getrennt: date="YYYY-MM-DD", time="HH:MM"
     *
     * @param array $game
     * @return int|false
     */
    public function parse_game_date( $game ) {
        if ( empty( $game['date'] ) ) return false;

        $date_str = $game['date'];
        if ( ! empty( $game['time'] ) ) {
            $date_str .= ' ' . $game['time'];
        }

        $ts = strtotime( $date_str );
        return $ts ?: false;
    }

    /**
     * Prüfen ob ein Spiel ein Ergebnis hat.
     * API: ended=true und result.home_goals / result.guest_goals gesetzt
     *
     * @param array $game
     * @return bool
     */
    public function has_result( $game ) {
        if ( ! empty( $game['ended'] ) ) {
            return true;
        }
        // Fallback: result-Objekt vorhanden und befüllt
        if ( isset( $game['result']['home_goals'] ) && isset( $game['result']['guest_goals'] ) ) {
            return true;
        }
        return false;
    }

    /**
     * @param array $games
     * @return array
     */
    public function filter_past_games( $games ) {
        $now = time();
        return array_values( array_filter( $games, function( $game ) use ( $now ) {
            $date = $this->parse_game_date( $game );
            if ( ! $date ) return false;
            // Spiel gilt als vergangen wenn das Datum + 2h Puffer überschritten ist
            // (ended=true ist im Spielplan-Endpoint nicht immer gesetzt)
            return $date < ( $now - 7200 );
        } ) );
    }

    /**
     * @param array $games
     * @return array
     */
    public function filter_upcoming_games( $games ) {
        $now = time();
        return array_values( array_filter( $games, function( $game ) use ( $now ) {
            if ( $this->has_result( $game ) ) return false;
            $date = $this->parse_game_date( $game );
            return $date && $date >= $now;
        } ) );
    }

    /**
     * @param array $games
     * @return array|null
     */
    public function get_last_game( $games ) {
        $past = $this->filter_past_games( $games );
        if ( empty( $past ) ) return null;

        usort( $past, function( $a, $b ) {
            return $this->parse_game_date( $b ) - $this->parse_game_date( $a );
        } );

        return $past[0];
    }

    /**
     * @param array $games
     * @return array|null
     */
    public function get_next_game( $games ) {
        $upcoming = $this->filter_upcoming_games( $games );
        if ( empty( $upcoming ) ) return null;

        usort( $upcoming, function( $a, $b ) {
            return $this->parse_game_date( $a ) - $this->parse_game_date( $b );
        } );

        return $upcoming[0];
    }

    /**
     * @param array  $games
     * @param string $team_name
     * @return array
     */
    public function filter_by_team( $games, $team_name ) {
        $needle = strtolower( trim( $team_name ) );
        return array_values( array_filter( $games, function( $game ) use ( $needle ) {
            $home  = strtolower( isset( $game['home_team_name'] )  ? $game['home_team_name']  : '' );
            $guest = strtolower( isset( $game['guest_team_name'] ) ? $game['guest_team_name'] : '' );
            return strpos( $home, $needle ) !== false || strpos( $guest, $needle ) !== false;
        } ) );
    }

    public function flush_cache() {
        $this->cache->flush();
    }
}
