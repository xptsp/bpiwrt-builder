<?php
require_once("subs/manage.php");

###################################################################################################
# Supporting functions:
###################################################################################################
function ip_range_cmd($dest_addr, $mask_addr, $gate_addr, $dev, $metric)
{
	$mask = $mask_addr == "0.0.0.0" ? 0 : 32-log(( ip2long($mask_addr) ^ ip2long('255.255.255.255') ) + 1, 2);
	return "ip route add " . $dest_addr . "/" . $mask . " via " . $gate_addr . " dev " . $dev . " metric " . $metric;
}

function validate_params()
{
	$_POST['dest_addr'] = option_ip('dest_addr');
	$_POST['mask_addr'] = option_ip('mask_addr');
	$_POST['gate_addr'] = option_ip('gate_addr');
	$_POST['metric'] = option_range('metric', 0, 9999);

	$_POST['iface'] = isset($_POST['iface']) ? $_POST['iface'] : '';
	if (empty($_POST['iface']) || !file_exists("/sys/class/net/" . $_POST['iface']))
		die('[IFACE] ERROR: "' . $_POST['iface'] . '" is not a valid network interface!');

	return ip_range_cmd($_POST['dest_addr'], $_POST['mask_addr'], $_POST['gate_addr'], $_POST['iface'], $_POST['metric']);
}

#################################################################################################
# If action specified and invalid SID passed, force a reload of the page.  Otherwise:
#################################################################################################
if (isset($_POST['action']))
{
	###################################################################################################
	# ACTION: SHOW ==> Show the current routing table.  Add delete icons to any custom lines we find.
	###################################################################################################
	if ($_POST['action'] == 'show')
	{
		$routes = $out = array();
		$delete = '<center><a href="javascript:void(0);"><i class="far fa-trash-alt"></i></a></center>';
		foreach (explode("\n", trim(@shell_exec("route | grep -v Kernel | grep -v Destination"))) as $line)
		{
			$a = explode(" ", preg_replace('/\s+/', ' ', $line));
			if (empty($routes[$a[7]]))
				$routes[$a[7]] = trim(@file_get_contents("/etc/network/if-up.d/" . $a[7] . "-route"));
			echo '<tr>',
					'<td class="dest_addr">', $a[0], '</td>',
					'<td class="mask_addr">', $a[2], '</td>',
					'<td class="gate_addr">', $a[1], '</td>',
					'<td class="metric">', $a[4], '</td>',
					'<td class="iface">', $a[7], '</td>',
					'<td>', strpos($routes[$a[7]], ip_range_cmd($a[0], $a[2], $a[1], $a[7], $a[4])) !== false ? $delete : '', '</td>',
				'</tr>';
		}
		die();
	}
	###################################################################################################
	# ACTION: DELETE ==> Remove specified ip routing from the system configuration:
	###################################################################################################
	else if ($_POST['action'] == 'delete')
	{
		$out = validate_params();
		if (!file_exists('/etc/network/if-up.d/' . $_POST['iface'] . '-route'))
			die('ERROR: Post-up script does not exist for interface "' . $_POST['iface'] . '"!');
		@shell_exec('cat /etc/network/if-up.d/' . $_POST['iface'] . '-route | grep -v "' . $out . '" > /tmp/' . $_POST['iface'] . '-route');
		@shell_exec('router-helper route move ' . $_POST['iface'] . '-route');
		die( @shell_exec('router-helper route ' . str_replace("ip route add", "del", $out)) );
	}
	###################################################################################################
	# ACTION: ADD ==> Add specified ip routing to the system configuration:
	###################################################################################################
	else if ($_POST['action'] == 'add')
	{
		$out = validate_params();
		if (!file_exists('/etc/network/if-up.d/' . $_POST['iface'] . '-route'))
			$text = '#!/bin/bash' . "\n" . 'if [[ "${IFACE}" == "' . $_POST['iface'] . '" ]]; then' . "\n\t" . 'echo ""' . "\n\t" . $out . "\nfi\n" . 'exit 0' ."\n";
		else
		{
			$text = @file_get_contents('/etc/network/if-up.d/' . $_POST['iface'] . '-route');
			$text = str_replace("fi", "\t" . $out . "\nfi", $text);
		}
		$handle = fopen("/tmp/" . $_POST['iface'] . "-route", "w");
		fwrite($handle, $text);
		fclose($handle);
		@shell_exec('router-helper route move ' . $_POST['iface'] . '-route');
		@shell_exec('router-helper route ' . str_replace("ip route ", "", $out));
		die("OK");
	}
	#################################################################################################
	# Got here?  We need to return "invalid action" to user:
	#################################################################################################
	die("Invalid action");
}

#################################################################################################
# Main code for this page:
#################################################################################################
site_menu();
$thead = '
				<thead>
					<tr>
						<th width="25%">Destination LAN IP</th>
						<th width="25%">Subnet Mask</th>
						<th width="25%">Gateway</th>
						<th width="10%">Metric</th>
						<th width="10%">Interface</th>
						<th width="5%">&nbsp;</th>
					</tr>
				</thead>';
echo '
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">Network Routing</h3>
	</div>
	<div class="card-body" id="routing-div">
		<div class="alert alert-danger hidden" id="dhcp_error_box">
			<a href="javascript:void(0);"><button type="button" class="close" id="dhcp_error_close">&times;</button></a>
			<i class="fas fa-ban"></i>&nbsp;<span id="dhcp_error_msg" />
		</div>
		<h5 class="dhcp_div">
			<a href="javascript:void(0);"><button type="button" id="routing-refresh" class="btn btn-sm btn-primary float-right">Refresh</button></a>
			Routing Table
		</h5>
		<div class="table-responsive p-0 dhcp_div">
			<table class="table table-hover text-nowrap table-sm table-striped">' . $thead . '
				<tbody id="routing-table">
					<tr><td colspan="6"><center>Loading...</center></td></tr>
				</tbody>
			</table>
		</div>
		<br />
		<h5 class="dhcp_div">Add New Routing</h5>
		<div class="table-responsive p-0 dhcp_div">
			<table class="table table-hover text-nowrap table-sm table-striped">' . $thead . '
				<tbody>
					<tr>
						<td><input id="dest_addr" type="text" class="ip_address form-control" placeholder="0.0.0.0" /></td>
						<td><input id="mask_addr" type="text" class="ip_address form-control" placeholder="255.255.255.0" value="255.255.255.0" /></td>
						<td><input id="gate_addr" type="text" class="ip_address form-control" placeholder="0.0.0.0" value="0.0.0.0" /></td>
						<td><input id="metric" class="form-control" value="0" /></td>
						<td colspan="2">
							<select class="custom-select" id="iface">';
foreach (get_network_adapters() as $iface => $ignore)
{
	if (!preg_match('/^(lo|sit.+|eth0|eth1|aux)$/', $iface))
		echo '
								<option value="' . $iface . '"' . ($iface == 'br0' ? ' selected="selected"' : '') . '>' . $iface . '</option>';
}
echo '
							</select>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<a href="javascript:void(0);"><button type="button" id="add_route" class="btn btn-success float-right">Add Route</button></a>
	</div>
</div>';
apply_changes_modal('Please wait....', true);
site_footer('Init_Routing();');
