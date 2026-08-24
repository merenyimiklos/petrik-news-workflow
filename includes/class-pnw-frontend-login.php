<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles the Hírkezelő login on the public manager page.
 *
 * We deliberately authenticate against WordPress core credentials and then
 * create a normal WordPress authentication cookie. This means an MK leader is
 * genuinely logged in to WordPress, just like an administrator, while
 * PNW_Access keeps them out of wp-admin and redirects them to the Hírkezelő.
 *
 * Using the core username/password checker directly avoids role-based login
 * blockers from interfering with our custom Petrik roles, while still using
 * WordPress password hashing and authentication cookies.
 */
final class PNW_Frontend_Login {
    public static function init(): void {
        add_action( 'template_redirect', array( __CLASS__, 'maybe_handle_login' ), 1 );
    }

    public static function maybe_handle_login(): void {
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

        // Verify the password using WordPress' own hashing implementation.
        if ( ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
            self::redirect_error( 'invalid_credentials' );
        }

        // Only the Petrik workflow roles and administrators may enter here.
        if ( ! PNW_Roles::has_manager_role( $user ) ) {
            self::redirect_error( 'no_access' );
        }

        // Create a genuine WordPress login session/cookie.
        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID, $remember, is_ssl() );
        do_action( 'wp_login', $user->user_login, $user );

        wp_safe_redirect( PNW_Plugin::manager_url() );
        exit;
    }

    private static function redirect_error( string $code ): void {
        wp_safe_redirect(
            add_query_arg(
                'pnw_login_error',
                sanitize_key( $code ),
                PNW_Plugin::manager_url()
            )
        );
        exit;
    }
}
