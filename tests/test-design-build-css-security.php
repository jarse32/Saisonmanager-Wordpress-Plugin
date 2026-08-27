<?php
/**
 * Eigenständiger PHP-Test für SMF_Design::build_css() - läuft ohne
 * WordPress, ohne Composer, ohne Build-Step:
 *
 *     php tests/test-design-build-css-security.php
 *
 * Prüft, dass build_css() nicht blind auf eine bereits sanitisierte
 * Option vertraut: simuliert eine "smf_design"-Option, die NICHT über
 * SMF_Design::sanitize() geschrieben wurde (z.B. direkter update_option()-
 * Aufruf durch Fremdcode, fehlerhafte Migration, DB-Zugriff) und enthält
 * u.a. einen url(...)-Payload in einem Farbfeld sowie einen Block-
 * Breakout-Versuch (}) - build_css() muss solche Werte trotzdem sicher
 * behandeln, nicht nur Werte, die bereits durch sanitize() liefen.
 *
 * Exit-Code 0 = alle Assertions bestanden, 1 = mindestens eine fehlgeschlagen.
 */

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

$failures = 0;
$count    = 0;

function smf_test_assert( string $label, bool $ok, string $detail = '' ): void {
    global $failures, $count;
    $count++;
    if ( ! $ok ) {
        $failures++;
    }
    echo ( $ok ? 'OK  ' : 'FAIL' ) . " $label" . ( ! $ok && $detail !== '' ? "\n     $detail" : '' ) . "\n";
}

// --- Option direkt korrumpieren, OHNE über SMF_Design::sanitize() zu gehen ---

update_option( 'smf_design', array(
    'color_primary'       => 'url(https://evil.example/track.png)',
    'color_accent'        => 'red; } body { display:none } .x {color:red',
    'color_surface'       => '#ffffff', // gültig, muss unverändert bleiben
    'color_surface_alt'   => '#f5f5f5',
    'color_border'        => '#e0e0e0',
    'color_border_strong' => '#c0c0c0',
    'color_text'          => '#1a1a1a',
    'color_text_muted'    => '#5a5a5a',
    'contrast_on_primary' => 'auto',
    'contrast_on_accent'  => 'auto',
    'radius'              => 'url(x)',
    'shadow_intensity'    => 'normal',
    'font_size'           => 'DROP TABLE wp_options;',
    'font_mode'           => 'custom',
    'font_custom'         => 'Arial; } * { color:red } //',
    'font_title_mode'     => 'inherit',
    'font_title_custom'   => '',
) );

$css      = SMF_Design::build_css();
$defaults = SMF_Design::get_defaults();

echo "--- generiertes CSS ---\n$css\n\n";

smf_test_assert(
    'kein url() im Output (Tracking-/Exfiltrations-Vektor über background: var(--smf-color-primary))',
    stripos( $css, 'url(' ) === false,
    'gefunden: ' . $css
);
smf_test_assert(
    'color_primary fällt auf Default zurück statt den url()-Payload zu übernehmen',
    strpos( $css, '--smf-color-primary:' . $defaults['color_primary'] . ';' ) !== false
);
smf_test_assert(
    'color_accent fällt auf Default zurück statt den Block-Breakout-Versuch zu übernehmen',
    strpos( $css, '--smf-color-accent:' . $defaults['color_accent'] . ';' ) !== false
);
smf_test_assert(
    'gültige Farben (color_surface etc.) bleiben unverändert erhalten',
    strpos( $css, '--smf-color-surface:#ffffff;' ) !== false
);
smf_test_assert(
    'font_custom mit verbotenen Zeichen -> "inherit" statt Payload',
    strpos( $css, '--smf-font:inherit;' ) !== false
);
smf_test_assert(
    'radius fällt auf Default zurück statt "url(x)px" auszugeben',
    strpos( $css, '--smf-radius:' . $defaults['radius'] . 'px;' ) !== false
);
smf_test_assert(
    'font_size fällt auf Default zurück statt Freitext auszugeben',
    strpos( $css, '--smf-fs-base:' . $defaults['font_size'] . 'px;' ) !== false
);
smf_test_assert(
    'genau ein Regelblock (kein Breakout in einen zweiten Block)',
    substr_count( $css, '{' ) === 1 && substr_count( $css, '}' ) === 1
);
smf_test_assert(
    'kein rohes Semikolon innerhalb eines Wertes (escape_css_value greift zusätzlich)',
    substr_count( $css, ';' ) === substr_count( $css, ':' ) // je Deklaration genau ein ':' und ein ';'
);

echo "\n============================================================\n";
echo "$count Assertions, " . ( $count - $failures ) . " OK, $failures FAIL\n";

exit( $failures > 0 ? 1 : 0 );
