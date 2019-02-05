<?php
/**
 * Based on Pippin Williamsons WP-Logging class, see https://github.com/pippinsplugins/wp-logging
 *
 * @Author: 						Timi Wahalahti, Digitoimisto Dude Oy (https://dude.fi)
 * @Date:   						2019-02-05 10:58:47
 * @Last Modified by:   Timi Wahalahti
 * @Last Modified time: 2019-02-05 16:15:47
 *
 * @package mysaas-integration
 *
 * Ignore phpcs in whole file as it contains multple direct file operations.
 * @codingStandardsIgnoreFile
 */

namespace MysaasIntegration;

use MysaasIntegration;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 *  Class for logging events and errors
 */
class Logging extends MysaasIntegration\Plugin {

	public static $is_writable   = true;
	private static $filename     = '';
	private static $file         = '';
	private static $debug_to_log = false;

	/**
	 * Set up the class
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		// should we write a debug log
		self::$debug_to_log = constant( 'WP_DEBUG' );
		self::$debug_to_log = \apply_filters( 'mysaas_debug_log_write', self::$debug_to_log );

		if ( self::$debug_to_log ) {
			self::setup_log_file();
		}

		if ( \apply_filters( 'mysaas_disable_simple_history_cpt', true ) ) {
			\add_filter( 'simple_history/log/do_log', array( __CLASS__, 'simple_log_disable_cpt' ), 10, 5 );
		}
	} // end __construct

	/**
	 * Sets up the log file if it is writable
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public static function setup_log_file() {
		$upload_dir     = \wp_upload_dir();
		self::$filename = \wp_hash( \home_url( '/' ) ) . '-mysaas-debug.log';
		self::$file     = \trailingslashit( $upload_dir['basedir'] ) . self::$filename;

		if ( ! is_writeable( $upload_dir['basedir'] ) ) {
			self::$is_writable = false;
		}
	} // end setup_log_file

	public static function simple_log_disable_cpt( $do_log = null, $level = null, $message = null, $context = null, $logger = null ) {
		if ( ( isset( $logger->slug ) && ( $logger->slug === 'SimplePostLogger' ) ) && ( isset( $context['post_type'] ) && Plugin::$cpt === $context['post_type'] ) ) {
			$do_log = false;
		}

		return $do_log;
	} // end simple_log_disable_cpt

	/**
	 * Create new log entry
	 *
	 * @since 0.1.0
	 * @param string $message Log entry message.
	 * @return void
	 */
	public static function log( $message = '', $level = 'info', $wp_error = null ) {
		// write to debug log
		if ( self::$debug_to_log ) {
			self::log_to_file( $message );
		}

		// prefix our log line
		$message = "Mysaas API: {$message}";

		// write to query monitor
		if ( true === self::$debug_to_log ) {
			// query monitor supports WP_Error directly, so give it of there is WP_Error class
			if ( $wp_error && \is_wp_error( $wp_error ) ) {
				\do_action( "qm/error", $message );
				\do_action( "qm/error", $wp_error );
			} else {
				\do_action( "qm/{$level}", $message );
			}
		}

		// write info and notice messaged to simple history
		if ( 'info' === $level || 'notice' === $level ) {
			\apply_filters( 'simple_history_log', $message, null, $level );
		}
	} // end log

	/**
	 * Retrieve the log data
	 *
	 * @since 2.8.7
	 * @return string
	 */
	public static function get_file_contents() {
		return self::get_file();
	} // end get_file_contents

	/**
	 * Log message to file
	 *
	 * @since 2.8.7
	 * @return void
	 */
	public static function log_to_file( $message = '' ) {
		$message = date( 'Y-n-d H:i:s' ) . ' - ' . $message . "\r\n";
		self::write_to_log( $message );

	} // end log_to_file

	/**
	 * Retrieve the file data is written to
	 *
	 * @since 2.8.7
	 * @return string
	 */
	protected static function get_file() {

		$file = '';

		if ( @file_exists( self::$file ) ) {

			if ( ! is_writeable( self::$file ) ) {
				self::$is_writable = false;
			}

			$file = @file_get_contents( self::$file );

		} else {

			@file_put_contents( self::$file, '' );
			@chmod( self::$file, 0664 );

		}

		return $file;
	} // end get_file

	/**
	 * Write the log message
	 *
	 * @since 2.8.7
	 * @return void
	 */
	protected static function write_to_log( $message = '' ) {
		$file = self::get_file();
		$file .= $message;
		@file_put_contents( self::$file, $file );
	} // end write_to_log

	/**
	 * Delete the log file or removes all contents in the log file if we cannot delete it
	 *
	 * @since 0.1.0
	 * @return boolean
	 */
	public static function clear_log_file() {
		@unlink( self::$file );

		if ( file_exists( self::$file ) ) {

			// it's still there, so maybe server doesn't have delete rights
			chmod( self::$file, 0664 ); // Try to give the server delete rights
			@unlink( self::$file );

			// See if it's still there
			if ( @file_exists( self::$file ) ) {

				// Remove all contents of the log file if we cannot delete it
				if ( is_writeable( self::$file ) ) {

					file_put_contents( self::$file, '' );

				} else {

					return false;

				}
			}
		}

		self::$file = '';
		return true;

	} // end clear_log_file
} // end Logging
