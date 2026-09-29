<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Zentrale Periodenlogik (v1.9.2): Anzahl der regulären Perioden eines
 * Spiels, Art einer Periode (regulär/Verlängerung/Penaltyschießen) und ihre
 * Bezeichnung - statt hartcodierter "n. Drittel"-Strings im Spieldetail.
 * Kleinfeld wird in zwei Halbzeiten gespielt, Großfeld in drei Dritteln;
 * bis v1.9.1 wurde die Kleinfeld-Verlängerung als "3. Drittel" angezeigt.
 *
 * Nummerierung wie in der API (geprüft an echten Antworten, September 2026):
 * Periode n = Index n-1 in result.home_goals_period/guest_goals_period und
 * events[].period. Auf die regulären Perioden folgt immer zuerst die
 * Verlängerung, dann das Penaltyschießen:
 *
 *     Kleinfeld: 1, 2 = Halbzeiten · 3 = Verlängerung · 4 = Penaltyschießen
 *     Großfeld:  1-3  = Drittel    · 4 = Verlängerung · 5 = Penaltyschießen
 *
 * Die Arrays sind aufgefüllt (Kleinfeld und Großfeld ohne Penaltyschießen:
 * 4 Einträge, Großfeld mit Penaltyschießen: 5) - die Länge taugt deshalb
 * NICHT zur Erkennung der Spielform. ingame_status/current_period_title
 * sind bei beendeten Spielen unzuverlässig (z.B. "extratime" ohne
 * Verlängerung) und werden hier bewusst nicht genutzt.
 */
class SMF_Periods {

    /** Rückfall, wenn sich die Spielform aus keiner Quelle ermitteln lässt (Großfeld). */
    const DEFAULT_REGULAR = 3;

    /**
     * Anzahl der regulären Perioden. Quellen in absteigender Verlässlichkeit:
     *
     * 1. period_titles aus games/{id}.json (Einträge mit status_id "periodN")
     * 2. periods aus leagues/{id}.json
     * 3. field_size aus leagues/{id}.json ("KF" = 2, "GF" = 3)
     * 4. Liganame ("Kleinfeld", "KF", "Kleintor") - reine Notlösung
     * 5. DEFAULT_REGULAR
     *
     * @param array      $game   Spiel, idealerweise aus games/{id}.json
     * @param array|null $league Liga aus leagues/{id}.json, falls vorhanden
     * @return int
     */
    public static function regular_count( array $game, $league = null ): int {
        if ( ! empty( $game['period_titles'] ) && is_array( $game['period_titles'] ) ) {
            $count = 0;
            foreach ( $game['period_titles'] as $title ) {
                if ( isset( $title['status_id'] ) && is_string( $title['status_id'] ) && preg_match( '/^period\d+$/', $title['status_id'] ) ) {
                    $count++;
                }
            }
            if ( $count > 0 ) {
                return $count;
            }
        }

        if ( is_array( $league ) ) {
            if ( isset( $league['periods'] ) && is_numeric( $league['periods'] ) && (int) $league['periods'] > 0 ) {
                return (int) $league['periods'];
            }
            if ( isset( $league['field_size'] ) ) {
                if ( $league['field_size'] === 'KF' ) {
                    return 2;
                }
                if ( $league['field_size'] === 'GF' ) {
                    return 3;
                }
            }
        }

        $name = '';
        if ( is_array( $league ) && ! empty( $league['name'] ) ) {
            $name = (string) $league['name'];
        } elseif ( ! empty( $game['league_name'] ) ) {
            $name = (string) $game['league_name'];
        }
        if ( $name !== '' && preg_match( '/Kleinfeld|Kleintor|\bKF\b/i', $name ) ) {
            return 2;
        }

        return self::DEFAULT_REGULAR;
    }

    /**
     * Art der Periode $n bei $regular regulären Perioden.
     *
     * @param int $n
     * @param int $regular
     * @return string 'regular' | 'overtime' | 'penalty_shots' | 'unknown'
     */
    public static function kind( int $n, int $regular ): string {
        if ( $n >= 1 && $n <= $regular ) {
            return 'regular';
        }
        if ( $n === $regular + 1 ) {
            return 'overtime';
        }
        if ( $n === $regular + 2 ) {
            return 'penalty_shots';
        }
        return 'unknown';
    }

    /**
     * Bezeichnung der Periode $n, überschreibbar per smf_label (Schlüssel
     * siehe README). Leerstring für 'unknown' - eine Periode jenseits von
     * Verlängerung und Penaltyschießen bekommt lieber keine Überschrift als
     * eine falsche.
     *
     * @param int    $n
     * @param int    $regular
     * @param string $form 'long' (Spielverlauf) oder 'short' (Ergebnis-Aufschlüsselung)
     * @return string
     */
    public static function label( int $n, int $regular, string $form = 'long' ): string {
        $short = $form === 'short';

        switch ( self::kind( $n, $regular ) ) {
            case 'regular':
                if ( $regular === 2 ) {
                    /* translators: %d: Nummer der Halbzeit */
                    return sprintf( smf_label( 'period_label_half', '%d. Halbzeit' ), $n );
                }
                if ( $regular === 3 ) {
                    /* translators: %d: Nummer des Drittels */
                    return sprintf( smf_label( 'period_label_third', '%d. Drittel' ), $n );
                }
                // Bisher in keiner Liga gesehen, aber lieber neutral als
                // "4. Drittel".
                /* translators: %d: Nummer des Spielabschnitts */
                return sprintf( smf_label( 'period_label_generic', '%d. Spielabschnitt' ), $n );
            case 'overtime':
                return $short
                    ? smf_label( 'period_label_overtime_short', 'Verl.' )
                    : smf_label( 'period_label_overtime', 'Verlängerung' );
            case 'penalty_shots':
                return $short
                    ? smf_label( 'period_label_penalty_shots_short', 'PS' )
                    : smf_label( 'period_label_penalty_shots', 'Penaltyschießen' );
        }
        return '';
    }

    /**
     * Zeilen der Ergebnis-Aufschlüsselung im Spieldetail.
     *
     * - Reguläre Perioden immer, auch 0:0 (bis v1.9.1 übersprungen).
     * - Verlängerung nur, wenn es eine gab (result.overtime) oder dort ein
     *   Tor fiel - bei einem Penaltyschießen also auch "Verl. 0:0".
     * - Penaltyschießen nur, wenn es eins gab (Postfix) oder dort ein Tor
     *   gezählt ist. Die API zählt dort nur das entscheidende Tor (z.B. 0:1),
     *   nicht alle Schüsse.
     * - Perioden jenseits davon: nie.
     * - Keine Zeilen bei Forfait, ohne Periodendaten oder wenn die Perioden
     *   nur Nullen enthalten, das Ergebnis aber nicht 0:0 ist (Perioden
     *   nicht erfasst).
     *
     * @param array $game
     * @param int   $regular Rückgabe von regular_count()
     * @return array<int, array{period: int, kind: string, label: string, home: int, guest: int}>
     */
    public static function breakdown( array $game, int $regular ): array {
        $result = ( isset( $game['result'] ) && is_array( $game['result'] ) ) ? $game['result'] : array();
        if ( ! empty( $result['forfait'] ) ) {
            return array();
        }

        $home  = ( isset( $result['home_goals_period'] ) && is_array( $result['home_goals_period'] ) ) ? array_values( $result['home_goals_period'] ) : array();
        $guest = ( isset( $result['guest_goals_period'] ) && is_array( $result['guest_goals_period'] ) ) ? array_values( $result['guest_goals_period'] ) : array();
        if ( empty( $home ) && empty( $guest ) ) {
            return array();
        }

        $period_sum = array_sum( array_map( 'intval', $home ) ) + array_sum( array_map( 'intval', $guest ) );
        $total      = ( isset( $result['home_goals'] ) ? (int) $result['home_goals'] : 0 ) + ( isset( $result['guest_goals'] ) ? (int) $result['guest_goals'] : 0 );
        if ( $period_sum === 0 && $total > 0 ) {
            return array();
        }

        $had_overtime  = ! empty( $result['overtime'] );
        $had_shootout  = self::is_penalty_shootout( $result );

        $rows  = array();
        $count = max( count( $home ), count( $guest ) );
        for ( $i = 0; $i < $count; $i++ ) {
            $n    = $i + 1;
            $kind = self::kind( $n, $regular );
            $h    = isset( $home[ $i ] ) ? (int) $home[ $i ] : 0;
            $g    = isset( $guest[ $i ] ) ? (int) $guest[ $i ] : 0;
            $any  = $h !== 0 || $g !== 0;

            if ( $kind === 'unknown'
                || ( $kind === 'overtime' && ! $had_overtime && ! $any )
                || ( $kind === 'penalty_shots' && ! $had_shootout && ! $any ) ) {
                continue;
            }

            $rows[] = array(
                'period' => $n,
                'kind'   => $kind,
                'label'  => self::label( $n, $regular, 'short' ),
                'home'   => $h,
                'guest'  => $g,
            );
        }
        return $rows;
    }

    /**
     * Ob das Spiel im Penaltyschießen entschieden wurde - laut API-Postfix
     * ("n. PS" / "nach Penalty-Schießen"). Bewusst nicht über
     * current_period_title, siehe Klassenkommentar.
     *
     * @param array $result
     * @return bool
     */
    private static function is_penalty_shootout( array $result ): bool {
        if ( ! isset( $result['postfix'] ) ) {
            return false;
        }
        $postfix = $result['postfix'];
        $text    = is_array( $postfix )
            ? ( isset( $postfix['short'] ) ? $postfix['short'] : '' ) . ' ' . ( isset( $postfix['long'] ) ? $postfix['long'] : '' )
            : (string) $postfix;
        return (bool) preg_match( '/\bPS\b|Penalty/i', $text );
    }
}
