<?php
/**
 * [WeEngine System] Copyright (c) 2014 W7.CC.
 */
defined('IN_IA') or exit('Access Denied');
load()->model('mc');

$dos = array('address', 'base_information', 'member_credits', 'credit_statistics', 'display', 'del', 'add', 'group', 'register_setting', 'credit_setting', 'save_credit_setting', 'save_tactics_setting', 'export');
$do = in_array($do, $dos) ? $do : 'display';

$creditnames = uni_setting_load('creditnames');
$creditnames = $creditnames['creditnames'];

if ('save_tactics_setting' == $do) {
	$setting = safe_gpc_array($_GPC['setting']);
	if (empty($setting)) {
		iajax(1, '不可为空！');
	}
	uni_setting_save('creditbehaviors', $setting);
	iajax(0, '设置成功！', referer());
}

if ('save_credit_setting' == $do) {
	$credit_setting = safe_gpc_array($_GPC['credit_setting']);
	if (empty($credit_setting)) {
		iajax(1, '不可为空');
	}
	uni_setting_save('creditnames', $credit_setting);
	iajax(0, '设置成功！', referer());
}

if ('register_setting' == $do) {
	permission_check_account_user('mc_member_register_seting');
	if (checksubmit('submit')) {
		$passport = safe_gpc_array($_GPC['passport']);
		if (!empty($passport)) {
			uni_setting_save('passport', $passport);
			itoast('设置成功', '', 'success');
		}
	}
	$setting = uni_setting_load('passport');
	$register_setting = !empty($setting['passport']) ? $setting['passport'] : array();
	template('mc/member');
}

if ('credit_setting' == $do) {
	permission_check_account_user('mc_member_credit_setting');
	$credit_setting = uni_setting_load('creditnames');
	$credit_setting = $credit_setting['creditnames'];

	$credit_tactics = uni_setting_load('creditbehaviors');
	$credit_tactics = empty($credit_tactics['creditbehaviors']) ? array() : $credit_tactics['creditbehaviors'];

	$enable_credit = array();
	if (!empty($credit_setting)) {
		foreach ($credit_setting as $key => $credit) {
			if (1 == $credit['enabled']) {
				$enable_credit[] = $key;
			}
		}
		unset($credit);
	}
	template('mc/member');
}

if ('display' == $do) {
	permission_check_account_user('mc_member_diaplsy');
	$groups = mc_groups();
	$search_mod = !empty($_GPC['search_mod']) && 1 == intval($_GPC['search_mod']) ? '1' : '2';
	$pindex = empty($_GPC['page']) ? 1 : intval($_GPC['page']);
	$psize = 25;

	$condition = '';
	$params = array(':uniacid' => $_W['uniacid']);
	$username = empty($_GPC['username']) ? '' : safe_gpc_string($_GPC['username']);
	if (!empty($username)) {
		if (1 == $search_mod) {
			$condition .= ' AND ((`uid` = :openid) OR (`realname` = :realname) OR (`nickname` = :nickname) OR (`mobile` = :mobile))';
			$params[':realname'] = $params[':nickname'] = $params[':mobile'] = $username;
			if (!is_numeric($username)) {
				$uid = table('mc_mapping_fans')
					->where(array('openid' => $username))
					->getcolumn('uid');
				$params[':openid'] = empty($uid) ? '' : $uid;
			} else {
				$params[':openid'] = $username;
			}
		} else {
			$condition .= ' AND ((`uid` = :openid) OR (`realname` LIKE :realname) OR (`nickname` LIKE :nickname) OR (`mobile` LIKE :mobile))';
			$params[':realname'] = $params[':nickname'] = $params[':mobile'] = '%' . $username . '%';
			if (!is_numeric($username)) {
				$uid = table('mc_mapping_fans')
					->where(array('openid' => $username))
					->getcolumn('uid');
				$params[':openid'] = empty($uid) ? '' : $uid;
			} else {
				$params[':openid'] = $username;
			}
		}
	}
	if (!empty($_GPC['datelimit'])) {
		$starttime = strtotime($_GPC['datelimit']['start']);
		if (!empty($starttime)) {
			$endtime = strtotime($_GPC['datelimit']['end']) + 86399;
			$condition .= ' AND createtime > :start AND createtime < :end';
			$params[':start'] = $starttime;
			$params[':end'] = $endtime;
		}
	}
	if (!empty($_GPC['groupid']) && intval($_GPC['groupid']) > 0) {
		$condition .= ' AND `groupid` = :groupid';
		$params[':groupid'] = intval($_GPC['groupid']);
	}
	if (checksubmit('export_submit', true)) {
		$account_member_fields = uni_account_member_fields($_W['uniacid']);
		$available_fields = array();
		foreach ($account_member_fields as $key => $val) {
			if ($val['available']) {
				$available_fields[$val['field']] = $val['title'];
			}
		}
		foreach ($creditnames as $key => $info) {
			if (1 == $info['enabled']) {
				$available_fields[$key] = empty($info['title']) ? $key : $info['title'];
			}
		}

		$keys = trim(implode(',', array_map(function ($item) {
			return safe_gpc_string($item, '', 'alpha_dash');
		}, array_keys($available_fields))), ',');
		$sql = 'SELECT ' . $keys . ' FROM ' . tablename('mc_members') . ' WHERE uniacid = :uniacid ' . $condition;

		$members = pdo_fetchall($sql, $params);
		$html = mc_member_export_parse($members, $available_fields);
		header('Content-type:text/csv');
		header('Content-Disposition:attachment; filename=会员数据.csv');
		echo $html;
		exit();
	}
	$sql = 'SELECT uid, uniacid, groupid, realname, nickname, email, mobile, credit1, credit2, credit6, createtime  FROM ' . tablename('mc_members') . ' WHERE uniacid = :uniacid ' . $condition . ' ORDER BY `uid` DESC LIMIT ' . ($pindex - 1) * $psize . ',' . $psize;
	$member_list = pdo_fetchall($sql, $params);
	if (!empty($member_list)) {
		foreach ($member_list as &$li) {
			if (empty($li['email']) || (!empty($li['email']) && 'we7.cc' == substr($li['email'], -6) && 39 == strlen($li['email']))) {
				$li['email_effective'] = 0;
			} else {
				$li['email_effective'] = 1;
			}
		}
	}
	$total = pdo_fetchcolumn('SELECT COUNT(*) FROM ' . tablename('mc_members') . ' WHERE uniacid = :uniacid ' . $condition, $params);
	$pager = pagination($total, $pindex, $psize);
	$stat['total'] = table('mc_members')
		->searchWithUniacid($_W['uniacid'])
		->getcolumn('COUNT(*)');
	$stat['today'] = table('mc_members')
		->where(array(
			'uniacid' => $_W['uniacid'],
			'createtime >=' => strtotime('today'),
			'createtime <=' => strtotime('today') + 86399
		))
		->getcolumn('COUNT(*)');
	$stat['yesterday'] = table('mc_members')
		->where(array(
			'uniacid' => $_W['uniacid'],
			'createtime >=' => strtotime('today') - 86399,
			'createtime <=' => strtotime('today')
		))
		->getcolumn('COUNT(*)');

	template('mc/member');
}

if ('del' == $do) {
	if (!empty($_GPC['uid'])) {
		if (is_array($_GPC['uid'])) {
			$delete_uids = array();
			foreach ($_GPC['uid'] as $uid) {
				$uid = intval($uid);
				if (!empty($uid)) {
					$delete_uids[] = $uid;
				}
			}
		} else {
			$delete_uids = intval($_GPC['uid']);
		}
		if (!empty($delete_uids)) {
			$tables = array('mc_members', 'mc_cash_record', 'mc_credits_recharge', 'mc_credits_record', 'mc_member_address');
			foreach ($tables as $key => $value) {
				table($value)->where(array('uniacid' => $_W['uniacid'], 'uid' => $delete_uids))->delete();
			}
			table('mc_mapping_fans')
				->where(array(
					'uid' => $delete_uids,
					'uniacid' => $_W['uniacid']
				))
				->fill(array('uid' => 0))
				->save();
			itoast('删除成功！', referer(), 'success');
		}
		itoast('请选择要删除的项目！', referer(), 'error');
	}
}

if ('add' == $do) {
	if ($_W['isajax']) {
		$type = safe_gpc_string($_GPC['type']);
		$data = trim($_GPC['data']);
		if ('email' == $type) {
			$data = !preg_match(REGULAR_EMAIL, $data) ? '' : $data;
		} elseif ('mobile' == $type) {
			$data = !preg_match(REGULAR_MOBILE, $data) ? '' : $data;
		} else {
			$data = '';
		}
		if (empty($data) || empty($type)) {
			exit(json_encode(array('valid' => false)));
		}
		$user = table('mc_members')
			->where(array(
				'uniacid' => $_W['uniacid'],
				$type => $data
			))
			->get();
		if (empty($user)) {
			exit(json_encode(array('valid' => true)));
		} else {
			exit(json_encode(array('valid' => false)));
		}
	}
	if (checksubmit('form')) {
		$realname = safe_gpc_string($_GPC['realname']) ? safe_gpc_string($_GPC['realname']) : itoast('姓名不能为空', '', '');
		$mobile = safe_gpc_string($_GPC['mobile']) ? safe_gpc_string($_GPC['mobile']) : itoast('手机不能为空', '', '');
		$email = trim($_GPC['email']);
		if (!empty($mobile)) {
			if (!preg_match(REGULAR_MOBILE, $mobile)) {
				itoast('手机号格式不正确！', '', '');
			}
			$user = table('mc_members')
				->where(array(
					'uniacid' => $_W['uniacid'],
					'mobile' => $mobile
				))
				->get();
			if (!empty($user)) {
				itoast('手机号被占用', '', '');
			}
		}
		if (!empty($email)) {
			if (!preg_match(REGULAR_EMAIL, $email)) {
				itoast('邮箱格式不正确！', '', '');
			}
			$user = table('mc_members')
				->where(array(
					'uniacid' => $_W['uniacid'],
					'email' => $email
				))
				->get();
			if (!empty($user)) {
				itoast('邮箱被占用', '', '');
			}
		}
		$salt = random(8);
		$data = array(
			'uniacid' => $_W['uniacid'],
			'realname' => $realname,
			'mobile' => $mobile,
			'email' => $email,
			'salt' => $salt,
			'password' => md5(safe_gpc_string($_GPC['password']) . $salt . $_W['config']['setting']['authkey']),
			'credit1' => intval($_GPC['credit1']),
			'credit2' => intval($_GPC['credit2']),
			'groupid' => intval($_GPC['groupid']),
			'createtime' => TIMESTAMP,
		);
		table('mc_members')->fill($data)->save();
		$uid = pdo_insertid();
		itoast('添加会员成功,将进入编辑页面', url('mc/member/post', array('uid' => $uid, 'version_id' => intval($_GPC['version_id']))), 'success');
	}
	template('mc/member-add');
}

if ('group' == $do) {
	if ($_W['isajax']) {
		$id = intval($_GPC['id']);
		$group = $_W['account']['groups'][$id];
		if (empty($group)) {
			exit('会员组信息不存在');
		}
		$uid = intval($_GPC['uid']);
		$member = mc_fetch($uid);
		if (empty($member)) {
			exit('会员信息不存在');
		}
		$credit = intval($group['credit']);
		$credit6 = $credit - $member['credit1'];
		$status_update_groupid = mc_update($uid, array('groupid' => $id));
		$status_update_credit6 = mc_credit_update($uid, 'credit6', $credit6);
		if ($status_update_groupid && !is_error($status_update_credit6)) {
			$openid = table('mc_mapping_fans')
				->where(array('uniacid' => $_W['uniacid'], 'uid' => $uid))
				->getcolumn('openid');
			if (!empty($openid)) {
				mc_notice_group($openid, $_W['account']['groups'][$member['groupid']]['title'], $_W['account']['groups'][$id]['title']);
			}
			exit('success');
		} else {
			exit('更新会员信息出错');
		}
	}
	exit('error');
}

if ('credit_statistics' == $do) {
	$uid = intval($_GPC['uid']);
	$credits = array(
		'credit1' => $creditnames['credit1']['title'],
		'credit2' => $creditnames['credit2']['title'],
	);
	if (!empty($_GPC['datelimit'])) {
		$starttime = strtotime($_GPC['datelimit']['start']);
		$endtime = strtotime($_GPC['datelimit']['end']) + 86399;
		$time_where = array(
			'createtime >' => $starttime,
			'createtime <' => $endtime,
		);
	}
	if (!empty($credits)) {
		$data = array();
		foreach ($credits as $key => $li) {
			$mc_credits_record_add = table('mc_credits_record')
				->where(array(
					'uniacid' => $_W['uniacid'],
					'uid' => $uid,
					'credittype' => $key,
					'num >' => 0,
				));

			$mc_credits_record_del = table('mc_credits_record')
				->where(array(
					'uniacid' => $_W['uniacid'],
					'uid' => $uid,
					'credittype' => $key,
					'num <' => 0
				));
			if (!empty($time_where)) {
				$mc_credits_record_add->where($time_where);
				$mc_credits_record_del->where($time_where);
			}
			$data[$key]['add'] = round($mc_credits_record_add
				->getcolumn('SUM(num)'), 2);
			$data[$key]['del'] = abs(round($mc_credits_record_del->getcolumn('SUM(num)'), 2));
			$data[$key]['end'] = $data[$key]['add'] - $data[$key]['del'];
		}
	}

	template('mc/member-information');
}

if ('member_credits' == $do) {
	$uid = intval($_GPC['uid']);
	$credits = mc_credit_fetch($uid, array('credit1', 'credit2'));
	//积分或余额记录
	$type = !empty($_GPC['type']) ? safe_gpc_string($_GPC['type']) : 'credit1';
	$pindex = empty($_GPC['page']) ? 1 : intval($_GPC['page']);
	$psize = 50;
	$mc_credits_record = table('mc_credits_record');
	$mc_credits_record->searchWithUniacid($_W['uniacid']);
	$mc_credits_record->searchWithPage($pindex, $psize);
	$records = $mc_credits_record->getCreditsRecordListByUidAndCredittype($uid, $type);
	$total = $mc_credits_record->getLastQueryTotal();

	$pager = pagination($total, $pindex, $psize);
	template('mc/member-information');
}

if ('export' == $do) {
	$uid = intval($_GPC['uid']);
	$type = safe_gpc_string($_GPC['type']) ? safe_gpc_string($_GPC['type']) : 'credit1';
	if (empty($uid) || empty($_W['uniacid'])) {
		iajax('-1', '参数错误，请刷新后重试！');
	}
	//导出数据
	$available_fields = [
		'credittype' => '账户类型',
		'username' => '操作员',
		'num' => '积分增减',
		'module' => '模块',
		'createtime' => '操作时间',
		'remark' => '备注',
	];
	$params = [
		':uniacid' => $_W['uniacid'],
		':uid' => $uid,
	];
	$condition = '';
	if (!empty($type)) {
		$condition .= 'AND r.credittype = :credittype ';
		$params[':credittype'] = $type;
	}
	$sql = 'SELECT r.credittype, u.username, r.num, r.module, r.createtime, r.remark FROM ' . tablename('mc_credits_record') . ' r LEFT JOIN ' . tablename('users') . ' u ON r.operator = u.uid  WHERE r.uniacid = :uniacid AND r.uid = :uid ' . $condition . 'ORDER BY r.id desc';
	$credittype_data = pdo_fetchall($sql, $params);
	$html = mc_member_export_parse($credittype_data, $available_fields, 'creditinfo');
	header('Content-type:text/csv');
	header('Content-Disposition:attachment; filename=会员账户数据.csv');
	echo $html;
	exit();
}

if ('base_information' == $do) {
	$uid = intval($_GPC['uid']);
	$profile = mc_fetch_one($uid, $_W['uniacid']);
	$profile = mc_parse_profile($profile);
	$mc_member_fields = table('mc_member_fields');
	$mc_member_fields->searchWithUniacid($_W['uniacid']);
	$mc_member_fields->selectFields(array('a.title', 'a.fieldid', 'a.available', 'b.field'));
	$uniacid_fields = $mc_member_fields->getAllFields();
	$all_fields = mc_fields();
	$custom_fields = array();
	$base_fields = cache_load(cache_system_key('userbasefields'));
	$base_fields = array_keys($base_fields);
	foreach ($all_fields as $field => $title) {
		if (!in_array($field, $base_fields) && !empty($uniacid_fields[$field]['available'])) {
			$custom_fields[] = $field;
		}
	}
	$groups = mc_groups($_W['uniacid']);
	$addresses = table('mc_member_address')
		->where(array(
			'uid' => $uid,
			'uniacid' => $_W['uniacid']
		))
		->getall();
	if ($_W['ispost'] && $_W['isajax']) {
		if (!empty($_GPC['type'])) {
			$type = safe_gpc_string($_GPC['type']);
		} else {
			iajax(-1, '参数错误！', '');
		}
		switch ($type) {
			case 'avatar':
				$data = array('avatar' => safe_gpc_url($_GPC['imgsrc']));
				break;
			case 'groupid':
			case 'gender':
			case 'education':
			case 'constellation':
			case 'zodiac':
			case 'bloodtype':
				$data = array($type => safe_gpc_string($_GPC['request_data']));
				break;
			case 'nickname':
			case 'realname':
			case 'address':
			case 'qq':
			case 'mobile':
			case 'email':
			case 'telephone':
			case 'msn':
			case 'taobao':
			case 'alipay':
			case 'graduateschool':
			case 'grade':
			case 'studentid':
			case 'revenue':
			case 'position':
			case 'occupation':
			case 'company':
			case 'nationality':
			case 'height':
			case 'weight':
			case 'idcard':
			case 'zipcode':
			case 'site':
			case 'affectivestatus':
			case 'lookingfor':
			case 'bio':
			case 'interest':
				$data = array($type => safe_gpc_string($_GPC['request_data']));
				break;
			case 'births':
				$data = array(
					'birthyear' => safe_gpc_string($_GPC['birthyear']),
					'birthmonth' => safe_gpc_string($_GPC['birthmonth']),
					'birthday' => safe_gpc_string($_GPC['birthday']),
				);
				break;
			case 'resides':
				$data = array(
					'resideprovince' => safe_gpc_string($_GPC['resideprovince']),
					'residecity' => safe_gpc_string($_GPC['residecity']),
					'residedist' => safe_gpc_string($_GPC['residedist']),
				);
				break;
			case 'password':
				$password = safe_check_password($_GPC['password']);
				if (is_error($password)) {
					iajax(-1, $password['mesage']);
				}
				$user = table('mc_members')
					->select(array('uid', 'salt'))
					->where(array(
						'uniacid' => $_W['uniacid'],
						'uid' => $uid
					))
					->get();
				$data = array();
				if (!empty($user) && $user['uid'] == $uid) {
					if (empty($user['salt'])) {
						$user['salt'] = $salt = random(8);
						table('mc_members')
							->where(array(
								'uid' => $uid,
								'uniacid' => $_W['uniacid']
							))
							->fill(array('salt' => $salt))
							->save();
					}
					$password = md5($password . $user['salt'] . $_W['config']['setting']['authkey']);
					$data = array('password' => $password);
				}
				break;
			default:
				//其它信息
				$data = array($type => safe_gpc_string($_GPC['request_data']));
				break;
		}
		$result = mc_update($uid, $data);
		if ($result) {
			iajax(0, '修改成功！', '');
		} else {
			iajax(1, '修改失败！', '');
		}
	}
	template('mc/member-information');
}
if ('address' == $do) {
	$uid = intval($_GPC['uid']);
	if ($_W['ispost'] && $_W['isajax']) {
		$op = safe_gpc_string($_GPC['op']);
		if ('addaddress' === $op || 'editaddress' === $op) {
			$post = array(
				'uniacid' => $_W['uniacid'],
				'province' => safe_gpc_string($_GPC['province']),
				'city' => safe_gpc_string($_GPC['city']),
				'district' => safe_gpc_string($_GPC['district']),
				'address' => safe_gpc_string($_GPC['detail']),
				'uid' => intval($_GPC['uid']),
				'username' => safe_gpc_string($_GPC['name']),
				'mobile' => safe_gpc_string($_GPC['phone']),
				'zipcode' => safe_gpc_string($_GPC['code']),
			);
			if ('addaddress' === $op) {
				$exist_address = table('mc_member_address')
					->where(array(
						'uniacid' => $post['uniacid'],
						'uid' => $uid
					))
					->getcolumn('COUNT(*)');
				if (!$exist_address) {
					$post['isdefault'] = 1;
				}
				if (table('mc_member_address')->fill($post)->save()) {
					$post['id'] = pdo_insertid();
					iajax(0, $post, '');
				} else {
					iajax(1, '收货地址添加失败', '');
				}
			} else {
				$post['id'] = intval($_GPC['id']);
				$result = table('mc_member_address')
					->where(array(
						'id' => intval($_GPC['id']),
						'uniacid' => $_W['uniacid']
					))
					->fill($post)
					->save();
				if ($result) {
					iajax(0, $post, '');
				} else {
					iajax(1, '收货地址修改失败', '');
				}
			}
		}
		if ('deladdress' === $op) {
			$id = intval($_GPC['id']);
			if (table('mc_member_address')
				->where(array(
					'id' => $id,
					'uniacid' => $_W['uniacid']
				))
				->delete()) {
				iajax(0, '删除成功', '');
			} else {
				iajax(1, '删除失败', '');
			}
		}
		if ('isdefault' === $op) {
			$id = intval($_GPC['id']);
			$uid = intval($_GPC['uid']);
			table('mc_member_address')
				->where(array(
					'uid' => $uid,
					'uniacid' => $_W['uniacid']
				))
				->fill(array('isdefault' => 0))
				->save();
			table('mc_member_address')
				->where(array(
					'id' => $id,
					'uniacid' => $_W['uniacid']
				))
				->fill(array('isdefault' => 1))
				->save();
			iajax(0, '设置成功', '');
		}
	}
}
