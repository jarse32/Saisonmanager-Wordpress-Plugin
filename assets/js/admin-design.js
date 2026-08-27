/**
 * SM Floorball – Admin JS für die Design-Unterseite.
 *
 * - Initialisiert wp-color-picker auf den Farbfeldern.
 * - Blendet das Freitextfeld für den eigenen Font-Stack nur bei
 *   "Eigener Font-Stack" ein.
 * - Presets (Eichehorn/Neutral/Dark) befüllen die Formularfelder.
 * - Live-Vorschau: nutzt smfDesignPreview.apply() aus design-contrast.js
 *   (dieselbe Funktion wie dev/preview.html) statt einer eigenen Kopie der
 *   Anwendungslogik - hier wird nur definiert, WIE ein Feldwert aus dem
 *   WordPress-Formular gelesen wird (fieldValue()).
 */
(function ( $ ) {
    'use strict';

    // Reines UI-Preset ohne PHP-Pendant - Eichehorn/Neutral kommen per
    // wp_localize_script aus SMF_Design (smf_design_presets), damit diese
    // Werte nicht doppelt gepflegt werden müssen.
    var DARK_PRESET = {
        color_primary:       '#3b82c4',
        color_accent:        '#ffb347',
        color_surface:       '#1e1e1e',
        color_surface_alt:   '#2a2a2a',
        color_border:        '#3a3a3a',
        color_border_strong: '#4a4a4a',
        color_text:          '#f0f0f0',
        color_text_muted:    '#a0a0a0',
        contrast_on_primary: 'auto',
        contrast_on_accent:  'auto',
        radius:              8,
        shadow_intensity:    'normal',
        font_size:           16,
        font_mode:           'inherit',
        font_custom:         '',
        font_title_mode:     'inherit',
        font_title_custom:   ''
    };

    $( function () {
        var $form   = $( '#smf-design-form' );
        var $root   = $( '#smf-design-preview-root' );
        var presets = {
            eichehorn: ( typeof smf_design_presets !== 'undefined' ) ? smf_design_presets.eichehorn : {},
            neutral:   ( typeof smf_design_presets !== 'undefined' ) ? smf_design_presets.neutral : {},
            dark:      DARK_PRESET
        };

        if ( ! $form.length || ! $root.length ) {
            return;
        }

        function fieldValue( key ) {
            var $field = $form.find( '[name="smf_design[' + key + ']"]' );
            if ( ! $field.length ) {
                return '';
            }
            if ( $field.attr( 'type' ) === 'radio' ) {
                var $checked = $form.find( '[name="smf_design[' + key + ']"]:checked' );
                return $checked.length ? $checked.val() : '';
            }
            return $field.val() || '';
        }

        function applyPreview() {
            smfDesignPreview.apply( $root.get( 0 ), fieldValue );
        }

        function toggleFontCustomRow( $select ) {
            var targetId = $select.data( 'target' );
            if ( ! targetId ) return;
            $( '#' + targetId ).toggle( $select.val() === 'custom' );
        }

        // Farb-Picker initialisieren
        $form.find( '.smf-design-color-field' ).wpColorPicker( {
            change: function () { applyPreview(); },
            clear: function () { applyPreview(); }
        } );

        // Font-Modus: Freitextfeld ein-/ausblenden
        $form.find( '.smf-design-font-mode' ).each( function () {
            toggleFontCustomRow( $( this ) );
        } );
        $form.on( 'change', '.smf-design-font-mode', function () {
            toggleFontCustomRow( $( this ) );
            applyPreview();
        } );

        // Alle übrigen Felder (Radius, Schatten, Kontrast, Schriftgröße,
        // Freitext-Font-Stacks) live an die Vorschau koppeln.
        $form.on( 'input change', 'input:not(.smf-design-color-field), select:not(.smf-design-font-mode)', function () {
            applyPreview();
        } );

        // Presets
        $( '[data-smf-preset]' ).on( 'click', function () {
            var data = presets[ $( this ).data( 'smf-preset' ) ] || {};

            $.each( data, function ( key, value ) {
                var $field = $form.find( '[name="smf_design[' + key + ']"]' );
                if ( ! $field.length ) return;

                if ( $field.attr( 'type' ) === 'radio' ) {
                    $form.find( '[name="smf_design[' + key + ']"]' ).prop( 'checked', false );
                    $form.find( '[name="smf_design[' + key + ']"][value="' + value + '"]' ).prop( 'checked', true );
                    return;
                }

                if ( $field.hasClass( 'smf-design-color-field' ) ) {
                    $field.wpColorPicker( 'color', value );
                    return;
                }

                $field.val( value );
                if ( $field.hasClass( 'smf-design-font-mode' ) ) {
                    toggleFontCustomRow( $field );
                }
            } );

            applyPreview();
        } );

        applyPreview();
    } );

} )( jQuery );
