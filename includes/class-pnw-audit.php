<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PNW_Audit {
    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'pnw_audit_log';
    }

    public static function install(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table           = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            action varchar(60) NOT NULL,
            old_status varchar(30) NOT NULL DEFAULT '',
            new_status varchar(30) NOT NULL DEFAULT '',
            note text NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) {$charset_collate};";

        dbDelta( $sql );
        update_option( 'pnw_db_version', PNW_VERSION );
    }

    public static function log(
        int $post_id,
        string $action,
        string $old_status = '',
        string $new_status = '',
        string $note = '',
        ?int $user_id = null
    ): void {
        global $wpdb;

        $user_id = null === $user_id ? get_current_user_id() : $user_id;

        $wpdb->insert(
            self::table_name(),
            array(
                'post_id'     => $post_id,
                'user_id'     => $user_id,
                'action'      => sanitize_key( $action ),
                'old_status'  => sanitize_key( $old_status ),
                'new_status'  => sanitize_key( $new_status ),
                'note'        => sanitize_textarea_field( $note ),
                'created_at'  => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
        );
    }

    public static function recent( int $limit = 50, int $post_id = 0 ): array {
        global $wpdb;

        $limit = max( 1, min( 200, $limit ) );
        $table = self::table_name();

        if ( $post_id > 0 ) {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$table} WHERE post_id = %d ORDER BY id DESC LIMIT %d",
                $post_id,
                $limit
            );
        } else {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d",
                $limit
            );
        }

        return (array) $wpdb->get_results( $sql );
    }
}
