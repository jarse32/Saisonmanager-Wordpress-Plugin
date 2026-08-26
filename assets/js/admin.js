/**
 * Saisonmanager Floorball – Admin JS
 * Dynamisches Hinzufügen/Entfernen von Verbands-Zeilen und Verein/Team-Konfiguration
 */
(function ($) {
    'use strict';

    // ----------------------------------------------------------------
    // Verbände-Tabelle
    // ----------------------------------------------------------------

    const verbandRowTemplate = `
        <tr class="smf-verband-row">
            <td><input type="text" name="smf_v_slug[]" class="regular-text" placeholder="z.B. fvd"
                       pattern="[a-z0-9\\-]+" title="Nur Kleinbuchstaben, Zahlen und Bindestriche"></td>
            <td><input type="text" name="smf_v_name[]" class="regular-text" placeholder="z.B. Floorball Verband Deutschland"></td>
            <td><input type="url"  name="smf_v_url[]"  class="regular-text" placeholder="https://fvd.saisonmanager.de/api/v2"></td>
            <td><input type="text" name="smf_v_api_key[]" class="regular-text" autocomplete="off" placeholder="(Standard-Key)"></td>
            <td><button type="button" class="button smf-remove-row">&#10005; Entfernen</button></td>
        </tr>`;

    // ----------------------------------------------------------------
    // Vereine – Zähler für neue Indizes
    // ----------------------------------------------------------------

    // Startet beim nächsten freien Index nach bestehenden Vereinen
    let vereinCounter = (typeof smf_admin_data !== 'undefined' && smf_admin_data.verein_count)
        ? parseInt( smf_admin_data.verein_count, 10 )
        : 0;

    /**
     * Verbands-Optionen als HTML-String generieren
     * (für dynamisch hinzugefügte Select-Elemente)
     */
    function buildVerbandOptions() {
        if ( typeof smf_admin_data === 'undefined' || ! smf_admin_data.verbaende ) {
            return '<option value="">(Standard-URL)</option>';
        }
        let opts = '<option value="">(Standard-URL)</option>';
        smf_admin_data.verbaende.forEach(function (v) {
            opts += '<option value="' + escAttr(v.slug) + '">' + escHtml(v.name || v.slug) + '</option>';
        });
        return opts;
    }

    /**
     * Neue Team-Zeile für einen Verein generieren
     */
    function buildTeamRow( vi ) {
        const template = document.getElementById('smf-team-row-template');
        if ( ! template ) return null;

        // Inhalt aus dem versteckten Template holen (steht in einer <table><tbody>)
        const $row = $(template).find('tr.smf-team-row').clone();

        // Verein-Index im Field-Namen ersetzen
        $row.find('input, select').each(function () {
            const name = $(this).attr('name') || '';
            $(this).attr('name', name.replace(/__VI__/g, vi));
        });

        // Verbands-Optionen befüllen (select ist bereits im Template vorhanden)
        $row.find('select').html( buildVerbandOptions() );

        return $row;
    }

    /**
     * Neuen Verein-Block generieren
     */
    function buildVereinBlock( vi ) {
        const template = document.getElementById('smf-verein-template');
        if ( ! template ) return null;

        const $block = $(template).children('.smf-verein-block').clone();

        // Verein-Index in allen Field-Namen, data-Attributen und Labels ersetzen
        $block.attr('data-verein-index', vi);

        $block.find('input, select, button').each(function () {
            const name = $(this).attr('name') || '';
            if ( name ) $(this).attr('name', name.replace(/__VI__/g, vi));

            const dataVi = $(this).data('verein-index');
            if ( typeof dataVi !== 'undefined' ) $(this).attr('data-verein-index', vi);
        });

        $block.find('[data-verein-index]').attr('data-verein-index', vi);

        return $block;
    }

    /**
     * Einfaches HTML-Escaping
     */
    function escHtml( str ) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function escAttr( str ) {
        return escHtml( str );
    }

    // ----------------------------------------------------------------
    // Team-Finder
    // ----------------------------------------------------------------

    function runTeamFinderSearch() {
        const $results = $('#smf-tf-results');
        const query    = $('#smf-tf-query').val().trim();

        if ( ! query ) {
            $results.html('<p class="smf-notice">Bitte einen Vereinsnamen oder eine Club-ID eingeben.</p>');
            return;
        }
        if ( typeof smf_admin_data === 'undefined' || ! smf_admin_data.ajax_url ) {
            return;
        }

        $results.html('<p>Suche läuft…</p>');

        $.post(smf_admin_data.ajax_url, {
            action:    'smf_find_teams',
            nonce:     smf_admin_data.nonce,
            query:     query,
            verband:   $('#smf-tf-verband').val(),
            season_id: $('#smf-tf-season').val(),
        }).done(function (response) {
            if ( ! response || ! response.success ) {
                const msg = (response && response.data) ? response.data : 'Unbekannter Fehler';
                $results.html('<p class="smf-error">' + escHtml(msg) + '</p>');
                return;
            }

            const clubs = response.data.results || [];
            if ( ! clubs.length ) {
                $results.html('<p class="smf-notice">Keine Treffer.</p>');
                return;
            }

            let html = '';
            clubs.forEach(function (club) {
                html += '<div class="smf-tf-club">';
                html += '<strong>' + escHtml(club.club_name) + '</strong> ';
                html += '<small>(Club-ID ' + escHtml(club.club_id) + ', Spielbetriebsstelle ' + escHtml(club.operation_id) + ')</small>';
                if ( club.teams && club.teams.length ) {
                    html += '<ul class="smf-tf-teams">';
                    club.teams.forEach(function (team) {
                        html += '<li><code>' + escHtml(team.id) + '</code> – ' + escHtml(team.name || '(ohne Namen)') + '</li>';
                    });
                    html += '</ul>';
                } else {
                    html += '<p class="smf-notice">Keine Teams für diesen Verein gefunden.</p>';
                }
                html += '</div>';
            });
            $results.html(html);
        }).fail(function () {
            $results.html('<p class="smf-error">Anfrage fehlgeschlagen. Bitte erneut versuchen.</p>');
        });
    }

    // ----------------------------------------------------------------
    // Event-Binding
    // ----------------------------------------------------------------

    $(document).ready(function () {

        $('#smf-tf-search').on('click', runTeamFinderSearch);
        $('#smf-tf-query, #smf-tf-season').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                runTeamFinderSearch();
            }
        });

        // --- Verbände ---

        $('#smf-add-verband').on('click', function () {
            $('#smf-verbaende-body').append( verbandRowTemplate );
        });

        $(document).on('click', '.smf-remove-row', function () {
            $(this).closest('tr').remove();
        });

        // --- Vereine ---

        // Neuen Verein hinzufügen
        $('#smf-add-verein').on('click', function () {
            const vi = vereinCounter++;
            const $block = buildVereinBlock( vi );
            if ( $block ) {
                $('#smf-vereine-container').append( $block );
            }
        });

        // Verein entfernen (delegiert)
        $(document).on('click', '.smf-remove-verein', function () {
            if ( confirm('Diesen Verein wirklich entfernen?') ) {
                $(this).closest('.smf-verein-block').remove();
            }
        });

        // Team/Liga-Zeile hinzufügen (delegiert, da Vereine dynamisch hinzugefügt werden)
        $(document).on('click', '.smf-add-team', function () {
            const vi = $(this).data('verein-index');
            const $row = buildTeamRow( vi );
            if ( $row ) {
                $(this).closest('.smf-verein-block').find('.smf-teams-body').append( $row );
            }
        });

        // Team-Zeile entfernen (delegiert)
        $(document).on('click', '.smf-remove-team', function () {
            $(this).closest('tr').remove();
        });

        // Vereinsname live im Block-Header spiegeln
        $(document).on('input', '.smf-verein-name-input', function () {
            const $block = $(this).closest('.smf-verein-block');
            const name = $(this).val().trim() || 'Neuer Verein';
            $block.find('.smf-verein-block__title').text( name );
        });

    });

})(jQuery);
