<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PNW_Actions {
    public static function init(): void {
        add_action( 'admin_post_nopriv_pnw_frontend_login', array( __CLASS__, 'frontend_login' ) );
        add_action( 'admin_post_pnw_save_news', array( __CLASS__, 'save_news' ) );
        add_action( 'admin_post_pnw_review_news', array( __CLASS__, 'review_news' ) );
        add_action( 'admin_post_pnw_reviewer_save', array( __CLASS__, 'reviewer_save' ) );
        add_action( 'admin_post_pnw_delete_news', array( __CLASS__, 'delete_news' ) );
    }

    public static function frontend_login(): void {
        if ( is_user_logged_in() ) {
            wp_safe_redirect( PNW_Plugin::manager_url() );
            exit;
        }

        check_admin_referer( 'pnw_frontend_login', 'pnw_login_nonce' );

        $login    = isset( $_POST['pnw_login'] ) ? sanitize_text_field( wp_unslash( $_POST['pnw_login'] ) ) : '';
        $password = isset( $_POST['pnw_password'] ) ? (string) wp_unslash( $_POST['pnw_password'] ) : '';
        $remember = ! empty( $_POST['pnw_remember'] );

        if ( '' === trim( $login ) || '' === $password ) {
            self::redirect_login_error( 'empty_fields' );
        }

        $user = wp_signon(
            array(
                'user_login'    => $login,
                'user_password' => $password,
                'remember'      => $remember,
            ),
            is_ssl()
        );

        if ( is_wp_error( $user ) ) {
            self::redirect_login_error( 'invalid_credentials' );
        }

        if ( ! user_can( $user, 'pnw_submit_news' ) && ! user_can( $user, 'pnw_review_news' ) ) {
            wp_logout();
            self::redirect_login_error( 'no_access' );
        }

        wp_safe_redirect( PNW_Plugin::manager_url() );
        exit;
    }

    private static function require_login_and_cap( string $cap ): void {
        if ( ! is_user_logged_in() || ! current_user_can( $cap ) ) {
            wp_die( esc_html__( 'Nincs jogosultságod ehhez a művelethez.', 'petrik-news-workflow' ), 403 );
        }
    }

    public static function save_news(): void {
        self::require_login_and_cap( 'pnw_submit_news' );
        check_admin_referer( 'pnw_save_news', 'pnw_nonce' );

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $command = isset( $_POST['pnw_command'] ) ? sanitize_key( wp_unslash( $_POST['pnw_command'] ) ) : 'draft';
        $title   = isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : '';
        $content = isset( $_POST['post_content'] ) ? wp_kses_post( wp_unslash( $_POST['post_content'] ) ) : '';
        $excerpt = isset( $_POST['post_excerpt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['post_excerpt'] ) ) : '';

        if ( '' === trim( $title ) || '' === trim( wp_strip_all_tags( $content ) ) ) {
            self::redirect_notice( 'missing_fields', $post_id ? array( 'pnw_view' => 'edit', 'post_id' => $post_id ) : array( 'pnw_view' => 'new' ) );
        }

        if ( $post_id && ! PNW_Access::can_edit_workflow_post( $post_id ) ) {
            wp_die( esc_html__( 'Ezt a hírt már nem szerkesztheted.', 'petrik-news-workflow' ), 403 );
        }

        $categories = isset( $_POST['post_category'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['post_category'] ) ) : array();
        $allowed    = PNW_Access::allowed_category_ids();
        $categories = array_values( array_intersect( $categories, $allowed ) );

        if ( empty( $categories ) ) {
            self::redirect_notice( 'category_required', $post_id ? array( 'pnw_view' => 'edit', 'post_id' => $post_id ) : array( 'pnw_view' => 'new' ) );
        }

        $old_status = $post_id ? (string) get_post_status( $post_id ) : '';
        $new_status = 'submit' === $command ? 'pending' : 'draft';

        $data = array(
            'post_type'     => 'post',
            'post_title'    => $title,
            'post_content'  => $content,
            'post_excerpt'  => $excerpt,
            'post_status'   => $new_status,
            'post_category' => $categories,
        );

        if ( $post_id ) {
            $data['ID'] = $post_id;
            $result     = wp_update_post( $data, true );
        } else {
            $data['post_author'] = get_current_user_id();
            $result              = wp_insert_post( $data, true );
        }

        if ( is_wp_error( $result ) ) {
            self::redirect_notice( 'save_error' );
        }

        $post_id = (int) $result;
        update_post_meta( $post_id, '_pnw_managed', '1' );
        self::handle_featured_image( $post_id );

        if ( 'submit' === $command ) {
            delete_post_meta( $post_id, '_pnw_review_note' );
            PNW_Audit::log( $post_id, 'submitted', $old_status, 'pending' );
            PNW_Notifications::submitted( $post_id );
            self::redirect_notice( 'submitted' );
        }

        PNW_Audit::log( $post_id, $old_status ? 'updated_draft' : 'created_draft', $old_status, 'draft' );
        self::redirect_notice( 'draft_saved', array( 'pnw_view' => 'edit', 'post_id' => $post_id ) );
    }

    public static function reviewer_save(): void {
        self::require_login_and_cap( 'pnw_review_news' );
        check_admin_referer( 'pnw_reviewer_save', 'pnw_nonce' );

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $post    = get_post( $post_id );
        if ( ! $post || 'post' !== $post->post_type || 'pending' !== $post->post_status || '1' !== (string) get_post_meta( $post_id, '_pnw_managed', true ) ) {
            wp_die( esc_html__( 'A hír nem szerkeszthető ebben az állapotban.', 'petrik-news-workflow' ), 400 );
        }

        $title   = isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : '';
        $content = isset( $_POST['post_content'] ) ? wp_kses_post( wp_unslash( $_POST['post_content'] ) ) : '';
        $excerpt = isset( $_POST['post_excerpt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['post_excerpt'] ) ) : '';
        $cats    = isset( $_POST['post_category'] ) ? array_values( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['post_category'] ) ) ) ) : array();

        if ( '' === trim( $title ) || '' === trim( wp_strip_all_tags( $content ) ) || empty( $cats ) ) {
            self::redirect_notice( 'missing_fields', array( 'pnw_view' => 'review', 'post_id' => $post_id ) );
        }

        $result = wp_update_post(
            array(
                'ID'            => $post_id,
                'post_title'    => $title,
                'post_content'  => $content,
                'post_excerpt'  => $excerpt,
                'post_status'   => 'pending',
                'post_category' => $cats,
            ),
            true
        );

        if ( is_wp_error( $result ) ) {
            self::redirect_notice( 'save_error', array( 'pnw_view' => 'review', 'post_id' => $post_id ) );
        }

        self::handle_featured_image( $post_id );
        PNW_Audit::log( $post_id, 'reviewer_edited', 'pending', 'pending' );
        self::redirect_notice( 'review_saved', array( 'pnw_view' => 'review', 'post_id' => $post_id ) );
    }

    public static function review_news(): void {
        self::require_login_and_cap( 'pnw_review_news' );
        check_admin_referer( 'pnw_review_news', 'pnw_nonce' );

        $post_id  = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $decision = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
        $note     = isset( $_POST['review_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['review_note'] ) ) : '';
        $post     = get_post( $post_id );

        if ( ! $post || 'post' !== $post->post_type || 'pending' !== $post->post_status || '1' !== (string) get_post_meta( $post_id, '_pnw_managed', true ) ) {
            wp_die( esc_html__( 'Ez a hír már nem vár jóváhagyásra.', 'petrik-news-workflow' ), 400 );
        }

        if ( 'reject' === $decision ) {
            if ( '' === trim( $note ) ) {
                self::redirect_notice( 'rejection_note_required', array( 'pnw_view' => 'review', 'post_id' => $post_id ) );
            }

            $result = wp_update_post( array( 'ID' => $post_id, 'post_status' => PNW_Statuses::REVISION ), true );
            if ( is_wp_error( $result ) ) {
                self::redirect_notice( 'save_error', array( 'pnw_view' => 'review', 'post_id' => $post_id ) );
            }

            update_post_meta( $post_id, '_pnw_review_note', $note );
            update_post_meta( $post_id, '_pnw_last_reviewer', get_current_user_id() );
            update_post_meta( $post_id, '_pnw_last_reviewed_at', current_time( 'mysql' ) );
            PNW_Audit::log( $post_id, 'rejected', 'pending', PNW_Statuses::REVISION, $note );
            PNW_Notifications::rejected( $post_id, $note );
            self::redirect_notice( 'rejected' );
        }

        if ( 'approve' !== $decision ) {
            wp_die( esc_html__( 'Ismeretlen döntés.', 'petrik-news-workflow' ), 400 );
        }

        $result = wp_update_post(
            array(
                'ID'            => $post_id,
                'post_status'   => 'publish',
                'post_date'     => current_time( 'mysql' ),
                'post_date_gmt' => current_time( 'mysql', true ),
            ),
            true
        );
        if ( is_wp_error( $result ) ) {
            self::redirect_notice( 'save_error', array( 'pnw_view' => 'review', 'post_id' => $post_id ) );
        }

        delete_post_meta( $post_id, '_pnw_review_note' );
        update_post_meta( $post_id, '_pnw_last_reviewer', get_current_user_id() );
        update_post_meta( $post_id, '_pnw_last_reviewed_at', current_time( 'mysql' ) );
        PNW_Audit::log( $post_id, 'approved', 'pending', 'publish', $note );
        PNW_Notifications::approved( $post_id );
        self::redirect_notice( 'approved' );
    }

    public static function delete_news(): void {
        self::require_login_and_cap( 'pnw_submit_news' );
        check_admin_referer( 'pnw_delete_news', 'pnw_nonce' );

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $post    = get_post( $post_id );

        if ( ! $post || ! PNW_Access::can_edit_workflow_post( $post_id ) ) {
            wp_die( esc_html__( 'Ezt a hírt nem törölheted.', 'petrik-news-workflow' ), 403 );
        }

        PNW_Audit::log( $post_id, 'trashed', $post->post_status, 'trash' );
        wp_trash_post( $post_id );
        self::redirect_notice( 'trashed' );
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

    private static function redirect_login_error( string $error ): void {
        wp_safe_redirect(
            PNW_Plugin::manager_url(
                array(
                    'pnw_login_error' => sanitize_key( $error ),
                )
            )
        );
        exit;
    }

    private static function redirect_notice( string $notice, array $args = array() ): void {
        $args['pnw_notice'] = sanitize_key( $notice );
        wp_safe_redirect( PNW_Plugin::manager_url( $args ) );
        exit;
    }
}
