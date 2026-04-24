<?php
defined( 'ABSPATH' ) || exit;

/**
 * WPC_Ajax
 *
 * Handles all AJAX endpoints.
 * Search results are cached — Redis if available, else WP transients.
 * Cache is cleared/updated on product save, stock change, and publish.
 */
class WPC_Ajax {

    const CACHE_GROUP  = 'wpc_search';
    const CACHE_TTL    = 3600; // 1 hour
    const CACHE_PREFIX = 'wpc_s_';

    public static function init() {
        // ── Admin AJAX ──
        add_action( 'wp_ajax_wpc_get_keys',    [ __CLASS__, 'get_keys' ] );
        add_action( 'wp_ajax_wpc_add_new_key', [ __CLASS__, 'add_new_key' ] );

        // ── Frontend AJAX (public) ──
        add_action( 'wp_ajax_wpc_search_products',        [ __CLASS__, 'search_products' ] );
        add_action( 'wp_ajax_nopriv_wpc_search_products', [ __CLASS__, 'search_products' ] );

        add_action( 'wp_ajax_wpc_get_product_specs',        [ __CLASS__, 'get_product_specs' ] );
        add_action( 'wp_ajax_nopriv_wpc_get_product_specs', [ __CLASS__, 'get_product_specs' ] );

        // ── Cache invalidation hooks ──
        add_action( 'save_post_product',              [ __CLASS__, 'flush_search_cache' ], 10, 1 );
        add_action( 'woocommerce_product_set_stock',  [ __CLASS__, 'flush_search_cache_by_product' ] );
        add_action( 'woocommerce_variation_set_stock',[ __CLASS__, 'flush_search_cache_by_product' ] );
        add_action( 'transition_post_status',         [ __CLASS__, 'on_status_change' ], 10, 3 );
    }

    // =========================================================================
    // Cache helpers
    // =========================================================================

    /**
     * Check if Redis Object Cache is available.
     */
    private static function has_redis(): bool {
        return (
            class_exists( 'Redis' ) &&
            function_exists( 'wp_cache_get' ) &&
            defined( 'WP_REDIS_VERSION' ) // Redis Object Cache plugin
        ) || (
            // Object Cache Pro
            class_exists( '\RedisCachePro\ObjectCaches\PhpRedis' )
        );
    }

    private static function cache_key( string $term, array $exclude ): string {
        sort( $exclude );
        return self::CACHE_PREFIX . md5( strtolower( trim( $term ) ) . '_' . implode( '_', $exclude ) );
    }

    private static function cache_get( string $key ) {
        if ( self::has_redis() ) {
            $val = wp_cache_get( $key, self::CACHE_GROUP );
            return ( false !== $val ) ? $val : null;
        }
        $val = get_transient( $key );
        return ( false !== $val ) ? $val : null;
    }

    private static function cache_set( string $key, $data ): void {
        if ( self::has_redis() ) {
            wp_cache_set( $key, $data, self::CACHE_GROUP, self::CACHE_TTL );
        } else {
            set_transient( $key, $data, self::CACHE_TTL );
        }

        // Track all cache keys so we can flush them
        $index = get_option( 'wpc_cache_index', [] );
        if ( ! in_array( $key, $index, true ) ) {
            $index[] = $key;
            update_option( 'wpc_cache_index', $index, false );
        }
    }

    public static function flush_all_search_cache(): void {
        $index = get_option( 'wpc_cache_index', [] );
        foreach ( $index as $key ) {
            if ( self::has_redis() ) {
                wp_cache_delete( $key, self::CACHE_GROUP );
            } else {
                delete_transient( $key );
            }
        }
        update_option( 'wpc_cache_index', [], false );
    }

    // ── Invalidation triggers ─────────────────────────────────────────────────

    public static function flush_search_cache( $post_id ): void {
        if ( get_post_type( $post_id ) === 'product' ) {
            self::flush_all_search_cache();
        }
    }

    public static function flush_search_cache_by_product( $product ): void {
        self::flush_all_search_cache();
    }

    public static function on_status_change( $new_status, $old_status, $post ): void {
        if ( $post->post_type === 'product' && $new_status !== $old_status ) {
            self::flush_all_search_cache();
        }
    }

    // =========================================================================
    // Admin AJAX
    // =========================================================================

    public static function get_keys(): void {
        check_ajax_referer( 'wpc_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_products' ) ) wp_send_json_error( 'Unauthorized' );

        $slug = sanitize_key( $_POST['group'] ?? '' );
        wp_send_json_success( WPC_Spec_Groups::get_keys( $slug ) );
    }

    public static function add_new_key(): void {
        check_ajax_referer( 'wpc_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( 'Unauthorized' );

        $slug = sanitize_key( $_POST['group'] ?? '' );
        $key  = sanitize_text_field( $_POST['key'] ?? '' );

        if ( ! $slug || ! $key ) {
            wp_send_json_error( __( 'Missing group or key.', 'woo-product-compare' ) );
        }

        $ok = WPC_Spec_Groups::add_key( $slug, $key );
        if ( $ok ) {
            wp_send_json_success( [
                'key'  => $key,
                'keys' => WPC_Spec_Groups::get_keys( $slug ),
            ] );
        } else {
            wp_send_json_error( __( 'Could not add key.', 'woo-product-compare' ) );
        }
    }

    // =========================================================================
    // Frontend AJAX
    // =========================================================================

    public static function search_products(): void {
        check_ajax_referer( 'wpc_public_nonce', 'nonce' );

        $term    = sanitize_text_field( $_POST['term'] ?? '' );
        $exclude = array_values( array_filter( array_map( 'absint', (array) ( $_POST['exclude'] ?? [] ) ) ) );

        if ( mb_strlen( $term ) < 2 ) {
            wp_send_json_success( [] );
        }

        // ── Try cache first ──
        $cache_key = self::cache_key( $term, $exclude );
        $cached    = self::cache_get( $cache_key );
        if ( null !== $cached ) {
            wp_send_json_success( $cached );
        }

        // ── Run DB query ──
        $ids = self::run_search_query( $term, $exclude );
        $ids = array_unique( array_slice( $ids, 0, 5 ) );

        $results = [];
        foreach ( $ids as $id ) {
            $product = wc_get_product( $id );
            if ( ! $product ) continue;
            $results[] = [
                'id'    => $id,
                'name'  => $product->get_name(),
                'image' => get_the_post_thumbnail_url( $id, 'thumbnail' ) ?: wc_placeholder_img_src( 'thumbnail' ),
                'url'   => get_permalink( $id ),
                'price' => $product->get_price_html(),
            ];
        }

        // ── Store in cache ──
        self::cache_set( $cache_key, $results );

        wp_send_json_success( $results );
    }

    /**
     * Run WP_Query searches across title, description, short description, and SKU.
     */
    private static function run_search_query( string $term, array $exclude ): array {
        $base_meta = [
            'key'     => '_woo_compare_specs',
            'compare' => 'EXISTS',
        ];
        $not_in = $exclude ?: [ 0 ];

        // ── Title / description search ──
        $q1 = new WP_Query( [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            's'              => $term,
            'meta_query'     => [ $base_meta ],
            'post__not_in'   => $not_in,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ] );
        $ids = (array) $q1->posts;

        if ( count( $ids ) >= 5 ) return $ids;

        // ── SKU search ──
        $q2 = new WP_Query( [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 10,
            'meta_query'     => [
                'relation' => 'AND',
                $base_meta,
                [
                    'key'     => '_sku',
                    'value'   => $term,
                    'compare' => 'LIKE',
                ],
            ],
            'post__not_in'  => array_merge( $not_in, $ids ?: [ 0 ] ),
            'fields'        => 'ids',
            'no_found_rows' => true,
        ] );
        $ids = array_merge( $ids, (array) $q2->posts );

        if ( count( $ids ) >= 5 ) return $ids;

        // ── Short description fallback ──
        global $wpdb;
        $like = '%' . $wpdb->esc_like( $term ) . '%';
        $not_in_str = implode( ',', array_map( 'intval', array_merge( $not_in, $ids ?: [ 0 ] ) ) );
        $extra = $wpdb->get_col( $wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_woo_compare_specs'
             WHERE p.post_type = 'product' AND p.post_status = 'publish'
               AND p.post_excerpt LIKE %s
               AND p.ID NOT IN ({$not_in_str})
             LIMIT 5",
            $like
        ) );

        return array_merge( $ids, array_map( 'intval', $extra ) );
    }

    // ── Get product specs ─────────────────────────────────────────────────────

    public static function get_product_specs(): void {
        check_ajax_referer( 'wpc_public_nonce', 'nonce' );

        $product_id = absint( $_POST['product_id'] ?? 0 );
        if ( ! $product_id ) wp_send_json_error( 'Invalid product ID' );

        $raw = get_post_meta( $product_id, '_woo_compare_specs', true );
        if ( ! $raw ) wp_send_json_error( 'No specs found' );

        $data    = json_decode( $raw, true );
        $product = wc_get_product( $product_id );
        $img     = get_the_post_thumbnail_url( $product_id, 'medium' ) ?: wc_placeholder_img_src( 'medium' );

        wp_send_json_success( [
            'product_id' => $product_id,
            'name'       => $product ? $product->get_name() : '',
            'url'        => get_permalink( $product_id ),
            'image'      => $img,
            'price'      => $product ? $product->get_price_html() : '',
            'group'      => $data['group'] ?? '',
            'specs'      => $data['specs'] ?? [],
        ] );
    }
}
