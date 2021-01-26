<?php

class ASPSENDY_admin_menu {

	var $plugin_slug;
	var $ASPAdmin;

	function __construct() {
		$this->ASPAdmin    = AcceptStripePayments_Admin::get_instance();
		$this->plugin_slug = $this->ASPAdmin->plugin_slug;
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'asp-settings-page-after-styles', array( $this, 'after_styles' ) );
		add_action( 'asp-settings-page-after-tabs-menu', array( $this, 'after_tabs_menu' ) );
		add_action( 'asp-settings-page-after-tabs', array( $this, 'after_tabs' ) );
		add_filter( 'asp-admin-settings-addon-field-display', array( $this, 'field_display' ), 10, 2 );
		add_filter( 'apm-admin-settings-sanitize-field', array( $this, 'sanitize_settings' ), 10, 2 );

		add_action( 'asp_edit_product_metabox', array( $this, 'add_metabox' ) );
		add_action( 'asp_save_product_handler', array( $this, 'save_product' ), 10, 3 );
	}

	function save_product( $post_id, $post, $update ) {
		if ( isset( $_POST['asp_sendy_list_id'] ) ) {
			update_post_meta( $post_id, 'asp_sendy_list_id', sanitize_text_field( $_POST['asp_sendy_list_id'] ) );
		}
		$unsubscribe = filter_input( INPUT_POST, 'asp_sendy_unsubscribe_sub', FILTER_SANITIZE_NUMBER_INT );
		update_post_meta( $post_id, 'asp_sendy_unsubscribe_sub', $unsubscribe );
	}

	function add_metabox() {
		add_meta_box( 'asp_sendy_metabox', __( 'Sendy Integration', 'asp-sendy' ), array( $this, 'display_metabox' ), ASPMain::$products_slug, 'normal', 'default' );
	}

	function display_metabox( $post ) {
		$ASP = AcceptStripePayments::get_instance();
		if ( $ASP->get_setting( 'sendy_enable' ) !== 1 ) {
			_e( 'Sendy Integration addon is disabled. It must be enabled <a href="edit.php?post_type=asp-products&page=stripe-payments-settings#Sendy" target="_blank">in the settings</a> before you can configure it for this product.', 'asp-sendy' );
			return false;
		}
		$current_val = get_post_meta( $post->ID, 'asp_sendy_list_id', true );
		?>
	<label>Sendy List ID:</label><br/>
	<input type="text" name="asp_sendy_list_id" size="50" value="<?php echo $current_val; ?>">
	<p class="description"><?php _e( 'Specify Sendy List ID where users will be added to after successful purchase of this product. Mandatory', 'asp-sendy' ); ?></p>
		
		<?php
		//check if address collection is enabled and display notice if it's not
		$collect_billing_addr = get_post_meta( $post->ID, 'asp_product_collect_billing_addr', true );
		if ( $collect_billing_addr != '1' ) {
			echo sprintf( '<p>%s<p>', __( 'Note: billing address collection is disabled for this product. Customer name won\'t be collected and passed to Sendy.', 'asp-sendy' ) );
		}
		$sub_id = get_post_meta( $post->ID, 'asp_sub_plan_id', true );
		if ( ! empty( $sub_id ) ) {
			//this is subscription product. Let's add option to unsubscribe users from subscription if subscription is cancelled or expired
			$unsubscribe = get_post_meta( $post->ID, 'asp_sendy_unsubscribe_sub', true );
			?>
		<label><input type="checkbox" name="asp_sendy_unsubscribe_sub" value="1"<?php echo ! empty( $unsubscribe ) ? ' checked' : ''; ?>><?php _e( 'Unsubscribe Customer If Subscription Ended', 'asp-sendy' ); ?></label>
		<p class="description"><?php _e( 'Enable this to unsubscribe customer from the list if Stripe subscription eneded (cancelled or expired).', 'asp-sendy' ); ?></p>
			<?php
			//check if API Secret is set. It's required for unsubscribe process to work properly
			$this->ASPMain = AcceptStripePayments::get_instance();
			//check if Subscription addon version is >= 1.5.4
			$req_ver = '1.5.4';
			if ( class_exists( 'ASPSUB_main' ) && version_compare( ASPSUB_main::ADDON_VER, $req_ver ) < 0 ) {
				?>
		<p style="color: red;"><?php printf( __( 'Stripe Subscriptions addon version %1$s required for unsubscription option to work properly. You have version %2$s installed. Please update Stripe Subscriptions addon.', 'asp-sendy' ), $req_ver, ASPSUB_main::ADDON_VER ); ?></p>
				<?php
			}
		}
	}

	function sanitize_settings( $output, $input ) {

		$output['sendy_enable'] = isset( $input['sendy_enable'] ) ? 1 : 0;

		$output['sendy_disable_double_opt_in'] = isset( $input['sendy_disable_double_opt_in'] ) ? 1 : 0;

		$output['sendy_api_key'] = isset( $input['sendy_api_key'] ) ? sanitize_text_field( $input['sendy_api_key'] ) : '';

		if ( $output['sendy_enable'] === 1 && empty( $output['sendy_api_key'] ) ) {
			add_settings_error( 'AcceptStripePayments-settings', 'sendy_api_key', __( 'Sendy: you need to enter your API Key in order for the integration to work.', 'asp-sendy' ) );
		}

		$output['sendy_url'] = isset( $input['sendy_url'] ) ? sanitize_text_field( $input['sendy_url'] ) : '';

		if ( $output['sendy_enable'] === 1 && empty( $output['sendy_url'] ) ) {
			add_settings_error( 'AcceptStripePayments-settings', 'sendy_url', __( 'Sendy: you need to enter your Sendy Installation URL in order for the integration to work.', 'asp-sendy' ) );
		}

		return $output;
	}

	function field_display( $field, $field_value ) {
		$ret = array();
		switch ( $field ) {
			case 'sendy_enable':
			case 'sendy_disable_double_opt_in':
				$ret['field']      = 'checkbox';
				$ret['field_name'] = $field;
				break;
		}
		if ( ! empty( $ret ) ) {
			return $ret;
		} else {
			return $field;
		}
	}

	function register_settings() {
		add_settings_section( 'AcceptStripePayments-sendy-section', __( 'Sendy Integration Settings', 'asp-sendy' ), null, $this->plugin_slug . '-sendy' );

		add_settings_field(
			'sendy_enable',
			__( 'Enable Sendy Integration', 'asp-sendy' ),
			array( &$this->ASPAdmin, 'settings_field_callback' ),
			$this->plugin_slug . '-sendy',
			'AcceptStripePayments-sendy-section',
			array(
				'field' => 'sendy_enable',
				'desc'  => __( 'Enable Sendy integration.', 'asp-sendy' ),
			)
		);

		add_settings_field(
			'sendy_url',
			__( 'Sendy Installation URL', 'asp-sendy' ),
			array( &$this->ASPAdmin, 'settings_field_callback' ),
			$this->plugin_slug . '-sendy',
			'AcceptStripePayments-sendy-section',
			array(
				'field' => 'sendy_url',
				'desc'  => __( 'Your Sendy installation URL. Ex: http://your_sendy_installation', 'asp-sendy' ),
			)
		);

		add_settings_field(
			'sendy_api_key',
			__( 'Sendy API Key', 'asp-sendy' ),
			array( &$this->ASPAdmin, 'settings_field_callback' ),
			$this->plugin_slug . '-sendy',
			'AcceptStripePayments-sendy-section',
			array(
				'field' => 'sendy_api_key',
				'desc'  => __( 'Your Sendy API key. You can use existing key or generate a new one for this integration from your Sendy account.', 'asp-sendy' ),
			)
		);


		add_settings_field(
			'sendy_disable_double_opt_in',
			__( 'Disable Double Opt-In', 'asp-sendy' ),
			array( &$this->ASPAdmin, 'settings_field_callback' ),
			$this->plugin_slug . '-sendy',
			'AcceptStripePayments-sendy-section',
			array(
				'field' => 'sendy_disable_double_opt_in',
				'desc'  => __( 'Disables double opt-in for Sendy signup.', 'asp-sendy' ),
			)
		);
	}

	function after_styles() {
		?>
	<style>

	</style>
		<?php
	}

	function after_tabs_menu() {
		?>
	<a href="#Sendy" data-tab-name="Sendy" class="nav-tab"><?php echo __( 'Sendy', 'asp-sendy' ); ?></a>
		<?php
	}

	function after_tabs() {
		?>
	<div class="wp-asp-tab-container asp-apm-container" data-tab-name="Sendy">
		<?php do_settings_sections( $this->plugin_slug . '-sendy' ); ?>
	</div>
		<?php
	}

}