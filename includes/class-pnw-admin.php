<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PNW_Admin {
    public static function init(): void {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'show_user_profile', array( __CLASS__, 'user_category_field' ) );
        add_action( 'edit_user_profile', array( __CLASS__, 'user_category_field' ) );
        add_action( 'personal_options_update', array( __CLASS__, 'save_user_category_field' ) );
        add_action( 'edit_user_profile_update', array( __CLASS__, 'save_user_category_field' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( PNW_FILE ), array( __CLASS__, 'plugin_links' ) );
    }

    public static function menu(): void {
        add_menu_page(
            'Petrik Hírkezelő',
            'Petrik Hírkezelő',
            'pnw_manage_workflow',
            'petrik-news-workflow',
            array( __CLASS__, 'settings_page' ),
            'dashicons-megaphone',
            25
        );
    }

    public static function settings_page(): void {
        if ( ! current_user_can( 'pnw_manage_workflow' ) ) {
            wp_die( esc_html__( 'Nincs jogosultságod.', 'petrik-news-workflow' ), 403 );
        }

        $page_id = PNW_Plugin::ensure_manager_page();
        $roles   = array(
            PNW_Roles::MK_LEADER => 'Munkaközösség-vezető',
            PNW_Roles::DEPUTY    => 'Igazgatóhelyettes',
            PNW_Roles::DIRECTOR  => 'Igazgató',
        );

        echo '<div class="wrap"><h1>Petrik Hírkezelő</h1><p>Az oldal híreihez kapcsolódó belső jóváhagyási workflow.</p>';
        echo '<div class="card" style="max-width:900px"><h2>Hírkezelő oldal</h2><p>A plugin a meglévő WordPress <code>post</code> bejegyzéseket használja.</p>';
        if ( $page_id ) {
            echo '<p><a class="button button-primary" target="_blank" href="' . esc_url( get_permalink( $page_id ) ) . '">Hírkezelő megnyitása</a> <a class="button" href="' . esc_url( get_edit_post_link( $page_id ) ) . '">Oldal szerkesztése</a></p>';
        }
        echo '<p>Shortcode: <code>[petrik_news_manager]</code></p></div>';

        echo '<div class="card" style="max-width:900px"><h2>Szerepkörök</h2><table class="widefat striped"><thead><tr><th>Szerepkör</th><th>Felhasználók</th><th>Fő jogosultság</th></tr></thead><tbody>';
        foreach ( $roles as $role => $label ) {
            $users = get_users( array( 'role' => $role, 'fields' => 'ID' ) );
            $cap   = PNW_Roles::MK_LEADER === $role ? 'Hírt ír és beküld' : 'Jóváhagy és publikál';
            echo '<tr><td><strong>' . esc_html( $label ) . '</strong><br><code>' . esc_html( $role ) . '</code></td><td>' . esc_html( (string) count( $users ) ) . '</td><td>' . esc_html( $cap ) . '</td></tr>';
        }
        echo '</tbody></table><p>A szerepkört a <a href="' . esc_url( admin_url( 'users.php' ) ) . '">Felhasználók</a> oldalon lehet hozzárendelni.</p></div>';

        echo '<div class="card" style="max-width:900px"><h2>Workflow</h2><p><strong>Piszkozat → Jóváhagyásra vár → Publikálva</strong>, vagy <strong>Javításra visszaküldve → újraküldés</strong>.</p><p>Nincs szükség PublishPressre vagy más fizetős bővítményre.</p></div>';

        self::render_role_diagnostics();
        echo '</div>';
    }

    private static function render_role_diagnostics(): void {
        $admin = get_role( 'administrator' );
        $mk    = get_role( PNW_Roles::MK_LEADER );

        if ( ! $admin || ! $mk ) {
            return;
        }

        $admin_caps = array_keys( array_filter( (array) $admin->capabilities ) );
        $mk_caps    = array_keys( array_filter( (array) $mk->capabilities ) );
        sort( $admin_caps );
        sort( $mk_caps );

        $admin_only = array_values( array_diff( $admin_caps, $mk_caps ) );
        sort( $admin_only );

        echo '<div class="card" style="max-width:900px"><h2>Belépési / szerepkör diagnosztika</h2>';
        echo '<p><strong>Fontos:</strong> a WordPress core-ban nincs külön „beléphet” capability. A hitelesítés után a szerepkör capability-jei szabályozzák, mit csinálhat a felhasználó. Ez a blokk azért van, hogy lássuk, valamelyik meglévő plugin/sablon mely admin-jogot használhatja saját belépési feltételként.</p>';
        echo '<table class="widefat striped"><tbody>';
        echo '<tr><th style="width:230px">Adminisztrátor capability-k</th><td>' . esc_html( (string) count( $admin_caps ) ) . '</td></tr>';
        echo '<tr><th>MK-vezető capability-k</th><td>' . esc_html( (string) count( $mk_caps ) ) . '</td></tr>';
        echo '<tr><th>Csak adminnál lévő capability-k</th><td>' . esc_html( (string) count( $admin_only ) ) . '</td></tr>';
        echo '</tbody></table>';

        echo '<details style="margin-top:16px"><summary><strong>MK-vezető összes capability megjelenítése</strong></summary><p style="line-height:2">';
        foreach ( $mk_caps as $cap ) {
            echo '<code style="display:inline-block;margin:2px 4px 2px 0">' . esc_html( $cap ) . '</code> ';
        }
        echo '</p></details>';

        echo '<details style="margin-top:10px"><summary><strong>Adminisztrátorban megvan, MK-vezetőben nincs</strong></summary><p style="line-height:2">';
        foreach ( $admin_only as $cap ) {
            echo '<code style="display:inline-block;margin:2px 4px 2px 0">' . esc_html( $cap ) . '</code> ';
        }
        echo '</p></details>';
        echo '<p class="description">A következő lépésben ebből nem adunk vakon adminjogokat az MK-vezetőnek: célzottan azonosítjuk, mely capability-t figyeli a jelenlegi Petrik környezet.</p>';
        echo '</div>';
    }

    public static function user_category_field( WP_User $user ): void {
        if ( ! current_user_can( 'edit_user', $user->ID ) || ! in_array( PNW_Roles::MK_LEADER, (array) $user->roles, true ) ) {
            return;
        }

        $saved      = get_user_meta( $user->ID, 'pnw_allowed_categories', true );
        $saved      = is_array( $saved ) ? array_map( 'absint', $saved ) : array();
        $categories = get_categories( array( 'hide_empty' => false ) );

        echo '<h2>Petrik Hírkezelő</h2><table class="form-table"><tr><th>Engedélyezett hírkategóriák</th><td>';
        wp_nonce_field( 'pnw_user_categories_' . $user->ID, 'pnw_user_categories_nonce' );
        echo '<fieldset><p class="description">Ha egy kategória sincs kijelölve, az MK-vezető minden kategóriát használhat.</p>';
        foreach ( $categories as $category ) {
            echo '<label style="display:block;margin:5px 0"><input type="checkbox" name="pnw_allowed_categories[]" value="' . esc_attr( (string) $category->term_id ) . '" ' . checked( in_array( (int) $category->term_id, $saved, true ), true, false ) . '> ' . esc_html( $category->name ) . '</label>';
        }
        echo '</fieldset></td></tr></table>';
    }

    public static function save_user_category_field( int $user_id ): void {
        if ( ! current_user_can( 'edit_user', $user_id ) ) {
            return;
        }

        if ( empty( $_POST['pnw_user_categories_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pnw_user_categories_nonce'] ) ), 'pnw_user_categories_' . $user_id ) ) {
            return;
        }

        $categories = isset( $_POST['pnw_allowed_categories'] ) ? array_values( array_filter( array_map( 'absint', (array) wp_unslash( $_POST['pnw_allowed_categories'] ) ) ) ) : array();
        update_user_meta( $user_id, 'pnw_allowed_categories', $categories );
    }

    public static function plugin_links( array $links ): array {
        array_unshift( $links, '<a href="' . esc_url( PNW_Plugin::manager_url() ) . '">Hírkezelő</a>' );
        return $links;
    }
}
