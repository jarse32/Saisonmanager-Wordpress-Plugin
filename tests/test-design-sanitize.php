<?php
/**
 * Eigenständiger PHP-Test für SMF_Design::sanitize() - läuft ohne
 * WordPress, ohne Composer, ohne Build-Step:
 *
 *     php tests/test-design-sanitize.php
 *
 * Deckt genau den Fall ab, der SMF_Design::sanitize() dazu gebracht hat,
 * gegen den gespeicherten Zustand statt gegen get_defaults() zu mergen:
 * eine bestehende Installation mit klar von den Defaults abweichenden
 * Werten (z.B. Eichehorn) darf durch ein unvollständiges oder teilweise
 * ungültiges POST-Array (klassischer Fall: eine Radiogroup ohne gesetztes
 * "checked") NICHT auf die neutralen Defaults zurückfallen.
 *
 * Exit-Code 0 = alle Assertions bestanden, 1 = mindestens eine fehlgeschlagen.
 */

// --- Minimale WordPress-Stubs, nur was SMF_Design tatsächlich aufruft ---

define( 'ABSPATH', '/tmp/' );

$GLOBALS['__smf_test_options'] = array();

function get_option( $key, $default = false ) {
    return $GLOBALS['__smf_test_options'][ $key ] ?? $default;
}
function update_option( $key, $value ) {
    $GLOBALS['__smf_test_options'][ $key ] = $value;
    return true;
}
function apply_filters( $tag, $value, ...$args ) {
    return $value;
}
function sanitize_hex_color( $value ) {
    return ( is_string( $value ) && preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ) ? $value : '';
}
function absint( $value ) {
    return abs( (int) $value );
}
function wp_parse_args( $args, $defaults ) {
    return array_merge( $defaults, (array) $args );
}

require __DIR__ . '/../includes/class-design.php';

// --- Mini-Testharness ---

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

// --- Fixture: gespeicherte Konfiguration, die sich in JEDEM Feld klar von
//     get_defaults() unterscheidet (angelehnt an Eichehorn) ---

$saved = array(
    'color_primary'       => '#006045',
    'color_accent'        => '#ffdf26',
    'color_surface'       => '#fffbe6',
    'color_surface_alt'   => '#eef5f1',
    'color_border'        => '#c9c9c9',
    'color_border_strong' => '#a0a0a0',
    'color_text'          => '#0a0a0a',
    'color_text_muted'    => '#444444',
    'contrast_on_primary' => 'dark',
    'contrast_on_accent'  => 'light',
    'radius'              => 14,
    'shadow_intensity'    => 'strong',
    'font_size'           => 18,
    'font_mode'           => 'system-sans',
    'font_custom'         => 'Arial, sans-serif', // aktuell ungenutzt (Modus != custom), muss trotzdem erhalten bleiben
    'font_title_mode'     => 'custom',
    'font_title_custom'   => '"Oswald", sans-serif',
);

$defaults = SMF_Design::get_defaults();
foreach ( $saved as $field => $value ) {
    smf_test_assert( "Fixture-Sanity: $field weicht von get_defaults() ab", true, $saved[ $field ] !== $defaults[ $field ] );
}

update_option( 'smf_design', $saved );

echo "\n--- Test 1: komplett leeres POST-Array -> alle Felder bleiben wie gespeichert ---\n";
$result = SMF_Design::sanitize( array() );
foreach ( $saved as $field => $value ) {
    smf_test_assert( "leeres POST: $field bleibt gespeicherter Wert", $value, $result[ $field ] );
}

echo "\n--- Test 2: unvollständiges POST (nur radius gesetzt) -> Rest bleibt gespeichert ---\n";
$result = SMF_Design::sanitize( array( 'radius' => '10' ) );
smf_test_assert( 'radius wird übernommen', 10, $result['radius'] );
foreach ( $saved as $field => $value ) {
    if ( $field === 'radius' ) {
        continue;
    }
    smf_test_assert( "unvollständiges POST: $field bleibt gespeicherter Wert (kein Reset auf Default)", $value, $result[ $field ] );
    smf_test_assert( "unvollständiges POST: $field ist NICHT der Neutral-Default", true, $result[ $field ] !== $defaults[ $field ] );
}

echo "\n--- Test 3: Radiogroup ohne 'checked' (Feld fehlt komplett im POST) ---\n";
$input = $saved;
unset( $input['contrast_on_primary'] ); // simuliert: keine Radio-Option war "checked"
$result = SMF_Design::sanitize( $input );
smf_test_assert( 'fehlende Radiogroup behält gespeicherten Wert', $saved['contrast_on_primary'], $result['contrast_on_primary'] );
smf_test_assert( 'fehlende Radiogroup ist NICHT "auto" (Default)', true, $result['contrast_on_primary'] !== $defaults['contrast_on_primary'] );

echo "\n--- Test 4: vorhandene, aber ungültige Werte -> gespeicherter Wert bleibt (kein Reset auf Default) ---\n";
$invalid_input = array(
    'color_primary'       => 'javascript:alert(1)',
    'color_accent'        => 'nicht-hex',
    'contrast_on_primary' => 'seitwaerts',
    'radius'              => '999',
    'shadow_intensity'    => 'explodiert',
    'font_size'           => '17',
    'font_mode'           => 'comic-sans-bitte',
    'font_title_custom'   => 'Arial; } body { background:url(evil) ', // enthält verbotene Zeichen
);
$result = SMF_Design::sanitize( $invalid_input );
smf_test_assert( 'ungültige Farbe -> gespeicherter Wert', $saved['color_primary'], $result['color_primary'] );
smf_test_assert( 'ungültige Farbe -> NICHT Neutral-Default', true, $result['color_primary'] !== $defaults['color_primary'] );
smf_test_assert( 'ungültiger Kontrast-Modus -> gespeicherter Wert', $saved['contrast_on_primary'], $result['contrast_on_primary'] );
// Radius ist absichtlich ein Sonderfall: laut Phase-5-Vorgabe wird er
// geclamped (absint + min/max 0..24), nicht auf den gespeicherten Wert
// zurückgesetzt - "999" ist damit kein *ungültiger*, sondern ein
// *außerhalb des Bereichs liegender* Wert und wird auf 24 begrenzt.
smf_test_assert( 'Radius > 24 -> auf 24 geclampt (kein Reset, kein Default)', 24, $result['radius'] );
smf_test_assert( 'ungültiges Schatten-Enum -> gespeicherter Wert', $saved['shadow_intensity'], $result['shadow_intensity'] );
smf_test_assert( 'ungültige Schriftgröße -> gespeicherter Wert', $saved['font_size'], $result['font_size'] );
smf_test_assert( 'ungültiger Font-Modus -> gespeicherter Wert', $saved['font_mode'], $result['font_mode'] );
smf_test_assert( 'Font-Stack mit verbotenen Zeichen -> gespeicherter Wert (nicht stillschweigend geleert)', $saved['font_title_custom'], $result['font_title_custom'] );

echo "\n--- Test 5: bewusst geleertes Font-Feld (leerer String, kein ungültiger Wert) -> wird als leer gespeichert ---\n";
$result = SMF_Design::sanitize( array( 'font_title_custom' => '' ) );
smf_test_assert( 'bewusst geleertes Font-Feld wird übernommen (nicht der alte Wert)', '', $result['font_title_custom'] );

echo "\n--- Test 6: vollständiges, gültiges POST -> wird komplett übernommen ---\n";
$new_values = array(
    'color_primary'       => '#112233',
    'color_accent'        => '#445566',
    'color_surface'       => '#ffffff',
    'color_surface_alt'   => '#f0f0f0',
    'color_border'        => '#dddddd',
    'color_border_strong' => '#bbbbbb',
    'color_text'          => '#000000',
    'color_text_muted'    => '#555555',
    'contrast_on_primary' => 'auto',
    'contrast_on_accent'  => 'auto',
    'radius'              => 0,
    'shadow_intensity'    => 'none',
    'font_size'           => 20,
    'font_mode'           => 'inherit',
    'font_custom'         => '',
    'font_title_mode'     => 'inherit',
    'font_title_custom'   => '',
);
$result = SMF_Design::sanitize( $new_values );
ksort( $new_values );
ksort( $result );
smf_test_assert( 'vollständiges gültiges POST wird 1:1 übernommen', $new_values, $result );

echo "\n============================================================\n";
echo "$count Assertions, " . ( $count - $failures ) . " OK, $failures FAIL\n";

exit( $failures > 0 ? 1 : 0 );
