<?php
/**
 * Eigenständiger PHP-Test für SMF_API::filter_past_games() /
 * filter_upcoming_games() / get_last_game() / get_next_game() - läuft ohne
 * WordPress, ohne Composer, ohne Build-Step:
 *
 *     php tests/test-api-game-filters.php
 *
 * Deckt zwei zusammenhängende Fälle ab:
 *
 * 1. Ein laufendes Spiel (started=true, ended=false) verschwand für bis zu
 *    zwei Stunden aus BEIDEN Filtern: nicht "nächstes" (Anstoß liegt bereits
 *    in der Vergangenheit), aber auch noch nicht "vergangen" (der 2h-Puffer
 *    in filter_past_games() greift erst danach) - sm_naechstes_spiel/
 *    sm_letztes_spiel zeigten in diesem Fenster gar kein Spiel an.
 * 2. Ohne Obergrenze würde ein Spiel, bei dem der Verband ended nie setzt
 *    (z.B. Spielbericht nie abgeschlossen - ein "Zombie-Spiel"), dauerhaft
 *    als "nächstes" hängen bleiben und ein tatsächlich nächstes Spiel
 *    verdrängen. SMF_API::RUNNING_MAX_AGE (4 Stunden) begrenzt das - danach
 *    greift wieder die normale Vergangenheits-Logik.
 *
 * Fixtures sind rein synthetisch (keine echten API-Antworten, keine
 * Personennamen), Zeitpunkte relativ zu time(), damit der Test nicht mit
 * der Zeit veraltet.
 *
 * Exit-Code 0 = alle Assertions bestanden, 1 = mindestens eine fehlgeschlagen.
 */

// --- Minimale WordPress-Stubs, nur was SMF_API/SMF_Cache tatsächlich aufrufen ---

define( 'ABSPATH', '/tmp/' );

// class-cache.php nutzt MINUTE_IN_SECONDS/HOUR_IN_SECONDS/DAY_IN_SECONDS in
// einer Klassenkonstante (STALE_MAX_AGE) - ohne WordPress selbst definieren,
// dieselben Werte wie WP Core.
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 60 * MINUTE_IN_SECONDS );
define( 'DAY_IN_SECONDS', 24 * HOUR_IN_SECONDS );

function get_option( $key, $default = false ) {
    return $default;
}

require __DIR__ . '/../includes/class-cache.php';
require __DIR__ . '/../includes/class-api.php';

// --- Mini-Testharness (gleiches Muster wie test-design-sanitize.php) ---

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

/**
 * Baut ein synthetisches Spiel-Fixture, Kickoff relativ zu jetzt.
 *
 * @param int    $offset_seconds Sekunden relativ zu time() (negativ = Vergangenheit)
 * @param array  $overrides      Zusätzliche/überschreibende Felder
 * @return array
 */
function smf_test_game( int $offset_seconds, array $overrides = array() ): array {
    $ts = time() + $offset_seconds;
    $base = array(
        'game_id'          => 1,
        'date'             => date( 'Y-m-d', $ts ),
        'time'             => date( 'H:i', $ts ),
        'home_team_name'   => 'Team A',
        'guest_team_name'  => 'Team B',
    );
    return array_merge( $base, $overrides );
}

$api = new SMF_API();

// --- Fixtures ---

// 1. Echtes zukünftiges Spiel, in zwei Tagen, keine Status-Flags gesetzt.
$future = smf_test_game( 2 * DAY_IN_SECONDS, array( 'game_id' => 1 ) );

// 2. Laufendes Spiel, Anstoß vor 30 Minuten - der Kernfall des Bugs.
$running_30min = smf_test_game( -30 * MINUTE_IN_SECONDS, array(
    'game_id' => 2,
    'started' => true,
    'ended'   => false,
    // result/result_string fehlen komplett, nicht nur null - so liefert
    // die API laut Doku ein laufendes Spiel ohne Echtzeit-Freigabe.
) );

// 3. Laufendes Spiel in Verlängerung, Anstoß vor 2,5 Stunden - liegt
//    bewusst JENSEITS des 2h-Puffers aus filter_past_games(), um genau den
//    gemeldeten Bug zu reproduzieren (vorher: fiel hier in "vergangen").
$running_2_5h = smf_test_game( (int) ( -2.5 * HOUR_IN_SECONDS ), array(
    'game_id' => 3,
    'started' => true,
    'ended'   => false,
) );

// 4. Beendetes Spiel mit Ergebnis, vor 3 Stunden.
$ended = smf_test_game( -3 * HOUR_IN_SECONDS, array(
    'game_id' => 4,
    'started' => true,
    'ended'   => true,
    'result'  => array( 'home_goals' => 3, 'guest_goals' => 2 ),
) );

// 5. Datenqualitäts-Fall: Anstoß vor 3 Stunden, started/ended fehlen
//    komplett (kommt laut Code-Kommentar im Spielplan-Endpoint vor) - muss
//    weiterhin über den 2h-Puffer als "vergangen" gelten, unverändertes
//    Bestandsverhalten.
$stale_no_flags = smf_test_game( -3 * HOUR_IN_SECONDS, array( 'game_id' => 5 ) );

// 6. Datenqualitäts-Fall innerhalb des 2h-Puffers: Anstoß vor 90 Minuten,
//    started/ended fehlen. Bleibt bewusst in keinem der beiden Filter -
//    unverändertes, vorbestehendes Pufferverhalten, nicht Teil dieses Fixes.
$stale_within_buffer = smf_test_game( (int) ( -1.5 * HOUR_IN_SECONDS ), array( 'game_id' => 6 ) );

// 7. "Zombie-Spiel": started=true, ended=false, Anstoß vor 5 Stunden -
//    jenseits von RUNNING_MAX_AGE (4h). Der Verband hat ended nie gesetzt
//    (z.B. Spielbericht nie abgeschlossen). Muss wie jedes andere
//    überfällige Spiel behandelt werden: nicht mehr "nächstes", sondern
//    "letztes" Spiel (2h-Puffer-Fallback greift, da 5h > 2h).
$zombie_5h = smf_test_game( -5 * HOUR_IN_SECONDS, array(
    'game_id' => 7,
    'started' => true,
    'ended'   => false,
) );

// 8. Grenzfall: Anstoß vor GENAU RUNNING_MAX_AGE (4h) - zählt laut
//    "höchstens 4 Stunden" noch als laufend (inklusive Grenze).
//
// Die API liefert date/time nur mit Minutenauflösung (kein Sekundenfeld,
// siehe "Bekannte Datenfallen" in docs/saisonmanager-api-uebersicht.md im
// Website-Repo), parse_game_date() rundet beim Zurück-Parsen also ohnehin
// auf die volle Minute ab. Referenz-"jetzt" hier bewusst auf die NÄCHSTE
// volle Minute AUFgerundet (garantiert > tatsächliches time() jetzt),
// bevor RUNNING_MAX_AGE (glatte 240 Minuten) abgezogen wird - so bleibt
// "jetzt minus Kickoff" im echten Filter-Aufruf gleich danach immer <=
// RUNNING_MAX_AGE. Mit Abrunden (die naheliegendere Variante) wäre der
// Test deterministisch fehlgeschlagen: Abrunden nimmt Zeit weg, wodurch
// der Kickoff-Zeitpunkt older wird als beabsichtigt und die Differenz zum
// echten time() im Filter-Aufruf immer > RUNNING_MAX_AGE landet.
$boundary_ceil     = time();
$boundary_ceil     += 60 - ( $boundary_ceil % 60 );
$boundary_kickoff  = $boundary_ceil - SMF_API::RUNNING_MAX_AGE;
$boundary_4h = array(
    'game_id'         => 8,
    'date'            => date( 'Y-m-d', $boundary_kickoff ),
    'time'            => date( 'H:i', $boundary_kickoff ),
    'home_team_name'  => 'Team A',
    'guest_team_name' => 'Team B',
    'started'         => true,
    'ended'           => false,
);

// 9. Ungültiges Datum ("TBD", wie laut Code-Kommentar in game_days.date
//    vorkommt) bei started=true, ended=false - darf NICHT als laufend
//    gewertet werden, obwohl started gesetzt ist (kein verlässliches
//    Zeitfenster prüfbar). parse_game_date() liefert hier false.
$invalid_date = array(
    'game_id'         => 9,
    'date'            => 'TBD',
    'time'            => '',
    'home_team_name'  => 'Team A',
    'guest_team_name' => 'Team B',
    'started'         => true,
    'ended'           => false,
);

// --- Einzeltests: filter_upcoming_games() ---

smf_test_assert(
    'filter_upcoming_games: zukünftiges Spiel ist upcoming',
    array( $future ),
    $api->filter_upcoming_games( array( $future ) )
);

smf_test_assert(
    'filter_upcoming_games: laufendes Spiel (Anstoß vor 30 Min) ist upcoming',
    array( $running_30min ),
    $api->filter_upcoming_games( array( $running_30min ) )
);

smf_test_assert(
    'filter_upcoming_games: laufendes Spiel (Anstoß vor 2,5 Std, Verlängerung) ist weiterhin upcoming - Kernfall des Bugs',
    array( $running_2_5h ),
    $api->filter_upcoming_games( array( $running_2_5h ) )
);

smf_test_assert(
    'filter_upcoming_games: beendetes Spiel ist NICHT upcoming',
    array(),
    $api->filter_upcoming_games( array( $ended ) )
);

smf_test_assert(
    'filter_upcoming_games: Datenqualitäts-Fall ohne Flags (3h alt) ist NICHT upcoming',
    array(),
    $api->filter_upcoming_games( array( $stale_no_flags ) )
);

smf_test_assert(
    'filter_upcoming_games: Datenqualitäts-Fall ohne Flags innerhalb 2h-Puffer ist NICHT upcoming (unverändertes Bestandsverhalten)',
    array(),
    $api->filter_upcoming_games( array( $stale_within_buffer ) )
);

smf_test_assert(
    'filter_upcoming_games: Zombie-Spiel (started, Anstoß vor 5h, jenseits RUNNING_MAX_AGE) ist NICHT mehr upcoming',
    array(),
    $api->filter_upcoming_games( array( $zombie_5h ) )
);

smf_test_assert(
    'filter_upcoming_games: Grenzfall genau RUNNING_MAX_AGE (4h) zählt noch als upcoming',
    array( $boundary_4h ),
    $api->filter_upcoming_games( array( $boundary_4h ) )
);

smf_test_assert(
    'filter_upcoming_games: started=true mit ungültigem Datum ("TBD") ist NICHT upcoming',
    array(),
    $api->filter_upcoming_games( array( $invalid_date ) )
);

// --- Einzeltests: filter_past_games() ---

smf_test_assert(
    'filter_past_games: zukünftiges Spiel ist NICHT past',
    array(),
    $api->filter_past_games( array( $future ) )
);

smf_test_assert(
    'filter_past_games: laufendes Spiel (Anstoß vor 30 Min) ist NICHT past',
    array(),
    $api->filter_past_games( array( $running_30min ) )
);

smf_test_assert(
    'filter_past_games: laufendes Spiel (Anstoß vor 2,5 Std) ist NICHT past - vorher fälschlich ja, Kernfall des Bugs',
    array(),
    $api->filter_past_games( array( $running_2_5h ) )
);

smf_test_assert(
    'filter_past_games: beendetes Spiel ist past',
    array( $ended ),
    $api->filter_past_games( array( $ended ) )
);

smf_test_assert(
    'filter_past_games: Datenqualitäts-Fall ohne Flags (3h alt) ist past (2h-Puffer greift, unverändertes Bestandsverhalten)',
    array( $stale_no_flags ),
    $api->filter_past_games( array( $stale_no_flags ) )
);

smf_test_assert(
    'filter_past_games: Datenqualitäts-Fall ohne Flags innerhalb 2h-Puffer ist NICHT past (unverändertes Bestandsverhalten)',
    array(),
    $api->filter_past_games( array( $stale_within_buffer ) )
);

smf_test_assert(
    'filter_past_games: Zombie-Spiel (started, Anstoß vor 5h) ist past (2h-Puffer-Fallback greift wie bei jedem überfälligen Spiel)',
    array( $zombie_5h ),
    $api->filter_past_games( array( $zombie_5h ) )
);

smf_test_assert(
    'filter_past_games: Grenzfall genau RUNNING_MAX_AGE (4h) ist NICHT past (zählt noch als laufend)',
    array(),
    $api->filter_past_games( array( $boundary_4h ) )
);

smf_test_assert(
    'filter_past_games: started=true mit ungültigem Datum ("TBD") ist NICHT past (kein Datum zum Vergleichen)',
    array(),
    $api->filter_past_games( array( $invalid_date ) )
);

// --- get_next_game() / get_last_game(): laufendes Spiel bleibt "nächstes",
//     erscheint nie als "letztes" - Entscheidung B2 aus dem Umsetzungsauftrag ---

$mixed = array( $future, $running_30min, $ended );

smf_test_assert(
    'get_next_game: liefert das laufende Spiel, nicht das weiter entfernte zukünftige',
    $running_30min,
    $api->get_next_game( $mixed )
);

// Zombie-Spiel zusammen mit einem echten zukünftigen Spiel: das Zombie darf
// das echte nächste Spiel nicht mehr verdrängen (der eigentliche Bericht
// dieser Korrektur), landet stattdessen als "letztes Spiel".
$mixed_with_zombie = array( $future, $zombie_5h );

smf_test_assert(
    'get_next_game: Zombie-Spiel verdrängt NICHT mehr das echte nächste Spiel',
    $future,
    $api->get_next_game( $mixed_with_zombie )
);

smf_test_assert(
    'get_last_game: Zombie-Spiel erscheint als letztes Spiel, nicht als nächstes',
    $zombie_5h,
    $api->get_last_game( $mixed_with_zombie )
);

smf_test_assert(
    'get_last_game: liefert das beendete Spiel, NICHT das laufende',
    $ended,
    $api->get_last_game( $mixed )
);

$only_running = array( $running_2_5h );

smf_test_assert(
    'get_next_game: laufendes Spiel in Verlängerung wird gefunden, wenn es das einzige ist',
    $running_2_5h,
    $api->get_next_game( $only_running )
);

smf_test_assert(
    'get_last_game: laufendes Spiel in Verlängerung wird NICHT als letztes Spiel gefunden',
    null,
    $api->get_last_game( $only_running )
);

// ------------------------------------------------------------------

echo "\n" . str_repeat( '=', 60 ) . "\n";
echo "$count Assertions, " . ( $count - $failures ) . " OK, $failures FAIL\n";

exit( $failures > 0 ? 1 : 0 );
