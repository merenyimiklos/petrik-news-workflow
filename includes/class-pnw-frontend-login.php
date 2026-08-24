<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles the Hírkezelő login on the public manager page.
 *
 * Credentials are verified with WordPress' password hashing, then a genuine
 * WordPress auth session is created. We intentionally do not fire wp_login
 * here: a third-party role-based login hook on the Petrik site can otherwise
 * immediately clear a valid MK-leader session after successful authentication.
 */
final class PNW_Frontend_Login {
    private const HANDOFF_PREFIX = 'pnw_login_handoff_';

    public static function init(): void {
        add_action( 'template_redirect', array( __CLASS__, 'maybe_handle_login' ), 1 );
    }

    public static function maybe_handle_login(): void {
        if ( 'GET' === strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) && ! empty( $_GET['pnw_auth_check'] ) ) {
            self::handle_auth_handoff();
            return;
        }

        if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
            return;
        }

        $action = isset( $_POST['pnw_frontend_action'] ) ? sanitize_key( wp_unslash( $_POST['pnw_frontend_action'] ) ) : '';
        if ( 'login' !== $action ) {
            return;
        }

        if ( is_user_logged_in() ) {
            wp_safe_redirect( PNW_Plugin::manager_url() );
            exit;
        }

        if ( empty( $_POST['pnw_login_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pnw_login_nonce'] ) ), 'pnw_frontend_login' ) ) {
            self::redirect_error( 'security' );
        }

        $login    = isset( $_POST['pnw_login'] ) ? sanitize_text_field( wp_unslash( $_POST['pnw_login'] ) ) : '';
        $password = isset( $_POST['pnw_password'] ) ? (string) wp_unslash( $_POST['pnw_password'] ) : '';
        $remember = ! empty( $_POST['pnw_remember'] );

        if ( '' === trim( $login ) || '' === $password ) {
            self::redirect_error( 'empty_fields' );
        }

        $user = is_email( $login )
            ? get_user_by( 'email', $login )
            : get_user_by( 'login', $login );

        if ( ! $user instanceof WP_User ) {
            self::redirect_error( 'invalid_credentials' );
        }

        if ( ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
            self::redirect_error( 'invalid_credentials' );
        }

        if ( ! PNW_Roles::has_manager_role( $user ) ) {
            self::redirect_error( 'no_access' );
        }

        // Remove any stale auth cookies first, then create the normal WordPress
        // session. Let WordPress decide the secure-cookie mode from site config.
        wp_clear_auth_cookie();
        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID, $remember );

        // One-time handoff. Besides avoiding a cached login response, it gives
        // us one clean follow-up request in which we can re-issue the cookie if
        // another plugin interfered with the first Set-Cookie headers.
        $token = wp_generate_password( 40, false, false );
        set_transient(
            self::HANDOFF_PREFIX . $token,
            array(
                'user_id'  => (int) $user->ID,
                'remember' => $remember ? 1 : 0,
            ),
            2 * MINUTE_IN_SECONDS
        );

        nocache_headers();
        wp_safe_redirect(
            PNW_Plugin::manager_url(
                array(
                    'pnw_auth_check' => $token,
                    'pnw_nocache'    => time(),
                )
            )
        );
        exit;
    }

    private static function handle_auth_handoff(): void {
        $token = sanitize_text_field( wp_unslash( $_GET['pnw_auth_check'] ) );
        if ( '' === $token ) {
            self::redirect_error( 'session_failed' );
        }

        $key  = self::HANDOFF_PREFIX . $token;
        $data = get_transient( $key );
        delete_transient( $key );

        if ( ! is_array( $data ) || empty( $data['user_id'] ) ) {
            self::redirect_error( 'session_failed' );
        }

        $user = get_user_by( 'id', (int) $data['user_id'] );
        if ( ! $user instanceof WP_User || ! PNW_Roles::has_manager_role( $user ) ) {
            self::redirect_error( 'session_failed' );
        }

        if ( ! is_user_logged_in() ) {
            wp_clear_auth_cookie();
            wp_set_current_user( $user->ID );
            wp_set_auth_cookie( $user->ID, ! empty( $data['remember'] ) );
        }

        nocache_headers();
        wp_safe_redirect(
            PNW_Plugin::manager_url(
                array(
                    'pnw_session_check' => '1',
                    'pnw_nocache'       => time(),
                )
            )
        );
        exit;
    }

    private static function redirect_error( string $code ): void {
        nocache_headers();
        wp_safe_redirect(
            add_query_arg(
                array(
                    'pnw_login_error' => sanitize_key( $code ),
                    'pnw_nocache'     => time(),
                ),
                PNW_Plugin::manager_url()
            )
        );
        exit;
    }
}
