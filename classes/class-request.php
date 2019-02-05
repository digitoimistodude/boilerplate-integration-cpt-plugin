<?php
/**
 * @Author:             Timi Wahalahti, Digitoimisto Dude Oy (https://dude.fi)
 * @Date:               2019-02-04 15:08:21
 * @Last Modified by:   Timi Wahalahti
 * @Last Modified time: 2019-02-05 16:15:46
 *
 * @package mysaas-integration
 */

namespace MysaasIntegration;

use MysaasIntegration;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Base for plugin request class.
 */
class Request extends MysaasIntegration\Plugin {

	/**
	 *  Make the call to  API.
	 *
	 *  @since 0.1.0
	 *  @param string $endpoint endpoint we want to call.
	 *  @param array  $url_args possible url arguments to add to call.
	 *  @return mixed           boolean false if call failed, return body if call was succesful and there is data.
	 */
	public static function make_call( $endpoint = '', $url_args = array() ) {

		// Get url for request.
		$url = self::build_request_url( $endpoint, $url_args );

		/**
		 * Bail if we can't have url, logging is handled by
		 * self::_build_request_url.
		 */
		if ( ! $url ) {
			return false;
		}

		// Add log entry about making call.
		Logging::log( "Called {$url}", 'debug' );

		// Call API and get response.
		$response = \wp_safe_remote_get( $url, array(
			'headers' => self::build_request_headers(),
		) );

		/**
		 *  Validate that we did get response and it looks good, if not
		 *  then bail early. Logging is handled by
		 *  self::_validate_request_response.
		 */
		if ( ! self::validate_request_response( $response ) ) {
			return false;
		}

		// Get body from response and decode it.
		$data = \wp_remote_retrieve_body( $response );
		$data = json_decode( $data );

		// Bail of not valid JSON or empty data.
		if ( empty( $data ) ) {
			Logging::log( 'Empty or invalid response', 'debug' );
			return false;
		}

		return $data;
	} // end make_call

	/**
	 *  Build API url to request.
	 *
	 *  @since  0.1.0
	 *  @param string $endpoint endpoint we want to call.
	 *  @param array  $args 		possible url arguments to add to call.
	 *  @return mixed 					boolean false if no endpoint spesificed, otherwise url string.
	 */
	private static function build_request_url( $endpoint = '', $args = array() ) {
		// Bail if no endpoint spesified.
		if ( empty( $endpoint ) ) {
			Logging::log( 'No endpoint specified', 'debug' );
			return false;
		}

		$default_args = array();

		// Parse url together.
		$url  = \trailingslashit( Plugin::$api_base );
		$url .= $endpoint;
		$url  = \add_query_arg( wp_parse_args( $args, $default_args ), $url );

		return $url;
	} // end build_request_url

	/**
	 *  Build headers for request.
	 *
	 *  @since  0.1.0
	 *  @return array  additional headers for call.
	 */
	private static function build_request_headers() {
		return array();
	} // end build_request_headers

	/**
	 *  Do checks againts API response and try to make sure it is the real thing.
	 *
	 *  @since  0.1.0
	 *  @param  mixed $response response from API.
	 *  @return boolean         false if response is not valid, true if it is.
	 */
	private static function validate_request_response( $response ) {
		// chck against WP error.
		if ( \is_wp_error( $response ) ) {
			Logging::log( 'Request caused WP_Error', 'debug', $response );
			return false;
		}

		// check API status code.
		if ( 200 !== $response['response']['code'] ) {
			Logging::log( 'Returned status ' . $response['response']['code'], 'debug' );
			return false;
		}

		// check data existance
		if ( empty( \wp_remote_retrieve_body( $response ) ) ) {
			Logging::log( 'Return body was empty', 'debug' );
			return false;
		}

		return true;
	} // end validate_request_response
} // end Request
