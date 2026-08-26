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

        // The Hírkezelő is user-specific and must never be served from a public
        // full-page cache. Query-string cache busting in manager_url() protects
        // against caches that run before normal WordPress plugin hooks; these
        // response headers protect the normal WordPress response as well.
        add_action( 'template_redirect', array( __CLASS__, 'protect_manager_cache' ), 0 );

        // The Hírkezelő is an internal application page in test and production
        // alike. Keep it out of public navigation and search-engine indexing.
        add_filter( 'wp_nav_menu_objects', array( __CLASS__, 'hide_manager_page_from_menu' ), 999, 2 );
        add_filter( 'wp_robots', array( __CLASS__, 'manager_robots' ) );
    }

    public static function activate(): void {
        PNW_Roles::install();
        PNW_Audit::install();
        PNW_Statuses::register();
        $page_id = self::ensure_manager_page();

        if ( $page_id ) {
            self::remove_manager_page_from_saved_menus( $page_id );
        }

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

    public static function protect_manager_cache(): void {
        $page_id = absint( get_option( 'pnw_manager_page_id', 0 ) );
        if ( ! $page_id || ! is_page( $page_id ) ) {
            return;
        }

        if ( ! defined( 'DONOTCACHEPAGE' ) ) {
            define( 'DONOTCACHEPAGE', true );
        }

        nocache_headers();

        if ( ! headers_sent() ) {
            header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
            header( 'Pragma: no-cache', true );
            header( 'Vary: Cookie', false );
            header( 'X-Robots-Tag: noindex, nofollow', true );
        }
    }

    public static function manager_robots( array $robots ): array {
        $page_id = absint( get_option( 'pnw_manager_page_id', 0 ) );
        if ( $page_id > 0 && is_page( $page_id ) ) {
            $robots['noindex']  = true;
            $robots['nofollow'] = true;
        }

        return $robots;
    }

    public static function hide_manager_page_from_menu( array $items, $args ): array {
        $page_id = absint( get_option( 'pnw_manager_page_id', 0 ) );
        if ( ! $page_id ) {
            return $items;
        }

        return array_values(
            array_filter(
                $items,
                static function ( $item ) use ( $page_id ): bool {
                    return ! ( isset( $item->object, $item->object_id ) && 'page' === $item->object && (int) $item->object_id === $page_id );
                }
            )
        );
    }

    private static function remove_manager_page_from_saved_menus( int $page_id ): void {
        $menu_items = get_posts(
            array(
                'post_type'      => 'nav_menu_item',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => array(
                    array(
                        'key'     => '_menu_item_object_id',
                        'value'   => (string) $page_id,
                        'compare' => '=',
                    ),
                    array(
                        'key'     => '_menu_item_object',
                        'value'   => 'page',
                        'compare' => '=',
                    ),
                ),
            )
        );

        foreach ( $menu_items as $menu_item_id ) {
            wp_delete_post( (int) $menu_item_id, true );
        }
    }

    public static function manager_url( array $args = array() ): string {
        $page_id = absint( get_option( 'pnw_manager_page_id', 0 ) );
        $url     = $page_id ? get_permalink( $page_id ) : home_url( '/hirkezelo/' );

        // Never navigate an authenticated workflow user to the bare public
        // /hirkezelo/ URL. That URL may have been cached earlier while logged
        // out. The nonce is only a cache-buster (not an authorization check),
        // and it is different for each logged-in WordPress session.
        $cache_args = array(
            'pnw_app' => PNW_VERSION,
        );

        if ( is_user_logged_in() ) {
            $cache_args['pnw_session'] = wp_create_nonce( 'pnw_manager_cache_' . get_current_user_id() );
        }

        $url = add_query_arg( array_merge( $cache_args, $args ), $url );

        return $url;
    }
}
