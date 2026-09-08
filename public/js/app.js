/* 论坛 - 前端交互脚本（ES5 兼容写法，无 emoji） */
(function() {
    'use strict';

    // 简单对象扩展（兼容 Object.assign）
    function extend(target, source) {
        if (!source) return target;
        for (var k in source) {
            if (source.hasOwnProperty(k)) target[k] = source[k];
        }
        return target;
    }

    // CSRF 令牌（兼容无 meta 的情况）
    var meta = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = meta ? meta.getAttribute('content') : '';

    // 获取 CSRF token（优先从隐藏 input，其次 meta 变量）
    window.getCsrfToken = function() {
        var input = document.querySelector('input[name="_token"]');
        return input ? input.value : csrfToken;
    };

    // AJAX 请求封装
    window.ajax = function(options) {
        var defaults = {
            method: 'GET',
            url: '',
            data: null,
            headers: {},
            success: function() {},
            error: function() {}
        };
        var opts = extend(defaults, options);

        var xhr = new XMLHttpRequest();
        xhr.open(opts.method, opts.url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        var token = window.getCsrfToken();
        if (token) xhr.setRequestHeader('X-CSRF-TOKEN', token);

        var data = opts.data;
        if (opts.method === 'POST' || opts.method === 'PUT' || opts.method === 'DELETE') {
            if (data && !(data instanceof FormData) && typeof data === 'object') {
                xhr.setRequestHeader('Content-Type', 'application/json');
                if (data._token === undefined && token) data._token = token;
                data = JSON.stringify(data);
            }
        }
        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4) return;
            if (xhr.status >= 200 && xhr.status < 300) {
                var res = xhr.responseText;
                var parsed = null;
                try { parsed = JSON.parse(res); } catch (e) { parsed = null; }
                // 服务端未返回 JSON（HTML 错误页 / 空 / OPcache 致命崩溃页）→ 构造兜底对象让回调看到原文
                if (!parsed || typeof parsed !== 'object') {
                    parsed = { code: 1, message: '[响应不是 JSON] ' + String(res).substring(0, 300) };
                    if (typeof console !== 'undefined') console.error('[ajax] non-JSON response:', res);
                }
                opts.success(parsed, xhr);
            } else {
                var err = { message: '请求失败 (' + xhr.status + ')' };
                try { err = JSON.parse(xhr.responseText); } catch (e) {}
                if (!err || typeof err !== 'object') err = { message: '请求失败 (' + xhr.status + ')' };
                // 404 时给具体提示 + 打印到 console，方便排查 nginx try_files / OPcache 问题
                if (xhr.status === 404) {
                    err.message = '接口不存在 (404)：请检查 nginx try_files 配置或确认 PHP 路由已生效';
                    if (typeof console !== 'undefined' && console.error) {
                        console.error('[ajax] 404 接口不存在', {
                            url: opts.url, method: opts.method,
                            responseSnippet: xhr.responseText ? xhr.responseText.slice(0, 200) : ''
                        });
                    }
                }
                opts.error(err, xhr);
            }
        };
        xhr.onerror = function() {
            opts.error({ message: '网络错误，请检查网络连接' }, xhr);
        };
        xhr.send(data);
    };

    // 简化 POST
    window.postJSON = function(url, data, success, error) {
        if (typeof success !== 'function') success = function() {};
        if (typeof error !== 'function') error = function(res) { window.toast(res && res.message ? res.message : '网络错误', 'error'); };
        window.ajax({ method: 'POST', url: url, data: data, success: success, error: error });
    };

    // 提示信息
    window.toast = function(message, type) {
        type = type || 'info';
        var div = document.createElement('div');
        div.style.cssText = 'position:fixed;top:70px;left:50%;transform:translateX(-50%);padding:10px 24px;border-radius:4px;color:#fff;z-index:9999;font-size:14px;box-shadow:0 2px 8px rgba(0,0,0,.15);transition:opacity .3s;';
        var colors = { info: '#1890ff', success: '#52c41a', error: '#f5222d', warning: '#fa8c16' };
        div.style.background = colors[type] || colors.info;
        div.textContent = message || '';
        document.body.appendChild(div);
        setTimeout(function() {
            div.style.opacity = '0';
            setTimeout(function() { if (div.parentNode) div.parentNode.removeChild(div); }, 300);
        }, 2500);
    };

    // 积分获取提示：按 earnAction() 返回的 {token, byte, extra, labels} 渲染。
    //   - labels 是 {currencyCode: '显示名'} 映射（后台改名后自动联动）
    //   - extra 承载自定义币种（如钻石 / 经验 等）的实际发放数额
    // 注意：earnAction 已按规则行 currency 字段发奖（前人踩坑：token/byte 是同一币种的两份，
    //   自定义币种走 extra，不写入 res.token/res.byte），所以这里只按 points 里"非零"项
    //   渲染即可，不会重复。
    window.showPointsToast = function(points) {
        if (!points) return;
        var labels = points.labels || {};
        // 兜底：后端未返回 labels（旧版本）时回退硬编码，避免「返 0 不显示」bug
        var labelOf = function(code, fallback) {
            return labels[code] || fallback || code;
        };
        var parts = [];
        // 1) 标准 token 字段（如基础规则发 token，或自定义币种正好用 token 代码）
        var t = parseInt(points.token, 10) || 0;
        if (t > 0) {
            // 若 points.token 实际承载的是自定义币种，则用 labels.token 的名；否则按 CURRENCY_TOKEN 显示
            parts.push('+' + t + ' ' + labelOf('token', 'Token'));
        }
        // 2) 标准 byte 字段（同上，可能是 byte 也可能是自定义币种）
        var b = parseInt(points.byte, 10) || 0;
        if (b > 0) parts.push('+' + b + ' ' + labelOf('byte', 'Byte'));
        // 3) 自定义币种（多个），每一份单独渲染
        if (points.extra && typeof points.extra === 'object') {
            Object.keys(points.extra).forEach(function(code) {
                var amt = parseInt(points.extra[code], 10) || 0;
                if (amt > 0) parts.push('+' + amt + ' ' + labelOf(code, code));
            });
        }
        if (parts.length === 0) {
            // 没有任何数额：检查是否后端给了 reasons（规则存在但被风控/未启用/重复）。
            // reasons 里已是中文文案（PointService::reasonText 转义过），直接展示即可。
            if (points.reasons && typeof points.reasons === 'object') {
                var rks = Object.keys(points.reasons);
                if (rks.length) {
                    var first = points.reasons[rks[0]];
                    var hint = (first === '规则未启用' || first === '规则金额为 0' || first === '规则不存在')
                        ? '请到后台「积分规则配置」启用并设置金额'
                        : first;
                    window.toast(hint, 'warning');
                }
            }
            return;
        }
        window.toast(parts.join(' · '), 'success');
    };

    // 确认弹窗
    window.confirmModal = function(title, body, onConfirm) {
        var modal = document.getElementById('confirmModal');
        if (!modal) {
            window.toast('页面缺少确认弹窗组件', 'error');
            return;
        }
        var titleEl = document.getElementById('modalTitle');
        var bodyEl = document.getElementById('modalBody');
        if (titleEl) titleEl.textContent = title || '确认操作';
        if (bodyEl) bodyEl.textContent = body || '';
        modal.classList.add('active');
        var btn = document.getElementById('modalConfirmBtn');
        if (!btn) return;
        // 防止重复绑定：克隆按钮
        var newBtn = btn.cloneNode(true);
        btn.parentNode.replaceChild(newBtn, btn);
        newBtn.addEventListener('click', function() {
            window.closeModal();
            if (onConfirm) onConfirm();
        });
    };
    window.closeModal = function() {
        var modal = document.getElementById('confirmModal');
        if (modal) modal.classList.remove('active');
    };

    // 生成 URL 辅助（与后端 url() 对应）
    //
    // **必须输出绝对 URL**。背景：上一版 `url()` 是 `(window.location.pathname).replace(/\/index\.php.*/, '') + '/index.php'`，
    // 当在 pretty URL 页面（/post/1-xxx、/c/5、/u/8）时，pathname 不会被正则匹配，base 被错拼成
    // `/post/1-xxx/index.php` 或 `/c/5/index.php` 等 → 全部 404（截图证据）。改用绝对 URL 后
    // 不再依赖当前 pathname 推导子目录，仅需识别「子目录前缀」。
    //
    // 同时抽出 _detectSubdir() 给 absoluteAssetUrl() / absoluteUploadUrl() 共用，
    // 解决「pretty URL 页面下用 `pathname.replace(/\/index\.php.*/,'') + '/public/'` 拼图片路径 → 404」的反模式。
    function _detectSubdir() {
        var pathname = window.location.pathname || '/';
        var markers = ['/post/', '/c/', '/u/', '/index.php', '/index.html'];
        var minIdx = -1;
        for (var i = 0; i < markers.length; i++) {
            var idx = pathname.indexOf(markers[i]);
            if (idx > 0 && (minIdx === -1 || idx < minIdx)) minIdx = idx;
        }
        return minIdx > 0 ? pathname.substring(0, minIdx) : '';
    }
    window._detectSubdir = _detectSubdir;

    window.url = function(route, params) {
        var subdir = _detectSubdir();

        // 2) 拼参数
        var query = { r: route };
        if (params) extend(query, params);
        var qs = Object.keys(query).map(function(k) { return k + '=' + encodeURIComponent(query[k]); }).join('&');

        // 3) 永远用绝对 URL（origin + 自动检测的子目录 + /index.php），不再被当前路径误导
        return window.location.origin + subdir + '/index.php?' + qs;
    };

    /**
     * 把站内 assets 相对路径（如 `css/style.css`、`uploads/post/x.jpg`）拼成 `<img src>/<link href>` 能直接用的绝对 URL。
     * 行为与后端 upload_url() / asset() 一致：origin + 子目录 + /public/ + relPath。
     * - 已是绝对 URL 原样返回；
     * - 已含 /public/ 前缀也会被规范化为单层 /public/，避免写成 `//public/...`。
     */
    window.absoluteAssetUrl = function(relPath) {
        if (!relPath) return '';
        if (/^https?:\/\//i.test(relPath)) return relPath;
        var cleanRel = String(relPath).replace(/^(\.?\/)?(public\/)?/i, '');
        return window.location.origin + _detectSubdir() + '/public/' + cleanRel;
    };
    // 旧名 alias，兼容老调用
    window.absoluteUploadUrl = window.absoluteAssetUrl;
    // 反模式 sanity check：
    //   永远不要写：window.location.pathname.replace(/\/index\.php.*/, '') + '/public/' + relPath
    //   在 pretty URL 页面会被拼成 "/post/1-xxx/public/uploads/..." → 404。

    // 点赞
    window.likePost = function(postId, btn) {
        window.ajax({
            method: 'POST',
            url: window.url('post/like', { id: postId }),
            data: {},
            success: function(res) {
                if (res.code === 0) {
                    btn.classList.toggle('active', !!res.data.liked);
                    var count = btn.querySelector('.count');
                    if (count && res.data.like_count !== undefined) count.textContent = res.data.like_count;
                    window.toast(res.message, 'success');
                } else {
                    window.toast(res.message || '操作失败', 'error');
                }
            },
            error: function(res) { window.toast(res.message || '网络错误', 'error'); }
        });
    };

    // 收藏
    window.collectPost = function(postId, btn) {
        window.ajax({
            method: 'POST',
            url: window.url('post/collect', { id: postId }),
            data: {},
            success: function(res) {
                if (res.code === 0) {
                    btn.classList.toggle('active', !!res.data.collected);
                    var count = btn.querySelector('.count');
                    if (count && res.data.collect_count !== undefined) count.textContent = res.data.collect_count;
                    window.toast(res.message, 'success');
                } else {
                    window.toast(res.message || '操作失败', 'error');
                }
            },
            error: function(res) { window.toast(res.message || '网络错误', 'error'); }
        });
    };

    // 关注用户
    window.followUser = function(userId, btn) {
        window.ajax({
            method: 'POST',
            url: window.url('user/follow', { id: userId }),
            data: {},
            success: function(res) {
                if (res.code === 0) {
                    if (res.data.followed) {
                        btn.textContent = '已关注';
                        btn.classList.add('btn-ghost');
                    } else {
                        btn.textContent = '关注';
                        btn.classList.remove('btn-ghost');
                    }
                    window.toast(res.message, 'success');
                } else {
                    window.toast(res.message || '操作失败', 'error');
                }
            },
            error: function(res) { window.toast(res.message || '网络错误', 'error'); }
        });
    };

    // 删除帖子
    window.deletePost = function(postId) {
        window.confirmModal('删除帖子', '确定要删除这篇帖子吗？删除后无法恢复。', function() {
            window.ajax({
                method: 'POST',
                url: window.url('post/delete', { id: postId }),
                data: { _method: 'DELETE', _token: window.getCsrfToken() },
                success: function(res) {
                    if (res.code === 0) {
                        window.toast('删除成功', 'success');
                        setTimeout(function() { window.location.href = window.url('home/index'); }, 800);
                    } else {
                        window.toast(res.message || '删除失败', 'error');
                    }
                },
                error: function(res) { window.toast(res.message || '网络错误', 'error'); }
            });
        });
    };

    // 删除评论
    window.deleteComment = function(commentId, item) {
        window.confirmModal('删除评论', '确定要删除这条评论吗？', function() {
            window.ajax({
                method: 'POST',
                url: window.url('post/deleteComment', { id: commentId }),
                data: { _token: window.getCsrfToken() },
                success: function(res) {
                    if (res.code === 0) {
                        if (item) item.remove();
                        window.toast('删除成功', 'success');
                    } else {
                        window.toast(res.message || '删除失败', 'error');
                    }
                },
                error: function(res) { window.toast(res.message || '网络错误', 'error'); }
            });
        });
    };

    // 举报
    window.reportItem = function(type, id) {
        var reason = prompt('请输入举报原因：');
        if (!reason) return;
        window.ajax({
            method: 'POST',
            url: window.url('home/report'),
            data: { target_type: type, target_id: id, reason: reason, _token: window.getCsrfToken() },
            success: function(res) {
                window.toast(res.message || (res.code === 0 ? '举报成功' : '举报失败'), res.code === 0 ? 'success' : 'error');
            },
            error: function(res) { window.toast(res.message || '网络错误', 'error'); }
        });
    };

    // 切换回复表单
    window.toggleReply = function(commentId) {
        var form = document.getElementById('replyForm-' + commentId);
        if (form) form.classList.toggle('active');
    };

    // 引用定位：点击评论里「被引用」预览块 → 直接跳到原评论所在分页（自动 window.location 跳转），不再 toast 打扰用户。
    //   - 锚点格式来自 _comment.php：'comment-<id>'（普通评论）/ 'statement-<id>'（辩论站队发言，站队发言跨页不保证可跳）
    //   - 跨分页：show.php 已注入 #quoteAnchorPatch 占位，并附带 data-locate-page=<pageNo>
    //     → 直接改 ?page=N + #comment-M 跳过去，无需用户手动翻页
    //   - 当前页且目标存在：scrollIntoView 居中 + 1.6s 品牌色高亮闪烁，便于视线跟随
    //   - 目标真不存在（评论被删 / 跨帖 / 站队发言锚点找不到）：fallback toast
    window.locateQuote = function(anchor) {
        if (!anchor) return;
        var target = document.getElementById(anchor);
        if (!target) {
            window.toast('原评论已删除，无法定位', 'warning');
            return;
        }
        // 跨分页补丁 anchor：show.php 灌了 data-locate-page，直接 window.location 跳过去
        if (target.getAttribute('data-missing-quote') === '1'
            || (target.closest && target.closest('#quoteAnchorPatch'))) {
            var page = parseInt(target.getAttribute('data-locate-page') || '0', 10);
            if (page > 0) {
                var u = new URL(window.location.href);
                var cur = parseInt(u.searchParams.get('page') || '1', 10) || 1;
                if (page !== cur) {
                    // 不同页：set page 参数 + hash 后整页跳转
                    u.searchParams.set('page', String(page));
                    u.hash = anchor;
                    window.location.href = u.toString();
                    return;
                }
                // 已经在目标页（理论上不应发生——除非 patch 与实际可见区有偏差），把 hash 设上去让浏览器滚
                u.hash = anchor;
                window.location.href = u.toString();
                return;
            }
            // 没拿到页码（跨帖或异常情况）：去 hash 让浏览器至少滚到位置（旧行为兜底）
            window.location.hash = anchor;
            return;
        }
        if (typeof target.scrollIntoView === 'function') {
            try {
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } catch (e) {
                target.scrollIntoView();
            }
        }
        // 移除旧闪烁再加新的，便于连续点不同引用也能看到动画
        target.classList.remove('quote-flash');
        // 强制 reflow 让浏览器重新跑动画（不加这一行，连续点击同一目标第二次无效）
        void target.offsetWidth;
        target.classList.add('quote-flash');
        setTimeout(function() { target.classList.remove('quote-flash'); }, 1800);

        // 60ms 后更新地址栏 hash：分两步是因为部分浏览器在 hash 改变时会强制跳到锚点，与 scrollIntoView 重复抖动
        // 我们已经把目标放到屏幕中央，再用 hash 让"复制链接 / 浏览器后退"也能回到原评论。
        setTimeout(function() {
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', '#' + anchor);
            } else {
                window.location.hash = anchor;
            }
        }, 60);
    };

    // 简易 Markdown / 富文本渲染
    window.renderContent = function(html) {
        return html;
    };

    // 图片上传预览
    window.previewImage = function(input, previewEl) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                if (typeof previewEl === 'string') previewEl = document.getElementById(previewEl);
                if (previewEl) {
                    var img = previewEl.querySelector('img');
                    if (!img) {
                        img = document.createElement('img');
                        previewEl.appendChild(img);
                    }
                    img.src = e.target.result;
                }
            };
            reader.readAsDataURL(input.files[0]);
        }
    };

    // 富文本编辑器简易工具栏
    window.insertFormat = function(textareaId, before, after) {
        var ta = document.getElementById(textareaId);
        if (!ta) return;
        var start = ta.selectionStart;
        var end = ta.selectionEnd;
        var sel = ta.value.substring(start, end);
        ta.value = ta.value.substring(0, start) + before + sel + (after || '') + ta.value.substring(end);
        ta.focus();
        ta.selectionStart = start + before.length;
        ta.selectionEnd = start + before.length + sel.length;
    };

    // ========== 顶栏消息图标下拉（通知/私信） ==========
    window.toggleMsgDropdown = function(e) {
        if (e) { e.preventDefault(); e.stopPropagation(); }
        var wrap = document.getElementById('navMsgWrap');
        if (!wrap) return;
        // 关闭头像下拉
        var avatarWrap = document.getElementById('avatarMenuWrap');
        if (avatarWrap) avatarWrap.classList.remove('open');
        wrap.classList.toggle('open');
    };
    // 点击外部 / ESC 关闭
    document.addEventListener('click', function(e) {
        var wrap = document.getElementById('navMsgWrap');
        if (!wrap) return;
        if (!wrap.contains(e.target)) wrap.classList.remove('open');
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            var wrap = document.getElementById('navMsgWrap');
            if (wrap) wrap.classList.remove('open');
        }
    });

    // ========== 通知/私信未读数实时轮询 ==========
    function setBadge(elId, count) {
        var el = document.getElementById(elId);
        if (!el) return;
        if (count > 0) {
            el.textContent = count > 99 ? '99+' : count;
            el.style.display = 'inline-block';
        } else {
            el.style.display = 'none';
        }
    }
    function updateMsgBadges() {
        ajax({ url: url('message/unreadSummary'), method: 'GET', success: function(res) {
            if (res && res.code === 0 && res.data) {
                var d = res.data;
                // 顶栏主红点：通知+私信总数
                var mainBadge = document.getElementById('navMsgBadge');
                if (mainBadge) {
                    var total = (d.notif || 0) + (d.pm || 0);
                    if (total > 0) {
                        mainBadge.innerHTML = '<span class="navbar-msg-badge">' + (total > 99 ? '99+' : total) + '</span>';
                    } else {
                        mainBadge.innerHTML = '';
                    }
                }
                setBadge('navNotifBadge', d.notif || 0);
                setBadge('navPmBadge', d.pm || 0);
            }
        }});
    }
    // 登录后页面才轮询
    if (document.getElementById('navMsgWrap')) {
        updateMsgBadges();
        setInterval(updateMsgBadges, 15000); // 15 秒轮询一次
    }
})();
