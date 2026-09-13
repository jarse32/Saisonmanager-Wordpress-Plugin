<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Template: Vereinsübersicht
 * Variablen: $club_name (string), $upcoming (array), $played (array),
 *            $stale_since (int|null), $partial_error_count (int) - siehe
 *            SMF_ClubOverview::get_games()
 *            $show_names (bool), $logo_size_class (string, siehe
 *            SMF_Shortcodes::logo_size_class())
 *            $hervorheben (string, Rohwert des Attributs - Standard "false",
 *            siehe class-shortcodes.php), $own_team_ids (int[], siehe
 *            SMF_Highlight::get_own_team_ids())
 *
 * Logos sind hier immer an (kein logos-Attribut, siehe class-shortcodes.php)
 * - $show_names pro Team daher nur maßgeblich, wenn für dieses Team
 * tatsächlich ein Logo vorliegt, sonst bleibt der Name sichtbar.
 */

$api = new SMF_API();

/**
 * Einzelne kompakte Spielkarte ausgeben
 *
 * @param array   $game
 * @param SMF_API $api
 * @param bool    $show_names
 * @param string  $hervorheben
 * @param int[]   $own_team_ids
 */
$render_game = function ( array $game ) use ( $api, $show_names, $hervorheben, $own_team_ids ) {
    $game_id    = isset( $game['game_id'] )              ? $game['game_id']              : 0;
    $has_result = $api->has_result( $game );
    $date_ts    = $api->parse_game_date( $game );
    $liga_name  = isset( $game['_liga_name'] )           ? $game['_liga_name']           : '';

    $home_name  = isset( $game['home_team_name'] )       ? $game['home_team_name']       : '?';
    $away_name  = isset( $game['guest_team_name'] )      ? $game['guest_team_name']      : '?';
    $home_score = isset( $game['result']['home_goals'] ) ? $game['result']['home_goals'] : null;
    $away_score = isset( $game['result']['guest_goals'] )? $game['result']['guest_goals']: null;

    $home_logo = SMF_API::get_logo_url( isset( $game['home_team_small_logo'] )  ? $game['home_team_small_logo']  : '' );
    $away_logo = SMF_API::get_logo_url( isset( $game['guest_team_small_logo'] ) ? $game['guest_team_small_logo'] : '' );

    // Name pro Team nur ausblenden, wenn für GENAU dieses Team ein Logo da
    // ist - sonst stünde in der Karte nichts.
    $show_home_name = $show_names || ! $home_logo;
    $show_away_name = $show_names || ! $away_logo;

    $home_team_id = isset( $game['home_team_id'] )  ? (int) $game['home_team_id']  : 0;
    $away_team_id = isset( $game['guest_team_id'] ) ? (int) $game['guest_team_id'] : 0;
    $home_is_own  = SMF_Highlight::should_highlight( $home_team_id, $home_name, $hervorheben, $own_team_ids );
    $away_is_own  = SMF_Highlight::should_highlight( $away_team_id, $away_name, $hervorheben, $own_team_ids );

    $card_class = 'smf-co-game' . ( $has_result ? ' smf-co-game--played' : ' smf-co-game--upcoming' );
    ?>
    <div class="<?php echo esc_attr( $card_class ); ?>"
         <?php if ( $game_id ) : ?>
             data-game-id="<?php echo esc_attr( $game_id ); ?>"
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

            <div class="smf-co-game__team smf-co-game__team--home<?php echo $home_is_own ? ' smf-co-game__team--own' : ''; ?>">
                <?php if ( $home_logo ) : ?>
                    <img src="<?php echo esc_url( $home_logo ); ?>"
                         alt="<?php echo esc_attr( $home_name ); ?>"
                         <?php if ( ! $show_home_name ) : ?>title="<?php echo esc_attr( $home_name ); ?>"<?php endif; ?>
                         class="smf-co-game__logo" loading="lazy">
                <?php endif; ?>
                <?php if ( $show_home_name ) : ?>
                    <span class="smf-co-game__name"><?php echo esc_html( $home_name ); ?></span>
                <?php endif; ?>
                <?php if ( $home_is_own ) : ?>
                    <span class="smf-visually-hidden"><?php echo esc_html( smf_label( 'own_team_label', ' (eigenes Team)' ) ); ?></span>
                <?php endif; ?>
            </div>

            <div class="smf-co-game__center">
                <?php if ( $has_result && $home_score !== null && $away_score !== null ) : ?>
                    <span class="smf-co-score"><?php echo esc_html( $home_score . ':' . $away_score ); ?></span>
                <?php else : ?>
                    <span class="smf-co-vs"><?php echo esc_html( smf_label( 'vs_label_compact', 'vs' ) ); ?></span>
                <?php endif; ?>
            </div>

            <div class="smf-co-game__team smf-co-game__team--away<?php echo $away_is_own ? ' smf-co-game__team--own' : ''; ?>">
                <?php if ( $show_away_name ) : ?>
                    <span class="smf-co-game__name"><?php echo esc_html( $away_name ); ?></span>
                <?php endif; ?>
                <?php if ( $away_logo ) : ?>
                    <img src="<?php echo esc_url( $away_logo ); ?>"
                         alt="<?php echo esc_attr( $away_name ); ?>"
                         <?php if ( ! $show_away_name ) : ?>title="<?php echo esc_attr( $away_name ); ?>"<?php endif; ?>
                         class="smf-co-game__logo" loading="lazy">
                <?php endif; ?>
                <?php if ( $away_is_own ) : ?>
                    <span class="smf-visually-hidden"><?php echo esc_html( smf_label( 'own_team_label', ' (eigenes Team)' ) ); ?></span>
                <?php endif; ?>
            </div>

        </div>

    </div>
    <?php
};
?>

<div class="smf smf-club-overview<?php echo ! empty( $logo_size_class ) ? ' ' . esc_attr( $logo_size_class ) : ''; ?>">

    <div class="smf-header smf-co-header">
        <h3 class="smf-title"><?php echo esc_html( $club_name ); ?></h3>
    </div>

    <?php echo SMF_Shortcodes::stale_notice( $stale_since ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- stale_notice() escapt bereits intern ?>

    <?php if ( $partial_error_count > 0 ) : ?>
        <p class="smf-notice">
            <?php echo esc_html( sprintf(
                /* translators: %d: Anzahl der Teams, für die aktuell keine Daten geladen werden konnten */
                smf_label( 'club_overview_partial_error', 'Für %d Team(s) konnten aktuell keine Daten geladen werden.' ),
                $partial_error_count
            ) ); ?>
        </p>
    <?php endif; ?>

    <div class="smf-co-grid">

        <!-- Anstehende Spiele -->
        <div class="smf-co-col smf-co-col--upcoming">
            <div class="smf-co-col-header">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <?php echo esc_html( smf_label( 'club_overview_upcoming_title', 'Anstehende Spiele' ) ); ?>
            </div>
            <?php if ( empty( $upcoming ) ) : ?>
                <p class="smf-co-empty"><?php echo esc_html( smf_label( 'club_overview_no_upcoming', 'Keine kommenden Spiele gefunden.' ) ); ?></p>
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
                <?php echo esc_html( smf_label( 'club_overview_played_title', 'Letzte Ergebnisse' ) ); ?>
            </div>
            <?php if ( empty( $played ) ) : ?>
                <p class="smf-co-empty"><?php echo esc_html( smf_label( 'club_overview_no_played', 'Noch keine Ergebnisse vorhanden.' ) ); ?></p>
            <?php else : ?>
                <div class="smf-co-games">
                    <?php foreach ( $played as $game ) : $render_game( $game ); endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

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
