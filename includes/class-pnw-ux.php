<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Frontend usability helpers for the Hírkezelő.
 *
 * Provides persistent AJAX autosave for author drafts and loads the lightweight
 * template/scheduling/mobile-enhancement assets. The browser also keeps a local
 * safety copy before the first server-side draft can be created.
 */
final class PNW_UX {
    public static function init(): void {
        add_action( 'wp_ajax_pnw_autosave_news', array( __CLASS__, 'autosave_news' ) );
        add_action( 'wp_ajax_pnw_reset_draft', array( __CLASS__, 'reset_draft' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 40 );
    }

    public static function enqueue_assets(): void {
        if ( ! self::is_manager_page() || ! is_user_logged_in() ) {
            return;
        }

        wp_enqueue_style(
            'pnw-ux',
            PNW_URL . 'assets/css/pnw-ux.css',
            array( 'pnw-app' ),
            PNW_VERSION
        );

        wp_enqueue_style(
            'pnw-draft-tools',
            PNW_URL . 'assets/css/pnw-draft-tools.css',
            array( 'pnw-app', 'pnw-ux' ),
            PNW_VERSION
        );

        wp_enqueue_script(
            'pnw-ux',
            PNW_URL . 'assets/js/pnw-ux.js',
            array( 'pnw-app' ),
            PNW_VERSION,
            true
        );

        wp_enqueue_script(
            'pnw-draft-tools',
            PNW_URL . 'assets/js/pnw-draft-tools.js',
            array( 'pnw-ux' ),
            PNW_VERSION,
            true
        );

        $timezone = wp_timezone();
        $minimum  = new DateTimeImmutable( '+5 minutes', $timezone );

        wp_localize_script(
            'pnw-ux',
            'PNWUX',
            array(
                'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
                'autosaveNonce'    => wp_create_nonce( 'pnw_autosave_news' ),
                'resetNonce'       => wp_create_nonce( 'pnw_reset_draft' ),
                'autosaveInterval' => 30000,
                'userId'           => get_current_user_id(),
                'managerUrl'       => PNW_Plugin::manager_url(),
                'scheduleMin'      => $minimum->format( 'Y-m-d\TH:i' ),
                'timezone'         => wp_timezone_string() ?: 'Europe/Budapest',
            )
        );
    }

    public static function autosave_news(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'pnw_submit_news' ) ) {
            wp_send_json_error( array( 'message' => 'Nincs jogosultságod az automatikus mentéshez.' ), 403 );
        }

        check_ajax_referer( 'pnw_autosave_news', 'nonce' );

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $title   = isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : '';
        $content = isset( $_POST['post_content'] ) ? wp_kses_post( wp_unslash( $_POST['post_content'] ) ) : '';
        $excerpt = isset( $_POST['post_excerpt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['post_excerpt'] ) ) : '';

        $categories = isset( $_POST['post_category'] )
            ? array_values( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['post_category'] ) ) ) )
            : array();
        $categories = array_values( array_intersect( $categories, PNW_Access::allowed_category_ids() ) );

        // A server-side WordPress draft is only created once a real category is
        // selected. Before that the browser-side safety copy keeps the text, so
        // WordPress cannot silently assign its default category.
        if ( empty( $categories ) ) {
            wp_send_json_error(
                array(
                    'code'    => 'category_pending',
                    'message' => 'Válassz kategóriát, hogy a piszkozat a rendszerbe is automatikusan menthető legyen.',
                ),
                400
            );
        }

        if ( $post_id > 0 ) {
            if ( ! PNW_Access::can_edit_workflow_post( $post_id ) ) {
                wp_send_json_error( array( 'message' => 'Ezt a hírt már nem lehet automatikusan menteni.' ), 403 );
            }

            $current_status = (string) get_post_status( $post_id );
            if ( ! in_array( $current_status, array( 'draft', PNW_Statuses::REVISION ), true ) ) {
                wp_send_json_error( array( 'message' => 'Ez a hír már nem szerkeszthető piszkozatként.' ), 409 );
            }
            $status = $current_status;
        } else {
            $status = 'draft';
        }

        $data = array(
            'post_type'     => 'post',
            'post_title'    => $title,
            'post_content'  => $content,
            'post_excerpt'  => $excerpt,
            'post_status'   => $status,
            'post_category' => $categories,
        );

        if ( $post_id > 0 ) {
            $data['ID'] = $post_id;
            $result     = wp_update_post( $data, true );
        } else {
            $data['post_author'] = get_current_user_id();
            $result              = wp_insert_post( $data, true );
        }

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => 'Az automatikus mentés most nem sikerült.' ), 500 );
        }

        $post_id = (int) $result;
        update_post_meta( $post_id, '_pnw_managed', '1' );
        update_post_meta( $post_id, '_pnw_autosaved_at', current_time( 'mysql' ) );

        wp_send_json_success(
            array(
                'postId'  => $post_id,
                'savedAt' => wp_date( 'H:i', current_time( 'timestamp', true ), wp_timezone() ),
                'editUrl' => PNW_Plugin::manager_url(
                    array(
                        'pnw_view' => 'edit',
                        'post_id'  => $post_id,
                    )
                ),
                'status'  => $status,
            )
        );
    }

    public static function reset_draft(): void {
        if ( ! is_user_logged_in() || ! current_user_can( 'pnw_submit_news' ) ) {
            wp_send_json_error( array( 'message' => 'Nincs jogosultságod a piszkozat kiürítéséhez.' ), 403 );
        }

        check_ajax_referer( 'pnw_reset_draft', 'nonce' );

        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        $post    = $post_id ? get_post( $post_id ) : null;

        if ( ! $post || 'post' !== $post->post_type || 'draft' !== $post->post_status ) {
            wp_send_json_error( array( 'message' => 'Csak saját, még be nem küldött piszkozat üríthető ki.' ), 409 );
        }

        if ( ! PNW_Access::can_edit_workflow_post( $post_id ) ) {
            wp_send_json_error( array( 'message' => 'Ezt a piszkozatot nem módosíthatod.' ), 403 );
        }

        $result = wp_update_post(
            array(
                'ID'           => $post_id,
                'post_title'   => '',
                'post_content' => '',
                'post_excerpt' => '',
                'post_status'  => 'draft',
            ),
            true
        );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => 'A piszkozat kiürítése most nem sikerült.' ), 500 );
        }

        // A Tiszta lap valóban üres szerkesztési állapotot ad. A médiatárban lévő
        // fájlt nem töröljük, csak leválasztjuk erről a piszkozatról.
        wp_set_object_terms( $post_id, array(), 'category' );
        delete_post_thumbnail( $post_id );
        delete_post_meta( $post_id, '_pnw_review_note' );
        delete_post_meta( $post_id, '_pnw_autosaved_at' );
        PNW_Audit::log( $post_id, 'draft_reset', 'draft', 'draft', 'A szerző tiszta lappal újrakezdte a piszkozatot.' );

        wp_send_json_success( array( 'postId' => $post_id ) );
    }

    private static function is_manager_page(): bool {
        $page_id = absint( get_option( 'pnw_manager_page_id', 0 ) );
        return $page_id > 0 && is_page( $page_id );
    }
}
