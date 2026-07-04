<?php
/**
 * BrandProfileMapper — maps a brand profile input to the exact Rank Math option keys and values.
 *
 * This is the Mapper layer: it knows which Rank Math options correspond to each brand concept
 * (Knowledge Graph, homepage title/description, social profiles, sameAs, OpenGraph, Twitter card,
 * Schema entity, breadcrumbs, sitemap).  No writing happens here — only data transformation.
 *
 * @package webo-mcp-rank-math
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WeboMcpRankMath_BrandProfileMapper' ) ) {

	/**
	 * Maps a brand profile payload to Rank Math option group patches.
	 *
	 * Output shape:
	 * [
	 *   'general' => [ key => value, ... ],
	 *   'titles'  => [ key => value, ... ],
	 *   'social'  => [ key => value, ... ],
	 * ]
	 *
	 * Keys use the exact Rank Math option key names that live inside each option group array.
	 */
	class WeboMcpRankMath_BrandProfileMapper {

		/**
		 * Build the full patch map from a validated brand profile input.
		 *
		 * @param array<string,mixed> $input  Validated brand profile input.
		 * @return array<string,array<string,mixed>>  group => [key => value]
		 */
		public static function map( $input ) {
			$input         = self::normalize_input( $input );
			$brand_name    = sanitize_text_field( trim( (string) ( $input['brand_name'] ?? '' ) ) );
			$person_name   = sanitize_text_field( trim( (string) ( $input['person_name'] ?? '' ) ) );
			$alternate     = sanitize_text_field( trim( (string) ( $input['alternate_name'] ?? '' ) ) );
			$url           = self::normalize_url( $input['url'] ?? '' );
			$description   = sanitize_textarea_field( trim( (string) ( $input['description'] ?? '' ) ) );
			$profile       = sanitize_key( (string) ( $input['profile'] ?? 'organization' ) );
			$logo          = self::normalize_url( $input['logo'] ?? '' );
			$og_image      = self::normalize_url( $input['open_graph_image'] ?? ( $input['default_og_image'] ?? '' ) );
			$email         = sanitize_email( (string) ( $input['email'] ?? ( $input['contact_email'] ?? '' ) ) );
			$publisher     = sanitize_text_field( trim( (string) ( $input['publisher_name'] ?? ( $input['publisher'] ?? $brand_name ) ) ) );
			$publisher_logo = self::normalize_url( $input['publisher_logo'] ?? ( $input['publisher_image'] ?? $logo ) );

			// Rank Math Knowledge Graph entity type.
			$kg_type = ( 'personal' === $profile ) ? 'person' : 'organization';
			$kg_name = ( 'personal' === $profile && '' !== $person_name ) ? $person_name : $brand_name;

			// Social URLs.
			$facebook  = self::normalize_url( $input['facebook'] ?? '' );
			$twitter   = self::normalize_url( $input['twitter'] ?? '' );
			$instagram = self::normalize_url( $input['instagram'] ?? '' );
			$linkedin  = self::normalize_url( $input['linkedin'] ?? '' );
			$youtube   = self::normalize_url( $input['youtube'] ?? '' );
			$github    = self::normalize_url( $input['github'] ?? '' );
			$pinterest = self::normalize_url( $input['pinterest'] ?? '' );

			// Build sameAs array from all non-empty social links and explicit same_as entries.
			$same_as = self::normalize_url_list( array(
				$facebook,
				$twitter,
				$instagram,
				$linkedin,
				$youtube,
				$github,
				$pinterest,
			) );
			if ( isset( $input['same_as'] ) ) {
				$same_as = array_values( array_unique( array_merge( $same_as, self::normalize_url_list( $input['same_as'] ) ) ) );
			}

			// Homepage title defaults to brand_name.
			$homepage_title = ! empty( $input['homepage_title'] )
				? sanitize_text_field( trim( (string) $input['homepage_title'] ) )
				: $brand_name;

			// Homepage description defaults to provided description.
			$homepage_desc = ! empty( $input['homepage_description'] )
				? sanitize_textarea_field( trim( (string) $input['homepage_description'] ) )
				: $description;

			$general_patch = array(
				'knowledgegraph_type'        => $kg_type,
				'knowledgegraph_name'        => $kg_name,
				'knowledgegraph_url'         => $url,
				'knowledgegraph_description' => $description,
			);

			if ( '' !== $logo ) {
				$general_patch['knowledgegraph_logo'] = $logo;
			}
			if ( '' !== $email ) {
				$general_patch['email'] = $email;
				$general_patch['local_seo_email'] = $email;
			}
			if ( '' !== $publisher ) {
				$general_patch['publisher_name'] = $publisher;
			}
			if ( '' !== $publisher_logo ) {
				$general_patch['publisher_logo'] = $publisher_logo;
			}

			if ( ! empty( $same_as ) ) {
				$general_patch['social_url_facebook']  = $facebook;
				$general_patch['social_url_twitter']   = $twitter;
				$general_patch['social_url_instagram'] = $instagram;
				$general_patch['social_url_linkedin']  = $linkedin;
				$general_patch['social_url_youtube']   = $youtube;
				$general_patch['social_url_pinterest'] = $pinterest;
				$general_patch['social_url_github']    = $github;
				$general_patch['social_additional_profiles'] = implode( "\n", $same_as );
			}

			if ( ! empty( $input['local_business_type'] ) ) {
				$general_patch['local_business_type'] = sanitize_text_field( (string) $input['local_business_type'] );
			}
			if ( ! empty( $input['local_address'] ) ) {
				$general_patch['local_address'] = self::sanitize_mixed_text( $input['local_address'] );
			}
			if ( ! empty( $input['phone_numbers'] ) ) {
				$general_patch['phone_numbers'] = self::sanitize_list_or_text( $input['phone_numbers'] );
			} elseif ( ! empty( $input['phone'] ) ) {
				$general_patch['phone_numbers'] = array( sanitize_text_field( (string) $input['phone'] ) );
			}
			if ( ! empty( $input['contact'] ) && is_array( $input['contact'] ) ) {
				$contact = self::sanitize_assoc_patch( $input['contact'] );
				foreach ( $contact as $key => $value ) {
					$general_patch[ 'contact_' . sanitize_key( $key ) ] = $value;
				}
			}
			if ( ! empty( $input['opening_hours'] ) ) {
				$general_patch['opening_hours'] = self::sanitize_list_or_text( $input['opening_hours'] );
			}
			if ( isset( $input['hide_opening_hours'] ) ) {
				$general_patch['hide_opening_hours'] = self::truthy_to_on_off( $input['hide_opening_hours'] );
			}

			$titles_patch = array(
				'website_name'          => $brand_name,
				'homepage_custom_robots' => 'off',
			);

			if ( '' !== $alternate ) {
				$titles_patch['website_alternate_name'] = $alternate;
			}

			if ( '' !== $homepage_title ) {
				$titles_patch['homepage_title'] = $homepage_title;
			}

			if ( '' !== $homepage_desc ) {
				$titles_patch['homepage_description'] = $homepage_desc;
			}

			// Breadcrumb home label & link.
			$titles_patch['breadcrumbs_home_label'] = $brand_name;
			if ( '' !== $url ) {
				$titles_patch['breadcrumbs_home_link'] = $url;
			}

			// Twitter card type.
			$titles_patch['twitter_card_type'] = 'summary_large_image';
			if ( ! empty( $input['robots_global'] ) ) {
				$titles_patch['robots_global'] = self::sanitize_list_or_text( $input['robots_global'] );
			}
			if ( ! empty( $input['advanced_robots_global'] ) && is_array( $input['advanced_robots_global'] ) ) {
				$titles_patch['advanced_robots_global'] = array_map( 'sanitize_text_field', $input['advanced_robots_global'] );
			}
			if ( ! empty( $input['post_title_template'] ) ) {
				$titles_patch['pt_post_title'] = sanitize_text_field( (string) $input['post_title_template'] );
			}
			if ( ! empty( $input['page_title_template'] ) ) {
				$titles_patch['pt_page_title'] = sanitize_text_field( (string) $input['page_title_template'] );
			}
			if ( ! empty( $input['local_seo_about_page'] ) ) {
				$titles_patch['local_seo_about_page'] = max( 0, intval( $input['local_seo_about_page'] ) );
			}
			if ( ! empty( $input['local_seo_contact_page'] ) ) {
				$titles_patch['local_seo_contact_page'] = max( 0, intval( $input['local_seo_contact_page'] ) );
			}
			if ( isset( $input['image_seo'] ) && is_array( $input['image_seo'] ) ) {
				$titles_patch = array_merge( $titles_patch, self::map_image_seo( $input['image_seo'] ) );
			}
			if ( isset( $input['nofollow_external_links'] ) ) {
				$titles_patch['nofollow_external_links'] = self::truthy_to_on_off( $input['nofollow_external_links'] );
			}
			if ( isset( $input['nofollow_image_links'] ) ) {
				$titles_patch['nofollow_image_links'] = self::truthy_to_on_off( $input['nofollow_image_links'] );
			}

			// Social patch — OpenGraph & Twitter author.
			$social_patch = array();
			if ( '' !== $facebook ) {
				$social_patch['facebook_author_urls'] = array( $facebook );
				$social_patch['facebook_link']        = $facebook;
			}
			if ( '' !== $twitter ) {
				// Rank Math expects the handle, not the full URL; extract it.
				$tw_handle = ltrim( (string) parse_url( $twitter, PHP_URL_PATH ), '/' );
				if ( '' !== $tw_handle ) {
					$social_patch['twitter_author_names'] = array( $tw_handle );
				}
				$social_patch['twitter_link'] = $twitter;
			}

			if ( ! empty( $same_as ) ) {
				$social_patch['social_urls'] = $same_as;
			}
			if ( '' !== $og_image ) {
				$social_patch['open_graph_image'] = $og_image;
				$social_patch['twitter_image']    = $og_image;
			}

			// Entity sameAs — stored in general group.
			if ( ! empty( $same_as ) ) {
				$general_patch['social_networks'] = $same_as;
			}

			$sitemap_patch = array();
			if ( isset( $input['sitemap'] ) && is_array( $input['sitemap'] ) ) {
				$sitemap_patch = self::sanitize_assoc_patch( $input['sitemap'] );
			}

			$instant_patch = array();
			if ( isset( $input['instant_indexing'] ) && is_array( $input['instant_indexing'] ) ) {
				$instant_patch = self::sanitize_assoc_patch( $input['instant_indexing'] );
			}

			return array_filter(
				array(
					'general'          => array_filter( $general_patch ),
					'titles'           => array_filter( $titles_patch ),
					'social'           => array_filter( $social_patch ),
					'sitemap'          => array_filter( $sitemap_patch ),
					'instant-indexing' => array_filter( $instant_patch ),
				),
				static function ( $group ) {
					return ! empty( $group );
				}
			);
		}

		/**
		 * Normalize flat and nested ChatGPT payloads into one reusable input shape.
		 *
		 * @param array<string,mixed> $input Raw tool input.
		 * @return array<string,mixed>
		 */
		public static function normalize_input( $input ) {
			$input = (array) $input;
			foreach ( array( 'options', 'brand_identity' ) as $container ) {
				if ( isset( $input[ $container ] ) && is_array( $input[ $container ] ) ) {
					$input = array_merge( $input[ $container ], $input );
					unset( $input[ $container ] );
				}
			}

			if ( isset( $input['social'] ) && is_array( $input['social'] ) ) {
				foreach ( $input['social'] as $key => $value ) {
					if ( ! array_key_exists( $key, $input ) ) {
						$input[ $key ] = $value;
					}
				}
			}
			if ( isset( $input['sameAs'] ) && ! isset( $input['same_as'] ) ) {
				$input['same_as'] = $input['sameAs'];
			}
			foreach ( array( 'organization', 'person', 'publisher', 'contact' ) as $container ) {
				if ( isset( $input[ $container ] ) && is_array( $input[ $container ] ) ) {
					foreach ( $input[ $container ] as $key => $value ) {
						if ( ! array_key_exists( $key, $input ) ) {
							$input[ $key ] = $value;
						}
					}
				}
			}
			if ( isset( $input['organization'] ) && is_array( $input['organization'] ) ) {
				if ( ! isset( $input['brand_name'] ) && isset( $input['organization']['name'] ) ) {
					$input['brand_name'] = $input['organization']['name'];
				}
				if ( ! isset( $input['url'] ) && isset( $input['organization']['url'] ) ) {
					$input['url'] = $input['organization']['url'];
				}
				if ( ! isset( $input['logo'] ) && isset( $input['organization']['logo'] ) ) {
					$input['logo'] = $input['organization']['logo'];
				}
				if ( ! isset( $input['same_as'] ) && isset( $input['organization']['same_as'] ) ) {
					$input['same_as'] = $input['organization']['same_as'];
				}
			}
			if ( isset( $input['person'] ) && is_array( $input['person'] ) && ! isset( $input['person_name'] ) && isset( $input['person']['name'] ) ) {
				$input['person_name'] = $input['person']['name'];
			}
			if ( isset( $input['publisher'] ) && is_array( $input['publisher'] ) ) {
				if ( ! isset( $input['publisher_name'] ) && isset( $input['publisher']['name'] ) ) {
					$input['publisher_name'] = $input['publisher']['name'];
				}
				if ( ! isset( $input['publisher_logo'] ) ) {
					if ( isset( $input['publisher']['logo'] ) ) {
						$input['publisher_logo'] = $input['publisher']['logo'];
					} elseif ( isset( $input['publisher']['image'] ) ) {
						$input['publisher_logo'] = $input['publisher']['image'];
					}
				}
			}
			if ( isset( $input['contact'] ) && is_array( $input['contact'] ) && ! isset( $input['contact_email'] ) && isset( $input['contact']['email'] ) ) {
				$input['contact_email'] = $input['contact']['email'];
			}
			if ( ! isset( $input['brand_name'] ) && isset( $input['name'] ) ) {
				$input['brand_name'] = $input['name'];
			}
			if ( ! isset( $input['logo'] ) && isset( $input['image'] ) ) {
				$input['logo'] = $input['image'];
			}

			if ( isset( $input['local'] ) && is_array( $input['local'] ) ) {
				$local_map = array(
					'business_type'     => 'local_business_type',
					'address'           => 'local_address',
					'phone'             => 'phone',
					'phone_numbers'     => 'phone_numbers',
					'opening_hours'     => 'opening_hours',
					'hide_opening_hours'=> 'hide_opening_hours',
					'about_page'        => 'local_seo_about_page',
					'contact_page'      => 'local_seo_contact_page',
				);
				foreach ( $local_map as $from => $to ) {
					if ( isset( $input['local'][ $from ] ) && ! array_key_exists( $to, $input ) ) {
						$input[ $to ] = $input['local'][ $from ];
					}
				}
			}

			if ( isset( $input['analytics'] ) && is_array( $input['analytics'] ) && ! isset( $input['analytics_settings'] ) ) {
				$input['analytics_settings'] = $input['analytics'];
			}

			return $input;
		}

		private static function map_image_seo( $image_seo ) {
			$patch      = array();
			$post_types = array();
			if ( isset( $image_seo['post_types'] ) && is_array( $image_seo['post_types'] ) ) {
				$post_types = $image_seo['post_types'];
			}
			foreach ( $post_types as $post_type => $enabled ) {
				$key = 'pt_' . sanitize_key( (string) $post_type ) . '_autogenerate_image';
				$patch[ $key ] = self::truthy_to_on_off( $enabled );
			}
			if ( isset( $image_seo['default_image_overlay'] ) ) {
				$patch['default_image_overlay'] = sanitize_text_field( (string) $image_seo['default_image_overlay'] );
			}
			return $patch;
		}

		private static function normalize_url( $value ) {
			$value = trim( (string) $value );
			if ( '' === $value ) {
				return '';
			}
			$value = preg_replace( '#(?<!:)/{2,}#', '/', $value );
			return esc_url_raw( $value );
		}

		private static function normalize_url_list( $value ) {
			$values = is_array( $value ) ? $value : array( $value );
			$urls   = array();
			foreach ( $values as $item ) {
				$url = self::normalize_url( $item );
				if ( '' !== $url ) {
					$urls[] = $url;
				}
			}
			return array_values( array_unique( $urls ) );
		}

		private static function sanitize_assoc_patch( $patch ) {
			$clean = array();
			foreach ( (array) $patch as $key => $value ) {
				$key = sanitize_key( (string) $key );
				if ( '' === $key ) {
					continue;
				}
				if ( is_array( $value ) ) {
					$clean[ $key ] = array_map( 'sanitize_text_field', $value );
				} elseif ( is_bool( $value ) ) {
					$clean[ $key ] = $value;
				} else {
					$clean[ $key ] = sanitize_text_field( (string) $value );
				}
			}
			return $clean;
		}

		private static function sanitize_list_or_text( $value ) {
			if ( is_array( $value ) ) {
				return array_map( array( __CLASS__, 'sanitize_mixed_text' ), $value );
			}
			return sanitize_text_field( (string) $value );
		}

		private static function sanitize_mixed_text( $value ) {
			if ( is_array( $value ) ) {
				return self::sanitize_assoc_patch( $value );
			}
			return sanitize_textarea_field( (string) $value );
		}

		private static function truthy_to_on_off( $value ) {
			return webo_mcp_is_truthy( $value ) ? 'on' : 'off';
		}

		/**
		 * Build a list of human-readable field changes for diff output.
		 *
		 * @param array<string,array<string,mixed>> $patch   group => [key => new_value]
		 * @param array<string,array<string,mixed>> $current group => current group options
		 * @return array<string,mixed>[]  Each entry: {option_group, key, before, after, changed}
		 */
		public static function build_diff( $patch, $current ) {
			$diff = array();
			foreach ( $patch as $group => $keys ) {
				$group_current = is_array( $current[ $group ] ?? null ) ? $current[ $group ] : array();
				foreach ( $keys as $key => $new_val ) {
					$old_val = $group_current[ $key ] ?? null;
					$diff[]  = array(
						'option_group' => $group,
						'key'          => $key,
						'before'       => $old_val,
						'after'        => $new_val,
						'changed'      => $old_val !== $new_val,
					);
				}
			}
			return $diff;
		}

		/**
		 * Extract only the changed fields from a diff.
		 *
		 * @param array<string,mixed>[] $diff  Output of build_diff().
		 * @return string[]  List of "group.key" identifiers that would change.
		 */
		public static function changed_fields( $diff ) {
			$fields = array();
			foreach ( $diff as $item ) {
				if ( ! empty( $item['changed'] ) ) {
					$fields[] = $item['option_group'] . '.' . $item['key'];
				}
			}
			return $fields;
		}
	}
}
