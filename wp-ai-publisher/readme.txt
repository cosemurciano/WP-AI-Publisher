=== WP AI Publisher ===
Contributors: wp-ai-publisher
Tags: ai, publishing, admin, openai, drafts
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A modular foundation for future AI-assisted WordPress publishing workflows.

== Description ==

WP AI Publisher phase 1 provides the secure plugin foundation for future AI-assisted article generation, structured drafts, media workflows, SEO metadata, internal links, knowledge indexing, job queues, dry-runs, duplicate checks, and assisted publishing.

This initial release intentionally does not generate articles, images, embeddings, or remote AI requests. All future AI functionality is routed through the central AI Provider Adapter.

== Installation ==

1. Upload the `wp-ai-publisher` directory to `/wp-content/plugins/` or install a ZIP through Plugins > Add New > Upload Plugin.
2. Activate WP AI Publisher from the Plugins screen.
3. Open WP AI Publisher > Dashboard.
4. Review WP AI Publisher > Settings.
5. Review WP AI Publisher > System Status.

== Frequently Asked Questions ==

= Does this version call OpenAI? =

No. Version 0.1.0 only detects possible configuration defensively and never performs remote OpenAI requests.

= Does this version generate posts? =

No. Article generation will be implemented in a later phase.

== Changelog ==

= 0.1.0 =
* Initial phase 1 plugin foundation.
* Admin dashboard, settings, and system status screens.
* Database log table scaffolding.
* Central AI Provider Adapter stubs.
