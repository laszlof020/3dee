<?php

if( !defined( 'ABSPATH' ) ) {
    exit;
}

if( !isset( $stylesheet_directory ) ) {
    $stylesheet_directory = get_stylesheet_directory();
}

require_once $stylesheet_directory.'/inc/theme/utility/php_logger.php';
require_once $stylesheet_directory.'/inc/theme/utility/array_utility.php';
//require_once $stylesheet_directory.'/inc/theme/utility/xmlrpc.php';
require_once $stylesheet_directory.'/inc/phpxmlrpc/src/Autoloader.php';

PhpXmlRpc\Autoloader::register();

PhpLogger::log( esc_html__( 'Loaded OdooConnector!', get_stylesheet() ), array( 'color' => '#00FF00' ) );

/**
 * -------------------------------------------------------------
 * ODOO Connector class
 * 
 * @method get_db_settings      Gets the database settings
 * @method authenticate         Authenticates database settings
 * @method execute_model_method Executes a models method
 * 
 * @since 1.0
 * -------------------------------------------------------------
 */

class OdooConnector {

    /**
     * --------------------------
     * Gets the database settings
     * --------------------------
     */

    private static function get_db_settings() {
        return array(
            'url' => get_option( 'odoo_url' ),
            'db'  => get_option( 'odoo_db'  ),
            'usr' => get_option( 'odoo_usr' ),
            'pwd' => get_option( 'odoo_pwd' )
        );
    }

    /**
     * -------------------------------------------------------
     * Attempts to authenticate the user
     * 
     * @param $settings An array with database settings
     * 
     * @return Returns user id if authentic
     *         Returns false if user could not be autheticated
     * -------------------------------------------------------
     */

    public static function authenticate( $settings = array() ) {
        /*$settings = self::get_db_settings();
        $response = XMLRPC::call( $settings['url'].'/xmlrpc/2/common', 'authenticate', array( $settings['db'], $settings['usr'], $settings['pwd'], array() ) );
        if( isset( $response['fault'] ) ) {
            PhpLogger::log( 'Could not authenticate user '.$settings['usr'].'!', array( 'color' => '#FF0000' ) );
            return $response['fault']['value']['struct']['member'];
        }
        return $response['params']['param']['value']['int'];*/
        $settings = self::get_db_settings();
        $encoder  = new PhpXmlRpc\Encoder();
        $client   = new PhpXmlRpc\Client( $settings['url'].'/xmlrpc/2/common' );
        $request  = new PhpXmlRpc\Request( 'authenticate', $encoder->encode( array( $settings['db'], $settings['usr'], $settings['pwd'], array() ) ) );
        $response = $client->send( $request );
        if( $response->faultCode() || !isset( $response->value()['int'] ) ) {
            PhpLogger::log( 'Could not authenticate user '.$settings['usr'].'!', array( 'color' => '#FF0000' ) );
            return $response->faultCode();
        }
        return $encoder->decode( $response->value() );
    }

    /**
     * -------------------------------------------------------
     * Executes a function on an odoo model
     * 
     * @param $user_id The user id
     * @param $model   The model
     * @param $method  The method
     * @param $filter  An array used as filters
     * @param $map     An array used as a field-map
     * 
     * @return Returns data if function could be executed
     *         Returns false if function could not be executed
     * -------------------------------------------------------
     */

    public static function execute_model_method( $user_id, $model, $method, $filter = null, $map = null ) {
        /*$settings = self::get_db_settings();
        $response = XMLRPC::call( $settings['url'].'/xmlrpc/2/object', 'execute_kw', array( $settings['db'], $user_id, $settings['pwd'], $model, $method, $filter, $map ) );
        if( isset( $response['fault'] ) ) {
            PhpLogger::log( 'Method of model could not be executed!', array( 'color' => '#ff0000' ) );
            return $response['fault']['value']['struct']['member'];
        }
        return $response['params']['param']['value']['int'];*/
        $settings = self::get_db_settings();
        $encoder  = new PhpXmlRpc\Encoder();
        $client   = new PhpXmlRpc\Client( $settings['url'].'/xmlrpc/2/object' );
        $request  = new PhpXmlRpc\Request( 'execute_kw', $encoder->encode( array( $settings['db'], $user_id, $settings['pwd'], $model, $method, $filter, $map ) ) );
        $response = $client->send( $request );
        if( $response->faultCode() ) {
            PhpLogger::log( 'Method of model could not be executed!', array( 'color' => '#ff0000' ) );
            return false;
        }
        return $encoder->decode( $response->value() );
    }

    /**
     * ------------------------------------------------------
     * Attempts to update wc products from odoo
     * 
     * @return Retruns true if updated successfuly
     *         Returns false if products could not be updated
     * ------------------------------------------------------
     */

    public static function update_products_odoo_to_wc() {
        global $wpdb;
        // authenticate
        $user_id = self::authenticate();
        if( empty( $user_id ) ){
            return $user_id;
        }
        // get language
        $lang = get_option( 'odoo_lang' );
        if( empty( $lang ) ) {
            $lang = 'en_EN';
        }
        // get company id
        $company_id = get_option( 'odoo_company_id' );
        if( empty( $company_id ) ) {
            PhpLogger::log( 'Please provide a company id!', array( 'color' => '#FFFF00' ) );
            return false;
        }
        // get locations whitelist
        $quantities = array();
        $whitelist  = get_option( 'odoo_locations_wl' );
        if( !empty( $whitelist ) ) {
            $locations = explode( ',', $whitelist );
            foreach( $locations as $key => $location ) {
                $locations[$key] = trim( $location );
            }
            $relations = self::execute_model_method( $user_id, 'stock.quant', 'search_read', array( array( array( 'company_id', '=', $company_id ), array( 'location_id', 'in', $locations ) ) ), array( 'fields' => array( 'location_id', 'product_id', 'available_quantity' ), 'context' => array( 'lang' => $lang ) ) );
            if( empty( $relations ) ){
                PhpLogger::log( 'No quantity found!', array( 'color' => '#FFFF00' ) );
                return false;
            }
            // add up quantities of stock relations
            foreach( $relations as $relation ) {
                if( isset( $quantities[$relation['product_id'][0]] ) ) {
                    $quantities[$relation['product_id'][0]] += $relation['available_quantity'];
                } else {
                    $quantities[$relation['product_id'][0]] = $relation['available_quantity'];
                }
            }
        }
        // get products from odoo
        $odoo_prods = array();
        $odoo_archived_prods_raw = self::execute_model_method( $user_id, 'product.product', 'search_read', array( array( array( 'company_id', '=', $company_id ), array( 'active', '=', false ) ) ), array( 'fields' => array( 'id', 'default_code', 'name', 'lst_price', 'd3_central_wh', 'd3_delivery_time', 'd3_package_content', 'active' ), 'context' => array( 'lang' => $lang ) ) );
        if( !empty( $odoo_archived_prods_raw ) ) {
            foreach( $odoo_archived_prods_raw as $odoo_prod ) {
                $odoo_prods[$odoo_prod['default_code']] = $odoo_prod;
            }
        }
        $odoo_active_prods_raw = self::execute_model_method( $user_id, 'product.product', 'search_read', array( array( array( 'company_id', '=', $company_id ), array( 'active', '=', true ) ) ), array( 'fields' => array( 'id', 'default_code', 'name', 'lst_price', 'd3_central_wh', 'd3_delivery_time', 'd3_package_content', 'active' ), 'context' => array( 'lang' => $lang ) ) );
        if( !empty( $odoo_active_prods_raw ) ) {
            foreach( $odoo_active_prods_raw as $odoo_prod ) {
                $odoo_prods[$odoo_prod['default_code']] = $odoo_prod;
            }
        }
        // get products from wc
        $wc_prods_sku_ids = $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key='_sku'", ARRAY_A );
        if( empty( $wc_prods_sku_ids ) ){
            PhpLogger::log( 'No woocommerce products found!', array( 'color' => '#FFFF00' ) );
            return false;
        }
        $wc_prods = array();
        foreach( $wc_prods_sku_ids as $wc_prod ) {
            $wc_prods[$wc_prod['meta_value']] = $wc_prod['post_id'];
        }
        // modify wc products
        foreach( $wc_prods as $sku => $post_id ) {
            // if product is found on odoo
            if( isset( $odoo_prods[$sku] ) ) {
                // get parent id
                $parent_id = $wpdb->get_var( "SELECT post_parent FROM {$wpdb->posts} WHERE ID={$post_id}" );
                // get odoo meta
                if( empty( $parent_id ) ) {
                    $odoo_meta = get_metadata( 'post', $post_id, '_odoo', true );
                } else {
                    $odoo_meta = get_metadata( 'post', $parent_id, '_odoo', true );
                }
                if( empty( $odoo_meta ) ) {
                    $odoo_meta = array( 'sync_name' => false, 'sync_price' => false, 'sync_stock' => false );
                }
                // if allowed sync price
                if( empty( $odoo_meta['sync_price'] ) ) {
                    $sale_price = $wpdb->get_var( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key='_sale_price' AND post_id={$post_id}" );
                    if( empty( $sale_price ) && !empty( ( $odoo_prods[$sku]['lst_price'] = floatval( $odoo_prods[$sku]['lst_price'] ) ) ) ) {
                        $wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value={$odoo_prods[$sku]['lst_price']} WHERE meta_key='_price' AND post_id={$post_id}" );
                        $wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value={$odoo_prods[$sku]['lst_price']} WHERE meta_key='_regular_price' AND post_id={$post_id}" );
                    }
                }
                // if allowed sync stock
                if( empty( $odoo_meta['sync_stock'] ) ) {
                    if( empty( $odoo_prods[$sku]['active'] ) ) {
                        $wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value='no' WHERE meta_key='_backorders' AND post_id={$post_id}" );
                    } else {
                        if( isset( $quantities[$odoo_prods[$sku]['id']] ) ) {
                            $quantity = intval( $quantities[$odoo_prods[$sku]['id']] );
                        } else {
                            $quantity = 0;
                        }
                        $wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value={$quantity} WHERE meta_key='_stock' AND post_id={$post_id}" );
                        if( empty( $odoo_prods[$sku]['d3_central_wh'] ) ) {
                            $wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value='notify' WHERE meta_key='_backorders' AND post_id={$post_id}" );
                            if( $quantity > 0 ) {
                                $wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value='instock' WHERE meta_key='_stock_status' AND post_id={$post_id}" );
                            } else {
                                $wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value='onbackorder' WHERE meta_key='_stock_status' AND post_id={$post_id}" );
                            }
                        } else {
                            $wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value='no' WHERE meta_key='_backorders' AND post_id={$post_id}" );
                            if( $quantity > 0 ) {
                                $wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value='instock' WHERE meta_key='_stock_status' AND post_id={$post_id}" );
                            } else {
                                $wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value='outofstock' WHERE meta_key='_stock_status' AND post_id={$post_id}" );
                            }
                        }
                    }
                }
                // set post id to parent id if parent post id is found
                if( !empty( $parent_id ) ) {
                    $post_id = $parent_id;
                }
                // if allowed update name
                if( empty( $odoo_meta['sync_name'] ) ) {
                    if( !empty( $odoo_prods[$sku]['name'] = strip_tags( $odoo_prods[$sku]['name'] ) ) ) {
                        $wpdb->query( "UPDATE {$wpdb->posts} SET post_title='{$odoo_prods[$sku]['name']}' WHERE ID={$post_id}" );
                    }
                }
                // update _d3 variables
                update_post_meta( $post_id, '_d3_central_wh', strip_tags( $odoo_prods[$sku]['d3_central_wh'] ) );
                update_post_meta( $post_id, '_d3_delivery_time', strip_tags( $odoo_prods[$sku]['d3_delivery_time'] ) );
                update_post_meta( $post_id, '_d3_package_content', strip_tags( $odoo_prods[$sku]['d3_package_content'] ) );
            // if product is not found on odoo
            } else {
                // set backorders to default
                $wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value='notify' WHERE meta_key='_backorders' AND post_id={$post_id}" );
                $quantity = intval( get_metadata( 'post', $post_id, '_stock', true ) );
                if( $quantity > 0 ) {
                    $wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value='instock' WHERE meta_key='_stock_status' AND post_id={$post_id}" );
                } else {
                    $wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value='onbackorder' WHERE meta_key='_stock_status' AND post_id={$post_id}" );
                }
                // set _d3 variables to defaults
                delete_post_meta( $post_id, '_d3_central_wh' );
                delete_post_meta( $post_id, '_d3_delivery_time' );
                delete_post_meta( $post_id, '_d3_package_content' );
            }
        }
        PhpLogger::log( 'WC products updated!', array( 'color' => '#FFFF00' ) );
        return true;
    }

}