<?php
/*
Plugin Name: TokenLink SSO Login for Zendesk
Plugin URI: https://www.mailborder.com/zendesk-sso-plugin
Description: Provides secure JWT-based single sign-on between WordPress and Zendesk. Developed by Mailborder Systems. Go to [Settings > TokenLink - Zendesk ] for configuration.
Version: 1.0.6
Author: Mailborder Systems (Jerry Benton)
Author URI: https://www.mailborder.com
License: GPL v3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

This plugin includes portions of the firebase/php-jwt library,
© 2011–2025 Firebase, licensed under the BSD 3-Clause License.
See includes/jwt/LICENSE for details.
*/

if (!defined('ABSPATH')) exit;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/* ---------------------------------------------------------------------- */
/* Settings page under Settings → Zendesk SSO */
/* ---------------------------------------------------------------------- */
add_action('admin_menu', function () {
    add_options_page(
        'TokenLink Zendesk SSO Settings',
        'TokenLink - Zendesk',
        'manage_options',
        'tokenlink_zendesk_sso',
        'tokenlink_zendesk_sso_settings_page'
    );
});

function tokenlink_zendesk_sso_settings_page() {
    ?>
    <div class="wrap">
        <h1>TokenLink SSO Login for Zendesk - Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('tokenlink_zendesk_sso');
            do_settings_sections('tokenlink_zendesk_sso');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', function () {
    register_setting('tokenlink_zendesk_sso', 'tokenlink_zendesk_subdomain', ['sanitize_callback' => 'sanitize_text_field']);
    register_setting('tokenlink_zendesk_sso', 'tokenlink_zendesk_shared_key', ['sanitize_callback' => 'sanitize_text_field']);
    register_setting('tokenlink_zendesk_sso', 'tokenlink_zendesk_login_redirect', ['sanitize_callback' => 'sanitize_text_field']);

    add_settings_section(
        'tokenlink_zendesk_sso_main',
        '',
        function () {},
        'tokenlink_zendesk_sso'
    );

    add_settings_field(
        'tokenlink_zendesk_subdomain',
        'Zendesk Subdomain',
        function () {
            printf(
                '<input type="text" name="tokenlink_zendesk_subdomain" value="%s" class="regular-text" />',
                esc_attr(get_option('tokenlink_zendesk_subdomain', ''))
            );
            echo '<p class="description">Specify your Zendesk subdomain — this identifies your Zendesk instance:</p>';
            echo '<ul style="list-style-type: disc; margin-left: 20px;">';
            echo '<li>If your Zendesk URL were <strong>mailborder.zendesk.com</strong>, enter only <strong>mailborder</strong></li>';
            echo '<li>Do not include <strong>https://</strong> or <strong>.zendesk.com</strong></li>';
            echo '<li>This value is used to construct the SSO endpoint URL when generating the JWT redirect.</li>';
            echo '</ul>';
        },
        'tokenlink_zendesk_sso',
        'tokenlink_zendesk_sso_main'
    );

    add_settings_field(
        'tokenlink_zendesk_shared_key',
        'Zendesk Shared Secret',
        function () {
            printf(
                '<input type="text" name="tokenlink_zendesk_shared_key" value="%s" class="regular-text" />',
                esc_attr(get_option('tokenlink_zendesk_shared_key', ''))
            );
            echo '<p class="description">Follow these steps to obtain and configure your shared secret:</p>';
            echo '<ul style="list-style-type: disc; margin-left: 20px;">';
            echo '<li>Go to the <strong>Zendesk Admin Center → Account → Security → Single sign-on</strong>.</li>';
            echo '<li>Create a new <strong>JSON Web Token (JWT)</strong> configuration.</li>';
            echo '<li>Copy the generated <em>Shared Secret</em> and paste it here.</li>';
            echo '<li>Ensure your Zendesk account is configured to allow SSO access.</li>';
            echo '<li>See the <strong>Implementation</strong> section below for the <strong>Remote Login URL</strong>.</li>';
            echo '</ul>';
        },
        'tokenlink_zendesk_sso',
        'tokenlink_zendesk_sso_main'
    );

    add_settings_field(
        'tokenlink_zendesk_login_redirect',
        'Login Redirect Page',
        function () {
            printf(
                '<input type="text" name="tokenlink_zendesk_login_redirect" value="%s" class="regular-text" />',
                esc_attr(get_option('tokenlink_zendesk_login_redirect', ''))
            );
            echo '<p class="description">Configure where users should be redirected if they are not logged in:</p>';
            echo '<ul style="list-style-type: disc; margin-left: 20px;">';
            echo '<li>Leave blank to use the default WordPress login page.</li>';
            echo '<li>Enter a relative path (e.g., <strong>/login</strong>) or a full URL to a custom login page.</li>';
            echo '<li>After logging in, users are automatically redirected back to the original local TokenLink Zendesk SSO page.</li>';
            echo '</ul>';
        },
        'tokenlink_zendesk_sso',
        'tokenlink_zendesk_sso_main'
    );

    // Implementation instructions
    add_settings_field(
        'tokenlink_zendesk_implementation',
        'Implementation',
        function () {
            echo '<p class="description">Follow these steps to complete your integration:</p>';
            echo '<ul style="list-style-type: disc; margin-left: 20px;">';
            echo '<li>Create a blank WordPress page and insert this shortcode: <code>&#91;tokenlink_zendesk_login&#93;</code></li>';
            echo '<li>(Optional) Create a menu item that links to that page (e.g., <strong>Support</strong>).</li>';
            echo '<li>In your zendesk.com SSO settings, set the full page URL you created above as your <strong>Remote Login URL</strong>.</li>';
            echo '<li>The plugin will automatically handle authentication and redirect users to Zendesk via secure JWT SSO.</li>';
            echo '</ul>';
        },
        'tokenlink_zendesk_sso',
        'tokenlink_zendesk_sso_main'
    );

    // Thank You / Rating section
    add_settings_field(
        'tokenlink_zendesk_thankyou',
        'Thank You',
        function () {
            echo '<p class="description">If you find this plugin useful, please consider leaving a quick rating on <a href="https://wordpress.org/plugins/tokenlink-sso-login-for-zendesk/" target="_blank">WordPress.org</a>. Your feedback helps support continued development.</p>';
            echo '<p class="description">The plugin is totally free. However, if you wish to show your support you can <a href="https://donate.stripe.com/14AdRa6XJ1Xn8yT8KObfO00" target="_blank">send a donation here</a>.</p>';
        },
        'tokenlink_zendesk_sso',
        'tokenlink_zendesk_sso_main'
    );
});

/* ---------------------------------------------------------------------- */
/* Shortcode: [tokenlink_zendesk_login] */
/* Redirects logged-in users to Zendesk using JWT SSO */
/* ---------------------------------------------------------------------- */
add_shortcode('tokenlink_zendesk_login', function () {
    if (!is_user_logged_in()) {
        // Pull configured redirect URL or default to WordPress login
        $login_redirect = trim(get_option('tokenlink_zendesk_login_redirect', ''));

        // Default fallback to the native WordPress login page
        if (empty($login_redirect) || $login_redirect === '/') {
            $redirect_url = wp_login_url();
        } else {
            // Handle relative or full URLs cleanly
            if (!preg_match('#^https?://#', $login_redirect)) {
                $redirect_url = home_url($login_redirect);
            } else {
                $redirect_url = $login_redirect;
            }
        }

        // Append redirect_to for returning after login
        $redirect_url = add_query_arg('redirect_to', urlencode(get_permalink()), $redirect_url);

        wp_redirect($redirect_url);
        exit;
    }

    // Lazy-load JWT library ONLY at runtime
    if (!class_exists('\\Firebase\\JWT\\JWT')) {
        $dir = __DIR__ . '/includes/jwt/';
        require_once $dir . 'Key.php';
        require_once $dir . 'JWT.php';
    }

    $user = wp_get_current_user();
    $subdomain = sanitize_text_field(get_option('tokenlink_zendesk_subdomain'));
    $secret = sanitize_text_field(get_option('tokenlink_zendesk_shared_key'));

    if (empty($subdomain) || empty($secret)) {
        return '<p>TokenLink Zendesk SSO is not configured properly. Please contact the administrator.</p>';
    }

    $payload = [
        'iat'   => time(),
        'jti'   => base64_encode(random_bytes(16)),
        'name'  => $user->display_name,
        'email' => $user->user_email
    ];

    // Use fully qualified class path to avoid namespace parsing at load
    $jwt = \Firebase\JWT\JWT::encode($payload, $secret, 'HS256');
    $redirect_url = "https://{$subdomain}.zendesk.com/access/jwt?jwt={$jwt}";

    wp_redirect($redirect_url);
    exit;
});
