=== Automatic Post Date Filler ===
Contributors: Devtard
Donate link: http://devtard.com/donate
Tags: automatic, custom, date, default, filler, future, future posts, override, planner, post, post status, schedule, scheduler, scheduled post, time
Requires at least: 3.0
Tested up to: 3.6
Stable tag: trunk
License: GPLv2

This plugin automatically sets custom date and time when scheduling a post.

== Description ==
With APDF you won't have to change the date and time of scheduled posts ever again. You just have to create simple rules and you are done. This plugin will **set the date and time automatically** when scheduling a post.

The customized date and time of scheduled posts will be automatically filled after clicking the "Edit" link next to "Publish immediately" in the Publish module. Users are still free to modify the value to whatever they want afterwards.

= Features =
* You can set a certain time of day when posts should be published
* Time inserted by APDF can be based on the current date/time or the date/time of the furthest schedulest post + specified number of days/minutes
* Default values provided by WordPress will be overroden only when scheduling posts with posts statuses set by the user

*Follow [@devtard_com](http://twitter.com/devtard_com) on Twitter or subscribe to my blog [devtard.com](http://devtard.com) to keep up to date on new releases and WordPress-related information.*

== Installation ==
1. Upload the plugin to the '/wp-content/plugins/' directory.
2. Activate it through the 'Plugins' menu in WordPress.
3. Configure the plugin (Settings -> Automatic Post Date Filler).

== Screenshots ==
1. Administration interface

== Frequently Asked Questions ==
= This plugin doesn't work without JavaScript, why? =
There is no WP API that can be used to insert custom date and time when scheduling a post, that's why JavaScript is used.

= Which plugin data is stored in the database? =
Plugin settings can be found in the option "automatic_post_date_filler". It will be automatically removed from your database after you delete the plugin via your administration interface.

== Changelog ==
= 1.0 =
* Initial release

== Upgrade Notice ==
= 1.0 =
* Initial release
