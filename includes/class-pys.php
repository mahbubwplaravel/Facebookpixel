<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * PixelYourSite Core class.
 */
final class PYS extends Settings implements Plugin {
	
	private static $_instance;

	/** @var $eventsManager EventsManager */
	private $eventsManager;
	
    /** @var $registeredPixels array Registered pixels */
    private $registeredPixels = array();
	
    /** @var $registeredPlugins array Registered plugins */
    private $registeredPlugins = array();

    private $adminPagesSlugs = array();
	
	public static function instance() {
		
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		
		return self::$_instance;
		
	}
	
	public function getPluginName() {
		return PYS_PLUGIN_NAME;
	}
	
	public function getPluginFile() {
		return PYS_PLUGIN_FILE;
	}
	
	public function getPluginVersion() {
		return PYS_VERSION;
	}

    public function __construct() {
	
	    add_filter( 'plugin_row_meta', array( $this, 'pluginRowMeta' ), 10, 2 );

	    // initialize settings
	    parent::__construct( 'core' );

	    add_action( 'admin_init', array( $this, 'updatePlugin' ), 0 );
	    add_action( 'admin_init', 'PixelYourSite\manageAdminPermissions' );
	
	    /**
	     * Priority 9 used because on some events, like EDD's CompleteRegistration, are fired on 'init' action
	     * with default (10) priority and PYS should be initialized before it.
	     *
	     * 3rd party extensions, like Pinterest addon, should be loaded with lower priority.
	     */
        add_action( 'init', array( $this, 'init' ), 9 );
        add_action( 'init', array( $this, 'afterInit' ), 11 );

        add_action( 'admin_menu', array( $this, 'adminMenu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'adminEnqueueScripts' ) );
        add_action( 'admin_notices', 'PixelYourSite\adminRenderNotices' );
        add_action( 'admin_init', array( $this, 'adminProcessRequest' ), 11 );

	    if ( ! defined( 'DOING_AJAX' ) ) {
		    add_action( 'template_redirect', array( $this, 'managePixels' ) );
	    }

	    // track user registrations
	    add_action( 'user_register', array( $this, 'userRegisterHandler' ) );

	    // "admin_permission" option custom sanitization function
	    add_filter( 'pys_core_settings_sanitize_admin_permissions_field', function( $value ) {

	    	// "administrator" always should be allowed
	    	if ( ! is_array( $value ) || ! in_array( 'administrator', $value ) ) {
	    		$value[] = 'administrator';
		    }

		    manageAdminPermissions();

	    	return $this->sanitize_multi_select_field( $value );

	    } );
	
	    add_action( 'wp_ajax_pys_get_gdpr_filters_values', array( $this, 'ajaxGetGdprFiltersValues' ) );
	    add_action( 'wp_ajax_nopriv_pys_get_gdpr_filters_values', array( $this, 'ajaxGetGdprFiltersValues' ) );
    }

    public function init() {
	
	    register_post_type( 'pys_event', array(
		    'public' => false,
		    'supports' => array( 'title' )
	    ) );
	
	    // initialize options
	    $this->locateOptions(
		    PYS_PATH . '/includes/options_fields.json',
		    PYS_PATH . '/includes/options_defaults.json'
	    );
	    
	    // register pixels and plugins (addons)
	    do_action( 'pys_register_pixels', $this );
	    do_action( 'pys_register_plugins', $this );
	    
        // load dummy Pinterest plugin for admin UI
	    if ( ! array_key_exists( 'pinterest', $this->registeredPlugins ) ) {
		    /** @noinspection PhpIncludeInspection */
		    require_once PYS_PATH . '/modules/pinterest/pinterest.php';
	    }
	    
        // maybe disable Facebook for WooCommerce pixel output
	    if ( isWooCommerceActive() && $this->getOption( 'woo_enabled' )
	         && array_key_exists( 'facebook', $this->registeredPixels ) && Facebook()->configured() ) {
		    add_filter( 'facebook_for_woocommerce_integration_pixel_enabled', '__return_false' );
	    }

    }
	
	/**
	 * Extend options after post types are registered
	 */
    public function afterInit() {
	
	    // add available public custom post types to settings
	    foreach ( get_post_types( array( 'public' => true, '_builtin' => false ), 'objects' ) as $post_type ) {
		
		    // skip product post type when WC is active
		    if ( isWooCommerceActive() && $post_type->name == 'product' ) {
			    continue;
		    }
		
		    // skip download post type when EDD is active
		    if ( isEddActive() && $post_type->name == 'download' ) {
			    continue;
		    }
		
		    $this->addOption( 'general_event_on_' . $post_type->name . '_enabled', 'checkbox', false );
		
	    }
	
	    maybeMigrate();
	    
    }
	
	/**
	 * @param Pixel|Settings $pixel
	 */
    public function registerPixel( &$pixel ) {
	    $this->registeredPixels[ $pixel->getSlug() ] = $pixel;
    }
	
	/**
	 * Return array of registered pixels
	 *
	 * @return array
	 */
	public function getRegisteredPixels() {
		return $this->registeredPixels;
	}
	
	/**
	 * @param Pixel|Settings $plugin
	 */
	public function registerPlugin( &$plugin ) {
		$this->registeredPlugins[ $plugin->getSlug() ] = $plugin;
	}
	
	/**
	 * Return array of registered plugins
	 *
	 * @return array
	 */
    public function getRegisteredPlugins() {
	    return $this->registeredPlugins;
    }

	/**
	 * Front-end entry point
	 */
    public function managePixels() {

    	// output debug info
	    add_action( 'wp_head', function() {
		    echo "<script type='text/javascript'>console.log('PixelYourSite PRO version " . PYS_VERSION . "');</script>\r\n";
	    }, 1 );

	    if ( isDisabledForCurrentRole() ) {
	    	return;
	    }

	    // at least one pixel should be configured
	    if ( ! Facebook()->configured() && ! GA()->configured() && ! Ads()->configured() && ! Pinterest()->configured() ) {

		    add_action( 'wp_head', function() {
			    echo "<script type='text/javascript'>console.warn('PixelYourSite PRO: no pixel configured.');</script>\r\n";
		    } );

	    	return;

	    }

	    // setup events
	    $this->eventsManager = new EventsManager();

    }
    
    public function ajaxGetGdprFiltersValues() {

	    wp_send_json_success( array(
		    'all_disabled_by_api'       => apply_filters( 'pys_disable_by_gdpr', false ),
		    'facebook_disabled_by_api'  => apply_filters( 'pys_disable_facebook_by_gdpr', false ),
		    'analytics_disabled_by_api' => apply_filters( 'pys_disable_analytics_by_gdpr', false ),
            'google_ads_disabled_by_api' => apply_filters( 'pys_disable_google_ads_by_gdpr', false ),
		    'pinterest_disabled_by_api' => apply_filters( 'pys_disable_pinterest_by_gdpr', false ),
	    ) );
    
    }
	
	public function userRegisterHandler( $user_id ) {
		
		if ( isEventEnabled( 'complete_registration_event_enabled' ) ) {
			update_user_meta( $user_id, 'pys_complete_registration', true );
		}
		
	}
	
	public function getEventsManager() {
		return $this->eventsManager;
	}
	
    public function adminMenu() {
        global $submenu;
	    
        add_menu_page( 'PixelYourSite', 'PixelYourSite', 'manage_pys', 'pixelyoursite',
            array( $this, 'adminPageMain' ), PYS_URL . '/dist/images/favicon.png' );

        add_submenu_page( 'pixelyoursite', 'Licenses', 'Licenses',
            'manage_pys', 'pixelyoursite_licenses', array( $this, 'adminPageLicenses' ) );

        add_submenu_page( 'pixelyoursite', 'System Report', 'System Report',
            'manage_pys', 'pixelyoursite_report', array( $this, 'adminPageReport' ) );

        // core admin pages
        $this->adminPagesSlugs = array(
            'pixelyoursite',
            'pixelyoursite_licenses',
            'pixelyoursite_report',
        );

        // rename first submenu item
        if ( isset( $submenu['pixelyoursite'] ) ) {
            $submenu['pixelyoursite'][0][0] = 'Dashboard';
        }
	
	    $this->adminSaveSettings();
     
    }

    public function adminEnqueueScripts() {

        if ( in_array( getCurrentAdminPage(), $this->adminPagesSlugs ) ) {
	
	        wp_register_style( 'select2', '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css' );
	        wp_register_script( 'select2', '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js',
		        array( 'jquery' ) );
	
	        wp_deregister_script( 'jquery' );
	        wp_enqueue_script( 'jquery', '//cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js' );
	
	        wp_enqueue_script( 'popper', '//cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.3/umd/popper.min.js', 'jquery' );
	        wp_enqueue_script( 'bootstrap', '//maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta.2/js/bootstrap.min.js', 'jquery',
		        'popper' );
	        
            wp_enqueue_style( 'pys', PYS_URL . '/dist/styles/admin.css', array( 'select2' ), PYS_VERSION );
            wp_enqueue_script( 'pys', PYS_URL . '/dist/scripts/admin.js', array( 'jquery', 'select2', 'popper',
                                                                                 'bootstrap' ), PYS_VERSION );

        }

    }

    public function adminPageMain() {
	    
        $this->adminResetSettings();
        $this->adminExportCustomAudiences();

        include 'views/html-wrapper-main.php';

    }

	public function adminPageReport() {
		include 'views/html-report.php';
	}

	public function adminPageLicenses() {
		
    	$this->adminUpdateLicense();
		
		/** @var Plugin|Settings $plugin */
		foreach ( $this->registeredPlugins as $plugin ) {
			if ( $plugin->getSlug() !== 'head_footer' ) {
				$plugin->adminUpdateLicense();
			}
		}

		include 'views/html-licenses.php';

	}
	
	public function adminProcessRequest() {
        $this->adminCheckLicense();
        $this->adminUpdateCustomEvents();
        $this->adminEnableGdprAjax();
    }
	
	private function adminCheckLicense() {
    	
    	$is_dashboard = isset( $_REQUEST['page'] ) && $_REQUEST['page'] == 'pixelyoursite';
		$license_status = $this->getOption( 'license_status' );
		
		// redirect to license page in case if license was never activated
		if ( $is_dashboard && empty( $license_status ) ) {
			wp_safe_redirect( buildAdminUrl( 'pixelyoursite_licenses' ) );
			exit;
		}
		
	}
	
	public function adminUpdateLicense() {

		if ( ! $this->adminSecurityCheck() ) {
			return;
		}

		updateLicense( $this );

	}

	public function updatePlugin() {
        
        foreach ( $this->registeredPlugins as $slug => $plugin ) {
            
            if ( $slug == 'head_footer' ) {
                continue;
            }
            
            updatePlugin( $plugin );
            
        }
        
		updatePlugin( $this );
  
	}

	public function adminSecurityCheck() {

		// verify user access
		if ( ! current_user_can( 'manage_pys' ) ) {
			return false;
		}

		// nonce filed and PYS data are required request
		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! isset( $_REQUEST['pys'] ) ) {
			return false;
		}

		return true;

	}
	
    private function adminEnableGdprAjax() {
        
        if ( ! $this->adminSecurityCheck() ) {
            return;
        }
    
        if ( isset( $_REQUEST['pys']['enable_gdpr_ajax'] ) ) {
            $this->updateOptions( array(
                'gdpr_ajax_enabled' => true,
                'gdpr_cookie_law_info_integration_enabled' => true,
            ) );

            add_action( 'admin_notices', 'PixelYourSite\adminGdprAjaxEnabledNotice' );
            purgeCache();
        }
        
    }
    
	private function adminUpdateCustomEvents() {
		
		if ( ! $this->adminSecurityCheck() ) {
			return;
		}
		
		/**
		 * Single Custom Event Actions
		 */
		if ( isset( $_REQUEST['pys']['event'] ) && isset( $_REQUEST['action'] ) ) {
			
			$nonce   = isset( $_REQUEST['_wpnonce'] ) ? $_REQUEST['_wpnonce'] : null;
			$action  = $_REQUEST['action'];
			$post_id = isset( $_REQUEST['pys']['event']['post_id'] ) ? $_REQUEST['pys']['event']['post_id'] : false;
			
			if ( $action == 'update' && wp_verify_nonce( $nonce, 'pys_update_event' ) ) {
				
				if ( $post_id ) {
					$event = CustomEventFactory::getById( $post_id );
					$event->update( $_REQUEST['pys']['event'] );
				} else {
					CustomEventFactory::create( $_REQUEST['pys']['event'] );
				}
				
			} elseif ( $action == 'enable' && $post_id && wp_verify_nonce( $nonce, 'pys_enable_event' ) ) {
				
				$event = CustomEventFactory::getById( $post_id );
				$event->enable();
				
			} elseif ( $action == 'disable' && $post_id && wp_verify_nonce( $nonce, 'pys_disable_event' ) ) {
				
				$event = CustomEventFactory::getById( $post_id );
				$event->disable();
				
			} elseif ( $action == 'remove' && $post_id && wp_verify_nonce( $nonce, 'pys_remove_event' ) ) {
				
				CustomEventFactory::remove( $post_id );
				
			}
			
			purgeCache();
			
			// redirect to events tab
			wp_safe_redirect( buildAdminUrl( 'pixelyoursite', 'events' ) );
			exit;
			
		}
		
		/**
		 * Bulk Custom Events Actions
		 */
		if ( isset( $_REQUEST['pys']['bulk_event_action'], $_REQUEST['pys']['selected_events'] )
		     && isset( $_REQUEST['pys']['bulk_event_action_nonce'] )
		     && wp_verify_nonce( $_REQUEST['pys']['bulk_event_action_nonce'], 'bulk_event_action' )
		     && is_array( $_REQUEST['pys']['selected_events'] ) ) {
			
			foreach ( $_REQUEST['pys']['selected_events'] as $event_id ) {
				
				$event_id = (int) $event_id;
				
				switch ( $_REQUEST['pys']['bulk_event_action'] ) {
					case 'enable':
						$event = CustomEventFactory::getById( $event_id );
						$event->enable();
						break;
					
					case 'disable':
						$event = CustomEventFactory::getById( $event_id );
						$event->disable();
						break;
					
					case 'clone':
						CustomEventFactory::makeClone( $event_id );
						break;
					
					case 'delete':
						CustomEventFactory::remove( $event_id );
						break;
				}
				
			}
			
			purgeCache();
			
			// redirect to events tab
			wp_safe_redirect( buildAdminUrl( 'pixelyoursite', 'events' ) );
			exit;
			
		}
		
	}
	
	/**
	 * Show row meta on the plugin screen.
	 *
	 * @param mixed $links Plugin Row Meta.
	 * @param mixed $file  Plugin Base file.
	 *
	 * @return array
	 */
	public function pluginRowMeta( $links, $file ) {
		
		if ( PYS_PLUGIN_BASENAME === $file ) {
			$links[] = '<a href="https://www.pixelyoursite.com/documentation">Help</a>';
		}
		
		return (array) $links;
  
	}
    
    private function adminSaveSettings() {

    	if ( ! $this->adminSecurityCheck() ) {
    		return;
	    }

        if ( wp_verify_nonce( $_REQUEST['_wpnonce'], 'pys_save_settings' ) ) {
    
            $core_options = isset( $_POST['pys']['core'] ) ? $_POST['pys']['core'] : array();

            $gdpr_ajax_enabled = isset( $core_options['gdpr_ajax_enabled'] )
                ? $core_options['gdpr_ajax_enabled']        // value from form data
                : $this->getOption('gdpr_ajax_enabled');    // previous value

            // allow 3rd party plugins to by-pass option value
            $core_options['gdpr_ajax_enabled'] = apply_filters( 'pys_gdpr_ajax_enabled', $gdpr_ajax_enabled );

            // update core options
            $this->updateOptions( $core_options );
        	
        	$objects = array_merge( $this->registeredPixels, $this->registeredPlugins );

        	// update plugins and pixels options
	        foreach ( $objects as $obj ) {
	        	/** @var Plugin|Pixel|Settings $obj */
		        $obj->updateOptions();
	        }
	
	        purgeCache();
	        
        }
	    
    }
    
    private function adminResetSettings() {
	
	    if ( ! $this->adminSecurityCheck() ) {
		    return;
	    }
	
	    if ( wp_verify_nonce( $_REQUEST['_wpnonce'], 'pys_save_settings' ) && isset( $_REQUEST['pys']['reset_settings'] ) ) {
		    
		    if ( isSuperPackActive() ) {
			
			    $old_options = array(
				    'license_key'     => SuperPack()->getOption( 'license_key' ),
				    'license_status'  => SuperPack()->getOption( 'license_status' ),
				    'license_expires' => SuperPack()->getOption( 'license_expires' ),
			    );
			    
		    	SuperPack()->resetToDefaults();
		    	SuperPack()->updateOptions( $old_options );
			   
		    }
		    
		    if ( isPinterestActive() ) {
			
			    $old_options = array(
				    'license_key'     => Pinterest()->getOption( 'license_key' ),
				    'license_status'  => Pinterest()->getOption( 'license_status' ),
				    'license_expires' => Pinterest()->getOption( 'license_expires' ),
				    'pixel_id'        => Pinterest()->getOption( 'pixel_id' ),
			    );
			
			    Pinterest()->resetToDefaults();
			    Pinterest()->updateOptions( $old_options );
		    	
		    }
		    
		    // Core
		    $old_options = array(
			    'license_key'     => $this->getOption( 'license_key' ),
			    'license_status'  => $this->getOption( 'license_status' ),
			    'license_expires' => $this->getOption( 'license_expires' ),
		    );
		
		    PYS()->resetToDefaults();
		    PYS()->updateOptions( $old_options );
		
		    // Facebook
		    $old_options = array(
			    'pixel_id' => Facebook()->getOption( 'pixel_id' ),
		    );
		    
		    Facebook()->resetToDefaults();
		    Facebook()->updateOptions( $old_options );
		    
		    // Google Analytics
		    $old_options = array(
			    'tracking_id' => GA()->getOption( 'tracking_id' ),
		    );
		
		    GA()->resetToDefaults();
		    GA()->updateOptions( $old_options );
		
		    // Google Analytics
		    $old_options = array(
			    'ads_ids' => Ads()->getOption( 'ads_ids' ),
		    );
		    Ads()->resetToDefaults();
		    GA()->updateOptions( $old_options );
		    
		    //HeadFooter()->resetToDefaults();
		    
		    // do redirect
		    wp_safe_redirect( buildAdminUrl( 'pixelyoursite' ) );
		    exit;
		    
	    }
    
    }
    
    private function adminExportCustomAudiences() {
	
	    if ( ! $this->adminSecurityCheck() ) {
		    return;
	    }
	
	    if ( isset( $_REQUEST['pys']['export_custom_audiences'] )
	         && wp_verify_nonce( $_REQUEST['_wpnonce'], 'pys_save_settings' ) ) {
	    	
	    	if ( $_REQUEST['pys']['export_custom_audiences'] == 'woo' && isWooCommerceActive() ) {
			    wooExportCustomAudiences();
		    } elseif ( $_REQUEST['pys']['export_custom_audiences'] == 'edd' ) {
			    eddExportCustomAudiences();
		    }
		    
	    }
    
    }

}

/**
 * @return PYS
 */
function PYS() {
    return PYS::instance();
}