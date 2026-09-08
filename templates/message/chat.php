<?php
/* ⚠️ 此模板已废弃（orphan）：MessageController::chat() 已改为 header('Location: ...message/index?id=X')，
 * 私信对话页实际由 templates/message/index.php 渲染。本文件不再被任何路由加载，
 * 在此修改不会生效。表情按钮等私信页改动请去 index.php。 */
/** 私信对话 */ $title = '与 ' . ($targetUser['nickname'] ?: $targetUser['username']) . ' 的对话'; $hideSidebar = true;
?>
<div class="card pm-chat-card">
    <div class="pm-chat-layout">
        <div class="chat-sidebar">
            <div class="chat-sidebar-title">会话列表</div>
            <div class="chat-sidebar-list">
                <?php if (empty($conversations)): ?>
                <div class="empty-state" style="padding:24px 12px;color:#bbb;font-size:12px;text-align:center;">暂无会话</div>
                <?php else: ?>
                <?php foreach ($conversations as $c): ?>
                <a href="<?= url('message/chat', ['id' => $c['id']]) ?>" class="chat-conv<?= $c['id'] == $targetUser['id'] ? ' active' : '' ?>">
                    <span class="chat-conv-avatar">
                        <?php if (!empty($c['avatar'])): ?>
                        <img src="<?= upload_url($c['avatar']) ?>" alt="">
                        <?php else: ?>
                        <?= e(mb_substr($c['nickname'] ?: $c['username'], 0, 1)) ?>
                        <?php endif; ?>
                    </span>
                    <span class="chat-conv-name"><?= e($c['nickname'] ?: $c['username']) ?></span>
                    <span class="chat-conv-unread" data-uid="<?= (int)$c['id'] ?>" style="display:none;"></span>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="chat-main">
            <div class="chat-header">
                <a href="<?= url('message/index') ?>" class="chat-back-link" aria-label="返回会话列表"><i class="fa-solid fa-arrow-left"></i></a>
                <a href="<?= url('user/profile', ['id' => $targetUser['id']]) ?>" class="chat-target-name">
                    <?= e($targetUser['nickname'] ?: $targetUser['username']) ?><?= cert_badge_html($targetUser) ?>
                </a>
            </div>
            <div class="chat-box" id="chatBox">
                <?php if (empty($messages)): ?>
                <div class="empty-state" style="padding:40px;"><p>开始你们的对话吧</p></div>
                <?php else: ?>
                <?php foreach ($messages as $m): ?>
                <div class="chat-msg<?= $m['from_user_id'] == Auth::id() ? ' me' : '' ?>">
                    <div class="chat-msg-bubble"><?= nl2br(render_emoji(e($m['content']))) ?></div>
                    <div class="chat-msg-time"><?= time_ago($m['created_at']) ?></div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <form class="chat-input-wrap" id="chatForm" autocomplete="off">
                <?= csrf_field() ?>
                <textarea id="msgInput" class="form-control chat-textarea" rows="3" placeholder="输入消息... (Enter 发送 / Shift+Enter 换行)"></textarea>
                <div class="chat-input-actions">
                    <button type="button" class="emoji-trigger chat-emoji-btn" data-emoji-trigger data-emoji-target="msgInput" title="插入表情（:code: 短代码，发送后自动渲染）" aria-label="插入表情"><i class="fa-regular fa-face-smile" aria-hidden="true"></i> 表情</button>
                    <button type="button" class="btn btn-primary chat-send-btn" onclick="sendMessage()">发送</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
var targetId = <?= $targetUser['id'] ?>;
var myId = <?= (int)Auth::id() ?>;
// isHtml=true 时 content 已是服务端渲染好的 HTML（emoji 短代码已替换为 <img>/字符，含 <br>），
//   直接拼接，不再做 < → &lt; 替换；否则按原文转义。
function appendMessage(content, fromMe, isHtml) {
    var box = document.getElementById('chatBox');
    var empty = box.querySelector('.empty-state');
    if (empty) empty.remove();
    var div = document.createElement('div');
    div.className = 'chat-msg' + (fromMe ? ' me' : '');
    var body = isHtml
        ? (content + '').replace(/\n/g, '<br>')
        : (content + '').replace(/</g, '&lt;').replace(/\n/g, '<br>');
    div.innerHTML = '<div class="chat-msg-bubble">' + body + '</div><div class="chat-msg-time">刚刚</div>';
    box.appendChild(div);
    box.scrollTop = box.scrollHeight;
}
function sendMessage() {
    var input = document.getElementById('msgInput');
    var content = input.value.replace(/\s+$/g, '');
    if (!content.trim()) { input.focus(); return; }
    var token = document.querySelector('input[name="_token"]').value;
    input.disabled = true;
    postJSON(url('message/send'), {to_user_id: targetId, content: content, _token: token}, function(res) {
        input.disabled = false;
        if (res.code === 0) {
            appendMessage(res.data.content_html, true, true);
            input.value = '';
            input.style.height = 'auto';
            input.focus();
        } else {
            toast(res.message || '发送失败', 'error');
            input.focus();
        }
    }, function() { input.disabled = false; input.focus(); });
}
// Enter 发送 / Shift+Enter 换行
document.getElementById('msgInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
        e.preventDefault();
        sendMessage();
    }
});
// textarea 自适应高度：5 行 (PC) / 2 行 (移动) 显示，多余内容向上滚动；行数无限滚动，最高 max-height 触发内部滚动
(function() {
    var ta = document.getElementById('msgInput');
    function autosize() {
        ta.style.height = 'auto';
        // 受 CSS max-height 限制（.chat-textarea{max-height:160px}）；超出时浏览器自动显示内部滚动条
        ta.style.height = Math.min(ta.scrollHeight, 240) + 'px';
    }
    ta.addEventListener('input', autosize);
    autosize();
})();
document.getElementById('chatBox').scrollTop = document.getElementById('chatBox').scrollHeight;

// 实时轮询：每 4 秒拉取新消息
var lastMsgId = <?= !empty($messages) ? (int)end($messages)['id'] : 0 ?>;
function pollNewMessages() {
    postJSON(url('message/newMessages', {id: targetId, last_id: lastMsgId}), {}, function(res) {
        if (res && res.code === 0 && res.data && res.data.messages) {
            var msgs = res.data.messages;
            if (msgs.length === 0) {
                if (typeof updateMsgBadges === 'function') updateMsgBadges();
                return;
            }
            for (var i = 0; i < msgs.length; i++) {
                var m = msgs[i];
                lastMsgId = Math.max(lastMsgId, parseInt(m.id));
                if (parseInt(m.from_user_id) === myId) continue; // 自己发的已实时插入
                appendMessage(m.content_html, false, true);
            }
            if (typeof updateMsgBadges === 'function') updateMsgBadges();
        }
    });
}
setInterval(pollNewMessages, 4000);
</script>
</content>
</invoke>