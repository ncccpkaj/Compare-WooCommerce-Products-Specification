<?php
defined( 'ABSPATH' ) || exit;

/**
 * WPC_Settings
 * Adds a "Compare" field under Settings → Permalinks.
 *
 * WordPress's Permalinks page uses its own save routine (update-permalink nonce)
 * and does NOT go through the standard options.php Settings API flow.
 * We handle save manually and keep the WP page post_name in sync.
 */
class WPC_Settings {

    const SLUG_OPTION = 'wpc_compare_slug';

    public static function init() {
        add_action( 'admin_init', [ __CLASS__, 'maybe_save' ] );
        add_action( 'admin_init', [ __CLASS__, 'add_fields' ] );
    }

    // ── Manual save ───────────────────────────────────────────────────────────

    public static function maybe_save() {
        if (
            ! is_admin() ||
            ! isset( $_POST[ self::SLUG_OPTION ] ) ||
            ! isset( $_POST['_wpnonce'] ) ||
            ! wp_verify_nonce( $_POST['_wpnonce'], 'update-permalink' ) ||
            ! current_user_can( 'manage_options' )
        ) {
            return;
        }

        $raw   = sanitize_text_field( trim( $_POST[ self::SLUG_OPTION ] ) );
        $lower = strtolower( $raw );

        if ( $lower === 'off' || $raw === '' ) {
            update_option( self::SLUG_OPTION, 'off' );
            return;
        }

        $slug = sanitize_title( $raw );
        if ( ! $slug ) {
            update_option( self::SLUG_OPTION, 'off' );
            return;
        }

        update_option( self::SLUG_OPTION, $slug );

        // Keep the WP compare page post_name in sync with the new slug
        $page_id = (int) get_option( 'wpc_compare_page_id' );
        if ( $page_id && get_post_status( $page_id ) ) {
            wp_update_post( [
                'ID'        => $page_id,
                'post_name' => $slug,
            ] );
            // Flush rewrite rules so the new slug works immediately
            flush_rewrite_rules( false );
        }
    }

    // ── Register section + field on Permalinks page ───────────────────────────

    public static function add_fields() {
        add_settings_section(
            'wpc_permalink_section',
            __( 'Product Compare', 'woo-product-compare' ),
            '__return_empty_string',
            'permalink'
        );

        add_settings_field(
            'wpc_compare_slug_field',
            __( 'Compare Page Slug', 'woo-product-compare' ),
            [ __CLASS__, 'field_html' ],
            'permalink',
            'wpc_permalink_section'
        );
    }

    public static function field_html() {
        $slug = self::get_slug();
        ?>
        <input type="text"
               name="<?php echo esc_attr( self::SLUG_OPTION ); ?>"
               id="wpc_compare_slug"
               value="<?php echo esc_attr( $slug ); ?>"
               class="regular-text"
               placeholder="off">
        <p class="description">
            <?php esc_html_e( 'Slug for the compare page, or "off" to disable the compare button on product pages.', 'woo-product-compare' ); ?>
        </p>
        <?php
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public static function get_slug(): string {
        return get_option( self::SLUG_OPTION, 'off' );
    }

    public static function is_enabled(): bool {
        $slug = self::get_slug();
        return $slug !== 'off' && $slug !== '';
    }

    /**
     * Always build URL from the saved slug option — never from get_permalink()
     * which would return the stale WP page slug before rewrite rules flush.
     */
    public static function get_compare_url( array $ids = [] ): string {
        if ( ! self::is_enabled() ) return '';

        // Always construct from the saved slug so it reflects the setting immediately
        $url = home_url( '/' . self::get_slug() . '/' );

        if ( ! empty( $ids ) ) {
            $url = add_query_arg(
                'compare_ids',
                implode( '_', array_map( 'absint', $ids ) ),
                $url
            );
        }

        return $url;
    }
}
