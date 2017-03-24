<?php
/*
Plugin Name: WooCommerce Deposits - User Rates
Description: Adds deposits support to WooCommerce on the user-level
Version: 1.0.0
Author: David Jensen
Author URI: https://dkjensen.com
Text Domain: wcdur
Domain Path: /locale
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

if( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'woothemes_queue_update' ) ) {
    require_once 'woo-includes/woo-functions.php';
}

if( ! defined( 'WC_DEPOSITS_USER_RATES_URL' ) ) {
    define( 'WC_DEPOSITS_USER_RATES_URL', plugin_dir_url( __FILE__ ) );
}

if( is_woocommerce_active() ) {

    function woocommerce_deposits_user_rates() {
        require_once 'includes/woocommerce-deposits-user-rates-functions.php';
        require_once 'includes/woocommerce-deposits-user-rates-filters.php';
        require_once 'includes/woocommerce-deposits-user-rates-cart.php';
        require_once 'includes/woocommerce-deposits-user-rates-orders.php';

        if( is_admin() ) {
            require_once 'includes/admin/class-wc-deposits-admin-user-settings.php';
        }
    }
    add_action( 'plugins_loaded', 'woocommerce_deposits_user_rates', 15 );


    function woocommerce_deposits_user_rates_scripts() {
        wp_enqueue_style( 'wc-deposits-user-rates-style', WC_DEPOSITS_USER_RATES_URL . 'assets/css/style.css' );
        wp_enqueue_script( 'wc-deposits-user-rates-cart', WC_DEPOSITS_USER_RATES_URL . 'assets/js/add-to-cart.js', array( 'jquery' ) );

        $script_args = array();
        $script_args['update_payment_total_option_nonce'] = wp_create_nonce( 'update-payment-total-option' );

        wp_localize_script( 'wc-deposits-user-rates-cart', 'wc_deposits_add_to_cart_options', $script_args );
    }
    add_action( 'wp_enqueue_scripts', 'woocommerce_deposits_user_rates_scripts' );


    function woocommerce_deposits_user_rates_admin_scripts( $hook ) {
    	wp_enqueue_script( 'wc-deposits-user-rates-orders', WC_DEPOSITS_USER_RATES_URL . 'assets/js/admin/wc-deposits-user-rates-orders.js', array( 'jquery', 'wc-admin-order-meta-boxes' ) );
    }
    add_action( 'admin_enqueue_scripts', 'woocommerce_deposits_user_rates_admin_scripts' );
    
}