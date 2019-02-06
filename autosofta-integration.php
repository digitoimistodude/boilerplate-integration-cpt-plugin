<?php
/**
 * Get items from Mysaas.
 *
 * Plugin Name:       Mysaas
 * Plugin URI:
 * Description:				Get items from Mysaas.
 * Author:            Timi Wahalahti, Digitoimisto Dude
 * Author URI:        https://timi.dude.fi
 * License:           GPLv3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Version:           0.1.0
 * Requires at least: 5.0
 * Tested up to:      5.0.3
 *
 * @Author: Timi Wahalahti
 * @Date:   2019-02-04 15:00:00
 * @Last Modified by:   Timi Wahalahti
 * @Last Modified time: 2019-02-06 16:18:22
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
 *  Base for plugin.
 */
class Plugin {
	/**
	 *  Set CPT used for this sync plugin.
	 *
	 *  @var string
	 */
	public static $cpt = null;

	/**
	 *  Set REST API base url.
	 *
	 *  @var string
	 */
	public static $api_base = 'https://reqres.in/api';

	/**
	 *  Set REST API key env variable name.
	 *
	 *  @var string
	 */
	public static $api_key_name = 'MYSAAS_API_KEY';

	/**
	 *  Spesify what REST API field contains unique id for item.
	 *
	 *  @var string
	 */
	public static $item_uniq_id_key = 'color';

	/**
	 *  How ofter automated sync should be run via WP cron,
	 *  give time in seconds. You can use time constants, see
	 *  https://codex.wordpress.org/Easier_Expression_of_Time_Constants
	 *
	 *  @var integer
	 */
	public static $cron_interval = MINUTE_IN_SECONDS * 15;

	/**
	 *  Simplify plugin files including.
	 *
	 *  @since  0.1.0
	 */
	public static function plugin_base_path( $file = null ) {
		$path = \untrailingslashit( \plugin_dir_path( __FILE__ ) );

		if ( $file ) {
			$path .= "/{$file}";
		}

		return $path;
	} // end plugin_base_path

	/**
	 *  Load all things needed.
	 *
	 *  @since  0.1.0
	 */
	public static function load() {
		include_once self::plugin_base_path( 'classes/class-logging.php' );
		include_once self::plugin_base_path( 'classes/class-helper.php' );
		include_once self::plugin_base_path( 'classes/class-request.php' );
		include_once self::plugin_base_path( 'classes/class-sync.php' );

		// setup logger
		new Logging();

		// actions
		add_action( 'admin_menu',			array( __CLASS__, 'manual_sync_link' ) );
		add_action( 'admin_init',			array( __CLASS__, 'output_buffer_manual_sync_page' ) );
	} // end load

	/**
	 *  Add link to admin menu for manual sync.
	 *
	 *  @since  0.1.0
	 */
	public static function manual_sync_link() {
		$cpt = Plugin::$cpt;

		\add_submenu_page(
			"edit.php?post_type={$cpt}",
			'Päivitä',
			'Päivitä',
			'publish_pages',
			'mysaas-do-manual-sync',
			array( __CLASS__, 'manual_sync_link_callback' )
		);
	} // end add_force_sync_link

	/**
	 *  Output buffer manual sync page for redirect to work.
	 *
	 *  @since  0.1.0
	 *  @return void
	 */
	public static function output_buffer_manual_sync_page() {
		if ( ! isset( $_GET['page'] ) ) { // @codingStandardsIgnoreLine
			return;
		}

		if ( 'mysaas-do-manual-sync' !== $_GET['page'] ) { // @codingStandardsIgnoreLine
			return;
		}

		ob_start();
	} // end output_buffer_manual_sync_page

	/**
	 *  Maybe do the manual sync.
	 *
	 *  @since  0.1.0
	 */
	public static function manual_sync_link_callback() {
		$notice_class   = 'notice notice-error';
		$notice_message = 'Päivitys ei onnistunut. Yritä uudelleen tai odota seuraavaa automaattista ajoa.';

		if ( \current_user_can( 'publish_pages' ) ) {

			// do the manual sync
			$run = Sync::sync();

			if ( $run ) {
				Logging::log( 'Manual sync made' );

				// Usually when user does manual sync, there is something off. Maybe a cron job?
				if ( ! \wp_next_scheduled( 'mysaas_sync' ) ) {
					$schedule_name = self::$cron_interval % 60;
					\wp_schedule_event( time(), "{$schedule_name}min", 'mysaas_sync' );
				}

				$notice_class 	= 'notice notice-success is-dismissible';
				$notice_message = "Päivitys onnistui! Päivitettiin {$run['save']} ja poistettiin {$run['remove']}.";
			}
		}

		echo '<div class="wrap">';
		echo '<h2>Mysaas manuaalinen päivitys</h2>';
		printf( '<div class="%1$s"><p>%2$s</p></div>', \esc_attr( $notice_class ), \esc_html( $notice_message ) );
		printf( '<p>Viimeisin päivitys aloitettu: %s</p>', \get_option( 'mysaas_sync_start' ) );
		printf( '<p>Viimeisin päivitys lopetettu: %s</p>', \get_option( 'mysaas_sync_end' ) );
		echo '</div>';
	} // end manual_sync_link_callback
} // end Plugin

/**
 *  Start the plugin.
 *
 *  @since  0.1.0
 */
function load_plugin() {
	$plugin = new Plugin();
	$plugin->load();
} // end load_plugin

// Add action to really start the plugin.
\add_action( 'plugins_loaded', __NAMESPACE__ . '\\load_plugin' );

// Add custom cron schedule
\add_filter( 'cron_schedules', __NAMESPACE__ . '\\cron_schedules' ); // @codingStandardsIgnoreLine
function cron_schedules( $schedules ) {
	$schedule_name = Plugin::$cron_interval % 60;
	if ( ! isset( $schedules[ "{$schedule_name}min" ] ) ) {
		$schedules[ "{$schedule_name}min" ] = array(
			'interval' => Plugin::$cron_interval,
			'display'  => 'Once every {$schedule_name} minutes',
		);
	}

	return $schedules;
} // end cron_schedules

// Add cron job for sync
\register_activation_hook( __FILE__, __NAMESPACE__ . '\\schedule_cron_sync' );
function schedule_cron_sync() {
	if ( ! \wp_next_scheduled( 'mysaas_sync' ) ) {
		$schedule_name = Plugin::$cron_interval % 60;
		\wp_schedule_event( time(), "{$schedule_name}min", 'mysaas_sync' );
	}
}

// On deactivation, remove cron job
\register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deschedule_cron_sync' );
function deschedule_cron_sync() {
	\wp_clear_scheduled_hook( 'mysaas_sync' );
}
