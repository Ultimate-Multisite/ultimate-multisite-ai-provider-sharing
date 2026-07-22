<?php
/**
 * Main Site AI Providers option bridge.
 *
 * @package Main_Site_AI_Providers
 */

defined('ABSPATH') || exit;

/**
 * Shares main-site AI connector credentials with child sites.
 *
 * WordPress core and Superdav AI Agent both load AI-provider credentials from
 * `connectors_ai_*_api_key` options. Intercepting that established read path
 * means provider plugins continue to register and authenticate normally,
 * without copying credentials into a child site's database.
 */
final class MSAIP_Main_Site_AI_Providers {

	/**
	 * Cache of main-site credentials for the current request.
	 *
	 * @var array<string, string>
	 */
	private static $credentials = [];

	/**
	 * Register the option bridge before AI providers register on init.
	 *
	 * @return void
	 */
	public static function bootstrap(): void {
		if ( ! is_multisite() ) {
			return;
		}

		add_filter('pre_option', [__CLASS__, 'inherit_main_site_credential'], 10, 3);
	}

	/**
	 * Return the main site's credential for an AI connector option.
	 *
	 * Returning an empty string when the main site has no credential is
	 * intentional: a child site must not silently use an independently stored
	 * credential when this plugin is active. Credentials are controlled solely
	 * by the network main site and are never copied to child sites.
	 *
	 * @param mixed  $pre_option    Value supplied by an earlier option filter.
	 * @param string $option        Option being read.
	 * @param mixed  $default_value Default value supplied to get_option().
	 * @return mixed Main-site credential for AI connector options; otherwise the
	 *               unmodified pre-option value.
	 */
	public static function inherit_main_site_credential($pre_option, string $option, $default_value) {
		unset($default_value);

		if ( is_main_site() || ! self::is_ai_connector_credential($option) ) {
			return $pre_option;
		}

		if ( isset(self::$credentials[ $option ]) ) {
			return self::$credentials[ $option ];
		}

		$credential = self::get_filtered_main_site_credential($option);

		self::$credentials[ $option ] = $credential;

		return $credential;
	}

	/**
	 * Read a main-site credential through the normal filtered get_option() path.
	 *
	 * WordPress AI key encryption stores connector credentials outside wp_options
	 * and supplies them through option/default_option filters. get_blog_option() can
	 * return the raw empty placeholder before those filters can provide the secret,
	 * so switch to the main site and call get_option() while temporarily removing
	 * this plugin broad pre_option bridge to avoid recursion.
	 *
	 * @param string $option Option name.
	 * @return string Filtered main-site credential, or an empty string if absent.
	 */
	private static function get_filtered_main_site_credential(string $option): string {
		$main_site_id = get_main_site_id();
		$switched     = false;

		remove_filter("pre_option", array(__CLASS__, "inherit_main_site_credential"), 10);

		if ( get_current_blog_id() !== $main_site_id ) {
			switch_to_blog($main_site_id);
			$switched = true;
		}

		$credential = get_option($option, "");

		if ( "" === $credential ) {
			$credential = self::get_wordpress_ai_secret($option);
		}

		if ( $switched ) {
			restore_current_blog();
		}

		add_filter("pre_option", array(__CLASS__, "inherit_main_site_credential"), 10, 3);

		return is_string($credential) ? $credential : "";
	}

	/**
	 * Read a WordPress AI encrypted connector secret from the main site.
	 *
	 * This fallback covers subsites where the main-site-only WordPress AI plugin
	 * is not active, so its option filters and connector registry are unavailable.
	 *
	 * @param string $option Option name.
	 * @return string Decrypted secret, or an empty string if unavailable.
	 */
	private static function get_wordpress_ai_secret(string $option): string {
		if ( ! self::load_wordpress_ai_secrets_api() ) {
			return "";
		}

		$connector_id = self::connector_id_from_option($option);
		if ( "" === $connector_id ) {
			return "";
		}

		$manager_class  = "\\WordPress\\AI\\Vendor\\Secrets\\Secrets_Manager";
		$provider_class = "\\WordPress\\AI\\Vendor\\Secrets\\Secrets_Provider_Encrypted_Options";
		$secrets_class  = "\\WordPress\\AI\\Vendor\\Secrets\\Secrets";

		try {
			$manager = $manager_class::get_instance();
			if ( null === $manager->get_provider("encrypted-options") ) {
				$manager->register_provider(new $provider_class());
			}
			if ( null === $manager->get_active_provider_id() ) {
				$manager->select_provider();
			}

			$secret = $secrets_class::get("ai/" . $connector_id . "_api_key", array("plugin" => "ai"));
		} catch (\Throwable $e) {
			return "";
		}

		return is_string($secret) ? $secret : "";
	}

	/**
	 * Load the WordPress AI bundled Secrets API when the AI plugin is main-site-only.
	 *
	 * @return bool Whether the API classes are available.
	 */
	private static function load_wordpress_ai_secrets_api(): bool {
		if ( class_exists("\\WordPress\\AI\\Vendor\\Secrets\\Secrets") ) {
			return true;
		}

		$base = WP_PLUGIN_DIR . "/ai/includes/Vendor/Secrets/";
		$files = array(
			"Secrets_Exception.php",
			"Secrets_Context.php",
			"Secrets_Audit.php",
			"Secrets_Provider.php",
			"Secrets_Manager.php",
			"Secrets.php",
			"Secrets_Provider_Encrypted_Options.php",
		);

		foreach ( $files as $file ) {
			$path = $base . $file;
			if ( ! file_exists($path) ) {
				return false;
			}
			require_once $path;
		}

		return class_exists("\\WordPress\\AI\\Vendor\\Secrets\\Secrets");
	}

	/**
	 * Convert a connector option name to the WordPress AI secret connector id.
	 *
	 * @param string $option Option name.
	 * @return string Connector id.
	 */
	private static function connector_id_from_option(string $option): string {
		$slug = substr($option, strlen("connectors_ai_"), -strlen("_api_key"));

		$map = array(
			"sd_ai_agent_cloud" => "sd-ai-agent-cloud",
			"ultimate_ai_connector_anthropic_max" => "ultimate-ai-connector-anthropic-max",
		);

		if ( isset($map[$slug]) ) {
			return $map[$slug];
		}

		return str_replace("_", "-", $slug);
	}

	/**
	 * Determine whether an option stores an AI-provider API key.
	 *
	 * This covers the provider option convention used by the WordPress core
	 * Connectors API, including OpenAI, Anthropic, Google, and Superdav AI
	 * Agent's `connectors_ai_sd_ai_agent_cloud_api_key` option.
	 *
	 * @param string $option Option name.
	 * @return bool Whether the option is an AI-provider credential.
	 */
	private static function is_ai_connector_credential(string $option): bool {
		return 0 === strpos($option, 'connectors_ai_') && '_api_key' === substr($option, -8);
	}
}
