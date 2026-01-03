<?php
/**
 * Abstract OpenAPI Documentation Endpoint.
 *
 * Provides base functionality for generating OpenAPI documentation
 * for existing WordPress REST API endpoints.
 *
 * @since 4.25.2
 *
 * @package LearnDash\Core
 */

namespace LearnDash\Core\Modules\REST\Documentation_Migration\OpenAPI\Contracts;

use LearnDash\Core\Modules\REST\V1\Contracts\Endpoint as EndpointV1;
use LearnDash\Core\Utilities\Cast;
use stdClass;

/**
 * Abstract OpenAPI Documentation Endpoint.
 *
 * @since 4.25.2
 */
abstract class Endpoint extends EndpointV1 {
	/**
	 * Whether the endpoint is experimental.
	 * All documentation endpoints are not experimental.
	 *
	 * @since 4.25.2
	 *
	 * @var bool
	 */
	protected bool $experimental = false;

	/**
	 * Prevents registering routes for this endpoint.
	 *
	 * @since 4.25.2
	 *
	 * @return void
	 */
	public function register_routes(): void {
		/**
		 * Intentionally left blank.
		 * This class should be used only for existing endpoints that do not need registering.
		 */
	}

	/**
	 * Returns the request schema for this endpoint.
	 *
	 * @since 4.25.2
	 *
	 * @param string $path   The path of the route.
	 * @param string $method The HTTP method.
	 *
	 * @return array{type:string, properties:array<string, array<string, mixed>>|object, required?:string[]}
	 */
	public function get_request_schema( string $path, string $method ): array {
		$request_schema = $this->convert_endpoint_args_to_schema(
			array_filter(
				$this->get_route_args( $path, $method ),
				function ( $config ) {
					// Request Body parameters are handled by build_openapi_request_body().
					return $config['in'] !== 'body';
				}
			)
		);

		$request_schema['properties'] = $this->sanitize_property_config( $request_schema['properties'] );

		return $request_schema;
	}

	/**
	 * Builds the OpenAPI request body schema.
	 *
	 * @since 4.25.2
	 *
	 * @param string $path   The path of the route.
	 * @param string $method The HTTP method.
	 *
	 * @return array{type:string, properties:array<string, array<string, mixed>>|object, required?:string[]}
	 */
	protected function build_openapi_request_body( string $path, string $method ): array {
		if (
			! in_array(
				$method,
				[ 'POST', 'PUT', 'PATCH' ],
				true
			)
		) {
			return $this->convert_endpoint_args_to_schema( [] );
		}

		$required  = [];
		$body_args = array_filter(
			$this->get_route_args( $path, $method ),
			function ( $config ) {
				return $config['in'] === 'body';
			}
		);

		foreach ( $body_args as $key => $config ) {
			/**
			 * We do not need the 'in' key for the request body,
			 * it just helps us to determine which ones are request body.
			 */
			unset( $config['in'] );

			if (
				isset( $config['required'] )
				&& $config['required'] === true
			) {
				$required[] = $key;
			}

			$body_args[ $key ] = $config;
		}

		$request_body = $this->convert_endpoint_args_to_schema( $body_args );

		$request_body['properties'] = $this->sanitize_property_config( $request_body['properties'] );

		if ( ! empty( $required ) ) {
			$request_body['required'] = $required;
		}

		return $request_body;
	}

	/**
	 * Returns the route configuration for a specific route.
	 *
	 * @since 4.25.2
	 *
	 * @param string $route The route to get the configuration for.
	 *
	 * @return array<string,mixed>[]
	 */
	protected function get_route_config( string $route ): array {
		// The WP REST API Server expects the namespace to not have a preceding or trailing slash.
		$namespace = trim( $this->get_namespace(), '/' );

		// Ensure the routes are loaded by the WP REST API Server.
		$routes = rest_get_server()->get_routes( $namespace );

		$route = ltrim( $route, '/' );

		/**
		 * If there are any OpenAPI-formatted dynamic parameters in the route,
		 * we need to find the corresponding static route via RegEx.
		 *
		 * Examples:
		 *
		 * ldlms/v2/sfwd-courses/{id}
		 * ldlms/v2/sfwd-courses/{id}/steps
		 * ldlms/v2/price-types/{slug}
		 */
		if (
			preg_match_all( '/\{([^\}]+)\}/', $route, $matches )
			&& ! empty( $matches[1] )
		) {
			// Escape the whole route for RegEx.
			$pattern = preg_quote( '/' . trim( trailingslashit( $namespace ) . $route, '/' ), '/' );

			foreach ( $matches[1] as $match ) {
				// Replace all dynamic parameters with RegEx that'll match what WordPress uses.
				$search  = preg_quote( '{' . $match . '}', '/' );
				$replace = '\(\?P\<' . $match . '\>\[[^\]]+\]\+\)';

				/**
				 * Replaces the OpenAPI-formatted dynamic parameter with a WordPress-formatted dynamic parameter.
				 *
				 * The type of the WordPress-formatted dynamic parameter is a RegEx that matches any number of characters within square brackets.
				 *
				 * This allows us to match patterns like this without knowing the exact type of the dynamic parameter.
				 *
				 * Examples:
				 *
				 * ldlms/v2/sfwd-courses/(?P<id>[\d]+)
				 * ldlms/v2/sfwd-courses/(?P<id>[\d]+)/steps
				 * ldlms/v2/price-types/(?P<slug>[\w-]+)
				 */
				$pattern = str_replace( $search, $replace, $pattern );
			}

			/**
			 * Must ensure that we match against the end of the route to avoid matching against multiple nested routes.
			 *
			 * If we do not check against the end of the route, preg_grep() could return multiple routes that match the pattern.
			 *
			 * For instance, if searching for:
			 *
			 * ldlms/v2/sfwd-courses/(?P<id>[\d]+)
			 *
			 * It could also return:
			 *
			 * ldlms/v2/sfwd-courses/(?P<id>[\d]+)/steps
			 * ldlms/v2/sfwd-courses/(?P<id>[\d]+)/users
			 * etc.
			 */
			$results = preg_grep( '/' . $pattern . '$/', array_keys( $routes ) );

			if ( ! empty( $results ) ) {
				$key = reset( $results );

				// Find the WordPress-formatted route and return the route config.
				return $routes[ $key ] ?? [];
			}
		}

		// However, when retrieving a specific route, we need to use a preceding slash.
		$search = '/' . trim( trailingslashit( $namespace ) . $route, '/' );

		return $routes[ $search ] ?? [];
	}

	/**
	 * Discovers all available routes for the endpoint.
	 *
	 * @since 4.25.2
	 *
	 * @param string   $base_endpoint The base endpoint (e.g., 'courses').
	 * @param string[] $types         The types of routes to discover. Defaults to all types. Accepts 'collection', 'singular', and 'nested'.
	 *
	 * @return array<string,array<string,string|callable>>
	 */
	protected function discover_routes( string $base_endpoint, array $types = [] ): array {
		$routes = [];

		// Get all registered routes for the namespace.
		$namespace  = trim( $this->get_namespace(), '/' );
		$all_routes = rest_get_server()->get_routes( $namespace );

		// Find all routes that start with the base endpoint.
		$found_routes = [];
		foreach ( $all_routes as $route_path => $route_config ) {
			// Check if this route is related to the base endpoint.
			if (
				$this->is_base_endpoint_route(
					$this->normalize_route_path( $route_path ),
					$this->normalize_route_path( $base_endpoint )
				)
			) {
				$found_routes[ $route_path ] = $route_config;
			}
		}

		// Process each discovered route.
		$routes = [];
		foreach ( $found_routes as $route_path => $route_config ) {
			$route_type = $this->determine_route_type( $route_path );

			if (
				! empty( $types )
				&& ! in_array(
					$route_type,
					$types,
					true
				)
			) {
				continue;
			}

			$route_key = $this->normalize_route_path( $route_path );
			$methods   = $this->extract_methods_from_route_config( $route_config );

			if ( ! empty( $methods ) ) {
				$route_routes = [];
				foreach ( $methods as $method ) {
					$route_routes[] = [
						'methods'     => $method,
						'summary'     => $this->get_method_summary( $method, $route_type ),
						'description' => $this->get_method_description( $method, $route_type ),
					];
				}

				$routes[ $route_key ] = $route_routes;
			}
		}

		/**
		 * The discovered routes.
		 *
		 * @var array<string,array<string,string|callable>> $routes
		 */
		return $routes;
	}

	/**
	 * Checks if a route is related to the base endpoint.
	 *
	 * @since 4.25.2
	 *
	 * @param string $route_path The full route path.
	 * @param string $base_endpoint The base endpoint.
	 *
	 * @return bool
	 */
	protected function is_base_endpoint_route( string $route_path, string $base_endpoint ): bool {
		// Remove the namespace prefix to get the relative path.
		$namespace     = trim( $this->get_namespace(), '/' );
		$relative_path = str_replace( '/' . $namespace . '/', '', $route_path );

		// Check if the route starts with the base endpoint.
		return strpos( $relative_path, $base_endpoint ) === 0;
	}

	/**
	 * Normalizes the route path for use as a key.
	 *
	 * @since 4.25.2
	 *
	 * @param string $route_path The full route path.
	 *
	 * @return string
	 */
	protected function normalize_route_path( string $route_path ): string {
		// Remove the namespace prefix to get the relative path.
		$namespace     = trim( $this->get_namespace(), '/' );
		$relative_path = str_replace( '/' . $namespace . '/', '', $route_path );

		// Convert patterns like (?P<id>[\d]+) to {id}.
		$relative_path = Cast::to_string( preg_replace( '/\(\?P<([^>]+)>[^)]+\)/', '{$1}', $relative_path ) );

		return $relative_path;
	}

	/**
	 * Extracts HTTP methods from route configuration.
	 *
	 * @since 4.25.2
	 *
	 * @param array<string,mixed>[] $route_config The route configuration.
	 *
	 * @return array<string>
	 */
	protected function extract_methods_from_route_config( array $route_config ): array {
		$methods = [];

		foreach ( $route_config as $config ) {
			if ( ! isset( $config['methods'] ) ) {
				continue;
			}

			$route_methods = $config['methods'];
			if ( is_string( $route_methods ) ) {
				$route_methods = [ $route_methods ];
			}

			if ( is_array( $route_methods ) ) {
				foreach ( $route_methods as $method => $enabled ) {
					if ( $enabled === true && ! in_array( $method, $methods, true ) ) {
						$methods[] = $method;
					}
				}
			}
		}

		return $methods;
	}

	/**
	 * Determines the type of route (collection, singular, or nested).
	 *
	 * @since 4.25.2
	 *
	 * @param string $route_path The route path.
	 *
	 * @return string
	 */
	protected function determine_route_type( string $route_path ): string {
		// Remove the namespace prefix.
		$namespace     = trim( $this->get_namespace(), '/' );
		$relative_path = str_replace( '/' . $namespace . '/', '', $route_path );
		$base_endpoint = Cast::to_string( preg_replace( '/^([^\/]+).*?$/', '$1', $relative_path ) );

		// Check for singular routes.
		if (
			// Check for WordPress-formatted dynamic parameters. Example: /courses/(?P<id>[\d]+)/ .
			preg_match( '/^' . preg_quote( $base_endpoint, '/' ) . '\/\(\?P<[^>]+>\[[^\]]+\]\+\)\/?$/', $relative_path )
			// Check for OpenAPI-formatted dynamic parameters. Example: /courses/{id}/ .
			|| preg_match( '/^' . preg_quote( $base_endpoint, '/' ) . '\/\{[^\}]+\}\/?$/', $relative_path )
		) {
			return 'singular';
		}

		// Check for nested routes.
		if (
			// Check for WordPress-formatted dynamic parameters. Example: /courses/(?P<id>[\d]+)/steps .
			preg_match( '/^' . preg_quote( $base_endpoint, '/' ) . '\/\(\?P<[^>]+>\[[^\]]+\]\+\)\/[^\/]+/', $relative_path )
			// Check for OpenAPI-formatted dynamic parameters. Example: /courses/{id}/steps .
			|| preg_match( '/^' . preg_quote( $base_endpoint, '/' ) . '\/\{[^\}]+\}\/[^\/]+/', $relative_path )
		) {
			return 'nested';
		}

		// Default to collection.
		return 'collection';
	}

	/**
	 * Returns the route arguments for a specific route and method.
	 * These are returned in WordPress format with the addition of the 'in' key to differentiate between
	 * path, query, and body parameters.
	 *
	 * @since 4.25.2
	 *
	 * @param string $path The path of the route.
	 * @param string $method The HTTP method.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	protected function get_route_args( string $path, string $method ): array {
		$route_args = [];

		foreach ( $this->get_routes() as $route => $args ) {
			if (
				trim( $this->normalize_route_path( $path ), '/' ) !== trim( $this->normalize_route_path( $route ), '/' )
			) {
				continue;
			}

			$route_config = $this->get_route_config( $route );

			foreach ( $route_config as $config ) {
				if (
					! isset( $config['methods'] )
					|| ! is_array( $config['methods'] )
					|| ! in_array(
						$method,
						array_keys( $config['methods'] ),
						true
					)
					|| ! isset( $config['args'] )
				) {
					continue;
				}

				/**
				 * Route arguments configuration.
				 * WordPress combines the path and query/body parameters into a single array, so we will need to separate them.
				 *
				 * @var array<string,array<string,mixed>> $config_args
				 */
				$config_args = $config['args'];

				/**
				 * Matches both OpenAPI-formatted and WordPress-formatted dynamic parameters.
				 *
				 * Capture Group 1 holds OpenAPI-formatted dynamic parameters.
				 * Capture Group 2 holds WordPress-formatted dynamic parameters.
				 *
				 * Examples:
				 *
				 * ldlms/v2/sfwd-courses/{id}
				 * ldlms/v2/sfwd-courses/(?P<id>[\d]+)
				 */
				preg_match_all( '/(?:\{([^\}]+)\})|(?:\(\?P\<([^>]*)>\[[^\]]+\]\+\))/', $route, $matches );

				$path_parameters = array_filter( array_merge( $matches[1], $matches[2] ) );

				// Determine which parameters are set in the path.
				$path_config = array_filter(
					$config_args,
					function ( $key ) use ( $path_parameters ) {
						return in_array( $key, $path_parameters, true );
					},
					ARRAY_FILTER_USE_KEY
				);

				foreach ( $path_config as &$value ) {
					$value['in']       = 'path';
					$value['required'] = true; // Should always be true for path parameters.
				}

				// Determine which parameters are set in the query/body.
				$query_or_body_config = array_diff_key( $config_args, $path_config );

				$query_or_body_type = in_array(
					$method,
					[ 'POST', 'PUT', 'PATCH' ],
					true
				) ? 'body' : 'query';

				foreach ( $query_or_body_config as &$value ) {
					$value['in'] = $query_or_body_type;
				}

				/**
				 * The request schema for the endpoint.
				 *
				 * @var array<string,array<string,mixed>> $route_args
				 */
				$route_args = array_merge( $path_config, $query_or_body_config );
			}
		}

		return $route_args;
	}

	/**
	 * Sanitizes the property configuration from WordPress Route Arguments.
	 *
	 * @since 4.25.2
	 *
	 * @param array<string, array<string, mixed>>|object $endpoint_args The endpoint arguments.
	 *
	 * @return array<string, array<string, mixed>>|object
	 */
	protected function sanitize_property_config( $endpoint_args ) {
		if ( is_object( $endpoint_args ) ) {
			$endpoint_args = (array) $endpoint_args;
		}

		foreach ( $endpoint_args as &$config ) {
			/**
			 * We cannot force this via EndpointV1::convert_property_config() because it could end up being set
			 * in incorrect locations (such as Request Body).
			 *
			 * As EndpointV1 is more "manual" than Documentation Migration Endpoints, we will handle it here.
			 */
			if (
				isset( $config['in'] )
				&& $config['in'] === 'path'
			) {
				$config['required'] = true;
			}

			// WordPress returns invalid Types. This ensures we have a valid Type and only one of them.

			if ( isset( $config['type'] ) ) {
				if ( is_array( $config['type'] ) ) {
					if (
						in_array( 'array', $config['type'], true )
						&& isset( $config['items'] )
					) {
						$config['type'] = [ 'array' ];
					} else {
						$config['type'] = array_diff( $config['type'], [ 'array' ] );
					}

					if ( in_array( 'null', $config['type'], true ) ) {
						$config['type'] = array_diff( $config['type'], [ 'null' ] );
					}

					// Set type to first item in array.
					$config['type'] = $config['type'][ array_key_first( $config['type'] ) ];
				}

				if ( $config['type'] === 'float' ) {
					$config['type'] = 'number';
				} elseif (
					$config['type'] === 'text'
					|| $config['type'] === 'date'
				) {
					$config['type'] = 'string';
				}

				// Fixes an issue where the schema will validate, but some applications will not parse the example correctly.
				if (
					$config['type'] === 'array'
					&& isset( $config['example'] )
					&& ! is_array( $config['example'] )
				) {
					$config['example'] = [ $config['example'] ];
				}
			}

			// Recursively sanitize properties and additionalProperties.

			if (
				isset( $config['properties'] )
				&& is_array( $config['properties'] )
			) {
				foreach ( $config['properties'] as $key => $property ) {
					$config['properties'][ $key ] = $this->sanitize_property_config( $property );
				}

				if ( empty( $config['properties'] ) ) {
					$config['properties'] = new stdClass();
				}
			}

			if (
				isset( $config['additionalProperties'] )
				&& is_array( $config['additionalProperties'] )
			) {
				$config['additionalProperties'] = $this->sanitize_property_config( $config['additionalProperties'] );

				if ( empty( $config['additionalProperties'] ) ) {
					$config['additionalProperties'] = new stdClass();
				}
			}

			/**
			 * Handles a weird issue with enums in WordPress Schemas, while also ensuring that all items.enums
			 * get the same fix if needed.
			 */

			if ( isset( $config['schema'] ) ) {
				$config['schema'] = $this->sanitize_property_config( $config['schema'] );
			}

			if (
				isset( $config['items'] )
				&& is_array( $config['items'] )
				&& isset( $config['items']['enum'] )
				&& is_array( $config['items']['enum'] )
			) {
				$config['items']['enum'] = array_values( (array) $config['items']['enum'] );
			}
		}

		return $endpoint_args;
	}

	/**
	 * Returns the endpoint arguments.
	 *
	 * @since 4.25.2
	 *
	 * @return array<string,array<string,mixed>>
	 */
	protected function get_endpoint_args(): array {
		/**
		 * Intentionally left blank.
		 * This gets handled more directly in the build_openapi_request_body() method.
		 */
		return [];
	}

	/**
	 * Returns the summary for a specific HTTP method.
	 *
	 * @since 4.25.2
	 *
	 * @param string $method The HTTP method.
	 * @param string $route_type The route type ('collection', 'singular', or 'nested').
	 *
	 * @return string
	 */
	abstract protected function get_method_summary( string $method, string $route_type = 'collection' ): string;

	/**
	 * Returns the description for a specific HTTP method.
	 *
	 * @since 4.25.2
	 *
	 * @param string $method The HTTP method.
	 * @param string $route_type The route type ('collection', 'singular', or 'nested').
	 *
	 * @return string
	 */
	abstract protected function get_method_description( string $method, string $route_type = 'collection' ): string;
}
