<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PNW_Plugin {
    private static ?PNW_Plugin $instance = null;

    public static function instance(): PNW_Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {}

    public function boot(): void {
        PNW_Roles::init();
        PNW_Statuses::init();
        PNW_Access::init();
        PNW_Actions::init();
        PNW_Frontend::init();
        PNW_Admin::init();
    }

    public static function activate(): void {
        PNW_Roles::install();
        PNW_Audit::install();
        PNW_Statuses::register();
        self::ensure_manager_page();
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }

    public static function ensure_manager_page(): int {
        $page_id = absint( get_option( 'pnw_manager_page_id', 0 ) );
        if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
            return $page_id;
        }

        $existing = get_page_by_path( 'hirkezelo' );
        if ( $existing instanceof WP_Post ) {
            update_option( 'pnw_manager_page_id', $existing->ID );
            return (int) $existing->ID;
        }

        $page_id = wp_insert_post(
            array(
                'post_type'    => 'page',
                'post_status'  => 'publish',
                'post_title'   => 'Hírkezelő',
                'post_name'    => 'hirkezelo',
                'post_content' => '[petrik_news_manager]',
            ),
            true
        );

        if ( is_wp_error( $page_id ) ) {
            return 0;
        }

        update_option( 'pnw_manager_page_id', (int) $page_id );
        return (int) $page_id;
    }

    public static function manager_url( array $args = array() ): string {
        $page_id = absint( get_option( 'pnw_manager_page_id', 0 ) );
        $url     = $page_id ? get_permalink( $page_id ) : home_url( '/hirkezelo/' );

        if ( ! empty( $args ) ) {
            $url = add_query_arg( $args, $url );
        }

        return $url;
    }
}
