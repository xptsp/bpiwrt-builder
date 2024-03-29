<?php
require_once("subs/manage.php");
$options = parse_options();

#################################################################################################
# If action specified and invalid SID passed, force a reload of the page.  Otherwise:
#################################################################################################
if (isset($_POST['action']))
{
	#################################################################################################
	# ACTION: SUBMIT => Make the requested changes to the firewall
	#################################################################################################
	if ($_POST['action'] == 'submit')
	{
		// Apply configuration file changes:
		$options['use_isp']         = option('use_isp');
		$options['dns1']            = option_ip('dns1', false, true);
		$options['dns2']            = option_ip('dns2', true, true);
		#echo '<pre>'; print_r($options); exit;
		die(apply_options());
	}
	#################################################################################################
	# Got here?  We need to return "invalid action" to user:
	#################################################################################################
	die("Invalid action");
}

###################################################################################################
# Domain Name (DNS) Servers
###################################################################################################
site_menu();
$isp = $current = array();
foreach (@file("/etc/dnsmasq.d/01-pihole.conf") as $line)
{
	if (preg_match("/^server=(.*)/", $line, $regex))
		$current[ count($current) ] = $regex[1];
}
$primary = empty($current[0]) ? '' : $current[0];
$secondary = empty($current[1]) ? '' : $current[1];
foreach (@file("/etc/resolv.conf") as $line)
{
	if (preg_match("/^nameserver (.*)/", $line, $regex))
		$isp[ count($isp) ] = $regex[1];
}
$use_isp = (empty($isp[0]) || $primary == $isp[0]) && (empty($isp[1]) ? true : ($secondary == $isp[1]));
$providers = array(
	array('Cloudflared', '1.1.1.1', '1.0.0.1'),
	array('Cloudflare - Malware Filter', '1.1.1.2', '1.0.0.2'),
	array('Cloudflare - Malware and Adult Filter<', '1.1.1.3', '1.0.0.3'),
	array('Google', '8.8.8.8', '8.8.4.4'),
	array('OpenDNS', '208.67.222.222', '208.67.220.220'),
	array('OpenDNS - FamilyShield', '208.67.222.123', '208.67.220.123'),
	array('Quad9', '9.9.9.9', '149.112.112.112'),
	array('Quad9 - No Malware Blocking', '9.9.9.10', '149.112.112.10'),
	array('CleanBrowsing', '185.228.168.9', '185.228.169.9'),
	array('CleanBrowsing - Adult Filter', '185.228.168.10', ''),
	array('CleanBrowsing - Family Filter', '185.228.168.168', '185.228.168.168'),
	array('AdGuard DNS', '94.140.14.14', '94.140.15.15'),
	array('AdGuard DNS - Non-Filtering', '94.140.14.140', '94.140.15.141'),
	array('AdGuard DNS - Family Protection', '94.140.14.15', '94.140.15.16'),
	array('Alternate DNS', '76.76.19.19', '76.223.122.150'),
	array('Level3 DNS', '4.2.2.1', '4.2.2.2'),
	array('Comodo Secure DNS', '8.26.56.26', '8.20.247.20'),
	array('DNS.WATCH', '84.200.69.80', '84.200.70.40'),
);
$use_provider = false;
foreach ($providers as $provider)
	$use_provider |= ($primary == $provider[1] && $secondary == $provider[2]);
$use_custom = !($use_isp || $use_provider);
#echo '<pre>$current = '; print_r($current); echo '$primary = ' . $primary . "\n" . '$secondary = ' . $secondary . "\n" . '$isp = '; print_r($isp); echo '$use_isp = ' . ($use_isp ? 'Y' : 'N') . "\n"; echo '$cloudflared_mode = ' . ($cloudflared_mode ? 'Y' : 'N') . "\n"; echo '$use_provider = ' . ($use_provider ? 'Y' : 'N') . "\n"; echo '$use_custom = ' . ($use_custom ? 'Y' : 'N') . "\n"; exit;

###################################################################################################
# Output the DNS Settings page:
###################################################################################################
echo '
<div class="card card-primary">
	<div class="card-header">
		<h3 class="card-title">Domain Name Servers</h3>
	</div>
	<div class="card-body">
		<div class="row">
			<div class="col-sm-6">
				<div class="form-group clearfix">
					<div class="icheck-primary">
						<input type="radio" id="dns_custom" value="custom" name="dns_server_opt"', $use_custom ? ' checked="checked"' : '', '>
						<label for="dns_custom">Manually Set DNS Servers</label>
					</div>
					<div class="icheck-primary">
						<input type="radio" id="dns_isp" value="isp" name="dns_server_opt"', $use_isp ? ' checked="checked"' : '', '>
						<label for="dns_isp">Get Automatically from ISP</label>
					</div>
					<div class="icheck-primary">
						<input type="radio" id="dns_provider" value="alt" name="dns_server_opt"', $use_provider ? ' checked="checked"' : '', '>
						<label for="dns_provider">Use Public DNS Servers</label>
					</div>
				</div>
			</div>
			<div class="col-sm-6">
				<div class="form-group" id="provider-group">
					<label>Select DNS Servers:</label>
					<select class="provider form-control', !$use_provider ? ' hidden' : '', '" id="select_provider">';
foreach ($providers as $provider)
	echo '
						<option value="', $provider[1], '/', $provider[2], '"', ($primary == $provider[1] && $secondary == $provider[2]) ? ' selected="selected"' : '', '>', $provider[0], '</option>';
echo '
					</select>
				</div>
			</div>
		</div>
		<div id="dns_settings">
			<hr style="border-width: 2px" />
			<div class="row">
				<div class="col-sm-6">
					<label for="ip_address">Primary DNS Server</label>
				</div>
				<div class="col-sm-6">
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text"><i class="fas fa-laptop"></i></span>
						</div>
						<input id="dns1" type="text" placeholder="127.0.0.1" class="dns_address form-control" value="', explode("#", $primary . "#")[0], '"', !$use_isp ? ' disabled="disabled"' : '', '>
						<div class="input-group-prepend">
							<span class="input-group-text" title="Port Number"><i class="fas fa-hashtag"></i></span>
						</div>
						<input id="dns_port1" type="text" class="dns_port form-control" placeholder="53" value="', explode("#", $primary . "#")[1], '"', empty($use_isp) ? ' disabled="disabled"' : '', '>
					</div>
				</div>
			</div>
			<div class="row" style="margin-top: 5px">
				<div class="col-sm-6">
					<label for="ip_address">Secondary DNS Server</label>
				</div>
				<div class="col-sm-6">
					<div class="input-group">
						<div class="input-group-prepend">
							<span class="input-group-text"><i class="fas fa-laptop"></i></span>
						</div>
						<input id="dns2" type="text" placeholder="127.0.0.1" class="dns_address form-control" value="', explode("#", $secondary . "#")[0], '"', !$use_isp ? ' disabled="disabled"' : '', '>
						<div class="input-group-prepend">
							<span class="input-group-text" title="Port Number"><i class="fas fa-hashtag"></i></span>
						</div>
						<input id="dns_port2" type="text" class="dns_port form-control" placeholder="53" value="', explode("#", $secondary . "#")[1], '"', empty($use_isp) ? ' disabled="disabled"' : '', '>
					</div>
				</div>
			</div>
		</div>
	</div>';

###################################################################################################
# Apply Changes button:
###################################################################################################
echo '
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-success center_50" id="submit">Apply Changes</button></a>
	</div>
</div>';

###################################################################################################
# Close page
###################################################################################################
apply_changes_modal("Please wait while the Pi-Hole FTL service is restarted....", true);
site_footer('Init_DNS("' . (!empty($isp[0]) ? $isp[0] : '') . '", "' . (!empty($isp[1]) ? $isp[1] : '') . '");');
