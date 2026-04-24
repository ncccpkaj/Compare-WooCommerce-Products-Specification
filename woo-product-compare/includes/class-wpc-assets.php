<?php
defined( 'ABSPATH' ) || exit;

class WPC_Assets {

    public static function init() {
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'admin_assets' ] );
        add_action( 'wp_enqueue_scripts',    [ __CLASS__, 'frontend_assets' ] );
    }

    // ── Admin ─────────────────────────────────────────────────────────────────

    public static function admin_assets( $hook ) {
        $allowed = [ 'post.php', 'post-new.php', 'product_page_wpc-spec-groups', 'admin_page_wpc-edit-group', 'options-general.php' ];
        if ( ! in_array( $hook, $allowed, true ) ) return;

        $screen     = get_current_screen();
        $is_product = ( $screen && $screen->post_type === 'product' );

        wp_enqueue_style( 'wpc-admin', WPC_URL . 'assets/css/admin.css', [], WPC_VERSION );

        if ( $is_product ) {
            wp_enqueue_script( 'wpc-admin', WPC_URL . 'assets/js/admin.js', [ 'jquery' ], WPC_VERSION, true );
            wp_localize_script( 'wpc-admin', 'wpcAdmin', [
                'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
                'nonce'           => wp_create_nonce( 'wpc_admin_nonce' ),
                'aiNonce'         => wp_create_nonce( 'wpc_ai_nonce' ),
                'aiActive'        => WPC_AI_Settings::is_active(),
                'aiProviders'     => WPC_AI_Settings::get_active_providers(),
                'aiPromptDesc'    => WPC_AI_Settings::get_prompt( 'desc' ),
                'aiPromptShort'   => WPC_AI_Settings::get_prompt( 'short' ),
                'aiPromptSpec'    => WPC_AI_Settings::get_prompt( 'spec' ),
                'i18n'            => [
                    'selectKey'     => __( '— Select key —', 'woo-product-compare' ),
                    'enterValue'    => __( 'Enter value', 'woo-product-compare' ),
                    'keyAdded'      => __( 'Key added!', 'woo-product-compare' ),
                    'keyExists'     => __( 'Key already exists.', 'woo-product-compare' ),
                    'enterKeyFirst' => __( 'Please enter a key name.', 'woo-product-compare' ),
                ],
            ] );
        }
    }

    // ── Frontend ──────────────────────────────────────────────────────────────

    public static function frontend_assets() {
        wp_enqueue_style( 'wpc-frontend', WPC_URL . 'assets/css/frontend.css', [], WPC_VERSION );

        // Auto-enqueue compare JS on the designated compare page (by stored page ID)
        $compare_page_id = (int) get_option( 'wpc_compare_page_id' );
        if ( $compare_page_id && is_page( $compare_page_id ) ) {
            self::enqueue_compare_assets();
        }
        // Note: WPC_Frontend::compare_shortcode() also calls enqueue_compare_assets()
        // when [woo_compare] is used on any other page.
    }

    /**
     * Enqueue compare page JS + pass localised data.
     * Safe to call multiple times (wp_script_is guard inside).
     */
    public static function enqueue_compare_assets() {
        if ( wp_script_is( 'wpc-compare', 'enqueued' ) ) return;

        wp_enqueue_script( 'wpc-compare', WPC_URL . 'assets/js/compare.js', [ 'jquery' ], WPC_VERSION, true );

        /*
         * Resolve initial IDs to load.
         * Priority: compare_ids param → compare param (single product) → empty
         * We deliberately strip ?compare and ?compare_ids from the URL here
         * and normalise everything into a single compare_ids value for JS.
         */
        $init_ids = [];

        $raw_ids = sanitize_text_field( $_GET['compare_ids'] ?? '' );
        if ( $raw_ids ) {
            $init_ids = array_values( array_filter( array_map( 'absint', explode( '_', $raw_ids ) ) ) );
        }

        // ?compare=ID — single product shortcut; prepend if not already in list
        $single = absint( $_GET['compare'] ?? 0 );
        if ( $single && ! in_array( $single, $init_ids, true ) ) {
            array_unshift( $init_ids, $single );
        }

        $init_ids = array_slice( $init_ids, 0, 3 );

        wp_localize_script( 'wpc-compare', 'wpcCompare', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'wpc_public_nonce' ),
            'initIds' => $init_ids,          // clean merged list, max 3
            'i18n'    => [
                'noResults' => __( 'No products found.', 'woo-product-compare' ),
                'loading'   => __( 'Searching…', 'woo-product-compare' ),
            ],
        ] );
    }
}
