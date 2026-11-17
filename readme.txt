=== Visibility Logic for Elementor and the Block Editor ===
Contributors: staxwp, kierantaylorio, codezz, rtynio, geowrge
Tags: elementor, block visibility, display conditions, conditional visibility, gutenberg
Requires at least: 6.0
Requires PHP: 7.4
Tested up to: 7.1.4
Stable tag: 3.0.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Display conditions for Elementor and the block editor: show or hide widgets, containers and blocks by user role, ACF field, device, date, browser.

== Description ==

**Visibility Logic** adds focused **display conditions** to every Elementor widget, section and container, every Elementor 4 element, and every block in the block editor (Gutenberg). Use conditional display rules to control exactly who sees what, when, and on which device without writing a single line of code.

By default, hidden elements are removed from the HTML instead of only hidden with CSS. If you need the markup to stay in the DOM, enable the **Keep HTML / Hide by CSS** option.

= When to use Visibility Logic =
- **Restrict content** to logged-in users, specific roles, or subscribers
- **Control membership content** with Elementor sections for members, subscribers, guests, or specific roles
- **Schedule content** to appear during a sale, event, or launch window
- **Personalize pages** based on user meta, ACF custom fields, or device type
- **Show/hide Elementor elements** for Desktop, Tablet, or Mobile visitors
- **A/B test layouts** by showing different content to different user segments
- **Hide empty sections** automatically when all child widgets are hidden

= Free Conditions =
- **ACF Fields** (NEW): Show/hide based on Advanced Custom Fields values on the current post or page. 7 operators: is empty, is not empty, equals, not equals, contains, is true, is false.
- **Device Type** (NEW): Target Desktop, Tablet, or Mobile users with server-side User-Agent detection. Hidden elements are fully removed from HTML.
- **User Role**: Logged in, logged out, or specific roles (Administrator, Editor, Subscriber, custom roles).
- **User Meta**: Show/hide based on any user meta field value.
- **Date & Time**: Schedule visibility with "from" and "to" dates. Perfect for sales, events, and time-limited content.
- **Browser Type**: Target Chrome, Firefox, Safari, Edge, Opera, iPhone, Android, and more.
- **Flexbox Containers**: Full support for Elementor's Flexbox containers, classic sections, and nested containers.
- **Hide When Empty**: Automatically hide a parent section/container when all child widgets are hidden by visibility conditions.
- **Rule builder** (NEW): "Show / Hide this element when ..." with groups (all of these, or any group), a plain-language summary, a searchable condition picker with presets, in Elementor and the block editor.
- **Preview as** (NEW): see the page as a guest, a role, a specific user, a device or another date inside the editor, with "why is this hidden?" for every element.
- **Rules** (NEW): one list of every element with visibility rules, its rule in plain words, warnings (date range ended, can never be true, condition turned off, page cache) and filters.
- **Built for AI agents and the command line** (NEW): read-only WordPress Abilities (`visibility-logic/list-rules`, `visibility-logic/list-conditions`) for MCP clients, and `wp visibility-logic rules` / `wp visibility-logic conditions` for WP-CLI.

= Elementor, Elementor 4 and the block editor =
The same conditions work on Elementor widgets, sections and containers, on Elementor 4 atomic elements, and on every block in the block editor (Gutenberg), core or third-party. One evaluator checks them all on the server.

= One settings panel =
Everything lives in **Settings > Visibility Logic**: turn conditions on or off, see which conditions come with Pro, review the rules in use and, with Visibility Logic Pro, its settings and license. Free and Pro share the same panel.

= PRO Conditions =
- **Advanced ACF**: Repeater fields, options page, term meta, user fields, specific post/user sources, AND/OR logic with 17 operators.
- **Language**: Show/hide based on current language. Supports WPML, Polylang, and TranslatePress.
- **URL Parameters**: URL parameter visibility and standalone query string conditions with repeater support and 9 operators.
- **Geo Location**: Display elements based on visitor's country using MaxMind geolocation.
- **Dynamic Conditions**: Use all Elementor Pro Dynamic Tags as visibility conditions.
- **WooCommerce**: Restrict content based on order history, active subscriptions, or customer status.
- **Easy Digital Downloads**: Restrict based on purchase history or subscription status.
- **Advanced User Meta**: Multiple user meta conditions with AND/OR logic.
- **Post & Page, Taxonomy**: Show/hide based on current post type, specific pages, or taxonomy terms.
- **Archive**: Conditions based on post type archives and taxonomy archives.
- **IP & Referrer**: Target visitors by IP address or referral source.
- **WordPress Conditional Tags**: Use any WordPress conditional tag as a visibility condition.
- **Fallback Content**: Replace hidden elements with a custom text message or an Elementor template.
- **Copy/Paste**: Right-click to copy visibility settings between widgets or sections.

Get [Visibility Logic Pro](https://staxwp.com/go/visibility-logic) and unlock all conditions.

= How it works =
1. Edit any page with Elementor
2. Select a widget, section, or container
3. Go to the **Visibility** tab
4. Enable conditions and configure your display rules
5. Save: elements are shown or hidden on the live site based on your rules

= More from StaxWP =
- [BuddyBuilder](https://staxwp.com/go/buddybuilder): Build stunning BuddyPress communities with Elementor.

= Privacy Policy =
Visibility Logic collects no usage data and makes no requests to external services. The usage tracker (Appsero) bundled until version 2.5.1 was removed in 3.0, and what it stored is deleted on update.

= Found a bug? =
Report security bugs through the [Patchstack Vulnerability Disclosure Program](https://patchstack.com/database/vdp/visibility-logic-elementor). The Patchstack team helps validate, triage, and handle any security vulnerabilities.

== Frequently Asked Questions ==

= How do I show or hide an Elementor widget based on user role? =
Edit your page with Elementor, select any widget, go to the **Visibility** tab, enable Visibility Logic, then open the **User Role** section. Select the roles you want to target (e.g., Administrator, Subscriber, Guest). You can choose to show or hide the element when the condition is met, including common setups such as hide Elementor by user role or show a section only to logged in members.

= Can I restrict content to logged-in users only? =
Yes. Enable Visibility Logic on any widget, section or container, go to User Role conditions and select "Logged in users". The element will only be visible to authenticated users. Guests will see nothing (or a fallback message with Pro).

= How do I schedule content to show at a specific date and time? =
Use the **Date & Time** condition. You can set a "From" and "To" date to display content only during a specific period, a time window, or days of the week. This is useful for sales, events, promotions or limited-time offers. Dates and times of day use the site time zone (Settings > General). Before 2.5.2, Date FROM and Date TO were read as UTC; unchecking **Read Date FROM and Date TO in the site time zone** in Settings > Visibility Logic restores that reading.

= Does it work with Elementor containers and Flexbox? =
Yes. Visibility Logic fully supports Elementor's Flexbox containers, classic sections, and nested containers. You can also enable "Hide when empty" on a container to automatically hide it when all child widgets are hidden by visibility conditions.

= Can I combine multiple conditions (AND / OR)? =
Yes. You can enable multiple condition types at once (e.g., User Role + Date Time). In the General settings, choose **All** (all conditions must be met) or **At least one** (any single condition triggers the action). The [Pro version](https://staxwp.com/go/visibility-logic) extends this with advanced AND/OR logic for user meta conditions.

= Can I show or hide content based on ACF field values? =
Yes! The free version supports ACF (Advanced Custom Fields) visibility on the current post or page with 7 operators. The [Pro version](https://staxwp.com/go/visibility-logic) extends ACF support with repeater fields, options pages, user fields, term meta, and AND/OR logic with 17 operators.

= Can I show different content on mobile vs desktop? =
Yes. The **Device Type** condition detects Desktop, Tablet, and Mobile visitors using server-side User-Agent detection. Unlike CSS-based solutions, hidden elements are completely removed from the HTML, not just visually hidden.

= Can I show different content based on the visitor's country? =
Geolocation-based visibility is available in [Visibility Logic Pro](https://staxwp.com/go/visibility-logic). It uses MaxMind to detect the visitor's country and dynamically show or hide elements for localized offers, compliance notices, or region-specific content.

= Does it work with WooCommerce? =
The [Pro version](https://staxwp.com/go/visibility-logic) includes WooCommerce conditions: restrict content based on a user's order history, active subscriptions, or customer status. Perfect for showing exclusive content to paying customers.

= What happens when an element is hidden? Is the HTML removed? =
By default, hidden elements are completely removed from the page HTML. If you need the HTML to remain in the DOM (hidden via CSS), enable the **Keep HTML / Hide by CSS** option. The Pro version also offers a **Fallback** feature to replace hidden elements with a custom message or an Elementor template.

= Will this slow down my website? =
No. Conditions are checked on the server only for elements that have Visibility Logic turned on, once per element. No CSS or JavaScript is added to your pages, and hidden elements are removed before the page is sent to the browser.

= Is it compatible with caching plugins? =
Yes, with one rule: the visitor must reach WordPress. Pages with visibility rules skip Elementor's element cache, as in earlier versions. New in 3.0, **Keep Elementor's element cache on for pages with rules** (Settings > Visibility Logic; on for new installs) renders only the elements with rules on every request and serves the rest of the page from the cache, which is much faster. Full-page caches (WP Rocket, LiteSpeed Cache, Cloudflare APO and similar) store one copy of a page for everyone, so exclude pages with user, device, date or URL based rules from them, or serve logged-in users without the page cache (the default in most caching plugins).

= Does it work with Elementor 4 and the new atomic elements? =
Yes. Widgets, sections and containers get the Visibility tab as before, and the atomic elements of Elementor 4 (V4 editor: heading, paragraph, flexbox, div block and the others) get a **Visibility** section in their settings with the same conditions. Keep HTML and Hide when empty are available on classic elements only. Tested with Elementor 3.21, 3.35 and 4.4.

= Does it work with the block editor (Gutenberg)? =
Yes. Every block, core or from other plugins, gets a **Visibility** panel in the block settings sidebar with the same conditions as in Elementor. The rules are checked on the server when the page is rendered: hidden blocks are removed from the HTML (or kept and hidden with CSS), and no script is added to your pages. The editor always shows every block.

= Do I need Elementor? =
No. Since 3.0 the plugin works on sites without Elementor for blocks; the Elementor features load only when Elementor is active.

= Where are the settings? =
In **Settings > Visibility Logic**. It is one panel for the free plugin and Visibility Logic Pro: conditions, Pro settings, Pro license and the list of rules in use.

= Can AI assistants read my visibility rules? =
On WordPress 6.9 or newer, Visibility Logic registers two read-only abilities through the WordPress Abilities API: `visibility-logic/list-conditions` and `visibility-logic/list-rules`. MCP clients connected through the WordPress MCP Adapter can use them to answer questions such as "which pages hide content from guests?". They need a user who can edit posts and never change anything.

== Installation ==

Free plugin:
1. Install directly from WP Admin → Plugins → Add New by searching for "Visibility Logic", or upload the free plugin folder to your `wp-content/plugins` directory.
2. Activate the plugin.
3. Edit a page using Elementor, select any widget, section, or container, then go to the **Visibility** tab.
4. Enable conditions and configure your display rules.
5. Save and preview your page.

Visibility Logic Pro:
1. Purchase Pro from StaxWP and download the Pro zip file from your StaxWP account.
2. In WP Admin, go to Plugins → Add New → Upload Plugin, upload the Pro zip file, then install and activate it.
3. Go to Settings → Visibility Logic → License.
4. Enter the license key from your staxwp.com account and activate it.

== Screenshots ==

1. **Visibility tab**: Enable conditional visibility on any Elementor widget
2. **User Role**: Restrict content by user role or logged-in status
3. **User Meta**: Show/hide based on user meta field values
4. **Date & Time**: Schedule content to appear during a specific period
5. **Browser Type**: Target specific browsers and devices
6. **Settings > Visibility Logic**: one panel for conditions, rules in use and Visibility Logic Pro

== Changelog ==

= 3.0.0 =
* Renamed to Visibility Logic for Elementor and the Block Editor (same plugin, same slug and settings).
Updating keeps your site working exactly as before: every saved rule renders the same, and each newer behaviour below is an option in Settings > Visibility Logic (on by default only for new installs). The 2.5.2 fixes (Date FROM/TO in the site time zone, Hide when empty on Flexbox containers) stay on and can be turned off there.

* NEW: Block editor (Gutenberg): a Visibility panel on every block (core and third-party) with the same conditions, evaluated on the server; no front-end script.
* NEW: Elementor 4 atomic elements: a Visibility section with the same conditions in the V4 editor.
* NEW: Works without Elementor (blocks); the Elementor features load only when Elementor is active.
* NEW: Conditions are builder-agnostic: one registry and one evaluator for Elementor, Elementor 4 and blocks; add-ons register fields with the `stax/visibility/condition_fields` filter and evaluate through `stax/visibility/apply_conditions` as before.
* NEW: Developer API for custom conditions: `stax_visibility_register_condition( $slug, [ label, fields, summary, evaluate ] )` on the `stax/visibility/register_conditions` action. The condition shows in the rule builder, Elementor 4 elements, blocks, summaries, Preview as and the Rules overview with no editor code; no PHP is typed in the editor. Rules that use it are stored in the new format (administrators only on 2.x).
* NEW: Settings > Visibility Logic is the single panel for the free plugin and Visibility Logic Pro (conditions, Pro settings, Pro license), redesigned with the native WordPress admin look, keyboard and screen reader friendly.
* NEW: Clear free and Pro overview: every condition with a short description, Pro conditions marked.
* NEW: Rule builder in Elementor (Visibility tab) and the block editor ("Visibility rules" panel): "Show / Hide this element when ...", conditions in groups (all conditions of a group, any group), a plain-language summary, a condition picker with search, task categories (Who, When, Device and browser, Page and content, Shop, Where from) and presets (Logged-in members only, Guests only, Hide on mobile, Only during a date range, Business hours, Administrators only), "When hidden" (remove, keep the HTML hidden by CSS, other content with Pro), inline warnings. Keyboard and screen reader friendly. The classic sections stay one click away ("Show the classic settings").
* NEW: Short rule labels on the Elementor canvas, in the Navigator, on blocks and in the block List View.
* NEW: Preview as: guest, a role, a specific user (with the right to list users), a device, a date and time, inside both editors; hidden elements are faded with "Hidden for ..." and each element explains why. Only the Visibility Logic check is simulated: you stay logged in as yourself.
* NEW: Preview as a country and a language (with Visibility Logic Pro: Geo Location and Language conditions); a country or a language alone also starts the preview.
* NEW: Front end, for editors: the admin bar shows "Visibility: N hidden here" with the reason for each element, and "View this page as" a guest or a role.
* NEW: Rules (first tab of Settings > Visibility Logic): every element with a rule, the rule in plain words, warnings (date range ended, starts later, can never be true, condition turned off in Settings, draft, visitor rules on cached pages), filters by editor, condition and status, and a cache check for WP Rocket, LiteSpeed, W3 Total Cache, WP Super Cache, Cloudflare and others.
* NEW: Date & Time: "Every year, FROM/TO day" (seasonal content, ranges over the year end, 29 February handled).
* NEW: User Role: specific users, "has capability", and "must have all selected roles".
* NEW: "Hide from everyone" for any element, page or popup: a third choice next to Show and Hide that ignores the conditions and keeps them for later.
* NEW: Page condition (free): page type (front page, blog, single post, page, archive, search results, 404), post type and specific pages or posts; inside a loop the loop item is checked. Taxonomies, meta and archive details stay in Pro.
* NEW: Page visibility (free): one rule for a whole page or post, in Elementor's page settings (Page visibility) and in the block editor's document sidebar, built with the same rule builder. When the rule hides the page, visitors get a message instead of the content (theme header and footer kept); REST responses, feeds and excerpts get the message too, and search engines get noindex. People who can edit the page always see it, with a notice bar. Redirect, login page, 404 and an Elementor template need Visibility Logic Pro.
* NEW: Text fallback (free): "When hidden: show a message instead" for Elementor elements and blocks (basic HTML, sanitized). The Elementor template fallback stays in Pro and is chosen in the same place.
* NEW: Summaries say "(next day)" for time ranges over midnight, and every date and time field shows the site time zone and the current site time.
* NEW: Works with Visibility Logic Pro 2.4 and 3.0 in any update order: your rules keep working while one plugin is newer. With Pro older than 3.0, a notice on the Dashboard and Plugins screens asks to update it.
* NEW: Classic Elementor columns have the Visibility tab (with Visibility Logic Pro 2.4, columns get the free conditions; Pro conditions on columns need Pro 3.0).
* NEW: More operators for User Meta and ACF Field: equal (ignore case), not equal (ignore case), starts with, ends with, less or equal, greater or equal, is not one of. The existing operators answer exactly as before.
* Backward compatibility: saved rules are not migrated and render the same (checked by an equivalence suite of 26,568 old-vs-new cases). A rule edited in the builder is still saved in the 2.x format when 2.x can express it; rules that 2.x cannot express (groups, the same condition twice, draft, the new options) are saved in a new format, and their 2.x settings show the element to administrators only, so going back to 2.x never shows restricted content to everyone.
* NEW: Read-only WordPress Abilities `visibility-logic/list-rules` and `visibility-logic/list-conditions` (WordPress 6.9+, MCP Adapter).
* NEW: WP-CLI commands `wp visibility-logic rules` and `wp visibility-logic conditions`.
* NEW: Option "Keep Elementor's element cache on for pages with rules": only elements with rules are rendered per request (a 300-widget test page went from about 177 ms to 60 ms per render, and Elementor no longer rewrites its cache on every view). On for new installs, opt-in for updated sites.
* NEW: Option "Hide when empty: count widgets placed directly in a container" (on since 2.5.2): unchecking it restores the pre-2.5.2 behaviour.
* NEW: Option to read Date FROM/TO in the site time zone (on since 2.5.2): unchecking it reads them as UTC, as before 2.5.2.
* NEW: Filter `stax/visibility/device_type` to override the detected device (for example from a CDN header).
* NEW: One section per subject in the Elementor panel: with Visibility Logic Pro 3.0+, "Advanced ACF" and "Advanced User Meta" are part of the ACF Field and User Meta sections (more field sources and operators in the same rule, plus an AND/OR rule group). Saved rules keep working exactly as before and move into the merged section the next time the page is saved in Elementor.
* NEW: Pro conditions in the rule builder's picker are listed greyed out with a Pro mark (never hidden, never usable without Pro); clicking one says what it does, with one link to the Pro page. One dismissible notice after the site's 5th saved visibility rule (Dashboard, Plugins and Visibility Logic screens, administrators only); once dismissed it never comes back. Nothing is tracked or sent: the rule count stays in the site's database. Nothing shows while Visibility Logic Pro is active.
* Improved: Pro options are shown quietly: Pro sources and operators are listed disabled with "(Pro)" in their label, and a single "More conditions in Pro" line with one link replaces the empty Pro sections at the end of the Visibility tab (and the link in the block editor). Nothing Pro is shown when Pro is active.
* Fix (security): Fallback on a section, column or container now replaces the element. Before, a hidden section or container with Fallback on was printed in full (for example members-only content shown to guests) and the fallback was not shown.
* Fix: Opening a section in the Visibility tab no longer scrolls the panel back to the top; the opened section stays where you clicked it.
* Fix: Notices inside the Visibility tab (for example "Advanced Custom Fields plugin is not active") were white on a light panel; they now use the panel's muted text color.
* Improved: Each element's rules are checked once per request, the User-Agent once per request; no get_plugins() scan on front-end requests; no admin assets outside the plugin's screens; usage tracking client loaded only in the admin.
* Improved: Compatibility with PHP 8.1 to 8.5 (no deprecations), WordPress 6.x to 7.1, Elementor 3.x and 4.x (tested with 3.21, 3.35 and 4.4) and Elementor Pro 4.4. The Elementor editor uses Elementor's own Select2 (the second copy bundled until 2.5.1 is gone).
* Removed: the Appsero usage tracker (SDK, opt-in notice, stored options and scheduled event).
* Added: a one-time, dismissible notice on updated sites (Dashboard, Plugins, Visibility Logic screens) pointing to the two new options.
* Fix: Saving the options with an expired form no longer causes a fatal error.
* Fix: User Meta: meta keys that start with an underscore (for example `_my_key`) are offered and saved exactly as stored. Before, the field list removed the underscore and the rule never matched. Rules saved before keep working as they did; pick the key again to use the underscore version.
* Security: Stricter checks on the editor search requests, prepared SQL, escaped admin output; the password hash and activation key are no longer offered in the User Meta field list (rules saved with them keep working).

= 2.5.3 =
* Security fix: with Fallback turned on (Visibility Logic Pro), a hidden section or container was shown in full to the visitors the rule should hide it from, and the fallback was not shown. Now the fallback replaces the whole element. Widgets were not affected. Please update.
* Fix: opening a section in the Visibility tab of the Elementor panel (for example WooCommerce Users) no longer scrolls the panel back to the top; the opened section stays where you clicked it.

= 2.5.2 =
* Fix: Date FROM and Date TO now use the site time zone (Settings > General), like the time of day and weekday options. Before, they were read as UTC, so a schedule started or ended early or late by the site's offset from UTC. If you shifted your dates to work around this, set them back to the site time.
* Fix: Hide when empty no longer hides a Flexbox container whose widgets are visible. Widgets placed directly in a container now count.
* Fix: the User Meta key list keeps keys that start with an underscore (such as `_my_field`), so rules on these keys match. If a rule was saved with the key without its underscore, pick the key again.
* Fix: a PHP 8.5 deprecation notice in the Date Time condition.

= 2.5.1 =
* Fix: Corrected the Pro/admin URL used on the settings page.
* Improved: Refreshed readme positioning around Display Conditions for Elementor, conditional display, user role, ACF, device, and membership visibility use cases.
* Improved: Clarified installation steps for the free plugin and separate Visibility Logic Pro zip/license activation flow.

= 2.5.0 =
* NEW: Device Type condition: show/hide elements for Desktop, Tablet, or Mobile (server-side User-Agent detection)
* NEW: ACF Field condition: show/hide based on Advanced Custom Fields values on the current post/page with 7 operators: is empty, is not empty, equals, not equals, contains, is true, is false
* Advanced ACF features available in Pro: user fields, repeater fields, options page, term meta, specific post/user sources, AND/OR logic with 17 operators

= 2.4.0 =
* WordPress 6.9 compatibility
* Elementor 3.35 compatibility
* Fix: Replace deprecated current_time('timestamp') with current_datetime()
* Fix: Browser detection now properly detects Edge, iPhone and Android browsers
* Fix: PHP 8.x warnings for undefined HTTP_USER_AGENT
* Fix: PHP 8.x TypeError when using strpos() on non-string user meta values
* Fix: Early translation loading notice on WordPress 6.7+
* Improved: Minimum PHP version raised to 7.4

= 2.3.9 =
* Fix compatibility with Elementor 3.12.0
* Latest WordPress compatibility
* Latest Elementor compatibility
* Fix PHP noticies

= 2.3.6 =
* Add compatibility with Elementor element caching experimental feature.
* Other fixes and improvements.

= 2.3.5.1 =
* Update readme.txt to include patchstack.com report bug link

= 2.3.5 =
* Add extra security checks when saving plugin options.

= 2.3.4 =
* Upgrade Appsero SDK

= 2.3.3 =
* Fix Elementor deprecation warnings

= 2.3.2 =
* Fix Datetime render

= 2.3.1 =
* Add support for Elementor's experimental containers

= 2.2.9 =
* Fix user meta when selecting data from the user table; eq. email.

= 2.2.8 =
* Fix require plugin message display in admin page

= 2.2.7 =
* Register query control - use to register_control method for previuous Elementor versions compatibility.

= 2.2.6 =
* Fix state markers for applied options in Elementor editor
* Add support for new Pro options

= 2.2.5 =
* Fix css render for hiding sections

= 2.2.4 =
* Added new variation for Date & Time option - Weeks Days + Time
* Fixed a bug on Date & Time option where server time was not fetch correctly. fixed date-time condition over midnight
* Tested on WP 5.9
* Improved admin UI

= 2.2.3 =
* Fix error when using Elementor version older than 3.3.0

= 2.2.2 =
* New Section option: Hide section when all the widgets inside it are hidden
* Fix printed styles for hidden elements of the same type.

= 2.2.1 =
* Added extra compatibility with Visibility Logic Pro older versions

= 2.2.0 =
* Added Enabled/Disabled icons on all sections to inside Stax Visibility tab to see at a glance which options are in use.
* Added Dynamic conditions based on all Elementor Pro Dynamic tags (in PRO version)

= 2.1.7 =
* Date and time conditions UI improvement to show current server time in editor

= 2.1.6 =
* Fix Section restriction when used with Hide HTML option
* Fix integration with Paid Memberships Pro plugin for section restrictions

= 2.1.5 =
* Improved logic and fixed Condition type when set to "At least one" to match the restrictions

= 2.1.4 =
* Improvement: Added AJAX control for User meta select to improve editor speed
* Fix Fallback text so it shows correct escaped HTML

= 2.1.3 =
* Moving from version 1.2.0 is won't change anything on your site. We added safe fallback settings for older setting from v1. Old settings are now being taken into consideration and are available in the Widget settings. You can switch to nee new settings if you like or just leave those in place.

= 2.1.2 =
* Added more User meta conditional operators
* Added icon next to Widgets and Sections with conditions enabled in Elementor editor
* Admin settings page improvements

= 2.1.1 =
* Update migration logic to be less memory demanding

= 2.1.0 =
* New free feature : User Meta content restriction
* New free feature: Restrict content based on User Browser(Chrome, Mozilla, Safari, etc)
* New free feature: Date & Time restrictions for content

= 2.0.3 =
* Old settings migration logic update for inner elements.

= 2.0.2 =
* Make the settings migration automatic on plugin update.

= 2.0.1 =
* Fix PHP version compatibility in Updates logic

= 2.0.0 =
* Code logic refactoring and introducing Pro features.

= 1.2.0 =
* Fixed the edge case when you combined hiding elements for guests and user role

= 1.1.0 =
* Made the selectors full width since there was a bug with Select2 control

= 1.0.4 =
* Made changes to the hiding logic and the element is fully hidden, no extra empty wrapping divs shown

= 1.0.3 =
* Visibility settings added for Section

= 1.0.2 =
* Added translation pot file

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 3.0.0 =
New rule builder, Preview as and a Rules overview in Elementor and the block editor. Your saved rules keep working exactly as before; nothing is migrated.

= 2.5.3 =
Security fix: a hidden section or container with Fallback on was shown in full. Please update. Also keeps the Visibility tab scroll position.

= 2.5.2 =
Bug fixes: Date FROM/TO use the site time zone, Hide when empty works on Flexbox containers, User Meta keys starting with an underscore match.

= 2.5.1 =
Maintenance release with a corrected Pro/admin URL, refreshed Display Conditions for Elementor readme copy, and clearer free/Pro installation instructions.

= Be a contributor =
If you want to contribute, go to our [GitHub Repository](https://github.com/staxwp/visibility-logic-elementor).

You can also add a new language via [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/visibility-logic-elementor).

== Credits ==
This plugin implements some functionality similar to:
* Dynamic Content for Elementor (GPL v2 or later)
