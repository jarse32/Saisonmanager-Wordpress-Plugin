<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Kommunikation mit der Saisonmanager-API
 */
class SMF_API {

    /** Aufeinanderfolgende Fehler, nach denen der Breaker für einen Host öffnet. */
    const BREAKER_THRESHOLD = 3;

    /** Sekunden, die der Breaker nach dem Auslösen geschlossen bleibt (Ausnahme: 429, siehe request()). */
    const BREAKER_COOLDOWN = 120;

    /** Obergrenze für eine vom Server per Retry-After vorgegebene Sperrzeit (Sekunden). */
    const BREAKER_MAX_RETRY_AFTER = 900;

    /** Fallback-Sperrzeit bei 429 ohne Retry-After-Header (Sekunden). */
    const BREAKER_DEFAULT_RETRY_AFTER = 60;

    /** @var string */
    private $base_url;

    /** @var string API-Key, wird als X-Api-Key-Header mitgeschickt (serverseitig, nie an Besucher ausgeliefert) */
    private $api_key;

    /** @var SMF_Cache */
    private $cache;

    /**
     * Ältester Zeitstempel, aus dem diese Instanz bereits Notreserve-Daten
     * ausgeliefert hat - siehe stale_since(). Ein Shortcode macht oft
     * mehrere Requests (z.B. Tabelle + Liganame); der Hinweis im Frontend
     * soll den konservativsten (ältesten) Stand zeigen.
     *
     * @var int|null
     */
    private $stale_since = null;

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

        $parsed = wp_parse_url( $base_url );
        $domain = $parsed['scheme'] . '://' . $parsed['host'];

        return $domain . '/' . ltrim( $path, '/' );
    }

    /**
     * Generischer GET-Request mit Caching, Fail-Fast-Timeout, Circuit
     * Breaker und Stale-Fallback.
     *
     * @param string $endpoint
     * @param bool   $mirror   Antwort zusätzlich in die Notreserve spiegeln
     *                         (SMF_Cache::set()) - false bei Endpunkten mit
     *                         personenbezogenen Daten oder unverhältnis-
     *                         mäßig großen Antworten, siehe Aufrufstellen.
     * @return array|WP_Error
     */
    private function request( $endpoint, $mirror = true ) {
        $url    = $this->base_url . '/' . ltrim( $endpoint, '/' );
        $cached = $this->cache->get( $url );

        if ( false !== $cached ) {
            return $cached;
        }

        $host = $this->get_host();

        // Kern der Ausfallsicherheit: Ist der Breaker für diesen Host offen,
        // wird gar nicht erst versucht, eine Verbindung aufzubauen. Nur der
        // erste Request während eines Ausfalls kostet Zeit, alle folgenden
        // Shortcodes auf derselben Seite kosten nichts.
        if ( $this->breaker_is_open( $host ) ) {
            return $this->stale_or_error( $url, 0, 'Breaker offen für ' . $host );
        }

        // Nur zum Testen des Ausfallverhaltens (siehe README/CHANGELOG),
        // bewusst keine Backend-Option: greift ausschließlich bei
        // WP_DEBUG === true und einer ausdrücklichen Konstante bzw. einem
        // Filter, damit niemand versehentlich einen echten Ausfall simuliert.
        if ( self::is_outage_simulated() ) {
            $this->breaker_record_failure( $host );
            return $this->stale_or_error( $url, 0, 'Simulierter Ausfall (SMF_SIMULATE_OUTAGE)' );
        }

        $timeout = max( 2, (int) get_option( 'smf_api_timeout', 6 ) );

        $args = array(
            'timeout'    => $timeout,
            'user-agent' => 'WordPress/SMF-Plugin ' . SMF_VERSION,
        );

        // Key wird ausschließlich hier, serverseitig, angehängt - taucht nie
        // im Browser oder in an Besucher ausgelieferten Seiteninhalten auf.
        if ( $this->api_key !== '' ) {
            $args['headers'] = array( 'X-Api-Key' => $this->api_key );
        }

        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            $this->breaker_record_failure( $host );
            return $this->stale_or_error( $url, 0, $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );

        if ( $code === 429 ) {
            // Ratelimit hängt an unserem Key - abwarten statt sofort erneut
            // zu versuchen. Retry-After ist serverseitig vorgegeben, wird
            // aber gegen Missbrauch/fehlerhafte Header begrenzt.
            $retry_after = (int) wp_remote_retrieve_header( $response, 'retry-after' );
            $retry_after = $retry_after > 0
                ? min( self::BREAKER_MAX_RETRY_AFTER, $retry_after )
                : self::BREAKER_DEFAULT_RETRY_AFTER;

            $this->breaker_record_failure( $host, $retry_after );
            return $this->stale_or_error( $url, 429, "HTTP 429 für $url, Sperre für {$retry_after}s" );
        }

        if ( $code >= 500 ) {
            $this->breaker_record_failure( $host );
            return $this->stale_or_error( $url, $code );
        }

        if ( $code !== 200 ) {
            // 4xx außer 429: Konfigurationsfehler (z.B. falsche Liga-ID,
            // fehlender Key). Löst den Breaker bewusst NICHT aus, sonst
            // blockiert eine einzelne falsche ID alle anderen Abfragen
            // über denselben Host. Kein Stale-Fallback - eine falsche ID
            // bleibt falsch, egal wie alt die gespiegelten Daten sind.
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

        $this->breaker_reset( $host );
        $this->cache->set( $url, $data, $mirror );
        return $data;
    }

    /**
     * Host der Basis-URL für den Circuit Breaker. Bewusst der Host und
     * nicht die volle URL - sonst würde der Breaker pro Endpunkt greifen
     * und nie tatsächlich einen ganzen Ausfall abdecken.
     *
     * @return string
     */
    private function get_host() {
        $parsed = wp_parse_url( $this->base_url );
        return isset( $parsed['host'] ) && $parsed['host'] !== '' ? $parsed['host'] : $this->base_url;
    }

    /**
     * @param string $host
     * @return string
     */
    private function breaker_key( $host ) {
        return 'smf_breaker_' . md5( $host );
    }

    /**
     * @param string $host
     * @return bool
     */
    private function breaker_is_open( $host ) {
        $state = get_transient( $this->breaker_key( $host ) );
        if ( ! is_array( $state ) || empty( $state['open_until'] ) ) {
            return false;
        }
        return time() < (int) $state['open_until'];
    }

    /**
     * Fehler für einen Host zählen und den Breaker ggf. öffnen.
     *
     * @param string   $host
     * @param int|null $force_open_seconds Bei 429 direkt für diese Dauer öffnen
     *                                     (Retry-After), statt erst nach
     *                                     BREAKER_THRESHOLD Fehlern in Folge.
     */
    private function breaker_record_failure( $host, $force_open_seconds = null ) {
        $key   = $this->breaker_key( $host );
        $state = get_transient( $key );
        if ( ! is_array( $state ) ) {
            $state = array( 'failures' => 0, 'open_until' => 0 );
        }

        if ( $force_open_seconds !== null ) {
            $state['failures']   = self::BREAKER_THRESHOLD;
            $state['open_until'] = time() + $force_open_seconds;
        } else {
            $state['failures']++;
            if ( $state['failures'] >= self::BREAKER_THRESHOLD ) {
                $state['open_until'] = time() + self::BREAKER_COOLDOWN;
            }
        }

        $ttl = max( self::BREAKER_COOLDOWN, $state['open_until'] - time() );
        set_transient( $key, $state, $ttl );

        update_option( 'smf_api_last_outage', time(), false );
    }

    /**
     * @param string $host
     */
    private function breaker_reset( $host ) {
        delete_transient( $this->breaker_key( $host ) );
    }

    /**
     * Testschalter für Phase 5 (Verifikation): true, wenn ein Ausfall
     * erzwungen werden soll, ohne auf einen echten warten zu müssen.
     * Greift ausschließlich bei WP_DEBUG === true, zusätzlich entweder über
     * die Konstante SMF_SIMULATE_OUTAGE (z.B. in wp-config.php) oder den
     * Filter 'smf_simulate_outage'. Bewusst nicht als Backend-Option, damit
     * das niemand versehentlich auf einer Live-Seite aktiviert.
     *
     * @return bool
     */
    private static function is_outage_simulated() {
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            return false;
        }
        if ( defined( 'SMF_SIMULATE_OUTAGE' ) && SMF_SIMULATE_OUTAGE ) {
            return true;
        }
        return (bool) apply_filters( 'smf_simulate_outage', false );
    }

    /**
     * Aktueller Breaker-Zustand für den konfigurierten Host - für die
     * Backend-Statusanzeige (SM Floorball -> Einstellungen).
     *
     * @return array{open: bool, open_until: int|null, host: string}
     */
    public static function get_breaker_status() {
        $api   = new self();
        $host  = $api->get_host();
        $state = get_transient( $api->breaker_key( $host ) );

        $open = is_array( $state ) && ! empty( $state['open_until'] ) && time() < (int) $state['open_until'];

        return array(
            'open'       => $open,
            'open_until' => $open ? (int) $state['open_until'] : null,
            'host'       => $host,
        );
    }

    /**
     * Liefert bei einem Fehler die Notreserve zurück, wenn vorhanden, sonst
     * einen WP_Error. Der Fehlertext bleibt bewusst allgemein - technische
     * Details (HTTP-Code, URL, Ursache) landen in den WP_Error-Daten, nicht
     * in der für Besucher:innen sichtbaren Message (siehe SMF_Shortcodes,
     * die daraus für Admins die Details, für alle anderen nur den
     * allgemeinen Satz anzeigt).
     *
     * @param string      $url
     * @param int         $code   HTTP-Code (0 = kein HTTP-Response, z.B. Netzwerkfehler oder offener Breaker)
     * @param string|null $detail Technischer Zusatz für die WP_Error-Daten
     * @return array|WP_Error
     */
    private function stale_or_error( $url, $code, $detail = null ) {
        $stale = $this->cache->get_stale( $url );

        if ( false !== $stale ) {
            $this->stale_since = ( $this->stale_since === null )
                ? $stale['time']
                : min( $this->stale_since, $stale['time'] );
            return $stale['data'];
        }

        $message = ( $code === 429 )
            ? 'Der Saisonmanager-Server hat aktuell zu viele Anfragen erhalten. Bitte in Kürze erneut versuchen.'
            : 'Der Saisonmanager-Server ist aktuell nicht erreichbar. Bitte später erneut versuchen.';

        return new WP_Error(
            'smf_api_unavailable',
            $message,
            array(
                'http_code' => $code,
                'url'       => $url,
                'detail'    => $detail,
            )
        );
    }

    /**
     * Ältester Zeitstempel, aus dem diese Instanz Notreserve-Daten
     * ausgeliefert hat, oder null, wenn alle Antworten frisch waren. Erst
     * nach dem letzten API-Aufruf eines Shortcodes lesen, nicht zwischen
     * zwei Aufrufen - ein Shortcode macht oft mehrere Requests.
     *
     * @return int|null
     */
    public function stale_since() {
        return $this->stale_since;
    }

    /** @return array|WP_Error */
    public function get_table( $league_id ) {
        return $this->request( "leagues/{$league_id}/table.json" );
    }

    /** @return array|WP_Error */
    public function get_schedule( $league_id ) {
        return $this->request( "leagues/{$league_id}/schedule.json" );
    }

    /**
     * Kein Stale-Spiegel: Antwort enthält u.a. players[] und referees[]
     * (Namen), wird ausschließlich per AJAX für das Spieldetail-Modal
     * geladen und blockiert damit ohnehin keinen Seitenaufbau.
     *
     * @return array|WP_Error
     */
    public function get_game( $game_id ) {
        return $this->request( "games/{$game_id}.json", false );
    }

    /** @return array|WP_Error */
    public function get_league( $league_id ) {
        return $this->request( "leagues/{$league_id}.json" );
    }

    /**
     * Kein Stale-Spiegel: Antwort umfasst alle Ligen des Verbands und kann
     * mehrere hundert KB groß werden - das gehört nicht als serialisierte
     * Option dauerhaft in wp_options. Aktuell von keiner Aufrufstelle im
     * Plugin genutzt.
     *
     * @return array|WP_Error
     */
    public function get_leagues() {
        return $this->request( 'leagues.json', false );
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
     * Team-Statistik (u.a. Scorerliste) - Rohantwort von teams/{id}/stats.
     * Enthält neben "scorer" auch "team", "recent_games", "upcoming_games"
     * und "totals"; die Auswertung übernimmt SMF_Scorer.
     *
     * Der Endpunktpfad ist über den Filter 'smf_scorer_endpoint' an dieser
     * einen Stelle austauschbar.
     *
     * Kein Stale-Spiegel: "scorer" enthält Vor-/Nachnamen der Spieler:innen.
     *
     * @param int $team_id
     * @return array|WP_Error
     */
    public function get_scorer( $team_id ) {
        $endpoint = apply_filters( 'smf_scorer_endpoint', "teams/{$team_id}/stats", (int) $team_id );
        return $this->request( $endpoint, false );
    }

    /**
     * Generischer, öffentlicher Zugriff auf beliebige (bekannte) Endpunkte -
     * für Hilfswerkzeuge wie SMF_TeamFinder, die keine eigene Wrapper-Methode
     * rechtfertigen. Default kein Stale-Spiegel: die bisherigen Aufrufstellen
     * (Team-Finder, Saison-Ermittlung über init.json) sind reine Admin-
     * Werkzeuge, bei denen "Server aktuell nicht erreichbar, bitte später
     * erneut versuchen" die richtige Antwort ist - keine Notreserve nötig,
     * hält wp_options schlank.
     *
     * @param string $endpoint
     * @param bool   $mirror
     * @return array|WP_Error
     */
    public function get_raw( $endpoint, $mirror = false ) {
        return $this->request( $endpoint, $mirror );
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
        return array_values( array_filter( $games, function( $game ) use ( $team_name ) {
            $home  = isset( $game['home_team_name'] )  ? $game['home_team_name']  : '';
            $guest = isset( $game['guest_team_name'] ) ? $game['guest_team_name'] : '';
            return self::name_matches( $home, $team_name ) || self::name_matches( $guest, $team_name );
        } ) );
    }

    /**
     * Teilstring-Namensabgleich, case-insensitive - einzige Stelle für diesen
     * Vergleich, genutzt von filter_by_team() oben und von
     * SMF_Highlight::should_highlight() für den expliziten Override des
     * hervorheben-Attributs (Namensfragment statt Team-ID).
     *
     * @param string $team_name Zu prüfender Name (z.B. home_team_name)
     * @param string $needle    Gesuchter Teilstring (z.B. Attributwert)
     * @return bool
     */
    public static function name_matches( $team_name, $needle ) {
        $needle = strtolower( trim( (string) $needle ) );
        if ( $needle === '' ) {
            return false;
        }
        return strpos( strtolower( (string) $team_name ), $needle ) !== false;
    }

    public function flush_cache() {
        $this->cache->flush();
    }

    /**
     * Löscht auch die Notreserve (Stufe 2) - nur für den ausdrücklichen
     * "Cache vollständig zurücksetzen"-Knopf im Backend.
     */
    public function flush_all_cache() {
        $this->cache->flush_all();
    }
}
