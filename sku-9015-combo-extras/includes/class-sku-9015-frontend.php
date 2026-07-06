<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SKU_9015_Frontend {
    private static $adding_addons = false;
    private static $removing_addons = false;

    public static function init() {
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
        add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'render_combo' ), 18 );
        add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'mark_parent_cart_item' ), 10, 4 );
        add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_extras' ), 10, 6 );
        add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'add_extras_to_cart' ), 20, 6 );
        add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_discounts' ), 20 );
        add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'show_cart_item_data' ), 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'save_order_item_data' ), 10, 4 );
        add_action( 'woocommerce_cart_item_removed', array( __CLASS__, 'remove_child_addons' ), 10, 2 );
    }

    public static function assets() {
        if ( ! is_product() ) {
            return;
        }

        wp_enqueue_style(
            'sku_9015-frontend',
            SKU_9015_URL . 'assets/css/frontend.css',
            array(),
            SKU_9015_VERSION
        );

        wp_enqueue_script(
            'sku_9015-frontend',
            SKU_9015_URL . 'assets/js/frontend.js',
            array( 'jquery' ),
            SKU_9015_VERSION,
            true
        );

        wp_localize_script(
            'sku_9015-frontend',
            'SKU9015Frontend',
            array(
                'currency' => get_woocommerce_currency(),
                'i18n'     => array(
                    'add'        => 'Agregar',
                    'selected'   => 'seleccionados',
                    'selectText' => 'Selecciona una opción',
                ),
            )
        );
    }

    private static function current_product() {
        global $product;

        if ( $product instanceof WC_Product ) {
            return $product;
        }

        $product_id = get_the_ID();
        if ( $product_id ) {
            return wc_get_product( $product_id );
        }

        return false;
    }

    public static function render_combo() {
        $product = self::current_product();
        if ( ! $product ) {
            return;
        }

        $map = SKU_9015_Helper::find_map_for_product( $product );
        if ( ! $map ) {
            return;
        }

        $extras = SKU_9015_Helper::get_extra_products_for_map( $map );
        if ( empty( $extras ) ) {
            return;
        }

        $tiers = SKU_9015_Helper::parse_tiers( $map['tiers'] );
        $max_qty = ! empty( $map['max_qty'] ) ? absint( $map['max_qty'] ) : 99;

        wp_nonce_field( 'sku_9015_add_extras', 'sku_9015_nonce' );
        ?>
        <input type="hidden" name="sku_9015_map_hash" value="<?php echo esc_attr( $map['hash'] ); ?>">

        <div class="sku_9015-combo" data-sku_9015-combo data-currency="<?php echo esc_attr( get_woocommerce_currency() ); ?>" data-tiers="<?php echo esc_attr( wp_json_encode( $tiers ) ); ?>">
            <div class="sku_9015-combo__head">
                <div>
                    <h3><?php echo esc_html( $map['title'] ); ?> <span class="sku_9015-combo__info">?</span></h3>
                    <p><?php echo esc_html( $map['description'] ); ?></p>
                </div>
            </div>

            <div class="sku_9015-combo__switch" role="group" aria-label="Selector de extras">
                <button type="button" class="sku_9015-combo__switch-btn is-active" data-sku_9015-mode="yes">
                    Agrega extras
                    <small>Desbloquea descuentos</small>
                </button>
                <button type="button" class="sku_9015-combo__switch-btn" data-sku_9015-mode="no">
                    Sin extras
                    <small>Solo el producto principal</small>
                </button>
            </div>

            <div class="sku_9015-combo__steps">
                <?php foreach ( $tiers as $min_qty => $discount ) : ?>
                    <div class="sku_9015-combo__step" data-sku_9015-step="<?php echo esc_attr( $min_qty ); ?>">
                        <span class="sku_9015-combo__dot">✓</span>
                        <strong><?php echo esc_html( SKU_9015_Helper::clean_number( $discount ) ); ?>% OFF</strong>
                        <small><?php echo esc_html( absint( $min_qty ) ); ?>+ pzas</small>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="sku_9015-combo__summary">
                <div>
                    <span>Piezas</span>
                    <strong data-sku_9015-total-qty>0</strong>
                </div>
                <div>
                    <span>Subtotal</span>
                    <strong data-sku_9015-subtotal>$0.00</strong>
                </div>
                <div>
                    <span>Descuento</span>
                    <strong data-sku_9015-discount>$0.00</strong>
                </div>
                <div>
                    <span>Total extras</span>
                    <strong data-sku_9015-total>$0.00</strong>
                </div>
            </div>

            <div class="sku_9015-combo__message" data-sku_9015-message>
                Agrega extras para activar el descuento.
            </div>

            <div class="sku_9015-combo__list">
                <?php foreach ( $extras as $index => $extra_product ) : ?>
                    <?php self::render_extra_card( $index, $extra_product, $max_qty ); ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    private static function render_extra_card( $index, $product, $max_qty ) {
        $sku = $product->get_sku();
        $display_price = SKU_9015_Helper::get_display_price( $product );
        $name = SKU_9015_Helper::get_product_name_for_display( $product );
        $is_variable = $product->is_type( 'variable' );
        $input_name = 'sku_9015_items[' . absint( $index ) . ']';
        ?>
        <div class="sku_9015-combo__card" data-sku_9015-card data-price="<?php echo esc_attr( $display_price ); ?>">
            <label class="sku_9015-combo__check" aria-label="Seleccionar extra">
                <input type="checkbox" name="<?php echo esc_attr( $input_name ); ?>[selected]" value="1" data-sku_9015-check>
                <span>✓</span>
            </label>

            <input type="hidden" name="<?php echo esc_attr( $input_name ); ?>[sku]" value="<?php echo esc_attr( $sku ); ?>">

            <div class="sku_9015-combo__product">
                <div class="sku_9015-combo__image">
                    <?php echo wp_kses_post( SKU_9015_Helper::get_product_image_html( $product ) ); ?>
                </div>

                <div class="sku_9015-combo__data">
                    <strong><?php echo esc_html( $name ); ?></strong>
                    <small>SKU: <?php echo esc_html( $sku ); ?></small>
                    <div class="sku_9015-combo__price">
                        <span data-sku_9015-card-price><?php echo wp_kses_post( wc_price( $display_price ) ); ?></span>
                    </div>
                </div>
            </div>

            <?php if ( $is_variable ) : ?>
                <?php self::render_variation_select( $input_name, $product ); ?>
            <?php endif; ?>

            <div class="sku_9015-combo__qty">
                <button type="button" class="sku_9015-combo__minus" data-sku_9015-minus aria-label="Restar">-</button>
                <input type="number" name="<?php echo esc_attr( $input_name ); ?>[qty]" value="0" min="0" max="<?php echo esc_attr( $max_qty ); ?>" step="1" data-sku_9015-qty>
                <button type="button" class="sku_9015-combo__plus" data-sku_9015-plus aria-label="Sumar">+</button>
                <small data-sku_9015-selected-text>0 seleccionados</small>
            </div>
        </div>
        <?php
    }

    private static function render_variation_select( $input_name, $product ) {
        $available = $product->get_available_variations();
        ?>
        <select class="sku_9015-combo__select" name="<?php echo esc_attr( $input_name ); ?>[variation_id]" data-sku_9015-variation>
            <option value="">Selecciona una opción</option>
            <?php foreach ( $available as $variation_data ) : ?>
                <?php
                $variation_id = absint( $variation_data['variation_id'] );
                $variation = wc_get_product( $variation_id );

                if ( ! $variation || ! $variation->is_purchasable() || ! $variation->is_in_stock() ) {
                    continue;
                }

                $label = wc_get_formatted_variation( $variation, true, false, true );
                $price = SKU_9015_Helper::get_display_price( $variation );
                $variation_sku = $variation->get_sku();
                ?>
                <option value="<?php echo esc_attr( $variation_id ); ?>" data-price="<?php echo esc_attr( $price ); ?>">
                    <?php echo esc_html( wp_strip_all_tags( $label ) ); ?><?php echo $variation_sku ? ' — SKU: ' . esc_html( $variation_sku ) : ''; ?> — <?php echo esc_html( wp_strip_all_tags( wc_price( $price ) ) ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    private static function get_posted_combo() {
        if ( self::$adding_addons ) {
            return false;
        }

        if ( empty( $_POST['sku_9015_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sku_9015_nonce'] ) ), 'sku_9015_add_extras' ) ) {
            return false;
        }

        $map_hash = isset( $_POST['sku_9015_map_hash'] ) ? sanitize_text_field( wp_unslash( $_POST['sku_9015_map_hash'] ) ) : '';
        if ( '' === $map_hash ) {
            return false;
        }

        $map = SKU_9015_Helper::find_map_by_hash( $map_hash );
        if ( ! $map || 'yes' !== $map['enabled'] ) {
            return false;
        }

        $posted_items = isset( $_POST['sku_9015_items'] ) && is_array( $_POST['sku_9015_items'] ) ? wp_unslash( $_POST['sku_9015_items'] ) : array();
        $items = array();
        $allowed = array_map( 'strtolower', $map['extra_skus'] );

        foreach ( $posted_items as $posted ) {
            if ( ! is_array( $posted ) ) {
                continue;
            }

            $sku = isset( $posted['sku'] ) ? SKU_9015_Helper::normalize_sku( $posted['sku'] ) : '';
            $qty = isset( $posted['qty'] ) ? absint( $posted['qty'] ) : 0;
            $selected = ! empty( $posted['selected'] ) || $qty > 0;
            $variation_id = isset( $posted['variation_id'] ) ? absint( $posted['variation_id'] ) : 0;

            if ( ! $selected || $qty < 1 || '' === $sku ) {
                continue;
            }

            if ( ! in_array( strtolower( $sku ), $allowed, true ) ) {
                continue;
            }

            $items[] = array(
                'sku'          => $sku,
                'qty'          => $qty,
                'variation_id' => $variation_id,
            );
        }

        return array(
            'map'   => $map,
            'items' => $items,
        );
    }

    public static function mark_parent_cart_item( $cart_item_data, $product_id, $variation_id, $quantity ) {
        $combo = self::get_posted_combo();

        if ( $combo && ! empty( $combo['items'] ) ) {
            $cart_item_data['_sku_9015_parent_has_addons'] = true;
            $cart_item_data['_sku_9015_map_hash'] = $combo['map']['hash'];
            $cart_item_data['_sku_9015_unique'] = md5( microtime( true ) . wp_rand() );
        }

        return $cart_item_data;
    }

    public static function validate_extras( $passed, $product_id, $quantity, $variation_id = 0, $variations = array(), $cart_item_data = array() ) {
        if ( self::$adding_addons ) {
            return $passed;
        }

        $main_product = wc_get_product( $product_id );
        if ( ! $main_product ) {
            return $passed;
        }

        $current_map = SKU_9015_Helper::find_map_for_product( $main_product );
        if ( ! $current_map ) {
            return $passed;
        }

        $combo = self::get_posted_combo();
        if ( ! $combo || empty( $combo['items'] ) ) {
            return $passed;
        }

        if ( $combo['map']['hash'] !== $current_map['hash'] ) {
            wc_add_notice( 'La relación de extras no corresponde a este producto.', 'error' );
            return false;
        }

        foreach ( $combo['items'] as $item ) {
            $data = SKU_9015_Helper::get_cart_add_data_from_sku( $item['sku'], $item['variation_id'] );

            if ( ! $data ) {
                wc_add_notice( 'Uno de los extras seleccionados no es válido o requiere seleccionar una variación.', 'error' );
                return false;
            }

            $extra_product = $data['product'];

            if ( ! $extra_product || ! $extra_product->is_purchasable() || ! $extra_product->is_in_stock() ) {
                wc_add_notice( 'Uno de los extras seleccionados no está disponible.', 'error' );
                return false;
            }

            if ( method_exists( $extra_product, 'has_enough_stock' ) && ! $extra_product->has_enough_stock( $item['qty'] ) ) {
                wc_add_notice( 'No hay suficiente stock para uno de los extras seleccionados.', 'error' );
                return false;
            }
        }

        return $passed;
    }

    public static function add_extras_to_cart( $cart_item_key, $product_id, $quantity, $variation_id = 0, $variation = array(), $cart_item_data = array() ) {
        if ( self::$adding_addons ) {
            return;
        }

        $combo = self::get_posted_combo();
        if ( ! $combo || empty( $combo['items'] ) ) {
            return;
        }

        $main_product = wc_get_product( $product_id );
        if ( ! $main_product ) {
            return;
        }

        $current_map = SKU_9015_Helper::find_map_for_product( $main_product );
        if ( ! $current_map || $current_map['hash'] !== $combo['map']['hash'] ) {
            return;
        }

        self::$adding_addons = true;

        foreach ( $combo['items'] as $item ) {
            $data = SKU_9015_Helper::get_cart_add_data_from_sku( $item['sku'], $item['variation_id'] );
            if ( ! $data ) {
                continue;
            }

            $price_product = $data['product'];
            $original_price = (float) $price_product->get_price();

            WC()->cart->add_to_cart(
                $data['product_id'],
                $item['qty'],
                $data['variation_id'],
                $data['variation'],
                array(
                    '_sku_9015_combo_addon'       => true,
                    '_sku_9015_parent_key'        => $cart_item_key,
                    '_sku_9015_parent_product_id' => absint( $product_id ),
                    '_sku_9015_main_sku'          => $combo['map']['main_sku'],
                    '_sku_9015_map_hash'          => $combo['map']['hash'],
                    '_sku_9015_tiers'             => $combo['map']['tiers'],
                    '_sku_9015_title'             => $combo['map']['title'],
                    '_sku_9015_original_price'    => $original_price,
                    '_sku_9015_unique'            => md5( microtime( true ) . wp_rand() . $item['sku'] ),
                )
            );
        }

        self::$adding_addons = false;
    }

    public static function apply_discounts( $cart ) {
        if ( is_admin() && ! wp_doing_ajax() ) {
            return;
        }

        if ( ! $cart || empty( $cart->cart_contents ) ) {
            return;
        }

        $groups = array();

        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( empty( $cart_item['_sku_9015_combo_addon'] ) ) {
                continue;
            }

            $parent_key = isset( $cart_item['_sku_9015_parent_key'] ) ? $cart_item['_sku_9015_parent_key'] : 'orphan';
            $hash = isset( $cart_item['_sku_9015_map_hash'] ) ? $cart_item['_sku_9015_map_hash'] : 'nohash';
            $group_key = $parent_key . '|' . $hash;

            if ( ! isset( $groups[ $group_key ] ) ) {
                $groups[ $group_key ] = array(
                    'qty'   => 0,
                    'tiers' => isset( $cart_item['_sku_9015_tiers'] ) ? $cart_item['_sku_9015_tiers'] : array(),
                    'items' => array(),
                );
            }

            $groups[ $group_key ]['qty'] += absint( $cart_item['quantity'] );
            $groups[ $group_key ]['items'][] = $cart_item_key;
        }

        foreach ( $groups as $group ) {
            $percent = SKU_9015_Helper::get_discount_percent_for_qty( $group['qty'], $group['tiers'] );

            foreach ( $group['items'] as $cart_item_key ) {
                if ( empty( $cart->cart_contents[ $cart_item_key ] ) ) {
                    continue;
                }

                $cart_item = $cart->cart_contents[ $cart_item_key ];
                $original_price = isset( $cart_item['_sku_9015_original_price'] ) ? (float) $cart_item['_sku_9015_original_price'] : (float) $cart_item['data']->get_price();
                $new_price = $original_price;

                if ( $percent > 0 ) {
                    $new_price = $original_price - ( $original_price * ( $percent / 100 ) );
                }

                $cart->cart_contents[ $cart_item_key ]['data']->set_price( max( 0, $new_price ) );
                $cart->cart_contents[ $cart_item_key ]['_sku_9015_active_discount'] = $percent;
            }
        }
    }

    public static function show_cart_item_data( $item_data, $cart_item ) {
        if ( empty( $cart_item['_sku_9015_combo_addon'] ) ) {
            return $item_data;
        }

        $item_data[] = array(
            'key'   => 'Combo',
            'value' => ! empty( $cart_item['_sku_9015_title'] ) ? wc_clean( $cart_item['_sku_9015_title'] ) : 'Extra relacionado',
        );

        if ( ! empty( $cart_item['_sku_9015_main_sku'] ) ) {
            $item_data[] = array(
                'key'   => 'SKU principal',
                'value' => wc_clean( $cart_item['_sku_9015_main_sku'] ),
            );
        }

        if ( isset( $cart_item['_sku_9015_active_discount'] ) && (float) $cart_item['_sku_9015_active_discount'] > 0 ) {
            $item_data[] = array(
                'key'   => 'Descuento aplicado',
                'value' => SKU_9015_Helper::clean_number( $cart_item['_sku_9015_active_discount'] ) . '% OFF',
            );
        }

        return $item_data;
    }

    public static function save_order_item_data( $item, $cart_item_key, $values, $order ) {
        if ( empty( $values['_sku_9015_combo_addon'] ) ) {
            return;
        }

        $item->add_meta_data( 'Combo', ! empty( $values['_sku_9015_title'] ) ? $values['_sku_9015_title'] : 'Extra relacionado', true );

        if ( ! empty( $values['_sku_9015_main_sku'] ) ) {
            $item->add_meta_data( 'SKU principal', $values['_sku_9015_main_sku'], true );
        }

        if ( isset( $values['_sku_9015_active_discount'] ) && (float) $values['_sku_9015_active_discount'] > 0 ) {
            $item->add_meta_data( 'Descuento aplicado', SKU_9015_Helper::clean_number( $values['_sku_9015_active_discount'] ) . '% OFF', true );
        }
    }

    public static function remove_child_addons( $cart_item_key, $cart ) {
        if ( self::$removing_addons ) {
            return;
        }

        if ( empty( $cart ) || empty( $cart->cart_contents ) ) {
            return;
        }

        self::$removing_addons = true;

        foreach ( $cart->get_cart() as $child_key => $cart_item ) {
            if ( ! empty( $cart_item['_sku_9015_parent_key'] ) && $cart_item['_sku_9015_parent_key'] === $cart_item_key ) {
                $cart->remove_cart_item( $child_key );
            }
        }

        self::$removing_addons = false;
    }
}
