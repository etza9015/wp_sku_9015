<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SKU_9015_Admin {
    private static $page_hook = '';

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ), 80 );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
        add_action( 'admin_post_sku_9015_save_maps', array( __CLASS__, 'save_maps' ) );
        add_action( 'wp_ajax_sku_9015_product_search', array( __CLASS__, 'ajax_product_search' ) );
    }

    public static function menu() {
        self::$page_hook = add_submenu_page(
            'woocommerce',
            'Combo por SKU',
            'Combo por SKU',
            'manage_woocommerce',
            'sku-9015-combo-extras',
            array( __CLASS__, 'render_page' )
        );
    }

    public static function assets( $hook ) {
        if ( $hook !== self::$page_hook ) {
            return;
        }

        wp_enqueue_style(
            'sku_9015-admin',
            SKU_9015_URL . 'assets/css/admin.css',
            array(),
            SKU_9015_VERSION
        );

        wp_enqueue_script(
            'sku_9015-admin',
            SKU_9015_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            SKU_9015_VERSION,
            true
        );

        wp_localize_script(
            'sku_9015-admin',
            'SKU9015Admin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'sku_9015_admin_search' ),
                'i18n'    => array(
                    'searching'     => 'Buscando...',
                    'noResults'     => 'Sin resultados.',
                    'remove'        => 'Quitar',
                    'confirmRemove' => '¿Eliminar esta relación?',
                    'emptyExtras'   => 'Sin extras agregados todavía.',
                    'open'          => 'Abrir',
                    'close'         => 'Cerrar',
                    'newRelation'   => 'Nueva relación',
                ),
            )
        );
    }

    public static function ajax_product_search() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => 'Sin permisos.' ), 403 );
        }

        check_ajax_referer( 'sku_9015_admin_search', 'nonce' );

        $term    = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
        $results = SKU_9015_Helper::admin_product_search( $term, 20 );

        wp_send_json_success( array( 'results' => $results ) );
    }

    public static function save_maps() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'No tienes permisos suficientes.', 'sku-9015-combo-extras' ) );
        }

        check_admin_referer( 'sku_9015_save_maps', 'sku_9015_nonce' );

        $posted = isset( $_POST['sku_9015_maps'] ) && is_array( $_POST['sku_9015_maps'] ) ? wp_unslash( $_POST['sku_9015_maps'] ) : array();
        $maps   = array();

        foreach ( $posted as $map ) {
            if ( ! is_array( $map ) ) {
                continue;
            }

            $maps[] = array(
                'enabled'        => isset( $map['enabled'] ) ? sanitize_text_field( $map['enabled'] ) : 'no',
                'main_sku'       => isset( $map['main_sku'] ) ? sanitize_text_field( $map['main_sku'] ) : '',
                'extra_skus_raw' => isset( $map['extra_skus_raw'] ) ? sanitize_textarea_field( $map['extra_skus_raw'] ) : '',
                'title'          => isset( $map['title'] ) ? sanitize_text_field( $map['title'] ) : '',
                'description'    => isset( $map['description'] ) ? sanitize_text_field( $map['description'] ) : '',
                'tiers_raw'      => isset( $map['tiers_raw'] ) ? sanitize_text_field( $map['tiers_raw'] ) : '',
                'max_qty'        => isset( $map['max_qty'] ) ? absint( $map['max_qty'] ) : 99,
            );
        }

        SKU_9015_Helper::save_maps( $maps );

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page'           => 'sku-9015-combo-extras',
                    'sku_9015_saved' => '1',
                ),
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    public static function render_page() {
        $maps = SKU_9015_Helper::get_maps();

        if ( empty( $maps ) ) {
            $maps = array(
                SKU_9015_Helper::normalize_map(
                    array(
                        'enabled'        => 'yes',
                        'main_sku'       => '',
                        'extra_skus_raw' => '',
                        'title'          => 'Filamentos Esenciales',
                        'description'    => 'Agrega filamentos compatibles y desbloquea descuentos por cantidad.',
                        'tiers_raw'      => '2:10, 4:15, 6:20',
                        'max_qty'        => 99,
                    )
                ),
            );
        }

        $stats = self::get_stats( $maps );
        ?>
        <div class="wrap sku_9015-admin-wrap">
            <div class="sku_9015-page-head">
                <div>
                    <h1>Combo por SKU para WooCommerce</h1>
                    <p>Relaciona un SKU principal con SKUs de extras. El frontend mostrará el bloque visual y aplicará descuentos por cantidad.</p>
                </div>
                <div class="sku_9015-version">v<?php echo esc_html( SKU_9015_VERSION ); ?></div>
            </div>

            <?php if ( isset( $_GET['sku_9015_saved'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>Relaciones guardadas correctamente.</p></div>
            <?php endif; ?>

            <div class="sku_9015-stats" aria-label="Resumen de relaciones">
                <div><strong><?php echo esc_html( $stats['total'] ); ?></strong><span>Relaciones</span></div>
                <div><strong><?php echo esc_html( $stats['active'] ); ?></strong><span>Activas</span></div>
                <div><strong><?php echo esc_html( $stats['extras'] ); ?></strong><span>Extras enlazados</span></div>
            </div>

            <div class="sku_9015-admin-intro">
                <p><strong>Mapa principal:</strong> SKU del producto principal → SKUs de extras relacionados.</p>
                <p>Ejemplo: <code>BAMBU-H2D</code> como SKU principal y <code>PLA-CIAN, PLA-VERDE, PLA-NEGRO</code> como extras.</p>
            </div>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sku_9015-form">
                <input type="hidden" name="action" value="sku_9015_save_maps">
                <?php wp_nonce_field( 'sku_9015_save_maps', 'sku_9015_nonce' ); ?>

                <div class="sku_9015-toolbar">
                    <div class="sku_9015-toolbar__left">
                        <button type="button" class="button button-secondary" id="sku_9015-add-map">+ Agregar relación</button>
                        <button type="button" class="button" id="sku_9015-open-all">Abrir todo</button>
                        <button type="button" class="button" id="sku_9015-close-all">Cerrar todo</button>
                    </div>
                    <button type="submit" class="button button-primary button-hero">Guardar relaciones</button>
                </div>

                <div class="sku_9015-table-head" aria-hidden="true">
                    <span>Relación</span>
                    <span>SKU principal</span>
                    <span>Extras</span>
                    <span>Descuentos</span>
                    <span>Estado</span>
                </div>

                <div id="sku_9015-maps" class="sku_9015-maps">
                    <?php foreach ( $maps as $index => $map ) : ?>
                        <?php self::render_map_card( $index, $map, 0 === absint( $index ) ); ?>
                    <?php endforeach; ?>
                </div>

                <div class="sku_9015-toolbar sku_9015-toolbar-bottom">
                    <button type="button" class="button button-secondary" id="sku_9015-add-map-bottom">+ Agregar relación</button>
                    <button type="submit" class="button button-primary button-hero">Guardar relaciones</button>
                </div>
            </form>

            <template id="sku_9015-map-template">
                <?php self::render_map_card( '__INDEX__', SKU_9015_Helper::normalize_map( array(
                    'enabled'        => 'yes',
                    'main_sku'       => '',
                    'extra_skus_raw' => '',
                    'title'          => 'Filamentos Esenciales',
                    'description'    => 'Agrega productos relacionados y desbloquea descuentos por cantidad.',
                    'tiers_raw'      => '2:10, 4:15, 6:20',
                    'max_qty'        => 99,
                ) ), true ); ?>
            </template>
        </div>
        <?php
    }

    private static function get_stats( $maps ) {
        $stats = array(
            'total'  => is_array( $maps ) ? count( $maps ) : 0,
            'active' => 0,
            'extras' => 0,
        );

        foreach ( (array) $maps as $map ) {
            if ( isset( $map['enabled'] ) && 'yes' === $map['enabled'] ) {
                $stats['active']++;
            }
            if ( ! empty( $map['extra_skus'] ) && is_array( $map['extra_skus'] ) ) {
                $stats['extras'] += count( $map['extra_skus'] );
            }
        }

        return $stats;
    }

    private static function product_payload_by_sku( $sku ) {
        $product = SKU_9015_Helper::get_product_by_sku( $sku );

        if ( ! $product ) {
            return array(
                'sku'    => $sku,
                'name'   => 'Producto no encontrado',
                'type'   => '—',
                'price'  => '—',
                'status' => 'missing',
            );
        }

        return array(
            'id'     => $product->get_id(),
            'sku'    => $product->get_sku(),
            'name'   => SKU_9015_Helper::get_product_name_for_display( $product ),
            'type'   => $product->get_type(),
            'price'  => wp_strip_all_tags( wc_price( SKU_9015_Helper::get_display_price( $product ) ) ),
            'status' => SKU_9015_Helper::product_is_available_for_combo( $product ) ? 'ok' : 'unavailable',
        );
    }

    private static function known_products_for_map( $map ) {
        $known = array();

        if ( ! empty( $map['main_sku'] ) ) {
            $main = self::product_payload_by_sku( $map['main_sku'] );
            $known[ strtolower( $main['sku'] ) ] = $main;
        }

        foreach ( (array) $map['extra_skus'] as $sku ) {
            $payload = self::product_payload_by_sku( $sku );
            $known[ strtolower( $payload['sku'] ) ] = $payload;
        }

        return $known;
    }

    private static function render_map_card( $index, $map, $is_open = false ) {
        $index_attr  = esc_attr( $index );
        $enabled     = isset( $map['enabled'] ) ? $map['enabled'] : 'yes';
        $main_sku    = isset( $map['main_sku'] ) ? $map['main_sku'] : '';
        $extras_raw  = isset( $map['extra_skus_raw'] ) ? $map['extra_skus_raw'] : '';
        $extra_count = ! empty( $map['extra_skus'] ) && is_array( $map['extra_skus'] ) ? count( $map['extra_skus'] ) : 0;
        $tiers_raw   = isset( $map['tiers_raw'] ) ? $map['tiers_raw'] : '2:10, 4:15, 6:20';
        $title       = isset( $map['title'] ) ? $map['title'] : 'Filamentos Esenciales';
        $known       = self::known_products_for_map( $map );
        ?>
        <section class="sku_9015-map-card <?php echo $is_open ? 'is-open' : ''; ?>" data-sku_9015-map>
            <script type="application/json" class="sku_9015-known-products"><?php echo wp_json_encode( $known ); ?></script>

            <header class="sku_9015-map-card__header">
                <button type="button" class="sku_9015-map-card__toggle" data-sku_9015-toggle aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>">
                    <span class="sku_9015-caret" aria-hidden="true">›</span>
                    <span class="sku_9015-relation-title" data-sku_9015-card-title><?php echo esc_html( $main_sku ? $main_sku : 'Nueva relación' ); ?></span>
                    <span class="sku_9015-muted" data-sku_9015-card-subtitle><?php echo esc_html( $title ); ?></span>
                </button>

                <div class="sku_9015-map-card__summary">
                    <span class="sku_9015-pill" data-sku_9015-main-pill><?php echo esc_html( $main_sku ? $main_sku : 'Sin SKU' ); ?></span>
                    <span class="sku_9015-pill" data-sku_9015-extra-pill><?php echo esc_html( $extra_count ); ?> extras</span>
                    <span class="sku_9015-pill" data-sku_9015-tier-pill><?php echo esc_html( $tiers_raw ); ?></span>
                    <span class="sku_9015-status <?php echo 'yes' === $enabled ? 'is-active' : 'is-off'; ?>" data-sku_9015-status-pill><?php echo 'yes' === $enabled ? 'Activo' : 'Inactivo'; ?></span>
                </div>

                <button type="button" class="button-link-delete sku_9015-remove-map">Eliminar</button>
            </header>

            <div class="sku_9015-map-card__body" data-sku_9015-body>
                <div class="sku_9015-panel">
                    <div class="sku_9015-grid sku_9015-grid-2">
                        <div class="sku_9015-field">
                            <label>Estado</label>
                            <input type="hidden" name="sku_9015_maps[<?php echo $index_attr; ?>][enabled]" value="no">
                            <label class="sku_9015-toggle-field">
                                <input type="checkbox" name="sku_9015_maps[<?php echo $index_attr; ?>][enabled]" value="yes" <?php checked( $enabled, 'yes' ); ?> data-sku_9015-enabled>
                                <span>Activo</span>
                            </label>
                        </div>

                        <div class="sku_9015-field">
                            <label>Máximo por extra</label>
                            <input type="number" min="1" step="1" name="sku_9015_maps[<?php echo $index_attr; ?>][max_qty]" value="<?php echo esc_attr( isset( $map['max_qty'] ) ? $map['max_qty'] : 99 ); ?>">
                            <small>Cantidad máxima que podrá elegir el cliente por cada extra.</small>
                        </div>
                    </div>

                    <div class="sku_9015-field sku_9015-search-field" data-sku_9015-search="main">
                        <label>SKU del producto principal</label>
                        <div class="sku_9015-search-box">
                            <input type="text" class="sku_9015-product-search" placeholder="Busca por nombre, SKU o ID" autocomplete="off">
                            <div class="sku_9015-search-results" hidden></div>
                        </div>
                        <input type="text" class="sku_9015-main-sku" name="sku_9015_maps[<?php echo $index_attr; ?>][main_sku]" value="<?php echo esc_attr( $main_sku ); ?>" placeholder="SKU principal, ej. BAMBU-H2D" data-sku_9015-main-sku>
                        <div class="sku_9015-main-preview" data-sku_9015-main-preview></div>
                        <small>El bloque se mostrará solo cuando el producto visitado tenga este SKU.</small>
                    </div>
                </div>

                <div class="sku_9015-panel">
                    <div class="sku_9015-field sku_9015-search-field" data-sku_9015-search="extra">
                        <label>Extras relacionados</label>
                        <div class="sku_9015-search-box">
                            <input type="text" class="sku_9015-product-search" placeholder="Busca extras por nombre, SKU o ID y haz clic para agregarlos" autocomplete="off">
                            <div class="sku_9015-search-results" hidden></div>
                        </div>

                        <textarea class="sku_9015-extra-skus" name="sku_9015_maps[<?php echo $index_attr; ?>][extra_skus_raw]" rows="3" placeholder="PLA-CIAN, PLA-VERDE, PLA-NEGRO" data-sku_9015-extra-skus><?php echo esc_textarea( $extras_raw ); ?></textarea>

                        <div class="sku_9015-extra-table-wrap">
                            <table class="widefat striped sku_9015-extra-table">
                                <thead>
                                    <tr>
                                        <th>SKU</th>
                                        <th>Producto</th>
                                        <th>Tipo</th>
                                        <th>Precio</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody data-sku_9015-extra-rows></tbody>
                            </table>
                            <div class="sku_9015-empty" data-sku_9015-empty-extras>Sin extras agregados todavía.</div>
                        </div>

                        <small>También puedes pegar SKUs separados por coma o salto de línea. Puede ser SKU de producto simple o SKU de variación.</small>
                    </div>
                </div>

                <div class="sku_9015-panel">
                    <div class="sku_9015-grid sku_9015-grid-2">
                        <div class="sku_9015-field">
                            <label>Título del bloque</label>
                            <input type="text" name="sku_9015_maps[<?php echo $index_attr; ?>][title]" value="<?php echo esc_attr( $title ); ?>" data-sku_9015-title>
                        </div>

                        <div class="sku_9015-field">
                            <label>Niveles de descuento</label>
                            <input type="text" name="sku_9015_maps[<?php echo $index_attr; ?>][tiers_raw]" value="<?php echo esc_attr( $tiers_raw ); ?>" placeholder="2:10, 4:15, 6:20" data-sku_9015-tiers>
                            <small>Formato: <code>cantidad:descuento</code>. Ejemplo: <code>2:10</code> = 2 o más piezas con 10% OFF.</small>
                        </div>
                    </div>

                    <div class="sku_9015-field">
                        <label>Descripción</label>
                        <input type="text" name="sku_9015_maps[<?php echo $index_attr; ?>][description]" value="<?php echo esc_attr( isset( $map['description'] ) ? $map['description'] : '' ); ?>">
                    </div>
                </div>
            </div>
        </section>
        <?php
    }
}
