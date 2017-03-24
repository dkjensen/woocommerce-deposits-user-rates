jQuery( function($) {

	$( '#woocommerce-order-items' ).on( 'click', 'a.delete-order-item', function(e) {
		// Save and re-calculate totals
		$( 'button.save-action' ).click();
	});

	$( '#woocommerce-order-items' ).on( 'click', 'button.calculate-remaining', function(e) {
		$( '#woocommerce-order-items' ).block();

		// Get row totals
		var line_totals    = 0;
		var tax            = 0;
		var shipping       = 0;
		var paid		   = $( '.total.paid').attr( 'data-paid' );
		var remaining      = 0 - paid;

		$( '.woocommerce_order_items tr.item, .woocommerce_order_items tr.fee' ).each(function() {
			var line_total = $( this ).find( 'input.line_total' ).val() || '0';
			line_totals    = line_totals + accounting.unformat( line_total.replace( ',', '.' ) );
		});

		remaining = line_totals + remaining;

		$( 'input[name=_balance_remaining]' ).val( accounting.formatNumber( remaining, woocommerce_admin_meta_boxes.currency_format_num_decimals, '', woocommerce_admin.mon_decimal_point ) );

		$( 'button.save-action' ).trigger( 'click' );

		//$( '#woocommerce-order-items' ).unblock();

		return false;
	});

});