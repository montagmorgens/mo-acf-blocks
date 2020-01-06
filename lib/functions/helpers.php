<?php
/**
 * ACF Blocks Helper Functions
 *
 * @category   Plugin
 * @package    Mo\Acf
 * @author     Christoph Schüßler <schuessler@montagmorgens.com>
 * @license    https://www.gnu.org/licenses/gpl-2.0.txt GNU/GPLv2
 * @since      1.0.0
 */

namespace Mo\Acf;

trait Helpers {

	/**
	 * Prints debug data. If called from the WP Admin, output will be printed to
	 * admin_notices.
	 *
	 * @param mixed $var The variable to be inspected.
	 */
	public function debug( $var ) {
		ob_start();
		// phpcs:disable
		var_dump( $var );
		// phpcs:enable
		$result = ob_get_clean();

		if ( \is_admin() ) {
			\add_action(
				'admin_notices',
				function() use ( $result ) {
					if ( ! defined( 'WP_ENV' ) || WP_ENV !== 'development' ) {
						return;
					}
					echo '<div class="notice notice-info is-dismissible"><pre class="debug">';
					echo \esc_html( $result );
					echo '</pre></div>';
				}
			);

		} else {
			echo '<pre class="debug">';
			echo \esc_html( $result );
			echo '</pre>';
		}
	}

	/**
	 * Prints message to WP Admin.
	 *
	 * @param string       $message The message to print.
	 * @param string|false $title The message title.
	 * @param string|null  $type The message type (error, warning or success).
	 */
	public function admin_message( $message, $title = false, $type = 'info' ) {
		if ( \is_admin() ) {
			\add_action(
				'admin_notices',
				function() use ( $message, $title, $type ) {
					if ( ! is_string( $message ) || ! is_string( $type ) ) {
						return;
					}
					printf( '<div class="notice notice-%s">', esc_attr( $type ) );
					if ( is_string( $title ) ) {
						printf( '<p><strong>%s</strong></p>', esc_html( $title ) );
					}
					echo '<p>';
					echo \esc_html( $message );
					echo '</p></div>';
				}
			);
		}
	}

	/**
	 * Prints error message to WP Admin.
	 *
	 * @param string       $message The message to print.
	 * @param string|false $title The message title.
	 */
	public function admin_error_message( $message, $title = false ) {
		$this->admin_message( $message, $title, $type = 'error' );
	}

	/**
	 * Prints warning message to WP Admin.
	 *
	 * @param string       $message The message to print.
	 * @param string|false $title The message title.
	 */
	public function admin_warning_message( $message, $title = false ) {
		$this->admin_message( $message, $title, $type = 'warning' );
	}

	/**
	 * Prints success message to WP Admin.
	 *
	 * @param string       $message The message to print.
	 * @param string|false $title The message title.
	 */
	public function admin_success_message( $message, $title = false ) {
		$this->admin_message( $message, $title, $type = 'success' );
	}
}
