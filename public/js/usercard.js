/**
 * 全站 hover 用户名片
 * 扫描所有 <a data-uid>：帖子内页作者（含楼中楼）、@ 高亮用户名、正文里的用户名卡片，
 * 鼠标滑过时懒加载并弹出用户名片（样式参考个人中心，精简改造）。
 * 依赖 app.js 暴露的全局 url()。
 */
(function () {
    'use strict';
    if (typeof url !== 'function') return; // 依赖 app.js

    var pop = null;
    var cache = {};
    var hideTimer = null;
    var curUid = null;
    var curEl = null;

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function escapeAttr(s) { return escapeHtml(s); }

    function fmt(n) {
        n = parseInt(n, 10) || 0;
        if (n >= 100000000) return (n / 100000000).toFixed(1) + '亿';
        if (n >= 10000) return (n / 10000).toFixed(1) + '万';
        if (n >= 1000) return (n / 1000).toFixed(1) + 'k';
        return String(n);
    }

    function ensurePop() {
        if (pop) return pop;
        pop = document.createElement('div');
        pop.className = 'user-popover';
        pop.style.display = 'none';
        pop.setAttribute('role', 'tooltip');
        document.body.appendChild(pop);
        pop.addEventListener('mouseenter', function () { if (hideTimer) clearTimeout(hideTimer); });
        pop.addEventListener('mouseleave', function () { scheduleHide(); });
        // 点击 popover 内部 <a> 进入个人主页时立即关闭，避免跳转后短暂留白
        // （后续 navigate 同步触发，不必等 200ms scheduleHide）
        pop.addEventListener('click', function (e) {
            var a = e.target.closest ? e.target.closest('a') : null;
            if (a) pop.style.display = 'none';
        });
        return pop;
    }

    function buildCardHtml(d) {
        var avatar;
        if (d.avatar) {
            avatar = '<img class="up-avatar" src="' + escapeAttr(d.avatar) + '" alt="">';
        } else {
            var initial = escapeHtml((d.nickname || d.username || '?').charAt(0));
            avatar = '<span class="up-avatar up-avatar-text">' + initial + '</span>';
        }

        // 行内描述三段：昵称+认证 / 等级·角色 / 签名
        var nameLine = '' +
            '<span class="up-name">' + escapeHtml(d.nickname || d.username) + '</span>' +
            (d.certBadgesHtml ? '<span class="up-certs">' + d.certBadgesHtml + '</span>' : '');
        var levelText = (d.level > 0 ? ('Lv.' + d.level + (d.levelName ? ' ' + escapeHtml(d.levelName) : '')) : 'Lv.0');
        var roleText  = d.roleName ? escapeHtml(d.roleName) : '';
        var metaLine  = roleText ? (levelText + '<span class="up-dot">·</span>' + roleText) : levelText;
        var bioText   = d.bio ? escapeHtml(d.bio) : '这家伙很懒，什么也没留下';

        // 上半部分：整片不再被一个 <a> 全包，否则「私信」按钮就成了嵌套 <a>。
        // 拆成左侧 <a class="up-info-link">（点进个人主页）+ 右侧 <div class="up-side">（独立），
        // hover 整片的浅色响应通过 .up-top:hover .up-info-link 触发。
        var infoBlock = '' +
            '<a class="up-info-link" href="' + escapeAttr(d.profileUrl) + '">' +
                avatar +
                '<div class="up-info">' +
                    '<div class="up-row1">' + nameLine + '</div>' +
                    '<div class="up-row2">' + metaLine + '</div>' +
                    '<div class="up-row3">' + bioText + '</div>' +
                '</div>' +
            '</a>';

        // 右侧独立列：UID 徽章 + 私信按钮（私信仅在「非本人」且后端返了 pmUrl 时显示）
        var pmBtn = (d.pmUrl && !d.isOwn)
            ? '<a class="up-pm-btn" href="' + escapeAttr(d.pmUrl) + '">私信</a>' : '';
        var sideBlock = '' +
            '<div class="up-side">' +
                (d.uidBadgeHtml ? '<span class="up-uid">' + d.uidBadgeHtml + '</span>' : '') +
                pmBtn +
            '</div>';

        var topHalf = '<div class="up-top">' + infoBlock + sideBlock + '</div>';

        // 下半部分：5 列统计 —— 获赞 / 主题 / 回帖 / 关注 / 粉丝（参考个人主页 tabs）
        var stats = '' +
            '<div class="up-stats">' +
                '<div class="up-stat"><b>' + fmt(d.likeCount)      + '</b><span>获赞</span></div>' +
                '<div class="up-stat"><b>' + fmt(d.postCount)      + '</b><span>主题</span></div>' +
                '<div class="up-stat"><b>' + fmt(d.commentCount)   + '</b><span>回帖</span></div>' +
                '<div class="up-stat"><b>' + fmt(d.followingCount) + '</b><span>关注</span></div>' +
                '<div class="up-stat"><b>' + fmt(d.followerCount)  + '</b><span>粉丝</span></div>' +
            '</div>';

        return topHalf + stats;
    }

    function position(el) {
        var p = ensurePop();
        var rect = el.getBoundingClientRect();
        var pw = p.offsetWidth, ph = p.offsetHeight;
        var vw = window.innerWidth, vh = window.innerHeight;
        var left = rect.left + window.scrollX;
        var top = rect.bottom + window.scrollY + 8;
        if (left + pw > vw + window.scrollX - 8) left = vw + window.scrollX - 8 - pw;
        if (left < window.scrollX + 8) left = window.scrollX + 8;
        // 下方空间不足则翻到上方
        if (rect.bottom + ph + 8 > vh) {
            top = rect.top + window.scrollY - ph - 8;
            if (top < window.scrollY + 8) top = window.scrollY + 8;
        }
        p.style.left = left + 'px';
        p.style.top = top + 'px';
    }

    function render(uid, el) {
        var p = ensurePop();
        p.innerHTML = '<div class="up-loading">加载中…</div>';
        position(el);
        p.style.display = 'block';
        if (cache[uid]) {
            p.innerHTML = buildCardHtml(cache[uid]);
            position(el);
            return;
        }
        var reqUrl = url('user/card', { id: uid });
        fetch(reqUrl, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (r) {
                var ct = r.headers.get('content-type') || '';
                if (r.ok && ct.indexOf('application/json') !== -1) return r.json();
                throw new Error('bad response');
            })
            .then(function (res) {
                if (res && res.code === 0 && res.data) {
                    cache[uid] = res.data;
                    if (curUid === uid && pop.style.display !== 'none') {
                        pop.innerHTML = buildCardHtml(res.data);
                        position(el);
                    }
                } else {
                    pop.style.display = 'none';
                }
            })
            .catch(function () { pop.style.display = 'none'; });
    }

    function scheduleHide() {
        if (hideTimer) clearTimeout(hideTimer);
        hideTimer = setTimeout(function () {
            if (pop) pop.style.display = 'none';
            curUid = null;
            curEl = null;
        }, 200);
    }

    function showFor(el) {
        var uid = el.getAttribute('data-uid');
        if (!uid) return;
        if (hideTimer) clearTimeout(hideTimer);
        if (curUid === uid && pop && pop.style.display !== 'none' && curEl === el) return;
        curUid = uid;
        curEl = el;
        render(uid, el);
    }

    document.addEventListener('mouseover', function (e) {
        var a = e.target.closest ? e.target.closest('a[data-uid]') : null;
        if (!a) return;
        showFor(a);
    });
    document.addEventListener('mouseout', function (e) {
        var a = e.target.closest ? e.target.closest('a[data-uid]') : null;
        if (!a) return;
        scheduleHide();
    });
    // 滚动 / 缩放时隐藏，避免错位
    window.addEventListener('scroll', function () {
        if (pop && pop.style.display !== 'none') pop.style.display = 'none';
    }, true);
    window.addEventListener('resize', function () {
        if (pop) pop.style.display = 'none';
    });
})();
