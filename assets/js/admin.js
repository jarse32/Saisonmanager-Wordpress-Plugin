/**
 * SM Floorball – Admin JS
 * Dynamisches Hinzufügen/Entfernen von Verein/Team-Konfiguration, Team-Finder
 * und Club-ID-basiertes Team-Laden.
 */
(function ($) {
    'use strict';

    // ----------------------------------------------------------------
    // Vereine – Zähler für neue Indizes
    // ----------------------------------------------------------------

    // Startet beim nächsten freien Index nach bestehenden Vereinen
    let vereinCounter = (typeof smf_admin_data !== 'undefined' && smf_admin_data.verein_count)
        ? parseInt( smf_admin_data.verein_count, 10 )
        : 0;

    /**
     * Neue Team-ID-Zeile für einen Verein generieren
     */
    function buildTeamRow( vi ) {
        const template = document.getElementById('smf-team-row-template');
        if ( ! template ) return null;

        // Inhalt aus dem versteckten Template holen (steht in einer <table><tbody>)
        const $row = $(template).find('tr.smf-team-row').clone();

        // Verein-Index im Field-Namen ersetzen
        $row.find('input').each(function () {
            const name = $(this).attr('name') || '';
            $(this).attr('name', name.replace(/__VI__/g, vi));
        });

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
    // Club-ID: Teams laden
    // ----------------------------------------------------------------

    /**
     * Gleiche Darstellung wie SMF_Admin::render_club_teams_list() (PHP),
     * damit initialer (Cache-)Zustand und AJAX-Ergebnis identisch aussehen.
     */
    function renderClubTeamsList( teams ) {
        if ( ! teams || ! teams.length ) {
            return '<p class="smf-notice">Noch keine Teams geladen.</p>';
        }

        let html = '<ul class="smf-club-teams">';
        teams.forEach(function (team) {
            html += '<li><strong>' + escHtml(team.name || '(ohne Namen)') + '</strong> ';
            html += '<code>' + escHtml(team.id) + '</code>';

            if ( team.leagues && team.leagues.length ) {
                const parts = team.leagues.map(function (l) {
                    const label = l.name || l.short_name || 'Liga';
                    const id    = (l.id !== undefined && l.id !== null) ? l.id : '?';
                    return escHtml(label + ' (' + id + ')');
                });
                html += '<br><small>Liga(en): ' + parts.join(', ') + '</small>';
            }
            html += '</li>';
        });
        html += '</ul>';

        return html;
    }

    function runClubTeamsSync( $btn ) {
        const $block   = $btn.closest('.smf-verein-block');
        const $results = $block.find('.smf-club-teams-results');
        const clubId   = $block.find('.smf-club-id-input').val();

        if ( ! clubId ) {
            $results.html('<p class="smf-notice">Bitte zuerst eine Club-ID eintragen.</p>');
            return;
        }
        if ( typeof smf_admin_data === 'undefined' || ! smf_admin_data.ajax_url ) {
            return;
        }

        $results.html('<p>Lade Teams…</p>');
        $btn.prop('disabled', true);

        $.post(smf_admin_data.ajax_url, {
            action:  'smf_sync_club_teams',
            nonce:   smf_admin_data.nonce,
            club_id: clubId,
        }).done(function (response) {
            if ( ! response || ! response.success ) {
                const msg = (response && response.data) ? response.data : 'Unbekannter Fehler';
                $results.html('<p class="smf-error">' + escHtml(msg) + '</p>');
                return;
            }
            $results.html( renderClubTeamsList( response.data.teams || [] ) );
        }).fail(function () {
            $results.html('<p class="smf-error">Anfrage fehlgeschlagen. Bitte erneut versuchen.</p>');
        }).always(function () {
            $btn.prop('disabled', false);
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

        $(document).on('click', '.smf-sync-club-teams', function () {
            runClubTeamsSync( $(this) );
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

        // Team-ID-Zeile hinzufügen (delegiert, da Vereine dynamisch hinzugefügt werden)
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
