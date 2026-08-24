<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Published-news management for reviewers and webadmins.
 *
 * This deliberately includes every published WordPress post, not only posts
 * previously created by Petrik News Workflow, so legacy Petrik news can be
 * maintained from the same frontend manager after production launch.
 */
trait PNW_Frontend_Published_Trait {
    private static function enqueue_published_assets(): void {
        wp_enqueue_style(
            'pnw-published',
            PNW_URL . 'assets/css/pnw-published.css',
            array( 'pnw-app' ),
            PNW_VERSION
        );
    }

    private static function render_published_news(): void {
        if ( ! current_user_can( 'pnw_manage_published_news' ) ) {
            self::render_unauthorized();
            return;
        }

        self::enqueue_published_assets();

        $search = isset( $_GET['pnw_q'] ) ? sanitize_text_field( wp_unslash( $_GET['pnw_q'] ) ) : '';
        $paged  = isset( $_GET['pnw_page'] ) ? max( 1, absint( $_GET['pnw_page'] ) ) : 1;

        $args = array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 30,
            'paged'          => $paged,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        if ( '' !== $search ) {
            $args['s'] = $search;
        }

        $query     = new WP_Query( $args );
        $test_mode = defined( 'PNW_TEST_MODE' ) && PNW_TEST_MODE;

        echo '<section class="pnw-section">';
        echo '<div class="pnw-section-heading"><div><div class="pnw-kicker">Publikált tartalom</div><h3>Kint lévő hírek</h3><p>A lista a régi Petrik-bejegyzéseket és a Hírkezelőből később publikált híreket is tartalmazza.</p></div></div>';

        if ( $test_mode ) {
            echo '<div class="pnw-notice pnw-notice-warning"><strong>TESZT MÓD:</strong> a jelenlegi éles hírek megtekinthetők, de szerkesztésük és törlésük blokkolva van a production indulásig.</div>';
        }

        echo '<form class="pnw-form pnw-published-search-card" method="get" action="' . esc_url( PNW_Plugin::manager_url() ) . '">';
        echo '<input type="hidden" name="pnw_view" value="published">';
        echo '<div class="pnw-field" style="margin-bottom:0"><label for="pnw-published-search">Keresés a publikált hírek között</label><div class="pnw-published-search-row"><input id="pnw-published-search" type="search" name="pnw_q" value="' . esc_attr( $search ) . '" placeholder="Hír címe vagy szövege"><button class="pnw-button pnw-button-secondary" type="submit">Keresés</button></div></div>';
        echo '</form>';

        if ( ! $query->have_posts() ) {
            echo '<div class="pnw-empty-inline">Nincs megjeleníthető publikált hír.</div>';
            echo '</section>';
            wp_reset_postdata();
            return;
        }

        echo '<div class="pnw-table-card pnw-published-card"><div class="pnw-table-wrap"><table class="pnw-table pnw-published-table"><thead><tr><th>Hír</th><th>Szerző</th><th>Publikálva</th><th>Forrás</th></tr></thead><tbody>';
        foreach ( $query->posts as $post ) {
            $author  = get_userdata( (int) $post->post_author );
            $managed = '1' === (string) get_post_meta( $post->ID, '_pnw_managed', true );

            echo '<tr class="pnw-published-main-row">';
            echo '<td data-label="Hír"><strong class="pnw-published-title">' . esc_html( $post->post_title ?: '(Névtelen hír)' ) . '</strong><small class="pnw-published-meta">' . esc_html( self::category_names( (int) $post->ID ) ) . '</small></td>';
            echo '<td data-label="Szerző">' . esc_html( $author ? $author->display_name : '—' ) . '</td>';
            echo '<td data-label="Publikálva"><time datetime="' . esc_attr( get_the_date( DATE_W3C, $post ) ) . '">' . esc_html( get_the_date( 'Y.m.d. H:i', $post ) ) . '</time></td>';
            echo '<td data-label="Forrás"><span class="pnw-published-source">' . esc_html( $managed ? 'Hírkezelő' : 'Korábbi WordPress hír' ) . '</span></td>';
            echo '</tr>';

            echo '<tr class="pnw-published-action-row"><td colspan="4">';
            echo '<div class="pnw-published-inline-actions">';
            echo '<a class="pnw-button pnw-button-secondary" href="' . esc_url( get_permalink( $post ) ) . '" target="_blank" rel="noopener">Megnyitás</a>';
            echo '<a class="pnw-button" href="' . esc_url( PNW_Plugin::manager_url( array( 'pnw_view' => 'published_edit', 'post_id' => $post->ID ) ) ) . '">' . esc_html( $test_mode ? 'Részletek' : 'Szerkesztés' ) . '</a>';
            echo '</div>';
            echo '</td></tr>';
        }
        echo '</tbody></table></div></div>';

        if ( $query->max_num_pages > 1 ) {
            echo '<div class="pnw-published-pagination">';
            if ( $paged > 1 ) {
                $prev_args = array( 'pnw_view' => 'published', 'pnw_page' => $paged - 1 );
                if ( '' !== $search ) {
                    $prev_args['pnw_q'] = $search;
                }
                echo '<a class="pnw-button pnw-button-secondary" href="' . esc_url( PNW_Plugin::manager_url( $prev_args ) ) . '">← Előző</a>';
            }
            echo '<span>' . esc_html( (string) $paged ) . ' / ' . esc_html( (string) $query->max_num_pages ) . ' oldal</span>';
            if ( $paged < (int) $query->max_num_pages ) {
                $next_args = array( 'pnw_view' => 'published', 'pnw_page' => $paged + 1 );
                if ( '' !== $search ) {
                    $next_args['pnw_q'] = $search;
                }
                echo '<a class="pnw-button pnw-button-secondary" href="' . esc_url( PNW_Plugin::manager_url( $next_args ) ) . '">Következő →</a>';
            }
            echo '</div>';
        }

        echo '</section>';
        wp_reset_postdata();
    }

    private static function render_published_editor( int $post_id ): void {
        if ( ! current_user_can( 'pnw_manage_published_news' ) ) {
            self::render_unauthorized();
            return;
        }

        self::enqueue_published_assets();

        $post = get_post( $post_id );
        if ( ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
            echo '<div class="pnw-notice pnw-notice-error">Ez a publikált hír nem található.</div>';
            return;
        }

        $test_mode = defined( 'PNW_TEST_MODE' ) && PNW_TEST_MODE;
        $managed   = '1' === (string) get_post_meta( $post_id, '_pnw_managed', true );

        echo '<section class="pnw-section">';
        echo '<div class="pnw-section-heading"><div><div class="pnw-kicker">Publikált hír</div><h3>' . esc_html( $post->post_title ) . '</h3><p>' . esc_html( $managed ? 'Hírkezelőből publikált hír' : 'Korábbi WordPress hír' ) . '</p></div><a class="pnw-button pnw-button-secondary pnw-published-editor-back" href="' . esc_url( get_permalink( $post ) ) . '" target="_blank" rel="noopener">Megnyitás a weboldalon</a></div>';

        if ( $test_mode ) {
            echo '<div class="pnw-notice pnw-notice-warning"><strong>TESZT MÓD:</strong> ez valódi, jelenleg publikus tartalom. A production indulásig ezen a felületen csak megtekinthető, nem módosítható és nem törölhető.</div>';
            self::render_readonly_details( $post, false );
            echo '</section>';
            return;
        }

        $selected = wp_get_post_categories( $post_id );

        echo '<form class="pnw-form pnw-editor-form" method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="pnw_update_published_news"><input type="hidden" name="post_id" value="' . esc_attr( (string) $post_id ) . '">';
        wp_nonce_field( 'pnw_update_published_news', 'pnw_nonce' );
        self::render_common_fields( $post, array(), $selected, 'pnw_published_editor', true );
        echo '<div class="pnw-form-actions pnw-form-actions-spread"><button class="pnw-button pnw-button-preview pnw-preview-trigger" type="button">Előnézet</button><div class="pnw-form-actions-main"><a class="pnw-button pnw-button-secondary" href="' . esc_url( PNW_Plugin::manager_url( array( 'pnw_view' => 'published' ) ) ) . '">Mégse</a><button class="pnw-button" type="submit">Módosítások mentése</button></div></div>';
        echo '</form>';

        self::render_preview_modal();

        echo '<form class="pnw-delete-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-confirm="Biztosan a Lomtárba helyezed ezt a jelenleg publikus hírt? A weboldalról azonnal eltűnik, de a WordPress Lomtárból visszaállítható.">';
        echo '<input type="hidden" name="action" value="pnw_trash_published_news"><input type="hidden" name="post_id" value="' . esc_attr( (string) $post_id ) . '">';
        wp_nonce_field( 'pnw_trash_published_news', 'pnw_nonce' );
        echo '<button class="pnw-link-danger" type="submit">Hír Lomtárba helyezése</button></form>';
        echo '</section>';
    }
}
