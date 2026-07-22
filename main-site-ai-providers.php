<?php
/**
 * Plugin Name: Main Site AI Providers
 * Description: Makes AI providers configured on a multisite network's main site available to every subsite.
 * Version: 1.0.0
 * Requires at least: 6.1
 * Requires PHP: 7.4
 * Network: true
 * Text Domain: main-site-ai-providers
 *
 * @package Main_Site_AI_Providers
 */

defined('ABSPATH') || exit;

require_once __DIR__ . '/includes/class-msaip-main-site-ai-providers.php';

add_action('plugins_loaded', [MSAIP_Main_Site_AI_Providers::class, 'bootstrap'], 0);
