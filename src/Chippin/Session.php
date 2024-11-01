<?php

namespace Chippin;

class Session
{
    /**
     * WooCommerce Order ID.
     *
     * @var int
     */
    public $order_id;

    /**
     * Whether the buyer has returned from Chippin.
     *
     * If checkout_completed is true Chippin should be selected as the payment
     * method.
     *
     * @var bool
     */
    public $checkout_completed = false;

    /**
     * How long the token will expires (in seconds).
     *
     * @var int
     */
    public $expiry_time;

    /**
     * Constructor.
     *
     * @param array $args Arguments for session data
     */
    public function __construct($args = array()) {
        $args = \wp_parse_args($args, array(
            'order_id'          => false,
            'expires_in'        => 10800,
        ));

        $this->expiry_time       = time() + $args['expires_in'];

        $this->order_id = $args['order_id'];
    }
}
