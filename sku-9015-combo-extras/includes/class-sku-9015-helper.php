<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SKU_9015_Helper {
    public static function get_maps() {
        $maps = get_option( SKU_9015_OPTION_MAPS, array() );

        if ( ! is_array( $maps ) ) {
            return array();
        }

        $normalized = array();

        foreach ( $maps as $map ) {
            $prepared = self::normalize_map( $map );
            if ( ! empty( $prepared['main_sku'] ) && ! empty( $prepared['extra_skus'] ) ) {
                $normalized[] = $prepared;
            }
        }

        return $normalized;
    }

    public static function save_maps( $maps ) {
        $clean = array();

        if ( ! is_array( $maps ) ) {
            $maps = array();
        }

        foreach ( $maps as $map ) {
            $prepared = self::normalize_map( $map );
            if ( ! empty( $prepared['main_sku'] ) && ! empty( $prepared['extra_skus'] ) ) {
                $clean[] = $prepared;
            }
        }

        update_option( SKU_9015_OPTION_MAPS, $clean, false );
        return $clean;
    }

    public static function normalize_map( $map ) {
        $enabled = true;
        if ( isset( $map['enabled'] ) ) {
            $enabled = wc_string_to_bool( $map['enabled'] );
        }

        $main_sku = isset( $map['main_sku'] ) ? self::normalize_sku( $map['main_sku'] ) : '';

        $extra_skus_raw = '';
        if ( isset( $map['extra_skus_raw'] ) ) {
            $extra_skus_raw = $map['extra_skus_raw'];
        } elseif ( isset( $map['extra_skus'] ) && is_array( $map['extra_skus'] ) ) {
            $extra_skus_raw = implode( ',', $map['extra_skus'] );
        } elseif ( isset( $map['extra_skus'] ) ) {
            $extra_skus_raw = $map['extra_skus'];
        }

        $extra_skus = self::parse_sku_list( $extra_skus_raw );

        $tiers_raw = '';
        if ( isset( $map['tiers_raw'] ) ) {
            $tiers_raw = $map['tiers_raw'];
        } elseif ( isset( $map['tiers'] ) && is_array( $map['tiers'] ) ) {
            $tiers_raw = self::tiers_to_text( $map['tiers'] );
        } elseif ( isset( $map['tiers'] ) ) {
            $tiers_raw = $map['tiers'];
        }

        $tiers = self::parse_tiers( $tiers_raw );

        if ( empty( $tiers ) ) {
            $tiers = array(
                2 => 10,
                4 => 15,
                6 => 20,
            );
        }

        $title = isset( $map['title'] ) && '' !== trim( $map['title'] ) ? sanitize_text_field( $map['title'] ) : 'Filamentos Esenciales';
        $description = isset( $map['description'] ) && '' !== trim( $map['description'] ) ? sanitize_text_field( $map['description'] ) : 'Agrega productos relacionados y desbloquea descuentos por cantidad.';
        $max_qty = isset( $map['max_qty'] ) ? absint( $map['max_qty'] ) : 99;
        $max_qty = $max_qty < 1 ? 99 : $max_qty;

        $prepared = array(
            'enabled'        => $enabled ? 'yes' : 'no',
            'main_sku'       => $main_sku,
            'extra_skus'     => $extra_skus,
            'extra_skus_raw' => implode( ', ', $extra_skus ),
            'title'          => $title,
            'description'    => $description,
            'tiers'          => $tiers,
            'tiers_raw'      => self::tiers_to_text( $tiers ),
            'max_qty'        => $max_qty,
        );

        $prepared['hash'] = self::map_hash( $prepared );

        return $prepared;
    }

    public static function normalize_sku( $sku ) {
        $sku = is_scalar( $sku ) ? (string) $sku : '';
        $sku = wp_strip_all_tags( $sku );
        $sku = preg_replace( '/\s+/', ' ', $sku );
        return trim( $sku );
    }

    public static function parse_sku_list( $raw ) {
        if ( is_array( $raw ) ) {
            $parts = $raw;
        } else {
            $raw = is_scalar( $raw ) ? (string) $raw : '';
            $raw = str_replace( array( "\r\n", "\r", "\n", ';', '|' ), ',', $raw );
            $parts = explode( ',', $raw );
        }

        $skus = array();
        foreach ( $parts as $sku ) {
            $sku = self::normalize_sku( $sku );
            if ( '' !== $sku ) {
                $key = strtolower( $sku );
                $skus[ $key ] = $sku;
            }
        }

        return array_values( $skus );
    }

    public static function parse_tiers( $raw ) {
        $tiers = array();

        if ( is_array( $raw ) ) {
            foreach ( $raw as $min => $percent ) {
                $min = absint( $min );
                $percent = floatval( $percent );
                if ( $min > 0 && $percent > 0 ) {
                    $tiers[ $min ] = $percent;
                }
            }
        } else {
            $raw = is_scalar( $raw ) ? (string) $raw : '';
            $raw = str_replace( array( "\r\n", "\r", "\n", ';' ), ',', $raw );
            $pairs = explode( ',', $raw );

            foreach ( $pairs as $pair ) {
                $pair = trim( $pair );
                if ( '' === $pair ) {
                    continue;
                }

                if ( false !== strpos( $pair, ':' ) ) {
                    list( $min, $percent ) = array_map( 'trim', explode( ':', $pair, 2 ) );
                } elseif ( false !== strpos( $pair, '=' ) ) {
                    list( $min, $percent ) = array_map( 'trim', explode( '=', $pair, 2 ) );
                } else {
                    continue;
                }

                $min = absint( $min );
                $percent = floatval( str_replace( '%', '', $percent ) );

                if ( $min > 0 && $percent > 0 ) {
                    $tiers[ $min ] = $percent;
                }
            }
        }

        ksort( $tiers, SORT_NUMERIC );
        return $tiers;
    }

    public static function tiers_to_text( $tiers ) {
        $out = array();

        if ( ! is_array( $tiers ) ) {
            return '';
        }

        ksort( $tiers, SORT_NUMERIC );
        foreach ( $tiers as $min => $percent ) {
            $min = absint( $min );
            $percent = floatval( $percent );
            if ( $min > 0 && $percent > 0 ) {
                $out[] = $min . ':' . self::clean_number( $percent );
            }
        }

        return implode( ', ', $out );
    }

    public static function clean_number( $number ) {
        $number = (float) $number;
        if ( floor( $number ) === $number ) {
            return (string) absint( $number );
        }
        return rtrim( rtrim( number_format( $number, 4, '.', '' ), '0' ), '.' );
    }

    public static function map_hash( $map ) {
        $base = array(
            'main_sku'   => isset( $map['main_sku'] ) ? strtolower( $map['main_sku'] ) : '',
            'extra_skus' => isset( $map['extra_skus'] ) ? array_map( 'strtolower', (array) $map['extra_skus'] ) : array(),
            'tiers'      => isset( $map['tiers'] ) ? $map['tiers'] : array(),
        );

        return substr( md5( wp_json_encode( $base ) ), 0, 12 );
    }

    public static function find_map_for_product( $product ) {
        if ( ! $product instanceof WC_Product ) {
            return false;
        }

        $sku = self::normalize_sku( $product->get_sku() );

        if ( '' === $sku ) {
            return false;
        }

        foreach ( self::get_maps() as $map ) {
            if ( 'yes' !== $map['enabled'] ) {
                continue;
            }

            if ( strtolower( $map['main_sku'] ) === strtolower( $sku ) ) {
                return $map;
            }
        }

        return false;
    }

    public static function find_map_by_hash( $hash ) {
        $hash = sanitize_text_field( $hash );

        foreach ( self::get_maps() as $map ) {
            if ( isset( $map['hash'] ) && $map['hash'] === $hash ) {
                return $map;
            }
        }

        return false;
    }

    public static function get_discount_percent_for_qty( $qty, $tiers ) {
        $qty = absint( $qty );
        $tiers = self::parse_tiers( $tiers );
        $percent = 0;

        foreach ( $tiers as $min => $discount ) {
            if ( $qty >= absint( $min ) ) {
                $percent = floatval( $discount );
            }
        }

        return $percent;
    }

    public static function get_product_by_sku( $sku ) {
        $sku = self::normalize_sku( $sku );

        if ( '' === $sku ) {
            return false;
        }

        $product_id = wc_get_product_id_by_sku( $sku );
        if ( ! $product_id ) {
            return false;
        }

        $product = wc_get_product( $product_id );

        return $product instanceof WC_Product ? $product : false;
    }

    public static function product_is_available_for_combo( $product ) {
        if ( ! $product instanceof WC_Product ) {
            return false;
        }

        if ( $product->is_type( 'variable' ) ) {
            return ! empty( $product->get_available_variations() );
        }

        return $product->is_purchasable() && $product->is_in_stock();
    }

    public static function get_extra_products_for_map( $map ) {
        $products = array();

        if ( empty( $map['extra_skus'] ) || ! is_array( $map['extra_skus'] ) ) {
            return $products;
        }

        foreach ( $map['extra_skus'] as $sku ) {
            $product = self::get_product_by_sku( $sku );
            if ( ! $product || ! self::product_is_available_for_combo( $product ) ) {
                continue;
            }

            $products[] = $product;
        }

        return $products;
    }

    public static function get_display_price( $product ) {
        if ( ! $product instanceof WC_Product ) {
            return 0;
        }

        if ( $product->is_type( 'variable' ) ) {
            $price = $product->get_variation_price( 'min', true );
        } else {
            $price = $product->get_price();
        }

        return (float) wc_get_price_to_display( $product, array( 'price' => $price ) );
    }

    public static function get_raw_price( $product ) {
        if ( ! $product instanceof WC_Product ) {
            return 0;
        }

        if ( $product->is_type( 'variable' ) ) {
            $price = $product->get_variation_price( 'min', false );
        } else {
            $price = $product->get_price();
        }

        return (float) $price;
    }

    public static function get_product_name_for_display( $product ) {
        if ( ! $product instanceof WC_Product ) {
            return '';
        }

        if ( $product->is_type( 'variation' ) ) {
            $parent = wc_get_product( $product->get_parent_id() );
            $name = $parent ? $parent->get_name() : $product->get_name();
            $variation = wc_get_formatted_variation( $product, true, false, true );
            return trim( $name . ' - ' . wp_strip_all_tags( $variation ) );
        }

        return $product->get_name();
    }

    public static function get_product_image_html( $product, $size = 'woocommerce_thumbnail' ) {
        if ( ! $product instanceof WC_Product ) {
            return '';
        }

        $image_id = $product->get_image_id();

        if ( ! $image_id && $product->is_type( 'variation' ) ) {
            $parent = wc_get_product( $product->get_parent_id() );
            if ( $parent ) {
                $image_id = $parent->get_image_id();
            }
        }

        if ( $image_id ) {
            return wp_get_attachment_image( $image_id, $size );
        }

        return wc_placeholder_img( $size );
    }

    public static function get_cart_add_data_from_sku( $sku, $variation_id = 0 ) {
        $product = self::get_product_by_sku( $sku );

        if ( ! $product ) {
            return false;
        }

        if ( $product->is_type( 'variation' ) ) {
            return array(
                'product_id'   => $product->get_parent_id(),
                'variation_id' => $product->get_id(),
                'variation'    => $product->get_variation_attributes(),
                'product'      => $product,
            );
        }

        if ( $product->is_type( 'variable' ) ) {
            $variation_id = absint( $variation_id );
            if ( ! $variation_id ) {
                return false;
            }

            $variation = wc_get_product( $variation_id );
            if ( ! $variation || ! $variation->is_type( 'variation' ) || $variation->get_parent_id() !== $product->get_id() ) {
                return false;
            }

            return array(
                'product_id'   => $product->get_id(),
                'variation_id' => $variation->get_id(),
                'variation'    => $variation->get_variation_attributes(),
                'product'      => $variation,
            );
        }

        return array(
            'product_id'   => $product->get_id(),
            'variation_id' => 0,
            'variation'    => array(),
            'product'      => $product,
        );
    }

    public static function admin_product_search( $term, $limit = 20 ) {
        $term = sanitize_text_field( wp_unslash( $term ) );
        $term = trim( $term );
        $limit = max( 1, min( 50, absint( $limit ) ) );

        if ( strlen( $term ) < 2 ) {
            return array();
        }

        $ids = array();

        if ( is_numeric( $term ) ) {
            $maybe = wc_get_product( absint( $term ) );
            if ( $maybe ) {
                $ids[] = $maybe->get_id();
            }
        }

        $sku_query = new WP_Query(
            array(
                'post_type'      => array( 'product', 'product_variation' ),
                'post_status'    => array( 'publish', 'private' ),
                'posts_per_page' => $limit,
                'fields'         => 'ids',
                'meta_query'     => array(
                    array(
                        'key'     => '_sku',
                        'value'   => $term,
                        'compare' => 'LIKE',
                    ),
                ),
            )
        );

        if ( ! empty( $sku_query->posts ) ) {
            $ids = array_merge( $ids, $sku_query->posts );
        }

        $title_query = new WP_Query(
            array(
                'post_type'      => array( 'product', 'product_variation' ),
                'post_status'    => array( 'publish', 'private' ),
                'posts_per_page' => $limit,
                'fields'         => 'ids',
                's'              => $term,
            )
        );

        if ( ! empty( $title_query->posts ) ) {
            $ids = array_merge( $ids, $title_query->posts );
        }

        $ids = array_values( array_unique( array_map( 'absint', $ids ) ) );
        $ids = array_slice( $ids, 0, $limit );

        $results = array();
        foreach ( $ids as $id ) {
            $product = wc_get_product( $id );
            if ( ! $product ) {
                continue;
            }

            $sku = $product->get_sku();
            if ( '' === $sku ) {
                continue;
            }

            $results[] = array(
                'id'     => $product->get_id(),
                'sku'    => $sku,
                'name'   => self::get_product_name_for_display( $product ),
                'type'   => $product->get_type(),
                'price'  => wp_strip_all_tags( wc_price( self::get_display_price( $product ) ) ),
                'status' => self::product_is_available_for_combo( $product ) ? 'ok' : 'unavailable',
                'label'  => sprintf( '%s — %s', $sku, self::get_product_name_for_display( $product ) ),
            );
        }

        return $results;
    }
}
