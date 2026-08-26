<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PNW_Statuses {
    public const REVISION      = 'pnw_revision';
    public const TEST_APPROVED = 'pnw_test_ok';
    public const ARCHIVED      = 'pnw_archived';

    public static function init(): void {
        add_action( 'init', array( __CLASS__, 'register' ) );
    }

    public static function register(): void {
        register_post_status(
            self::REVISION,
            array(
                'label'                     => 'Javításra visszaküldve',
                'public'                    => false,
                'internal'                  => false,
                'protected'                 => true,
                'exclude_from_search'       => true,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop(
                    'Javításra visszaküldve <span class="count">(%s)</span>',
                    'Javításra visszaküldve <span class="count">(%s)</span>',
                    'petrik-news-workflow'
                ),
            )
        );

        register_post_status(
            self::TEST_APPROVED,
            array(
                'label'                     => 'Tesztben jóváhagyva',
                'public'                    => false,
                'internal'                  => false,
                'protected'                 => true,
                'exclude_from_search'       => true,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop(
                    'Tesztben jóváhagyva <span class="count">(%s)</span>',
                    'Tesztben jóváhagyva <span class="count">(%s)</span>',
                    'petrik-news-workflow'
                ),
            )
        );

        register_post_status(
            self::ARCHIVED,
            array(
                'label'                     => 'Archiválva',
                'public'                    => false,
                'internal'                  => false,
                'protected'                 => true,
                'exclude_from_search'       => true,
                'show_in_admin_all_list'    => true,
                'show_in_admin_status_list' => true,
                'label_count'               => _n_noop(
                    'Archiválva <span class="count">(%s)</span>',
                    'Archiválva <span class="count">(%s)</span>',
                    'petrik-news-workflow'
                ),
            )
        );
    }

    public static function label( string $status ): string {
        $labels = array(
            'draft'             => 'Piszkozat',
            'pending'           => 'Jóváhagyásra vár',
            self::REVISION      => 'Javításra visszaküldve',
            self::TEST_APPROVED => 'Tesztben jóváhagyva – nem publikus',
            self::ARCHIVED      => 'Archiválva – nem publikus',
            'publish'           => 'Publikálva',
            'future'            => 'Időzítve',
            'private'           => 'Privát',
            'trash'             => 'Lomtár',
        );

        return $labels[ $status ] ?? ucfirst( $status );
    }

    public static function css_class( string $status ): string {
        if ( self::TEST_APPROVED === $status ) {
            return 'pending';
        }

        if ( self::ARCHIVED === $status ) {
            return 'draft';
        }

        $allowed = array( 'draft', 'pending', self::REVISION, 'publish', 'future' );
        return in_array( $status, $allowed, true ) ? $status : 'other';
    }
}
