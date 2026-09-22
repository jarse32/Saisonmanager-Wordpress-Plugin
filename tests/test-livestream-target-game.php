<?php
/**
 * Eigenständiger PHP-Test für SMF_API::get_livestream_target_game() (Basis
 * für den Shortcode sm_livestream) - läuft ohne WordPress, ohne Composer,
 * ohne Build-Step:
 *
 *     php tests/test-livestream-target-game.php
 *
 * Deckt die Priorität ab (siehe Docblock der Methode, Stand v1.9.1):
 * 1. Laufendes Spiel schlägt alles andere.
 * 2. Die Aufzeichnung des letzten Spiels - UNABHÄNGIG von ihrem Alter -
 *    solange Stufe 3 noch nicht greift.
 * 3. Das nächste kommende Spiel, aber erst wenn BEIDES zutrifft: Anstoß
 *    < NEXT_GAME_SWITCH_BEFORE (24 Std.) entfernt UND ein nutzbarer Link
 *    (Callback $has_link). Ersetzt die alte, vom Anstoß des LETZTEN Spiels
 *    aus gemessene 48h-Regel.
 * 4. Kein kommendes Spiel überhaupt: die Aufzeichnung nur, wenn
 *    $include_ended_fallback das erlaubt (nach_spielende="ausblenden"
 *    unterdrückt das) - sonst null.
 * Zusätzlich: kein Spiel überhaupt, nur ein kommendes Spiel ohne jede
 * Aufzeichnung (Saisonstart), und Ende-zu-Ende mit SMF_Stream für "kein
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
// Grenzfall-Tests (exakt/knapp über 24h) wären sonst flaky, wenn zwischen
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

/** @param array<int,bool> $with_link game_id => hat einen nutzbaren Link */
function smf_test_has_link( array $with_link ): callable {
    return function( $game ) use ( $with_link ) {
        return ! empty( $with_link[ $game['game_id'] ?? 0 ] );
    };
}

$api = new SMF_API();

$SWITCH_BEFORE = SMF_API::NEXT_GAME_SWITCH_BEFORE; // 24h

// ====================================================================
// 1) Laufend schlägt alles - auch eine Aufzeichnung UND ein reifes,
//    verlinktes kommendes Spiel.
// ====================================================================

$running        = smf_test_game( -20 * MINUTE_IN_SECONDS, array( 'game_id' => 101, 'started' => true, 'ended' => false ) );
$recording      = smf_test_ended_game( -3 * DAY_IN_SECONDS, 102 );
$next_ready     = smf_test_game( 1 * HOUR_IN_SECONDS, array( 'game_id' => 103 ) );

$target = $api->get_livestream_target_game(
    array( $recording, $next_ready, $running ), true, $NOW, smf_test_has_link( array( 103 => true ) )
);
smf_test_assert( 'Laufend schlägt Aufzeichnung UND ein reifes kommendes Spiel', 101, $target['game_id'] ?? null );

// ====================================================================
// 2) Die Aufzeichnung bleibt sichtbar, bis das nächste Spiel reif ist -
//    unabhängig davon, wie alt sie ist, und selbst wenn das kommende Spiel
//    schon einen Link hat, aber noch zu weit weg ist.
// ====================================================================

$recording_tuesday = smf_test_ended_game( -3 * DAY_IN_SECONDS, 201 );     // "Dienstag"
$next_far_linked    = smf_test_game( 10 * DAY_IN_SECONDS, array( 'game_id' => 202 ) ); // schon verlinkt, aber weit weg
$next_far_no_link    = smf_test_game( 10 * DAY_IN_SECONDS, array( 'game_id' => 203 ) );
$recording_old       = smf_test_ended_game( -30 * DAY_IN_SECONDS, 204 );

$target = $api->get_livestream_target_game(
    array( $recording_tuesday, $next_far_linked ), true, $NOW, smf_test_has_link( array( 201 => true, 202 => true ) )
);
smf_test_assert( 'Aufzeichnung (z.B. "Dienstag") bleibt trotz Link am nächsten Spiel - das ist noch zu weit weg', 201, $target['game_id'] ?? null );

$target = $api->get_livestream_target_game(
    array( $recording_tuesday, $next_far_no_link ), true, $NOW, smf_test_has_link( array( 201 => true ) )
);
smf_test_assert( 'Aufzeichnung bleibt, wenn das weit entfernte nächste Spiel zusätzlich noch keinen Link hat', 201, $target['game_id'] ?? null );

// Eine sehr alte Aufzeichnung schlägt weiterhin ein Spiel, das noch nicht reif ist.
$target = $api->get_livestream_target_game(
    array( $recording_old, $next_far_linked ), true, $NOW, smf_test_has_link( array( 204 => true, 202 => true ) )
);
smf_test_assert( 'Auch eine 30 Tage alte Aufzeichnung schlägt ein noch nicht reifes kommendes Spiel', 204, $target['game_id'] ?? null );

// Unabhängig von include_ended_fallback - das betrifft nur Stufe 4 (kein
// kommendes Spiel überhaupt), nicht den Fall "kommendes Spiel existiert,
// ist aber noch nicht reif".
$target = $api->get_livestream_target_game(
    array( $recording_tuesday, $next_far_linked ), false, $NOW, smf_test_has_link( array( 201 => true, 202 => true ) )
);
smf_test_assert( 'nach_spielende="ausblenden" wirkt NICHT, solange ein (noch nicht reifes) kommendes Spiel existiert', 201, $target['game_id'] ?? null );

// ====================================================================
// 3) Wechsel auf das nächste Spiel erst, wenn es reif ist: Anstoß
//    < NEXT_GAME_SWITCH_BEFORE (24h) entfernt UND ein nutzbarer Link.
// ====================================================================

$recording_before_switch = smf_test_ended_game( -5 * DAY_IN_SECONDS, 301 );
$next_in_23h              = smf_test_game( $SWITCH_BEFORE - HOUR_IN_SECONDS, array( 'game_id' => 302 ) );      // 23h
$next_just_inside         = smf_test_game( $SWITCH_BEFORE - MINUTE_IN_SECONDS, array( 'game_id' => 303 ) );    // 23h59
$next_just_outside        = smf_test_game( $SWITCH_BEFORE + HOUR_IN_SECONDS, array( 'game_id' => 304 ) );      // 25h
$next_in_1h_no_link       = smf_test_game( HOUR_IN_SECONDS, array( 'game_id' => 305 ) );

// Wechsel 23h vor Anstoß, MIT Link.
$target = $api->get_livestream_target_game(
    array( $recording_before_switch, $next_in_23h ), true, $NOW, smf_test_has_link( array( 302 => true ) )
);
smf_test_assert( 'Wechsel auf das nächste Spiel 23h vor Anstoß (mit Link)', 302, $target['game_id'] ?? null );

// Grenzfall: knapp innerhalb 24h (23h59) zählt schon als reif.
$target = $api->get_livestream_target_game(
    array( $recording_before_switch, $next_just_inside ), true, $NOW, smf_test_has_link( array( 303 => true ) )
);
smf_test_assert( 'Knapp innerhalb 24h (23h59) vor Anstoß: schon Wechsel', 303, $target['game_id'] ?? null );

// Grenzfall: knapp außerhalb 24h (25h) - noch nicht reif, Aufzeichnung bleibt.
$target = $api->get_livestream_target_game(
    array( $recording_before_switch, $next_just_outside ), true, $NOW, smf_test_has_link( array( 304 => true ) )
);
smf_test_assert( 'Knapp außerhalb 24h (25h) vor Anstoß: noch kein Wechsel', 301, $target['game_id'] ?? null );

// Kein Wechsel, wenn das nächste Spiel (trotz zeitlicher Nähe) keinen Link hat.
$target = $api->get_livestream_target_game(
    array( $recording_before_switch, $next_in_1h_no_link ), true, $NOW, smf_test_has_link( array() )
);
smf_test_assert( 'Kein Wechsel ohne Link am nächsten Spiel, selbst 1h vor Anstoß', 301, $target['game_id'] ?? null );

// Ohne Link-Callback (Default): reine Terminregel, nur noch an
// NEXT_GAME_SWITCH_BEFORE gebunden - kein Request für Spiele, die am Ende
// gar nicht gezeigt werden.
$target = $api->get_livestream_target_game( array( $recording_before_switch, $next_in_23h ), true, $NOW );
smf_test_assert( 'Ohne Link-Callback: Wechsel 23h vor Anstoß gilt trotzdem (Termin allein reicht)', 302, $target['game_id'] ?? null );

$target = $api->get_livestream_target_game( array( $recording_before_switch, $next_just_outside ), true, $NOW );
smf_test_assert( 'Ohne Link-Callback: 25h vor Anstoß noch kein Wechsel', 301, $target['game_id'] ?? null );

// ====================================================================
// 4) Kein kommendes Spiel überhaupt (Saisonende o.ä.): die Aufzeichnung
//    nur, wenn $include_ended_fallback das erlaubt.
// ====================================================================

$target = $api->get_livestream_target_game( array( $recording_old ), true, $NOW );
smf_test_assert( 'Kein kommendes Spiel, Fallback an (Standard): Aufzeichnung wird gezeigt', 204, $target['game_id'] ?? null );

$target = $api->get_livestream_target_game( array( $recording_old ), false, $NOW );
smf_test_assert( 'Kein kommendes Spiel, nach_spielende="ausblenden": null', null, $target );

// ====================================================================
// 5) Nur ein kommendes Spiel, gar keine Aufzeichnung (Saisonstart) - dann
//    lieber das kommende Spiel zeigen als gar nichts, auch weit weg/ohne Link.
// ====================================================================

$only_next_far_no_link = smf_test_game( 10 * DAY_IN_SECONDS, array( 'game_id' => 501 ) );
$target = $api->get_livestream_target_game(
    array( $only_next_far_no_link ), true, $NOW, smf_test_has_link( array() )
);
smf_test_assert( 'Keine Aufzeichnung vorhanden: das kommende Spiel wird trotzdem gezeigt (weit weg, ohne Link)', 501, $target['game_id'] ?? null );

// ====================================================================
// 6) Gar kein Spiel vorhanden
// ====================================================================

smf_test_assert( 'Leere Spieleliste, Fallback an: null', null, $api->get_livestream_target_game( array(), true, $NOW ) );
smf_test_assert( 'Leere Spieleliste, Fallback aus: null', null, $api->get_livestream_target_game( array(), false, $NOW ) );

// ====================================================================
// 7) Ende-zu-Ende mit SMF_Stream: "kein Link" und "Kanal-Link" am
//    AUSGEWÄHLTEN Spiel - die Auswahl selbst liefert trotzdem ein Spiel,
//    nur eben keinen einbettbaren Player.
// ====================================================================

$running_no_link = smf_test_game( -10 * MINUTE_IN_SECONDS, array( 'game_id' => 601, 'started' => true, 'ended' => false ) );
$target           = $api->get_livestream_target_game( array( $running_no_link ), true, $NOW );
$stream_fields_no_link = array( 'live_stream_link' => null, 'vod_link' => null );
$picked = SMF_Stream::pick_link( $stream_fields_no_link, SMF_Game_Status::status( $target, $NOW ) );
smf_test_assert( '"Kein Link": Spiel wird trotzdem ausgewählt', 601, $target['game_id'] ?? null );
smf_test_assert( '"Kein Link": pick_link() liefert null', null, $picked );

$running_channel_link = smf_test_game( -10 * MINUTE_IN_SECONDS, array( 'game_id' => 602, 'started' => true, 'ended' => false ) );
$target                = $api->get_livestream_target_game( array( $running_channel_link ), true, $NOW );
$stream_fields_channel  = array( 'live_stream_link' => 'https://www.twitch.tv/eichehornfloorball', 'vod_link' => null );
$picked                 = SMF_Stream::pick_link( $stream_fields_channel, SMF_Game_Status::status( $target, $NOW ) );
$parsed                 = SMF_Stream::parse_url( $picked['url'] );
smf_test_assert( '"Kanal-Link": Spiel wird ausgewählt', 602, $target['game_id'] ?? null );
smf_test_assert( '"Kanal-Link": pick_link() liefert trotzdem einen Link (kind=live)', 'live', $picked['kind'] ?? null );
smf_test_assert( '"Kanal-Link": aber nicht einbettbar (kein Player, nur Link-Button)', false, $parsed['embeddable'] );
smf_test_assert( '"Kanal-Link": bleibt ein gültiger https-Link', true, $parsed['valid'] );

// ====================================================================
// 8) Regression, Originalfall aus dem Fehlerbericht (Team 9667, TV Eiche
//    Horn Bremen): Heimspiel mit Aufzeichnung, nächstes Spiel erst eine
//    Woche später und noch ohne Link. Mit der alten 48h-Regel (v1.9.0)
//    verschwand die Aufzeichnung ab Stunde 49 hinter dem linklosen
//    kommenden Spiel; mit der neuen, am NÄCHSTEN Spiel orientierten Regel
//    bleibt sie sichtbar, bis dieses reif ist.
// ====================================================================

$ended_3_days  = smf_test_ended_game( -3 * DAY_IN_SECONDS, 58658 );
$upcoming_week = smf_test_game( 7 * DAY_IN_SECONDS, array( 'game_id' => 58665 ) );

$target = $api->get_livestream_target_game(
    array( $ended_3_days, $upcoming_week ), true, $NOW, smf_test_has_link( array( 58658 => true ) )
);
smf_test_assert( 'Regression (Originalfall 9667): Aufzeichnung bleibt trotz linklosem Termin in einer Woche', 58658, $target['game_id'] ?? null );

// ====================================================================
// Zusammenfassung
// ====================================================================

echo "\n$count Assertions, $failures fehlgeschlagen.\n";
exit( $failures > 0 ? 1 : 0 );
