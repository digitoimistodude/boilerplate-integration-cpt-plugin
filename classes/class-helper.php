<?php
/**
 * @Author: 						Timi Wahalahti, Digitoimisto Dude Oy (https://dude.fi)
 * @Date:   						2019-02-05 12:06:50
 * @Last Modified by:   Timi Wahalahti
 * @Last Modified time: 2019-02-05 16:15:49
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
 * Base for plugin helper class.
 */
class Helper extends MysaasIntegration\Plugin {
	/**
	 *  Check that requirements for running sync are met.
	 *
	 *  @since  0.1.0
	 *  @return boolean  true if sync can be run, false otherwise
	 */
	public static function check_requirements() {
		$requirements = true;

		/**
		 *  Check API key existance.
		 *
		 *  @since 0.1.0
		 */
		// @codingStandardsIgnoreStart
		// if ( ! self::get_api_key() ) {
		// 	Logging::log( 'No API key in env', 'error' );
		// 	$requirements = false;
		// }
		// @codingStandardsIgnoreEnd

		return $requirements;
	} // end check_requirements

	/**
	 *  Get API key.
	 *
	 *  @since  0.1.0
	 *  @return mixed  string if API key exists, false otherwise
	 */
	public static function get_api_key() {
		return getenv( Plugin::$api_key_name );
	} // end get_api_key

	/**
	 *  [get_items_from_db description]
	 *
	 *  @since  0.1.0
	 *  @param  array $args arguments for WP_Query.
	 *  @return array       array containing existing item
	 */
	public static function get_items_from_db( $args = array() ) {
		// @codingStandardsIgnoreStart
		$items 				= array();
		$default_args = array(
			'post_type'				=> Plugin::$cpt,
			'post_status'			=> 'publish',
			'posts_per_page'	=> -1,
		);
		// @codingStandardsIgnoreEnd

		$items_query = new \WP_Query( \wp_parse_args( $args, $default_args ) );

		if ( $items_query->have_posts() ) {
			while ( $items_query->have_posts() ) {
				$items_query->the_post();

				$uniq_id      = 'nouniq-' . \get_the_id();
				$uniq_id_meta = \get_post_meta( get_the_id(), '_mysaas_' . Plugin::$item_uniq_id_key , true ); // @codingStandardsIgnoreLine

				if ( ! empty( $uniq_id_meta ) ) {
					$uniq_id = $uniq_id_meta;
				}

				$items[ $uniq_id ] = \get_the_id();
			}
		}

		return $items;
	} // end get_items_from_db
} // end Helper
