<?php

function woocommerce_deposits_order_is_editable( $editable, $order ) {
	if( $order->has_status( 'partial-payment' ) ) {
    	$editable = true;
    }

	return $editable;
}
add_filter( 'wc_order_is_editable', 'woocommerce_deposits_order_is_editable', 15, 2 );


function woocommerce_deposits_admin_order_totals_after_total( $order_id ) {
	$parent_id = wp_get_post_parent_id( $order_id );

	if( $parent_id ) return;

	$paid      = get_post_meta( $order_id, '_balance_paid', true );
	$remaining = wc_deposits_order_remaining( $order_id );
	?>

	<tr>
		<td class="label"><?php _e( 'Amount Paid', 'woocommerce' ); ?>:</td>
		<td width="1%"></td>
		<td class="total paid" data-paid="<?php print esc_attr( $paid ); ?>">
			<?php print wc_price( $paid ); ?>
		</td>
	</tr>
	<?php /*
	<tr>
		<td class="label"><?php _e( 'Remaining', 'woocommerce' ); ?>:</td>
		<td width="1%"></td>
		<td class="total">
			<?php print wc_price( $remaining ); ?>
			<input type="hidden" name="_balance_remaining" value="<?php print $remaining; ?>" />
		</td>
	</tr>
	*/ ?>

	<?php
}
add_action( 'woocommerce_admin_order_totals_after_total', 'woocommerce_deposits_admin_order_totals_after_total' );


function woocommerce_deposits_order_action_buttons( $order ) {
	$ordermanager = WC_Deposits_Order_Manager::get_instance();

	if( $ordermanager->has_deposit( $order ) ) {
		$remaining_balance_order_id = get_post_meta( $order->id, '_remaining_balance_order_id', true );

		if( ! empty( $remaining_balance_order_id ) && ( $remaining_balance_order = wc_get_order( $remaining_balance_order_id ) ) ) : ?>

			<a href="<?php print admin_url( 'post.php?post=' . absint( $remaining_balance_order_id ) . '&action=edit' ); ?>" class="button" style="float: none;"><?php printf( __( 'Remainder - Invoice #%1$s', 'woocommerce-deposits' ), $remaining_balance_order->get_order_number() ) ?></a>

		<?php else : ?>

			<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'invoice_remaining_balance' => $order->id ), admin_url( 'post.php?post=' . $order_id . '&action=edit' ) ), 'invoice_remaining_balance', 'invoice_remaining_balance_nonce' ) ); ?>" class="button" style="float: none;"><?php _e( 'Invoice Remaining Balance', 'woocommerce-deposits' ); ?></a>

			<?php /* <button type="button" class="button button-primary calculate-remaining"><?php _e( 'Calculate Remaining', 'woocommerce-deposits' ); ?></button> */ ?>
			
		<?php endif;
		
	}
}
add_action( 'woocommerce_order_item_add_action_buttons', 'woocommerce_deposits_order_action_buttons' );


function woocommerce_deposits_order_action_handler() {
	global $wpdb;

	$order_id = false;

	if( ! empty( $_GET['invoice_remaining_balance'] ) && isset( $_GET['invoice_remaining_balance_nonce'] ) && wp_verify_nonce( $_GET['invoice_remaining_balance_nonce'], 'invoice_remaining_balance' ) ) {
		$action    = 'invoice_remaining_balance';
		$order_id  = absint( $_GET['invoice_remaining_balance'] );
	}

	if ( ! $order_id ) {
		return;
	}

	$order    		= wc_get_order( $order_id );
	$item     		= false;
	$create_items 	= array();
	$currency		= $order->get_order_currency();

	foreach( $order->get_items() as $order_item_id => $order_item ) {
		if( WC_Deposits_Order_Item_Manager::is_deposit( $order_item ) ) {
			$_item = woocommerce_deposits_line_item_calculate( $order_item );

			$remaining = $_item['line_total'];

			/*
			if( $order_item['qty'] > $order_item['deposit_initial_qty'] ) {
				$paid      = $order_item['deposit_initial_qty'] * $order_item['deposit_single_amount_ex_tax'];
				$remaining = $order->get_line_total( $order_item, false ) - $paid;
			}else {
				$paid      = $order->get_line_total( $order_item, false );
				$remaining = $order_item['deposit_full_amount_ex_tax'] - $paid;
			}
			*/
		}else {
			$remaining = $order->get_line_total( $order_item, true );
		}

		$product = $order->get_product_from_item( $order_item );

		$create_items[] = array(
			'product' 	=> $product,
			'qty'		=> $order_item['qty'],
			'amount'	=> $remaining
		);
	}

	$new_order_id = wc_deposits_create_order( current_time( 'timestamp' ), $order_id, 2, $create_items, 'pending-deposit' );

	update_post_meta( $order_id, '_remaining_balance_order_id', $new_order_id );

	update_post_meta( $new_order_id, '_order_currency', $currency );

	// Email invoice
	$emails = WC_Emails::instance();
	$emails->customer_invoice( wc_get_order( $new_order_id ) );

	wp_redirect( admin_url( 'post.php?post=' . absint( $new_order_id ) . '&action=edit' ) );
	exit;
}
add_action( 'admin_init', 'woocommerce_deposits_order_action_handler' );



function woocommerce_deposits_process_deposits_in_order( $order_id ) {
	$order     = wc_get_order( $order_id );
	$parent_id = wp_get_post_parent_id( $order_id );
	$remaining = wc_deposits_order_remaining( $order_id );

	if( $parent_id ) {
		$parent_order = wc_get_order( $parent_id );
		if( $parent_order && $parent_order->has_status( 'partial-payment' ) ) {
			$paid = true;

			if( $parent_order->get_total() < $remaining ) {
				$paid = false;
			}

			if( $paid ) {
				$parent_order->update_status( 'completed', __( 'All deposit items fully paid', 'woocommerce-deposits' ) );
			}
		}
	}
}
add_action( 'woocommerce_order_status_processing', 'woocommerce_deposits_process_deposits_in_order', 10, 1 );
add_action( 'woocommerce_order_status_completed', 'woocommerce_deposits_process_deposits_in_order', 10, 1 );
add_action( 'woocommerce_order_status_on-hold', 'woocommerce_deposits_process_deposits_in_order', 10, 1 );
add_action( 'woocommerce_order_status_partial-payment', 'woocommerce_deposits_process_deposits_in_order', 10, 1 );


function woocommerce_deposits_save_order_items( $order_id, $items ) {
	$ordermanager = WC_Deposits_Order_Manager::get_instance();
	$order        = wc_get_order( $order_id );

	$remaining_balance_order_id = get_post_meta( $order_id, '_remaining_balance_order_id', true );

	if( $ordermanager->has_deposit( $order ) || ( $order && $order->has_status( 'partial-payment' ) ) ) {
		if( ! empty( $remaining_balance_order_id ) ) {
			delete_post_meta( $order_id, '_remaining_balance_order_id' );

			wp_delete_post( $remaining_balance_order_id );
		}
	}
}
add_action( 'woocommerce_saved_order_items', 'woocommerce_deposits_save_order_items', 10, 2 );


function woocommerce_deposits_order_meta( $order_id, $posted ) {
	$cartmanager = WC_Deposits_Cart_Manager::get_instance();
	$order       = wc_get_order( $order_id );

	if( $cartmanager->has_deposit( $order ) ) {
		update_post_meta( $order_id, '_original_total', $cartmanager->get_deposit_remaining_amount() + $cartmanager->get_future_payments_amount() );
		update_post_meta( $order_id, '_balance_paid', $order->get_total() );
		update_post_meta( $order_id, '_remaining_balance', $cartmanager->get_deposit_remaining_amount() );
	}
}
add_action( 'woocommerce_checkout_update_order_meta', 'woocommerce_deposits_order_meta' );


function woocommerce_deposits_order_item_values( $_product, $item, $item_id ) {
	$full_amount = isset( $item['deposit_full_amount_ex_tax'] ) ? wc_format_localized_price( $item['deposit_full_amount_ex_tax'] ) : wc_format_localized_price( $item['line_total'] );
	?>

	<td class="item_full_cost" data-full-amount="<?php print esc_attr( $full_amount ); ?>" style="display: none;">

	</td>

	<?php
}
add_action( 'woocommerce_admin_order_item_values', 'woocommerce_deposits_order_item_values', 10, 3 );


function woocommerce_deposits_line_item_calculate( $item ) {
	if( empty( $item['is_deposit'] ) )
		return $item;

	$deposit_rate = $item['deposit_single_amount_ex_tax'];
	$normal_rate  = ( $item['deposit_full_amount_ex_tax'] / $item['deposit_initial_qty'] );
	$final_rate   = $normal_rate - $deposit_rate;

	$deposit_qty = intval( $item['deposit_initial_qty'] );
	$current_qty = intval( $item['qty'] );

	$deposit_total = $deposit_rate * $deposit_qty;

	$new_qty = $deposit_qty - $current_qty;

	$total = 0;
	if( $current_qty >= $deposit_qty ) {
		for( $i = 1; $i <= $current_qty; $i++ ) {
			if( $i <= $deposit_qty ) {
				$total += $final_rate;
			}else {
				$total += $normal_rate;
			}
		}
	}else {
		$total = ( $normal_rate * $current_qty ) - $deposit_total;
	}
	

	$item['line_subtotal'] = $total;
	$item['line_total']    = $total;

	return $item;
}


function woocommerce_deposits_get_items( $items, $order ) {
	foreach( $items as $item_id => $item ) {
		if( empty( $item['is_deposit'] ) )
			continue;

		$deposit_rate = $item['deposit_single_amount_ex_tax'];
		$normal_rate  = $item['deposit_full_amount_ex_tax'];

		$deposit_qty = intval( $item['deposit_initial_qty'] );
		$current_qty = intval( $item['qty'] );

		$deposit_total = $deposit_rate * $deposit_qty;

		$new_qty = $deposit_qty - $current_qty;

		$total = 0;
		for( $i = 1; $i <= $current_qty; $i++ ) {
			if( $i <= $deposit_qty ) {
				$total += $deposit_rate;
			}else {
				$total += $normal_rate;
			}
		}

		$subtotal = floatval( $deposit_rate * $deposit_qty );

		$items[ $item_id ]['line_subtotal'] = $subtotal;
		$items[ $item_id ]['line_total'] = $total;
	}

	return $items;
}

//add_filter( 'woocommerce_order_get_items', 'woocommerce_deposits_get_items', 100, 2 );


function woocommerce_deposits_order_item_amount( $price, $order, $item, $inc_tax, $round ) {
	if( ! empty( $item['is_deposit'] ) ) {
		if( $item['qty'] !== $item['deposit_initial_qty'] ) {
			$price = $item['deposit_single_amount_ex_tax'];
		}
	}

	return $price;
}
add_filter( 'woocommerce_order_amount_item_total', 'woocommerce_deposits_order_item_amount', 10, 5 );