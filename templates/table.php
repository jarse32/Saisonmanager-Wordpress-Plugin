<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Template: Liga-Tabelle
 * Variablen: $table (array), $league_name (string), $show_title (bool),
 *            $show_logos (bool), $logo_size_class (string, siehe
 *            SMF_Shortcodes::logo_size_class()),
 *            $hervorheben (string, Rohwert des Attributs),
 *            $own_team_ids (int[], siehe SMF_Highlight::get_own_team_ids()),
 *            $stale_since (int|null - Zeitstempel, wenn $table aus der
 *            Notreserve kommt, siehe SMF_API::stale_since())
 *
 * Kein namen-Attribut hier (bewusst, siehe Umsetzungsauftrag Phase 0/1):
 * die Tabelle listet üblicherweise viele verschiedene Vereine, nicht
 * mehrere Teams desselben Vereins, und der Name bricht in der Zelle
 * bereits nativ um statt abgeschnitten zu werden - ein Ausblenden hätte
 * hier keinen Nutzen, nur Informationsverlust.
 *
 * Saisonmanager API Feldnamen:
 *   position, team_id, team_name, games, won, won_ot, lost_ot, lost,
 *   goals_scored, goals_received, points, team_logo_small
 */

$rows = array();
if ( isset( $table['teams'] ) ) {
    $rows = $table['teams'];
} elseif ( isset( $table['data'] ) ) {
    $rows = $table['data'];
} elseif ( is_array( $table ) && ! empty( $table ) && array_keys( $table ) === range( 0, count( $table ) - 1 ) ) {
    $rows = $table;
}
?>

<div class="smf smf-table-wrapper<?php echo ! empty( $logo_size_class ) ? ' ' . esc_attr( $logo_size_class ) : ''; ?>">

    <?php if ( $show_title && $league_name ) : ?>
        <div class="smf-header">
            <h3 class="smf-title"><?php echo esc_html( $league_name ); ?> – Tabelle</h3>
        </div>
    <?php endif; ?>

    <?php echo SMF_Shortcodes::stale_notice( $stale_since ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- stale_notice() escapt bereits intern ?>

    <?php if ( empty( $rows ) ) : ?>
        <p class="smf-notice"><?php echo esc_html( smf_label( 'no_table_data', 'Keine Tabellendaten verfügbar.' ) ); ?></p>
    <?php else : ?>

        <div class="smf-table-scroll">
            <table class="smf-table">
                <thead>
                    <tr>
                        <th class="smf-col-rank">#</th>
                        <th class="smf-col-team">Team</th>
                        <th class="smf-col-num" title="Spiele">Sp</th>
                        <th class="smf-col-num" title="Siege">S</th>
                        <th class="smf-col-num" title="Siege nach Verlängerung/Penalty">SV</th>
                        <th class="smf-col-num" title="Niederlagen nach Verlängerung/Penalty">NV</th>
                        <th class="smf-col-num" title="Niederlagen">N</th>
                        <th class="smf-col-num" title="Tore">Tore</th>
                        <th class="smf-col-num smf-col-pts" title="Punkte">Pkt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $rows as $i => $row ) :
                        $rank     = isset( $row['position'] )       ? $row['position']       : ( $i + 1 );
                        $team_id  = isset( $row['team_id'] )        ? (int) $row['team_id']  : 0;
                        $name     = isset( $row['team_name'] )      ? $row['team_name']      : '–';
                        $logo     = isset( $row['team_logo_small'] ) ? $row['team_logo_small'] : '';
                        $played   = isset( $row['games'] )          ? $row['games']          : 0;
                        $wins     = isset( $row['won'] )            ? $row['won']            : 0;
                        $wins_ot  = isset( $row['won_ot'] )         ? $row['won_ot']         : 0;
                        $loss_ot  = isset( $row['lost_ot'] )        ? $row['lost_ot']        : 0;
                        $losses   = isset( $row['lost'] )           ? $row['lost']           : 0;
                        $goals_f  = isset( $row['goals_scored'] )   ? $row['goals_scored']   : 0;
                        $goals_a  = isset( $row['goals_received'] ) ? $row['goals_received'] : 0;
                        $points   = isset( $row['points'] )         ? $row['points']         : 0;
                        $is_own   = SMF_Highlight::should_highlight( $team_id, $name, $hervorheben, $own_team_ids );
                    ?>
                        <tr class="smf-table-row<?php echo $i % 2 === 0 ? ' smf-row-even' : ''; ?><?php echo $is_own ? ' smf-row--own' : ''; ?>">
                            <td class="smf-col-rank"><?php echo esc_html( $rank ); ?></td>
                            <td class="smf-col-team smf-team-name">
                                <?php if ( $show_logos && $logo ) : ?>
                                    <img src="<?php echo esc_url( SMF_API::get_logo_url( $logo ) ); ?>" alt="" class="smf-team-logo" width="20" height="20" loading="lazy">
                                <?php endif; ?>
                                <?php echo esc_html( $name ); ?>
                                <?php if ( $is_own ) : ?>
                                    <span class="smf-visually-hidden"><?php echo esc_html( smf_label( 'own_team_label', ' (eigenes Team)' ) ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="smf-col-num"><?php echo esc_html( $played ); ?></td>
                            <td class="smf-col-num"><?php echo esc_html( $wins ); ?></td>
                            <td class="smf-col-num"><?php echo esc_html( $wins_ot ); ?></td>
                            <td class="smf-col-num"><?php echo esc_html( $loss_ot ); ?></td>
                            <td class="smf-col-num"><?php echo esc_html( $losses ); ?></td>
                            <td class="smf-col-num"><?php echo esc_html( $goals_f . ':' . $goals_a ); ?></td>
                            <td class="smf-col-num smf-col-pts"><strong><?php echo esc_html( $points ); ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>

</div>
