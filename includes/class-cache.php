<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Einfaches Transient-basiertes Caching für API-Antworten
 */
class SMF_Cache {

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
     */
    public function set( $key, $data ) {
        set_transient( 'smf_' . md5( $key ), $data, $this->duration );
    }

    /**
     * @param string $key
     */
    public function delete( $key ) {
        delete_transient( 'smf_' . md5( $key ) );
    }

    public function flush() {
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_smf_%'
                OR option_name LIKE '_transient_timeout_smf_%'"
        );
    }
}
