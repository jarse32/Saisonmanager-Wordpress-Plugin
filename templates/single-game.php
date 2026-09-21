<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Template: Einzelnes Spiel (nächstes / letztes)
 * Variablen: $game, $league_name, $label, $running_label (string|null),
 *            $show_logos, $show_names,
 *            $logo_size_class (string, siehe SMF_Shortcodes::logo_size_class()),
 *            $stale_since (int|null - siehe SMF_API::stale_since()),
 *            $show_status_badge (bool - siehe SMF_Shortcodes::resolve_show_status_badge())
 *
 * $show_names pro Team nur maßgeblich, wenn für dieses Team ein Logo da
 * ist - fehlt es, bleibt der Name sichtbar (siehe $show_home_name/
 * $show_away_name unten).
 */

$api = new SMF_API();

$game_id    = isset( $game['game_id'] )  ? $game['game_id']  : 0;
$has_result = $api->has_result( $game );
$date_ts    = $api->parse_game_date( $game );
$game_tz    = SMF_API::game_timezone();

$status = SMF_Game_Status::status( $game );
// Staleness-Gate: Daten aus der Notreserve (Verbandsserver gerade nicht
// erreichbar) können veraltet sein - ein "läuft gerade" auf so einem Stand
// wäre eine erfundene Momentaufnahme. Karte fällt in diesem Fall optisch
// komplett auf "bevorstehend" zurück (kein Live-Label, kein Badge, kein
// "Ergebnis folgt"), der bestehende stale_notice()-Hinweis unten bleibt die
// einzige Kommunikation über den veralteten Stand.
$display_status = ( $status === 'running' && $stale_since ) ? 'upcoming' : $status;

$show_running_ui = $display_status === 'running';
$show_badge      = ! empty( $show_status_badge ) && in_array( $display_status, array( 'running', 'canceled' ), true );

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

// Name pro Team nur ausblenden, wenn für GENAU dieses Team ein Logo da ist
// - sonst stünde in der Karte nichts.
$show_home_name = $show_names || ! $home_logo;
$show_away_name = $show_names || ! $away_logo;
?>

<div class="smf smf-single-game<?php echo $has_result ? ' smf-game--played' : ' smf-game--upcoming'; ?><?php echo $show_running_ui ? ' smf-game--running' : ''; ?><?php echo $display_status === 'canceled' ? ' smf-game--canceled' : ''; ?><?php echo $show_logos ? ' smf-single-game--with-logos' : ''; ?><?php echo ! empty( $logo_size_class ) ? ' ' . esc_attr( $logo_size_class ) : ''; ?>"
     <?php if ( $game_id ) : ?>
         data-game-id="<?php echo esc_attr( $game_id ); ?>"
         role="button"
         tabindex="0"
     <?php endif; ?>>

    <!-- Label + Liga -->
    <div class="smf-single-game__label">
        <span><?php echo esc_html( ( $show_running_ui && ! empty( $running_label ) ) ? $running_label : $label ); ?></span>
        <?php if ( $league_name ) : ?>
            <span class="smf-single-game__league"><?php echo esc_html( $league_name ); ?></span>
        <?php endif; ?>
    </div>

    <!-- Datum prominent über dem Matchup -->
    <?php
    $show_time = ! $has_result && ! empty( $game['time'] );
    ?>
    <?php if ( $date_ts ) : ?>
        <div class="smf-single-game__date-row">
            <?php /* wp_date() statt date_i18n(): date_i18n() addiert den
                     AKTUELLEN gmt_offset der Site auf den Timestamp, ohne
                     Sommer-/Winterzeit am Datum des Spiels selbst zu
                     berücksichtigen - bei einer anderen Site-Zeitzone als
                     Europe/Berlin könnte das vom unverändert angezeigten
                     Rohstring $game['time'] abweichen. wp_date() mit
                     expliziter Zeitzone ist DST-korrekt für das jeweilige
                     Datum, siehe SMF_API::game_timezone(). */ ?>
            <span class="smf-single-game__date-day"><?php echo esc_html( wp_date( 'l', $date_ts, $game_tz ) ); ?></span>
            <span class="smf-single-game__date-full"><?php echo esc_html( wp_date( 'd. F Y', $date_ts, $game_tz ) ); ?></span>
            <?php /* Zeile bleibt immer im Markup (layoutwirksam, siehe
                     .smf-single-game__date-time--empty in style.css) - sonst
                     hat die Karte ohne Uhrzeit (z.B. "Letztes Spiel") eine
                     Zeile weniger als die mit Uhrzeit und alles darunter
                     rutscht hoch. */ ?>
            <?php if ( $show_time ) : ?>
                <span class="smf-single-game__date-time"><?php echo esc_html( $game['time'] . ' ' . smf_label( 'time_suffix', 'Uhr' ) ); ?></span>
            <?php else : ?>
                <span class="smf-single-game__date-time smf-single-game__date-time--empty" aria-hidden="true">&nbsp;</span>
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
                         <?php if ( ! $show_home_name ) : ?>title="<?php echo esc_attr( $home_name ); ?>"<?php endif; ?>
                         class="smf-single-game__logo"
                         loading="lazy">
                </div>
            <?php endif; ?>
            <?php if ( $show_home_name ) : ?>
                <span class="smf-single-game__team-name"><?php echo esc_html( $home_name ); ?></span>
            <?php endif; ?>
        </div>

        <div class="smf-single-game__center">
            <?php if ( $show_badge ) : ?>
                <?php echo smf_game_status_badge_html( $display_status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapt bereits intern ?>
            <?php endif; ?>
            <?php if ( $has_result ) : ?>
                <div class="smf-single-game__score"><?php echo esc_html( $home_score . ' : ' . $away_score ); ?></div>
            <?php elseif ( $show_running_ui ) : ?>
                <?php /* Niemals 0:0 erfinden: ohne Echtzeit-Freigabe blendet
                         die API das Ergebnis eines laufenden Spiels aus, statt
                         es veraltet zu liefern (siehe README, Abschnitt
                         "Ausfallverhalten"/Umsetzungsauftrag Etappe B). */ ?>
                <div class="smf-single-game__score smf-single-game__score--upcoming"><?php echo esc_html( smf_label( 'live_no_result_label', 'Live – Ergebnis folgt' ) ); ?></div>
            <?php else : ?>
                <div class="smf-single-game__score smf-single-game__score--upcoming"><?php echo esc_html( smf_label( 'vs_label', 'vs.' ) ); ?></div>
            <?php endif; ?>
        </div>

        <div class="smf-single-game__team smf-single-game__team--away">
            <?php if ( $away_logo ) : ?>
                <div class="smf-single-game__logo-wrap">
                    <img src="<?php echo esc_url( $away_logo ); ?>"
                         alt="<?php echo esc_attr( $away_name ); ?>"
                         <?php if ( ! $show_away_name ) : ?>title="<?php echo esc_attr( $away_name ); ?>"<?php endif; ?>
                         class="smf-single-game__logo"
                         loading="lazy">
                </div>
            <?php endif; ?>
            <?php if ( $show_away_name ) : ?>
                <span class="smf-single-game__team-name"><?php echo esc_html( $away_name ); ?></span>
            <?php endif; ?>
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
                <span><?php echo esc_html( smf_label( 'game_day_prefix', 'Spieltag' ) . ' ' . $game_day ); ?></span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ( $game_id ) : ?>
        <div class="smf-single-game__action">
            <span class="smf-btn"><?php echo esc_html( $has_result ? smf_label( 'single_game_btn_played', 'Spielbericht ansehen' ) : smf_label( 'single_game_btn_upcoming', 'Details ansehen' ) ); ?></span>
        </div>
    <?php endif; ?>

</div>

<?php echo SMF_Shortcodes::stale_notice( $stale_since ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- stale_notice() escapt bereits intern ?>

<?php if ( ! defined( 'SMF_MODAL_RENDERED' ) ) : define( 'SMF_MODAL_RENDERED', true ); ?>
<div id="smf-modal" class="smf-modal" role="dialog" aria-modal="true" style="display:none;">
    <div class="smf-modal-overlay"></div>
    <div class="smf-modal-content">
        <button class="smf-modal-close" aria-label="Schließen">&times;</button>
        <div id="smf-modal-body"></div>
    </div>
</div>
<?php endif; ?>
