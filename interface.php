<?php

/** 
 * Custom interface 
 *
 * Provide 
 *
 * array $_IMC
 * boolean $im_is_admin
 * boolean $im_is_login
 * object $imuser require when $im_is_login
 * function webim_get_buddies()
 * function webim_get_online_buddies()
 * function webim_get_rooms()
 * function webim_get_notifications()
 * function webim_login()
 *
 */

//discuzX1.5 will check url and report error when url content quote
$_SERVER['REQUEST_URI'] = "";
require_once '../../class/class_core.php';
require_once '../../function/function_friend.php';
require_once '../../function/function_group.php';

$discuz = & discuz_core::instance();
$discuz->init();

//Find and insert data with utf8 client.
DB::query( "SET NAMES utf8" );

require 'config.php';

/**
 *
 * Provide the webim database config.
 *
 * $_IMC['dbuser'] MySQL database user
 * $_IMC['dbpassword'] MySQL database password
 * $_IMC['dbname'] MySQL database name
 * $_IMC['dbhost'] MySQL database host
 * $_IMC['dbtable_prefix'] MySQL database table prefix
 * $_IMC['dbcharset'] MySQL database charset
 *
 */

$_dbconfig = $_G['config']['db'][1];
$_IMC['dbuser'] = $_dbconfig['dbuser'];
$_IMC['dbpassword'] = $_dbconfig['dbpw'];
$_IMC['dbname'] = $_dbconfig['dbname'];
$_IMC['dbhost'] = $_dbconfig['dbhost'];
$_IMC['dbtable_prefix'] = $_dbconfig['tablepre'];
unset( $_dbconfig );

/**
 * Init im user.
 * 	-uid:
 * 	-id:
 * 	-nick:
 * 	-pic_url:
 * 	-show:
 *
 */

//if( !defined('IN_DISCUZ') || !$_G['uid'] ) {
//	exit('"Access Denied"');
//}

if ( $_G['uid'] ) {
	$im_is_login = true;
	$imuser->uid = $_G['uid'];
	$imuser->id = to_utf8( $_G['username'] );
	$imuser->nick = to_utf8( $_G['username'] );
	if( $_IMC['show_realname'] ) {
		$data = DB::fetch_first("SELECT realname FROM ".DB::table('common_member_profile')." WHERE uid = $imuser->uid");
		if( $data && $data['realname'] )
			$imuser->nick = $data['realname'];
	}

	$imuser->pic_url = avatar($imuser->uid, 'small', true);
	$imuser->show = webim_gp('show') ? webim_gp('show') : "available";
	$imuser->url = "home.php?mod=space&uid=".$imuser->uid;
	complete_status( array( $imuser ) );
} else {
	$im_is_login = false;
}

function webim_login( $user, $password ) {
}


//Cache friend_groups;
$friend_groups = friend_group_list();
foreach($friend_groups as $k => $v){
	$friend_groups[$k] = to_utf8($v);
}


/**
 * Online buddy list.
 *
 */
function webim_get_online_buddies(){
	global $friend_groups, $imuser;
	$list = array();
	$query = DB::query("SELECT f.fuid uid, f.fusername username, p.realname name, f.gid 
		FROM ".DB::table('home_friend')." f, ".DB::table('common_session')." s, ".DB::table('common_member_profile')." p
		WHERE f.uid='$imuser->uid' AND f.fuid = s.uid AND p.uid = s.uid 
		ORDER BY f.num DESC, f.dateline DESC");
	while ($value = DB::fetch($query)){
		$list[] = (object)array(
			"uid" => $value['uid'],
			"id" => $value['username'],
			"nick" => nick($value),
			"group" => $friend_groups[$value['gid']],
			"url" => "home.php?mod=space&uid=".$value['uid'],
			"pic_url" => avatar($value['uid'], 'small', true),
		);
	}
	complete_status( $list );
	return $list;
}

/**
 * Get buddy list from given ids
 * $ids:
 *
 * Example:
 * 	buddy('admin,webim,test');
 *
 */

function webim_get_buddies( $names, $uids = null ){
	global $friend_groups, $imuser;
	$where_name = "";
	$where_uid = "";
	if(!$names and !$uids)return array();
	if($names){
		$names = "'".implode("','", explode(",", $names))."'";
		$where_name = "m.username IN ($names)";
	}
	if($uids){
		$where_uid = "m.uid IN ($uids)";
	}
	$where_sql = $where_name && $where_uid ? "($where_name OR $where_uid)" : ($where_name ? $where_name : $where_uid);

	$list = array();
	$query = DB::query("SELECT m.uid, m.username, p.realname name, f.gid FROM ".DB::table('common_member')." m
		LEFT JOIN ".DB::table('home_friend')." f 
		ON f.fuid = m.uid AND f.uid = $imuser->uid 
		LEFT JOIN ".DB::table('common_member_profile')." p
		ON m.uid = p.uid 
		WHERE m.uid <> $imuser->uid AND $where_sql");
	while ( $value = DB::fetch( $query ) ){
		$list[] = (object)array(
			"uid" => $value['uid'],
			"id" => $value['username'],
			"nick" => nick($value),
			"group" => $value['gid'] ? $friend_groups[$value['gid']] : "stranger",
			"url" => "home.php?mod=space&uid=".$value['uid'],
			"pic_url" => avatar($value['uid'], 'small', true),
		);
	}
	complete_status( $list );
	return $list;
}

/**
 * Get room list
 * $ids: Get all imuser rooms if not given.
 *
 */

function webim_get_rooms($ids=null){
	global $imuser;
	if(!$ids){
		$ids = DB::result_first("SELECT fid FROM ".DB::table("forum_groupuser")." WHERE uid=$imuser->uid");
	}
	$list = array();
	if(!$ids){
		return $list;
	}
	$where = "f.fid IN ($ids)";
	$query = DB::query("SELECT f.fid, f.name, ff.icon, ff.membernum, ff.description 
		FROM ".DB::table('forum_forum')." f 
		LEFT JOIN ".DB::table("forum_forumfield")." ff ON ff.fid=f.fid 
		WHERE f.type='sub' AND f.status=3 AND $where");

	while ($value = DB::fetch($query)){
		$list[] = (object)array(
			"fid" => $value['fid'],
			"id" => $value['fid'],
			"nick" => $value['name'],
			"url" => "forum.php?mod=group&fid=".$value['fid'],
			"pic_url" => get_groupimg($value['icon'], 'icon'),
			"status" => $value['description'],
			"count" => 0,
			"all_count" => $value['membernum'],
			"blocked" => false,
		);
	}
	return $list;
}

function webim_get_notifications(){
	return array();
}

/**
 * Add status to member info.
 *
 * @param array $members the member list
 * @return 
 *
 */
function complete_status( $members ) {
	if(!empty($members)){
		$num = count($members);
		$ids = array();
		$ob = array();
		for($i = 0; $i < $num; $i++){
			$m = $members[$i];
			$id = $m->uid;
			if ( $id ) {
				$ids[] = $id;
				$ob[$id] = $m;
			}
		}
		$ids = implode(",", $ids);
		$query = DB::query("SELECT uid, spacenote FROM ".DB::table('common_member_field_home')." WHERE uid IN ($ids)");
		while($res = DB::fetch($query)) {
			$ob[$res['uid']]->status = $res['spacenote'];
		}
	}
	return $members;
}

function nick( $sp ) {
	global $_IMC;
	return (!$_IMC['show_realname']||empty($sp['name'])) ? $sp['username'] : $sp['name'];
}

function to_utf8( $s ) {
	if( strtoupper( CHARSET ) == 'UTF-8' ) {
		return $s;
	} else {
		if ( function_exists( 'iconv' ) ) {
			return iconv( CHARSET, 'utf-8', $s );
		} else {
			require_once DISCUZ_ROOT . './source/class/class_chinese.php';
			$chs = new Chinese( CHARSET, 'utf-8' );
			return $chs->Convert( $s );
		}
	}
}

