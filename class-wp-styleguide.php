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
	}

	/**
	 * Register rewrite rules to listen for styleguide requests
	 */
	public function action_init() {
		add_rewrite_rule( '^styleguide/([^\.]+?)/?$', 'index.php?styleguide=1&styleguide_path=$matches[1]', 'top' );
		add_rewrite_rule( '^styleguide/?', 'index.php?styleguide=1', 'top' );
	}

	/**
	 * Redirect styleguide requests that don't have a trailing slash
	 */
	public function action_template_redirect() {
		if ( ! static::is_styleguide_request() ) {
			// Not a styleguide request
			return;
		}

		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$request = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$parts   = explode( '?', $request );
		$request = $parts[0];
		if ( '/' === substr( $request, -1 ) ) {
			return;
		}
		$redirect = home_url( $wp->request );
		// If the request had a query string, add it back in
		if ( ! empty( $parts[1] ) ) {
			$redirect = add_query_arg( $parts[1], $redirect );
		}
		wp_safe_redirect( $redirect, 301 );
		die();
	}

	/**
	 * Make WordPress aware of our styleguide query vars
	 *
	 * @param  array  $vars The query vars
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
			$path = 'index';
			if ( ! empty( get_query_var( 'styleguide_path' ) ) ) {
				$path = get_query_var( 'styleguide_path' );
			}
			$styleguide_template = locate_template(
				array( 'styleguide/' . $path . '.php' )
			);
			if ( ! empty( $styleguide_template ) ) {
				return $styleguide_template;
			} else {
				wp_die( 'Styleguide page not found! Looking for <code>/styleguide/' . esc_html( $path ) . '.php</code> in the theme directory.' );
			}
			die();
		}
		return $template;
	}
	
	/**
	 * Set the <title> of styleguide pages by prettifying the styleguide path being requested
	 *
	 * @param string $title The page title to be modified
	 */
	public function filter_wp_title( $title = '' ) {
		$path = get_query_var( 'styleguide_path' );
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
	 * @param  array  $files List of files to parse
	 * @return array         Output keyed to the sass variable containing the hex color value and any comments
	 */
	public static function get_sass_colors( $files = array() ) {
		if ( ! is_array( $files ) ) {
			$files = array( $files );
		}

		$output = [];
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

				$comment = '';
				$comment_parts = explode( '//', $line );
				if ( ! empty( $comment_parts[1] ) ) {
					$comment = trim( $comment_parts[1] );
				}

				$output[ $variable ] = [
					'hex'     => $value,
					'comment' => $comment,
				];
			}
			fclose( $handle );
		endforeach;
		return $output;
	}
}

WP_Styleguide::get_instance();
