<?php
/**
 * @Author: 						Timi Wahalahti, Digitoimisto Dude Oy (https://dude.fi)
 * @Date:   						2019-02-15 10:49:50
 * @Last Modified by:   Timi Wahalahti
 * @Last Modified time: 2019-03-12 17:29:42
 *
 * @package leirintaopas
 */

namespace MysaasIntegration;

use MysaasIntegration;

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command( 'mysaasreaimport', __NAMESPACE__ . '\\WP_CLI_Sync' );
}

class WP_CLI_Sync extends MysaasIntegration\Plugin {

    /**
     * Sync the items
     */
    public function sync( $args, $assoc_args ) {
    	if ( ! isset( $assoc_args['page'] ) ) {
    		$assoc_args['page'] = 1;
    	}

		// Let's make sure we really want to do this
		if ( ! isset( $assoc_args['yes'] ) ) {
			\WP_CLI::confirm( 'Are you sure you want to proceed? Sync might take a while.', $assoc_args );
		}

    	$run = Sync::sync( $assoc_args['page'] );

    	\WP_CLI::success( "Päivitys onnistui! Päivitettiin {$run['save']} ja poistettiin {$run['remove']}." );
    }

}
