=== Visibility Logic for Elementor ===
Contributors: staxwp, kierantaylorio, codezz, rtynio, geowrge
Tags: elementor, visibility, conditional logic, restrict content, dynamic visibility
Requires at least: 5.0
Requires PHP: 7.4
Tested up to: 6.9
Stable tag: 2.5.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Conditional visibility for Elementor — show or hide widgets, sections and containers based on user role, ACF fields, device type, date & time, browser, user meta and more.

== Description ==

**Visibility Logic** adds powerful **conditional display conditions** to every Elementor widget, section, and container. Control exactly who sees what, when, and on which device — without writing a single line of code.

Hidden elements are **completely removed from the HTML** (not just hidden with CSS), keeping your pages fast and your content secure.

= When to use Visibility Logic =
- **Restrict content** to logged-in users, specific roles, or subscribers
- **Schedule content** to appear during a sale, event, or launch window
- **Personalize pages** based on user meta, ACF custom fields, or device type
- **Show/hide elements** for Desktop, Tablet, or Mobile visitors
- **A/B test layouts** by showing different content to different user segments
- **Hide empty sections** automatically when all child widgets are hidden

= Free Conditions =
- **ACF Fields** (NEW) — Show/hide based on Advanced Custom Fields values on the current post or page. 7 operators: is empty, is not empty, equals, not equals, contains, is true, is false.
- **Device Type** (NEW) — Target Desktop, Tablet, or Mobile users with server-side User-Agent detection. Hidden elements are fully removed from HTML.
- **User Role** — Logged in, logged out, or specific roles (Administrator, Editor, Subscriber, custom roles).
- **User Meta** — Show/hide based on any user meta field value.
- **Date & Time** — Schedule visibility with "from" and "to" dates. Perfect for sales, events, and time-limited content.
- **Browser Type** — Target Chrome, Firefox, Safari, Edge, Opera, iPhone, Android, and more.
- **Flexbox Containers** — Full support for Elementor's Flexbox containers, classic sections, and nested containers.
- **Hide When Empty** — Automatically hide a parent section/container when all child widgets are hidden by visibility conditions.

= PRO Conditions =
- **Advanced ACF** — Repeater fields, options page, term meta, user fields, specific post/user sources, AND/OR logic with 17 operators.
- **Language** — Show/hide based on current language. Supports WPML, Polylang, and TranslatePress.
- **URL Parameters** — Standalone query string conditions with repeater support and 9 operators.
- **Geo Location** — Display elements based on visitor's country using MaxMind geolocation.
- **Dynamic Conditions** — Use all Elementor Pro Dynamic Tags as visibility conditions.
- **WooCommerce** — Restrict content based on order history, active subscriptions, or customer status.
- **Easy Digital Downloads** — Restrict based on purchase history or subscription status.
- **Advanced User Meta** — Multiple user meta conditions with AND/OR logic.
- **Post & Page, Taxonomy** — Show/hide based on current post type, specific pages, or taxonomy terms.
- **Archive** — Conditions based on post type archives and taxonomy archives.
- **IP & Referrer** — Target visitors by IP address or referral source.
- **WordPress Conditional Tags** — Use any WordPress conditional tag as a visibility condition.
- **Fallback Content** — Replace hidden elements with a custom text message or an Elementor template.
- **Copy/Paste** — Right-click to copy visibility settings between widgets or sections.

Get [Visibility Logic Pro](https://staxwp.com/go/visibility-logic) and unlock all conditions.

= How it works =
1. Edit any page with Elementor
2. Select a widget, section, or container
3. Go to the **Visibility** tab (or Advanced → Visibility Control)
4. Enable conditions and configure your display rules
5. Save — elements are shown or hidden on the live site based on your rules

= Free vs Pro =

| Feature | Free | Pro |
|---------|------|-----|
| User Role & Logged-in status | ✅ | ✅ |
| User Meta | ✅ | ✅ |
| Date & Time scheduling | ✅ | ✅ |
| Browser detection | ✅ | ✅ |
| Device Type (Desktop/Tablet/Mobile) | ✅ | ✅ |
| ACF Fields (current post/page) | ✅ | ✅ |
| Advanced ACF (repeater, options, user, AND/OR) | ❌ | ✅ |
| Language (WPML/Polylang/TranslatePress) | ❌ | ✅ |
| URL Parameters | ❌ | ✅ |
| Geolocation (country) | ❌ | ✅ |
| WooCommerce & EDD | ❌ | ✅ |
| Dynamic Tags conditions | ❌ | ✅ |
| Post/Page, Taxonomy, Archive | ❌ | ✅ |
| IP & Referrer | ❌ | ✅ |
| Fallback content | ❌ | ✅ |
| Copy/Paste conditions | ❌ | ✅ |

= More from StaxWP =
- [BuddyBuilder — BuddyPress Builder for Elementor](https://staxwp.com/go/buddybuilder) — Build stunning BuddyPress communities with Elementor.
- [Elementor Addons & Widgets](https://staxwp.com/go/addons-for-elementor) — Powerful widgets to help you build stunning pages.
- [Woo Addons for Elementor](https://staxwp.com/go/woo-addons-for-elementor/) — Elementor enhancements for WooCommerce.

= Privacy Policy =
We use Appsero SDK to collect some telemetry data upon user's confirmation. This helps us troubleshoot problems faster and make product improvements.

= Found a bug? =
Report security bugs through the [Patchstack Vulnerability Disclosure Program](https://patchstack.com/database/vdp/visibility-logic-elementor). The Patchstack team helps validate, triage, and handle any security vulnerabilities.

== Frequently Asked Questions ==

= How do I show or hide an Elementor widget based on user role? =
Edit your page with Elementor, select any widget, go to the **Visibility** tab, enable Visibility Logic, then open the **User Role** section. Select the roles you want to target (e.g., Administrator, Subscriber, Guest). You can choose to show or hide the element when the condition is met.

= Can I restrict content to logged-in users only? =
Yes. Enable Visibility Logic on any widget, section or container, go to User Role conditions and select "Logged in users". The element will only be visible to authenticated users. Guests will see nothing (or a fallback message with Pro).

= How do I schedule content to show at a specific date and time? =
Use the **Date Time** condition. You can set a "From" and "To" date to display content only during a specific period — perfect for sales, events, promotions or limited-time offers. All times are based on your WordPress server time.

= Does it work with Elementor containers and Flexbox? =
Yes. Visibility Logic fully supports Elementor's Flexbox containers, classic sections, and nested containers. You can also enable "Hide when empty" on a container to automatically hide it when all child widgets are hidden by visibility conditions.

= Can I combine multiple conditions (AND / OR)? =
Yes. You can enable multiple condition types at once (e.g., User Role + Date Time). In the General settings, choose **All** (all conditions must be met) or **At least one** (any single condition triggers the action). The [Pro version](https://staxwp.com/go/visibility-logic) extends this with advanced AND/OR logic for user meta conditions.

= Can I show or hide content based on ACF field values? =
Yes! The free version supports ACF (Advanced Custom Fields) visibility on the current post or page with 7 operators. The [Pro version](https://staxwp.com/go/visibility-logic) extends ACF support with repeater fields, options pages, user fields, term meta, and AND/OR logic with 17 operators.

= Can I show different content on mobile vs desktop? =
Yes. The **Device Type** condition detects Desktop, Tablet, and Mobile visitors using server-side User-Agent detection. Unlike CSS-based solutions, hidden elements are completely removed from the HTML — not just visually hidden.

= Can I show different content based on the visitor's country? =
Geolocation-based visibility is available in [Visibility Logic Pro](https://staxwp.com/go/visibility-logic). It uses MaxMind to detect the visitor's country and dynamically show or hide elements — great for localized offers, compliance notices, or region-specific content.

= Does it work with WooCommerce? =
The [Pro version](https://staxwp.com/go/visibility-logic) includes WooCommerce conditions — restrict content based on a user's order history, active subscriptions, or customer status. Perfect for showing exclusive content to paying customers.

= What happens when an element is hidden? Is the HTML removed? =
By default, hidden elements are completely removed from the page HTML. If you need the HTML to remain in the DOM (hidden via CSS), enable the **Keep HTML / Hide by CSS** option. The Pro version also offers a **Fallback** feature to replace hidden elements with a custom message or an Elementor template.

= Will this slow down my website? =
No. Visibility Logic processes conditions server-side during page render with minimal overhead. Hidden elements are removed before the page is sent to the browser, so there's no extra HTML, CSS, or JavaScript loaded for hidden content.

= Is it compatible with caching plugins? =
Visibility Logic automatically disables Elementor's element caching for pages that use visibility conditions, ensuring dynamic content renders correctly. For full-page caching plugins (WP Rocket, LiteSpeed, etc.), make sure to exclude pages with user-specific conditions from the cache or use the "Keep HTML / Hide by CSS" option with client-side cache.

== Installation ==

1. Upload the plugin to your `wp-content/plugins` directory or install directly from WP Admin → Plugins → Add New
2. Activate the plugin
3. Edit a page using Elementor
4. Select any widget, section, or container
5. Go to the **Visibility** tab
6. Enable conditions and configure your display rules
7. Save and preview your page

== Screenshots ==

1. **Visibility tab** — Enable conditional visibility on any Elementor widget
2. **User Role** — Restrict content by user role or logged-in status
3. **User Meta** — Show/hide based on user meta field values
4. **Date & Time** — Schedule content to appear during a specific period
5. **Browser Type** — Target specific browsers and devices
6. **Admin Panel** — Global plugin settings and configuration

== Changelog ==

= 2.5.0 =
* NEW: Device Type condition — show/hide elements for Desktop, Tablet, or Mobile (server-side User-Agent detection)
* NEW: ACF Field condition — show/hide based on Advanced Custom Fields values on the current post/page with 7 operators: is empty, is not empty, equals, not equals, contains, is true, is false
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

= Be a contributor =
If you want to contribute, go to our [GitHub Repository](https://github.com/staxwp/visibility-logic-elementor).

You can also add a new language via [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/visibility-logic-elementor).

== Credits ==
This plugin implements some functionality similar to:
* Dynamic Content for Elementor (GPL v2 or later)
