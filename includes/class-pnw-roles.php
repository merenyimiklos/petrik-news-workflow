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
     * level_0 / level_1 are legacy WordPress capabilities. Core WordPress does
     * not require them for authentication, but older themes/plugins may still
     * use the derived user_level value to decide whether a user may log in.
     * Giving our custom roles a minimal Contributor-like legacy level avoids
     * those compatibility problems without granting administrator access.
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
                'read'            => true,
                'level_0'         => true,
                'level_1'         => true,
                'upload_files'    => true,
                'edit_posts'      => true,
                'pnw_submit_news' => true,
            ),
            self::DEPUTY => array(
                'read'                 => true,
                'level_0'              => true,
                'level_1'              => true,
                'upload_files'         => true,
                'edit_posts'           => true,
                'edit_others_posts'    => true,
                'edit_published_posts' => true,
                'publish_posts'        => true,
                'delete_posts'         => true,
                'pnw_review_news'      => true,
                'pnw_view_audit_log'   => true,
            ),
            self::DIRECTOR => array(
                'read'                 => true,
                'level_0'              => true,
                'level_1'              => true,
                'upload_files'         => true,
                'edit_posts'           => true,
                'edit_others_posts'    => true,
                'edit_published_posts' => true,
                'publish_posts'        => true,
                'delete_posts'         => true,
                'delete_others_posts'  => true,
                'pnw_review_news'      => true,
                'pnw_view_audit_log'   => true,
                'pnw_manage_workflow'  => true,
            ),
        );
    }

    /**
     * When capabilities are added to an already existing role, WordPress does
     * not automatically recalculate the persisted wp_user_level value of users
     * who already had that role. Repair it once it is below our minimal level.
     */
    public static function repair_custom_role_user_levels(): void {
        global $wpdb;

        foreach ( array( self::MK_LEADER, self::DEPUTY, self::DIRECTOR ) as $role_name ) {
            $user_ids = get_users(
                array(
                    'role'   => $role_name,
                    'fields' => 'ID',
                )
            );

            foreach ( $user_ids as $user_id ) {
                $user_id   = (int) $user_id;
                $meta_key  = $wpdb->get_blog_prefix() . 'user_level';
                $old_level = (int) get_user_meta( $user_id, $meta_key, true );

                if ( $old_level >= 1 ) {
                    continue;
                }

                $user = new WP_User( $user_id );
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
