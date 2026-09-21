<?php
/**
 * Eigenständiger PHP-Test für SMF_API::get_livestream_target_game() (Basis
 * für den Shortcode sm_livestream, v1.9.0) - läuft ohne WordPress, ohne
 * Composer, ohne Build-Step:
 *
 *     php tests/test-livestream-target-game.php
 *
 * Deckt die vierstufige Priorität ab (siehe Docblock der Methode):
 * 1. Laufendes Spiel schlägt alles andere.
 * 2. Eine kürzlich beendete Aufzeichnung (Anstoß <= LIVESTREAM_RECENT_ENDED_MAX_AGE
 *    zurück) schlägt ein kommendes Spiel - UNABHÄNGIG von
 *    $include_ended_fallback, das nur Stufe 4 betrifft.
 * 3. Sonst das nächste kommende Spiel.
 * 4. Erst ohne 1-3: die Aufzeichnung des zuletzt gespielten Spiels, auch
 *    wenn es länger zurückliegt - nur wenn $include_ended_fallback nicht
 *    false ist (Shortcode-Attribut nach_spielende="ausblenden").
 * Zusätzlich: leere Spieleliste, und Ende-zu-Ende mit SMF_Stream für "kein
 * Link"/"Kanal-Link" am ausgewählten Spiel.
 *
 * Fixtures sind rein synthetisch (keine echten API-Antworten, keine
 * Personennamen), Zeitpunkte relativ zu time(), damit der Test nicht mit
 * der Zeit veraltet - gleiches Muster wie test-api-game-filters.php.
 *
 * Exit-Code 0 = alle Assertions bestanden, 1 = mindestens eine fehlgeschlagen.
 */

define( 'ABSPATH', '/tmp/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 60 * MINUTE_IN_SECONDS );
define( 'DAY_IN_SECONDS', 24 * HOUR_IN_SECONDS );

function get_option( $key, $default = false ) {
    return $default;
}

/**
 * wp_parse_url() ist im echten WordPress ein parse_url()-Polyfill - siehe
 * ausführliche Begründung in test-stream-url-parsing.php.
 */
function wp_parse_url( $url, $component = -1 ) {
    return ( $component === -1 ) ? parse_url( $url ) : parse_url( $url, $component );
}

require __DIR__ . '/../includes/class-cache.php';
require __DIR__ . '/../includes/class-api.php';
require __DIR__ . '/../includes/class-game-status.php';
require __DIR__ . '/../includes/class-stream.php';

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

/** Siehe test-api-game-filters.php: Datum/Zeit in Europe/Berlin, nicht im Prozess-Default. */
function smf_test_berlin_date_time( int $ts ): array {
    $dt = ( new DateTimeImmutable( '@' . $ts ) )->setTimezone( new DateTimeZone( 'Europe/Berlin' ) );
    return array( $dt->format( 'Y-m-d' ), $dt->format( 'H:i' ) );
}

// Fester Referenzzeitpunkt statt time() an jeder Aufrufstelle: die
// Grenzfall-Tests (exakt/knapp über 48h) wären sonst flaky, wenn zwischen
// dem Bauen der Fixtures und dem Aufruf von get_livestream_target_game()
// eine Sekundengrenze überschritten wird. get_livestream_target_game()
// nimmt genau dafür ein optionales $now entgegen.
$NOW = time();

/**
 * @param int $offset_seconds Anstoß relativ zu $NOW (negativ = Vergangenheit)
 */
function smf_test_game( int $offset_seconds, array $overrides = array() ): array {
    global $NOW;
    $ts = $NOW + $offset_seconds;
    list( $date, $time ) = smf_test_berlin_date_time( $ts );
    $base = array(
        'game_id'         => 1,
        'date'            => $date,
        'time'            => $time,
        'home_team_name'  => 'Team A',
        'guest_team_name' => 'Team B',
    );
    return array_merge( $base, $overrides );
}

function smf_test_ended_game( int $offset_seconds, int $game_id ): array {
    return smf_test_game( $offset_seconds, array(
        'game_id' => $game_id,
        'ended'   => true,
        'result'  => array( 'home_goals' => 3, 'guest_goals' => 1 ),
    ) );
}

$api = new SMF_API();

$RECENT_MAX = SMF_API::LIVESTREAM_RECENT_ENDED_MAX_AGE;

$running          = smf_test_game( -20 * MINUTE_IN_SECONDS, array( 'game_id' => 101, 'started' => true, 'ended' => false ) );
$upcoming_far      = smf_test_game( 10 * DAY_IN_SECONDS, array( 'game_id' => 102 ) );
$upcoming_near      = smf_test_game( 1 * DAY_IN_SECONDS,  array( 'game_id' => 103 ) );
$ended_recent       = smf_test_ended_game( -( $RECENT_MAX - HOUR_IN_SECONDS ), 201 );       // 1h innerhalb des Fensters
// 1 Minute innerhalb der Grenze statt exakt auf ihr: date/time-Fixtures
// haben wie die echte API nur Minutenauflösung ("HH:MM", keine Sekunden,
// siehe smf_test_berlin_date_time()) - eine exakt-auf-die-Sekunde-Grenze
// ließe sich durch den Datum/Zeit-Roundtrip nicht zuverlässig nachbilden.
$ended_near_boundary = smf_test_ended_game( -( $RECENT_MAX - MINUTE_IN_SECONDS ), 202 );
$ended_just_outside  = smf_test_ended_game( -( $RECENT_MAX + HOUR_IN_SECONDS ), 203 );        // 1h jenseits des Fensters
$ended_old            = smf_test_ended_game( -10 * DAY_IN_SECONDS, 204 );

// ====================================================================
// 1) Laufend schlägt alles - auch eine kürzlich beendete Aufzeichnung
// ====================================================================

$target = $api->get_livestream_target_game( array( $upcoming_far, $ended_recent, $running ), true, $NOW );
smf_test_assert( 'Laufend schlägt kürzlich beendet UND kommendes Spiel', 101, $target['game_id'] ?? null );

// ====================================================================
// 2) Kürzlich beendet (<= 48h) schlägt ein kommendes Spiel - auch wenn
//    das kommende Spiel näher liegt als das Ende-Fenster
// ====================================================================

$target = $api->get_livestream_target_game( array( $upcoming_near, $ended_recent ), true, $NOW );
smf_test_assert( 'Kürzlich beendet (<=48h) schlägt ein vorhandenes kommendes Spiel', 201, $target['game_id'] ?? null );

// Knapp innerhalb der 48h-Grenze zählt noch als "kürzlich".
$target = $api->get_livestream_target_game( array( $upcoming_near, $ended_near_boundary ), true, $NOW );
smf_test_assert( 'Knapp innerhalb 48h zählt noch als "kürzlich beendet"', 202, $target['game_id'] ?? null );

// Knapp jenseits der 48h-Grenze zählt nicht mehr - das kommende Spiel gewinnt.
$target = $api->get_livestream_target_game( array( $upcoming_near, $ended_just_outside ), true, $NOW );
smf_test_assert( 'Knapp über 48h: kommendes Spiel schlägt die Aufzeichnung', 103, $target['game_id'] ?? null );

// Stufe 2 ist UNABHÄNGIG von include_ended_fallback - false betrifft nur Stufe 4.
$target = $api->get_livestream_target_game( array( $upcoming_near, $ended_recent ), false, $NOW );
smf_test_assert( 'Kürzlich beendet gewinnt auch mit include_ended_fallback=false (betrifft nur Stufe 4)', 201, $target['game_id'] ?? null );

// ====================================================================
// 3) Nur ein kommendes Spiel (kein laufendes, keine kürzliche Aufzeichnung)
// ====================================================================

$target = $api->get_livestream_target_game( array( $upcoming_far ), true, $NOW );
smf_test_assert( 'Ohne laufendes/kürzlich beendetes Spiel: das kommende', 102, $target['game_id'] ?? null );

// Eine ÄLTERE (>48h) Aufzeichnung tritt hinter einem kommenden Spiel zurück.
$target = $api->get_livestream_target_game( array( $upcoming_far, $ended_old ), true, $NOW );
smf_test_assert( 'Kommendes Spiel schlägt eine ältere (>48h) Aufzeichnung', 102, $target['game_id'] ?? null );

// ====================================================================
// 4) Ältere Aufzeichnung nur als letzter Fallback (kein laufendes, keine
//    kürzliche Aufzeichnung, kein kommendes Spiel) - und nur wenn erlaubt
// ====================================================================

$target = $api->get_livestream_target_game( array( $ended_old ), true, $NOW );
smf_test_assert( 'Nur eine ältere Aufzeichnung vorhanden, Fallback an (Standard): wird gezeigt', 204, $target['game_id'] ?? null );

$target = $api->get_livestream_target_game( array( $ended_old ), false, $NOW );
smf_test_assert( 'Nur eine ältere Aufzeichnung vorhanden, nach_spielende="ausblenden": null', null, $target );

// ====================================================================
// 5) Gar kein Spiel vorhanden
// ====================================================================

smf_test_assert( 'Leere Spieleliste, Fallback an: null', null, $api->get_livestream_target_game( array(), true, $NOW ) );
smf_test_assert( 'Leere Spieleliste, Fallback aus: null', null, $api->get_livestream_target_game( array(), false, $NOW ) );

// ====================================================================
// 6) Ende-zu-Ende mit SMF_Stream: "kein Link" und "Kanal-Link" am
//    AUSGEWÄHLTEN Spiel - die Auswahl selbst liefert trotzdem ein Spiel,
//    nur eben keinen einbettbaren Player.
// ====================================================================

$running_no_link = smf_test_game( -10 * MINUTE_IN_SECONDS, array( 'game_id' => 301, 'started' => true, 'ended' => false ) );
$target           = $api->get_livestream_target_game( array( $running_no_link ), true, $NOW );
$stream_fields_no_link = array( 'live_stream_link' => null, 'vod_link' => null );
$picked = SMF_Stream::pick_link( $stream_fields_no_link, SMF_Game_Status::status( $target, $NOW ) );
smf_test_assert( '"Kein Link": Spiel wird trotzdem ausgewählt', 301, $target['game_id'] ?? null );
smf_test_assert( '"Kein Link": pick_link() liefert null', null, $picked );

$running_channel_link = smf_test_game( -10 * MINUTE_IN_SECONDS, array( 'game_id' => 302, 'started' => true, 'ended' => false ) );
$target                = $api->get_livestream_target_game( array( $running_channel_link ), true, $NOW );
$stream_fields_channel  = array( 'live_stream_link' => 'https://www.twitch.tv/eichehornfloorball', 'vod_link' => null );
$picked                 = SMF_Stream::pick_link( $stream_fields_channel, SMF_Game_Status::status( $target, $NOW ) );
$parsed                 = SMF_Stream::parse_url( $picked['url'] );
smf_test_assert( '"Kanal-Link": Spiel wird ausgewählt', 302, $target['game_id'] ?? null );
smf_test_assert( '"Kanal-Link": pick_link() liefert trotzdem einen Link (kind=live)', 'live', $picked['kind'] ?? null );
smf_test_assert( '"Kanal-Link": aber nicht einbettbar (kein Player, nur Link-Button)', false, $parsed['embeddable'] );
smf_test_assert( '"Kanal-Link": bleibt ein gültiger https-Link', true, $parsed['valid'] );

// ====================================================================
// Zusammenfassung
// ====================================================================

echo "\n$count Assertions, $failures fehlgeschlagen.\n";
exit( $failures > 0 ? 1 : 0 );
