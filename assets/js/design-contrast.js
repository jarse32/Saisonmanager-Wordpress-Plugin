/**
 * SM Floorball – gemeinsames Vorschau-Modul für dev/preview.html und das
 * Backend-Design-UI (assets/js/admin-design.js).
 *
 * Enthält:
 * - relativeLuminance()/contrastMode(): spiegelt NUR die Luminanz-/
 *   Kontrastentscheidung aus includes/class-design.php
 *   (SMF_Design::relative_luminance()/contrast_var()) - die einzige
 *   Stelle, an der Farbmathematik zwischen PHP und JS dupliziert wird.
 *   Alle reinen Auf-/Abdunklungen (primary-light/-dark, Hover, Schatten-
 *   Tönung) laufen ausschließlich per color-mix() im Stylesheet.
 * - resolveFont(): reine Modus->Font-Stack-Zuordnung (kein Farbmathematik-
 *   Äquivalent, nur ein Namens-Lookup), spiegelt SMF_Design::resolve_font().
 * - applyShadowStep(): setzt die aktiven --smf-shadow-*-Variablen als
 *   var()-Referenzen auf die in style.css definierte Stufe
 *   (--smf-shadow-step-*) - die Zahlen selbst stehen nur dort, hier nur
 *   die Zuordnung Enum-Wert -> CSS-Token-Suffix (spiegelt
 *   SMF_Design::SHADOW_STEP_SUFFIX).
 * - apply(): setzt alle Design-CSS-Variablen auf einem Vorschau-Element.
 *   Liest die aktuellen Werte über eine vom Aufrufer übergebene
 *   getValue(key)-Funktion, damit dev/preview.html (einfache Controls)
 *   und admin-design.js (volles WordPress-Formular) dieselbe Funktion
 *   nutzen können, ohne dass beide eigene Kopien der Anwendungslogik
 *   mitbringen. Fehlt ein Wert (getValue liefert '' /undefined), wird die
 *   entsprechende Variable einfach nicht gesetzt - der Stylesheet-Default
 *   greift dann weiter.
 */
(function ( global ) {
    'use strict';

    // Punkt, an dem der Kontrast zu Schwarz und zu Weiß rechnerisch gleich
    // groß ist (aus der WCAG-Kontrastformel hergeleitet) - muss exakt zum
    // PHP-Pendant SMF_Design::LUMINANCE_THRESHOLD passen.
    var LUMINANCE_THRESHOLD = 0.179;

    var FONT_STACKS = {
        'system-sans':  '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, sans-serif',
        'system-serif': 'Georgia, "Times New Roman", Times, serif'
    };

    // Muss zu SMF_Design::SHADOW_STEP_SUFFIX passen.
    var SHADOW_STEP_SUFFIX = { none: 'none', soft: 'dezent', normal: 'normal', strong: 'stark' };
    // Muss zu SMF_Design::SHADOW_LEAVES passen.
    var SHADOW_LEAVES = [ 'alpha', 'alpha-hover', 'sm-alpha', 'sm-alpha-hover', 'modal-alpha', 'tint-pct', 'tint-pct-hover' ];

    // Design-Feld -> CSS-Variable, für die reinen 1:1-Farbwerte.
    var COLOR_FIELD_VARS = {
        color_primary:       '--smf-color-primary',
        color_accent:        '--smf-color-accent',
        color_surface:       '--smf-color-surface',
        color_surface_alt:   '--smf-color-surface-alt',
        color_border:        '--smf-color-border',
        color_border_strong: '--smf-color-border-strong',
        color_text:          '--smf-color-text',
        color_text_muted:    '--smf-color-text-muted'
    };

    /**
     * @param {string} hex z.B. "#006045" oder "006045"
     * @return {number} relative Luminanz nach WCAG 2.x, 0..1
     */
    function relativeLuminance( hex ) {
        hex = String( hex || '' ).trim().replace( '#', '' );
        if ( hex.length === 3 ) {
            hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
        }
        if ( hex.length !== 6 || ! /^[0-9a-fA-F]{6}$/.test( hex ) ) {
            return 1; // ungültiger Wert -> sicherheitshalber dunklen Text wählen
        }

        function toLinear( channel ) {
            var c = channel / 255;
            return c <= 0.03928 ? c / 12.92 : Math.pow( ( c + 0.055 ) / 1.055, 2.4 );
        }

        var r = toLinear( parseInt( hex.substr( 0, 2 ), 16 ) );
        var g = toLinear( parseInt( hex.substr( 2, 2 ), 16 ) );
        var b = toLinear( parseInt( hex.substr( 4, 2 ), 16 ) );

        return 0.2126 * r + 0.7152 * g + 0.0722 * b;
    }

    /**
     * @param {string} hex  Hintergrundfarbe
     * @param {string} mode 'auto' | 'light' | 'dark'
     * @return {'light'|'dark'} welche Textfarbe auf hex besser lesbar ist
     */
    function contrastMode( hex, mode ) {
        if ( mode === 'light' || mode === 'dark' ) {
            return mode;
        }
        return relativeLuminance( hex ) > LUMINANCE_THRESHOLD ? 'dark' : 'light';
    }

    /**
     * @param {string} mode   'inherit' | 'system-sans' | 'system-serif' | 'custom'
     * @param {string} custom Freitext-Font-Stack (nur bei mode === 'custom' relevant)
     * @return {string}
     */
    function resolveFont( mode, custom ) {
        if ( mode === 'system-sans' || mode === 'system-serif' ) {
            return FONT_STACKS[ mode ];
        }
        if ( mode === 'custom' ) {
            return custom ? custom : 'inherit';
        }
        return 'inherit';
    }

    /**
     * Setzt die sieben aktiven --smf-shadow-*-Variablen als var()-Referenz
     * auf die gewählte Stufe (--smf-shadow-step-{suffix}-*, definiert in
     * style.css). Keine Zahlen hier - nur die Namenszuordnung.
     *
     * @param {HTMLElement} root
     * @param {string}      level 'none' | 'soft' | 'normal' | 'strong'
     */
    function applyShadowStep( root, level ) {
        var suffix = SHADOW_STEP_SUFFIX[ level ] || 'normal';
        SHADOW_LEAVES.forEach( function ( leaf ) {
            root.style.setProperty( '--smf-shadow-' + leaf, 'var(--smf-shadow-step-' + suffix + '-' + leaf + ')' );
        } );
    }

    /**
     * Setzt alle Design-CSS-Variablen auf `root`, basierend auf den über
     * `getValue(key)` gelieferten aktuellen Werten. `key` ist einer der
     * SMF_Design-Feldnamen (color_primary, radius, shadow_intensity,
     * font_size, contrast_on_primary, font_mode, font_custom, ...).
     * Liefert getValue() für ein Feld '' /undefined/null, wird die
     * zugehörige Variable übersprungen (Stylesheet-Default bleibt aktiv) -
     * so kann dieselbe Funktion sowohl von der vollen Design-Seite
     * (alle Felder) als auch von der schlanken dev/preview.html (nur
     * Primary/Accent/Radius/Schatten/Schriftgröße) genutzt werden.
     *
     * @param {HTMLElement} root
     * @param {function(string): string} getValue
     */
    function apply( root, getValue ) {
        if ( ! root || typeof getValue !== 'function' ) {
            return;
        }

        function val( key ) {
            var v = getValue( key );
            return ( v === undefined || v === null ) ? '' : String( v );
        }

        Object.keys( COLOR_FIELD_VARS ).forEach( function ( key ) {
            var v = val( key );
            if ( v !== '' ) {
                root.style.setProperty( COLOR_FIELD_VARS[ key ], v );
            }
        } );

        var primary = val( 'color_primary' );
        if ( primary !== '' ) {
            var onPrimary = contrastMode( primary, val( 'contrast_on_primary' ) || 'auto' );
            root.style.setProperty( '--smf-color-on-primary', onPrimary === 'dark' ? 'var(--smf-color-text)' : 'var(--smf-color-surface)' );
        }

        var accent = val( 'color_accent' );
        if ( accent !== '' ) {
            var onAccent = contrastMode( accent, val( 'contrast_on_accent' ) || 'auto' );
            root.style.setProperty( '--smf-color-on-accent', onAccent === 'dark' ? 'var(--smf-color-text)' : 'var(--smf-color-surface)' );
        }

        var radius = val( 'radius' );
        if ( radius !== '' ) {
            root.style.setProperty( '--smf-radius', radius + 'px' );
        }

        var fontSize = val( 'font_size' );
        if ( fontSize !== '' ) {
            root.style.setProperty( '--smf-fs-base', fontSize + 'px' );
        }

        var shadow = val( 'shadow_intensity' );
        if ( shadow !== '' ) {
            applyShadowStep( root, shadow );
        }

        var fontMode = val( 'font_mode' );
        if ( fontMode !== '' ) {
            root.style.setProperty( '--smf-font', resolveFont( fontMode, val( 'font_custom' ) ) );
        }

        var titleMode = val( 'font_title_mode' );
        if ( titleMode !== '' ) {
            root.style.setProperty( '--smf-font-title', resolveFont( titleMode, val( 'font_title_custom' ) ) );
        }
    }

    global.smfDesignPreview = {
        LUMINANCE_THRESHOLD: LUMINANCE_THRESHOLD,
        relativeLuminance: relativeLuminance,
        contrastMode: contrastMode,
        resolveFont: resolveFont,
        applyShadowStep: applyShadowStep,
        apply: apply
    };
} )( window );
