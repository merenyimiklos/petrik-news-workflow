<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PNW_Roles {
    public const MK_LEADER = 'petrik_mk_leader';
    public const DEPUTY    = 'petrik_deputy_director';
    public const DIRECTOR  = 'petrik_director';

    public static function init(): void {
        add_action( 'init', array( __CLASS__, 'keep_admin_caps_current' ), 5 );
    }

    public static function install(): void {
        add_role(
            self::MK_LEADER,
            'Munkaközösség-vezető',
            array(
                'read'              => true,
                'upload_files'      => true,
                'edit_posts'        => true,
                'pnw_submit_news'   => true,
            )
        );

        add_role(
            self::DEPUTY,
            'Igazgatóhelyettes',
            array(
                'read'                  => true,
                'upload_files'          => true,
                'edit_posts'            => true,
                'edit_others_posts'     => true,
                'edit_published_posts'  => true,
                'publish_posts'         => true,
                'delete_posts'          => true,
                'pnw_review_news'       => true,
                'pnw_view_audit_log'    => true,
            )
        );

        add_role(
            self::DIRECTOR,
            'Igazgató',
            array(
                'read'                  => true,
                'upload_files'          => true,
                'edit_posts'            => true,
                'edit_others_posts'     => true,
                'edit_published_posts'  => true,
                'publish_posts'         => true,
                'delete_posts'          => true,
                'delete_others_posts'   => true,
                'pnw_review_news'       => true,
                'pnw_view_audit_log'    => true,
                'pnw_manage_workflow'   => true,
            )
        );

        self::keep_admin_caps_current();
    }

    public static function keep_admin_caps_current(): void {
        $admin = get_role( 'administrator' );
        if ( ! $admin ) {
            return;
        }

        foreach ( self::custom_caps() as $cap ) {
            if ( ! $admin->has_cap( $cap ) ) {
                $admin->add_cap( $cap );
            }
        }
    }

    public static function custom_caps(): array {
        return array(
            'pnw_submit_news',
            'pnw_review_news',
            'pnw_view_audit_log',
            'pnw_manage_workflow',
        );
    }

    public static function is_mk_leader( ?WP_User $user = null ): bool {
        $user = $user ?: wp_get_current_user();
        return in_array( self::MK_LEADER, (array) $user->roles, true );
    }

    public static function can_review( ?WP_User $user = null ): bool {
        $user = $user ?: wp_get_current_user();
        return $user->exists() && user_can( $user, 'pnw_review_news' );
    }
}
