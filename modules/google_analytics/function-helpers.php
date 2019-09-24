<?php

namespace PixelYourSite\GA\Helpers;

use PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

function adaptDynamicRemarketingParams( $params ) {
	
	if ( PixelYourSite\PYS()->getOption( 'google_retargeting_logic' ) == 'ecomm' ) {
		
		return array(
			'ecomm_prodid'     => $params['product_id'],
			'ecomm_pagetype'   => $params['page_type'],
			'ecomm_totalvalue' => $params['total_value'],
		);
		
	} else {
		
		// custom vertical has different than retail page types
		$page_types = array(
			'search' => 'searchresults',
			'product' => 'offerdetail',
			'category' => null, //not supported by custom vertical
			'cart' => 'conversionintent',
			'checkout' => 'conversionintent',
			'purchase' => 'conversion'
		);
		
		return array(
			'dynx_itemid'     => $params['product_id'],
			'dynx_pagetype'   => $page_types[ $params['page_type'] ],
			'dynx_totalvalue' => $params['total_value'],
		);
		
	}
	
}
