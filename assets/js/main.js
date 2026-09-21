/**
 * Saisonmanager Floorball – Frontend JavaScript
 * Handhabt Modal-Öffnung, AJAX-Laden der Spieldetails und Keyboard-Navigation
 */

(function ($) {
    'use strict';

    const SMF = {

        modal: null,
        overlay: null,
        body: null,
        activeGameId: null,

        init() {
            this.bindEvents();
        },

        bindEvents() {
            // Spielkarten-Klick (delegiert, da Shortcodes mehrfach auf einer Seite sein können)
            $(document).on('click keydown', '[data-game-id]', function (e) {
                if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
                e.preventDefault();

                const gameId = $(this).data('game-id');
                if (!gameId) return;

                SMF.openModal(gameId);
            });

            // Stream-Button in einer Karte (Etappe D): öffnet dasselbe Modal
            // wie ein Klick auf die Karte selbst, aber eigener Handler +
            // stopPropagation - sonst würde der Klick zusätzlich noch den
            // kartenweiten [data-game-id]-Handler oben auslösen (doppelter
            // AJAX-Request für dasselbe Spiel).
            $(document).on('click', '.smf-stream-btn', function (e) {
                e.preventDefault();
                e.stopPropagation();

                const gameId = $(this).data('game-id');
                if (!gameId) return;

                SMF.openModal(gameId);
            });

            // Zwei-Klick-Reveal im Spieldetail-Modal (Etappe D, Option
            // smf_stream_embed_mode = "zwei_klick"): erst hier, nach
            // explizitem Klick, wird das iframe erzeugt - vorher (Platzhalter)
            // ist kein Request an den Streaming-Anbieter ausgelöst worden.
            $(document).on('click', '.smf-stream-reveal', function (e) {
                e.preventDefault();

                const $btn      = $(this);
                const embedUrl  = $btn.data('embed-url');
                const embedTitle = $btn.data('embed-title') || '';
                if (!embedUrl) return;

                const $iframe = $('<iframe>', {
                    src: embedUrl,
                    title: embedTitle,
                    allow: 'autoplay; fullscreen; picture-in-picture',
                    allowfullscreen: true,
                    referrerpolicy: 'strict-origin-when-cross-origin',
                    class: 'smf-stream-embed__iframe',
                });

                $btn.closest('.smf-stream-embed').empty().append($iframe);
            });

            // Modal schließen – Close-Button
            $(document).on('click', '.smf-modal-close', function () {
                SMF.closeModal();
            });

            // Modal schließen – Overlay-Klick
            $(document).on('click', '.smf-modal-overlay', function () {
                SMF.closeModal();
            });

            // Modal schließen – ESC
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape') {
                    SMF.closeModal();
                }
            });
        },

        /**
         * Modal öffnen und Spieldetails laden
         *
         * @param {number} gameId
         */
        openModal(gameId) {
            // Prüfen ob ein Modal auf der Seite existiert
            let $modal = $('#smf-modal');
            if (!$modal.length) return;

            this.modal   = $modal;
            this.overlay = $modal.find('.smf-modal-overlay');
            this.body    = $modal.find('#smf-modal-body');

            this.activeGameId = gameId;

            // Loading-Zustand
            this.body.html(
                '<div class="smf-loading">' +
                '<div class="smf-spinner"></div>' +
                '<p>Lade Spieldetails…</p>' +
                '</div>'
            );

            this.modal.show();
            $('body').css('overflow', 'hidden');

            // Fokus auf Close-Button setzen (Accessibility)
            setTimeout(() => {
                this.modal.find('.smf-modal-close').trigger('focus');
            }, 100);

            // AJAX-Request
            $.ajax({
                url: smf_ajax.ajax_url,
                type: 'POST',
                data: {
                    action:   'smf_game_detail',
                    nonce:    smf_ajax.nonce,
                    game_id:  gameId,
                },
                success: (response) => {
                    if (response.success && response.data && response.data.html) {
                        this.body.html(response.data.html);
                    } else {
                        const msg = (response.data && response.data.message)
                            ? response.data.message
                            : 'Unbekannter Fehler';
                        this.body.html(
                            '<div class="smf-error"><strong>Fehler:</strong> ' + this.escHtml(msg) + '</div>'
                        );
                    }
                },
                error: (xhr, status, error) => {
                    this.body.html(
                        '<div class="smf-error"><strong>Verbindungsfehler:</strong> ' +
                        this.escHtml(error || 'Bitte Verbindung prüfen.') + '</div>'
                    );
                },
            });
        },

        /**
         * Modal schließen
         */
        closeModal() {
            if (!this.modal) return;

            // Inhalt leeren statt nur zu verstecken: ein evtl. eingebetteter
            // Livestream (Etappe D, .smf-stream-embed__iframe) darf im
            // Hintergrund nicht weiterlaufen. Unkritisch für den Normalfall
            // (Spieldetails) - openModal() ersetzt den Inhalt beim nächsten
            // Öffnen ohnehin sofort durch den Loading-Zustand und lädt ihn
            // frisch per AJAX nach.
            this.body.empty();

            this.modal.hide();
            $('body').css('overflow', '');
            this.activeGameId = null;

            // Fokus zurück auf die Spielkarte
            if (this.activeGameId) {
                $('[data-game-id="' + this.activeGameId + '"]').first().trigger('focus');
            }
        },

        /**
         * HTML escapen
         */
        escHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        },
    };

    $(document).ready(function () {
        SMF.init();
    });

})(jQuery);
