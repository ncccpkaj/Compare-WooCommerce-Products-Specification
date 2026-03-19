# Compare-WooCommerce-Products-Specification
A WordPress plugin for WooCommerce that lets you build custom product specification groups, assign specs to products, and give shoppers a live side-by-side comparison tool — all without any third-party dependency.

---

## Features

- **Specification Groups** — Create named groups (Phone, Watch, Laptop, etc.) with any number of spec keys
- **Product Spec Editor** — Assign a group and fill in spec values directly on the product edit page
- **Single Product Spec Table** — Auto-appears as a "Specifications" tab on product pages; also available as a standalone shortcode
- **Compare Page** — Three-slot live search with skeleton loaders, side-by-side spec table, and persistent state via cookie + URL
- **Smart Caching** — Search results cached via Redis Object Cache (if available) or WP transients, with automatic invalidation on product changes
- **Configurable Slug** — Set the compare page URL slug from Settings → Permalinks, or disable it entirely with `off`

---

## Requirements

| Requirement | Version |
|---|---|
| WordPress | 5.8+ |
| WooCommerce | 5.0+ |
| PHP | 7.4+ |

---

## Installation

1. Download the latest ZIP from the [Releases]([https://github.com/ncccpkaj/Compare-WooCommerce-Products-Specification]) page
2. In your WordPress admin go to **Plugins → Add New → Upload Plugin**
3. Upload the ZIP and click **Install Now**, then **Activate**

On activation the plugin will:
- Create a **Compare Products** page automatically with the `[woo_compare]` shortcode inside it
- Seed two default spec groups: **Phone** and **Watch**
- Set the compare page slug to **off** (no compare button shown until you configure a slug)

---

## Configuration

### 1. Set the Compare Page Slug

Go to **Settings → Permalinks**. At the bottom you will find a **Product Compare** section with a single field: **Compare Page Slug**.

| Value | Effect |
|---|---|
| `off` *(default)* | Compare button hidden on all product pages. Shortcode still works. |
| any slug (e.g. `compare-products`) | Compare button appears on product pages and links to `/your-slug/` |

After saving, the WP page slug and rewrite rules are updated automatically — no need to manually visit Permalinks again.

---

### 2. Create Specification Groups

Go to **Products → Specification Groups** in the WordPress admin.

Click **Add New Group** and fill in:
- **Group Label** — the display name, e.g. `Phone`
- **Spec Keys** — add as many keys as you need, e.g. `Brand`, `Model`, `Display`, `Battery Info`

You can add or remove keys at any time. If a key is later renamed or deleted, any product that used it will show the old value as a read-only field on the product edit page — the data is never lost.

---

### 3. Assign Specs to a Product

Open any product in **Products → Edit**.

In the **Product Specifications** meta box:

1. Select a **Specification Category** from the dropdown
2. A table appears with one empty row. Each row has a **Key** dropdown (populated from the selected group) and a **Value** text field
3. Use **+ Add Row** (bottom) or the **+** button on any row to insert a new row below it
4. Use **✕** to remove a row (at least one row is always kept)
5. Use **Add New Spec Key to Group** (shown after selecting a category) to add a brand-new key to the group inline — no need to leave the page
6. Save the product as normal

All spec data is stored in a single post meta key `_woo_compare_specs` as JSON.

---

## Shortcodes

### `[wpc_specs]`

Displays the specification table for a product.

**Where to use:** Single product pages (auto-injected as a "Specifications" tab), or any page/post.

**Attributes:**

| Attribute | Default | Description |
|---|---|---|
| `id` | current product | WooCommerce product ID to display specs for |

**Examples:**

```
[wpc_specs]
```
```
[wpc_specs id="123"]
```

If the compare page slug is enabled, a **Compare This Product** button appears below the table linking to the compare page with the product pre-loaded.

---

### `[woo_compare]`

Renders the full product comparison interface.

**Where to use:** Any page or post. The auto-created **Compare Products** page already contains this shortcode.

**What it includes:**
- Three search slots — type to search by product title, SKU, description, or short description
- Live skeleton loader while searching (5 animated placeholder rows) and while loading a selected product card
- Each selected slot shows: product image, name, price, **Remove** button, **Shop Now** button
- A side-by-side spec table built from the union of all selected products' spec keys
- Removing a product compacts remaining products to the left; an empty search slot appears on the right

**State persistence:**
- Selected product IDs are stored in a cookie (`wpc_compare_ids`) with a **30-day** lifetime
- The URL is kept in sync as `?compare_ids=893_894_809` via `history.replaceState` — shareable and bookmarkable
- On page load the plugin reads the URL param first, then falls back to the cookie

**Examples:**

```
[woo_compare]
```

---

## Linking to the Compare Page

### From a single product page

If the slug is configured (not `off`), a **Compare This Product** button automatically appears in the Specifications tab linking to:

```
/your-slug/?compare_ids=PRODUCT_ID
```

### Manual link

You can link to the compare page with up to 3 pre-loaded products:

```
/your-slug/?compare_ids=893_894_809
```

IDs are separated by underscores. The page will show a skeleton loader for each product while fetching.

---

## Caching

Search results are cached automatically.

**With Redis Object Cache plugin active:** results are cached using `wp_cache_set` in the `wpc_search` group (1-hour TTL).

**Without Redis:** results are stored as WP transients (1-hour TTL).

Cache is cleared automatically whenever:
- A product is saved or updated
- A product's stock level changes
- A product's publish status changes (draft ↔ publish ↔ trash)

To verify caching is working, see the [Cache Verification](#cache-verification) section below.

---

## Cache Verification

To confirm cache hits, temporarily add these two lines to `class-wpc-ajax.php` inside `search_products()` right after the cache check:

```php
$cached = self::cache_get( $cache_key );
if ( null !== $cached ) {
    header( 'X-WPC-Cache: HIT' );
    wp_send_json_success( $cached );
}
header( 'X-WPC-Cache: MISS' );
```

Open **DevTools → Network**, type in a search box, and inspect the response headers of the `wpc_search_products` request. The second identical search should return `X-WPC-Cache: HIT`. Remove the headers after testing.

---

## File Structure

```
woo-product-compare/
├── woo-product-compare.php          # Main plugin file, activation, constants
├── includes/
│   ├── class-wpc-spec-groups.php    # CRUD helpers for spec groups (wp_options)
│   ├── class-wpc-settings.php       # Settings → Permalinks slug field
│   ├── class-wpc-admin-menu.php     # Products → Specification Groups admin page
│   ├── class-wpc-meta-box.php       # Product edit page meta box
│   ├── class-wpc-ajax.php           # All AJAX handlers + Redis/transient cache
│   ├── class-wpc-frontend.php       # Product tab, [wpc_specs], [woo_compare]
│   └── class-wpc-assets.php         # CSS/JS enqueue
├── assets/
│   ├── css/admin.css
│   ├── css/frontend.css
│   ├── js/admin.js                  # Meta box dynamic table
│   └── js/compare.js                # Compare page search, skeleton, state
└── templates/
    └── compare-page.php             # [woo_compare] HTML template
```

---

## Frequently Asked Questions

**The compare button is not showing on product pages.**
Go to **Settings → Permalinks**, set the **Compare Page Slug** to any value other than `off` (e.g. `compare-products`), and save.

**I renamed a spec key in the group but the product edit page shows a yellow read-only field.**
That is by design — the old key name is preserved so no data is lost. You can manually update the value and save, or re-add the old key name to the group to restore the dropdown.

**The `[woo_compare]` shortcode search is not returning results.**
Only products that have been assigned a spec group and saved will appear in search results. Make sure you have added specs to at least a few products.

**Can I place `[woo_compare]` on a page that is not the auto-created Compare Products page?**
Yes. The shortcode works on any page. The plugin detects when the shortcode is used and enqueues the required JavaScript automatically.

**How do I change the compare page URL?**
Go to **Settings → Permalinks**, update the **Compare Page Slug**, and click **Save Changes**. The WP page slug and rewrite rules are updated automatically.

---

## Changelog

### 1.1.0
- Added Settings → Permalinks slug configuration with `off` support
- Added Redis Object Cache support with transient fallback
- Added automatic cache invalidation on product save/stock/status changes
- Added skeleton loaders for search suggestions and product card loading
- Cookie-based compare state with 30-day lifetime
- URL sync via `?compare_ids=` with `history.replaceState`
- Orphaned spec keys shown as read-only on product edit page
- Plus (+) button to insert a row after any existing row
- Search now covers title, SKU, description, and short description

### 1.0.0
- Initial release

---

## License

GPL-2.0-or-later — see [LICENSE](LICENSE) for details.
