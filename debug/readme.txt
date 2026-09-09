=== Debug ===
Contributors: soninow
Donate link: https://soninow.com/
Tags: debug, error reporting, error log, error notification, display error
Requires at least: 5.0
Tested up to: 6.7
Stable tag: 1.13
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Debug helps you find and fix errors in your WordPress site by safely editing your wp-config.php file and sending real-time email notifications.

== Description ==

Debug is a development/production tool for WordPress. It helps you find bugs in your site by letting you toggle WordPress's built-in debug flags and by emailing you when a runtime error occurs.

**Features**

* Toggle `WP_DEBUG`, `WP_DEBUG_LOG`, `WP_DEBUG_DISPLAY`, `SCRIPT_DEBUG` and `SAVEQUERIES` from the admin settings page.
* View, clear and download your `wp-content/debug.log` tail (last 1 MB).
* Real-time error → email notification with a throttled, level-filtered error handler.
* Automatic timestamped backup of `wp-config.php` before every save, plus one-click restore.

= How it works =
The Debug plugin modifies your `wp-config.php` file to enable or disable WordPress debug constants, and it reads the tail of your `debug.log`. A backup of `wp-config.php` is created automatically before each change so you can always roll back.

= Note on file permissions =
To edit `wp-config.php`, your server must allow the plugin to write that file. If it cannot, the plugin shows you the code to paste manually instead.

= Security & email notifications =
Notifications only fire for meaningful error levels (errors and warnings), never for deprecation notices or suppressed (`@`) code, and emails are queued and throttled so a busy site won't flood your inbox or SMTP queue.

== Installation ==

1. Unzip and upload `debug.zip` to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to the **Debug** settings page and configure your preferences.
4. Hit **Save Changes**.

== Frequently Asked Questions ==

= Is my wp-config.php safe? =
Yes. The plugin creates a timestamped backup of `wp-config.php` before every change, and the settings page lets you restore any backup with one click. `wp-config.php` is never downloadable from the browser.

= Why am I not getting email notifications? =
Make sure email notifications are enabled and a valid address is set, and that `wp_mail()` works on your host. Only errors and warnings trigger emails.

= The plugin cannot write wp-config.php =
Check your file permissions. If the file is not writable the plugin shows the exact code to paste into `wp-config.php` manually.

== Screenshots ==
1. Debug Configuration Settings.
2. Save Settings with success message.
3. wp-config.php manual-paste fallback when the file is not writable.
4. Debug log file viewer with clear / download actions.
5. No log file notice.

== Changelog ==
= 1.13 =
* Security: removed the ability to download `wp-config.php` (full credential leak). Debug log download now only serves the log via a clean `admin-post.php` endpoint.
* Security: added strict path allowlisting + traversal guards to all file reads/writes.
* Security: automatic timestamped `wp-config.php` backup before every save + one-click restore UI.
* Security: error handler now only registers when email notifications are on, filters out deprecation/notice noise, respects `error_reporting()`, throttles and queues emails, and chains the previous handler.
* Fix: broken Settings link `href` (missing closing quote) on the plugin list page.
* Fix: `esc_html_e()` misuse replaced with correct escaping (`esc_html`, `esc_attr`, `esc_url`, `wp_json_encode`-style safe output).
* Fix: tolerant, case/format-insensitive regex in `debug_add_option` — no more duplicate `define()` creation.
* Fix: nonce action strings standardized + consistent failure notices (no silent no-op, no `die()`).
* Fix: read tail capped to 1 MB (was only capping the start offset).
* Improvements: enqueued CSS instead of inline `<style>` (CSP-friendly), modernized baseline (PHP 7.4+, WP 5.0+), `uninstall.php` cleanup.

= 1.12: Apr 1, 2024 =
* BugFix: Fix all bug reporting in plugin https://wordpress.org/plugins/plugin-check/.
* BugFix: Fix duplicate define variable creation error.

= 1.11: Mar 25, 2024 =
* BugFix: Cross Site Request Forgery (CSRF) issue.

= 1.10: Jun 29, 2022 =
* Increase security for direct access of files.

= 1.9: Dec 15, 2019 =
* Compatible with new version.

= 1.7: Mar 2, 2016 =
* BugFix: Display error not required for email notification enable.

= 1.6: Jan 24, 2016 =
* Add: Email Notification Settings on plugin setting page.
* BugFix: Resolved any user to download debug.log and wp-config.php file. now only super admin can download files.
* BugFix: resolved error when large debug.log file load on plugin section. now only load 1.4 MB file only from end of file.

= 1.5: Sept 30, 2015 =
* Add: Email Notification Page to Enter Notification email address and email subject.
* Add: Handle any error and send it to your email address on real time system. (no-email delay).
* BugFix: Resolved undefined "scrollHeight" in console log in jQuery. incase of admin debug.log file not exist.

= 1.4: June 2, 2015 =
* BugFix: download wp-config.php file for back-up /Downloads/wp-config.php.exe to /Downloads/wp-config.php.
* BugFix: download wp-config.php file for back-up /Downloads/debug.log.exe to /Downloads/debug.log.
* Remove: console.log function from js.
* Add: Add Setting page link on plugin page.

= 1.3: March 29, 2015 =
* Add: option for download wp-config.php file for back-up.
* Add: option for download debug.log file.
* Add: Auto scroll down in debug.log file view in admin panel to see latest error log.

= 1.2: March 4, 2015 =
* Add: script debug option
* Add: save query debug option
* Add: debug.log file view and clear option from admin panel

= 1.1: December 30, 2014 =
* Add: Banner at wordpress Community
* Add: Add change Log Session

= 1.0 =
* Rewrite wp-config.php file with Debug variables.
* Add: WP_DEBUG, WP_DEBUG_LOG, WP_DEBUG_DISPLAY Functionality.

== Upgrade Notice ==
= 1.13 =
Security release: removes the wp-config.php download, adds strict path guards, automatic backups + restore, and a hardened error handler. Upgrade recommended.

= How to contact the support / development team of our Debug plugin =
You can contact us through, https://soninow.com/contact
