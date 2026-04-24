/* global wpcCompare, jQuery */
(function ($) {
    'use strict';

    var MAX_SLOTS  = 3;
    var COOKIE_KEY = 'wpc_compare_ids';
    var COOKIE_DAYS = 30;
    var debounce   = {};
    var slots      = [null, null, null];

    // =========================================================================
    // Cookie — single source of truth for persistence
    // =========================================================================

    function readCookie() {
        var m = document.cookie.match(/(?:^|;\s*)wpc_compare_ids=([^;]*)/);
        if (!m) return [];
        try { return decodeURIComponent(m[1]).split('_').map(Number).filter(Boolean); }
        catch (e) { return []; }
    }

    function writeCookie(ids) {
        var val = ids.length ? ids.join('_') : '';
        var exp = new Date(Date.now() + COOKIE_DAYS * 864e5).toUTCString();
        document.cookie = COOKIE_KEY + '=' + encodeURIComponent(val)
            + '; expires=' + exp + '; path=/; SameSite=Lax';
    }

    // Keep URL in sync — only compare_ids param, remove stale ?compare
    function syncUrl(ids) {
        var params = new URLSearchParams(window.location.search);
        params.delete('compare');          // always remove legacy single-product param
        if (ids.length) {
            params.set('compare_ids', ids.join('_'));
        } else {
            params.delete('compare_ids');
        }
        var qs  = params.toString();
        var url = window.location.pathname + (qs ? '?' + qs : '');
        window.history.replaceState(null, '', url);
    }

    function saveState() {
        var ids = slots.filter(Boolean).map(function (p) { return p.product_id; });
        writeCookie(ids);
        syncUrl(ids);
    }

    // =========================================================================
    // Init
    // =========================================================================

    $(function () {
        bindSlots();

        /*
         * Priority:
         *  1. wpcCompare.initIds  (PHP merged compare_ids + compare params)
         *  2. cookie
         */
        var initIds = (wpcCompare.initIds && wpcCompare.initIds.length)
            ? wpcCompare.initIds
            : readCookie();

        initIds = initIds.slice(0, MAX_SLOTS);

        if (initIds.length) {
            initIds.forEach(function (id, i) { showSkeleton(i); });
            loadBatch(initIds, 0, function () {
                renderAllSlots();
                rebuildSpecRows();
                saveState();   // normalise URL (removes ?compare, sets compare_ids)
            });
        }
    });

    function loadBatch(ids, idx, done) {
        if (idx >= ids.length) { done(); return; }
        fetchSpecs(ids[idx], function (data) {
            if (data) slots[idx] = data;
            hideSkeleton(idx);
            loadBatch(ids, idx + 1, done);
        });
    }

    // =========================================================================
    // Bind
    // =========================================================================

    function bindSlots() {
        for (var i = 0; i < MAX_SLOTS; i++) bindSlot(i);
        $(document).on('click.wpc', function (e) {
            if (!$(e.target).closest('.wpc-slot-col').length) {
                $('.wpc-suggestions').hide().empty();
            }
        });
    }

    function bindSlot(idx) {
        var $col = slotCol(idx);
        $col.find('.wpc-search-input').on('input', function () {
            var val = $(this).val();
            $col.find('.wpc-clear-btn').toggle(val.length > 0);
            clearTimeout(debounce[idx]);
            if (val.length < 2) { $col.find('.wpc-suggestions').hide().empty(); return; }
            debounce[idx] = setTimeout(function () { doSearch(idx, val); }, 280);
        });
        $col.find('.wpc-clear-btn').on('click', function () {
            $col.find('.wpc-search-input').val('').trigger('input').focus();
        });
        $col.find('.wpc-btn-remove').on('click', function () { removeSlot(idx); });
    }

    // =========================================================================
    // Skeleton
    // =========================================================================

    var SKEL =
        '<div class="wpc-skeleton">' +
            '<div class="wpc-skel-img wpc-skel-pulse"></div>' +
            '<div class="wpc-skel-line wpc-skel-pulse" style="width:80%"></div>' +
            '<div class="wpc-skel-line wpc-skel-pulse" style="width:55%"></div>' +
            '<div class="wpc-skel-actions">' +
                '<div class="wpc-skel-btn wpc-skel-pulse"></div>' +
                '<div class="wpc-skel-btn wpc-skel-pulse"></div>' +
            '</div>' +
        '</div>';

    function showSkeleton(idx) {
        var $col = slotCol(idx);
        $col.find('.wpc-slot-search-state').hide();
        $col.find('.wpc-slot-product-state').hide();
        if (!$col.find('.wpc-skeleton').length) $col.append(SKEL);
    }

    function hideSkeleton(idx) {
        slotCol(idx).find('.wpc-skeleton').remove();
    }

    // =========================================================================
    // Search
    // =========================================================================

    function doSearch(idx, term) {
        var $col    = slotCol(idx);
        var $list   = $col.find('.wpc-suggestions');
        var exclude = slots.filter(Boolean).map(function (p) { return p.product_id; });

        // Show 5 skeleton suggestion rows while fetching
        var skelHtml = '';
        for (var s = 0; s < 5; s++) {
            skelHtml +=
                '<li class="wpc-suggest-skel">' +
                    '<div class="wpc-ss-img wpc-skel-pulse"></div>' +
                    '<div class="wpc-ss-text">' +
                        '<div class="wpc-ss-line wpc-skel-pulse" style="width:' + (55 + s * 8) + '%"></div>' +
                    '</div>' +
                '</li>';
        }
        $list.html(skelHtml).show();

        $.post(wpcCompare.ajaxUrl, {
            action:  'wpc_search_products',
            nonce:   wpcCompare.nonce,
            term:    term,
            exclude: exclude,
        }, function (res) {
            $list.empty();
            if (!res.success || !res.data.length) {
                $list.html('<li class="wpc-suggest-none">' + wpcCompare.i18n.noResults + '</li>').show();
                return;
            }
            $.each(res.data.slice(0, 5), function (i, p) {
                $('<li class="wpc-suggest-item">')
                    .html('<img src="' + esc(p.image) + '" alt="" loading="lazy">'
                        + '<span class="wpc-suggest-name">' + escHtml(p.name) + '</span>')
                    .on('click', function () {
                        $list.hide().empty();
                        $col.find('.wpc-search-input').val('');
                        $col.find('.wpc-clear-btn').hide();
                        selectProduct(idx, p.id);
                    })
                    .appendTo($list);
            });
            $list.show();
        }).fail(function () {
            $list.html('<li class="wpc-suggest-none">' + wpcCompare.i18n.noResults + '</li>').show();
        });
    }

    // =========================================================================
    // Select
    // =========================================================================

    function selectProduct(idx, productId) {
        slotCol(idx).find('.wpc-slot-search-state').hide();
        slotCol(idx).find('.wpc-suggestions').hide().empty();
        showSkeleton(idx);

        fetchSpecs(productId, function (data) {
            hideSkeleton(idx);
            if (!data) { slotCol(idx).find('.wpc-slot-search-state').show(); return; }
            slots[idx] = data;
            saveState();
            renderSlot(idx);
            rebuildSpecRows();
        });
    }

    function fetchSpecs(productId, cb) {
        $.post(wpcCompare.ajaxUrl, {
            action:     'wpc_get_product_specs',
            nonce:      wpcCompare.nonce,
            product_id: productId,
        }, function (res) { cb(res.success ? res.data : null); })
        .fail(function () { cb(null); });
    }

    // =========================================================================
    // Remove
    // =========================================================================

    function removeSlot(idx) {
        slots[idx] = null;
        var filled = slots.filter(Boolean);
        slots = [null, null, null];
        filled.forEach(function (p, i) { slots[i] = p; });
        saveState();
        renderAllSlots();
        rebuildSpecRows();
    }

    // =========================================================================
    // Render
    // =========================================================================

    function renderAllSlots() { for (var i = 0; i < MAX_SLOTS; i++) renderSlot(i); }

    function renderSlot(idx) {
        var $col     = slotCol(idx);
        var data     = slots[idx];
        var $search  = $col.find('.wpc-slot-search-state');
        var $product = $col.find('.wpc-slot-product-state');

        hideSkeleton(idx);

        if (!data) {
            $product.hide();
            $search.show();
            $col.find('.wpc-search-input').val('');
            $col.find('.wpc-clear-btn').hide();
            $col.find('.wpc-suggestions').hide().empty();
            return;
        }

        $col.find('.wpc-prod-img').attr('src', data.image).attr('alt', data.name);
        $col.find('.wpc-prod-name').attr('href', data.url).text(data.name);
        $col.find('.wpc-prod-price').html(data.price || '');
        $col.find('.wpc-btn-shop').attr('href', data.url);
        $search.hide();
        $product.show();
    }

    // =========================================================================
    // Spec rows inside the unified table
    // =========================================================================

    function rebuildSpecRows() {
        $('#wpc-main-table tbody tr.wpc-spec-row').remove();
        var filled = slots.filter(Boolean);
        if (!filled.length) return;

        var allKeys = [];
        filled.forEach(function (p) {
            (p.specs || []).forEach(function (r) {
                if (allKeys.indexOf(r.key) === -1) allKeys.push(r.key);
            });
        });

        var lookup = slots.map(function (p) {
            if (!p) return {};
            var m = {};
            (p.specs || []).forEach(function (r) { m[r.key] = r.value; });
            return m;
        });

        var $tbody = $('#wpc-main-table tbody');

        allKeys.forEach(function (key) {
            var $tr = $('<tr class="wpc-spec-row">');
            $tr.append('<td class="wpc-spec-key-col">' + escHtml(key) + '</td>');
            slots.forEach(function (p, i) {
                if (!p) { $tr.append('<td class="wpc-spec-val-col wpc-empty-col"></td>'); return; }
                var val  = lookup[i][key];
                var disp = (val !== undefined && val !== '')
                    ? escHtml(val) : '<span class="wpc-na">&#8212;</span>';
                $tr.append('<td class="wpc-spec-val-col">' + disp + '</td>');
            });
            $tbody.append($tr);
        });
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    function slotCol(idx) { return $('.wpc-slot-col[data-slot="' + idx + '"]'); }
    function escHtml(s)   { return $('<div>').text(String(s)).html(); }
    function esc(s)       { return String(s).replace(/"/g, '&quot;').replace(/'/g, '&#39;'); }

})(jQuery);
