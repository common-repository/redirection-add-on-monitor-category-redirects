<?php
/*
Plugin Name: Redirection add-on: Monitor category redirects
Plugin URI: https://wordpress.org/plugins/redirection-monitor-category-redirects/
Description: Monitors category redirects
Version: 1.0.1
Author: Jesus Iniesta
Author URI: https://jesusiniesta.es
Text Domain: redirection-monitor-category-redirects
============================================================================================================
This software is provided "as is" and any express or implied warranties, including, but not limited to, the
implied warranties of merchantibility and fitness for a particular purpose are disclaimed. In no event shall
the copyright owner or contributors be liable for any direct, indirect, incidental, special, exemplary, or
consequential damages(including, but not limited to, procurement of substitute goods or services; loss of
use, data, or profits; or business interruption) however caused and on any theory of liability, whether in
contract, strict liability, or tort(including negligence or otherwise) arising in any way out of the use of
this software, even if advised of the possibility of such damage.

For full license details see license.txt
============================================================================================================
*/

define( 'REDIRECTION_MONITOR_CATEGORY_REDIRECTS_DB_VERSION', '1.0.1' );     // DB schema version. Only change if DB needs changing
define( 'REDIRECTION_MONITOR_CATEGORY_REDIRECTS_FILE', __FILE__ );
define( 'REDIRECTION_MONITOR_CATEGORY_REDIRECTS_DEV_MODE', false );
define( 'REDIRECTION_MONITOR_CATEGORY_REDIRECTS_PLUGIN_NAME', 'redirection-monitor-category-redirects' );

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// If Redirection is not active, abort.
if ( ! is_plugin_active( 'redirection/redirection.php' ) ) {
	add_action( 'admin_notices', 'redirection_monitor_category_redirects_admin_notice' );
	function redirection_monitor_category_redirects_admin_notice() {
		$class   = 'notice notice-error';
		$message = __( 'Redirection add-on: Monitor category redirects requires <a href="https://wordpress.org/plugins/redirection/">Redirection</a> to be installed and active.', 'redirection-monitor-category-redirects' );
		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );

		return;
	}
}


add_action( 'edit_terms', 'update_redirections_for_category_slug_change', 10, 2 );
function update_redirections_for_category_slug_change( $term_id ) {
	$old_category           = get_term( $term_id );
	$old_category_permalink = get_term_link( $old_category );
	if ( ! empty( $old_category_permalink ) && ! is_wp_error( $old_category_permalink ) ) {
		add_action( 'edited_category', function ( $term_id ) use ( $old_category_permalink ) {
			$category               = get_term( $term_id );
			$new_category_permalink = get_term_link( $category );

			if ( empty( $new_category_permalink ) || is_wp_error( $new_category_permalink ) ) {
				return;
			}

			$redirections = Red_Item::get_for_url( $old_category_permalink, false, 'url' );
			if ( empty( $redirections ) ) {
				// create redirection
				$redirection = Red_Item::create( array(
					'status'      => 'enabled',
					'match_type'  => 'url',
					'title'       => sprintf(__('Auto-generated redirection triggered by category slug change (%s -> %s)', 'redirection-monitor-category-redirects'), $old_category_permalink, $new_category_permalink),
					'url'         => $old_category_permalink,
					'action_type' => 'url',
					'action_code' => 301,
					'group_id'    => 1,
					'action_data' => array(
						'url' => $new_category_permalink
					)
				) );
				if ( is_wp_error( $redirection ) ) {
					$plugin_name = REDIRECTION_MONITOR_CATEGORY_REDIRECTS_PLUGIN_NAME;
					$message = "[{$plugin_name}] Error: " . $redirection->get_error_message();
					add_action( 'admin_notices', function () use ( $message ) {
						$class   = 'notice notice-error';
						sprintf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
					} );
					error_log( $message );
				}
			} else {
				// update redirection
				$redirection = $redirections[0];
				$redirection->update( array(
					'title'       => sprintf(__('[%s] Auto-generated redirection triggered by category slug change (%s -> %s)', 'redirection-monitor-category-redirects'), get_bloginfo('name'), $old_category_permalink, $new_category_permalink),
					'url'         => $old_category_permalink,
					'action_data' => array(
						'url' => $new_category_permalink
					)
				) );
			}
		} );
	} else {
		$message = sprintf( __( '[%s] Error: Could not get permalink for category with ID %s.', 'redirection-monitor-category-redirects' ), REDIRECTION_MONITOR_CATEGORY_REDIRECTS_PLUGIN_NAME, $old_category_permalink );
		add_action( 'admin_notices', function () use ( $message ) {
			$class   = 'notice notice-error';
			sprintf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
		} );
		error_log( $message );
	}
}
