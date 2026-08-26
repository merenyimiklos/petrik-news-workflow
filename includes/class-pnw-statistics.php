<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PNW_Statistics {
    public static function render(): void {
        if ( ! current_user_can( 'pnw_view_audit_log' ) ) {
            echo '<div class="pnw-notice pnw-notice-error">Nincs jogosultságod a statisztikák megtekintéséhez.</div>';
            return;
        }

        wp_enqueue_style(
            'pnw-statistics',
            PNW_URL . 'assets/css/pnw-statistics.css',
            array( 'pnw-app' ),
            PNW_VERSION
        );

        $live_all       = self::count_posts_by_status( 'publish', false );
        $live_managed   = self::count_posts_by_status( 'publish', true );
        $scheduled      = self::count_posts_by_status( 'future', true );
        $pending        = self::count_posts_by_status( 'pending', true );
        $archived       = self::count_posts_by_status( PNW_Statuses::ARCHIVED, false );
        $month_published = self::published_this_month();
        $average_seconds = self::average_approval_seconds();

        echo '<section class="pnw-section pnw-statistics">';
        echo '<div class="pnw-section-heading"><div><div class="pnw-kicker">Rendszerkép</div><h3>Statisztika</h3><p>Áttekintés a Hírkezelő használatáról és a publikált tartalmakról.</p></div></div>';

        echo '<div class="pnw-stats pnw-statistics-cards">';
        self::metric_card( $live_all, 'Kint lévő hírek', 'Az összes jelenleg publikus WordPress hír' );
        self::metric_card( $live_managed, 'Hírkezelőből publikált', 'A workflow-val létrehozott és jelenleg élő hírek' );
        self::metric_card( $scheduled, 'Időzített', 'Jóváhagyva, későbbi megjelenésre vár' );
        self::metric_card( $pending, 'Jóváhagyásra vár', 'Vezetői döntésre váró hírek' );
        self::metric_card( $archived, 'Archívumban', 'Nem publikus, visszaállítható hírek' );
        self::metric_card( $month_published, 'Ebben a hónapban', 'Hírkezelőből publikált hírek' );
        self::metric_card( self::format_duration( $average_seconds ), 'Átlagos jóváhagyási idő', 'Beküldéstől a jóváhagyásig / időzítésig' );
        echo '</div>';

        echo '<div class="pnw-statistics-grid">';
        self::render_monthly_chart();
        self::render_top_authors();
        self::render_top_categories();
        echo '</div>';

        echo '</section>';
    }

    private static function metric_card( $value, string $label, string $description ): void {
        echo '<div class="pnw-stat pnw-statistics-card"><strong>' . esc_html( (string) $value ) . '</strong><span>' . esc_html( $label ) . '</span><small>' . esc_html( $description ) . '</small></div>';
    }

    private static function count_posts_by_status( string $status, bool $managed_only ): int {
        $args = array(
            'post_type'              => 'post',
            'post_status'            => $status,
            'posts_per_page'         => 1,
            'fields'                 => 'ids',
            'no_found_rows'          => false,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        );

        if ( $managed_only ) {
            $args['meta_key']   = '_pnw_managed';
            $args['meta_value'] = '1';
        }

        $query = new WP_Query( $args );
        return (int) $query->found_posts;
    }

    private static function published_this_month(): int {
        $query = new WP_Query(
            array(
                'post_type'              => 'post',
                'post_status'            => array( 'publish', PNW_Statuses::ARCHIVED ),
                'meta_key'               => '_pnw_managed',
                'meta_value'             => '1',
                'posts_per_page'         => 1,
                'fields'                 => 'ids',
                'no_found_rows'          => false,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'date_query'             => array(
                    array(
                        'year'  => (int) current_time( 'Y' ),
                        'month' => (int) current_time( 'm' ),
                    ),
                ),
            )
        );

        return (int) $query->found_posts;
    }

    private static function average_approval_seconds(): int {
        global $wpdb;

        $table = PNW_Audit::table_name();
        $sql   = "SELECT AVG(TIMESTAMPDIFF(SECOND, x.submitted_at, x.decided_at))
            FROM (
                SELECT post_id,
                    MAX(CASE WHEN action = 'submitted' THEN created_at END) AS submitted_at,
                    MAX(CASE WHEN action IN ('approved','scheduled') THEN created_at END) AS decided_at
                FROM {$table}
                GROUP BY post_id
            ) x
            WHERE x.submitted_at IS NOT NULL
              AND x.decided_at IS NOT NULL
              AND x.decided_at >= x.submitted_at";

        $value = $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return max( 0, (int) round( (float) $value ) );
    }

    private static function format_duration( int $seconds ): string {
        if ( $seconds <= 0 ) {
            return '—';
        }
        if ( $seconds < HOUR_IN_SECONDS ) {
            return max( 1, (int) round( $seconds / MINUTE_IN_SECONDS ) ) . ' perc';
        }
        if ( $seconds < DAY_IN_SECONDS ) {
            return round( $seconds / HOUR_IN_SECONDS, 1 ) . ' óra';
        }
        return round( $seconds / DAY_IN_SECONDS, 1 ) . ' nap';
    }

    private static function render_monthly_chart(): void {
        global $wpdb;

        $months = array();
        $labels = array();
        $tz     = wp_timezone();
        $cursor = new DateTimeImmutable( 'first day of this month 00:00:00', $tz );

        for ( $i = 5; $i >= 0; $i-- ) {
            $month = $cursor->modify( '-' . $i . ' months' );
            $key   = $month->format( 'Y-m' );
            $months[ $key ] = 0;
            $labels[ $key ] = wp_date( 'Y. M', $month->getTimestamp(), $tz );
        }

        $start = $cursor->modify( '-5 months' )->format( 'Y-m-01 00:00:00' );
        $posts = $wpdb->posts;
        $meta  = $wpdb->postmeta;
        $sql   = $wpdb->prepare(
            "SELECT DATE_FORMAT(p.post_date, '%%Y-%%m') AS ym, COUNT(DISTINCT p.ID) AS total
             FROM {$posts} p
             INNER JOIN {$meta} pm ON pm.post_id = p.ID AND pm.meta_key = '_pnw_managed' AND pm.meta_value = '1'
             WHERE p.post_type = 'post'
               AND p.post_status IN ('publish', %s)
               AND p.post_date >= %s
             GROUP BY ym
             ORDER BY ym ASC",
            PNW_Statuses::ARCHIVED,
            $start
        );

        foreach ( (array) $wpdb->get_results( $sql ) as $row ) {
            if ( isset( $months[ $row->ym ] ) ) {
                $months[ $row->ym ] = (int) $row->total;
            }
        }

        $max = max( 1, max( $months ) );

        echo '<div class="pnw-stat-panel pnw-stat-panel-wide"><div class="pnw-card-heading"><div><h4>Publikálások az elmúlt 6 hónapban</h4><p>A Hírkezelőből publikált hírek havi alakulása.</p></div></div><div class="pnw-mini-chart">';
        foreach ( $months as $key => $count ) {
            $height = max( 5, (int) round( ( $count / $max ) * 100 ) );
            echo '<div class="pnw-mini-chart-item"><strong>' . esc_html( (string) $count ) . '</strong><div class="pnw-mini-chart-track"><span style="height:' . esc_attr( (string) $height ) . '%"></span></div><small>' . esc_html( $labels[ $key ] ) . '</small></div>';
        }
        echo '</div></div>';
    }

    private static function render_top_authors(): void {
        global $wpdb;

        $posts = $wpdb->posts;
        $meta  = $wpdb->postmeta;
        $sql   = $wpdb->prepare(
            "SELECT p.post_author, COUNT(DISTINCT p.ID) AS total
             FROM {$posts} p
             INNER JOIN {$meta} pm ON pm.post_id = p.ID AND pm.meta_key = '_pnw_managed' AND pm.meta_value = '1'
             WHERE p.post_type = 'post'
               AND p.post_status IN ('publish','future',%s)
             GROUP BY p.post_author
             ORDER BY total DESC
             LIMIT 8",
            PNW_Statuses::ARCHIVED
        );
        $rows = (array) $wpdb->get_results( $sql );

        echo '<div class="pnw-stat-panel"><div class="pnw-card-heading"><div><h4>Legaktívabb beküldők</h4><p>Publikált, időzített vagy archivált workflow-hírek alapján.</p></div></div>';
        if ( empty( $rows ) ) {
            echo '<div class="pnw-empty-inline">Még nincs elegendő adat.</div></div>';
            return;
        }
        echo '<div class="pnw-stat-list">';
        foreach ( $rows as $row ) {
            $user = get_userdata( (int) $row->post_author );
            echo '<div><span>' . esc_html( $user ? $user->display_name : 'Ismeretlen felhasználó' ) . '</span><strong>' . esc_html( (string) (int) $row->total ) . '</strong></div>';
        }
        echo '</div></div>';
    }

    private static function render_top_categories(): void {
        global $wpdb;

        $posts = $wpdb->posts;
        $meta  = $wpdb->postmeta;
        $tr    = $wpdb->term_relationships;
        $tt    = $wpdb->term_taxonomy;
        $terms = $wpdb->terms;
        $sql   = $wpdb->prepare(
            "SELECT t.name, COUNT(DISTINCT p.ID) AS total
             FROM {$posts} p
             INNER JOIN {$meta} pm ON pm.post_id = p.ID AND pm.meta_key = '_pnw_managed' AND pm.meta_value = '1'
             INNER JOIN {$tr} rel ON rel.object_id = p.ID
             INNER JOIN {$tt} tax ON tax.term_taxonomy_id = rel.term_taxonomy_id AND tax.taxonomy = 'category'
             INNER JOIN {$terms} t ON t.term_id = tax.term_id
             WHERE p.post_type = 'post'
               AND p.post_status IN ('publish','future',%s)
             GROUP BY t.term_id, t.name
             ORDER BY total DESC, t.name ASC
             LIMIT 8",
            PNW_Statuses::ARCHIVED
        );
        $rows = (array) $wpdb->get_results( $sql );

        echo '<div class="pnw-stat-panel"><div class="pnw-card-heading"><div><h4>Leggyakoribb kategóriák</h4><p>Milyen témákban készül a legtöbb workflow-hír.</p></div></div>';
        if ( empty( $rows ) ) {
            echo '<div class="pnw-empty-inline">Még nincs elegendő adat.</div></div>';
            return;
        }
        echo '<div class="pnw-stat-list">';
        foreach ( $rows as $row ) {
            echo '<div><span>' . esc_html( $row->name ) . '</span><strong>' . esc_html( (string) (int) $row->total ) . '</strong></div>';
        }
        echo '</div></div>';
    }
}
