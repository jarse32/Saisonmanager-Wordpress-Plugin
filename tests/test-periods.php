<?php
/**
 * Eigenständiger PHP-Test für SMF_Periods (v1.9.2) - läuft ohne WordPress,
 * ohne Composer, ohne Build-Step:
 *
 *     php tests/test-periods.php
 *
 * Hintergrund: Das Spieldetail beschriftete Perioden bis v1.9.1 hartcodiert
 * als "n. Drittel". Kleinfeld wird aber in zwei Halbzeiten gespielt - die
 * Kleinfeld-Verlängerung erschien als "3. Drittel", das Großfeld-
 * Penaltyschießen als "Verl." bzw. "Verlängerung", und 0:0-Perioden fehlten
 * in der Aufschlüsselung ganz.
 *
 * Die Fixtures bilden die Array-FORMEN echter API-Antworten nach (geprüft
 * September 2026: Auffüllung auf 4 bzw. 5 Einträge, Postfix-Werte,
 * period_titles), enthalten aber keine echten Spiele, Teams oder Personen.
 *
 * Exit-Code 0 = alle Assertions bestanden, 1 = mindestens eine fehlgeschlagen.
 */

define( 'ABSPATH', '/tmp/' );

// smf_label()-Stub: wie im Plugin, Overrides zum Testen der
// Überschreibbarkeit über $GLOBALS statt über den smf_labels-Filter.
$GLOBALS['smf_test_label_overrides'] = array();
function smf_label( $key, $fallback ) {
    $o = $GLOBALS['smf_test_label_overrides'];
    return ( isset( $o[ $key ] ) && $o[ $key ] !== '' ) ? $o[ $key ] : $fallback;
}

require __DIR__ . '/../includes/class-periods.php';

// --- Mini-Testharness (gleiches Muster wie test-api-game-filters.php) ---

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
 * Aufschlüsselung als kompakte Strings ("1. Halbzeit 4:3"), damit die
 * Assertions lesbar bleiben.
 */
function smf_test_rows( array $rows ): array {
    return array_map( function ( $r ) {
        return $r['label'] . ' ' . $r['home'] . ':' . $r['guest'];
    }, $rows );
}

/** period_titles wie in games/{id}.json (Pausen-Einträge mit .5 inklusive). */
function smf_test_period_titles( int $regular ): array {
    $titles = array();
    for ( $i = 1; $i <= $regular; $i++ ) {
        $titles[] = array( 'period' => $i, 'status_id' => 'period' . $i );
        if ( $i < $regular ) {
            $titles[] = array( 'period' => $i + 0.5, 'status_id' => 'pause' . $i );
        }
    }
    $titles[] = array( 'period' => $regular + 0.5, 'status_id' => 'pause_et' );
    $titles[] = array( 'period' => $regular + 1, 'status_id' => 'extratime' );
    $titles[] = array( 'period' => $regular + 1.5, 'status_id' => 'pause_ps' );
    $titles[] = array( 'period' => $regular + 2, 'status_id' => 'penalty_shots' );
    return $titles;
}

// --- regular_count(): Quellen und Rangfolge ---

smf_test_assert( 'regular_count: period_titles Kleinfeld = 2', 2, SMF_Periods::regular_count( array( 'period_titles' => smf_test_period_titles( 2 ) ) ) );
smf_test_assert( 'regular_count: period_titles Großfeld = 3', 3, SMF_Periods::regular_count( array( 'period_titles' => smf_test_period_titles( 3 ) ) ) );
smf_test_assert(
    'regular_count: period_titles schlagen widersprüchliche Liga-Daten',
    2,
    SMF_Periods::regular_count( array( 'period_titles' => smf_test_period_titles( 2 ) ), array( 'periods' => 3, 'field_size' => 'GF' ) )
);
smf_test_assert( 'regular_count: Liga periods = 2', 2, SMF_Periods::regular_count( array(), array( 'periods' => 2 ) ) );
smf_test_assert( 'regular_count: Liga field_size KF ohne periods = 2', 2, SMF_Periods::regular_count( array(), array( 'field_size' => 'KF' ) ) );
smf_test_assert( 'regular_count: Liga field_size GF ohne periods = 3', 3, SMF_Periods::regular_count( array(), array( 'field_size' => 'GF' ) ) );
smf_test_assert(
    'regular_count: Notlösung Liganame "Kleinfeld" aus dem Spiel',
    2,
    SMF_Periods::regular_count( array( 'league_name' => 'Damen Kleinfeld Regionalliga' ) )
);
smf_test_assert(
    'regular_count: Notlösung Liganame "(KF)"',
    2,
    SMF_Periods::regular_count( array( 'league_name' => 'Regionalliga Damen (KF)' ) )
);
smf_test_assert(
    'regular_count: "KF" nur als eigenes Wort, nicht in anderen Wörtern',
    3,
    SMF_Periods::regular_count( array( 'league_name' => 'Kfz-Pokal Herren' ) )
);
smf_test_assert( 'regular_count: ohne jede Quelle Großfeld-Default 3', 3, SMF_Periods::regular_count( array() ) );
smf_test_assert(
    'regular_count: period_titles ohne periodN-Einträge fallen auf Liga zurück',
    2,
    SMF_Periods::regular_count( array( 'period_titles' => array( array( 'status_id' => 'extratime' ) ) ), array( 'periods' => 2 ) )
);

// --- kind() / label() ---

smf_test_assert( 'kind: KF Periode 3 ist Verlängerung', 'overtime', SMF_Periods::kind( 3, 2 ) );
smf_test_assert( 'kind: KF Periode 4 ist Penaltyschießen', 'penalty_shots', SMF_Periods::kind( 4, 2 ) );
smf_test_assert( 'kind: GF Periode 3 ist regulär', 'regular', SMF_Periods::kind( 3, 3 ) );
smf_test_assert( 'kind: GF Periode 4 ist Verlängerung', 'overtime', SMF_Periods::kind( 4, 3 ) );
smf_test_assert( 'kind: GF Periode 5 ist Penaltyschießen', 'penalty_shots', SMF_Periods::kind( 5, 3 ) );
smf_test_assert( 'kind: KF Periode 5 ist unbekannt', 'unknown', SMF_Periods::kind( 5, 2 ) );
smf_test_assert( 'kind: Periode 0 ist unbekannt', 'unknown', SMF_Periods::kind( 0, 3 ) );

smf_test_assert( 'label: KF 1 lang', '1. Halbzeit', SMF_Periods::label( 1, 2 ) );
smf_test_assert( 'label: KF 3 lang (nie "3. Drittel")', 'Verlängerung', SMF_Periods::label( 3, 2 ) );
smf_test_assert( 'label: KF 4 lang', 'Penaltyschießen', SMF_Periods::label( 4, 2 ) );
smf_test_assert( 'label: GF 3 lang', '3. Drittel', SMF_Periods::label( 3, 3 ) );
smf_test_assert( 'label: GF 4 lang (nie "4. Drittel")', 'Verlängerung', SMF_Periods::label( 4, 3 ) );
smf_test_assert( 'label: GF 5 lang (nicht "Verlängerung")', 'Penaltyschießen', SMF_Periods::label( 5, 3 ) );
smf_test_assert( 'label: GF 4 kurz', 'Verl.', SMF_Periods::label( 4, 3, 'short' ) );
smf_test_assert( 'label: GF 5 kurz', 'PS', SMF_Periods::label( 5, 3, 'short' ) );
smf_test_assert( 'label: reguläre Periode kurz = lang', '2. Halbzeit', SMF_Periods::label( 2, 2, 'short' ) );
smf_test_assert( 'label: unbekannte Periode ohne Beschriftung', '', SMF_Periods::label( 6, 3 ) );
smf_test_assert( 'label: ungewöhnliche Periodenzahl neutral statt "4. Drittel"', '4. Spielabschnitt', SMF_Periods::label( 4, 4 ) );

$GLOBALS['smf_test_label_overrides'] = array(
    'period_label_half'                => '%d. HZ',
    'period_label_penalty_shots_short' => 'n.P.',
);
smf_test_assert( 'label: per smf_label überschreibbar (Halbzeit)', '2. HZ', SMF_Periods::label( 2, 2 ) );
smf_test_assert( 'label: per smf_label überschreibbar (PS kurz)', 'n.P.', SMF_Periods::label( 4, 2, 'short' ) );
$GLOBALS['smf_test_label_overrides'] = array();

// --- breakdown(): die gemeldeten und angrenzenden Fälle ---

// Kernfall aus dem Live-Test: Kleinfeld 7:6 n.V., Array auf 4 aufgefüllt.
$kf_ot = array(
    'period_titles' => smf_test_period_titles( 2 ),
    'result'        => array(
        'home_goals'         => 7,
        'guest_goals'        => 6,
        'home_goals_period'  => array( 4, 2, 1, 0 ),
        'guest_goals_period' => array( 3, 3, 0, 0 ),
        'postfix'            => array( 'short' => 'n.V.', 'long' => 'nach Verlängerung' ),
        'forfait'            => false,
        'overtime'           => true,
    ),
);
smf_test_assert(
    'breakdown: Kleinfeld n.V. = 2 Halbzeiten + Verlängerung',
    array( '1. Halbzeit 4:3', '2. Halbzeit 2:3', 'Verl. 1:0' ),
    smf_test_rows( SMF_Periods::breakdown( $kf_ot, SMF_Periods::regular_count( $kf_ot ) ) )
);

$kf_regular = array(
    'result' => array(
        'home_goals'         => 4,
        'guest_goals'        => 2,
        'home_goals_period'  => array( 4, 0, 0, 0 ),
        'guest_goals_period' => array( 0, 2, 0, 0 ),
        'postfix'            => array( 'short' => '', 'long' => '' ),
        'forfait'            => false,
        'overtime'           => false,
    ),
);
smf_test_assert(
    'breakdown: Kleinfeld regulär - keine leere Verlängerung, kein Penalty',
    array( '1. Halbzeit 4:0', '2. Halbzeit 0:2' ),
    smf_test_rows( SMF_Periods::breakdown( $kf_regular, 2 ) )
);

$gf_regular_with_nil = array(
    'result' => array(
        'home_goals'         => 3,
        'guest_goals'        => 1,
        'home_goals_period'  => array( 2, 0, 1, 0 ),
        'guest_goals_period' => array( 1, 0, 0, 0 ),
        'overtime'           => false,
    ),
);
smf_test_assert(
    'breakdown: 0:0-Drittel in der regulären Spielzeit wird wieder angezeigt',
    array( '1. Drittel 2:1', '2. Drittel 0:0', '3. Drittel 1:0' ),
    smf_test_rows( SMF_Periods::breakdown( $gf_regular_with_nil, 3 ) )
);

$gf_ot = array(
    'result' => array(
        'home_goals'         => 3,
        'guest_goals'        => 2,
        'home_goals_period'  => array( 0, 1, 1, 1 ),
        'guest_goals_period' => array( 1, 0, 1, 0 ),
        'postfix'            => array( 'short' => 'n.V.', 'long' => 'nach Verlängerung' ),
        'overtime'           => true,
    ),
);
smf_test_assert(
    'breakdown: Großfeld n.V.',
    array( '1. Drittel 0:1', '2. Drittel 1:0', '3. Drittel 1:1', 'Verl. 1:0' ),
    smf_test_rows( SMF_Periods::breakdown( $gf_ot, 3 ) )
);

// Großfeld n. PS: Array auf 5, Verlängerung 0:0, PS zählt nur das
// entscheidende Tor (0:1).
$gf_ps = array(
    'result' => array(
        'home_goals'         => 6,
        'guest_goals'        => 7,
        'home_goals_period'  => array( 2, 0, 4, 0, 0 ),
        'guest_goals_period' => array( 2, 2, 2, 0, 1 ),
        'postfix'            => array( 'short' => 'n. PS', 'long' => 'nach Penalty-Schießen' ),
        'overtime'           => true,
    ),
);
smf_test_assert(
    'breakdown: Großfeld n. PS = 3 Drittel + Verl. 0:0 + PS (nie "Verl." für den PS-Eintrag)',
    array( '1. Drittel 2:2', '2. Drittel 0:2', '3. Drittel 4:2', 'Verl. 0:0', 'PS 0:1' ),
    smf_test_rows( SMF_Periods::breakdown( $gf_ps, 3 ) )
);

// Kleinfeld n. PS (in den Stichproben nicht gefunden, aber nach derselben
// Nummerierung zu erwarten): Periode 4 = PS.
$kf_ps = array(
    'result' => array(
        'home_goals'         => 3,
        'guest_goals'        => 2,
        'home_goals_period'  => array( 1, 1, 0, 1 ),
        'guest_goals_period' => array( 0, 2, 0, 0 ),
        'postfix'            => array( 'short' => 'n. PS', 'long' => 'nach Penalty-Schießen' ),
        'overtime'           => true,
    ),
);
smf_test_assert(
    'breakdown: Kleinfeld n. PS',
    array( '1. Halbzeit 1:0', '2. Halbzeit 1:2', 'Verl. 0:0', 'PS 1:0' ),
    smf_test_rows( SMF_Periods::breakdown( $kf_ps, 2 ) )
);

// Kleinfeld-Array mit unerwartetem 5. Eintrag: nie als Periode anzeigen.
$kf_extra = $kf_regular;
$kf_extra['result']['home_goals_period']  = array( 4, 0, 0, 0, 9 );
$kf_extra['result']['guest_goals_period'] = array( 0, 2, 0, 0, 0 );
smf_test_assert(
    'breakdown: Eintrag jenseits von PS wird nie angezeigt',
    array( '1. Halbzeit 4:0', '2. Halbzeit 0:2' ),
    smf_test_rows( SMF_Periods::breakdown( $kf_extra, 2 ) )
);

$forfait = array(
    'result' => array(
        'home_goals'         => 5,
        'guest_goals'        => 0,
        'home_goals_period'  => array( 0, 0, 0, 0 ),
        'guest_goals_period' => array( 0, 0, 0, 0 ),
        'postfix'            => array( 'short' => ' (forfait)', 'long' => ' kampflos' ),
        'forfait'            => true,
        'overtime'           => false,
    ),
);
smf_test_assert( 'breakdown: Forfait ohne Aufschlüsselung', array(), SMF_Periods::breakdown( $forfait, 3 ) );

$zeros_without_flag = $forfait;
$zeros_without_flag['result']['forfait'] = false;
smf_test_assert(
    'breakdown: Perioden nur 0, Ergebnis nicht 0:0 (nicht erfasst) - keine Aufschlüsselung',
    array(),
    SMF_Periods::breakdown( $zeros_without_flag, 3 )
);

$nil_nil = array(
    'result' => array(
        'home_goals'         => 0,
        'guest_goals'        => 0,
        'home_goals_period'  => array( 0, 0, 0, 0 ),
        'guest_goals_period' => array( 0, 0, 0, 0 ),
        'overtime'           => false,
    ),
);
smf_test_assert(
    'breakdown: echtes 0:0 zeigt die regulären Perioden',
    array( '1. Drittel 0:0', '2. Drittel 0:0', '3. Drittel 0:0' ),
    smf_test_rows( SMF_Periods::breakdown( $nil_nil, 3 ) )
);

smf_test_assert( 'breakdown: abgesagt (result {}) - leer', array(), SMF_Periods::breakdown( array( 'result' => array() ), 3 ) );
smf_test_assert( 'breakdown: ohne result - leer', array(), SMF_Periods::breakdown( array(), 3 ) );
smf_test_assert(
    'breakdown: String-Postfix (Altformat) wird ebenfalls erkannt',
    array( '1. Drittel 2:2', '2. Drittel 0:2', '3. Drittel 4:2', 'Verl. 0:0', 'PS 0:1' ),
    smf_test_rows( SMF_Periods::breakdown( array( 'result' => array_merge( $gf_ps['result'], array( 'postfix' => 'n. PS' ) ) ), 3 ) )
);

// ------------------------------------------------------------------

echo "\n" . str_repeat( '=', 60 ) . "\n";
echo "$count Assertions, " . ( $count - $failures ) . " OK, $failures FAIL\n";

exit( $failures > 0 ? 1 : 0 );
