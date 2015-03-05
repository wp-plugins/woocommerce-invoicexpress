<?php
/*
 Plugin Name: WooCommerce InvoiceXpress Extension
Plugin URI: http://woothemes.com/woocommerce
Description: Automatically create InvoiceXpress invoices when sales are made.
Version: 0.9
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
			
			add_action('woocommerce_order_status_processing',array(&$this,'process'));
			//add_action('woocommerce_order_status_completed',array(&$this,'process'));
			
			add_action('woocommerce_order_actions', array(&$this,'my_woocommerce_order_actions'), 10, 1);
			add_action('woocommerce_order_action_my_action', array(&$this,'do_my_action'), 10, 1);			
		}
		
		function my_woocommerce_order_actions($actions) {
			$actions['my_action'] = "Create Invoice (InvoiceXpress)";
			return $actions;
		}
		

		function do_my_action($order) {
			// Do something here with the WooCommerce $order object
			$this->process($order->id);
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
			echo ' <label for="wc_ie_subdomain">When you access InvoiceXpress you use https://<b>subdomain</b>.invoicexpress.net</label>';
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
			<?php do_settings_sections('woocommerce_invoicexpress'); ?>
			<p class="submit"><input type="submit" class="button-primary" value="Save Changes" /></p>
			</form>
			</div>
		<?php }
		
		function process($order_id) {
			
			InvoiceXpressRequest::init($this->subdomain, $this->token);
		
			$order = new WC_Order($order_id);
		
			$client_name = $order->billing_first_name." ".$order->billing_last_name;
			
			$countries = new WC_Countries();
			$countries_list = $countries->countries;
			$country = $countries_list[$order->billing_country];
			
			$vat='';
			if(isset($order->billing_company))
				$vat =  $order->billing_company;
			
			
			$client_email = $order->billing_email;
			
			// Lets get the user's InvoiceXpress data
			
			$client_data = array(
								'name' => $client_name. " (".$client_email.")",
								'email' => $client_email,
								'phone' => $order->billing_phone,
								'address' => $order->billing_address_1."\n".$order->billing_address_2."\n", 
								'postal_code' => $order->billing_postcode . " - " . $order->billing_city,
								'country' => $country,
								'fiscal_id' => $vat,
								'send_options' => 3
								
								);
			
			if(get_option('wc_ie_create_invoice')==1) {
				foreach($order->get_items() as $item) {						
					$pid = $item['item_meta']['_product_id'][0];
					
					$prod = get_product($pid);
					
					$items[] = array(
							'name'			=> "#".$pid, //$item['name'],
							'description'	=> get_the_title($pid), //'('.$item['qty'].') '.$item['name'],
							'unit_price'	=> $prod->price / 1.23, // subtract tax 23%
							'quantity'		=> $item['qty'],
							'unit'			=> 'unit',
							'tax'			=> array(
									'name'	=> 'IVA23'
							)
					);
				}
				// divide the value of shipping per 1.23 so that when
				// invoicexpress calculates the tax is correct
				$shipping_unit_price = 	$order->get_total_shipping() / 1.23;
				$items[] = array(
						'name'			=> 'Envio',
						'description'	=> 'Custos de Envio',
						'unit_price'	=> $shipping_unit_price,
						'quantity'		=> 1,
						'tax'			=> array(
								'name'	=> 'IVA23'
						)
				);
				
				if(get_option('wc_ie_create_simplified_invoice')==1) {
					$data = array(
							'simplified_invoice' => array(
									'date'	=> $order->completed_date,
									'due_date' => $order->completed_date,
									'client' => $client_data,
									'reference' => $order_id,
									'items'		=> array(
											'item'	=> $items
									)
							)
					);
				} else {
					$data = array(
							'invoice' => array(
									'date'	=> $order->completed_date,
									'due_date' => $order->completed_date,
									'client' => $client_data,
									'reference' => $order_id,
									'items'		=> array(
											'item'	=> $items
									)
							)
					);
				}
									
				if(get_option('wc_ie_create_simplified_invoice')==1) {
					$invoice = new InvoiceXpressRequest('simplified_invoices.create');						
				} else {
					$invoice = new InvoiceXpressRequest('invoices.create');
				}
	
				$invoice->post($data);
				$invoice->request();
				if($invoice->success()) {
					$response = $invoice->getResponse();
					$invoice_id = $response['id'];
					$order->add_order_note(__('Client invoice in InvoiceXpress','wc_invoicexpress').' #'.$invoice_id);
					add_post_meta($order_id, 'wc_ie_inv_num', $invoice_id, true);
					
					// extra request to change status to final
					if(get_option('wc_ie_create_simplified_invoice')==1) {
						$invoice = new InvoiceXpressRequest('simplified_invoices.change-state');
					} else {
						$invoice = new InvoiceXpressRequest('invoices.change-state');
					}
					$data = array('invoice' => array('state'	=> 'finalized'));
					$invoice->post($data);
					$invoice->request($invoice_id);
					
					if($invoice->success()) { // keep the invoice sequence number in a meta
						$response = $invoice->getResponse();
						$inv_seq_number = $response['sequence_number'];
						add_post_meta($order_id, 'wc_ie_inv_seq_num', $inv_seq_number, true);
					}
					
					$data = array('invoice' => array('state'	=> 'settled'));
					$invoice->post($data);
					$invoice->request($invoice_id);
				} else {
					$order->add_order_note(__('InvoiceXpress Invoice API Error:','wc_invoicexpress').': '.$invoice->getError());
				}
			}
			
			if(get_option('wc_ie_send_invoice')==1 && isset($invoice_id)) {
				$data = array(
						'message' => array(
								'client' => array(
										'email' => $order->billing_email,
										'save' => 1
										),
								'subject' => __('Order Invoice','wc_invoicexpress'),
								'body' => __('Please find your invoice in attach. Archive this e-mail as proof of payment.','wc_invoicexpress')
								)
						);
	
				if(get_option('wc_ie_create_simplified_invoice')==1) {
					$send_invoice = new InvoiceXpressRequest('simplified_invoices.email-invoice');
				} else {
					$send_invoice = new InvoiceXpressRequest('invoices.email-invoice');
				}
				$send_invoice->post($data);
				$send_invoice->request($invoice_id);
				
				if($send_invoice->success()) {
					$response = $send_invoice->getResponse();
					$order->add_order_note(__('Client invoice sent from InvoiceXpress','wc_invoicexpress'));
				} else {
					$order->add_order_note(__('InvoiceXpress Send Invoice API Error','wc_invoicexpress').': '.$send_invoice->getError());
				}
			}
			
		}
		
		
	}
}