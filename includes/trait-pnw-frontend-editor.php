<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait PNW_Frontend_Editor_Trait {
    private static function render_editor( int $post_id = 0 ): void {
        if ( ! current_user_can( 'pnw_submit_news' ) ) {
            self::render_unauthorized();
            return;
        }

        $post = $post_id ? get_post( $post_id ) : null;
        if ( $post_id && ( ! $post || ! PNW_Access::can_edit_workflow_post( $post_id ) ) ) {
            echo '<div class="pnw-notice pnw-notice-error">Ezt a hírt jelenleg nem szerkesztheted.</div>';
            return;
        }

        $selected = $post ? wp_get_post_categories( $post_id ) : array();
        $note     = $post ? (string) get_post_meta( $post_id, '_pnw_review_note', true ) : '';

        echo '<section class="pnw-section">';
        echo '<div class="pnw-section-heading"><div><div class="pnw-kicker">' . ( $post ? 'Szerkesztés' : 'Új tartalom' ) . '</div><h3>' . ( $post ? esc_html( $post->post_title ) : 'Új hír beküldése' ) . '</h3></div></div>';

        if ( $note ) {
            echo '<div class="pnw-review-note"><strong>Vezetői megjegyzés</strong><p>' . nl2br( esc_html( $note ) ) . '</p></div>';
        }

        echo '<form class="pnw-form" method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="pnw_save_news">';
        echo '<input type="hidden" name="post_id" value="' . esc_attr( (string) $post_id ) . '">';
        wp_nonce_field( 'pnw_save_news', 'pnw_nonce' );

        self::render_common_fields( $post, PNW_Access::allowed_category_ids(), $selected, 'pnw_author_editor' );

        echo '<div class="pnw-form-actions">';
        echo '<button class="pnw-button pnw-button-secondary" type="submit" name="pnw_command" value="draft">Piszkozat mentése</button>';
        echo '<button class="pnw-button" type="submit" name="pnw_command" value="submit">Beküldés jóváhagyásra</button>';
        echo '</div>';
        echo '</form>';

        if ( $post ) {
            echo '<form class="pnw-delete-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-confirm="Biztosan a lomtárba helyezed ezt a piszkozatot?">';
            echo '<input type="hidden" name="action" value="pnw_delete_news"><input type="hidden" name="post_id" value="' . esc_attr( (string) $post_id ) . '">';
            wp_nonce_field( 'pnw_delete_news', 'pnw_nonce' );
            echo '<button class="pnw-link-danger" type="submit">Piszkozat törlése</button></form>';
        }
        echo '</section>';
    }

    private static function render_review( int $post_id ): void {
        $post = get_post( $post_id );
        if ( ! $post || ! PNW_Access::can_view_workflow_post( $post_id ) ) {
            echo '<div class="pnw-notice pnw-notice-error">A hír nem érhető el.</div>';
            return;
        }

        if ( ! current_user_can( 'pnw_review_news' ) ) {
            self::render_readonly_details( $post );
            return;
        }

        $author   = get_userdata( (int) $post->post_author );
        $selected = wp_get_post_categories( $post_id );

        echo '<section class="pnw-section">';
        echo '<div class="pnw-section-heading"><div><div class="pnw-kicker">Vezetői ellenőrzés</div><h3>' . esc_html( $post->post_title ) . '</h3><p>Beküldő: ' . esc_html( $author ? $author->display_name : '—' ) . '</p></div>';
        if ( 'pending' === $post->post_status ) {
            $preview = get_preview_post_link( $post );
            if ( $preview ) {
                echo '<a class="pnw-button pnw-button-secondary" target="_blank" rel="noopener" href="' . esc_url( $preview ) . '">Előnézet</a>';
            }
        }
        echo '</div>';

        if ( 'pending' !== $post->post_status ) {
            echo '<div class="pnw-notice pnw-notice-warning">Ez a hír már nem vár jóváhagyásra. Aktuális állapot: ' . esc_html( PNW_Statuses::label( $post->post_status ) ) . '.</div>';
            self::render_readonly_details( $post, false );
            self::render_post_audit( $post_id );
            echo '</section>';
            return;
        }

        echo '<form class="pnw-form" method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="pnw_reviewer_save"><input type="hidden" name="post_id" value="' . esc_attr( (string) $post_id ) . '">';
        wp_nonce_field( 'pnw_reviewer_save', 'pnw_nonce' );
        self::render_common_fields( $post, array(), $selected, 'pnw_reviewer_editor', true );
        echo '<div class="pnw-form-actions"><button class="pnw-button pnw-button-secondary" type="submit">Vezetői módosítások mentése</button></div>';
        echo '</form>';

        $test_mode = defined( 'PNW_TEST_MODE' ) && PNW_TEST_MODE;
        $approve_description = $test_mode
            ? 'TESZT módban a jóváhagyás rögzül, de a hír nem jelenik meg a nyilvános weboldalon.'
            : 'A hír azonnal megjelenik a weboldalon.';
        $approve_label = $test_mode ? '✓ Teszt jóváhagyás – NEM publikál' : '✓ Jóváhagyás és publikálás';

        echo '<div class="pnw-decision-grid">';
        echo '<form class="pnw-decision pnw-decision-approve" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="pnw_review_news"><input type="hidden" name="post_id" value="' . esc_attr( (string) $post_id ) . '"><input type="hidden" name="decision" value="approve">';
        wp_nonce_field( 'pnw_review_news', 'pnw_nonce' );
        echo '<h4>Jóváhagyás</h4><p>' . esc_html( $approve_description ) . '</p><textarea name="review_note" rows="3" placeholder="Opcionális belső megjegyzés"></textarea><button class="pnw-button pnw-button-success" type="submit">' . esc_html( $approve_label ) . '</button></form>';

        echo '<form class="pnw-decision pnw-decision-reject" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="pnw_review_news"><input type="hidden" name="post_id" value="' . esc_attr( (string) $post_id ) . '"><input type="hidden" name="decision" value="reject">';
        wp_nonce_field( 'pnw_review_news', 'pnw_nonce' );
        echo '<h4>Visszaküldés javításra</h4><p>A megjegyzést az MK-vezető e-mailben és itt a felületen is látja.</p><textarea name="review_note" rows="4" required placeholder="Mit kell javítani?"></textarea><button class="pnw-button pnw-button-danger" type="submit">↩ Visszaküldés</button></form>';
        echo '</div>';

        self::render_post_audit( $post_id );
        echo '</section>';
    }

    private static function render_readonly_details( WP_Post $post, bool $with_section = true ): void {
        if ( $with_section ) {
            echo '<section class="pnw-section">';
            echo '<div class="pnw-section-heading"><div><div class="pnw-kicker">Részletek</div><h3>' . esc_html( $post->post_title ) . '</h3></div></div>';
        }

        $note = (string) get_post_meta( $post->ID, '_pnw_review_note', true );
        if ( $note ) {
            echo '<div class="pnw-review-note"><strong>Vezetői megjegyzés</strong><p>' . nl2br( esc_html( $note ) ) . '</p></div>';
        }

        echo '<div class="pnw-readonly-meta"><span class="pnw-badge pnw-badge-' . esc_attr( PNW_Statuses::css_class( $post->post_status ) ) . '">' . esc_html( PNW_Statuses::label( $post->post_status ) ) . '</span><span>' . esc_html( self::category_names( (int) $post->ID ) ) . '</span></div>';
        if ( has_post_thumbnail( $post ) ) {
            echo '<div class="pnw-featured-preview">' . get_the_post_thumbnail( $post, 'large' ) . '</div>';
        }
        echo '<article class="pnw-content-preview">' . wp_kses_post( apply_filters( 'the_content', $post->post_content ) ) . '</article>';

        if ( $with_section ) {
            echo '</section>';
        }
    }

    private static function render_common_fields( ?WP_Post $post, array $allowed_category_ids, array $selected, string $editor_id, bool $all_categories = false ): void {
        $title   = $post ? $post->post_title : '';
        $content = $post ? $post->post_content : '';
        $excerpt = $post ? $post->post_excerpt : '';

        echo '<div class="pnw-field"><label for="pnw-title">Cím <span>*</span></label><input id="pnw-title" type="text" name="post_title" required maxlength="200" value="' . esc_attr( $title ) . '" placeholder="A hír címe"></div>';
        echo '<div class="pnw-field"><label>Hír szövege <span>*</span></label>';
        wp_editor(
            $content,
            $editor_id,
            array(
                'textarea_name' => 'post_content',
                'textarea_rows' => 14,
                'media_buttons' => true,
                'teeny'         => false,
                'quicktags'     => true,
            )
        );
        echo '</div>';

        echo '<div class="pnw-field"><label for="pnw-excerpt">Rövid kivonat</label><textarea id="pnw-excerpt" name="post_excerpt" rows="3" maxlength="500" placeholder="Opcionális rövid összefoglaló">' . esc_textarea( $excerpt ) . '</textarea><small>Ha a sablon használ kivonatot, ez jelenhet meg a hírlistában.</small></div>';

        $categories = get_categories( array( 'hide_empty' => false ) );
        echo '<fieldset class="pnw-field pnw-category-field"><legend>Kategória <span>*</span></legend><div class="pnw-category-grid">';
        foreach ( $categories as $category ) {
            $id = (int) $category->term_id;
            if ( ! $all_categories && ! in_array( $id, $allowed_category_ids, true ) ) {
                continue;
            }
            echo '<label><input type="checkbox" name="post_category[]" value="' . esc_attr( (string) $id ) . '" ' . checked( in_array( $id, $selected, true ), true, false ) . '> <span>' . esc_html( $category->name ) . '</span></label>';
        }
        echo '</div></fieldset>';

        echo '<div class="pnw-field"><label for="pnw-featured">Kiemelt kép</label>';
        if ( $post && has_post_thumbnail( $post ) ) {
            echo '<div class="pnw-current-image">' . get_the_post_thumbnail( $post, 'medium' ) . '<span>Jelenlegi kiemelt kép</span></div>';
        }
        echo '<input id="pnw-featured" type="file" name="featured_image" accept="image/*"><small>Új kép feltöltésével a jelenlegi kiemelt kép lecserélődik. A WordPress feltöltési méretkorlátja érvényes.</small></div>';
    }
}
