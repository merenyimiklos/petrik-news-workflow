<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PNW_Roles {
    public const MK_LEADER = 'petrik_mk_leader';
    public const DEPUTY    = 'petrik_deputy_director';
    public const DIRECTOR  = 'petrik_director';

    public static function init(): void {
        add_action( 'init', array( __CLASS__, 'keep_role_caps_current' ), 4 );
        add_action( 'init', array( __CLASS__, 'repair_custom_role_user_levels' ), 5 );
        add_action( 'init', array( __CLASS__, 'keep_admin_caps_current' ), 6 );
    }

    public static function install(): void {
        add_role(
            self::MK_LEADER,
            'Munkaközösség-vezető',
            self::role_capabilities()[ self::MK_LEADER ]
        );

        add_role(
            self::DEPUTY,
            'Igazgatóhelyettes',
            self::role_capabilities()[ self::DEPUTY ]
        );

        add_role(
            self::DIRECTOR,
            'Igazgató',
            self::role_capabilities()[ self::DIRECTOR ]
        );

        self::keep_role_caps_current();
        self::repair_custom_role_user_levels();
        self::keep_admin_caps_current();
    }

    /**
     * add_role() does not update an already existing role. Keep the capabilities
     * required by this plugin healthy after plugin upgrades as well.
     *
     * The MK leader intentionally has a normal Contributor-compatible WordPress
     * base capability set, plus media upload and the Petrik submission cap. It
     * does NOT get publish_posts, edit_others_posts or administrator rights.
     */
    public static function keep_role_caps_current(): void {
        foreach ( self::role_capabilities() as $role_name => $caps ) {
            $role = get_role( $role_name );
            if ( ! $role ) {
                continue;
            }

            foreach ( $caps as $cap => $grant ) {
                if ( $grant && ! $role->has_cap( $cap ) ) {
                    $role->add_cap( $cap, true );
                }
            }
        }
    }

    private static function role_capabilities(): array {
        return array(
            self::MK_LEADER => array(
                // Normal WordPress Contributor-compatible login/base rights.
                'read'            => true,
                'level_0'         => true,
                'level_1'         => true,
                'edit_posts'      => true,
                'delete_posts'    => true,

                // Needed by the frontend news editor.
                'upload_files'    => true,
                'pnw_submit_news' => true,
            ),
            self::DEPUTY => array(
                'read'                      => true,
                'level_0'                   => true,
                'level_1'                   => true,
                'upload_files'              => true,
                'edit_posts'                => true,
                'edit_others_posts'         => true,
                'edit_published_posts'      => true,
                'publish_posts'             => true,
                'delete_posts'              => true,
                'pnw_review_news'           => true,
                'pnw_view_audit_log'        => true,
                'pnw_manage_published_news' => true,
            ),
            self::DIRECTOR => array(
                'read'                      => true,
                'level_0'                   => true,
                'level_1'                   => true,
                'upload_files'              => true,
                'edit_posts'                => true,
                'edit_others_posts'         => true,
                'edit_published_posts'      => true,
                'publish_posts'             => true,
                'delete_posts'              => true,
                'delete_others_posts'       => true,
                'pnw_review_news'           => true,
                'pnw_view_audit_log'        => true,
                'pnw_manage_workflow'       => true,
                'pnw_manage_published_news' => true,
            ),
        );
    }

    /**
     * When capabilities are added to an already existing role, WordPress does
     * not automatically recalculate the persisted wp_user_level value of users
     * who already had that role. Keep it aligned with the role capabilities.
     */
    public static function repair_custom_role_user_levels(): void {
        foreach ( array( self::MK_LEADER, self::DEPUTY, self::DIRECTOR ) as $role_name ) {
            $user_ids = get_users(
                array(
                    'role'   => $role_name,
                    'fields' => 'ID',
                )
            );

            foreach ( $user_ids as $user_id ) {
                $user = new WP_User( (int) $user_id );
                $user->update_user_level_from_caps();
            }
        }
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
            'pnw_manage_published_news',
        );
    }

    public static function is_mk_leader( ?WP_User $user = null ): bool {
        $user = $user ?: wp_get_current_user();
        return in_array( self::MK_LEADER, (array) $user->roles, true );
    }

    public static function has_manager_role( ?WP_User $user = null ): bool {
        $user = $user ?: wp_get_current_user();
        if ( ! $user->exists() ) {
            return false;
        }

        return (bool) array_intersect(
            array( self::MK_LEADER, self::DEPUTY, self::DIRECTOR, 'administrator' ),
            (array) $user->roles
        );
    }

    public static function can_review( ?WP_User $user = null ): bool {
        $user = $user ?: wp_get_current_user();
        return $user->exists() && user_can( $user, 'pnw_review_news' );
    }
}
