<?php
/*
 Plugin Name: WooCommerce InvoiceXpress Extension
Plugin URI: http://woothemes.com/woocommerce
Description: Automatically create InvoiceXpress invoices when sales are made.
Version: 0.1
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
			add_action('woocommerce_payment_complete',array(&$this,'payment')); // Check if user is InvoiceXpress client (create if not) and create invoice.
	
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
							'title' 	=> __('InvoiceXpress Settings','wc_invoicexpress'),
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
											'name'		=> 'wc_ie_create_client',
											'title'		=> __('Create Client','wc_invoicexpress'),
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
											'name'		=> 'wc_ie_add_payments',
											'title'		=> __('Add Payments','wc_invoicexpress'),
									),
									array(
											'name'		=> 'wc_ie_send_method',
											'title'		=> __('Invoice Send Method','wc_invoicexpress'),
									),
									array(
											'name'		=> 'wc_ie_inv_num_prefix',
											'title'		=> __('Invoice Number Prefix','wc_invoicexpress'),
									),
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
			echo '<p>'.__('You can find this information in the "Settings > API" section of your InvoiceXpress account.','wc_invoicexpress').'</p>';
		}
		function wc_ie_subdomain() {
			echo '<input type="text" name="wc_ie_subdomain" id="wc_ie_subdomain" value="'.get_option('wc_ie_subdomain').'" />';
		}
		function wc_ie_api_token() {
			echo '<input type="password" name="wc_ie_api_token" id="wc_ie_api_token" value="'.get_option('wc_ie_api_token').'" />';
		}
		function wc_ie_create_client() {
			$checked = (get_option('wc_ie_create_client')==1) ? 'checked="checked"' : '';
			echo '<input type="hidden" name="wc_ie_create_client" value="0" />';
			echo '<input type="checkbox" name="wc_ie_create_client" id="wc_ie_create_client" value="1" '.$checked.' />';
		}
		function wc_ie_create_invoice() {
			$checked = (get_option('wc_ie_create_invoice')==1) ? 'checked="checked"' : '';
			echo '<input type="hidden" name="wc_ie_create_invoice" value="0" />';
			echo '<input type="checkbox" name="wc_ie_create_invoice" id="wc_ie_create_invoice" value="1" '.$checked.' />';
		}
		function wc_ie_send_invoice() {
			$checked = (get_option('wc_ie_send_invoice')==1) ? 'checked="checked"' : '';
			echo '<input type="hidden" name="wc_ie_send_invoice" value="0" />';
			echo '<input type="checkbox" name="wc_ie_send_invoice" id="wc_ie_send_invoice" value="1" '.$checked.' />';
		}
		function wc_ie_add_payments() {
			$checked = (get_option('wc_ie_add_payments')==1) ? 'checked="checked"' : '';
			echo '<input type="hidden" name="wc_ie_add_payments" value="0" />';
			echo '<input type="checkbox" name="wc_ie_add_payments" id="wc_ie_add_payments" value="1" '.$checked.' />';
		}
		function wc_ie_send_method() {
			$options = array(
					'Email' => __('Email','wc_invoicexpress'),
					'SnailMail' => __('Snail Mail','wc_invoicexpress'),
			);
			echo '<select name="wc_ie_send_method">';
			foreach($options as $option=>$title) {
				$checked = (get_option('wc_ie_send_method')==$option) ? 'selected="selected"' : '';
				echo '<option value="'.$option.'" '.$checked.'>'.$title.'</option>';
			}
			echo '</select>';
		}
		function wc_ie_inv_num_prefix() {
			echo '<input type="text" name="wc_ie_inv_num_prefix" id="wc_ie_inv_num_prefix" value="'.get_option('wc_ie_inv_num_prefix').'" />';
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
		
			error_log("estou aqui process");
			
			InvoiceXpressRequest::init($this->subdomain, $this->token);
		
			$order = new woocommerce_order($order_id);
		
			$client_id = get_user_meta($order->user_id, 'wc_ie_client_id', true);
			$client_name = $order->billing_first_name." ".$order->billing_last_name;
			
			// Lets get the user's InvoiceXpress data
			if($client_id == '' && get_option('wc_ie_create_client')==1) {
				$data = array(
						'client' => array(
								'name'			=> $client_name,
								//'organization'	=> $order->billing_company,
								'email'			=> $order->billing_email,
								'phone'			=> $order->billing_phone,
								'address'		=> $order->billing_address_1."\n".
												   $order->billing_address_2."\n",								
								//'p_street2'		=> $order->billing_address_2,
								//'p_city'		=> $order->billing_city,
								//'p_state'		=> $order->billing_state,
								'postal_code'	=> $order->billing_postcode . " - " . $order->billing_city,
								'country'		=> 'Portugal',
								'send_options'	=> 1
						),
				);
				error_log("clients.create");

				$client = new InvoiceXpressRequest('clients.create');
				$client->post($data);
				$client->request();
				if($client->success()) {
					$response = $client->getResponse();
					$client_id = $response['id'];
					$order->add_order_note(__('Client created in InvoiceXpress','wc_invoicexpress').' #'.$client_id);
					update_user_meta($order->user_id,'wc_ie_client_id',$client_id);
				} else {
					$order->add_order_note(__('InvoiceXpress Client (Create) API Error','wc_invoicexpress').': '.$client->getError());
				}
			} else {
				error_log("clients.get");
				$client = new InvoiceXpressRequest('clients.get');
				$client->post($data);
				$client->request($client_id);
				
				if($client->success()) {
					$response = $client->getResponse();
					$client_id = $response['id'];
				} else {
					$client_id = '';
					$order->add_order_note(__('InvoiceXpress Client (Get) API Error','wc_invoicexpress').': '.$client->getError());
				}
			}
			
			if(intval($client_id) > 0) {
				if(get_option('wc_ie_create_invoice')==1) {
					foreach($order->get_items() as $item) {
						$debug = print_r($item, true);
						error_log("Carrinho = ".$debug);
						
						$items[] = array(
								'name'			=> $item['name'],
								'description'	=> '('.$item['qty'].') '.$item['name'],
								'unit_price'		=> $item['line_total'],
								'quantity'		=> 1,
								'unit'			=> 'unit',
								'tax'			=> array(
										'name'	=> 'IVA23'
								)
						);
						$items[] = array(
								'name'			=> 'Manuseamento e Transporte',
								'description'	=> 'Manuseamento e Transporte',
								'unit_price'	=> $order->get_shipping(),
								'quantity'		=> 1,
								'unit'			=> 'unit',
								'tax'			=> array(
										'name'	=> 'IVA23'
								)
						);
					}	
					
					
					/*
					$items[] = array(
							'description'	=> 'Taxes',
							'unit_cost'		=> $order->get_total_tax(),
							'quantity'		=> 1,
					);*/
					$data = array(
							'simplified_invoice' => array(
									'date'	=> $order->completed_date,
									'client' => array( 'name' => $client_name, 'code' => $client_id ),
									'items'		=> array(
											'item'	=> $items
									)
							)
					);
					
					// @TODO: invoice number prefix
					//if(get_option('wc_ie_inv_num_prefix') != '') $data['invoice']['number'] = get_option('wc_ie_inv_num_prefix').$order_id;

					$invoice = new InvoiceXpressRequest('simplified_invoices.create');
		
					$invoice->post($data);
					$invoice->request();
					if($invoice->success()) {
						$response = $invoice->getResponse();
						$invoice_id = $response['id'];
						$order->add_order_note(__('Client invoice in InvoiceXpress','wc_invoicexpress').' #'.$invoice_id);
						add_post_meta($order_id, 'wc_ie_inv_num', $invoice_id, true);
						
						// extra request to change status to final
						$invoice = new InvoiceXpressRequest('simplified_invoices.change-state');
						$data = array('invoice' => array('state'	=> 'finalized'));
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
									'subject' => 'PopyBox Factura de Pagamento',
									'body' => 'Por favor encontre a sua factura em anexo. Pode guardar este documento como prova do seu pagamento. Obrigado.'									
									)
							);
		
					$send_invoice = new InvoiceXpressRequest('simplified_invoices.email-invoice');
					$send_invoice->post($data);
					$send_invoice->request($invoice_id);
					
					if($send_invoice->success()) {
						$response = $send_invoice->getResponse();
						$order->add_order_note(__('Client invoice sent from InvoiceXpress','wc_invoicexpress').' ('.get_option('wc_ie_send_method').')');
					} else {
						$order->add_order_note(__('InvoiceXpress Send Invoice API Error','wc_invoicexpress').': '.$send_invoice->getError());
					}
				}
				
			}
		}
		
		function payment($order_id) {
			InvoiceXpressRequest::init($this->subdomain, $this->token);
			$order = new woocommerce_order ($order_id);
		
			$invoice_id = get_post_meta($order_id,'wc_ie_inv_num',true);
		
			if(get_option('wc_ie_add_payments')==1 && isset($invoice_id) && $invoice_id != '') {
				$data = array(
						'payment' => array(
								'invoice_id'	=> $invoice_id,
								'amount'		=> $order->order_total,
								'type'			=> 'Credit'
						),
				);
				$payment = new InvoiceXpressRequest('payment.create');
		
				$payment->post($data);
				$payment->request();
				if($payment->success()) {
					$response = $payment->getResponse();
					$payment_id = $response['payment_id'];
					$order->add_order_note(__('Payment posted to InvoiceXpress','wc_invoicexpress').' #'.$payment_id);
				} else {
					$order->add_order_note(__('InvoiceXpress Payment API Error','wc_invoicexpress').': '.$payment->getError());
				}
			}
		}
		
	}
}