<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles the Hírkezelő login without touching /wp-login.php or /wp-admin.
 * This keeps the frontend login compatible with WPS Hide Login.
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

        // Resolve e-mail explicitly so login does not depend on optional auth filters.
        $user_login = $login;
        if ( is_email( $login ) ) {
            $email_user = get_user_by( 'email', $login );
            if ( $email_user instanceof WP_User ) {
                $user_login = $email_user->user_login;
            }
        }

        $user = wp_signon(
            array(
                'user_login'    => $user_login,
                'user_password' => $password,
                'remember'      => $remember,
            ),
            is_ssl()
        );

        if ( is_wp_error( $user ) ) {
            $codes = $user->get_error_codes();
            $credential_errors = array( 'invalid_username', 'invalid_email', 'incorrect_password', 'empty_username', 'empty_password' );
            if ( array_intersect( $credential_errors, $codes ) ) {
                self::redirect_error( 'invalid_credentials' );
            }

            // TEST MODE diagnostics: another authentication hook/plugin rejected
            // an otherwise normal WordPress login request.
            self::redirect_error( 'auth_rejected' );
        }

        // Role membership is the source of truth for entering the manager.
        // Capabilities are still checked for individual actions inside it.
        if ( ! PNW_Roles::has_manager_role( $user ) ) {
            wp_logout();
            self::redirect_error( 'no_access' );
        }

        wp_set_current_user( $user->ID );
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
