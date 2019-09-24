<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class EventsManager {
	
	public $doingAMP = false;
	
	private $staticEvents = array();

	private $dynamicEventsParams = array();

	private $dynamicEventsTriggers = array();

	private $wooCustomerTotals = array();

	private $eddCustomerTotals = array();

	public function __construct() {

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueueScripts' ) );

		add_action( 'wp_head', array( $this, 'setupEventsParams' ), 3 );
		add_action( 'wp_head', array( $this, 'outputData' ), 4 );
		add_action( 'wp_footer', array( $this, 'outputNoScriptData' ), 10 );

	}

	public function enqueueScripts() {
	    
	    wp_register_script( 'vimeo', PYS_URL . '/dist/scripts/vimeo.min.js' );
        wp_register_script( 'jquery-bind-first', PYS_URL . '/dist/scripts/jquery.bind-first-0.2.3.min.js', array( 'jquery' ) );
        wp_register_script( 'js-cookie', PYS_URL . '/dist/scripts/js.cookie-2.1.3.min.js', array(), '2.1.3' );
        
		wp_enqueue_script( 'js-cookie' );
		wp_enqueue_script( 'jquery-bind-first' );

		if ( isEventEnabled( 'watchvideo_event_enabled' ) ) {
			wp_enqueue_script( 'vimeo' );
		}
  
		wp_enqueue_script( 'pys', PYS_URL . '/dist/scripts/public.js',
			array( 'jquery', 'js-cookie', 'jquery-bind-first' ), PYS_VERSION );

	}

	public function outputData() {
	    global $post;

		$data = array(
			'staticEvents'          => $this->staticEvents,
			'dynamicEventsParams'   => $this->dynamicEventsParams,
			'dynamicEventsTriggers' => $this->dynamicEventsTriggers,
		);

		// collect options for configured pixel
		foreach ( PYS()->getRegisteredPixels() as $pixel ) {
			/** @var Pixel|Settings $pixel */
      
		    if ( $pixel->configured() ) {
			    $data[ $pixel->getSlug() ] = $pixel->getPixelOptions();
		    }
		    
		}
		
		$options = array(
			'debug'                => PYS()->getOption( 'debug_enabled' ),
			'siteUrl'              => site_url(),
			'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
			'commonEventParams'    => getCommonEventParams(),
			'clickEventEnabled'    => isEventEnabled( 'click_event_enabled' ),
			'adSenseEventEnabled'  => isEventEnabled( 'adsense_enabled' ),
			'watchVideoEnabled'    => isEventEnabled( 'watchvideo_event_enabled' ),
			'commentEventEnabled'  => isEventEnabled( 'comment_event_enabled' ),
			'formEventEnabled'     => isEventEnabled( 'form_event_enabled' ),
			'downloadEventEnabled' => isEventEnabled( 'download_event_enabled' ),
			'downloadExtensions'   => PYS()->getOption( 'download_event_extensions' ),
			'trackUTMs'            => PYS()->getOption( 'track_utms' ),
			'trackTrafficSource'   => PYS()->getOption( 'track_traffic_source' ),
		);
		
		$options['gdpr'] = array(
			'ajax_enabled'              => PYS()->getOption( 'gdpr_ajax_enabled' ),
			'all_disabled_by_api'       => apply_filters( 'pys_disable_by_gdpr', false ),
			'facebook_disabled_by_api'  => apply_filters( 'pys_disable_facebook_by_gdpr', false ),
			'analytics_disabled_by_api' => apply_filters( 'pys_disable_analytics_by_gdpr', false ),
            'google_ads_disabled_by_api' => apply_filters( 'pys_disable_google_ads_by_gdpr', false ),
			'pinterest_disabled_by_api' => apply_filters( 'pys_disable_pinterest_by_gdpr', false ),
			
			'facebook_prior_consent_enabled'   => PYS()->getOption( 'gdpr_facebook_prior_consent_enabled' ),
			'analytics_prior_consent_enabled'  => PYS()->getOption( 'gdpr_analytics_prior_consent_enabled' ),
			'google_ads_prior_consent_enabled' => PYS()->getOption( 'gdpr_google_ads_prior_consent_enabled' ),
			'pinterest_prior_consent_enabled'  => PYS()->getOption( 'gdpr_pinterest_prior_consent_enabled' ),
			
			'cookiebot_integration_enabled'         => isCookiebotPluginActivated() && PYS()->getOption( 'gdpr_cookiebot_integration_enabled' ),
			'cookiebot_facebook_consent_category'   => PYS()->getOption( 'gdpr_cookiebot_facebook_consent_category' ),
			'cookiebot_analytics_consent_category'  => PYS()->getOption( 'gdpr_cookiebot_analytics_consent_category' ),
			'cookiebot_google_ads_consent_category' => PYS()->getOption( 'gdpr_cookiebot_google_ads_consent_category' ),
			'cookiebot_pinterest_consent_category'  => PYS()->getOption( 'gdpr_cookiebot_pinterest_consent_category' ),
			
			'ginger_integration_enabled' => isGingerPluginActivated() && PYS()->getOption( 'gdpr_ginger_integration_enabled' ),
			'cookie_notice_integration_enabled' => isCookieNoticePluginActivated() && PYS()->getOption( 'gdpr_cookie_notice_integration_enabled' ),
			'cookie_law_info_integration_enabled' => isCookieLawInfoPluginActivated() && PYS()->getOption( 'gdpr_cookie_law_info_integration_enabled' ),
		);
		
		$options['woo'] = array(
			'enabled'                       => isWooCommerceActive() && PYS()->getOption( 'woo_enabled' ),
			'addToCartOnButtonEnabled'      => isEventEnabled( 'woo_add_to_cart_enabled' ) && PYS()->getOption( 'woo_add_to_cart_on_button_click' ),
			'addToCartOnButtonValueEnabled' => PYS()->getOption( 'woo_add_to_cart_value_enabled' ),
			'addToCartOnButtonValueOption'  => PYS()->getOption( 'woo_add_to_cart_value_option' ),
			'singleProductId'               => isWooCommerceActive() && is_singular( 'product' ) ? $post->ID : null,
			'removeFromCartEnabled'         => isEventEnabled( 'woo_remove_from_cart_enabled' ),
			'affiliateEnabled'              => isEventEnabled( 'woo_affiliate_enabled' ),
			'payPalEnabled'                 => isEventEnabled( 'woo_paypal_enabled' ),
			'removeFromCartSelector'        => isWooCommerceVersionGte( '3.0.0' )
                ? 'form.woocommerce-cart-form .remove'
				: '.cart .product-remove .remove',
		);

		$options['edd'] = array(
			'enabled'                       => isEddActive() && PYS()->getOption( 'edd_enabled' ),
			'addToCartOnButtonEnabled'      => isEventEnabled( 'edd_add_to_cart_enabled' ) && PYS()->getOption( 'edd_add_to_cart_on_button_click' ),
			'addToCartOnButtonValueEnabled' => PYS()->getOption( 'edd_add_to_cart_value_enabled' ),
			'addToCartOnButtonValueOption'  => PYS()->getOption( 'edd_add_to_cart_value_option' ),
			'removeFromCartEnabled'         => isEventEnabled( 'edd_remove_from_cart_enabled' ),
		);

		$woo_affiliate_custom_event_type = PYS()->getOption( 'woo_affiliate_custom_event_type' );
		
		if ( PYS()->getOption( 'woo_affiliate_event_type' ) == 'custom' && ! empty( $woo_affiliate_custom_event_type ) ) {
			$options['woo']['affiliateEventName'] = sanitizeKey( PYS()->getOption( 'woo_affiliate_custom_event_type' ) );
		} else {
			$options['woo']['affiliateEventName'] = PYS()->getOption( 'woo_affiliate_event_type' );
		}
		
		$woo_paypal_custom_event_type = PYS()->getOption( 'woo_paypal_custom_event_type' );

		if ( PYS()->getOption( 'woo_paypal_event_type' ) == 'custom' && ! empty( $woo_paypal_custom_event_type ) ) {
			$options['woo']['paypalEventName'] = sanitizeKey( PYS()->getOption( 'woo_paypal_custom_event_type' ) );
		} else {
			$options['woo']['paypalEventName'] = PYS()->getOption( 'woo_paypal_event_type' );
		}

		$data = array_merge( $data, $options );

		wp_localize_script( 'pys', 'pysOptions', $data );

	}
	
	public function outputNoScriptData() {
  
		foreach ( PYS()->getRegisteredPixels() as $pixel ) {
			/** @var Pixel|Settings $pixel */
			$pixel->outputNoScriptEvents();
		}
	   
    }

	public function setupEventsParams() {

		// initial event
		$this->addStaticEvent( 'init_event' );

		if ( isEventEnabled( 'general_event_enabled' ) ) {
			$this->addStaticEvent( 'general_event' );
		}

		if ( isEventEnabled( 'search_event_enabled' ) && is_search() ) {
			$this->addStaticEvent( 'search_event' );
		}

		if ( PYS()->getOption( 'custom_events_enabled' ) ) {

			$this->setupCustomEvents();

		    add_filter( 'the_content', 'PixelYourSite\filterContentUrls', 1000 );
		    add_filter( 'widget_text', 'PixelYourSite\filterContentUrls', 1000 );

		}

	    if ( isWooCommerceActive() && PYS()->getOption( 'woo_enabled' ) ) {
			$this->setupWooCommerceEvents();
	    }

		if ( isEddActive() && PYS()->getOption( 'edd_enabled' ) ) {
			$this->setupEddEvents();
		}

		if ( isEventEnabled( 'complete_registration_event_enabled' ) && $user_id = get_current_user_id() ) {

			if ( get_user_meta( $user_id, 'pys_complete_registration', true ) ) {

				$this->addStaticEvent( 'complete_registration' );
				delete_user_meta( $user_id, 'pys_complete_registration' );

			}

		}
  
	}

	public function getWooCustomerTotals() {

		// setup and cache params
		if ( empty( $this->wooCustomerTotals ) ) {
			$this->wooCustomerTotals = getWooCustomerTotals();
		}

		return $this->wooCustomerTotals;

	}

	public function getEddCustomerTotals() {

		// setup and cache params
		if ( empty( $this->eddCustomerTotals ) ) {
			$this->eddCustomerTotals = getEddCustomerTotals();
		}

		return $this->eddCustomerTotals;

	}
	
	public function getStaticEvents( $context ) {
	    return isset( $this->staticEvents[ $context ] ) ? $this->staticEvents[ $context ] : array();
    }

	/**
	 * Add static event for each pixel
	 *
	 * @param string           $eventType Event name for internal usage
	 * @param CustomEvent|null $customEvent
	 */
	private function addStaticEvent( $eventType, $customEvent = null ) {

		foreach ( PYS()->getRegisteredPixels() as $pixel ) {
			/** @var Pixel|Settings $pixel */

			$eventData = $pixel->getEventData( $eventType, $customEvent );

			if ( false === $eventData ) {
				continue; // event is disabled or not supported for the pixel
			}

			$eventName = $eventData['name'];
			$ids = isset( $eventData['ids'] ) ? $eventData['ids'] : array();
			
			$this->staticEvents[ $pixel->getSlug() ][ $eventName ][] = array(
				'params' => sanitizeParams( $eventData['data'] ),
				'delay'  => isset( $eventData['delay'] ) ? $eventData['delay'] : 0,
				'ids'    => $ids,
			);

		}

	}

	/**
	 * Add dynamic event for each pixel
	 *
	 * @param CustomEvent $customEvent
	 * @param array       $triggers
	 */
	private function addDynamicEvent( $customEvent, $triggers ) {

		// collect adapted params
		foreach ( PYS()->getRegisteredPixels() as $pixel ) {
			/** @var Pixel|Settings $pixel */

			$eventData = $pixel->getEventData( 'custom_event', $customEvent );

			// event is disabled or not supported for the pixel
			if ( false === $eventData ) {
				continue;
			}

			// push event params
			if ( $pixel->getSlug() == 'ga' ) {

				$this->dynamicEventsParams[ $customEvent->getPostId() ]['ga'] = array(
					'action' => $customEvent->getGoogleAnalyticsAction(),
					'params' => sanitizeParams( $eventData['data'] ),
				);

			} elseif ( $pixel->getSlug() == 'google_ads' ) {
				
				$this->dynamicEventsParams[ $customEvent->getPostId() ]['google_ads'] = array(
					'action' => $customEvent->getGoogleAdsAction(),
					'params' => sanitizeParams( $eventData['data'] ),
                    'ids' => $eventData['ids']
				);
				
			} elseif ( $pixel->getSlug() == 'facebook' ) {

				$this->dynamicEventsParams[ $customEvent->getPostId() ]['facebook'] = array(
					'name'   => $customEvent->getFacebookEventType(),
					'params' => sanitizeParams( $eventData['data'] ),
				);

			} else {
				
				$this->dynamicEventsParams[ $customEvent->getPostId() ]['pinterest'] = array(
					'name'   => $customEvent->getPinterestEventType(),
					'params' => sanitizeParams( $eventData['data'] ),
				);
			    
            }

			// push event triggers
			foreach ( $triggers as $trigger ) {
				$this->dynamicEventsTriggers[ $customEvent->getTriggerType() ][ $customEvent->getPostId() ][] = $trigger['value'];
			}

		}

	}

	private function setupCustomEvents() {

		foreach ( CustomEventFactory::get( 'active' ) as $event ) {
			/** @var CustomEvent $event */

			switch ( $event->getTriggerType() ) {
				case 'page_visit':
					$triggers = $event->getPageVisitTriggers();
					break;

				case 'url_click':
					$triggers = $event->getURLClickTriggers();
					break;

				case 'css_click':
					$triggers = $event->getCSSClickTriggers();
					break;

				case 'css_mouseover':
					$triggers = $event->getCSSMouseOverTriggers();
					break;

				case 'scroll_pos':
					$triggers = $event->getScrollPosTriggers();
					break;

				default:
					$triggers = array();
			}

			// no triggers were defined
			if ( empty( $triggers ) ) {
				continue;
			}

			if ( 'page_visit' == $event->getTriggerType() ) {
				
				$triggers = apply_filters( 'pys_page_url_triggers', $triggers );
				
				// match triggers with current page URL
				if ( ! compareURLs( $triggers ) ) {
					continue;
				}

			} else {

				$urlFilters = $event->getURLFilters();

				// match URL filters with current page URL
				if ( ! empty( $urlFilters ) && ! compareURLs( $urlFilters ) ) {
					continue;
				}

			}

			if ( 'page_visit' == $event->getTriggerType() ) {
				$this->addStaticEvent( 'custom_event', $event );
			} else {
				$this->addDynamicEvent( $event, $triggers );
			}

		}

	}

	private function setupWooCommerceEvents() {

		// Advanced Marketing events
		if ( is_order_received_page() ) {

			$customerTotals = $this->getWooCustomerTotals();

			// FrequentShopper
			if ( isEventEnabled( 'woo_frequent_shopper_enabled' ) ) {

				$orders_count = (int) PYS()->getOption( 'woo_frequent_shopper_transactions' );

				if ( $customerTotals['orders_count'] >= $orders_count ) {
					$this->addStaticEvent( 'woo_frequent_shopper' );
				}

			}

			// VIPClient
			if ( isEventEnabled( 'woo_vip_client_enabled' ) ) {

				$orders_count = (int) PYS()->getOption( 'woo_vip_client_transactions' );
				$avg = (int) PYS()->getOption( 'woo_vip_client_average_value' );

				if ( $customerTotals['orders_count'] >= $orders_count && $customerTotals['avg_order_value'] >= $avg ) {
					$this->addStaticEvent( 'woo_vip_client' );
				}

			}

			// BigWhale
			if ( isEventEnabled( 'woo_big_whale_enabled' ) ) {

				$ltv = (int) PYS()->getOption( 'woo_big_whale_ltv' );

				if ( $customerTotals['ltv'] >= $ltv ) {
					$this->addStaticEvent( 'woo_big_whale' );
				}

			}

		}

		// AddToCart on button and Affiliate
		if ( isEventEnabled( 'woo_add_to_cart_enabled') && PYS()->getOption( 'woo_add_to_cart_on_button_click' )
		     || isEventEnabled( 'woo_affiliate_enabled') ) {
			add_action( 'woocommerce_after_shop_loop_item', array( $this, 'setupWooLoopProductData' ) );
			add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'setupWooSingleProductData' ) );
		}

		// ViewContent
		if ( isEventEnabled( 'woo_view_content_enabled' ) && is_product() ) {

			$this->addStaticEvent( 'woo_view_content' );
			return;

		}

		// ViewCategory
		//@todo: +7.1.0+ maybe fire on Shop page as well? review GA 'list' param then
		if ( isEventEnabled( 'woo_view_category_enabled' ) && is_tax( 'product_cat' ) ) {

			$this->addStaticEvent( 'woo_view_category' );
			return;

		}

		// AddToCart on Cart page
		if ( isEventEnabled( 'woo_add_to_cart_enabled' ) && PYS()->getOption( 'woo_add_to_cart_on_cart_page' )
		     && is_cart() ) {

			$this->addStaticEvent( 'woo_add_to_cart_on_cart_page' );

		}

		// AddToCart on Checkout page
		if ( isEventEnabled( 'woo_add_to_cart_enabled' ) && PYS()->getOption( 'woo_add_to_cart_on_checkout_page' )
		     && is_checkout() && ! is_wc_endpoint_url() ) {

			$this->addStaticEvent( 'woo_add_to_cart_on_checkout_page' );

		}

		// RemoveFromCart
		if ( isEventEnabled( 'woo_remove_from_cart_enabled') && is_cart() ) {
			add_action( 'woocommerce_after_cart', array( $this, 'setupWooRemoveFromCartData' ) );
		}

		// InitiateCheckout Event
		if ( isEventEnabled( 'woo_initiate_checkout_enabled' ) && is_checkout() && ! is_wc_endpoint_url() ) {
			$this->addStaticEvent( 'woo_initiate_checkout' );
		}

		// PayPal
		if ( isEventEnabled( 'woo_paypal_enabled' ) && is_checkout() && ! is_wc_endpoint_url() ) {
		    setupWooPayPalData();
		}

		// Purchase Event
		if ( isEventEnabled( 'woo_purchase_enabled' ) && is_order_received_page() && isset( $_REQUEST['key'] ) ) {

			$order_id = (int) wc_get_order_id_by_order_key( $_REQUEST['key'] );

			// skip if event was fired before
			if ( PYS()->getOption( 'woo_purchase_on_transaction' ) && get_post_meta( $order_id, '_pys_purchase_event_fired', true ) ) {
				return;
			}

			update_post_meta( $order_id, '_pys_purchase_event_fired', true );

			$this->addStaticEvent( 'woo_purchase' );

		}

	}

	public function setupWooLoopProductData() {
		global $product;

		if ( wooProductIsType( $product, 'variable' ) ) {
			return; // skip variable products
		} elseif ( wooProductIsType( $product, 'external' ) ) {
			$eventType = 'woo_affiliate_enabled';
		} else {
			$eventType = 'woo_add_to_cart_on_button_click';
		}

		/** @var \WC_Product $product */
		if ( isWooCommerceVersionGte( '2.6' ) ) {
			$product_id = $product->get_id();
		} else {
			$product_id = $product->post->ID;
		}

		$params = array();
  
		foreach ( PYS()->getRegisteredPixels() as $pixel ) {
			/** @var Pixel|Settings $pixel */

			$eventData = $pixel->getEventData( $eventType, $product_id );

			if ( false === $eventData ) {
				continue; // event is disabled or not supported for the pixel
			}

			$params[ $pixel->getSlug() ] = sanitizeParams( $eventData['data'] );

		}

		if ( empty( $params ) ) {
			return;
		}

		$params = json_encode( $params );

		?>

		<script type="text/javascript">
            /* <![CDATA[ */
            window.pysWooProductData = window.pysWooProductData || [];
            window.pysWooProductData[ <?php echo $product_id; ?> ] = <?php echo $params; ?>;
            /* ]]> */
		</script>

		<?php

	}

	public function setupWooSingleProductData() {
		global $product;

		if ( wooProductIsType( $product, 'external' ) ) {
			$eventType = 'woo_affiliate_enabled';
		} else {
			$eventType = 'woo_add_to_cart_on_button_click';
		}

		/** @var \WC_Product $product */
		if ( isWooCommerceVersionGte( '2.6' ) ) {
			$product_id = $product->get_id();
		} else {
			$product_id = $product->post->ID;
		}

		// main product id
		$product_ids[] = $product_id;

		// variations ids
		if ( wooProductIsType( $product, 'variable' ) ) {

			/** @var \WC_Product_Variable $variation */
			foreach ( $product->get_available_variations() as $variation ) {

				$variation = wc_get_product( $variation['variation_id'] );

				if ( isWooCommerceVersionGte( '2.6' ) ) {
					$product_ids[] = $variation->get_id();
				} else {
					$product_ids[] = $variation->post->ID;
				}

			}

		}

		$params = array();

		foreach ( $product_ids as $product_id ) {
			foreach ( PYS()->getRegisteredPixels() as $pixel ) {
				/** @var Pixel|Settings $pixel */

				$eventData = $pixel->getEventData( $eventType, $product_id );

				if ( false === $eventData ) {
					continue; // event is disabled or not supported for the pixel
				}

				$params[ $product_id ][ $pixel->getSlug() ] = sanitizeParams( $eventData['data'] );

			}
		}

		if ( empty( $params ) ) {
			return;
		}

		?>

		<script type="text/javascript">
            /* <![CDATA[ */
            window.pysWooProductData = window.pysWooProductData || [];
			<?php foreach ( $params as $product_id => $product_data ) : ?>
            window.pysWooProductData[<?php echo $product_id; ?>] = <?php echo json_encode( $product_data ); ?>;
			<?php endforeach; ?>
            /* ]]> */
		</script>

		<?php

	}

	public function setupWooRemoveFromCartData() {

		$data = array();

		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {

			$item_data = array();
			
			foreach ( PYS()->getRegisteredPixels() as $pixel ) {
				/** @var Pixel|Settings $pixel */

				$eventData = $pixel->getEventData( 'woo_remove_from_cart', $cart_item );

				if ( false === $eventData ) {
					continue; // event is disabled or not supported for the pixel
				}

				$item_data[ $pixel->getSlug() ] = sanitizeParams( $eventData['data'] );

			}

			if ( ! empty( $item_data ) ) {
				$data[ $cart_item_key ] = $item_data;
			}

		}

		?>

		<script type="text/javascript">
            /* <![CDATA[ */
            window.pysWooRemoveFromCartData = window.pysWooRemoveFromCartData || [];
            window.pysWooRemoveFromCartData = <?php echo json_encode( $data ); ?>;
            /* ]]> */
		</script>

		<?php

	}

	private function setupEddEvents() {

		// Advanced Marketing events
		if ( edd_is_success_page() ) {

			$customerTotals = $this->getEddCustomerTotals();

			// FrequentShopper
			if ( isEventEnabled( 'edd_frequent_shopper_enabled' ) ) {

				$orders_count = (int) PYS()->getOption( 'edd_frequent_shopper_transactions' );

				if ( $customerTotals['orders_count'] >= $orders_count ) {
					$this->addStaticEvent( 'edd_frequent_shopper' );
				}

			}

			// VIPClient
			if ( isEventEnabled( 'edd_vip_client_enabled' ) ) {

				$orders_count = (int) PYS()->getOption( 'edd_vip_client_transactions' );
				$avg = (int) PYS()->getOption( 'edd_vip_client_average_value' );

				if ( $customerTotals['orders_count'] >= $orders_count && $customerTotals['avg_order_value'] >= $avg ) {
					$this->addStaticEvent( 'edd_vip_client' );
				}

			}

			// BigWhale
			if ( isEventEnabled( 'edd_big_whale_enabled' ) ) {

				$ltv = (int) PYS()->getOption( 'edd_big_whale_ltv' );

				if ( $customerTotals['ltv'] >= $ltv ) {
					$this->addStaticEvent( 'edd_big_whale' );
				}

			}

		}

		// AddToCart on button
		if ( isEventEnabled( 'edd_add_to_cart_enabled') && PYS()->getOption( 'edd_add_to_cart_on_button_click' ) ) {
			add_action( 'edd_purchase_link_end', array( $this, 'setupEddSingleDownloadData' ) );
		}

		// ViewContent
		if ( isEventEnabled( 'edd_view_content_enabled' ) && is_singular( 'download' ) ) {

			$this->addStaticEvent( 'edd_view_content' );
			return;

		}

		// ViewCategory
		//@todo: +7.1.0+  maybe fire on Shop page as well? review GA 'list' param then
		if ( isEventEnabled( 'edd_view_category_enabled' ) && is_tax( 'download_category' ) ) {

			$this->addStaticEvent( 'edd_view_category' );
			return;

		}

		// AddToCart on Checkout page
		if ( isEventEnabled( 'edd_add_to_cart_enabled' ) && PYS()->getOption( 'edd_add_to_cart_on_checkout_page' )
		     && edd_is_checkout() ) {

			$this->addStaticEvent( 'edd_add_to_cart_on_checkout_page' );

		}

		// RemoveFromCart
		if ( isEventEnabled( 'edd_remove_from_cart_enabled') && edd_is_checkout() ) {
			add_action( 'edd_cart_items_after', array( $this, 'setupEddRemoveFromCartData' ) );
		}

		// InitiateCheckout Event
		if ( isEventEnabled( 'edd_initiate_checkout_enabled' ) && edd_is_checkout() ) {

			$this->addStaticEvent( 'edd_initiate_checkout' );
			return;

		}

		// Purchase Event
		if ( isEventEnabled( 'edd_purchase_enabled' ) && edd_is_success_page() ) {
   
			/**
			 * When a payment gateway used, user lands to Payment Confirmation page first, which does automatic
			 * redirect to Purchase Confirmation page. We filter Payment Confirmation to avoid double Purchase event.
			 */
			if ( isset( $_GET['payment-confirmation'] ) ) {
				//@fixme: some users will not reach success page and event will not be fired
				//return;
			}

			$payment_key = getEddPaymentKey();
			$order_id = (int) edd_get_purchase_id_by_key( $payment_key );
			$status = edd_get_payment_status( $order_id, true );

			// pending payment status used because we can't fire event on IPN
			if ( strtolower( $status ) != 'complete' && strtolower( $status ) != 'pending' ) {
				return;
			}

			// skip if event was fired before
			if ( PYS()->getOption( 'edd_purchase_on_transaction' ) && get_post_meta( $order_id, '_pys_purchase_event_fired', true ) ) {
				return;
			}

			update_post_meta( $order_id, '_pys_purchase_event_fired', true );

			$this->addStaticEvent( 'edd_purchase' );
			return;

		}

	}

	public function setupEddSingleDownloadData() {
		global $post;

		$download_ids = array();

        if ( edd_has_variable_prices( $post->ID ) ) {

            $prices = edd_get_variable_prices( $post->ID );

	        foreach ( $prices as $price_index => $price_data ) {
		        $download_ids[] = $post->ID . '_' . $price_index;
            }

        } else {

	        $download_ids[] = $post->ID;

        }

		$params = array();

		foreach ( $download_ids as $download_id ) {
			foreach ( PYS()->getRegisteredPixels() as $pixel ) {
				/** @var Pixel|Settings $pixel */

				$eventData = $pixel->getEventData( 'edd_add_to_cart_on_button_click', $download_id );

				if ( false === $eventData ) {
					continue; // event is disabled or not supported for the pixel
				}

				$params[ $download_id ][ $pixel->getSlug() ] = sanitizeParams( $eventData['data'] );

			}
		}

		if ( empty( $params ) ) {
			return;
		}

		/**
		 * Format is pysEddProductData[ id ][ id ] or pysEddProductData[ id ] [ id_1, id_2, ... ]
		 */

		?>

        <script type="text/javascript">
            /* <![CDATA[ */
            window.pysEddProductData = window.pysEddProductData || [];
            window.pysEddProductData[<?php echo $post->ID; ?>] = <?php echo json_encode( $params ); ?>;
            /* ]]> */
        </script>

		<?php

    }

    public function setupEddRemoveFromCartData() {

	    $data = array();

	    foreach ( edd_get_cart_contents() as $cart_item_key => $cart_item ) {

		    $item_data = array();
		
		    foreach ( PYS()->getRegisteredPixels() as $pixel ) {
			    /** @var Pixel|Settings $pixel */

			    $eventData = $pixel->getEventData( 'edd_remove_from_cart', $cart_item );

			    if ( false === $eventData ) {
				    continue; // event is disabled or not supported for the pixel
			    }

			    $item_data[ $pixel->getSlug() ] = sanitizeParams( $eventData['data'] );

		    }

		    if ( ! empty( $item_data ) ) {
			    $data[ $cart_item_key ] = $item_data;
		    }

	    }

	    ?>

        <script type="text/javascript">
            /* <![CDATA[ */
            window.pysEddRemoveFromCartData = window.pysEddRemoveFromCartData || [];
            window.pysEddRemoveFromCartData = <?php echo json_encode( $data ); ?>;
            /* ]]> */
        </script>

	    <?php

    }

}