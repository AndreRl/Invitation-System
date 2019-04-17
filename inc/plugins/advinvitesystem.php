<?php
/***************************************************************************
 *
 *   This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 ***************************************************************************/
// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.");
}

$plugins->add_hook('usercp_start', 'advinvitesystem_usercp');
$plugins->add_hook('member_profile_start', 'advinvitesystem_profile');
$plugins->add_hook("member_register_start", "advinvitesystem_registerstart");
$plugins->add_hook("datahandler_user_validate", "advinvitesystem_register");
$plugins->add_hook("member_do_register_end", "advinvitesystem_registerdone");

function advinvitesystem_info()
{
	global $mybb, $lang;
	
	$lang->load('advinvitesystem');
	
	if($mybb->settings['usereferrals'] != 0)
	{
		$referralsystem_notice = "<br><br><span style=\"color: red\"><b>".$lang->advinvitesystem_notice."</b></span>";
	}
	
	return array(
			"name"  => $lang->advinvitesystem_name,
			"description"=> $lang->advinvitesystem_desc . $referralsystem_notice,
			"website"        => "https://oseax.com",
			"author"        => "Wires <i>(AndreRl)</i>",
			"authorsite"    => "https://oseax.com",
			"version"        => "1.0",
			"guid"             => "",
			"compatibility" => "18*"
		);
}

function advinvitesystem_install()
{
	global $db, $mybb, $lang;
	
	advinvitesystem_refsystem_check();
	$lang->load('advinvitesystem');
	if(!$db->table_exists('advinvitesystem_codes'))
	{
		$db->query('CREATE TABLE '.TABLE_PREFIX.'advinvitesystem_codes (
			id smallint(4) NOT NULL auto_increment,
			code varchar(10) NOT NULL,
			creator int NOT NULL,
			used int NOT NULL,
			usedby int NOT NULL,
			dateline int NOT NULL,
			PRIMARY KEY  (id)
		) ENGINE=innodb;');
  	}
	
	$setting_group = array(
		'name' => 'advinvitesystem',
		'title' => $lang->advinvitesystem_title,
		'description' => $lang->advinvitesystem_sdesc,
		'disporder' => 5, 
		'isdefault' => 0
	);
	
	$gid = $db->insert_query("settinggroups", $setting_group);
	
	$setting_array = array(
		'advinvitesystem_enable' => array(
			'title' => $lang->advinvitesystem_enable,
			'description' => $lang->advinvitesystem_enabledesc,
			'optionscode' => 'yesno',
			'value' => 1,
			'disporder' => 1
		),
		'advinvitesystem_maxcodes' => array(
			'title' => $lang->advinvitesystem_limitcodes,
			'description' => $lang->advinvitesystem_limitcodesdesc,
			'optionscode' => "numeric",
			'value' => 5,
			'disporder' => 2
		),
		'advinvitesystem_groupcontrol' => array(
			'title' => $lang->advinvitesystem_groupcontrol,
			'description' => $lang->advinvitesystem_groupcontroldesc,
			'optionscode' => 'groupselect',
			'value' => "-1",
			'disporder' => 3
		),
		'advinvitesystem_quickreg' => array(
			'title' => $lang->advinvitesystem_quickregister,
			'description' => $lang->advinvitesystem_quickregisterdesc,
			'optionscode' => 'yesno',
			'value' => 1,
			'disporder' => 4
		),
		'advinvitesystem_groupcp_quickreg' => array(
			'title' => $lang->advinvitesystem_gc_quickregister,
			'description' => $lang->advinvitesystem_gc_quickregisterdesc,
			'optionscode' => 'groupselect',
			'value' => "-1",
			'disporder' => 5
		),
		'advinvitesystem_quickreg_bypassregtype' => array(
			'title' => $lang->advinvitesystem_bypass,
			'description' => $lang->advinvitesystem_bypassdesc,
			'optionscode' => 'yesno',
			'value' => 1,
			'disporder' => 6
		),
	);

	foreach($setting_array as $name => $setting)
	{
		$setting['name'] = $name;
		$setting['gid'] = $gid;

		$db->insert_query('settings', $setting);
	}

	rebuild_settings();
	
}

function advinvitesystem_is_installed()
{
    global $db;
    if($db->table_exists("advinvitesystem_codes"))
    {
        return true;
    }
    return false;
}

function advinvitesystem_uninstall()
{
	global $db;
	
	$db->drop_table('advinvitesystem_codes');
	$db->delete_query('settings', "name LIKE ('advinvitesystem_%')");
	$db->delete_query('settinggroups', "name = 'advinvitesystem'");

	rebuild_settings();
}

function advinvitesystem_activate()
{
	global $db;
	
	advinvitesystem_refsystem_check();

	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("usercp_nav_misc",'#'.preg_quote('{$attachmentop}').'#','{$attachmentop}
	<tr><td class="trow1 smalltext"><a href="usercp.php?action=advinvite">Invite Codes</a></td></tr>'); 
	
	find_replace_templatesets("member_register",'#'.preg_quote('{$referrer}').'#','{$referrer}
	{$advfield}'); 
	
	find_replace_templatesets("usercp_nav_misc",'#'.preg_quote('<tr><td class="trow1 smalltext"><a href="usercp.php?action=advinvite">Invite Codes</a></td></tr>').'#',
	'<tr><td class="trow1 smalltext"><a href="usercp.php?action=advinvite">Invite Codes</a></td></tr> <tr><td class="trow1 smalltext"><a href="usercp.php?action=advquickreg">Quick Referral Register</a></td></tr>'); 

	$advinvitesystem_codes_template = '<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->advinvitesystem_title}</title>
{$headerinclude}
</head>
<body>
{$header}
<table width="100%" border="0" align="center">
<tr>
	{$usercpnav}
	<td valign="top">

		<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
			<tr>
				<td class="thead" colspan="3"><strong>{$lang->advinvitesystem_title}</strong></td>
			</tr>
	<tr>
			<td class="tcat" width="20%" align="center"><span class="smalltext"><strong>{$lang->advinvitesystem_code}</strong></span></td>
	<td class="tcat" width="13%" align="center" style="white-space: nowrap"><span class="smalltext"><strong>{$lang->advinvitesystem_generated}</strong></span></td>
			<td class="tcat" width="10%" align="center" style="white-space: nowrap"><span class="smalltext"><strong>{$lang->advinvitesystem_action}</strong></span></td>
	</tr>
	{$codeslisting}
		</table>
		<br />
		<div align="center">
		<form method="post">
		<input type="hidden" name="my_post_key" value="{$mybb->post_code}"></input>
		<input type="submit" class="button" name="submit" value="{$lang->advinvitesystem_button}">
		</form>
		</div>
	</td>
</tr>
</table>
</form>
{$footer}
</body>
</html>';
	
	$template_array = array(
		'title' => 'AdvInviteSystem_InviteCodes',
		'template' => $db->escape_string($advinvitesystem_codes_template),
		'sid' => '-1',
		'version' => '',
		'dateline' => time()
	);
	$db->insert_query('templates', $template_array);
	
	$advinvitesystem_quickreg_template = '<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->advinvitesystem_qrtitle}</title>
{$headerinclude}
</head>
<body>
{$header}
	{$regerrors}
<table width="100%" border="0" align="center">
<tr>
	{$usercpnav}
	<td valign="top">

		<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
			<tr>
				<td class="thead" colspan="3"><strong>{$lang->advinvitesystem_qrtitle}</strong>
				<br>
				<div class="smalltext">{$lang->advinvitesystem_qrdesc}</div>
				</td>
			</tr>
	<tr>
	<td class="trow1" colspan="2">{$lang->advinvitesystem_text}</td>
	</tr>
	<tr>
			<td class="tcat" colspan="2"><strong>{$lang->advinvitesystem_qrusername}</strong></td>
	</tr>
	<tr>
	<td class="trow1" width="40%"><strong>{$lang->advinvitesystem_qrdesired}</strong></td>
			<form method="post">
	<td class="trow1" width="60%"><input type="text" class="textbox" name="username" size="25"></td>
	</tr>
	<tr>
			<td class="tcat" colspan="2"><strong>{$lang->advinvitesystem_qremail}</strong></td>	
	</tr>
	<tr>
	<td class="trow1" width="40%"><strong>{$lang->advinvitesystem_qremail1}</strong></td>
	<td class="trow1" width="60%"><input type="text" class="textbox" name="email" size="25"></td>
	</tr>
	<tr>
	<td class="trow1" width="40%"><strong>{$lang->advinvitesystem_qremail2}</strong></td>
	<td class="trow1" width="60%"><input type="text" class="textbox" name="email2" size="25"></td>
	</tr>
	{$captcha}
		</table>
		<br />
		<div align="center">
		<input type="hidden" name="my_post_key" value="{$mybb->post_code}"></input>
		<input type="submit" class="button" name="submit" value="{$lang->advinvitesystem_qrbutton}">
		</form>
		</div>
	</td>
</tr>
</table>
</form>
{$footer}
</body>
</html>';
	
	$template2_array = array(
		'title' => 'AdvInviteSystem_QuickReg',
		'template' => $db->escape_string($advinvitesystem_quickreg_template),
		'sid' => '-1',
		'version' => '',
		'dateline' => time()
	);
	$db->insert_query('templates', $template2_array);

	$advinvitesystem_codefield_template = '<br />
<fieldset class="trow2">
<legend><strong>{$lang->advinvitesystem_regtitle}</strong></legend>
<table cellspacing="0" cellpadding="{$theme[\'tablespace\']}">
<tr>
<td><span class="smalltext"><label for="advcode">{$lang->advinvitesystem_regdesc}</label></span></td>
</tr>
<tr>
<td>
<input type="text" class="textbox" name="advcode" id="advcode" value="" style="width: 100%;" />
</td>
</tr></table>
</fieldset>';
	
	$template3_array = array(
		'title' => 'AdvInviteSystem_CodeField',
		'template' => $db->escape_string($advinvitesystem_codefield_template),
		'sid' => '-1',
		'version' => '',
		'dateline' => time()
	);
	$db->insert_query('templates', $template3_array);
}

function advinvitesystem_deactivate()
{
	global $db;
	
	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("usercp_nav_misc",'#'.preg_quote('<tr><td class="trow1 smalltext"><a href="usercp.php?action=advinvite">Invite Codes</a></td></tr>').'#',''); 
	find_replace_templatesets("usercp_nav_misc",'#'.preg_quote('<tr><td class="trow1 smalltext"><a href="usercp.php?action=advquickreg">Quick Referral Register</a></td></tr>').'#',''); 
	find_replace_templatesets("member_register",'#'.preg_quote('{$advfield}').'#',''); 
	$db->delete_query("templates", "title LIKE ('AdvInviteSystem_%')");

}

function advinvitesystem_refsystem_check()
{
	global $mybb, $lang;

	if($mybb->settings['usereferrals'] != 0)
	{
		$lang->load('advinvitesystem');
		flash_message($lang->advinvitesystem_noticeerror, 'error');
		admin_redirect('index.php?module=config-plugins');
	}
}

function advinvitesystem_usercp()
{
	global $mybb, $db, $templates, $header, $headerinclude, $footer, $navigation, $usercpnav, $theme, $lang, $errors, $userhandler;
	if($mybb->get_input('action') == 'advinvite' || $mybb->get_input('action') == 'advquickreg')
	{
		if($mybb->settings['advinvitesystem_enable'] != 1)
		{
			return;
		}
	}
	
	$lang->load('advinvitesystem');
	
	if($mybb->get_input('action') == 'advinvite')
	{
		add_breadcrumb($lang->advinvitesystem_title, "usercp.php?action=advinvite");
		
		if($mybb->settings['advinvitesystem_groupcontrol'] != "-1" && !is_member($mybb->settings['advinvitesystem_groupcontrol']))
		{
			error_no_permission();
		}
		
		// Display rows
		$user = get_user($mybb->user['uid']);
		$query = $db->simple_select("advinvitesystem_codes", "*", "creator = '".$user['uid']."' AND used <> 1");
		$count = $db->num_rows($query);
		if($count == 0)
		{
			$codeslisting = '<td class="trow1" colspan="3"><center>'.$lang->advinvitesystem_listingerror.'</center></td>';
		} else 
		{
		
		while($row = $db->fetch_array($query))
		{
			$dateline = my_date('relative', $row['dateline']);
			$codeslisting .= "<tr>
			<td class=\"trow1\" align=\"center\" style=\"white-space: nowrap\">".$row['code']."</td>
			<td class=\"trow1\" align=\"center\" style=\"white-space: nowrap\">".$dateline."</td>
			<td class=\"trow1\" align=\"center\" style=\"white-space: nowrap\"><a href=\"\usercp.php?action=advinvite&amp;code=".$row['id']."&amp;do=delete&amp;my_post_key={$mybb->post_code}\">".$lang->advinvitesystem_del."</a></td>
			</tr>";
			
		}
		
		}
		
		// Generate button
		if(isset($mybb->input['submit']) && $mybb->request_method == "post")
		{
			verify_post_check($mybb->get_input('my_post_key'));
			// Max limit?
			if($count >= $mybb->settings['advinvitesystem_maxcodes'])
			{
				error($lang->advinvitesystem_maxcodes.$mybb->settings['advinvitesystem_maxcodes'].".");
			}
			$code = random_str(10, true);
			$insert = array(
				"code" => $code,
				"creator" => $user['uid'],
				"dateline" => time()
				
			);
			$db->insert_query("advinvitesystem_codes", $insert);
			redirect("usercp.php?action=advinvite", $lang->advinvitesystem_generatesuccess);
			
		} else if($mybb->get_input('do') == 'delete')
		{
			verify_post_check($mybb->get_input('my_post_key'));
			
			// Does user own code?
			$code = (int)$mybb->get_input('code');
			$check = advinvitesystem_codecreator($code, $user['uid']);
			if($check === true)
			{
				$db->delete_query("advinvitesystem_codes", "id = '".$code."' AND creator = '".$user['uid']."'");
				redirect("usercp.php?action=advinvite", $lang->advinvitesystem_delsuccess);
			} else {
				error_no_permission();
			}
		}
		
		eval("\$page = \"".$templates->get("AdvInviteSystem_InviteCodes")."\";");
	} else if($mybb->get_input('action') == 'advquickreg')
	{
		add_breadcrumb($lang->advinvitesystem_qrtitle, "usercp.php?action=advquickreg");
		if($mybb->settings['advinvitesystem_quickreg'] != 1)
		{
			return;
		}else if($mybb->settings['advinvitesystem_groupcp_quickreg'] != "-1" && !is_member($mybb->settings['advinvitesystem_groupcp_quickreg']))
		{
			error_no_permission();
		}
		
		require_once MYBB_ROOT.'inc/class_captcha.php';
		
		$captcha = '';
		// Generate CAPTCHA?
		if($mybb->settings['captchaimage'])
		{
			$form_captcha = new captcha(true, "post_captcha");
				$captcha = $form_captcha->html;
		}
		
		if(isset($mybb->input['submit']) && $mybb->request_method == "post")
		{
			// Use MyBB's Random Pass
			$password_length = (int)$mybb->settings['minpasswordlength'];
			if($password_length < 8)
			{
				$password_length = min(8, (int)$mybb->settings['maxpasswordlength']);
			}
			$mybb->input['password'] = random_str($password_length, $mybb->settings['requirecomplexpasswords']);
			$mybb->input['password2'] = $mybb->input['password'];
			
			// Do we bypass registration method?
			$bypass = false;
			$errors = array();
			if($mybb->settings['advinvitesystem_quickreg_bypassregtype'] == 1)
			{
				$usergroup = 2;
				$user = advinvitesystem_datahandler($usergroup);
				$bypass = true;
			} else
			{
				if($mybb->settings['regtype'] == "verify" || $mybb->settings['regtype'] == "admin" || $mybb->settings['regtype'] == "both")
				{
					$usergroup = 5;
					$user = advinvitesystem_datahandler($usergroup);
				}else
				{
					$usergroup = 2;
					$user = advinvitesystem_datahandler($usergroup);
				}	
			}
			
			if($mybb->settings['captchaimage'])
			{
			//	$captcha = new captcha;
				if($form_captcha->validate_captcha() == false)
				{
					$lang->load('member');
					// CAPTCHA validation failed
					$errors[] = $lang->error_regimageinvalid;
				/*	foreach($captcha->get_errors() as $error)
					{
						$errors[] = $error;
					}*/
				}
			}
			
			if(!empty($errors))
			{
				$regerrors = inline_error($errors);
				$mybb->input['action'] = "advquickreg";
			} else
			{
				$user_info = $userhandler->insert_user();
				
				// Update referrer and referee
				$mine = get_user($mybb->user['uid']);
				$uid = $mine['uid'];
				$referee = $user_info['uid'];
				
				$update_referrer = array(
					'referrals' => ++$mine['referrals']
				);
				$db->update_query("users", $update_referrer, "uid = '".$uid."'");
				
				$update_referee = array(
					'referrer' => $uid
				);
				
				$db->update_query("users", $update_referee, "uid = '".$referee."'");
				
				if($bypass == true)
				{
					advinvitesystem_randompass_register($user_info);
				}
				else if($mybb->settings['regtype'] == 'verify')
				{
					advinvitesystem_verify_register($user_info);
				} else if($mybb->settings['regtype'] == 'randompass')
				{
					advinvitesystem_randompass_register($user_info);
				} else if($mybb->settings['regtype'] == 'admin')
				{
					advinvitesystem_admin_register($user_info);
				} else if($mybb->settings['regtype'] == 'both')
				{
					advinvitesystem_both_register($user_info);
				} else
				{
					advinvitesystem_randompass_register($user_info);
				}
				redirect("usercp.php?action=advquickreg", $lang->advinvitesystem_qrsuccess);
			}
		}
		eval("\$page = \"".$templates->get("AdvInviteSystem_QuickReg")."\";");
	}
	output_page($page);
}

function advinvitesystem_registerstart()
{
	global $mybb, $templates, $advfield;
	
	if($mybb->settings['advinvitesystem_enable'] != 1)
	{
		return;
	}
	
	eval("\$advfield = \"".$templates->get("AdvInviteSystem_CodeField")."\";");
}

function advinvitesystem_register($err)
{
	global $mybb, $db, $code;
	
	$advcode = $db->escape_string($mybb->get_input('advcode'));
	if(!empty($advcode))
	{
		// Is this code existent or used?
		$query = $db->simple_select("advinvitesystem_codes", "*", "code = '".$advcode."' AND used = 0");
		$count = $db->num_rows($query);
		$code = '';

		if($count == 0)
		{
			// Error for not being valid, or its already used
			$err->set_error($lang->advinvitesystem_regerror);
			return false;
		} else
		{
			// Carry over to next function at member_register_end
			$code = $advcode;
			$valid = true;
			return;
		}
		
	}
	
}

function advinvitesystem_registerdone()
{
	global $db, $code, $valid, $user_info;
	
	if(empty($code) && $valid != true)
	{
		return;
	} else
	{
		// Select code row for uid etc of creator
		$select = $db->simple_select("advinvitesystem_codes", "*", "code = '".$code."'");
		$creator = $db->fetch_array($select);

		// Update referee account to mention referrer uid
		$update = array(
			"used" => 1,
			"usedby" => $user_info['uid'],
			"dateline" => TIME()
		);
		
		$db->update_query("advinvitesystem_codes", $update, "code = '".$code."' AND creator = '".$creator['creator']."'");
		
		$codeowner = get_user($creator['creator']);
		
		$update_referrer = array(
			"referrals" => ++$codeowner['referrals']
		);
		
		$db->update_query("users", $update_referrer, "uid = '".$codeowner['uid']."'");
		
		$update_referee = array(
			"referrer" => $codeowner['uid']
		);
		
		$db->update_query("users", $update_referee, "uid = '".$user_info['uid']."'");
	}
	
}

function advinvitesystem_profile()
{
	global $mybb;
	
	$mybb->settings['usereferrals'] = 1;
}

function advinvitesystem_codecreator($code, $uid=null)
{
	global $db;
	
	if($uid === null)
	{
		$uid = $mybb->user['uid'];
	}
	
	$query = $db->simple_select("advinvitesystem_codes", "*", "id = '".$code."' AND creator = '".$uid."'");
	$count = $db->num_rows($query);
	
	if($count == 0)
	{
		return false;
	} else
	{
		return true;
	}

}

function advinvitesystem_both_register($user_info)
{
	global $db, $mybb, $lang, $cache;

	$lang->load('messages');
	$lang->load('member');
	$groups = $cache->read("usergroups");
	$admingroups = array();
	if(!empty($groups)) // Shouldn't be...
	{
		foreach($groups as $group)
		{
			if($group['cancp'] == 1)
			{
				$admingroups[] = (int)$group['gid'];
			}
		}
	}
	if(!empty($admingroups))
	{
		$sqlwhere = 'usergroup IN ('.implode(',', $admingroups).')';
		foreach($admingroups as $admingroup)
		{
			switch($db->type)
			{
				case 'pgsql':
				case 'sqlite':
					$sqlwhere .= " OR ','||additionalgroups||',' LIKE '%,{$admingroup},%'";
					break;
				default:
					$sqlwhere .= " OR CONCAT(',',additionalgroups,',') LIKE '%,{$admingroup},%'";
					break;
			}
		}
		$q = $db->simple_select('users', 'uid,username,email,language', $sqlwhere);
		while($recipient = $db->fetch_array($q))
		{
			// First we check if the user's a super admin: if yes, we don't care about permissions
			$is_super_admin = is_super_admin($recipient['uid']);
			if(!$is_super_admin)
			{
				// Include admin functions
				if(!file_exists(MYBB_ROOT.$mybb->config['admin_dir']."/inc/functions.php"))
				{
					continue;
				}
				require_once MYBB_ROOT.$mybb->config['admin_dir']."/inc/functions.php";
				// Verify if we have permissions to access user-users
				require_once MYBB_ROOT.$mybb->config['admin_dir']."/modules/user/module_meta.php";
				if(function_exists("user_admin_permissions"))
				{
					// Get admin permissions
					$adminperms = get_admin_permissions($recipient['uid']);
					$permissions = user_admin_permissions();
					if(array_key_exists('users', $permissions['permissions']) && $adminperms['user']['users'] != 1)
					{
						continue; // No permissions
					}
				}
			}
			// Load language
			if($recipient['language'] != $lang->language && $lang->language_exists($recipient['language']))
			{
				$reset_lang = true;
				$lang->set_language($recipient['language']);
				$lang->load("member");
			}
			$subject = $lang->sprintf($lang->newregistration_subject, $mybb->settings['bbname']);
			$message = $lang->sprintf($lang->newregistration_message, $recipient['username'], $mybb->settings['bbname'], $user_info['username'], $mybb->get_input('password'));
			my_mail($recipient['email'], $subject, $message);
		}
		// Reset language
		if(isset($reset_lang))
		{
			$lang->set_language($mybb->settings['bblanguage']);
			$lang->load("member");
		}
	}
	$activationcode = random_str();
	$activationarray = array(
		"uid" => $user_info['uid'],
		"dateline" => TIME_NOW,
		"code" => $activationcode,
		"type" => "b"
	);
	$db->insert_query("awaitingactivation", $activationarray);
	$emailsubject = $lang->sprintf($lang->emailsubject_activateaccount, $mybb->settings['bbname']);
	$passwordsubject = $lang->sprintf($lang->emailsubject_randompassword, $mybb->settings['bbname']);
	switch($mybb->settings['username_method'])
	{
		case 0:
			$emailmessage = $lang->sprintf($lang->email_activateaccount, $user_info['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $user_info['uid'], $activationcode);
			$passwordmessage = $lang->sprintf($lang->email_randompassword, $user_info['username'], $mybb->settings['bbname'], $user_info['username'], $mybb->get_input('password'));
			break;
		case 1:
			$emailmessage = $lang->sprintf($lang->email_activateaccount1, $user_info['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $user_info['uid'], $activationcode);
			$passwordmessage = $lang->sprintf($lang->email_randompassword1, $user_info['username'], $mybb->settings['bbname'], $user_info['username'], $mybb->get_input('password'));
			break;
		case 2:
			$emailmessage = $lang->sprintf($lang->email_activateaccount2, $user_info['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $user_info['uid'], $activationcode);
			$passwordmessage = $lang->sprintf($lang->email_randompassword2, $user_info['username'], $mybb->settings['bbname'], $user_info['username'], $mybb->get_input('password'));
			break;
		default:
			$emailmessage = $lang->sprintf($lang->email_activateaccount, $user_info['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $user_info['uid'], $activationcode);
			$passwordmessage = $lang->sprintf($lang->email_randompassword, $user_info['username'], $mybb->settings['bbname'], $user_info['username'], $mybb->get_input('password'));
			break;
	}
	my_mail($user_info['email'], $emailsubject, $emailmessage);
	my_mail($user_info['email'], $passwordsubject, $passwordmessage);
}

function advinvitesystem_randompass_register($user_info)
{
	global $mybb, $lang;
	
	$lang->load('messages');
	$emailsubject = $lang->sprintf($lang->emailsubject_randompassword, $mybb->settings['bbname']);
	switch($mybb->settings['username_method'])
	{
		case 0:
			$emailmessage = $lang->sprintf($lang->email_randompassword, $user_info['username'], $mybb->settings['bbname'], $user_info['username'], $mybb->get_input('password'));
			break;
		case 1:
			$emailmessage = $lang->sprintf($lang->email_randompassword1, $user_info['username'], $mybb->settings['bbname'], $user_info['username'], $mybb->get_input('password'));
			break;
		case 2:
			$emailmessage = $lang->sprintf($lang->email_randompassword2, $user_info['username'], $mybb->settings['bbname'], $user_info['username'], $mybb->get_input('password'));
			break;
		default:
			$emailmessage = $lang->sprintf($lang->email_randompassword, $user_info['username'], $mybb->settings['bbname'], $user_info['username'], $mybb->get_input('password'));
			break;
	}
	
	my_mail($user_info['email'], $emailsubject, $emailmessage);
}

function advinvitesystem_admin_register($user_info)
{
	global $db, $mybb, $lang, $cache;
	
	$lang->load("member");
	$lang->load("messages");
	
	$groups = $cache->read("usergroups");
	$admingroups = array();
	if(!empty($groups)) // Shouldn't be...
	{
		foreach($groups as $group)
		{
			if($group['cancp'] == 1)
			{
				$admingroups[] = (int)$group['gid'];
			}
		}
	}
	if(!empty($admingroups))
	{
		$sqlwhere = 'usergroup IN ('.implode(',', $admingroups).')';
		foreach($admingroups as $admingroup)
		{
			switch($db->type)
			{
				case 'pgsql':
				case 'sqlite':
					$sqlwhere .= " OR ','||additionalgroups||',' LIKE '%,{$admingroup},%'";
					break;
					default:
					$sqlwhere .= " OR CONCAT(',',additionalgroups,',') LIKE '%,{$admingroup},%'";
					break;
			}
		}
		$q = $db->simple_select('users', 'uid,username,email,language', $sqlwhere);
		while($recipient = $db->fetch_array($q))
		{
			// First we check if the user's a super admin: if yes, we don't care about permissions
			$is_super_admin = is_super_admin($recipient['uid']);
			if(!$is_super_admin)
			{
				// Include admin functions
				if(!file_exists(MYBB_ROOT.$mybb->config['admin_dir']."/inc/functions.php"))
				{
					continue;
				}
				require_once MYBB_ROOT.$mybb->config['admin_dir']."/inc/functions.php";
				// Verify if we have permissions to access user-users
				require_once MYBB_ROOT.$mybb->config['admin_dir']."/modules/user/module_meta.php";
				if(function_exists("user_admin_permissions"))
				{
					// Get admin permissions
					$adminperms = get_admin_permissions($recipient['uid']);
					$permissions = user_admin_permissions();
					if(array_key_exists('users', $permissions['permissions']) && $adminperms['user']['users'] != 1)
					{
						continue; // No permissions
					}
				}
			}
			// Load language
			if($recipient['language'] != $lang->language && $lang->language_exists($recipient['language']))
			{
				$reset_lang = true;
				$lang->set_language($recipient['language']);
				$lang->load("member");
			}
							
			$passwordsubject = $lang->sprintf($lang->emailsubject_randompassword, $mybb->settings['bbname']);
			switch($mybb->settings['username_method'])
			{
				case 0:
					$passwordmessage = $lang->sprintf($lang->email_randompassword, $user_info['username'], $mybb->settings['bbname'], $user_info['username'], $mybb->get_input('password'));
					break;
				case 1:
					$passwordmessage = $lang->sprintf($lang->email_randompassword1, $user_info['username'], $mybb->settings['bbname'], $user_info['username'], $mybb->get_input('password'));
					break;
				case 2:
					$passwordmessage = $lang->sprintf($lang->email_randompassword2, $user_info['username'], $mybb->settings['bbname'], $user_info['username'], $mybb->get_input('password'));
					break;
				default:
					$passwordmessage = $lang->sprintf($lang->email_randompassword, $user_info['username'], $mybb->settings['bbname'], $user_info['username'], $mybb->get_input('password'));
					break;
			}
			my_mail($user_info['email'], $passwordsubject, $passwordmessage);	
			$subject = $lang->sprintf($lang->newregistration_subject, $mybb->settings['bbname']);
			$message = $lang->sprintf($lang->newregistration_message, $recipient['username'], $mybb->settings['bbname'], $user_info['username']);
			my_mail($recipient['email'], $subject, $message);
			}
	}
	// Reset language
	if(isset($reset_lang))
	{
		$lang->set_language($mybb->settings['bblanguage']);
		$lang->load("member");
	}	
}

function advinvitesystem_verify_register($user_info)
{
	global $db, $mybb, $lang;
	
	$lang->load('messages');
	
	$activationcode = random_str();
	$now = TIME_NOW;
	$activationarray = array(
		"uid" => $user_info['uid'],
		"dateline" => TIME_NOW,
		"code" => $activationcode,
		"type" => "r"
	);
	$db->insert_query("awaitingactivation", $activationarray);
	$emailsubject = $lang->sprintf($lang->emailsubject_activateaccount, $mybb->settings['bbname']);
	$passwordsubject = $lang->sprintf($lang->emailsubject_randompassword, $mybb->settings['bbname']);
	switch($mybb->settings['username_method'])
	{
		case 0:
			$emailmessage = $lang->sprintf($lang->email_activateaccount, $user_info['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $user_info['uid'], $activationcode, $mybb->get_input('password'));
			$passwordmessage = $lang->sprintf($lang->email_randompassword, $user_info['username'], $mybb->settings['bbname'], $user_info['username'], $mybb->get_input('password'));
			break;
		case 1:
			$emailmessage = $lang->sprintf($lang->email_activateaccount1, $user_info['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $user_info['uid'], $activationcode, $mybb->get_input('password'));
			$passwordmessage = $lang->sprintf($lang->email_randompassword1, $user_info['username'], $mybb->settings['bbname'], $user_info['username'], $mybb->get_input('password'));
			break;
		case 2:
			$emailmessage = $lang->sprintf($lang->email_activateaccount2, $user_info['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $user_info['uid'], $activationcode, $mybb->get_input('password'));
			$passwordmessage = $lang->sprintf($lang->email_randompassword2, $user_info['username'], $mybb->settings['bbname'], $user_info['username'], $mybb->get_input('password'));
			break;
			default:
			$emailmessage = $lang->sprintf($lang->email_activateaccount, $user_info['username'], $mybb->settings['bbname'], $mybb->settings['bburl'], $user_info['uid'], $activationcode, $mybb->get_input('password'));
			$passwordmessage = $lang->sprintf($lang->email_randompassword, $user_info['username'], $mybb->settings['bbname'], $user_info['username'], $mybb->get_input('password'));
			break;
	}
	my_mail($user_info['email'], $emailsubject, $emailmessage);
	my_mail($user_info['email'], $passwordsubject, $passwordmessage);

}

function advinvitesystem_datahandler($usergroup)
{
	global $mybb, $errors, $userhandler;

		// Datahandler
			require_once MYBB_ROOT."inc/datahandlers/user.php";
			$userhandler = new UserDataHandler("insert");
			
			// Set the data for the new user.
			$user = array(
				"username" => $mybb->get_input('username'),
				"password" => $mybb->get_input('password'),
				"password2" => $mybb->get_input('password2'),
				"email" => $mybb->get_input('email'),
				"email2" => $mybb->get_input('email2'),
				"usergroup" => $usergroup,
				"timezone" => 0,
				"language" => '',
				"regip" => '',
				"coppa_user" => 0,
			);
			
			$user['options'] = array(
				"allownotices" => 1,
				"hideemail" => 0,
				"subscriptionmethod" => 0,
				"receivepms" => 1,
				"pmnotice" => 1,
				"pmnotify" => 0,
				"invisible" => 0,
				"dstcorrection" => 2
			);
			
			$userhandler->set_data($user);
			if(!$userhandler->validate_user())
			{
				$errors = $userhandler->get_friendly_errors();
			}
			
			if($mybb->settings['enablestopforumspam_on_register'])
			{
				require_once MYBB_ROOT . '/inc/class_stopforumspamchecker.php';
				$stop_forum_spam_checker = new StopForumSpamChecker(
					$plugins,
					$mybb->settings['stopforumspam_min_weighting_before_spam'],
					$mybb->settings['stopforumspam_check_usernames'],
					$mybb->settings['stopforumspam_check_emails'],
					$mybb->settings['stopforumspam_check_ips'],
					$mybb->settings['stopforumspam_log_blocks']
				);
				try {
					if($stop_forum_spam_checker->is_user_a_spammer($user['username'], $user['email'], get_ip()))
					{
						error($lang->sprintf($lang->error_stop_forum_spam_spammer,
								$stop_forum_spam_checker->getErrorText(array(
									'stopforumspam_check_usernames',
									'stopforumspam_check_emails',
									'stopforumspam_check_ips'
									))));
					}
				}
				catch (Exception $e)
				{
					if($mybb->settings['stopforumspam_block_on_error'])
					{
						error($lang->error_stop_forum_spam_fetching);
					}
				}
			}
			
			return $user;
}