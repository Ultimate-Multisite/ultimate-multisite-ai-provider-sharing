<?php
/**
 * Focused unit tests for Main Site AI Providers.
 *
 * Run: php tests/test-main-site-ai-providers.php
 */

define('ABSPATH', __DIR__);

$GLOBALS['msap_current_blog_id'] = 2;
$GLOBALS['msap_main_blog_id']    = 1;
$GLOBALS['msap_actions']         = [];
$GLOBALS['msap_filters']         = [];
$GLOBALS['msap_options']         = [
	1 => [
		'connectors_ai_openai_api_key'            => 'main-openai-key',
		'connectors_ai_sd_ai_agent_cloud_api_key' => 'main-superdav-key',
	],
	2 => [
		'connectors_ai_openai_api_key'            => 'child-openai-key',
		'connectors_ai_sd_ai_agent_cloud_api_key' => 'child-superdav-key',
	],
];

/**
 * Record a WordPress action registration.
 *
 * @param string   $hook     Action name.
 * @param callable $callback Action callback.
 * @param int      $priority Action priority.
 * @return void
 */
function add_action(string $hook, $callback, int $priority = 10): void {
	$GLOBALS['msap_actions'][ $hook ][ $priority ][] = $callback;
}

/**
 * Record a WordPress filter registration.
 *
 * @param string   $hook          Filter name.
 * @param callable $callback      Filter callback.
 * @param int      $priority      Filter priority.
 * @param int      $accepted_args Callback argument count.
 * @return void
 */
function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void {
	unset($accepted_args);

	$GLOBALS['msap_filters'][ $hook ][ $priority ][] = $callback;
}

/**
 * Report a multisite test environment.
 *
 * @return bool Whether multisite is enabled.
 */
function is_multisite(): bool {
	return true;
}

/**
 * Report whether the mocked request serves the main site.
 *
 * @return bool Whether the request serves the main site.
 */
function is_main_site(): bool {
	return $GLOBALS['msap_current_blog_id'] === $GLOBALS['msap_main_blog_id'];
}

/**
 * Return the mocked network main-site ID.
 *
 * @return int Main-site ID.
 */
function get_main_site_id(): int {
	return $GLOBALS['msap_main_blog_id'];
}

/**
 * Return an option from a mocked site.
 *
 * @param int    $blog_id       Site ID.
 * @param string $option        Option name.
 * @param mixed  $default_value Default value.
 * @return mixed Stored option value or the default.
 */
function get_blog_option(int $blog_id, string $option, $default_value = false) {
	$original_blog_id = $GLOBALS['msap_current_blog_id'];

	$GLOBALS['msap_current_blog_id'] = $blog_id;

	$pre_option = MSAIP_Main_Site_AI_Providers::inherit_main_site_credential(false, $option, $default_value);

	$GLOBALS['msap_current_blog_id'] = $original_blog_id;

	if ( false !== $pre_option ) {
		return $pre_option;
	}

	return $GLOBALS['msap_options'][ $blog_id ][ $option ] ?? $default_value;
}

require dirname(__DIR__) . '/main-site-ai-providers.php';

/**
 * Assert strict equality.
 *
 * @param mixed $expected Expected value.
 * @param mixed $actual   Actual value.
 * @return void
 * @throws RuntimeException When the values differ.
 */
function assert_same($expected, $actual): void {
	if ( $expected !== $actual ) {
		throw new RuntimeException('Assertion failed.');
	}
}

call_user_func($GLOBALS['msap_actions']['plugins_loaded'][0][0]);

assert_same(true, isset($GLOBALS['msap_filters']['pre_option'][10][0]));

assert_same(
	'main-openai-key',
	MSAIP_Main_Site_AI_Providers::inherit_main_site_credential(false, 'connectors_ai_openai_api_key', '')
);

assert_same(
	'main-superdav-key',
	MSAIP_Main_Site_AI_Providers::inherit_main_site_credential(false, 'connectors_ai_sd_ai_agent_cloud_api_key', '')
);

assert_same(
	false,
	MSAIP_Main_Site_AI_Providers::inherit_main_site_credential(false, 'unrelated_option', '')
);

$GLOBALS['msap_current_blog_id'] = 1;

assert_same(
	false,
	MSAIP_Main_Site_AI_Providers::inherit_main_site_credential(false, 'connectors_ai_openai_api_key', '')
);

$GLOBALS['msap_current_blog_id'] = 2;
unset($GLOBALS['msap_options'][1]['connectors_ai_anthropic_api_key']);
$GLOBALS['msap_options'][2]['connectors_ai_anthropic_api_key'] = 'child-anthropic-key';

assert_same(
	'',
	MSAIP_Main_Site_AI_Providers::inherit_main_site_credential(false, 'connectors_ai_anthropic_api_key', '')
);
