<?php 

if( !defined( 'ABSPATH' ) ) {
    exit;
}



/**
 * --------------------------
 * Enqueue styles and scripts
 * @since Version 1.0
 * --------------------------
 */

function odoo_admin_enqueue_scripts() {
    global $child_directory, $child_version;
    $path = $child_directory . '/inc/odoo';
    if( !wp_script_is( 'odoo_connector_scripts', 'enqueued' ) ) {
        wp_enqueue_script( 'odoo_connector_scripts', $path . '/scripts.js', array(), $child_version, false );
        wp_localize_script( 'odoo_connector_scripts', 'opt', array(
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'noResults' => esc_html__( 'No results', 'woocommerce' ),
        ) );
    }
    if( !wp_style_is( 'odoo_connector_styles', 'enqueued' ) ) {
        wp_enqueue_style( 'odoo_connector_styles', $path . '/styles.css', array(), $child_version );
    }
} add_action( 'admin_enqueue_scripts', 'odoo_admin_enqueue_scripts' );



/**
 * --------------------------
 * Display d3_package_content
 * @since Version 1.0
 * --------------------------
 */

function odoo_display_d3_package_content() {
    $content = get_post_meta( get_the_ID(), '_d3_package_content', true );
    if( empty( $content ) ) {
        return;
    }
    $lines = explode( "\n", $content );
    if( empty( $lines ) ) {
        return;
    }
    preg_match( '/(.*?)((\.co)?.[a-z]{2,4})$/i', $_SERVER['HTTP_HOST'], $m );
    $ext = isset( $m[2] ) ? $m[2] : '';
    $pos = 0;
    echo '<div style="margin-bottom: 80px;">';
    if( $ext === '.at' || $ext === 'at' ) {
        echo '<h3>Lieferumfang</h3>';
    } else if( $ext === '.hu' || $ext === 'hu' ) {
        echo '<h3>Csomag tartalma</h3>';
    } else {
        echo '<h3>Package content</h3>';
    }
    echo '<ul class="primary">';
    foreach( $lines as $line ) {
        echo '<li>'.$line.'</li>';
    }
    echo '</ul>';
    echo '</div>';
} add_action( 'woocommerce_after_single_product', 'odoo_display_d3_package_content', 10 );



/**
 * --------------------
 * Set default timezone
 * @since Version 1.0
 * --------------------
 */

date_default_timezone_set( wp_timezone_string() );



/**
 * -----------------------
 * Add odoo cron intervals
 * @since Version 1.0
 * -----------------------
 */
    
function odoo_add_cron_intervals( $schedules ) { 
    $schedules['odoo_cron_interval_60'] = array(
        'interval' => 60,
        'display'  => esc_html__( 'Every minute', 'woocommerce' )
    );
    $schedules['odoo_cron_interval_600'] = array(
        'interval' => 600,
        'display'  => esc_html__( 'Every 10 minutes', 'woocommerce' )
    );
    $schedules['odoo_cron_interval_3600'] = array(
        'interval' => 3600,
        'display'  => esc_html__( 'Every hour', 'woocommerce' )
    );
    $schedules['odoo_cron_interval_86400'] = array(
        'interval' => 86400,
        'display'  => esc_html__( 'Every day', 'woocommerce' )
    );
    return $schedules;
} add_filter( 'cron_schedules', 'odoo_add_cron_intervals' );



/**
 * ------------------------------------
 * Add odoo tab to woocommerce settings
 * @since Version 1.0
 * ------------------------------------
 */

function odoo_add_settings_tab( $settings_tabs ) {

    $settings_tabs['odoo'] = esc_html__( 'Odoo', 'woocommerce' );

    return $settings_tabs;

} add_filter( 'woocommerce_settings_tabs_array', 'odoo_add_settings_tab', 50 );



/**
 * ---------------------------
 * Add new tab to product data
 * @since Version 1.0
 * ---------------------------
 */

function odoo_product_data_tabs( $tabs ) {
    
    $tabs['odoo_product_options'] = [
        'label'    => esc_html__( 'Odoo', 'woocommerce' ),
        'target'   => 'odoo_product_options',
        'class'    => ['hide_if_external'],
        'priority' => 56
    ];

    return $tabs;

} add_filter( 'woocommerce_product_data_tabs', 'odoo_product_data_tabs' );



/**
 * ----------------------------------
 * Add odoo fields to product options
 * @since Version 1.0
 * ----------------------------------
 */

function odoo_product_options_general_product_data() {

    $odoo_meta = get_post_meta( get_the_ID(), '_odoo', true );

    $product = wc_get_product( get_the_ID() );

    echo '<div id="odoo_product_options" class="panel woocommerce_options_panel odoo_options_panel hidden">';

    echo '<p><strong>' . esc_html__( 'Odoo Settings', 'woocommerce' ) . '</strong></p>';

    echo '<p>' . esc_html__( 'Please select the fields that you want to syncronize.', 'woocommerce' ) . '</p>';

    echo '<div class="odoo_product_options_content">';

    woocommerce_wp_checkbox( array(
        'id'          => 'odoo_sync_name',
        'value'       => empty( $odoo_meta ) ? 'yes' : $odoo_meta['sync_name'],
        'label'       => esc_html__( 'Name', 'woocommerce' ),
        'desc_tip'    => false,
        'description' => esc_html__( 'Synchronize the product name from odoo.', 'woocommerce' )
    ) );

    woocommerce_wp_checkbox( array(
        'id'          => 'odoo_sync_price',
        'value'       => empty( $odoo_meta ) ? 'yes' : $odoo_meta['sync_price'],
        'label'       => esc_html__( 'Price', 'woocommerce' ),
        'desc_tip'    => false,
        'description' => esc_html__( 'Synchronize the product price from odoo.', 'woocommerce' )
    ) );

    woocommerce_wp_checkbox( array(
        'id'          => 'odoo_sync_stock',
        'value'       => empty( $odoo_meta ) ? 'yes' : $odoo_meta['sync_stock'],
        'label'       => esc_html__( 'Stock', 'woocommerce' ),
        'desc_tip'    => false,
        'description' => esc_html__( 'Synchronize the product stock from odoo.', 'woocommerce' )
    ) );

    echo '</div>';

    echo '</div>';
 
} add_action( 'woocommerce_product_data_panels', 'odoo_product_options_general_product_data' );



/**
 * ---------------------------
 * Save cst fields to metadata
 * @since Version 1.0
 * ---------------------------
 */

function odoo_save_as_metadata( $id, $post ) {

    update_post_meta( $id, '_odoo', array(
        'sync_name'  => $_POST['odoo_sync_name'],
        'sync_price' => $_POST['odoo_sync_price'],
        'sync_stock' => $_POST['odoo_sync_stock']
    ) );
 
} add_action( 'woocommerce_process_product_meta', 'odoo_save_as_metadata', 10, 2 );



/**
 * ----------------------------
 * Get odoo connection settings
 * @since Version 1.0
 * ----------------------------
 */

function odoo_get_connection_settings() {
    $settings = array(
        'odoo_connection_section' => array( 
            'title' => esc_html__( 'Connection', 'woocommerce' ), 
            'type'  => 'title', 
            'desc'  => esc_html__( 'Connect your store to odoo!', 'woocommerce' ), 
            'id'    => 'odoo_connection_section'
        ),
        'odoo_url' => array(
            'title'    => esc_html__( 'Host', 'woocommerce' ),
            'desc'     => esc_html__( 'The url to connect to the host of your odoo installation.', 'woocommerce' ),
            'id'       => 'odoo_url',
            'type'     => 'text',
            'class'    => 'odoo-url',
            'desc_tip' => esc_html__( 'This is the url used to connect to the host of your odoo installation.', 'woocommerce' )
        ),
        'odoo_db' => array(
            'title'    => esc_html__( 'Database', 'woocommerce' ),
            'desc'     => esc_html__( 'The name of the database used by your odoo installation.', 'woocommerce' ),
            'id'       => 'odoo_db',
            'type'     => 'text',
            'class'    => 'odoo-db',
            'desc_tip' => esc_html__( 'This is the name of the database used by your odoo installation.', 'woocommerce' )
        ),
        'odoo_usr' => array(
            'title'    => esc_html__( 'Username', 'woocommerce' ),
            'desc'     => esc_html__( 'The username of the administrator managing your odoo installation.', 'woocommerce' ),
            'id'       => 'odoo_usr',
            'type'     => 'text',
            'class'    => 'odoo-usr',
            'desc_tip' => esc_html__( 'This is the username the administrator managing your odoo installation.', 'woocommerce' )
        ),
        'odoo_pwd' => array(
            'title'    => esc_html__( 'Password', 'woocommerce' ),
            'desc'     => esc_html__( 'The password of the administrator managing your odoo installation.', 'woocommerce' ),
            'id'       => 'odoo_pwd',
            'type'     => 'password',
            'class'    => 'odoo-pwd',
            'desc_tip' => esc_html__( 'This is the password of the administrator managing your odoo installation.', 'woocommerce' )
        ),
        'section_end' => array(
            'type' => 'sectionend',
            'id'   => 'odoo_connection_section'
        ),
    );
    return apply_filters( 'wc_settings_odoo', $settings );
}



/**
 * ----------------------------
 * Get odoo cron_event settings
 * @since Version 1.0
 * ----------------------------
 */

function odoo_get_cron_event_settings() {
    global $odoo_next_exec;
    if( $odoo_next_exec ) {
        $seconds = abs( $odoo_next_exec - time() );
        $minutes = $seconds / 60;
        $hours   = $minutes / 60;
        $seconds = $seconds % 60;
        $minutes = $minutes % 60;
        $hours   = $hours   % 24;
    }
    $settings = array(
        'odoo_cron_event_section' => array( 
            'title' => esc_html__( 'Syncronization', 'woocommerce' ), 
            'type'  => 'title', 
            'desc'  => esc_html__( 'Keep your store syncronized!', 'woocommerce' ), 
            'id'    => 'odoo_cron_event_section'
        ),
        'odoo_do_cron' => array(
            'title'    => esc_html__( 'Update store automatically', 'woocommerce' ),
            'desc'     => esc_html__( 'Activate automatic updates of your odoo installation.', 'woocommerce' ),
            'id'       => 'odoo_do_cron',
            'type'     => 'checkbox',
            'default'  => 'no',
            'class'    => 'odoo-do-cron',
            'desc_tip' => ( $odoo_next_exec ? esc_html__( 'Next update in: ', 'woocomemrce' ) . $hours . ' ' . esc_html__( 'hour(s)', 'woocommerce' ) . ' ' . $minutes . ' ' . esc_html__( 'minute(s)', 'woocommerce' ) . ' ' . $seconds . ' ' . esc_html__( 'second(s)', 'woocommerce' ) : esc_html__( 'You can activate this to automatically update your odoo installation.', 'woocommerce' ) )  . '<br><br><button type="button" class="button-primary" id="odoo_connector_update_now">' . esc_html__( 'Update now', 'woocommerce' ) . '</button>'
        ),
        'odoo_first_exec' => array(
            'title'    => esc_html__( 'First Synchronization', 'woocommerce' ),
            'desc'     => esc_html__( 'The time at which your store is first synchronized.', 'woocommerce' ),
            'id'       => 'odoo_first_exec',
            'type'     => 'time',
            'default'  => '00:00',
            'class'    => 'odoo-first-exec',
            'desc_tip' => esc_html__( 'This is the time at which your store is first synchronized.', 'woocommerce' )
        ),
        'odoo_sdl' => array(
            'title'    => esc_html__( 'Syncronization interval', 'woocommerce' ),
            'desc'     => esc_html__( 'The interval in minutes after which your store will be syncronized.', 'woocommerce' ),
            'id'       => 'odoo_sdl',
            'type'     => 'select',
            'default'  => 'odoo_cron_interval_86400',
            'options'  => array(
                'odoo_cron_interval_60'    => esc_html__( 'Every minute', 'woocommerce' ),
                'odoo_cron_interval_600'   => esc_html__( 'Every 10 minutes', 'woocommerce' ),
                'odoo_cron_interval_3600'  => esc_html__( 'Every hour', 'woocommerce' ),
                'odoo_cron_interval_86400' => esc_html__( 'Every day', 'woocommerce' )
            ),
            'class'    => 'odoo-sdl',
            'desc_tip' => esc_html__( 'This is the interval in minutes after which your store will be syncronized.', 'woocommerce' )
        ),
        'odoo_lang' => array(
            'title'    => esc_html__( 'Language', 'woocommerce' ),
            'desc'     => esc_html__( 'The language context of the database.', 'woocommerce' ),
            'id'       => 'odoo_lang',
            'type'     => 'select',
            'default'  => 'en_EN',
            'options'  => array(
                'en_EN' => esc_html__( 'English', 'woocommerce' ),
                'de_DE' => esc_html__( 'German', 'woocommerce' ),
                'hu_HU' => esc_html__( 'Hungarian', 'woocommerce' ),
            ),
            'class'    => 'odoo-lang',
            'desc_tip' => esc_html__( 'This determins the language context of the database.', 'woocommerce' )
        ),
        'section_end' => array(
            'type' => 'sectionend',
            'id'   => 'odoo_cron_event_section'
        ),
    );
    return apply_filters( 'wc_settings_odoo', $settings );
}



/**
 * ---------------------------
 * Add setting to odoo section
 * @since Version 1.0
 * ---------------------------
 */

function odoo_get_settings_products() {
    
    global $odoo_next_exec;
    $odoo_next_exec = wp_next_scheduled( 'odoo_cron_job' );

    woocommerce_admin_fields( odoo_get_connection_settings() );
    woocommerce_admin_fields( odoo_get_cron_event_settings() );

} add_action( 'woocommerce_settings_tabs_odoo', 'odoo_get_settings_products' );



/**
 * ------------------
 * Save odoo settings
 * @since Version 1.0
 * ------------------
 */

function odoo_save_settings() {

    global $odoo_next_exec;
    $odoo_next_exec = wp_next_scheduled( 'odoo_cron_job' );

    woocommerce_update_options( odoo_get_connection_settings() );
    woocommerce_update_options( odoo_get_cron_event_settings() );

} add_action( 'woocommerce_update_options_odoo', 'odoo_save_settings' );



require_once __DIR__.'/../theme/utility/cron_utility.php';
require_once 'connector.php';



/**
 * ---------------------------
 * Initiates the odoo cron job
 * 
 * @since 1.0
 * ---------------------------
 */

function init_odoo_cron_job() {
    if( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
        return false;
    }
    CronUtility::add_function( 'odoo_cron_job', 'OdooConnector::update_products_odoo_to_wc' );
} add_action( 'init', 'init_odoo_cron_job' );



/**
 * -------------------------
 * Updates the odoo cron job
 * 
 * @since 1.0
 * -------------------------
 */

function update_odoo_cron_job() {
    if( get_option( 'odoo_do_cron' ) === 'yes' ) {
        if( CronUtility::schedule_cron_job( 'odoo_cron_job', get_option( 'odoo_sdl' ), strtotime( get_option( 'odoo_first_exec' ) ) ) ) {
            PhpLogger::log( 'Scheduled odoo cron job!', array( 'color' => '#FFFF00' ) );
            return true;
        }
        if( CronUtility::update_cron_job( 'odoo_cron_job', get_option( 'odoo_sdl' ), strtotime( get_option( 'odoo_first_exec' ) ) ) ) {
            PhpLogger::log( 'Updated odoo cron job!', array( 'color' => '#FFFF00' ) );
            return true;
        }
        PhpLogger::log( 'Could not configure odoo cron job!', array( 'color' => '#FF0000' ) );
        return false;
    }
    if( CronUtility::unschedule_cron_job( 'odoo_cron_job' ) ) {
        PhpLogger::log( 'Unscheduled odoo cron job!', array( 'color' => '#FFFF00' ) );
        return true;
    }
    PhpLogger::log( 'Could not unschedule odoo cron job!', array( 'color' => '#FF0000' ) );
    return false;
} add_action( 'woocommerce_update_options_odoo', 'update_odoo_cron_job' );



/**
 * -------------------------
 * Execute the odoo cron job
 * 
 * @since 1.0
 * -------------------------
 */

function execute_odoo_cron_job() {
    $result = OdooConnector::update_products_odoo_to_wc();
    if( wp_doing_ajax() ) {
        wp_die( $result );
    }
} add_action( 'wp_ajax_execute_odoo_cron_job', 'execute_odoo_cron_job' );
  add_action( 'wp_ajax_nopriv_execute_odoo_cron_job', 'execute_odoo_cron_job' );