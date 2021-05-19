<?php
if (!isset($_GET['sid']) or $_GET['sid'] != strrev(session_id()))
{
	require_once("404.php");
	exit();
}
header('Content-type: application/json');

# Get number of updates available:
$result = trim(@shell_exec('/usr/local/bin/router-helper apt update | grep "packages"'));
$updates = 0;
if (preg_match("/(\d+) packages/", $result, $regex))
	$updates = $regex[1];

# Gather the list of upgradable packages: 
$table = "";
$list = explode("\n", trim(@shell_exec('/usr/local/bin/router-helper apt list --upgradable')));
foreach ($list as $id => $text)
{
	if ($text == "Listing...")
		unset($list[$id]);
	else
	{
		$tmp = explode(" ", $list[$id], 4);
		if (isset($tmp[3]))
		{
			$table .= 
				'<tr>' .
					'<td>' . explode("/", $tmp[0])[0] . '</td>' .
					'<td>' . $tmp[1] . '</td>' .
					'<td>' . explode(" ", str_replace(']', '', str_replace('[', '', $tmp[3])))[2] . '</td>' .
				'</tr>';
		}
	}
}

# Output the gathered information as a JSON array:
echo json_encode(array(
	'updates' => $updates, 
	'list' => $table,
));
