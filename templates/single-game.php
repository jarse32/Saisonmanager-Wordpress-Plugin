<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Template: Einzelnes Spiel (nächstes / letztes)
 * Variablen: $game, $league_name, $label, $show_logos
 */

$api = new SMF_API();

$game_id    = isset( $game['game_id'] )  ? $game['game_id']  : 0;
$has_result = $api->has_result( $game );
$date_ts    = $api->parse_game_date( $game );

$home_name  = isset( $game['home_team_name'] )     ? $game['home_team_name']     : '?';
$away_name  = isset( $game['guest_team_name'] )    ? $game['guest_team_name']    : '?';
$home_score = isset( $game['result']['home_goals'] )  ? $game['result']['home_goals']  : null;
$away_score = isset( $game['result']['guest_goals'] ) ? $game['result']['guest_goals'] : null;
$venue      = isset( $game['arena_name'] )         ? $game['arena_name']         : ( isset( $game['arena'] ) ? $game['arena'] : '' );
$game_day   = isset( $game['game_day'] )           ? $game['game_day']           : '';

$home_logo  = '';
$away_logo  = '';
if ( $show_logos ) {
    $logo_field = 'home_team_small_logo';
    $raw = isset( $game[ $logo_field ] ) ? $game[ $logo_field ] : ( isset( $game['home_team_logo'] ) ? $game['home_team_logo'] : '' );
    $home_logo = SMF_API::get_logo_url( $raw );

    $logo_field = 'guest_team_small_logo';
    $raw = isset( $game[ $logo_field ] ) ? $game[ $logo_field ] : ( isset( $game['guest_team_logo'] ) ? $game['guest_team_logo'] : '' );
    $away_logo = SMF_API::get_logo_url( $raw );
}
?>

<div class="smf smf-single-game<?php echo $has_result ? ' smf-game--played' : ' smf-game--upcoming'; ?><?php echo $show_logos ? ' smf-single-game--with-logos' : ''; ?>"
     <?php if ( $game_id ) : ?>
         data-game-id="<?php echo esc_attr( $game_id ); ?>"
         role="button"
         tabindex="0"
     <?php endif; ?>>

    <!-- Label + Liga -->
    <div class="smf-single-game__label">
        <span><?php echo esc_html( $label ); ?></span>
        <?php if ( $league_name ) : ?>
            <span class="smf-single-game__league"><?php echo esc_html( $league_name ); ?></span>
        <?php endif; ?>
    </div>

    <!-- Datum prominent über dem Matchup -->
    <?php if ( $date_ts ) : ?>
        <div class="smf-single-game__date-row">
            <span class="smf-single-game__date-day"><?php echo esc_html( date_i18n( 'l', $date_ts ) ); ?></span>
            <span class="smf-single-game__date-full"><?php echo esc_html( date_i18n( 'd. F Y', $date_ts ) ); ?></span>
            <?php if ( ! $has_result && ! empty( $game['time'] ) ) : ?>
                <span class="smf-single-game__date-time"><?php echo esc_html( $game['time'] ); ?> Uhr</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Matchup -->
    <div class="smf-single-game__matchup">

        <div class="smf-single-game__team smf-single-game__team--home">
            <?php if ( $home_logo ) : ?>
                <div class="smf-single-game__logo-wrap">
                    <img src="<?php echo esc_url( $home_logo ); ?>"
                         alt="<?php echo esc_attr( $home_name ); ?>"
                         class="smf-single-game__logo"
                         loading="lazy">
                </div>
            <?php endif; ?>
            <span class="smf-single-game__team-name"><?php echo esc_html( $home_name ); ?></span>
        </div>

        <div class="smf-single-game__center">
            <?php if ( $has_result ) : ?>
                <div class="smf-single-game__score"><?php echo esc_html( $home_score . ' : ' . $away_score ); ?></div>
            <?php else : ?>
                <div class="smf-single-game__score smf-single-game__score--upcoming">vs.</div>
            <?php endif; ?>
        </div>

        <div class="smf-single-game__team smf-single-game__team--away">
            <?php if ( $away_logo ) : ?>
                <div class="smf-single-game__logo-wrap">
                    <img src="<?php echo esc_url( $away_logo ); ?>"
                         alt="<?php echo esc_attr( $away_name ); ?>"
                         class="smf-single-game__logo"
                         loading="lazy">
                </div>
            <?php endif; ?>
            <span class="smf-single-game__team-name"><?php echo esc_html( $away_name ); ?></span>
        </div>

    </div>

    <?php if ( $venue || $game_day ) : ?>
        <div class="smf-single-game__footer">
            <?php if ( $venue ) : ?>
                <span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <?php echo esc_html( $venue ); ?>
                </span>
            <?php endif; ?>
            <?php if ( $game_day ) : ?>
                <span>Spieltag <?php echo esc_html( $game_day ); ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ( $game_id ) : ?>
        <div class="smf-single-game__action">
            <span class="smf-btn"><?php echo $has_result ? 'Spielbericht ansehen' : 'Details ansehen'; ?></span>
        </div>
    <?php endif; ?>

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
