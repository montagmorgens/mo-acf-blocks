<?php
/**
 * ACF Blocks
 *
 * @category   Plugin
 * @package    Mo\Acf
 * @author     Christoph Schüßler <schuessler@montagmorgens.com>
 * @license    https://www.gnu.org/licenses/gpl-2.0.txt GNU/GPLv2
 * @since      1.0.0
 *
 * @wordpress-plugin
 * Plugin Name: MONTAGMORGENS ACF Blocks
 * Description: Dieses Plugin stellt eine YAML-basierte ACF-Block-API für MONTAGMORGENS-Themes zur Verfügung.
 * Version:     1.1.0
 * Author:      MONTAGMORGENS GmbH
 * Author URI:  https://www.montagmorgens.com/
 * License:     GNU General Public License v.2
 * Text Domain: mo-acf-blocks
 * GitHub Plugin URI: montagmorgens/mo-acf-blocks
 */

namespace Mo\Acf;

// Don't call this file directly.
defined( 'ABSPATH' ) || die();

// Define absolute path to plugin root.
define( 'Mo\Acf\PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

// Require composer autoloader.
require_once \Mo\Acf\PLUGIN_PATH . 'lib/vendor/autoload.php';

// Require helper functions.
require_once \Mo\Acf\PLUGIN_PATH . 'lib/functions/helpers.php';

// Autoload dependencies.
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

// Init plugin instance.
\add_action( 'plugins_loaded', [ '\Mo\Acf\Blocks', 'get_instance' ] );

/**
 * Plugin code.
 */
final class Blocks {

	use Helpers;

	const PLUGIN_VERSION = '1.1.0';

	/**
	 * The plugin singleton.
	 *
	 * @var Blocks Class instance.
	 */
	protected static $instance = null;

	/**
	 * Template directories.
	 *
	 * @var array An array of directories containing blocks.
	 */
	private $template_directories;

	/**
	 * Rendered blocks.
	 *
	 * @var array An array of blocks that have are rendered in the current context.
	 */
	private $rendered_blocks = [];

	/**
	 * Gets a singelton instance of our plugin.
	 *
	 * @return Blocks
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

		// Bail if dependency plugins are missing.
		if ( ! $this->check_dependencies() ) {
			return;
		}

		// Set template directory.
		$this->template_directories = apply_filters( 'mo_acf_blocks_directories', [ 'views/blocks' ] );

		// Add action and filter hooks.
		add_action( 'acf/init', [ $this, 'register_acf_blocks' ] );
		add_action( 'admin_menu', [ $this, 'add_block_page' ] );
		add_filter( 'block_categories', [ $this, 'register_acf_block_category' ], 1, 2 );
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
	 *
	 * @param array   $categories Array of block categories..
	 * @param WP_Post $post Post being loaded.
	 */
	public function register_acf_block_category( $categories, $post ) {
		return array_merge(
			$categories,
			[
				[
					'slug'  => 'theme',
					'title' => _x( 'Theme', 'Custom Block Category', 'mo-acf-blocks' ),
				],
			]
		);
	}

	/**
	 *  Register ACF blocks by YAML config files in 'views/blocks'.
	 *
	 * The YAML file follow the pattern:
	 * ***************************************************************************
	 * title: 'The Block Name'
	 * category: 'theme'
	 * mode: 'edit'
	 * align: 'full'
	 * attach_style: 'block-xyz'
	 * keywords: ['xyz']
	 * supports:
	 *   align: false
	 *   mode: true
	 * icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"></svg>'
	 * ***************************************************************************
	 * The file name (without the `.yml` extension) will be used as internal block name.
	 *
	 * A twig template with the same file name (but with `.twig` extension,
	 * obviously) will be automatically called by the render_callback of the
	 * ACF block.
	 */
	public function register_acf_blocks() {

		// Check if template_directories is an array.
		if ( ! is_array( $this->template_directories ) ) {
			$return = new \WP_Error( 'mo_acf_blocks', __( 'Der Filter mo_acf_blocks_directories muss ein Array zurückgeben.', 'mo-acf-blocks' ) );
			$this->admin_error_message( $return->get_error_message() );
			return;
		}

		// Search for block definitions in all valid directories.
		foreach ( $this->template_directories as $directory ) {
			if ( empty( \locate_template( $directory ) ) ) {
				return;
			}

			// Iterate over the directories provided and look for templates.
			$template_directory = new \DirectoryIterator( \locate_template( $directory ) );

			foreach ( $template_directory as $template ) {
				if ( ! $template->isDot() && ! $template->isDir() && $template->getExtension() === 'yml' ) {
					try {
						$block_config = Yaml::parseFile( $template->getPathname() );
						$block_style  = null;

						// Break if title is missing.
						if ( empty( $block_config['title'] ) ) {
							$this->admin_error_message(
								/* translators: %1$s: folder name, %2$s: block name */
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
						$block_config['render_callback'] = [ $this, 'render_acf_block' ];

						// Register the block.
						\acf_register_block_type( $block_config );

						// Warn if correspondent twig template is missing.
						if ( ! file_exists( $template->getPath() . '/' . $template->getBasename( '.yml' ) . '.twig' ) ) {
							$this->admin_warning_message(
								/* translators: %1$s: folder name, %2$s: block name */
								sprintf( __( 'Das Block-Template <strong>%1$s/%2$s.twig</strong> fehlt.', 'mo-acf-blocks' ), $directory, $template->getBasename( '.yml' ) ),
								__( 'Theme: Block template', 'mo-acf-blocks' )
							);
						}
					} catch ( ParseException $exception ) {
						// Show error message for YAML errors.
						$this->admin_error_message(
							/* translators: %1$s: folder name, %2$s: block name */
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

		$data = \get_fields(); // Get ACF field data.
		$name = substr( $block['name'], 4 ); // Strip 'acf/' from block name.

		// Store values in Timber context.
		$context               = \Timber::context();
		$context['block']      = $block;
		$context['name']       = $name;
		$context['is_preview'] = $is_preview;

		// Apply filter to all blocks.
		$data = apply_filters( 'mo_acf_blocks/render_acf_block', $data, $block, $name );

		// Apply filter to specific block.
		$data = apply_filters( 'mo_acf_blocks/render_acf_block/' . $name, $data, $block );

		$context['data'] = $data;

		// Make sure stylesheet is only attached once per block.
		if ( array_key_exists( 'attach_style', $block ) && empty( $this->rendered_blocks[ $block['name'] ] ) ) {
			$context['stylesheet']                   = (string) $block['attach_style'];
			$this->rendered_blocks[ $block['name'] ] = true;
		}

		// Render the block.
		\Timber::render( 'blocks/' . $context['name'] . '.twig', $context );
	}

	/**
	 * Add dahsboard page that displays all active ACF blocks.
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
			[ $this, 'list_block_page' ]
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

		$data['title']       = esc_html( get_admin_page_title() );
		$data['block_types'] = \WP_Block_Type_Registry::get_instance()->get_all_registered();

		sort( $data['block_types'] );

		// phpcs:disable
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
		// phpcs:enable
	}
}
