<?php

/**
 * Plugin Name: PixelYourSite PRO
 * Plugin URI: http://www.pixelyoursite.com/
 * Description: Implement the Facebook Pixel, Google Analytics, and the Google Ads Tag. Track key actions with automatic events, or create your own events. WooCommerce and EDD fully supported, with Facebook Dynamic Ads Pixel set-up,  Google Analytics Enhanced Ecommerce, and Dynamic Remarketing.
 * Version: 7.1.1
 * Author: PixelYourSite
 * Author URI: http://www.pixelyoursite.com
 * License URI: http://www.pixelyoursite.com/pixel-your-site-pro-license
 *
 * Requires at least: 4.4
 * Tested up to: 5.1.0
 *
 * WC requires at least: 2.6.0
 * WC tested up to: 3.5.6
 *
 * Text Domain: pys
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

define( 'PYS_VERSION', '7.1.1' );
define( 'PYS_PINTEREST_MIN_VERSION', '2.0.3' );
define( 'PYS_SUPER_PACK_MIN_VERSION', '2.0.3' );
define( 'PYS_PLUGIN_NAME', 'PixelYourSite Pro' );
define( 'PYS_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'PYS_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'PYS_PLUGIN_FILE', __FILE__ );
define( 'PYS_PLUGIN_BASENAME', plugin_basename( PYS_PLUGIN_FILE ) );

require_once 'vendor/autoload.php';
require_once 'includes/functions-common.php';
require_once 'includes/functions-admin.php';
require_once 'includes/functions-custom-event.php';
require_once 'includes/functions-woo.php';
require_once 'includes/functions-edd.php';
require_once 'includes/functions-system-report.php';
require_once 'includes/functions-license.php';
require_once 'includes/functions-update-plugin.php';
require_once 'includes/functions-gdpr.php';
require_once 'includes/functions-migrate.php';
require_once 'includes/class-pixel.php';
require_once 'includes/class-settings.php';
require_once 'includes/class-plugin.php';

register_activation_hook( __FILE__, 'pysProActivation' );
function pysProActivation() {

    if ( PixelYourSite\isPysFreeActive() ) {
        wp_die( 'You must first deactivate PixelYourSite Free version.', 'Plugin Activation', array(
            'back_link' => true,
        ) );
    }

	\PixelYourSite\manageAdminPermissions();

}

if ( PixelYourSite\isPysFreeActive() ) {
    return; // exit early when PYS Free is active
}

require_once 'includes/class-pys.php';
require_once 'includes/class-events-manager.php';
require_once 'includes/class-custom-event.php';
require_once 'includes/class-custom-event-factory.php';
require_once 'modules/facebook/facebook.php';
require_once 'modules/google_analytics/ga.php';
require_once 'modules/google_ads/google_ads.php';
require_once 'modules/head_footer/head_footer.php';

// here we go...
PixelYourSite\PYS();
