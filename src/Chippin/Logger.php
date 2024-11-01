<?php

namespace Chippin;

class Logger
{
    private $settings;
    private $sentryClient;

    public function __construct(\Chippin\Settings $settings, $file = '', $version = '0.1.0')
    {
        $this->settings = $settings;
        $this->sentryClient = new \Raven_Client('https://f6419e1fd8d8488a9f7a6f68056b17e6:9229a65353f243bf920fd308441cd2c5@sentry.io/233938');

        $this->sentryClient->setSendCallback(function($data) {
            $accepted_types = array('Chippin\Exception');

            if (isset($data['exception']) && !in_array($data['exception']['values'][0]['type'], $accepted_types))
            {
                return false;
            }
        });
        if (!$this->settings->isLoggingEnabled()) {
            return false;
        }


        $error_handler = new \Raven_ErrorHandler($this->sentryClient, array(
            'release' => $version,
            'tags' => array(
                'php_version' => phpversion(),
            ),
            'environment' => 'production',
            'app_path' => trailingslashit(plugin_dir_path($file))
        ));

        $error_handler->registerExceptionHandler();
    }

    public function log($message, $level = 'debug', $extra = array())
    {
        if (!$this->settings->isLoggingEnabled()) {
            return false;
        }
        $this->sentryClient->captureMessage($message, array(), array(
            'extra' => $extra,
            'level' => $level,
        ));
        return true;
    }
}
