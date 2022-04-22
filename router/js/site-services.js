//======================================================================================================
// Javascript functions for "Services / UPnP Setup":
//======================================================================================================
function __Services_Init(service)
{
	$("#refresh_switch").bootstrapSwitch();
	$("#refresh_switch").on('switchChange.bootstrapSwitch', function(event, state) {
		__Service_Call( state, state ? 'enable' : 'disable', service );
	});
	$("#service_status").click(function() {
		__Service_Call( 'status', service );
	});
	$("#service_start").click(function() {
		__Service_Call( 'start', service );
	});
	$("#service_stop").click(function() {
		__Service_Call( 'stop', service );
	});
}

function __Service_Call(cmd, service)
{
	$("#apply-modal-middle").removeClass("modal-xl");
	$("#apply_msg").html( $("#apply_default").html() );
	$("#apply_cancel").addClass("hidden");
	$("#apply-modal").modal("show");
	$.post("/services", __postdata(cmd, service), function(data) {
		$("#apply-modal").modal("hide");
		data = data.trim();
		if (data == "RELOAD" || data == "OK")
			document.location.reload(true);
		else
		{
			$("#apply-modal-middle").addClass("modal-xl");
			$("#apply_msg").html(data);
			$("#apply_cancel").removeClass("hidden");
		}
	}).fail(function() {
		if (cmd == 'enable' || cmd == 'disable')
			$("#refresh_switch").bootstrapSwitch('state', !state, true);
		$("#apply_msg").html("AJAX call failed");
		$("#apply_cancel").removeClass("hidden");
	});
}

//======================================================================================================
// Javascript functions for "Services / UPnP Setup":
//======================================================================================================
function Init_UPnP()
{
	__Services_Init('miniupnpd');
	$("#toggle_service").click(function() {
		$("#refresh_switch").bootstrapSwitch('state', true);
	});
	$("#upnp_refresh").click(function() {
		$.post('/services/upnp', __postdata("list"), function(data) {
			if (data == "RELOAD")
				document.location.reload(true);
			else
				$("#upnp-table").html(data);
		});
	}).click();
	$("#upnp_submit").click(UPnP_Submit);
}

function UPnP_Submit()
{
	// Hide confirmation dialog if shown:
	$("#confirm-modal").modal("hide");

	// Assemble the post data for the AJAX call:
	postdata = {
		'sid':           SID,
		'action':        'submit',
		'secure_mode':   $("#secure_mode").prop("checked") ? "Y" : "N",
		'enable_natpmp': $("#enable_natpmp").prop("checked") ? "Y" : "N",
		'ext_ifname':    $("#ext_ifname").val(),
		'listening_ip':  $("#listening_ip").val().join(","),
	};
	//alert(JSON.stringify(postdata, null, 5)); return;

	// Perform our AJAX request to change the WAN settings:
	$("#apply_msg").html( $("#apply_default").html() );
	$("#apply-modal").modal("show");
	$("#apply_cancel").addClass("hidden");
	$.post("/services/upnp", postdata, function(data) {
		data = data.trim();
		if (data == "RELOAD" || data == "OK")
			document.location.reload(true);
		else
		{
			$("#apply_msg").html(data);
			$(".alert_control").removeClass("hidden");
		}
	}).fail(function() {
		$("#apply_msg").html("AJAX call failed!");
		$("#apply_cancel").removeClass("hidden");
	});
}

//======================================================================================================
// Javascript functions for "Advanced / Bandwidth"
//======================================================================================================
function Init_Bandwidth(tx, rx)
{
	__Services_Init('vnstat');
	barTX = tx;
	barRX = rx;
	barChart = false;
	$("#update_chart").click(Bandwidth_Update).click();
	$("#interface").change(Bandwidth_Update);
	$("#mode").change(Bandwidth_Update);
}

function Bandwidth_Update()
{
	// Assemble the post data for the AJAX call:
	postdata = {
		'sid':        SID,
		'action':     $("#mode").val(),
		'iface':      $("#interface").val(),
	};
	//alert(JSON.stringify(postdata, null, 5)); return;

	// Perform our AJAX request to change the WAN settings:
	$.post("/services/bandwidth", postdata, function(data) {
		if (data.reload == true)
			document.location.reload(true);
		if (Object.keys(data.rx).length == 0)
		{
			$("#table_data").addClass("hidden");
			$("#table_empty").removeClass("hidden");
		}
		else
		{
			$("#table_data").removeClass("hidden");
			$("#table_empty").addClass("hidden");
		}
		$("#table_header").html( data.title );
		$("#table_data").html( data.table );
		if (barChart != false)
			barChart.destroy();
		barChart = new Chart($("#barChart"), {
			type: "bar",
			data: {
			  labels  : Object.values(data.label),
			  datasets: [
					{
						label               : barTX,
						backgroundColor     : 'rgba(60,141,188,0.9)',
						borderColor         : 'rgba(60,141,188,0.8)',
						pointRadius          : false,
						pointColor          : '#3b8bba',
						pointStrokeColor    : 'rgba(60,141,188,1)',
						pointHighlightFill  : '#fff',
						pointHighlightStroke: 'rgba(60,141,188,1)',
						data                : Object.values(data.tx)
					},
					{
						label               : barRX,
						backgroundColor     : 'rgba(210, 214, 222, 1)',
						borderColor         : 'rgba(210, 214, 222, 1)',
						pointRadius         : false,
						pointColor          : 'rgba(210, 214, 222, 1)',
						pointStrokeColor    : '#c1c7d1',
						pointHighlightFill  : '#fff',
						pointHighlightStroke: 'rgba(220,220,220,1)',
						data                : Object.values(data.rx)
					},
				]
			},
			options: {
				responsive              : true,
				maintainAspectRatio     : false,
				datasetFill             : false,
				tooltips: {
					borderWidth: 1,
					borderColor: "white",
					callbacks: {
						label: function(tooltipItem, chartdata) {
							var dataItem = chartdata.datasets[ tooltipItem.datasetIndex ].data[ tooltipItem.index ];
							var labelItem = chartdata.datasets[ tooltipItem.datasetIndex ].label;
							return labelItem + ": " + dataItem + " " + data.unit;
						}
					}
				},
				scales: {
					yAxes: [{
						display: true,
						ticks: {
							beginAtZero: true,
		                    callback: function(value, index, values) {
		                        return value + " " + data.unit;
		                    }
						}
					}]
				}
			}
		});
	}).fail(function() {
		$("#table_data").removeClass("hidden");
		$("#table_empty").addClass("hidden");
		$("#table_data").html('<tr><td colspan="4"><center><strong>AJAX Call Failed</strong></center></td></tr>');
	});
}

//======================================================================================================
// Javascript functions for "Services / UPnP Setup":
//======================================================================================================
function Init_Multicast()
{
	__Services_Init('miniupnpd');
	$("#multicast_submit").click(Multicast_Submit);
}

function Multicast_Submit()
{
	// Assemble the post data for the AJAX call:
	postdata = {
		'sid':       SID,
		'action':    'submit',
		'listen_on': $("#listening_on").val().join(","),
	};
	//alert(JSON.stringify(postdata, null, 5)); return;

	// Perform our AJAX request to change the WAN settings:
	$("#apply_msg").html( $("#apply_default").html() );
	$("#apply-modal").modal("show");
	$("#apply_cancel").addClass("hidden");
	$.post("/services/multicast", postdata, function(data) {
		data = data.trim();
		if (data == "RELOAD" || data == "OK")
			document.location.reload(true);
		else
		{
			$("#apply_msg").html(data);
			$(".alert_control").removeClass("hidden");
		}
	}).fail(function() {
		$("#apply_msg").html("AJAX call failed!");
		$("#apply_cancel").removeClass("hidden");
	});
}
