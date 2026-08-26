<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Extra editor helpers for non-technical Hírkezelő users.
 *
 * Handles safe AJAX category creation and loads the lightweight editor-tool UI
 * on the frontend manager page. Category creation deliberately stays inside
 * the Hírkezelő instead of granting WordPress manage_categories capability.
 */
final class PNW_Editor_Tools {
    public static function init(): void {
        add_action( 'wp_ajax_pnw_create_category', array( __CLASS__, 'create_category' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 30 );
    }

    public static function enqueue_assets(): void {
        if ( self::is_manager_page() ) {
            wp_enqueue_style(
                'pnw-editor-tools',
                PNW_URL . 'assets/css/pnw-editor-tools.css',
                array( 'pnw-app' ),
                PNW_VERSION
            );

            wp_enqueue_style(
                'pnw-validation',
                PNW_URL . 'assets/css/pnw-validation.css',
                array( 'pnw-app', 'pnw-editor-tools' ),
                PNW_VERSION
            );

            wp_enqueue_script(
                'pnw-validation',
                PNW_URL . 'assets/js/pnw-validation.js',
                array( 'pnw-app' ),
                PNW_VERSION,
                true
            );

            if ( wp_script_is( 'pnw-app', 'registered' ) ) {
                wp_localize_script(
                    'pnw-app',
                    'PNWEditorTools',
                    array(
                        'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
                        'categoryNonce' => wp_create_nonce( 'pnw_create_category' ),
                        'testMode'      => defined( 'PNW_TEST_MODE' ) && PNW_TEST_MODE,
                    )
                );
            }
        }
    }

    public static function create_category(): void {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'A művelethez be kell jelentkezni.' ), 401 );
        }

        if (
            ! current_user_can( 'pnw_submit_news' )
            && ! current_user_can( 'pnw_review_news' )
            && ! current_user_can( 'pnw_manage_published_news' )
        ) {
            wp_send_json_error( array( 'message' => 'Nincs jogosultságod kategória létrehozásához.' ), 403 );
        }

        check_ajax_referer( 'pnw_create_category', 'nonce' );

        $name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        $name   = trim( $name );
        $parent = isset( $_POST['parent'] ) ? absint( $_POST['parent'] ) : 0;

        if ( '' === $name ) {
            wp_send_json_error( array( 'message' => 'Add meg az új kategória nevét.' ), 400 );
        }

        if ( function_exists( 'mb_strlen' ) ? mb_strlen( $name ) > 80 : strlen( $name ) > 80 ) {
            wp_send_json_error( array( 'message' => 'A kategória neve legfeljebb 80 karakter lehet.' ), 400 );
        }

        if ( $parent > 0 ) {
            $parent_term = term_exists( $parent, 'category' );
            if ( ! $parent_term ) {
                wp_send_json_error( array( 'message' => 'A kiválasztott szülőkategória nem található.' ), 400 );
            }

            // MK leaders with a restricted category list may only create under
            // one of the categories already available to them.
            if ( current_user_can( 'pnw_submit_news' ) && ! current_user_can( 'pnw_review_news' ) ) {
                $allowed = PNW_Access::allowed_category_ids();
                if ( ! in_array( $parent, $allowed, true ) ) {
                    wp_send_json_error( array( 'message' => 'Ehhez a szülőkategóriához nincs jogosultságod.' ), 403 );
                }
            }
        }

        $existing = term_exists( $name, 'category', $parent );
        if ( $existing ) {
            $term_id = is_array( $existing ) ? (int) $existing['term_id'] : (int) $existing;
            $term    = get_term( $term_id, 'category' );
            self::allow_category_for_current_user( $term_id );

            wp_send_json_success(
                array(
                    'id'      => $term_id,
                    'name'    => $term instanceof WP_Term ? $term->name : $name,
                    'parent'  => $term instanceof WP_Term ? (int) $term->parent : $parent,
                    'message' => 'Ez a kategória már létezett, ezért kiválasztottuk neked.',
                    'existing'=> true,
                )
            );
        }

        $result = wp_insert_term(
            $name,
            'category',
            array(
                'parent' => $parent,
            )
        );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error(
                array(
                    'message' => $result->get_error_message() ?: 'A kategória létrehozása nem sikerült.',
                ),
                400
            );
        }

        $term_id = (int) $result['term_id'];
        $term    = get_term( $term_id, 'category' );
        self::allow_category_for_current_user( $term_id );

        wp_send_json_success(
            array(
                'id'       => $term_id,
                'name'     => $term instanceof WP_Term ? $term->name : $name,
                'parent'   => $term instanceof WP_Term ? (int) $term->parent : $parent,
                'message'  => 'Az új kategória létrejött és ki is választottuk.',
                'existing' => false,
            )
        );
    }

    private static function allow_category_for_current_user( int $term_id ): void {
        $user_id = get_current_user_id();
        if ( $user_id <= 0 ) {
            return;
        }

        $saved = get_user_meta( $user_id, 'pnw_allowed_categories', true );
        if ( ! is_array( $saved ) || empty( $saved ) ) {
            return;
        }

        $saved[] = $term_id;
        $saved   = array_values( array_unique( array_filter( array_map( 'absint', $saved ) ) ) );
        update_user_meta( $user_id, 'pnw_allowed_categories', $saved );
    }

    private static function is_manager_page(): bool {
        $page_id = absint( get_option( 'pnw_manager_page_id', 0 ) );
        return $page_id > 0 && is_page( $page_id );
    }
}
