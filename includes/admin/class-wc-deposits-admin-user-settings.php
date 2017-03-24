<?php

if( ! defined( 'ABSPATH' ) ) {
    exit;
}


class WC_Deposits_Admin_User_Settings {
    public function __construct() {
    
        add_action( 'edit_user_profile', array( $this, 'edit_profile_fields' ) );
        add_action( 'show_user_profile', array( $this, 'edit_profile_fields' ) );

        add_action( 'edit_user_profile_update', array( $this, 'save_profile_fields' ) );
        add_action( 'personal_options_update', array( $this, 'save_profile_fields' ) );
    }


    public function edit_profile_fields( $user ) {
    ?>
        
    <table class="form-table">
      <tr id="deposit-amount" class="user-deposit-amount-wrap">
        <th><label for="deposit-amount"><?php _e( 'Deposit Amount' ); ?></label></th>
        <td>
          <input type="text" id="deposit-amount" name="deposit_amount" class="regular-text" value="<?php print esc_attr( get_user_meta( $user->ID, 'deposit_amount', true ) ); ?>" />
          <p class="description"><?php _e( 'Deposit Required (%)', 'woocommerce-deposits' ); ?></p>
        </td>
      </tr>
    </table>

    <?php  
    }

    public function save_profile_fields( $user_id ) {
        update_user_meta( $user_id, 'deposit_amount', intval( $_POST['deposit_amount'] ) );
    }

}
new WC_Deposits_Admin_User_Settings();