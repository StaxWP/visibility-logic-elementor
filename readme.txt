=== Visibility Logic for Elementor ===
Contributors: staxwp, seventhqueen, kierantaylorio, codezz, rtynio
Tags: elementor, elementor restrictions, elementor conditions, elementor widgets, visibility logic, widget conditions
Requires at least: 3.0
Requires PHP: 5.6
Tested up to: 5.7.2
Stable tag: 2.0.2
License: GLPv2 or later

Hide or show Elementor widgets based on user role, if logged in or not.

== Description ==
Hide or show an Elementor widget based on whether a user is logged in, logged out (guest) or a specific role.
You can also hide an entire section too or show it just for specific users.

Based on your visibility setting for each widget you can restrict rendering elements on front-end, meaning that you can hide or show any Elementor widget based on the user role(Subscriber, Author, Administrator, etc), if the user is Logged our or if the user is Logged in.

= PRO Features =
- User Meta content restriction
- WordPress Conditional Tags restrictions
- Post & Page, Taxonomy, URL Parameter content restriction
- IP & Referrer restrictions
- Restrict content based on Browser
- Date & Time restrictions for content
- Fallback - Allows you to show a message instead of hiding the element.

Other restriction options to come. Suggestions are welcomed. 

Here is how you will find the Elementor visibility restrict settings:
1. Open a page with Elementor
2. Go and select any widget from the page
3. Go to Advanced - Visibility control
4. Here you will find the restriction settings for your element.

= More from StaxWP =
Check out [BuddyBuilder plugin](https://staxwp.com/go/buddybuilder).
Create stunning communities on your site powered by Elementor and BuddyPress

= Privacy Policy =
We use Appsero SDK to collect some telemetry data upon user's confirmation. This helps us to troubleshoot problems faster & make product improvements.

== Installation ==

1. Upload the plugin to your 'wp-content/plugins' directory
2. Activate the plugin
3. Edit a page using Elementor
4. Select an existing element or add a new one.
5. Go to Advanced - Visibility control
6. Enable conditions and set your restrictions for the Elementor widget
7. That is it :)

== Screenshots ==

1. Advanced - Visibility control settings - Show for users
2. Advanced - Visibility control settings - Hide for users

== Changelog ==


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
If you want to contribute, go to our [GitHub Repository](https://github.com/seventhqueen/visibility-logic-elementor).

You can also add a new language via [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/visibility-logic-elementor).

