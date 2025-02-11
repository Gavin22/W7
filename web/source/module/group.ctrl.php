<?php
/**
 * 编辑应用套餐
 * [WeEngine System] Copyright (c) 2014 W7.CC.
 */
defined('IN_IA') or exit('Access Denied');
load()->model('module');
load()->model('user');
load()->model('module');

$dos = array('display', 'del', 'post', 'save', 'edit', 'change_expired');
$do = in_array($do, $dos) ? $do : 'display';
if (!$_W['isfounder']) {
	if ($_W['isajax']) {
		iajax(-1, '无权限操作！');
	}
	itoast('无权限操作！', referer(), 'error');
}

if ('display' == $do) {
	$pageindex = empty($_GPC['page']) ? 1 : intval($_GPC['page']);
	$pagesize = 10;

	$uni_group_table = table('uni_group');
	$uni_group_table->searchWithUid();

	$name = empty($_GPC['name']) ? '' : safe_gpc_string($_GPC['name']);
	if (!empty($name)) {
		$uni_group_table->searchWithName($name);
	}

	if (user_is_vice_founder($_W['uid'])) {
		$uni_group_table->searchWithFounderUid($_W['uid']);
	}
	$uni_group_table->orderby('id', 'DESC');
	$uni_group_table->searchWithPage($pageindex, $pagesize);
	$modules_group_list = $uni_group_table->getUniGroupList();
	$total = $uni_group_table->getLastQueryTotal();
	$pager = pagination($total, $pageindex, $pagesize);
	$module_support_type = module_support_type();
	if (!empty($modules_group_list)) {
		$all_module_names = array();
		foreach ($modules_group_list as $key => $value) {
			$value['modules'] = iunserializer($value['modules']);
			if (!is_array($value['modules'])) {
				$value['modules'] = array();
			}
			$modules_group_list[$key]['modules'] = $value['modules'];

			foreach ($value['modules'] as $type => $modulenames) {
				if (empty($modulenames) || !is_array($modulenames)) {
					$modules_group_list[$key][$type . '_num'] = 0;
					continue;
				} else {
					$type = 'modules' == $type ? 'account' : $type;
					$modules_group_list[$key][$type . '_num'] = count($modulenames);
				}
				$all_module_names = array_merge($all_module_names, $modulenames);
			}
		}
		$all_modules = table('modules')->searchWithName(array_unique($all_module_names))->getall('name');

		foreach ($modules_group_list as &$group) {
			$template_ids = iunserializer($group['templates']);

			$group['templates'] = array();
			if (is_array($template_ids)) {
				$group['templates'] = table('modules')->getAllTemplateByIds($template_ids);
			}
            if (empty($group['modules'])) {
                continue;
            }
			$group['modules_all'] = array();
			foreach ($module_support_type as $support => $info) {
				if (MODULE_SUPPORT_ACCOUNT_NAME == $support) {
					$info['type'] = 'modules';
				}
				if (empty($group['modules'][$info['type']])) {
					continue;
				}
				foreach ($group['modules'][$info['type']] as $modulename) {
					if (empty($all_modules[$modulename])) {
						continue;
					}
					if (empty($group['modules_all'][$modulename])) {
						$all_modules[$modulename]['logo'] = tomedia($all_modules[$modulename]['logo']);
						$group['modules_all'][$modulename] = $all_modules[$modulename];
					}
					if ($all_modules[$modulename][$support] == $info['support']) {
						$support_type = 'modules' == $info['type'] ? 'account' : $info['type'];
						$group['modules_all'][$modulename]['group_support'][] = $support_type;
					}
				}
			}
		}
	}

	if ($_W['isw7_request']) {
		$message = array(
			'total' => $total,
			'page' => $pageindex,
			'page_size' => $pagesize,
			'keyword' => $name,
			'list' => $modules_group_list
		);
		iajax(0, $message);
	}
}

if (in_array($do, array('save', 'del', 'post'))) {
	$id = empty($_GPC['id']) ? 0 : intval($_GPC['id']);
	if (!empty($id) && ACCOUNT_MANAGE_NAME_VICE_FOUNDER == $_W['highest_role']) {
		$exists = table('users_founder_own_uni_groups')->getByFounderUidAndUniGroupId($_W['uid'], $id);
		if (empty($exists)) {
			itoast('无权限操作！', referer(), 'error');
		}
	}
}

if ('save' == $do) {
	$account_all_type_sign = array_column($module_all_support, 'type');
	$modules = safe_gpc_array($_GPC['modules']);
	$package_info = array(
		'id' => $id,
		'name' => safe_gpc_string($_GPC['name']),
		'modules' => array(),
		'templates' => empty($_GPC['templates']) ? array() : safe_gpc_array($_GPC['templates']),
	);
	foreach ($account_all_type_sign as $account_type) {
		if ('account' == $account_type) {
			$package_info['modules']['modules'] = empty($modules[$account_type]) ? array() : $modules[$account_type];
		} else {
			$package_info['modules'][$account_type] = empty($modules[$account_type]) ? array() : $modules[$account_type];
		}
	}

	$modules_name = array_reduce($package_info['modules'], 'array_merge', array());
	if (!empty($modules_name)) {
		if (!empty($package_info['id'])) {
			$group_info = pdo_get('uni_group', array('id' => $package_info['id']), array('modules'));
			$group_info['modules'] = empty($group_info['modules']) ? array() : array_reduce(iunserializer($group_info['modules']), 'array_merge', array());
			$modules_name = array_diff($modules_name, $group_info['modules']);
		}
		$module_expired_list = module_expired_list();
		if (is_error($module_expired_list)) {
			if ($_W['isajax']) {
				iajax(-1, $module_expired_list['message'], referer());
			}
			itoast($module_expired_list['message'], '', '');
		}
		if (!empty($module_expired_list)) {
			$expired_modules_name = module_expired_diff($module_expired_list, $modules_name);
			if (!empty($expired_modules_name)) {
				if ($_W['isajax']) {
					iajax(-1, '应用：' . $expired_modules_name . '，服务费到期，无法添加！', referer());
				}
				itoast('应用：' . $expired_modules_name . '，服务费到期，无法添加！', '', '');
			}
		}
	}

	$package_info = module_save_group_package($package_info);

	if (is_error($package_info)) {
		iajax(1, $package_info['message'], '');
	}
	iajax(0, ($id ? '更新成功' : '添加成功'), url('module/group/display'));
}

if ('del' == $do) {
	if (empty($id)) {
		iajax(-1, '请选择要操作的权限组');
	}
	pdo_delete('uni_group', array('id' => $id));
	pdo_delete('users_founder_own_uni_groups', array('uni_group_id' => $id));
	cache_build_uni_group();
	cache_build_account_modules();
	iajax(0, '删除成功！', referer());
}

if ('post' == $do) {
	$group_id = $id;
	if (!empty($group_id)) {
		$group = table('uni_group')->getById($group_id);
		if (!empty($group['modules'])) {
			$group['modules'] = iunserializer($group['modules']);
		}
		if (!empty($group['templates'])) {
			$group['templates'] = iunserializer($group['templates']);
		}
	}
	$module_support_type = module_support_type();
	$module_list = array(
		'modules' => array(),
	);

	$user_modules = user_modules($_W['uid']);
	foreach ($user_modules as $name => $module) {
		if (!empty($module['issystem'])) {
			continue;
		}
		foreach ($module_support_type as $support => $info) {
			$info['type'] = 'account' == $info['type'] ? 'modules' : $info['type'];
			if ($module[$support] == $info['support']) {
				$module_list['modules'][] = array(
					'id' => $module['mid'],
					'name' => $module['name'],
					'title' => $module['title'],
					'logo' => $module['logo'],
					'support' => $support,
					'checked' => !empty($group['modules'][$info['type']]) && in_array($module['name'], $group['modules'][$info['type']]) ? 1 : 0,
				);
			}
		}
	}
	if ($_W['isw7_request']) {
		iajax(0, $module_list);
	}
}

if ('change_expired' == $do) {
	$modules_name = safe_gpc_array($_GPC['modules']);
	if (!empty($modules_name)) {
		$module_expired_list = module_expired_list();
		if (is_error($module_expired_list)) {
			iajax(-1, $module_expired_list['message']);
		}
		if (!empty($module_expired_list)) {
			$expired_modules_name = module_expired_diff($module_expired_list, $modules_name);
			if (!empty($expired_modules_name)) {
				iajax(-1, '应用：' . $expired_modules_name . '，服务费到期，无法添加！');
			}
		}
	}
	iajax(0);
}
template('module/group');
