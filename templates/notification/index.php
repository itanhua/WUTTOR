<?php /** 通知列表 - 按 tab 分类 */ $title = '消息通知'; $hideSidebar = true; ?>
<?php
$typeLabels = [
    'comment'         => '评论',
    'reply'           => '回复',
    'like'            => '点赞',
    'follow'          => '关注',
    'certification'   => '认证',
    'mention'         => '提到',
    'reward'          => '打赏',
    'system'          => '系统',
    'topic_purchased' => '购买',
    'bounty_accepted' => '悬赏',
    'favorite'        => '收藏',
    'lottery_won'     => '中奖',
    'interview_invite'  => '采访邀请',
    'interview_answered' => '采访回答',
];
// 私信已剥离到独立入口（顶栏铃铛下拉的"私信" → message/index），通知页不再列出 pm tab
$tabs = [
    'all'           => '全部',
    'comment'       => '评论',
    'reply'         => '回复',
    'like'          => '点赞',
    'follow'        => '关注',
    'certification' => '认证',
    'mention'       => '提到我的',
    'favorite'      => '收藏',
    'system'        => '系统通知',
];
$currentTab = $tab ?? 'all';

// 各 tab 未读数（按 notifications.type + is_system_notification 分组聚合）
// ✦ 私信完全独立：不查询 messages 表，所有 tab 角标都只看 notifications；
//   私信自己的未读由顶栏铃铛下拉的"私信"入口（message/index）独立处理。
$userId = Auth::id();
$unreadByType = [];
try {
    $rows = Model::query(
        "SELECT type, is_system_notification, COUNT(*) AS c FROM notifications WHERE user_id = ? AND is_read = 0 AND type != 'message' GROUP BY type, is_system_notification",
        [$userId]
    );
    $unreadAll = 0; $unreadSys = 0;
    foreach ($rows as $r) {
        $c = (int)$r['c'];
        $unreadAll += $c;
        $t = $r['type'];
        $unreadByType[$t] = ($unreadByType[$t] ?? 0) + $c;
        if ((int)$r['is_system_notification'] === 1) $unreadSys += $c;
    }
    $unreadByType['__all'] = $unreadAll;
    $unreadByType['__system'] = $unreadSys;
} catch (Throwable $e) {
    $unreadByType['__all'] = 0;
    $unreadByType['__system'] = 0;
}

// tab key → 未读统计键 的映射
$unreadKeyMap = [
    'mention' => 'mention',
    'favorite' => 'favorite',
];
?>

<div class="card" style="margin-bottom:0;">
    <div class="card-header" style="padding:0 0 0 0;">
        <div class="switch-tabs" style="width:100%;border-radius:6px 6px 0 0;border-left:none;border-right:none;border-top:none;border-bottom:1px solid #f0f0f0;">
            <?php foreach ($tabs as $key => $label):
                $isActive = $key === $currentTab;
                $unreadKey = $unreadKeyMap[$key] ?? '__' . $key;
                $unread = (int)($unreadByType[$unreadKey] ?? 0);
            ?>
            <a href="<?= url('notification/index', ['tab' => $key]) ?>" class="<?= $isActive ? 'active' : '' ?>" style="position:relative;">
                <?= e($label) ?>
                <?php if ($unread > 0): ?>
                <span class="tab-unread-badge" style="display:inline-block;min-width:16px;padding:0 5px;height:16px;line-height:16px;background:#ea6f5a;color:#fff;border-radius:8px;font-size:10px;margin-left:4px;font-weight:600;"><?= $unread > 99 ? '99+' : $unread ?></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
            <span class="switch-tabs-spacer"></span>
            <?php
            // 任何 tab 下都显示"全部已读"按钮，让用户一键清掉所有未读提醒（包括不在当前 tab 类型的）
            // 即使当前 tab 没有未读也仍然显示，方便用户主动清掉其他 tab 残留红点
            ?>
            <button type="button" id="markAllReadBtn" class="switch-tabs-mark-all">
                <svg viewBox="0 0 24 24" style="width:13px;height:13px;vertical-align:-2px;margin-right:3px;" xmlns="http://www.w3.org/2000/svg">
                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z" fill="currentColor"/>
                </svg>
                全部已读
            </button>
            <span class="switch-tabs-meta"><?= $pagination['total'] ?? 0 ?> 条</span>
        </div>
    </div>
    <div class="message-list" style="box-shadow:none;border:none;border-top:none;">
        <?php if (empty($notifications)): ?>
        <div class="empty-state" style="padding:60px 20px;">
            <div class="empty-icon">空</div>
            <p><?= $currentTab === 'all' ? '暂无通知' : '此分类下暂无通知' ?></p>
            <?php if ($currentTab !== 'all'): ?>
            <p style="margin-top:8px;font-size:12px;color:#bbb;"><a href="<?= url('notification/index') ?>" style="color:#ea6f5a;">查看全部 →</a></p>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <?php
        // tab=system 单纯显示「系统通知」标签；其他 tab 按 type 映射显示。
        // 私信触发留下的旧数据（已剥离到独立私信中心，不会被通知页加载）
        foreach ($notifications as $n):
            $isSys = !empty($n['is_system_notification']);
            $type = $n['type'];
            $label = $typeLabels[$type] ?? '通知';
            $tagClass = 'info';
        ?>
        <div class="message-item <?= $n['is_read'] ? '' : 'unread' ?>" style="<?= $isSys ? 'border-left:3px solid #ea6f5a;background:#fffbf5;' : '' ?>">
            <div class="message-content">
                <div class="message-meta">
                    <span class="status-tag <?= $tagClass ?>" <?= $isSys ? 'style="background:#ea6f5a;color:#fff;border-color:#ea6f5a;"' : '' ?>>
                        <?php if ($isSys): ?>
                        <svg viewBox="0 0 24 24" style="width:11px;height:11px;vertical-align:-1px;margin-right:2px;" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2L4 6v6c0 5 3.5 9.5 8 10 4.5-.5 8-5 8-10V6l-8-4z" fill="currentColor"/>
                        </svg>
                        <?php endif; ?>
                        <?= e($label) ?>
                    </span>
                    <span><?= time_ago($n['created_at']) ?></span>
                    <?php if ($isSys && empty($n['is_read'])): ?>
                    <span style="color:#ea6f5a;font-size:11px;font-weight:600;">· 未读</span>
                    <?php endif; ?>
                </div>
                <div class="message-text">
                    <?= e($n['content']) ?>
                    <?php if (!empty($n['link'])): ?>
                    <a href="<?= e($n['link']) ?>" class="notif-mark-read" data-id="<?= (int)$n['id'] ?>" data-type="<?= e($n['type']) ?>" data-system="<?= !empty($n['is_system_notification']) ? 1 : 0 ?>" style="margin-left:8px;">查看</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($pagination) && $pagination['last_page'] > 1): ?>
<div style="margin-top:16px;">
    <?= pagination($pagination['total'], $page, 20, 'notification/index', ['tab' => $currentTab]) ?>
</div>
<?php endif; ?>

<script>
(function() {
    var btn = document.getElementById('markAllReadBtn');
    if (btn) {
        var tokenInput = document.querySelector('input[name="_token"]');
        var token = tokenInput ? tokenInput.value : '';
        btn.addEventListener('click', function() {
            if (btn.disabled) return;
            if (!confirm('确定将所有通知标记为已读吗？')) return;
            btn.disabled = true;
            var oldText = btn.innerHTML;
            btn.innerHTML = '处理中…';
            window.postJSON(window.url('notification/markAllRead'), { _token: token }, function(res) {
                if (res && res.code === 0) {
                    document.querySelectorAll('.message-item.unread').forEach(function(el) {
                        el.classList.remove('unread');
                    });
                    document.querySelectorAll('.message-item').forEach(function(el) {
                        var indicators = el.querySelectorAll('span');
                        indicators.forEach(function(s) {
                            if (s.textContent.trim() === '· 未读') s.remove();
                        });
                    });
                    // 隐藏 tab 角标红点（仅清通知 tab，不动私信铃铛角标）
                    document.querySelectorAll('.tab-unread-badge').forEach(function(s) { s.remove(); });
                    // ✦ 立即同步顶栏铃铛——markAllRead 后用户期望看到主角标也归零，
                    //   否则要等下一次 15s 轮询才更新，体验像"刷新后仍残留"
                    if (typeof window.updateMsgBadges === 'function') {
                        try { window.updateMsgBadges(); } catch (err) {}
                    }
                    btn.innerHTML = '✓ 已清除';
                    setTimeout(function() {
                        btn.disabled = false;
                        btn.innerHTML = oldText;
                    }, 2000);
                } else {
                    btn.disabled = false;
                    btn.innerHTML = oldText;
                    alert((res && res.message) || '操作失败，请稍后重试');
                }
            }, function(err) {
                btn.disabled = false;
                btn.innerHTML = oldText;
                alert(err && err.message ? err.message : '网络错误');
            });
        });
    }

    // ✦ 单条「查看」点击：先调 markAsRead 接口 mark 已读，再继续跳转
    // - mark 后立即 -1 当前 type 对应的 tab 角标、-1 系统通知专用角标 / -1 "全部"角标
    // - 所有归零的 span 自动 remove；保留顶栏铃铛由 updateMsgBadges() 后续 15s 内同步
    // - 服务端失败时仍允许跳转（不阻断），后端 batch mark read 会在下次进 tab 时兜底
    function decBadge(type, isSystem) {
        var tabs = document.querySelectorAll('.switch-tabs a');
        for (var i = 0; i < tabs.length; i++) {
            var href = tabs[i].getAttribute('href') || '';
            var qIdx = href.indexOf('tab=');
            var tabKey = qIdx >= 0 ? href.substring(qIdx + 4).split('&')[0] : '';
            var badge = tabs[i].querySelector('.tab-unread-badge');
            if (!badge) continue;
            var matchesAll = (tabKey === 'all');
            var matchesType = (tabKey === type);
            var matchesSystem = (tabKey === 'system' && isSystem);
            if (matchesAll || matchesType || matchesSystem) {
                var n = parseInt(badge.textContent.trim(), 10);
                if (isNaN(n)) n = 0;
                n = Math.max(0, n - 1);
                if (n <= 0) {
                    badge.parentNode.removeChild(badge);
                } else {
                    badge.textContent = n > 99 ? '99+' : String(n);
                }
            }
        }
    }
    document.querySelectorAll('a.notif-mark-read').forEach(function(a) {
        a.addEventListener('click', function(e) {
            var id = a.getAttribute('data-id');
            var type = a.getAttribute('data-type') || '';
            var sysAttr = a.getAttribute('data-system');
            var isSystem = sysAttr === '1' || sysAttr === 'true';
            if (!id) return;
            e.preventDefault();
            var tokenInput = document.querySelector('input[name="_token"]');
            var token = tokenInput ? tokenInput.value : '';
            var href = a.getAttribute('href');
            window.postJSON(window.url('notification/markAsRead'), { id: id, _token: token }, function(res) {
                if (res && res.code === 0) {
                    var d = res.data || {};
                    if (d.was_unread) {
                        // 行级 -unread 视觉反馈
                        var row = a.closest('.message-item');
                        if (row) {
                            row.classList.remove('unread');
                            var indicators = row.querySelectorAll('span');
                            indicators.forEach(function(s) {
                                if (s.textContent.trim() === '· 未读') s.remove();
                            });
                        }
                        // tab 角标统一 -1（按 type）
                        decBadge(d.type || type, d.is_system ? 1 : (isSystem ? 1 : 0));
                        // 同步顶栏铃铛（与 updateMsgBadges 同源：unreadSummary 接口）
                        if (typeof window.updateMsgBadges === 'function') {
                            try { window.updateMsgBadges(); } catch (err) {}
                        }
                    }
                }
                // 跳转保持原行为（即便 mark 接口失败也要让用户能点进去）
                if (href) window.location.href = href;
            }, function() {
                if (href) window.location.href = href;
            });
        });
    });
})();
</script>