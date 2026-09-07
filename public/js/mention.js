/**
 * @提及 通用组件
 * 用法：给文本域/输入框加 data-mention="1"，本脚本在 DOMContentLoaded 后自动初始化。
 * 输入 @ 弹出候选（默认关注列表；无关注时降级到最近活跃用户；输入字符后服务端全站模糊搜索）。
 * 插入的 token 始终用目标用户的 username（保证老内容里 @ 显示稳定，不受后续昵称修改影响）。
 * 后端 mention_replace_escaped() 渲染时统一展示为 @username（DB 原样大小写）。
 */
(function () {
    'use strict';

    var popup = null;

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // 把后端返回的相对路径头像转成绝对 URL（解决 pretty URL 页面下 /uploads/xxx
    // 被解析成 /post/1-xxx/uploads/xxx 找不到的问题），同时做 onerror 兜底
    // ——加载失败时降级为字母占位符，避免裂图。
    function absoluteAvatarUrl(u) {
        if (!u) return '';
        var s = String(u);
        // 已是绝对 URL（http/https/data:）直接返回
        if (/^(https?:|data:)/i.test(s)) return s;
        // 协议相对 //xxx 补上当前协议
        if (s.indexOf('//') === 0) return window.location.protocol + s;
        try {
            return new URL(s, window.location.origin).href;
        } catch (e) {
            return s;
        }
    }

    function avatarHtml(u) {
        var name = u.nickname || u.username || '?';
        var initial = (name && name.charAt) ? name.charAt(0) : '?';
        if (u.avatar) {
            var src = absoluteAvatarUrl(u.avatar);
            // 双层兜底：
            //   1) onerror 先尝试补 /public/ 前缀再发一次请求——防止某处 controller 漏了 upload_url()
            //   2) 还是 404 就降级为字母占位符（不再裂图）
            return '<img src="' + escapeHtml(src) +
                '" alt="" onerror="var s=this.getAttribute(\'data-fallback\');if(s){this.src=s;this.removeAttribute(\'data-fallback\');}else{this.outerHTML=\'<span style=\\\'width:28px;height:28px;border-radius:50%;background:#ea6f5a;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:13px;margin-right:8px;flex:0 0 auto;\\\'>' +
                escapeHtml(initial) + '</span>\';}"' +
                ' data-fallback="' + escapeHtml(buildPublicFallback(src)) + '"' +
                ' style="width:28px;height:28px;border-radius:50%;object-fit:cover;margin-right:8px;flex:0 0 auto;">';
        }
        return '<span style="width:28px;height:28px;border-radius:50%;background:#ea6f5a;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:13px;margin-right:8px;flex:0 0 auto;">' + escapeHtml(initial) + '</span>';
    }

    // 兜底 URL：如果当前 src 形如 https://bbs.wuttor.com/uploads/xxx，
    // 尝试补 /public/ 前缀再试一次（因为项目实际资源在 /public/uploads/xxx）。
    // 已经是 /public/ 或绝对路径不变，则返回空（onerror 跳过兜底直接降级字母）。
    function buildPublicFallback(src) {
        try {
            var u = new URL(src, window.location.origin);
            // 已经是 /public/ 或没有 /uploads/ 路径，跳过
            if (/\/public\//.test(u.pathname)) return '';
            if (!/\/uploads\//.test(u.pathname)) return '';
            // 在 pathname 前插入 /public/
            var newPath = '/public' + u.pathname;
            u.pathname = newPath;
            return u.href;
        } catch (e) {
            return '';
        }
    }

    function ensurePopup() {
        if (!popup) {
            popup = document.createElement('div');
            popup.className = 'mention-popup';
            popup.style.display = 'none';
            document.body.appendChild(popup);
        }
        return popup;
    }

    function popupVisible() {
        return popup && popup.style.display !== 'none';
    }

    function hidePopup() {
        if (popup) popup.style.display = 'none';
    }

    function getTrigger(el) {
        var pos = el.selectionStart;
        if (pos === null || pos === undefined) return null;
        var before = el.value.slice(0, pos);
        var at = before.lastIndexOf('@');
        if (at === -1) return null;
        var prev = at === 0 ? ' ' : before.charAt(at - 1);
        if (at !== 0 && !/\s/.test(prev)) return null; // @ 必须位于开头或空白后
        var query = before.slice(at + 1);
        if (/\s/.test(query)) return null; // query 不能含空白
        return { at: at, query: query, pos: pos };
    }

    function filterCandidates(list, query) {
        if (!query) return list.slice(0, 50);
        var q = query.toLowerCase();
        return list.filter(function (u) {
            return (u.username && u.username.toLowerCase().indexOf(q) !== -1) ||
                   (u.nickname && u.nickname.toLowerCase().indexOf(q) !== -1);
        }).slice(0, 50);
    }

    function renderPopup(el, state) {
        var p = ensurePopup();
        p.innerHTML = '';
        if (!state.candidates.length) {
            // 显示"未找到匹配"占位，避免误以为组件没启动
            p.style.display = 'block';
            p.innerHTML = '<div style="padding:10px 14px;font-size:12px;color:#888;">未找到匹配用户</div>';
            return;
        }
        state.candidates.forEach(function (u, i) {
            var item = document.createElement('div');
            item.className = 'mention-item' + (i === state.active ? ' active' : '');
            // 弹窗展示：昵称（主）+ @username（副）；点击插入的 token 始终是 username
            item.innerHTML = avatarHtml(u) +
                '<span class="mention-name">' + escapeHtml(u.nickname || u.username) + '</span>' +
                '<span class="mention-uname">@' + escapeHtml(u.username) + '</span>';
            item.addEventListener('mousedown', function (e) {
                e.preventDefault();
                selectMention(el, state, u);
            });
            p.appendChild(item);
        });
        var rect = el.getBoundingClientRect();
        p.style.position = 'absolute';
        p.style.left = (rect.left + window.scrollX) + 'px';
        p.style.top = (rect.bottom + window.scrollY + 4) + 'px';
        p.style.width = Math.max(rect.width, 240) + 'px';
        p.style.display = 'block';
    }

    function selectMention(el, state, user) {
        var t = state.trigger;
        if (!t || !user) { hidePopup(); return; }
        // 插入的 token 始终用 username：①不受后续用户昵称修改影响；②老内容里的 @ 渲染后稳定显示
        var token = user.username;
        var insertText = '@' + token + ' ';
        var val = el.value;
        el.value = val.slice(0, t.at) + insertText + val.slice(t.pos);
        var caret = t.at + insertText.length;
        el.setSelectionRange(caret, caret);
        el.focus();
        state.trigger = null;
        hidePopup();
        // 触发 input 事件，便于其它监听（如实时校验）同步
        if (typeof el.dispatchEvent === 'function') {
            try { el.dispatchEvent(new Event('input', { bubbles: true })); } catch (e) {}
        }
    }

    // @提及服务端候选查询：单一接口 user/mentionCandidates，按 q 参数分流（后端三档策略）：
    //   - q 为空：关注列表；无关注时降级到最近活跃用户
    //   - q 纯数字：UID 精确查
    //   - q 其它：全站用户名/昵称模糊搜索
    //
    // URL 必须用 window.url('route', { q: q }) 让 query 编进 ?...&q=...，
    // 而**不能**用 '...?' + 'q=' 这种字符串拼——否则生成两个 ?，浏览器只解析前一个，
    // PHP $_GET 拿到 r=user/mentionCandidates?q=xxx（action 里带 ?）→ 方法不存在 → 404。
    function fetchCandidatesFromServer(q, cb) {
        if (typeof fetch !== 'function' || typeof url !== 'function') { cb([]); return; }
        var params = (q === '' || q == null) ? null : { q: q };
        var urlStr = url('user/mentionCandidates', params);
        fetch(urlStr, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (r) {
                var ct = r.headers.get('content-type') || '';
                if (r.ok && ct.indexOf('application/json') !== -1) return r.json();
                if (typeof console !== 'undefined' && console.error) {
                    console.error('[mention] user/mentionCandidates 接口返回异常', { url: urlStr, status: r.status, contentType: ct });
                }
                return { code: -1, data: { list: [] } };
            })
            .then(function (res) {
                var list = (res && res.code === 0 && res.data && res.data.list) ? res.data.list : [];
                cb(list);
            })
            .catch(function (err) {
                if (typeof console !== 'undefined' && console.error) {
                    console.error('[mention] user/mentionCandidates 网络错误', { url: urlStr, err: err });
                }
                cb([]);
            });
    }

    function initMention(el) {
        if (el.__mentionInited) return;
        el.__mentionInited = true;
        var state = { trigger: null, candidates: [], active: 0, list: [] };
        var queryTimer = null;
        // 初始拉一次候选（空 q 走关注列表或最近活跃兜底），保证刚开弹窗就有内容
        fetchCandidatesFromServer('', function (list) { state.list = list; });

        el.addEventListener('input', function () {
            var t = getTrigger(el);
            state.trigger = t;
            if (!t) { hidePopup(); return; }
            // 任何非空 q 都走服务端（避免本地 filter 空关注列表导致"@ 弹窗空"），单一接口自带 UID/字符分流
            if (t.query !== '') {
                if (queryTimer) clearTimeout(queryTimer);
                var query = t.query;
                queryTimer = setTimeout(function () {
                    fetchCandidatesFromServer(query, function (list) {
                        if (!state.trigger || state.trigger.query !== query) return; // 已变化，丢弃旧结果
                        state.candidates = list.slice(0, 50);
                        state.active = 0;
                        renderPopup(el, state);
                    });
                }, 250);
                return;
            }
            // q 为空：直接用初始拉到的关注/活跃列表（无需再请求）
            // 但如果 state.list 还是空的（初始 fetch 还没回来或失败），立即补一次空白 fetch
            if (!state.list || !state.list.length) {
                fetchCandidatesFromServer('', function (list) {
                    state.list = list;
                    // 防止用户已改 query 后才回调
                    if (!state.trigger || state.trigger.query !== '') return;
                    state.candidates = list.slice(0, 50);
                    state.active = 0;
                    renderPopup(el, state);
                });
                state.candidates = [];
                state.active = 0;
                renderPopup(el, state);
                return;
            }
            state.candidates = state.list.slice(0, 50);
            state.active = 0;
            renderPopup(el, state);
        });

        el.addEventListener('keydown', function (e) {
            if (!popupVisible() || !state.candidates.length) return;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                state.active = Math.min(state.active + 1, state.candidates.length - 1);
                renderPopup(el, state);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                state.active = Math.max(state.active - 1, 0);
                renderPopup(el, state);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                selectMention(el, state, state.candidates[state.active]);
            } else if (e.key === 'Escape') {
                hidePopup();
            }
        });

        el.addEventListener('blur', function () { setTimeout(hidePopup, 150); });
        // 滚动时关闭弹窗，避免错位
        window.addEventListener('scroll', function () { if (popupVisible()) hidePopup(); }, true);
    }

    function boot() {
        var els = document.querySelectorAll('[data-mention="1"]');
        for (var i = 0; i < els.length; i++) initMention(els[i]);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

    window.Mention = { init: initMention };
})();
