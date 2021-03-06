<?php
/**
 * Assets
 * Handles script and stylesheet loading.
 *
 * @package Sensei\Assets
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sensei Assets
 * Loading CSS and Javascript files.
 *
 * @package Core
 * @author  Automattic
 * @since   3.1.0
 */
class Sensei_Assets {

	/**
	 * The URL for the plugin.
	 *
	 * @var string
	 */
	protected $plugin_url;

	/**
	 * Plugin location.
	 *
	 * @var string
	 */
	protected $plugin_path;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	protected $version;

	/**
	 * Sensei_Assets constructor.
	 *
	 * @param string $plugin_url  The URL for the plugin.
	 * @param string $plugin_path Plugin location.
	 * @param string $version     Plugin version.
	 */
	public function __construct( $plugin_url, $plugin_path, $version ) {
		$this->plugin_url  = $plugin_url;
		$this->plugin_path = $plugin_path;
		$this->version     = $version;
	}

	/**
	 * Enqueue a script or stylesheet with wp_enqueue_script/wp_enqueue_style.
	 *
	 * @param string      $handle       Unique name of the asset.
	 * @param string      $filename     The filename.
	 * @param array       $dependencies Dependencies.
	 * @param bool|string $args         In footer flag (script) or media type (style).
	 */
	public function enqueue( $handle, $filename, $dependencies = [], $args = null ) {

		$config = $this->asset_config( $filename, $dependencies, $args );
		$this->call_wp( 'wp_enqueue', $handle, $config );

	}

	/**
	 * Register a script or stylesheet with wp_register_script/wp_register_style.
	 *
	 * @param string|null $handle       Unique name of the asset.
	 * @param string      $filename     The filename.
	 * @param array       $dependencies Dependencies.
	 * @param null        $args
	 */
	public function register( $handle, $filename, $dependencies = [], $args = null ) {
		$config = $this->asset_config( $filename, $dependencies, $args );

		$this->call_wp( 'wp_register', $handle, $config );
	}

	/**
	 * Enqueue a registered script.
	 *
	 * @param string $handle Unique name of the script.
	 */
	public function enqueue_script( $handle ) {
		wp_enqueue_script( $handle );
	}

	/**
	 * Enqueue a registered stylesheet.
	 *
	 * @param string $handle Unique name of the stylesheet.
	 */
	public function enqueue_style( $handle ) {
		wp_enqueue_style( $handle );
	}

	/**
	 * Call the wrapped WordPress core function with _type postfix
	 *
	 * @param string $action wp_enqueue or wp_register.
	 * @param string $handle Unique handle for the asset.
	 * @param array  $config Asset information.
	 */
	private function call_wp( $action, $handle, $config ) {
		call_user_func( $action . '_' . $config['type'], $handle, $config['url'], $config['dependencies'], $config['version'], $config['args'] );
	}

	/**
	 * Builds asset metadata for a given file.
	 * Loads dependencies and version hash tracked by the build process from [filename].asset.php
	 *
	 * @param string      $filename     The filename.
	 * @param array       $dependencies Dependencies.
	 * @param bool|string $args         Argument passed to wp_enqueue_script or wp_enqueue_style.
	 *
	 * @return array Asset information.
	 */
	public function asset_config( $filename, $dependencies = [], $args = null ) {

		$is_js             = preg_match( '/\.js$/', $filename );
		$basename          = preg_replace( '/\.\w+$/', '', $filename );
		$url               = $this->asset_url( $filename );
		$version           = $this->version;
		$asset_config_path = path_join( $this->plugin_path, 'assets/dist/' . $basename . '.asset.php' );

		if ( file_exists( $asset_config_path ) ) {
			$asset_config = require $asset_config_path;

			// Only add generated dependencies for scripts.
			if ( $is_js ) {
				$dependencies = array_unique( array_merge( $dependencies, $asset_config['dependencies'] ) );
			}
			$version = $asset_config['version'];
		}

		return [
			'url'          => $url,
			'dependencies' => $dependencies,
			'version'      => $version,
			'type'         => $is_js ? 'script' : 'style',
			'args'         => null !== $args ? $args : ( $is_js ? false : 'all' ), // defaults for wp_enqueue_script or wp_enqueue_style.
		];
	}

	/**
	 * Construct public url for the file.
	 *
	 * @param string $filename The filename.
	 *
	 * @return string Public url for the file.
	 */
	private function asset_url( $filename ) {
		return rtrim( $this->plugin_url, '/' ) . '/assets/dist/' . $filename;
	}

	/**
	 * Preload the given REST routes and pass data to Javascript.
	 *
	 * @param string[] $rest_routes REST routes to preload.
	 */
	public function preload_data( $rest_routes ) {
		$preload_data = array_reduce(
			$rest_routes,
			'rest_preload_api_request',
			[]
		);
		wp_add_inline_script(
			'wp-api-fetch',
			sprintf( 'wp.apiFetch.use( wp.apiFetch.createPreloadingMiddleware( %s ) );', wp_json_encode( $preload_data ) ),
			'after'
		);

	}
}
