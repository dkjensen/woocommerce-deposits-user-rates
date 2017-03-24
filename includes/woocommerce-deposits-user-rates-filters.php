<?php


if( ! defined( 'ABSPATH' ) ) exit;

if( ! class_exists( 'WC_Deposits_Cart_Manager' ) )
	return;

$cartmanager  = WC_Deposits_Cart_Manager::get_instance();
$ordermanager = WC_Deposits_Order_Manager::get_instance();

remove_action( 'woocommerce_before_add_to_cart_button', 	array( $cartmanager, 'deposits_form_output' ), 99 );
remove_action( 'woocommerce_cart_loaded_from_session', 		array( $cartmanager, 'get_cart_from_session' ), 99, 1 );
remove_filter( 'woocommerce_add_to_cart_validation', 		array( $cartmanager, 'validate_add_cart_item' ), 10, 3 );
remove_filter( 'woocommerce_add_cart_item_data', 			array( $cartmanager, 'add_cart_item_data' ), 10, 2 );
remove_filter( 'woocommerce_add_cart_item', 				array( $cartmanager, 'add_cart_item' ), 99, 1 );
remove_filter( 'woocommerce_cart_item_subtotal', 			array( $cartmanager, 'display_item_subtotal' ), 10, 3 );
remove_filter( 'woocommerce_cart_item_price', 				array( $cartmanager, 'display_item_price' ), 10, 3 );
remove_filter( 'woocommerce_add_cart_item_data', 			array( $cartmanager, 'add_cart_item_data' ), 10, 2 );
remove_filter( 'woocommerce_add_cart_item', 				array( $cartmanager, 'add_cart_item' ), 99, 1 );

remove_action( 'woocommerce_after_order_itemmeta', 			array( $ordermanager, 'woocommerce_after_order_itemmeta' ), 10, 3 );
remove_action( 'admin_init', 								array( $ordermanager, 'order_action_handler' ) );
remove_action( 'woocommerce_order_status_processing', 		array( $ordermanager, 'process_deposits_in_order' ), 10, 1 );
remove_action( 'woocommerce_order_status_completed', 		array( $ordermanager, 'process_deposits_in_order' ), 10, 1 );
remove_action( 'woocommerce_order_status_on-hold', 			array( $ordermanager, 'process_deposits_in_order' ), 10, 1 );
remove_action( 'woocommerce_order_status_partial-payment', 	array( $ordermanager, 'process_deposits_in_order' ), 10, 1 );