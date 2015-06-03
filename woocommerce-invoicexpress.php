<?php
/*
 Plugin Name: WooCommerce InvoiceXpress Extension
Plugin URI: http://woothemes.com/woocommerce
Description: Automatically create InvoiceXpress invoices when sales are made.
Version: 0.10
Author: WidgiLabs
Author URI: http://www.widgilabs.com
License: GPLv2
*/

/**
 * Required functions
 **/
if ( ! function_exists( 'is_woocommerce_active' ) ) require_once( 'woo-includes/woo-functions.php' );

if (is_woocommerce_active()) {
	
	add_action('plugins_loaded', 'woocommerce_invoicexpress_init', 0);
	
	function woocommerce_invoicexpress_init() {
		$woocommerce_invoicexpress = new woocommerce_invoicexpress;
	}
	
	class woocommerce_invoicexpress {
		function __construct() {
			require_once('InvoiceXpressRequest-PHP-API/lib/InvoiceXpressRequest.php');
	
			$this->subdomain 	= get_option('wc_ie_subdomain');
			$this->token 		= get_option('wc_ie_api_token');
	
			add_action('admin_init',array(&$this,'settings_init'));
			add_action('admin_menu',array(&$this,'menu'));
			//add_action('woocommerce_checkout_order_processed',array(&$this,'process')); // Check if user is InvoiceXpress client (create if not) and create invoice.
			
			//add_action('woocommerce_order_status_processing',array(&$this,'process'));
			//add_action('woocommerce_order_status_completed',array(&$this,'process'));
			
			//add_action('woocommerce_order_actions', array(&$this,'my_woocommerce_order_actions'), 10, 1);
			//add_action('woocommerce_order_action_my_action', array(&$this,'do_my_action'), 10, 1);			
		}
		
		function my_woocommerce_order_actions($actions) {
			$actions['my_action'] = "Create Invoice (InvoiceXpress)";
			return $actions;
		}
		
		
		function menu() {
			add_submenu_page('woocommerce', __('InvoiceXpress', 'wc_invoicexpress'),  __('InvoiceXpress', 'wc_invoicexpress') , 'manage_woocommerce', 'woocommerce_invoicexpress', array(&$this,'options_page'));
		}
		
		function settings_init() {
			global $woocommerce;
			wp_enqueue_style('woocommerce_admin_styles', $woocommerce->plugin_url().'/assets/css/admin.css');
		
			$settings = array(
					array(
							'name'		=> 'wc_ie_settings',
							'title' 	=> __('InvoiceXpress for WooCommerce Settings','wc_invoicexpress'),
							'page'		=> 'woocommerce_invoicexpress',
							'settings'	=> array(
									array(
											'name'		=> 'wc_ie_subdomain',
											'title'		=> __('Subdomain','wc_invoicexpress'),
									),
									array(
											'name'		=> 'wc_ie_api_token',
											'title'		=> __('API Token','wc_invoicexpress'),
									),
									array(
											'name'		=> 'wc_ie_create_invoice',
											'title'		=> __('Create Invoice','wc_invoicexpress'),
									),
									array(
											'name'		=> 'wc_ie_send_invoice',
											'title'		=> __('Send Invoice','wc_invoicexpress'),
									),
									array(
											'name'		=> 'wc_ie_create_simplified_invoice',
											'title'		=> __('Create Simplified Invoice','wc_invoicexpress'),
									)
							),
					),
			);
		
			foreach($settings as $sections=>$section) {
				add_settings_section($section['name'],$section['title'],array(&$this,$section['name']),$section['page']);
				foreach($section['settings'] as $setting=>$option) {
					add_settings_field($option['name'],$option['title'],array(&$this,$option['name']),$section['page'],$section['name']);
					register_setting($section['page'],$option['name']);
					$this->$option['name'] = get_option($option['name']);
				}
			}
		
		}
		
		
		function wc_ie_settings() {
			echo '<p>'.__('Please fill in the necessary settings below. InvoiceXpress for WooCommerce works by creating an invoice when order status is updated to processing.','wc_invoicexpress').'</p>';
		}
		function wc_ie_subdomain() {
			echo '<input type="text" name="wc_ie_subdomain" id="wc_ie_subdomain" value="'.get_option('wc_ie_subdomain').'" />';
			echo ' <label for="wc_ie_subdomain">When you access InvoiceXpress you use https://<b>subdomain</b>.invoicexpress.com</label>';
		}
		function wc_ie_api_token() {
			echo '<input type="password" name="wc_ie_api_token" id="wc_ie_api_token" value="'.get_option('wc_ie_api_token').'" />';
			echo ' <label for="wc_ie_api_token">Go to Settings >> API in InvoiceXpress to get one.</label>';
		}
		function wc_ie_create_invoice() {
			$checked = (get_option('wc_ie_create_invoice')==1) ? 'checked="checked"' : '';
			echo '<input type="hidden" name="wc_ie_create_invoice" value="0" />';
			echo '<input type="checkbox" name="wc_ie_create_invoice" id="wc_ie_create_invoice" value="1" '.$checked.' />';
			echo ' <label for="wc_ie_create_invoice">Create invoices for orders that come in, otherwise only the client is created (<i>recommended</i>).</label>';
		}
		function wc_ie_send_invoice() {
			$checked = (get_option('wc_ie_send_invoice')==1) ? 'checked="checked"' : '';
			echo '<input type="hidden" name="wc_ie_send_invoice" value="0" />';
			echo '<input type="checkbox" name="wc_ie_send_invoice" id="wc_ie_send_invoice" value="1" '.$checked.' />';
			echo ' <label for="wc_ie_send_invoice">Send the client an e-mail with the order invoice attached (<i>recommended</i>).</label>';
		}

		function wc_ie_create_simplified_invoice() {
			$checked = (get_option('wc_ie_create_simplified_invoice')==1) ? 'checked="checked"' : '';
			echo '<input type="hidden" name="wc_ie_create_simplified_invoice" value="0" />';
			echo '<input type="checkbox" name="wc_ie_create_simplified_invoice" id="wc_ie_create_simplified_invoice" value="1" '.$checked.' />';
			echo ' <label for="wc_ie_create_simplified_invoice">Create simplified invoices. Only available for Portuguese accounts.</label>';
		}
		
		
		function options_page() { ?>



			<div class="wrap woocommerce">



			<form method="post" id="mainform" action="options.php">
			<div class="icon32 icon32-woocommerce-settings" id="icon-woocommerce"><br /></div>
			<h2><?php _e('InvoiceXpress for WooCommerce','wc_invoicexpress'); ?></h2>


			<?php settings_fields('woocommerce_invoicexpress'); ?>

			<?php

			if ($this->subdomain == "" || $this->token == ""):
			?>
			<div class="updated woocommerce-message below-h2">
					<p>Fill in your subdomain and API token. </p>
			</div>
			<?php
			else:
				// test connection to InvoiceXpress
				InvoiceXpressRequest::init($this->subdomain, $this->token);

				$test = new InvoiceXpressRequest('top-clients.get');	
				$test->post(array());
				$test->request();

				if($test->success()) :
					$response = $test->getResponse();

					?>
					<div class="updated woocommerce-message below-h2">
						<p>You've established connection to InvoiceXpress successfully. </p>
						<p><b>Your top clients so far are:</b><br/>
						<?php
							foreach ($response["client"] as $client) {
								echo $client["name"]."<br/>";
							}
						?>

						</p>
						<p><a target="_blank" href="http://widgilabs.pt/plugin-woocommerce-invoicexpress-pro/">Upgrade to WooCommerce InvoiceXpress Pro »</a> to start creating invoices automatically right away. </p>
					</div>
				<?php else: ?>
						<div class="updated woocommerce-message below-h2">
						<p>Connection to InvoiceXpress NOT OK. </p>
						<p>Possible cause: Your subdomain should be .app, e.g. example.app<br/>

						</p>
						<p><a target="_blank" href="http://widgilabs.pt/plugin-woocommerce-invoicexpress-pro/">Upgrade to WooCommerce InvoiceXpress Pro »</a> to start creating invoices automatically right away. </p>
					</div>		
				<?php endif; ?>
			<?php endif; ?>

			<?php do_settings_sections('woocommerce_invoicexpress'); ?>
			<p class="submit"><input type="submit" class="button-primary" value="Save Changes" /></p>
			</form>
			</div>
		<?php }
	}
}