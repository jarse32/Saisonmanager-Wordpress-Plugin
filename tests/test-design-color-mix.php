<?php
/**
 * Eigenständiger PHP-Test für SMF_Design::mix_hex() - läuft ohne
 * WordPress, ohne Composer, ohne Build-Step:
 *
 *     php tests/test-design-color-mix.php
 *
 * mix_hex() bildet CSS' color-mix(in srgb, A P%, B) in PHP nach, damit die
 * Team-Highlighting-Kontrastprüfung (SMF_Design::build_css(), Variable
 * --smf-color-on-highlight) weiß, welche Textfarbe auf dem tatsächlich von
 * color-mix() gerenderten Hintergrund lesbar bleibt.
 *
 * WICHTIG: color-mix(in srgb, ...) interpoliert die gammakodierten
 * sRGB-Kanäle direkt (kein linear-light blending wie bei physikalischer
 * Lichtmischung) - mix_hex() macht deshalb einen simplen kanalweisen
 * gewichteten Mittelwert der rohen Hex-Werte, keine Linearisierung.
 *
 * Die erwarteten Werte unten sind KEINE Annahme, sondern per echtem
 * Chrome-Rendering ermittelt (headless, getComputedStyle() auf Elementen
 * mit genau diesen color-mix()-Deklarationen, macOS, Chrome, Sept. 2026):
 *
 *   color-mix(in srgb, #f2a531 20%, #ffffff) -> color(srgb 0.989804 0.929412 0.838431)
 *   color-mix(in srgb, #000000 20%, #ffffff) -> color(srgb 0.8 0.8 0.8)
 *   color-mix(in srgb, #ffffff 20%, #1a1a1a) -> color(srgb 0.281569 0.281569 0.281569)
 *   color-mix(in srgb, #006045 20%, #ffffff) -> color(srgb 0.8 0.875294 0.854118)
 *   color-mix(in srgb, #2f5d7c 37%, #f5f5f5) -> color(srgb 0.67349 0.740235 0.785216)
 *
 * (auf 0-255 gerundet, siehe erwartete Hex-Werte unten). Bei einer Änderung
 * an mix_hex() diesen Abgleich erneut gegen einen echten Browser fahren,
 * nicht nur gegen die Spezifikation argumentieren - ein stiller Versatz
 * zwischen Prüfung und tatsächlicher Darstellung wäre schlimmer als gar
 * keine Prüfung.
 *
 * Exit-Code 0 = alle Assertions bestanden, 1 = mindestens eine fehlgeschlagen.
 */

define( 'ABSPATH', '/tmp/' );

require __DIR__ . '/../includes/class-design.php';

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

// Gegen echtes Chrome-Rendering verifizierte Paare (siehe Kommentar oben).
smf_test_assert(
    'mix_hex: Standard-Akzent 20% auf weißer Oberfläche (hell auf hell)',
    '#fcedd6',
    SMF_Design::mix_hex( '#f2a531', '#ffffff', 20 )
);
smf_test_assert(
    'mix_hex: sehr dunkle Akzentfarbe (Schwarz) 20% auf Weiß',
    '#cccccc',
    SMF_Design::mix_hex( '#000000', '#ffffff', 20 )
);
smf_test_assert(
    'mix_hex: sehr helle Akzentfarbe (Weiß) 20% auf dunkler Oberfläche',
    '#484848',
    SMF_Design::mix_hex( '#ffffff', '#1a1a1a', 20 )
);
smf_test_assert(
    'mix_hex: Eichehorn-Grün 20% auf Weiß',
    '#ccdfda',
    SMF_Design::mix_hex( '#006045', '#ffffff', 20 )
);
smf_test_assert(
    'mix_hex: abweichender Mischanteil (37%) auf grauer Oberfläche',
    '#acbdc8',
    SMF_Design::mix_hex( '#2f5d7c', '#f5f5f5', 37 )
);

// Randfälle
smf_test_assert( 'mix_hex: 0% -> reines B', '#ffffff', SMF_Design::mix_hex( '#000000', '#ffffff', 0 ) );
smf_test_assert( 'mix_hex: 100% -> reines A', '#000000', SMF_Design::mix_hex( '#000000', '#ffffff', 100 ) );
smf_test_assert( 'mix_hex: 3-stelliger Hex-Wert wird expandiert', '#cccccc', SMF_Design::mix_hex( '#000', '#fff', 20 ) );
smf_test_assert( 'mix_hex: ungültiger Hex-Wert faellt auf Schwarz zurueck (0,0,0)', '#cccccc', SMF_Design::mix_hex( 'keine-farbe', '#ffffff', 20 ) );

// Die tatsächlich produktiv genutzte Konstante bleibt bei 20% - dieser Test
// schlägt bewusst fehl, wenn jemand den Wert ändert, ohne die obigen
// Browser-Referenzwerte neu zu erheben.
smf_test_assert( 'HIGHLIGHT_TINT_PERCENT ist weiterhin 20 (siehe Testkommentar oben)', 20, SMF_Design::HIGHLIGHT_TINT_PERCENT );

echo "\n$count Checks, $failures fehlgeschlagen.\n";
exit( $failures > 0 ? 1 : 0 );
