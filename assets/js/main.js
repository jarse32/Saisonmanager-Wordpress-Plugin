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

                const verband = $(this).data('verband') || '';
                SMF.openModal(gameId, verband);
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
         * @param {string} verband  Optionaler Verbands-Slug für Verbands-spezifische Anfragen
         */
        openModal(gameId, verband) {
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
                    verband:  verband || '',
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
