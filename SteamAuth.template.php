<?php
// Version 1.0: SteamAuth.template.php
// Licence: ISC

function template_steam_login_above() {}
function template_steam_login_below()
{
	global $scripturl;

	echo '
	<center>
		<form action="', $scripturl, '?action=login;steam" method="post">
			<input type="image" src="http://cdn.steamcommunity.com/public/images/signinthroughsteam/sits_large_border.png">
		</form>
	</center>';
}

?>