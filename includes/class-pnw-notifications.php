<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class PNW_Notifications {
    public static function reviewers(): array {
        $users = get_users(
            array(
                'role__in' => array( PNW_Roles::DEPUTY, PNW_Roles::DIRECTOR ),
            )
        );

        if ( empty( $users ) ) {
            $users = get_users( array( 'role' => 'administrator' ) );
        }

        return array_values(
            array_filter(
                $users,
                static fn( WP_User $user ): bool => user_can( $user, 'pnw_review_news' ) && is_email( $user->user_email )
            )
        );
    }

    public static function submitted( int $post_id ): void {
        $post   = get_post( $post_id );
        $author = $post ? get_userdata( (int) $post->post_author ) : false;
        if ( ! $post ) {
            return;
        }

        $subject = sprintf( '[Petrik Hírkezelő] Jóváhagyásra vár: %s', wp_strip_all_tags( $post->post_title ) );
        $body    = "Új hír érkezett jóváhagyásra.\n\n";
        $body   .= 'Cím: ' . wp_strip_all_tags( $post->post_title ) . "\n";
        $body   .= 'Beküldő: ' . ( $author ? $author->display_name : 'Ismeretlen' ) . "\n";
        $body   .= 'Megnyitás: ' . PNW_Plugin::manager_url( array( 'pnw_view' => 'review', 'post_id' => $post_id ) ) . "\n";

        foreach ( self::reviewers() as $reviewer ) {
            wp_mail( $reviewer->user_email, $subject, $body );
        }
    }

    public static function rejected( int $post_id, string $note ): void {
        $post   = get_post( $post_id );
        $author = $post ? get_userdata( (int) $post->post_author ) : false;
        if ( ! $post || ! $author || ! is_email( $author->user_email ) ) {
            return;
        }

        $subject = sprintf( '[Petrik Hírkezelő] Javításra visszaküldve: %s', wp_strip_all_tags( $post->post_title ) );
        $body    = "A hírt a vezetőség javításra visszaküldte.\n\n";
        $body   .= 'Cím: ' . wp_strip_all_tags( $post->post_title ) . "\n";
        $body   .= 'Megjegyzés: ' . $note . "\n\n";
        $body   .= 'Szerkesztés: ' . PNW_Plugin::manager_url( array( 'pnw_view' => 'edit', 'post_id' => $post_id ) ) . "\n";

        wp_mail( $author->user_email, $subject, $body );
    }

    public static function approved( int $post_id ): void {
        $post   = get_post( $post_id );
        $author = $post ? get_userdata( (int) $post->post_author ) : false;
        if ( ! $post || ! $author || ! is_email( $author->user_email ) ) {
            return;
        }

        if ( defined( 'PNW_TEST_MODE' ) && PNW_TEST_MODE ) {
            $subject = sprintf( '[Petrik Hírkezelő][TESZT] Jóváhagyva: %s', wp_strip_all_tags( $post->post_title ) );
            $body    = "A hírt a vezetőség TESZT módban jóváhagyta.\n\n";
            $body   .= "A hír NEM lett publikálva és nem jelenik meg a Petrik nyilvános oldalán.\n\n";
            $body   .= 'Cím: ' . wp_strip_all_tags( $post->post_title ) . "\n";
            $body   .= 'Hírkezelő: ' . PNW_Plugin::manager_url() . "\n";
            wp_mail( $author->user_email, $subject, $body );
            return;
        }

        $subject = sprintf( '[Petrik Hírkezelő] Publikálva: %s', wp_strip_all_tags( $post->post_title ) );
        $body    = "A hírt a vezetőség jóváhagyta és publikálta.\n\n";
        $body   .= 'Cím: ' . wp_strip_all_tags( $post->post_title ) . "\n";
        $body   .= 'Hír: ' . get_permalink( $post_id ) . "\n";

        wp_mail( $author->user_email, $subject, $body );
    }
}
