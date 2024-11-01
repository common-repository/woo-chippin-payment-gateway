<?php

namespace Chippin\Settings;

use Chippin\I18n;

class Form
{
    public function getFields()
    {
        return array(
            'enabled' => array(
                'title'   => \__('Enable/Disable', I18n::NAME_SPACE),
                'type'    => 'checkbox',
                'label'   => \__('Enable Chippin', I18n::NAME_SPACE),
                'description' => \__('This enables Chippin which allows customers to checkout with Chippin from your cart page.', I18n::NAME_SPACE),
                'desc_tip'    => true,
                'default'     => 'yes',
            ),

            'title' => array(
                'title'       => \__('Title', I18n::NAME_SPACE),
                'type'        => 'text',
                'description' => \__('This controls the title which the user sees during checkout.', I18n::NAME_SPACE),
                'default'     => \__('Chippin', I18n::NAME_SPACE),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => \__('Description', I18n::NAME_SPACE),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => \__('This controls the description which the user sees during checkout.', I18n::NAME_SPACE),
                'default'     => \__('Chippin; split the cost with friends and family', I18n::NAME_SPACE),
            ),

            'account_settings' => array(
                'title'       => \__('Account Settings', I18n::NAME_SPACE),
                'type'        => 'title',
                'description' => '',
            ),
            'environment' => array(
                'title'       => \__('Environment', I18n::NAME_SPACE),
                'type'        => 'select',
                'class'       => 'wc-enhanced-select',
                'description' => \__('This setting specifies whether you will process live transactions, or whether you will process simulated transactions using the Chippin Sandbox.', I18n::NAME_SPACE),
                'default'     => 'live',
                'desc_tip'    => true,
                'options'     => array(
                    'live'    => \__('Live', I18n::NAME_SPACE),
                    'sandbox' => \__('Sandbox', I18n::NAME_SPACE),
                ),
            ),
            'debug' => array(
                'title'       => __('Debug Log', I18n::NAME_SPACE),
                'type'        => 'checkbox',
                'label'       => __('Enable Logging', I18n::NAME_SPACE),
                'default'     => 'no',
                'desc_tip'    => true,
                'description' => __('Log Chippin events, such as IPN requests.', I18n::NAME_SPACE),
            ),
            'merchant_credentials' => array(
                'title'       => \__('Merchant Credentials', I18n::NAME_SPACE),
                'type'        => 'title',
                'description' => \__( "Your merchant credentials can be found in your Chippin account page. Enter them here:", "woocommerce-gateway-chippin"),
            ),
            'merchant_id' => array(
                'title'       => \__('Merchant ID', I18n::NAME_SPACE),
                'type'        => 'text',
                'description' => \__('Get your Merchant ID credentials from Chippin.', I18n::NAME_SPACE),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'merchant_secret' => array(
                'title'       => \__('Merchant Secret', I18n::NAME_SPACE),
                'type'        => 'text',
                'description' => \__('Get your Merchant Secret from Chippin.', I18n::NAME_SPACE),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'chippin_config' => array(
                'title'       => \__('Chippin Settings', I18n::NAME_SPACE),
                'type'        => 'title',
                'description' => \__( "Configure how Chippin should behave:", I18n::NAME_SPACE),
            ),
            'duration' => array(
                'title'       => \__('Chippin Duration', I18n::NAME_SPACE),
                'type'        => 'text',
                'description' => \__('The duration you wish to allow for contributions to be made.', I18n::NAME_SPACE),
                'default'     => '48',
                'desc_tip'    => true,
            ),
            'grace_period' => array(
                'title'       => \__('Chippin Grace Period', I18n::NAME_SPACE),
                'type'        => 'text',
                'description' => \__('The period of time you wish to allow the instigator to complete the transaction after a failed chippin.', I18n::NAME_SPACE),
                'default'     => '48',
                'desc_tip'    => true,
            ),
        );
       // TODO: write logic here
    }
}
