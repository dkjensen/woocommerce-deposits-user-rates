<?php

/**
 * Returns the users deposit amount
 * 
 * @param  integer $user_id 
 * @return integer
 */
function wc_deposits_user_amount( $user_id = 0 ) {
    if( empty( $user_id ) ) {
        $user_id = get_current_user_id();
    }

    $deposit_amount = get_user_meta( $user_id, 'deposit_amount', true );

    if( empty( $default_deposit = get_option( 'wc_deposits_default_amount' ) ) ) {
    	$default_deposit = 50;
    }

    return ! empty( $deposit_amount ) ? intval( $deposit_amount ) : $default_deposit;
}


function wc_update_deposit_meta( $product, $quantity, &$cart_item_data ) {
    $deposit_amount = wc_deposits_user_amount();

    $cart_item_data['is_deposit'] = false;

    if( ! empty( $deposit_amount ) && WC()->session->get( 'deposit_enable' ) ) {
    	$deposit = $deposit_amount;

    	$tax_display_mode = get_option( 'woocommerce_tax_display_shop' );


    	if( $tax_display_mode == 'incl' ) {
    		$amount = $product->get_price_including_tax( 1 );
    	}else {
    		$amount = $product->get_price_excluding_tax( 1 );
    	}

	    $deposit = $amount * ( $deposit_amount / 100.0 );

	    if( $deposit < $amount && $deposit > 0 ) {
	    	$cart_item_data['is_deposit']     = true;
	        $cart_item_data['deposit_amount'] = $deposit;
	        $cart_item_data['full_amount']    = $amount;
	    }else {
	        $cart_item_data['is_deposit']     = false;
	        $cart_item_data['deposit_amount'] = $amount;
	        $cart_item_data['full_amount']    = $amount;
	    }

	    $cart_item_data['data']->set_price( $deposit );
	}
}


function wc_deposits_order_remaining( $order_id = 0 ) {
	$order = wc_get_order( $order_id );
	$paid  = get_post_meta( $order_id, '_balance_paid', true );
	$total = get_post_meta( $order_id, '_original_total', true );

	$remaining = 0 - $paid;
	if( $order && $paid ) {
		foreach( $order->get_items() as $order_item_id => $order_item ) {
			if( WC_Deposits_Order_Item_Manager::is_deposit( $order_item ) ) {
				$remaining += floatval( $order_item['deposit_full_amount_ex_tax'] );
			}else {
				$remaining += floatval( $order->get_line_total( $order_item, true ) );
			}
		}

		$remaining = round( $remaining, wc_get_price_decimals() );

		if( $remaining <= 0 ) {
			return 0.00;
		}

		return $remaining;
	}

	return false;
}


function wc_deposits_create_order( $payment_date, $original_order_id, $payment_number, $items, $status = '' ) {
	$original_order = wc_get_order( $original_order_id );
	$new_order      = wc_create_order( array(
		'status'        => $status,
		'customer_id'   => $original_order->get_user_id(),
		'customer_note' => $original_order->customer_note,
		'created_via'   => 'wc_deposits'
	) );
	if ( is_wp_error( $new_order ) ) {
		$original_order->add_order_note( sprintf( __( 'Error: Unable to create follow up payment (%s)', 'woocommerce-deposits' ), $scheduled_order->get_error_message() ) );
	} else {
		$new_order->set_address( array(
			'first_name' => $original_order->billing_first_name,
			'last_name'  => $original_order->billing_last_name,
			'company'    => $original_order->billing_company,
			'address_1'  => $original_order->billing_address_1,
			'address_2'  => $original_order->billing_address_2,
			'city'       => $original_order->billing_city,
			'state'      => $original_order->billing_state,
			'postcode'   => $original_order->billing_postcode,
			'country'    => $original_order->billing_country,
			'email'      => $original_order->billing_email,
			'phone'      => $original_order->billing_phone
		), 'billing' );
		$new_order->set_address( array(
			'first_name' => $original_order->shipping_first_name,
			'last_name'  => $original_order->shipping_last_name,
			'company'    => $original_order->shipping_company,
			'address_1'  => $original_order->shipping_address_1,
			'address_2'  => $original_order->shipping_address_2,
			'city'       => $original_order->shipping_city,
			'state'      => $original_order->shipping_state,
			'postcode'   => $original_order->shipping_postcode,
			'country'    => $original_order->shipping_country
		), 'shipping' );

		foreach( $items as $item ) {
			// Handle items
			$item_id = $new_order->add_product( $item['product'], $item['qty'], array(
				'totals' => array(
					'subtotal'     => $item['amount'],
					'total'        => $item['amount'],
					'subtotal_tax' => 0,
					'tax'          => 0
				)
			) );
			wc_add_order_item_meta( $item_id, '_original_order_id', $original_order_id );
			wc_add_order_item_meta( $item_id, '_remaining_balance_order_id', $new_order->id );

			/* translators: Payment number for product's title */
			wc_update_order_item( $item_id, array( 'order_item_name' => sprintf( __( 'Payment #%d for %s', 'woocommerce-deposits' ), $payment_number, $item['product']->get_title() ) ) );
		}

		$new_order->calculate_totals( wc_tax_enabled() );

		// Set future date and parent
		$new_order_post = array(
			'ID'          => $new_order->id,
			'post_date'   => date( 'Y-m-d H:i:s', $payment_date ),
			'post_parent' => $original_order_id
		);
		wp_update_post( $new_order_post );

		do_action( 'woocommerce_deposits_create_order', $new_order->id );
		return $new_order->id;
	}
}