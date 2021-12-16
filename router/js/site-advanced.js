//======================================================================================================
// Javascript functions for "Advanced / Firewall Settings"
//======================================================================================================
function Init_Firewall()
{
	$(".checkbox").bootstrapSwitch();
	$("#drop_port_scan").on('switchChange.bootstrapSwitch', function(event, state) {
		if (state == true)
			$("#port_scan_options").slideDown(400);
		else
			$("#port_scan_options").slideUp(400);
	});
	$("#apply_changes").click( FireWall_Apply );
}

function FireWall_Apply()
{
	// Assemble the post data for the AJAX call:
	postdata = {
		'sid':            SID,
		'action':         'submit',
		'drop_port_scan': $("#drop_port_scan").prop("checked") ? "Y" : "N",
		'log_port_scan':  $("#log_port_scan").prop("checked") ? "Y" : "N",
		'log_udp_flood':  $("#log_udp_flood").prop("checked") ? "Y" : "N",
		'drop_ping':      $("#drop_ping").prop("checked") ? "Y" : "N",
		'drop_ident':     $("#drop_ident").prop("checked") ? "Y" : "N",
		'drop_multicast': $("#drop_multicast").prop("checked") ? "Y" : "N",
		'redirect_dns':   $("#redirect_dns").prop("checked") ? "Y" : "N",
	};
	//alert(JSON.stringify(postdata, null, 5)); return;

	// Perform our AJAX request to change the WAN settings:
	$("#apply_msg").html( $("#apply_default").html() );
	$("#apply_cancel").addClass("hidden");
	$("#apply-modal").modal("show");
	$.post("/advanced/firewall", postdata, function(data) {
		data = data.trim();
		if (data == "RELOAD")
			document.location.reload(true);
		else if (data == "OK")
			$("#apply-modal").modal("hide");
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
// Javascript functions for "Advanced / DMZ Settings"
//======================================================================================================
function Init_DMZ()
{
	$("#enable_dmz").bootstrapSwitch().on('switchChange.bootstrapSwitch', function(event, state) {
		if (state == true)
			$("#dmz_info").slideDown(400);
		else
			$("#dmz_info").slideUp(400);
	});
	$("#src_any").click(function() {
		$("#range_from").attr("disabled", "disabled");
		$("#range_to").attr("disabled", "disabled");
		$("#mask_ip").attr("disabled", "disabled");
		$("#mask_bits").attr("disabled", "disabled");
	});
	$("#src_range").click(function() {
		$("#range_from").removeAttr("disabled");
		$("#range_to").removeAttr("disabled");
		$("#mask_ip").attr("disabled", "disabled");
		$("#mask_bits").attr("disabled", "disabled");
	});
	$("#src_mask").click(function() {
		$("#range_from").attr("disabled", "disabled");
		$("#range_to").attr("disabled", "disabled");
		$("#mask_ip").removeAttr("disabled");
		$("#mask_bits").removeAttr("disabled");
	});
	$("#range_from").change(function() {
		$("#range_to").val( $("#range_from").val().substring( $("#range_from").val().lastIndexOf('.') + 1) );
	});
	$("#range_to").inputmask('integer', {min:0, max:254});
	$("#mask_bits").inputmask('integer', {min:0, max: 32});
	$("#dest_ip").click(function() {
		$("#ip_addr").removeAttr("disabled");
		$("#mac_addr").attr("disabled", "disabled");
	});
	$("#dest_mac").click(function() {
		$("#ip_addr").attr("disabled", "disabled");
		$("#mac_addr").removeAttr("disabled");
	});
	$("#mac_addr").inputmask('mac');
	$("#apply_changes").click( DMZ_Apply );
}

function DMZ_Apply()
{
	// Assemble the post data for the AJAX call:
	postdata = {
		'sid':        SID,
		'action':     'submit',
		'enable_dmz': $("#enable_dmz").prop("checked") ? "Y" : "N",
		'src_type':   $("[name=src_type]:checked").val(),
		'range_from': $("#range_from").val(),
		'range_to':   $("#range_to").val(),
		'mask_ip':    $("#mask_ip").val(),
		'mask_bits':  $("#mask_bits").val(),
		'dest_type':   $("[name=dest_type]:checked").val(),
		'dest_ip':    $("#ip_addr").val(),
		'dest_mac':   $("#mac_addr").val(),
	};
	//alert(JSON.stringify(postdata, null, 5)); return;

	// Perform our AJAX request to change the WAN settings:
	$("#apply_msg").html( $("#apply_default").html() );
	$("#apply-modal").modal("show");
	$("#apply_cancel").addClass("hidden");
	$.post("/advanced/dmz", postdata, function(data) {
		data = data.trim();
		if (data == "RELOAD")
			document.location.reload(true);
		else if (data == "OK")
			$("#apply-modal").modal("hide");
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
	$.post("/advanced/bandwidth", postdata, function(data) {
		if (Object.keys(data.rx).length == 0)
		{
			$("#table_data").addClass("hidden");
			$("#table_empty").removeClass("hidden");
			return;
		}
		$("#table_data").removeClass("hidden");
		$("#table_empty").addClass("hidden");
		$("#table_header").html( data.title );
		$("#table_data").html( data.table );
		if (barChart != false)
			barChart.destroy();
		barChart = new Chart($("#barChart"), {
			type: "bar",
			data: {
			  labels  : Object.values(data.label),
			  datasets: 
				[
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
				datasetFill             : false
			}
		});
	}).fail(function() {
		alert("AJAX call failed!");
	});
}
