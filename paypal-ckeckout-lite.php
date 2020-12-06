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
add_filter('woocommerce_order_button_html', 'bbloomer_rename_place_order_button_alt', 20);

function bbloomer_rename_place_order_button_alt($button)
{
    $button = '';
    return $button;
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
            add_action('wp_enqueue_scripts', array($this, 'paypal_checkout_tag'));
            add_action('wp_enqueue_scripts', array($this, 'paypal_checkout_lite_js'));
            add_action('woocommerce_api_paypal_checkout_lite', array($this, 'webhook'));
        }

        public function paypal_checkout_tag()
        {
            $currency = get_woocommerce_currency();
            wp_enqueue_script('paypal_checkout_lite_script', urldecode("https://www.paypal.com/sdk/js?client-id=$this->client_id&currency=$currency"), array(), null,);
        }
        public function paypal_checkout_lite_js()
        {
            global $woocommerce, $post;
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
            wp_enqueue_script('woocommerce_paypal_checkout_lite', plugins_url('/paypal.js', __FILE__), array('jquery', 'paypal_checkout_lite_script'), null, true);
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
            wp_localize_script('woocommerce_paypal_checkout_lite', 'woocommerce', array(
                'order_id' => '',
                'content' => json_encode([$cart_content], JSON_PRETTY_PRINT)
            ));
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

        public function process_payment($order_id)
        {
            global $woocommerce;
            $order = wc_get_order($order_id);
            $order->payment_complete();
            $order->reduce_order_stock();
            $order->add_order_note('Hey, your order is paid! Thank you!', true);
            $woocommerce->cart->empty_cart();
        }


        public function webhook()
        {
            global $woocommerce;
            $order = wc_get_order($_GET['order_id']);
            $order->payment_complete();
            $order->reduce_order_stock();
            $order->add_order_note('Hey, your order is paid! Thank you!', true);
            $woocommerce->cart->empty_cart();
            update_option('webhook_debug', $_GET);
        }
    }
}
