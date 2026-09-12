<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Template: Spielliste
 * Variablen: $games (array), $league_name (string), $show_title (bool), $modus (string),
 *            $stale_since (int|null - siehe SMF_API::stale_since())
 *
 * Saisonmanager API Feldnamen:
 *   game_id, date (YYYY-MM-DD), time (HH:MM), game_day,
 *   home_team_name, guest_team_name,
 *   result.home_goals, result.guest_goals,
 *   arena_name, ended, started
 */

$api = new SMF_API();

$title_map = array(
    'alle'      => smf_label( 'games_list_title_alle', 'Alle Spiele' ),
    'vergangen' => smf_label( 'games_list_title_vergangen', 'Vergangene Spiele' ),
    'kommend'   => smf_label( 'games_list_title_kommend', 'Kommende Spiele' ),
);
$section_title = isset( $title_map[ $modus ] ) ? $title_map[ $modus ] : smf_label( 'games_list_title_default', 'Spiele' );
?>

<div class="smf smf-games-wrapper">

    <?php if ( $show_title ) : ?>
        <div class="smf-header">
            <h3 class="smf-title">
                <?php if ( $league_name ) : ?>
                    <?php echo esc_html( $league_name ); ?> –
                <?php endif; ?>
                <?php echo esc_html( $section_title ); ?>
            </h3>
        </div>
    <?php endif; ?>

    <?php echo SMF_Shortcodes::stale_notice( $stale_since ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- stale_notice() escapt bereits intern ?>

    <?php if ( empty( $games ) ) : ?>
        <p class="smf-notice"><?php echo esc_html( smf_label( 'no_games_found', 'Keine Spiele gefunden.' ) ); ?></p>
    <?php else : ?>

        <div class="smf-games-list">
            <?php foreach ( $games as $game ) :
                $game_id    = isset( $game['game_id'] )         ? $game['game_id']         : 0;
                $has_result = $api->has_result( $game );
                $date_ts    = $api->parse_game_date( $game );

                $home_name  = isset( $game['home_team_name'] )     ? $game['home_team_name']     : '?';
                $away_name  = isset( $game['guest_team_name'] )    ? $game['guest_team_name']    : '?';
                $home_score = isset( $game['result']['home_goals'] )  ? $game['result']['home_goals']  : null;
                $away_score = isset( $game['result']['guest_goals'] ) ? $game['result']['guest_goals'] : null;
                $venue      = isset( $game['arena_name'] )         ? $game['arena_name']         : ( isset( $game['arena'] ) ? $game['arena'] : '' );
                $game_day   = isset( $game['game_day'] )           ? $game['game_day']           : '';
                $home_logo  = $show_logos ? SMF_API::get_logo_url( isset( $game['home_team_small_logo'] ) ? $game['home_team_small_logo'] : '' ) : '';
                $away_logo  = $show_logos ? SMF_API::get_logo_url( isset( $game['guest_team_small_logo'] ) ? $game['guest_team_small_logo'] : '' ) : '';
            ?>

                <div class="smf-game-card<?php echo $has_result ? ' smf-game--played' : ' smf-game--upcoming'; ?>"
                     <?php if ( $game_id ) : ?>
                         data-game-id="<?php echo esc_attr( $game_id ); ?>"
                         role="button"
                         tabindex="0"
                         aria-label="Spieldetails: <?php echo esc_attr( $home_name . ' vs ' . $away_name ); ?>"
                     <?php endif; ?>>

                    <!-- Datum & Infos -->
                    <div class="smf-game-meta">
                        <?php if ( $date_ts ) : ?>
                            <span class="smf-game-date">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                <?php echo esc_html( date_i18n( 'd.m.Y', $date_ts ) ); ?>
                                <?php if ( ! empty( $game['time'] ) ) : ?>
                                    <span class="smf-game-time"><?php echo esc_html( $game['time'] . ' ' . smf_label( 'time_suffix', 'Uhr' ) ); ?></span>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                        <?php if ( $game_day ) : ?>
                            <span class="smf-game-day"><?php echo esc_html( smf_label( 'game_day_prefix', 'Spieltag' ) . ' ' . $game_day ); ?></span>
                        <?php endif; ?>
                        <?php if ( $venue ) : ?>
                            <span class="smf-game-venue">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                <?php echo esc_html( $venue ); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Spielergebnis / Matchup -->
                    <div class="smf-game-matchup">
                        <!-- Home: Name links, Logo rechts (nah am Score) -->
                        <div class="smf-game-team smf-game-team--home">
                            <span class="smf-team-name"><?php echo esc_html( $home_name ); ?></span>
                            <?php if ( $home_logo ) : ?>
                                <img src="<?php echo esc_url( $home_logo ); ?>" alt="<?php echo esc_attr( $home_name ); ?>" class="smf-game-team-logo" loading="lazy">
                            <?php endif; ?>
                        </div>

                        <div class="smf-game-score">
                            <?php if ( $has_result ) : ?>
                                <span class="smf-score"><?php echo esc_html( $home_score . ' : ' . $away_score ); ?></span>
                            <?php else : ?>
                                <span class="smf-score-vs"><?php echo esc_html( smf_label( 'vs_label', 'vs.' ) ); ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Away: Logo links (nah am Score), Name rechts -->
                        <div class="smf-game-team smf-game-team--away">
                            <?php if ( $away_logo ) : ?>
                                <img src="<?php echo esc_url( $away_logo ); ?>" alt="<?php echo esc_attr( $away_name ); ?>" class="smf-game-team-logo" loading="lazy">
                            <?php endif; ?>
                            <span class="smf-team-name"><?php echo esc_html( $away_name ); ?></span>
                        </div>
                    </div>

                    <?php if ( $game_id ) : ?>
                        <div class="smf-game-action">
                            <span class="smf-detail-link">
                                <?php echo esc_html( $has_result ? smf_label( 'detail_link_played', 'Spielbericht' ) : smf_label( 'detail_link_upcoming', 'Details' ) ); ?>
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                            </span>
                        </div>
                    <?php endif; ?>

                </div>

            <?php endforeach; ?>
        </div>

    <?php endif; ?>

    <?php smf_render_attribution(); ?>

</div>

<?php if ( ! defined( 'SMF_MODAL_RENDERED' ) ) : define( 'SMF_MODAL_RENDERED', true ); ?>
<div id="smf-modal" class="smf-modal" role="dialog" aria-modal="true" style="display:none;">
    <div class="smf-modal-overlay"></div>
    <div class="smf-modal-content">
        <button class="smf-modal-close" aria-label="Schließen">&times;</button>
        <div id="smf-modal-body"></div>
    </div>
</div>
<?php endif; ?>
