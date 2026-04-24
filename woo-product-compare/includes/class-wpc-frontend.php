<?php
defined( 'ABSPATH' ) || exit;

class WPC_Frontend {

    public static function init() {
        // add_filter( 'woocommerce_product_tabs', [ __CLASS__, 'add_spec_tab' ] );
        // add_action( 'woocommerce_single_product_summary', [ __CLASS__, 'show_specs_before_description' ], 19 );
        add_filter( 'body_class',     [ __CLASS__, 'add_body_class_if_specs' ] );
        add_shortcode( 'woo_compare', [ __CLASS__, 'compare_shortcode' ] );
        add_shortcode( 'wpc_specs',   [ __CLASS__, 'specs_shortcode' ] );
    }

    // ── [wpc_specs] shortcode ─────────────────────────────────────────────────

    public static function specs_shortcode( $atts ) {
        $atts = shortcode_atts( [ 'id' => 0 ], $atts );
        $id   = absint( $atts['id'] );

        if ( ! $id ) {
            global $product;
            if ( $product ) $id = $product->get_id();
        }
        if ( ! $id ) return '';

        $raw   = get_post_meta( $id, '_woo_compare_specs', true );
        $data  = $raw ? json_decode( $raw, true ) : [];
        $specs = $data['specs'] ?? [];
        if ( empty( $specs ) ) return '';

        // Only show Compare button if compare page is enabled
        $show_btn    = WPC_Settings::is_enabled();
        $compare_url = $show_btn ? WPC_Settings::get_compare_url( [ $id ] ) : '';

        ob_start(); ?>
        <div class="wpc-spec-table-wrap">
            <table class="wpc-spec-table">
                <tbody>
                    <?php foreach ( $specs as $row ) : ?>
                    <tr>
                        <th><?php echo esc_html( $row['key'] ); ?></th>
                        <td><?php echo esc_html( $row['value'] ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ( $compare_url ) : ?>
            <p style="margin-top:14px;">
                <a href="<?php echo esc_url( $compare_url ); ?>"
                   class="button wpc-compare-btn">
                    <?php esc_html_e( 'Compare This Product', 'woo-product-compare' ); ?>
                </a>
            </p>
            <?php endif; ?>
        </div>
        <?php return ob_get_clean();
    }

    // ── Specifications tab on single product ──────────────────────────────────

    public static function add_spec_tab( $tabs ) {
        global $product;
        if ( ! $product ) return $tabs;
        if ( ! get_post_meta( $product->get_id(), '_woo_compare_specs', true ) ) return $tabs;

        $tabs['wpc_specs'] = [
            'title'    => __( 'Specifications', 'woo-product-compare' ),
            'priority' => 50,
            'callback' => [ __CLASS__, 'render_spec_tab' ],
        ];
        return $tabs;
    }

    public static function show_specs_before_description() {
        global $product;
        if ( ! $product ) return;

        echo self::specs_shortcode( [ 'id' => $product->get_id() ] );
    }

    public static function render_spec_tab() {
        echo do_shortcode( '[wpc_specs]' );
    }

    public static function add_body_class_if_specs( $classes ) {
        if ( ! is_product() ) return $classes;
        $product_id = get_queried_object_id();
        if ( ! $product_id ) return $classes;
        if ( get_post_meta( $product_id, '_wpc_has_specs', true ) === '1' ) {
            $classes[] = 'have_specs';
        }
        return $classes;
    }

    // ── [woo_compare] shortcode ───────────────────────────────────────────────

    public static function compare_shortcode( $atts ) {
        // Always enqueue compare assets when shortcode is used
        // (handles cases where shortcode is on a non-compare-page-ID page)
        if ( ! wp_script_is( 'wpc-compare', 'enqueued' ) ) {
            WPC_Assets::enqueue_compare_assets();
        }
        ob_start();
        include WPC_PATH . 'templates/compare-page.php';
        return ob_get_clean();
    }
}
