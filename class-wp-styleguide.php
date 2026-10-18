<?php

class WP_Styleguide {

	/**
	 * Get an instance of this class
	 */
	public static function get_instance() {
		static $instance = null;
		if ( null === $instance ) {
			$instance = new static();
			$instance->setup_actions();
			$instance->setup_filters();
		}
		return $instance;
	}

	/**
	 * Hook into various WordPress actions
	 */
	public function setup_actions() {
		add_action( 'init', array( $this, 'action_init' ), 0 );
		add_action( 'template_redirect', array( $this, 'action_template_redirect' ) );
	}

	/**
	 * Hook into various WordPress filterss
	 */
	public function setup_filters() {
		add_filter( 'query_vars', array( $this, 'filter_query_vars' ) );
		add_filter( 'template_include', array( $this, 'filter_template_include' ) );
		add_filter( 'wp_title', array( $this, 'filter_wp_title' ), 10 );
		add_filter( 'rank_math/frontend/title', array( $this, 'filter_wp_title' ) );
		add_filter( 'posts_request', array( $this, 'filter_posts_request' ), 10, 2 );
	}

	/**
	 * Register rewrite rules to listen for styleguide requests
	 */
	public function action_init() {
		add_rewrite_rule( '^styleguide/([^\.]+?)/?$', 'index.php?styleguide=1&styleguide_path=$matches[1]', 'top' );
		add_rewrite_rule( '^styleguide/?$', 'index.php?styleguide=1', 'top' );
	}

	/**
	 * Redirect styleguide requests that don't have a trailing slash
	 */
	public function action_template_redirect() {
		global $wp;

		if ( ! static::is_styleguide_request() ) {
			// Not a styleguide request
			return;
		}

		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only parsed, never output. The redirect is built from home_url().
		$request_uri  = wp_unslash( $_SERVER['REQUEST_URI'] );
		$request_path = wp_parse_url( $request_uri, PHP_URL_PATH );
		if ( empty( $request_path ) || '/' === substr( $request_path, -1 ) ) {
			return;
		}

		// Always redirect to a path on this site with a trailing slash
		$redirect = trailingslashit( home_url( $wp->request ) );

		// If the request had a query string, add it back in
		$query_string = wp_parse_url( $request_uri, PHP_URL_QUERY );
		if ( ! empty( $query_string ) ) {
			$query_args = array();
			wp_parse_str( $query_string, $query_args );
			$redirect = add_query_arg( urlencode_deep( $query_args ), $redirect );
		}
		wp_safe_redirect( $redirect, 301 );
		die();
	}

	/**
	 * Make WordPress aware of our styleguide query vars
	 *
	 * @param  array $vars The query vars
	 * @return array        Modified query vars
	 */
	public function filter_query_vars( $vars = array() ) {
		$vars[] = 'styleguide';
		$vars[] = 'styleguide_path';
		return $vars;
	}

	/**
	 * Include styleguide template files to serve the request
	 *
	 * @param  string $template The template to modify
	 * @return string           The modified template
	 */
	public function filter_template_include( $template = '' ) {
		if ( static::is_styleguide_request() ) {
			$raw_path = get_query_var( 'styleguide_path' );
			$path     = 'index';
			if ( ! empty( $raw_path ) ) {
				$path = static::sanitize_styleguide_path( $raw_path );
			}
			$styleguide_template = false;
			if ( ! empty( $path ) ) {
				$styleguide_template = static::locate_styleguide_template( $path );
			}
			if ( ! empty( $styleguide_template ) ) {
				return $styleguide_template;
			}
			$display_path = is_string( $raw_path ) && '' !== $raw_path ? $raw_path : 'index';
			wp_die(
				'Styleguide page not found! Looking for <code>/styleguide/' . esc_html( $display_path ) . '.php</code> in the theme directory.',
				'Styleguide page not found',
				array( 'response' => 404 )
			);
		}
		return $template;
	}

	/**
	 * Validate and normalize a requested styleguide path
	 *
	 * Only letters, numbers, hyphens, underscores, and forward slashes are allowed.
	 * Paths with a leading slash, empty segments, or `.`/`..` segments are rejected.
	 *
	 * @param  string $path The requested styleguide path
	 * @return string|false  The normalized path or false if the path is not allowed
	 */
	public static function sanitize_styleguide_path( $path = '' ) {
		if ( ! is_string( $path ) ) {
			return false;
		}

		// Trailing slashes are fine (/styleguide/colors/), leading slashes are not
		$path = rtrim( $path, '/' );
		if ( '' === $path || '/' === substr( $path, 0, 1 ) ) {
			return false;
		}

		if ( ! preg_match( '/^[a-z0-9_\-\/]+$/i', $path ) ) {
			return false;
		}

		foreach ( explode( '/', $path ) as $segment ) {
			if ( '' === $segment || '.' === $segment || '..' === $segment ) {
				return false;
			}
		}

		return $path;
	}

	/**
	 * Locate a styleguide template and make sure it lives inside a theme's styleguide directory
	 *
	 * @param  string $path A path already validated by sanitize_styleguide_path()
	 * @return string|false  Absolute path to the template file or false if not found
	 */
	public static function locate_styleguide_template( $path = '' ) {
		$template = locate_template( array( 'styleguide/' . $path . '.php' ) );
		if ( empty( $template ) ) {
			return false;
		}

		$real_template = realpath( $template );
		if ( false === $real_template ) {
			return false;
		}
		$real_template = wp_normalize_path( $real_template );

		$theme_dirs = array_unique(
			array(
				get_stylesheet_directory(),
				get_template_directory(),
			)
		);
		foreach ( $theme_dirs as $theme_dir ) {
			$styleguide_dir = realpath( trailingslashit( $theme_dir ) . 'styleguide' );
			if ( false === $styleguide_dir ) {
				continue;
			}
			$styleguide_dir = trailingslashit( wp_normalize_path( $styleguide_dir ) );
			if ( 0 === strpos( $real_template, $styleguide_dir ) ) {
				return $template;
			}
		}

		return false;
	}
	
	/**
	 * Set the <title> of styleguide pages by prettifying the styleguide path being requested
	 *
	 * @param string $title The page title to be modified
	 */
	public function filter_wp_title( $title = '' ) {
		$path = '';
		if ( static::is_styleguide_request() ) {
			$path = (string) static::sanitize_styleguide_path( get_query_var( 'styleguide_path' ) );
		}
		if ( ! empty( $path ) ) {
			$title  = $path;
			$title  = str_replace( '-', ' ', $title );
			$title  = ucwords( $title );
			$title .= ' - ' . get_bloginfo( 'name' ) . ' Styleguide';
		}
		$title = apply_filters( 'wp_styleguide/wp_title', $title, $path );
		return $title;
	}

	/**
	 * Prevent the main query from running on styleguide requests
	 *
	 * @param  string   $request The complete SQL query
	 * @param  WP_Query $query The WP_Query instance (passed by reference)
	 */
	public function filter_posts_request( $request = '', $query = array() ) {
		if( static::is_styleguide_request() && $query->is_main_query() && ! $query->is_admin ){
			return false;
		}
		return $request;
	}

	/**
	 * Check if the current request is to a styleguide page or not
	 *
	 * @return boolean
	 */
	public static function is_styleguide_request() {
		return ! empty( get_query_var( 'styleguide' ) );
	}

	/**
	 * Parse a list of given files looking for 3 or 6 digit hex codes
	 *
	 * @param  array $files List of files to parse
	 * @return array         Output keyed to the sass variable containing the hex color value and any comments
	 */
	public static function get_sass_colors( $files = array() ) {
		if ( ! is_array( $files ) ) {
			$files = array( $files );
		}

		$output = array();
		foreach ( $files as $file ) :
			$handle = fopen( $file, 'r' );
			if ( ! $handle ) {
				return $output;
			}
			while ( $line = fgets( $handle ) ) {
				$line = trim( $line );
				if ( empty( $line ) ) {
					continue;
				}

				// Match 3 or 6 character hex code patterns
				preg_match( '/#([a-f0-9]{3}){1,2}\b/i', $line, $match );
				if ( empty( $match[0] ) ) {
					continue;
				}
				$value = $match[0];
				$value = str_replace( '#', '', $value );
				if ( ! $value ) {
					continue;
				}

				// Match Sass variable names:
				// - Starts with $
				// - Contains a-z, A-Z, 0-9
				// - Contains hyphen or underscore
				// - Has a colon in it
				preg_match( '/(\$[0-9a-z_\-]+)(.*):/i', $line, $match );
				if ( empty( $match[1] ) ) {
					continue;
				}
				$variable = $match[1];

				$comment       = '';
				$comment_parts = explode( '//', $line );
				if ( ! empty( $comment_parts[1] ) ) {
					$comment = trim( $comment_parts[1] );
				}

				$output[ $variable ] = array(
					'hex'     => $value,
					'comment' => $comment,
				);
			}
			fclose( $handle );
		endforeach;
		return $output;
	}
}

WP_Styleguide::get_instance();
