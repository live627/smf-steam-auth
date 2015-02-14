<?php
// Version: 1.0: SteamAuth.php
// Licence: ISC

if (!defined('SMF'))
	die('Hacking attempt...');

function steam_auth_load_theme()
{
	global $context, $modSettings, $smcFunc, $sourcedir, $user_settings, $txt;

	if ($context['current_action'] == 'login' && !empty($modSettings['steam_auth_api_key']))
	{
		loadLanguage('SteamAuth');

		try
		{
			require_once($sourcedir . '/openid.php');
			$openid = new LightOpenID($_SERVER['SERVER_NAME']);
			if (!$openid->mode)
			{
				if (isset($_GET['steam']))
				{
					$openid->identity = 'http://steamcommunity.com/openid/?l=english';    // This is forcing english because it has a weird habit of selecting a random language otherwise
					header('Location: ' . $openid->authUrl());
				}
				else
				{
					loadTemplate('SteamAuth');
					$context['template_layers'][] = 'steam_login';
				}
			}
			elseif ($openid->mode == 'cancel')
				$context['login_errors'] = array($txt['steam_auth_x']);
			else
			{
				if ($openid->validate())
				{
					$id = $openid->identity;
					$ptn = "/^http:\/\/steamcommunity\.com\/openid\/id\/(7[0-9]{15,25}+)$/";
					preg_match($ptn, $id, $matches);
					$steamid = $matches[1];

					$request = $smcFunc['db_query']('', '
						SELECT passwd, id_member, id_group, lngfile, is_activated, email_address, additional_groups, member_name, password_salt,
							openid_uri, passwd_flood
						FROM {db_prefix}members
						WHERE member_name = {string:steamid}
						LIMIT 1',
						array(
							'steamid' => 'steamuser-' . $steamid,
						)
					);

					$user_settings = $smcFunc['db_fetch_assoc']($request);
					$smcFunc['db_free_result']($request);
					if (empty($user_settings))
					{
						require_once($sourcedir . '/Subs-Package.php');
						$url = "http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=$modSettings[steam_auth_api_key]&steamids=$matches[1]";
						$json_object= fetch_web_data($url);
						$json_decoded = json_decode($json_object);

						foreach ($json_decoded->response->players as $player)
						{
							$regOptions = array(
								'interface' => '',
								'username' => 'steamuser',
								'email' => 'steamuser-' . $steamid . '@' . $_SERVER['SERVER_NAME'],
								'check_reserved_name' => false,
								'check_password_strength' => false,
								'check_email_ban' => false,
								'send_welcome_email' => false,
								'require' => 'nothing',
								'extra_register_vars' => array(
									'member_name' => 'steamuser-' . $steamid,
									'real_name' => $player->personaname,
									'avatar' => $player->avatarmedium,
									'date_registered' => $player->timecreated,
								),
								'theme_vars' => array(),
							);
							require_once($sourcedir . '/Subs-Members.php');
							mt_srand(time() + 1277);
							$regOptions['password'] = generateValidationCode();
							$regOptions['password_check'] = $regOptions['password'];
							if (is_array($errors = registerMember($regOptions, true)))
								$context['login_errors'] = $errors;
						}

						$request = $smcFunc['db_query']('', '
							SELECT passwd, id_member, id_group, lngfile, is_activated, email_address, additional_groups, member_name, password_salt,
								openid_uri, passwd_flood
							FROM {db_prefix}members
							WHERE member_name = {string:steamid}
							LIMIT 1',
							array(
								'steamid' => 'steamuser-' . $steamid,
							)
						);

						$user_settings = $smcFunc['db_fetch_assoc']($request);
						$smcFunc['db_free_result']($request);
					}

					require_once($sourcedir . '/LogInOut.php');
					DoLogin();
				}
				else
					$context['login_errors'] = array($txt['error_occured']);
			}
		}
		catch (ErrorException $e)
		{
			$context['login_errors'] = array($e->getMessage());
		}
	}
}

function steam_auth_general_mod_settings(&$config_vars)
{
	global $txt;

	loadLanguage('SteamAuth');
	$config_vars = array_merge($config_vars, array(
		'',
		array('text', 'steam_auth_api_key', 80, 'postinput' => $txt['steam_auth_api_key_link']),
	));
}

?>