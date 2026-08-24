<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Mutating actions for already published news.
 *
 * These actions are intentionally disabled while PNW_TEST_MODE is active so
 * the live Petrik site's existing posts cannot be changed during the pilot.
 */
final class PNW_Published_Actions {
    public static function init(): void {
        add_action( 'admin_post_pnw_update_published_news', array( __CLASS__, 'update_published_news' ) );
        add_action( 'admin_post_pnw_trash_published_news', array( __CLASS__, 'trash_published_news' ) );
    }

    public static function update_published_news(): void {
        self::require_access();
        check_admin_referer( 'pnw_update_published_news', 'pnw_nonce' );

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( self::test_mode() ) {
            self::redirect_notice( 'production_only', array( 'pnw_view' => 'published_edit', 'post_id' => $post_id ) );
        }

        $post = self::published_post( $post_id );
        if ( ! $post ) {
            wp_die( esc_html__( 'A publikált hír nem található.', 'petrik-news-workflow' ), 404 );
        }

        $title   = isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : '';
        $content = isset( $_POST['post_content'] ) ? wp_kses_post( wp_unslash( $_POST['post_content'] ) ) : '';
        $excerpt = isset( $_POST['post_excerpt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['post_excerpt'] ) ) : '';
        $cats    = isset( $_POST['post_category'] ) ? array_values( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['post_category'] ) ) ) ) : array();

        if ( '' === trim( $title ) || '' === trim( wp_strip_all_tags( $content ) ) ) {
            self::redirect_notice( 'missing_fields', array( 'pnw_view' => 'published_edit', 'post_id' => $post_id ) );
        }

        if ( empty( $cats ) ) {
            self::redirect_notice( 'category_required', array( 'pnw_view' => 'published_edit', 'post_id' => $post_id ) );
        }

        $result = wp_update_post(
            array(
                'ID'            => $post_id,
                'post_title'    => $title,
                'post_content'  => $content,
                'post_excerpt'  => $excerpt,
                'post_status'   => 'publish',
                'post_category' => $cats,
            ),
            true
        );

        if ( is_wp_error( $result ) ) {
            self::redirect_notice( 'save_error', array( 'pnw_view' => 'published_edit', 'post_id' => $post_id ) );
        }

        self::handle_featured_image( $post_id );
        PNW_Audit::log( $post_id, 'published_edited', 'publish', 'publish', 'Publikált hír módosítva a Hírkezelőből.' );
        self::redirect_notice( 'published_updated', array( 'pnw_view' => 'published_edit', 'post_id' => $post_id ) );
    }

    public static function trash_published_news(): void {
        self::require_access();
        check_admin_referer( 'pnw_trash_published_news', 'pnw_nonce' );

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( self::test_mode() ) {
            self::redirect_notice( 'production_only', array( 'pnw_view' => 'published_edit', 'post_id' => $post_id ) );
        }

        $post = self::published_post( $post_id );
        if ( ! $post ) {
            wp_die( esc_html__( 'A publikált hír nem található.', 'petrik-news-workflow' ), 404 );
        }

        PNW_Audit::log( $post_id, 'published_trashed', 'publish', 'trash', 'Publikált hír Lomtárba helyezve a Hírkezelőből.' );
        $result = wp_trash_post( $post_id );
        if ( ! $result ) {
            self::redirect_notice( 'save_error', array( 'pnw_view' => 'published_edit', 'post_id' => $post_id ) );
        }

        self::redirect_notice( 'published_trashed', array( 'pnw_view' => 'published' ) );
    }

    private static function require_access(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'pnw_manage_published_news' ) ) {
            wp_die( esc_html__( 'Nincs jogosultságod a publikált hírek kezeléséhez.', 'petrik-news-workflow' ), 403 );
        }
    }

    private static function published_post( int $post_id ): ?WP_Post {
        $post = get_post( $post_id );
        if ( ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
            return null;
        }
        return $post;
    }

    private static function test_mode(): bool {
        return defined( 'PNW_TEST_MODE' ) && true === PNW_TEST_MODE;
    }

    private static function handle_featured_image( int $post_id ): void {
        if ( empty( $_FILES['featured_image']['name'] ) ) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_handle_upload( 'featured_image', $post_id );
        if ( is_wp_error( $attachment_id ) ) {
            return;
        }

        $mime = get_post_mime_type( $attachment_id );
        if ( ! is_string( $mime ) || 0 !== strpos( $mime, 'image/' ) ) {
            wp_delete_attachment( $attachment_id, true );
            return;
        }

        set_post_thumbnail( $post_id, $attachment_id );
    }

    private static function redirect_notice( string $notice, array $args = array() ): void {
        $args['pnw_notice'] = sanitize_key( $notice );
        wp_safe_redirect( PNW_Plugin::manager_url( $args ) );
        exit;
    }
}
