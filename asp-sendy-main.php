<?php

/**
 * Plugin Name: Stripe Payments Sendy Integration
 * Plugin URI: https://s-plugins.com/
 * Description: Sendy integration.
 * Version: 1.0
 * Author: Chema Garrido
 * Author URI: https://garridodiaz.com/
 * License: GPL2
 * Text Domain: asp-sendy
 * Domain Path: /languages
 */
//slug - aspsendy

if ( ! defined( 'ABSPATH' ) ) {
	exit; //Exit if accessed directly
}

class ASPSENDY_main {

	public $VERSION = '1.0';
	public $helper;
	public $ASPMain;
	public $textdomain = 'asp-sendy';
	public $file;
	public $ADDON_SHORT_NAME  = 'Sendy';
	public $ADDON_FULL_NAME   = 'Stripe Payments Sendy Integration Addon';
	public $MIN_ASP_VER       = '1.9.12';
	public $SLUG              = 'stripe-payments-sendy-integration';
	public $SETTINGS_TAB_NAME = 'Sendy';
	public $api;

	public function __construct() {
		$this->file = __FILE__;
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
		register_activation_hook( __FILE__, array( 'ASPSENDY_main', 'activate' ) );
		add_action( 'asp_subscription_ended', array( $this, 'handle_sub_ended' ), 10, 2 );
		add_action( 'asp_subscription_canceled', array( $this, 'handle_sub_ended' ), 10, 2 );
	}

	public static function activate() {
		// set default settings if needed
		$opt      = get_option( 'AcceptStripePayments-settings' );
		$defaults = array(
			'sendy_url'               	  => 'http://your_sendy_installation.com',
			'sendy_api_key'               => '',
			'sendy_enable'                => 0,
			'sendy_disable_double_opt_in' => 0,
		);
		$new_opt  = array_merge( $defaults, $opt );
		// unregister setting to prevent main plugin from sanitizing our new values
		unregister_setting( 'AcceptStripePayments-settings-group', 'AcceptStripePayments-settings' );
		update_option( 'AcceptStripePayments-settings', $new_opt );
	}

	public function plugins_loaded() {
		if ( class_exists( 'AcceptStripePayments' ) ) {
			$this->ASPMain = AcceptStripePayments::get_instance();
			$this->helper  = new ASPAddonsHelper( $this );

			if ( ! $this->helper->check_ver() ) {
				return false;
			}
			$this->helper->init_tasks();

			if ( ! is_admin() ) {
				add_filter( 'asp-button-output-data-ready', array( $this, 'data_ready' ), 10, 2 );
				add_action( 'asp_stripe_payment_completed', array( $this, 'do_sendy_signup' ), 10, 2 );
			} else {
				include_once plugin_dir_path( __FILE__ ) . 'admin/asp-sendy-admin-menu.php';
				new ASPSENDY_admin_menu();
			}
		}
	}


	public function handle_sub_ended( $post_id, $data ) {
		if ( $this->ASPMain->get_setting( 'sendy_enable' ) !== 1 ) {
			//Sendy Addon disabled
			return false;
		};

		$this->helper->log( 'Processing subscription ended hook' );

		$prod_id = get_post_meta( $post_id, 'prod_post_id', true );
		if ( empty( $prod_id ) ) {
			//no product ID set
			$this->helper->log( 'No product ID set', false );
			return false;
		}
		$unsubscribe = get_post_meta( $prod_id, 'asp_sendy_unsubscribe_sub', true );
		if ( empty( $unsubscribe ) ) {
			//unsubscribe option not set
			$this->helper->log( 'Unsubscribe option not set for this product', false );
			return false;
		}
		$cust_email = get_post_meta( $post_id, 'customer_email', true );
		if ( empty( $cust_email ) ) {
			//no customer email set
			$this->helper->log( 'No customer email set', false );
			return false;
		}

		$this->helper->log( sprintf( 'Customer email: %s', $cust_email ) );

		$sendy_url = $this->ASPMain->get_setting( 'sendy_url' );
		if ( empty( $sendy_url ) ) {
			//url not set
			$this->helper->log( 'URL not set.', false );
			return false;
		}

		$api_key = $this->ASPMain->get_setting( 'sendy_api_key' );
		if ( empty( $api_key ) ) {
			//API key not set
			$this->helper->log( 'API Key not set.', false );
			return false;
		}

		$sendy_list_id = get_post_meta( $prod_id, 'asp_sendy_list_id', true );
		if ( ! $sendy_list_id || $sendy_list_id === '' ) {
			//no Sendy List Name specified
			$this->helper->log( 'No list name specified for this product.', false );
			return false;
		}

		include_once 'lib/SendyPHP.php';

		$config = array(
				'api_key' => $api_key , 
				'installation_url' => $sendy_url,
				'list_id' => $sendy_list_id
			);
			
		$sendy = new \SendyPHP\SendyPHP($config);

		try {
			$results = $sendy->unsubscribe($cust_email);
		} catch (Exception $e) {
			$this->helper->log( $e->getMessage(), false );
			return false;
		}

		$this->helper->log($results['message'], $results['status']);
		return $results['status'];	
	}

	public function do_sendy_signup( $post_data, $charge ) {

		if ( $this->ASPMain->get_setting( 'sendy_enable' ) !== 1 ) {
			//Sendy Addon disabled
			return false;
		};

		$this->helper->log( 'Starting Sendy signup process.' );

		$id = '';

		if ( isset( $post_data['button_key'] ) && ! empty( $post_data['button_key'] ) ) {
			$id = get_transient( 'stripe-payments-sendy-post-id-' . $post_data['button_key'] );
		}

		if ( empty( $id ) && isset( $post_data['product_id'] ) && ! empty( $post_data['product_id'] ) ) {
			$id = $post_data['product_id'];
		}

		if ( ! $id || empty( $id ) ) {
			//post id not set or invalid
			$this->helper->log( 'Product ID not set or invalid. This is normal message if product has no Sendy list specified.', false );
			return false;
		}

		$post = get_post( $id );
		if ( ! $post || get_post_type( $id ) != ASPMain::$products_slug ) {
			//this is not Stripe Payments product
			$this->helper->log( sprintf( 'Post ID %s is not Stripe Payments product.', $id ), false );
			return false;
		}

		$sendy_url = $this->ASPMain->get_setting( 'sendy_url' );
		if ( empty( $sendy_url ) ) {
			//url not set
			$this->helper->log( 'URL not set.', false );
			return false;
		}

		$api_key = $this->ASPMain->get_setting( 'sendy_api_key' );
		if ( empty( $api_key ) ) {
			//API key not set
			$this->helper->log( 'API Key not set.', false );
			return false;
		}

		$sendy_list_id = get_post_meta( $id, 'asp_sendy_list_id', true );
		if ( ! $sendy_list_id || $sendy_list_id === '' ) {
			//no Sendy List ID specified
			$this->helper->log( 'No list ID specified for this product.', false );
			return false;
		}

		include_once 'lib/SendyPHP.php';

		$config = array(
				'api_key' => $api_key , 
				'installation_url' => $sendy_url,
				'list_id' => $sendy_list_id
			);
			
		$this->helper->log( 'Sendy Config ' . print_r($config,1) );

		$sendy = new \SendyPHP\SendyPHP($config);


		$api_arr = array(
			'email'		=> $post_data['stripeEmail'],
			'name'		=> $post_data['customer_name'],
			'silent'	=> ($this->ASPMain->get_setting( 'sendy_disable_double_opt_in' ) === 1)?TRUE:FALSE,
		);

		try {
			$results = $sendy->subscribe($api_arr);
		} catch (Exception $e) {
			$this->helper->log( $e->getMessage(), false );
			return false;
		}
		
		$this->helper->log($results['message'], $results['status']);
		return $results['status'];

	}

	public function data_ready( $data, $atts ) {
		if ( $this->ASPMain->get_setting( 'sendy_enable' ) !== 1 ) {
			//Sendy Addon disabled
			return $data;
		};

		if ( ! isset( $atts['product_id'] ) || empty( $atts['product_id'] ) ) {
			//post id not set or invalid
			return $data;
		}

		$id   = $atts['product_id'];
		$post = get_post( $id );
		if ( ! $post || get_post_type( $id ) != ASPMain::$products_slug ) {
			//this is not Stripe Payments product
			return $data;
		}

		$sendy_list_id = get_post_meta( $id, 'asp_sendy_list_id', true );
		if ( ! $sendy_list_id || $sendy_list_id === '' ) {
			//no Sendy List Name specified
			return $data;
		}

		if ( isset( $data['product_id'] ) && $data['product_id'] == $id ) {
			//no need to set transient - we'll have product ID available when in the $post_data array after payment is completed
		} else {
			set_transient( 'stripe-payments-sendy-post-id-' . $data['button_key'], $atts['product_id'], 24 * 3600 );
		}

		return $data;
	}

	public function order_before_insert( $post, $order_details, $charge_details ) {

	}

}

new ASPSENDY_main();
