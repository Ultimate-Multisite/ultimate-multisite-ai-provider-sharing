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

		$credential = get_blog_option(get_main_site_id(), $option, '');
		$credential = is_string($credential) ? $credential : '';

		self::$credentials[ $option ] = $credential;

		return $credential;
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
