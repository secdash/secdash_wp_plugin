=== SECDASH ===
Contributors: phelmig
Plugin URI: https://secdash.com
Tags: secdash, monitoring, security, updates, auto-update
Requires at least: 3.0.0
Tested up to: 4.2.2
Stable tag: 1.3
License: GPLv2
License URI: https://raw.githubusercontent.com/secdash/secdash_wp_plugin/master/LICENSE

SECDASH allows website owners to monitor the security of their Webserver, WordPress installation and Plugins in almost real time.

== Description == 

**NOTE:** For WordPress users SECDASH is free for up to three websites.

SECDASH is a cloud-based website monitoring platform that empowers organizations with multiple websites and different Content-Management-Systems to always know if they are online, up-to-date and secure.

We provide organizations the SECDASH plug-in that they install on their website/s. The plug-in continuously sends us data of the website’s software assets (Content-Management-System (CMS), the installed modules, and the webserver). The data includes information such as names, version numbers, etc. At the other end we crawl the web and aggregate information that affects these website components such as available updates and security vulnerabilities.

SECDASH then matches the information and figures out if all parts of the website are okay. If not, SECDASH notifies the right person that is responsible for the affected website and component. In addition, we provide a dashboard that shows and visualizes all the information about the websites, its modules and the webservers.

Try it out here: [secdash.com](https://www.secdash.com/)

== Installation ==

1. Upload the `secdash` folder to `/wp-content/plugins/`
1. Activate the plugin through the 'Plugins' menu in WordPress
3. Initialize SECDASH through the item 'SECDASH' under the 'Options' menu

== Screenshots ==

1. Initialize SECDASH by entering your license key through the item 'SECDASH' under the 'Options' menu.
2. Our engine will retrieve version information from you site on a regular basis (current default: every 10 minutes)
3. As soon as one of the used software components is affected by a security issue you will be notified

== Changelog ==

= 1.3 =
* Fix for old PHP Versions

= 1.2 =
*   First Plugin Update Support

= 1.1 =
*    Minor improvements

= 1.0 =
*   Major UI improvements
*   Localization (currently available in German und Swedish)
*   Improved initialization process
*   Optional handshake process when cookies are not available
*   First final release

= 0.9.5 =
*   The manual activation key is now shown in a text area to make it more accessible
*   Instructions / Error messages and UI improvements

= 0.9.4 =
*   Increased backwards compatibility. SECDASH now works even with WP3.0 (we don't recommend using this though ^^)

= 0.9.3 =
*   (re-)include wp-includes/version.php to make sure we get the right WP Version

= 0.9.2 =
*   Force HTTPS

= 0.9.1 =
*    Bugfix for older PHP Versions

= 0.9 =
*   Initial Public Release (Beta)
