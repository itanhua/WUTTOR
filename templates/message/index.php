<?php /** 私信主入口（双列布局） */ $title = '私信'; $hideSidebar = true; ?>
<div class="card pm-chat-card">
    <div class="pm-chat-layout">
        <!-- 左侧：会话列表（始终显示） -->
        <div class="chat-sidebar">
            <div class="chat-sidebar-title">会话列表</div>
            <div class="chat-sidebar-list" id="convSidebarList">
                <?php if (empty($conversations)): ?>
                <div class="empty-state" style="padding:24px 12px;color:#bbb;font-size:12px;text-align:center;">暂无私信会话</div>
                <?php else: ?>
                <?php foreach ($conversations as $c):
                    $uid = (int)$c['id'];
                    $isActive = $uid === (int)$selectedId;
                    $lastIso = (string)($c['last_time'] ?? '');
                    $preview = (string)($c['last_message'] ?? '');
                    // ✦ 侧栏预览在服务端直接渲染 emoji，避免显示字面 :code:
                    //    截断按"原文 28 字"计算（HTML 中的 <img> 不参与长度计算，视觉上仍稳定）。
                    $previewTrunc = mb_strlen($preview) > 28 ? mb_substr($preview, 0, 28) . '…' : $preview;
                    $previewHtml  = render_emoji(e($previewTrunc));
                    $unread = (int)($c['unread'] ?? 0);
                ?>
                <a href="<?= url('message/index', ['id' => $uid]) ?>"
                   class="chat-conv<?= $isActive ? ' active' : '' ?>"
                   data-conv-id="<?= $uid ?>" data-conv-iso="<?= e($lastIso) ?>">
                    <span class="chat-conv-avatar">
                        <?php if (!empty($c['avatar'])): ?>
                        <img src="<?= upload_url($c['avatar']) ?>" alt="">
                        <?php else: ?>
                        <?= e(mb_substr($c['nickname'] ?: $c['username'], 0, 1)) ?>
                        <?php endif; ?>
                    </span>
                    <span class="chat-conv-body">
                        <span class="chat-conv-row">
                            <span class="chat-conv-name"><?= e($c['nickname'] ?: $c['username']) ?></span>
                            <span class="chat-conv-time conv-time"><?= time_ago($lastIso) ?></span>
                        </span>
                        <span class="chat-conv-row">
                            <span class="chat-conv-preview conv-preview" title="<?= e($preview) ?>"><?= $previewHtml ?></span>
                            <span class="chat-conv-unread conv-unread" data-uid="<?= $uid ?>" style="<?= $unread > 0 ? '' : 'display:none;' ?>"><?= $unread > 99 ? '99+' : $unread ?></span>
                        </span>
                    </span>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- 右侧：占位 or 完整对话 -->
        <div class="chat-main" id="chatMain"<?php if (!empty($targetUser)): ?> data-target-id="<?= (int)$targetUser['id'] ?>"<?php endif; ?>>
            <?php if (empty($targetUser)): ?>
            <!-- 占位：未选中任何会话 -->
            <div class="chat-empty" id="chatEmpty">
                <i class="fa-regular fa-comments"></i>
                <p>请选择会话</p>
            </div>
            <?php else: ?>
            <!-- 完整对话：消息历史 + 输入框 -->
            <div class="chat-header">
                <a href="<?= url('message/index') ?>" class="chat-back-link" aria-label="返回会话列表"><i class="fa-solid fa-arrow-left"></i></a>
                <a href="<?= url('user/profile', ['id' => $targetUser['id']]) ?>" class="chat-target-name">
                    <?= e($targetUser['nickname'] ?: $targetUser['username']) ?><?= cert_badge_html($targetUser) ?>
                </a>
            </div>
            <div class="chat-box" id="chatBox">
                <?php if (!empty($targetUser) && !empty($hasMore)): ?>
                <div class="chat-load-earlier" id="chatLoadEarlier" data-oldest="<?= (int)$oldestId ?>" data-target="<?= (int)$targetUser['id'] ?>">
                    <button type="button" class="btn btn-sm chat-load-btn">加载更早的消息</button>
                </div>
                <?php endif; ?>
                <?php if (empty($messages)): ?>
                <div class="empty-state" style="padding:40px;"><p>开始你们的对话吧</p></div>
                <?php else: ?>
                <?php foreach ($messages as $m): ?>
                <div class="chat-msg<?= $m['from_user_id'] == Auth::id() ? ' me' : '' ?>" data-msg-id="<?= (int)$m['id'] ?>">
                    <div class="chat-msg-bubble"><?= nl2br(render_emoji(e($m['content']))) ?></div>
                    <div class="chat-msg-time"><?= time_ago($m['created_at']) ?></div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <form class="chat-input-wrap" id="chatForm" autocomplete="off">
                <?= csrf_field() ?>
                <textarea id="msgInput" class="form-control chat-textarea" rows="3" placeholder="输入消息..."></textarea>
                <div class="chat-input-actions">
                    <button type="button" class="emoji-trigger chat-emoji-btn" data-emoji-trigger data-emoji-target="msgInput" title="插入表情（:code: 短代码，发送后自动渲染）" aria-label="插入表情"><i class="fa-regular fa-face-smile" aria-hidden="true"></i> 表情</button>
                    <button type="button" class="btn btn-primary chat-send-btn" id="chatSendBtn">发送</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function() {
    var myId = <?= (int)Auth::id() ?>;

    // ============ 视觉：chat-input-wrap 不再 fixed，改用 flex 列布局 (chat-box flex:1 + chat-input-wrap flex-shrink:0) ============
    // 优势：最后一条消息紧贴输入框（无灰色 padding-bottom 留白），不会被 fixed wrap 遮挡，
    //       iOS keyboard 弹起时 main 被 visualViewport 压缩 → box 跟着压缩 → wrap 自动贴压缩后的 main 底部
    //       进入新会话 box.scrollTop = scrollHeight 永远是 box 内部滚动，不受 fixed 影响
    // 这里只同步 --chat-vh（用于 pm-chat-layout 高度），不再做任何 wrap bottom / box padding 调整
    // 关键：navbar 已隐藏（CSS body.pm-page.pm-chat-page .navbar{display:none}），
    //       pm-chat-card 顶 = screen 顶 = 0；
    //       keyboard 弹起后：layout 应精确等于「从 screen 顶到 keyboard 上沿」的高度，
    //       即 layout.height = vv.offsetTop + vv.height（offsetTop=layout 顶到 window 顶的距离，height=layout 可视高）
    //       —— 不需要再减 navbar 高度，键盘上方的可视区天然贴合 keyboard。
    function fitChatHeight() {
        var vv = window.visualViewport;
        var h;
        if (vv && vv.height) {
            // keyboard 弹起：vv.offsetTop > 0, vv.height 缩小；总可视高度 = offsetTop + height
            // 正常状态：offsetTop = 0, height = window.innerHeight；总可视高度 = window.innerHeight
            h = (vv.offsetTop || 0) + vv.height;
        } else {
            h = window.innerHeight;
        }
        document.documentElement.style.setProperty('--chat-vh', h + 'px');
    }
    fitChatHeight();
    window.addEventListener('resize', fitChatHeight);
    window.addEventListener('orientationchange', fitChatHeight);
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', fitChatHeight);
        window.visualViewport.addEventListener('scroll', fitChatHeight);
    }

    // ============ 实时：会话列表预览 + 角标（每 6s） ============
    function pmAgo(t) {
        if (!t) return '';
        var s = String(t).replace(/-/g, '/').replace(' ', 'T');
        var d = new Date(s).getTime() / 1000;
        if (!d) return t;
        var diff = Math.floor(Date.now() / 1000 - d);
        if (diff < 60) return '刚刚';
        if (diff < 3600) return Math.floor(diff / 60) + '分钟前';
        if (diff < 86400) return Math.floor(diff / 3600) + '小时前';
        if (diff < 86400 * 7) return Math.floor(diff / 86400) + '天前';
        var dt = new Date(d * 1000);
        return (dt.getMonth() + 1) + '月' + dt.getDate() + '日';
    }
    function pmPreview(s) {
        s = (s + '').replace(/\s+/g, ' ').trim();
        return s.length > 28 ? s.substring(0, 28) + '…' : s;
    }
    function formatNowIso() {
        var d = new Date();
        var p = function(n) { return n < 10 ? '0' + n : '' + n; };
        return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate())
            + ' ' + p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
    }
    // 把单个 chat-conv item 提到所有「比自己更旧」的兄弟之前（最新消息置顶）
    // 由于 ISO 时间字符串 'YYYY-MM-DD HH:MM:SS' 的字典序 = 时间序，可直接字符串比较。
    function reorderConvItem(item) {
        var iso = item.getAttribute('data-conv-iso') || '';
        while (true) {
            var prev = item.previousElementSibling;
            if (!prev || !prev.classList || !prev.classList.contains('chat-conv')) break;
            var prevIso = prev.getAttribute('data-conv-iso') || '';
            // 如果 prev 比自己更新（prevIso > iso），则当前应在 prev 之后，停止上移
            if (prevIso && prevIso > iso) break;
            // 否则把 item 插到 prev 之前
            item.parentNode.insertBefore(item, prev);
        }
    }

    function pollConvList() {
        if (typeof ajax !== 'function') return;
        ajax({ url: url('message/unreadSummary'), method: 'GET', success: function(res) {
            if (!res || res.code !== 0 || !res.data) return;
            var d = res.data;
            if (Array.isArray(d.convList)) {
                d.convList.forEach(function(row) {
                    var item = document.querySelector('.chat-conv[data-conv-id="' + row.id + '"]');
                    if (!item) return;
                    var prevEl = item.querySelector('.conv-preview');
                    var timeEl = item.querySelector('.conv-time');
                    var oldIso = item.getAttribute('data-conv-iso') || '';
                    // ✦ 用服务端已渲染的 last_message_html（emoji 已替换）走 innerHTML，避免显示字面 :code:
                    //    若服务端未返回该字段（兼容老接口），降级为 textContent + pmPreview 截断。
                    var newHtml = row.last_message_html;
                    var newIso = row.last_time || '';
                    var prevFull = (row.last_message || '').replace(/\s+/g, ' ').trim();
                    if (prevEl) {
                        if (typeof newHtml === 'string' && newHtml !== '') {
                            prevEl.innerHTML = newHtml;
                        } else {
                            prevEl.textContent = pmPreview(row.last_message || '');
                        }
                        prevEl.setAttribute('title', prevFull);
                    }
                    if (timeEl && oldIso !== newIso) {
                        item.setAttribute('data-conv-iso', newIso);
                        timeEl.textContent = pmAgo(newIso);
                        // ✦ 时间变了 → 把这个会话重新排序到正确位置（最新消息置顶）
                        reorderConvItem(item);
                    }
                });
            }
            if (d.convUnread && typeof d.convUnread === 'object') {
                document.querySelectorAll('.conv-unread').forEach(function(el) {
                    var uid = el.getAttribute('data-uid');
                    var c = parseInt(d.convUnread[uid] || 0, 10) || 0;
                    if (c > 0) {
                        el.textContent = c > 99 ? '99+' : c;
                        el.style.display = '';
                    } else {
                        el.textContent = '';
                        el.style.display = 'none';
                    }
                });
            }
            if (typeof updateMsgBadges === 'function') updateMsgBadges();
        }});
    }
    setInterval(pollConvList, 6000);

    // ============ sidebar 滚动位置保留 ============
    var sb = document.getElementById('convSidebarList');
    var SB_KEY = 'pm_sidebar_scroll';
    try {
        var saved = sessionStorage.getItem(SB_KEY);
        if (saved && sb) sb.scrollTop = parseInt(saved, 10) || 0;
    } catch (e) {}
    function saveSidebarScroll() {
        try {
            if (sb) sessionStorage.setItem(SB_KEY, sb.scrollTop + '');
            var active = document.querySelector('.chat-conv.active');
            if (active) sessionStorage.setItem('pm_active_conv', active.getAttribute('data-conv-id') || '');
        } catch (e) {}
    }
    if (sb) sb.addEventListener('scroll', saveSidebarScroll);

    // ============ 单会话内：消息加载、发送、轮询（每次切会话调用一次） ============
    var pollTimer = null;            // 当前会话轮询 timer（切会话时清掉）
    function bindChatMain() {
        var main = document.getElementById('chatMain');
        if (!main) return;
        var box = document.getElementById('chatBox');
        var input = document.getElementById('msgInput');
        var sendBtn = document.getElementById('chatSendBtn');
        if (!box || !input) return;

        var targetId = (function() {
            // 优先读 #chatMain 上的 data-target-id（模板实际写在这里）
            if (main && main.getAttribute('data-target-id')) {
                return parseInt(main.getAttribute('data-target-id'), 10) || 0;
            }
            var dataEl = document.getElementById('chatMainData');
            if (dataEl) return parseInt(dataEl.getAttribute('data-target-id'), 10) || 0;
            // 兜底：从 sidebar 当前激活会话拿
            var active = document.querySelector('.chat-conv.active');
            return active ? parseInt(active.getAttribute('data-conv-id'), 10) || 0 : 0;
        })();
        if (targetId <= 0) return;

        var msgForm = document.getElementById('chatForm');
        var lastMsgId = (function() {
            var nodes = box.querySelectorAll('.chat-msg');
            if (!nodes.length) return 0;
            return parseInt(nodes[nodes.length - 1].getAttribute('data-msg-id') || '0', 10) || 0;
        })();

        function appendMessage(html, fromMe, realId, timeText) {
            var empty = box.querySelector('.empty-state');
            if (empty) empty.remove();
            var div = document.createElement('div');
            div.className = 'chat-msg' + (fromMe ? ' me' : '');
            div.setAttribute('data-msg-id', (realId ? String(realId) : '0'));
            // html 来自服务端 render_emoji(e(...))，已转义+emoji 替换完成，直接注入（避免发出去/收到时显 raw 短代码，刷新才看到表情）
            div.innerHTML = '<div class="chat-msg-bubble">' + (html || '') + '</div><div class="chat-msg-time">' + (timeText || '刚刚') + '</div>';
            box.appendChild(div);
            // ✦ 只滚 chat-box，不影响 window 整体（不会"回顶"）
            requestAnimationFrame(function() { box.scrollTop = box.scrollHeight; });
        }

        function sendMessage() {
            if (sendMessage._sending) return;   // 防重复发送（不用 input.disabled，否则 iOS 会收键盘）
            var content = input.value.replace(/\s+$/g, '');
            if (!content.trim()) { input.focus(); return; }
            var tokenEl = msgForm.querySelector('input[name="_token"]');
            var token = tokenEl ? tokenEl.value : '';
            sendMessage._sending = true;
            if (sendBtn) sendBtn.disabled = true;
            try {
                postJSON(url('message/send'), {to_user_id: targetId, content: content, _token: token}, function(res) {
                    sendMessage._sending = false;
                    if (sendBtn) sendBtn.disabled = false;
                    if (res.code === 0) {
                        // ✦ 用服务端返回的真实 id 写回 DOM + 同步 closure 中的 lastMsgId，
                        //   避免下次 setInterval(newMessages, last_id=lastMsgId) 把"自己刚发的"
                        //   当作"待补拉"消息再次插入到 box 顶部（from_user_id===myId 路径虽会 skip，
                        //   但用户感知仍是"我的消息被错插入/对方的几条历史变成最新"）
                        var realId = (res.data && res.data.id) ? parseInt(res.data.id) : 0;
                        // 服务端 send 已返回 content_html（render_emoji(e(...)) 渲染好），优先用；
                        // 兜底本地转义（防止接口异常时表情短代码可见 raw）
                        var sendHtml = (res.data && res.data.content_html != null && res.data.content_html !== '')
                            ? res.data.content_html.replace(/\n/g, '<br>')
                            : (content + '').replace(/</g, '&lt;').replace(/\n/g, '<br>');
                        var sendTime = (res.data && res.data.created_at_ago) ? String(res.data.created_at_ago) : '刚刚';
                        appendMessage(sendHtml, true, realId, sendTime);
                        if (realId > 0) lastMsgId = Math.max(lastMsgId, realId);
                        input.value = '';
                        input.style.height = 'auto';
                        input.focus();      // 保持焦点，键盘不收
                        // ✦ 等 iOS 键盘动画 ~300ms 完成后再滚 box 到底（避免发送瞬间 chat-box 还没完成布局导致滚动位置错乱）
                        var sendBox = box;
                        setTimeout(function() { sendBox.scrollTop = sendBox.scrollHeight; }, 400);
                        // ✦ 立即更新 sidebar 上当前会话的预览 + 时间（服务端 6s 轮询来不及，用户体感瞬间置顶）
                        try {
                            var me = document.querySelector('.chat-conv[data-conv-id="' + targetId + '"]');
                            if (me) {
                                var prevEl2 = me.querySelector('.conv-preview');
                                var timeEl2 = me.querySelector('.conv-time');
                                var nowIso = res.data && res.data.created_at ? String(res.data.created_at) : formatNowIso();
                                me.setAttribute('data-conv-iso', nowIso);
                                if (prevEl2) {
                                    // ✦ 用服务端返回的 content_html（已 render_emoji）走 innerHTML，
                                    //    保证侧栏预览与聊天气泡同步显示表情；旧接口未返回时降级到 pmPreview。
                                    var sidebarHtml = (res.data && res.data.content_html != null && res.data.content_html !== '')
                                        ? res.data.content_html.replace(/\n/g, '<br>')
                                        : pmPreview(content);
                                    if (typeof sidebarHtml === 'string' && /<[^>]+>/.test(sidebarHtml)) {
                                        prevEl2.innerHTML = sidebarHtml;
                                    } else {
                                        prevEl2.textContent = sidebarHtml;
                                    }
                                    prevEl2.setAttribute('title', content);
                                }
                                if (timeEl2) timeEl2.textContent = pmAgo(nowIso);
                                reorderConvItem(me);
                            }
                        } catch (e) {}
                    } else {
                        toast(res.message || '发送失败', 'error');
                        input.focus();
                    }
                }, function() {
                    sendMessage._sending = false;
                    if (sendBtn) sendBtn.disabled = false;
                    input.focus();
                });
            } catch (err) {
                // 极端兜底：postJSON 同步抛错时务必复位，否则 _sending/disbaled 永久卡死 → 按钮永远点不动
                sendMessage._sending = false;
                if (sendBtn) sendBtn.disabled = false;
                toast('发送失败，请重试', 'error');
            }
        }
        if (sendBtn) {
            // ✦ 仅 iOS 需要：mousedown 上 preventDefault 可阻止 textarea 失焦，避免点发送时键盘跳动。
            //    ⚠️ 安卓（含各厂商 WebView / 微信 X5 内核）在 mousedown 上 preventDefault 会吞掉后续合成的 click，
            //       导致"点发送无反应"，故安卓绝对不能绑这个事件。
            var _ua = navigator.userAgent || '';
            var _isIOS = /iPad|iPhone|iPod/.test(_ua);
            if (_isIOS) {
                sendBtn.addEventListener('mousedown', function(e) { e.preventDefault(); });
            }
            // 安卓 / 其他浏览器：直接监听 click（安卓合成点击链路正常），保证一定能触发发送
            sendBtn.addEventListener('click', sendMessage);
        }

        // Enter 发 / Shift+Enter 换行
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
                e.preventDefault();
                sendMessage();
            }
        });
        // 自适应高度（仅控制 textarea 自身高度）
        function autosize() {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 240) + 'px';
        }
        input.addEventListener('input', autosize);
        autosize();

        // iOS focus 时键盘弹起 → 重算 --chat-vh（pm-chat-layout 用），让 main 高度跟随 visualViewport。
        // flex 列布局下 box 自然被压缩，不需要手动算 keyboard 高度。
        // 注意：避免在 focus 时立即 box.scrollTop = box.scrollHeight（iOS Safari 自带 scrollIntoView，
        //       强制滚动会触发 window 跳转）；改为 iOS 键盘弹出动画结束后再滚，让用户看到最新消息上下文。
        function onFocusAdjust() {
            fitChatHeight();
            setTimeout(function() {
                fitChatHeight();
                box.scrollTop = box.scrollHeight;
            }, 350);
        }
        input.addEventListener('focus', onFocusAdjust);
        input.addEventListener('blur', function() { setTimeout(fitChatHeight, 350); });

        // 进页滚到底（只在 chat-box 内滚，不影响 window；此时 keyboard 未弹，无 iOS scrollIntoView 干扰）
        // 用 rAF + setTimeout 双保险：iOS 布局/图片未稳定时 scrollHeight 可能不准，延时再滚一次确保定位到最新消息
        requestAnimationFrame(function() {
            box.scrollTop = box.scrollHeight;
            setTimeout(function() { box.scrollTop = box.scrollHeight; }, 300);
        });

        // ============ 加载更早的消息（向前翻页） ============
        var loadEarlierBtn = box.querySelector('#chatLoadEarlier');
        var loadingEarlier = false;
        function loadEarlier() {
            if (loadingEarlier || !loadEarlierBtn) return;
            var oldest = parseInt(loadEarlierBtn.getAttribute('data-oldest'), 10) || 0;
            if (!oldest) return;
            loadingEarlier = true;
            var btnEl = loadEarlierBtn.querySelector('.chat-load-btn');
            if (btnEl) btnEl.textContent = '加载中…';
            postJSON(url('message/history', {id: targetId, before: oldest}), {}, function(res) {
                loadingEarlier = false;
                if (!res || res.code !== 0 || !res.data) { if (btnEl) btnEl.textContent = '加载失败，点击重试'; return; }
                var msgs = res.data.messages || [];
                if (!msgs.length) {
                    if (loadEarlierBtn) { loadEarlierBtn.remove(); loadEarlierBtn = null; }
                    return;
                }
                var prevHeight = box.scrollHeight;
                var prevTop = box.scrollTop;
                for (var i = 0; i < msgs.length; i++) {
                    var m = msgs[i];
                    var div = document.createElement('div');
                    div.className = 'chat-msg' + (parseInt(m.from_user_id) === myId ? ' me' : '');
                    div.setAttribute('data-msg-id', (m.id ? String(m.id) : '0'));
                    // 优先用服务端 content_html（emoji 已渲染），兜底本地转义
                    var bubble = (m.content_html != null && m.content_html !== '')
                        ? m.content_html.replace(/\n/g, '<br>')
                        : (m.content + '').replace(/</g, '&lt;').replace(/\n/g, '<br>');
                    div.innerHTML = '<div class="chat-msg-bubble">' + bubble + '</div><div class="chat-msg-time">' + pmAgo(m.created_at) + '</div>';
                    box.insertBefore(div, loadEarlierBtn);
                }
                // 维持滚动位置：插入到顶部，scrollTop 需补偿新增高度
                box.scrollTop = prevTop + (box.scrollHeight - prevHeight);
                if (res.data.has_more && res.data.oldest_id) {
                    loadEarlierBtn.setAttribute('data-oldest', res.data.oldest_id);
                    if (btnEl) btnEl.textContent = '加载更早的消息';
                } else {
                    if (loadEarlierBtn) { loadEarlierBtn.remove(); loadEarlierBtn = null; }
                }
            }, function() {
                loadingEarlier = false;
                if (btnEl) btnEl.textContent = '加载失败，点击重试';
            });
        }
        if (loadEarlierBtn) {
            loadEarlierBtn.querySelector('.chat-load-btn').addEventListener('click', loadEarlier);
        }
        // 滚到顶部自动加载更早
        box.addEventListener('scroll', function() {
            if (box.scrollTop < 40 && loadEarlierBtn && !loadingEarlier) loadEarlier();
        });

        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(function() {
            postJSON(url('message/newMessages', {id: targetId, last_id: lastMsgId}), {}, function(res) {
                if (res && res.code === 0 && res.data && res.data.messages) {
                    var msgs = res.data.messages;
                    var appended = false;
for (var i = 0; i < msgs.length; i++) {
                    var m = msgs[i];
                    lastMsgId = Math.max(lastMsgId, parseInt(m.id));
                    if (parseInt(m.from_user_id) === myId) continue;
                    // 优先用服务端 content_html（emoji 已渲染），兜底本地转义
                    var newHtml = (m.content_html != null && m.content_html !== '')
                        ? m.content_html.replace(/\n/g, '<br>')
                        : (m.content + '').replace(/</g, '&lt;').replace(/\n/g, '<br>');
                    appendMessage(newHtml, false, m.id, pmAgo(m.created_at));
                    appended = true;
                }
                    if (appended && typeof updateMsgBadges === 'function') updateMsgBadges();
                }
            });
        }, 4000);
    }

    // ============ SPA：拦截 sidebar 点击，AJAX 替换右侧 main，sidebar / 页面整体不回顶 ============
    function bindConvLinks() {
        document.querySelectorAll('.chat-conv').forEach(function(a) {
            if (a.__pmBound) return;
            a.__pmBound = true;
            a.addEventListener('click', function(e) {
                var href = a.getAttribute('href');
                if (!href || a.classList.contains('active')) {
                    e.preventDefault();
                    return;
                }
                e.preventDefault();
                saveSidebarScroll();
                loadMainByAjax(href, true);
            });
        });
    }
    bindConvLinks();

    // 解析 fetch 出的 HTML，提取 .chat-main 替换到当前页
    function loadMainByAjax(href, pushState) {
        fetch(href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) {
                if (!r.ok) throw new Error('http');
                return r.text();
            })
            .then(function(html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var newMain = doc.querySelector('#chatMain');
                var newSb = doc.querySelector('#convSidebarList');
                if (!newMain) { location.href = href; return; }
                // 1) 更新 sidebar 里 active 状态（保证新 active 高亮）并恢复滚动位置
                if (newSb) {
                    var curSb = document.getElementById('convSidebarList');
                    if (curSb) {
                        curSb.innerHTML = newSb.innerHTML;
                        // 恢复 sidebar 滚动位置（innerHTML 替换会让 scrollTop 归零）
                        try {
                            var saved = sessionStorage.getItem(SB_KEY);
                            if (saved) curSb.scrollTop = parseInt(saved, 10) || 0;
                        } catch (e) {}
                    }
                }
                // 2) 替换右侧 main
                var curMain = document.getElementById('chatMain');
                if (curMain) {
                    curMain.innerHTML = newMain.innerHTML;
                    // ⚠️ 关键：data-target-id 写在 #chatMain 自身属性上，innerHTML 只替换子节点、
                    // 不会改变元素自身属性。不手动同步的话，切到 B 会话后 #chatMain 的 data-target-id
                    // 仍是初始会话 A → bindChatMain 读出的 targetId 永远是 A → sendMessage 把私信
                    // 全部错发给初始会话用户（数据级错乱：给A发的出现在B、刷新后串会话）。
                    if (newMain.getAttribute('data-target-id')) {
                        curMain.setAttribute('data-target-id', newMain.getAttribute('data-target-id'));
                    } else {
                        curMain.removeAttribute('data-target-id');
                    }
                }
                // 3) 更新 sidebar 上 .chat-conv 的绑定
                bindConvLinks();
                // 4) 重新绑定聊天事件 + 重启轮询
                if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
                bindChatMain();
                // 4b) AJAX 换 DOM 后，新插入的 [data-emoji-trigger] 表情按钮需要重新扫描绑定
                //     （EmojiPicker.init 内部有 __emojiTriggerBound 幂等守卫，重复调用安全）
                if (window.EmojiPicker && typeof window.EmojiPicker.init === 'function') {
                    window.EmojiPicker.init();
                }
                // 5) URL 同步（不刷新）
                if (pushState && history.pushState) {
                    history.pushState({ pm: 1 }, '', href);
                }
                // 6) 重新计算高度（移动端 rotate / 浏览器工具栏收起）
                fitChatHeight();
                // 7) 根据 URL 是否带 id 切换 body.pm-chat-page 类（移动端隐藏 sidebar / 显示返回按钮）
                syncPmChatBodyClass(href);
            })
            .catch(function() { location.href = href; });
    }
    // 根据 URL 是否有 id 参数决定 body 上是否加 pm-chat-page（移动端二级页面关键）
    function syncPmChatBodyClass(url) {
        try {
            var u = new URL(url, location.origin);
            var hasId = u.searchParams.get('id') && parseInt(u.searchParams.get('id'), 10) > 0;
            if (hasId) document.body.classList.add('pm-chat-page');
            else document.body.classList.remove('pm-chat-page');
        } catch (e) {}
    }
    // 浏览器前进 / 后退
    window.addEventListener('popstate', function() {
        syncPmChatBodyClass(location.href);
        loadMainByAjax(location.href, false);
    });
    // 捕获 chat-back-link 点击（mobile "←" 按钮）→ AJAX 回首页占位（无 id）
    document.addEventListener('click', function(e) {
        var back = e.target.closest && e.target.closest('.chat-back-link');
        if (back) {
            e.preventDefault();
            saveSidebarScroll();
            var href = back.getAttribute('href');
            loadMainByAjax(href, true);
        }
    });

    // 首次进页：绑定 chat 事件（如果有 targetUser）
    bindChatMain();
})();
</script>
