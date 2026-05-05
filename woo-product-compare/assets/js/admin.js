/* global wpcAdmin, jQuery, tinymce */
(function ($) {
    'use strict';

    var rowIndex     = 0;
    var currentGroup = '';

    // ── Init ──────────────────────────────────────────────────────────────────

    $(function () {
        rowIndex     = $('#wpc-specs-tbody .wpc-spec-row').length;
        currentGroup = $('#wpc_spec_group').val();

        if (currentGroup) { $('#wpc-new-key-area').show(); $('#wpc-specs-area').show(); }

        updateTableButtons();
        injectDescBar();
        injectShortDescBar();
        buildAiPopup();

        $('#wpc_spec_group').on('change', function () {
            currentGroup = $(this).val();
            if (!currentGroup) { $('#wpc-specs-area').hide(); $('#wpc-new-key-area').hide(); updateTableButtons(); return; }
            fetchAndRebuildTable(currentGroup);
            $('#wpc-new-key-area').show();
            updateTableButtons();
        });

        $(document).on('click', '#wpc-btn-copy',     onCopy);
        $(document).on('click', '#wpc-btn-paste',    onPaste);
        $(document).on('click', '#wpc-btn-generate', onGenerate);
        $(document).on('click', '#wpc-btn-ai-spec',  function () { openAiPopup('spec'); });

        $(document).on('click', '#wpc-add-row',       function () { if (currentGroup) { addRowAtEnd(getCurrentKeys()); } });
        $(document).on('click', '.wpc-add-row-after', function () { if (currentGroup) addRowAfter($(this).closest('tr'), getCurrentKeys()); });
        $(document).on('click', '.wpc-del-row', function () {
            if ($('#wpc-specs-tbody .wpc-spec-row').length <= 1) { alert('At least one row is required.'); return; }
            $(this).closest('tr').remove();
            reindexRows(); updateTableButtons();
        });

        $('#wpc_add_new_key').on('click', function () {
            var k = $('#wpc_new_key_input').val().trim();
            if (!k) { alert(wpcAdmin.i18n.enterKeyFirst); return; }
            addKeyToGroup(currentGroup, k);
        });
        $('#wpc_new_key_input').on('keypress', function (e) {
            if (e.which === 13) { e.preventDefault(); $('#wpc_add_new_key').trigger('click'); }
        });
    });

    // =========================================================================
    // Table button states
    // =========================================================================

    function updateTableButtons() {
        var hasRows  = $('#wpc-specs-tbody .wpc-spec-row').length > 0;
        var hasGroup = !!currentGroup;

        setBtn('#wpc-btn-copy',     hasRows);
        setBtn('#wpc-btn-paste',    hasGroup);
        setBtn('#wpc-btn-generate', hasGroup);

        if (wpcAdmin.aiActive) {
            $('#wpc-btn-ai-spec').show();
            setBtn('#wpc-btn-ai-spec', hasGroup);
        } else {
            $('#wpc-btn-ai-spec').hide();
        }
    }

    function setBtn(sel, enabled) {
        $(sel).prop('disabled', !enabled).toggleClass('wpc-btn-disabled', !enabled);
    }

    // =========================================================================
    // COPY / PASTE / GENERATE
    // =========================================================================

    var CLIPBOARD_MARKER = 'WPC_SPEC_TABLE_V1';
    var CLIPBOARD_TYPE   = 'woo-product-compare/spec-table';

    function onCopy() {
        var rows = [];
        $('#wpc-specs-tbody .wpc-spec-row').each(function () {
            var $r = $(this);
            var key = $r.find('.wpc-key-select').val() || $r.find('.wpc-key-readonly').val();
            var val = $r.find('input[type=text]:not(.wpc-key-readonly)').val();
            if (key) rows.push({ key: key, value: val || '' });
        });
        if (!rows.length) return;

        var clip = {
            type: CLIPBOARD_TYPE,
            version: 1,
            group: currentGroup,
            groupLabel: getCurrentGroupLabel(),
            rows: rows
        };

        saveLocalClipboard(clip);
        writeSystemClipboard(clip).done(function () {
            updateTableButtons();
            flashButton('#wpc-btn-copy', 'Copied!');
        }).fail(function () {
            updateTableButtons();
            flashButton('#wpc-btn-copy', 'Copy blocked');
            alert('The table was copied for this site, but the browser blocked system clipboard access. Allow clipboard access to paste across sites.');
        });
    }

    function onPaste() {
        if (!currentGroup) return;

        var $btn = $('#wpc-btn-paste');
        setBtn('#wpc-btn-paste', false);

        readSpecClipboard().done(function (clip) {
            if (!clip) {
                alert('Nothing to paste. Copy a spec table first.');
                return;
            }
            if (!isClipboardGroupCompatible(clip)) {
                alert('Copied spec table category does not match the selected category.');
                return;
            }

            var keys = getCurrentKeys();
            clip.rows.forEach(function (r) { addRowAtEnd(keys, r.key, r.value); });
            reindexRows();
            flashButton('#wpc-btn-paste', 'Pasted!');
        }).fail(function () {
            alert('Could not read the clipboard. Please allow clipboard access and try again.');
        }).always(function () {
            updateTableButtons();
            $btn.blur();
        });
    }

    function getClipboard() {
        try { var r = localStorage.getItem('wpc_spec_clipboard'); return r ? JSON.parse(r) : null; } catch(e) { return null; }
    }

    function saveLocalClipboard(clip) {
        try { localStorage.setItem('wpc_spec_clipboard', JSON.stringify(clip)); } catch(e) {}
    }

    function writeSystemClipboard(clip) {
        var d = $.Deferred();
        var text = serializeClipboard(clip);
        var html = buildClipboardHtml(clip);

        if (navigator.clipboard && window.ClipboardItem) {
            try {
                navigator.clipboard.write([
                    new ClipboardItem({
                        'text/plain': new Blob([text], { type: 'text/plain' }),
                        'text/html':  new Blob([html], { type: 'text/html' })
                    })
                ]).then(function () {
                    d.resolve();
                }).catch(function () {
                    copyTextFallback(text) ? d.resolve() : d.reject();
                });
                return d.promise();
            } catch(e) {}
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                d.resolve();
            }).catch(function () {
                copyTextFallback(text) ? d.resolve() : d.reject();
            });
            return d.promise();
        }

        copyTextFallback(text) ? d.resolve() : d.reject();
        return d.promise();
    }

    function readSpecClipboard() {
        var d = $.Deferred();
        var localClip = normalizeClipboard(getClipboard());

        if (navigator.clipboard && navigator.clipboard.readText) {
            navigator.clipboard.readText().then(function (text) {
                d.resolve(parseClipboardText(text) || localClip);
            }).catch(function () {
                d.resolve(localClip);
            });
        } else {
            d.resolve(localClip);
        }

        return d.promise();
    }

    function serializeClipboard(clip) {
        return CLIPBOARD_MARKER + "\n" + JSON.stringify(clip);
    }

    function parseClipboardText(text) {
        if (!text) return null;
        var raw = String(text).trim();
        var markerPos = raw.indexOf(CLIPBOARD_MARKER);

        if (markerPos !== -1) {
            raw = raw.substring(markerPos + CLIPBOARD_MARKER.length).trim();
        }

        try {
            return normalizeClipboard(JSON.parse(raw));
        } catch(e) {
            return null;
        }
    }

    function normalizeClipboard(clip) {
        if (!clip || !Array.isArray(clip.rows)) return null;

        var rows = [];
        clip.rows.forEach(function (row) {
            if (!row || row.key === undefined || row.key === null || String(row.key).trim() === '') return;
            rows.push({
                key: String(row.key),
                value: row.value === undefined || row.value === null ? '' : String(row.value)
            });
        });

        if (!rows.length) return null;

        return {
            type: clip.type || CLIPBOARD_TYPE,
            version: clip.version || 1,
            group: clip.group ? String(clip.group) : '',
            groupLabel: clip.groupLabel ? String(clip.groupLabel) : '',
            rows: rows
        };
    }

    function isClipboardGroupCompatible(clip) {
        if (!clip || !currentGroup) return false;
        if (clip.group && clip.group === currentGroup) return true;

        var copiedLabel = (clip.groupLabel || '').toLowerCase();
        var currentLabel = getCurrentGroupLabel().toLowerCase();
        return !!copiedLabel && copiedLabel === currentLabel;
    }

    function buildClipboardHtml(clip) {
        var html = '<table data-wpc-clipboard="' + escAttr(JSON.stringify(clip)) + '"><tbody>';
        clip.rows.forEach(function (row) {
            html += '<tr><th>' + escHtml(row.key) + '</th><td>' + escHtml(row.value) + '</td></tr>';
        });
        return html + '</tbody></table>';
    }

    function copyTextFallback(text) {
        var $ta = $('<textarea readonly>');
        $ta.css({ position: 'fixed', top: '-9999px', left: '-9999px', opacity: 0 }).val(text);
        $('body').append($ta);
        $ta[0].select();

        var ok = false;
        try { ok = document.execCommand('copy'); } catch(e) {}
        $ta.remove();
        return ok;
    }

    function flashButton(selector, text) {
        var $b = $(selector), orig = $b.text();
        $b.text(text);
        setTimeout(function () { $b.text(orig); }, 1500);
    }

    function onGenerate() {
        if (!currentGroup) return;
        if (!confirm('Replace the current table with all keys from the selected category?')) return;
        var keys = getCurrentKeys();
        $('#wpc-specs-tbody').empty(); rowIndex = 0;
        keys.forEach(function (k) { addRowAtEnd(keys, k, ''); });
        reindexRows(); updateTableButtons();
    }

    // =========================================================================
    // AI Pre-generation Popup
    // =========================================================================

    function buildAiPopup() {
        if (!wpcAdmin.aiActive) return;

        var providers = wpcAdmin.aiProviders || {};
        if (!Object.keys(providers).length) return;

        var providerOpts = '';
        Object.keys(providers).forEach(function (pid) {
            var p = providers[pid];
            var warn = p.has_key ? '' : ' ⚠ No key';
            providerOpts += '<option value="' + esc(pid) + '">' + esc(p.label) + warn + '</option>';
        });

        var html =
        '<div id="wpc-ai-popup-overlay" style="display:none;">' +
          '<div id="wpc-ai-popup">' +
            '<div class="wpc-popup-header">' +
              '<h3 id="wpc-popup-title">Generate with AI</h3>' +
              '<button type="button" class="wpc-popup-close" id="wpc-ai-popup-close">✕</button>' +
            '</div>' +
            '<div class="wpc-popup-body">' +
              '<div class="wpc-popup-row">' +
                '<label>AI Provider</label>' +
                '<select id="wpc-popup-provider">' + providerOpts + '</select>' +
              '</div>' +
              '<div class="wpc-popup-row">' +
                '<label>Model</label>' +
                '<select id="wpc-popup-model"></select>' +
              '</div>' +
              '<div class="wpc-popup-row wpc-popup-row-full">' +
                '<label>Prompt <small>(editable)</small></label>' +
                '<textarea id="wpc-popup-prompt" rows="8"></textarea>' +
              '</div>' +
              '<div id="wpc-popup-key-warn" class="wpc-popup-warn" style="display:none;">' +
                '⚠ API key for this provider is not set. Add it in <a href="/wp-admin/options-general.php#wpc_ai_section" target="_blank">Settings → General</a>.' +
              '</div>' +
            '</div>' +
            '<div class="wpc-popup-footer">' +
              '<button type="button" class="button" id="wpc-ai-popup-cancel">Cancel</button>' +
              '<button type="button" class="button button-primary" id="wpc-ai-popup-submit">Generate</button>' +
            '</div>' +
          '</div>' +
        '</div>';

        $('body').append(html);

        // Provider change → update model list
        $(document).on('change', '#wpc-popup-provider', updatePopupModels);

        // Close
        $(document).on('click', '#wpc-ai-popup-close, #wpc-ai-popup-cancel', closeAiPopup);
        $(document).on('click', '#wpc-ai-popup-overlay', function (e) {
            if ($(e.target).is('#wpc-ai-popup-overlay')) closeAiPopup();
        });

        // Submit
        $(document).on('click', '#wpc-ai-popup-submit', onPopupSubmit);
    }

    var _popupType = '';

    function openAiPopup(type) {
        var providers = wpcAdmin.aiProviders || {};
        if (!Object.keys(providers).length) {
            showAlert('No active AI provider configured. Go to Settings → General to set one up.');
            return;
        }

        _popupType = type;

        // Validation: title required
        var title = getProductTitle();
        if (!title) { showAlert('Please enter a product title before generating.'); return; }

        // Category is recommended but not blocking
        var category = getProductCategory();

        // Set prompt template based on type, pre-filled with title+category
        var rawPrompt = type === 'desc'  ? (wpcAdmin.aiPromptDesc  || '')
                      : type === 'short' ? (wpcAdmin.aiPromptShort || '')
                      :                    (wpcAdmin.aiPromptSpec  || '');

        if (type === 'spec') {
            var keys = getCurrentKeys();
            rawPrompt = rawPrompt
                .replace(/\{spec_keys\}/g, keys.join(', '));
        }
        rawPrompt = rawPrompt
            .replace(/\{title\}/g,    title)
            .replace(/\{category\}/g, category || 'General');

        var titles = { desc: 'Generate Description with AI', short: 'Generate Short Description with AI', spec: 'Generate Specifications with AI' };
        $('#wpc-popup-title').text(titles[type] || 'Generate with AI');
        $('#wpc-popup-prompt').val(rawPrompt);
        updatePopupModels();
        $('#wpc-ai-popup-overlay').fadeIn(150);
    }

    function updatePopupModels() {
        var pid      = $('#wpc-popup-provider').val();
        var providers = wpcAdmin.aiProviders || {};
        var p        = providers[pid];
        if (!p) return;

        var opts = '';
        Object.keys(p.models).forEach(function (mid) {
            var def = mid === p.default_model ? ' selected' : '';
            opts += '<option value="' + esc(mid) + '"' + def + '>' + esc(p.models[mid]) + '</option>';
        });
        $('#wpc-popup-model').html(opts);

        if (!p.has_key) {
            $('#wpc-popup-key-warn').show();
            $('#wpc-ai-popup-submit').prop('disabled', true);
        } else {
            $('#wpc-popup-key-warn').hide();
            $('#wpc-ai-popup-submit').prop('disabled', false);
        }
    }

    function closeAiPopup() {
        $('#wpc-ai-popup-overlay').fadeOut(150);
    }

    function onPopupSubmit() {
        var provider = $('#wpc-popup-provider').val();
        var model    = $('#wpc-popup-model').val();
        var prompt   = $('#wpc-popup-prompt').val().trim();

        if (!prompt) { showAlert('Prompt cannot be empty.'); return; }

        var $btn = $('#wpc-ai-popup-submit');
        $btn.text('Generating…').prop('disabled', true);

        var postData = {
            action:    'wpc_ai_generate',
            nonce:     wpcAdmin.aiNonce,
            type:      _popupType,
            provider:  provider,
            model:     model,
            prompt:    prompt,
        };

        if (_popupType === 'spec') {
            postData.spec_keys = getCurrentKeys();
        }

        $.post(wpcAdmin.ajaxUrl, postData, function (res) {
            $btn.text('Generate').prop('disabled', false);
            closeAiPopup();

            if (!res.success) {
                showAlert(res.data.message || 'Unknown error.');
                return;
            }

            var content = res.data.content;

            if (_popupType === 'desc') {
                placeDescription(content);
            } else if (_popupType === 'short') {
                placeShortDescription(content);
            } else if (_popupType === 'spec') {
                placeSpecValues(content);
            }

        }).fail(function () {
            $btn.text('Generate').prop('disabled', false);
            showAlert('Network error. Please try again.');
        });
    }

    // =========================================================================
    // Content placement — works for all AI providers (Gemini, OpenAI, Claude,
    // OpenRouter, Groq). PHP already cleans/parses before sending to JS.
    // =========================================================================

    function placeDescription(html) {
        // Ensure html is a plain string (some providers may return an object)
        if (typeof html !== 'string') html = String(html || '');

        // TinyMCE visual mode (standard WP / WooCommerce)
        if (typeof tinymce !== 'undefined') {
            var ed = tinymce.get('content');
            if (ed && !ed.isHidden()) {
                ed.setContent(html);
                ed.fire('change');
                return;
            }
        }
        // Text mode fallback
        var $ta = $('#content');
        if ($ta.length) { $ta.val(html).trigger('input').trigger('change'); return; }
    }

    function placeShortDescription(text) {
        // Ensure plain string
        if (typeof text !== 'string') text = String(text || '');

        // WooCommerce short description uses a TinyMCE editor with id 'excerpt'
        if (typeof tinymce !== 'undefined') {
            var ed = tinymce.get('excerpt');
            if (ed && !ed.isHidden()) {
                ed.setContent(text);
                ed.fire('change');
                return;
            }
        }
        // Plain textarea fallback (text-mode or no TinyMCE)
        var $ex = $('#excerpt');
        if ($ex.length) { $ex.val(text).trigger('input').trigger('change'); return; }
        // Last resort: first textarea inside #postexcerpt meta box
        $('#postexcerpt textarea').first().val(text).trigger('input').trigger('change');
    }

    function placeSpecValues(content) {
        // 'content' arrives as a parsed JS object from PHP (spec returns object).
        // Guard: if it somehow came back as a JSON string, parse it.
        var data = content;
        if (typeof data === 'string') {
            // Strip any stray markdown fences a model may have added
            data = data.replace(/^```(?:json)?\s*/im, '').replace(/```\s*$/m, '').trim();
            try { data = JSON.parse(data); } catch (e) {
                showAlert('Could not parse AI spec response. Raw:\n' + String(content).substring(0, 200));
                return;
            }
        }

        if (!data || typeof data !== 'object' || Array.isArray(data)) {
            showAlert('AI spec response has unexpected format. Please try again.');
            return;
        }

        // Normalise all values to strings (some models return numbers/booleans)
        var normalised = {};
        Object.keys(data).forEach(function (k) {
            var v = data[k];
            if (v === null || v === undefined || v === '' || v === 'N/A') return; // skip blanks
            normalised[k] = String(v);
        });

        var filled = {};

        // 1. Fill existing rows that match a key in the AI response
        $('#wpc-specs-tbody .wpc-spec-row').each(function () {
            var $row = $(this);
            var key  = $row.find('.wpc-key-select').val() || $row.find('.wpc-key-readonly').val();
            if (key && normalised[key] !== undefined) {
                $row.find('input[type=text]:not(.wpc-key-readonly)').val(normalised[key]);
                filled[key] = true;
            }
        });

        // 2. Append new rows for any AI keys not already in the table
        var keys = getCurrentKeys();
        keys.forEach(function (k) {
            if (!filled[k] && normalised[k] !== undefined) {
                addRowAtEnd(keys, k, normalised[k]);
            }
        });

        reindexRows();
        updateTableButtons();
    }

    // =========================================================================
    // AI bars for description and short description
    // =========================================================================

    function injectDescBar() {
        if (!wpcAdmin.aiActive) return;

        var $btn = $(
            '<button type="button" class="button wpc-ai-gen-btn" id="wpc-ai-gen-desc">' +
            '<span class="wpc-ai-icon">✦</span> Generate with AI</button>'
        );

        // Ideal: inject right inside #wp-content-media-buttons (alongside Add Media)
        var $mediaBar = $('#wp-content-media-buttons');
        if ($mediaBar.length) {
            $mediaBar.append($btn);
        } else {
            // Fallback: prepend to editor tools bar
            var $tools = $('#wp-content-editor-tools');
            if (!$tools.length) $tools = $('#wp-content-wrap');
            if (!$tools.length) return;
            $tools.prepend($('<div class="wpc-ai-desc-bar">').append($btn));
        }

        $(document).on('click', '#wpc-ai-gen-desc', function () { openAiPopup('desc'); });
    }

    function injectShortDescBar() {
        if (!wpcAdmin.aiActive) return;

        var $btn = $(
            '<button type="button" class="button wpc-ai-gen-btn" id="wpc-ai-gen-short">' +
            '<span class="wpc-ai-icon">✦</span> Generate with AI</button>'
        );

        // WooCommerce short description: inject into #wp-excerpt-media-buttons if it exists
        var $mediaBar = $('#wp-excerpt-media-buttons');
        if ($mediaBar.length) {
            $mediaBar.append($btn);
            $(document).on('click', '#wpc-ai-gen-short', function () { openAiPopup('short'); });
            return;
        }

        // Fallback: inject into the editor-tools bar inside #postexcerpt
        var $tools = $('#wp-excerpt-editor-tools');
        if ($tools.length) {
            $tools.append($btn);
            $(document).on('click', '#wpc-ai-gen-short', function () { openAiPopup('short'); });
            return;
        }

        // Last resort: prepend to the .inside container of #postexcerpt
        var $inside = $('#postexcerpt .inside');
        if (!$inside.length) return;
        $inside.prepend($('<div class="wpc-ai-desc-bar">').append($btn));
        $(document).on('click', '#wpc-ai-gen-short', function () { openAiPopup('short'); });
    }

    // =========================================================================
    // Fetch / rebuild table
    // =========================================================================

    function fetchAndRebuildTable(group) {
        $.post(wpcAdmin.ajaxUrl, { action: 'wpc_get_keys', nonce: wpcAdmin.nonce, group: group }, function (res) {
            if (!res.success) return;
            $('#wpc-specs-tbody').empty(); rowIndex = 0;
            addRowAtEnd(res.data);
            $('#wpc-specs-area').show();
            updateTableButtons();
        });
    }

    // =========================================================================
    // Row helpers
    // =========================================================================

    function buildRow(keys, selectedKey, selectedVal) {
        var idx  = rowIndex++;
        var $row = $($('#wpc-row-template').html().replace(/__IDX__/g, idx));
        if (selectedKey && keys.indexOf(selectedKey) === -1) {
            $row.find('.wpc-key-select').replaceWith(
                '<input type="text" name="wpc_specs[' + idx + '][key]" value="' + escAttr(selectedKey) + '" class="widefat wpc-key-readonly" readonly>'
            );
        } else {
            $row.find('.wpc-key-select').html(buildOptions(keys, selectedKey));
        }
        if (selectedVal !== undefined) $row.find('input[type=text]:not(.wpc-key-readonly)').val(selectedVal);
        return $row;
    }

    function addRowAtEnd(keys, selectedKey, selectedVal) {
        $('#wpc-specs-tbody').append(buildRow(keys, selectedKey, selectedVal));
        updateTableButtons();
    }

    function addRowAfter($ref, keys) {
        var $new = buildRow(keys);
        $ref.after($new); reindexRows();
        $new.find('.wpc-key-select').focus();
        updateTableButtons();
    }

    function buildOptions(keys, selected) {
        var html = '<option value="">' + wpcAdmin.i18n.selectKey + '</option>';
        $.each(keys, function (i, k) {
            html += '<option value="' + escAttr(k) + '"' + (selected === k ? ' selected' : '') + '>' + escHtml(k) + '</option>';
        });
        return html;
    }

    function reindexRows() {
        $('#wpc-specs-tbody .wpc-spec-row').each(function (i) {
            $(this).find('select.wpc-key-select').attr('name', 'wpc_specs[' + i + '][key]');
            $(this).find('input[type=text]').attr('name', 'wpc_specs[' + i + '][value]');
            $(this).find('input.wpc-key-readonly').attr('name', 'wpc_specs[' + i + '][key]');
        });
        rowIndex = $('#wpc-specs-tbody .wpc-spec-row').length;
    }

    function getCurrentKeys() {
        var keys = [], raw = $('#wpc_spec_group option:selected').data('keys');
        if (raw) {
            try { var p = typeof raw === 'string' ? JSON.parse(raw) : raw; if (Array.isArray(p)) return p; } catch(e) {}
        }
        $('#wpc-specs-tbody .wpc-key-select option').each(function () {
            var v = $(this).val(); if (v && keys.indexOf(v) === -1) keys.push(v);
        });
        return keys;
    }

    function getCurrentGroupLabel() {
        return $('#wpc_spec_group option:selected').text().trim();
    }

    function addKeyToGroup(group, key) {
        var $msg = $('#wpc_new_key_msg');
        $.post(wpcAdmin.ajaxUrl, { action: 'wpc_add_new_key', nonce: wpcAdmin.nonce, group: group, key: key }, function (res) {
            if (!res.success) { $msg.text(res.data).css('color','red').show(); return; }
            var nk = res.data.keys;
            $('#wpc-specs-tbody .wpc-key-select').each(function () { $(this).html(buildOptions(nk, $(this).val())); });
            $('#wpc_spec_group option[value="' + group + '"]').data('keys', nk);
            addRowAtEnd(nk, key);
            $('#wpc_new_key_input').val('');
            $msg.text(wpcAdmin.i18n.keyAdded).css('color','green').show();
            setTimeout(function () { $msg.fadeOut(); }, 2000);
        });
    }

    // =========================================================================
    // Utility
    // =========================================================================

    function getProductTitle() {
        return $('#title').val() || $('[name="post_title"]').val() || '';
    }

    function getProductCategory() {
        var cats = [];
        $('#product_catdiv input[type=checkbox]:checked').each(function () {
            cats.push($(this).closest('label').text().trim());
        });
        return cats.join(', ');
    }

    function showAlert(msg) {
        var $o = $('<div class="wpc-alert-overlay">');
        var $b = $('<div class="wpc-alert-box"><div class="wpc-alert-icon">⚠</div><div class="wpc-alert-msg"></div><button type="button" class="button wpc-alert-close">OK</button></div>');
        $b.find('.wpc-alert-msg').text(msg);
        $o.append($b); $('body').append($o);
        $o.on('click', '.wpc-alert-close', function () { $o.remove(); });
    }

    function escHtml(s)  { return $('<div>').text(String(s)).html(); }
    function escAttr(s)  {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }
    function esc(s)      { return escHtml(s); }

})(jQuery);
