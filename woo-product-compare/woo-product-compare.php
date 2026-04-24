<?php
/**
 * Plugin Name:     Woo Product Compare
 * Plugin URI:      https://github.com/ncccpkaj/Compare-WooCommerce-Products-Specification
 * Description:     Compare WooCommerce products using specification groups. Create groups, assign specs on products, display them anywhere, and use AI to generate descriptions, short descriptions, and specs.
 * Version:         1.2.0
 * Author:          Nayeem Hasan
 * Text Domain:     woo-product-compare
 * Domain Path:     /languages
 * Requires PHP:    7.4
 * WC requires at least: 5.0
 */

defined( 'ABSPATH' ) || exit;

// ── Constants ────────────────────────────────────────────────────────────────
define( 'WPC_VERSION',   '1.2.0' );
define( 'WPC_FILE',      __FILE__ );
define( 'WPC_PATH',      plugin_dir_path( __FILE__ ) );
define( 'WPC_URL',       plugin_dir_url( __FILE__ ) );
define( 'WPC_BASENAME',  plugin_basename( __FILE__ ) );

// ── Dependency check ─────────────────────────────────────────────────────────
function wpc_check_woocommerce() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>'
                . esc_html__( 'Woo Product Compare requires WooCommerce to be installed and active.', 'woo-product-compare' )
                . '</p></div>';
        } );
        deactivate_plugins( WPC_BASENAME );
    }
}
add_action( 'plugins_loaded', 'wpc_check_woocommerce' );

// ── Autoload includes ─────────────────────────────────────────────────────────
function wpc_load_plugin() {
    if ( ! class_exists( 'WooCommerce' ) ) return;

    require_once WPC_PATH . 'includes/class-wpc-spec-groups.php';
    require_once WPC_PATH . 'includes/class-wpc-settings.php';
    require_once WPC_PATH . 'includes/class-wpc-ai-settings.php';
    require_once WPC_PATH . 'includes/class-wpc-ai-ajax.php';
    require_once WPC_PATH . 'includes/class-wpc-admin-menu.php';
    require_once WPC_PATH . 'includes/class-wpc-meta-box.php';
    require_once WPC_PATH . 'includes/class-wpc-ajax.php';
    require_once WPC_PATH . 'includes/class-wpc-frontend.php';
    require_once WPC_PATH . 'includes/class-wpc-assets.php';

    WPC_Settings::init();
    WPC_AI_Settings::init();
    WPC_AI_Ajax::init();
    WPC_Admin_Menu::init();
    WPC_Meta_Box::init();
    WPC_Ajax::init();
    WPC_Frontend::init();
    WPC_Assets::init();
}
add_action( 'plugins_loaded', 'wpc_load_plugin', 20 );


// ── Add plugin action links (Settings / Spec Groups)
add_filter( 'plugin_action_links_' . WPC_BASENAME, 'wpc_plugin_action_links' );

function wpc_plugin_action_links( $links ) {
    $ai_config_link   = '<a href="' . admin_url( 'options-general.php' ) . '">AI Config</a>';
    $spec_groups_link = '<a href="' . admin_url( 'edit.php?post_type=product&page=wpc-spec-groups' ) . '">Spec Groups</a>';
    array_unshift( $links, $ai_config_link, $spec_groups_link );
    return $links;
}

// ── Activation ───────────────────────────────────────────────────────────────
register_activation_hook( __FILE__, 'wpc_activate' );
function wpc_activate() {
    // Create compare page if not exists
    $page_id = get_option( 'wpc_compare_page_id' );
    if ( ! $page_id || ! get_post( $page_id ) ) {
        $page_id = wp_insert_post( [
            'post_title'   => 'Compare Products',
            'post_name'    => 'compare-products',
            'post_content' => '[woo_compare]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ] );
        update_option( 'wpc_compare_page_id', $page_id );
    }

    // Seed default slug option (off by default)
    if ( ! get_option( 'wpc_compare_slug' ) ) {
        update_option( 'wpc_compare_slug', 'off' );
    }

    // Seed default spec groups if none exist
    if ( ! get_option( 'wpc_spec_groups' ) ) {
        $defaults = [
            'phone' => [
                'label' => 'Phone',
                'keys'  => [ 'Brand', 'Model', 'Network', 'Body', 'Display', 'Platform', 'Memory', 'Battery Info' ],
            ],
            'watch' => [
                'label' => 'Watch',
                'keys'  => [ 'Brand', 'Dimensions', 'Weight', 'OS', 'Features', 'Sensors', 'Strap' ],
            ],
        ];
        update_option( 'wpc_spec_groups', wp_json_encode( $defaults ) );
    }

    flush_rewrite_rules();
}

// ── Deactivation ─────────────────────────────────────────────────────────────
register_deactivation_hook( __FILE__, 'wpc_deactivate' );
function wpc_deactivate() {
    flush_rewrite_rules();
}
