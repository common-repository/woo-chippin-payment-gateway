<?php

namespace Chippin;

use Chippin\I18n;
use Chippin\Settings\Form;
use Chippin\Handler\Ipn;

class Gateway extends \WC_Payment_Gateway
{
    const ID = 'chippin';

    /**
     * Constructor for the gateway.
     */
    public function __construct() {
        $this->id = Gateway::ID;

        $this->has_fields         = false;
        $this->icon               = 'https://s3-eu-west-1.amazonaws.com/chippin-assets/chippin.png';
        $this->method_title       = __(
            'Chippin Shared Payments',
            I18n::NAME_SPACE
        );
        $this->method_description = __(
            'Allow customers to conveniently split the cost with friends and family.',
            I18n::NAME_SPACE
        );

        $this->initSettingsFormFields();
        $this->init_settings();

        $this->title        = $this->get_option('title');
        $this->description  = $this->get_option('description');

        $this->enabled      = $this->get_option('enabled', 'yes');
        $this->environment  = $this->get_option('environment', 'live');
        $this->merchant_id  = $this->get_option('merchant_id');
        $this->merchant_secret = $this->get_option('merchant_secret');


        $this->invoice_prefix     = $this->get_option('invoice_prefix', 'WC-');
        $this->paymentaction      = $this->get_option('paymentaction', 'sale');

        \add_filter(
            'woocommerce_payment_gateways',
            array($this, 'registerPaymentGateways')
        );

        \add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            array($this, 'process_admin_options')
        );

        \add_action(
            'woocommerce_receipt_chippin',
            array($this, 'receipt_page')
        );

        \add_action(
            'woocommerce_thankyou_chippin',
            array($this, 'thankyou_page')
        );
    }

    /**
     * Initialise Gateway Settings Form Fields.
     *
     * Required method for WC
     */
    private function initSettingsFormFields() {
        $form = new Form();
        $this->form_fields = $form->getFields();
    }

    public function thankyou_page($order_id)
    {

    }

    /**
     * Get gateway icon.
     * @return string
     */
    public function get_icon() {
        $icon_html = '<img src="' . esc_attr( $this->get_icon_url() ) . '" alt="' . esc_attr__( 'Chippin acceptance mark', 'woocommerce' ) . '" />';
        $icon_html .= sprintf(
            '<a href="%1$s" class="about_chippin" onclick="javascript:window.open(\'%1$s\',\'WIChippin\',\'toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes, width=1060, height=700\'); return false;">What is Chippin?</a>',
            esc_url('http://www.chippin.co.uk')
        );

        return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
    }


    public function get_icon_url() {
        return 'https://s3-eu-west-1.amazonaws.com/chippin-assets/chippin-woocommerce.png';
    }


    /**
     * Receipt Page
     **/
    function receipt_page($order)
    {
        WC()->cart->empty_cart();

        echo '<p>'.__('Thank you for your order, you will shortly be redirected to Chippin.', I18n::NAME_SPACE).'</p>';
        echo $this->generate_redirect_form($order);
    }

    public function generate_redirect_form($order_id)
    {
        $order    = wc_get_order( $order_id );
        $settings = Plugin::getInstance()->getSettings();
        $signature = $settings->getSignature();

        $data = array(
            'merchant_order_id' => $order->get_id(),
            'total_amount' => $order->get_total() * 100,
            'currency_code' => strtolower($order->get_currency()),
            'duration' => $settings->duration,
            'grace_period' => $settings->grace_period,
            'first_name' => $order->get_billing_first_name(),
            'last_name' => $order->get_billing_last_name(),
            'email' => $order->get_billing_email(),
            'products' => array()
        );

        foreach ($order->get_items() as $item) {
            $item = array(
                'label' => 'test',
                'image' => 'http://url.com/image.png',
                'amount' => '1000'
            );
            $data['products'][] = $item;
        }

        $data['hmac'] = $settings->getSignature()->generateOrderHash($data);
        // $relay_url = get_site_url().'/wc-api/'.get_class( $this );

        $action = $settings->getChippinRedirectUrl(array());
        $products = $this->generate_product_fields($order->get_items());

        $form = <<<EOT
        <form action="$action" method="post" id="chippin_redirect" accept-charset="utf-8">
        <input type="hidden" name="merchant_id" value="{$signature->getMerchantId()}"/>
        <input type="hidden" name="merchant_order_id" value="{$data[merchant_order_id]}"/>
        <input type="hidden" name="total_amount" value="{$data[total_amount]}"/>
        <input type="hidden" name="first_name" value="{$data[first_name]}"/>
        <input type="hidden" name="last_name" value="{$data[last_name]}"/>
        <input type="hidden" name="email" value="{$data[email]}"/>
        <input type="hidden" name="duration" value="{$data[duration]}"/>
        <input type="hidden" name="grace_period" value="{$data[grace_period]}"/>
        <input type="hidden" name="currency_code" value="{$data[currency_code]}"/>
        <input type="hidden" name="hmac" value="{$data[hmac]}"/>
        {$products}
        </form>
<script type="text/javascript">document.getElementById("chippin_redirect").submit();</script>
EOT;

         return $form;
      }

    public function generate_product_fields($items)
    {
        $html = '';
        foreach ($items as $item) {
            $total = $item->get_total() * 100;
            $product = $item->get_product();
            $image_url = \wp_get_attachment_url($product->get_image_id());
            $html .= "<input type=\"hidden\" name=\"products[][label]\" value=\"{$item->get_name()}\"/>";
            $html .= "<input type=\"hidden\" name=\"products[][image]\" value=\"{$image_url}\"/>";
            $html .= "<input type=\"hidden\" name=\"products[][amount]\" value=\"{$total}\"/>";
        }

        return $html;
    }

    /**
     * Process payments.
     *
     * @param int $order_id Order ID
     *
     * @return array
     */
    public function process_payment($order_id)
    {
        $order    = wc_get_order( $order_id );
        $settings = Plugin::getInstance()->getSettings();

        WC()->session->chippin = new Session(
            array(
                'order_id'   => $order_id,
                'expires_in' => $settings->getTokenSessionLength()
            )
        );

        return array(
            'result'   => 'success',
            'redirect' => $order->get_checkout_payment_url(true),
        );
    }

    /**
     * Check if this gateway is enabled.
     *
     * @return bool
     */
    public function is_available()
    {
        return 'yes' === $this->enabled;
    }

    /**
     * Register the Chippin payment method.
     *
     * @param array $methods Payment methods.
     *
     * @return array Payment methods
     */
    public function registerPaymentGateways($methods) {
        $methods[] = $this;

        return $methods;
    }

}
