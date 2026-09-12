<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Zweistufiges Caching für API-Antworten.
 *
 * Stufe 1 ("frisch"): Transient mit kurzer Lebensdauer (smf_cache_duration),
 * reduziert die Anzahl der API-Anfragen im Normalbetrieb.
 *
 * Stufe 2 ("Notreserve"/Stale-Spiegel): Option mit Präfix smf_stale_,
 * autoload=false. Bewusst eine Option und kein Transient - Transients
 * können bei aktivem Object-Cache (Redis/Memcached) jederzeit ohne
 * Vorwarnung verworfen werden, und genau dann wäre die Notreserve weg,
 * wenn sie am dringendsten gebraucht wird. Wird nur für ausgewählte
 * Endpunkte befüllt (siehe SMF_API::request(), Parameter $mirror) - vor
 * allem Endpunkte mit personenbezogenen Daten (Spieler-, Schiedsrichter-
 * namen) landen hier bewusst nicht.
 */
class SMF_Cache {

    /** Notreserve gilt nach dieser Zeit als zu alt, um noch ausgeliefert zu werden. */
    const STALE_MAX_AGE = 7 * DAY_IN_SECONDS;

    /** Aufräumschwelle für cleanup_stale(): entfernt Notreserve-Einträge, die niemand mehr abfragt. */
    const STALE_CLEANUP_MAX_AGE = 30 * DAY_IN_SECONDS;

    /** @var int */
    private $duration;

    public function __construct() {
        $this->duration = (int) get_option( 'smf_cache_duration', 300 );
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function get( $key ) {
        return get_transient( 'smf_' . md5( $key ) );
    }

    /**
     * @param string $key
     * @param mixed  $data
     * @param bool   $mirror Zusätzlich in die Notreserve (Stufe 2) spiegeln.
     *                       false bei Endpunkten mit personenbezogenen Daten
     *                       oder unverhältnismäßig großen Antworten.
     */
    public function set( $key, $data, $mirror = true ) {
        set_transient( 'smf_' . md5( $key ), $data, $this->duration );

        if ( $mirror ) {
            update_option(
                'smf_stale_' . md5( $key ),
                array(
                    'data' => $data,
                    'time' => time(),
                    'url'  => $key,
                ),
                false
            );
        }
    }

    /**
     * @param string $key
     */
    public function delete( $key ) {
        delete_transient( 'smf_' . md5( $key ) );
    }

    /**
     * Notreserve für einen Schlüssel lesen.
     *
     * @param string $key
     * @return array{data: mixed, time: int, age: int}|false
     */
    public function get_stale( $key ) {
        $entry = get_option( 'smf_stale_' . md5( $key ), false );

        if ( ! is_array( $entry ) || ! isset( $entry['data'], $entry['time'] ) ) {
            return false;
        }

        $age = time() - (int) $entry['time'];
        if ( $age > self::STALE_MAX_AGE ) {
            return false;
        }

        return array(
            'data' => $entry['data'],
            'time' => (int) $entry['time'],
            'age'  => $age,
        );
    }

    /**
     * Löscht nur Stufe 1 (frischer Cache). Die Notreserve bleibt bewusst
     * erhalten - sie ist die Versicherung gegen einen Ausfall und darf
     * nicht durch ein beiläufiges "Cache leeren" verschwinden.
     */
    public function flush() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- bulk-deletes all smf_* transients by option_name prefix; no WP API covers "delete transients matching a prefix", and caching this query would defeat its purpose. Query string is fully static (no interpolated values), so $wpdb->prepare() has nothing to bind.
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_smf_%'
                OR option_name LIKE '_transient_timeout_smf_%'"
        );
    }

    /**
     * Löscht beide Stufen - inklusive der Notreserve. Nur für den
     * ausdrücklichen "Cache vollständig zurücksetzen"-Knopf im Backend,
     * nicht für den regulären "Cache leeren".
     */
    public function flush_all() {
        $this->flush();

        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like( 'smf_stale_' ) . '%'
            )
        );
    }

    /**
     * Entfernt Notreserve-Einträge, die älter als STALE_CLEANUP_MAX_AGE
     * sind - z.B. von Endpunkten, die längst nicht mehr konfiguriert sind
     * (alte Liga-ID, entfernter Verein). Bewusst kein eigener Cron-Job,
     * wird stattdessen gedrosselt aus einem bestehenden admin_init-Kontext
     * aufgerufen (siehe smf_maybe_cleanup_stale_cache() im Hauptplugin).
     *
     * @return int Anzahl der entfernten Einträge
     */
    public function cleanup_stale() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- einmaliges, gedrosseltes Aufräumen über alle smf_stale_*-Optionen; keine WP-API deckt "alle Optionen mit Präfix X lesen" ab.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like( 'smf_stale_' ) . '%'
            ),
            ARRAY_A
        );

        if ( empty( $rows ) ) {
            return 0;
        }

        $now     = time();
        $removed = 0;

        foreach ( $rows as $row ) {
            $entry = maybe_unserialize( $row['option_value'] );
            $time  = ( is_array( $entry ) && isset( $entry['time'] ) ) ? (int) $entry['time'] : 0;

            if ( ! $time || ( $now - $time ) > self::STALE_CLEANUP_MAX_AGE ) {
                delete_option( $row['option_name'] );
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Statistik für die Backend-Statusanzeige.
     *
     * @return array{count: int, oldest_age: int|null}
     */
    public function get_stale_stats() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- reine Backend-Statusanzeige, keine WP-API für "Optionen nach Präfix zählen/lesen".
        $rows = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like( 'smf_stale_' ) . '%'
            )
        );

        $count      = 0;
        $oldest_age = null;
        $now        = time();

        foreach ( (array) $rows as $value ) {
            $entry = maybe_unserialize( $value );
            $time  = ( is_array( $entry ) && isset( $entry['time'] ) ) ? (int) $entry['time'] : 0;
            if ( ! $time ) continue;

            $count++;
            $age = $now - $time;
            if ( $oldest_age === null || $age > $oldest_age ) {
                $oldest_age = $age;
            }
        }

        return array(
            'count'      => $count,
            'oldest_age' => $oldest_age,
        );
    }
}
