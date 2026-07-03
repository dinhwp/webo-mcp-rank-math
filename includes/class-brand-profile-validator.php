<?php
/**
 * BrandProfileValidator — validates the input for apply-brand-profile and migrate-brand actions.
 *
 * @package webo-mcp-rank-math
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WeboMcpRankMath_BrandProfileValidator' ) ) {

	/**
	 * Validates brand profile and brand migration inputs before any mutation occurs.
	 */
	class WeboMcpRankMath_BrandProfileValidator {

		/**
		 * Allowed profile types.
		 *
		 * @return string[]
		 */
		public static function allowed_profiles() {
			return array( 'personal', 'organization', 'company' );
		}

		/**
		 * Validate the input for apply-brand-profile action.
		 *
		 * @param array<string,mixed> $input  Raw tool input.
		 * @return WP_Error|true  WP_Error on failure, true on success.
		 */
		public static function validate_brand_profile( $input ) {
			$errors = new WP_Error();

			// profile type
			$profile = sanitize_key( (string) ( $input['profile'] ?? '' ) );
			if ( '' === $profile ) {
				$errors->add( 'missing_profile', 'profile is required (personal, organization, company).' );
			} elseif ( ! in_array( $profile, self::allowed_profiles(), true ) ) {
				$errors->add(
					'invalid_profile',
					sprintf( 'profile must be one of: %s.', implode( ', ', self::allowed_profiles() ) )
				);
			}

			// brand_name
			$brand_name = trim( (string) ( $input['brand_name'] ?? '' ) );
			if ( '' === $brand_name ) {
				$errors->add( 'missing_brand_name', 'brand_name is required.' );
			}

			// url — optional but must be valid when provided
			$url = trim( (string) ( $input['url'] ?? '' ) );
			if ( '' !== $url && false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
				$errors->add( 'invalid_url', 'url must be a valid URL.' );
			}

			// Social links — optional, but must be valid URLs when provided
			$social_fields = array( 'facebook', 'twitter', 'instagram', 'linkedin', 'youtube', 'github', 'pinterest' );
			foreach ( $social_fields as $field ) {
				$val = trim( (string) ( $input[ $field ] ?? '' ) );
				if ( '' !== $val && false === filter_var( $val, FILTER_VALIDATE_URL ) ) {
					$errors->add( "invalid_{$field}", "{$field} must be a valid URL." );
				}
			}

			if ( $errors->has_errors() ) {
				return $errors;
			}

			return true;
		}

		/**
		 * Validate the input for migrate-brand action.
		 *
		 * @param array<string,mixed> $input  Raw tool input.
		 * @return WP_Error|true
		 */
		public static function validate_migrate_brand( $input ) {
			$errors = new WP_Error();

			$from = trim( (string) ( $input['from'] ?? '' ) );
			$to   = trim( (string) ( $input['to'] ?? '' ) );

			if ( '' === $from ) {
				$errors->add( 'missing_from', 'from (old brand name) is required.' );
			}

			if ( '' === $to ) {
				$errors->add( 'missing_to', 'to (new brand name) is required.' );
			}

			if ( '' !== $from && '' !== $to && $from === $to ) {
				$errors->add( 'same_values', 'from and to must be different values.' );
			}

			if ( $errors->has_errors() ) {
				return $errors;
			}

			return true;
		}

		/**
		 * Convert a WP_Error with multiple errors into a flat array of messages.
		 *
		 * @param WP_Error $error
		 * @return string[]
		 */
		public static function flatten_errors( WP_Error $error ) {
			$messages = array();
			foreach ( $error->get_error_codes() as $code ) {
				foreach ( $error->get_error_messages( $code ) as $msg ) {
					$messages[] = "[{$code}] {$msg}";
				}
			}
			return $messages;
		}
	}
}
