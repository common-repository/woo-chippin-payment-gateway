<?php
/**
 * Plugin Name: WooCommerce Chippin Payment Gateway
 * Description: A payment gateway for Chippin (https://www.chippin.co.uk).
 * Version: 0.1.5
 * Author: Chippin
 * Author URI: https://chippin.co.uk
 * Copyright: © 2017 Chippin.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */
/**
 * Copyright (c) 2017 Chippin
 *
 * The name of the Chippin may not be used to endorse or promote products derived from this
 * software without specific prior written permission. THIS SOFTWARE IS PROVIDED ``AS IS'' AND
 * WITHOUT ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, WITHOUT LIMITATION, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE.
 */

if ( !defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

define( 'WC_GATEWAY_CHIPPIN_VERSION', '0.1.5' );

require_once("vendor/autoload.php");


Chippin\Plugin::maybeRun(__FILE__, WC_GATEWAY_CHIPPIN_VERSION);
