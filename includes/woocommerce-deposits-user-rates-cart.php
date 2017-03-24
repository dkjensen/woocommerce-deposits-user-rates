<?php



function woocommerce_deposits_cart_update_payment_total_option() {
	check_ajax_referer( 'update-payment-total-option', 'security' );

	if( ! defined( 'WOOCOMMERCE_CART' ) ) {
		define( 'WOOCOMMERCE_CART', true );
	}

	$payment_total_option = esc_attr( $_POST['payment_total_option'] );

	if( $payment_total_option === 'deposit' ) {
		WC()->session->set( 'deposit_enable', true );
		WC()->cart->deposit_enable = true;
	}else {
		WC()->session->set( 'deposit_enable', false );
		WC()->cart->deposit_enable = false;
	}

	WC()->cart->calculate_totals();

	woocommerce_cart_totals();

	exit;
}
add_action( 'wp_ajax_cart_update_payment_total_option', 'woocommerce_deposits_cart_update_payment_total_option' );
add_action( 'wp_ajax_nopriv_cart_update_payment_total_option', 'woocommerce_deposits_cart_update_payment_total_option' );


function woocommerce_deposits_add_cart_item( $cart_item, $cart_item_key = '' ) {
	if( WC()->session->get( 'deposit_enable' ) ) {
		wc_update_deposit_meta( $cart_item['data'], $cart_item['quantity'], $cart_item );
	}

	return $cart_item;
}
add_filter( 'woocommerce_add_cart_item', 'woocommerce_deposits_add_cart_item' , 15, 2 );


function add_cart_item_data( $cart_item_meta, $product_id ) {
	if ( ! WC_Deposits_Product_Manager::deposits_enabled( $product_id ) ) {
		return $cart_item_meta;
	}

	if( WC()->session->get( 'deposit_enable' ) ) {
		$cart_item_meta['is_deposit'] = true;

		if ( 'plan' === WC_Deposits_Product_Manager::get_deposit_type( $product_id ) ) {
			$cart_item_meta['payment_plan'] = $wc_deposit_payment_plan;
		} else {
			$cart_item_meta['payment_plan'] = 0;
		}
	}else {
		$cart_item_meta['is_deposit'] = false;
	}

	return $cart_item_meta;
}
add_filter( 'woocommerce_add_cart_item_data', 'woocommerce_deposits_add_cart_item_data', 15, 2 );


function woocommerce_deposits_cart_total_option() {
?>

	<h2><?php _e( 'Payment Option', 'woocommerce' ); ?></h2>

	<table cellspacing="0" class="shop_table shop_table_responsive">
	  <tr class="cart-subtotal">
	    <th><?php _e( 'Payment Option', 'woocommerce' ); ?></th>
	    <td data-title="<?php esc_attr_e( 'Payment Option', 'woocommerce' ); ?>">
	      <ul class="payment-total-options">
	        <li>
	          <input type="radio" name="payment_total" value="full" id="payment-total-full" class="payment-total-option" <?php checked( WC()->session->get( 'deposit_enable' ), false ); ?> />
	          <label for="payment-total-full"><?php _e( 'Pay in full', 'woocommerce' ); ?></label>
	        </li>
	        <li>
	          <input type="radio" name="payment_total" value="deposit" id="payment-total-deposit" class="payment-total-option" <?php checked( WC()->session->get( 'deposit_enable' ), true ); ?> />
	          <label for="payment-total-deposit"><?php printf( __( 'Pay a deposit (%s)', 'woocommerce' ), wc_deposits_user_amount() . '%' ); ?></label>
	        </li>
	      </ul>
	    </td>
	  </tr>
	</table>

<?php
}
add_action( 'woocommerce_before_cart_totals', 'woocommerce_deposits_cart_total_option', 15 );


function woocommerce_deposits_cart_updated() {
	$deposit_enable = WC()->session->get( 'deposit_enable' );

    if( ! isset( $deposit_enable ) ) {
    	WC()->session->set( 'deposit_enable', false );
    	WC()->cart->deposit_enable = false;
    }else {
    	WC()->cart->deposit_enable = (bool) WC()->session->get( 'deposit_enable' );
    }
}
add_action( 'woocommerce_cart_updated', 'woocommerce_deposits_cart_updated', 15 );


function woocommerce_deposits_get_cart_item_from_session( $cart_item, $values ) {
	if( WC()->session->get( 'deposit_enable' ) ) {
		$cart_item['is_deposit']   = ! empty( $values['is_deposit'] );
		$cart_item['payment_plan'] = ! empty( $values['payment_plan'] ) ? absint( $values['payment_plan'] ) : 0;
	}else {
		$cart_item['is_deposit']   = false;
	}

	return woocommerce_deposits_add_cart_item( $cart_item );
}
add_filter( 'woocommerce_get_cart_item_from_session', 'woocommerce_deposits_get_cart_item_from_session', 15, 2 );


function woocommerce_deposits_display_item_subtotal( $output, $cart_item, $cart_item_key ) {
	if ( ! WC()->session->get( 'deposit_enable' ) || ! isset( $cart_item['full_amount'] ) ) {
		return $output;
	}

	if( ! empty( $cart_item['is_deposit'] ) ) {
		$_product = $cart_item['data'];
		$quantity = $cart_item['quantity'];

		if ( 'excl' === WC()->cart->tax_display_cart ) {
			$full_amount    = $_product->get_price_excluding_tax( $quantity, $cart_item['full_amount'] );
			$deposit_amount = $_product->get_price_excluding_tax( $quantity, $cart_item['deposit_amount'] );
		} else {
			$full_amount    = $_product->get_price_including_tax( $quantity, $cart_item['full_amount'] );
			$deposit_amount = $_product->get_price_including_tax( $quantity, $cart_item['deposit_amount'] );
		}

		$output .= '<br/><small>' . sprintf( __( '%s payable in total', 'woocommerce-deposits' ), wc_price( $full_amount ) ) . '</small>';
	}

	return $output;
}
add_filter( 'woocommerce_cart_item_subtotal', 'woocommerce_deposits_display_item_subtotal', 15, 3 );


function woocommerce_deposits_add_order_item_meta( $item_id, $cart_item ) {
	if( ! empty( $cart_item['is_deposit'] ) ) {
		wc_add_order_item_meta( $item_id, '_deposit_initial_qty', $cart_item['quantity'] );
		wc_add_order_item_meta( $item_id, '_deposit_single_amount', $cart_item['data']->get_price_including_tax( 1, $cart_item['deposit_amount'] ) );
		wc_add_order_item_meta( $item_id, '_deposit_single_amount_ex_tax', $cart_item['data']->get_price_excluding_tax( 1, $cart_item['deposit_amount'] ) );
	}
}
add_action( 'woocommerce_add_order_item_meta', 'woocommerce_deposits_add_order_item_meta', 60, 2 );