<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait PNW_Frontend_Dashboard_Trait {
    private static function render_dashboard(): void {
        echo '<section class="pnw-section">';
        echo '<div class="pnw-section-heading"><div><div class="pnw-kicker">Állapot</div><h3>Áttekintés</h3></div></div>';

        if ( current_user_can( 'pnw_review_news' ) ) {
            self::render_reviewer_dashboard();
        } else {
            self::render_author_dashboard();
        }

        echo '</section>';
    }

    private static function render_author_dashboard(): void {
        $user_id = get_current_user_id();
        $counts  = array(
            'draft'                => self::count_posts( array( 'author' => $user_id, 'post_status' => 'draft' ) ),
            'pending'              => self::count_posts( array( 'author' => $user_id, 'post_status' => 'pending' ) ),
            PNW_Statuses::REVISION => self::count_posts( array( 'author' => $user_id, 'post_status' => PNW_Statuses::REVISION ) ),
            'publish'              => self::count_posts( array( 'author' => $user_id, 'post_status' => 'publish' ) ),
        );

        self::render_stat_cards( $counts );

        $posts = get_posts(
            array(
                'post_type'      => 'post',
                'meta_key'       => '_pnw_managed',
                'meta_value'     => '1',
                'author'         => $user_id,
                'post_status'    => array( 'draft', 'pending', PNW_Statuses::REVISION, 'publish', 'future', PNW_Statuses::TEST_APPROVED ),
                'posts_per_page' => 50,
                'orderby'        => 'modified',
                'order'          => 'DESC',
            )
        );

        echo '<div class="pnw-table-card"><div class="pnw-card-heading"><h4>Saját híreim</h4><a class="pnw-button" href="' . esc_url( PNW_Plugin::manager_url( array( 'pnw_view' => 'new' ) ) ) . '">+ Új hír</a></div>';
        self::render_posts_table( $posts, false );
        echo '</div>';
    }

    private static function render_reviewer_dashboard(): void {
        $pending = get_posts(
            array(
                'post_type'      => 'post',
                'meta_key'       => '_pnw_managed',
                'meta_value'     => '1',
                'post_status'    => 'pending',
                'posts_per_page' => 100,
                'orderby'        => 'modified',
                'order'          => 'ASC',
            )
        );

        $returned = self::count_posts( array( 'post_status' => PNW_Statuses::REVISION ) );
        $today     = self::published_today_count();

        echo '<div class="pnw-stats">';
        self::stat_card( count( $pending ), 'Jóváhagyásra vár', 'pending' );
        self::stat_card( $returned, 'Javításra visszaküldve', PNW_Statuses::REVISION );
        self::stat_card( $today, 'Ma publikálva', 'publish' );
        echo '</div>';

        if ( current_user_can( 'pnw_submit_news' ) ) {
            $own_working = get_posts(
                array(
                    'post_type'      => 'post',
                    'meta_key'       => '_pnw_managed',
                    'meta_value'     => '1',
                    'author'         => get_current_user_id(),
                    'post_status'    => array( 'draft', PNW_Statuses::REVISION ),
                    'posts_per_page' => 50,
                    'orderby'        => 'modified',
                    'order'          => 'DESC',
                )
            );

            echo '<div class="pnw-table-card"><div class="pnw-card-heading"><div><h4>Saját piszkozatok és javítások</h4><p>Az admin tesztfiókkal készített, még be nem küldött hírek.</p></div><a class="pnw-button" href="' . esc_url( PNW_Plugin::manager_url( array( 'pnw_view' => 'new' ) ) ) . '">+ Új hír</a></div>';
            self::render_posts_table( $own_working, false );
            echo '</div>';
        }

        echo '<div class="pnw-table-card"><div class="pnw-card-heading"><div><h4>Jóváhagyási sor</h4><p>Legrégebbi beküldés elöl.</p></div></div>';
        self::render_posts_table( $pending, true );
        echo '</div>';

        $recent = get_posts(
            array(
                'post_type'      => 'post',
                'meta_key'       => '_pnw_managed',
                'meta_value'     => '1',
                'post_status'    => array( 'publish', PNW_Statuses::REVISION, PNW_Statuses::TEST_APPROVED ),
                'posts_per_page' => 10,
                'orderby'        => 'modified',
                'order'          => 'DESC',
            )
        );
        echo '<div class="pnw-table-card"><div class="pnw-card-heading"><h4>Legutóbbi döntések</h4></div>';
        self::render_posts_table( $recent, true );
        echo '</div>';
    }

    private static function render_stat_cards( array $counts ): void {
        echo '<div class="pnw-stats">';
        foreach ( $counts as $status => $count ) {
            self::stat_card( (int) $count, PNW_Statuses::label( (string) $status ), (string) $status );
        }
        echo '</div>';
    }

    private static function stat_card( int $count, string $label, string $status ): void {
        echo '<div class="pnw-stat"><span class="pnw-status-dot pnw-status-' . esc_attr( PNW_Statuses::css_class( $status ) ) . '"></span><strong>' . esc_html( (string) $count ) . '</strong><span>' . esc_html( $label ) . '</span></div>';
    }

    private static function render_posts_table( array $posts, bool $reviewer ): void {
        if ( empty( $posts ) ) {
            echo '<div class="pnw-empty-inline">Nincs megjeleníthető hír.</div>';
            return;
        }

        echo '<div class="pnw-table-wrap"><table class="pnw-table"><thead><tr><th>Hír</th><th>Beküldő</th><th>Állapot</th><th>Módosítva</th><th></th></tr></thead><tbody>';
        foreach ( $posts as $post ) {
            $author = get_userdata( (int) $post->post_author );
            $status = $post->post_status;
            echo '<tr>';
            echo '<td><strong>' . esc_html( $post->post_title ?: '(Névtelen hír)' ) . '</strong><small>' . esc_html( self::category_names( (int) $post->ID ) ) . '</small></td>';
            echo '<td>' . esc_html( $author ? $author->display_name : '—' ) . '</td>';
            echo '<td><span class="pnw-badge pnw-badge-' . esc_attr( PNW_Statuses::css_class( $status ) ) . '">' . esc_html( PNW_Statuses::label( $status ) ) . '</span></td>';
            echo '<td>' . esc_html( get_the_modified_date( 'Y.m.d. H:i', $post ) ) . '</td>';
            echo '<td class="pnw-actions">';

            if ( $reviewer && 'pending' === $status ) {
                echo '<a class="pnw-button pnw-button-small" href="' . esc_url( PNW_Plugin::manager_url( array( 'pnw_view' => 'review', 'post_id' => $post->ID ) ) ) . '">Ellenőrzés</a>';
            } elseif ( ! $reviewer && PNW_Access::can_edit_workflow_post( (int) $post->ID ) ) {
                $label = PNW_Statuses::REVISION === $status ? 'Módosítás' : 'Szerkesztés';
                echo '<a class="pnw-button pnw-button-small" href="' . esc_url( PNW_Plugin::manager_url( array( 'pnw_view' => 'edit', 'post_id' => $post->ID ) ) ) . '">' . esc_html( $label ) . '</a>';
            } elseif ( PNW_Statuses::REVISION === $status && (int) $post->post_author === get_current_user_id() && current_user_can( 'pnw_submit_news' ) ) {
                echo '<a class="pnw-button pnw-button-small" href="' . esc_url( PNW_Plugin::manager_url( array( 'pnw_view' => 'edit', 'post_id' => $post->ID ) ) ) . '">Módosítás</a>';
            } elseif ( 'publish' === $status ) {
                echo '<a class="pnw-button pnw-button-secondary pnw-button-small" href="' . esc_url( get_permalink( $post ) ) . '" target="_blank" rel="noopener">Megnyitás</a>';
            } elseif ( PNW_Access::can_view_workflow_post( (int) $post->ID ) ) {
                echo '<a class="pnw-button pnw-button-secondary pnw-button-small" href="' . esc_url( PNW_Plugin::manager_url( array( 'pnw_view' => 'review', 'post_id' => $post->ID ) ) ) . '">Részletek</a>';
            }

            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private static function count_posts( array $args ): int {
        $query = new WP_Query(
            array_merge(
                array(
                    'post_type'              => 'post',
                    'meta_key'               => '_pnw_managed',
                    'meta_value'             => '1',
                    'posts_per_page'         => 1,
                    'fields'                 => 'ids',
                    'no_found_rows'          => false,
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false,
                ),
                $args
            )
        );
        return (int) $query->found_posts;
    }

    private static function published_today_count(): int {
        $query = new WP_Query(
            array(
                'post_type'      => 'post',
                'meta_key'       => '_pnw_managed',
                'meta_value'     => '1',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'date_query'     => array(
                    array(
                        'year'  => (int) current_time( 'Y' ),
                        'month' => (int) current_time( 'm' ),
                        'day'   => (int) current_time( 'd' ),
                    ),
                ),
            )
        );
        return (int) $query->found_posts;
    }

    private static function category_names( int $post_id ): string {
        $terms = get_the_category( $post_id );
        if ( empty( $terms ) ) {
            return 'Nincs kategória';
        }
        return implode( ', ', wp_list_pluck( $terms, 'name' ) );
    }
}
