<?php
require_once('subs-detailed.php');
#header('Content-type: application/json');

##########################################################################################
# Get information for the AJAX request:
##########################################################################################
$load = sys_getloadavg();
$temp = number_format((float) @file_get_contents('/sys/devices/virtual/thermal/thermal_zone0/temp') / 1000, 1);
$pihole = @json_decode( @file_get_contents( "http://pi.hole/admin/api.php?summary" ) );

##########################################################################################
# Split each line of the results of the "arp" command into array elements:
##########################################################################################
$arr = array(
	'count' => 0,
	'load0' => number_format((float)$load[0], 2),
	'load1' => number_format((float)$load[1], 2),
	'load2' => number_format((float)$load[2], 2),
	'temp' => $temp,
	'temp_icon' => 'fa-thermometer-' . ($temp > 70 ? 'full' : ($temp > 60 ? 'three-quarters' : ($temp > 50 ? 'half' : ($temp > 40 ? 'quarter' : 'empty')))),
	'system_uptime' => system_uptime(),
	'server_time' => date('Y-m-d H:i:s'),
	'devices' => array(),
);
if (isset($pihole->unique_clients))
	$arr['unique_clients'] = $pihole->unique_clients;
if (isset($pihole->dns_queries_today))
	$arr['dns_queries_today'] = $pihole->dns_queries_today;
if (isset($pihole->ads_blocked_today))
	$arr['ads_blocked_today'] = $pihole->ads_blocked_today;
if (isset($pihole->ads_percentage_today))
	$arr['ads_percentage_today'] = $pihole->ads_percentage_today;
if (isset($pihole->domains_being_blocked))
	$arr['domains_being_blocked'] = $pihole->domains_being_blocked;

##########################################################################################
# Return WAN status:
##########################################################################################
$wan_if = parse_ifconfig('wan');
if (strpos($wan_if['brackets'], 'RUNNING') === false)
	$arr['wan_status'] = 'Disconnected';
else
	$arr['wan_status'] = strpos(@shell_exec('ping -c 1 -W 1 8.8.8.8'), '1 received') > 0 ? 'Online' : 'Offline';

##########################################################################################
# Split each line of the dnsmasq.leases file and place into appropriate element:
##########################################################################################
foreach (explode("\n", trim(@file_get_contents("/var/lib/misc/dnsmasq.leases"))) as $num => $line)
{
	$temp = explode(" ", preg_replace("/\s+/", " ", $line));
	$arr['devices'][] = array(
		'lease_expires' => $temp[0],
		'mac_address' => $temp[1],
		'ip_address' => $temp[2],
		'machine_name' => $temp[3],
	);
}
$arr['count'] = count($arr['devices']);

##########################################################################################
# Output the resulting array:
##########################################################################################
echo json_encode($arr);
die();