<?php
/**
 * [WeEngine System] Copyright (c) 2014 W7.CC
 * $sn: pro/web/source/platform/service.ctrl.php : v 7e4ce85c5d2f : 2015/07/15 03:25:22 : yanghf $.
 */
defined('IN_IA') or exit('Access Denied');
$_W['page']['title'] = '常用接入服务 - 常用接入服务 - 高级功能';
permission_check_account_user('platform_reply');
load()->model('module');
load()->model('reply');
$m = module_fetch('userapi');
$cfg = $m['config'];
$ds = reply_search("`uniacid` = 0 AND module = 'userapi' AND `status`=1");
$apis = array();
foreach ($ds as $row) {
	$apis[$row['id']] = $row;
}

if ($_W['ispost'] && $_W['isajax']) {
	$rids = explode(',', safe_gpc_string($_GPC['rids']));
	if (is_array($rids)) {
		$cfg = array();
		foreach ($rids as $rid) {
			if (!empty($apis[$rid])) {
				$cfg[intval($rid)] = true;
			}
		}
		$module = WeUtility::createModule('userapi');
		$module->saveSettings($cfg);
	}
	exit();
}
$ds = array();
foreach ($apis as $row) {
	$reply = pdo_fetch('SELECT * FROM ' . tablename('userapi_reply') . ' WHERE `rid`=:rid', array(':rid' => $row['id']));
	$r = array();
	$r['title'] = $row['name'];
	$r['rid'] = $row['id'];
	$r['description'] = $reply['description'];
	$r['switch'] = $cfg[$r['rid']] ? ' checked="checked"' : '';
	$ds[] = $r;
}

template('platform/service');
