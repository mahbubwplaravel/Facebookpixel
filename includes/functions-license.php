<?php

namespace PixelYourSite;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * @param Plugin|Settings $plugin
 */
function renderLicenseControls( $plugin ) {

	$slug = $plugin->getSlug();
	$input_name = "pys[{$slug}][license_action]";

	$license_status = $plugin->getOption( 'license_status' );

	?>

	<div class="row">
		<div class="col-9">
			<?php $plugin->render_text_input( 'license_key', 'Enter your license key' ); ?>
		</div>
		<div class="col-3">
			<?php if( $license_status == 'valid' ) : ?>
				<button class="btn btn-block btn-sm btn-danger" name="<?php esc_attr_e( $input_name ); ?>"
				        value="deactivate">Deactivate License</button>
			<?php else : ?>
				<button class="btn btn-block btn-sm btn-primary" name="<?php esc_attr_e( $input_name ); ?>"
				        value="activate">Activate License</button>
			<?php endif; ?>
		</div>
	</div>

	<?php

	$license_key = $plugin->getOption( 'license_key' );
	$license_expires = $plugin->getOption( 'license_expires', null );

	$license_expires_soon = false;
	$license_expired = false;

	if( $license_expires ) {

		$now = time();

		if( $now >= $license_expires ) {
			$license_expired = true;
		} elseif ( $now >= ( $license_expires - 30 * DAY_IN_SECONDS ) ) {
			$license_expires_soon = true;
		}

	}

	if ( $notice = get_transient( "pys_{$slug}_license_notice" ) ) :
		?>

		<div class="row mt-3">
			<div class="col">
				<div class="alert alert-<?php esc_attr_e( $notice['class'] ); ?> mb-0" role="alert">
					<?php echo $notice['msg']; ?>
				</div>
			</div>
		</div>

		<?php

		delete_transient(  "pys_{$slug}_license_notice" );

	endif;

	if ( $license_expires_soon ) :
		?>

		<div class="row mt-3">
			<div class="col">
				<div class="alert alert-warning mb-0">
					<p>Your license key <strong>expires
							on <?php echo date( get_option( 'date_format' ), $license_expires ); ?></strong>. Make sure
						you keep everything updated and in order.</p>
					<p class="mb-0"><a href="https://www.pixelyoursite.com/checkout/?edd_license_key=<?php esc_attr_e(
						$license_key ); ?>&utm_campaign=admin&utm_source=licenses&utm_medium=renew" target="_blank"><strong>Click here to renew your license now for a 40% discount</strong></a></p>
				</div>
			</div>
		</div>

		<?php
	endif;

	if ( $license_expired ) :
		?>

		<div class="row mt-3">
			<div class="col">
				<div class="alert alert-danger mb-0">
					<p><strong>Your license key is expired</strong>, so you no longer get any updates. Don't miss our
                        latest improvements and make sure that everything works smoothly.</p>
					<p class="mb-0"><a href="https://www.pixelyoursite.com/checkout/?edd_license_key=<?php esc_attr_e(
						$license_key ); ?>&utm_campaign=admin&utm_source=licenses&utm_medium=renew" target="_blank"><strong>Click here to renew your license now</strong></a></p>
				</div>
			</div>
		</div>

		<?php
	endif;

}

/**
 * @param Plugin|Settings $plugin
 */
function updateLicense( $plugin ) {

	$slug = $plugin->getSlug();

	// nothing to do...
	if( ! isset( $_POST['pys'][ $slug ]['license_action'] ) ) {
		return;
	}

	$license_action = $_POST['pys'][ $slug ]['license_action'];
	$license_key    = isset( $_POST['pys'][ $slug ]['license_key'] ) ? $_POST['pys'][ $slug ]['license_key'] : '';

	// activate/deactivate license
	if ( $license_action == 'activate' ) {
		$license_data = licenseActivate( $license_key, $plugin );
	} else {
		$license_data = licenseDeactivate( $license_key, $plugin );
	}

	$license_status = $plugin->getOption( 'license_status' );
	$license_expires = $plugin->getOption( 'license_expires' );

	$admin_notice = array();

	if ( is_wp_error( $license_data ) ) {

		$admin_notice = array(
			'class' => 'danger',
			'msg'   => 'Something went wrong during license update request. [' . $license_data->get_error_message() . ']'
		);

	} else {

		/**
		 * Overwrite empty license status only on successful activation.
		 * For existing status overwrite with any value except error.
		 */
		if ( empty( $license_status ) && $license_data->license == 'valid' ) {
			$license_status = 'valid';
		} elseif ( ! empty( $license_status ) ) {
			$license_status = $license_data->license;
		}

		if ( $license_data->success ) {

			switch ( $license_data->license ) {
				case
				'valid':
					$admin_notice = array(
						'class' => 'success',
						'msg'   => 'Your license is working fine. Good job!'
					);
					break;

				case 'deactivated':
					$admin_notice = array(
						'class' => 'success',
						'msg'   => 'Your license was successfully deactivated for this site.'
					);
					break;
			}

			$license_expires = strtotime( $license_data->expires );

		} else {

			switch ( $license_data->error ) {
				case 'invalid':                 // key do not exist
				case 'missing':
				case 'key_mismatch':
					$admin_notice = array(
						'class' => 'success',
						'msg'   => "Your license was successfully deactivated for this site."
					);
					break;

				case 'license_not_activable':   // trying to activate bundle license
					$admin_notice = array(
						'class' => 'danger',
						'msg'   => 'If you have a bundle package, please use each individual license for your products.'
					);
					break;

				case 'revoked':                 // license key revoked
					$admin_notice = array(
						'class' => 'danger',
						'msg'   => 'This license was revoked.'
					);
					break;

				case 'no_activations_left':     // no activations left
					$admin_notice = array(
						'class' => 'danger',
						'msg'   => 'No activations left. Log in to your account to extent your license.'
					);
					break;

				case 'invalid_item_id':
					$admin_notice = array(
						'class' => 'danger',
						'msg'   => 'Invalid item ID.'
					);
					break;

				case 'item_name_mismatch':      // item names don't match
					$admin_notice = array(
						'class' => 'danger',
						'msg'   => "Item names don't match."
					);
					break;

				case 'expired':                 // license has expired
					$admin_notice = array(
						'class' => 'danger',
						'msg'   => 'Your License has expired. <a href="http://www.pixelyoursite.com/checkout/?edd_license_key=' . urlencode( $license_key ) . '&utm_campaign=admin&utm_source=licenses&utm_medium=renew" target="_blank">Renew it now.</a>'
					);
					break;

				case 'inactive':                // license is not active
					$admin_notice = array(
						'class' => 'danger',
						'msg'   => 'This license is not active. Activate it now.'
					);
					break;

				case 'disabled':                // license key disabled
					$admin_notice = array(
						'class' => 'danger',
						'msg'   => 'License key disabled.'
					);
					break;

				case 'site_inactive':
					$admin_notice = array(
						'class' => 'danger',
						'msg'   => 'The license is not active for this site. Activate it now.'
					);
					break;

			}

			// add error code
			$admin_notice['msg'] .= " [error: $license_data->error]";

		}

	}

	if ( ! empty( $admin_notice ) ) {
		set_transient( "pys_{$slug}_license_notice", $admin_notice, 60 * 5 );
	}

	$plugin->updateOptions(
	array (
   'license_key'     => 'NULLED-BY-GANJAPARKER',
   'license_status'  => 'valid',
   'license_expires' => $license_expires
  )
	);

}

/**
 * @param string          $license_key
 * @param Plugin|Settings $plugin
 *
 * @return array|mixed|object|\WP_Error
 */
function licenseActivate( $license_key, $plugin ) {

	$api_params = array(
		'edd_action' => 'activate_license',
		'license'    => $license_key,
		'item_name'  => urlencode( $plugin->getPluginName() ),
		'url'        => home_url()
	);

	$response = wp_remote_post( 'https://www.pixelyoursite.com', array(
		'timeout'   => 120,
		'sslverify' => false,
		'body'      => $api_params
	) );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	// $license_data->license will be either "valid" or "invalid"
	return json_decode( wp_remote_retrieve_body( $response ) );

}

/**
 * @param string          $license_key
 * @param Plugin|Settings $plugin
 *
 * @return array|mixed|object|\WP_Error
 */
function licenseDeactivate( $license_key, $plugin ) {

	$api_params = array(
		'edd_action' => 'deactivate_license',
		'license'    => $license_key,
		'item_name'  => urlencode( $plugin->getPluginName() ),
		'url'        => home_url()
	);

	$response = wp_remote_post( 'https://www.pixelyoursite.com', array(
		'timeout'   => 120,
		'sslverify' => false,
		'body'      => $api_params
	) );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	// $license_data->license will be either "deactivated" or "failed"
	return json_decode( wp_remote_retrieve_body( $response ) );

}
