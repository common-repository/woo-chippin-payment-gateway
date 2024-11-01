<?php

namespace Chippin\Handler;

use Chippin\I18n;
use Chippin\Settings;
use Chippin\Logger;
use Chippin\Exception;

class Ipn
{
    private $settings;
    private $logger;

    function __construct(Settings $settings, Logger $logger)
    {
        $this->settings = $settings;
        $this->logger = $logger;
        \add_action(
            'woocommerce_api_chippin',
            array($this, 'validateRequest')
        );
        \add_action(
            'woocommerce_chippin_valid_ipn_request',
            array($this, 'handleValidIpnRequest')
        );
    }

    /**
     * Validate request.
     */
    public function validateRequest() {
        try {
            if (empty($_POST['merchant_order_id']) || empty($_POST['hmac']) || !in_array($_GET['status'], array('invited', 'paid', 'failed', 'timed_out')))
            {
                return;
            }
            if (empty($_POST))
            {
                throw new \Exception(__(
                    'Empty POST data.',
                    I18n::NAME_SPACE
                ));
            }

            if ($this->isRequestValid($_POST)) {
                $this->logger->log('IPN request is valid according to Chippin.');
                \do_action('woocommerce_chippin_valid_ipn_request', \wp_unslash($_POST));
                exit;
            } else {
                $this->logger->log('IPN request is NOT valid according to Chippin.');
                throw new \Exception(__('Invalid IPN request.', I18n::NAME_SPACE));
            }
        } catch (\Exception $e) {
            \wp_die($e->getMessage(), __('Chippin IPN Request Failure', I18n::NAME_SPACE), array('response' => 500));
        }
    }

    /**
     * Check whether posted data is valid IPN request.
     *
     * @throws Exception
     *
     * @param array $posted_data Posted data
     * @return bool True if posted_data is valid IPN request
     */
    public function isRequestValid(array $posted_data) {
        $this->logger->log(
            \sprintf(
                '%s: %s',
                __FUNCTION__,
                'Checking IPN request validity'
            ));

        // TODO bvalidate the hmac value

        $this->logger->log(
            \sprintf(
                '%s: %s',
                __FUNCTION__,
                'Verify IPN request'
            ), 'debug', $posted_data);

        return true;
    }

    /**
     * Handle valid IPN request.
     *
     * @param array $posted_data Posted data
     */
    public function handleValidIpnRequest($posted_data) {
        if ($order = $this->getOrder( $posted_data['merchant_order_id'])) {
            // Lowercase returned variables.
            $posted_data['status'] = \strtolower($_GET['status']);

            $order_id = \version_compare( WC_VERSION, '3.0', '<' ) ? $order->id : $order->get_id();
            $this->logger->log( 'Found order #' . $order_id );
            $this->logger->log( 'Payment status: ' . $posted_data['status'] );

            if (\method_exists($this, 'payment_status_' . $posted_data['status'])) {
                \call_user_func(array($this, 'payment_status_' . $posted_data['status']), $order, $posted_data);
            }
        } else {
            $this->logger->log(\sprintf('%s: %s', __FUNCTION__, 'No order data being passed'));
        }
    }

    /**
     * Handle a pending payment following invites being sent.
     *
     * @param WC_Order $order Order object
     * @param array $posted_data Posted data
     */
    protected function payment_status_invited($order, $posted_data) {
        $this->payment_status_completed($order, $posted_data);
    }

    /**
     * Handle a pending payment following invites being sent.
     *
     * @param WC_Order $order Order object
     * @param array $posted_data Posted data
     */
    protected function payment_status_paid($order, $posted_data) {
        $this->payment_status_completed($order, $posted_data);
    }

    /**
     * Handle a completed payment.
     *
     * @param WC_Order $order Order object
     * @param array $posted_data Posted data
     */
    protected function payment_status_completed( $order, $posted_data ) {
        $order_id = version_compare(WC_VERSION, '3.0', '<') ? $order->id : $order->get_id();

        if ($order->has_status(array('processing', 'completed'))) {
            $this->logger->log('Aborting, Order #' . $order_id . ' is already complete.');
            exit;
        }

        if ('paid' === $posted_data['status']) {
            $this->paymentComplete($order, __( 'Chippin payment completed', I18n::NAME_SPACE));
        } else {
            $this->paymentOnHold($order, \sprintf(__( 'Payment on pending (%s).', I18n::NAME_SPACE), 'Chippin process completion'));
        }
    }

    /**
     * Handle a failed payment.
     *
     * @param WC_Order $order Order object
     * @param array $posted_data Posted data
     */
    protected function payment_status_failed($order, $posted_data) {
        $order->update_status(
            'failed',
            sprintf(
                __(
                    'Payment %s via Chippin.',
                    I18n::NAME_SPACE
                ),
                \wc_clean($posted_data['status'])
            )
        );
    }

    /**
     * Handle a denied payment.
     *
     * @param WC_Order $order Order object
     * @param array $posted_data Posted data
     */
    protected function payment_status_timed_out($order, $posted_data) {
        $this->payment_status_failed($order, $posted_data);
    }

    /**
     * Send a notification to the user handling orders.
     *
     * @param string $subject Email subject
     * @param string $message Email message
     */
    protected function sendIpnEmailNotification($subject, $message)
    {
        $new_order_settings = get_option('woocommerce_new_order_settings', array());
        $mailer             = WC()->mailer();
        $message            = $mailer->wrap_message( $subject, $message );
        $mailer->send( ! empty( $new_order_settings['recipient'] ) ? $new_order_settings['recipient'] : get_option( 'admin_email' ), strip_tags( $subject ), $message );
    }

     /**
     * Get the order from the signature.
     *
     * @param  string $order_id merchant_order_id being passed back
     * @return bool|WC_Order      Order object or false
     */
    protected function getOrder($order_id) {
        $order = \wc_get_order( $order_id );

        if (!$order) {
            $this->logger->log(
                \sprintf(
                    '%s: %s %s',
                    __FUNCTION__,
                    'Error: Order could not be retreived.',
                    $order_id
                ),
                'error'
            );
            return false;
        }

        return $order;
    }

    /**
     * Complete order, add transaction ID and note.
     *
     * @param  WC_Order $order  Order object
     * @param  string   $txn_id Transaction ID
     * @param  string   $note   Order note
     */
    protected function paymentComplete($order, $txn_id = '', $note = '') {
        $order->add_order_note($note);
        $order->payment_complete($txn_id);
    }

    /**
     * Hold order and add note.
     *
     * @param  WC_Order $order  Order object
     * @param  string   $reason On-hold reason
     */
    protected function paymentOnHold($order, $reason = '') {
        $order->update_status('on-hold', $reason);
        if (\version_compare( WC_VERSION, '3.0', '<'))
        {
            if (!\get_post_meta($order->id, '_order_stock_reduced', true))
            {
                $order->reduce_order_stock();
            }
        } else {
            \wc_maybe_reduce_stock_levels( $order->get_id() );
        }
    }

}
