<?php
/**
 * [WeEngine System] Copyright (c) 2014 W7.CC.
 */
defined('IN_IA') or exit('Access Denied');
load()->model('article');
load()->model('user');

$dos = array('detail', 'list', 'like_comment', 'more_comments', 'delete');
$do = in_array($do, $dos) ? $do : 'list';
$_W['breadcrumb'] = '公告';
if ('detail' == $do) {
	$id = intval($_GPC['id']);
	$notice = article_notice_info($id);
	if (is_error($notice)) {
		itoast('公告不存在或已删除', referer(), 'error');
	}
	if (!empty($notice)) {
		$notice['style'] = iunserializer($notice['style']);
		$notice['group'] = empty($notice['group']) ? array('vice_founder' => array(), 'normal' => array()) : iunserializer($notice['group']);
		if (($_W['isfounder'] && !empty($notice['group']['vice_founder']) && !in_array($_W['user']['groupid'], $notice['group']['vice_founder'])) || (!$_W['isfounder'] && !empty($notice['group']['normal']) && !in_array($_W['user']['groupid'], $notice['group']['normal']))) {
			itoast('没有权限访问公告', referer(), 'error');
		}
	}
	$comment_status = setting_load('notice_comment_status');
	$comment_status = empty($comment_status['notice_comment_status']) ? 0 : 1;

	if ($_W['ispost']) {
		$comment_table = table('article_comment');
		if (empty($comment_status)) {
			if ($_W['isw7_request']) {
				iajax(-1, '未开启评论功能！');
			}
			itoast('未开启评论功能！', referer(), 'error');
		}
		$content = safe_gpc_string($_GPC['content']);
		if (empty($content)) {
			if ($_W['isw7_request']) {
				iajax(-1, '评论内容不能为空！');
			}
			itoast('评论内容不能为空！', referer(), 'error');
		}
		$result = $comment_table->addComment(array(
			'articleid' => $id,
			'content' => $content,
			'uid' => $_W['uid'],
		));
		if ($_W['isw7_request']) {
			iajax($result? 0 :1, $result? '评论成功':'评论失败');
		}
		itoast($result ? '评论成功' : '评论失败', url('article/notice-show/detail', array('id' => $id, 'page' => 1)), $result ? 'success' : 'error');
	}
	if ($_W['isw7_request']) {
		iajax(0, array('notice' => $notice, 'comment_status' => $comment_status));
	}
	pdo_update('article_notice', array('click +=' => 1), array('id' => $id));
	$title = $notice['title'];
}

if ('more_comments' == $do) {
	$order = empty($_GPC['order']) || 'id' == safe_gpc_string($_GPC['order']) ? 'id' : 'like_num';
	$pageindex = empty($_GPC['page']) ? 1 : max(1, intval($_GPC['page']));
	$pagesize = 15;
	$comment_table = table('article_comment');
	$comment_table->orderby($order, 'DESC');
	$comment_table->searchWithPage($pageindex, $pagesize);
	$comments = $comment_table->getCommentsByArticleid(intval($_GPC['id']));
	$total = $comment_table->getLastQueryTotal();

	if (!empty($comments)) {
		$uids = array();
		foreach ($comments as $comment) {
			$uids[$comment['uid']] = $comment['uid'];
		}
		$user_info = table('users')->searchWithUid($uids)->getUsersList();
		foreach ($comments as $k => $comment) {
			if (!empty($user_info[$comment['uid']])) {
				$comments[$k] = array_merge($user_info[$comment['uid']], $comment);
			}
		}
	}
	iajax(0, array(
		'total' => $total,
		'page' => $pageindex,
		'page_size' => $pagesize,
		'list' => array_values($comments),
		'pager' => pagination($total, $pageindex, $pagesize, '', array('ajaxcallback' => true, 'callbackfuncname' => 'changePage')),
	));
}

if ('like_comment' == $do) {
	$articleid = intval($_GPC['articleid']);
	$comment_id = intval($_GPC['id']);
	$article_comment_table = table('article_comment');

	$comment = $article_comment_table->getById($comment_id);
	if (empty($comment)) {
		iajax(-1, '评论不存在');
	}
	$like_comment = $article_comment_table->getLikeComment($_W['uid'], $articleid, $comment_id);
	if (!empty($like_comment)) {
		iajax(-1, '已赞');
	}
	if ($article_comment_table->likeComment($_W['uid'], $articleid, $comment_id)) {
		iajax(0);
	} else {
		iajax(-1, '操作失败，请重试。');
	}
}

if ('list' == $do) {
	$categroys = article_categorys('notice');
	$categroys[0] = array('title' => '所有公告');

	$cateid = empty($_GPC['cateid']) ? 0 : intval($_GPC['cateid']);

	$pindex = empty($_GPC['page']) ? 1 : max(1, $_GPC['page']);
	$psize = 20;
	$filter = array('cateid' => $cateid);
	$notices = article_notice_all($filter, $pindex, $psize);
	$total = intval($notices['total']);
	$data = $notices['notice'];
	$pager = pagination($total, $pindex, $psize);
	if ($_W['isw7_request']) {
		$message = array(
			'notice'	=> $notice,
			'comment_status' => $comment_status,
		);
		iajax(0, $message);
	}
}

if ('delete' == $do) {
	$id = empty($_GPC['id']) ? 0 : intval($_GPC['id']);
	if (empty($_W['isadmin'])) {
		iajax(0, '没有权限删除评论！');
	}
	if (empty($id)) {
		iajax(0, '评论不存在或已删除！');
	}
	pdo_delete('article_comment', array('id' => $id));

	iajax(0, '删除成功！');
}

template('article/notice-show');
