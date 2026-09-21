<?php
/**
 * Eigenständiger PHP-Test für SMF_API::get_game_stream_fields() - läuft
 * ohne echtes WordPress, ohne Composer, ohne Build-Step:
 *
 *     php tests/test-stream-fields-caching.php
 *
 * Beweist zwei Punkte aus dem Code-Review vor dem Commit:
 *
 * 1. Der eigene Cache-Eintrag von get_game_stream_fields() enthält
 *    AUSSCHLIESSLICH die zwei Stream-Felder (live_stream_link, vod_link) -
 *    nicht die rohe games/{id}-Antwort (die u.a. Spielernamen enthält).
 * 2. get_game_stream_fields() läuft über denselben Circuit Breaker wie
 *    alle anderen SMF_API-Aufrufe (weil es intern get_game() -> request()
 *    aufruft): Ist der Breaker für den Host offen, wird KEIN
 *    HTTP-Request ausgelöst (wp_remote_get() bleibt ungenutzt) und die
 *    Methode liefert leere Felder zurück - kein Sonderpfad, der den
 *    Breaker umgeht.
 *
 * WordPress-Funktionen werden hier (anders als in
 * test-stream-url-parsing.php) vollständig gestubbt, inkl. eines
 * In-Memory-Transient-/Options-Speichers und einer Gegenstelle für
 * wp_remote_get() - notwendig, weil SMF_API::request() (der Breaker
 * selbst) getestet werden soll, nicht nur reine Logik.
 *
 * Exit-Code 0 = alle Assertions bestanden, 1 = mindestens eine fehlgeschlagen.
 */

define( 'ABSPATH', '/tmp/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'SMF_VERSION', 'test' );

// --- In-Memory-Speicher für Optionen/Transients/simulierte HTTP-Antworten ---

$GLOBALS['__smf_options']      = array();
$GLOBALS['__smf_transients']   = array();
$GLOBALS['__smf_remote_calls'] = array(); // jeder wp_remote_get()-Aufruf landet hier, Beweis für "kein Request bei offenem Breaker"
$GLOBALS['__smf_next_response'] = null;    // von wp_remote_get() als nächstes zurückgegeben

function get_option( $key, $default = false ) {
    return $GLOBALS['__smf_options'][ $key ] ?? $default;
}
function update_option( $key, $value, $autoload = null ) {
    $GLOBALS['__smf_options'][ $key ] = $value;
    return true;
}

function get_transient( $key ) {
    return $GLOBALS['__smf_transients'][ $key ] ?? false;
}
function set_transient( $key, $value, $ttl = 0 ) {
    $GLOBALS['__smf_transients'][ $key ] = $value;
    return true;
}
function delete_transient( $key ) {
    unset( $GLOBALS['__smf_transients'][ $key ] );
    return true;
}

function wp_parse_url( $url, $component = -1 ) {
    return ( $component === -1 ) ? parse_url( $url ) : parse_url( $url, $component );
}

function wp_remote_get( $url, $args = array() ) {
    $GLOBALS['__smf_remote_calls'][] = $url;
    return $GLOBALS['__smf_next_response'];
}
function wp_remote_retrieve_response_code( $response ) {
    return $response['response']['code'] ?? 0;
}
function wp_remote_retrieve_body( $response ) {
    return $response['body'] ?? '';
}
function wp_remote_retrieve_header( $response, $header ) {
    return $response['headers'][ $header ] ?? '';
}

class WP_Error {
    private $code;
    private $message;
    private $data;
    public function __construct( $code = '', $message = '', $data = null ) {
        $this->code    = $code;
        $this->message = $message;
        $this->data    = $data;
    }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return $this->data; }
}
function is_wp_error( $thing ) {
    return $thing instanceof WP_Error;
}

require __DIR__ . '/../includes/class-cache.php';
require __DIR__ . '/../includes/class-api.php';

$failures = 0;
$count    = 0;

function smf_test_assert( string $label, $expected, $actual ): void {
    global $failures, $count;
    $count++;
    $ok = $expected === $actual;
    if ( ! $ok ) {
        $failures++;
    }
    echo ( $ok ? 'OK  ' : 'FAIL' ) . " $label\n";
    if ( ! $ok ) {
        echo '     erwartet: ' . var_export( $expected, true ) . "\n";
        echo '     erhalten: ' . var_export( $actual, true ) . "\n";
    }
}

// ====================================================================
// 1) Erfolgreiche Antwort: Cache-Eintrag enthält NUR die zwei Stream-
//    Felder, nicht die rohe games/{id}-Antwort (hier mit players[] als
//    Stellvertreter für Personendaten).
// ====================================================================

$GLOBALS['__smf_next_response'] = array(
    'response' => array( 'code' => 200 ),
    'headers'  => array( 'content-type' => 'application/json' ),
    'body'     => json_encode( array(
        'game_id'          => 999,
        'live_stream_link' => 'https://www.youtube.com/watch?v=aaaaaaaaaaa',
        'vod_link'         => null,
        'players'          => array( 'home' => array( array( 'player_name' => 'Geheim' ) ) ),
        'referees'         => array( array( 'first_name' => 'Auch', 'last_name' => 'Geheim' ) ),
    ) ),
);

$api    = new SMF_API();
$fields = $api->get_game_stream_fields( 999, 'upcoming' );

smf_test_assert(
    'Rückgabe: nur live_stream_link/vod_link',
    array( 'live_stream_link' => 'https://www.youtube.com/watch?v=aaaaaaaaaaa', 'vod_link' => null ),
    $fields
);

$cached_entry = $GLOBALS['__smf_transients']['smf_stream_fields_999'] ?? null;
smf_test_assert( 'Cache-Eintrag: genau zwei Schlüssel', array( 'live_stream_link', 'vod_link' ), is_array( $cached_entry ) ? array_keys( $cached_entry ) : null );
smf_test_assert( 'Cache-Eintrag: KEIN players[]', false, is_array( $cached_entry ) && array_key_exists( 'players', $cached_entry ) );
smf_test_assert( 'Cache-Eintrag: KEIN referees[]', false, is_array( $cached_entry ) && array_key_exists( 'referees', $cached_entry ) );
smf_test_assert( 'Cache-Eintrag: KEIN game_id (kein Rest der Rohantwort)', false, is_array( $cached_entry ) && array_key_exists( 'game_id', $cached_entry ) );

// Request-Dedup: zweiter Aufruf für DASSELBE Spiel im selben PHP-Prozess
// löst keinen weiteren HTTP-Request aus (statischer Zwischenspeicher).
$calls_before = count( $GLOBALS['__smf_remote_calls'] );
$api->get_game_stream_fields( 999, 'upcoming' );
smf_test_assert( 'Zweiter Aufruf fürs selbe Spiel: kein weiterer HTTP-Request', $calls_before, count( $GLOBALS['__smf_remote_calls'] ) );

// ====================================================================
// 2) Offener Circuit Breaker: get_game_stream_fields() löst KEINEN
//    HTTP-Request aus und liefert leere Felder - derselbe Breaker wie
//    jeder andere SMF_API-Aufruf, kein Sonderpfad.
// ====================================================================

// Default-Host aus SMF_API::__construct() (smf_api_base_url-Fallback).
$host        = 'saisonmanager.de';
$breaker_key = 'smf_breaker_' . md5( $host );
$GLOBALS['__smf_transients'][ $breaker_key ] = array(
    'failures'   => 3,
    'open_until' => time() + 120, // Breaker ist fuer die naechsten 120s offen
);

$calls_before = count( $GLOBALS['__smf_remote_calls'] );

$api2   = new SMF_API(); // neue Instanz - Breaker-Zustand lebt im Transient, nicht im Objekt
$fields2 = $api2->get_game_stream_fields( 888, 'upcoming' ); // anderes game_id, damit der statische Zwischenspeicher aus Block 1 nicht greift

smf_test_assert( 'Offener Breaker: kein HTTP-Request ausgelöst', $calls_before, count( $GLOBALS['__smf_remote_calls'] ) );
smf_test_assert( 'Offener Breaker: leere Stream-Felder (kein Button)', array( 'live_stream_link' => null, 'vod_link' => null ), $fields2 );

// ====================================================================
// Zusammenfassung
// ====================================================================

echo "\n$count Assertions, $failures fehlgeschlagen.\n";
exit( $failures > 0 ? 1 : 0 );
