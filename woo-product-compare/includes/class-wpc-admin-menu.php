<?php
defined( 'ABSPATH' ) || exit;

class WPC_Admin_Menu {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_post_wpc_save_group',   [ __CLASS__, 'handle_save_group' ] );
        add_action( 'admin_post_wpc_delete_group', [ __CLASS__, 'handle_delete_group' ] );
    }

    public static function register_menu() {
        add_submenu_page(
            'edit.php?post_type=product',
            __( 'Specification Groups', 'woo-product-compare' ),
            __( 'Specification Groups', 'woo-product-compare' ),
            'manage_woocommerce',
            'wpc-spec-groups',
            [ __CLASS__, 'render_list_page' ]
        );
        add_submenu_page(
            null,
            __( 'Edit Spec Group', 'woo-product-compare' ),
            __( 'Edit Spec Group', 'woo-product-compare' ),
            'manage_woocommerce',
            'wpc-edit-group',
            [ __CLASS__, 'render_edit_page' ]
        );
    }

    // ── List Page ─────────────────────────────────────────────────────────────

    public static function render_list_page() {
        $groups = WPC_Spec_Groups::get_all( true );
        $add_url = admin_url( 'admin.php?page=wpc-edit-group' );
        ?>
        <div class="wrap wpc-wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Spec Groups', 'woo-product-compare' ); ?></h1>
            <a href="<?php echo esc_url( $add_url ); ?>" class="page-title-action"><?php esc_html_e( 'Add New Group', 'woo-product-compare' ); ?></a>
            <hr class="wp-header-end">

            <?php if ( isset( $_GET['wpc_msg'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php
                    $msgs = [
                        'saved'   => __( 'Spec group saved.', 'woo-product-compare' ),
                        'deleted' => __( 'Spec group deleted.', 'woo-product-compare' ),
                    ];
                    echo esc_html( $msgs[ $_GET['wpc_msg'] ] ?? '' );
                ?></p></div>
            <?php endif; ?>

            <?php if ( empty( $groups ) ) : ?>
                <p><?php esc_html_e( 'No spec groups yet. Add one!', 'woo-product-compare' ); ?></p>
            <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Label', 'woo-product-compare' ); ?></th>
                        <th><?php esc_html_e( 'Slug', 'woo-product-compare' ); ?></th>
                        <th><?php esc_html_e( 'Spec Keys', 'woo-product-compare' ); ?></th>
                        <th style="width:100px;"><?php esc_html_e( 'Actions', 'woo-product-compare' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $groups as $slug => $group ) :
                        $edit_url   = admin_url( 'admin.php?page=wpc-edit-group&slug=' . urlencode( $slug ) );
                        $delete_url = wp_nonce_url(
                            admin_url( 'admin-post.php?action=wpc_delete_group&slug=' . urlencode( $slug ) ),
                            'wpc_delete_group_' . $slug
                        );
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html( $group['label'] ); ?></strong></td>
                        <td><code><?php echo esc_html( $slug ); ?></code></td>
                        <td>
                            <div style="overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;line-clamp:2;-webkit-box-orient:vertical">
                                <?php
                                    $keys       = $group['keys'] ?? [];
                                    $output = implode( ', ', $keys );
                                
                                    if ( count( $keys ) >= 15 ) {
                                        $output .= ' ...';
                                    }
                                    echo esc_html( $output );
                                ?>
                            </div>
                        </td>
                        <td>
                            <a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'woo-product-compare' ); ?></a>
                            &nbsp;|&nbsp;
                            <a href="<?php echo esc_url( $delete_url ); ?>"
                               onclick="return confirm('<?php esc_attr_e( 'Delete this spec group?', 'woo-product-compare' ); ?>')"
                               style="color:#b32d2e"><?php esc_html_e( 'Delete', 'woo-product-compare' ); ?></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }

    // ── Edit / Add Page ───────────────────────────────────────────────────────

    public static function render_edit_page() {
        $slug  = isset( $_GET['slug'] ) ? sanitize_key( $_GET['slug'] ) : '';
        $group = $slug ? WPC_Spec_Groups::get_group( $slug ) : null;
        $label = $group['label'] ?? '';
        $keys  = $group['keys']  ?? [];
        $is_edit = (bool) $group;
        $form_action = admin_url( 'admin-post.php' );
        ?>
        <div class="wrap wpc-wrap">
            <h1><?php echo $is_edit
                ? esc_html__( 'Edit Spec Group', 'woo-product-compare' )
                : esc_html__( 'Add Spec Group', 'woo-product-compare' );
            ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=wpc-spec-groups' ) ); ?>">
                &larr; <?php esc_html_e( 'Back to Spec Groups', 'woo-product-compare' ); ?>
            </a>
            <br><br>

            <form method="post" action="<?php echo esc_url( $form_action ); ?>" id="wpc-group-form">
                <?php wp_nonce_field( 'wpc_save_group', 'wpc_nonce' ); ?>
                <input type="hidden" name="action" value="wpc_save_group">
                <?php if ( $is_edit ) : ?>
                    <input type="hidden" name="original_slug" value="<?php echo esc_attr( $slug ); ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th><label for="wpc_group_label"><?php esc_html_e( 'Group Label', 'woo-product-compare' ); ?></label></th>
                        <td>
                            <input type="text" id="wpc_group_label" name="group_label"
                                   value="<?php echo esc_attr( $label ); ?>"
                                   class="regular-text" required
                                   placeholder="<?php esc_attr_e( 'e.g. Phone', 'woo-product-compare' ); ?>">
                            <p class="description"><?php esc_html_e( 'The slug will be auto-generated from the label.', 'woo-product-compare' ); ?></p>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e( 'Spec Keys', 'woo-product-compare' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Add the specification keys for this group. These will appear as selectable fields on the product edit page.', 'woo-product-compare' ); ?></p>
                <br>

                <div id="wpc-keys-list">
                    <?php foreach ( $keys as $i => $key ) : ?>
                    <div class="wpc-key-row">
                        <input type="text" name="spec_keys[]"
                               value="<?php echo esc_attr( $key ); ?>"
                               class="regular-text"
                               placeholder="<?php esc_attr_e( 'e.g. Brand', 'woo-product-compare' ); ?>">
                        <button type="button" class="button wpc-remove-key" title="<?php esc_attr_e( 'Remove', 'woo-product-compare' ); ?>">✕</button>
                    </div>
                    <?php endforeach; ?>
                    <?php if ( empty( $keys ) ) : ?>
                    <div class="wpc-key-row">
                        <input type="text" name="spec_keys[]" class="regular-text"
                               placeholder="<?php esc_attr_e( 'e.g. Brand', 'woo-product-compare' ); ?>">
                        <button type="button" class="button wpc-remove-key" title="<?php esc_attr_e( 'Remove', 'woo-product-compare' ); ?>">✕</button>
                    </div>
                    <?php endif; ?>
                </div>

                <br>
                <button type="button" id="wpc-add-key" class="button">
                    + <?php esc_html_e( 'Add Key', 'woo-product-compare' ); ?>
                </button>

                <br><br>
                <?php submit_button( $is_edit ? __( 'Update Group', 'woo-product-compare' ) : __( 'Save Group', 'woo-product-compare' ) ); ?>
            </form>
        </div>

        <script>
        (function(){
            document.getElementById('wpc-add-key').addEventListener('click', function(){
                const row = document.createElement('div');
                row.className = 'wpc-key-row';
                row.innerHTML = '<input type="text" name="spec_keys[]" class="regular-text" placeholder="<?php echo esc_js( __( 'e.g. Brand', 'woo-product-compare' ) ); ?>">'
                              + '<button type="button" class="button wpc-remove-key" title="Remove">✕</button>';
                document.getElementById('wpc-keys-list').appendChild(row);
                row.querySelector('input').focus();
                bindRemove(row.querySelector('.wpc-remove-key'));
            });
            document.querySelectorAll('.wpc-remove-key').forEach(bindRemove);
            function bindRemove(btn){
                btn.addEventListener('click', function(){
                    const list = document.getElementById('wpc-keys-list');
                    if(list.querySelectorAll('.wpc-key-row').length > 1){
                        btn.closest('.wpc-key-row').remove();
                    }
                });
            }
        })();
        </script>

        <style>
        .wpc-key-row { display:flex; align-items:center; gap:8px; margin-bottom:8px; }
        .wpc-key-row input { flex:1; max-width:360px; }
        </style>
        <?php
    }

    // ── Handlers ──────────────────────────────────────────────────────────────

    public static function handle_save_group() {
        check_admin_referer( 'wpc_save_group', 'wpc_nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Unauthorized' );

        $label         = sanitize_text_field( $_POST['group_label'] ?? '' );
        $original_slug = sanitize_key( $_POST['original_slug'] ?? '' );
        $keys          = array_filter( array_map( 'sanitize_text_field', (array) ( $_POST['spec_keys'] ?? [] ) ) );
        $slug          = $original_slug ?: WPC_Spec_Groups::label_to_slug( $label );

        WPC_Spec_Groups::save_group( $slug, $label, array_values( $keys ) );
        wp_redirect( admin_url( 'admin.php?page=wpc-spec-groups&wpc_msg=saved' ) );
        exit;
    }

    public static function handle_delete_group() {
        $slug = sanitize_key( $_GET['slug'] ?? '' );
        check_admin_referer( 'wpc_delete_group_' . $slug );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Unauthorized' );

        WPC_Spec_Groups::delete_group( $slug );
        wp_redirect( admin_url( 'admin.php?page=wpc-spec-groups&wpc_msg=deleted' ) );
        exit;
    }
}
