<?php

namespace Chippin;

use Chippin\I18n;
use Chippin\Logger;
use Chippin\Gateway;
use Chippin\Plugin\Bootstrap;
use Chippin\Handler\Checkout;
use Chippin\Handler\Ipn;

class Plugin
{
    const DEPENDENCIES_UNSATISFIED = 2;
    const NOT_CONFIGURED = 3;

    private static $_plugin;

    public $bootstrap;

    /**
     * Filepath of main plugin file.
     *
     * @var string
     */
    public $file;

    /**
     * Absolute plugin path.
     *
     * @var string
     */
    public $plugin_path;

    /**
     * Absolute plugin URL.
     *
     * @var string
     */
    public $plugin_url;


    private $version;

    /**
     * Instance of Chippin\Settings.
     *
     * @var Chippin\Settings
     */

    private $settings;

    /**
     * Instance of Chippin\Logger.
     *
     * @var Chippin\Logger
     */
    private $logger;

    /**
     * Instance of Chippin\Gateway.
     *
     * @var Chippin\Gateway
     */
    private $gateway;

    /**
     * Instance of Chippin\Handler\Checkout.
     *
     * @var Chippin\Handler\Checkout
     */
    private $checkout;

    /**
     * Instance of Chippin\Handler\Ipn.
     *
     * @var Chippin\Handler\Ipn
     */
    private $ipn;

    /**
     * Instance of Chippin\Handler\Admin.
     *
     * @var Chippin\Handler\Admin
     */
    private $admin;


    /**
     * Maybe run the plugin.
     */
    public static function maybeRun($file) {
        if (self::$_plugin == NULL) {
            self::$_plugin = new Plugin($file);
            \register_activation_hook(
                $file,
                array(self::$_plugin, 'activate')
            );

            \add_action(
                'plugins_loaded',
                array(self::$_plugin, 'bootstrap')
            );
            \add_filter(
                'allowed_redirect_hosts' ,
                array(self::$_plugin, 'addChippinDomainsForRedirectToWhitelist')
            );
            \add_action(
                'init',
                array(self::$_plugin, 'loadPluginDomainText')
            );

            \add_filter(
                'plugin_action_links_' . \plugin_basename($file),
                array(self::$_plugin, 'addPluginActionLinks')
            );

        }

        return self::$_plugin;
    }

    public static function getInstance($file = null)
    {
        return self::maybeRun($file);
    }

    public function getSettings()
    {
        return $this->settings;
    }

    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Constructor.
     *
     * @param string $file    Filepath of main plugin file
     */
    private function __construct($file, $version = '0.1.0')
    {
        $this->file = $file;
        $this->plugin_path = \trailingslashit(\plugin_dir_path($this->file));
        $this->version = $version;
        $this->settings = new Settings();
        $this->logger = new Logger($this->settings, $file, $version);
        $this->bootstrap = new Bootstrap($this->settings);
    }

    /**
     * Callback for activation hook.
     */
    public function activate()
    {

    }


    public function bootstrap() {
        try {
            $this->bootstrap->bootstrap();

            $this->gateway = new Gateway();
            $this->checkout = new Checkout($this->settings, $this->logger);
            $this->ipn = new Ipn($this->settings, $this->logger);
        } catch (\Exception $e) {

        }
    }

    /**
     * Allow Chippin domains for redirect.
     *
     * @param array $domains Whitelist domains for `wp_safe_redirect`
     *
     * @return array $domains Whitelist domains for `wp_safe_redirect`
     */
    public function addChippinDomainsForRedirectToWhitelist($domains) {
        $domains[] = 'www.chippin.co.uk';
        $domains[] = 'chippin.co.uk';

        return $domains;
    }

    /**
     * Load localisation files.
     *
     */
    public function loadPluginDomainText() {
        \load_plugin_textdomain(
            I18n::NAME_SPACE,
            false,
            \plugin_basename($this->plugin_path ) . '/languages'
        );
    }

    /**
     * Add relevant links to plugins page.
     *
     * @param array $links Plugin action links
     *
     * @return array Plugin action links
     */
    public function addPluginActionLinks( $links ) {
        $plugin_links = array();

        if ($this->bootstrap->isBootstrapped()) {
            $setting_url = $this->bootstrap->getAdminSettingLink();
            $plugin_links[] = '<a href="' . esc_url( $setting_url ) . '">' . \esc_html__('Settings', I18n::NAME_SPACE) . '</a>';
        }

        $plugin_links[] = '<a href="https://docs.chippin.co.uk">' . \esc_html__( 'Docs', I18n::NAME_SPACE) . '</a>';

        return \array_merge($plugin_links, $links);
    }
}
