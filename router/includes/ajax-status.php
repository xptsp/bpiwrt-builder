<?php
if (!isset($_GET['sid']) || $_GET['sid'] != strrev(session_id()))
{
	require_once("404.php");
	die;
}
$dhcp = explode(' ', @shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh dhcp-info'));
echo  json_encode(array(
	'remote_ver'   => date('Y.md.Hi', (int) trim(@shell_exec('/opt/bpi-r2-router-builder/helpers/router-helper.sh webui remote'))),
	'dhcp_type'    => $dhcp[0],
	'lease_start'  => date('Y-m-d H:i:s', $dhcp[1]),
	'lease_expire' => date('Y-m-d H:i:s', $dhcp[2])
));