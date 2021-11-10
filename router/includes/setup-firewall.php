<?php
##############################################################################
# Read in the iptables configuration file:
##############################################################################
$file = '/etc/default/firewall';
$options = array();
foreach (explode("\n", trim(@file_get_contents($file))) as $line)
{
	$parts = explode("=", $line . '=');
	$options[$parts[0]] = $parts[1];
}

##############################################################################
# Helper function to simplify checkbox creation task:
##############################################################################
function checkbox($name, $description, $default = true)
{
	global $options;
	$checked = (!isset($options[$name]) ? $default : ($options[$name] == "Y"));
	return '<p><input type="checkbox" id="' . $name . '" class="checkbox"' . ($checked ? ' checked="checked"' : '') . ' data-bootstrap-switch="" data-off-color="danger" data-on-color="success"> <strong>' . $description . '</strong></p>';
}

##############################################################################
# Output the configuration screen:
##############################################################################
site_menu();
echo '
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">Firewall Settings</h3>
	</div>
	<div class="card-body">
		', checkbox("drop_port_scan", "Enable Port Scan protection from Internet"), '
		', checkbox("drop_ping",      "Do Not Respond to Pings from the Internet"), '
		', checkbox("drop_ident",     "Do Not Respond to IDENT requests from Internet (port 113)"), '
		', checkbox("drop_multicast", "Filter Multicast Packets from Internet", false), '
	</div>
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-outline-danger center_50" id="apply_changes">Apply Changes</button></a>
	</div>
	<!-- /.card-body -->
</div>';
site_footer('Init_Firewall();');
