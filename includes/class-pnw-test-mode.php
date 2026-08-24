<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Hard safety guard used by the first live-server trial.
 *
 * The test package must never make a workflow-managed news post public. This
 * class intercepts every WordPress post update, including wp-admin and REST
 * based updates, and replaces public statuses with a private test-only status.
 */
final class PNW_Test_Mode {
    public static function init(): void {
        if ( ! self::enabled() ) {
            return;
        }

        add_filter( 'wp_insert_post_data', array( __CLASS__, 'block_publication' ), 1, 2 );
        add_filter( 'wp_robots', array( __CLASS__, 'manager_robots' ) );
        add_action( 'template_redirect', array( __CLASS__, 'protect_manager_response' ) );
    }

    public static function enabled(): bool {
        return defined( 'PNW_TEST_MODE' ) && true === PNW_TEST_MODE;
    }

    public static function block_publication( array $data, array $postarr ): array {
        if ( 'post' !== ( $data['post_type'] ?? '' ) ) {
            return $data;
        }

        $status = (string) ( $data['post_status'] ?? '' );
        if ( ! in_array( $status, array( 'publish', 'future', 'private' ), true ) ) {
            return $data;
        }

        $post_id = isset( $postarr['ID'] ) ? absint( $postarr['ID'] ) : 0;
        $managed = $post_id > 0 && '1' === (string) get_post_meta( $post_id, '_pnw_managed', true );

        if ( ! $managed && isset( $postarr['meta_input']['_pnw_managed'] ) ) {
            $managed = '1' === (string) $postarr['meta_input']['_pnw_managed'];
        }

        if ( $managed ) {
            $data['post_status'] = PNW_Statuses::TEST_APPROVED;
        }

        return $data;
    }

    public static function manager_robots( array $robots ): array {
        if ( self::is_manager_page() ) {
            $robots['noindex']  = true;
            $robots['nofollow'] = true;
        }

        return $robots;
    }

    public static function protect_manager_response(): void {
        if ( ! self::is_manager_page() ) {
            return;
        }

        nocache_headers();
        header( 'X-Robots-Tag: noindex, nofollow', true );
    }

    private static function is_manager_page(): bool {
        $page_id = absint( get_option( 'pnw_manager_page_id', 0 ) );
        return $page_id > 0 && is_page( $page_id );
    }
}
