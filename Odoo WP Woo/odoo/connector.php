<?php 

if( !defined( 'ABSPATH' ) ) {
    exit;
}

if( !isset( $stylesheet_directory ) ) {
    $stylesheet_directory = get_stylesheet_directory();
}

require_once $stylesheet_directory.'/inc/theme/utility/php_logger.php';
require_once $stylesheet_directory.'/inc/theme/utility/array_utility.php';
require_once $stylesheet_directory.'/inc/odoo/ripcord/ripcord.php';

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
        try {
            $settings = ArrayUtility::parse( $settings, self::get_db_settings() );
            $client   = ripcord::client( $settings['url'].'/xmlrpc/2/common' );
            $user_id  = $client->authenticate( $settings['db'], $settings['usr'], $settings['pwd'], array() );
            if( empty( $user_id ) ) {
                PhpLogger::log( 'Could not authenticate user '.$settings['usr'].'!', array( 'color' => '#FF0000' ) );
                return false;
            }
            PhpLogger::log( 'User authenticated!', array( 'color' => '#FFFF00' ) );
            return $user_id;
        } catch ( Exception $e ) {
            PhpLogger::log( $e->getMessage(), array( 'color' => '#FF0000' ) );
            return false;
        }
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
        try {
            $settings = self::get_db_settings();
            $client   = ripcord::client( $settings['url'].'/xmlrpc/2/object' );
            $data     = $client->execute_kw( $settings['db'], $user_id, $settings['pwd'], $model, $method, $filter, $map );
            if( empty( $data ) ) {
                PhpLogger::log( 'Could not retrieve data!', array( 'color' => '#FF0000' ) );
                return false;
            }
            PhpLogger::log( 'Data retrieved!', array( 'color' => '#FFFF00' ) );
            return $data;
        } catch ( Exception $e ) {
            PhpLogger::log( $e->getMessage() );
            return false;
        }
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
        $user_id = self::authenticate();
        if( empty( $user_id ) ){
            return false;
        }
        $lang = get_option( 'odoo_lang' );
        if( empty( $lang ) ) {
            $lang = 'en_EN';
        }
        $odoo_prods = self::execute_model_method( $user_id, 'product.product', 'search_read', array(array()), array( 'fields' => array( 'default_code', 'name', 'list_price', 'lst_price', 'qty_available', 'is_product_variant', 'd3_central_wh', 'd3_delivery_time', 'd3_package_content' ), 'context' => array( 'lang' => $lang ) ) );
        if( empty( $odoo_prods ) ){
            return false;
        }
        global $wpdb;
        foreach( $odoo_prods as $odoo_prod ) {
            $post_id = $wpdb->get_var( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_sku' AND meta_value='{$odoo_prod['default_code']}'" );
            if( empty( $post_id ) ) {
                continue;
            }
            $odoo_meta = get_post_meta( $post_id, '_odoo', true );
            if( empty( $odoo_meta ) ) {
                $odoo_meta = array( 'sync_name' => true, 'sync_price' => true, 'sync_stock' => true );
            }
            if( !empty( $odoo_meta['sync_price'] ) ) {
                $sale_price = $wpdb->get_var( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key='_sale_price' AND post_id={$post_id}" );
                if( empty( $sale_price ) && !empty( ( $odoo_prod['lst_price'] = floatval( $odoo_prod['lst_price'] ) ) ) ) {
                    $wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value={$odoo_prod['lst_price']} WHERE meta_key='_price' AND post_id={$post_id}" );
                    $wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value={$odoo_prod['lst_price']} WHERE meta_key='_regular_price' AND post_id={$post_id}" );
                }
            }
            if( !empty( $odoo_meta['sync_stock'] ) ) {
                if( !empty( ( $odoo_prod['qty_available'] = intval( $odoo_prod['qty_available'] ) ) ) ) {
                    $wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value={$odoo_prod['qty_available']} WHERE meta_key='_stock' AND post_id={$post_id}" );
                } else {
                    $wpdb->query( "UPDATE {$wpdb->postmeta} SET meta_value=0 WHERE meta_key='_stock' AND post_id={$post_id}" );
                }
            }
            $parent_id = $wpdb->get_var( "SELECT post_parent FROM {$wpdb->posts} WHERE ID={$post_id}" );
            if( !empty( $parent_id ) ) {
                $post_id = $parent_id;
            }
            if( !empty( $odoo_meta['sync_name'] ) ) {
                if( !empty( $odoo_prod['name'] = htmlspecialchars( $odoo_prod['name'] ) ) ) {
                    $wpdb->query( "UPDATE {$wpdb->posts} SET post_title='{$odoo_prod['name']}' WHERE ID={$post_id}" );
                }
            }
            if( !empty( $odoo_prod['d3_central_wh'] = htmlspecialchars( $odoo_prod['d3_central_wh'] ) ) ) {
                update_post_meta( $post_id, '_d3_central_wh', $odoo_prod['d3_central_wh'] );
            }
            if( !empty( $odoo_prod['d3_delivery_time'] = htmlspecialchars( $odoo_prod['d3_delivery_time'] ) ) ) {
                update_post_meta( $post_id, '_d3_delivery_time', $odoo_prod['d3_delivery_time'] );
            }
            if( !empty( $odoo_prod['d3_package_content'] = htmlspecialchars( $odoo_prod['d3_package_content'] ) ) ) {
                update_post_meta( $post_id, '_d3_package_content', $odoo_prod['d3_package_content'] );
            }
        }
        PhpLogger::log( 'WC products updated!', array( 'color' => '#FFFF00' ) );
        return true;
    }

    /*public static function get_print_jobs_from_odoo() {
        $user_id = self::authenticate();
        if( empty( $user_id ) ){
            return false;
        }
        $odoo_projects = self::execute_model_method( $user_id, 'project.task', 'fields_get', array(), array( 'attributes' => array( 'string', 'help', 'type' ) ) );
        //$odoo_projects = self::execute_model_method( $user_id, 'project.task', 'search_read', array(array()), array( 'fields' => array( 'project_id', 'name', 'stage_id', 'company_id', 'material_id', 'material_color', 'technology_id', 'layer_partition_id', 'filling_id', 'printer_id', 'price' ) ) );
        PhpLogger::log( addslashes( json_encode( $odoo_projects ) ) );
        if( empty( $odoo_projects ) ){
            return false;
        }
        return $odoo_projects;
    }*/

}