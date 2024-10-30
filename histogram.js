jQuery(document).ready(function($) {
	
	// adjust placeholder height to maintain aspect ratio
	var width = $("#bws-placeholder").width();
	$("#bws-histogram").height( width / 1.63 );
	
	// load plot on first page load
	bwsGetData();
	
	// update plot
	$("#bws-histogram-controls form").submit( function() {
		bwsGetData( true );
		return false;
	});
	
	// change display
	$("#bws-histogram-display input").change( bwsChoosePlots );
	
	// change resolution
	$("#bws-placeholder").bind("plotselected", function (event, ranges) {
		$.bwsRange = ranges.xaxis;
		bwsGetData( false );
	});
	
	// reset plot
	$("#bws-axis-reset a").live( "click", function() {
		bwsGetData( true );
		return false;
	});
	
	function bwsGetData( reset ) {
		if( reset === undefined ) {
			reset = false;
		}
		
		$("#bws-histogram-controls .button").attr("disabled", "disabled");
		
		// if no range has been selected or we want to reset
		if( !$.bwsRange ) {
			$.bwsRange = null;
		}
		if( reset ) {
			$.bwsRange = null;
		}
		
		$.formData = $("#bws-histogram-controls form").serializeArray();
		$.submitData = {
			action: 'bws-histogram-data',
			security: "%nonce%",
			bws_histogram_update: $.param( $.formData ),
			range: $.bwsRange
			}
		
		$.get( ajaxurl, $.submitData, function( response ) {
			if( response.errors ) {
				if( $("#bws-error").length == 0 ) {
					$("#bws-histogram-controls").before('<div id="bws-error" class="error"></div>');
				}
				
				$.each(response.errors, function() {
					$("#bws-error").hide().html('<p><strong>Error:</strong> '+ this +'</p>').slideDown();
				});
			}
			
			if( response.width.data ) {
				// cache response data
				$.bwsData = response;
				
				$("#bws-error").slideUp(400, function() {
					$(this).remove();
				});
				
				
				if( response.axis.html ) {
					if( $("#bws-axis-reset").length == 0 ) {
						$("#bws-histogram").before('<div id="bws-axis-reset"><p></p></div>');
						$("#bws-axis-reset p").html( response.axis.html );
					}
				} else {
					$("#bws-axis-reset").remove();
				}
				
				bwsChoosePlots();
				
				$("#bws-supplemental").html( response.stats );
			}
		$("#bws-histogram-controls .button").removeAttr("disabled");
		});		
		
		return false;
	}
	
	function bwsChoosePlots() {
		// set scale on x axis if zoomed in
		if( $.bwsData.axis ) {
			var xaxis = {
				color: '#545454',
				min: $.bwsData.axis.min,
				max: $.bwsData.axis.max,
				axisLabel: 'Dimension / px',
				axisLabelUseCanvas: false
				}
		} else {
			var xaxis = {
				color: '#545454',
				axisLabel: '%dimension%',
				axisLabelUseCanvas: false
				}
		}
		
		var options = {
			bars: {show: true},
			legend: {show: false},
			grid: {color: '#eee', borderWidth: 0, backgroundColor: 'white', hoverable: true},
			xaxis: xaxis,
			yaxis: {color: '#545454', axisLabel: '%frequency%', axisLabelUseCanvas: false},
			selection: { mode: "x" }
			}
		
		

		var series = [];
		$("#bws-histogram-display").find("input:checked").each( function() {
			var key = $( this ).attr("name");
			if( key && $.bwsData[key] ) {
				series.push( $.bwsData[key] );
			}
		});
		$.plot( "#bws-placeholder", series, options );
	}
	
	function bwsTooltip( x, y, contents ) {
		var posRight = $(window).width() - x + 10;
		$('<div id="bws-tooltip">' + contents + '</div>').css({top: y, right: posRight, display: 'none'}).appendTo("body").fadeIn();
	}
	
	$.previousPoint = null;
	$("#bws-placeholder").bind("plothover", function( event, pos, item ) {
		if( item ) { // we're hovering on a point
			if( $.previousPoint != item.dataIndex ) { // check if we've moved to new a new point
				$.previousPoint = item.dataIndex; // update the point if we have
				
				$("#bws-tooltip").remove();
				
				var valPercent = Math.round( item.datapoint[1] * 10 ) / 10;
				var valAbs = Math.round( ( item.datapoint[1] / 100 ) * $.bwsData.count );
				
				var tooltipText = '%percent% = ' + valPercent + '<br />%absolute% = ' + valAbs;
				bwsTooltip( item.pageX, item.pageY, tooltipText );
			}
		} else {
			$("#bws-tooltip").remove();
			$.previousPoint = null;
		}
	});
});

