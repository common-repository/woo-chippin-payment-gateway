<?php

namespace Chippin\Handler;

use Chippin\I18n;
use Chippin\Settings;
use Chippin\Logger;
use Chippin\Session;
use Chippin\Exception;

class Checkout
{

    private $settings;
    private $logger;

    public function __construct(Settings $settings, Logger $logger)
    {
        $this->settings = $settings;
        \add_action('woocommerce_api_chippin_checkout', array($this, 'complete_chippin'));
        \add_action('woocommerce_api_chippin_checkout', array($this, 'contributed_chippin'));
        \add_action('woocommerce_api_chippin_checkout', array($this, 'rejected_chippin'));
        \add_action('woocommerce_api_chippin_checkout', array($this, 'cancel_chippin'));
    }


    /**
     * Checks data is correctly set when returning from Chippin
     */
    public function complete_chippin() {
        if (empty($_GET['merchant_order_id']) || empty($_GET['hmac']) || empty($_GET['status']))
        {
            return;
        }
        if ($_GET['status'] === 'completed') {
            $hmac    = $_GET['hmac'];
            $orderId = $_GET['merchant_order_id'];
            $session = WC()->session->get('chippin');

            if (empty($session))
            {
                \wc_add_notice(__('Your Chippin session has expired. Please check out again.', I18n::NAME_SPACE), 'error');
                return;
            }

            $session->checkout_completed = true;
            WC()->session->set('chippin', $session);

            try {
                if ($session->order_id) {
                    $order = \wc_get_order($session->order_id);
                    if (!$order) {
                        throw new Exception('Unable to find the order');
                    }
                    $order->add_order_note('Order completed by Chippin Instigator');
                    $order->payment_complete();
                    $notice = __('You have completed your Chippin order.', I18n::NAME_SPACE );
                    if (!\wc_has_notice($notice, 'notice')) {
                        \wc_add_notice($notice, 'notice');
                    }
                    WC()->cart->empty_cart();
                    \wp_redirect($order->get_checkout_order_received_url());
                    exit;
                }
            } catch (Exception $e) {
                \wc_add_notice(__('Your Chippin checkout session has expired. Please check out again.', I18n::NAME_SPACE), 'error' );
                $this->maybe_clear_session_data();
                \wp_safe_redirect(\wc_get_page_permalink('cart'));
                exit;
            }
        }


    }

    public function contributed_chippin() {
        if (empty($_GET['merchant_order_id']) || empty($_GET['hmac']) || empty($_GET['status']))
        {
            return;
        }
        if ($_GET['status'] === 'contributed') {
            $hmac    = $_GET['hmac'];
            $orderId = $_GET['merchant_order_id'];

            try {
                $order = \wc_get_order($orderId);
                if (!$order) {
                    throw new Exception('Unable to find the order');
                }
                $notice = __('Thanks for contrinbuting to this Chippin order.', I18n::NAME_SPACE );
                if (!\wc_has_notice($notice, 'notice')) {
                    \wc_add_notice($notice, 'notice');
                }
                \wp_redirect($order->get_checkout_order_received_url());
                exit;
            } catch (Exception $e) {
                \wc_add_notice(__('Your Chippin order can\'t be found. Please try again.', I18n::NAME_SPACE), 'error' );
                \wp_safe_redirect(\wc_get_page_permalink('cart'));
                exit;
            }
        }


    }

    public function rejected_chippin() {
        if (empty($_GET['merchant_order_id']) || empty($_GET['hmac']) || empty($_GET['status']))
        {
            return;
        }
        if ($_GET['status'] === 'rejected') {
            $hmac    = $_GET['hmac'];
            $orderId = $_GET['merchant_order_id'];

            try {
                $order = \wc_get_order($orderId);
                if (!$order) {
                    throw new Exception('Unable to find the order');
                }
                $notice = __('Sorry you were inable to towards this Chippin order.', I18n::NAME_SPACE );
                if (!\wc_has_notice($notice, 'error')) {
                    \wc_add_notice($notice, 'error');
                }
                \wp_redirect($order->get_checkout_order_received_url());
                exit;
            } catch (Exception $e) {
                \wc_add_notice(__('Your Chippin order can\'t be found. Please try again.', I18n::NAME_SPACE), 'error' );
                \wp_safe_redirect(\wc_get_page_permalink('cart'));
                exit;
            }
        }


    }

    /**
     * Buyer cancels checkout with Chippin.
     *
     */
    public function cancel_chippin() {
            if (empty($_GET['merchant_order_id']) || empty($_GET['hmac']) || empty($_GET['status']))
            {
                return;
            }
            if ($_GET['status'] === 'cancelled') {
                $notice = __('You have cancelled Checkout with Chippin. Please try to process your order again.', I18n::NAME_SPACE );
                if (!\wc_has_notice($notice, 'notice')) {
                    \wc_add_notice($notice, 'notice');
                }
                \wp_safe_redirect(\wc_get_page_permalink('cart'));
                exit;
            }
    }



    /**
     * Handle result of do_payment
     */
    public function handle_payment_response( $order, $payment ) {
        // Store meta data to order
        $old_wc = version_compare( WC_VERSION, '3.0', '<' );

        update_post_meta( $old_wc ? $order->id : $order->get_id(), '_paypal_status', strtolower( $payment->payment_status ) );
        update_post_meta( $old_wc ? $order->id : $order->get_id(), '_transaction_id', $payment->transaction_id );

        // Handle $payment response
        if ( 'completed' === strtolower( $payment->payment_status ) ) {
            $order->payment_complete( $payment->transaction_id );
        } else {
            if ( 'authorization' === $payment->pending_reason ) {
                $order->update_status( 'on-hold', __( 'Payment authorized. Change payment status to processing or complete to capture funds.', 'woocommerce-gateway-paypal-express-checkout' ) );
            } else {
                $order->update_status( 'on-hold', sprintf( __( 'Payment pending (%s).', 'woocommerce-gateway-paypal-express-checkout' ), $payment->pending_reason ) );
            }
            if ( $old_wc ) {
                if ( ! get_post_meta( $order->id, '_order_stock_reduced', true ) ) {
                    $order->reduce_order_stock();
                }
            } else {
                wc_maybe_reduce_stock_levels( $order->get_id() );
            }
        }
    }
}
