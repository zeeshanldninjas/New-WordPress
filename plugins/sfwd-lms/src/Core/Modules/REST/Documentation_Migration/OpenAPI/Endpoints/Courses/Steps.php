<?php
/**
 * Course Steps OpenAPI Documentation.
 *
 * Provides OpenAPI specification for courses endpoints.
 * Currently based on V2 REST API: https://developers.learndash.com/rest-api/v2/v2-courses/.
 *
 * @since 4.25.2
 *
 * @package LearnDash\Core
 */

namespace LearnDash\Core\Modules\REST\Documentation_Migration\OpenAPI\Endpoints\Courses;

use LearnDash_Settings_Section;
use LearnDash\Core\Modules\REST\Documentation_Migration\OpenAPI\Contracts\LDLMS_V2_Endpoint;
use LearnDash\Core\Modules\REST\Documentation_Migration\OpenAPI\Schemas\Course_Steps;
use WP_REST_Server;

/**
 * Course Steps OpenAPI Documentation Endpoint.
 *
 * @since 4.25.2
 */
class Steps extends LDLMS_V2_Endpoint {
	/**
	 * Returns the response schema for this endpoint.
	 *
	 * @since 4.25.2
	 *
	 * @param string $path   The path of the route. Defaults to empty string.
	 * @param string $method The HTTP method. Defaults to empty string.
	 *
	 * @return array<string,array<string,mixed>|string>
	 */
	public function get_response_schema( string $path = '', string $method = '' ): array {
		if ( $method === WP_REST_Server::READABLE ) {
			return Course_Steps::get_schema();
		}

		return [
			'type'        => 'object',
			'description' => sprintf(
				// translators: %s: singular course label.
				__( 'Updated hierarchical view of %s steps.', 'learndash' ),
				learndash_get_custom_label_lower( 'course' )
			),
			'properties'  => Course_Steps::get_hierarchical_properties(),
		];
	}

	/**
	 * Returns the routes configuration for this endpoint.
	 *
	 * @since 4.25.2
	 *
	 * @return array<string,array<string,string|callable>>
	 */
	protected function get_routes(): array {
		$courses_endpoint = LearnDash_Settings_Section::get_section_setting( 'LearnDash_Settings_Section_General_REST_API', 'courses_v2' );
		$steps_endpoint   = LearnDash_Settings_Section::get_section_setting( 'LearnDash_Settings_Section_General_REST_API', 'courses-steps_v2' );

		return $this->discover_routes(
			trailingslashit( $courses_endpoint ) . '(?P<id>[\d]+)/' . $steps_endpoint,
			[ 'nested' ]
		);
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
	protected function get_method_summary( string $method, string $route_type = 'collection' ): string {
		$summaries = [
			'nested' => [
				'GET'    => sprintf(
					// translators: %s: singular course label.
					__( 'Get associated steps for a %s.', 'learndash' ),
					learndash_get_custom_label_lower( 'course' )
				),
				'POST'   => sprintf(
					// translators: %s: singular course label.
					__( 'Add associated steps for a %s.', 'learndash' ),
					learndash_get_custom_label_lower( 'course' )
				),
				'PUT'    => sprintf(
					// translators: %s: singular course label.
					__( 'Update associated steps for a %s.', 'learndash' ),
					learndash_get_custom_label_lower( 'course' )
				),
				'PATCH'  => sprintf(
					// translators: %s: singular course label.
					__( 'Update associated steps for a %s.', 'learndash' ),
					learndash_get_custom_label_lower( 'course' )
				),
				'DELETE' => sprintf(
					// translators: %s: singular course label.
					__( 'Delete associated steps for a %s.', 'learndash' ),
					learndash_get_custom_label_lower( 'course' )
				),
			],
		];

		return $summaries[ $route_type ][ $method ]
			?? sprintf(
				// translators: %s: singular step label.
				__( '%s step operation', 'learndash' ),
				learndash_get_custom_label( 'step' )
			);
	}

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
	protected function get_method_description( string $method, string $route_type = 'collection' ): string {
		$descriptions = [
			'nested' => [
				'GET'   => sprintf(
					// translators: %s: singular course label.
					__( 'Retrieves the %1$s steps for a specific %2$s.', 'learndash' ),
					learndash_get_custom_label_lower( 'course' ),
					learndash_get_custom_label_lower( 'course' )
				),
				'POST'  => sprintf(
					// translators: %s: singular course label.
					__( 'Adds %1$s steps for a specific %2$s. This will overwrite any existing steps. Passing empty data will remove the associated steps from the %3$s.', 'learndash' ),
					learndash_get_custom_label_lower( 'course' ),
					learndash_get_custom_label_lower( 'course' ),
					learndash_get_custom_label_lower( 'course' )
				),
				'PUT'   => sprintf(
					// translators: %s: singular course label.
					__( 'Updates the %1$s step association for an existing %2$s.', 'learndash' ),
					learndash_get_custom_label_lower( 'course' ),
					learndash_get_custom_label_lower( 'course' )
				),
				'PATCH' => sprintf(
					// translators: %s: singular course label.
					__( 'Partially updates the %1$s step association for an existing %2$s. Only the provided fields will be updated, leaving other fields unchanged.', 'learndash' ),
					learndash_get_custom_label_lower( 'course' ),
					learndash_get_custom_label_lower( 'course' )
				),
			],
		];

		return $descriptions[ $route_type ][ $method ] ?? sprintf(
			// translators: %s: singular course label.
			__( 'Performs %1$s step operations on %2$s.', 'learndash' ),
			learndash_get_custom_label_lower( 'course' ),
			learndash_get_custom_label_lower( 'course' )
		);
	}
}
