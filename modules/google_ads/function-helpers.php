<?php

namespace PixelYourSite\Ads\Helpers;

use PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

function getWooFullItemId( $item_id ) {
	
	$prefix = PixelYourSite\Ads()->getOption( 'woo_item_id_prefix' );
	$suffix = PixelYourSite\Ads()->getOption( 'woo_item_id_suffix' );
	
	return trim( $prefix ) . $item_id . trim( $suffix );
	
}

function renderConversionLabelInputs( $eventKey ) {
    
    $options = array();
    foreach ( PixelYourSite\Ads()->getPixelIDs() as $conversion_id ) {
        $options[ $conversion_id ] = $conversion_id;
    }
    
    $count = count( PixelYourSite\Ads()->getPixelIDs() );
    
    ?>
    
    <div class="row mt-1 mb-2">
        <div class="col-11 col-offset-left form-inline">
            <label>Add conversion label </label>
            <?php PixelYourSite\Ads()->render_text_input( $eventKey . '_conversion_label', 'Enter conversion label' ); ?>
            <?php if ( $count > 1 ) : ?>
                <label> for </label>
                <?php PixelYourSite\Ads()->render_select_input( $eventKey . '_conversion_id', $options ); ?>
            <?php endif; ?>
        </div>
        <div class="col-1">
            <button type="button" class="btn btn-link" role="button" data-toggle="pys-popover" data-trigger="focus"
                    data-placement="right" data-popover_id="google_ads_conversion_label" data-original-title=""
                    title="">
                <i class="fa fa-info-circle" aria-hidden="true"></i>
            </button>
        </div>
    </div>
    
    <?php
}

function getConversionIDs( $key ) {
    
    // conversion label for specified event
    $label = PixelYourSite\Ads()->getOption( $key . '_conversion_label' );
    // account id and for specified event
    $id = PixelYourSite\Ads()->getOption( $key . '_conversion_id' );
    
    if ( empty( $label ) || empty( $id ) ) {
        return [];
    }
    
    $conversion_ids = [];
    
    // add conversion label only to specified account id
    foreach ( PixelYourSite\Ads()->getPixelIDs() as $key => $conversion_id ) {
        if ( $conversion_id == $id ) {
            $conversion_ids[] = $conversion_id . '/' . $label;
        } else {
            $conversion_ids[] = $conversion_id;
        }
    }
    
    return $conversion_ids;
    
}