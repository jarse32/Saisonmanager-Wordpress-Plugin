<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Alle Shortcodes des Plugins
 *
 * [sm_tabelle liga_id="123"]
 * [sm_spiele liga_id="123" anzahl="10" team="Eichehorn"]
 * [sm_naechstes_spiel liga_id="123" team="Eichehorn"]
 * [sm_letztes_spiel liga_id="123"]
 * [sm_spiel_duo liga_id="123" reihenfolge="naechstes-zuerst"]
 * [sm_scorer team_id="6754" anzahl="10"]
 * [sm_livestream team_id="6754" nach_spielende="aufzeichnung"]
 */
class SMF_Shortcodes {

    public function register() {
        add_shortcode( 'sm_tabelle',            array( $this, 'shortcode_tabelle' ) );
        add_shortcode( 'sm_spiele',             array( $this, 'shortcode_spiele' ) );
        add_shortcode( 'sm_naechstes_spiel',    array( $this, 'shortcode_naechstes_spiel' ) );
        add_shortcode( 'sm_letztes_spiel',      array( $this, 'shortcode_letztes_spiel' ) );
        add_shortcode( 'sm_spiel_duo',          array( $this, 'shortcode_spiel_duo' ) );
        add_shortcode( 'sm_vereinsuebersicht',  array( $this, 'shortcode_vereinsuebersicht' ) );
        add_shortcode( 'sm_scorer',             array( $this, 'shortcode_scorer' ) );
        add_shortcode( 'sm_livestream',         array( $this, 'shortcode_livestream' ) );
    }

    // ----------------------------------------------------------------
    // [sm_tabelle liga_id="123" titel="true" logo_groesse="mittel" hervorheben=""]
    // ----------------------------------------------------------------
    public function shortcode_tabelle( $atts ) {
        $atts = shortcode_atts( array(
            'liga_id'      => get_option( 'smf_default_league_id', '' ),
            'titel'        => 'true',
            'logos'        => 'false',
            'logo_groesse' => 'mittel',
            'hervorheben'  => '',
        ), $atts, 'sm_tabelle' );

        $liga_id = (int) $atts['liga_id'];
        if ( ! $liga_id ) {
            return $this->error( 'Bitte liga_id angeben, z.B. [sm_tabelle liga_id="123"]' );
        }

        $api = new SMF_API();

        $table = $api->get_table( $liga_id );
        if ( is_wp_error( $table ) ) {
            return $this->error( $table );
        }

        $league      = $api->get_league( $liga_id );
        $league_name = is_wp_error( $league ) ? '' : ( isset( $league['name'] ) ? $league['name'] : '' );

        // Erst nach dem letzten API-Aufruf lesen, direkt vor dem Rendern -
        // ein Shortcode macht hier zwei Requests (Tabelle + Liganame), der
        // Hinweis soll den konservativeren (älteren) Stand zeigen.
        $stale_since = $api->stale_since();

        ob_start();
        smf_render_template( 'table', array(
            'table'           => $table,
            'league_name'     => $league_name,
            'show_title'      => $atts['titel'] !== 'false',
            'show_logos'      => $atts['logos'] === 'true',
            'logo_size_class' => self::logo_size_class( $atts['logo_groesse'] ),
            'hervorheben'     => $atts['hervorheben'],
            'own_team_ids'    => SMF_Highlight::get_own_team_ids(),
            'stale_since'     => $stale_since,
        ) );
        return ob_get_clean();
    }

    // ----------------------------------------------------------------
    // [sm_spiele liga_id="123" anzahl="10" team="Eichehorn" modus="alle|vergangen|kommend" namen="true" logo_groesse="mittel" hervorheben=""]
    // ----------------------------------------------------------------
    public function shortcode_spiele( $atts ) {
        $atts = shortcode_atts( array(
            'liga_id'      => get_option( 'smf_default_league_id', '' ),
            'anzahl'       => 0,
            'team'         => '',
            'modus'        => 'alle',
            'titel'        => 'true',
            'logos'        => 'false',
            'namen'        => 'true',
            'logo_groesse' => 'mittel',
            'hervorheben'  => '',
            'live_badge'   => 'true',
        ), $atts, 'sm_spiele' );

        $liga_id = (int) $atts['liga_id'];
        if ( ! $liga_id ) {
            return $this->error( 'Bitte liga_id angeben, z.B. [sm_spiele liga_id="123"]' );
        }

        $api = new SMF_API();

        $schedule = $api->get_schedule( $liga_id );
        if ( is_wp_error( $schedule ) ) {
            return $this->error( $schedule );
        }

        $games = $this->normalize_schedule( $schedule );

        if ( ! empty( $atts['team'] ) ) {
            $games = $api->filter_by_team( $games, $atts['team'] );
        }

        if ( $atts['modus'] === 'vergangen' ) {
            $games = $api->filter_past_games( $games );
            usort( $games, function( $a, $b ) use ( $api ) {
                return $api->parse_game_date( $b ) - $api->parse_game_date( $a );
            } );
        } elseif ( $atts['modus'] === 'kommend' ) {
            $games = $api->filter_upcoming_games( $games );
            usort( $games, function( $a, $b ) use ( $api ) {
                return $api->parse_game_date( $a ) - $api->parse_game_date( $b );
            } );
        }

        $anzahl = (int) $atts['anzahl'];
        if ( $anzahl > 0 ) {
            $games = array_slice( $games, 0, $anzahl );
        }

        $league      = $api->get_league( $liga_id );
        $league_name = is_wp_error( $league ) ? '' : ( isset( $league['name'] ) ? $league['name'] : '' );

        // Erst nach dem letzten API-Aufruf lesen, direkt vor dem Rendern.
        $stale_since = $api->stale_since();

        $show_logos = $atts['logos'] === 'true';
        $show_names = self::resolve_show_names( $atts['namen'], $show_logos );

        ob_start();
        smf_render_template( 'games-list', array(
            'games'             => $games,
            'league_name'       => $league_name,
            'show_title'        => $atts['titel'] !== 'false',
            'modus'             => $atts['modus'],
            'show_logos'        => $show_logos,
            'show_names'        => $show_names,
            'logo_size_class'   => self::logo_size_class( $atts['logo_groesse'] ),
            'hervorheben'       => $atts['hervorheben'],
            'own_team_ids'      => SMF_Highlight::get_own_team_ids(),
            'stale_since'       => $stale_since,
            'show_status_badge' => self::resolve_show_status_badge( $atts['live_badge'] ),
        ) );
        return ob_get_clean();
    }

    // ----------------------------------------------------------------
    // [sm_naechstes_spiel liga_id="123" team="Eichehorn" namen="true" logo_groesse="mittel" hervorheben=""]
    //
    // hervorheben wird akzeptiert (kein Fehlerkasten bei Verwendung), hat
    // hier aber bewusst KEINEN sichtbaren Effekt: Die Karte zeigt ohnehin
    // nur zwei Teams gleichzeitig, "eins davon eigen" hat hier keinen
    // Unterscheidungswert wie in Tabelle/Spielplan (siehe README/
    // Shortcode-Referenz).
    // ----------------------------------------------------------------
    public function shortcode_naechstes_spiel( $atts ) {
        $atts = shortcode_atts( array(
            'liga_id'      => get_option( 'smf_default_league_id', '' ),
            'team'         => '',
            'logos'        => 'true',
            'namen'        => 'true',
            'logo_groesse' => 'mittel',
            'hervorheben'  => '',
            'live_badge'   => 'true',
        ), $atts, 'sm_naechstes_spiel' );

        $liga_id = (int) $atts['liga_id'];
        if ( ! $liga_id ) {
            return $this->error( 'Bitte liga_id angeben.' );
        }

        $api = new SMF_API();

        $schedule = $api->get_schedule( $liga_id );
        if ( is_wp_error( $schedule ) ) {
            return $this->error( $schedule );
        }

        $games = $this->normalize_schedule( $schedule );

        if ( ! empty( $atts['team'] ) ) {
            $games = $api->filter_by_team( $games, $atts['team'] );
        }

        $game = $api->get_next_game( $games );
        if ( ! $game ) {
            return '<div class="smf-notice">Kein kommendes Spiel gefunden.</div>' . self::stale_notice( $api->stale_since() );
        }

        $league      = $api->get_league( $liga_id );
        $league_name = is_wp_error( $league ) ? '' : ( isset( $league['name'] ) ? $league['name'] : '' );

        // Erst nach dem letzten API-Aufruf lesen, direkt vor dem Rendern.
        $stale_since = $api->stale_since();

        $show_logos = $atts['logos'] === 'true';
        $show_names = self::resolve_show_names( $atts['namen'], $show_logos );

        ob_start();
        smf_render_template( 'single-game', array(
            'game'               => $game,
            'league_name'        => $league_name,
            'label'              => smf_label( 'next_game_label', 'Nächstes Spiel' ),
            'running_label'      => smf_label( 'next_game_running_label', 'Läuft gerade' ),
            'show_logos'         => $show_logos,
            'show_names'         => $show_names,
            'logo_size_class'    => self::logo_size_class( $atts['logo_groesse'] ),
            'stale_since'        => $stale_since,
            'show_status_badge'  => self::resolve_show_status_badge( $atts['live_badge'] ),
            'stream_button_kind' => self::resolve_stream_button_kind( $api, $game ),
        ) );
        return ob_get_clean();
    }

    // ----------------------------------------------------------------
    // [sm_letztes_spiel liga_id="123" team="Eichehorn" namen="true" logo_groesse="mittel" hervorheben=""]
    //
    // hervorheben wird akzeptiert, hat aber bewusst keinen sichtbaren
    // Effekt - siehe Begründung bei shortcode_naechstes_spiel() oben.
    // ----------------------------------------------------------------
    public function shortcode_letztes_spiel( $atts ) {
        $atts = shortcode_atts( array(
            'liga_id'      => get_option( 'smf_default_league_id', '' ),
            'team'         => '',
            'logos'        => 'true',
            'namen'        => 'true',
            'logo_groesse' => 'mittel',
            'hervorheben'  => '',
            'live_badge'   => 'true',
        ), $atts, 'sm_letztes_spiel' );

        $liga_id = (int) $atts['liga_id'];
        if ( ! $liga_id ) {
            return $this->error( 'Bitte liga_id angeben.' );
        }

        $api = new SMF_API();

        $schedule = $api->get_schedule( $liga_id );
        if ( is_wp_error( $schedule ) ) {
            return $this->error( $schedule );
        }

        $games = $this->normalize_schedule( $schedule );

        if ( ! empty( $atts['team'] ) ) {
            $games = $api->filter_by_team( $games, $atts['team'] );
        }

        $game = $api->get_last_game( $games );
        if ( ! $game ) {
            return '<div class="smf-notice">Kein gespieltes Spiel gefunden.</div>' . self::stale_notice( $api->stale_since() );
        }

        $league      = $api->get_league( $liga_id );
        $league_name = is_wp_error( $league ) ? '' : ( isset( $league['name'] ) ? $league['name'] : '' );

        // Erst nach dem letzten API-Aufruf lesen, direkt vor dem Rendern.
        $stale_since = $api->stale_since();

        $show_logos = $atts['logos'] === 'true';
        $show_names = self::resolve_show_names( $atts['namen'], $show_logos );

        ob_start();
        smf_render_template( 'single-game', array(
            'game'               => $game,
            'league_name'        => $league_name,
            'label'              => smf_label( 'last_game_label', 'Letztes Spiel' ),
            // Kein running_label: "Letztes Spiel" zeigt konstruktionsbedingt
            // nie ein laufendes Spiel (get_last_game() schließt "running"
            // aus, siehe SMF_API::filter_past_games()) - null statt der
            // Vollständigkeit halber eine Übersetzungszeichenkette pflegen,
            // die nie gerendert wird.
            'running_label'      => null,
            'show_logos'         => $show_logos,
            'show_names'         => $show_names,
            'logo_size_class'    => self::logo_size_class( $atts['logo_groesse'] ),
            'stale_since'        => $stale_since,
            'show_status_badge'  => self::resolve_show_status_badge( $atts['live_badge'] ),
            'stream_button_kind' => self::resolve_stream_button_kind( $api, $game ),
        ) );
        return ob_get_clean();
    }

    // ----------------------------------------------------------------
    // [sm_spiel_duo liga_id="123" team="Eichehorn" reihenfolge="naechstes-zuerst"
    //               logos="true" namen="true" logo_groesse="mittel" hervorheben=""]
    //
    // Kombiniert sm_naechstes_spiel und sm_letztes_spiel in einem
    // gemeinsamen Grid (siehe .smf-spiel-duo in style.css), statt beide
    // Shortcodes einzeln in Theme-Spalten zu setzen - Hauptgrund dafür
    // ist nicht das Layout selbst (das übernehmen die beiden Karten seit
    // der Höhen-/Button-Korrektur in shortcode_naechstes_spiel() /
    // shortcode_letztes_spiel() auch einzeln zuverlässig), sondern ein
    // gemeinsamer, garantiert gleich breiter Container für die
    // Container-Query-Umbruchlogik. Ruft absichtlich die beiden
    // bestehenden Methoden auf statt deren Logik zu duplizieren - beide
    // cachen ohnehin über denselben Spielplan-Request (SMF_Cache
    // dedupliziert per Transient), macht hier also keinen zweiten
    // API-Aufruf.
    //
    // reihenfolge steuert nur die Reihenfolge im Markup (und damit bei
    // Tab-Navigation/Screenreadern) - im Grid ab Container-Breite
    // nebeneinander sind beide ohnehin gleichzeitig sichtbar.
    // ----------------------------------------------------------------
    public function shortcode_spiel_duo( $atts ) {
        $duo_atts = shortcode_atts( array(
            'liga_id'      => get_option( 'smf_default_league_id', '' ),
            'team'         => '',
            'logos'        => 'true',
            'namen'        => 'true',
            'logo_groesse' => 'mittel',
            'hervorheben'  => '',
            'live_badge'   => 'true',
            'reihenfolge'  => 'naechstes-zuerst',
        ), $atts, 'sm_spiel_duo' );

        $liga_id = (int) $duo_atts['liga_id'];
        if ( ! $liga_id ) {
            // Einmalige Prüfung hier statt in beiden Einzel-Shortcodes
            // gleichzeitig - sonst erschiene dieselbe Fehlermeldung doppelt.
            return $this->error( 'Bitte liga_id angeben, z.B. [sm_spiel_duo liga_id="123"]' );
        }

        // reihenfolge ist kein Attribut der Einzel-Shortcodes - vor der
        // Weitergabe entfernen, shortcode_atts() dort würde es sonst
        // stillschweigend ignorieren, was zwar unschädlich, aber unnötig
        // unklar wäre.
        $shared_atts = $duo_atts;
        unset( $shared_atts['reihenfolge'] );

        $naechstes = $this->shortcode_naechstes_spiel( $shared_atts );
        $letztes   = $this->shortcode_letztes_spiel( $shared_atts );

        $reihenfolge = $duo_atts['reihenfolge'] === 'letztes-zuerst' ? 'letztes-zuerst' : 'naechstes-zuerst';
        $cards       = ( $reihenfolge === 'letztes-zuerst' ) ? array( $letztes, $naechstes ) : array( $naechstes, $letztes );

        // Zwei verschachtelte Divs sind Absicht, siehe Kommentar bei
        // .smf-spiel-duo in style.css: der äußere Wrapper liefert nur die
        // Breite für die Container Query, das Grid sitzt auf dem inneren.
        return '<div class="smf smf-spiel-duo"><div class="smf-spiel-duo__grid">' . implode( '', $cards ) . '</div></div>';
    }

    // ----------------------------------------------------------------
    // [sm_vereinsuebersicht verein="hannover" anzahl="4" namen="true" logo_groesse="mittel" hervorheben="false"]
    //
    // hervorheben ist hier standardmäßig false: Anders als bei Tabelle/
    // Spielplan ist in der Vereinsübersicht ohnehin JEDES Spiel eins der
    // eigenen Teams, eine Markierung auf allen Karten wäre Dekoration ohne
    // Informationswert. Explizit auf eine einzelne Team-ID/ein
    // Namensfragment gesetzt, funktioniert es trotzdem (z.B. um auf einer
    // Seite nur für die 1. Mannschaft nach eigenen Teams zu suchen).
    // ----------------------------------------------------------------
    public function shortcode_vereinsuebersicht( $atts ) {
        $atts = shortcode_atts( array(
            'verein'       => '',
            'anzahl'       => 0,
            'namen'        => 'true',
            'logo_groesse' => 'mittel',
            'hervorheben'  => 'false',
            'live_badge'   => 'true',
        ), $atts, 'sm_vereinsuebersicht' );

        $club = SMF_ClubOverview::get_club( $atts['verein'] );

        if ( ! $club ) {
            return $this->error(
                'Kein Verein konfiguriert. Bitte zuerst unter SM Floorball &rarr; Vereine anlegen.'
            );
        }

        // Anzahl: Shortcode-Parameter überschreibt Backend-Einstellung
        $anzahl = (int) $atts['anzahl'];
        if ( $anzahl <= 0 ) {
            $anzahl = (int) ( isset( $club['anzahl'] ) ? $club['anzahl'] : 4 );
        }
        if ( $anzahl <= 0 ) {
            $anzahl = 4;
        }

        $games = SMF_ClubOverview::get_games( $club, $anzahl );

        // "Alle Teams gescheitert": keine Daten vorhanden, weder frisch
        // noch aus der Notreserve - Hinweis statt leerer Spalten.
        if ( is_wp_error( $games['error'] ) ) {
            return $this->error( $games['error'] );
        }

        // Vereinsübersicht hat keinen logos-Schalter (Logos sind hier immer
        // an), daher entfällt die Zwangsregel "logos=false -> namen=true" -
        // resolve_show_names() mit $show_logos=true wertet namen normal aus.
        $show_names = self::resolve_show_names( $atts['namen'], true );

        ob_start();
        smf_render_template( 'club-overview', array(
            'club_name'            => isset( $club['name'] ) ? $club['name'] : '',
            'upcoming'             => $games['upcoming'],
            'played'               => $games['played'],
            'stale_since'          => $games['stale_since'],
            'partial_error_count'  => $games['partial_error_count'],
            'show_names'           => $show_names,
            'logo_size_class'      => self::logo_size_class( $atts['logo_groesse'] ),
            'hervorheben'          => $atts['hervorheben'],
            'own_team_ids'         => SMF_Highlight::get_own_team_ids(),
            'show_status_badge'    => self::resolve_show_status_badge( $atts['live_badge'] ),
        ) );
        return ob_get_clean();
    }

    // ----------------------------------------------------------------
    // [sm_scorer team_id="6754" anzahl="10" spalten="voll|kompakt" namen="voll|abgekuerzt" titel="true" summe="false"]
    // ----------------------------------------------------------------
    public function shortcode_scorer( $atts ) {
        $atts = shortcode_atts( array(
            'team_id' => '',
            'anzahl'  => 0,
            'titel'   => 'true',
            'spalten' => 'voll',
            'namen'   => '',
            'summe'   => 'false',
        ), $atts, 'sm_scorer' );

        $team_id = (int) $atts['team_id'];
        if ( ! $team_id ) {
            return $this->error( 'Bitte team_id angeben, z.B. [sm_scorer team_id="6754"]' );
        }

        // Enums strikt: unbekannte Werte fallen auf den Standard zurück,
        // statt sie unverändert ins Template durchzureichen.
        $spalten = ( $atts['spalten'] === 'kompakt' ) ? 'kompakt' : 'voll';
        $namen   = in_array( $atts['namen'], array( 'voll', 'abgekuerzt' ), true ) ? $atts['namen'] : '';

        // Globaler Schalter "Personennamen anzeigen" ist eine
        // Vereinsentscheidung und per Shortcode nicht übersteuerbar - ist
        // er aus, wird kein API-Request für die Liste ausgelöst.
        $names_disabled = ! SMF_Scorer::player_names_enabled();

        $result = array( 'status' => 'empty', 'error' => '', 'rows' => array(), 'columns' => array(), 'totals' => null );
        if ( ! $names_disabled ) {
            $result = SMF_Scorer::get( $team_id, array(
                'anzahl' => (int) $atts['anzahl'],
            ) );

            if ( $result['status'] === 'error' ) {
                return $this->error( $result['error'] ); // WP_Error, siehe SMF_Scorer::get()
            }
        }

        ob_start();
        smf_render_template( 'scorer', array(
            'result'         => $result,
            'show_title'     => $atts['titel'] !== 'false',
            'namen'          => $namen,
            'spalten'        => $spalten,
            'show_totals'    => $atts['summe'] === 'true',
            'names_disabled' => $names_disabled,
        ) );
        return ob_get_clean();
    }

    // ----------------------------------------------------------------
    // [sm_livestream team_id="6754" nach_spielende="aufzeichnung|ausblenden"
    //                titel="" hinweis="false"]
    //
    // Zeigt den Livestream/die Aufzeichnung des für dieses Team gerade
    // relevantesten Spiels als großen Player (v1.9.0, Teil 2) - siehe
    // SMF_API::get_livestream_target_game() für die Auswahl (laufend, sonst
    // nächstes, sonst optional das zuletzt gespielte). Nutzt
    // teams/{id}/matches statt liga_id, wie sm_scorer - liefert alle
    // Wettbewerbe des Teams in einem Request, kein Rätselraten nach der
    // richtigen Liga-ID.
    // ----------------------------------------------------------------
    public function shortcode_livestream( $atts ) {
        $atts = shortcode_atts( array(
            'team_id'       => '',
            'nach_spielende' => 'aufzeichnung',
            'titel'         => '',
            'hinweis'       => 'false',
        ), $atts, 'sm_livestream' );

        $team_id = (int) $atts['team_id'];
        if ( ! $team_id ) {
            return $this->error( 'Bitte team_id angeben, z.B. [sm_livestream team_id="6754"]' );
        }

        $api      = new SMF_API();
        $response = $api->get_team_matches( $team_id );
        if ( is_wp_error( $response ) ) {
            return $this->error( $response );
        }

        $games  = ( isset( $response['matches'] ) && is_array( $response['matches'] ) ) ? $response['matches'] : array();
        $include_ended_fallback = $atts['nach_spielende'] !== 'ausblenden';
        $game   = $api->get_livestream_target_game( $games, $include_ended_fallback );

        // Erst nach dem letzten API-Aufruf lesen, direkt vor dem Rendern.
        $stale_since = $api->stale_since();

        $stream        = null;
        $section_title = '';

        if ( $game ) {
            $game_id = isset( $game['game_id'] ) ? (int) $game['game_id'] : 0;
            $status  = SMF_Game_Status::status( $game );
            $mode    = get_option( 'smf_stream_embed_mode', 'nur_link' );

            if ( $game_id && $mode !== 'aus' ) {
                $stream_fields = $api->get_game_stream_fields( $game_id, $status );
                $picked        = SMF_Stream::pick_link( $stream_fields, $status );

                if ( $picked ) {
                    $parsed = SMF_Stream::parse_url( $picked['url'] );
                    if ( $parsed['valid'] ) {
                        $stream = array(
                            'kind'      => $picked['kind'],
                            'parsed'    => $parsed,
                            'embed_url' => ( $mode === 'zwei_klick' && $parsed['embeddable'] )
                                ? SMF_Stream::build_embed_url( $parsed, SMF_Stream::embed_parent_host() )
                                : '',
                        );
                    }
                }
            }

            if ( $stream ) {
                $section_title = ( $stream['kind'] === 'vod' )
                    ? smf_label( 'stream_section_title_vod', 'Aufzeichnung' )
                    : smf_label( 'stream_section_title_live', 'Livestream' );
            }
        }

        // "Keine leere Box": ohne Stream (kein passendes Spiel ODER Spiel
        // ohne nutzbaren Link) standardmäßig gar keine Ausgabe, per
        // hinweis="true" ein dezenter Text statt einer leeren Fläche.
        if ( ! $stream ) {
            if ( $atts['hinweis'] !== 'true' ) {
                return '';
            }
            return '<div class="smf-notice">' . esc_html( smf_label( 'livestream_no_stream_notice', 'Aktuell kein Livestream verfügbar.' ) ) . '</div>'
                . self::stale_notice( $stale_since );
        }

        ob_start();
        smf_render_template( 'livestream', array(
            'game'           => $game,
            'stream'         => $stream,
            'section_title'  => $section_title,
            'titel'          => $atts['titel'],
            'stale_since'    => $stale_since,
        ) );
        return ob_get_clean();
    }

    // ----------------------------------------------------------------
    // Hilfsfunktionen
    // ----------------------------------------------------------------

    /**
     * Attribut logo_groesse in eine CSS-Modifier-Klasse übersetzen (siehe
     * assets/css/style.css, --smf-logo-scale). Ungültige/leere Werte und
     * "mittel" selbst fallen still auf '' zurück (= Standardgröße, keine
     * Modifier-Klasse nötig) - kein Fehlerkasten bei einem Tippfehler im
     * Shortcode.
     *
     * @param string $value
     * @return string Leerstring oder "smf--logo-*"
     */
    private static function logo_size_class( $value ) {
        $map = array(
            'klein'      => 'smf--logo-klein',
            'gross'      => 'smf--logo-gross',
            'sehr_gross' => 'smf--logo-sehr-gross',
        );
        return isset( $map[ $value ] ) ? $map[ $value ] : '';
    }

    /**
     * Attribut namen (true/false) auswerten, inklusive der Zwangsregel
     * "logos=false -> namen=true": Ohne Logos wäre eine Begegnung mit
     * ausgeblendeten Namen komplett leer, das darf ein Shortcode-Attribut
     * nicht erzeugen können. Ergänzt die pro-Team-Regel in den Templates
     * (Name bleibt sichtbar, wenn ausgerechnet für dieses eine Team kein
     * Logo vorliegt), ersetzt sie nicht.
     *
     * Kein dritter Wert (z.B. ein API-Kurzname als Alternative zum vollen
     * Namen) - teams/{id}/matches und leagues/{id}/schedule.json liefern
     * keinen Kurznamen. Das existierende Feld "short_name" bei
     * game_operations/{id}/clubs ist zwar vorhanden, aber pro Verein und
     * nicht pro Team vergeben (z.B. bei TV Eiche Horn Bremen an allen
     * zwölf Mannschaften identisch "TVE") und kann daher nicht zwischen
     * "TV Eiche Horn 1" und "TV Eiche Horn 2" unterscheiden - der Fall,
     * den dieses Attribut eigentlich lösen soll.
     *
     * @param string $namen_att  Rohwert des namen-Attributs
     * @param bool   $show_logos Aufgelöster logos-Wert desselben Shortcodes
     * @return bool
     */
    private static function resolve_show_names( $namen_att, $show_logos ) {
        if ( ! $show_logos ) {
            return true;
        }
        return $namen_att !== 'false';
    }

    /**
     * Ob und als was (Livestream/Aufzeichnung) eine Karte den kleinen
     * Stream-Button zeigt (siehe single-game.php, Etappe D). Nur bei
     * Option smf_stream_embed_mode != "aus", nur bei einem tatsächlich
     * https-gültigen Link (SMF_Stream::parse_url()) - eine kaputte oder
     * unsichere URL soll erst gar keinen Button erzeugen, statt im Modal
     * dann doch nichts Anklickbares zu zeigen.
     *
     * @param SMF_API $api
     * @param array   $game
     * @return 'live'|'vod'|null
     */
    private static function resolve_stream_button_kind( SMF_API $api, array $game ) {
        if ( get_option( 'smf_stream_embed_mode', 'nur_link' ) === 'aus' ) {
            return null;
        }

        $game_id = isset( $game['game_id'] ) ? (int) $game['game_id'] : 0;
        if ( ! $game_id ) {
            return null;
        }

        $status        = SMF_Game_Status::status( $game );
        $stream_fields = $api->get_game_stream_fields( $game_id, $status );
        $picked        = SMF_Stream::pick_link( $stream_fields, $status );
        if ( ! $picked ) {
            return null;
        }

        $parsed = SMF_Stream::parse_url( $picked['url'] );
        return $parsed['valid'] ? $picked['kind'] : null;
    }

    /**
     * Attribut live_badge (true/false) mit der globalen Option
     * smf_live_badge_enabled kombinieren - beide müssen zustimmen, damit ein
     * Template das LIVE-/Abgesagt-Abzeichen überhaupt in Betracht zieht.
     * Die eigentliche Entscheidung, ob im Einzelfall ein Abzeichen gezeigt
     * wird (Status running/canceled, Staleness-Gate), trifft erst das
     * Template selbst anhand von SMF_Game_Status::status().
     *
     * @param string $live_badge_att Rohwert des live_badge-Attributs
     * @return bool
     */
    private static function resolve_show_status_badge( $live_badge_att ) {
        if ( $live_badge_att === 'false' ) {
            return false;
        }
        return get_option( 'smf_live_badge_enabled', 'an' ) === 'an';
    }

    /**
     * Spielplan normalisieren (API kann verschiedene Strukturen liefern)
     *
     * @param array $schedule
     * @return array
     */
    private function normalize_schedule( $schedule ) {
        if ( ! is_array( $schedule ) )                                  return array();
        if ( isset( $schedule['games'] ) && is_array( $schedule['games'] ) ) return $schedule['games'];
        if ( isset( $schedule['data'] )  && is_array( $schedule['data'] ) )  return $schedule['data'];
        // Direkte JSON-Array-Antwort: erstes Element mit Index 0 vorhanden
        if ( isset( $schedule[0] ) )                                    return array_values( $schedule );
        return array();
    }

    /**
     * Fehlerausgabe, rollengetrennt: Administrator:innen sehen die
     * technischen Details (HTTP-Code, URL, Breaker-/Rate-Limit-Status aus
     * den WP_Error-Daten), alle anderen einen freundlichen, allgemeinen
     * Hinweis ohne technische Interna.
     *
     * @param string|WP_Error $error Einfache Validierungsmeldung (String,
     *                                z.B. fehlende liga_id) oder ein
     *                                WP_Error aus SMF_API (API-/Ausfall-Fehler).
     * @return string
     */
    private function error( $error ) {
        if ( $error instanceof WP_Error ) {
            if ( ! current_user_can( 'manage_options' ) ) {
                return '<div class="smf-notice">' . esc_html( self::error_message_for_current_user( $error ) ) . '</div>';
            }
            return '<div class="smf-error"><strong>Saisonmanager Fehler (nur für Administrator:innen sichtbar):</strong> '
                . esc_html( self::error_message_for_current_user( $error ) ) . '</div>';
        }

        // Einfache Validierungsmeldung (Shortcode-Attribute) - unkritisch,
        // enthält keine Serverdetails, darf also für alle sichtbar bleiben.
        return '<div class="smf-error"><strong>Saisonmanager Fehler:</strong> ' . esc_html( $error ) . '</div>';
    }

    /**
     * Baut aus einem WP_Error die für die aktuelle Rolle passende
     * Fehlermeldung. Wird auch außerhalb dieser Klasse genutzt (AJAX-Route
     * smf_ajax_game_detail() im Hauptplugin), deshalb public/static.
     *
     * @param WP_Error $error
     * @return string
     */
    public static function error_message_for_current_user( WP_Error $error ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            return smf_label(
                'api_unavailable_visitor',
                'Aktuell sind keine aktuellen Daten verfügbar. Bitte versuche es in Kürze erneut.'
            );
        }

        $message = $error->get_error_message();
        $data    = $error->get_error_data();

        $parts = array();
        if ( is_array( $data ) ) {
            if ( ! empty( $data['http_code'] ) ) $parts[] = 'HTTP ' . $data['http_code'];
            if ( ! empty( $data['url'] ) )       $parts[] = $data['url'];
            if ( ! empty( $data['detail'] ) )    $parts[] = $data['detail'];
        }

        return $parts ? ( $message . ' (' . implode( ' · ', $parts ) . ')' ) : $message;
    }

    /**
     * Dezenter Hinweis auf den Stand der Notreserve-Daten, gemeinsam
     * genutzt von den Templates (table, games-list, single-game,
     * club-overview) und den Inline-Ausgaben oben (kein kommendes/
     * gespieltes Spiel gefunden) - damit die Formatierung nicht an
     * mehreren Stellen dupliziert wird.
     *
     * @param int|null $timestamp Rückgabe von SMF_API::stale_since()
     * @return string Leerstring, wenn $timestamp null ist
     */
    public static function stale_notice( $timestamp ) {
        if ( ! $timestamp ) {
            return '';
        }

        $date = date_i18n( 'l, d.m.Y', $timestamp ) . ', ' . date_i18n( 'H:i', $timestamp ) . ' ' . smf_label( 'time_suffix', 'Uhr' );

        $text = sprintf(
            /* translators: %s: Datum/Uhrzeit des letzten erfolgreichen Datenabrufs */
            smf_label( 'stale_notice', 'Stand: %s – der Verbandsserver liefert gerade keine aktuellen Daten.' ),
            $date
        );

        return '<p class="smf-notice smf-notice--stale">' . esc_html( $text ) . '</p>';
    }
}
