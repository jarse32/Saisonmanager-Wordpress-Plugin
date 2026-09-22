<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Livestream-/Aufzeichnungs-Einbettung (Etappe D): welcher Link (live_stream_link
 * vs. vod_link) je Spielstatus gilt, und ob/wie sich eine gegebene URL sicher
 * einbetten lässt.
 *
 * Nur zwei Hosts sind hinterlegt - siehe Schritt-0-Erhebung über
 * /live_streams (21 Spiele, Stichprobe 21.09.2026): ausschließlich YouTube
 * (www.youtube.com/youtube.com, 19 von 21 Vorkommen) und Twitch
 * (www.twitch.tv, 2 von 21) wurden von Vereinen tatsächlich eingetragen,
 * keine weiteren Hosts. team_id 6754 (TV Eiche Horn Bremen) nutzt
 * ausschließlich YouTube. m.youtube.com ist zusätzlich aufgenommen (nicht
 * in der Stichprobe beobachtet, aber derselbe Anbieter/dieselbe
 * Mobil-Domain - kein zusätzliches Vertrauen, kein neuer Anbieter).
 *
 * Bewusst eigenständig von SMF_API: reine, ohne WordPress testbare Logik
 * (parse_url()/pick_link()/build_embed_url() nutzen keine WP-Funktionen
 * außer wp_parse_url(), das in tests/test-stream-url-parsing.php gestubbt
 * wird) - analog zu SMF_Game_Status.
 */
class SMF_Stream {

    /** @var string[] */
    const YOUTUBE_HOSTS = array( 'youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be' );

    /** @var string[] */
    const TWITCH_HOSTS = array( 'twitch.tv', 'www.twitch.tv' );

    /**
     * Welcher Link gilt, und als was ('live' vs. 'vod') - steuert auch die
     * Beschriftung ("Livestream" vs. "Aufzeichnung") an den Aufrufstellen.
     *
     * Vor und während des Spiels ausschließlich live_stream_link. Nach
     * Spielende vod_link, falls vorhanden, sonst live_stream_link als
     * Fallback (z.B. wenn der Verein nie eine separate Aufzeichnung
     * hinterlegt hat, der Live-Link selbst aber weiterhin funktioniert -
     * YouTube wandelt eine beendete Live-Übertragung z.B. automatisch in
     * ein normales Video an derselben URL um).
     *
     * "canceled" liefert bewusst nie einen Link: ein für ein abgesagtes
     * Spiel eingetragener Link (z.B. vorab gesetzt, bevor die Absage kam)
     * wäre irreführend, wenn er neben einem "Abgesagt"-Hinweis erscheint.
     *
     * @param array{live_stream_link?: string|null, vod_link?: string|null} $stream_fields
     * @param string                                                        $status Rückgabe von SMF_Game_Status::status()
     * @return array{url: string, kind: 'live'|'vod'}|null
     */
    public static function pick_link( array $stream_fields, string $status ) {
        if ( $status === 'canceled' ) {
            return null;
        }

        $live = ! empty( $stream_fields['live_stream_link'] ) ? (string) $stream_fields['live_stream_link'] : '';
        $vod  = ! empty( $stream_fields['vod_link'] )         ? (string) $stream_fields['vod_link']         : '';

        if ( $status === 'ended' ) {
            if ( $vod !== '' )  return array( 'url' => $vod,  'kind' => 'vod' );
            if ( $live !== '' ) return array( 'url' => $live, 'kind' => 'live' );
            return null;
        }

        // upcoming/running
        return ( $live !== '' ) ? array( 'url' => $live, 'kind' => 'live' ) : null;
    }

    /**
     * Beschriftung ("Livestream" vs. "Aufzeichnung") an den Aufrufstellen -
     * bewusst NUR aus dem Spielstatus, NICHT aus pick_link()['kind']: bei
     * einem beendeten Spiel ohne vod_link liefert pick_link() den oben
     * beschriebenen Fallback auf live_stream_link mit kind='live' - das
     * Video selbst ist trotzdem eine Aufzeichnung, kein laufender Stream
     * mehr, nur weil es zufällig aus demselben Feld stammt wie vor dem
     * Anstoß. "Live" gilt ausschließlich vor/während des Spiels (v1.9.1,
     * vorher zeigte genau dieser Fallback-Fall fälschlich "Livestream").
     *
     * @param string $status Rückgabe von SMF_Game_Status::status()
     * @return 'live'|'vod'
     */
    public static function display_kind( $status ) {
        return ( $status === 'ended' ) ? 'vod' : 'live';
    }

    /**
     * URL robust auswerten: liefert IMMER ein vollständiges Array zurück
     * (nie null), 'valid' unterscheidet nutzbar/unnutzbar. Eine unnutzbare
     * URL (leer, kaputt, falsches Schema) darf an keiner Aufrufstelle in
     * einem href oder iframe landen - Aufrufer müssen 'valid' prüfen.
     *
     * Sicherheitsregeln:
     * - Nur https. Kein http, kein javascript:/data:/... - selbst als
     *   reiner Link nicht (siehe Umsetzungsauftrag Etappe D).
     * - Host-Vergleich exakt (in_array gegen die Konstanten oben), NIE
     *   per strpos/Teilstring - sonst wäre z.B.
     *   "https://youtube.com.evil.example/..." (Host laut wp_parse_url()
     *   exakt "youtube.com.evil.example") fälschlich als YouTube erkannt.
     *
     * 'embeddable' ist true nur, wenn zusätzlich eine konkrete Video-ID aus
     * dem Pfad extrahiert werden konnte - ein Kanal-Link (z.B.
     * twitch.tv/<channel> oder youtube.com/@handle) bleibt 'valid' (taugt
     * als normaler Link), aber nicht 'embeddable'.
     *
     * @param string $url
     * @return array{valid: bool, link_url: string, host: string, provider: string, provider_label: string, embeddable: bool, video_id: string}
     */
    public static function parse_url( $url ) {
        $result = array(
            'valid'          => false,
            'link_url'       => '',
            'host'           => '',
            'provider'       => '',
            'provider_label' => '',
            'embeddable'     => false,
            'video_id'       => '',
        );

        $url = is_string( $url ) ? trim( $url ) : '';
        if ( $url === '' ) {
            return $result;
        }

        $parsed = wp_parse_url( $url );
        if ( ! is_array( $parsed ) || empty( $parsed['host'] ) || empty( $parsed['scheme'] ) ) {
            return $result;
        }
        if ( strtolower( $parsed['scheme'] ) !== 'https' ) {
            return $result;
        }

        $host  = strtolower( $parsed['host'] );
        $path  = isset( $parsed['path'] )  ? $parsed['path']  : '';
        $query = isset( $parsed['query'] ) ? $parsed['query'] : '';

        $result['valid']    = true;
        $result['link_url'] = $url;
        $result['host']     = $host;

        if ( in_array( $host, self::YOUTUBE_HOSTS, true ) ) {
            $result['provider']       = 'youtube';
            $result['provider_label'] = 'YouTube';

            $id = self::extract_youtube_id( $host, $path, $query );
            if ( $id !== '' ) {
                $result['embeddable'] = true;
                $result['video_id']   = $id;
            }
        } elseif ( in_array( $host, self::TWITCH_HOSTS, true ) ) {
            $result['provider']       = 'twitch';
            $result['provider_label'] = 'Twitch';

            $id = self::extract_twitch_vod_id( $path );
            if ( $id !== '' ) {
                $result['embeddable'] = true;
                $result['video_id']   = $id;
            }
        }
        // Anderer https-Host: bleibt 'valid' (normaler Link erlaubt),
        // 'provider' bleibt leer - Aufrufer fallen dafür auf den reinen
        // Hostnamen als Anbieter-Bezeichnung zurück.

        return $result;
    }

    /**
     * @param string $host  Bereits lowercase (siehe parse_url())
     * @param string $path
     * @param string $query
     * @return string Leerstring, wenn keine konkrete Video-ID erkennbar ist
     *                (z.B. Kanal-URL statt Einzelvideo)
     */
    private static function extract_youtube_id( $host, $path, $query ) {
        $path = '/' . ltrim( $path, '/' );

        if ( $host === 'youtu.be' ) {
            $id = trim( $path, '/' );
            return self::looks_like_youtube_id( $id ) ? $id : '';
        }

        if ( strpos( $path, '/watch' ) === 0 ) {
            parse_str( $query, $q );
            $id = isset( $q['v'] ) ? $q['v'] : '';
            return self::looks_like_youtube_id( $id ) ? $id : '';
        }

        // /live/<id> (von /live_streams so beobachtet) und /embed/<id>
        // (bereits Embed-Form, z.B. wenn ein Verein die URL schon als
        // Embed-Link eingetragen hat) sind strukturgleich: zweites
        // Pfadsegment ist die ID.
        if ( strpos( $path, '/live/' ) === 0 || strpos( $path, '/embed/' ) === 0 ) {
            $segments = explode( '/', trim( $path, '/' ) );
            $id       = isset( $segments[1] ) ? $segments[1] : '';
            return self::looks_like_youtube_id( $id ) ? $id : '';
        }

        // Kanal-/Playlist-/Sonstige URLs (/@handle, /channel/…, /c/…,
        // /user/…, /playlist, …) - kein konkretes Video, nicht einbetten.
        return '';
    }

    /**
     * @param string $id
     * @return bool
     */
    private static function looks_like_youtube_id( $id ) {
        return is_string( $id ) && preg_match( '/^[A-Za-z0-9_-]{6,15}$/', $id ) === 1;
    }

    /**
     * Nur /videos/<id> (konkretes VOD) gilt als einbettbar. Ein einzelnes
     * Pfadsegment (twitch.tv/<channel>) ist der Kanal-Live-Link - laut
     * Schritt-0-Erhebung die tatsächlich beobachtete Twitch-Form (2 von 2
     * Twitch-Vorkommen waren Kanal-Links, kein VOD beobachtet) - bewusst
     * NICHT einbetten (siehe Umsetzungsauftrag: "Kanal-Links, die kein
     * konkretes Video bezeichnen, nicht einbetten, sondern als Link").
     *
     * @param string $path
     * @return string
     */
    private static function extract_twitch_vod_id( $path ) {
        $segments = array_values( array_filter( explode( '/', $path ), 'strlen' ) );

        if ( count( $segments ) >= 2 && $segments[0] === 'videos' && preg_match( '/^[0-9]+$/', $segments[1] ) === 1 ) {
            return $segments[1];
        }

        return '';
    }

    /**
     * Embed-URL aus einem bereits erfolgreich geparsten, einbettbaren
     * Ergebnis von parse_url() bauen. Liefert '', wenn nicht einbettbar
     * oder (Twitch) kein gültiger $parent_host übergeben wurde.
     *
     * YouTube: youtube-nocookie.com-Variante (siehe README/Umsetzungsauftrag) -
     * setzt vor dem ersten Klick auf den Player keinen Tracking-Cookie.
     *
     * Twitch: player.twitch.tv verlangt zwingend einen "parent"-Parameter
     * mit dem exakten Hostnamen der einbettenden Seite, sonst verweigert
     * der Player das Laden - siehe embed_parent_host().
     *
     * @param array{provider: string, embeddable: bool, video_id: string} $parsed Rückgabe von parse_url()
     * @param string                                                       $parent_host Nur für Twitch relevant, siehe embed_parent_host()
     * @return string Leerstring, wenn keine Embed-URL gebaut werden kann
     */
    public static function build_embed_url( array $parsed, $parent_host = '' ) {
        if ( empty( $parsed['embeddable'] ) || empty( $parsed['video_id'] ) ) {
            return '';
        }

        if ( $parsed['provider'] === 'youtube' ) {
            return 'https://www.youtube-nocookie.com/embed/' . rawurlencode( $parsed['video_id'] );
        }

        if ( $parsed['provider'] === 'twitch' ) {
            $parent_host = self::sanitize_parent_host( $parent_host );
            if ( $parent_host === '' ) {
                return '';
            }
            return 'https://player.twitch.tv/?video=' . rawurlencode( $parsed['video_id'] ) . '&parent=' . rawurlencode( $parent_host );
        }

        return '';
    }

    /**
     * @param string $host
     * @return string Leerstring, wenn $host keinem plausiblen Hostnamen entspricht
     */
    private static function sanitize_parent_host( $host ) {
        $host = is_string( $host ) ? strtolower( trim( $host ) ) : '';
        return ( $host !== '' && preg_match( '/^[a-z0-9.-]+$/', $host ) === 1 ) ? $host : '';
    }

    /**
     * Hostname der eigenen WordPress-Seite für den Twitch-"parent"-Parameter
     * (siehe build_embed_url()) - dynamisch ermittelt statt hartkodiert,
     * damit das Plugin auf jeder Installation (Live-Domain, Staging,
     * lokale Entwicklung) funktioniert, ohne dass Vereine selbst einen
     * Domainnamen eintragen müssen.
     *
     * @return string
     */
    public static function embed_parent_host() {
        $host = wp_parse_url( home_url(), PHP_URL_HOST );
        return is_string( $host ) ? $host : '';
    }
}
