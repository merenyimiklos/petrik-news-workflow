<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait PNW_Frontend_Audit_Trait {
    private static function render_audit(): void {
        if ( ! current_user_can( 'pnw_view_audit_log' ) ) {
            self::render_unauthorized();
            return;
        }

        echo '<section class="pnw-section"><div class="pnw-section-heading"><div><div class="pnw-kicker">Audit</div><h3>Hírkezelési napló</h3><p>A legutóbbi 100 workflow-esemény.</p></div></div>';
        self::render_audit_rows( PNW_Audit::recent( 100 ) );
        echo '</section>';
    }

    private static function render_post_audit( int $post_id ): void {
        if ( ! current_user_can( 'pnw_view_audit_log' ) ) {
            return;
        }
        echo '<div class="pnw-audit-block"><h4>Előzmények</h4>';
        self::render_audit_rows( PNW_Audit::recent( 30, $post_id ), true );
        echo '</div>';
    }

    private static function render_audit_rows( array $rows, bool $compact = false ): void {
        if ( empty( $rows ) ) {
            echo '<div class="pnw-empty-inline">Még nincs naplóbejegyzés.</div>';
            return;
        }

        echo '<div class="pnw-timeline' . ( $compact ? ' pnw-timeline-compact' : '' ) . '">';
        foreach ( $rows as $row ) {
            $user = get_userdata( (int) $row->user_id );
            $post = get_post( (int) $row->post_id );
            echo '<div class="pnw-timeline-item"><span class="pnw-timeline-dot"></span><div><strong>' . esc_html( self::audit_action_label( (string) $row->action ) ) . '</strong>';
            if ( ! $compact ) {
                echo '<span class="pnw-timeline-post">' . esc_html( $post ? $post->post_title : '#' . $row->post_id ) . '</span>';
            }
            echo '<small>' . esc_html( $user ? $user->display_name : 'Rendszer' ) . ' · ' . esc_html( mysql2date( 'Y.m.d. H:i', $row->created_at ) ) . '</small>';
            if ( $row->note ) {
                echo '<p>' . nl2br( esc_html( $row->note ) ) . '</p>';
            }
            echo '</div></div>';
        }
        echo '</div>';
    }

    private static function audit_action_label( string $action ): string {
        $labels = array(
            'created_draft'   => 'Piszkozat létrehozva',
            'updated_draft'   => 'Piszkozat módosítva',
            'submitted'       => 'Jóváhagyásra beküldve',
            'reviewer_edited' => 'Vezető által szerkesztve',
            'rejected'        => 'Javításra visszaküldve',
            'approved'        => 'Jóváhagyva és publikálva',
            'trashed'         => 'Lomtárba helyezve',
        );
        return $labels[ $action ] ?? $action;
    }
}
