<?php

/*
Plugin Name: iPay88 Payment Gateway
Plugin URI: #
Description: iPay88 Payment Gateway
Version: TEST
Author: Arya Wiratama
Author URI: #
 
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/
 
add_action('plugins_loaded', 'woocommerce_gateway_ipay88_init', 0);

function woocommerce_gateway_ipay88_init() 
{
	
		if ( !class_exists( 'WC_Payment_Gateway' ) ) return;
	 
		/**
		 * Localisation
		 */
		load_plugin_textdomain('wc-gateway-name', false, dirname( plugin_basename( __FILE__ ) ) . '/languages');
		
		/**
		 * Gateway class
		 */
		class WC_ipay88_Gateway extends WC_Payment_Gateway 
		{
				public function __construct() 
				{				
						$this->id = 'ipay88';
						$this->has_fields = true;     // false

						$this->init_form_fields();
						$this->init_settings();
						
						$this->title       = $this->settings['name'];
						$this->description = 'Pay With iPay88.';						
						
						if ( empty($this->settings['server_dest']) || $this->settings['server_dest'] == '0' || $this->settings['server_dest'] == 0 )
						{
							$this->url = 'https://sandbox.ipay88.co.id/epayment/entry.asp';							
						}
						else
						{
							$this->url = 'https://payment.ipay88.co.id/epayment/entry.asp';
						}

						if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) 
						{
								add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
						} 
						else 
						{
								add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
						}
				}

				function init_form_fields() 
				{
					
					$this->form_fields = array(
							'enabled' => array(
									'title' => __( 'Enable/Disable', 'woocommerce' ),
									'type' => 'checkbox',
									'label' => __( 'Enable iPay88 Payment Gateway', 'woocommerce' ),
									'default' => 'yes'
							),
							'server_dest' => array(
									'title' => __( 'Server Destination', 'woocommerce' ),
									'type' => 'select',
									'description' => __( 'Choose server destination developmet or production.', 'woocommerce' ),
									'options' => array(
														'0' => __( 'Development', 'woocommerce' ),
														'1' => __( 'Production', 'woocommerce' )
									),
									'desc_tip' => true,
							),
							'merchant_code' => array(
									'title' => __('Merchant Code : ', 'woocommerce'),
									'type' => 'text',
									'desc_tip' => true,
							),
							'merchant_key' => array(
									'title' => __('Merchant Key : ', 'woocommerce'),
									'type' => 'text',
									'desc_tip' => true,
							),																				
							'name' => array(
									'title' => __('Payment Name : ', 'woocommerce'),
									'type' => 'text',
									'description' => __('Payment name to be displayed when checkout.', 'woocommerce'),
									'default' => 'iPay88 Payment Gateway',
									'desc_tip' => true,
							),						
					);
					
				}
			
				public function admin_options() 
				{
						echo '<h2>'.__('iPay88 Payment Gateway', 'woocommerce').'</h2>';
						echo "<h3>iPay88 Parameter</h3><br>\r\n";
						
						echo '<table class="form-table">';
						$this->generate_settings_html();
						echo '</table>';
						
						// URL                             
						$myserverpath = explode ( "/", $_SERVER['PHP_SELF'] );
						if ( $myserverpath[1] <> 'admin' && $myserverpath[1] <> 'wp-admin' ) 
						{
								$serverpath = '/' . $myserverpath[1];    
						}
						else
						{
								$serverpath = '';
						}
						
						if((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443)
						{
								$myserverprotocol = "https";
						}
						else
						{
								$myserverprotocol = "http";    
						}
						
						$myservername = $_SERVER['SERVER_NAME'] . $serverpath;			
										
						$mainurl =  $myserverprotocol.'://'.$myservername;
						
				}

				public function receipt_page($order)
				{
						echo $this->generate_ipay88_form($order);
				}
			
				public function generate_ipay88_form($order_id) 
				{
					
						global $woocommerce;
						global $wpdb;
						static $basket;
		
						$order = new WC_Order($order_id);
						$counter = 0;
		
						foreach($order->get_items() as $item) 
						{
								$BASKET = $basket.$item['name'].','.$order->get_item_subtotal($item).','.$item['qty'].','.$order->get_line_subtotal($item).';';
						}
						
						$BASKET = "";
						
						// Order Items
						if( sizeof( $order->get_items() ) > 0 )
						{
								foreach( $order->get_items() as $item )
								{							
										$BASKET .= $item['name'] . "," . number_format($order->get_item_subtotal($item), 2, '.', '') . "," . $item['qty'] . "," . number_format($order->get_item_subtotal($item)*$item['qty'], 2, '.', '') . ";";
								}
						}
						
						// Shipping Fee
						if( $order->order_shipping > 0 )
						{
								$BASKET .= "Shipping Fee," . number_format($order->order_shipping, 2, '.', '') . ",1," . number_format($order->order_shipping, 2, '.', '') . ";";
						}					
						
						// Tax
						if( $order->get_total_tax() > 0 )
						{
								$BASKET .= "Tax," . $order->get_total_tax() . ",1," . $order->get_total_tax() . ";";
						}
			
						// Fees
						if ( sizeof( $order->get_fees() ) > 0 )
						{
								$fee_counter = 0;
								foreach ( $order->get_fees() as $item )
								{
										$fee_counter++;
										$BASKET .= "Fee Item," . $item['line_total'] . ",1," . $item['line_total'] . ";";																		
								}
						}
				
						$BASKET = preg_replace("/([^a-zA-Z0-9.\-,=:;&% ]+)/", " ", $BASKET);						
						
						$merchant_code			= trim($this->merchant_code);
						$merchant_key			= trim($this->merchant_key);
						$payment_id				= "1";
						$ref_no					= $order_id;
						$amount					= number_format($order->order_total, 2, '.', '');
						$currency				= get_woocommerce_currency();
						$prod_desc				= "Order #".$order_id;
						$user_name				= trim($order->billing_first_name . " " . $order->billing_last_name);
						$user_email				= trim($order->billing_email);
						$user_contact			= trim($order->billing_phone);
						$remark					= "";
						$lang					= "UTF-8";
						$signature				= $this->iPay88_signature($mechant_key.$mechant_code.$ref_no.$amount.$currency);
						$url					= $this->url;


						$ipay88_args = array(
							'merchant_code'			=> trim($this->merchant_code),
							'merchant_key'			=> trim($this->merchant_key),
							'payment_id'			=> "1",
							'ref_no'				=> $order_id,
							'amount'				=> number_format($order->order_total, 2, '.', ''),
							'currency'				=> get_woocommerce_currency(),
							'prod_desc'				=> "Order #".$order_id,
							'user_name'				=> trim($order->billing_first_name . " " . $order->billing_last_name),
							'user_email'			=> trim($order->billing_email),
							'user_contact'			=> trim($order->billing_phone),
							'remark'				=> "",
							'lang'					=> "UTF-8",
							'signature'				=> $this->iPay88_signature($mechant_key.$mechant_code.$ref_no.$amount.$currency),						
						);				
						
						// Form
						$ipay88_args_array = array();
						foreach($ipay88_args as $key => $value)
						{
								$ipay88_args_array[] = "<input type='hidden' name='$key' value='$value' />";
						}
						
						return '<form action="'.$url.'" method="post" id="ipay88_payment_form">'.
										implode(" \r\n", $ipay88_args_array).
										'<input type="submit" class="button-alt" id="submit_ipay88_payment_form" value="'.__('Pay via iPay88', 'woocommerce').'" />
										<!--
										<a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'woocommerce').'</a>
										-->
										
										<script type="text/javascript">
										jQuery(function(){
										jQuery("body").block(
										{
												message: "<img src=\"'.$woocommerce->plugin_url().'/assets/images/ajax-loader.gif\" alt=\"Redirecting...\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to iPay88 to make payment.', 'woocommerce').'",
												overlayCSS:
										{
										background: "#fff",
										opacity: 0.6
										},
										css: {
													padding:        20,
													textAlign:      "center",
													color:          "#555",
													border:         "3px solid #aaa",
													backgroundColor:"#fff",
													cursor:         "wait",
													lineHeight:     "32px"
												}
										});
										jQuery("#submit_ipay88_payment_form").click();});
										</script>
										</form>';
			 
				}
			
				public function process_payment($order_id)
				{
						global $woocommerce;
						$order = new WC_Order($order_id);
						return array(
								'result' => 'success',
								'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
						);	
				}
				
				function clear_cart()
				{
						add_action( 'init', 'woocommerce_clear_cart_url' );
						global $woocommerce;
						
						$woocommerce->cart->empty_cart(); 														
				}

				function iPay88_signature($source)
				{
					return base64_encode(hex2bin(sha1($source)));
				}

				function hex2bin($hexSource)
				{
					for ($i=0;$i<strlen($hexSource);$i=$i+2)
					{
					  $bin .= chr(hexdec(substr($hexSource,$i,2)));
					}
				  return $bin;
				}
				
		}
		
		function woocommerce_add_gateway_ipay88_gateway($methods)
		{
				$methods[] = 'WC_ipay88_Gateway';
				return $methods;
		}
		
		add_filter('woocommerce_payment_gateways', 'woocommerce_add_gateway_ipay88_gateway' );
		
}

?>
