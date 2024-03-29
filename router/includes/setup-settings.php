<?php
require_once("subs/manage.php");
require_once("subs/setup.php");

# Gather WebUI language file names:
$langs = array();
foreach (glob("lang/*.php") as $lang)
{
	$s = str_replace('.php', '', basename($lang));
	$langs[$s] = $s;
}
#echo '<pre>'; print_r($langs); exit;

# Parse the regulatory.bin file for list of supported country codes:
$countries = explode("\n", trim(@shell_exec('regdbdump /lib/crda/regulatory.bin | grep -i "country " | cut -d" " -f 2 | cut -d":" -f 1')));

#################################################################################################
# If action specified and invalid SID passed, force a reload of the page.
#################################################################################################
if (isset($_POST['action']))
{
	#################################################################################################
	#   ACTION: DETECT ==> Detect where the machine is, according to "http://ipinfo.io":
	#################################################################################################
	if ($_POST['action'] == 'detect')
	{
		$_SESSION['ipinfo']['arr'] = array();
		foreach (explode("\n", trim(@shell_exec("curl ipinfo.io"))) as $line)
		{
			if (preg_match("/\"(.*)\"\:\s\"(.*)\"/", $line, $matches))
				$_SESSION['ipinfo']['arr'][$matches[1]] = $matches[2];
		}
		die(json_encode($_SESSION['ipinfo']['arr']));
	}
	#################################################################################################
	#   ACTION: SET ==> Set the timezone and hostname of the system:
	#################################################################################################
	else if ($_POST['action'] == 'set')
	{
		// Set the change to the onboard wifi mode:
		$options = parse_options();
		$tmp = option_allowed('onboard_wifi', array('1', 'A'));
		$options['webui_lang'] = option_allowed('ui_lang', $langs);
		$options['wifi_country'] = option_allowed('country', array_keys($countries));
		$restart = ($tmp != $options['onboard_wifi']);
		$options['onboard_wifi'] = $tmp; 
		apply_options();
		if ($restart)
			@shell_exec("router-helper systemctl restart networking");

		// Set the other options:
		$mac = option_mac('mac');
		$timezone = option_allowed('timezone', array_keys(timezone_list()) );
		$locale = option_allowed('locale', array_keys(get_os_locales()) );
		$hostname = option('hostname', "/^([0-9a-zA-Z]|[0-9a-zA-Z][0-9a-zA-Z0-9\-]+)$/");

		@shell_exec("router-helper mac " . $mac);
		die(@shell_exec('router-helper device ' . $hostname . ' ' . $timezone . ' ' . $locale));
	}
	#################################################################################################
	# Got here?  We need to return "invalid action" to user:
	#################################################################################################
	die("Invalid action");
}

###########################################################################################
# Main code for this page:
###########################################################################################
$options = parse_options();
site_menu();
#echo '<pre>'; print_r($current); exit;
echo '
<div class="card card-primary" id="settings-div">
	<div class="card-header">
		<h3 class="card-title">Router Settings</h3>
	</div>';

###########################################################################################
# Hostname:
###########################################################################################
echo '
	<div class="card-body">
		<div class="row" style="margin-top: 10px">
			<div class="col-sm-6">
				<label for="hostname">Host Name</label></td>
			</div>
			<div class="col-sm-6">
				<div class="input-group">
					<div class="input-group-prepend">
						<span class="input-group-text"><i class="fas fa-laptop-code"></i></span>
					</div>
					<input id="hostname" type="text" class="hostname form-control" value="', @file_get_contents('/etc/hostname'), '" data-inputmask-regex="([0-9a-zA-Z]|[0-9a-zA-Z][0-9a-zA-Z0-9\-]+)">
				</div>
			</div>
		</div>';

###########################################################################################
# Time Zone:
###########################################################################################
echo '
		<div class="row" style="margin-top: 10px">
			<div class="col-sm-6">
				<label for="hostname">System Time Zone</label></td>
			</div>
			<div class="col-sm-6 input-group">
				<select class="form-control" id="timezone">';
$current = date_default_timezone_get();
foreach (timezone_list() as $id => $text)
	echo '
					<option value="', trim($id), '"', trim($id) == trim($current) ? ' selected="selected"' : '', '>', $text, '</option>';
echo '
				</select>
				<span class="input-group-append">
					<button type="button" class="btn btn-info btn-flat" id="tz_detect">Detect</button>
				</span>
			</div>
		</div>';

###########################################################################################
# Wifi Country Setting:
###########################################################################################
$wifi_country = isset($options['wifi_country']) ? $options['wifi_country'] : '';
echo '
		<div class="row" style="margin-top: 10px">
			<div class="col-sm-6">
				<label for="webui_language">WiFi Country:</label></td>
			</div>
			<div class="col-sm-6 input-group">
				<select class="form-control" id="wifi_country">';
foreach ($countries as $code)
	echo '
					<option value="', $code, '"', $wifi_country == $code ? ' selected="selected"' : '', '>', $code, '</option>';
echo '
				</select>
			</div>
		</div>';

###########################################################################################
# OS Locales Installed:
###########################################################################################
echo '
		<div class="row" style="margin-top: 10px">
			<div class="col-sm-6">
				<label for="hostname">Available OS Locales:</label></td>
			</div>
			<div class="col-sm-6 input-group">
				<select class="form-control" id="locale">';
foreach (get_os_locales() as $id => $text)
	echo '
					<option value="', trim($id), '"', trim($id) == trim($current) ? ' selected="selected"' : '', '>[', $id, '] ', $text, '</option>';
echo '
				</select>
			</div>
		</div>';

###########################################################################################
# WebUI Language Setting:
###########################################################################################
$default = isset($options['lang']) ? $options['lang'] : 'English';
echo '
		<div class="row" style="margin-top: 10px">
			<div class="col-sm-6">
				<label for="webui_language">WebUI Language:</label></td>
			</div>
			<div class="col-sm-6 input-group">
				<select class="form-control" id="webui_language"', count($langs) == 1 ? ' disabled="disabled"' : '', '>';
foreach ($langs as $lang)
	echo '
					<option value="', $lang, '"', $default == $lang ? ' selected="selected"' : '', '>', $lang, '</option>';
echo '
				</select>
			</div>
		</div>';

###########################################################################################
# Onboard Wifi Setting:
###########################################################################################
$mode = isset($options['onboard_wifi']) ? $options['onboard_wifi'] : '';
echo '
		<div class="row" style="margin-top: 10px">
			<div class="col-sm-6">
				<label for="onboard_wifi">BPi R2 Onboard Wifi Mode:</label></td>
			</div>
			<div class="col-sm-6 input-group">
				<select class="form-control" id="onboard_wifi">
					<option value="A"', $mode == 'A' ? ' selected="selected"' : '', '>Access Point or Client Mode</option>
					<option value="1"', $mode == '1' ? ' selected="selected"' : '', '>Client Mode Only</option>
				</select>
			</div>
		</div>
	</div>';

###################################################################################################
# Router MAC Address settings:
###################################################################################################
$wan = parse_ifconfig('wan');
#echo '<pre>'; print_r($wan); exit();
$mac = trim($wan['ether']);
$options = parse_options("/boot/persistent.conf");
#echo '<pre>'; print_r($options); exit;
$def = isset($options['MAC']) ? $options['MAC'] : $mac;
$mac_com = trim(@shell_exec("arp -n | grep -m 1 \"^" . $_SERVER['REMOTE_ADDR'] . " \" | awk '{print $3}'"));
$mac_chk = ($mac == $def || $mac == $mac_com);
echo '
	<div class="card-header">
		<h3 class="card-title">Router MAC Address</h3>
	</div>
	<!-- /.card-header -->
	<div class="card-body">
		<div class="row">
			<div class="col-sm-6">
				<div class="icheck-primary">
					<input class="mac_opt" type="radio" id="mac_custom" name="router_mac"', !$mac_chk ? ' checked="checked"' : '', '>
					<label for="mac_custom">Use this MAC Address</label>
				</div>
				<div class="icheck-primary">
					<input class="mac_opt" type="radio" id="mac_default" name="router_mac"', $mac == $def ? ' checked="checked"' : '', '>
					<label for="mac_default">Current MAC Address</label>
				</div>
				<div class="icheck-primary">
					<input class="mac_opt" type="radio" id="mac_computer" name="router_mac"', $mac == $mac_com ? ' checked="checked"' : '', ' data-mac="', $mac_com, '"', $mac_com == "" ? ' disabled="disabled"' : '', '>
					<label for="mac_computer">Use Computer MAC Address</label>
				</div>
				<div class="icheck-primary">
					<input class="mac_opt" type="radio" id="mac_random" name="router_mac"', $mac == $mac_com ? ' checked="checked"' : '', ' data-mac="', $mac_com, '">
					<label for="mac_random">Use Randomly Generated MAC Address</label>
				</div>
			</div>
			<div class="col-sm-6">
				<span class="float-right">
					<input id="mac_addr" name="mac_addr" type="text" class="form-control" placeholder="', strtoupper($mac), '" value="', $mac, '" maxlength="17"', $mac_chk ? ' disabled="disabled"' : '', '>
				</span>
			</div>
		</div>';

###########################################################################################
# Finalize Page:
###########################################################################################
echo '
	</div>
	<div class="card-footer">
		<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-success center_50" id="apply_changes">Apply Changes</button></a>
	</div>
	<!-- /.card-body -->
</div>';
apply_changes_modal("Please wait while router settings are being set....", true);
site_footer('Init_Settings("' . $mac_com . '", "' . $mac . '");');
