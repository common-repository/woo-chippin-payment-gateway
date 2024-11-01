<?php

namespace Chippin;

use Chippin\Credential\Signature;

class Settings
{

    /**
     * Setting values from get_option.
     *
     * @var array
     */
    protected $_settings = array();

    /**
     * Flag to indicate setting has been loaded from DB.
     *
     * @var bool
     */
    private $_is_setting_loaded = false;

    public function __construct($settings = null)
    {
        if ($settings) {
            $this->_settings = $settings;
        } else {
            $this->load();
        }
    }


    public function __set($key, $value)
    {
        if (array_key_exists($key, $this->_settings))
        {
            $this->_settings[$key] = $value;
        }
    }

    public function __get($key)
    {
        if (array_key_exists($key, $this->_settings))
        {
            return $this->_settings[$key];
        }
        return null;
    }

    public function __isset($name)
    {
        return array_key_exists( $key, $this->_settings );
    }

    /**
     * Load settings from Wordpress DB.
     *
     * @param bool $force_reload Force reload settings
     *
     * @return Chippin\Settings Instance of Chippin\Settings
     */
    public function load( $force_reload = false ) {
        if ( $this->_is_setting_loaded && ! $force_reload ) {
            return $this;
        }
        $this->_settings = (array) \get_option('woocommerce_chippin_settings', array());
        $this->_is_setting_loaded = true;

        return $this;
    }

    /**
     * Save current settings.
     *
     */
    public function save() {
        \update_option( 'woocommerce_gateway_chippin_settings', $this->_settings );
    }

    /**
     * Get Chippin Credentials
     *
     * @return Chippin\Credential\Signature
     */
    public function getSignature()
    {
            return new Signature(
                $this->merchant_id,
                $this->merchant_secret
            );

    }


    /**
     * Get Chippin redirect URL.
     *
     * @return string Chippin redirect URL
     */
    public function getChippinRedirectUrl($params)
    {
        $url = 'https://chippin.co.uk';

        if ( 'live' !== $this->environment ) {
            $url .= '/sandbox';
        }

        $url .= '/new?' . \http_build_query($params);

        return $url;
    }

    /**
     * Is Chippin enabled.
     *
     * @return bool
     */
    public function isEnabled()
    {
        return 'yes' === $this->enabled;
    }

    /**
     * Is logging enabled.
     *
     * @return bool
     */
    public function isLoggingEnabled()
    {
        return 'yes' === $this->debug;
    }

    /**
     * Get payment action from setting.
     *
     * @return string
     */
    public function getPaymentAction()
    {
        return 'authorization' === $this->paymentaction ? 'authorization' : 'sale';
    }

    /**
     * Get active environment from setting.
     *
     * @return string
     */
    public function getEnvironment() {
        return 'sandbox' === $this->environment ? 'sandbox' : 'live';
    }

    /**
     * Get session length.
     *
     * @return int
     */
    public function getTokenSessionLength() {
        return 10800; // 3h
    }
}
