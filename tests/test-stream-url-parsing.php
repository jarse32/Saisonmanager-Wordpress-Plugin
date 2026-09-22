<?php
/**
 * Eigenständiger PHP-Test für SMF_Stream (Livestream-/Aufzeichnungs-
 * Einbettung, Etappe D) - läuft ohne WordPress, ohne Composer, ohne
 * Build-Step:
 *
 *     php tests/test-stream-url-parsing.php
 *
 * Deckt genau die in Schritt 0 identifizierten Risiken ab: Kanal-Links
 * (kein konkretes Video), http statt https, fremde Hosts, Lookalike-Hosts
 * (z.B. "youtube.com.evil.example" - Host laut wp_parse_url() exakt dieser
 * String, nicht "youtube.com"), javascript:-Schema, leere/null-Werte, und
 * die Link-Wahl (live_stream_link vs. vod_link) je Spielstatus.
 *
 * Exit-Code 0 = alle Assertions bestanden, 1 = mindestens eine fehlgeschlagen.
 */

// --- Minimale WordPress-Stubs, nur was SMF_Stream tatsächlich aufruft ---

define( 'ABSPATH', '/tmp/' );

/**
 * wp_parse_url() ist im echten WordPress ein parse_url()-Polyfill mit
 * Normalisierungen für ältere PHP-Versionen - für diesen Test genügt eine
 * dünne Weiterleitung an PHP's natives parse_url(), das SMF_Stream
 * tatsächlich benötigte Verhalten (scheme/host/path/query extrahieren)
 * ist identisch.
 */
function wp_parse_url( $url, $component = -1 ) {
    return ( $component === -1 ) ? parse_url( $url ) : parse_url( $url, $component );
}

$GLOBALS['__smf_test_home_url'] = 'https://verein-beispiel.de';
function home_url() {
    return $GLOBALS['__smf_test_home_url'];
}

require __DIR__ . '/../includes/class-stream.php';

$failures = 0;
$count    = 0;

function smf_test_assert( string $label, $expected, $actual ): void {
    global $failures, $count;
    $count++;
    $ok = $expected === $actual;
    if ( ! $ok ) {
        $failures++;
    }
    echo ( $ok ? 'OK  ' : 'FAIL' ) . " $label\n";
    if ( ! $ok ) {
        echo '     erwartet: ' . var_export( $expected, true ) . "\n";
        echo '     erhalten: ' . var_export( $actual, true ) . "\n";
    }
}

// ====================================================================
// parse_url() - gültige Links je Host
// ====================================================================

$p = SMF_Stream::parse_url( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&pp=xyz' );
smf_test_assert( 'YouTube /watch?v=: valid',      true,        $p['valid'] );
smf_test_assert( 'YouTube /watch?v=: embeddable', true,        $p['embeddable'] );
smf_test_assert( 'YouTube /watch?v=: provider',   'youtube',   $p['provider'] );
smf_test_assert( 'YouTube /watch?v=: video_id',   'dQw4w9WgXcQ', $p['video_id'] );

$p = SMF_Stream::parse_url( 'https://youtube.com/live/AbCdEfGhIjK' );
smf_test_assert( 'YouTube /live/: valid',      true, $p['valid'] );
smf_test_assert( 'YouTube /live/: embeddable', true, $p['embeddable'] );
smf_test_assert( 'YouTube /live/: video_id',   'AbCdEfGhIjK', $p['video_id'] );

$p = SMF_Stream::parse_url( 'https://youtu.be/AbCdEfGhIjK' );
smf_test_assert( 'youtu.be Kurzlink: valid',      true, $p['valid'] );
smf_test_assert( 'youtu.be Kurzlink: embeddable', true, $p['embeddable'] );
smf_test_assert( 'youtu.be Kurzlink: video_id',   'AbCdEfGhIjK', $p['video_id'] );

$p = SMF_Stream::parse_url( 'https://m.youtube.com/watch?v=AbCdEfGhIjK' );
smf_test_assert( 'm.youtube.com: valid',      true, $p['valid'] );
smf_test_assert( 'm.youtube.com: embeddable', true, $p['embeddable'] );

$p = SMF_Stream::parse_url( 'https://www.twitch.tv/videos/1234567890' );
smf_test_assert( 'Twitch /videos/<id>: valid',      true,   $p['valid'] );
smf_test_assert( 'Twitch /videos/<id>: embeddable', true,   $p['embeddable'] );
smf_test_assert( 'Twitch /videos/<id>: provider',   'twitch', $p['provider'] );
smf_test_assert( 'Twitch /videos/<id>: video_id',   '1234567890', $p['video_id'] );

// ====================================================================
// parse_url() - Kanal-Links (kein konkretes Video, nicht einbetten)
// ====================================================================

$p = SMF_Stream::parse_url( 'https://www.twitch.tv/eichehornfloorball' );
smf_test_assert( 'Twitch Kanal-Link: valid',      true,  $p['valid'] );
smf_test_assert( 'Twitch Kanal-Link: provider',   'twitch', $p['provider'] );
smf_test_assert( 'Twitch Kanal-Link: embeddable', false, $p['embeddable'] );
smf_test_assert( 'Twitch Kanal-Link: video_id leer', '', $p['video_id'] );

$p = SMF_Stream::parse_url( 'https://www.youtube.com/@eichehornfloorball/live' );
smf_test_assert( 'YouTube Kanal/live-Link: valid',      true,  $p['valid'] );
smf_test_assert( 'YouTube Kanal/live-Link: embeddable', false, $p['embeddable'] );

$p = SMF_Stream::parse_url( 'https://www.youtube.com/channel/UC1234567890' );
smf_test_assert( 'YouTube /channel/-Link: embeddable', false, $p['embeddable'] );

// ====================================================================
// parse_url() - http statt https, fremde Hosts, Lookalikes, javascript:
// ====================================================================

$p = SMF_Stream::parse_url( 'http://www.youtube.com/watch?v=dQw4w9WgXcQ' );
smf_test_assert( 'http statt https: valid = false', false, $p['valid'] );

$p = SMF_Stream::parse_url( 'https://vimeo.com/123456789' );
smf_test_assert( 'Fremder Host (vimeo): valid = true (bleibt normaler Link)', true, $p['valid'] );
smf_test_assert( 'Fremder Host (vimeo): kein Provider erkannt', '', $p['provider'] );
smf_test_assert( 'Fremder Host (vimeo): nicht einbettbar', false, $p['embeddable'] );
smf_test_assert( 'Fremder Host (vimeo): link_url gesetzt', 'https://vimeo.com/123456789', $p['link_url'] );

$p = SMF_Stream::parse_url( 'https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ' );
smf_test_assert( 'Lookalike-Host: kein Provider erkannt', '', $p['provider'] );
smf_test_assert( 'Lookalike-Host: nicht einbettbar', false, $p['embeddable'] );
smf_test_assert( 'Lookalike-Host: bleibt aber ein gültiger https-Link', true, $p['valid'] );

$p = SMF_Stream::parse_url( 'https://evil.example/x?u=https://www.youtube.com/watch?v=dQw4w9WgXcQ' );
smf_test_assert( 'Fremder Host mit youtube-URL im Query: kein Provider erkannt', '', $p['provider'] );

$p = SMF_Stream::parse_url( 'javascript:alert(1)' );
smf_test_assert( 'javascript:-Schema: valid = false', false, $p['valid'] );
smf_test_assert( 'javascript:-Schema: link_url leer', '', $p['link_url'] );

$p = SMF_Stream::parse_url( 'data:text/html,<script>alert(1)</script>' );
smf_test_assert( 'data:-Schema: valid = false', false, $p['valid'] );

// ====================================================================
// parse_url() - leere/null-Werte, kaputte IDs
// ====================================================================

$p = SMF_Stream::parse_url( '' );
smf_test_assert( 'Leerstring: valid = false', false, $p['valid'] );

$p = SMF_Stream::parse_url( null );
smf_test_assert( 'null: valid = false', false, $p['valid'] );

$p = SMF_Stream::parse_url( '   ' );
smf_test_assert( 'Nur Leerzeichen: valid = false', false, $p['valid'] );

$p = SMF_Stream::parse_url( 'https://www.youtube.com/watch' );
smf_test_assert( '/watch ohne v=: nicht einbettbar', false, $p['embeddable'] );

$p = SMF_Stream::parse_url( 'https://www.youtube.com/watch?v=' );
smf_test_assert( '/watch?v= (leer): nicht einbettbar', false, $p['embeddable'] );

$p = SMF_Stream::parse_url( 'https://www.youtube.com/watch?v=../../etc' );
smf_test_assert( '/watch?v= mit Sonderzeichen: nicht einbettbar', false, $p['embeddable'] );

// ====================================================================
// pick_link() - Link-Wahl je Spielstatus
// ====================================================================

$fields_live_only = array( 'live_stream_link' => 'https://www.youtube.com/watch?v=aaaaaaaaaaa', 'vod_link' => null );
$fields_both       = array( 'live_stream_link' => 'https://www.youtube.com/watch?v=live0000000', 'vod_link' => 'https://www.youtube.com/watch?v=vod00000000' );
$fields_vod_only    = array( 'live_stream_link' => null, 'vod_link' => 'https://www.youtube.com/watch?v=vod00000000' );
$fields_none        = array( 'live_stream_link' => null, 'vod_link' => null );

smf_test_assert( 'pick_link upcoming, nur live: kind=live', 'live', SMF_Stream::pick_link( $fields_live_only, 'upcoming' )['kind'] );
smf_test_assert( 'pick_link running, nur live: kind=live',  'live', SMF_Stream::pick_link( $fields_live_only, 'running' )['kind'] );
smf_test_assert( 'pick_link upcoming, beide vorhanden: nimmt live', 'live', SMF_Stream::pick_link( $fields_both, 'upcoming' )['kind'] );
smf_test_assert( 'pick_link ended, beide vorhanden: nimmt vod',     'vod',  SMF_Stream::pick_link( $fields_both, 'ended' )['kind'] );
smf_test_assert( 'pick_link ended, nur live vorhanden: Fallback auf live', 'live', SMF_Stream::pick_link( $fields_live_only, 'ended' )['kind'] );
smf_test_assert( 'pick_link ended, nur vod vorhanden: kind=vod', 'vod', SMF_Stream::pick_link( $fields_vod_only, 'ended' )['kind'] );
smf_test_assert( 'pick_link ended, nichts vorhanden: null', null, SMF_Stream::pick_link( $fields_none, 'ended' ) );
smf_test_assert( 'pick_link upcoming, nichts vorhanden: null', null, SMF_Stream::pick_link( $fields_none, 'upcoming' ) );
smf_test_assert( 'pick_link canceled: immer null, auch mit Daten', null, SMF_Stream::pick_link( $fields_both, 'canceled' ) );

// ====================================================================
// display_kind() - Beschriftung nach Spielstatus, nicht nach pick_link()['kind']
// ====================================================================

smf_test_assert( 'display_kind upcoming: live', 'live', SMF_Stream::display_kind( 'upcoming' ) );
smf_test_assert( 'display_kind running: live',  'live', SMF_Stream::display_kind( 'running' ) );
smf_test_assert( 'display_kind ended: vod',     'vod',  SMF_Stream::display_kind( 'ended' ) );

// Der eigentliche Regressionsfall (v1.9.1): beendetes Spiel, kein vod_link,
// pick_link() greift auf live_stream_link zurück (kind='live') - die
// BESCHRIFTUNG muss trotzdem "Aufzeichnung" sein, nicht "Livestream".
$ended_fallback_picked = SMF_Stream::pick_link( $fields_live_only, 'ended' );
smf_test_assert( 'Regression: pick_link ended ohne vod_link liefert weiterhin kind=live (Quelle des Fehlers)', 'live', $ended_fallback_picked['kind'] );
smf_test_assert( 'Regression: display_kind() zeigt trotzdem "Aufzeichnung" (vod), nicht "Livestream"', 'vod', SMF_Stream::display_kind( 'ended' ) );

// ====================================================================
// build_embed_url()
// ====================================================================

$p = SMF_Stream::parse_url( 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' );
smf_test_assert(
    'build_embed_url YouTube: youtube-nocookie.com/embed/<id>',
    'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
    SMF_Stream::build_embed_url( $p )
);

$p = SMF_Stream::parse_url( 'https://www.twitch.tv/videos/1234567890' );
smf_test_assert(
    'build_embed_url Twitch: player.twitch.tv mit parent',
    'https://player.twitch.tv/?video=1234567890&parent=verein-beispiel.de',
    SMF_Stream::build_embed_url( $p, 'verein-beispiel.de' )
);
smf_test_assert(
    'build_embed_url Twitch ohne parent_host: leer (kein Embed möglich)',
    '',
    SMF_Stream::build_embed_url( $p, '' )
);

$p = SMF_Stream::parse_url( 'https://www.twitch.tv/eichehornfloorball' ); // Kanal-Link, nicht embeddable
smf_test_assert(
    'build_embed_url für nicht-embeddable Ergebnis: leer',
    '',
    SMF_Stream::build_embed_url( $p, 'verein-beispiel.de' )
);

// ====================================================================
// embed_parent_host()
// ====================================================================

smf_test_assert( 'embed_parent_host(): Host aus home_url()', 'verein-beispiel.de', SMF_Stream::embed_parent_host() );

// ====================================================================
// Zusammenfassung
// ====================================================================

echo "\n$count Assertions, $failures fehlgeschlagen.\n";
exit( $failures > 0 ? 1 : 0 );
