<?php
#################################################################################################
# If action specified and invalid SID passed, force a reload of the page.  Otherwise:
#################################################################################################
if (isset($_POST['action']))
{
	#################################################################################################
	# Make sure we know what repo is expected to be actioned upon:
	#################################################################################################
	$match = array(
		'webui' => 'bpi-r2-router-builder',
		'regdb' => 'wireless-regdb',
	);
	$misc = isset($match[$_POST['misc']]) ? $match[$_POST['misc']] : $_POST['misc'];

	#################################################################################################
	# ACTION: CHECK => Returns the current version of the specified repo:
	#################################################################################################
	if ($_POST['action'] == 'check')
	{
		$time = trim(@shell_exec('router-helper git remote ' . $misc));
		die(json_encode(array(
			'elem' => $_POST['misc'],
			'time' => $time ? date('Y.md.Hi', $time) : 'Invalid Data',
		)));
	}
	#################################################################################################
	# ACTION: PULL => Updates to the current version of the specified repo:
	#################################################################################################
	else if ($_POST['action'] == 'pull')
	{
		unset($_SESSION[$_POST['misc'] . '_version']);
		unset($_SESSION[$_POST['misc'] . '_version_last']);
		$out = trim(@shell_exec('router-helper git update ' . $misc));
		$lines = explode("\n", $out);
		die($lines[ count($lines) - 1 ] == "ERROR" ? '<pre>' . $out . '</pre>' : "OK");
	}
	#################################################################################################
	# Got here?  We need to return "invalid action" to user:
	#################################################################################################
	die("Invalid action");
}

#################################################################################################
# This function shows a card about the specified repository:
#################################################################################################
function show_repo($title, $repo, $url, $alt_desc = null)
{
	if (!isset($_SESSION[$repo . '_version']))
	{
		$time = trim(@shell_exec('router-helper git current ' . $repo));
		$_SESSION[$repo . '_version'] = ($time == (int) $time ? date('Y.md.Hi', (int) $time) : "Invalid Data");
	}
	echo '
		<div class="col-md-6">
			<div class="card card-primary">
				<div class="ribbon-wrapper ribbon-xl hidden" id="', $repo, '_ribbon">
					<div class="ribbon bg-success text-lg">
						Updated!
					</div>
				</div>
				<div class="card-header">
					<h3 class="card-title"><i class="fab fa-github"></i> ', $title, '</h3>
				</div>
				<!-- /.card-header -->
				<div class="card-body table-responsive p-0" id="', $repo, '_div">
					<table class="table">
						<tr>
							<td width="40%"><strong>Current Version</strong></td>
							<td width="60%">v<span id="', $repo, '_current">', $_SESSION[$repo . '_version'], '</span></td>
						</tr>
						<tr>
							<td><strong>Latest Version</strong></td>
							<td><span id="', $repo, '_latest"><i>Retrieving...</i></span></td>
						</tr>
						<tr>
							<td><strong>Repository Location</strong></td>
							<td nowrap><a href="', $url, '" target="_blank">', $alt_desc == null ? $title : str_replace("https://github.com/", "", $alt_desc), '</a></td>
						</tr>
						<tr id="', $repo, '_check_div">
							<td colspan="2">
								<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-primary center_50 check_repo" id="', $repo, '_check">Check for Update</button></a>
							</td>
						</tr>
						<tr class="hidden" id="', $repo, '_pull_div">
							<td colspan="2">
								<a href="javascript:void(0);"><button type="button" class="btn btn-block btn-primary center_50 pull_repo" id="', $repo, '_pull">Pull Updates</button></a>
							</td>
						</tr>
					</table>
				</div>
				<!-- /.card-body -->
			</div>
			<!-- /.card -->
		</div>
		<!-- /.col -->';
}

#################################################################################################
# Main code of the page:
#################################################################################################
site_menu();
echo '
<div class="container-fluid">
	<div class="row">';
show_repo('Web UI', 'webui', 'https://github.com/xptsp/bpiwrt-builder', 'xptsp/bpiwrt-builder');
show_repo('Multicast Relay', 'multicast-relay', 'https://github.com/alsmith/multicast-relay', 'alsmith/multicast-relay');
echo '
	</div>
</div>';
apply_changes_modal("Please wait while the GitHub repository is being updated...", true);
site_footer('Init_Repo();');

