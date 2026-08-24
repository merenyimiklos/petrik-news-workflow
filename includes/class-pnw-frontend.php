<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once PNW_DIR . 'includes/trait-pnw-frontend-shell.php';
require_once PNW_DIR . 'includes/trait-pnw-frontend-dashboard.php';
require_once PNW_DIR . 'includes/trait-pnw-frontend-editor.php';
require_once PNW_DIR . 'includes/trait-pnw-frontend-published.php';
require_once PNW_DIR . 'includes/trait-pnw-frontend-audit.php';

final class PNW_Frontend {
    use PNW_Frontend_Shell_Trait;
    use PNW_Frontend_Dashboard_Trait;
    use PNW_Frontend_Editor_Trait;
    use PNW_Frontend_Published_Trait;
    use PNW_Frontend_Audit_Trait;
}
