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
			$brand_name    = sanitize_text_field( trim( (string) ( $input['brand_name'] ?? '' ) ) );
			$person_name   = sanitize_text_field( trim( (string) ( $input['person_name'] ?? '' ) ) );
			$alternate     = sanitize_text_field( trim( (string) ( $input['alternate_name'] ?? '' ) ) );
			$url           = esc_url_raw( trim( (string) ( $input['url'] ?? '' ) ) );
			$description   = sanitize_textarea_field( trim( (string) ( $input['description'] ?? '' ) ) );
			$profile       = sanitize_key( (string) ( $input['profile'] ?? 'organization' ) );
			$logo          = esc_url_raw( trim( (string) ( $input['logo'] ?? '' ) ) );

			// Rank Math Knowledge Graph entity type.
			$kg_type = ( 'personal' === $profile ) ? 'person' : 'organization';
			$kg_name = ( 'personal' === $profile && '' !== $person_name ) ? $person_name : $brand_name;

			// Social URLs.
			$facebook  = esc_url_raw( trim( (string) ( $input['facebook'] ?? '' ) ) );
			$twitter   = esc_url_raw( trim( (string) ( $input['twitter'] ?? '' ) ) );
			$instagram = esc_url_raw( trim( (string) ( $input['instagram'] ?? '' ) ) );
			$linkedin  = esc_url_raw( trim( (string) ( $input['linkedin'] ?? '' ) ) );
			$youtube   = esc_url_raw( trim( (string) ( $input['youtube'] ?? '' ) ) );
			$github    = esc_url_raw( trim( (string) ( $input['github'] ?? '' ) ) );
			$pinterest = esc_url_raw( trim( (string) ( $input['pinterest'] ?? '' ) ) );

			// Build sameAs array from all non-empty social links.
			$same_as = array_values( array_filter( array(
				$facebook,
				$twitter,
				$instagram,
				$linkedin,
				$youtube,
				$github,
				$pinterest,
			) ) );

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

			if ( ! empty( $same_as ) ) {
				$general_patch['social_url_facebook']  = $facebook;
				$general_patch['social_url_twitter']   = $twitter;
				$general_patch['social_url_instagram'] = $instagram;
				$general_patch['social_url_linkedin']  = $linkedin;
				$general_patch['social_url_youtube']   = $youtube;
				$general_patch['social_url_pinterest'] = $pinterest;
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

			// Entity sameAs — stored in general group.
			if ( ! empty( $same_as ) ) {
				$general_patch['social_networks'] = $same_as;
			}

			return array_filter(
				array(
					'general' => array_filter( $general_patch ),
					'titles'  => array_filter( $titles_patch ),
					'social'  => array_filter( $social_patch ),
				),
				static function ( $group ) {
					return ! empty( $group );
				}
			);
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
