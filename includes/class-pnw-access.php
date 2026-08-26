<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PNW_Access {
    public static function init(): void {
        add_filter( 'show_admin_bar', array( __CLASS__, 'maybe_hide_admin_bar' ) );
        add_action( 'admin_init', array( __CLASS__, 'redirect_workflow_roles_from_admin' ) );
        add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 10, 3 );
        add_filter( 'wp_insert_post_data', array( __CLASS__, 'force_mk_safe_status' ), 10, 2 );
        add_filter( 'map_meta_cap', array( __CLASS__, 'lock_managed_posts_for_mk' ), 20, 4 );
    }

    /**
     * The three Petrik workflow roles are intentionally frontend-only users.
     * Administrators remain normal wp-admin users even if they also have one
     * of the custom workflow capabilities.
     */
    public static function is_workflow_only_user( ?WP_User $user = null ): bool {
        $user = $user ?: wp_get_current_user();
        if ( ! $user->exists() || in_array( 'administrator', (array) $user->roles, true ) ) {
            return false;
        }

        return (bool) array_intersect(
            array( PNW_Roles::MK_LEADER, PNW_Roles::DEPUTY, PNW_Roles::DIRECTOR ),
            (array) $user->roles
        );
    }

    public static function maybe_hide_admin_bar( bool $show ): bool {
        return self::is_workflow_only_user() ? false : $show;
    }

    /**
     * Keep workflow roles inside the dedicated Hírkezelő. The exceptions are
     * required by frontend forms, TinyMCE media uploads and WordPress AJAX.
     */
    public static function redirect_workflow_roles_from_admin(): void {
        if ( ! is_user_logged_in() || ! self::is_workflow_only_user() ) {
            return;
        }

        global $pagenow;
        $allowed = array( 'admin-post.php', 'async-upload.php', 'media-upload.php' );
        if ( wp_doing_ajax() || in_array( (string) $pagenow, $allowed, true ) ) {
            return;
        }

        wp_safe_redirect( PNW_Plugin::manager_url() );
        exit;
    }

    public static function login_redirect( string $redirect_to, string $requested, $user ): string {
        if ( $user instanceof WP_User && self::is_workflow_only_user( $user ) ) {
            return PNW_Plugin::manager_url();
        }

        return $redirect_to;
    }

    /**
     * MK leaders must never be able to publish directly through a WordPress
     * post operation. Their content has to enter the approval workflow first.
     */
    public static function force_mk_safe_status( array $data, array $postarr ): array {
        if ( ! is_user_logged_in() || ! PNW_Roles::is_mk_leader() ) {
            return $data;
        }

        if ( 'post' !== ( $data['post_type'] ?? '' ) ) {
            return $data;
        }

        if ( in_array( $data['post_status'] ?? '', array( 'publish', 'future', 'private' ), true ) ) {
            $data['post_status'] = 'pending';
        }

        return $data;
    }

    public static function lock_managed_posts_for_mk( array $caps, string $cap, int $user_id, array $args ): array {
        if ( ! in_array( $cap, array( 'edit_post', 'delete_post' ), true ) || empty( $args[0] ) ) {
            return $caps;
        }

        $post_id = absint( $args[0] );
        $post    = get_post( $post_id );
        $user    = get_userdata( $user_id );

        if ( ! $post || ! $user || 'post' !== $post->post_type || ! self::is_managed( $post_id ) || ! PNW_Roles::is_mk_leader( $user ) ) {
            return $caps;
        }

        if ( (int) $post->post_author !== $user_id || ! in_array( $post->post_status, array( 'draft', PNW_Statuses::REVISION ), true ) ) {
            return array( 'do_not_allow' );
        }

        return $caps;
    }

    public static function is_managed( int $post_id ): bool {
        return '1' === (string) get_post_meta( $post_id, '_pnw_managed', true );
    }

    public static function allowed_category_ids( int $user_id = 0 ): array {
        $user_id = $user_id ?: get_current_user_id();
        $saved   = get_user_meta( $user_id, 'pnw_allowed_categories', true );

        if ( is_array( $saved ) && ! empty( $saved ) ) {
            return array_values(
                array_filter(
                    array_map( 'absint', $saved ),
                    static fn( int $id ): bool => $id > 0 && term_exists( $id, 'category' ) !== null
                )
            );
        }

        $terms = get_categories( array( 'hide_empty' => false ) );
        return array_map( static fn( WP_Term $term ): int => (int) $term->term_id, $terms );
    }

    public static function can_edit_workflow_post( int $post_id, ?WP_User $user = null ): bool {
        $post = get_post( $post_id );
        $user = $user ?: wp_get_current_user();

        if ( ! $post || 'post' !== $post->post_type || ! $user->exists() || ! self::is_managed( $post_id ) ) {
            return false;
        }

        if ( user_can( $user, 'pnw_review_news' ) ) {
            return true;
        }

        return user_can( $user, 'pnw_submit_news' )
            && (int) $post->post_author === (int) $user->ID
            && in_array( $post->post_status, array( 'draft', PNW_Statuses::REVISION ), true );
    }

    public static function can_view_workflow_post( int $post_id, ?WP_User $user = null ): bool {
        $post = get_post( $post_id );
        $user = $user ?: wp_get_current_user();

        if ( ! $post || 'post' !== $post->post_type || ! $user->exists() || ! self::is_managed( $post_id ) ) {
            return false;
        }

        if ( user_can( $user, 'pnw_review_news' ) ) {
            return true;
        }

        return user_can( $user, 'pnw_submit_news' ) && (int) $post->post_author === (int) $user->ID;
    }
}
