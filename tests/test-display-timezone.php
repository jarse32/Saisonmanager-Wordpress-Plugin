<?php
/**
 * Eigenständiger PHP-Test für die ANZEIGE-Seite des Zeitzonen-Fixes - läuft
 * ohne WordPress, ohne Composer, ohne Build-Step:
 *
 *     php tests/test-display-timezone.php
 *
 * SMF_API::parse_game_date() korrekt zu machen reicht allein nicht: Die
 * Templates (single-game, games-list, club-overview, game-detail) lesen den
 * Timestamp zurück und formatieren Wochentag/Datum daraus per wp_date() -
 * vorher date_i18n(). date_i18n() addiert den AKTUELLEN gmt_offset der Site
 * auf den Timestamp, ohne die Sommer-/Winterzeit am Datum des SPIELS selbst
 * zu berücksichtigen. Dieser Test prüft die komplette Kette
 * parse_game_date() -> wp_date() für ein Spiel kurz vor und kurz nach der
 * Zeitumstellung am 25.10.2026 - und zeigt konkret, welches falsche Ergebnis
 * die alte Methode (fester "aktueller" Offset statt zeitzonenbewusster
 * Umrechnung) an einem der beiden Daten geliefert hätte.
 *
 * wp_date() wird hier minimal nachgebaut (nur die für DST relevante
 * Zeitzonen-Umrechnung über DateTimeImmutable::setTimezone(), keine
 * Locale-Übersetzung von Wochentags-/Monatsnamen - dafür bewusst nur
 * lateinische Format-Zeichen wie 'l'/'H:i', keine deutschen Bezeichner).
 *
 * Exit-Code 0 = alle Assertions bestanden, 1 = mindestens eine fehlgeschlagen.
 */

define( 'ABSPATH', '/tmp/' );

function get_option( $key, $default = false ) {
    return $default;
}

/**
 * Minimaler wp_date()-Nachbau für diesen Test - siehe Kommentar oben.
 */
function wp_date( $format, $timestamp = null, $timezone = null ) {
    if ( $timestamp === null ) $timestamp = time();
    if ( $timezone === null )  $timezone  = new DateTimeZone( 'UTC' );
    $dt = ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $timezone );
    return $dt->format( $format );
}

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

// --- Fixtures: 18:00-Anstoß kurz vor und kurz nach der Zeitumstellung ---

$before = array( 'date' => '2026-10-24', 'time' => '18:00' ); // Samstag, noch CEST (UTC+2)
$after  = array( 'date' => '2026-10-26', 'time' => '18:00' ); // Montag, schon CET (UTC+1)

$ts_before = SMF_API::parse_game_date( $before );
$ts_after  = SMF_API::parse_game_date( $after );
$tz        = SMF_API::game_timezone();

// --- Kernprüfung: 18:00 bleibt 18:00, auf beiden Seiten der Umstellung ---

smf_test_assert(
    'wp_date H:i mit game_timezone: 18:00-Anstoß vor der Zeitumstellung bleibt 18:00',
    '18:00',
    wp_date( 'H:i', $ts_before, $tz )
);

smf_test_assert(
    'wp_date H:i mit game_timezone: 18:00-Anstoß nach der Zeitumstellung bleibt 18:00',
    '18:00',
    wp_date( 'H:i', $ts_after, $tz )
);

// --- Wochentag korrekt fürs jeweilige Datum (unabhängig vom Testlauf-Datum) ---

smf_test_assert(
    'wp_date l: 24.10.2026 ist ein Saturday',
    'Saturday',
    wp_date( 'l', $ts_before, $tz )
);

smf_test_assert(
    'wp_date l: 26.10.2026 ist ein Monday',
    'Monday',
    wp_date( 'l', $ts_after, $tz )
);

// --- Gegenprobe: die alte, falsche Methode (fester statt DST-bewusster
//     Offset) an einem konkreten Beispiel. Simuliert date_i18n() mit
//     $gmt=false: addiert einen FIXEN "aktuellen" Offset (hier: die zum
//     Testzeitpunkt gültige CEST-Verschiebung, +2h) auf den UTC-Timestamp,
//     statt für JEDES Datum neu zu prüfen, ob CET oder CEST gilt. Für das
//     Oktober-Spiel NACH der Umstellung (das dann schon CET/+1 braucht) ist
//     das nachweislich falsch. ---

$simulated_current_cest_offset_seconds = 2 * 3600;
$naive_fixed_offset_result = gmdate( 'H:i', $ts_after + $simulated_current_cest_offset_seconds );

smf_test_assert(
    'Gegenprobe: alte Methode (fester Offset statt Zeitzone) liefert fuer das Nach-Umstellungs-Spiel faelschlich 19:00',
    '19:00',
    $naive_fixed_offset_result
);

smf_test_assert(
    'Gegenprobe bestaetigt den Fix: wp_date mit echter Zeitzone weicht von der alten, falschen Methode ab',
    true,
    wp_date( 'H:i', $ts_after, $tz ) !== $naive_fixed_offset_result
);

// ------------------------------------------------------------------

echo "\n" . str_repeat( '=', 60 ) . "\n";
echo "$count Assertions, " . ( $count - $failures ) . " OK, $failures FAIL\n";

exit( $failures > 0 ? 1 : 0 );
