<?php
defined( 'ABSPATH' ) || exit;
/**
 * Template: Compare Page — [woo_compare]
 * Single unified <table>: product header row + spec rows all in one table.
 */
?>
<div id="wpc-compare-page" class="wpc-compare-wrap wpc-spec-compare">
    <div class="wpc-compare-top-wrap">
        <table class="wpc-top-table" id="wpc-main-table">
            <tbody>
                <tr id="wpc-header-row">
                    <td class="wpc-info-col">
                        <div class="wpc-brand-cell">
                            <strong><?php esc_html_e( 'Compare Products', 'woo-product-compare' ); ?></strong>
                            <p><?php esc_html_e( 'Find and select products to see the differences and similarities between them', 'woo-product-compare' ); ?></p>
                        </div>
                    </td>
                    <?php for ( $i = 0; $i < 3; $i++ ) : ?>
                    <td class="wpc-slot-col" data-slot="<?php echo $i; ?>">

                        <div class="wpc-slot-search-state">
                            <div class="wpc-search-box-wrap">
                                <span class="wpc-search-icon">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#aaa" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                </span>
                                <input type="text" class="wpc-search-input"
                                       placeholder="<?php esc_attr_e( 'Search...', 'woo-product-compare' ); ?>"
                                       autocomplete="off">
                                <button class="wpc-clear-btn" style="display:none;" title="Clear">&#10005;</button>
                            </div>
                            <ul class="wpc-suggestions" style="display:none;"></ul>
                            <div class="wpc-slot-placeholder">
                                <svg xmlns="http://www.w3.org/2000/svg" width="52" height="52" viewBox="0 0 52 52"><rect width="52" height="52" rx="8" fill="#f0f0f0"/><path d="M10 42l8-10 6 7 8-10L42 42H10z" fill="#d0d0d0"/><circle cx="18" cy="20" r="5" fill="#d0d0d0"/></svg>
                            </div>
                        </div>

                        <div class="wpc-slot-product-state" style="display:none;">
                            <img class="wpc-prod-img" src="" alt="">
                            <a class="wpc-prod-name" href="#" target="_blank"></a>
                            <div class="wpc-prod-price"></div>
                            <div class="wpc-prod-actions">
                                <button class="wpc-btn-remove"><?php esc_html_e( 'Remove', 'woo-product-compare' ); ?></button>
                                <a class="wpc-btn-shop" href="#" target="_blank"><?php esc_html_e( 'Shop Now', 'woo-product-compare' ); ?></a>
                            </div>
                        </div>

                    </td>
                    <?php endfor; ?>
                </tr>


            </tbody>
        </table>
    </div>
</div>
