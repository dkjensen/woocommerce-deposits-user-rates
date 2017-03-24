jQuery(document).ready(function($) {
  /*
  var options = wc_deposits_add_to_cart_options;
  var form = $('#wc-deposits-options-form');
  var deposit = form.find('#pay-deposit');
  var full = form.find('#pay-full-amount');
  var msg = form.find('#wc-deposits-notice');
  var amount = form.find('#deposit-amount');
  var original_amount = amount.html();
  var update_message = function() {
    if (deposit.is(':checked')) {
      msg.html(options.message.deposit);
    } else if (full.is(':checked')) {
      msg.html(options.message.full);
    }
  };
  var update_variation = function(event, variation) {
    var id = variation.variation_id;
    if (typeof options.variations !== typeof undefined) {
      if (typeof options.variations[id] !== typeof undefined) {
        amount.html(options.variations[id]);
        return;
      }
    }
    amount.html(original_amount);
  };
  $('.cart').on('change', 'input, select', update_message);
  $('.variations_form')
    .on('show_variation', update_variation)
    .on('click', '.reset_variations', function() { amount.html(original_amount); });
  update_message();
  */

  $( document ).on(
    'change',
    '.payment-total-option',
    function(e) {
      var target = e.currentTarget;

      var payment_total_option = $( 'input.payment-total-option:checked' ).val();

      var $form = $( '.shop_table.cart' ).closest( 'form' );

      $( 'div.cart_totals' ).addClass( 'processing' ).block( {
        message: null,
        overlayCSS: {
          background: '#fff',
          opacity: 0.6
        }
      } );

      $form.block( {
        message: null,
        overlayCSS: {
          background: '#fff',
          opacity: 0.6
        }
      } );

      var data = {
        action: 'cart_update_payment_total_option',
        security: wc_deposits_add_to_cart_options.update_payment_total_option_nonce,
        payment_total_option: payment_total_option
      };

      $.ajax( {
        type:     'post',
        url:      ajaxurl,
        data:     data,
        dataType: 'html',
        success:  function( response ) {
          
        },
        complete: function() {
          $( 'div.cart_totals' ).unblock();
          $form.unblock();
          $( document ).trigger( 'wc_update_cart' );
        }
      } );
    }
  );
  
});
