<?php
/**
 * ACF Blocks
 *
 * @package     Acf_Blocks
 * @author      MONTAGMORGENS GmbH
 * @copyright   2019 MONTAGMORGENS GmbH
 *
 * @wordpress-plugin
 * Plugin Name: MONTAGMORGENS ACF Blocks
 * Description: Dieses Plugin stellt eine YAML-basierte ACF-Block-API für MONTAGMORGENS-Themes zur Verfügung.
 * Version:     1.0.0
 * Author:      MONTAGMORGENS GmbH
 * Author URI:  https://www.montagmorgens.com/
 * License:     GNU General Public License v.2
 * Text Domain: mo-acf-blocks
 */

namespace Mo\Acf;

// Don't call this file directly.
defined( 'ABSPATH' ) || die();

// Define absolute path to plugin root.
define( 'Mo\Acf\PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

// Require composer autoloader.
require_once( \Mo\Acf\PLUGIN_PATH . 'lib/vendor/autoload.php' );

// Require helper functions.
require_once( \Mo\Acf\PLUGIN_PATH . 'lib/functions/helpers.php' );

// Autoload dependencies.
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

// Init plugin instance.
\add_action( 'plugins_loaded', array( '\Mo\Acf\Blocks', 'get_instance' ) );

/**
 * Plugin code.
 *
 * @var object|null $instance The plugin singleton.
 */
class Blocks {

	use Helpers;

	const PLUGIN_VERSION = '1.0.0';
	protected static $instance = null;

	/**
	 * Gets a singelton instance of our plugin.
	 *
	 * @return Core_Functionality
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		if ( ! $this->check_dependencies() ) {
			return;
		}

		add_action( 'acf/init', array( $this, 'register_acf_blocks' ) );
		add_action( 'admin_menu', array( $this, 'add_block_page' ) );
		add_filter( 'block_categories', array( $this, 'register_acf_block_category' ), 1, 2 );
	}

	/**
	 *  Check if plugin dependencies have been loaded.
	 */
	public function check_dependencies() {

		// Check whether ACF > 5.8 is available.
		if ( ! function_exists( 'acf_register_block_type' ) ) {
			$return = new \WP_Error( 'mo_acf_blocks', __( 'Das Plugin MONTAGMORGENS ACF Blocks setzt Advanced Custom Fields > 5.8 voraus. Dieses Plugin konnte nicht gefunden werden.', 'mo-acf-blocks' ) );
			$this->admin_error_message( $return->get_error_message(), esc_html( __( 'FEHLER: Advanced Custom Fields ist nicht aktiv.', 'mo-acf-blocks' ) ) );
			return false;
		}

		// Check whether Core Functionality plugin is available.
		if ( ! class_exists( '\Mo\Core\Core_Functionality' ) ) {
			$return = new \WP_Error( 'mo_theme', __( 'Das MONTAGMORGENS Core-Functionality-Plugin fehlt oder ist inaktiv.', 'mo-acf-blocks' ) );
			$this->admin_error_message( $return->get_error_message(), esc_html( __( 'FEHLER: ACF-Blöcke können nicht initialisiert werden.', 'mo-acf-blocks' ) ) );
			return false;
		}

		return true;
	}

	/**
	 *  Register custom block category.
	 */
	public function register_acf_block_category( $categories, $post ) {
		return array_merge(
			$categories,
			[
				[
					'slug' => 'theme',
					'title' => _x( 'Theme', 'Custom Block Category', 'mo-acf-blocks' ),
				],
			]
		);
	}

	/**
	 *  Register ACF blocks by YAML config files in 'views/blocks'
	 */
	public function register_acf_blocks() {

		// An array of directories containing blocks.
		$directories = [ 'views/blocks' ];

		foreach ( $directories as $directory ) {
			if ( empty( \locate_template( $directory ) ) ) {
				return;
			}

			// Iterate over the directories provided and look for templates.
			$template_directory = new \DirectoryIterator( \locate_template( $directory ) );

			foreach ( $template_directory as $template ) {
				if ( ! $template->isDot() && ! $template->isDir() && $template->getExtension() === 'yml' ) {
					try {
						$block_config = Yaml::parseFile( $template->getPathname() );

						// Break if title is missing.
						if ( empty( $block_config['title'] ) ) {
							$this->admin_error_message(
								sprintf( __( 'In der Block-Konfiguration <strong>%1$s/%2$s</strong> fehlt der Titel (<code>title</code>).', 'mo-acf-blocks' ), $directory, $template->getBasename() ),
								__( 'Theme: Block-Konfiguration', 'mo-acf-blocks' )
							);
							break;
						}

						// If category is missing, use 'theme' as category.
						if ( empty( $block_config['category'] ) ) {
							$block_config['category'] = 'theme';
						}

						// Set 'name' to filename string without .yml extension.
						$block_config['name'] = $template->getBasename( '.yml' );

						// Add same render callback for all blocks.
						$block_config['render_callback'] = array( $this, 'render_acf_block' );

						// Register the block.
						\acf_register_block_type( $block_config );

						// Warn if correspondent twig template is missing.
						if ( ! file_exists( $template->getPath() . '/' . $template->getBasename( '.yml' ) . '.twig' ) ) {
							$this->admin_warning_message(
								sprintf( __( 'Das Block-Template <strong>%1$s/%2$s.twig</strong> fehlt.', 'mo-acf-blocks' ), $directory, $template->getBasename( '.yml' ) ),
								__( 'Theme: Block template', 'mo-acf-blocks' )
							);
						}
					} catch ( ParseException $exception ) {
						// Show error message for YAML errors.
						$this->admin_error_message(
							sprintf( __( 'Fehler beim Parsen von <strong>%1$s/%2$s</strong>:<br><code>%3$s</code>', 'mo-acf-blocks' ), $directory, $template->getBasename(), $exception->getMessage() ),
							__( 'Theme: Block-Konfiguration', 'mo-acf-blocks' )
						);
					}
				}
			}
		}
	}

	/**
	 *  This is the callback that displays the block.
	 *
	 * @param   array  $block      The block settings and attributes.
	 * @param   string $content    The block content (emtpy string).
	 * @param   bool   $is_preview True during AJAX preview.
	 */
	public function render_acf_block( $block, $content = '', $is_preview = false ) {

		// Store values in Timber context.
		$context               = \Timber::context();
		$context['block']      = $block;
		$context['name']       = substr( $block['name'], 4 ); // Strip 'acf/' from block name.
		$context['data']       = \get_fields();
		$context['is_preview'] = $is_preview;

		// Render the block.
		\Timber::render( 'blocks/' . $context['name'] . '.twig', $context );
	}

	/**
	 * Add dahsboard page that displays all acitve ACF blocks.
	 */
	public function add_block_page() {
		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		add_dashboard_page(
			__( 'Verfügbare Theme-Blocks', 'mo-acf-blocks' ),
			__( 'Theme Blocks', 'mo-acf-blocks' ),
			'manage_options',
			'mo-theme-blocks',
			array( $this, 'list_block_page' )
		);
	}

	/**
	 * Populate dashboard page.
	 */
	public function list_block_page() {
		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$data['title'] = esc_html( get_admin_page_title() );
		$data['block_types'] = \WP_Block_Type_Registry::get_instance()->get_all_registered();

		sort( $data['block_types'] );

		echo \Timber::compile_string(
			'
			<div class="wrap">
			<h1>{{ title }}</h1>
			{% if block_types is not empty %}
			<div>
			<h2>{{ __("Diese ACF-Blöcke stehen aktuell zur Verfügung:", "mo-acf-blocks") }}</h2>
			{% for block in block_types if block.name|slice(0,4) == "acf/" %}
			<ul>
			<li><code>{{ block.name|slice(4) }}</code></li>
			</ul>
			{% endfor %}
			{% endif %}
			</div>
			</div>
			',
			$data
		);
	}
}
