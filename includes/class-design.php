<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Vereinsindividuelles Design: Datenmodell, Sanitizing, Kontrastberechnung
 * und CSS-Ausgabe für die Design-Einstellungen (Option "smf_design").
 */
class SMF_Design {

    const OPTION_KEY = 'smf_design';

    /**
     * Schwellwert für die relative Luminanz (WCAG), ab dem dunkler statt
     * heller Text auf einer Fläche besser lesbar ist. 0.179 ist der Punkt,
     * an dem der Kontrast zu Schwarz und zu Weiß rechnerisch gleich groß
     * ist (aus der WCAG-Kontrastformel hergeleitet) - oberhalb davon
     * gewinnt Schwarz, unterhalb Weiß.
     */
    const LUMINANCE_THRESHOLD = 0.179;

    const FONT_STACK_SYSTEM_SANS  = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, sans-serif';
    const FONT_STACK_SYSTEM_SERIF = 'Georgia, "Times New Roman", Times, serif';

    /**
     * Defaults für NEUE Installationen. Bewusst neutrale Farben statt der
     * Eichehorn-Farben - das Plugin soll für beliebige Vereine nutzbar
     * sein, nicht standardmäßig fremde Vereinsfarben zeigen. Die Schrift
     * erbt vom Theme (kein hartes Oswald/System-Font mehr), damit auf
     * fremden Seiten keine kaputt aussehende, nicht geladene Schrift
     * referenziert wird.
     *
     * @return array
     */
    public static function get_defaults(): array {
        return array(
            'color_primary'       => '#2f5d7c',
            'color_accent'        => '#f2a531',
            'color_surface'       => '#ffffff',
            'color_surface_alt'   => '#f5f5f5',
            'color_border'        => '#e0e0e0',
            'color_border_strong' => '#c0c0c0',
            'color_text'          => '#1a1a1a',
            'color_text_muted'    => '#5a5a5a',
            'contrast_on_primary' => 'auto',
            'contrast_on_accent'  => 'auto',
            'radius'              => 8,
            'shadow_intensity'    => 'normal',
            'font_mode'           => 'inherit',
            'font_custom'         => '',
            'font_title_mode'     => 'inherit',
            'font_title_custom'   => '',
        );
    }

    /**
     * Die heutigen, fest im Stylesheet stehenden Eichehorn-Werte. Wird von
     * der Upgrade-Routine einmalig in bestehende Installationen
     * geschrieben, damit eichehorn-floorball.de nach dem Update optisch
     * unverändert bleibt (siehe smf_maybe_migrate_design() in
     * saisonmanager-floorball.php).
     *
     * @return array
     */
    public static function get_legacy_values(): array {
        $defaults = self::get_defaults();
        return array_merge(
            $defaults,
            array(
                'color_primary'     => '#006045',
                'color_accent'      => '#ffdf26',
                'font_mode'         => 'system-sans',
                'font_title_mode'   => 'custom',
                'font_title_custom' => '"Oswald", sans-serif',
            )
        );
    }

    /**
     * Gespeicherte Konfiguration, über die Defaults gemergt.
     *
     * @return array
     */
    public static function get(): array {
        $stored = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $stored ) ) {
            $stored = array();
        }
        return wp_parse_args( $stored, self::get_defaults() );
    }

    /**
     * Sanitizing-Callback für register_setting() - einzige Stelle, an der
     * eingehende Design-Werte validiert werden (Formular-Speichern läuft
     * über register_setting, kein eigener admin_post-Handler).
     *
     * @param array $input
     * @return array
     */
    public static function sanitize( $input ): array {
        $defaults = self::get_defaults();
        $input    = is_array( $input ) ? $input : array();
        $out      = array();

        $color_fields = array(
            'color_primary', 'color_accent', 'color_surface', 'color_surface_alt',
            'color_border', 'color_border_strong', 'color_text', 'color_text_muted',
        );
        foreach ( $color_fields as $field ) {
            $val           = isset( $input[ $field ] ) ? sanitize_hex_color( $input[ $field ] ) : '';
            $out[ $field ] = $val ? $val : $defaults[ $field ];
        }

        foreach ( array( 'contrast_on_primary', 'contrast_on_accent' ) as $field ) {
            $val           = isset( $input[ $field ] ) ? $input[ $field ] : '';
            $out[ $field ] = in_array( $val, array( 'auto', 'light', 'dark' ), true ) ? $val : $defaults[ $field ];
        }

        $radius          = isset( $input['radius'] ) ? absint( $input['radius'] ) : $defaults['radius'];
        $out['radius']   = max( 0, min( 24, $radius ) );

        $shadow                    = isset( $input['shadow_intensity'] ) ? $input['shadow_intensity'] : '';
        $out['shadow_intensity']   = in_array( $shadow, array( 'none', 'soft', 'normal', 'strong' ), true )
            ? $shadow : $defaults['shadow_intensity'];

        foreach ( array( 'font_mode', 'font_title_mode' ) as $field ) {
            $val           = isset( $input[ $field ] ) ? $input[ $field ] : '';
            $out[ $field ] = in_array( $val, array( 'inherit', 'system-sans', 'system-serif', 'custom' ), true )
                ? $val : $defaults[ $field ];
        }

        $out['font_custom']       = self::sanitize_font_stack( $input['font_custom'] ?? '' );
        $out['font_title_custom'] = self::sanitize_font_stack( $input['font_title_custom'] ?? '' );

        return $out;
    }

    /**
     * Whitelist-Sanitizing für Freitext-Font-Stacks. Erlaubt sind nur
     * Buchstaben, Ziffern, Leerzeichen, Komma, Anführungszeichen und
     * Bindestrich - insbesondere keine Klammern (blockiert "url(...)"),
     * kein Semikolon, keine geschweiften Klammern. Bei Verstoß oder
     * Überlänge wird stumm ein leerer String zurückgegeben (führt beim
     * Rendern zum inherit-Fallback statt zu einem Fehler).
     *
     * @param mixed $raw
     * @return string
     */
    private static function sanitize_font_stack( $raw ): string {
        $raw = is_string( $raw ) ? trim( $raw ) : '';
        if ( $raw === '' || strlen( $raw ) > 150 ) {
            return '';
        }
        if ( ! preg_match( '/^[A-Za-zÀ-ÖØ-öø-ÿ0-9 ,"\'\-]+$/u', $raw ) ) {
            return '';
        }
        return $raw;
    }

    /**
     * Font-Modus + optionalen Freitext zu einem CSS font-family-Wert auflösen.
     *
     * @param string $mode
     * @param string $custom
     * @return string
     */
    public static function resolve_font( string $mode, string $custom ): string {
        switch ( $mode ) {
            case 'system-sans':
                return self::FONT_STACK_SYSTEM_SANS;
            case 'system-serif':
                return self::FONT_STACK_SYSTEM_SERIF;
            case 'custom':
                return $custom !== '' ? $custom : 'inherit';
            default:
                return 'inherit';
        }
    }

    /**
     * Relative Luminanz nach WCAG 2.x für einen Hex-Farbwert.
     *
     * @param string $hex
     * @return float
     */
    public static function relative_luminance( string $hex ): float {
        $hex = ltrim( $hex, '#' );
        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if ( strlen( $hex ) !== 6 || ! ctype_xdigit( $hex ) ) {
            return 1.0; // ungültiger Wert -> Kontrastberechnung wählt sicherheitshalber dunklen Text
        }

        $to_linear = function ( $channel ) {
            $c = $channel / 255;
            return $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
        };

        $r = $to_linear( hexdec( substr( $hex, 0, 2 ) ) );
        $g = $to_linear( hexdec( substr( $hex, 2, 2 ) ) );
        $b = $to_linear( hexdec( substr( $hex, 4, 2 ) ) );

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /**
     * Liefert den CSS-Wert (Referenz auf ein bestehendes Token, keine neue
     * Farbe) für hellen/dunklen Text auf einer farbigen Fläche.
     *
     * @param string $hex_bg Hintergrundfarbe als Hex-Wert
     * @param string $mode   'auto' | 'light' | 'dark'
     * @return string
     */
    public static function contrast_var( string $hex_bg, string $mode ): string {
        if ( $mode === 'light' ) {
            return 'var(--smf-color-surface)';
        }
        if ( $mode === 'dark' ) {
            return 'var(--smf-color-text)';
        }
        $luminance = self::relative_luminance( $hex_bg );
        return $luminance > self::LUMINANCE_THRESHOLD ? 'var(--smf-color-text)' : 'var(--smf-color-surface)';
    }

    /**
     * Enum-Wert (Option) -> CSS-Token-Suffix der Schatten-Stufe. Die
     * eigentlichen Zahlen (Alpha-Anteile, Prozentsätze) stehen nur noch
     * einmal in style.css (--smf-shadow-step-*), hier wird nur noch der
     * Name der gewählten Stufe aufgelöst - keine Farbmathematik, keine
     * Zahlen mehr in PHP.
     */
    const SHADOW_STEP_SUFFIX = array(
        'none'   => 'none',
        'soft'   => 'dezent',
        'normal' => 'normal',
        'strong' => 'stark',
    );

    /** Leaf-Namen der Schatten-Feinsteuerung, siehe style.css. */
    const SHADOW_LEAVES = array( 'alpha', 'alpha-hover', 'sm-alpha', 'sm-alpha-hover', 'modal-alpha', 'tint-pct', 'tint-pct-hover' );

    /**
     * Liefert die aktiven --smf-shadow-*-Variablen als reine var()-Referenzen
     * auf die in style.css definierte Stufe (--smf-shadow-step-{suffix}-*).
     *
     * @param string $intensity
     * @return array<string,string>
     */
    private static function shadow_vars( string $intensity ): array {
        $suffix = self::SHADOW_STEP_SUFFIX[ $intensity ] ?? self::SHADOW_STEP_SUFFIX['normal'];

        $vars = array();
        foreach ( self::SHADOW_LEAVES as $leaf ) {
            $vars[ "--smf-shadow-{$leaf}" ] = "var(--smf-shadow-step-{$suffix}-{$leaf})";
        }
        return $vars;
    }

    /**
     * Erzeugt den kompletten Inline-CSS-Block mit den Design-Variablen,
     * gescopet auf .smf und .smf-modal (nicht :root - siehe Umsetzungsplan
     * Punkt 3: Theme-eigene Custom Properties bleiben unberührt, und das
     * Modal hängt als Geschwisterelement neben .smf im DOM).
     *
     * @return string
     */
    public static function build_css(): string {
        $cfg = self::get();

        $vars = array(
            '--smf-color-primary'       => $cfg['color_primary'],
            '--smf-color-accent'        => $cfg['color_accent'],
            '--smf-color-surface'       => $cfg['color_surface'],
            '--smf-color-surface-alt'   => $cfg['color_surface_alt'],
            '--smf-color-border'        => $cfg['color_border'],
            '--smf-color-border-strong' => $cfg['color_border_strong'],
            '--smf-color-text'          => $cfg['color_text'],
            '--smf-color-text-muted'    => $cfg['color_text_muted'],
            '--smf-color-on-primary'    => self::contrast_var( $cfg['color_primary'], $cfg['contrast_on_primary'] ),
            '--smf-color-on-accent'     => self::contrast_var( $cfg['color_accent'], $cfg['contrast_on_accent'] ),
            '--smf-radius'              => $cfg['radius'] . 'px',
            '--smf-font'                => self::resolve_font( $cfg['font_mode'], $cfg['font_custom'] ),
            '--smf-font-title'          => self::resolve_font( $cfg['font_title_mode'], $cfg['font_title_custom'] ),
        );

        $vars = array_merge( $vars, self::shadow_vars( $cfg['shadow_intensity'] ) );

        /**
         * Erlaubt das programmatische Überschreiben/Ergänzen einzelner
         * Design-Variablen, z.B. für Sonderfälle ohne UI-Feld.
         *
         * @param array $vars CSS-Variablenname => Wert
         * @param array $cfg  Die gespeicherte/gemergte Design-Konfiguration
         */
        $vars = apply_filters( 'smf_design_vars', $vars, $cfg );

        $decls = '';
        foreach ( $vars as $name => $value ) {
            $decls .= $name . ':' . self::escape_css_value( $value ) . ';';
        }

        return '.smf, .smf-modal {' . $decls . '}';
    }

    /**
     * Defensive Bereinigung gegen versehentliches CSS-Aufbrechen (z.B.
     * durch einen Filter-Wert von Drittanbietern) - Zeichen, die eine
     * Deklaration vorzeitig beenden oder einen neuen Block öffnen könnten.
     *
     * @param mixed $value
     * @return string
     */
    private static function escape_css_value( $value ): string {
        return str_replace( array( ';', '{', '}', "\n", "\r" ), '', (string) $value );
    }
}
