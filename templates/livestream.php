<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Template: Großer Livestream-/Aufzeichnungs-Player (Shortcode sm_livestream, v1.9.0)
 *
 * Variablen: $game (array - ausgewähltes Spiel, siehe
 *            SMF_API::get_livestream_target_game()), $stream (array:
 *            kind/parsed/embed_url, siehe SMF_Shortcodes::shortcode_livestream()),
 *            $section_title (string, "Livestream"/"Aufzeichnung"),
 *            $titel (string, frei, optionales Attribut - zusätzlich zu
 *            $section_title, ersetzt es nicht), $stale_since (int|null -
 *            siehe SMF_API::stale_since(); steuert hier zusätzlich das
 *            Staleness-Gate fürs LIVE-Abzeichen, analog zu single-game.php)
 *
 * Nutzt für den eigentlichen Player dieselbe Funktion wie das
 * Spieldetail-Modal (smf_render_stream_embed() im Hauptplugin) - Markup,
 * Zwei-Klick-Reveal und "Einwilligung merken" bleiben so an einer Stelle.
 */

$home_name = isset( $game['home_team_name'] )  ? $game['home_team_name']  : '?';
$away_name = isset( $game['guest_team_name'] ) ? $game['guest_team_name'] : '?';

$embed_title = $section_title . ': ' . $home_name . ' – ' . $away_name;

$date_ts = SMF_API::parse_game_date( $game );
$game_tz = SMF_API::game_timezone();

$status = SMF_Game_Status::status( $game );
// Staleness-Gate wie in single-game.php: teams/{id}/matches hat (anders als
// das per AJAX geladene games/{id} im Modal) einen Stale-Spiegel - ein
// "läuft gerade" auf einem möglicherweise veralteten Stand wäre erfunden.
$display_status = ( $status === 'running' && $stale_since ) ? 'upcoming' : $status;

$show_badge = ( $display_status === 'running' ) && get_option( 'smf_live_badge_enabled', 'an' ) === 'an';
?>
<div class="smf smf-livestream">

    <div class="smf-header smf-livestream__header">
        <?php if ( $titel !== '' ) : ?>
            <h3 class="smf-title"><?php echo esc_html( $titel ); ?></h3>
        <?php endif; ?>
        <span class="smf-livestream__label"><?php echo esc_html( $section_title ); ?></span>
    </div>

    <div class="smf-livestream__body">

        <div class="smf-livestream__info">
            <span class="smf-livestream__matchup"><?php echo esc_html( $home_name . ' – ' . $away_name ); ?></span>
            <?php if ( $show_badge ) : ?>
                <?php echo smf_game_status_badge_html( $display_status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapt bereits intern ?>
            <?php endif; ?>
        </div>

        <?php if ( $date_ts ) : ?>
            <div class="smf-livestream__date">
                <?php // wp_date() statt date_i18n(), siehe SMF_API::game_timezone() ?>
                <?php echo esc_html( wp_date( 'l, d. F Y', $date_ts, $game_tz ) ); ?>
                <?php if ( ! empty( $game['time'] ) ) : ?>
                    &ndash; <?php echo esc_html( $game['time'] . ' ' . smf_label( 'time_suffix', 'Uhr' ) ); ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php echo smf_render_stream_embed( $stream, $embed_title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapt bereits intern ?>
    </div>
</div>

<?php echo SMF_Shortcodes::stale_notice( $stale_since ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapt bereits intern ?>
