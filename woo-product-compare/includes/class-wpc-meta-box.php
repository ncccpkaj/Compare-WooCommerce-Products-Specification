<?php
defined( 'ABSPATH' ) || exit;

class WPC_Meta_Box {

    const META_KEY = '_woo_compare_specs';

    public static function init() {
        add_action( 'add_meta_boxes', [ __CLASS__, 'register' ] );
        add_action( 'save_post_product', [ __CLASS__, 'save' ], 10, 2 );
    }

    public static function register() {
        add_meta_box(
            'wpc-specs',
            __( 'Product Specifications', 'woo-product-compare' ),
            [ __CLASS__, 'render' ],
            'product',
            'normal',
            'default'
        );
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public static function render( $post ) {
        wp_nonce_field( 'wpc_save_specs', 'wpc_specs_nonce' );

        $groups    = WPC_Spec_Groups::get_all();
        $saved     = get_post_meta( $post->ID, self::META_KEY, true );
        $saved     = $saved ? json_decode( $saved, true ) : [];
        $sel_group = $saved['group'] ?? '';
        $sel_specs = $saved['specs'] ?? [];

        if ( empty( $groups ) ) {
            echo '<p>' . esc_html__( 'No spec groups found.', 'woo-product-compare' )
               . ' <a href="' . esc_url( admin_url( 'edit.php?post_type=product&page=wpc-spec-groups' ) ) . '">'
               . esc_html__( 'Add Spec Groups', 'woo-product-compare' ) . '</a></p>';
            return;
        }
        ?>
        <div id="wpc-meta-box">

            <?php /* ── Group selector ── */ ?>
            <div class="wpc-row wpc-group-select-row">
                <label for="wpc_spec_group"><strong><?php esc_html_e( 'Specification Category', 'woo-product-compare' ); ?></strong></label>
                <select id="wpc_spec_group" name="wpc_spec_group" style="margin-left:10px;min-width:200px;">
                    <option value=""><?php esc_html_e( '— Select a group —', 'woo-product-compare' ); ?></option>
                    <?php foreach ( $groups as $slug => $group ) : ?>
                        <option value="<?php echo esc_attr( $slug ); ?>"
                            <?php selected( $sel_group, $slug ); ?>
                            data-keys="<?php echo esc_attr( wp_json_encode( $group['keys'] ) ); ?>">
                            <?php echo esc_html( $group['label'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php
            /*
             * ── Add new key area ──
             * Show if a group is already selected on page load (editing existing product).
             * JS will toggle it on group change.
             */
            $show_new_key = ! empty( $sel_group ) ? '' : 'display:none;';
            ?>
            <div id="wpc-new-key-area" style="<?php echo $show_new_key; ?> margin:12px 0 8px; min-width: calc(100% - 30px);">                
                    <strong><?php esc_html_e( 'Add New Spec Key to Group:', 'woo-product-compare' ); ?></strong>
                    <input type="text" id="wpc_new_key_input" class="regular-text"
                           placeholder="<?php esc_attr_e( 'e.g. Connectivity', 'woo-product-compare' ); ?>">
                    <button type="button" id="wpc_add_new_key" class="button">
                        <?php esc_html_e( '+ Add Key', 'woo-product-compare' ); ?>
                    </button>
                    <span id="wpc_new_key_msg" style="color:green;display:none;"></span>
            </div>

            <?php /* ── Specs table ── */ ?>
            <div id="wpc-specs-area" style="<?php echo $sel_group ? '' : 'display:none'; ?>">

                <?php /* ── Table action buttons ── */ ?>
                <div class="wpc-table-actions">
                    <button type="button" id="wpc-btn-copy"     class="button wpc-tbl-btn" disabled title="Copy this spec table to clipboard">
                        <span class="dashicons dashicons-clipboard"></span> <?php esc_html_e( 'Copy', 'woo-product-compare' ); ?>
                    </button>
                    <button type="button" id="wpc-btn-paste"    class="button wpc-tbl-btn" disabled title="Paste copied spec table (same category only)">
                        <span class="dashicons dashicons-editor-paste-text"></span> <?php esc_html_e( 'Paste', 'woo-product-compare' ); ?>
                    </button>
                    <button type="button" id="wpc-btn-generate" class="button wpc-tbl-btn" disabled title="Fill table with all keys from selected category">
                        <span class="dashicons dashicons-table-col-after"></span> <?php esc_html_e( 'Generate', 'woo-product-compare' ); ?>
                    </button>
                    <?php if ( WPC_AI_Settings::is_active() ) : ?>
                    <button type="button" id="wpc-btn-ai-spec"  class="button wpc-tbl-btn wpc-btn-ai" disabled title="Auto-fill spec values using AI">
                        <span class="wpc-ai-icon">✦</span> <?php esc_html_e( 'Generate with AI', 'woo-product-compare' ); ?>
                    </button>
                    <?php endif; ?>
                </div>

                <table id="wpc-specs-table" class="widefat" style="margin-top:10px;">
                    <thead>
                        <tr>
                            <th style="width:38%"><?php esc_html_e( 'Spec Key', 'woo-product-compare' ); ?></th>
                            <th><?php esc_html_e( 'Value', 'woo-product-compare' ); ?></th>
                            <th style="width:72px"><?php esc_html_e( 'Actions', 'woo-product-compare' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="wpc-specs-tbody">
                        <?php if ( $sel_group && ! empty( $sel_specs ) ) :
                            $group_keys = WPC_Spec_Groups::get_keys( $sel_group );
                            foreach ( $sel_specs as $i => $row ) :
                                $saved_key     = $row['key'] ?? '';
                                $key_in_group  = in_array( $saved_key, $group_keys, true );
                                ?>
                            <tr class="wpc-spec-row">
                                <td>
                                    <?php if ( $saved_key && ! $key_in_group ) :
                                        /*
                                         * Key was renamed / removed from group after saving.
                                         * Show as read-only so the value isn't lost.
                                         */
                                        ?>
                                        <input type="text"
                                               name="wpc_specs[<?php echo $i; ?>][key]"
                                               value="<?php echo esc_attr( $saved_key ); ?>"
                                               class="widefat wpc-key-readonly"
                                               readonly
                                               title="<?php esc_attr_e( 'This key no longer exists in the group. Edit the group to restore it.', 'woo-product-compare' ); ?>">
                                    <?php else : ?>
                                        <select name="wpc_specs[<?php echo $i; ?>][key]" class="wpc-key-select widefat">
                                            <option value=""><?php esc_html_e( '— Select key —', 'woo-product-compare' ); ?></option>
                                            <?php foreach ( $group_keys as $k ) : ?>
                                                <option value="<?php echo esc_attr( $k ); ?>"
                                                    <?php selected( $saved_key, $k ); ?>>
                                                    <?php echo esc_html( $k ); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <input type="text"
                                           name="wpc_specs[<?php echo $i; ?>][value]"
                                           value="<?php echo esc_attr( $row['value'] ?? '' ); ?>"
                                           class="widefat"
                                           placeholder="<?php esc_attr_e( 'Enter value', 'woo-product-compare' ); ?>">
                                </td>
                                <td class="wpc-row-actions">
                                    <button type="button" class="button wpc-add-row-after" title="<?php esc_attr_e( 'Add row after', 'woo-product-compare' ); ?>">+</button>
                                    <button type="button" class="button wpc-del-row" title="<?php esc_attr_e( 'Remove row', 'woo-product-compare' ); ?>">✕</button>
                                </td>
                            </tr>
                            <?php endforeach;

                        elseif ( $sel_group ) :
                            // Group selected but no saved specs — show one default empty row
                            $group_keys = WPC_Spec_Groups::get_keys( $sel_group );
                            ?>
                            <tr class="wpc-spec-row">
                                <td>
                                    <select name="wpc_specs[0][key]" class="wpc-key-select widefat">
                                        <option value=""><?php esc_html_e( '— Select key —', 'woo-product-compare' ); ?></option>
                                        <?php foreach ( $group_keys as $k ) : ?>
                                            <option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $k ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="wpc_specs[0][value]" class="widefat"
                                           placeholder="<?php esc_attr_e( 'Enter value', 'woo-product-compare' ); ?>">
                                </td>
                                <td class="wpc-row-actions">
                                    <button type="button" class="button wpc-add-row-after" title="<?php esc_attr_e( 'Add row after', 'woo-product-compare' ); ?>">+</button>
                                    <button type="button" class="button wpc-del-row" title="<?php esc_attr_e( 'Remove row', 'woo-product-compare' ); ?>">✕</button>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div style="margin-top:10px;">
                    <button type="button" id="wpc-add-row" class="button">
                        + <?php esc_html_e( 'Add Row', 'woo-product-compare' ); ?>
                    </button>
                </div>
            </div>

            <?php /* ── Hidden new-row template (used by JS) ── */ ?>
            <script type="text/html" id="wpc-row-template">
                <tr class="wpc-spec-row">
                    <td>
                        <select name="wpc_specs[__IDX__][key]" class="wpc-key-select widefat">
                            <option value=""><?php esc_html_e( '— Select key —', 'woo-product-compare' ); ?></option>
                        </select>
                    </td>
                    <td>
                        <input type="text" name="wpc_specs[__IDX__][value]" class="widefat"
                               placeholder="<?php esc_attr_e( 'Enter value', 'woo-product-compare' ); ?>">
                    </td>
                    <td class="wpc-row-actions">
                        <button type="button" class="button wpc-add-row-after" title="Add row after">+</button>
                        <button type="button" class="button wpc-del-row" title="Remove">✕</button>
                    </td>
                </tr>
            </script>

        </div><!-- #wpc-meta-box -->
        <?php
    }

    // ── Save ──────────────────────────────────────────────────────────────────

    public static function save( $post_id, $post ) {
        if ( ! isset( $_POST['wpc_specs_nonce'] ) ) return;
        if ( ! wp_verify_nonce( $_POST['wpc_specs_nonce'], 'wpc_save_specs' ) ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

        $group = sanitize_key( $_POST['wpc_spec_group'] ?? '' );
        $rows  = (array) ( $_POST['wpc_specs'] ?? [] );

        $specs = [];
        foreach ( $rows as $row ) {
            $key   = sanitize_text_field( $row['key']   ?? '' );
            $value = sanitize_text_field( $row['value'] ?? '' );
            if ( $key !== '' ) {
                $specs[] = [ 'key' => $key, 'value' => $value ];
            }
        }

        if ( $group ) {
            update_post_meta( $post_id, self::META_KEY, wp_json_encode( [
                'group' => $group,
                'specs' => $specs,
            ] ) );
            if (!empty($specs)){
                update_post_meta( $post_id, '_wpc_has_specs', '1' );          
            }
        } else {
            delete_post_meta( $post_id, self::META_KEY );
            delete_post_meta( $post_id, '_wpc_has_specs' );
        }
    }
} 
