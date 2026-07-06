(function($) {
    'use strict';

    function parseTiers($box) {
        var raw = $box.attr('data-tiers') || '{}';
        try {
            return JSON.parse(raw);
        } catch (e) {
            return {};
        }
    }

    function money(amount, currency) {
        amount = parseFloat(amount) || 0;
        return '$' + amount.toLocaleString('es-MX', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' ' + (currency || 'MXN');
    }

    function getTier(qty, tiers) {
        var percent = 0;
        var minActive = 0;

        Object.keys(tiers).map(function(k) {
            return parseInt(k, 10);
        }).sort(function(a, b) {
            return a - b;
        }).forEach(function(min) {
            if (qty >= min) {
                percent = parseFloat(tiers[min]) || 0;
                minActive = min;
            }
        });

        return {
            percent: percent,
            min: minActive
        };
    }

    function getNextTier(qty, tiers) {
        var keys = Object.keys(tiers).map(function(k) {
            return parseInt(k, 10);
        }).sort(function(a, b) {
            return a - b;
        });

        for (var i = 0; i < keys.length; i++) {
            if (qty < keys[i]) {
                return {
                    min: keys[i],
                    percent: parseFloat(tiers[keys[i]]) || 0
                };
            }
        }

        return null;
    }

    function updateCard($card) {
        var $check = $card.find('[data-sku_9015-check]');
        var $qty = $card.find('[data-sku_9015-qty]');
        var qty = parseInt($qty.val(), 10) || 0;
        var max = parseInt($qty.attr('max'), 10) || 99;

        qty = Math.max(0, Math.min(max, qty));
        $qty.val(qty);

        if (qty > 0) {
            $check.prop('checked', true);
        }

        if (!$check.is(':checked')) {
            qty = 0;
            $qty.val(0);
        }

        var $variation = $card.find('[data-sku_9015-variation]');
        var price = parseFloat($card.attr('data-price')) || 0;

        if ($variation.length) {
            var selectedPrice = $variation.find(':selected').data('price');
            if (selectedPrice !== undefined && selectedPrice !== '') {
                price = parseFloat(selectedPrice) || price;
                $card.attr('data-active-price', price);
            } else {
                $card.attr('data-active-price', price);
            }
        } else {
            $card.attr('data-active-price', price);
        }

        $card.toggleClass('is-selected', qty > 0 && $check.is(':checked'));
        $card.find('[data-sku_9015-selected-text]').text(qty + ' seleccionados');

        return {
            qty: qty,
            price: price,
            selected: qty > 0 && $check.is(':checked')
        };
    }

    function updateBox($box) {
        var currency = $box.attr('data-currency') || (window.SKU9015Frontend ? SKU9015Frontend.currency : 'MXN');
        var tiers = parseTiers($box);
        var qtyTotal = 0;
        var subtotal = 0;

        $box.find('[data-sku_9015-card]').each(function() {
            var data = updateCard($(this));
            if (!data.selected) {
                return;
            }

            qtyTotal += data.qty;
            subtotal += data.qty * data.price;
        });

        var tier = getTier(qtyTotal, tiers);
        var discount = tier.percent > 0 ? subtotal * (tier.percent / 100) : 0;
        var total = Math.max(0, subtotal - discount);
        var next = getNextTier(qtyTotal, tiers);

        $box.find('[data-sku_9015-total-qty]').text(qtyTotal);
        $box.find('[data-sku_9015-subtotal]').text(money(subtotal, currency));
        $box.find('[data-sku_9015-discount]').text('-' + money(discount, currency));
        $box.find('[data-sku_9015-total]').text(money(total, currency));

        $box.find('[data-sku_9015-step]').each(function() {
            var step = parseInt($(this).attr('data-sku_9015-step'), 10) || 0;
            $(this).toggleClass('is-active', qtyTotal >= step);
        });

        var $message = $box.find('[data-sku_9015-message]');
        if (qtyTotal < 1) {
            $message.html('Agrega extras para activar el descuento.');
        } else if (tier.percent > 0) {
            $message.html('Descuento activo: <strong>' + tier.percent + '% OFF</strong> en los extras seleccionados.');
        } else if (next) {
            var missing = next.min - qtyTotal;
            $message.html('Agrega <strong>' + missing + '</strong> pieza(s) más para activar <strong>' + next.percent + '% OFF</strong>.');
        } else {
            $message.html('Sigue agregando extras para revisar tu total.');
        }
    }

    function updateAll() {
        $('[data-sku_9015-combo]').each(function() {
            updateBox($(this));
        });
    }

    $(document).on('click', '[data-sku_9015-mode]', function() {
        var $button = $(this);
        var $box = $button.closest('[data-sku_9015-combo]');
        var mode = $button.attr('data-sku_9015-mode');

        $box.find('[data-sku_9015-mode]').removeClass('is-active');
        $button.addClass('is-active');

        if (mode === 'no') {
            $box.addClass('is-disabled');
            $box.find('[data-sku_9015-check]').prop('checked', false);
            $box.find('[data-sku_9015-qty]').val(0);
        } else {
            $box.removeClass('is-disabled');
        }

        updateBox($box);
    });

    $(document).on('click', '[data-sku_9015-plus]', function() {
        var $card = $(this).closest('[data-sku_9015-card]');
        var $box = $card.closest('[data-sku_9015-combo]');
        var $qty = $card.find('[data-sku_9015-qty]');
        var qty = parseInt($qty.val(), 10) || 0;
        var max = parseInt($qty.attr('max'), 10) || 99;

        qty = Math.min(max, qty + 1);
        $qty.val(qty);
        $card.find('[data-sku_9015-check]').prop('checked', qty > 0);
        $box.find('[data-sku_9015-mode="yes"]').trigger('click');
        updateBox($box);
    });

    $(document).on('click', '[data-sku_9015-minus]', function() {
        var $card = $(this).closest('[data-sku_9015-card]');
        var $box = $card.closest('[data-sku_9015-combo]');
        var $qty = $card.find('[data-sku_9015-qty]');
        var qty = parseInt($qty.val(), 10) || 0;

        qty = Math.max(0, qty - 1);
        $qty.val(qty);
        $card.find('[data-sku_9015-check]').prop('checked', qty > 0);
        updateBox($box);
    });

    $(document).on('change input', '[data-sku_9015-check], [data-sku_9015-qty], [data-sku_9015-variation]', function() {
        var $field = $(this);
        var $card = $field.closest('[data-sku_9015-card]');
        var $box = $field.closest('[data-sku_9015-combo]');
        var $qty = $card.find('[data-sku_9015-qty]');

        if ($field.is('[data-sku_9015-check]')) {
            if ($field.is(':checked') && (parseInt($qty.val(), 10) || 0) < 1) {
                $qty.val(1);
            }

            if (!$field.is(':checked')) {
                $qty.val(0);
            }
        }

        if ($field.is('[data-sku_9015-qty]')) {
            var qty = parseInt($qty.val(), 10) || 0;
            $card.find('[data-sku_9015-check]').prop('checked', qty > 0);
        }

        updateBox($box);
    });

    $(document).on('submit', 'form.cart', function() {
        var $form = $(this);
        var invalid = false;

        $form.find('[data-sku_9015-card].is-selected').each(function() {
            var $card = $(this);
            var $select = $card.find('[data-sku_9015-variation]');

            if ($select.length && !$select.val()) {
                invalid = true;
                $select.addClass('sku_9015-has-error');
            } else {
                $select.removeClass('sku_9015-has-error');
            }
        });

        if (invalid) {
            window.alert('Selecciona una opción para cada extra variable.');
            return false;
        }
    });

    $(function() {
        updateAll();
    });
})(jQuery);
