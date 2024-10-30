=== Plugin Name ===
Contributors: ChemicalSailor
Tags: stats, browser, screen, ajax
Requires at least: 3.1.4
Tested up to: 3.3
Stable tag: 1.1
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=YG6P2JSSRLDJC&lc=GB&item_name=Tom%20Fletcher&currency_code=GBP&bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted

Records browser window size for each page load and makes data available for administrators. Also counts visits by registered users.

== Description ==

Often site visitors view websites using a browser window that can be much smaller than the screen resolution, yet sites are often designed based on popular screen resolutions or assumed parameters. The current trend of responsive design acknowledges that design should fit to the window size; this plugin will help you identify important viewport sizes, and where you can switch layouts.

This plugin runs a small piece of javascript on each page load to measure the browser window size and screen resolution, sending it back to the server to be stored in the database. This gives you a better understanding of the how visitors actually view your site: whether a wide range of viewports are in use, or if most visitors use a very similar window size.

Data is stored on a per visit basis, which contains each visitors page views. A visit times out after 15 minutes of inactivity (no more page loads).

Visits made by registered users are also counted, and data is also collected for page views in the admin area (your own visits are important too!), except for the plugins own stats page.

There is a page in the admin area that shows off your browser data in an interactive histogram. You can choose different time periods, visits or page views, browser width or height, and select different parts of the plot to get more detail. There are also some supplementary stats provided. You can download all data in CSV format and analyse with your favourite stats program if required.

If you find this plugin useful, please let me know. It'd be great to put some case studies together. Donations are also welcome ;-)

== Installation ==

1. Download and unpack .zip file
1. Upload `browser-window-stats` directory to `wp-contents/plugins/` directory
1. Activate plugin through the Plugins menu in WordPress

== Screenshots ==

1. The stats page in the admin area

== Changelog ==

= 1.1 =
* Updated frequency counting method to provide more visually correct plot
* Changed plot to show frequency as percentage on y axis, instead of absolute numbers
* Added tooltip when hovering on bar to show accurate percentage and absolute value
* Added axis labels to plot

= 1.0.1 =
* Fixed issue where AJAX call would result in error and not display histogram in some installs
* Stopped enqueueing jQuery Dump

= 1.0 =
* Feature complete release
* Added interactive histogram for display of browser width and height data
* Includes simple quartile stats to supplement histogram

= 0.5 =
* Added dashboard stats page for viewing brief stats and download of data in CSV
* initial release

= 0.4 =
* fixed issue with safari top sites skewing stats

= 0.3 =
* save browser resolution in array for each page view per visitor
* session ended after 15 minutes inactivity
* stopped ajax call when page loaded in iframe

= 0.2 =
* jQuery ajax call

= 0.1 =
* database creation function
* scripts enqueued
* receive and save data

== Upgrade Notice ==

= 1.1 =
This version offers an improved histogram, with more visually accurate plotting, axis labels, a change to plotting frequency in percentages. Absolute numbers can now be accessed via a tooltip when hovering on a bar.

= 1.0.1 =
This version is a minor update addressing an issue where the histogram would not display in some installs. Also removes superfluous javascript.

= 1.0 =
This version is the first feature complete version. The plugin now includes an interactive histogram for displaying browser width and height stats.

== Other Notes ==

Hooks are provided in the plugin to modify the stats table.

To change the time periods displayed use the `bws_columns` filter. Just modify the array with new entries containing DateTime objects as per the example:
`<?php // add a last 60 days column
function add_60_days( $columns ) {
	$columns['last-60-days']['title'] = 'Last 60 Days'; // give the column a title
	$columns['last-60-days']['date_max'] = new DateTime(); // start at the current time
	$columns['last-60-days']['date_min'] = new DateTime(); // generate a previous date
	$columns['last-60-days']['date_min']->modify( '-60 days' );
	
	return $columns;
}
add_filter( 'bws_columns', 'add_60_days' );
?>`

To change the table rows use the `bws_rows` filter, which allows you to add your own stats functions in a similar way to the above:
`<?php // add a new table row
function add_new_row( $rows ) {
	$rows['my-new-row']['title'] = 'Row Title'; // give the row a title
	$rows['my-new-row']['function'] = 'my_stats_function'; // name a function that will generate the output for the row

	return $rows;
}
add_filter( 'bws_rows', 'add_new_row' );

/* Define a function that will parse the data and return the result for visits and page views.
* The function must take two parameters, the mode (visits or page views) and the data for the time period.
*/
function my_stats_function( $mode, $data ) {
	// the function should return different values depending on the mode, a switch is a good way to do this
	switch( $mode ) {
		case 'visits':
			$data_array = $data['visits_data']; // multidimensional array for each visit
			// do calculations on visits data
			break;
		case 'page_views':
			$data_array = $data['page_views_data']; // multidimensional array for each page view
			// do calculations on page views data
			break;
		default:
			return false;
			break;
	}
}
?>`

In the stats function above arrays of data are passed to the function. Other keys that may be accessed are:

* `$data['num_visits']` (int) The number of visits
* `$data['num_registered_visits']` (int) The number of visitors made by registered users
* `$data['num_page_views']` (int) The number of page views
* `$data['num_registered_page_views']` (int) The number of page views made by registered users