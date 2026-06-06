<?php
/**
 * EDD Software Licensing client for WEBO MCP paid addons.
 *
 * @package webo-mcp-rank-math
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'webo_mcp_rank_math_license_option_key' ) ) {
	function webo_mcp_rank_math_license_option_key( $suffix ) {
		return 'webo_mcp_rank_math_license_' . sanitize_key( $suffix );
	}

	function webo_mcp_rank_math_license_request( $action, $license_key = '' ) {
		$license_key = '' === $license_key ? (string) get_option( webo_mcp_rank_math_license_option_key( 'key' ), '' ) : (string) $license_key;
		if ( '' === trim( $license_key ) ) {
			return new WP_Error( 'webo_mcp_license_missing_key', __( 'License key is required.', 'webo-mcp-rank-math' ) );
		}

		$response = wp_remote_get(
			add_query_arg(
				array(
					'edd_action' => sanitize_key( $action ),
					'license'    => trim( $license_key ),
					'item_id'    => WEBO_MCP_RANK_MATH_ITEM_ID,
					'url'        => home_url(),
				),
				WEBO_MCP_LICENSE_STORE_URL
			),
			array( 'timeout' => 15 )
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'webo_mcp_license_bad_response', __( 'License server returned an invalid response.', 'webo-mcp-rank-math' ) );
		}

		return $body;
	}

	function webo_mcp_rank_math_license_status() {
		return (string) get_option( webo_mcp_rank_math_license_option_key( 'status' ), 'inactive' );
	}

	function webo_mcp_rank_math_license_admin_menu() {
		add_options_page(
			__( 'WEBO MCP Rank Math License', 'webo-mcp-rank-math' ),
			__( 'WEBO MCP Rank Math License', 'webo-mcp-rank-math' ),
			'manage_options',
			'webo-mcp-rank-math-license',
			'webo_mcp_rank_math_license_page'
		);
	}

	function webo_mcp_rank_math_handle_license_action() {
		if ( ! current_user_can( 'manage_options' ) || empty( $_POST['webo_mcp_rank_math_license_action'] ) ) {
			return;
		}

		check_admin_referer( 'webo_mcp_rank_math_license' );

		$license_key = isset( $_POST['webo_mcp_rank_math_license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['webo_mcp_rank_math_license_key'] ) ) : '';
		update_option( webo_mcp_rank_math_license_option_key( 'key' ), $license_key, false );

		$action = sanitize_key( wp_unslash( $_POST['webo_mcp_rank_math_license_action'] ) );
		if ( 'deactivate' === $action ) {
			$result = webo_mcp_rank_math_license_request( 'deactivate_license', $license_key );
			update_option( webo_mcp_rank_math_license_option_key( 'status' ), 'inactive', false );
		} else {
			$result = webo_mcp_rank_math_license_request( 'activate_license', $license_key );
			update_option( webo_mcp_rank_math_license_option_key( 'status' ), is_array( $result ) && ! empty( $result['license'] ) ? sanitize_key( $result['license'] ) : 'inactive', false );
		}

		update_option( webo_mcp_rank_math_license_option_key( 'last_response' ), is_wp_error( $result ) ? $result->get_error_message() : wp_json_encode( $result ), false );
		wp_safe_redirect( add_query_arg( array( 'page' => 'webo-mcp-rank-math-license', 'updated' => '1' ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	function webo_mcp_rank_math_license_page() {
		$license_key = (string) get_option( webo_mcp_rank_math_license_option_key( 'key' ), '' );
		$status      = webo_mcp_rank_math_license_status();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WEBO MCP Rank Math License', 'webo-mcp-rank-math' ); ?></h1>
			<p><?php esc_html_e( 'Activate this addon license for the current WordPress domain.', 'webo-mcp-rank-math' ); ?></p>
			<form method="post">
				<?php wp_nonce_field( 'webo_mcp_rank_math_license' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="webo_mcp_rank_math_license_key"><?php esc_html_e( 'License key', 'webo-mcp-rank-math' ); ?></label></th>
						<td><input id="webo_mcp_rank_math_license_key" class="regular-text" type="password" name="webo_mcp_rank_math_license_key" value="<?php echo esc_attr( $license_key ); ?>" autocomplete="off"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Status', 'webo-mcp-rank-math' ); ?></th>
						<td><code><?php echo esc_html( $status ); ?></code></td>
					</tr>
				</table>
				<p class="submit">
					<button class="button button-primary" type="submit" name="webo_mcp_rank_math_license_action" value="activate"><?php esc_html_e( 'Activate License', 'webo-mcp-rank-math' ); ?></button>
					<button class="button" type="submit" name="webo_mcp_rank_math_license_action" value="deactivate"><?php esc_html_e( 'Deactivate License', 'webo-mcp-rank-math' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	add_action( 'admin_menu', 'webo_mcp_rank_math_license_admin_menu' );
	add_action( 'admin_init', 'webo_mcp_rank_math_handle_license_action' );
}


