<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Template: Liga-Tabelle
 * Variablen: $table (array), $league_name (string), $show_title (bool)
 *
 * Saisonmanager API Feldnamen:
 *   position, team_name, games, won, won_ot, lost_ot, lost,
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

<div class="smf smf-table-wrapper">

    <?php if ( $show_title && $league_name ) : ?>
        <div class="smf-header">
            <h3 class="smf-title"><?php echo esc_html( $league_name ); ?> – Tabelle</h3>
        </div>
    <?php endif; ?>

    <?php if ( empty( $rows ) ) : ?>
        <p class="smf-notice">Keine Tabellendaten verfügbar.</p>
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
                    ?>
                        <tr class="smf-table-row<?php echo $i % 2 === 0 ? ' smf-row-even' : ''; ?>">
                            <td class="smf-col-rank"><?php echo esc_html( $rank ); ?></td>
                            <td class="smf-col-team smf-team-name">
                                <?php if ( $show_logos && $logo ) : ?>
                                    <img src="<?php echo esc_url( SMF_API::get_logo_url( $logo ) ); ?>" alt="" class="smf-team-logo" width="20" height="20" loading="lazy">
                                <?php endif; ?>
                                <?php echo esc_html( $name ); ?>
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
