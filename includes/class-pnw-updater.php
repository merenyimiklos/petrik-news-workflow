<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Native WordPress updater backed by the public GitHub repository.
 *
 * The plugin reads update.json directly from raw.githubusercontent.com and
 * exposes a normal WordPress plugin update when the remote version is newer.
 * The downloaded GitHub branch archive has a generated folder name, so we
 * normalize it back to petrik-news-workflow during the upgrade.
 */
final class PNW_Updater {
    private const OWNER     = 'merenyimiklos';
    private const REPO      = 'petrik-news-workflow';
    private const BRANCH    = 'main';
    private const CACHE_KEY = 'pnw_github_update_manifest_v2';

    public static function init(): void {
        add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_for_update' ) );
        add_filter( 'plugins_api', array( __CLASS__, 'plugin_information' ), 20, 3 );
        add_filter( 'upgrader_source_selection', array( __CLASS__, 'normalize_source_folder' ), 10, 4 );
        add_action( 'upgrader_process_complete', array( __CLASS__, 'clear_cache_after_upgrade' ), 10, 2 );
        add_action( 'load-update-core.php', array( __CLASS__, 'maybe_clear_cache_for_manual_check' ), 1 );
        add_action( 'load-plugins.php', array( __CLASS__, 'maybe_clear_cache_for_manual_check' ), 1 );
    }

    public static function maybe_clear_cache_for_manual_check(): void {
        if ( ! current_user_can( 'update_plugins' ) ) {
            return;
        }

        if ( isset( $_GET['force-check'] ) || isset( $_GET['plugin_status'] ) ) {
            delete_site_transient( self::CACHE_KEY );
        }
    }

    public static function check_for_update( $transient ) {
        if ( ! is_object( $transient ) ) {
            return $transient;
        }

        $manifest = self::manifest();
        if ( ! is_array( $manifest ) || empty( $manifest['version'] ) || empty( $manifest['package'] ) ) {
            return $transient;
        }

        $plugin         = plugin_basename( PNW_FILE );
        $remote_version = (string) $manifest['version'];

        if ( version_compare( PNW_VERSION, $remote_version, '>=' ) ) {
            if ( isset( $transient->response[ $plugin ] ) ) {
                unset( $transient->response[ $plugin ] );
            }
            return $transient;
        }

        $update = (object) array(
            'id'           => 'github.com/' . self::OWNER . '/' . self::REPO,
            'slug'         => dirname( $plugin ),
            'plugin'       => $plugin,
            'new_version'  => $remote_version,
            'url'          => 'https://github.com/' . self::OWNER . '/' . self::REPO,
            'package'      => esc_url_raw( (string) $manifest['package'] ),
            'requires'     => isset( $manifest['requires'] ) ? (string) $manifest['requires'] : '6.4',
            'requires_php' => isset( $manifest['requires_php'] ) ? (string) $manifest['requires_php'] : '7.4',
            'tested'       => isset( $manifest['tested'] ) ? (string) $manifest['tested'] : '',
        );

        $transient->response[ $plugin ] = $update;
        return $transient;
    }

    public static function plugin_information( $result, string $action, $args ) {
        if ( 'plugin_information' !== $action || ! is_object( $args ) ) {
            return $result;
        }

        $plugin_slug = dirname( plugin_basename( PNW_FILE ) );
        if ( empty( $args->slug ) || $plugin_slug !== $args->slug ) {
            return $result;
        }

        $manifest = self::manifest();
        if ( ! is_array( $manifest ) ) {
            return $result;
        }

        return (object) array(
            'name'          => 'Petrik News Workflow',
            'slug'          => $plugin_slug,
            'version'       => isset( $manifest['version'] ) ? (string) $manifest['version'] : PNW_VERSION,
            'author'        => '<a href="https://petrik.hu/">Petrik</a>',
            'homepage'      => 'https://github.com/' . self::OWNER . '/' . self::REPO,
            'requires'      => isset( $manifest['requires'] ) ? (string) $manifest['requires'] : '6.4',
            'requires_php'  => isset( $manifest['requires_php'] ) ? (string) $manifest['requires_php'] : '7.4',
            'tested'        => isset( $manifest['tested'] ) ? (string) $manifest['tested'] : '',
            'download_link' => isset( $manifest['package'] ) ? esc_url_raw( (string) $manifest['package'] ) : '',
            'sections'      => array(
                'description' => 'Belső hírbeküldési és vezetői jóváhagyási workflow a Petrik WordPress oldalához.',
                'changelog'   => isset( $manifest['changelog'] ) ? wp_kses_post( (string) $manifest['changelog'] ) : '',
            ),
        );
    }

    public static function normalize_source_folder( $source, $remote_source, $upgrader, array $hook_extra ) {
        $plugin = plugin_basename( PNW_FILE );
        if ( empty( $hook_extra['plugin'] ) || $plugin !== $hook_extra['plugin'] ) {
            return $source;
        }

        global $wp_filesystem;
        if ( ! $wp_filesystem ) {
            return $source;
        }

        $expected_dir      = dirname( $plugin );
        $target            = trailingslashit( $remote_source ) . $expected_dir . '/';
        $normalized_source = trailingslashit( (string) $source );

        if ( untrailingslashit( $normalized_source ) === untrailingslashit( $target ) ) {
            return $source;
        }

        if ( $wp_filesystem->exists( $target ) ) {
            $wp_filesystem->delete( $target, true );
        }

        if ( ! $wp_filesystem->move( $normalized_source, $target, true ) ) {
            return new WP_Error(
                'pnw_update_folder_error',
                'A Petrik Hírkezelő GitHub-frissítésének könyvtárát nem sikerült előkészíteni.'
            );
        }

        return $target;
    }

    public static function clear_cache_after_upgrade( $upgrader, array $options ): void {
        if ( empty( $options['type'] ) || 'plugin' !== $options['type'] ) {
            return;
        }

        delete_site_transient( self::CACHE_KEY );
        delete_site_transient( 'pnw_github_update_manifest' );
        delete_site_transient( 'update_plugins' );
    }

    private static function manifest(): ?array {
        $cached = get_site_transient( self::CACHE_KEY );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $url = sprintf(
            'https://raw.githubusercontent.com/%s/%s/%s/update.json?pnw=%s',
            rawurlencode( self::OWNER ),
            rawurlencode( self::REPO ),
            rawurlencode( self::BRANCH ),
            rawurlencode( PNW_VERSION )
        );

        $response = wp_remote_get(
            $url,
            array(
                'timeout' => 8,
                'headers' => array(
                    'User-Agent' => 'Petrik-News-Workflow/' . PNW_VERSION,
                    'Accept'     => 'application/json',
                ),
            )
        );

        if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
            return null;
        }

        $data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) || empty( $data['version'] ) || empty( $data['package'] ) ) {
            return null;
        }

        set_site_transient( self::CACHE_KEY, $data, MINUTE_IN_SECONDS );
        return $data;
    }
}
