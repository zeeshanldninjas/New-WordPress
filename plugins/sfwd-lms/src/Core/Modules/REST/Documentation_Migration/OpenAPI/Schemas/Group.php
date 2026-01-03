<?php
/**
 * LearnDash Group OpenAPI Schema Trait.
 *
 * @since 4.25.2
 *
 * @package LearnDash\Core
 */

namespace LearnDash\Core\Modules\REST\Documentation_Migration\OpenAPI\Schemas;

/**
 * Trait that provides LearnDash Group OpenAPI schema.
 *
 * @since 4.25.2
 */
class Group extends WP_Post {
	/**
	 * Returns the OpenAPI response schema for a LearnDash Group.
	 *
	 * @since 4.25.2
	 *
	 * @return array{
	 *     type: string,
	 *     properties: array<string,array<string,mixed>>,
	 *     required: array<string>,
	 * }
	 */
	public static function get_schema(): array {
		// Get the base WP_Post schema.
		$base_schema = parent::get_schema();

		$group_singular_lowercase  = learndash_get_custom_label_lower( 'group' );
		$group_plural_lowercase    = learndash_get_custom_label_lower( 'groups' );
		$group_singular            = learndash_get_custom_label( 'group' );
		$course_singular_lowercase = learndash_get_custom_label_lower( 'course' );
		$course_plural_lowercase   = learndash_get_custom_label_lower( 'courses' );

		// Add LearnDash Group specific properties based on actual API response.
		$group_properties = [
			// Group materials.
			'materials_enabled'                         => [
				'type'        => 'boolean',
				'description' => sprintf(
					/* translators: %s: Group label (lowercase) */
					__( 'Whether %s materials are enabled.', 'learndash' ),
					$group_singular_lowercase
				),
				'example'     => false,
			],
			'materials'                                 => [
				'type'        => 'object',
				'description' => sprintf(
					/* translators: %s: Group label (lowercase) */
					__( '%s materials information.', 'learndash' ),
					$group_singular
				),
				'properties'  => [
					'rendered' => [
						'type'        => 'string',
						'description' => __( 'The rendered materials content.', 'learndash' ),
						'example'     => '',
					],
				],
			],

			// Group certificate.
			'certificate'                               => [
				'type'        => 'integer',
				'description' => sprintf(
					/* translators: %s: Group label (lowercase) */
					__( 'The ID of the certificate associated with the %s.', 'learndash' ),
					$group_singular_lowercase
				),
				'example'     => 0,
			],

			// Group content visibility.
			'disable_content_table'                     => [
				'type'        => 'boolean',
				'description' => sprintf(
					/* translators: %s: Group label (lowercase) */
					__( 'Whether the %s content is always visible or only visible to members. False if only visible to members.', 'learndash' ),
					$group_singular_lowercase
				),
				'example'     => false,
			],

			// Group course ordering.
			'courses_orderby'                           => [
				'type'        => 'string',
				'description' => sprintf(
					/* translators: %1$s: Course label (lowercase, plural), %2$s: Group label (lowercase) */
					__( 'How to order %1$s within the %2$s. Empty string means use the default orderby value.', 'learndash' ),
					$course_plural_lowercase,
					$group_singular_lowercase
				),
				'example'     => '',
				'enum'        => [ '', 'title', 'date', 'menu_order' ],
			],
			'courses_order'                             => [
				'type'        => 'string',
				'description' => sprintf(
					/* translators: %1$s: Course label (lowercase, plural), %2$s: Group label (lowercase) */
					__( 'The order direction for %1$s within the %2$s. Empty string means use the default order.', 'learndash' ),
					$course_plural_lowercase,
					$group_singular_lowercase
				),
				'example'     => '',
				'enum'        => [ '', 'ASC', 'DESC' ],
			],

			// Group pricing and enrollment.
			'price_type'                                => [
				'type'        => 'string',
				'description' => sprintf(
					/* translators: %s: Group label (lowercase) */
					__( 'The %s price type. See ldlms/v2/price-types for available price types.', 'learndash' ),
					$group_singular_lowercase
				),
				'example'     => 'free',
			],
			'price_type_paynow_price'                   => [
				'type'        => 'string',
				'description' => sprintf(
					/* translators: %s: Group label (lowercase) */
					__( 'The pay now price for the %s.', 'learndash' ),
					$group_singular_lowercase
				),
				'example'     => '',
			],
			'group_price_type_paynow_enrollment_url'    => [
				'type'        => 'string',
				'description' => sprintf(
					/* translators: %s: Group label (lowercase) */
					__( 'The enrollment URL for the pay now %s.', 'learndash' ),
					$group_singular_lowercase
				),
				'example'     => '',
			],
			'price_type_subscribe_price'                => [
				'type'        => 'string',
				'description' => sprintf(
					/* translators: %s: Group label (lowercase) */
					__( 'The subscription price for the %s.', 'learndash' ),
					$group_singular_lowercase
				),
				'example'     => '',
			],
			'trial_price'                               => [
				'type'        => 'integer',
				'description' => sprintf(
					/* translators: %s: Group label (lowercase) */
					__( 'The trial price for the %s.', 'learndash' ),
					$group_singular_lowercase
				),
				'example'     => 0,
			],
			'group_price_type_subscribe_enrollment_url' => [
				'type'        => 'string',
				'description' => sprintf(
					/* translators: %s: Group label (lowercase) */
					__( 'The enrollment URL for subscription %s.', 'learndash' ),
					$group_singular_lowercase
				),
				'example'     => '',
			],
			'price_type_closed_price'                   => [
				'type'        => 'string',
				'description' => sprintf(
					/* translators: %s: Group label (lowercase) */
					__( 'The price for closed %s.', 'learndash' ),
					$group_singular_lowercase
				),
				'example'     => '',
			],
			'price_type_closed_custom_button_url'       => [
				'type'        => 'string',
				'description' => sprintf(
					/* translators: %s: Group label (lowercase) */
					__( 'The custom button URL for closed %s.', 'learndash' ),
					$group_singular_lowercase
				),
				'example'     => '',
			],

			// Group auto-enrollment.
			'auto_enroll'                               => [
				'type'        => 'string',
				'description' => sprintf(
					/* translators: %1$s: Group label (lowercase), %2$s: Course label (lowercase) */
					__( 'Whether to auto-enroll users in the %1$s when they join an included %2$s.', 'learndash' ),
					$group_singular_lowercase,
					$course_singular_lowercase
				),
				'example'     => '',
				'enum'        => [ '', 'yes' ],
			],
			'auto_enroll_courses'                       => [
				'type'        => 'string',
				'description' => sprintf(
					/* translators: %1$s: Group label (lowercase), %2$s: Course label (lowercase) */
					__( 'Whether to auto-enroll users in the %1$s when they join an included %2$s. This is not used. Use the auto_enroll field instead.', 'learndash' ),
					$group_singular_lowercase,
					$course_singular_lowercase
				),
				'example'     => '',
				'enum'        => [ '', 'yes' ],
			],

			// Group subscription settings.
			'interval'                                  => [
				'type'        => 'integer',
				'description' => sprintf(
					/* translators: %s: Group label (lowercase) */
					__( 'The subscription interval for the %s.', 'learndash' ),
					$group_singular_lowercase
				),
				'example'     => 0,
			],
			'frequency'                                 => [
				'type'        => 'string',
				'description' => sprintf(
					/* translators: %s: Group label (lowercase) */
					__( 'The subscription frequency for the %s.', 'learndash' ),
					$group_singular_lowercase
				),
				'example'     => '',
				'enum'        => [ '', 'D', 'W', 'M', 'Y' ],
			],
			'repeats'                                   => [
				'type'        => 'integer',
				'description' => sprintf(
					/* translators: %s: Group label (lowercase) */
					__( 'The number of times the %s subscription repeats.', 'learndash' ),
					$group_singular_lowercase
				),
				'example'     => 0,
			],
			'trial_interval'                            => [
				'type'        => 'integer',
				'description' => sprintf(
					/* translators: %s: Group label (lowercase) */
					__( 'The trial interval for the %s.', 'learndash' ),
					$group_singular_lowercase
				),
				'example'     => 0,
			],
			'trial_frequency'                           => [
				'type'        => 'string',
				'description' => sprintf(
					/* translators: %s: Group label (lowercase) */
					__( 'The trial frequency for the %s.', 'learndash' ),
					$group_singular_lowercase
				),
				'example'     => '',
				'enum'        => [ '', 'D', 'W', 'M', 'Y' ],
			],

			// Group dates and limits.
			'start_date'                                => [
				'type'        => 'string',
				'description' => sprintf(
					/* translators: %s: Group label (lowercase) */
					__( 'The start date for the %s as a unix timestamp. 0 means no start date.', 'learndash' ),
					$group_singular_lowercase
				),
				'example'     => '0',
			],
			'end_date'                                  => [
				'type'        => 'string',
				'description' => sprintf(
					/* translators: %s: Group label (lowercase) */
					__( 'The end date for the %s as a unix timestamp. 0 means no end date.', 'learndash' ),
					$group_singular_lowercase
				),
				'example'     => '0',
			],
			'student_limit'                             => [
				'type'        => 'integer',
				'description' => sprintf(
					/* translators: %s: Group label (lowercase) */
					__( 'The maximum number of students allowed in the %s. 0 means no limit.', 'learndash' ),
					$group_singular_lowercase
				),
				'example'     => 0,
			],

			// Group course pagination.
			'courses_per_page_enabled'                  => [
				'type'        => 'string',
				'description' => sprintf(
					/* translators: %1$s: Course label (lowercase), %2$s: Group label (lowercase, plural) */
					__( 'Whether %1$s per page setting for %2$s is enabled.', 'learndash' ),
					$course_singular_lowercase,
					$group_plural_lowercase
				),
				'example'     => '',
				'enum'        => [ '', 'yes' ],
			],

			// Group password.
			'password'                                  => [
				'type'        => 'string',
				'description' => __( 'Password if password protected.', 'learndash' ),
				'example'     => '',
			],

			// Group taxonomies.
			'ld_group_category'                         => [
				'type'        => 'array',
				'description' => sprintf(
					/* translators: %s: Group label (lowercase) */
					__( '%s categories term IDs.', 'learndash' ),
					$group_singular_lowercase
				),
				'items'       => [
					'type' => 'integer',
				],
				'example'     => [],
			],
			'ld_group_tag'                              => [
				'type'        => 'array',
				'description' => sprintf(
					/* translators: %s: Group label (lowercase) */
					__( '%s tags term IDs.', 'learndash' ),
					$group_singular_lowercase
				),
				'items'       => [
					'type' => 'integer',
				],
				'example'     => [],
			],

			// Group links (extending WP_Post _links).
			'_links'                                    => [
				'type'        => 'object',
				'description' => sprintf(
					/* translators: %s: Group label (lowercase) */
					__( 'HAL links for the %s (extends WP_Post links).', 'learndash' ),
					$group_singular_lowercase
				),
				'properties'  => [
					'about'               => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'href' => [
									'type'        => 'string',
									'description' => __( 'The link URL.', 'learndash' ),
								],
							],
						],
					],
					'version-history'     => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'count' => [
									'type'        => 'integer',
									'description' => __( 'Number of revisions.', 'learndash' ),
								],
								'href'  => [
									'type'        => 'string',
									'description' => __( 'The link URL.', 'learndash' ),
								],
							],
						],
					],
					'predecessor-version' => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'id'   => [
									'type'        => 'integer',
									'description' => __( 'The revision ID.', 'learndash' ),
								],
								'href' => [
									'type'        => 'string',
									'description' => __( 'The link URL.', 'learndash' ),
								],
							],
						],
					],
					'users'               => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'href'       => [
									'type'        => 'string',
									'description' => __( 'The link URL.', 'learndash' ),
								],
								'embeddable' => [
									'type'        => 'boolean',
									'description' => __( 'Whether the link is embeddable.', 'learndash' ),
								],
							],
						],
					],
					'leaders'             => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'href'       => [
									'type'        => 'string',
									'description' => __( 'The link URL.', 'learndash' ),
								],
								'embeddable' => [
									'type'        => 'boolean',
									'description' => __( 'Whether the link is embeddable.', 'learndash' ),
								],
							],
						],
					],
					'courses'             => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'href'       => [
									'type'        => 'string',
									'description' => __( 'The link URL.', 'learndash' ),
								],
								'embeddable' => [
									'type'        => 'boolean',
									'description' => __( 'Whether the link is embeddable.', 'learndash' ),
								],
							],
						],
					],
					'wp:attachment'       => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'href' => [
									'type'        => 'string',
									'description' => __( 'The link URL.', 'learndash' ),
								],
							],
						],
					],
					'wp:term'             => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'taxonomy'   => [
									'type'        => 'string',
									'description' => __( 'The taxonomy name.', 'learndash' ),
								],
								'href'       => [
									'type'        => 'string',
									'description' => __( 'The link URL.', 'learndash' ),
								],
								'embeddable' => [
									'type'        => 'boolean',
									'description' => __( 'Whether the link is embeddable.', 'learndash' ),
								],
							],
						],
					],
					'curies'              => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'name'      => [
									'type'        => 'string',
									'description' => __( 'The curie name.', 'learndash' ),
								],
								'href'      => [
									'type'        => 'string',
									'description' => __( 'The curie href template.', 'learndash' ),
								],
								'templated' => [
									'type'        => 'boolean',
									'description' => __( 'Whether the href is templated.', 'learndash' ),
								],
							],
						],
					],
				],
			],
		];

		$links = $group_properties['_links']['properties'];
		unset( $group_properties['_links'] );

		// Merge the base schema properties with group-specific properties.
		$base_schema['properties'] = array_merge(
			$base_schema['properties'],
			$group_properties
		);

		$base_links = is_array( $base_schema['properties']['_links']['properties'] ) ? $base_schema['properties']['_links']['properties'] : [];

		// Merge the _links properties to extend WP_Post links instead of overwriting them.
		$base_schema['properties']['_links']['properties'] = array_merge(
			$base_links,
			$links
		);

		// Add group-specific required fields.
		$base_schema['required'] = array_unique(
			array_merge(
				$base_schema['required'],
				[
					'materials_enabled',
					'materials',
					'certificate',
					'disable_content_table',
					'courses_orderby',
					'courses_order',
					'price_type',
					'price_type_paynow_price',
					'group_price_type_paynow_enrollment_url',
					'price_type_subscribe_price',
					'trial_price',
					'group_price_type_subscribe_enrollment_url',
					'price_type_closed_price',
					'price_type_closed_custom_button_url',
					'auto_enroll',
					'auto_enroll_courses',
					'interval',
					'frequency',
					'repeats',
					'trial_interval',
					'trial_frequency',
					'start_date',
					'end_date',
					'student_limit',
					'courses_per_page_enabled',
					'password',
					'ld_group_category',
					'ld_group_tag',
				]
			)
		);

		return $base_schema;
	}
}
