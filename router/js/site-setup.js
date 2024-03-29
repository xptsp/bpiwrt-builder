var iface_used;
var reboot_suggested = false;

//======================================================================================================
// Javascript functions for "Setup / Wired Setup"
//======================================================================================================
function Init_Wired(iface)
{
	iface_used = iface;
	page_url = '/setup/wired';

	// Main screen setup and handlers:
	$(".checkbox").bootstrapSwitch();
	$("#op_mode").change(function() {
		if ($(this).find("option:selected").val() == 'dhcp')
		{
			$("#static_ip_div").slideUp(400);
			$("#add_reservation_href").addClass("hidden");
		}
		else
		{
			$("#static_ip_div").slideDown(400);
			if ($("#use_dhcp").is(":checked"))
				$("#add_reservation_href").removeClass("hidden");
			else
				$("#add_reservation_href").addClass("hidden");
		}
	}).change();
	$(".bridge").click( function() {
		$(this).toggleClass("active");
	});
	$("#apply_changes").click(Wired_Submit);
	$('#use_dhcp').click(function() {
		if ($(this).is(":checked")) {
			$(".dhcp").removeAttr("disabled");
			$(".dhcp_div").slideDown(400);
			$("#add_reservation_href").removeClass("hidden");
			$("#ip_addr").val( $("#ip_addr").val() );
		} else {
			$(".dhcp").attr("disabled", "disabled");
			$(".dhcp_div").slideUp(400);
			$("#add_reservation_href").addClass("hidden");
		}
	});
	$('.ip_address').inputmask("ip").change(function() {
		parts = $("#ip_addr").val().substring(0, $("#ip_addr").val().lastIndexOf('.'));
		$("#dhcp_start").val( parts + $("#dhcp_start").val().substring( $("#dhcp_start").val().lastIndexOf('.')) );
		$("#dhcp_end").val( parts + $("#dhcp_end").val().substring( $("#dhcp_end").val().lastIndexOf('.')) );
		$("#dhcp_ip_addr").val( parts + $("#dhcp_ip_addr").val().substring( $("#dhcp_ip_addr").val().lastIndexOf('.')) );
	});
	$("#dhcp_lease").inputmask("integer");
	$("#dhcp_units").change(function() {
		if ($(this).val() == "infinite")
			$("#dhcp_lease").attr("disabled", "disabled");
		else
			$("#dhcp_lease").removeAttr("disabled");
	});
}

function Wired_Submit()
{
	// Assemble the post data for the AJAX call:
	postdata = {
		'sid':        SID,
		'iface':      iface_used,
		'action':     $("#op_mode option:selected").val(),
		'ip_addr':    $("#ip_addr").val(),
		'ip_mask':    $("#ip_mask").val(),
		'ip_gate':    $("#ip_gate").val(),
		'use_dhcp':   $("#use_dhcp").is(":checked") ? 1 : 0,
		'dhcp_start': $("#dhcp_start").val(),
		'dhcp_end':   $("#dhcp_end").val(),
		'dhcp_lease': $("#dhcp_lease").val() + $("#dhcp_units").val(),
		'firewalled': $("#firewalled").is(":checked") ? 'Y' : 'N',
		'no_net':     $("#if_no_net").is(":checked") ? 'Y' : 'N',
		'reboot':     reboot_suggested,
	};
	if ($("#dhcp_units").val() == "infinite")
		postdata.dhcp_lease = 'infinite';
	$(".bridge").each(function() {
		if ($(this).hasClass("active"))
			postdata.bridge += " " + $(this).text().trim();
	});
	//alert(JSON.stringify(postdata, null, 5)); return;

	// Notify the user what we are doing:
	$("#apply_msg").html( $("#apply_default").html() );
	$("#apply_cancel").addClass("hidden");
	if (reboot_suggested)
		$("#reboot-modal").modal("show");
	else
		$("#apply-modal").modal("show");

	// Perform our AJAX request to change the WAN settings:
	$.post('/setup/wired', postdata, function(data) {
		if (data == "OK")
			document.location.reload(true);
		else if (data == "REBOOT")
			Reboot_Confirmed();
		else
		{
			$("#apply_msg").html(data);
			$("#apply_cancel").removeClass("hidden");
		}
	}).fail(function() {
		$("#apply_msg").html("AJAX call failed!");
		$("#apply_cancel").removeClass("hidden");
	});
}

//======================================================================================================
// Javascript functions for "Setup / Router Settings"
//======================================================================================================
function Init_Settings(mac_com, mac_cur)
{
	$(".hostname").inputmask();
	$("#tz_detect").click( Settings_Detect );
	$("#mac_default").click(function() {
		$("#mac_addr").val(mac_cur).attr("disabled", "disabled");
	});
	$("#mac_random").click(function() {
		s = "X" + "26AE".charAt(Math.floor(Math.random() * 4)) + ":XX:XX:XX:XX:XX";
		$("#mac_addr").val(s.replace(/X/g, function() {
			return "0123456789ABCDEF".charAt(Math.floor(Math.random() * 16))
		})).attr("disabled", "disabled");
	});
	$("#mac_computer").click(function() {
		$("#mac_addr").val(mac_com).attr("disabled", "disabled");
	});
	$("#mac_custom").click(function() {
		$("#mac_addr").removeAttr("disabled");
	});
	$("#mac_addr").inputmask("mac");
	$("#apply_changes").click( Settings_Apply );
}

function Settings_Detect()
{
	$.post("/setup/settings", __postdata("detect"), function(data) {
		data = JSON.parse(data);
		$("#timezone").val(data.timezone).change();
		$("#wifi_country").val(data.country).change();
	}).fail(function() {
		alert("AJAX call failed!");
	});
}

function Settings_Apply()
{
	// Assemble the post data for the AJAX call:
	postdata = {
		'sid':      SID,
		'action':   'set',
		'hostname': $("#hostname").val(),
		'timezone': $("#timezone").val(),
		'locale':	$("#locale").val(),
		'mac':      $("#mac_addr").val(),
		'ui_lang':  $("#webui_language").val(),
		'country':  $("#wifi_country").val(),
		'onboard_wifi':  $("#onboard_wifi").find("option:selected").val(),
	};
	//alert(JSON.stringify(postdata, null, 5)); return;
	WebUI_Post("/setup/settings", postdata);
}

//======================================================================================================
// Javascript functions for "Setup / Wireless Setup"
//======================================================================================================
function Init_Wireless(iface)
{
	iface_used = iface;
	page_url = '/setup/wireless';

	// Main screen setup and handlers:
	$(".checkbox").bootstrapSwitch();
	$("#op_mode").change(function() {
		mode = $(this).find("option:selected").val();
		if (mode == 'disabled')
		{
			$("#client_mode_div").slideUp(400);
			$("#ap_mode_div").slideUp(400);
			$("#static_ip_div").slideUp(400);
			$("#network_div").slideUp(400);
			$(".dhcp_div").slideUp(400);
			$("#add_reservation_href").addClass("hidden");
		}
		else if (mode == "client_dhcp")
		{
			$("#client_mode_div").slideDown(400);
			$("#ap_mode_div").slideUp(400);
			$("#static_ip_div").slideUp(400);
			$("#network_div").slideDown(400);
			$(".dhcp_div").slideUp(400);
			$("#add_reservation_href").addClass("hidden");
		}
		else if (mode == "client_static")
		{
			$("#client_mode_div").slideDown(400);
			$("#ap_mode_div").slideUp(400);
			$("#static_ip_div").slideDown(400);
			$("#network_div").slideDown(400);
			$(".dhcp_div").slideUp(400);
			$("#add_reservation_href").addClass("hidden");
		}
		else if (mode == "ap")
		{
			$("#client_mode_div").slideUp(400);
			$("#ap_mode_div").slideDown(400);
			$("#static_ip_div").slideDown(400);
			$("#network_div").slideDown(400);
			$(".dhcp_div").slideDown(400);
			$("#add_reservation_href").removeClass("hidden");
		}
	}).change();
	$("#ap_band").change(function() {
		$(".bands").addClass("hidden");
		$(".band_" + $("#ap_band option:selected").val()).removeClass("hidden");
	});
	$("#apply_changes").click(Wireless_Submit);
	$("#wifi_scan").click(function() {
		$("#scan_data").html("");
		$("#scan-modal").modal("show");
		Wireless_Scan();
	});
	$("#scan_refresh").click(Wireless_Scan);
	$("#show_hidden").click(Wireless_Scan);
	$('#use_dhcp').click(function() {
		if ($(this).is(":checked")) {
			$(".dhcp").removeAttr("disabled");
			$(".dhcp_div").slideDown(400);
			$("#add_reservation_href").removeClass("hidden");
			$("#ip_addr").val( $("#ip_addr").val() );
		} else {
			$(".dhcp").attr("disabled", "disabled");
			$(".dhcp_div").slideUp(400);
			$("#add_reservation_href").addClass("hidden");
		}
	});
	$('.ip_address').inputmask("ip").change(function() {
		parts = $("#ip_addr").val().substring(0, $("#ip_addr").val().lastIndexOf('.'));
		$("#dhcp_start").val( parts + $("#dhcp_start").val().substring( $("#dhcp_start").val().lastIndexOf('.')) );
		$("#dhcp_end").val( parts + $("#dhcp_end").val().substring( $("#dhcp_end").val().lastIndexOf('.')) );
		$("#dhcp_ip_addr").val( parts + $("#dhcp_ip_addr").val().substring( $("#dhcp_ip_addr").val().lastIndexOf('.')) );
	});
	$("#dhcp_lease").inputmask("integer");
	$("#dhcp_units").change(function() {
		if ($(this).val() == "infinite")
			$("#dhcp_lease").attr("disabled", "disabled");
		else
			$("#dhcp_lease").removeAttr("disabled");
	});
}

function Wireless_Submit()
{
	// Assemble the post data for the AJAX call:
	band = $("#ap_band option:selected").val().replace("band_", "");
	postdata = {
		'sid':        SID,
		'iface':      iface_used,
		'action':     $("#op_mode option:selected").val(),
		'wpa_ssid':   $("#wpa_ssid").val(),
		'wpa_psk':    $("#wpa_psk").val(),
		'ip_addr':    $("#ip_addr").val(),
		'ip_mask':    $("#ip_mask").val(),
		'ip_gate':    $("#ip_gate").val(),
		'use_dhcp':   $("#op_mode option:selected").val() == 'ap' ? 'Y' : ($("#use_dhcp").is(":checked") ? 'Y' : 'N'),
		'dhcp_start': $("#dhcp_start").val(),
		'dhcp_end':   $("#dhcp_end").val(),
		'dhcp_lease': ($("#dhcp_units").val() == "infinite") ? 'infinite' : $("#dhcp_lease").val() + $("#dhcp_units").val().replace("s", ""),
		'firewalled': $("#op_mode option:selected").val() == 'ap' ? 'N' : ($("#firewalled").is(":checked") ? 'Y' : 'N'),
		'ap_ssid':    $("#ap_ssid").val(),
		'ap_psk':     $("#ap_psk").val(),
		'ap_band':    band,
		'ap_channel': $("#ap_channel_" + band + " option:selected").val(),
		'ap_mode':    $("#ap_mode_" + band + " option:selected").val(),
		'ap_hide':    $("#ap_hide").is(":checked") ? 'Y' : 'N',
		'ap_no_net':  $("#ap_no_net").is(":checked") ? 'Y' : 'N',
	};
	//alert(JSON.stringify(postdata, null, 5)); return;
	WebUI_Post("/setup/wireless", postdata);
}

function Wireless_Scan()
{
	// Assemble the post data for the AJAX call:
	postdata = {
		'sid':        SID,
		'action':     'scan',
		'iface':      iface_used,
		'hidden':     $("#show_hidden").is(":checked") ? 'Y' : 'N',
		'test':       $('#scan-test').val(),
	};
	//alert(JSON.stringify(postdata, null, 5)); return;

	// Perform our AJAX request to change the WAN settings:
	Add_Overlay("scan-modal");
	$.post('/setup/wireless', postdata, function(data) {
		data = data.trim();
		if (data == "RELOAD")
			document.location.reload(true);
		Del_Overlay("scan-modal");
		$("#scan_data").html( data );
		$(".use_network").click(function() {
			selected = $(this).parent().parent().parent().find(".network_name").html();
			$("#wpa_ssid").val( selected );
			$("#scan-modal").modal("hide");
		});
	}).fail(function() {
		Del_Overlay("scan-modal");
		$("#scan_data").html("AJAX call failed");
	});
}

//======================================================================================================
// Javascript functions for "Advanced / DHCP Reservations":
//======================================================================================================
function Init_DHCP(iface)
{
	iface_used = iface;

	//=========================================================================
	// IP Reservation modals and handlers:
	$("#reservations-refresh").click(DHCP_Refresh_Reservations).click();
	$("#dhcp_mac_addr").inputmask("mac");
	$("#reservation_remove").click(function() {
		$("#dhcp_client_name").val("");
		$("#dhcp_ip_addr").val("");
		$("#dhcp_mac_addr").val("");
	});
	$("#add_reservation").click(function() {
		$("#dhcp_error_box").addClass("hidden");
		$("#reservation-modal").modal("show");
		$("#reservation_remove").click();
		DHCP_Refresh_Leases();
	});
	$("#leases_refresh").click(DHCP_Refresh_Leases);
	$("#dhcp_add").click(DHCP_Reservation_Add);
	$("#dhcp_error_close").click(function() {
		$("#dhcp_error_box").addClass("hidden");
	});
	$("#confirm-proceed").click(DHCP_Reservation_Confirmed);
	$("#reboot_yes").click(Reboot_Confirmed);
}

function DHCP_Refresh_Leases()
{
	// Perform our AJAX request to refresh the LAN leases:
	$("#clients-table").html('<tr><td colspan="5"><center>Loading...</center></td></tr>');
	$.post('/setup/dhcp', __postdata("clients", iface_used), function(data) {
		if (data == "RELOAD")
			document.location.reload(true);
		$("#clients-table").html(data);
		$(".reservation-option").click(function() {
			line = $(this);
			$("#dhcp_client_name").val( line.find(".dhcp_host").html() );
			$("#dhcp_ip_addr").val( line.find(".dhcp_ip_addr").html() );
			$("#dhcp_mac_addr").val( line.find(".dhcp_mac_addr").html() );
		});
	}).fail(function() {
		DHCP_Error("AJAX call failed!");
	});
}

function DHCP_Refresh_Reservations()
{
	// Perform our AJAX request to refresh the reservations:
	$("#reservations-table").html('<tr><td colspan="5"><center>Loading...</center></td></tr>');
	$.post('/setup/dhcp', __postdata("reservations", iface_used), function(data) {
		$("#reservation-modal").modal("hide");
		$("#reservations-table").html(data);
		$(".dhcp_edit").click(function() {
			$("#add_reservation").click();
			line = $(this).parent();
			$("#dhcp_client_name").val( line.find(".dhcp_host").html() );
			$("#dhcp_ip_addr").val( line.find(".dhcp_ip_addr").html() );
			$("#dhcp_mac_addr").val( line.find(".dhcp_mac_addr").html() );
		});
		$(".dhcp_delete").click(DHCP_Reservation_Remove);
	});
}

function DHCP_Reservation_Remove()
{
	// Assemble the post data for the AJAX call:
	line = $(this).parent();
	postdata = {
		'sid':      SID,
		'action':   'remove',
		'misc':     iface_used,
		'hostname': line.find(".dhcp_host").html(),
		'ip_addr':  line.find(".dhcp_ip_addr").html(),
		'mac_addr': line.find(".dhcp_mac_addr").html(),
	};
	//alert(JSON.stringify(postdata, null, 5)); return;

	// Perform our AJAX request to remove the IP reservation:
	$.post('/setup/dhcp', postdata, function(data) {
		if (data.trim() == "OK")
		{
			DHCP_Refresh_Reservations();
			reboot_suggested = true;
		}
		else
			DHCP_Error(data);
	}).fail(function() {
		DHCP_Error("AJAX call failed!");
	});
}

function DHCP_Reservation_Add()
{
	// Assemble the post data for the AJAX call:
	postdata = {
		'sid':      SID,
		'action':   'check',
		'misc':     iface_used,
		'hostname': $("#dhcp_client_name").val(),
		'ip_addr':  $("#dhcp_ip_addr").val(),
		'mac_addr': $("#dhcp_mac_addr").val(),
	};
	//alert(JSON.stringify(postdata, null, 5)); return;

	// Check to make sure we actually have something to pass to the AJAX call:
	if (postdata.hostname == "")
		return DHCP_Error("No hostname specified!");
	else if (postdata.ip_addr == "")
		return DHCP_Error("No IP address specified!");
	else if (postdata.mac_addr == "")
		return DHCP_Error("No MAC address specified!");

	// Perform our AJAX request to add the IP reservation:
	$.post('/setup/dhcp', postdata, function(data) {
		if (data.trim() == "SAME")
			DHCP_Refresh_Reservations();
		else if (data.trim() == "OK")
			DHCP_Reservation_Add_Msg();
		else if (data.trim() == "ADD")
			DHCP_Reservation_Confirmed();
		else
		{
			$("#confirm-mac").html('<p>' + data + '</p><p>Proceed with replacement?</p>');
			$("#confirm-modal").modal("show");
		}
	}).fail(function() {
		DHCP_Error("AJAX call failed!");
	});
}

function DHCP_Reservation_Add_Msg()
{
	$("#apply_changes").addClass("hidden");
	$("#apply_reboot").removeClass("hidden");
	$("#alert-div").slideDown(400, function() {
		timer = setInterval(function() {
			$("#alert-div").slideUp();
			clearInterval(timer);
		}, 5000);
	});
	DHCP_Reservation_Confirmed();
}

function DHCP_Reservation_Confirmed()
{
	// Hide confirmation dialog if shown:
	$("#confirm-modal").modal("hide");

	// Assemble the post data for the AJAX call:
	postdata = {
		'sid':      SID,
		'action':   'add',
		'misc':     iface_used,
		'hostname': $("#dhcp_client_name").val(),
		'ip_addr':  $("#dhcp_ip_addr").val(),
		'mac_addr': $("#dhcp_mac_addr").val(),
	};
	//alert(JSON.stringify(postdata, null, 5)); return;

	// Perform our AJAX request to add the IP reservation:
	$.post('/setup/dhcp', postdata, function(data) {
		if (data.trim() == "OK")
			DHCP_Refresh_Reservations();
		else
			DHCP_Error(data);
	}).fail(function() {
		DHCP_Error("AJAX call failed!");
	});
}

function DHCP_Error(msg)
{
	$("#dhcp_error_msg").html(msg);
	$("#dhcp_error_box").slideDown(400, function() {
		timer = setInterval(function() {
			$("#dhcp_error_box").slideUp();
			clearInterval(timer);
		}, 5000);
	});
}

