<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Template: Vereinsübersicht
 * Variablen: $club_name (string), $upcoming (array), $played (array)
 */

$api = new SMF_API();

/**
 * Einzelne kompakte Spielkarte ausgeben
 *
 * @param array   $game
 * @param SMF_API $api
 */
$render_game = function ( array $game ) use ( $api ) {
    $game_id    = isset( $game['game_id'] )              ? $game['game_id']              : 0;
    $has_result = $api->has_result( $game );
    $date_ts    = $api->parse_game_date( $game );
    $liga_name  = isset( $game['_liga_name'] )           ? $game['_liga_name']           : '';
    $api_url    = isset( $game['_api_url'] )             ? $game['_api_url']             : '';

    $home_name  = isset( $game['home_team_name'] )       ? $game['home_team_name']       : '?';
    $away_name  = isset( $game['guest_team_name'] )      ? $game['guest_team_name']      : '?';
    $home_score = isset( $game['result']['home_goals'] ) ? $game['result']['home_goals'] : null;
    $away_score = isset( $game['result']['guest_goals'] )? $game['result']['guest_goals']: null;

    $home_logo = SMF_API::get_logo_url( isset( $game['home_team_small_logo'] )  ? $game['home_team_small_logo']  : '', $api_url );
    $away_logo = SMF_API::get_logo_url( isset( $game['guest_team_small_logo'] ) ? $game['guest_team_small_logo'] : '', $api_url );

    $card_class = 'smf-co-game' . ( $has_result ? ' smf-co-game--played' : ' smf-co-game--upcoming' );
    ?>
    <div class="<?php echo esc_attr( $card_class ); ?>"
         <?php if ( $game_id ) : ?>
             data-game-id="<?php echo esc_attr( $game_id ); ?>"
             <?php if ( $api_url ) : ?>data-api-url="<?php echo esc_attr( $api_url ); ?>"<?php endif; ?>
             role="button"
             tabindex="0"
             aria-label="Spieldetails: <?php echo esc_attr( $home_name . ' vs ' . $away_name ); ?>"
         <?php endif; ?>>

        <!-- Liga-Badge + Datum -->
        <div class="smf-co-game__top">
            <?php if ( $liga_name ) : ?>
                <span class="smf-co-badge"><?php echo esc_html( $liga_name ); ?></span>
            <?php endif; ?>
            <?php if ( $date_ts ) : ?>
                <span class="smf-co-date">
                    <?php echo esc_html( date_i18n( 'D, d.m.', $date_ts ) ); ?>
                    <?php if ( ! $has_result && ! empty( $game['time'] ) ) : ?>
                        <span class="smf-co-time"><?php echo esc_html( $game['time'] ); ?></span>
                    <?php endif; ?>
                </span>
            <?php endif; ?>
        </div>

        <!-- Matchup: Heimteam · Score/vs · Gastteam -->
        <div class="smf-co-game__matchup">

            <div class="smf-co-game__team smf-co-game__team--home">
                <?php if ( $home_logo ) : ?>
                    <img src="<?php echo esc_url( $home_logo ); ?>"
                         alt="<?php echo esc_attr( $home_name ); ?>"
                         class="smf-co-game__logo" loading="lazy">
                <?php endif; ?>
                <span class="smf-co-game__name"><?php echo esc_html( $home_name ); ?></span>
            </div>

            <div class="smf-co-game__center">
                <?php if ( $has_result && $home_score !== null && $away_score !== null ) : ?>
                    <span class="smf-co-score"><?php echo esc_html( $home_score . ':' . $away_score ); ?></span>
                <?php else : ?>
                    <span class="smf-co-vs">vs</span>
                <?php endif; ?>
            </div>

            <div class="smf-co-game__team smf-co-game__team--away">
                <span class="smf-co-game__name"><?php echo esc_html( $away_name ); ?></span>
                <?php if ( $away_logo ) : ?>
                    <img src="<?php echo esc_url( $away_logo ); ?>"
                         alt="<?php echo esc_attr( $away_name ); ?>"
                         class="smf-co-game__logo" loading="lazy">
                <?php endif; ?>
            </div>

        </div>

    </div>
    <?php
};
?>

<div class="smf smf-club-overview">

    <div class="smf-header smf-co-header">
        <h3 class="smf-title"><?php echo esc_html( $club_name ); ?></h3>
    </div>

    <div class="smf-co-grid">

        <!-- Anstehende Spiele -->
        <div class="smf-co-col smf-co-col--upcoming">
            <div class="smf-co-col-header">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Anstehende Spiele
            </div>
            <?php if ( empty( $upcoming ) ) : ?>
                <p class="smf-co-empty">Keine kommenden Spiele gefunden.</p>
            <?php else : ?>
                <div class="smf-co-games">
                    <?php foreach ( $upcoming as $game ) : $render_game( $game ); endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Letzte Ergebnisse -->
        <div class="smf-co-col smf-co-col--played">
            <div class="smf-co-col-header">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                Letzte Ergebnisse
            </div>
            <?php if ( empty( $played ) ) : ?>
                <p class="smf-co-empty">Noch keine Ergebnisse vorhanden.</p>
            <?php else : ?>
                <div class="smf-co-games">
                    <?php foreach ( $played as $game ) : $render_game( $game ); endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

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
