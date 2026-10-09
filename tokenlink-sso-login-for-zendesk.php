<?php
/**
 * Plugin Name: TokenLink SSO Login for Zendesk
 * Plugin URI: https://www.mailborder.com/zendesk-sso-plugin
 * Description: Provides secure JWT-based single sign-on between WordPress and Zendesk. Developed by Mailborder Systems.
 * Version: 1.0.10
 * Requires at least: 5.5
 * Tested up to: 7.0
 * Requires PHP: 7.4
 * Author: Jerry Benton
 * Author URI: https://www.mailborder.com
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: tokenlink-sso-login-for-zendesk
 *
 * This plugin includes portions of the firebase/php-jwt library,
 * © 2011–2025 Firebase, licensed under the BSD 3-Clause License.
 * See includes/jwt/LICENSE for details.
 *
 * @package TokenLink_SSO_Login_For_Zendesk
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/*
 * ----------------------------------------------------------------------
 * Activation: flag a one-time admin notice pointing the user at settings.
 * ----------------------------------------------------------------------
 */
register_activation_hook(
	__FILE__,
	function () {
		set_transient( 'tokenlink_zendesk_sso_activated', 1, MINUTE_IN_SECONDS * 30 );
	}
);

add_action(
	'admin_notices',
	function () {
		if ( ! get_transient( 'tokenlink_zendesk_sso_activated' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		delete_transient( 'tokenlink_zendesk_sso_activated' );
		$link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=tokenlink_zendesk_sso' ) ),
			esc_html__( 'configure your Zendesk subdomain and shared secret', 'tokenlink-sso-login-for-zendesk' )
		);
		printf(
			'<div class="notice notice-info is-dismissible"><p>%s</p></div>',
			wp_kses(
				sprintf(
					/* translators: %s: an HTML anchor link to the plugin settings page */
					__( 'TokenLink SSO Login for Zendesk: %s to enable SSO.', 'tokenlink-sso-login-for-zendesk' ),
					$link
				),
				array( 'a' => array( 'href' => array() ) )
			)
		);
	}
);

/*
 * ----------------------------------------------------------------------
 * Settings page under Settings → Zendesk SSO
 * ----------------------------------------------------------------------
 */
add_action(
	'admin_menu',
	function () {
		add_options_page(
			'TokenLink Zendesk SSO Settings',
			'TokenLink - Zendesk',
			'manage_options',
			'tokenlink_zendesk_sso',
			'tokenlink_zendesk_sso_settings_page'
		);
	}
);

/**
 * Adds a Settings link to the plugin action links.
 *
 * @param array $links Existing plugin action links.
 * @return array Modified plugin action links.
 */
add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'options-general.php?page=tokenlink_zendesk_sso' ),
			__( 'Settings', 'tokenlink-sso-login-for-zendesk' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}
);

/**
 * Renders the TokenLink Zendesk SSO settings page in the WordPress admin.
 *
 * @return void
 */
function tokenlink_zendesk_sso_settings_page() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab selection.
	$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';
	if ( ! in_array( $active_tab, array( 'general', 'about' ), true ) ) {
		$active_tab = 'general';
	}
	?>
	<style>
	/* Two-Level Tab Navigation System */
	.tokenlink-outer-tabs {
		display: flex;
		flex-wrap: wrap;
		border-bottom: 1px solid #dadadf;
		margin-top: 20px;
		margin-bottom: 0;
	}

	.tokenlink-outer-tab-link {
		padding: 20px 0 17px;
		text-decoration: none;
		font-size: 15px;
		color: #3a3a56;
		margin-right: 30px;
		border-bottom: 3px solid transparent;
		margin-bottom: -1px;
		transition: all 0.2s ease;
	}

	.tokenlink-outer-tab-link:hover {
		color: #09092c;
	}

	.tokenlink-outer-tab-link.active {
		color: #09092c;
		font-weight: 500;
		border-color: #1a325e;
	}

	.tokenlink-outer-tab-content {
		display: none;
		margin-top: 0;
		padding: 20px 0;
	}

	.tokenlink-outer-tab-content.active {
		display: block;
		animation: tokenlink-fadeIn 0.2s ease-in-out;
	}

	@keyframes tokenlink-fadeIn {
		from { opacity: 0; }
		to { opacity: 1; }
	}

	/* Responsive behavior for mobile */
	@media screen and (max-width: 782px) {
		.tokenlink-outer-tab-link {
			padding: 15px 0 12px;
			font-size: 13px;
			margin-right: 20px;
		}
	}
	</style>

	<div class="wrap">
		<h1>TokenLink SSO Login for Zendesk</h1>

		<div class="tokenlink-outer-tabs">
			<a href="<?php echo esc_url( admin_url( 'options-general.php?page=tokenlink_zendesk_sso&tab=general' ) ); ?>"
			   class="tokenlink-outer-tab-link <?php echo 'general' === $active_tab ? 'active' : ''; ?>">
				<?php esc_html_e( 'General', 'tokenlink-sso-login-for-zendesk' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'options-general.php?page=tokenlink_zendesk_sso&tab=about' ) ); ?>"
			   class="tokenlink-outer-tab-link <?php echo 'about' === $active_tab ? 'active' : ''; ?>">
				<?php esc_html_e( 'About', 'tokenlink-sso-login-for-zendesk' ); ?>
			</a>
		</div>

		<div class="tokenlink-outer-tab-content active">
			<?php
			if ( 'general' === $active_tab ) {
				tokenlink_zendesk_sso_render_general_tab();
			} else {
				tokenlink_zendesk_sso_render_about_tab();
			}
			?>
		</div>
	</div>
	<?php
}

/**
 * Renders the General settings tab content.
 *
 * @return void
 */
function tokenlink_zendesk_sso_render_general_tab() {
	?>
	<form method="post" action="options.php">
		<?php
		settings_fields( 'tokenlink_zendesk_sso' );
		do_settings_sections( 'tokenlink_zendesk_sso' );
		submit_button();
		?>
	</form>
	<?php
}

/**
 * Renders the About tab content.
 *
 * @return void
 */
function tokenlink_zendesk_sso_render_about_tab() {
	if ( ! function_exists( 'get_plugin_data' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$plugin_data = get_plugin_data( __FILE__ );
	$version     = $plugin_data['Version'];
	?>
	<style>
	.tokenlink-about-wrap {
		display: flex;
		gap: 40px;
		max-width: 1200px;
	}
	.tokenlink-about-main {
		flex: 1;
		min-width: 0;
	}
	.tokenlink-about-sidebar {
		width: 300px;
		flex-shrink: 0;
	}

	/* Hero Section */
	.tokenlink-about-wrap .tokenlink-hero {
		display: block;
		box-sizing: border-box;
		background: linear-gradient(135deg, #1d2327 0%, #2c3338 100%) !important;
		border-radius: 12px;
		padding: 32px;
		margin-bottom: 32px;
		color: #fff !important;
		overflow: hidden;
	}
	.tokenlink-about-wrap .tokenlink-hero h2,
	.tokenlink-about-wrap .tokenlink-hero h2.wp-heading-inline {
		margin: 0 0 8px 0 !important;
		padding: 0 !important;
		font-size: 1.8em !important;
		font-weight: 600 !important;
		color: #fff !important;
		display: flex !important;
		align-items: center;
		gap: 12px;
		border: none !important;
		background: transparent !important;
		line-height: 1.3;
	}
	.tokenlink-about-wrap .tokenlink-version-badge {
		display: inline-block;
		box-sizing: border-box;
		background: rgba(255,255,255,0.15) !important;
		padding: 4px 12px;
		border-radius: 20px;
		font-size: 13px !important;
		font-weight: 400 !important;
		color: rgba(255,255,255,0.9) !important;
		line-height: 1.4;
		flex-shrink: 0;
	}
	.tokenlink-about-wrap .tokenlink-hero-tagline {
		font-size: 16px !important;
		line-height: 1.6;
		color: rgba(255,255,255,0.85) !important;
		margin: 0 !important;
		padding: 0 !important;
	}

	/* Feature Grid */
	.tokenlink-features {
		display: grid;
		grid-template-columns: repeat(2, 1fr);
		gap: 16px;
		margin-bottom: 32px;
	}
	.tokenlink-feature {
		background: #f6f7f7;
		border: 1px solid #e0e0e0;
		border-radius: 8px;
		padding: 20px;
	}
	.tokenlink-feature-icon {
		width: 40px;
		height: 40px;
		background: #2271b1;
		border-radius: 8px;
		display: flex;
		align-items: center;
		justify-content: center;
		margin-bottom: 12px;
	}
	.tokenlink-feature-icon .dashicons {
		color: #fff;
		font-size: 20px;
		width: 20px;
		height: 20px;
	}
	.tokenlink-feature h4 {
		margin: 0 0 6px 0;
		font-size: 14px;
		font-weight: 600;
		color: #1d2327;
	}
	.tokenlink-feature p {
		margin: 0;
		font-size: 13px;
		line-height: 1.5;
		color: #50575e;
	}

	/* Section Styling */
	.tokenlink-section {
		margin-bottom: 28px;
	}
	.tokenlink-section h3 {
		margin: 0 0 14px 0;
		padding-bottom: 10px;
		border-bottom: 2px solid #2271b1;
		font-size: 1.15em;
		font-weight: 600;
		color: #1d2327;
	}
	.tokenlink-section p {
		font-size: 14px;
		line-height: 1.7;
		color: #3c434a;
		margin: 0 0 14px 0;
	}

	/* Creator Section */
	.tokenlink-creator {
		background: #fff;
		border: 1px solid #e0e0e0;
		border-radius: 8px;
		padding: 24px;
		margin-bottom: 28px;
	}
	.tokenlink-creator h3 {
		margin: 0 0 14px 0;
		font-size: 1.1em;
		font-weight: 600;
		color: #1d2327;
		border: none;
		padding: 0;
	}
	.tokenlink-creator p {
		font-size: 14px;
		line-height: 1.7;
		color: #3c434a;
		margin: 0 0 12px 0;
	}
	.tokenlink-creator p:last-child {
		margin-bottom: 0;
	}

	/* Links */
	.tokenlink-external-link {
		color: #2271b1;
		text-decoration: none;
		font-weight: 500;
	}
	.tokenlink-external-link:hover {
		color: #135e96;
		text-decoration: underline;
	}
	.tokenlink-external-link::after {
		content: "\f504";
		font-family: dashicons;
		font-size: 14px;
		margin-left: 3px;
		vertical-align: middle;
	}

	/* Resources List */
	.tokenlink-resources {
		display: grid;
		grid-template-columns: repeat(2, 1fr);
		gap: 12px;
	}
	.tokenlink-resource-link {
		display: flex;
		align-items: center;
		gap: 10px;
		padding: 12px 16px;
		background: #f6f7f7;
		border: 1px solid #e0e0e0;
		border-radius: 6px;
		text-decoration: none;
		color: #1d2327;
		font-size: 14px;
		font-weight: 500;
		transition: all 0.15s ease;
	}
	.tokenlink-resource-link:hover {
		background: #fff;
		border-color: #2271b1;
		color: #2271b1;
	}
	.tokenlink-resource-link .dashicons {
		color: #2271b1;
		font-size: 18px;
		width: 18px;
		height: 18px;
	}

	/* Sidebar */
	.tokenlink-donate-box {
		background: linear-gradient(135deg, #2271b1 0%, #135e96 100%);
		border-radius: 12px;
		padding: 28px 24px;
		text-align: center;
		color: #fff;
	}
	.tokenlink-donate-box h3 {
		margin: 0 0 8px 0;
		font-size: 1.3em;
		color: #fff;
		font-weight: 600;
	}
	.tokenlink-donate-free {
		font-size: 14px;
		color: rgba(255,255,255,0.85);
		margin: 0 0 20px 0;
	}
	.tokenlink-donate-button {
		display: block;
		background: #fff;
		color: #135e96 !important;
		font-size: 16px;
		font-weight: 600;
		padding: 14px 24px;
		border-radius: 8px;
		text-decoration: none;
		transition: all 0.2s ease;
	}
	.tokenlink-donate-button:hover {
		background: #f0f6fc;
		color: #135e96 !important;
		text-decoration: none;
		transform: translateY(-1px);
	}
	.tokenlink-donate-note {
		margin: 16px 0 0 0;
		font-size: 12px;
		color: rgba(255,255,255,0.7);
		line-height: 1.5;
	}

	.tokenlink-rate-box {
		background: #fff;
		border: 1px solid #e0e0e0;
		border-radius: 12px;
		padding: 24px;
		text-align: center;
		margin-top: 20px;
	}
	.tokenlink-rate-box h3 {
		margin: 0 0 8px 0;
		font-size: 1.1em;
		color: #1d2327;
		font-weight: 600;
	}
	.tokenlink-rate-box p {
		font-size: 13px;
		color: #50575e;
		margin: 0 0 16px 0;
		line-height: 1.5;
	}
	.tokenlink-rate-stars {
		font-size: 24px;
		color: #ffb900;
		margin: 0 0 16px 0;
		letter-spacing: 2px;
	}
	.tokenlink-rate-button {
		display: block;
		background: #f6f7f7;
		color: #1d2327 !important;
		font-size: 14px;
		font-weight: 500;
		padding: 12px 20px;
		border-radius: 6px;
		border: 1px solid #e0e0e0;
		text-decoration: none;
		transition: all 0.15s ease;
	}
	.tokenlink-rate-button:hover {
		background: #fff;
		border-color: #2271b1;
		color: #2271b1 !important;
		text-decoration: none;
	}

	/* Philosophy Box */
	.tokenlink-philosophy {
		background: #f0f6fc;
		border-left: 4px solid #2271b1;
		padding: 16px 20px;
		margin: 20px 0;
		border-radius: 0 8px 8px 0;
	}
	.tokenlink-philosophy p {
		margin: 0;
		font-size: 14px;
		line-height: 1.6;
		color: #1d2327;
		font-style: italic;
	}

	@media screen and (max-width: 960px) {
		.tokenlink-about-wrap {
			flex-direction: column;
		}
		.tokenlink-about-sidebar {
			width: 100%;
			order: -1;
		}
		.tokenlink-features {
			grid-template-columns: 1fr;
		}
		.tokenlink-resources {
			grid-template-columns: 1fr;
		}
	}
	</style>

	<div class="tokenlink-about-wrap">

		<!-- Main Content Column -->
		<div class="tokenlink-about-main">

			<!-- Hero -->
			<div class="tokenlink-hero">
				<h2>
					<?php esc_html_e( 'TokenLink SSO Login for Zendesk', 'tokenlink-sso-login-for-zendesk' ); ?>
					<span class="tokenlink-version-badge">v<?php echo esc_html( $version ); ?></span>
				</h2>
				<p class="tokenlink-hero-tagline">
					<?php esc_html_e( 'Secure JWT-based single sign-on between WordPress and Zendesk. No third-party dependencies, no tracking, no bloat.', 'tokenlink-sso-login-for-zendesk' ); ?>
				</p>
			</div>

			<!-- Feature Grid -->
			<div class="tokenlink-features">
				<div class="tokenlink-feature">
					<div class="tokenlink-feature-icon"><span class="dashicons dashicons-shield-alt"></span></div>
					<h4><?php esc_html_e( 'Secure JWT Authentication', 'tokenlink-sso-login-for-zendesk' ); ?></h4>
					<p><?php esc_html_e( 'Industry-standard JSON Web Token authentication using HS256 signing with cryptographically secure random identifiers.', 'tokenlink-sso-login-for-zendesk' ); ?></p>
				</div>
				<div class="tokenlink-feature">
					<div class="tokenlink-feature-icon"><span class="dashicons dashicons-admin-plugins"></span></div>
					<h4><?php esc_html_e( 'Zero Dependencies', 'tokenlink-sso-login-for-zendesk' ); ?></h4>
					<p><?php esc_html_e( 'Standalone plugin with bundled Firebase PHP-JWT library. No Composer required, no external API calls.', 'tokenlink-sso-login-for-zendesk' ); ?></p>
				</div>
				<div class="tokenlink-feature">
					<div class="tokenlink-feature-icon"><span class="dashicons dashicons-performance"></span></div>
					<h4><?php esc_html_e( 'Lightweight', 'tokenlink-sso-login-for-zendesk' ); ?></h4>
					<p><?php esc_html_e( 'Loads only ~40kb when active. JWT library is lazy-loaded only when the shortcode executes.', 'tokenlink-sso-login-for-zendesk' ); ?></p>
				</div>
				<div class="tokenlink-feature">
					<div class="tokenlink-feature-icon"><span class="dashicons dashicons-privacy"></span></div>
					<h4><?php esc_html_e( 'Privacy First', 'tokenlink-sso-login-for-zendesk' ); ?></h4>
					<p><?php esc_html_e( 'No user data stored or logged. Only passes name and email to Zendesk during authentication.', 'tokenlink-sso-login-for-zendesk' ); ?></p>
				</div>
			</div>

			<!-- Philosophy -->
			<div class="tokenlink-philosophy">
				<p><?php esc_html_e( 'Built because Zendesk SSO integration should be simple. One shortcode, three settings, done. No bloat, no upsells.', 'tokenlink-sso-login-for-zendesk' ); ?></p>
			</div>

			<!-- Creator -->
			<div class="tokenlink-creator">
				<h3><?php esc_html_e( 'About the Developer', 'tokenlink-sso-login-for-zendesk' ); ?></h3>
				<p>
					<?php
					printf(
						/* translators: %s: Link to Mailborder Systems website */
						esc_html__( 'Developed by Jerry Benton at %s. Creator of MailScanner v5 and enterprise email security infrastructure that processes millions of messages daily.', 'tokenlink-sso-login-for-zendesk' ),
						'<a href="https://mailborder.com" target="_blank" rel="noopener noreferrer" class="tokenlink-external-link">Mailborder Systems</a>'
					);
					?>
				</p>
			</div>

			<!-- Resources -->
			<div class="tokenlink-section">
				<h3><?php esc_html_e( 'Resources', 'tokenlink-sso-login-for-zendesk' ); ?></h3>
				<div class="tokenlink-resources">
					<a href="https://www.mailborder.com/zendesk-sso-plugin" target="_blank" rel="noopener noreferrer" class="tokenlink-resource-link">
						<span class="dashicons dashicons-book"></span>
						<?php esc_html_e( 'Documentation', 'tokenlink-sso-login-for-zendesk' ); ?>
					</a>
					<a href="https://github.com/jcbenton/tokenlink-sso-login-for-zendesk" target="_blank" rel="noopener noreferrer" class="tokenlink-resource-link">
						<span class="dashicons dashicons-editor-code"></span>
						<?php esc_html_e( 'GitHub', 'tokenlink-sso-login-for-zendesk' ); ?>
					</a>
					<a href="https://github.com/jcbenton/tokenlink-sso-login-for-zendesk/issues" target="_blank" rel="noopener noreferrer" class="tokenlink-resource-link">
						<span class="dashicons dashicons-flag"></span>
						<?php esc_html_e( 'Report Issue', 'tokenlink-sso-login-for-zendesk' ); ?>
					</a>
					<a href="https://mailborder.com" target="_blank" rel="noopener noreferrer" class="tokenlink-resource-link">
						<span class="dashicons dashicons-admin-site-alt3"></span>
						<?php esc_html_e( 'Mailborder', 'tokenlink-sso-login-for-zendesk' ); ?>
					</a>
				</div>
			</div>

		</div>

		<!-- Sidebar Column -->
		<div class="tokenlink-about-sidebar">
			<div class="tokenlink-donate-box">
				<h3><?php esc_html_e( 'Support Development', 'tokenlink-sso-login-for-zendesk' ); ?></h3>
				<p class="tokenlink-donate-free"><?php esc_html_e( 'Free and open source. No upsells, no premium tiers.', 'tokenlink-sso-login-for-zendesk' ); ?></p>
				<a href="https://donate.stripe.com/14AdRa6XJ1Xn8yT8KObfO00" target="_blank" rel="noopener noreferrer" class="tokenlink-donate-button">
					<?php esc_html_e( 'Make a Donation', 'tokenlink-sso-login-for-zendesk' ); ?>
				</a>
				<p class="tokenlink-donate-note">
					<?php esc_html_e( 'Helps fund continued development and updates.', 'tokenlink-sso-login-for-zendesk' ); ?>
				</p>
			</div>

			<div class="tokenlink-rate-box">
				<h3><?php esc_html_e( 'Find it Useful?', 'tokenlink-sso-login-for-zendesk' ); ?></h3>
				<p><?php esc_html_e( 'A review helps others discover the plugin.', 'tokenlink-sso-login-for-zendesk' ); ?></p>
				<div class="tokenlink-rate-stars">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
				<a href="https://wordpress.org/support/plugin/tokenlink-sso-login-for-zendesk/reviews/#new-post" target="_blank" rel="noopener noreferrer" class="tokenlink-rate-button">
					<?php esc_html_e( 'Leave a Review', 'tokenlink-sso-login-for-zendesk' ); ?>
				</a>
			</div>
		</div>

	</div>
	<?php
}

add_action(
	'admin_init',
	function () {
		register_setting(
			'tokenlink_zendesk_sso',
			'tokenlink_zendesk_subdomain',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'tokenlink_zendesk_sso_sanitize_subdomain',
				'show_in_rest'      => false,
				'default'           => '',
			)
		);
		register_setting(
			'tokenlink_zendesk_sso',
			'tokenlink_zendesk_shared_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'tokenlink_zendesk_sso_sanitize_secret',
				'show_in_rest'      => false,
				'default'           => '',
			)
		);
		register_setting(
			'tokenlink_zendesk_sso',
			'tokenlink_zendesk_login_redirect',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'tokenlink_zendesk_sso_sanitize_redirect',
				'show_in_rest'      => false,
				'default'           => '',
			)
		);

		add_settings_section(
			'tokenlink_zendesk_sso_main',
			'',
			function () {},
			'tokenlink_zendesk_sso'
		);

		add_settings_field(
			'tokenlink_zendesk_subdomain',
			__( 'Zendesk Subdomain', 'tokenlink-sso-login-for-zendesk' ),
			function () {
				printf(
					'<input type="text" name="tokenlink_zendesk_subdomain" value="%s" class="regular-text" autocomplete="off" />',
					esc_attr( get_option( 'tokenlink_zendesk_subdomain', '' ) )
				);
				echo '<p class="description">' . esc_html__( 'Specify your Zendesk subdomain — this identifies your Zendesk instance:', 'tokenlink-sso-login-for-zendesk' ) . '</p>';
				echo '<ul style="list-style-type: disc; margin-left: 20px;">';
				echo '<li>' . wp_kses(
					/* translators: %s: example subdomain wrapped in <strong> */
					sprintf( __( 'If your Zendesk URL were %1$s, enter only %2$s', 'tokenlink-sso-login-for-zendesk' ), '<strong>mailborder.zendesk.com</strong>', '<strong>mailborder</strong>' ),
					array( 'strong' => array() )
				) . '</li>';
				echo '<li>' . wp_kses(
					/* translators: %1$s: https:// in <strong>, %2$s: .zendesk.com in <strong> */
					sprintf( __( 'Do not include %1$s or %2$s', 'tokenlink-sso-login-for-zendesk' ), '<strong>https://</strong>', '<strong>.zendesk.com</strong>' ),
					array( 'strong' => array() )
				) . '</li>';
				echo '<li>' . esc_html__( 'This value is used to construct the SSO endpoint URL when generating the JWT redirect.', 'tokenlink-sso-login-for-zendesk' ) . '</li>';
				echo '</ul>';
			},
			'tokenlink_zendesk_sso',
			'tokenlink_zendesk_sso_main'
		);

		add_settings_field(
			'tokenlink_zendesk_shared_key',
			__( 'Zendesk Shared Secret', 'tokenlink-sso-login-for-zendesk' ),
			function () {
				printf(
					'<input type="password" name="tokenlink_zendesk_shared_key" value="%s" class="regular-text" autocomplete="new-password" spellcheck="false" />',
					esc_attr( get_option( 'tokenlink_zendesk_shared_key', '' ) )
				);
				echo '<p class="description">' . esc_html__( 'Follow these steps to obtain and configure your shared secret:', 'tokenlink-sso-login-for-zendesk' ) . '</p>';
				echo '<ul style="list-style-type: disc; margin-left: 20px;">';
				echo '<li>' . wp_kses(
					/* translators: %s: Zendesk Admin Center navigation path in <strong> */
					sprintf( __( 'Go to the %s.', 'tokenlink-sso-login-for-zendesk' ), '<strong>' . esc_html__( 'Zendesk Admin Center → Account → Security → Single sign-on', 'tokenlink-sso-login-for-zendesk' ) . '</strong>' ),
					array( 'strong' => array() )
				) . '</li>';
				echo '<li>' . wp_kses(
					/* translators: %s: JSON Web Token (JWT) wrapped in <strong> */
					sprintf( __( 'Create a new %s configuration.', 'tokenlink-sso-login-for-zendesk' ), '<strong>' . esc_html__( 'JSON Web Token (JWT)', 'tokenlink-sso-login-for-zendesk' ) . '</strong>' ),
					array( 'strong' => array() )
				) . '</li>';
				echo '<li>' . wp_kses(
					/* translators: %s: "Shared Secret" wrapped in <em> */
					sprintf( __( 'Copy the generated %s and paste it here.', 'tokenlink-sso-login-for-zendesk' ), '<em>' . esc_html__( 'Shared Secret', 'tokenlink-sso-login-for-zendesk' ) . '</em>' ),
					array( 'em' => array() )
				) . '</li>';
				echo '<li>' . esc_html__( 'Ensure your Zendesk account is configured to allow SSO access.', 'tokenlink-sso-login-for-zendesk' ) . '</li>';
				echo '<li>' . wp_kses(
					/* translators: %1$s: "Implementation" in <strong>, %2$s: "Remote Login URL" in <strong> */
					sprintf( __( 'See the %1$s section below for the %2$s.', 'tokenlink-sso-login-for-zendesk' ), '<strong>' . esc_html__( 'Implementation', 'tokenlink-sso-login-for-zendesk' ) . '</strong>', '<strong>' . esc_html__( 'Remote Login URL', 'tokenlink-sso-login-for-zendesk' ) . '</strong>' ),
					array( 'strong' => array() )
				) . '</li>';
				echo '</ul>';
			},
			'tokenlink_zendesk_sso',
			'tokenlink_zendesk_sso_main'
		);

		add_settings_field(
			'tokenlink_zendesk_login_redirect',
			__( 'Login Redirect Page', 'tokenlink-sso-login-for-zendesk' ),
			function () {
				printf(
					'<input type="text" name="tokenlink_zendesk_login_redirect" value="%s" class="regular-text" />',
					esc_attr( get_option( 'tokenlink_zendesk_login_redirect', '' ) )
				);
				echo '<p class="description">' . esc_html__( 'Configure where users should be redirected if they are not logged in:', 'tokenlink-sso-login-for-zendesk' ) . '</p>';
				echo '<ul style="list-style-type: disc; margin-left: 20px;">';
				echo '<li>' . esc_html__( 'Leave blank to use the default WordPress login page.', 'tokenlink-sso-login-for-zendesk' ) . '</li>';
				echo '<li>' . wp_kses(
					/* translators: %s: example relative path /login wrapped in <strong> */
					sprintf( __( 'Enter a relative path (e.g., %s) or a full URL to a custom login page.', 'tokenlink-sso-login-for-zendesk' ), '<strong>/login</strong>' ),
					array( 'strong' => array() )
				) . '</li>';
				echo '<li>' . esc_html__( 'After logging in, users are automatically redirected back to the original local TokenLink Zendesk SSO page.', 'tokenlink-sso-login-for-zendesk' ) . '</li>';
				echo '</ul>';
			},
			'tokenlink_zendesk_sso',
			'tokenlink_zendesk_sso_main'
		);

		// Implementation instructions.
		add_settings_field(
			'tokenlink_zendesk_implementation',
			__( 'Implementation', 'tokenlink-sso-login-for-zendesk' ),
			function () {
				echo '<p class="description">' . esc_html__( 'Follow these steps to complete your integration:', 'tokenlink-sso-login-for-zendesk' ) . '</p>';
				echo '<ul style="list-style-type: disc; margin-left: 20px;">';
				echo '<li>' . wp_kses(
					/* translators: %s: shortcode tag wrapped in <code> */
					sprintf( __( 'Create a blank WordPress page and insert this shortcode: %s', 'tokenlink-sso-login-for-zendesk' ), '<code>[tokenlink_zendesk_login]</code>' ),
					array( 'code' => array() )
				) . '</li>';
				echo '<li>' . wp_kses(
					/* translators: %s: example menu label "Support" wrapped in <strong> */
					sprintf( __( '(Optional) Create a menu item that links to that page (e.g., %s).', 'tokenlink-sso-login-for-zendesk' ), '<strong>' . esc_html__( 'Support', 'tokenlink-sso-login-for-zendesk' ) . '</strong>' ),
					array( 'strong' => array() )
				) . '</li>';
				echo '<li>' . wp_kses(
					/* translators: %s: "Remote Login URL" wrapped in <strong> */
					sprintf( __( 'In your zendesk.com SSO settings, set the full page URL you created above as your %s.', 'tokenlink-sso-login-for-zendesk' ), '<strong>' . esc_html__( 'Remote Login URL', 'tokenlink-sso-login-for-zendesk' ) . '</strong>' ),
					array( 'strong' => array() )
				) . '</li>';
				echo '<li>' . esc_html__( 'The plugin will automatically handle authentication and redirect users to Zendesk via secure JWT SSO.', 'tokenlink-sso-login-for-zendesk' ) . '</li>';
				echo '</ul>';
			},
			'tokenlink_zendesk_sso',
			'tokenlink_zendesk_sso_main'
		);

	}
);

/**
 * Sanitize the configured login-redirect option.
 *
 * Accepts an empty string, an absolute http/https URL, or a site-rooted relative path
 * starting with "/". Anything else is rejected (stored as empty) to avoid foot-guns like
 * `tel:`, `mailto:`, protocol-relative URLs, or path-less inputs that produce broken redirects.
 *
 * @param string $value Raw value from the settings form.
 * @return string Sanitized value safe for storage.
 */
function tokenlink_zendesk_sso_sanitize_redirect( $value ) {
	$value = is_string( $value ) ? trim( wp_unslash( $value ) ) : '';
	if ( '' === $value ) {
		return '';
	}
	if ( preg_match( '#^https?://#i', $value ) ) {
		return (string) esc_url_raw( $value, array( 'http', 'https' ) );
	}
	if ( '/' === $value[0] ) {
		// Site-rooted relative path. Reject protocol-relative `//host/...`.
		if ( isset( $value[1] ) && '/' === $value[1] ) {
			add_settings_error(
				'tokenlink_zendesk_login_redirect',
				'tokenlink_zendesk_login_redirect_invalid',
				__( 'Login Redirect Page: protocol-relative URLs are not allowed.', 'tokenlink-sso-login-for-zendesk' )
			);
			return '';
		}
		return (string) esc_url_raw( $value );
	}
	add_settings_error(
		'tokenlink_zendesk_login_redirect',
		'tokenlink_zendesk_login_redirect_invalid',
		__( 'Login Redirect Page must be empty, a path starting with "/", or a full http(s):// URL.', 'tokenlink-sso-login-for-zendesk' )
	);
	return '';
}

/**
 * Sanitize the Zendesk subdomain at save time.
 *
 * Enforces the DNS label rules Zendesk actually uses: lowercase alphanumeric plus
 * internal hyphens, 1–63 chars, no leading or trailing hyphen. Invalid input is
 * rejected (stored as empty) with an admin notice. This prevents an admin who pastes
 * a full URL or a malicious string from causing the JWT to be sent to an attacker
 * controlled host.
 *
 * @param string $value Raw value from the settings form.
 * @return string Sanitized subdomain safe for URL interpolation.
 */
function tokenlink_zendesk_sso_sanitize_subdomain( $value ) {
	$value = is_string( $value ) ? strtolower( trim( wp_unslash( $value ) ) ) : '';
	if ( '' === $value ) {
		return '';
	}
	// Strip trailing `.zendesk.com` if a user pasted the full host by mistake.
	$value = preg_replace( '/\.zendesk\.com$/i', '', $value );
	if ( ! preg_match( '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $value ) ) {
		add_settings_error(
			'tokenlink_zendesk_subdomain',
			'tokenlink_zendesk_subdomain_invalid',
			__( 'Zendesk Subdomain must contain only lowercase letters, digits, and hyphens (e.g., "mailborder").', 'tokenlink-sso-login-for-zendesk' )
		);
		return '';
	}
	return $value;
}

/**
 * Sanitize the Zendesk shared secret at save time.
 *
 * Trim only — the full sanitize_text_field treatment can strip characters that are
 * legitimate in random base64-like secrets (e.g., `<`, `>`, sequences resembling tags).
 * The secret is never echoed except via esc_attr() into a password input.
 *
 * @param string $value Raw value from the settings form.
 * @return string Trimmed secret.
 */
function tokenlink_zendesk_sso_sanitize_secret( $value ) {
	return is_string( $value ) ? trim( wp_unslash( $value ) ) : '';
}

/**
 * Whether both the Zendesk subdomain and shared secret are configured.
 *
 * @return bool
 */
function tokenlink_zendesk_sso_is_configured() {
	$subdomain = trim( (string) get_option( 'tokenlink_zendesk_subdomain', '' ) );
	$secret    = trim( (string) get_option( 'tokenlink_zendesk_shared_key', '' ) );
	return '' !== $subdomain && '' !== $secret;
}

/*
 * ----------------------------------------------------------------------
 * SSO redirect dispatch.
 *
 * The actual redirect runs on `template_redirect` — before any output —
 * so headers are always available. The [tokenlink_zendesk_login]
 * shortcode acts only as a marker that flags a page as the SSO entry
 * point; in normal operation that shortcode's render callback is never
 * reached, because template_redirect will have already exited the request.
 * ----------------------------------------------------------------------
 */

/**
 * On singular requests whose content contains [tokenlink_zendesk_login],
 * redirect to the WP login (if anonymous) or to Zendesk via JWT (if signed in).
 *
 * Runs at priority 1 so we beat most caching/security plugins that hook
 * `template_redirect` and may short-circuit the request.
 *
 * @return void
 */
function tokenlink_zendesk_sso_template_redirect() {
	if ( is_admin()
		|| is_feed()
		|| is_robots()
		|| is_preview()
		|| is_customize_preview()
		|| ( defined( 'REST_REQUEST' ) && REST_REQUEST )
		|| ( defined( 'DOING_AJAX' ) && DOING_AJAX )
		|| ( defined( 'DOING_CRON' ) && DOING_CRON )
		|| ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST )
		|| ( defined( 'WP_CLI' ) && WP_CLI )
		|| ! is_singular() ) {
		return;
	}

	$post = get_post();
	if ( ! $post instanceof WP_Post ) {
		return;
	}

	// Honour page-level password protection.
	if ( post_password_required( $post ) ) {
		return;
	}

	$has_marker = has_shortcode( $post->post_content, 'tokenlink_zendesk_login' );

	/**
	 * Filter: allow page builders that store content outside post_content
	 * (Elementor, Bricks, etc.) to opt a page in to SSO redirection.
	 *
	 * @param bool    $has_marker Whether this page should trigger SSO.
	 * @param WP_Post $post       Current post object.
	 */
	$has_marker = (bool) apply_filters( 'tokenlink_zendesk_sso_page_match', $has_marker, $post );

	if ( ! $has_marker ) {
		return;
	}

	$current_url = get_permalink( $post );

	if ( ! is_user_logged_in() ) {
		tokenlink_zendesk_sso_redirect_to_login( $current_url );
		// Function does not return.
	}

	tokenlink_zendesk_sso_redirect_to_zendesk();
	// If we returned, configuration is incomplete — let the shortcode render the message.
}
add_action( 'template_redirect', 'tokenlink_zendesk_sso_template_redirect', 1 );

/**
 * Send an anonymous visitor to the configured (or default) login URL,
 * appending redirect_to so they come back to the SSO page after login.
 *
 * @param string $current_url Permalink of the page to return to after login.
 * @return void
 */
function tokenlink_zendesk_sso_redirect_to_login( $current_url ) {
	$login_redirect = trim( (string) get_option( 'tokenlink_zendesk_login_redirect', '' ) );
	$is_external    = false;

	if ( '' === $login_redirect || '/' === $login_redirect ) {
		$redirect_url = wp_login_url( $current_url );
	} else {
		if ( preg_match( '#^https?://#i', $login_redirect ) ) {
			$redirect_url = $login_redirect;
			$is_external  = true;
		} else {
			$redirect_url = home_url( $login_redirect );
		}
		// add_query_arg handles encoding internally — passing a raw URL avoids double-encoding.
		$redirect_url = add_query_arg( 'redirect_to', $current_url, $redirect_url );
	}

	// Loop guard: if the resolved redirect resolves to the same page that triggered this redirect,
	// fall back to the default WP login URL to avoid an infinite redirect.
	$current_host = wp_parse_url( $current_url, PHP_URL_HOST );
	$current_path = wp_parse_url( $current_url, PHP_URL_PATH );
	$target_host  = wp_parse_url( $redirect_url, PHP_URL_HOST );
	$target_path  = wp_parse_url( $redirect_url, PHP_URL_PATH );
	if ( $current_host && $target_host && $current_host === $target_host && $current_path === $target_path ) {
		$redirect_url = wp_login_url( $current_url );
		$is_external  = false;
	}

	nocache_headers();

	if ( $is_external ) {
		// Operator explicitly opted in to an external login URL; bypass safe-redirect's host allowlist.
		$ext_host = wp_parse_url( $redirect_url, PHP_URL_HOST );
		if ( $ext_host ) {
			add_filter(
				'allowed_redirect_hosts',
				function ( $hosts ) use ( $ext_host ) {
					$hosts[] = $ext_host;
					return $hosts;
				}
			);
		}
	}

	wp_safe_redirect( $redirect_url, 303 );
	exit;
}

/**
 * Mint a short-lived JWT and redirect the current user to Zendesk's SSO endpoint.
 *
 * Returns silently (without redirecting) when subdomain or shared secret is unset
 * or the stored subdomain fails revalidation, so the shortcode can render the
 * "not configured" notice instead.
 *
 * @return void
 */
function tokenlink_zendesk_sso_redirect_to_zendesk() {
	$subdomain = trim( (string) get_option( 'tokenlink_zendesk_subdomain', '' ) );
	$secret    = trim( (string) get_option( 'tokenlink_zendesk_shared_key', '' ) );

	if ( '' === $subdomain || '' === $secret ) {
		return;
	}

	// Defense in depth: re-validate the stored subdomain at use time. If it was tampered
	// with via direct DB access (bypassing the save-time sanitize callback), refuse to ship
	// a JWT to an unverified host.
	if ( ! preg_match( '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $subdomain ) ) {
		return;
	}

	// Lazy-load the JWT library only when we are about to use it.
	if ( ! class_exists( '\\Firebase\\JWT\\JWT' ) ) {
		$dir = __DIR__ . '/includes/jwt/';
		require_once $dir . 'Key.php';
		require_once $dir . 'JWT.php';
	}

	$user = wp_get_current_user();
	$now  = time();

	// Normalize name and email: strip HTML, decode entities, lowercase email so
	// Zendesk does not create distinct user records per case variation.
	$name = trim( wp_strip_all_tags( html_entity_decode( (string) $user->display_name, ENT_QUOTES, 'UTF-8' ) ) );
	if ( '' === $name ) {
		$name = (string) $user->user_login;
	}
	$email = strtolower( sanitize_email( (string) $user->user_email ) );

	$payload = array(
		'iat'   => $now,
		// 60s skew slack — recipient clocks may be slightly ahead of ours.
		'nbf'   => $now - 60,
		// Short-lived token: 2 min is long enough for the browser to follow the redirect, short enough to limit replay.
		'exp'   => $now + 120,
		// URL-safe random JWT ID (128 bits of entropy, no `+/=` characters).
		'jti'   => rtrim( strtr( base64_encode( random_bytes( 16 ) ), '+/', '-_' ), '=' ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		'name'  => $name,
		'email' => $email,
	);

	$jwt          = \Firebase\JWT\JWT::encode( $payload, $secret, 'HS256' );
	$redirect_url = 'https://' . $subdomain . '.zendesk.com/access/jwt?jwt=' . rawurlencode( $jwt );

	nocache_headers();
	// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- External redirect to Zendesk SSO endpoint is intentional.
	wp_redirect( $redirect_url, 303 );
	exit;
}

/*
 * Shortcode acts as a page marker. In normal operation template_redirect
 * has already exited the request, so this callback only runs when the
 * plugin is not yet configured — in which case it shows the admin notice
 * inline on the page.
 */
add_shortcode(
	'tokenlink_zendesk_login',
	function () {
		if ( ! tokenlink_zendesk_sso_is_configured() ) {
			return '<p>' . esc_html__( 'TokenLink Zendesk SSO is not configured properly. Please contact the administrator.', 'tokenlink-sso-login-for-zendesk' ) . '</p>';
		}

		// Should be unreachable in normal flow: template_redirect would have already redirected.
		return '';
	}
);
