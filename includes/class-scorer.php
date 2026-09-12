<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Scorerliste (Punkteliste) eines Teams, auf Basis von teams/{id}/stats.
 *
 * Von der Antwort wird ausschließlich der "scorer"-Block (plus optional
 * "totals" für die Summenzeile) ausgewertet. "team", "recent_games" und
 * "upcoming_games" sind Teil derselben Antwort, aber nicht Gegenstand
 * dieser Klasse.
 */
class SMF_Scorer {

    /**
     * Globaler Schalter "Personennamen anzeigen" (Spieldetail-Modal UND
     * Scorerliste). Vereinsentscheidung - bewusst nicht per Shortcode-
     * Attribut übersteuerbar, siehe smf_labels/Datenschutz-Doku.
     *
     * @return bool
     */
    public static function player_names_enabled() {
        return get_option( 'smf_show_player_names', '' ) === '1';
    }

    /**
     * Personennamen einheitlich formatieren - gemeinsame Stelle für
     * templates/scorer.php und templates/game-detail.php, damit "voll"
     * vs. "abgekürzt" (z.B. "Max M.") nicht mehrfach implementiert wird.
     *
     * @param string      $first  Vorname (kann leer sein)
     * @param string      $last   Nachname (kann leer sein)
     * @param string|null $format 'voll' | 'abgekuerzt'; null = globale Option smf_player_name_format
     * @return string
     */
    public static function format_player_name( $first, $last, $format = null ) {
        $first = trim( (string) $first );
        $last  = trim( (string) $last );

        if ( ! in_array( $format, array( 'voll', 'abgekuerzt' ), true ) ) {
            $format = get_option( 'smf_player_name_format', 'abgekuerzt' );
            if ( ! in_array( $format, array( 'voll', 'abgekuerzt' ), true ) ) {
                $format = 'abgekuerzt';
            }
        }

        if ( $first === '' && $last === '' ) {
            return '—';
        }

        if ( $format === 'voll' || $first === '' || $last === '' ) {
            return trim( $first . ' ' . $last );
        }

        // abgekuerzt: Vorname voll, Nachname als Initiale ("Max M.")
        return $first . ' ' . mb_substr( $last, 0, 1 ) . '.';
    }

    /**
     * Scorerliste eines Teams laden, normalisieren, sortieren und
     * limitieren.
     *
     * @param int   $team_id
     * @param array $args {
     *     @type int $anzahl Max. Zeilen, 0 = alle
     * }
     * @return array {
     *     @type string       $status 'ok' | 'empty' | 'not_visible' | 'error'
     *     @type WP_Error|string $error WP_Error bei status 'error' (leerer
     *                                  String sonst) - der aufrufende Code
     *                                  entscheidet rollenbasiert, wie viel
     *                                  davon angezeigt wird, siehe
     *                                  SMF_Shortcodes::error().
     *     @type array      $rows    Normalisierte, sortierte Zeilen (rank, first_name,
     *                               last_name, games, goals, assists, points, penalty_minutes)
     *     @type array      $columns Sichtbarkeit optionaler Spalten: goals, assists, penalty_minutes
     *     @type array|null $totals  { goals, assists, penalty_minutes } aus "totals", oder null
     * }
     */
    public static function get( $team_id, array $args = array() ) {
        $team_id = (int) $team_id;
        $anzahl  = isset( $args['anzahl'] ) ? max( 0, (int) $args['anzahl'] ) : 0;

        $result = array(
            'status'  => 'empty',
            'error'   => '',
            'rows'    => array(),
            'columns' => array( 'goals' => false, 'assists' => false, 'penalty_minutes' => false ),
            'totals'  => null,
        );

        $api  = new SMF_API();
        $data = $api->get_scorer( $team_id );

        if ( is_wp_error( $data ) ) {
            $result['status'] = 'error';
            $result['error']  = $data;
            return $result;
        }

        // scorer_visible ist eine serverseitige Vorgabe des Saisonmanagers
        // (z.B. wenn ein Verband die Anzeige für eine Liga sperrt) - nicht
        // per Shortcode übersteuerbar.
        if ( is_array( $data ) && array_key_exists( 'scorer_visible', $data ) && ! $data['scorer_visible'] ) {
            $result['status'] = 'not_visible';
            return $result;
        }

        $raw_rows = self::extract_rows( $data );
        if ( empty( $raw_rows ) ) {
            return $result; // status bleibt 'empty'
        }

        $rows = array();
        foreach ( $raw_rows as $entry ) {
            if ( ! is_array( $entry ) ) continue;

            $rows[] = array(
                'first_name'      => isset( $entry['first_name'] ) ? (string) $entry['first_name'] : '',
                'last_name'       => isset( $entry['last_name'] )  ? (string) $entry['last_name']  : '',
                'games'           => self::num( $entry, 'games' ),
                'goals'           => self::num( $entry, 'goals' ),
                'assists'         => self::num( $entry, 'assists' ),
                'points'          => self::num( $entry, 'scorer_points' ),
                'penalty_minutes' => self::num( $entry, 'penalty_minutes' ),
            );
        }

        if ( empty( $rows ) ) {
            return $result;
        }

        usort( $rows, array( __CLASS__, 'compare_rows' ) );

        // Rang vergeben: bei Punktgleichheit denselben Rang (1, 2, 2, 4 -
        // nicht 1, 2, 2, 3). Die feinere Sortierung (Tore, Spiele) bestimmt
        // nur die Reihenfolge innerhalb eines Rangs, nicht den Rang selbst.
        $prev_points = null;
        $prev_rank   = 0;
        foreach ( $rows as $i => &$row ) {
            $points = (int) $row['points'];
            if ( $prev_points !== null && $points === $prev_points ) {
                $row['rank'] = $prev_rank;
            } else {
                $row['rank'] = $i + 1;
                $prev_rank   = $i + 1;
            }
            $prev_points = $points;
        }
        unset( $row );

        if ( $anzahl > 0 ) {
            $rows = array_slice( $rows, 0, $anzahl );
        }

        // Spalten-Erkennung: unterscheidet "in jeder Zeile null/fehlend"
        // (Spalte ausblenden) von "überall 0" (Spalte bleibt, ist eine
        // echte Info).
        foreach ( array( 'goals', 'assists', 'penalty_minutes' ) as $col ) {
            foreach ( $rows as $row ) {
                if ( $row[ $col ] !== null ) {
                    $result['columns'][ $col ] = true;
                    break;
                }
            }
        }

        $result['status'] = 'ok';
        $result['rows']   = $rows;
        $result['totals'] = self::extract_totals( $data );

        return $result;
    }

    /**
     * "scorer"-Array aus der Antwort holen, tolerant gegenüber einer
     * zusätzlichen "data"-Wrapper-Ebene (wie bei anderen Endpunkten dieser
     * API üblich). Feldnamen anderer Endpunkte (table.json, schedule.json)
     * werden bewusst NICHT als Fallback herangezogen - laut Spezifikation
     * ist teams/{id}/stats ein flaches, numerisch indiziertes Array unter
     * "scorer".
     *
     * @param mixed $data
     * @return array
     */
    private static function extract_rows( $data ) {
        if ( ! is_array( $data ) ) return array();
        if ( isset( $data['scorer'] ) && is_array( $data['scorer'] ) ) {
            return array_values( $data['scorer'] );
        }
        if ( isset( $data['data']['scorer'] ) && is_array( $data['data']['scorer'] ) ) {
            return array_values( $data['data']['scorer'] );
        }
        return array();
    }

    /**
     * @param mixed $data
     * @return array|null { goals, assists, penalty_minutes } oder null
     */
    private static function extract_totals( $data ) {
        $totals = null;
        if ( isset( $data['totals'] ) && is_array( $data['totals'] ) ) {
            $totals = $data['totals'];
        } elseif ( isset( $data['data']['totals'] ) && is_array( $data['data']['totals'] ) ) {
            $totals = $data['data']['totals'];
        }
        if ( $totals === null ) return null;

        // totals.games (Anzahl Teamspiele) wird absichtlich nicht
        // übernommen - das Template darf daraus keine "Summe Spiele aller
        // Scorer" machen, das wäre eine falsche Zahl.
        return array(
            'goals'           => self::num( $totals, 'goals' ),
            'assists'         => self::num( $totals, 'assists' ),
            'penalty_minutes' => self::num( $totals, 'penalty_minutes' ),
        );
    }

    /**
     * Zahlenfeld defensiv lesen: fehlend/null/"" => null, sonst (int).
     *
     * @param array  $arr
     * @param string $key
     * @return int|null
     */
    private static function num( $arr, $key ) {
        if ( ! is_array( $arr ) || ! array_key_exists( $key, $arr ) ) return null;
        $val = $arr[ $key ];
        if ( $val === null || $val === '' ) return null;
        return (int) $val;
    }

    /**
     * Sortierung: scorer_points absteigend, Tiebreak goals absteigend,
     * dann games aufsteigend.
     *
     * @param array $a
     * @param array $b
     * @return int
     */
    private static function compare_rows( $a, $b ) {
        $pa = (int) $a['points'];
        $pb = (int) $b['points'];
        if ( $pa !== $pb ) return $pb - $pa;

        $ga = (int) $a['goals'];
        $gb = (int) $b['goals'];
        if ( $ga !== $gb ) return $gb - $ga;

        return (int) $a['games'] - (int) $b['games'];
    }
}
