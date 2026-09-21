/**
 * Saisonmanager Floorball – Frontend JavaScript
 * Handhabt Modal-Öffnung, AJAX-Laden der Spieldetails und Keyboard-Navigation
 */

(function ($) {
    'use strict';

    /**
     * Gemerkte Zwei-Klick-Einwilligung (v1.9.0, Teil 1) - rein clientseitig
     * in localStorage, getrennt pro Anbieter, 12 Monate gültig. Jeder
     * Zugriff in try/catch: ist localStorage nicht verfügbar (Privacy-
     * Modus, deaktiviert, Quota voll), verhält sich alles wie bisher -
     * kein Fehler, nur keine gemerkte Einwilligung.
     */
    const SMF_STREAM_CONSENT_TTL_MS = 365 * 24 * 60 * 60 * 1000; // 12 Monate, siehe README

    const StreamConsent = {
        key(provider) {
            return 'smf_stream_consent_' + provider;
        },

        /** @return {boolean} */
        has(provider) {
            if (!provider) return false;
            try {
                const raw = window.localStorage.getItem(this.key(provider));
                if (!raw) return false;

                const data = JSON.parse(raw);
                if (!data || data.granted !== true || typeof data.ts !== 'number') return false;

                if ((Date.now() - data.ts) > SMF_STREAM_CONSENT_TTL_MS) {
                    this.clear(provider);
                    return false;
                }
                return true;
            } catch (e) {
                return false;
            }
        },

        set(provider) {
            if (!provider) return;
            try {
                window.localStorage.setItem(this.key(provider), JSON.stringify({ granted: true, ts: Date.now() }));
            } catch (e) {
                // localStorage nicht verfügbar - Einwilligung wird einfach nicht gemerkt.
            }
        },

        clear(provider) {
            if (!provider) return;
            try {
                window.localStorage.removeItem(this.key(provider));
            } catch (e) {
                // s.o.
            }
        },
    };

    const SMF = {

        modal: null,
        overlay: null,
        body: null,
        activeGameId: null,

        init() {
            this.bindEvents();
            this.maybeAutoRevealStreams($(document));
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

            // Zwei-Klick-Reveal im Spieldetail-Modal / bei sm_livestream
            // (Option smf_stream_embed_mode = "zwei_klick"): erst hier, nach
            // explizitem Klick, wird das iframe erzeugt - vorher (Platzhalter)
            // ist kein Request an den Streaming-Anbieter ausgelöst worden.
            $(document).on('click', '.smf-stream-reveal', function (e) {
                e.preventDefault();

                const $btn       = $(this);
                const embedUrl   = $btn.data('embed-url');
                const embedTitle = $btn.data('embed-title') || '';
                if (!embedUrl) return;

                const $container = $btn.closest('.smf-stream-embed');
                const provider   = $container.data('provider') || '';

                // Nur merken, wenn die Checkbox vorhanden UND angehakt ist -
                // die Checkbox selbst rendert PHP-seitig ohnehin nur, wenn
                // die Option "Einwilligung merken erlauben" an ist (siehe
                // smf_render_stream_embed()).
                const $checkbox = $container.find('.smf-stream-remember-checkbox');
                if (smf_ajax.stream_remember_allowed && $checkbox.length && $checkbox.is(':checked')) {
                    StreamConsent.set(provider);
                }

                SMF.revealStream($container, embedUrl, embedTitle, provider);
            });

            // Widerruf der gemerkten Einwilligung: löscht den localStorage-
            // Eintrag und zeigt wieder den (unveränderten, frisch geklonten)
            // Platzhalter mit einer wieder nicht angehakten Checkbox. Der
            // Link sitzt bewusst AUSSERHALB von .smf-stream-embed (dessen
            // 16:9-Box overflow:hidden hat, siehe style.css) - deshalb
            // .prev() statt .closest().
            $(document).on('click', '.smf-stream-forget', function (e) {
                e.preventDefault();

                const $forgetBtn = $(this);
                const $container = $forgetBtn.prev('.smf-stream-embed');
                const provider   = $container.data('provider') || '';
                StreamConsent.clear(provider);

                const placeholderHtml = $container.data('smf-placeholder-html');
                if (placeholderHtml) {
                    $container.html(placeholderHtml);
                }
                $forgetBtn.remove();
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
                        this.maybeAutoRevealStreams(this.body);
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
         * Platzhalter durch das iframe ersetzen, optional gefolgt vom
         * Widerrufs-Link "Automatisches Laden beenden" - genau dann, wenn
         * für diesen Anbieter tatsächlich eine gemerkte Einwilligung aktiv
         * ist (egal ob gerade erst gesetzt oder schon von einem früheren
         * Besuch). Ein einmaliger manueller Klick auf "Video laden" ohne
         * angehakte Checkbox erzeugt also KEINEN Widerrufs-Link - es gibt
         * nichts zu widerrufen.
         *
         * @param {jQuery} $container .smf-stream-embed
         * @param {string} embedUrl
         * @param {string} embedTitle
         * @param {string} provider
         */
        revealStream($container, embedUrl, embedTitle, provider) {
            // Platzhalter-HTML einmalig sichern, bevor er ersetzt wird -
            // .smf-stream-forget braucht ihn, um exakt dorthin zurückzukehren
            // (inkl. wieder nicht angehakter Checkbox).
            if ($container.data('smf-placeholder-html') === undefined) {
                $container.data('smf-placeholder-html', $container.html());
            }

            // Ein evtl. noch vorhandener Widerrufs-Link von einem früheren
            // Reveal desselben Containers muss weg, bevor ggf. ein neuer
            // angehängt wird (sonst doppelt).
            $container.next('.smf-stream-forget').remove();

            const $iframe = $('<iframe>', {
                src: embedUrl,
                title: embedTitle,
                allow: 'autoplay; fullscreen; picture-in-picture',
                allowfullscreen: true,
                referrerpolicy: 'strict-origin-when-cross-origin',
                class: 'smf-stream-embed__iframe',
            });

            $container.empty().append($iframe);

            if (smf_ajax.stream_remember_allowed && StreamConsent.has(provider)) {
                // AUSSERHALB von .smf-stream-embed anhängen (dessen 16:9-Box
                // overflow:hidden hat) - sonst wäre der Link unsichtbar.
                const $forget = $('<button>', {
                    type: 'button',
                    class: 'smf-stream-forget',
                    text: smf_ajax.stream_forget_label || 'Automatisches Laden beenden',
                });
                $container.after($forget);
            }
        },

        /**
         * Beim Laden der Seite (und nach dem AJAX-Laden des Modal-Inhalts)
         * alle Zwei-Klick-Platzhalter auf eine gültige gemerkte Einwilligung
         * prüfen und ggf. sofort das iframe laden, ohne den Platzhalter
         * überhaupt anzuzeigen. Greift komplett nicht, wenn die Option
         * "Einwilligung merken erlauben" aus ist - dann auch dann nicht,
         * wenn im Browser noch eine ältere Einwilligung gespeichert ist
         * (siehe smf_ajax.stream_remember_allowed).
         *
         * @param {jQuery} $scope Bereich, in dem gesucht wird (document oder das Modal-Body-Element)
         */
        maybeAutoRevealStreams($scope) {
            if (!smf_ajax.stream_remember_allowed) return;

            $scope.find('.smf-stream-embed[data-provider]').each(function () {
                const $container = $(this);
                const provider   = $container.data('provider');
                if (!StreamConsent.has(provider)) return;

                const $btn = $container.find('.smf-stream-reveal');
                const embedUrl = $btn.data('embed-url');
                if (!embedUrl) return;

                SMF.revealStream($container, embedUrl, $btn.data('embed-title') || '', provider);
            });
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
