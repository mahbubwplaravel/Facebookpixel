<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/** @noinspection PhpIncludeInspection */
require_once PYS_PATH . '/modules/facebook/function-helpers.php';

use PixelYourSite\Facebook\Helpers;

class Facebook extends Settings implements Pixel {
	
	private static $_instance;
	
	private $configured;
	
	public static function instance() {
		
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		
		return self::$_instance;
		
	}

    public function __construct() {
		
        parent::__construct( 'facebook' );
	
        $this->locateOptions(
            PYS_PATH . '/modules/facebook/options_fields.json',
            PYS_PATH . '/modules/facebook/options_defaults.json'
        );
	
	    add_action( 'pys_register_pixels', function( $core ) {
	    	/** @var PYS $core */
	    	$core->registerPixel( $this );
	    } );
	    
    }
    
    public function enabled() {
	    return $this->getOption( 'enabled' );
    }
	
	public function configured() {
		
		if ( $this->configured === null ) {
			
			$license_status = PYS()->getOption( 'license_status' );
			$pixel_id = $this->getOption( 'pixel_id' );
			
			$this->configured = $this->enabled()
			                    && ! empty( $license_status ) // license was activated before
			                    && ! empty( $pixel_id )
			                    && ! apply_filters( 'pys_pixel_disabled', false, $this->getSlug() );
			
		}
		
		return $this->configured;
		
	}
	
	public function getPixelIDs() {
		
		$ids = (array) $this->getOption( 'pixel_id' );
		
		if ( isSuperPackActive() && SuperPack()->getOption( 'enabled' ) && SuperPack()->getOption( 'additional_ids_enabled' ) ) {
			return $ids;
		} else {
			return (array) reset( $ids ); // return first id only
		}
		
	}
	
	public function getPixelOptions() {
		
		return array(
			'pixelIds'            => $this->getPixelIDs(),
			'advancedMatching'    => $this->getOption( 'advanced_matching_enabled' ) ? Helpers\getAdvancedMatchingParams() : array(),
			'removeMetadata'      => $this->getOption( 'remove_metadata' ),
			'contentParams'       => getTheContentParams(),
			'clickEventEnabled'   => $this->getOption( 'click_event_enabled' ),
			'watchVideoEnabled'   => $this->getOption( 'watchvideo_event_enabled' ),
			'adSenseEventEnabled' => $this->getOption( 'adsense_enabled' ),
			'commentEventEnabled' => $this->getOption( 'comment_event_enabled' ),
			'formEventEnabled'    => $this->getOption( 'form_event_enabled' ),
			'downloadEnabled'     => $this->getOption( 'download_event_enabled' ),
			'wooVariableAsSimple' => $this->getOption( 'woo_variable_as_simple' ),
		);
		
	}
	
	public function getEventData( $eventType, $args = null ) {
		
		if ( ! $this->configured() ) {
			return false;
		}
		
		switch ( $eventType ) {
			case 'init_event':
				return $this->getPageViewEventParams();
			
			case 'general_event':
				return $this->getGeneralEventParams();
			
			case 'search_event':
				return $this->getSearchEventParams();
			
			case 'custom_event':
				return $this->getCustomEventParams( $args );
			
			case 'woo_view_content':
				return $this->getWooViewContentEventParams();
			
			case 'woo_add_to_cart_on_button_click':
				return $this->getWooAddToCartOnButtonClickEventParams( $args );
			
			case 'woo_add_to_cart_on_cart_page':
			case 'woo_add_to_cart_on_checkout_page':
				return $this->getWooAddToCartOnCartEventParams();
			
			case 'woo_remove_from_cart':
				return $this->getWooRemoveFromCartParams( $args );
			
			case 'woo_view_category':
				return $this->getWooViewCategoryEventParams();
			
			case 'woo_initiate_checkout':
				return $this->getWooInitiateCheckoutEventParams();
			
			case 'woo_purchase':
				return $this->getWooPurchaseEventParams();
			
			case 'woo_affiliate_enabled':
				return $this->getWooAffiliateEventParams( $args );
			
			case 'woo_paypal':
				return $this->getWooPayPalEventParams();
			
			case 'woo_frequent_shopper':
			case 'woo_vip_client':
			case 'woo_big_whale':
				return $this->getWooAdvancedMarketingEventParams( $eventType );
			
			case 'edd_view_content':
				return $this->getEddViewContentEventParams();
			
			case 'edd_add_to_cart_on_button_click':
				return $this->getEddAddToCartOnButtonClickEventParams( $args );
			
			case 'edd_add_to_cart_on_checkout_page':
				return $this->getEddCartEventParams( 'AddToCart' );
			
			case 'edd_remove_from_cart':
				return $this->getEddRemoveFromCartParams( $args );
			
			case 'edd_view_category':
				return $this->getEddViewCategoryEventParams();
			
			case 'edd_initiate_checkout':
				return $this->getEddCartEventParams( 'InitiateCheckout' );
			
			case 'edd_purchase':
				return $this->getEddCartEventParams( 'Purchase' );
			
			case 'edd_frequent_shopper':
			case 'edd_vip_client':
			case 'edd_big_whale':
				return $this->getEddAdvancedMarketingEventParams( $eventType );
			
			case 'complete_registration':
				return $this->getCompleteRegistrationEventParams();
			
			default:
				return false;   // event does not supported
		}
		
	}
	
	public function outputNoScriptEvents() {
		
		if ( ! $this->configured() ) {
			return;
		}
		
		$eventsManager = PYS()->getEventsManager();
		
		foreach ( $eventsManager->getStaticEvents( 'facebook' ) as $eventName => $events ) {
			foreach ( $events as $event ) {
				foreach ( $this->getPixelIDs() as $pixelID ) {
					
					$args = array(
						'id'       => $pixelID,
						'ev'       => urlencode( $eventName ),
						'noscript' => 1,
					);
					
					foreach ( $event['params'] as $param => $value ) {
						@$args[ 'cd[' . $param . ']' ] = urlencode( $value );
					}
					
					// ALT tag used to pass ADA compliance
					printf( '<noscript><img height="1" width="1" style="display: none;" src="%s" alt="facebook_pixel"></noscript>',
						add_query_arg( $args, 'https://www.facebook.com/tr' ) );
					
					echo "\r\n";
					
				}
			}
		}
		
	}
	
	private function getPageViewEventParams() {

		return array(
			'name'  => 'PageView',
			'data'  => array(),
		);

	}

	private function getGeneralEventParams() {

		if ( ! $this->getOption( 'general_event_enabled' ) ) {
			return false;
		}

		$eventName = PYS()->getOption( 'general_event_name' );
		$eventName = sanitizeKey( $eventName );

		if ( empty( $eventName ) ) {
			$eventName = 'GeneralEvent';
		}

		$allowedContentTypes = array(
			'on_posts_enabled'      => PYS()->getOption( 'general_event_on_posts_enabled' ),
			'on_pages_enables'      => PYS()->getOption( 'general_event_on_pages_enabled' ),
			'on_taxonomies_enabled' => PYS()->getOption( 'general_event_on_tax_enabled' ),
			'on_cpt_enabled'        => PYS()->getOption( 'general_event_on_' . get_post_type() . '_enabled', false ),
			'on_woo_enabled'        => PYS()->getOption( 'general_event_on_woo_enabled' ),
			'on_edd_enabled'        => PYS()->getOption( 'general_event_on_edd_enabled' ),
		);

		$params = getTheContentParams( $allowedContentTypes );

		return array(
			'name'  => $eventName,
			'data'  => $params,
			'delay' => (int) PYS()->getOption( 'general_event_delay' ),
		);

	}

	private function getSearchEventParams() {
		global $posts;

		if ( ! $this->getOption( 'search_event_enabled' ) ) {
			return false;
		}

		$params['search_string'] = empty( $_GET['s'] ) ? null : $_GET['s'];

		if ( isWooCommerceActive() && isset( $_GET['post_type'] ) && $_GET['post_type'] == 'product' ) {

			$limit = min( count( $posts ), 5 );
			$post_ids = array();

			for ( $i = 0; $i < $limit; $i ++ ) {
				$post_ids = array_merge( Helpers\getFacebookWooProductContentId( $posts[ $i ]->ID ), $post_ids );
			}

			$params['content_type'] = 'product';
			$params['content_ids']  = json_encode( $post_ids );

		}

		return array(
			'name'  => 'Search',
			'data'  => $params,
		);

	}

	private function getWooViewContentEventParams() {
		global $post;

		if ( ! $this->getOption( 'woo_view_content_enabled' ) ) {
			return false;
		}

		$params = array();

		$product = wc_get_product( $post->ID );

		$content_id = Helpers\getFacebookWooProductContentId( $post->ID );
		$params['content_ids']  = json_encode( $content_id );

		if ( wooProductIsType( $product, 'variable' ) && ! $this->getOption( 'woo_variable_as_simple' ) ) {
			$params['content_type'] = 'product_group';
		} else {
			$params['content_type'] = 'product';
		}

		// Facebook for WooCommerce plugin integration
		if ( ! Helpers\isDefaultWooContentIdLogic() && wooProductIsType( $product, 'variable' ) ) {
			$params['content_type'] = 'product_group';
		}

		// content_name, category_name, tags
		$params['tags'] = implode( ', ', getObjectTerms( 'product_tag', $post->ID ) );
		$params = array_merge( $params, Helpers\getWooCustomAudiencesOptimizationParams( $post->ID ) );

		// currency, value
		if ( PYS()->getOption( 'woo_view_content_value_enabled' ) ) {

			if( PYS()->getOption( 'woo_event_value' ) == 'custom' ) {
				$amount = getWooProductPrice( $post->ID );
			} else {
				$amount = getWooProductPriceToDisplay( $post->ID );
			}

			$value_option   = PYS()->getOption( 'woo_view_content_value_option' );
			$global_value   = PYS()->getOption( 'woo_view_content_value_global', 0 );
			$percents_value = PYS()->getOption( 'woo_view_content_value_percent', 100 );

			$params['value']    = getWooEventValue( $value_option, $amount, $global_value, $percents_value );
			$params['currency'] = get_woocommerce_currency();

		}

		// contents
		if ( Helpers\isDefaultWooContentIdLogic() ) {

			// Facebook for WooCommerce plugin does not support new Dynamic Ads parameters
			$params['contents'] = json_encode( array(
				array(
					'id'         => (string) reset( $content_id ),
					'quantity'   => 1,
					'item_price' => getWooProductPriceToDisplay( $post->ID ),
				)
			) );

		}
		
		$params['product_price'] = getWooProductPriceToDisplay( $post->ID );

		return array(
			'name'  => 'ViewContent',
			'data'  => $params,
			'delay' => (int) PYS()->getOption( 'woo_view_content_delay' ),
		);

	}

	private function getWooAddToCartOnButtonClickEventParams( $product_id ) {

		if ( ! $this->getOption( 'woo_add_to_cart_enabled' ) || ! PYS()->getOption( 'woo_add_to_cart_on_button_click' ) ) {
			return false;
		}

		$params = Helpers\getWooSingleAddToCartParams( $product_id, 1, false );

		return array(
			'data' => $params,
		);

	}

	private function getWooAddToCartOnCartEventParams() {

		if ( ! $this->getOption( 'woo_add_to_cart_enabled' ) ) {
			return false;
		}

		$params = Helpers\getWooCartParams();

		return array(
			'name' => 'AddToCart',
			'data' => $params,
		);

	}

	private function getWooRemoveFromCartParams( $cart_item ) {

		if ( ! $this->getOption( 'woo_remove_from_cart_enabled' ) ) {
			return false;
		}
		
		$product_id = Helpers\getFacebookWooCartItemId( $cart_item );
		$content_id = Helpers\getFacebookWooProductContentId( $product_id );

		$params['content_type'] = 'product';
		$params['content_ids']  = json_encode( $content_id );

		// content_name, category_name, tags
		$params['tags'] = implode( ', ', getObjectTerms( 'product_tag', $product_id ) );
		$params = array_merge( $params, Helpers\getWooCustomAudiencesOptimizationParams( $product_id ) );

		$params['num_items'] = $cart_item['quantity'];
		$params['product_price'] = getWooProductPriceToDisplay( $product_id );

		$params['contents'] = json_encode( array(
			array(
				'id'         => (string) reset( $content_id ),
				'quantity'   => $cart_item['quantity'],
				'item_price' => getWooProductPriceToDisplay( $product_id ),
			)
		) );

		return array( 'data' => $params );

	}

	private function getWooViewCategoryEventParams() {
		global $posts;

		if ( ! $this->getOption( 'woo_view_category_enabled' ) ) {
			return false;
		}
		
		if ( Helpers\isDefaultWooContentIdLogic() ) {
			$params['content_type'] = 'product';
		} else {
			$params['content_type'] = 'product_group';
		}
        
        $params['content_category'] = array();
		$term = get_term_by( 'slug', get_query_var( 'term' ), 'product_cat' );
		
		if ( $term ) {
		    
            $params['content_name'] = $term->name;
            
            $parent_ids = get_ancestors( $term->term_id, 'product_cat', 'taxonomy' );
            
            foreach ( $parent_ids as $term_id ) {
                $term = get_term_by( 'id', $term_id, 'product_cat' );
                $params['content_category'][] = $term->name;
            }
            
        }
        
		$params['content_category'] = implode( ', ', $params['content_category'] );

		$content_ids = array();
		$limit       = min( count( $posts ), 5 );

		for ( $i = 0; $i < $limit; $i ++ ) {
			$content_ids = array_merge( Helpers\getFacebookWooProductContentId( $posts[ $i ]->ID ), $content_ids );
		}

		$params['content_ids']  = json_encode( $content_ids );

		return array(
			'name' => 'ViewCategory',
			'data' => $params,
		);

	}

	private function getWooInitiateCheckoutEventParams() {

		if ( ! $this->getOption( 'woo_initiate_checkout_enabled' ) ) {
			return false;
		}

		$params = Helpers\getWooCartParams( 'InitiateCheckout' );

		return array(
			'name' => 'InitiateCheckout',
			'data' => $params,
		);

	}

	private function getWooPurchaseEventParams() {

		if ( ! $this->getOption( 'woo_purchase_enabled' ) ) {
			return false;
		}

		$params = Helpers\getWooPurchaseParams( 'woo_purchase' );

		return array(
			'name' => 'Purchase',
			'data' => $params,
		);

	}

	private function getWooAffiliateEventParams( $product_id ) {

		if ( ! $this->getOption( 'woo_affiliate_enabled' ) ) {
			return false;
		}

		$params = Helpers\getWooSingleAddToCartParams( $product_id, 1, true );

		return array(
			'data' => $params,
		);

	}

	private function getWooPayPalEventParams() {

		if ( ! $this->getOption( 'woo_paypal_enabled' ) ) {
			return false;
		}

		// we're using Cart date as of Order not exists yet
		$params = Helpers\getWooCartParams( 'PayPal' );

		return array(
			'name' => '', // will be set on front-end
			'data' => $params,
		);

	}

	private function getWooAdvancedMarketingEventParams( $eventType ) {

		if ( ! $this->getOption( $eventType . '_enabled' ) ) {
			return false;
		}

		$params = Helpers\getWooPurchaseParams( $eventType );

		$params['product_names'] = $params['content_name'];
		$params['product_ids'] = $params['content_ids'];

		unset( $params['content_name'] );
		unset( $params['content_ids'] );
		unset( $params['content_type'] );
		unset( $params['contents'] );

		switch ( $eventType ) {
			case 'woo_frequent_shopper':
				$eventName = 'FrequentShopper';
				break;

			case 'woo_vip_client':
				$eventName = 'VipClient';
				break;

			default:
				$eventName = 'BigWhale';
		}

		return array(
			'name' => $eventName,
			'data' => $params,
		);

	}

	/**
	 * @param CustomEvent $customEvent
	 *
	 * @return array|bool
	 */
	private function getCustomEventParams( $customEvent ) {
		
		$event_type = $customEvent->getFacebookEventType();

		if ( ! $customEvent->isFacebookEnabled() || empty( $event_type ) ) {
			return false;
		}

		$params = array();

		// add pixel params
		if ( $customEvent->isFacebookParamsEnabled() ) {

			$params = $customEvent->getFacebookParams();

			// use custom currency if any
			if ( ! empty( $params['custom_currency'] ) ) {
				$params['currency'] = $params['custom_currency'];
				unset( $params['custom_currency'] );
			}

			// add custom params
			foreach ( $customEvent->getFacebookCustomParams() as $custom_param ) {
				$params[ $custom_param['name'] ] = $custom_param['value'];
			}

		}
		
		// SuperPack Dynamic Params feature
		$params = apply_filters( 'pys_superpack_dynamic_params', $params, 'facebook' );

		return array(
			'name'  => $customEvent->getFacebookEventType(),
			'data'  => $params,
			'delay' => $customEvent->getDelay(),
		);

	}

	private function getCompleteRegistrationEventParams() {

		if ( ! $this->getOption( 'complete_registration_event_enabled' ) ) {
			return false;
		}

		return array(
			'name'  => 'CompleteRegistration',
			'data'  => array(),
		);

	}

	private function getEddViewContentEventParams() {
		global $post;

		if ( ! $this->getOption( 'edd_view_content_enabled' ) ) {
			return false;
		}

		$params = array(
			'content_type' => 'product',
			'content_ids'  => json_encode( Helpers\getFacebookEddDownloadContentId( $post->ID ) ),
		);

		// content_name, category_name
		$params['tags'] = implode( ', ', getObjectTerms( 'download_tag', $post->ID ) );
		$params = array_merge( $params, Helpers\getEddCustomAudiencesOptimizationParams( $post->ID ) );

		// currency, value
		if ( PYS()->getOption( 'edd_view_content_value_enabled' ) ) {

			if( PYS()->getOption( 'edd_event_value' ) == 'custom' ) {
				$amount = getEddDownloadPrice( $post->ID );
			} else {
				$amount = getEddDownloadPriceToDisplay( $post->ID );
			}

			$value_option   = PYS()->getOption( 'edd_view_content_value_option' );
			$global_value   = PYS()->getOption( 'edd_view_content_value_global', 0 );
			$percents_value = PYS()->getOption( 'edd_view_content_value_percent', 100 );

			$params['value'] = getEddEventValue( $value_option, $amount, $global_value, $percents_value );
			$params['currency'] = edd_get_currency();

		}

		// contents
		$params['contents'] = json_encode( array(
			array(
				'id'         => (string) $post->ID,
				'quantity'   => 1,
				'item_price' => getEddDownloadPriceToDisplay( $post->ID ),
			)
		) );

		return array(
			'name'  => 'ViewContent',
			'data'  => $params,
			'delay' => (int) PYS()->getOption( 'edd_view_content_delay' ),
		);

	}

	private function getEddAddToCartOnButtonClickEventParams( $download_id ) {
		global $post;

		if ( ! $this->getOption( 'edd_add_to_cart_enabled' ) || ! PYS()->getOption( 'edd_add_to_cart_on_button_click' ) ) {
			return false;
		}

		// maybe extract download price id
		if ( strpos( $download_id, '_') !== false ) {
			list( $download_id, $price_index ) = explode( '_', $download_id );
		} else {
			$price_index = null;
		}

		$params = array(
			'content_type' => 'product',
			'content_ids'  => json_encode( Helpers\getFacebookEddDownloadContentId( $post->ID ) ),
		);

		// content_name, category_name
		$params['tags'] = implode( ', ', getObjectTerms( 'download_tag', $post->ID ) );
		$params = array_merge( $params, Helpers\getEddCustomAudiencesOptimizationParams( $post->ID ) );

		// currency, value
		if ( PYS()->getOption( 'edd_add_to_cart_value_enabled' ) ) {

			if( PYS()->getOption( 'edd_event_value' ) == 'custom' ) {
				$amount = getEddDownloadPrice( $post->ID, $price_index );
			} else {
				$amount = getEddDownloadPriceToDisplay( $post->ID, $price_index );
			}

			$value_option   = PYS()->getOption( 'edd_add_to_cart_value_option' );
			$percents_value = PYS()->getOption( 'edd_add_to_cart_value_percent', 100 );
			$global_value   = PYS()->getOption( 'edd_add_to_cart_value_global', 0 );

			$params['value'] = getEddEventValue( $value_option, $amount, $global_value, $percents_value );
			$params['currency'] = edd_get_currency();

		}

		$license = getEddDownloadLicenseData( $download_id );
		$params  = array_merge( $params, $license );

		// contents
		$params['contents'] = json_encode( array(
			array(
				'id'         => (string) $download_id,
				'quantity'   => 1,
				'item_price' => getEddDownloadPriceToDisplay( $download_id ),
			)
		) );

		return array(
			'data' => $params,
		);

	}

	private function getEddCartEventParams( $context = 'AddToCart' ) {

		if ( $context == 'AddToCart' && ! $this->getOption( 'edd_add_to_cart_enabled' ) ) {
			return false;
		} elseif ( $context == 'InitiateCheckout' && ! $this->getOption( 'edd_initiate_checkout_enabled' ) ) {
			return false;
		} elseif ( $context == 'Purchase' && ! $this->getOption( 'edd_purchase_enabled' ) ) {
			return false;
		} else {
			// AM events allowance checked by themselves
		}

		if ( $context == 'AddToCart' ) {
			$value_enabled  = PYS()->getOption( 'edd_add_to_cart_value_enabled' );
			$value_option   = PYS()->getOption( 'edd_add_to_cart_value_option' );
			$percents_value = PYS()->getOption( 'edd_add_to_cart_value_percent', 100 );
			$global_value   = PYS()->getOption( 'edd_add_to_cart_value_global', 0 );
		} elseif ( $context == 'InitiateCheckout' ) {
			$value_enabled  = PYS()->getOption( 'edd_initiate_checkout_value_enabled' );
			$value_option   = PYS()->getOption( 'edd_initiate_checkout_value_option' );
			$percents_value = PYS()->getOption( 'edd_initiate_checkout_value_percent', 100 );
			$global_value   = PYS()->getOption( 'edd_initiate_checkout_global', 0 );
		} else {
			$value_enabled  = PYS()->getOption( 'edd_purchase_value_enabled' );
			$value_option   = PYS()->getOption( 'edd_purchase_value_option' );
			$percents_value = PYS()->getOption( 'edd_purchase_value_percent', 100 );
			$global_value   = PYS()->getOption( 'edd_purchase_value_global', 0 );
		}

		$params = array(
			'content_type' => 'product'
		);

		$content_ids        = array();
		$content_names      = array();
		$content_categories = array();
		$tags               = array();
		$contents           = array();

		$num_items   = 0;
		$total       = 0;
		$total_as_is = 0;

		$licenses = array(
			'transaction_type'   => null,
			'license_site_limit' => null,
			'license_time_limit' => null,
			'license_version'    => null
		);

		if ( $context == 'AddToCart' || $context == 'InitiateCheckout' ) {
			$cart = edd_get_cart_contents();
		} else {
			$cart = edd_get_payment_meta_cart_details( edd_get_purchase_id_by_key( getEddPaymentKey() ), true );
		}

		foreach ( $cart as $cart_item_key => $cart_item ) {

			$download_id   = (int) $cart_item['id'];
			$content_ids[] = Helpers\getFacebookEddDownloadContentId( $download_id );

			// content_name, category_name
			$custom_audiences = Helpers\getEddCustomAudiencesOptimizationParams( $download_id );

			$content_names[]      = $custom_audiences['content_name'];
			$content_categories[] = $custom_audiences['category_name'];

			$tags = array_merge( $tags, getObjectTerms( 'download_tag', $download_id ) );

			$num_items += $cart_item['quantity'];
			
			if ( in_array( $context, array( 'Purchase', 'FrequentShopper', 'VipClient', 'BigWhale' ) ) ) {
				$item_options = $cart_item['item_number']['options'];
			} else {
				$item_options = $cart_item['options'];
			}
			
			if ( ! empty( $item_options ) && $item_options['price_id'] !== 0 ) {
				$price_index = $item_options['price_id'];
			} else {
				$price_index = null;
			}

			// calculate cart items total
			if ( $value_enabled ) {

				if ( $context == 'Purchase' ) {

					if ( PYS()->getOption( 'edd_tax_option' ) == 'included' ) {
						$total += $cart_item['subtotal'] + $cart_item['tax'] - $cart_item['discount'];
					} else {
						$total += $cart_item['subtotal'] - $cart_item['discount'];
					}

					$total_as_is += $cart_item['price'];

				} else {

					$total += getEddDownloadPrice( $download_id, $price_index ) * $cart_item['quantity'];
					$total_as_is += edd_get_cart_item_final_price( $cart_item_key );

				}

			}

			// get download license data
			array_walk( $licenses, function( &$value, $key, $license ) {

				if ( ! isset( $license[ $key ] ) ) {
					return;
				}

				if ( $value ) {
					$value = $value . ', ' . $license[ $key ];
				} else {
					$value = $license[ $key ];
				}

			}, getEddDownloadLicenseData( $download_id ) );

			// contents
			$contents[] = array(
				'id'         => (string) $download_id,
				'quantity'   => $cart_item['quantity'],
				'item_price' => getEddDownloadPriceToDisplay( $download_id, $price_index ),
			);

		}

		$params['content_ids']   = json_encode( $content_ids );
		$params['content_name']  = implode( ', ', $content_names );
		$params['category_name'] = implode( ', ', $content_categories );
		$params['contents']      = json_encode( $contents );

		$tags           = array_slice( array_unique( $tags ), 0, 100 );
		$params['tags'] = implode( ', ', $tags );

		$params['num_items'] = $num_items;

		// currency, value
		if ( $value_enabled ) {

			if( PYS()->getOption( 'edd_event_value' ) == 'custom' ) {
				$amount = $total;
			} else {
				$amount = $total_as_is;
			}

			$params['value']    = getEddEventValue( $value_option, $amount, $global_value, $percents_value );
			$params['currency'] = edd_get_currency();

		}

		$params = array_merge( $params, $licenses );

		if ( $context == 'Purchase' ) {

			$payment_key = getEddPaymentKey();
			$payment_id = (int) edd_get_purchase_id_by_key( $payment_key );
			$session  = edd_get_purchase_session();

			$user = edd_get_payment_meta_user_info( $payment_id );
			$meta = edd_get_payment_meta( $payment_id );

			// payment method
			if ( isset( $session['gateway'] ) ) {
				$params['payment'] = $session['gateway'];
			}

			// coupons
			$coupons = isset( $user['discount'] ) && $user['discount'] != 'none' ? $user['discount'] : null;

			if ( ! empty( $coupons ) ) {
				$coupons = explode( ', ', $coupons );
				$params['coupon'] = $coupons[0];
			}

			// add transaction date
			$params['transaction_year']  = strftime( '%Y', strtotime( $meta['date'] ) );
			$params['transaction_day']   = strftime( '%d', strtotime( $meta['date'] ) );

			$params['transaction_id'] = $payment_id;
			$params['currency'] = edd_get_currency();

			// calculate value
			if ( PYS()->getOption( 'edd_event_value' ) == 'custom' ) {
				$params['value'] = getEddOrderTotal( $payment_id );
			} else {
				$params['value'] = edd_get_payment_amount( $payment_id );
			}

			if ( edd_use_taxes() ) {
				$params['tax'] = edd_get_payment_tax( $payment_id );
			} else {
				$params['tax'] = 0;
			}

		}

		return array(
			'name' => $context,
			'data' => $params,
		);

	}

	private function getEddRemoveFromCartParams( $cart_item ) {

		if ( ! $this->getOption( 'edd_remove_from_cart_enabled' ) ) {
			return false;
		}

		$download_id = $cart_item['id'];
		$price_index = ! empty( $cart_item['options'] ) ? $cart_item['options']['price_id'] : null;

		$params = array(
			'content_type' => 'product',
			'content_ids' => Helpers\getFacebookEddDownloadContentId( $download_id )
		);

		// content_name, category_name, tags
		$params['tags'] = implode( ', ', getObjectTerms( 'download_tag', $download_id ) );
		$params = array_merge( $params, Helpers\getEddCustomAudiencesOptimizationParams( $download_id ) );

		$params['num_items'] = $cart_item['quantity'];

		$params['contents'] = json_encode( array(
			array(
				'id'         => (string) $download_id,
				'quantity'   => $cart_item['quantity'],
				'item_price' => getEddDownloadPriceToDisplay( $download_id, $price_index ),
			)
		) );

		return array( 'data' => $params );

	}

	private function getEddViewCategoryEventParams() {
		global $posts;

		if ( ! $this->getOption( 'edd_view_category_enabled' ) ) {
			return false;
		}

		$params['content_type'] = 'product';

		$term = get_term_by( 'slug', get_query_var( 'term' ), 'download_category' );
		$params['content_name'] = $term->name;

		$parent_ids = get_ancestors( $term->term_id, 'download_category', 'taxonomy' );
		$params['content_category'] = array();

		foreach ( $parent_ids as $term_id ) {
			$term = get_term_by( 'id', $term_id, 'download_category' );
			$params['content_category'][] = $term->name;
		}

		$params['content_category'] = implode( ', ', $params['content_category'] );

		$content_ids = array();
		$limit       = min( count( $posts ), 5 );

		for ( $i = 0; $i < $limit; $i ++ ) {
			$content_ids = array_merge( array( Helpers\getFacebookEddDownloadContentId( $posts[ $i ]->ID ) ),
				$content_ids );
		}

		$params['content_ids']  = json_encode( $content_ids );

		return array(
			'name' => 'ViewCategory',
			'data' => $params,
		);

	}

	private function getEddAdvancedMarketingEventParams( $eventType ) {

		if ( ! $this->getOption( $eventType . '_enabled' ) ) {
			return false;
		}

		switch ( $eventType ) {
			case 'edd_frequent_shopper':
				$eventName = 'FrequentShopper';
				break;

			case 'edd_vip_client':
				$eventName = 'VipClient';
				break;

			default:
				$eventName = 'BigWhale';
		}

		$params = $this->getEddCartEventParams( $eventName );

		$params['data']['product_names'] = $params['data']['content_name'];
		$params['data']['product_ids'] = $params['data']['content_ids'];

		unset( $params['data']['content_name'] );
		unset( $params['data']['content_ids'] );
		unset( $params['data']['content_type'] );
		unset( $params['data']['contents'] );

		return array(
			'name' => $eventName,
			'data' => $params['data'],
		);

	}

}

/**
 * @return Facebook
 */
function Facebook() {
	return Facebook::instance();
}

Facebook();