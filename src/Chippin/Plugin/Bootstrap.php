<?php

namespace Chippin\Plugin;

use Chippin\I18n;
use Chippin\Exception;

class Bootstrap
{
    const DEPENDENCIES_UNSATISFIED = 2;
    const NOT_CONFIGURED = 3;

    private $settings;
    private $bootstrapped = false;

    public function __construct(\Chippin\Settings $settings)
    {
        $this->settings = $settings;
    }

    public function isBootstrapped()
    {
        return $this->bootstrapped;
    }

    public function bootstrap() {
        try {
            $this->checkDependencies();
            $this->checkCredentials();
            $this->bootstrapped = true;
            \delete_option('wc_gateway_chippin_bootstrap_warning_message');
            \delete_option('wc_gateway_chippin_prompt_to_configure');
        } catch (\Exception $e) {
            if (in_array(
                $e->getCode(),
                array( self::DEPENDENCIES_UNSATISFIED )
            )) {
                \update_option(
                    'wc_gateway_chippin_bootstrap_warning_message',
                    $e->getMessage()
                );
            }

            if (self::NOT_CONFIGURED === $e->getCode()) {
                \update_option(
                    'wc_gateway_chippin_prompt_to_configure',
                    $e->getMessage()
                );
            }

            \add_action(
                'admin_notices',
                array($this, 'showBootstrapWarning')
            );
        }
    }

    public function showBootstrapWarning() {
        $dependencies_message = get_option(
            'wc_gateway_chippin_bootstrap_warning_message', ''
        );
        if (!empty( $dependencies_message)) {
            ?>
            <div class="error fade">
                <p>
                    <strong><?php echo \esc_html( $dependencies_message ); ?></strong>
                </p>
            </div>
            <?php
        }
        $prompt_configure = get_option(
            'wc_gateway_chippin_prompt_to_configure', ''
        );
        if (!empty($prompt_configure)) {
          ?>
              <div class="error">
              <p>
              <strong><?php echo wp_kses(
                  $prompt_configure,
                  array('a' => array('href' => array()))); ?></strong>
              </p>
              </div>
          <?php
        }
    }

    /**
     * Check dependencies.
     *
     * @throws Exception
     */
    protected function checkDependencies() {
        if (!function_exists("WC")) {
            throw new Exception( __(
                'Chippin Gateway requires WooCommerce to be activated',
                I18n::NAME_SPACE
            ), self::DEPENDENCIES_UNSATISFIED);
        }

        if (\version_compare(WC()->version, "2.5", "<")) {
            throw new Exception(__(
                'Chippin Gateway requires WooCommerce version 2.5 or greater',
                I18n::NAME_SPACE
            ), self::DEPENDENCIES_UNSATISFIED);
        }
    }

    /**
     * Check credentials and prompt admin to connect.
     *
     * @throws Exception
     */
    protected function checkCredentials() {
        $signature = $this->settings->getSignature();
        if ($this->settings->enabled == 'yes' &&
            ('' == $signature->getMerchantId()
             || '' == $signature->getMerchantSecret()
            ))
        {
            $setting_link = $this->getAdminSettingLink();
            throw new Exception(
                \sprintf(__(
                    "Chippin is enabled, but your merchant credentials are not set. To get started, <a href=\"%s\">enter your merchant id and merchant secret</a>.",
                    I18n::NAME_SPACE
                ), \esc_url($setting_link)), self::NOT_CONFIGURED);
        }
    }

    /**
     * Link to settings screen.
     */
    public function getAdminSettingLink() {
        if ( version_compare( WC()->version, "2.6", ">=" ) ) {
            $section_slug = "chippin";
        } else {
            $section_slug = strtolower( "Chippin_Gateway" );
        }
        return admin_url( "admin.php?page=wc-settings&tab=checkout&section=" . $section_slug );
    }
}
