<?php
/**
 * OpenAPI Endpoints Documentation Provider.
 *
 * @since 4.25.2
 *
 * @package LearnDash\Core
 */

namespace LearnDash\Core\Modules\REST\Documentation_Migration\OpenAPI\Endpoints;

use LearnDash\Core\Modules\REST\Documentation_Migration\OpenAPI\Endpoints;
use StellarWP\Learndash\lucatume\DI52\ServiceProvider;

/**
 * Provider for initializing OpenAPI documentation for endpoints.
 *
 * @since 4.25.2
 */
class Provider extends ServiceProvider {
	/**
	 * Registers the service provider bindings.
	 *
	 * @since 4.25.2
	 *
	 * @return void
	 */
	public function register(): void {
		$this->container->register( Endpoints\Courses\Provider::class );

		$this->hooks();
	}

	/**
	 * Adds the endpoints to the Open API documentation.
	 *
	 * @since 4.25.2
	 *
	 * @param string[] $endpoints Class names of endpoints.
	 *
	 * @return string[]
	 */
	public function add_endpoints( array $endpoints ): array {
		return array_merge(
			$endpoints,
			[
				Endpoints\Lesson::class,
				Endpoints\Topic::class,
				Endpoints\Price_Types::class,
				Endpoints\Assignment::class,
			]
		);
	}

	/**
	 * Hooks wrapper.
	 *
	 * @since 4.25.2
	 *
	 * @return void
	 */
	protected function hooks(): void {
		add_filter(
			'learndash_rest_endpoints',
			$this->container->callback(
				self::class,
				'add_endpoints'
			)
		);
	}
}
