<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Zentrale Entscheidung "ist dieses Team ein eigenes?" für das
 * Team-Highlighting (Shortcode-Attribut hervorheben). Alle Templates
 * fragen hier an statt eine eigene Vergleichslogik mitzubringen.
 */
class SMF_Highlight {

    /**
     * Team-IDs aller konfigurierten Vereine - Grundlage der Automatik, wenn
     * das hervorheben-Attribut leer ist. Ein Aufruf pro Shortcode-Rendering
     * reicht, das Ergebnis wird an should_highlight() durchgereicht statt
     * pro Zeile neu zu laden.
     *
     * @return int[]
     */
    public static function get_own_team_ids(): array {
        return SMF_ClubOverview::get_all_own_team_ids();
    }

    /**
     * Entscheidet, ob ein Team hervorgehoben werden soll.
     *
     * Im Zweifel nicht hervorheben: eine unbekannte/leere Team-ID (0) matcht
     * nie, auch nicht zufällig gegen eine ebenso leere own_team_ids-Liste.
     *
     * @param int|string $team_id         Team-ID der Zeile, 0/leer wenn unbekannt
     * @param string     $team_name       Name der Zeile (für den Namensfragment-Override)
     * @param string     $hervorheben_att Rohwert des hervorheben-Attributs:
     *                                    '' = Automatik, 'false' = aus,
     *                                    numerisch = genau diese Team-ID,
     *                                    sonst = Namensfragment
     * @param int[]      $own_team_ids    Ergebnis von get_own_team_ids()
     * @return bool
     */
    public static function should_highlight( $team_id, $team_name, $hervorheben_att, array $own_team_ids ): bool {
        $hervorheben_att = trim( (string) $hervorheben_att );

        if ( $hervorheben_att === 'false' ) {
            return false;
        }

        if ( $hervorheben_att !== '' ) {
            if ( ctype_digit( $hervorheben_att ) ) {
                $team_id = (int) $team_id;
                return $team_id > 0 && $team_id === (int) $hervorheben_att;
            }
            // Namensfragment-Override: dieselbe Vergleichslogik wie
            // SMF_API::filter_by_team(), nicht daneben neu erfunden.
            return SMF_API::name_matches( (string) $team_name, $hervorheben_att );
        }

        $team_id = (int) $team_id;
        return $team_id > 0 && in_array( $team_id, $own_team_ids, true );
    }
}
