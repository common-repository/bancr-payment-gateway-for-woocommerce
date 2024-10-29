<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if (!class_exists('WC_Payment_Gateway')){
    //Woocommerce is not active.
    return;
}

class WC_Gateway_Bancr extends WC_Payment_Gateway_CC {

    protected $order = null;
    protected $transactionId = null;
    protected $ccdigit = null;
    protected $transactionErrorMessage = null;
    protected $merchant_id = '';
    protected $pay_url = '';
	protected $testmode;
	protected $payment_redirect ;
	protected $kd_order_id;
	protected $end_point;
	protected $check_order_status;
	public $bancr_checkout;
	

    public function __construct() {
        $this->id                   = 'bancr';
		$this->method_title         = __( 'Bancr', 'woocommerce-gateway-bancr' );
		$this->method_description = '';
		$this->method_description .= sprintf( __( 'Bancr works sending the details to Bancr for verification. <a href="%1$s" target="_blank">Sign up</a> for a Bancr account, and <a href="%2$s" target="_blank">get your Bancr Merchant account ID</a>. ', 'woocommerce-gateway-bancr' ), 'https://pay.bancr.io/merchantAuth/signup', 'https://pay.bancr.io/merchantAuth/login' );
		$this->has_fields           = true;		
		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->title                   = $this->get_option( 'title' );
		$this->description             = $this->get_option( 'description' );
		$this->enabled                 = $this->get_option( 'enabled' );
		$this->testmode                = 'yes' === $this->get_option( 'testmode' );
		$this->merchant_id             = ($this->testmode) ? $this->get_option( 'sandbox_merchant_id' ) : $this->get_option( 'live_merchant_id' );
		$this->logging                 = 'yes' === $this->get_option( 'logging' );
		if ( $this->testmode ) {
			$this->end_point              = 'http://35.192.105.197'; 
			$this->pay_url                = $this->end_point.'/api/v1/order/create'; 
			//$this->payment_redirect       = $this->end_point.'/user/login/'; 
			$this->payment_redirect       = $this->end_point.'/userAuth/login/';
			$this->check_order_status     = $this->end_point.'/api/v1/order/'; 
		} else {
			$this->end_point              = 'https://pay.bancr.io'; 
			$this->pay_url                = $this->end_point.'/api/v1/order/create';
			//$this->payment_redirect       = $this->end_point.'/user/login/';
			$this->payment_redirect       = $this->end_point.'/userAuth/login/';
			$this->check_order_status     = $this->end_point.'/api/v1/order/'; 
		}
		$this->icon_image                   = $this->get_option( 'bancr_icon_image' );
		$this->icon_link                   = $this->get_option( 'bancr_icon_link' );
		$this->description .= ' ' . sprintf( __( 'I agree to have my form of payment charged after pressing the Place Order button in accordance with Bancr  <a href = "https://bancr.io/privacy-policy/" target="_blank">Terms and Conditions</a>.', 'woocommerce-gateway-bancr' ) );
		if ( $this->testmode ) {
			$this->description .= ' ' . sprintf( __( 'TEST MODE ENABLED. In test mode, If you need a Testing Merchant ID, please email <a href = "mailto:support@bancr.io">support@bancr.io</a>.', 'woocommerce-gateway-bancr' ) );
			$this->description  = trim( $this->description );
		} 

		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_api_wc_gateway_bancr', array( $this, 'check_pay_status_response' ) );
	}  

 
  	public function check_pay_status_response() {	
  		//order ID ex: 111
  		$order_id = (int) $_REQUEST['order_id']; 
  		if($order_id){
	  		//bancr order id ex: order-0f09996d-2a3b-4567-8e8e-201dacbg8c95
	  		$bancr_order_id = sanitize_text_field($_REQUEST['o']); 
	  		$this->order = wc_get_order( $order_id );
	  		if($bancr_order_id){
	  			update_post_meta( $order_id, '_bancr_order_id', $bancr_order_id);
	  			$response = wp_remote_get( $this->check_order_status.$bancr_order_id );
				$responseBody = wp_remote_retrieve_body( $response );
				$result = json_decode( $responseBody );
	 			if ( $result->id ) {
	 				if( strtolower($result->status) == 'completed' ) {
						$this->order->update_status( 'processing' );
					} else {
						$this->order->update_status( $result->status );
					}
					$this->order->add_order_note(
			                sprintf("Bancr Order('%s') Payment is Captured with Status '%s'", $bancr_order_id, $result->status)
			        );
	 				wp_redirect( $this->get_return_url( $this->order ) );
	 				exit;
	 			} else {
	 				$this->order->add_order_note("Transaction Status Capture Error (Contact admin) - Bancr");
	 				wp_redirect( $this->get_return_url( $this->order ) );
	 				exit;
	 			}
	  		} else {
	  			$notice = "Thank you for shopping with us. Right now your payment status is pending. We will keep you posted regarding the status of your order.";
	            $notice_type = 'info';
	  			wc_empty_cart();
	            wc_add_notice( $notice, $notice_type );
	            wp_redirect( $this->get_return_url( $this->order ) );
	            exit;
	  		}
	  	}
  	}

    /**
	 * Check if SSL is enabled and notify the user
	 */
    public function admin_notices() {
		if ($this->merchant_id && get_option('woocommerce_force_ssl_checkout') == 'no' && $this->enabled == 'yes'){
            echo '<div class="error"><p>' . sprintf(__('%s gateway requires SSL certificate for better security. The <a href="%s">force SSL option</a> is disabled on your site. Please ensure your server has a valid SSL certificate so you can enable the SSL option on your checkout page.', 'woocommerce'), $this->method_title, admin_url('admin.php?page=wc-settings&tab=checkout')) . '</p></div>';
        }
	}

	/**
	 * Check if this gateway is enabled
	 */
	public function is_available() {
		if ( 'yes' === $this->enabled ) {
			if ( ! $this->merchant_id ) {
				return false;
			}
			return true;
		}
		return false;
	}
    
    public function init_form_fields() {
        $this->form_fields = include( 'settings-bancr.php' );
    }

    /*
     * Validates the fields specified in the payment_fields() function.
     */
    public function validate_fields() {
		global $woocommerce;         
		return;
    }
    
    /*
     * Render the credit card fields on the checkout page
     */
	public function payment_fields() {

		echo '<div id="bancr-payment-data">';
		if ( $this->description ) {
			echo apply_filters( 'wc_bancr_description', wpautop( wp_kses_post( $this->description ) ) );
		} 
		echo '</div>';
	}
    //once validation done process to payment
    public function process_payment($order_id) {
        global $woocommerce,$wp_session;
        $this->order = wc_get_order( $order_id );
        if (  $this->order->get_total() > 0 ) {
	        $gatewayRequestData = $this->create_bancr_request();
			$returnURL = add_query_arg(array('order_id' => $order_id,'wc-api' => 'wc_gateway_bancr'), home_url('/'));
			//$this->get_return_url( $this->order );
			$woocommerce->cart->empty_cart();
	        if ($gatewayRequestData AND $this->verify_bancr_payment($gatewayRequestData,$returnURL)) {
				setcookie('kade_order_id', $this->kd_order_id, time() + (86400 * 30), "/"); // 86400 = 1 day 
				$this->order->add_order_note(sprintf("Created Order on Bancr. Your Order Id is: <b>%s</b>", $this->kd_order_id));
				return array('result' => 'success', 'redirect' => $this->payment_redirect.$this->kd_order_id);	             
	        } else {
	            $this->mark_as_failed_payment();
	        }
    	} else {
    		$this->order->payment_complete();
    	}
	}

    protected function mark_as_failed_payment() {
        $this->order->add_order_note(sprintf("Bancr Credit Card Payment Failed with message: '%s'", $this->transactionErrorMessage));
    }

    protected function do_order_complete_tasks() {
        global $woocommerce;
        if ($this->order->status == 'completed')
            return;

        $this->order->payment_complete();
        $woocommerce->cart->empty_cart();

        $this->order->add_order_note(
                sprintf("Bancr Credit Card(ending - '%s') payment is on hold with Transaction Id of '%s'", $this->ccdigit, $this->transactionId)
        );

		$this->order->update_status( 'on-hold' );
        unset($_SESSION['order_awaiting_payment']);
    }

    protected function do_order_processing_tasks() {
    	global $woocommerce;

        $woocommerce->cart->empty_cart();
        $this->order->add_order_note(sprintf("Your order is processing"));
		//$this->order->update_status( 'processing' );
        unset($_SESSION['order_awaiting_payment']);
    }

    protected function do_order_hold_tasks() {
    	global $woocommerce;

        $woocommerce->cart->empty_cart();
        $this->order->add_order_note(
                sprintf("Bancr Credit Card(ending - '%s') payment is pending with Transaction Id of '%s'", $this->ccdigit, $this->transactionId)
        );
		$this->order->update_status( 'pending' );
        unset($_SESSION['order_awaiting_payment']);
    }

    protected function do_order_failed_tasks() {
    	global $woocommerce;
        $woocommerce->cart->empty_cart();
        $this->order->add_order_note(sprintf("Bancr Credit Card Payment Failed with message: '%s'", $this->transactionErrorMessage));
		//$this->order->update_status( 'failed' );
        unset($_SESSION['order_awaiting_payment']);
    }

    //call api for payment verification
    protected function verify_bancr_payment($gatewayRequestData,$returnURL) {
        global $woocommerce;
        $fieldsArray = array();
        $url = $gatewayRequestData['URL'];
        $order_id = $gatewayRequestData['OID'];
		$merchant_id = $gatewayRequestData['MERCHANT_ID'];
		$ip_address = $gatewayRequestData['IPADDRESS'];
		 
		$amount = $gatewayRequestData['AMT'];
		$currency = $gatewayRequestData['CURRENCYCODE'];

		$first_name = $gatewayRequestData['FIRSTNAME'];
		$last_name = $gatewayRequestData['LASTNAME'];
		$email = $gatewayRequestData['EMAIL'];
		$address_line1 = $gatewayRequestData['ADDRESS_1'];
		$address_city = $gatewayRequestData['CITY'];
		$address_state = $gatewayRequestData['STATE'];
		$address_zip = $gatewayRequestData['POSTCODE'];
		$address_country = $gatewayRequestData['COUNTRY'];
		$note = "Order ".$order_id." placed by ".$gatewayRequestData['FIRSTNAME'];

		// Create POST string
		$PostFields = array(
			'user_id' => $merchant_id, 
			'wp_order_id'=>$order_id,
			'amount'=>$amount,
			'redirect_url' => $returnURL,
			'failed_redirect_url' => $returnURL,
			'currency'=>$currency,
			'first_name'=>$first_name,
			'last_name'=>$last_name,
			'email'=>$email,
			'webhook_url'=>$returnURL,
		);
		
		$PostFields = json_encode($PostFields);
		$args = array(
		    'body' => $PostFields,
		    'timeout' => 45,
		    'httpversion' => '1.0',
		    'headers' => array( 'Content-Type' => 'application/json' ),
		    'cookies' => array()
		);
		//print_r($args);
		$Response = wp_remote_post( $url, $args );	
		//print_r($Response);
		// response handle
		if ( is_wp_error( $Response ) ) {
		   $error_message = $Response->get_error_message();
		   $errMsg = "Something went wrong: $error_message";
		   wc_add_notice($errMsg,'error');
		   $this->transactionErrorMessage =  $errMsg;	   	
		   return false;
		} else {
		   if ( empty( $Response['body'] ) ){
		   		$errMsg = 'Bancr Response was not get any data.';
		   		wc_add_notice($errMsg,'error');
		   		$this->transactionErrorMessage =  $errMsg;	   	
		   		return false;
		   } else {
		   		$result = json_decode($Response['body']);
			    if( !empty($result->status) ) {
			    	//Transaction went through successfully
				    $this->transactionId = $result->transaction_id; 
				    $this->ccdigit = $result->card;
				    if($result->status == "success") {
						$this->kd_order_id = $result->order->order_id; // Bancr Order Id
			    		//$this->do_order_processing_tasks();
			    		//$this->do_order_hold_tasks();
			    	} else if($result->status == "failed") {
			    		//$this->do_order_failed_tasks();
			    	}
				    return true;
				} else {
				    //Transaction Failed, look at messages array for reason(s) why
				   if($result->error->error_msg){
				   		$errMsg = $result->error->error_msg;
				   } else {
				   		$errMsg = 'Status Failed!';
				   }
			   		wc_add_notice($errMsg,'error');
			   		$this->transactionErrorMessage =  $errMsg;	   	
			   		return false;
				}
		   }
		}
		return false;
    }


    protected function create_bancr_request() {
        if ($this->order AND $this->order != null) {
        	 
            return array(
            	'URL' => $this->pay_url,
            	'OID' => $this->order->get_id(),
                'MERCHANT_ID' => $this->merchant_id,
                'AMT' => $this->order->get_total(),
                'FIRSTNAME' => $this->order->get_billing_first_name(),
                'LASTNAME' => $this->order->get_billing_last_name(),
                'EMAIL' => $this->order->get_billing_email(),
                'ADDRESS_1' => $this->order->get_billing_address_1(),
                'CITY' => $this->order->get_billing_city(),
                'STATE' => $this->order->get_billing_state(),
                'COUNTRY' => $this->order->get_billing_country(),
                'POSTCODE' => $this->order->get_billing_postcode(),
                'IPADDRESS' => $_SERVER['REMOTE_ADDR'], 
                'CURRENCYCODE' => get_woocommerce_currency()
            );
        }
        return false;
    }
    /**
	 * Get gateway icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		// We need a base country for the link to work, bail if in the unlikely event no country is set.

		$icon_html = '';
		$icon      = (array) $this->get_icon_image( );

		foreach ( $icon as $i ) {
			$icon_html .= '<img style="max-width:151px;" src="' . esc_attr( $i ) . '" alt="' . esc_attr__( 'Bancr acceptance mark', 'woocommerce' ) . '" />';
		}

		// $icon_html .= sprintf( '<a href="%1$s" class="about_bancr" onclick="javascript:window.open(\'%1$s\',\'WIBancr\',\'toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes, width=1060, height=700\'); return false;">' . esc_attr__( 'What is Bancr?', 'woocommerce' ) . '</a>', esc_url( $this->get_icon_url() ) );

		return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
	}
	protected function get_icon_image( ) {
		$icon = esc_url( plugins_url( 'assets/images/Bancr-Card-Icons.jpg', dirname(__FILE__) ) );
		//plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__)) . '/assets/images/Bancr-Card-Icons.jpg';
		return apply_filters( 'woocommerce_bancr_icon', $icon );
	}
	protected function get_icon_url( ) {
	    return 'https://bancr.io/';
	}

    
}//End of class
