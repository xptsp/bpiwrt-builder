<?php
require_once("subs/setup.php");
require_once("subs/dhcp.php");

#################################################################################################
# If we are not doing the submission action, then skip this entire block of code:
#################################################################################################
if (isset($_POST['action']))
{
	#################################################################################################
	# If action specified and invalid SID passed, force a reload of the page.  Otherwise:
	#################################################################################################
	if (!isset($_POST['sid']) || $_POST['sid'] != $_SESSION['sid'])
		die('RELOAD');

	#################################################################################################
	# Validate the actions, then do any DHCP actions as requested by the caller:
	#################################################################################################
	$action = $_POST['action'] = option_allowed('action', get_dhcp_actions(array('disabled', 'client_dhcp', 'client_static', 'ap', 'encode', 'scan')));
	do_dhcp_actions();

	#################################################################################################
	# Encode the specified credentials:
	#################################################################################################
	if ($action == 'encode')
	{
		$wpa_ssid = option('wpa_ssid', '/[\w\d\s\_\-]+/');
		$wpa_psk = option('wpa_psk', '/[\w\d\s\_\-]{8,63}/');
		foreach (explode("\n", trim(@shell_exec('wpa_passphrase "' . $wpa_ssid . '" "' . $wpa_psk . '"'))) as $line)
		{
			$line = explode("=", trim($line . '='));
			if ($line[0] == "psk")
				die($line[1]);
		}
		die("ERROR");
	}

	#################################################################################################
	# Scan for Wireless Networks using the interface:
	#################################################################################################
	$iface   = option_allowed('iface', explode("\n", trim(@shell_exec("iw dev | grep Interface | awk '{print $2}'"))) );
	if ($action == 'scan')
	{
		$networks = array();
		$number = 0;
		$cmd = '/opt/bpi-r2-router-builder/helpers/router-helper.sh iface ' . (option("test") == "N" ? 'scan ' . $iface : 'scan-test');
		#echo '<pre>'; print_r(explode("\n", trim(@shell_exec($cmd)))); exit;
		foreach (explode("\n", trim(@shell_exec($cmd))) as $id => $line)
		{
			if (preg_match("/^BSS ([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})/", $line))
				$number++;
			else if (preg_match('/SSID: (.*)/', $line, $regex))
				$networks[ $number ]['ssid'] = trim($regex[1]);
			else if (preg_match('/DS Parameter set\: channel (\d+)/', $line, $regex))
				$networks[ $number ]['channel'] = $regex[1];
			else if (preg_match('/signal: (-?[0-9\.]+ dBm)/', $line, $regex))
				$networks[ $number ]['signal'] = $regex[1];
			else if (preg_match('/freq: ([\d+\.]+)/', $line, $regex))
				$networks[ $number ]['freq'] = $regex[1];
		}
		#echo '<pre>'; print_r($networks); exit;
		echo
		'<table class="table table-striped table-sm">',
			'<thead>',
				'<tr>',
					'<th>SSID</th>',
					'<th width="15%"><center>Channel</center></th>',
					'<th width="15%"><center>Frequency</center></th>',
					'<th width="15%"><center>Signal<br />Strength</center></th>',
					'<th>&nbsp;</th>',
				'</tr>',
			'</thead>',
			'<tbody>';
		$hidden = option("hidden") == "Y";
		foreach ($networks as $network)
		{
			if ($hidden || !empty($network['ssid']))
				echo 
				'<tr>',
					'<td class="network_name">', empty($network['ssid']) ? '<i>(No SSID broadcast)</i>' : $network['ssid'], '</td>',
					'<td><center>', $network['channel'], '</center></center></td>',
					'<td><center>', $network['freq'], ' GHz</center></center></td>',
					'<td><center><img src="/img/wifi_', network_signal_strength($network['signal']), '.png" width="24" height="24" title="Signal Strength: ', $network['signal'], '" /></center></td>',
					'<td><a href="javascript:void(0);"><button type="button" class="use_network btn btn-sm bg-primary float-right">Use</button></a></td>',
				 '</tr>';						
		}
		echo 
			'</tbody>',
		'</table>';
		die();
	}

	#################################################################################################
	# Validate the input sent to this script (we paranoid... for the right reasons, of course...):
	#################################################################################################
	if ($action == 'client_static' || $action == 'ap')
	{
		$ip_addr = option_ip('ip_addr');
		$ip_mask = option_ip('ip_mask');
		$ip_gate = option_ip('ip_gate');
	}
	if ($action == 'client_dhcp' || $action == 'client_static')
	{
		$wpa_ssid = option('wpa_ssid', '/[\w\d\s\_\-]+/');
		$wpa_psk = option('wpa_psk', '/[\w\d\s\_\-]{8,63}/');
	}
	$firewalled = option("firewalled", "/^(Y|N)$/");

	#################################################################################################
	# Shut down the wireless interface right now, before modifying the configuration:
	#################################################################################################
	@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh iface ifdown " . $iface);

	#################################################################################################
	# Decide what the interface configuration text will look like:
	#################################################################################################
	$text  = 'auto ' . $iface . "\n";
	$text .= 'iface ' . $iface . ' inet ' . ($action == "disabled" ? 'manual' : ($action == 'client_static' || $action == 'ap' ? 'static' : 'dhcp')) . "\n";
	if ($action != "disabled" && $action != 'client_dhcp')
	{
		$text .= '    address ' . $ip_addr . "\n";
		$text .= '    netmask ' . $ip_mask . "\n";
		if (!empty($ip_gate) && $ip_gate != "0.0.0.0")
			$text .= '    gateway ' . $ip_gate . "\n";
	}	
	if ($action == "client_dhcp" || $action == "client_static")
	{
		$text .= '    wpa_ssid "' . $wpa_ssid . '"' . "\n";
		if (!empty($wpa_psk))
			$text .= '    wpa_psk "' . $wpa_psk . '"' . "\n";
		$text .= '    masquerade yes' . "\n";
	}
	if ($firewalled && $action != "disabled")
		$text .= '    firewall yes' . "\n";
		
	#################################################################################################
	# Output the network adapter configuration to the "/tmp" directory:
	#################################################################################################
	#echo '<pre>'; echo $text; exit;
	$handle = fopen("/tmp/" . $iface, "w");
	fwrite($handle, trim($text) . "\n");
	fclose($handle);
	$tmp = @shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh iface move " . $iface);

	#################################################################################################
	# Output the DNSMASQ configuration file related to the network adapter:
	#################################################################################################
	if ($_POST['action'] == 'disabled' || $_POST['action'] == 'client_static' || $_POST['action'] == 'client_dhcp' || $_POST['use_dhcp'] == "N")
		$tmp = @shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh dhcp del " . $_POST['iface']);
	else
		$tmp = @shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh dhcp set " . $_POST['iface'] . " " . $ip_addr . " " . $dhcp_start . " " . $dhcp_end . ' ' . $dhcp_lease);
	if ($tmp != "")
		die($tmp);

	#################################################################################################
	# Start the wireless interface and restart pihole-FTL:
	#################################################################################################
	@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh iface ifup " . $iface);
	@shell_exec("/opt/bpi-r2-router-builder/helpers/router-helper.sh pihole restartdns");
	die("OK");
}

########################################################################################################
# Determine what wireless interfaces exist on the system, then remove the AP0 interface if client-mode
#  is specified for the R2's onboard wifi:
########################################################################################################
$ifaces = array();
$options = parse_options();
#echo '<pre>'; print_r($options); exit;
foreach (explode("\n", @trim(@shell_exec("iw dev | grep Interface | awk '{print $2}' | sort"))) as $tface)
	$ifaces[] = $tface;
#echo '<pre>'; print_r($ifaces); exit;
$iface = isset($_GET['iface']) ? $_GET['iface'] : $ifaces[0];
#echo $iface; exit;
$adapters = explode("\n", trim(@shell_exec("iw dev | grep Interface | awk '{print $2}'")));
#echo '<pre>'; print_r($adapters); exit();
$netcfg = get_mac_info($iface);
#echo '<pre>'; print_r($netcfg); exit;
$wpa_ssid = preg_match('/wpa_ssid\s+\"(.+)\"/', isset($netcfg['wpa_ssid']) ? $netcfg['wpa_ssid'] : '', $regex) ? $regex[1] : '';
#echo '<pre>'; print_r($wpa_ssid); exit;
$wpa_psk = preg_match('/wpa_psk\s+\"(.+)\"/', isset($netcfg['wpa_psk']) ? $netcfg['wpa_psk'] : '', $regex) ? $regex[1] : '';
#echo '<pre>'; print_r($wpa_psk); exit;
$dhcp = explode(",", explode("=", trim(@shell_exec("cat /etc/dnsmasq.d/" . $iface . ".conf | grep dhcp-range=")) . '=')[1]);
#echo '<pre>'; print_r($dhcp); exit();
$use_dhcp = isset($dhcp[1]);
#echo (int) $use_dhcp; exit;
$ifcfg = parse_ifconfig($iface);
#echo '<pre>'; print_r($ifcfg); echo '</pre>'; exit();
$wifi = get_wifi_capabilities($iface);
#echo '<pre>'; echo '$iface = ' . $iface . "\n"; print_r($wifi); echo '</pre>'; exit();

########################################################################################################
# Main code for the page:
########################################################################################################
site_menu();
echo '
<div class="card card-primary">
<div class="card card-primary">
    <div class="card-header p-0 pt-1">
		<ul class="nav nav-tabs">';
$init_list = array();
$URL = explode("?", $_SERVER['REQUEST_URI'])[0];
foreach ($ifaces as $tface)
{
	echo '
			<li class="nav-item">
				<a class="ifaces nav-link', $iface == $tface ? ' active' : '', '" href="', $URL, $tface == $ifaces[0] ? '' : '?iface=' . $tface, '">', $tface, '</a>
			</li>';
}
echo '
		</ul>
	</div>
	<div class="card-body">
		<input type="hidden" id="scan-test" value="', isset($_GET['test']) ? 'Y' : 'N', '">';

###################################################################################################
# List modes of operation for the interface:
###################################################################################################
echo '
		<div class="row">
			<div class="col-6">
				<label for="iface_mode">Mode of Operation:</label>
			</div>
			<div class="col-6">
				<select id="op_mode" class="form-control">
					<option value="disabled"', $netcfg['op_mode'] == 'manual' ? ' selected="selected"' : '', '>Not Configured</option>';
if (isset($wifi['supported']['AP']))
	echo '
					<option value="ap"' . ($netcfg['op_mode'] == 'static' && !isset($netcfg['wpa_ssid'])  ? ' selected="selected"' : '') . '>Access Point</option>';
if (isset($wifi['supported']['managed']))
	echo '
					<option value="client_dhcp"', $netcfg['op_mode'] == 'dhcp' && isset($netcfg['wpa_ssid']) ? ' selected="selected"' : '', '>Client Mode - Automatic Configuration (DHCP)</option>
					<option value="client_static"', $netcfg['op_mode'] == 'static' && isset($netcfg['wpa_ssid']) ? ' selected="selected"' : '', '>Client Mode - Static IP Address</option>';
echo '
				</select>
			</div>
		</div>';

###################################################################################################
# Wifi SSID, password and firewalled setting:
###################################################################################################
echo '
		<div id="client_mode_div"', ($netcfg['op_mode'] == 'dhcp' && isset($netcfg['wpa_ssid'])) || ($netcfg['op_mode'] == 'static' && !isset($netcfg['wpa_ssid'])) ? '' : ' class="hidden"', '>
			<hr style="border-width: 2px" />
			<div class="row" style="margin-top: 5px">
				<div class="col-6">
					<label for="ip_addr">Network Name (SSID):</label>
				</div>
				<div class="col-6">
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text"><i class="fas fa-laptop"></i></span>
						</div>
						<input id="wpa_ssid" type="text" class="form-control" value="', $wpa_ssid, '">
						<div class="input-group-prepend" id="wifi_scan_div">
							<a href="javascript:void(0);"><button type="button" class="btn btn-primary" id="wifi_scan">Scan</button></a>
						</div>
					</div>
				</div>
			</div>
			<div class="row" style="margin-top: 5px">
				<div class="col-6">
					<label for="ip_mask">Passphrase:</label>
				</div>
				<div class="col-6">
					<div class="input-group">
						<div class="input-group-prepend" id="wpa_toggle">
							<span class="input-group-text"><i class="fas fa-eye"></i></span>
						</div>
						<input type="password" class="form-control" id="wpa_psk" name="wpa_psk" placeholder="Required" value="', $wpa_psk, '">
						<div class="input-group-prepend" id="wifi_encode_div">
							<a href="javascript:void(0);"><button type="button" class="btn btn-primary" id="wifi_encode">Encode</button></a>
						</div>
					</div>
				</div>
			</div>
			<div class="row" style="margin-top: 5px">
				<div class="col-12">
					<div class="icheck-primary">
						<input type="checkbox" id="firewalled"', isset($netcfg['firewall']) ? ' checked="checked"' : '', '>
						<label for="firewalled">Firewall Interface from Internet</label>
					</div>
				</div>
			</div>
		</div>';

###################################################################################################
# Interface IP Address section
###################################################################################################
$subnet = isset($ifcfg['inet']) ? $ifcfg['inet'] : '';
$default = "192.168." . strval( (int) trim(@shell_exec("iw dev " . $iface . " info | grep ifindex | awk '{print \$NF}'")) + 10 ) . ".1";
$subnet = empty($subnet) ? $default : $subnet;
echo '
		<div id="static_ip_div"', ($netcfg['op_mode'] == 'static' && isset($netcfg['wpa_ssid'])) ? '' : ' class="hidden"', '>
			<hr style="border-width: 2px" />
			<div class="row" style="margin-top: 5px">
				<div class="col-6">
					<label for="ip_addr">IP Address:</label>
				</div>
				<div class="col-6">
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text"><i class="fas fa-laptop"></i></span>
						</div>
						<input id="ip_addr" type="text" class="ip_address form-control" value="', $subnet, '" data-inputmask="\'alias\': \'ip\'" data-mask>
					</div>
				</div>
			</div>
			<div class="row" style="margin-top: 5px">
				<div class="col-6">
					<label for="ip_mask">IP Subnet Mask:</label>
				</div>
				<div class="col-6">
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text"><i class="fas fa-laptop"></i></span>
						</div>
						<input id="ip_mask" type="text" class="ip_address form-control" value="', isset($ifcfg['netmask']) ? $ifcfg['netmask'] : '255.255.255.0', '" data-inputmask="\'alias\': \'ip\'" data-mask>
					</div>
				</div>
			</div>
			<div class="row" style="margin-top: 5px">
				<div class="col-6">
					<label for="ip_gate">IP Gateway Address:</label>
				</div>
				<div class="col-6">
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text"><i class="fas fa-laptop"></i></span>
						</div>
						<input id="ip_gate" type="text" class="ip_address form-control" value="', isset($netcfg['gateway']) ? $netcfg['gateway'] : '0.0.0.0', '" data-inputmask="\'alias\': \'ip\'" data-mask>
					</div>
				</div>
			</div>';

###################################################################################################
# DHCP Settings and IP Range, plus IP Address Reservation section
###################################################################################################
dhcp_reservations_settings();

###################################################################################################
# Page footer
###################################################################################################
echo '
		</div>
	</div>
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" id="apply_reboot" class="btn btn-success float-right hidden" data-toggle="modal" data-target="#reboot-modal" id="reboot_button">Apply and Reboot</button></a>
		<a href="javascript:void(0);"><button type="button" id="apply_changes" class="btn btn-success float-right">Apply Changes</button></a>
		<a id="add_reservation_href" href="javascript:void(0);"', !$use_dhcp || $netcfg['op_mode'] == 'dhcp' || isset($netcfg['wpa_ssid']) ? ' class="hidden"' : '', '><button type="button" id="add_reservation" class="dhcp_div btn btn-primary"><i class="fas fa-plus"></i>&nbsp;&nbsp;Add</button></a>
	</div>
	<!-- /.card-body -->
</div>';

#######################################################################################################
# Scan Wireless Network modal:
#######################################################################################################
echo '
<div class="modal fade" id="scan-modal" data-backdrop="static" style="display: none;" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title">Wireless Networks Found</h4>
				<div class="icheck-primary">
					<input type="checkbox" id="show_hidden">
					<label for="show_hidden">Show Hidden</label>
				</div>
			</div>
			<div class="modal-body">
				<p id="scan_data"></p>
			</div>
			<div class="modal-footer justify-content-between">
				<a href="javascript:void(0);"><button type="button" class="btn btn-default bg-primary" id="scan_close" data-dismiss="modal">Close</button></a>
				<a href="javascript:void(0);"><button type="button" class="btn btn-default bg-primary float-right" id="scan_refresh">Refresh</button></a>
			</div>
		</div>
		<!-- /.modal-content -->
	</div>
	<!-- /.modal-dialog -->
</div>';

#######################################################################################################
# Close the page:
#######################################################################################################
dhcp_reservations_modals();
apply_changes_modal('Please wait while the wireless interface is being configured....', true);
reboot_modal();
site_footer('Init_Wireless("' . $iface . '", "' . $subnet . '", "' . $default . '");');
