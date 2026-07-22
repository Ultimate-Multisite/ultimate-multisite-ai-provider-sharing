# Main Site AI Providers

Network-activate this plugin on a WordPress multisite installation. AI providers
configured on the network main site are then available on every subsite without
copying API keys into subsite databases.

It supports the WordPress core Connectors API providers, including OpenAI,
Anthropic, and Google, plus Superdav AI Agent's bundled Superdav AI provider.

The plugin has no settings. Configure provider credentials only on the network
main site. Subsite reads of `connectors_ai_*_api_key` options receive the
main-site value; an absent main-site credential disables that provider on
subsites rather than falling back to a subsite-specific key.

## Installation

1. Copy this repository to `wp-content/plugins/main-site-ai-providers`.
2. In Network Admin, activate **Main Site AI Providers**.
3. Configure AI providers on the network main site.

The provider plugins themselves must also be network-active or active on each
subsite so they can register with the WordPress AI Client registry.

## Security model

Credentials are read from the main site at runtime and are not copied, exposed,
or written through from child sites. The plugin only bridges credentials using
the WordPress Connectors API naming convention.
