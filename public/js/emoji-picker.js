/* 论坛 - 表情选择器（全局组件，自动扫描 [data-emoji-trigger] 绑定）
 * [emoji-picker v3 — tab 折行兜底]
 *
 * 设计要点：
 *  - 数据来源：GET window.url('emoji/list')（EmojiController::list，公开接口，无需登录）
 *  - 渲染方式由 pack.type 决定：
 *      * image  → 用 pack.cd 模板（{code} 占位符）拼 item.image 得到 CDN/相对路径 <img>
 *      * unicode → 直接渲染 item.char（原生 emoji 或 owo 颜文字）
 *  - 插入：把 :code: 短代码钉进 textarea 光标处，由后端 render_emoji() 在展示时统一替换。
 *    这样 unicode / twemoji / owo / 自定义包 走同一条渲染管线，前台所见即所得。
 *  - 选区捕获：复用本站既有的「持续记录选区 + 不依赖失焦后 selectionStart」策略
 *    （失焦后 Chrome 会把 selectionStart 重置成 value.length，故在 mousedown 阶段抢记一次最准）。
 *
 * 接入方式（模板里加一个按钮即可，无需额外 JS）：
 *   <button type="button" class="emoji-trigger" data-emoji-trigger data-emoji-target="content"><i class="fa-regular fa-face-smile"></i> 表情</button>
 * 其中 data-emoji-target 是目标 textarea 的 id。
 */
/* 版本标识：方便排查"是不是浏览器加载了老版本 JS/CSS 导致 tab 行被裁" */
var EMOJI_PICKER_VERSION = 'v3-no-overlay-20260903c';
console.log('[emoji-picker] ' + EMOJI_PICKER_VERSION + ' loaded（输入框内显示 :code: 原文，overlay 已停用）');

(function () {
    'use strict';

    var CATEGORY_LABELS = {
        smile:  '笑脸与情感',
        hand:   '手势',
        animal: '动物',
        food:   '食物',
        object: '物品',
        symbol: '符号',
        face:   '颜文字',
        other:  '其它'
    };

    // 分类展示顺序（其余未知分类排到后面）
    var CATEGORY_ORDER = ['smile', 'hand', 'animal', 'food', 'object', 'symbol', 'face', 'other'];

    var PICKER_W = 340;
    var PICKER_H = 380;

    var state = {
        ta: null,                                  // 当前激活的 textarea
        sel: { start: 0, end: 0 },                 // 已捕获的光标/选区
        packs: null,                               // 缓存的 packs 数据
        codeMap: null,                             // code → 渲染好的 emoji HTML 片段（用于输入框 live overlay）
        loading: false,
        loaded: false,
        activePackIdx: 0,
        query: '',
        open: false
    };
    var pickerEl = null;
    var lastTouchTs = 0;                  // 最近一次 touchstart 时间戳，用于区分触屏/鼠标，避免吞掉 click
    var liveOverlays = [];                // 已绑过 live overlay 的 textarea/input（packs 加载完后统一同步）

    function clamp(n, lo, hi) { n = isNaN(n) ? 0 : n; return Math.max(lo, Math.min(hi, n)); }

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function escapeAttr(s) { return escapeHtml(s); }

    function apiUrl() {
        return (typeof window.url === 'function')
            ? window.url('emoji/list')
            : '/index.php?r=emoji%2Flist';
    }

    /* ---------- 选区捕获（关键，避免失焦后光标跳到末尾） ---------- */
    function captureSel() {
        var ta = state.ta;
        if (!ta) return;
        // 注意：仅在该 textarea 仍聚焦的事件里调用；绝不在 blur 里「兜底」读，
        // 否则会把失焦后被重置成末尾的 selectionStart 覆盖正确值。
        state.sel.start = ta.selectionStart;
        state.sel.end = ta.selectionEnd;
    }

    function bindTextarea(ta) {
        if (!ta || ta.__emojiBound) return;
        ta.__emojiBound = true;
        // 只在「聚焦态」事件里记录选区
        ['keydown', 'keyup', 'click', 'select', 'input', 'mouseup', 'focus'].forEach(function (evt) {
            ta.addEventListener(evt, captureSel);
        });
    }

    /* ---------- 数据加载 ---------- */
    function ensureData(cb) {
        if (state.loaded) { cb(); return; }
        if (state.loading) return;
        state.loading = true;
        var xhr = new XMLHttpRequest();
        xhr.open('GET', apiUrl(), true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function () {
            state.loading = false;
            try {
                var res = JSON.parse(xhr.responseText);
                if (res && res.code === 0 && res.data && res.data.packs) {
                    state.packs = res.data.packs;
                    state.codeMap = buildCodeMap();
                    state.loaded = true;
                    // packs 加载完成 → 所有已绑定 live overlay 的输入框立即同步一次
                    syncAllOverlays();
                }
            } catch (e) { /* ignore */ }
            cb();
        };
        xhr.onerror = function () { state.loading = false; cb(); };
        xhr.send();
    }

    /* ---------- 渲染 ---------- */
    function buildPicker() {
        if (pickerEl) return;
        var el = document.createElement('div');
        el.className = 'emoji-picker';
        el.id = 'emojiPicker';
        el.innerHTML =
            '<div class="emoji-picker-head">' +
                '<input type="text" class="emoji-search" placeholder="搜索表情（中文名 / 英文 code / 关键词）" />' +
                '<button type="button" class="emoji-close" title="关闭">×</button>' +
            '</div>' +
            '<div class="emoji-tabs"></div>' +
            '<div class="emoji-body"><div class="emoji-grid"></div></div>';
        document.body.appendChild(el);
        pickerEl = el;

        var search = el.querySelector('.emoji-search');
        search.addEventListener('input', function () {
            state.query = search.value.trim();
            renderGrid();
        });
        search.addEventListener('keydown', function (e) { e.stopPropagation(); });
        el.querySelector('.emoji-close').addEventListener('click', closePicker);
        // 点击面板内部不冒泡到 document（避免触发外部关闭）
        // 必须在 mousedown 和 click 两阶段都拦下：
        //   - mousedown 拦下：防止 textarea 失焦导致选区丢失
        //   - click 拦下：防止内部节点（如 tab 重建时旧节点被移除）触发 document 的"点击外部关闭"误判
        function stopInside(e) { e.stopPropagation(); }
        el.addEventListener('mousedown', stopInside);
        el.addEventListener('click', stopInside);
        // 表情项点击：mousedown 先拦下，防止 textarea 失焦影响选区
        el.querySelector('.emoji-grid').addEventListener('click', function (e) {
            var item = e.target.closest('.emoji-item');
            if (!item) return;
            insertEmoji(item.getAttribute('data-code'));
        });
    }

    /* 把名字截断到 6 个字符以内。CSS 还有 max-width+text-overflow:ellipsis 兜底，
     * 这里的目的是"少渲染字符 → 名片足够紧凑"。原名保留在 title 属性里，hover 可查。 */
    function truncatePackName(name, max) {
        max = max || 6;
        var s = String(name == null ? '' : name);
        if (s.length <= max) return s;
        return s.slice(0, max);
    }

    function renderTabs() {
        if (!pickerEl) return;
        var tabs = pickerEl.querySelector('.emoji-tabs');
        if (!state.packs || !state.packs.length) { tabs.innerHTML = ''; return; }
        var html = '';
        state.packs.forEach(function (p, i) {
            var cls = 'emoji-tab' + (i === state.activePackIdx ? ' active' : '');
            var fullName = String(p.name || '');
            var dispName = truncatePackName(fullName, 6);
            // title 保留原名（hover 可见全文），aria-label 同
            html += '<button type="button" class="' + cls + '" data-idx="' + i + '"' +
                    ' title="' + escapeAttr(fullName) + '" aria-label="' + escapeAttr(fullName) + '">' +
                    escapeHtml(dispName) + '</button>';
        });
        tabs.innerHTML = html;
        // 重新绑定（innerHTML 替换会丢失旧监听）
        Array.prototype.forEach.call(tabs.querySelectorAll('.emoji-tab'), function (btn) {
            btn.addEventListener('click', function () {
                state.activePackIdx = parseInt(btn.getAttribute('data-idx'), 10);
                renderTabs();
                renderGrid();
            });
        });
    }

    function itemHtml(pack, item) {
        var inner;
        if (pack.type === 'image' && pack.cd) {
            var url = String(pack.cd).replace('{code}', item.image || item.code || '');
            inner = '<img class="emoji-item-img" src="' + escapeAttr(url) + '" alt="' + escapeAttr(item.name) + '" loading="lazy">';
        } else if (item.char) {
            inner = '<span class="emoji-item-char">' + escapeHtml(item.char) + '</span>';
        } else {
            inner = '<span class="emoji-item-char">' + escapeHtml(':' + item.code + ':') + '</span>';
        }
        return '<button type="button" class="emoji-item" data-code="' + escapeAttr(item.code) + '" title="' + escapeAttr(item.name) + '">' + inner + '</button>';
    }

    function orderCategories(cats) {
        var seen = {};
        var ordered = [];
        CATEGORY_ORDER.forEach(function (c) { if (cats.indexOf(c) !== -1) { ordered.push(c); seen[c] = 1; } });
        cats.forEach(function (c) { if (!seen[c]) ordered.push(c); });
        return ordered;
    }

    function renderGrid() {
        if (!pickerEl) return;
        var grid = pickerEl.querySelector('.emoji-grid');
        if (!state.packs || !state.packs.length) {
            grid.innerHTML = '<div class="emoji-empty">暂无表情包，请到后台「表情管理」添加</div>';
            return;
        }

        // 搜索：跨所有包/分类匹配
        if (state.query) {
            var q = state.query.toLowerCase();
            var html = '';
            var any = false;
            state.packs.forEach(function (p) {
                var hits = (p.items || []).filter(function (it) {
                    var hay = ((it.name || '') + ' ' + (it.code || '') + ' ' + (it.keywords || '')).toLowerCase();
                    return hay.indexOf(q) !== -1;
                });
                if (!hits.length) return;
                any = true;
                html += '<div class="emoji-cat-title">' + escapeHtml(p.name) + '</div>';
                html += '<div class="emoji-row">' + hits.map(function (it) { return itemHtml(p, it); }).join('') + '</div>';
            });
            grid.innerHTML = any ? html : '<div class="emoji-empty">没有找到匹配的表情</div>';
            grid.scrollTop = 0;
            return;
        }

        // 正常：按当前激活包 + 分类分组
        var pack = state.packs[state.activePackIdx] || state.packs[0];
        var items = pack.items || [];
        if (!items.length) {
            grid.innerHTML = '<div class="emoji-empty">该表情包暂无表情</div>';
            return;
        }
        var byCat = {};
        items.forEach(function (it) {
            var c = it.category || 'other';
            (byCat[c] = byCat[c] || []).push(it);
        });
        var cats = orderCategories(Object.keys(byCat));
        var html2 = '';
        cats.forEach(function (c) {
            html2 += '<div class="emoji-cat-title">' + escapeHtml(CATEGORY_LABELS[c] || c) + '</div>';
            html2 += '<div class="emoji-row">' + byCat[c].map(function (it) { return itemHtml(pack, it); }).join('') + '</div>';
        });
        grid.innerHTML = html2;
        grid.scrollTop = 0;
    }

    /* ---------- 插入 ---------- */
    function insertEmoji(code) {
        var ta = state.ta;
        if (!ta || !code) return;
        var token = ':' + code + ':';
        var v = ta.value;
        var s = clamp(state.sel.start, 0, v.length);
        var e = clamp(state.sel.end, s, v.length);
        var keep = ta.scrollTop;
        ta.value = v.slice(0, s) + token + v.slice(e);
        var pos = s + token.length;
        ta.focus();
        try { ta.setSelectionRange(pos, pos); } catch (err) {}
        ta.scrollTop = keep;
        // 派发 input 事件：让 mention / 自适应高度 / 字数统计等监听器同步
        try { ta.dispatchEvent(new Event('input', { bubbles: true })); } catch (err) {
            try { ta.dispatchEvent(new Event('input')); } catch (e2) {}
        }
        // 【v3-fix-tabs-mobile 行为变更】插入表情后不再主动关闭 picker
        // 旧逻辑：选一个表情就 closePicker() → 用户想一次发"你好[笑脸][太阳]"就必须反复点开表情
        // 新规则：只要不点击外部区域就不会被关闭。×按钮、Esc、再次点触发器、点 picker 外仍能关闭。
        // 不在此 closePicker()。
    }

    /* ---------- 开 / 关 ---------- */
    function positionPicker(trigger) {
        if (!pickerEl || !trigger) return;
        var vv = window.visualViewport;
        // 窄屏（手机/平板竖屏）→ 底部抽屉，且必须抬到软键盘上方
        var isMobile = !!(window.matchMedia && window.matchMedia('(max-width: 768px)').matches);
        if (isMobile) {
            pickerEl.classList.add('emoji-picker-mobile');
            var bottomFixed = 0;
            if (vv) {
                // 可见区底部（layout 坐标）= offsetTop + height；抽屉 bottom 取「布局底 - 可见底」，刚好贴在键盘上方
                bottomFixed = Math.max(0, window.innerHeight - (vv.offsetTop + vv.height));
            }
            pickerEl.style.top = 'auto';
            pickerEl.style.left = '0';
            pickerEl.style.right = '0';
            pickerEl.style.bottom = bottomFixed + 'px';
            return;
        }
        pickerEl.classList.remove('emoji-picker-mobile');
        pickerEl.style.right = '';
        pickerEl.style.bottom = '';

        var rect = trigger.getBoundingClientRect();
        // 用 visualViewport 修正「键盘弹起后真实可见区域」，否则会被键盘挡住看不见
        // getBoundingClientRect 相对可视区，转成 fixed 坐标系需加上 visualViewport 偏移
        var dy = vv ? vv.offsetTop : 0;
        var dx = vv ? vv.offsetLeft : 0;
        var visTop = vv ? vv.offsetTop : 0;
        var visH = vv ? vv.height : window.innerHeight;
        var visLeft = vv ? vv.offsetLeft : 0;
        var visW = vv ? vv.width : window.innerWidth;

        var tTop = rect.top + dy;
        var tBottom = rect.bottom + dy;
        var tLeft = rect.left + dx;

        var top = tBottom + 6;
        var left = tLeft;

        // 下方空间不足（被键盘/屏幕底边挡住）→ 翻到按钮上方
        if (top + PICKER_H > visTop + visH) {
            top = tTop - PICKER_H - 6;
        }
        // 上方也不够 → 顶部贴边（CSS 已 max-height 限制高度）
        if (top < visTop + 8) {
            top = visTop + 8;
        }
        // 水平越界
        if (left + PICKER_W > visLeft + visW) {
            left = Math.max(visLeft + 8, visLeft + visW - PICKER_W - 8);
        }
        if (left < visLeft + 8) {
            left = visLeft + 8;
        }
        pickerEl.style.top = top + 'px';
        pickerEl.style.left = left + 'px';
    }

    function openPicker(trigger) {
        var ta = document.getElementById(trigger.getAttribute('data-emoji-target'));
        if (!ta) return;
        state.ta = ta;
        bindTextarea(ta);
        captureSel();                       // 兜底：当前聚焦态立刻记一次
        buildPicker();
        ensureData(function () {
            renderTabs();
            renderGrid();
            positionPicker(trigger);
            pickerEl.classList.add('open');
            state.open = true;
            var search = pickerEl.querySelector('.emoji-search');
            if (search) { search.value = ''; state.query = ''; }
        });
    }

    function closePicker() {
        if (pickerEl) pickerEl.classList.remove('open');
        state.open = false;
        state.ta = null;
    }

    /* ---------- 触发器绑定 ---------- */
    function bindTrigger(btn) {
        if (btn.__emojiTriggerBound) return;
        btn.__emojiTriggerBound = true;
        // mousedown 阶段 textarea 尚未失焦，是捕获选区最可靠的时机
        btn.addEventListener('mousedown', function (e) {
            captureSel();
            // 桌面端阻止默认抢焦点，保留 textarea 选区；
            // 触屏端（近 800ms 内有 touch）不阻止，否则部分浏览器会吞掉后续合成的 click → 表情弹不出来
            if (Date.now() - lastTouchTs > 800) e.preventDefault();
        });
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            if (state.open && state.ta === document.getElementById(btn.getAttribute('data-emoji-target'))) {
                closePicker();
            } else {
                openPicker(btn);
            }
        });
    }

    /* 【已关闭】输入框内实时 emoji 渲染（"透明 textarea + 镜像 div" overlay 方案）
     *
     * 2026-09-03 站长决定关闭。该方案存在**结构性缺陷，无法在现有 textarea 架构下根治**：
     *   textarea 是纯文本字段，字符流里 `:clown:` 占 7 个 ASCII 字符（≈ 3.85em）；
     *   而镜像层里渲染出的 emoji <img> 只占 1.25em。两条流宽度永远对不齐，只能二选一：
     *     ① emoji span 撑到 7 字符宽（ch 单位对齐）→ caret 位置正确，但 emoji 左右各留 ≈ 1.3em 空白
     *     ② emoji span 紧贴 1.25em → 排版紧凑，但 caret 视觉位置跳到 emoji 后 ≈ 2.6em 处
     *   历史上两种都试过，用户都不满意；此外透明文本 + 镜像层在 iOS / 部分安卓输入法下
     *   caret 显示、选区拖动、字数统计也会出现边缘异常。
     * 要根治必须把输入框换成 contenteditable 富文本编辑器，超出当前架构范围。
     *
     * 现行方案（与关闭前一致）：
     *   输入框内显示 `:code:` 原文 → 发送/提交后由后端 render_emoji() 渲染成表情图片。
     *
     * 开关置 false 后：init() 不再扫描 data-emoji-overlay，value setter 劫持也不再生效，
     * 即使模板里残留该属性也不会触发（兜底）。模板中的属性已同步移除。
     */
    var LIVE_OVERLAY_ENABLED = false;

    function init() {
        var triggers = document.querySelectorAll('[data-emoji-trigger]');
        Array.prototype.forEach.call(triggers, bindTrigger);
        // 输入框实时渲染 emoji：扫描所有标记 data-emoji-overlay 的 textarea/input
        if (!LIVE_OVERLAY_ENABLED) return;      // 已关闭，不再扫描
        var overlays = document.querySelectorAll('textarea[data-emoji-overlay], input[data-emoji-overlay]');
        Array.prototype.forEach.call(overlays, bindLiveOverlay);
    }

    // 全局：点面板外 / Esc 关闭
    // 关闭条件：
    //   1) 点击 picker 外部区域（textarea / 空白 / 其它控件）
    //   2) Esc 键
    // 注意 picker 内部的 mousedown/click 已 stopPropagation，不会冒泡到这里；
    // 因此能走到这个 handler 的 click 一定是 picker 外部的（触发器除外）。
    // 不能再依赖 pickerEl.contains(e.target)：切换表情包 tab 时 renderTabs 会 innerHTML 重建 tab，
    // 旧节点被移除，contains 误判为"外部"导致误关。改用"点击目标是触发器则跳过"。
    document.addEventListener('click', function (e) {
        if (!state.open || !pickerEl) return;
        // 点击触发器本身留给触发器自己的 toggle 逻辑处理，不在这里关
        if (e.target.closest && e.target.closest('[data-emoji-trigger]')) return;
        closePicker();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && state.open) closePicker();
    });
    // 键盘弹起/收起、旋转、滚动可视区 → 重新定位（保证手机端选择器始终在键盘上方可见）
    function repositionOpen() {
        if (!state.open || !pickerEl || !state.ta) return;
        var ta = state.ta;
        // 找到当前激活 textarea 对应的触发器
        var trigger = null;
        var triggers = document.querySelectorAll('[data-emoji-trigger]');
        for (var i = 0; i < triggers.length; i++) {
            if (triggers[i].getAttribute('data-emoji-target') === ta.id) { trigger = triggers[i]; break; }
        }
        if (!trigger) { closePicker(); return; }
        positionPicker(trigger);
    }
    window.addEventListener('resize', repositionOpen);
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', repositionOpen);
        window.visualViewport.addEventListener('scroll', repositionOpen);
    }
    // 记录触屏时间戳（用于 mousedown 是否阻止默认的判断）
    document.addEventListener('touchstart', function () { lastTouchTs = Date.now(); }, { passive: true });

    /* ---------- 输入框实时渲染 emoji（textarea/input live overlay） ----------
     *
     * 背景：textarea/input 是纯文本字段，无法直接渲染 HTML（emoji 是 <img>，无法塞进 textarea）。
     * 方案：在输入框外裹一层 `.emoji-input-wrap` 相对容器，新增一个同步镜像 `.emoji-input-mirror`
     *       绝对定位叠在输入框上，渲染"原始文本 + :code: → <img>/unicode"的内容；
     *       输入框文本设为 transparent（保留 caret-color），用户看到的就是"实时 emoji 渲染"效果。
     *
     * 关键细节：
     *   - 镜像必须与输入框用相同的 font/padding/line-height/box-sizing/whitespace，
     *     否则两层的换行/折行位置错位；
     *   - `:code:` 在镜像中用一个占位 span 包：可见的透明文本 ":clown:" 撑出与 textarea 一致的宽度，
     *     <img> 绝对定位居中。这样后续文字的折行位置在两层完全相同，不会"看到代码在 A 行、表情在 B 行"。
     *   - packs 未加载时输入框保持可见（不透明），packs 加载并首次同步后切到 transparent。
     *   - SPA 换 DOM 后 EmojiPicker.init() 须被调用重绑（与 trigger 重绑共用一条入口）。
     */
    function buildCodeMap() {
        var map = {};
        if (!state.packs) return map;
        state.packs.forEach(function (p) {
            (p.items || []).forEach(function (it) {
                var replacement;
                if (p.type === 'image' && p.cd) {
                    var url = String(p.cd).replace('{code}', it.image || it.code || '');
                    replacement = '<img class="emoji-inline" src="' + url.replace(/"/g, '&quot;') + '" alt="">';
                } else if (it.char) {
                    replacement = escapeHtml(it.char);
                } else {
                    return;
                }
                map[it.code] = replacement;
            });
        });
        return map;
    }

    function renderTextForMirror(value) {
        var map = state.codeMap || {};
        var parts = String(value == null ? '' : value).split(/(:[a-zA-Z0-9_]+:)/g);
        return parts.map(function (p) {
            if (/^:[a-zA-Z0-9_]+:$/.test(p)) {
                var code = p.slice(1, -1);
                if (map[code] != null) {
                    // 紧凑内联：emoji span 自身 1.25em 宽，不撑 :code: 原字符宽（间距大根因）。
                    // 接受 textarea 字符流（:clown: 占 7 字符 ≈ 5-7em）与 mirror 文字流（1.25em）
                    // 宽度不对齐 → 视觉上 textarea 透明后右侧多余空间不可察，但 emoji 紧贴显示。
                    return '<span class="emoji-slot">' + map[code] + '</span>';
                }
            }
            return escapeHtml(p);
        }).join('');
    }

    function syncMirrorStyle(ta, mirror) {
        var cs = window.getComputedStyle(ta);
        mirror.style.fontFamily = cs.fontFamily;
        mirror.style.fontSize = cs.fontSize;
        mirror.style.fontWeight = cs.fontWeight;
        mirror.style.fontStyle = cs.fontStyle;
        mirror.style.lineHeight = cs.lineHeight;
        mirror.style.letterSpacing = cs.letterSpacing;
        mirror.style.padding = cs.padding;
        mirror.style.textAlign = cs.textAlign;
        mirror.style.boxSizing = 'border-box';
        mirror.style.background = 'transparent';
        // border 设为 transparent 占位（避免镜像框与 textarea 框错位）；textarea 自带 border 会盖在镜像上
        mirror.style.borderStyle = cs.borderStyle;
        mirror.style.borderWidth = cs.borderWidth;
        mirror.style.borderColor = 'transparent';
        mirror.style.borderRadius = cs.borderRadius;
        // textarea 多行换行；input 单行不换行
        if (ta.tagName === 'INPUT') {
            mirror.style.whiteSpace = 'pre';
            mirror.style.overflow = 'hidden';
        } else {
            mirror.style.whiteSpace = 'pre-wrap';
            mirror.style.wordWrap = 'break-word';
            mirror.style.overflowWrap = 'break-word';
            mirror.style.overflow = 'hidden';
        }
    }

    /* 关键：让 mirror 的几何精确贴合 textarea 自身的 bounding rect，而不是 wrap 的整个框。
     * 解决「emoji 跑到 textarea 边界外（如 toolbar 上方）」的溢出 bug——
     * wrap 可能含其它元素（错误提示、隐藏字段等）导致 wrap 高度 > textarea 高度，
     * 旧版 mirror 用 top:0/bottom:0 覆盖全 wrap → emoji 浮到 textarea 外。
     * 新版：mirror 的 top/left/width/height 全部由 JS 实时同步 textarea getBoundingClientRect()。 */
    function syncMirrorGeometry(ta, wrap, mirror) {
        if (!ta || !wrap || !mirror) return;
        var tr = ta.getBoundingClientRect();
        var wr = wrap.getBoundingClientRect();
        if (tr.width === 0 || tr.height === 0) return;     // 隐藏元素，跳过
        mirror.style.position = 'absolute';
        mirror.style.top = (tr.top - wr.top) + 'px';
        mirror.style.left = (tr.left - wr.left) + 'px';
        mirror.style.width = tr.width + 'px';
        mirror.style.height = tr.height + 'px';
        // 不再依赖 bottom: 0 / right: 0（已被 JS 设值覆盖）
        mirror.style.right = 'auto';
        mirror.style.bottom = 'auto';
    }

    function bindLiveOverlay(ta) {
        if (!LIVE_OVERLAY_ENABLED) return;      // 已关闭（见 LIVE_OVERLAY_ENABLED 注释）
        if (!ta || ta.__emojiOverlayBound) return;
        // 必须是 textarea 或 input[type=text]
        var tag = ta.tagName;
        if (tag !== 'TEXTAREA' && !(tag === 'INPUT' && (!ta.type || ta.type === 'text'))) return;

        ta.__emojiOverlayBound = true;
        var wrap = ta.parentNode;
        // 若不是相对定位容器，给父级加 wrap class（也作为 ready 切换 hook）
        if (!wrap.classList.contains('emoji-input-wrap')) {
            wrap.classList.add('emoji-input-wrap');
        }

        // 创建镜像 div，插在 textarea 之前
        var mirror = document.createElement('div');
        mirror.className = 'emoji-input-mirror';
        syncMirrorStyle(ta, mirror);
        syncMirrorGeometry(ta, wrap, mirror);        // ← 关键：精确贴合 textarea 边界
        // 初始镜像内容先放原文本转义（与 textarea 一致），packs 加载后再渲染 emoji
        mirror.innerHTML = escapeHtml(ta.value);
        wrap.insertBefore(mirror, ta);

        // 一次性主动同步函数：所有「程序改 value」后都必须调一次
        function syncMirrorNow() {
            if (!state.loaded) {
                mirror.innerHTML = escapeHtml(ta.value);
                return;
            }
            if (!state.codeMap) state.codeMap = buildCodeMap();
            mirror.innerHTML = renderTextForMirror(ta.value);
        }
        ta.__syncMirror = syncMirrorNow;       // 暴露给外部代码（如 sendMessage 清空）

        // —— 关键：劫持 value setter ——
        // 原生 HTMLTextAreaElement.value 是原生 setter，赋值不会派 input 事件
        // （仅"用户键入"才会派 input），导致 sendMessage(msgInput.value='') 后 mirror 没同步仍渲染旧表情。
        // 这里包一层 setter：任何程序赋值都同步 mirror + 派发 input 事件，让所有监听器都触发。
        if (!ta.__emojiValueSetterHijacked) {
            ta.__emojiValueSetterHijacked = true;
            var proto = tag === 'TEXTAREA' ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
            var nativeDesc = Object.getOwnPropertyDescriptor(proto, 'value');
            var nativeSetter = nativeDesc.set;
            var nativeGetter = nativeDesc.get;
            Object.defineProperty(ta, 'value', {
                configurable: true,
                enumerable: true,
                get: function () { return nativeGetter.call(this); },
                set: function (v) {
                    nativeSetter.call(this, v);
                    // 同步 mirror
                    if (typeof this.__syncMirror === 'function') this.__syncMirror();
                    // 派发 input 事件（让 mention/字数/自适应高度等监听器都跟上）
                    try { this.dispatchEvent(new Event('input', { bubbles: true })); } catch (e) {}
                }
            });
        }

        // 监听输入事件，实时同步
        ta.addEventListener('input', syncMirrorNow);

        // ResizeObserver 监听 textarea 大小变化（wrap resize / window resize / 移动设备键盘弹起收起）
        if (typeof ResizeObserver !== 'undefined') {
            try {
                var ro = new ResizeObserver(function () {
                    syncMirrorStyle(ta, mirror);
                    syncMirrorGeometry(ta, wrap, mirror);
                });
                ro.observe(ta);
                ro.observe(wrap);
            } catch (e) {}
        }
        // wrap 自身或祖先滚动时（如固定底部输入框 + 消息列表内部滚动）也需重定位
        function onScroll() { syncMirrorGeometry(ta, wrap, mirror); }
        window.addEventListener('scroll', onScroll, true);
        window.addEventListener('resize', onScroll);

        liveOverlays.push(ta);
        // 如果 packs 已经加载过，立即同步一次
        if (state.loaded) {
            syncMirrorNow();
            wrap.classList.add('is-ready');
        }
    }

    function syncAllOverlays() {
        if (!LIVE_OVERLAY_ENABLED) return;      // 已关闭（见 LIVE_OVERLAY_ENABLED 注释）
        if (!state.codeMap) state.codeMap = buildCodeMap();
        liveOverlays.forEach(function (ta) {
            var wrap = ta.parentNode;
            var mirror = wrap && wrap.querySelector ? wrap.querySelector('.emoji-input-mirror') : null;
            if (!mirror) return;
            syncMirrorStyle(ta, mirror);
            syncMirrorGeometry(ta, wrap, mirror);
            mirror.innerHTML = renderTextForMirror(ta.value);
            wrap.classList.add('is-ready');
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // 暴露给需要手动控制的场景
    window.EmojiPicker = {
        init: init,
        open: openPicker,
        close: closePicker,
        renderText: function (v) { if (!state.codeMap) state.codeMap = buildCodeMap(); return renderTextForMirror(v); },
        // 外部代码（如 sendMessage）清空 textarea 后可手动同步：
        //   document.getElementById('msgInput').value = ''; EmojiPicker.refreshMirror(document.getElementById('msgInput'));
        // 注意：现在 value setter 已自动劫持，理论上不需要再调。但保留作为显式 hook。
        refreshMirror: function (ta) {
            if (ta && typeof ta.__syncMirror === 'function') ta.__syncMirror();
        }
    };

    // iOS Safari BFCache：从其他页面后退回来时缓存仍带着旧状态，触发重绑
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) setTimeout(init, 0);
    });

    // 单页应用导航：URL hash 变化/自定义事件触发时也重绑一遍（覆盖未来 SPA 路由切换）
    window.addEventListener('hashchange', init);
})();
