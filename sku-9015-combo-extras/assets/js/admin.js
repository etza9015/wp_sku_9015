(function($) {
    'use strict';

    function splitSkus(raw) {
        raw = raw || '';
        return raw
            .replace(/[;|\n\r]+/g, ',')
            .split(',')
            .map(function(item) { return $.trim(item); })
            .filter(function(item, index, arr) {
                if (!item) return false;
                var lower = item.toLowerCase();
                return arr.findIndex(function(v) { return v.toLowerCase() === lower; }) === index;
            });
    }

    function escapeHtml(text) {
        return $('<div/>').text(text || '').html();
    }

    function readKnown($map) {
        var current = $map.data('sku9015-known');
        if (current) return current;

        var raw = $map.find('.sku_9015-known-products').first().text() || '{}';
        var parsed = {};

        try {
            parsed = JSON.parse(raw);
        } catch (e) {
            parsed = {};
        }

        $map.data('sku9015-known', parsed);
        return parsed;
    }

    function rememberProduct($map, product) {
        if (!product || !product.sku) return;
        var known = readKnown($map);
        known[String(product.sku).toLowerCase()] = product;
        $map.data('sku9015-known', known);
    }

    function getKnownProduct($map, sku) {
        var known = readKnown($map);
        return known[String(sku || '').toLowerCase()] || null;
    }

    function writeSkus($textarea, skus) {
        $textarea.val(skus.join(', '));
        renderMap($textarea.closest('[data-sku_9015-map]'));
    }

    function statusLabel(product) {
        if (!product) return '<span class="sku_9015-state is-manual">Manual</span>';
        if (product.status === 'missing') return '<span class="sku_9015-state is-missing">No encontrado</span>';
        if (product.status === 'unavailable') return '<span class="sku_9015-state is-warning">No disponible</span>';
        return '<span class="sku_9015-state is-ok">OK</span>';
    }

    function productName(product, fallback) {
        if (!product) return 'SKU capturado manualmente';
        return product.name || fallback || 'Producto';
    }

    function renderMainPreview($map) {
        var sku = $.trim($map.find('[data-sku_9015-main-sku]').val());
        var $preview = $map.find('[data-sku_9015-main-preview]');

        if (!sku) {
            $preview.empty();
            return;
        }

        var product = getKnownProduct($map, sku);
        var html = '';
        html += '<div class="sku_9015-main-selected">';
        html += '<strong>' + escapeHtml(sku) + '</strong>';
        html += '<span>' + escapeHtml(productName(product, sku)) + '</span>';
        html += statusLabel(product);
        html += '</div>';
        $preview.html(html);
    }

    function renderExtraRows($map) {
        var $textarea = $map.find('[data-sku_9015-extra-skus]');
        var $tbody = $map.find('[data-sku_9015-extra-rows]');
        var $empty = $map.find('[data-sku_9015-empty-extras]');
        var skus = splitSkus($textarea.val());

        $tbody.empty();
        $empty.toggle(!skus.length);

        skus.forEach(function(sku, index) {
            var product = getKnownProduct($map, sku);
            var type = product && product.type ? product.type : '—';
            var price = product && product.price ? product.price : '—';

            var $tr = $('<tr/>', { 'data-sku_9015-extra-row': sku });
            $tr.append('<td><code>' + escapeHtml(sku) + '</code><div class="sku_9015-mobile-meta">' + statusLabel(product) + '</div></td>');
            $tr.append('<td><strong>' + escapeHtml(productName(product, sku)) + '</strong><div class="sku_9015-row-status">' + statusLabel(product) + '</div></td>');
            $tr.append('<td>' + escapeHtml(type) + '</td>');
            $tr.append('<td>' + escapeHtml(price) + '</td>');

            var $actions = $('<td class="sku_9015-row-actions"/>');
            var $up = $('<button type="button" class="button button-small" title="Subir">↑</button>');
            var $down = $('<button type="button" class="button button-small" title="Bajar">↓</button>');
            var $remove = $('<button type="button" class="button button-small button-link-delete">Quitar</button>');

            $up.prop('disabled', index === 0).on('click', function() {
                var next = splitSkus($textarea.val());
                if (index > 0) {
                    var temp = next[index - 1];
                    next[index - 1] = next[index];
                    next[index] = temp;
                    writeSkus($textarea, next);
                }
            });

            $down.prop('disabled', index === skus.length - 1).on('click', function() {
                var next = splitSkus($textarea.val());
                if (index < next.length - 1) {
                    var temp = next[index + 1];
                    next[index + 1] = next[index];
                    next[index] = temp;
                    writeSkus($textarea, next);
                }
            });

            $remove.on('click', function() {
                var next = splitSkus($textarea.val()).filter(function(value) {
                    return value.toLowerCase() !== sku.toLowerCase();
                });
                writeSkus($textarea, next);
            });

            $actions.append($up, $down, $remove);
            $tr.append($actions);
            $tbody.append($tr);
        });
    }

    function updateSummary($map) {
        var mainSku = $.trim($map.find('[data-sku_9015-main-sku]').val());
        var title = $.trim($map.find('[data-sku_9015-title]').val()) || 'Sin título';
        var tiers = $.trim($map.find('[data-sku_9015-tiers]').val()) || 'Sin niveles';
        var extras = splitSkus($map.find('[data-sku_9015-extra-skus]').val());
        var enabled = $map.find('[data-sku_9015-enabled]').is(':checked');

        $map.find('[data-sku_9015-card-title]').text(mainSku || SKU9015Admin.i18n.newRelation);
        $map.find('[data-sku_9015-card-subtitle]').text(title);
        $map.find('[data-sku_9015-main-pill]').text(mainSku || 'Sin SKU');
        $map.find('[data-sku_9015-extra-pill]').text(extras.length + (extras.length === 1 ? ' extra' : ' extras'));
        $map.find('[data-sku_9015-tier-pill]').text(tiers);

        var $status = $map.find('[data-sku_9015-status-pill]');
        $status.text(enabled ? 'Activo' : 'Inactivo')
            .toggleClass('is-active', enabled)
            .toggleClass('is-off', !enabled);
    }

    function renderMap($map) {
        if (!$map || !$map.length) return;
        readKnown($map);
        renderMainPreview($map);
        renderExtraRows($map);
        updateSummary($map);
    }

    function addExtraSku($map, productOrSku) {
        var sku = typeof productOrSku === 'string' ? productOrSku : productOrSku.sku;
        if (!sku) return;

        if (typeof productOrSku === 'object') {
            rememberProduct($map, productOrSku);
        }

        var $textarea = $map.find('[data-sku_9015-extra-skus]');
        var skus = splitSkus($textarea.val());
        var exists = skus.some(function(value) {
            return value.toLowerCase() === String(sku).toLowerCase();
        });

        if (!exists) {
            skus.push(sku);
        }

        writeSkus($textarea, skus);
    }

    function resultHtml(product) {
        var sku = product.sku || 'SIN-SKU';
        var type = product.type ? ' · ' + product.type : '';
        var price = product.price ? ' · ' + product.price : '';

        return '<strong>' + escapeHtml(sku) + '</strong><span>' + escapeHtml(product.name + type + price) + '</span>';
    }

    function searchProducts($field, term) {
        var $box = $field.closest('.sku_9015-search-field');
        var $results = $box.find('.sku_9015-search-results');

        if (term.length < 2) {
            $results.empty().prop('hidden', true);
            return;
        }

        $results.html('<div class="sku_9015-search-result is-muted">' + SKU9015Admin.i18n.searching + '</div>').prop('hidden', false);

        $.ajax({
            url: SKU9015Admin.ajaxUrl,
            method: 'GET',
            dataType: 'json',
            data: {
                action: 'sku_9015_product_search',
                nonce: SKU9015Admin.nonce,
                term: term
            }
        }).done(function(response) {
            $results.empty();

            if (!response || !response.success || !response.data.results.length) {
                $results.html('<div class="sku_9015-search-result is-muted">' + SKU9015Admin.i18n.noResults + '</div>').prop('hidden', false);
                return;
            }

            response.data.results.forEach(function(product) {
                var $item = $('<button/>', {
                    type: 'button',
                    class: 'sku_9015-search-result'
                }).html(resultHtml(product));

                $item.on('click', function() {
                    var $map = $field.closest('[data-sku_9015-map]');
                    var mode = $box.attr('data-sku_9015-search');

                    rememberProduct($map, product);

                    if (mode === 'main') {
                        $map.find('[data-sku_9015-main-sku]').val(product.sku);
                    } else {
                        addExtraSku($map, product);
                    }

                    $field.val('');
                    $results.empty().prop('hidden', true);
                    renderMap($map);
                });

                $results.append($item);
            });
        }).fail(function() {
            $results.html('<div class="sku_9015-search-result is-muted">Error al buscar.</div>').prop('hidden', false);
        });
    }

    function setOpen($map, open) {
        $map.toggleClass('is-open', !!open);
        $map.find('[data-sku_9015-toggle]').first().attr('aria-expanded', open ? 'true' : 'false');
    }

    var timers = new WeakMap();

    $(document).on('input', '.sku_9015-product-search', function() {
        var field = this;
        var $field = $(field);
        var term = $.trim($field.val());

        if (timers.get(field)) {
            clearTimeout(timers.get(field));
        }

        timers.set(field, setTimeout(function() {
            searchProducts($field, term);
        }, 250));
    });

    $(document).on('input change blur', '[data-sku_9015-main-sku], [data-sku_9015-extra-skus], [data-sku_9015-title], [data-sku_9015-tiers], [data-sku_9015-enabled]', function() {
        var $map = $(this).closest('[data-sku_9015-map]');
        if ($(this).is('[data-sku_9015-extra-skus]')) {
            writeSkus($(this), splitSkus($(this).val()));
        } else {
            renderMap($map);
        }
    });

    $(document).on('click', '[data-sku_9015-toggle]', function() {
        var $map = $(this).closest('[data-sku_9015-map]');
        setOpen($map, !$map.hasClass('is-open'));
    });

    $(document).on('click', '.sku_9015-remove-map', function() {
        if (window.confirm(SKU9015Admin.i18n.confirmRemove)) {
            $(this).closest('[data-sku_9015-map]').slideUp(160, function() {
                $(this).remove();
            });
        }
    });

    function addMap() {
        var template = $('#sku_9015-map-template').html();
        var index = 'new_' + Date.now();
        template = template.replace(/__INDEX__/g, index);

        var $node = $(template);
        $('#sku_9015-maps').append($node);
        setOpen($node, true);
        renderMap($node);

        $('html, body').animate({ scrollTop: $node.offset().top - 80 }, 250);
        $node.find('.sku_9015-product-search').first().trigger('focus');
    }

    $('#sku_9015-add-map, #sku_9015-add-map-bottom').on('click', addMap);

    $('#sku_9015-open-all').on('click', function() {
        $('[data-sku_9015-map]').each(function() { setOpen($(this), true); });
    });

    $('#sku_9015-close-all').on('click', function() {
        $('[data-sku_9015-map]').each(function() { setOpen($(this), false); });
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.sku_9015-search-box').length) {
            $('.sku_9015-search-results').prop('hidden', true);
        }
    });

    $(function() {
        $('[data-sku_9015-map]').each(function() {
            renderMap($(this));
        });
    });
})(jQuery);
