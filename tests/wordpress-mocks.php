<?php
/**
 * WordPress function mocks for unit testing
 *
 * This file provides mock implementations of WordPress functions
 * to allow unit testing without a full WordPress installation.
 *
 * Following WordPress testing best practices:
 * - Functions are only defined if they don't already exist
 * - Mocks are kept simple and predictable
 * - Global state is avoided where possible
 *
 * @package MailChronicle\Tests
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

/**
 * Mock current_time function
 */
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type, $gmt = 0 ) {
		switch ( $type ) {
			case 'mysql':
				return gmdate( 'Y-m-d H:i:s' );
			case 'timestamp':
				return time();
			default:
				return time();
		}
	}
}

/**
 * Mock get_option function
 */
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		global $_mock_options;
		return $_mock_options[ $option ] ?? $default;
	}
}

/**
 * Mock update_option function
 */
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value, $autoload = null ) {
		global $_mock_options;
		$_mock_options[ $option ] = $value;
		return true;
	}
}

/**
 * Mock delete_option function
 */
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $option ) {
		global $_mock_options;
		unset( $_mock_options[ $option ] );
		return true;
	}
}

/**
 * Mock sanitize_text_field function
 */
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( $str ) );
	}
}

/**
 * Mock sanitize_email function
 */
if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $email ) {
		return filter_var( $email, FILTER_SANITIZE_EMAIL );
	}
}

/**
 * Mock wp_send_json function
 */
if ( ! function_exists( 'wp_send_json' ) ) {
	function wp_send_json( $response, $status_code = null ) {
		global $_mock_json_response;
		$_mock_json_response = $response;
		if ( $status_code ) {
			http_response_code( $status_code );
		}
		echo json_encode( $response );
		exit;
	}
}

/**
 * Mock wp_send_json_success function
 */
if ( ! function_exists( 'wp_send_json_success' ) ) {
	function wp_send_json_success( $data = null, $status_code = null ) {
		$response = array( 'success' => true );
		if ( isset( $data ) ) {
			$response['data'] = $data;
		}
		wp_send_json( $response, $status_code );
	}
}

/**
 * Mock wp_send_json_error function
 */
if ( ! function_exists( 'wp_send_json_error' ) ) {
	function wp_send_json_error( $data = null, $status_code = null ) {
		$response = array( 'success' => false );
		if ( isset( $data ) ) {
			$response['data'] = $data;
		}
		wp_send_json( $response, $status_code );
	}
}

/**
 * Mock esc_html function
 */
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

/**
 * Mock esc_attr function
 */
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

/**
 * Mock esc_url function
 */
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return filter_var( $url, FILTER_SANITIZE_URL );
	}
}

/**
 * Mock wp_kses_post function
 */
if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $data ) {
		return strip_tags( $data, '<p><a><strong><em><ul><ol><li><br><h1><h2><h3><h4><h5><h6>' );
	}
}

/**
 * Mock wp_strip_all_tags function
 */
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $string, $remove_breaks = false ) {
		$string = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $string );
		$string = strip_tags( $string );

		if ( $remove_breaks ) {
			$string = preg_replace( '/[\r\n\t ]+/', ' ', $string );
		}

		return trim( $string );
	}
}

/**
 * Mock wp_json_encode function
 */
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

/**
 * Mock add_action function
 */
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
		global $_mock_actions;
		if ( ! isset( $_mock_actions[ $tag ] ) ) {
			$_mock_actions[ $tag ] = array();
		}
		$_mock_actions[ $tag ][] = array(
			'function' => $function_to_add,
			'priority' => $priority,
			'args'     => $accepted_args,
		);
		return true;
	}
}

/**
 * Mock add_filter function
 */
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
		return add_action( $tag, $function_to_add, $priority, $accepted_args );
	}
}

/**
 * Mock do_action function
 */
if ( ! function_exists( 'do_action' ) ) {
	function do_action( $tag, ...$args ) {
		global $_mock_actions;
		if ( isset( $_mock_actions[ $tag ] ) ) {
			foreach ( $_mock_actions[ $tag ] as $action ) {
				call_user_func_array( $action['function'], $args );
			}
		}
	}
}

/**
 * Mock apply_filters function
 */
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value, ...$args ) {
		global $_mock_actions;
		if ( isset( $_mock_actions[ $tag ] ) ) {
			foreach ( $_mock_actions[ $tag ] as $filter ) {
				$value = call_user_func_array( $filter['function'], array_merge( array( $value ), $args ) );
			}
		}
		return $value;
	}
}

/**
 * Mock register_activation_hook function
 */
if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( $file, $function ) {
		// No-op for testing
		return true;
	}
}

/**
 * Mock register_deactivation_hook function
 */
if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook( $file, $function ) {
		// No-op for testing
		return true;
	}
}

/**
 * Mock wp_verify_nonce function
 */
if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action = -1 ) {
		// For testing, accept any nonce
		return 1;
	}
}

/**
 * Mock wp_create_nonce function
 */
if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) {
		return 'test_nonce_' . md5( $action );
	}
}

/**
 * Mock is_admin function
 */
if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return false;
	}
}

/**
 * Mock wp_die function
 */
if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message = '', $title = '', $args = array() ) {
		throw new Exception( $message );
	}
}

/**
 * Mock __ (translation) function
 */
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

/**
 * Mock _e (echo translation) function
 */
if ( ! function_exists( '_e' ) ) {
	function _e( $text, $domain = 'default' ) {
		echo $text;
	}
}

/**
 * Mock esc_html__ function
 */
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return esc_html( $text );
	}
}

/**
 * Mock esc_html_e function
 */
if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = 'default' ) {
		echo esc_html( $text );
	}
}

/**
 * Mock wp_parse_args function
 */
if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		if ( is_object( $args ) ) {
			$parsed_args = get_object_vars( $args );
		} elseif ( is_array( $args ) ) {
			$parsed_args =& $args;
		} else {
			parse_str( $args, $parsed_args );
		}

		if ( is_array( $defaults ) && $defaults ) {
			return array_merge( $defaults, $parsed_args );
		}
		return $parsed_args;
	}
}

/**
 * Mock absint function
 */
if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

/**
 * Mock current_user_can function
 */
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability, ...$args ) {
		global $_mock_current_user_can;
		if ( isset( $_mock_current_user_can ) ) {
			return $_mock_current_user_can;
		}
		// For testing, return true by default
		return true;
	}
}

/**
 * Mock register_rest_route function
 */
if ( ! function_exists( 'register_rest_route' ) ) {
	function register_rest_route( $namespace, $route, $args = array(), $override = false ) {
		global $_mock_rest_routes;
		if ( ! isset( $_mock_rest_routes ) ) {
			$_mock_rest_routes = array();
		}
		$_mock_rest_routes[] = array(
			'namespace' => $namespace,
			'route'     => $route,
			'args'      => $args,
		);
		return true;
	}
}

/**
 * Mock rest_ensure_response function
 */
if ( ! function_exists( 'rest_ensure_response' ) ) {
	function rest_ensure_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( $response instanceof WP_REST_Response ) {
			return $response;
		}

		return new WP_REST_Response( $response );
	}
}

/**
 * Mock is_wp_error function
 */
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return ( $thing instanceof WP_Error );
	}
}

/**
 * Define WordPress database constants
 */
if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! defined( 'ARRAY_N' ) ) {
	define( 'ARRAY_N', 'ARRAY_N' );
}

if ( ! defined( 'OBJECT' ) ) {
	define( 'OBJECT', 'OBJECT' );
}

if ( ! defined( 'OBJECT_K' ) ) {
	define( 'OBJECT_K', 'OBJECT_K' );
}

/**
 * Mock WP_REST_Controller class
 */
if ( ! class_exists( 'WP_REST_Controller' ) ) {
	/**
	 * Base REST Controller class
	 */
	class WP_REST_Controller {
		/**
		 * The namespace for this controller's route.
		 *
		 * @var string
		 */
		protected $namespace = '';

		/**
		 * The base of this controller's route.
		 *
		 * @var string
		 */
		protected $rest_base = '';

		/**
		 * Registers the routes for the objects of the controller.
		 */
		public function register_routes() {
			// Override in child class
		}

		/**
		 * Checks if a given request has access to get items.
		 *
		 * @param WP_REST_Request $request Full data about the request.
		 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
		 */
		public function get_items_permissions_check( $request ) {
			return true;
		}

		/**
		 * Retrieves the query params for the collections.
		 *
		 * @return array Collection parameters.
		 */
		public function get_collection_params() {
			return array();
		}
	}
}

/**
 * Mock WP_REST_Request class
 */
if ( ! class_exists( 'WP_REST_Request' ) ) {
	/**
	 * REST Request class
	 */
	class WP_REST_Request {
		/**
		 * Request parameters
		 *
		 * @var array
		 */
		protected $params = array();

		/**
		 * Constructor
		 *
		 * @param string $method HTTP method.
		 * @param string $route  Route.
		 */
		public function __construct( $method = 'GET', $route = '' ) {
			$this->params = array();
		}

		/**
		 * Get parameter
		 *
		 * @param string $key     Parameter key.
		 * @param mixed  $default Default value.
		 * @return mixed
		 */
		public function get_param( $key, $default = null ) {
			return $this->params[ $key ] ?? $default;
		}

		/**
		 * Set parameter
		 *
		 * @param string $key   Parameter key.
		 * @param mixed  $value Parameter value.
		 */
		public function set_param( $key, $value ) {
			$this->params[ $key ] = $value;
		}

		/**
		 * Get all parameters
		 *
		 * @return array
		 */
		public function get_params() {
			return $this->params;
		}
	}
}

/**
 * Mock WP_REST_Response class
 */
if ( ! class_exists( 'WP_REST_Response' ) ) {
	/**
	 * REST Response class
	 */
	class WP_REST_Response {
		/**
		 * Response data
		 *
		 * @var mixed
		 */
		protected $data;

		/**
		 * Response status
		 *
		 * @var int
		 */
		protected $status;

		/**
		 * Constructor
		 *
		 * @param mixed $data   Response data.
		 * @param int   $status HTTP status code.
		 */
		public function __construct( $data = null, $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}

		/**
		 * Get data
		 *
		 * @return mixed
		 */
		public function get_data() {
			return $this->data;
		}

		/**
		 * Get status
		 *
		 * @return int
		 */
		public function get_status() {
			return $this->status;
		}
	}
}

/**
 * Mock WP_REST_Server class
 */
if ( ! class_exists( 'WP_REST_Server' ) ) {
	/**
	 * REST Server class
	 */
	class WP_REST_Server {
		const READABLE   = 'GET';
		const CREATABLE  = 'POST';
		const EDITABLE   = 'POST, PUT, PATCH';
		const DELETABLE  = 'DELETE';
		const ALLMETHODS = 'GET, POST, PUT, PATCH, DELETE';
	}
}

/**
 * Mock WP_Error class
 */
if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Error class
	 */
	class WP_Error {
		/**
		 * Error code
		 *
		 * @var string
		 */
		protected $code;

		/**
		 * Error message
		 *
		 * @var string
		 */
		protected $message;

		/**
		 * Constructor
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 */
		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		/**
		 * Get error code
		 *
		 * @return string
		 */
		public function get_error_code() {
			return $this->code;
		}

		/**
		 * Get error message
		 *
		 * @return string
		 */
		public function get_error_message() {
			return $this->message;
		}
	}
}

/**
 * Mock wp_clear_scheduled_hook function
 */
if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	function wp_clear_scheduled_hook( $hook, $args = array() ) {
		return 0;
	}
}

/**
 * Mock wp_schedule_event function
 */
if ( ! function_exists( 'wp_schedule_event' ) ) {
	function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array() ) {
		return true;
	}
}

/**
 * Mock wp_next_scheduled function
 */
if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( $hook, $args = array() ) {
		return false;
	}
}

/**
 * Mock get_bloginfo function
 */
if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = '', $filter = 'raw' ) {
		global $_mock_bloginfo;
		if ( isset( $_mock_bloginfo[ $show ] ) ) {
			return $_mock_bloginfo[ $show ];
		}
		switch ( $show ) {
			case 'admin_email':
				return 'admin@example.com';
			case 'name':
				return 'Test Site';
			case 'url':
			case 'wpurl':
				return 'http://example.com';
			default:
				return '';
		}
	}
}

/**
 * Mock wp_parse_url function
 */
if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		if ( $component === -1 ) {
			return parse_url( $url );
		}
		return parse_url( $url, $component );
	}
}

// Initialize global mock storage
global $_mock_options, $_mock_actions, $_mock_json_response, $_mock_rest_routes, $_mock_current_user_can;
$_mock_options          = array();
$_mock_actions          = array();
$_mock_json_response    = null;
$_mock_rest_routes      = array();
$_mock_current_user_can = null;
$_mock_bloginfo         = array();
