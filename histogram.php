<?php
/* Histogram.php
* creates data array for plotting histograms in Flot
* receives AJAX request and sends data back
*/


function bws_histogram_js() {	
	// enqueue excanvas conditionally
	echo '<!--[if lte IE 8]><script type="text/javascript" src="'.plugins_url( 'excanvas.min.js', BWS_PLUGIN_FILE ).'"></script><![endif]-->';
	// embed histogram.js
	echo '<script type="text/javascript">';
	$js = file_get_contents( plugin_dir_path( BWS_PLUGIN_FILE ).'histogram.js' ); 
	$js = str_replace( '%nonce%', esc_js( wp_create_nonce( 'bws-histogram-referer-options-check' ) ), $js );
	$js = str_replace( '%percent%', esc_js( _x( 'Percent', 'plot tooltip label', 'browser-window-stats' ) ), $js );
	$js = str_replace( '%absolute%', esc_js( _x( 'Absolute', 'plot tooltip label', 'browser-window-stats' ) ), $js );
	$js = str_replace( '%dimension%', esc_js( _x( 'Dimension / px', 'plot x axis label', 'browser-window-stats' ) ), $js );
	$js = str_replace( '%frequency%', esc_js( _x( 'Frequency / %', 'plot y axis label', 'browser-window-stats' ) ), $js );
	echo $js;
	echo '</script>';
}

function bws_js_flot() {
	wp_enqueue_script( 'flot', plugins_url( 'jquery.flot.min.js', BWS_PLUGIN_FILE ), array('jquery') );
	
	//wp_enqueue_script( 'jquery-dump', plugins_url( 'jquery.dump.js', BWS_PLUGIN_FILE ), array('jquery') );
}

/* Output HTML for graph placeholder and form for graph options
*/
function bws_histogram_html() { ?>
	<div id="bws-histogram-controls">
		<form method="get" action="">
			<label for="date-range"><?php _e( 'Date Range', 'browser-window-stats' ); ?></label>
			<select name="date-range" id="date-range">
				<?php
				$columns = bws_get_column_parameters();
				foreach( $columns as $value=>$range ) : ?>
					<option id="<?php echo esc_attr( $value ); ?>" value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $range['title'] ); ?></option>
				<?php endforeach; ?>
			</select>
			
			<label for="mode"><?php _e( 'Plot Mode', 'browser-window-stats' ); ?></label>
			<select name="mode" id="mode">
				<option id="mode-visits" value="mode-visits"><?php _e( 'Visits', 'browser-window-stats' ); ?></option>
				<option id="mode-page-views" value="mode-page-views"><?php _e( 'Page Views', 'browser-window-stats' ); ?></option>
			</select>
			
			<input type="submit" value="<?php esc_attr_e( 'Update', 'browser-window-stats' ); ?>" class="button" />
		</form>
		
		<div id="bws-histogram-display">
			<strong><?php _e( 'Display:', 'browser-window-stats' ); ?></strong>
			<input type="checkbox" id="show-width" name="width" checked="checked" />
			<label for="show-width"><?php _e( 'Width', 'browser-window-stats' ); ?></label>
			<input type="checkbox" id="show-height" name="height" checked="checked" />
			<label for="show-height"><?php _e( 'Height', 'browser-window-stats' ); ?></label>
		</div>
	</div>
	
	<div id="bws-histogram">
		<div id="bws-placeholder"></div>
		<div id="bws-supplemental"></div>
	</div>
	
<?php
}


function bws_histogram_ajax() {
	check_ajax_referer( 'bws-histogram-referer-options-check', 'security' );
	
	if( current_user_can( 'activate_plugins' ) ) :
	
		parse_str( $_GET['bws_histogram_update'], $form );
		
		$form = filter_var_array( $form, FILTER_SANITIZE_STRING );
		
		// check range are floats, otherwise it's null
		if( is_array( $_GET['range'] ) ) {
			$range = filter_var_array( $_GET['range'], FILTER_VALIDATE_FLOAT );
		} else {
			$range = false;
		}
		$range = ( $range ) ? $_GET['range'] : null ;
		
		
		$columns = bws_get_column_parameters();
		if( !array_key_exists( $form['date-range'], $columns ) )
			$return = new WP_Error( 'bws-invalid-date', __( 'The date range chosen is not valid. Please select from the drop down list.', 'browser-window-stats' ) );
		
		if( isset( $return ) ) {
			echo json_encode( $return );
			exit;
		}
		
		if( !($form['mode'] == 'mode-visits' || $form['mode'] == 'mode-page-views') )
			$return = new WP_Error( 'bws-invalid-mode', __( 'The plot mode chosen is not valid. Please select from the drop down list.', 'browser-window-stats' ) );

		
		if( !isset( $return ) ) { // assuming no errors
			$data = bws_transient_data( $columns );
			$date_range = $form['date-range'];
						
			if( $form['mode'] == 'mode-visits' )
				$data = $data[$date_range]['visits_data'];
			
			if( $form['mode'] == 'mode-page-views' )
				$data = $data[$date_range]['page_views_data'];
			
			
			if( $range == null ) {
				$resolution = 100;
			} else {
				extract( $range );
				$range = $to - $from;
				
				if( $range > 1200 ) {
					$resolution = 100;
				} else if( $range > 500 ) {
					$resolution = 50;
				} else {
					$resolution = 20;
				}
				$axis['min'] = ( floor( $from / $resolution ) * $resolution );
				$axis['max'] = ( ceil( $to / $resolution ) * $resolution );
				$axis['html'] = __( 'You are viewing a portion of this plot. Some data may not be visible. ', 'browser-window-stats' );
				$axis['html'] .= '<a href="#reset-plot" title="'. __( 'Reset This Plot', 'browser-window-stats' ) .'">'. __( 'Reset this plot to see all data', 'browser-window-stats' ). '</a>.';
			}
						
			$width = bws_frequency( $data, 'width', $resolution );
			$height = bws_frequency( $data, 'height', $resolution );
			
			if( $range == null ) {
				$axis['min'] = 0;
				$copy_width = $width['histogram'];
				$axis_max = each( array_pop( $copy_width ) );
				$axis['max'] = $axis_max['value'] + $resolution + $resolution;
			}

			
			$return['width'] = array(
				'label' => 'Browser Width',
				'data' => $width['histogram'],
				'bars' => array('barWidth' => $resolution ),
				'color' => '#3C68AF'
				);
			
			$return['height'] = array(
				'label' => 'Browser Height',
				'data' => $height['histogram'],
				'bars' => array('barWidth' => $resolution ),
				'color' => '#48AF69'
				);
			
			$return['stats'] = bws_stats_table( $width['stats'], $height['stats'] );
			
			if( isset( $axis ) )
				$return['axis'] = $axis;
			
			$return['count'] = $width['count'];
		}
		
		
		header( 'Content-Type: application/json' );
		echo json_encode( $return );
		
	endif;
	
	exit;
}
add_action( 'wp_ajax_bws-histogram-data', 'bws_histogram_ajax' );

function bws_stats_table( $width, $height ) {
	$html = '<table><tbody>';
	//$html .= '';
	
	
	$html .= '<tr id="legend-width"><th colspan="2"><span class="colour" style="background: #ACBDDC; border-color: #3C68AF;"></span>'. __( 'Browser Width', 'browser-window-stats' ) .'</th></tr>';
	$html .= '<tr><td class="label">'. __( 'Minimum', 'browser-window-stats' ) .'</td><td class="value">'. $width['min'] .'</td></tr>';
	$html .= '<tr><td class="label">'. __( 'Lower Quartile', 'browser-window-stats' ) .'</td><td class="value">'. $width['q_lower'] .'</td></tr>';
	$html .= '<tr><td class="label">'. __( 'Median', 'browser-window-stats' ) .'</td><td class="value">'. $width['median'] .'</td></tr>';
	$html .= '<tr><td class="label">'. __( 'Upper Quartile', 'browser-window-stats' ) .'</td><td class="value">'. $width['q_upper'] .'</td></tr>';
	$html .= '<tr><td class="label">'. __( 'Maximum', 'browser-window-stats' ) .'</td><td class="value">'. $width['max'] .'</td></tr>';
	
	$html .= '<tr id="legend-height"><th colspan="2"><span class="colour" style="background: #B4DBBA; border-color: #48AF69;"></span>'. __( 'Browser Height', 'browser-window-stats' ) .'</th></tr>';
	$html .= '<tr><td class="label">'. __( 'Minimum', 'browser-window-stats' ) .'</td><td class="value">'. $height['min'] .'</td></tr>';
	$html .= '<tr><td class="label">'. __( 'Lower Quartile', 'browser-window-stats' ) .'</td><td class="value">'. $height['q_lower'] .'</td></tr>';
	$html .= '<tr><td class="label">'. __( 'Median', 'browser-window-stats' ) .'</td><td class="value">'. $height['median'] .'</td></tr>';
	$html .= '<tr><td class="label">'. __( 'Upper Quartile', 'browser-window-stats' ) .'</td><td class="value">'. $height['q_upper'] .'</td></tr>';
	$html .= '<tr><td class="label">'. __( 'Maximum', 'browser-window-stats' ) .'</td><td class="value">'. $height['max'] .'</td></tr>';
	
	$html .= '</tbody></table>';
	
	return $html;
}


function bws_frequency( $data, $mode, $resolution ) {
	// extract width/height from data
	if( $mode == 'width' ) {
		foreach( $data as $entry ) {
			$dimensions[] = $entry['browser_width'];
		}
	}
		
	if( $mode == 'height' ) {
		foreach( $data as $entry ) {
			$dimensions[] = $entry['browser_height'];
		}
	}
	
	if( !isset( $dimensions ) )
		return false;
	
	sort( $dimensions );
	
	$stats['min'] = min( $dimensions );
	$stats['max'] = max( $dimensions );
	$stats['median'] = bws_median( $dimensions );
	$stats['q_lower'] = bws_q1( $dimensions );
	$stats['q_upper'] = bws_q3( $dimensions );
	$count = count( $dimensions );
		
	array_walk( $dimensions, 'bws_floor', $resolution );
		
	$values_count = array_count_values( $dimensions );
	array_walk( $values_count, 'bws_freq_percent', $count );
	
	$floored_min = floor( $stats['min'] / $resolution ) * $resolution;
	for( $i = $floored_min; $i <= $stats['max'] ; $i=$i + $resolution ) {
		$frequency[] = array( $i, $values_count[$i] );
	}
	
	return array( 'histogram' => $frequency, 'stats' => $stats, 'count' => $count );
}

/* round down values to resolution required ready for frequency count
*/
function bws_floor( &$value, $key, $resolution ) {
	$value = strval( floor( $value / $resolution ) * $resolution );
}

function bws_freq_percent( &$value, $key, $count ) {
	$value = ( $value / $count ) * 100;
}

function bws_median( $data ) {
	$count = count( $data );
	$half_index = round( $count / 2 ) - 1;
	
	return $data[ $half_index ];
}

function bws_q1( $data ) {
	$count = count( $data );
	$index = round( $count * 0.25 ) - 1;
	
	return $data[ $index ];
}

function bws_q3( $data ) {
	$count = count( $data );
	$index = round( $count * 0.75 ) - 1;
	
	return $data[ $index ];
}

?>