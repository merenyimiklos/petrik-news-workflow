<?php
/**
 * Plugin Name: Petrik News Workflow
 * Plugin URI:  https://github.com/merenyimiklos/petrik-news-workflow
 * Description: Belső hírbeküldési és vezetői jóváhagyási workflow a Petrik WordPress oldalához.
 * Version:     1.0.15-test
 * Author:      Petrik
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: petrik-news-workflow
 * Requires at least: 6.4
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PNW_VERSION', '1.0.15-test' );
define( 'PNW_FILE', __FILE__ );
define( 'PNW_DIR', plugin_dir_path( __FILE__ ) );
define( 'PNW_URL', plugin_dir_url( __FILE__ ) );

define( 'PNW_TEST_MODE', true );

require_once PNW_DIR . 'includes/class-pnw-roles.php';
require_once PNW_DIR . 'includes/class-pnw-statuses.php';
require_once PNW_DIR . 'includes/class-pnw-audit.php';
require_once PNW_DIR . 'includes/class-pnw-notifications.php';
require_once PNW_DIR . 'includes/class-pnw-test-mode.php';
require_once PNW_DIR . 'includes/class-pnw-access.php';
require_once PNW_DIR . 'includes/class-pnw-actions.php';
require_once PNW_DIR . 'includes/class-pnw-published-actions.php';
require_once PNW_DIR . 'includes/class-pnw-frontend-login.php';
require_once PNW_DIR . 'includes/class-pnw-updater.php';
require_once PNW_DIR . 'includes/class-pnw-frontend.php';
require_once PNW_DIR . 'includes/class-pnw-admin.php';
require_once PNW_DIR . 'includes/class-pnw-plugin.php';

PNW_Test_Mode::init();
PNW_Frontend_Login::init();
PNW_Updater::init();
PNW_Published_Actions::init();

register_activation_hook( __FILE__, array( 'PNW_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'PNW_Plugin', 'deactivate' ) );

PNW_Plugin::instance()->boot();
