<?php
/* Browser Window Stats
*
* Dashboard Page
*/

global $bws_page_hook;

include_once( 'histogram.php' );


/* Registers a new dashboard page and saves page hook
*/
function bws_menu() {
	global $bws_page_hook;
	$bws_page_hook = add_dashboard_page( 'Browser Window Stats', 'Browser Window Stats', 'activate_plugins', 'bws', 'bws_output' );
	
	// load css for plugin page
	add_action( 'admin_print_styles-'.$bws_page_hook, 'bws_css' );
	
	// load js for plugin page
	add_action( 'admin_print_scripts-'.$bws_page_hook, 'bws_js_flot' );
	add_action( 'admin_head-'.$bws_page_hook, 'bws_histogram_js' );

	// load contextual help
	if( class_exists( 'WP_Screen' ) ) {
		// new method for WP>3.3
		add_action( 'load-'.$bws_page_hook, 'bws_contextual_help_3_3' );
	} else {
		// old method for WP<3.3
		add_filter( 'contextual_help', 'bws_contextual_help', 10, 3 );
	}
}
add_action( 'admin_menu', 'bws_menu' );

/* Loads plugin css on dashboard page
*/
function bws_css() {
	wp_enqueue_style( 'bws', plugins_url( 'bws.css', BWS_PLUGIN_FILE ) );
}

/* Returns translatable contextual help text
*/
function bws_help_text( $tab ) {
	switch( $tab ) {
		case 'overview':
		$help = '<p>';
		$help .= __('This plugin collects data for browser width, browser height, screen width, screen height and whether the user is registered. Data is recorded for every page load, and grouped into a site visit for each user. This means the plugin has two modes for displaying data: visits and page views. A visit can be one or many page views; they time out after 15 minutes of inactivity (no more page loads).', 'browser-window-stats' );
		$help .= '</p><p>';
		$help .= __('If a user logs in at any point during their visit, they are recorded as a registered user. If they subsequently log out their page views are still recorded as made by a registered user. This occurs until their visit times out.', 'browser-window-stats');
		$help .= '</p><p>';
		$help .= __('Records are made for every page on your site, including admin pages, except this plugin page. However, data is recorded using javascript, so no entries are recorded for users with javascript disabled, nor are entries recorded when the site is viewed within a frame.', 'browser-window-stats');
		$help .= '</p>';
		break;
		
		case 'histogram':
		$help = '<p>';
		$help .= __('The histogram shows the number of site visits or page views made within a range of browser dimensions. The default range is 100px, which should pick out major differences in browser window size. You can select a portion of the plot area to increase the resolution and see more detail, effectively reducing the range to 50px, then 20px, which should be small enough for most design choices.', 'browser-window-stats' );
		$help .= '</p><p>';
		$help .= __('You can select the same time ranges as shown in the data table, and choose which mode to view data for. Selectively hide and show the width and height dimensions using checkboxes.', 'browser-window-stats');
		$help .= '</p><p>';
		$help .= __('Box Plot stats are provided in the legend to supplement the histogram. The lower quartile cuts off the lowest 25% of the data, while the upper quartile cuts off the highest 25% of the data. The Interquartile range represents the middle 50% of the data, and is a good place to start when interpreting the browser dimensions.', 'browser-window-stats');
		$help .= '</p>';
		break;
		
		case 'table':
		$help = '<p>';
		$help .= __('The table shows some general stats for separate time periods. For each table cell there are two entries, one for site visits and the other for page views, which is in brackets.', 'browser-window-stats' );
		$help .= '</p><p>';
		$help .= __('You can download the full dataset in CSV format for each time period listed in the table, allowing you to perform your own analysis in your favourite statistics package.', 'browser-window-stats');
		$help .= '</p><p>';
		$help .= __('Site administrators can modify the date ranges and the statistics functions displayed in the table using plugin filters. Please see the <a href="http://wordpress.org/extend/plugins/browser-window-stats/other_notes/" title="Browser Window Stats - WordPress Plugins Repository">plugin documentation</a> for details.', 'browser-window-stats');
		$help .= '</p>';
		break;

	}
	return $help;
}

/* Adds contextual help for WP < 3.3
*
* @param string $contextual_help The help dialog
* @param string $screen_id The current page hook
* @param string $screen
* @return The new help dialog
*/
function bws_contextual_help( $contextual_help, $screen_id, $screen ) {
	global $bws_page_hook;
	
	if( $screen_id == $bws_page_hook ) {
		$help = '<h3>'. __('Overview', 'browser-window-stats') .'</h3>';
		$help .= bws_help_text( 'overview' );
		$help .= '<h3>'. __('Histogram', 'browser-window-stats') .'</h3>';
		$help .= bws_help_text( 'histogram' );
		$help .= '<h3>'. __('Table', 'browser-window-stats') .'</h3>';
		$help .= bws_help_text( 'table' );
		
		return $help;
	}
}

/* Adds contextual help for WP > 3.3
*
*/
function bws_contextual_help_3_3() {
	global $bws_page_hook;
	$screen = get_current_screen();
	
	if( $screen->id != $bws_page_hook )
		return;
	
	$screen->add_help_tab( array(
		'id' => 'bws-help-overview',
		'title' => __( 'Overview', 'browser-window-stats' ),
		'content' => bws_help_text( 'overview' )
		) );
	
	$screen->add_help_tab( array(
		'id' => 'bws-help-histogram',
		'title' => __( 'Histogram', 'browser-window-stats' ),
		'content' => bws_help_text( 'histogram' )
		) );
	
	$screen->add_help_tab( array(
		'id' => 'bws-help-table',
		'title' => __( 'Table', 'browser-window-stats' ),
		'content' => bws_help_text( 'table' )
		) );
}


/* Generates output for dashboard page
*/
function bws_output() {
	global $bws_page_hook, $pagenow, $bws_current_screen, $bws_data;
	
	$data = $bws_data;
	
	if( !current_user_can('activate_plugins') )
		wp_die( __('You do not have sufficient permissions to access this page.', 'browser-window-stats') );
	?>
	
	<div class="wrap">
		<?php screen_icon(); ?>
		<h2><?php _e( 'Browser Window Stats', 'browser-window-stats'); ?></h2>
		
		<?php $columns = bws_get_column_parameters(); ?>
		<?php $rows = bws_get_rows(); ?>
		
		<?php bws_histogram_html(); ?>
		
		<table class="widefat">
			<caption><?php _e('Visitor Stats', 'browser-window-stats'); ?></caption>
			<thead>
				<tr>
					<th><?php _e( 'Visitor Stats', 'browser-window-stats' ); ?></th>
					<?php foreach( $columns as $key=>$dates ) : ?>
					<th><?php esc_html_e( $dates['title'] ); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php _e('Download CSV', 'browser-window-stats'); ?></td>
					<?php foreach( $columns as $key=>$dates ) : ?>
						<?php $url_query_args = add_query_arg( 'bws_range', $key ); ?>
					<td>
						<a class="bws-visits" href="<?php echo add_query_arg( 'bws_csv', 'visits', $url_query_args ); ?>"><?php _e('Visits', 'browser-window-stats'); ?></a>
						<a class="bws-page-views" href="<?php echo add_query_arg( 'bws_csv', 'page_views', $url_query_args ); ?>">(<?php _e('Page Views', 'browser-window-stats'); ?>)</a>
					</td>
					<?php endforeach; ?>
				</tr>
				<?php foreach( $rows as $row ) : ?>
				<tr>
					<td><?php esc_html_e( $row['title'] ); ?></td>
					<?php foreach( $columns as $col=>$dates ) : ?>
					<td>
						<span class="bws-visits"><?php esc_html_e( call_user_func( $row['function'], 'visits', $data[$col] ) ); ?></span>
						<span class="bws-page-views">(<?php esc_html_e( call_user_func( $row['function'], 'page_views', $data[$col] ) ); ?>)</span>
					</td>
					<?php endforeach; ?>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		
	</div>
	<?php
}


/* Retrieves data from database and formats into arrays
*
* @param string $date_min Datetime string formatted as Y-m-d H:i:s for the oldest date
* @param string $date_max Datetime string formatted as Y-m-d H:i:s for the the recent date
* @return array Multidimensional array containing visits and page views data for specified time period
*
* @uses $wpdb To access database
* @uses bws_unserialize() To convert serialized strings back into arrays
* @uses bws_average_browser_res() To provide single browser width and height values when reporting site visits
* @uses bws_expand() To format data into page views mode
* @uses bws_count_registered() To add quick access to number of registered user visits/page views
*/
function bws_get_data( $date_min = false, $date_max = false ) {
	global $wpdb, $bws_table_name;
	
	if( !$date_max ) {
		$query = $wpdb->prepare("
			SELECT browser_width, browser_height, screen_width, screen_height, registered_user
			FROM $bws_table_name"
			);
	} else {
		$query = $wpdb->prepare("
			SELECT browser_width, browser_height, screen_width, screen_height, registered_user
			FROM $bws_table_name
			WHERE timestamp BETWEEN CAST( %s AS DATETIME ) AND CAST( %s AS DATETIME )",
			$date_min,
			$date_max
			);
	}
		
	$data = $wpdb->get_results( $query, ARRAY_A );
	
	array_walk( $data, 'bws_unserialize' );
	$visits_arr = $data;
	array_walk( $visits_arr, 'bws_average_browser_res' );
	$num_visits = $wpdb->num_rows;
	
	// expand to page views
	array_walk( $data, 'bws_expand' );
	$page_views_arr = bws_page_views( $data );
	$num_page_views = count( $page_views_arr );
	
	$num_registered_visits = bws_count_registered( $visits_arr );
	$num_registered_page_views = bws_count_registered( $page_views_arr );
	
	$return = array(
		'visits_data' => $visits_arr,
		'num_visits' => $num_visits,
		'num_registered_visits' => $num_registered_visits,
		'page_views_data' => $page_views_arr,
		'num_page_views' => $num_page_views,
		'num_registered_page_views' => $num_registered_page_views
		);
	
	return $return;
}

/* Converts array values that are serialized strings back into arrays
* utility function for array_walk() in bws_get_data()
*
* @param string $value The array value
* @param string $key The array key
*/
function bws_unserialize( &$value, $key ) {
	$value['browser_width'] = unserialize( $value['browser_width'] );
	$value['browser_height'] = unserialize( $value['browser_height'] );
	$value['registered_user'] = (bool) $value['registered_user'];
}

/* Expands visits data array into page views data array using browser width array
* utility function for array_walk() in bws_get_data()
*
* @param string $value The array value
* @param string $key The array key
*/
function bws_expand( &$value, $key ) {
	$screen_w = $value['screen_width'];
	$screen_h = $value['screen_height'];
	$registered = $value['registered_user'];
	
	$new_value = array();
	foreach( $value['browser_width'] as $i=>$width ) {
		$height = $value['browser_height'][$i];
		$new_value[] = array(
			'browser_width' => $width,
			'browser_height' => $height,
			'screen_width' => $screen_w,
			'screen_height' => $screen_h,
			'registered_user' => $registered
			);
	}
	
	$value = $new_value;
}

/* Reformats page views data array into useable form, removing array structures leftover from visits data
* utility function for bws_get_data()
*
* @param array $array Multidimensional array of visits containing arrays of page views
* @return array Array of page views data
*/
function bws_page_views( $array ) {
	$page_views_arr = array();
	foreach( $array as $visit ) {
		foreach( $visit as $view ) {
			$page_views_arr[] = $view;
		}
	}
	return $page_views_arr;
}

/* Averages and rounds to nearest pixel tthe browser height and width for data in visits mode
* utility function for array_walk() in bws_get_data()
*
* @param string $value The array value
* @param string $key The array key
*/
function bws_average_browser_res( &$value, $key ) {
	$value['page_views'] = count( array_filter($value['browser_width'] ) );
	// averages non-zero values only
	$value['browser_width'] = round( array_sum( $value['browser_width'] ) / count( array_filter( $value['browser_width'] ) ) );
	$value['browser_height'] = round( array_sum( $value['browser_height'] ) / count( array_filter( $value['browser_height'] ) ) );
}

/* Counts the number of array entries that represent a registered user visit/page view
* utility function for bws_get_data()
*
* @param array $array An array of visits/page views data
* @return int The number of visits/page views that can be attributed to a registered user
*/
function bws_count_registered( $array ) {
	$count = 0;
	foreach( $array as $value ) {
		if( $value['registered_user'] )
			$count++;
	}
	return $count;
}

/* Defines date ranges for columns of tables, and allows users to define their own using filters
*
* @return array An associative array of columns titles and datetime strings for use in bws_get_data()
*
* @uses apply_filters() To allow users to define their own columns
*/
function bws_get_column_parameters() {
	// last 30 days
	$columns['last-30-days']['title'] = __('Last 30 Days', 'browser-window-stats');
	$columns['last-30-days']['date_max'] = new DateTime();
	
	$columns['last-30-days']['date_min'] = new DateTime();
	$columns['last-30-days']['date_min']->modify( '-30 days' );
	
	
	// last 90 days
	$columns['last-90-days']['title'] = __('Last 90 Days', 'browser-window-stats');
	$columns['last-90-days']['date_max'] = new DateTime();
	
	$columns['last-90-days']['date_min'] = new DateTime();
	$columns['last-90-days']['date_min']->modify( '-90 days' );
	
	// last year
	$columns['last-year']['title'] = __('Last Year', 'browser-window-stats');
	$columns['last-year']['date_max'] = new DateTime();
	
	$columns['last-year']['date_min'] = new DateTime();
	$columns['last-year']['date_min']->modify( '-1 year' );
	
	// all time
	$columns['all-time']['title'] = __('All Time', 'browser-window-stats');
	$columns['all-time']['date_max'] = false;
	$columns['all-time']['date_min'] = false;

	
	$columns = apply_filters( 'bws_columns', $columns );
	
	
	foreach( $columns as $key=>$date_range ) {
		$columns_formatted[$key]['title'] = $date_range['title'];
		if( $date_range['date_min'] < $date_range['date_max'] ) {
			$columns_formatted[$key]['date_min'] = date_format( $date_range['date_min'], 'Y-m-d H:i:s' );
			$columns_formatted[$key]['date_max'] = date_format( $date_range['date_max'], 'Y-m-d H:i:s' );
		} else {
			$columns_formatted[$key]['date_min'] = false;
			$columns_formatted[$key]['date_max'] = false;
		}
	}
	return $columns_formatted;
	
}

/* Defines rows and functions to format data for display in table of general stats, and allows users to add their own using filters
*
* @return array An associative array of row titles and function names to format the data for the row
*
* @uses apply_filters() To allow users to define their own rows
*/
function bws_get_rows() {
	$rows['all'] = array(
		'title' => __('All', 'browser-window-stats'), 
		'function' => 'bws_row_all'
		);
	$rows['registered'] = array(
		'title' => __('Registered Users', 'browser-window-stats'),
		'function' => 'bws_row_registered'
		);
	$rows['percent_reg'] = array(
		'title' => __('% Registered', 'browser-window-stats'),
		'function' => 'bws_row_percent_registered'
		);
	
	apply_filters( 'bws_rows', $rows );
	
	return $rows;
}

/* Returns the number of visits/page views from the data passed to it for display in general stats table
* called by call_user_func() in bws_output()
*
* @param string $col The data mode we are currently outputting, visits or page views
* @param array $data An multidimensional array containing both the visits and page views data for the current datetime range being processed
* @return int|false The number of visits or page views. False on failure.
*/
function bws_row_all( $col, $data ) {
	switch( $col ) {
		case 'visits':
			return $data['num_visits'];
			break;
		case 'page_views':
			return $data['num_page_views'];
			break;
		default:
			return false;
			break;
	}
}

/* Returns the number of visits/page views made by registered users from the data passed to it for display in general stats table
* called by call_user_func() in bws_output()
*
* @param string $col The data mode we are currently outputting, visits or page views
* @param array $data An multidimensional array containing both the visits and page views data for the current datetime range being processed
* @return int|false The number of visits or page views made by registered users. False on failure.
*/
function bws_row_registered( $col, $data ) {
	switch( $col ) {
		case 'visits':
			return $data['num_registered_visits'];
			break;
		case 'page_views':
			return $data['num_registered_page_views'];
			break;
		default:
			return false;
			break;
	}
}

/* Calculates the percentage of visits made by registered users for display in general stats table
* called by call_user_func() in bws_output()
*
* @param string $col The data mode we are currently outputting, visits or page views
* @param array $data An multidimensional array containing both the visits and page views data for the current datetime range being processed
* @return int|false The percentage of visits or page views made by registered users. False on failure.
*/
function bws_row_percent_registered( $col, $data ) {
	switch( $col ) {
		case 'visits':
			$pc = $data['num_registered_visits'] / $data['num_visits'] * 100;
			return round( $pc, 1 );
			break;
		case 'page_views':
			$pc = $data['num_registered_page_views'] / $data['num_page_views'] * 100;
			return round( $pc, 1 );
			break;
		default:
			return false;
			break;
	}
}


/* Collects data for each table column from transient cache, or regerates if expired
* stores generated data in transient cache
*
* @param array $columns The parameters defining the table columns (date ranges) to collect data for
* @return array A multidimensional array containing all data for all columns, ready to be used throughout the page
*
* @uses bws_get_data() To retrieve data from database
* @uses get_transient() To retrive cached data
* @uses set_transient() To save data in cache
*/
function bws_transient_data( $columns ) {
	global $bws_data;

	$bws_data = get_transient( 'bws_data' ); // retrieve stored data
	
	if( $bws_data === false ) { // generate data if transient expired
		// retrieve data for all columns to build csv and use later.
		foreach( $columns as $title=>$dates ) {
			// globalised for use through rest of page
			$bws_data[$title] = bws_get_data( $dates['date_min'], $dates['date_max'] );
		}
		
		set_transient( 'bws_data', $bws_data, 15*60 );
	}
	
	return $bws_data;
}


/* Collects data for each table column using bws_get_data at admin_init
* Builds and outputs a CSV file containing data based on GET request
*
*/
function bws_save_csv() {
	
	$columns = bws_get_column_parameters();

	$bws_data = bws_transient_data( $columns );	

	if( !empty( $_GET['bws_csv'] ) ) { // csv requested - lets build it
			
		$titles = array(
			__('Browser Width', 'browser-window-stats'),
			__('Browser Height', 'browser-window-stats'),
			__('Screen Width', 'browser-window-stats'),
			__('Screen Height', 'browser-window-stats'),
			__('Registered User', 'browser-window-stats')
			);
		
		$bws_csv = filter_input( INPUT_GET, 'bws_csv', FILTER_SANITIZE_STRING );
		$bws_range = filter_input( INPUT_GET, 'bws_range', FILTER_SANITIZE_STRING );
		
		if( !array_key_exists( $bws_range, $columns ) ) // check the range from user input is specified in the columns
			return false;
		
		$range_title = $columns[$bws_range]['title'];
		$date_min = $columns[$bws_range]['date_min'];
		$date_max = $columns[$bws_range]['date_max'];
		
		// create temporary file
		$temp_file = tempnam( sys_get_temp_dir(), 'bws' );
		$file = fopen( $temp_file, 'w' );
		
		$csv_header = __('Browser Window Stats - CSV Export', 'browser-window-stats')."\n".
			sprintf( __('A WordPress plugin by %s', 'browser-window-stats'), 'Tom Fletcher' )."\n\n";
		
		if( !$date_min && !$date_max ) {
			$csv_header .= sprintf( __('All Data (%s)', 'browser-window-stats'), $range_title)."\n\n";
			$filename = 'bws_data_'.$bws_csv.'_all_data.csv';
		} else {
			$csv_header .= sprintf( __('Data from %1$s until %2$s (%3$s)', 'browser-window-stats'), $date_min, $date_max, $range_title)."\n\n";
			$filename = 'bws_data_'.$bws_csv.'_'.$date_min.'_'.$date_max.'.csv';
		}
		
		fwrite( $file, $csv_header );
		
		switch( $bws_csv ) {
			case 'visits':
				fwrite( $file, __("Data for site visits\n\nN.B. Browser Width and Browser Height is the average is the average for each page view during the visit.\n\n", 'browser-window-stats') );
				$titles[] = __('Page Views', 'browser-window-stats');
				fputcsv( $file, $titles );
				$data = $bws_data[$bws_range]['visits_data'];
				break;
			
			case 'page_views':
				fwrite( $file, __("Data for page views\n\n", 'browser-window-stats') );
				fputcsv( $file, $titles );
				$data = $bws_data[$bws_range]['page_views_data'];
				break;
		}
		
		foreach( $data as $row ) {
			$row['registered_user'] = ( $row['registered_user'] ) ? __('true', 'browser-window-stats') : __('false', 'browser-window-stats') ;
			fputcsv( $file, $row );
		}
				
		fclose( $file );
		
		// output csv file
		if( file_exists( $temp_file ) ) {
			header('Content-Type: text/csv');
			header('Content-Disposition: attachment; filename="'. sanitize_file_name( $filename ) .'"');
			header('Content-Description: File Transfer');
			header('Content-Length: ' . filesize($temp_file));
			header('Content-Transfer-Encoding: binary');
			header('Expires: 0');
			header('Cache-Control: must-revalidate');
			header('Pragma: public');
			ob_clean();
			flush();
			if( readfile( $temp_file ) ) {
				unlink( $temp_file ); // delete after download
			}
		}
	}
}
add_action( 'admin_init', 'bws_save_csv' );

?>