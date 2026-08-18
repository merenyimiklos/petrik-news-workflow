<?php
/**
 * Conservative uninstall: workflow content and audit history are intentionally retained.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'pnw_manager_page_id' );
delete_option( 'pnw_db_version' );

// Roles, posts, user category mappings and the audit table are retained deliberately.
// This prevents accidental loss of institutional publishing history.
