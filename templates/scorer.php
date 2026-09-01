<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Template: Scorerliste eines Teams
 * Variablen:
 *   $result         (array)  Rückgabe von SMF_Scorer::get()
 *   $show_title     (bool)
 *   $namen          (string) '' | 'voll' | 'abgekuerzt' - Shortcode-Attribut,
 *                            überschreibt bei Angabe die Option smf_player_name_format
 *   $spalten        (string) 'voll' | 'kompakt'
 *   $show_totals    (bool)
 *   $names_disabled (bool)   Option "Personennamen anzeigen" ist aus
 */

$rows    = isset( $result['rows'] )    ? $result['rows']    : array();
$columns = isset( $result['columns'] ) ? $result['columns'] : array();
$totals  = isset( $result['totals'] )  ? $result['totals']  : null;
$status  = isset( $result['status'] )  ? $result['status']  : 'empty';

$compact = ( $spalten === 'kompakt' );

$show_goals   = ! $compact && ! empty( $columns['goals'] );
$show_assists = ! $compact && ! empty( $columns['assists'] );
$show_pim     = ! $compact && ! empty( $columns['penalty_minutes'] );

// Anzahl Spalten vor "Sp" (# + Name) - für den colspan der Summenzeile.
$lead_cols = 2;
?>

<div class="smf smf-scorer-wrapper">

    <?php if ( $show_title ) : ?>
        <div class="smf-header">
            <h3 class="smf-title"><?php echo esc_html( smf_label( 'scorer_list_title', 'Scorerliste' ) ); ?></h3>
        </div>
    <?php endif; ?>

    <?php if ( $names_disabled ) : ?>
        <p class="smf-notice"><?php echo esc_html( smf_label( 'scorer_names_hidden', 'Die Scorerliste mit Personennamen ist in den Einstellungen deaktiviert.' ) ); ?></p>

    <?php elseif ( $status === 'not_visible' ) : ?>
        <p class="smf-notice"><?php echo esc_html( smf_label( 'scorer_not_visible', 'Die Scorerliste ist für dieses Team nicht verfügbar.' ) ); ?></p>

    <?php elseif ( empty( $rows ) ) : ?>
        <p class="smf-notice"><?php echo esc_html( smf_label( 'scorer_no_data', 'Für dieses Team liegen noch keine Scorerpunkte vor.' ) ); ?></p>

    <?php else : ?>

        <div class="smf-table-scroll">
            <table class="smf-table smf-scorer-table">
                <thead>
                    <tr>
                        <th class="smf-col-rank">#</th>
                        <th class="smf-col-team smf-scorer-col-name">Name</th>
                        <th class="smf-col-num" title="Spiele">Sp</th>
                        <?php if ( $show_goals ) : ?><th class="smf-col-num" title="Tore">Tore</th><?php endif; ?>
                        <?php if ( $show_assists ) : ?><th class="smf-col-num" title="Assists">Ass</th><?php endif; ?>
                        <th class="smf-col-num smf-col-pts" title="Scorerpunkte">Pkt</th>
                        <?php if ( $show_pim ) : ?><th class="smf-col-num" title="Strafminuten">Str</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $rows as $i => $row ) :
                        $name = SMF_Scorer::format_player_name(
                            $row['first_name'],
                            $row['last_name'],
                            $namen !== '' ? $namen : null
                        );
                    ?>
                        <tr class="smf-table-row<?php echo $i % 2 === 0 ? ' smf-row-even' : ''; ?>">
                            <td class="smf-col-rank"><?php echo esc_html( $row['rank'] ); ?></td>
                            <td class="smf-col-team smf-scorer-name"><?php echo esc_html( $name ); ?></td>
                            <td class="smf-col-num"><?php echo esc_html( (int) $row['games'] ); ?></td>
                            <?php if ( $show_goals ) : ?>
                                <td class="smf-col-num"><?php echo esc_html( (int) $row['goals'] ); ?></td>
                            <?php endif; ?>
                            <?php if ( $show_assists ) : ?>
                                <td class="smf-col-num"><?php echo esc_html( (int) $row['assists'] ); ?></td>
                            <?php endif; ?>
                            <td class="smf-col-num smf-col-pts"><strong><?php echo esc_html( (int) $row['points'] ); ?></strong></td>
                            <?php if ( $show_pim ) : ?>
                                <td class="smf-col-num"><?php echo esc_html( (int) $row['penalty_minutes'] ); ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>

                <?php if ( $show_totals && $totals ) : ?>
                    <tfoot>
                        <tr class="smf-scorer-total">
                            <td class="smf-scorer-total-label" colspan="<?php echo esc_attr( $lead_cols ); ?>">
                                <?php echo esc_html( smf_label( 'scorer_totals_label', 'Team gesamt' ) ); ?>
                            </td>
                            <?php // "Sp" bewusst leer: totals.games ist die Anzahl Teamspiele, keine Summe der Spielereinsätze. ?>
                            <td class="smf-col-num">–</td>
                            <?php if ( $show_goals ) : ?>
                                <td class="smf-col-num"><?php echo esc_html( $totals['goals'] === null ? '–' : (int) $totals['goals'] ); ?></td>
                            <?php endif; ?>
                            <?php if ( $show_assists ) : ?>
                                <td class="smf-col-num"><?php echo esc_html( $totals['assists'] === null ? '–' : (int) $totals['assists'] ); ?></td>
                            <?php endif; ?>
                            <td class="smf-col-num smf-col-pts">–</td>
                            <?php if ( $show_pim ) : ?>
                                <td class="smf-col-num"><?php echo esc_html( $totals['penalty_minutes'] === null ? '–' : (int) $totals['penalty_minutes'] ); ?></td>
                            <?php endif; ?>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>

    <?php endif; ?>

    <?php smf_render_attribution(); ?>

</div>
