<?php
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * Template: Spieldetail-Modal (via AJAX geladen)
 * Variable: $game (array – vollständige API-Daten von games/{id}.json)
 */

$api = new SMF_API();

$date_ts    = $api->parse_game_date( $game );
$has_result = $api->has_result( $game );

$home_name  = isset( $game['home_team_name'] )  ? $game['home_team_name']  : '?';
$away_name  = isset( $game['guest_team_name'] ) ? $game['guest_team_name'] : '?';
$home_score = isset( $game['result']['home_goals'] )  ? $game['result']['home_goals']  : null;
$away_score = isset( $game['result']['guest_goals'] ) ? $game['result']['guest_goals'] : null;
$venue      = isset( $game['arena_name'] ) ? $game['arena_name'] : ( isset( $game['arena'] ) ? $game['arena'] : '' );
$game_day   = isset( $game['game_day'] )   ? $game['game_day']   : '';
$league     = isset( $game['league_name'] ) ? $game['league_name'] : ( isset( $game['game_operation_name'] ) ? $game['game_operation_name'] : '' );

$periods_home  = isset( $game['result']['home_goals_period'] )  ? $game['result']['home_goals_period']  : array();
$periods_guest = isset( $game['result']['guest_goals_period'] ) ? $game['result']['guest_goals_period'] : array();
$overtime      = ! empty( $game['result']['overtime'] );
$postfix_raw   = isset( $game['result']['postfix'] ) ? $game['result']['postfix'] : '';
$postfix       = '';
if ( is_array( $postfix_raw ) && ! empty( $postfix_raw['short'] ) ) {
    $postfix = $postfix_raw['short'];
} elseif ( is_string( $postfix_raw ) ) {
    $postfix = $postfix_raw;
}

$home_logo = SMF_API::get_logo_url( isset( $game['home_team_small_logo'] ) ? $game['home_team_small_logo'] : ( isset( $game['home_team_logo'] ) ? $game['home_team_logo'] : '' ) );
$away_logo = SMF_API::get_logo_url( isset( $game['guest_team_small_logo'] ) ? $game['guest_team_small_logo'] : ( isset( $game['guest_team_logo'] ) ? $game['guest_team_logo'] : '' ) );

$events   = isset( $game['events'] ) ? $game['events'] : array();
$referees = isset( $game['referees'] ) ? $game['referees'] : array();
$nom_refs = isset( $game['nominated_referees'] ) ? $game['nominated_referees'] : '';

// Spieler-Lookup: Trikotnummer -> Name
$player_map = array( 'home' => array(), 'guest' => array() );
foreach ( array( 'home', 'guest' ) as $side ) {
    if ( isset( $game['players'][ $side ] ) && is_array( $game['players'][ $side ] ) ) {
        foreach ( $game['players'][ $side ] as $p ) {
            $nr = '';
            if ( isset( $p['trikot_number'] ) && $p['trikot_number'] !== '' ) {
                $nr = (string) $p['trikot_number'];
            } elseif ( isset( $p['number'] ) && $p['number'] !== '' ) {
                $nr = (string) $p['number'];
            }
            if ( $nr === '' ) continue;

            if ( isset( $p['player_firstname'] ) || isset( $p['player_name'] ) ) {
                $player_map[ $side ][ $nr ] = trim(
                    ( isset( $p['player_firstname'] ) ? $p['player_firstname'] : '' ) . ' ' .
                    ( isset( $p['player_name'] )      ? $p['player_name']      : '' )
                );
            } elseif ( isset( $p['first_name'] ) || isset( $p['last_name'] ) ) {
                $player_map[ $side ][ $nr ] = trim(
                    ( isset( $p['first_name'] ) ? $p['first_name'] : '' ) . ' ' .
                    ( isset( $p['last_name'] )  ? $p['last_name']  : '' )
                );
            } elseif ( isset( $p['name'] ) ) {
                $player_map[ $side ][ $nr ] = $p['name'];
            }
        }
    }
}

// Alle relevanten Events (Tore + Strafen) chronologisch sortieren
$timeline = array();
foreach ( $events as $ev ) {
    $type = isset( $ev['event_type'] ) ? $ev['event_type'] : '';
    if ( $type === 'goal' || $type === 'penalty' ) {
        $timeline[] = $ev;
    }
}
usort( $timeline, function( $a, $b ) {
    // Sortierung: zuerst nach period, dann nach sortkey/time
    $pa = isset( $a['period'] ) ? (int) $a['period'] : 0;
    $pb = isset( $b['period'] ) ? (int) $b['period'] : 0;
    if ( $pa !== $pb ) return $pa - $pb;
    $sa = isset( $a['sortkey'] ) ? $a['sortkey'] : ( isset( $a['time'] ) ? $a['time'] : '' );
    $sb = isset( $b['sortkey'] ) ? $b['sortkey'] : ( isset( $b['time'] ) ? $b['time'] : '' );
    return strcmp( $sa, $sb );
} );

// Events nach Drittel gruppieren
$by_period = array();
foreach ( $timeline as $ev ) {
    $period = isset( $ev['period'] ) ? (int) $ev['period'] : 0;
    $by_period[ $period ][] = $ev;
}
ksort( $by_period );

// Schiedsrichter-String
$ref_string = '';
if ( ! empty( $referees ) ) {
    $names = array();
    foreach ( $referees as $r ) {
        if ( isset( $r['first_name'] ) || isset( $r['last_name'] ) ) {
            $names[] = trim(
                ( isset( $r['first_name'] ) ? $r['first_name'] : '' ) . ' ' .
                ( isset( $r['last_name'] )  ? $r['last_name']  : '' )
            );
        }
    }
    $ref_string = implode( ', ', array_filter( $names ) );
}
if ( ! $ref_string && $nom_refs ) {
    $ref_string = $nom_refs;
}
?>

<div class="smf-detail">

    <!-- Header -->
    <div class="smf-detail__header">
        <?php if ( $league ) : ?>
            <div class="smf-detail__league"><?php echo esc_html( $league ); ?></div>
        <?php endif; ?>
        <?php if ( $date_ts ) : ?>
            <div class="smf-detail__date">
                <?php echo esc_html( date_i18n( 'l, d. F Y', $date_ts ) ); ?>
                <?php if ( ! empty( $game['time'] ) ) : ?>
                    &ndash; <?php echo esc_html( $game['time'] ); ?> Uhr
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="smf-detail__info-row">
            <?php if ( $venue ) : ?>
                <span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <?php echo esc_html( $venue ); ?>
                </span>
            <?php endif; ?>
            <?php if ( $game_day ) : ?>
                <span>Spieltag <?php echo esc_html( $game_day ); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Ergebnis -->
    <div class="smf-detail__result">
        <div class="smf-detail__team smf-detail__team--home">
            <?php if ( $home_logo ) : ?>
                <img src="<?php echo esc_url( $home_logo ); ?>" alt="<?php echo esc_attr( $home_name ); ?>" class="smf-detail__team-logo" loading="lazy">
            <?php endif; ?>
            <span class="smf-detail__team-name"><?php echo esc_html( $home_name ); ?></span>
        </div>

        <div class="smf-detail__score-box">
            <?php if ( $has_result ) : ?>
                <div class="smf-detail__score">
                    <?php echo esc_html( $home_score . ' : ' . $away_score ); ?>
                </div>
                <?php if ( $postfix ) : ?>
                    <div class="smf-detail__score-postfix"><?php echo esc_html( $postfix ); ?></div>
                <?php elseif ( $overtime ) : ?>
                    <div class="smf-detail__score-postfix">n.V.</div>
                <?php endif; ?>
                <?php if ( ! empty( $periods_home ) ) : ?>
                    <div class="smf-detail__periods">
                        <?php foreach ( $periods_home as $i => $ph ) :
                            $pa = isset( $periods_guest[ $i ] ) ? $periods_guest[ $i ] : 0;
                            if ( $ph == 0 && $pa == 0 ) continue;
                            $label = ( $i < 3 ) ? ( $i + 1 ) . '. Drittel' : 'Verl.';
                        ?>
                            <span><?php echo esc_html( $label . ': ' . $ph . ':' . $pa ); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php else : ?>
                <div class="smf-detail__score smf-detail__score--upcoming">&ndash;:&ndash;</div>
                <div class="smf-detail__upcoming-hint">Spiel noch nicht gespielt</div>
            <?php endif; ?>
        </div>

        <div class="smf-detail__team smf-detail__team--away">
            <?php if ( $away_logo ) : ?>
                <img src="<?php echo esc_url( $away_logo ); ?>" alt="<?php echo esc_attr( $away_name ); ?>" class="smf-detail__team-logo" loading="lazy">
            <?php endif; ?>
            <span class="smf-detail__team-name"><?php echo esc_html( $away_name ); ?></span>
        </div>
    </div>

    <?php if ( ! empty( $timeline ) ) : ?>
    <!-- Chronologische Ereignisse -->
    <div class="smf-detail__section">
        <h4 class="smf-detail__section-title">Spielverlauf</h4>

        <div class="smf-timeline">

            <?php foreach ( $by_period as $period_num => $period_events ) :
                if ( $period_num > 0 ) :
                    $period_label = ( $period_num <= 3 ) ? $period_num . '. Drittel' : 'Verlängerung';
                ?>
                <div class="smf-timeline__period"><?php echo esc_html( $period_label ); ?></div>
                <?php endif; ?>

                <?php foreach ( $period_events as $ev ) :
                    $type     = isset( $ev['event_type'] ) ? $ev['event_type'] : '';
                    $side     = isset( $ev['event_team'] ) ? $ev['event_team'] : '';
                    $is_home  = ( $side === 'home' );
                    $time     = isset( $ev['time'] ) ? $ev['time'] : '';
                    $nr       = isset( $ev['number'] ) ? (string) $ev['number'] : '';
                    $map_key  = $is_home ? 'home' : 'guest';
                    $player   = isset( $player_map[ $map_key ][ $nr ] )
                                    ? $player_map[ $map_key ][ $nr ]
                                    : ( $nr ? '#' . $nr : '' );

                    if ( $type === 'goal' ) :
                        $assist_nr  = isset( $ev['assist'] ) ? (string) $ev['assist'] : '';
                        $assist     = ( $assist_nr && isset( $player_map[ $map_key ][ $assist_nr ] ) )
                                        ? $player_map[ $map_key ][ $assist_nr ]
                                        : ( $assist_nr ? '#' . $assist_nr : '' );
                        $special    = isset( $ev['goal_type_string'] ) && $ev['goal_type_string'] !== 'Regulär'
                                        ? $ev['goal_type_string'] : '';
                        $running    = ( isset( $ev['home_goals'] ) && isset( $ev['guest_goals'] ) )
                                        ? $ev['home_goals'] . ':' . $ev['guest_goals'] : '';
                ?>
                    <div class="smf-timeline__event smf-tl-goal <?php echo $is_home ? 'smf-tl--home' : 'smf-tl--away'; ?>">
                        <span class="smf-tl-time"><?php echo esc_html( $time ); ?></span>
                        <span class="smf-tl-icon" aria-hidden="true">
                            <!-- Puck-Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><ellipse cx="12" cy="12" rx="11" ry="7"/></svg>
                        </span>
                        <span class="smf-tl-team-badge <?php echo $is_home ? 'smf-tl-team-badge--home' : 'smf-tl-team-badge--away'; ?>">
                            <?php echo $is_home ? 'Heim' : 'Gast'; ?>
                        </span>
                        <div class="smf-tl-info">
                            <?php if ( $player ) : ?>
                                <span class="smf-tl-player"><?php echo esc_html( $player ); ?></span>
                            <?php endif; ?>
                            <?php if ( $assist ) : ?>
                                <span class="smf-tl-assist">(<?php echo esc_html( $assist ); ?>)</span>
                            <?php endif; ?>
                            <?php if ( $special ) : ?>
                                <span class="smf-tl-tag"><?php echo esc_html( $special ); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ( $running ) : ?>
                            <span class="smf-tl-score"><?php echo esc_html( $running ); ?></span>
                        <?php endif; ?>
                    </div>

                <?php elseif ( $type === 'penalty' ) :
                        $reason   = isset( $ev['penalty_reason_string'] ) ? $ev['penalty_reason_string'] : '';
                        $duration = isset( $ev['penalty_type_string'] )   ? $ev['penalty_type_string']   : '';
                ?>
                    <div class="smf-timeline__event smf-tl-penalty <?php echo $is_home ? 'smf-tl--home' : 'smf-tl--away'; ?>">
                        <span class="smf-tl-time"><?php echo esc_html( $time ); ?></span>
                        <span class="smf-tl-icon" aria-hidden="true">
                            <!-- Strafbank-Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="3"/></svg>
                        </span>
                        <span class="smf-tl-team-badge <?php echo $is_home ? 'smf-tl-team-badge--home' : 'smf-tl-team-badge--away'; ?>">
                            <?php echo $is_home ? 'Heim' : 'Gast'; ?>
                        </span>
                        <div class="smf-tl-info">
                            <?php if ( $player ) : ?>
                                <span class="smf-tl-player"><?php echo esc_html( $player ); ?></span>
                            <?php endif; ?>
                            <?php if ( $reason ) : ?>
                                <span class="smf-tl-reason"><?php echo esc_html( $reason ); ?></span>
                            <?php endif; ?>
                            <?php if ( $duration ) : ?>
                                <span class="smf-tl-tag smf-tl-tag--penalty"><?php echo esc_html( $duration ); ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="smf-tl-score"></span>
                    </div>

                <?php endif; ?>
                <?php endforeach; ?>
            <?php endforeach; ?>

        </div>
    </div>
    <?php endif; ?>

    <!-- Schiedsrichter -->
    <?php if ( $ref_string ) : ?>
        <div class="smf-detail__section smf-detail__section--minor">
            <h4 class="smf-detail__section-title">Schiedsrichter</h4>
            <p class="smf-detail__referees"><?php echo esc_html( $ref_string ); ?></p>
        </div>
    <?php endif; ?>

</div>
