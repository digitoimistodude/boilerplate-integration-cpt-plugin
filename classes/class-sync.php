<?php
/**
 * @Author: 						Timi Wahalahti, Digitoimisto Dude Oy (https://dude.fi)
 * @Date:   						2019-02-05 12:22:27
 * @Last Modified by:   Timi Wahalahti
 * @Last Modified time: 2019-02-05 16:15:45
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
class Sync extends MysaasIntegration\Plugin {
	/**
	 *  Run sync.
	 *
	 *  @since  0.1.0
	 */
	public static function sync() {
		// start profiling
		$run_start = microtime( true );
		\do_action( 'qm/start', 'mysaas_sync' ); // @codingStandardsIgnoreLine

		// do logging
		Logging::log( 'Sync started' );

		// save sync start to option
		\update_option( 'mysaas_sync_start', date( 'Y-m-d H:i:s' ), false );

		/**
		 *  If requirements for making sync are not fulfilled, do nothing.
		 *  Logging is haneld by Helper::check_requirements function.
		 */
		if ( ! Helper::check_requirements() ) {
			Logging::log( 'Sync exited' );
			return false;
		}

		// Run items sync.
		$sync = self::sync_items( 'posts' );

		// save sync end to option
		\update_option( 'mysaas_sync_end', date( 'Y-m-d H:i:s' ), true );

		// do logging
		$run_time       = microtime( true ) - $run_start;
		$run_time_human	= \human_time_diff( $run_start, microtime( true ) );
		Logging::log( 'Sync done' );
		Logging::log( "Sync finished in {$run_time} ms ({$run_time_human})", 'debug' );

		// stop profiling
		\do_action( 'qm/stop', 'mysaas_sync' ); // @codingStandardsIgnoreLine

		return $sync;
	} // end sync

	private static function sync_items( $endpoint = null ) {
		// init the counter
		$counter = 0;

		// Get items.
		$response = Request::make_call( $endpoint );

		/**
		 *  Bail if there's no items. Logging is handled by
		 *  Request::make_call function.
		 */
		if ( ! $response ) {
			return false;
		}

		// Get real data
		$items = $response->data;

		// Get item posts from wp db
		$items_in_db = Helper::get_items_from_db();

		// Base for removable items
		$removable_items = $items_in_db;

		// Loop items from API
		foreach ( $items as $item ) {
			$item_uniq_id_key = Plugin::$item_uniq_id_key;
			$item_uniq_id     = $item->{$item_uniq_id_key};

			/**
			 *  Check if that item is alredy in wp db. If is,
			 *  pass the item object and item post id for saving.
			 */
			if ( isset( $items_in_db[ $item_uniq_id ] ) ) {
				unset( $removable_items[ $item_uniq_id ] ); // do not delete this existing item.
				$save = self::save_item( $item, $items_in_db[ $item_uniq_id ] );
			} else {
				// Item is not in wp db, pass item object for saving
				$save = self::save_item( $item );
			}

			// if saved, increase counter
			if ( $save ) {
				$counter++;
			}
		}

		// delete removed items from database
		if ( ! empty( $removable_items ) ) {
			foreach ( $removable_items as $removable_item => $removable_item_id ) {
				Logging::log( "Removing item {$removable_item} ID {$removable_item_id}", 'debug' );
				\wp_delete_post( $removable_item_id, true );
			}
		}

		return array(
			'save'   => $counter,
			'remove' => count( $removable_items ),
		);
	} // end sync_items

	/**
	 *  Do the actual saving of item.
	 *
	 *  @since  0.1.0
	 *  @param  object  $item    Item object from API.
	 *  @param  integer $post_id Existing databse entry id.
	 *  @return mixed            Boolean false if save fails, otherwise databse entry id as integer.
	 */
	private static function save_item( $item = null, $post_id = 0 ) {
		if ( empty( $item ) ) {
			return false; // Bail if we don't have item object.
		}

		// logging
		Logging::log( "Saving item {$item->id} ID {$post_id}", 'debug' );

		// make url slug for item
		$slug = '';
		$slug = $item->name;

		// gather basic post data for update/insert
		// @codingStandardsIgnoreStart
		$post_data = array(
			'post_author'   => 1,
			'post_type'     => Plugin::$cpt,
			'post_status'   => 'publish',
			'post_content'	=> $item->pantone_value,
			'post_title'	  => $item->name,
			'post_name'     => $slug,
			'meta_input'	  => array(
				'_mysaas_sync_update'	=> date( 'Y-m-d H:i:s' ),
			),
		);
		// @codingStandardsIgnoreEnd

		// save details to metadata
		if ( ! empty( $item->vehicle_type ) ) {
			$post_data['meta_input']['_mysaas_color'] = $item->color;
		}

		/**
		 *  If item post id is passed, do the update for existing post in wp.
		 *  Otherwise insert new post.
		 */
		if ( ! empty( $post_id ) ) {
			$post_data['ID'] = $post_id;

			// For some reason, wp_insert_post does not like to update meta when updating post.
			foreach ( $post_data['meta_input'] as $meta_key => $meta_value ) {
				\update_post_meta( $post_id, $meta_key, $meta_value );
			}
		} else {
			// These are meta data we wan't to save only on initial insert.
			$post_data['meta_input']['_mysaas_' . Plugin::$item_uniq_id_key] = $item->color; // @codingStandardsIgnoreLine
			$post_data['meta_input']['_mysaas_sync_initial'] = date( 'Y-m-d H:i:s' );
		}

		// save post
		$post_id = \wp_insert_post( $post_data );

		// If saving caused error, log it and bail
		if ( \is_wp_error( $post_id ) ) {
			Logging::log( "Item {$item->id} ID {$post_id} not saved", 'debug', $post_id );
			return false;
		}

		// If item has thibgs to save in taxonomy, handle that.
		if ( ! empty( $item->year ) ) {
			$term = self::maybe_save_tax_term( strval( $item->year ), 'post_year' );

			// Attach terms to inserted post.
			if ( $term ) {
				$term_set = \wp_set_post_terms( $post_id, array( intval( $term ) ), 'post_year' );

				if ( false === $term_set || \is_wp_error( $term_set ) ) {
					Logging::log( "Term assignement failed for {$item->id} ID {$post_id}", 'debug', $term_set );
				}
			}
		}

		// logging
		Logging::log( "Saved item {$item->id} ID {$post_id}", 'debug' );

		return $post_id;
	} // end save_item

	/**
	 *  Handle taxonomy term saving
	 *
	 *  @since  0.1.0
	 *  @param  mixed  $term    term to save. Accepts term ID, slug, or name.
	 *  @param  string $taxonmy taxonomy of term to save.
	 *  @return mixed           boolean false if term not saved, term id if existing term or saved.
	 */
	private static function maybe_save_tax_term( $term = null, $taxonmy = null ) {
		if ( empty( $term ) || empty( $taxonomy ) ) {
			return false; // Bail early if there's no term.
		}

		// Check if term exists in wp. If does, return term id.
		$term_exists = \term_exists( $term, $taxonomy );
		if ( ! empty( $term_exists ) ) {
			return $term_exists['term_id'];
		}

		// Term didn't exist, try to insert it into wp.
		$insert_term = \wp_insert_term( $term, $taxonomy );
		if ( \is_wp_error( $insert_term ) ) {
			// Term insert failed, log it and bail.
			Logging::log( "Failed saving term {$term} to {$taxonomy}", 'debug', $insert_term );
			return false;
		}

		return $insert_term['term_id'];
	} // end maybe_save_tax_term
} // end Sync
