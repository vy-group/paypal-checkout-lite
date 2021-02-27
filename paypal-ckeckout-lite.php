<?php
/*
* Plugin Name: PayPal Checkout Lite
* Description: Take PayPal and card payment in your store
* Author : Vyconsulting
* Author URI : https://vyconsulting-group.com
* Version : 1.0.0
*/
add_filter('woocommerce_payment_gateways', 'paypal_checkout_lite_class');

function paypal_checkout_lite_class()
{
    $gateways[] = 'WC_PayPal_Checkout_Lite';
    return $gateways;
}
add_action('plugins_loaded', 'paypal_checkout_lite_init_class');
function paypal_checkout_lite_init_class()
{
    class WC_PayPal_Checkout_Lite extends WC_Payment_Gateway
    {
        public function __construct()
        {
            $this->id = "paypal_checkout_lite";
            $this->icon = '';
            $this->method_title = "PayPal Checkout Lite";
            $this->method_description = "Take PayPal and card payment in your store";
            $this->supports = array('products');
            $this->init_form_fields();
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->sandbox = 'yes' == $this->get_option('sandbox');
            $this->client_id = $this->sandbox ? $this->get_option('sandbox_client_id') : $this->get_option('client_id');
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            remove_action('woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 20);
            add_action('woocommerce_proceed_to_checkout', array($this, 'display_paypal_button'), 20);
            add_action('woocommerce_api_wc_paypal_checkout_lite', array($this, 'webhook'), 20);
        }


        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Enable/Disabled',
                    'label' => 'Enable PayPal Checkout Lite',
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => 'Title',
                    'type' => 'text',
                    'description' => 'This control the title which the user sees during checkout',
                    'default' => 'PayPal Checkout Lite',
                    'desc_tip' => true
                ),
                'description' => array(
                    'title' => 'Description',
                    'type' => 'textarea',
                    'description' => 'This control the description which the user sees during the checkout',
                    'default' => 'Pay with your PayPal account or your credit card'
                ),
                'sandbox' => array(
                    'title' => 'Sandbox mode',
                    'label' => 'Enable the sandbox mode',
                    'type' => 'checkbox',
                    'description' => 'Place the sandbox client id for the sandbox mode',
                    'default' => 'yes',
                    'desc_tip' => true
                ),
                'sandbox_client_id' => array(
                    'title' => 'The sandbox client id',
                    'type' => 'text'
                ),
                'client_id' => array(
                    'title' => 'The live client id',
                    'type' => 'text'
                )
            );
        }

        public function payment_fields()
        {
            if ($this->description) {
                // you can instructions for test mode, I mean test card numbers etc.
                if ($this->sandbox) {
                    $this->description .= ' TEST MODE ENABLED. In test mode, you can use the card numbers listed in <a href="#" target="_blank" rel="noopener noreferrer">documentation</a>.';
                    $this->description  = trim($this->description);
                }
                // display the description with <p> tags etc.
                echo wpautop(wp_kses_post($this->description));
            }
            echo '<div id="paypal-button-container">' . '</div>';
        }

        public function display_paypal_button()
        {
            $currency = get_woocommerce_currency();
            wp_enqueue_script('w_paypal_checkout_lite_script', urldecode("https://www.paypal.com/sdk/js?client-id=$this->client_id&currency=$currency"), array(), null, false);
            global $woocommerce;
            $cart_content = [];
            $cart_content['amount'] = [
                'value' => $woocommerce->cart->total,
                'breakdown' => [
                    'item_total' => [
                        'currency_code' => get_woocommerce_currency(),
                        'value' => $woocommerce->cart->subtotal
                    ]
                ]
            ];
            wp_enqueue_script('wc_paypal_checkout_lite', plugins_url('/paypal.js', __FILE__), array(), null, true);
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                $cart_content['items'][] = [
                    'name' => $cart_item['data']->get_title(),
                    'quantity' => $cart_item['quantity'],
                    'unit_amount' => [
                        'currency_code' => get_woocommerce_currency(),
                        'value' => $cart_item['data']->get_price()
                    ]
                ];
            }
            wp_localize_script('wc_paypal_checkout_lite', 'woocommerce', array(
                'content' => json_encode([$cart_content], JSON_PRETTY_PRINT)
            ));
            if ($this->description) {
                // you can instructions for test mode, I mean test card numbers etc.
                if ($this->sandbox) {
                    $this->description .= ' TEST MODE ENABLED. In test mode, you can use the card numbers listed in <a href="#" target="_blank" rel="noopener noreferrer">documentation</a>.';
                    $this->description  = trim($this->description);
                }
                // display the description with <p> tags etc.
                echo wpautop(wp_kses_post($this->description));
            }
            echo '<div id="paypal-button-container">' . '</div>';
        }

        public function process_payment($order_id)
        {

            global $woocommerce;
            $order = wc_get_order($order_id);
            $order->payment_complete();
            $order->reduce_order_stock();
            $order->add_order_note('Hey, your order is paid! Thank you!', true);
            $woocommerce->cart->empty_cart();
            die();
        }


        public function webhook()
        {
            global $woocommerce;
            $order = wc_create_order();
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                $order->add_product(wc_get_product($cart_item['product_id']), $cart_item['quantity']);
            }
            $address = array(
                'first_name' => $_POST['first_name'],
                'last_name'  => $_POST['last_name'],
                'company'    => '',
                'email'      => $_POST['email'],
                'phone'      => '',
                'address_1'  => $_POST['address_1'],
                'address_2'  => '',
                'city'       => $_POST['city'],
                'state'      => '',
                'postcode'   => $_POST['postcode'],
                'country'    => $_POST['country']
            );
            $order->set_address($address, 'billing');
            $order->set_address($address, 'shipping');
            $order->calculate_totals();
            $order->payment_complete();
            $order->reduce_order_stock();
            $order->add_order_note('Hey, your order is paid! Thank you!', true);
            $woocommerce->cart->empty_cart();
            update_option('webhook_debug', $_POST);
            die();
        }
    }
}
