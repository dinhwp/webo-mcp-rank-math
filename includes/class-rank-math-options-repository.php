<?php
/**
 * RankMathOptionsRepository — single source of truth for reading/writing Rank Math WordPress options.
 *
 * Controller/Service layers MUST NOT call get_option/update_option for Rank Math options directly.
 * All reads and writes go through this class.
 *
 * @package webo-mcp-rank-math
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WeboMcpRankMath_OptionsRepository' ) ) {

	/**
	 * Repository for Rank Math WordPress options.
	 *
	 * Provides typed read/write access to the five core Rank Math option groups
	 * plus the modules list. No business logic lives here — only raw data access.
	 */
	class WeboMcpRankMath_OptionsRepository {

		/**
		 * Allowed Rank Math option name prefixes.
		 */
		const PREFIX_RANK_MATH_DASH = 'rank-math-options-';
		const PREFIX_RANK_MATH_UNDER = 'rank_math';

		/**
		 * Canonical Rank Math option group names.
		 *
		 * @return array<string,string>  short_name => wp_option_name
		 */
		public static function option_group_map() {
			return array(
				'general'          => 'rank-math-options-general',
				'titles'           => 'rank-math-options-titles',
				'sitemap'          => 'rank-math-options-sitemap',
				'social'           => 'rank-math-options-social',
				'instant-indexing' => 'rank-math-options-instant-indexing',
			);
		}

		/**
		 * Read one Rank Math option by WP option name.
		 *
		 * @param string $option_name  Exact WP option name (e.g. rank-math-options-general).
		 * @param mixed  $default      Default value when option does not exist.
		 * @return mixed
		 */
		public static function get( $option_name, $default = array() ) {
			if ( ! self::is_allowed( $option_name ) ) {
				return $default;
			}
			return get_option( $option_name, $default );
		}

		/**
		 * Read multiple Rank Math option groups at once.
		 *
		 * @param string[]|null $names  WP option names. NULL reads all default groups.
		 * @return array<string,mixed>  option_name => value
		 */
		public static function get_many( $names = null ) {
			if ( null === $names ) {
				$names = array_merge(
					array( 'rank_math_modules' ),
					array_values( self::option_group_map() )
				);
			}

			$result = array();
			foreach ( $names as $name ) {
				$value = get_option( $name, null );
				if ( null !== $value ) {
					$result[ $name ] = $value;
				}
			}
			return $result;
		}

		/**
		 * Read one named option group by short name (general|titles|sitemap|social|instant-indexing).
		 *
		 * @param string $group  Short group name.
		 * @return array<string,mixed>
		 */
		public static function get_group( $group ) {
			$map  = self::option_group_map();
			$name = $map[ $group ] ?? null;
			if ( null === $name ) {
				return array();
			}
			return (array) get_option( $name, array() );
		}

		/**
		 * Persist one Rank Math option by its exact WP option name.
		 *
		 * @param string $option_name  Exact WP option name.
		 * @param mixed  $value        Value to persist.
		 * @return bool  True on success.
		 */
		public static function set( $option_name, $value ) {
			if ( ! self::is_allowed( $option_name ) ) {
				return false;
			}
			update_option( $option_name, $value );
			return true;
		}

		/**
		 * Persist multiple options at once.
		 *
		 * @param array<string,mixed> $options  option_name => value
		 * @return array<string,bool>  option_name => success
		 */
		public static function set_many( $options ) {
			$results = array();
			foreach ( $options as $name => $value ) {
				$results[ $name ] = self::set( $name, $value );
			}
			return $results;
		}

		/**
		 * Deep-merge an associative array of key=>value pairs into an existing option group.
		 *
		 * @param string              $group   Short group name (general|titles|sitemap|social).
		 * @param array<string,mixed> $patch   Keys to merge/overwrite.
		 * @return array<string,mixed>  Full option group after merge (not yet saved).
		 */
		public static function merge_group( $group, $patch ) {
			$current = self::get_group( $group );
			return array_merge( $current, $patch );
		}

		/**
		 * Save a merged patch into an option group.
		 *
		 * @param string              $group  Short group name.
		 * @param array<string,mixed> $patch  Keys to merge and save.
		 * @return bool
		 */
		public static function patch_group( $group, $patch ) {
			$map  = self::option_group_map();
			$name = $map[ $group ] ?? null;
			if ( null === $name ) {
				return false;
			}
			$merged = self::merge_group( $group, $patch );
			return self::set( $name, $merged );
		}

		/**
		 * Read the current Rank Math active modules list.
		 *
		 * @return string[]
		 */
		public static function get_modules() {
			return array_values( array_filter( (array) get_option( 'rank_math_modules', array() ) ) );
		}

		/**
		 * Persist an updated modules list.
		 *
		 * @param string[] $modules
		 * @return bool
		 */
		public static function set_modules( $modules ) {
			$clean = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $modules ) ) ) );
			sort( $clean );
			update_option( 'rank_math_modules', $clean );
			return true;
		}

		/**
		 * Check whether an option name is allowed to be read/written by this repository.
		 *
		 * @param string $name  WP option name.
		 * @return bool
		 */
		public static function is_allowed( $name ) {
			$name = (string) $name;
			return (
				0 === strpos( $name, self::PREFIX_RANK_MATH_DASH ) ||
				0 === strpos( $name, self::PREFIX_RANK_MATH_UNDER )
			);
		}

		/**
		 * Resolve a short group name or full WP option name to the canonical WP option name.
		 *
		 * @param string $group_or_name  'general', 'titles', 'rank-math-options-general', etc.
		 * @return string|null  WP option name, or null if not recognised.
		 */
		public static function resolve_option_name( $group_or_name ) {
			$map = self::option_group_map();
			$key = sanitize_key( str_replace( ' ', '-', (string) $group_or_name ) );

			// Short name lookup.
			if ( isset( $map[ $key ] ) ) {
				return $map[ $key ];
			}

			// Full name that is allowed.
			if ( self::is_allowed( $key ) ) {
				return $key;
			}

			return null;
		}
	}
}
