<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Zentrale Ableitung des Spielstatus (canceled/upcoming/running/ended) -
 * alle Stellen, die Spiele rendern oder danach filtern, fragen hier an
 * statt started/ended/result/notice_type selbst zu interpretieren, analog
 * zu SMF_Highlight.
 *
 * "running" wird NICHT aus dem Spielbericht-Status abgeleitet (games/{id}
 * liefert z.B. game_status="match_record_closed", unabhängig vom
 * tatsächlichen Spielverlauf), sondern ausschließlich aus started/ended -
 * ein angepfiffenes Spiel ohne angelegten Spielbericht gilt korrekt als
 * laufend (siehe docs/saisonmanager-api-uebersicht.md im Website-Repo,
 * Abschnitt zum /live_streams-Endpunkt, dieselbe Ableitungsregel).
 */
class SMF_Game_Status {

    /**
     * Obergrenze, ab der ein started=true/ended=false-Spiel nicht mehr als
     * "läuft gerade" gilt, siehe is_running(). Schützt gegen ein Spiel, bei
     * dem der Verband ended nie setzt (z.B. Spielbericht nie
     * abgeschlossen) - ohne diese Obergrenze bliebe es dauerhaft
     * "nächstes Spiel" und würde das tatsächlich nächste Spiel verdrängen.
     * Stichprobe über ~1.280 Spiele aus 35 Ligen (aktuelle und vorherige
     * Saison, September 2026) fand keinen einzigen solchen Fall, das
     * Risiko bleibt aber real genug für eine feste Obergrenze statt einer
     * Option - siehe Umsetzungsauftrag.
     */
    const RUNNING_MAX_AGE = 4 * HOUR_IN_SECONDS;

    /**
     * @param array    $game
     * @param int|null $now Referenzzeitpunkt (Unix-Timestamp), Default
     *                      time() - nur zum deterministischen Testen mit
     *                      einer festen Zeit überschreiben, nicht im
     *                      Produktivcode.
     * @return string 'canceled' | 'upcoming' | 'running' | 'ended'
     */
    public static function status( array $game, $now = null ): string {
        // Vor allem anderen: eine Absage überschreibt jeden anderen Status,
        // auch falls started/ended/result widersprüchlich befüllt wären
        // (z.B. ein kampflos gewertetes Spiel). Einziger bekannter Wert
        // laut Doku ist "Canceled" - andere Werte (Verlegung o.ä., falls es
        // sie gibt) bleiben bewusst unbehandelt statt geraten.
        if ( isset( $game['notice_type'] ) && $game['notice_type'] === 'Canceled' ) {
            return 'canceled';
        }

        $now = $now ?? time();

        if ( SMF_API::has_result( $game ) ) {
            return 'ended';
        }

        if ( self::is_running( $game, $now ) ) {
            return 'running';
        }

        return 'upcoming';
    }

    /**
     * Ob ein Spiel aktuell als "läuft" gilt: angepfiffen, noch nicht
     * beendet, und der Anstoß liegt höchstens RUNNING_MAX_AGE zurück. Ein
     * fehlendes/unparsbares Datum (SMF_API::parse_game_date() liefert dann
     * false, z.B. bei "TBD") zählt bewusst NICHT als laufend - ohne
     * verlässliches Datum lässt sich das Zeitfenster nicht prüfen.
     *
     * Eigenständig nutzbar (nicht nur über status()), weil
     * SMF_API::filter_past_games()/filter_upcoming_games() exakt dieselbe
     * Grenze für zwei unterschiedliche Entscheidungen brauchen - sonst
     * könnte ein Spiel kurzzeitig in keinem oder in beiden Filtern
     * gleichzeitig auftauchen.
     *
     * @param array $game
     * @param int   $now
     * @return bool
     */
    public static function is_running( array $game, int $now ): bool {
        if ( empty( $game['started'] ) || ! empty( $game['ended'] ) ) {
            return false;
        }
        $date = SMF_API::parse_game_date( $game );
        return $date && ( $now - $date ) <= self::RUNNING_MAX_AGE;
    }
}
