<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait PNW_Frontend_Shell_Trait {
    public static function init(): void {
        add_shortcode( 'petrik_news_manager', array( __CLASS__, 'shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
    }

    public static function register_assets(): void {
        wp_register_style( 'pnw-app', PNW_URL . 'assets/css/pnw.css', array(), PNW_VERSION );
        wp_register_script( 'pnw-app', PNW_URL . 'assets/js/pnw.js', array(), PNW_VERSION, true );
    }

    public static function shortcode(): string {
        wp_enqueue_style( 'pnw-app' );
        wp_enqueue_script( 'pnw-app' );

        ob_start();
        echo '<div class="pnw-app">';

        if ( ! is_user_logged_in() ) {
            self::render_login();
            echo '</div>';
            return (string) ob_get_clean();
        }

        if ( ! current_user_can( 'pnw_submit_news' ) && ! current_user_can( 'pnw_review_news' ) ) {
            self::render_unauthorized();
            echo '</div>';
            return (string) ob_get_clean();
        }

        self::render_header();
        self::render_test_mode_banner();
        self::render_notice();

        $view = isset( $_GET['pnw_view'] ) ? sanitize_key( wp_unslash( $_GET['pnw_view'] ) ) : 'dashboard';

        switch ( $view ) {
            case 'new':
                self::render_editor();
                break;
            case 'edit':
                self::render_editor( isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0 );
                break;
            case 'review':
                self::render_review( isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0 );
                break;
            case 'audit':
                self::render_audit();
                break;
            default:
                self::render_dashboard();
                break;
        }

        echo '</div>';
        return (string) ob_get_clean();
    }

    private static function render_login(): void {
        $error = isset( $_GET['pnw_login_error'] ) ? sanitize_key( wp_unslash( $_GET['pnw_login_error'] ) ) : '';
        $messages = array(
            'empty_fields'        => 'Add meg a felhasználóneved/e-mail címed és a jelszavad.',
            'invalid_credentials' => 'A megadott felhasználónév/e-mail cím vagy jelszó nem megfelelő.',
            'no_access'           => 'Ezzel a felhasználóval nincs jogosultság a Petrik Hírkezelő használatához.',
        );

        echo '<section class="pnw-login-card">';
        echo '<div class="pnw-kicker">Petrik</div>';
        echo '<h2>Hírkezelő</h2>';
        if ( defined( 'PNW_TEST_MODE' ) && PNW_TEST_MODE ) {
            echo '<div class="pnw-notice pnw-notice-warning"><strong>TESZTÜZEM.</strong> Ezen a felületen jelenleg egyetlen hír sem publikálható.</div>';
        }
        echo '<p>A felületet munkaközösség-vezetők, igazgatóhelyettesek és az igazgató használhatják.</p>';

        if ( $error && isset( $messages[ $error ] ) ) {
            echo '<div class="pnw-notice pnw-notice-error pnw-login-error">' . esc_html( $messages[ $error ] ) . '</div>';
        }

        echo '<form class="pnw-login-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="pnw_frontend_login">';
        wp_nonce_field( 'pnw_frontend_login', 'pnw_login_nonce' );
        echo '<p class="login-username"><label for="pnw-login-user">Felhasználónév vagy e-mail</label><input id="pnw-login-user" type="text" name="pnw_login" autocomplete="username" required></p>';
        echo '<p class="login-password"><label for="pnw-login-password">Jelszó</label><input id="pnw-login-password" type="password" name="pnw_password" autocomplete="current-password" required></p>';
        echo '<p class="login-remember"><label><input type="checkbox" name="pnw_remember" value="1"> Emlékezzen rám</label></p>';
        echo '<p class="login-submit"><button class="pnw-button" type="submit">Bejelentkezés</button></p>';
        echo '</form>';
        echo '</section>';
    }

    private static function render_unauthorized(): void {
        echo '<section class="pnw-empty">';
        echo '<h2>Nincs hírkezelési jogosultságod</h2>';
        echo '<p>Ehhez a felülethez munkaközösség-vezetői vagy vezetői szerepkör szükséges.</p>';
        echo '<p><a class="pnw-button pnw-button-secondary" href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">Kijelentkezés</a></p>';
        echo '</section>';
    }

    private static function render_header(): void {
        $user = wp_get_current_user();
        echo '<header class="pnw-topbar">';
        echo '<div><div class="pnw-kicker">Petrik</div><h2>Hírkezelő</h2></div>';
        echo '<div class="pnw-user"><strong>' . esc_html( $user->display_name ) . '</strong><span>' . esc_html( self::role_label( $user ) ) . '</span></div>';
        echo '</header>';

        echo '<nav class="pnw-nav" aria-label="Hírkezelő navigáció">';
        echo '<a href="' . esc_url( PNW_Plugin::manager_url() ) . '">Áttekintés</a>';
        if ( current_user_can( 'pnw_submit_news' ) ) {
            echo '<a href="' . esc_url( PNW_Plugin::manager_url( array( 'pnw_view' => 'new' ) ) ) . '">+ Új hír</a>';
        }
        if ( current_user_can( 'pnw_view_audit_log' ) ) {
            echo '<a href="' . esc_url( PNW_Plugin::manager_url( array( 'pnw_view' => 'audit' ) ) ) . '">Napló</a>';
        }
        echo '<a class="pnw-nav-spacer" href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">Kijelentkezés</a>';
        echo '</nav>';
    }

    private static function render_test_mode_banner(): void {
        if ( ! defined( 'PNW_TEST_MODE' ) || ! PNW_TEST_MODE ) {
            return;
        }

        echo '<div class="pnw-notice pnw-notice-warning"><strong>TESZT MÓD AKTÍV.</strong> A jóváhagyás csak tesztállapotot rögzít. A plugin által kezelt hírek nem kerülhetnek publikus, privát vagy időzített állapotba.</div>';
    }

    private static function render_notice(): void {
        $key = isset( $_GET['pnw_notice'] ) ? sanitize_key( wp_unslash( $_GET['pnw_notice'] ) ) : '';
        if ( ! $key ) {
            return;
        }

        $approved_message = ( defined( 'PNW_TEST_MODE' ) && PNW_TEST_MODE )
            ? 'A hír TESZT módban jóváhagyva. Nem lett publikálva.'
            : 'A hír jóváhagyva és publikálva.';

        $messages = array(
            'draft_saved'             => array( 'success', 'A piszkozat mentve.' ),
            'submitted'               => array( 'success', 'A hír jóváhagyásra elküldve.' ),
            'review_saved'            => array( 'success', 'A vezetői módosítások mentve.' ),
            'approved'                => array( 'success', $approved_message ),
            'rejected'                => array( 'warning', 'A hír javításra visszaküldve.' ),
            'trashed'                 => array( 'success', 'A piszkozat a lomtárba került.' ),
            'missing_fields'          => array( 'error', 'A cím és a hír szövege kötelező.' ),
            'category_required'       => array( 'error', 'Legalább egy kategóriát válassz.' ),
            'rejection_note_required' => array( 'error', 'Visszaküldésnél kötelező megjegyzést írni.' ),
            'save_error'              => array( 'error', 'A mentés nem sikerült. Próbáld újra, vagy jelezd a webadminnak.' ),
        );

        if ( ! isset( $messages[ $key ] ) ) {
            return;
        }

        [ $type, $text ] = $messages[ $key ];
        echo '<div class="pnw-notice pnw-notice-' . esc_attr( $type ) . '">' . esc_html( $text ) . '</div>';
    }

    private static function role_label( WP_User $user ): string {
        if ( in_array( PNW_Roles::DIRECTOR, $user->roles, true ) ) {
            return 'Igazgató';
        }
        if ( in_array( PNW_Roles::DEPUTY, $user->roles, true ) ) {
            return 'Igazgatóhelyettes';
        }
        if ( in_array( PNW_Roles::MK_LEADER, $user->roles, true ) ) {
            return 'Munkaközösség-vezető';
        }
        if ( in_array( 'administrator', $user->roles, true ) ) {
            return 'Webadmin';
        }
        return 'Felhasználó';
    }
}
