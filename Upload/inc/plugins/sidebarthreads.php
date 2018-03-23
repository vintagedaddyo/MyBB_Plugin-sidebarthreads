<?php
/*
 * MyBB: Recent Threads Forum Sidebar
 *
 * File: sidebarthreads.php
 * 
 * Authors: borbole & Vintagedaddyo
 *
 * MyBB Version: 1.8
 *
 * Plugin Version: 1.1
 * 
 */

//Trying to access directly the file, are we :D

if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

//Hooking into index_start with our function

$plugins->add_hook("index_end", "sidebarthreads");

//Show some info about our mod

function sidebarthreads_info()
{
    global $lang;

    $lang->load("sidebarthreads");
    
    $lang->sidebarthreads_Desc = '<form action="https://www.paypal.com/cgi-bin/webscr" method="post" style="float:right;">' .
        '<input type="hidden" name="cmd" value="_s-xclick">' . 
        '<input type="hidden" name="hosted_button_id" value="AZE6ZNZPBPVUL">' .
        '<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">' .
        '<img alt="" border="0" src="https://www.paypalobjects.com/pl_PL/i/scr/pixel.gif" width="1" height="1">' .
        '</form>' . $lang->sidebarthreads_Desc;

    return Array(
        'name' => $lang->sidebarthreads_Name,
        'description' => $lang->sidebarthreads_Desc,
        'website' => $lang->sidebarthreads_Web,
        'author' => $lang->sidebarthreads_Auth,
        'authorsite' => $lang->sidebarthreads_AuthSite,
        'version' => $lang->sidebarthreads_Ver,
        'codename' => $lang->sidebarthreads_CodeName,
        'compatibility' => $lang->sidebarthreads_Compat
    );
}

//Activate it

function sidebarthreads_activate()
{
	global $db, $lang;

    $lang->load("sidebarthreads");

	//Insert the mod settings in the portal settinggroup

	$query = $db->simple_select("settinggroups", "gid", "name='portal'");
	$gid = $db->fetch_field($query, "gid");

	$setting = array(
		'name' => 'sidebar_enable',
		'title' => $lang->sidebarthreads_option_1_Title,
		'description' => $lang->sidebarthreads_option_1_Description,
		'optionscode' => 'yesno',
		'value' => '1',
		'disporder' => '90',
		'gid' => intval($gid)
	);
	$db->insert_query('settings',$setting);

    $setting = array(
        "sid" => "0",
        "name" => "sidebar_position",
        "title" => $lang->sidebarthreads_option_2_Title,
        "description" => $lang->sidebarthreads_option_2_Description,
        "optionscode" => 'select
         1= '.$lang->sidebarthreads_option_2_Select_1.'
         2= '.$lang->sidebarthreads_option_2_Select_2.'',
        "value" => "2",
        "disporder" => "91",
        "gid" => intval($gid),
        );
    $db->insert_query("settings", $setting);


	$template = array(
		"tid"		=> "0",
		"title"		=> 'thread_sidebar',
		"template"	=> '{$showrecentthreads}',
		"sid"		=> "-1",
		"version"	=> "1.1",
		"dateline"	=> time(),
	);

	$db->insert_query("templates", $template);

    rebuild_settings();
}

//Don't want to use it anymore? Let 's deactivate it then and drop the settings and the custom var as well

function sidebarthreads_deactivate()
{
	global $db, $mybb;

	$db->query("DELETE FROM ".TABLE_PREFIX."settings WHERE name='sidebar_enable'");	
    $db->query("DELETE FROM ".TABLE_PREFIX."settings WHERE name='sidebar_position'");
	
	$db->delete_query("templates","title = 'thread_sidebar'");

    rebuild_settings();
}


//Insert our function 

function sidebarthreads()
{
   global $db, $mybb, $forums, $templates;

   //Get the recent threads settings from the portal

   if($mybb->settings['portal_showdiscussions'] != 0 && $mybb->settings['portal_showdiscussionsnum'])
   {
       $showrecentthreads = showrecentthreads();
   }
    //Show the recent threads on the sidebar on board index

   if($mybb->settings['sidebar_enable'] == 1)
   {
      
	  //Get the template

	  eval("\$sidebarposition = \"".$templates->get("thread_sidebar")."\";");

	  //Display it on the right side

	  if ($mybb->settings['sidebar_position'] == "2") 
	  {
          $forums ='<table border="0" width="100%">
          <tr>
              <td width="200" valign="top">'. $sidebarposition. '</td>
              <td valign="top">'. $forums. '</td>
         </tr>
         </table>';
	  }
	  
	   //Display it on the left side

	 if ($mybb->settings['sidebar_position'] == "1") 
	 {
        $forums ='<table border="0" width="100%">
        <tr>
            <td valign="top">'. $forums. '</td>
            <td width="200" valign="top">'. $sidebarposition. '</td>
        </tr>
       </table>';
	 }
  }
}


//Get the recent threads from the portal to show them on the sidebar in forum index

function showrecentthreads()
{
    global $db, $mybb, $templates, $latestthreads, $lang, $theme, $forum_cache;
	
	// Load global language phrases

//    $lang->load("portal");

    $lang->load("sidebarthreads");

	// Latest forum discussions

	$altbg = alt_trow();
	$threadlist = '';
	$query = $db->query("
		SELECT t.*, u.username
		FROM ".TABLE_PREFIX."threads t
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=t.uid)
		WHERE 1=1 $unviewwhere AND t.visible='1' AND t.closed NOT LIKE 'moved|%'
		ORDER BY t.lastpost DESC 
		LIMIT 0, ".$mybb->settings['portal_showdiscussionsnum']
	);
	while($thread = $db->fetch_array($query))
	{
		$forumpermissions[$thread['fid']] = forum_permissions($thread['fid']);

		// Make sure we can view this thread

		if($forumpermissions[$thread['fid']]['canview'] == 0 || $forumpermissions[$thread['fid']]['canviewthreads'] == 0 || $forumpermissions[$thread['fid']]['canonlyviewownthreads'] == 1 && $thread['uid'] != $mybb->user['uid'])
		{
			continue;
		}

	//	$lastpostdate = my_date($mybb->settings['dateformat'], $thread['lastpost']);
	//	$lastposttime = my_date($mybb->settings['timeformat'], $thread['lastpost']);
        
        // Changed to fix where the time was not displaying with the date

		$lastpostdate = my_date('relative', $thread['lastpost']);

		// Don't link to guest's profiles (they have no profile).

		if($thread['lastposteruid'] == 0)
		{
			$lastposterlink = $thread['lastposter'];
		}
		else
		{
			$lastposterlink = build_profile_link($thread['lastposter'], $thread['lastposteruid']);
		}
		if(my_strlen($thread['subject']) > 25)
		{
			$thread['subject'] = my_substr($thread['subject'], 0, 25) . "...";
		}
		$thread['subject'] = htmlspecialchars_uni($thread['subject']);
		$thread['threadlink'] = get_thread_link($thread['tid']);
		$thread['lastpostlink'] = get_thread_link($thread['tid'], 0, "lastpost");

        // Add Forum name and link that was missing
        
		$thread['forumlink'] = get_forum_link($thread['fid']);
		$thread['forumname'] = $forum_cache[$thread['fid']]['name'];
		
		eval("\$threadlist .= \"".$templates->get("portal_latestthreads_thread")."\";");
		$altbg = alt_trow();
	}
	if($threadlist)
	{ 
		// Show the table only if there are threads

		eval("\$latestthreads = \"".$templates->get("portal_latestthreads")."\";");
	}

	return $latestthreads;
}

?>