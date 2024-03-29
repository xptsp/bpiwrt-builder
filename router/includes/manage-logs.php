<?php

$cmds = array(
	'dmesg' => array('cmd' => 'dmesg', 'header' => 'Kernel Logs'),
	'journal' => array('cmd' => 'journalctl', 'header' => 'Journal Logs'),
);
if (file_exists('/var/log/ulog/port_scans.log'))
	$cmds['portscan'] = array('header' => 'Port Scan Logs',  'cmd' => 'cat /var/log/ulog/port_scans.log');
if (file_exists('/var/log/ulog/udp_floods.log'))
	$cmds['udp_flood'] = array('header' => 'UDP Floods Logs', 'cmd' => 'cat /var/log/ulog/udp_floods.log');
$_GET['which'] = (!isset($_GET['which']) || !isset($cmds[$_GET['which']])) ? 'dmesg' : $_GET['which'];
$log = &$cmds[ $_GET['which'] ];
#echo '<pre>'; print_r($log); exit;

# Divide the program output into pages of specified number of lines:
$lines = "";
$per_page = 50;
$pages = 0;
$output = trim(@shell_exec($log['cmd']));
#echo '<pre>'; print_r($output); exit;
if (empty($output))
	$lines = '<div><center><strong>No Logs To Display</strong></center></div>';
else
{
	foreach (explode("\n", $output) as $num => $line)
	{
		$pages = floor(($num + $per_page) / $per_page);
		$lines .= '<div class="everything page_' . $pages . ($pages > 1 ? ' hidden' : '') . '" id="dmesg-' . $num . '">' . htmlspecialchars($line) . "\n" . '</div>';
	}
}
#echo '<pre>'; echo $lines; exit;

# Assemble pagination code:
$pagination = '';
if ($pages > 1)
{
	if ($pages > 10)
		$pagination .= '
				<li class="page-item page_first" id="prev"><span class="page-link">&laquo;&laquo;</span></li>';
	$pagination .= '
				<li class="page-item page_prev" id="prev"><span class="page-link">&laquo;</span></li>';
	for ($page = 1; $page <= $pages; $page++)
		$pagination .= '
				<li class="page-item pagelink pagelink_' . $page . ($page == 1 ? ' active' : '') . ($page > 10 ? ' hidden' : '') . '"><span class="page-link">' . $page . '</span></li>';
	$pagination .= '
				<li class="page-item page_next" id="next"><span class="page-link">&raquo;</span></li>';
	if ($pages > 10)
		$pagination .= '
				<li class="page-item page_last" id="next"><span class="page-link">&raquo;&raquo;</span></li>';
}

# Output everything:
site_menu();
echo '
<div class="row">
	<div class="col-sm-12">
		<div class="card card-tabs card-primary">
			<div class="card-header">
				Selected Logs: <select id="which">';
foreach ($cmds as $cmd => $arr)
{
	echo '<option value="', $cmd == 'dmesg' ? '' : $cmd, '"', $_GET['which'] == $cmd ? ' selected="selected"' : '', '>' . $arr['header'] . '</option>';
}
echo '
				</select>
    	    </div>
			<div class="card-body">
				<div class="row">
					<div class="col-8">';
if ($pages > 1)
	echo '
						<ul class="pagination pagination-sm m-0" id="pages">', $pagination, '
						</ul>';
echo '
					</div>
					<div class="col-4">
						<div class="float-right input-group input-group-sm">
							<input type="text" id="search" class="form-control float-right" placeholder="Search">
							<div class="input-group-append">
								<button type="submit" class="btn btn-default">
									<i class="fas fa-search"></i>
								</button>
							</div>
						</div>
					</div>
				</div>
				<hr style="border-width: 2px" />
				<pre id="lines">' . "\n" . $lines . '</pre>
			</div>
		</div>
	</div>
</div>';

# Wrap it up:
apply_changes_modal("Please wait while the logs are being loaded...", true);
site_footer('Init_Logs(' . $pages . ');');
