<?php /** 导航设置（tab：顶部导航 / 侧边导航 / 卡片导航） */ $title = '导航设置'; ?>
<?php
$navMenusSaved = json_decode($settings['nav_menus'] ?? '', true);
if (!is_array($navMenusSaved)) $navMenusSaved = ['top' => [], 'side' => []];
foreach (['top', 'side'] as $_pos) {
    if (!is_array($navMenusSaved[$_pos] ?? null)) $navMenusSaved[$_pos] = [];
    foreach ($navMenusSaved[$_pos] as &$_it) {
        if (!is_array($_it)) $_it = [];
        $_it = array_intersect_key($_it, array_flip(['name','icon','url']));
        // 服务端归一：历史 FA4 / FA5 / 裸名 / iconfont 都由 fa_icon_class() 输出为 FA6 标准类名（fa-solid fa-xxx / fa-brands fa-xxx）
        $_it['icon'] = fa_icon_class($_it['icon'] ?? '');
    }
    unset($_it);
}
$sideNavTitle = $settings['side_nav_title'] ?? '导航';

// 聚焦导航：{groups: [{title, icon, items: [{name, icon, url}]}]}
$cardNavSaved = json_decode($settings['card_nav'] ?? '', true);
if (!is_array($cardNavSaved)) $cardNavSaved = ['groups' => [], 'primary' => []];
if (!is_array($cardNavSaved['groups'] ?? null)) $cardNavSaved['groups'] = [];
if (!is_array($cardNavSaved['primary'] ?? null)) {
    $cardNavSaved['primary'] = [
        ['name' => '首页', 'icon' => 'fa-house', 'url' => url('home/index')],
        ['name' => '发布', 'icon' => 'fa-pen-to-square', 'url' => url('post/create')],
    ];
}
foreach ($cardNavSaved['groups'] as &$_g) {
    if (!is_array($_g)) $_g = ['title' => '', 'icon' => '', 'items' => []];
    $_g = [
        'title' => (string)($_g['title'] ?? ''),
        'icon'  => fa_icon_class((string)($_g['icon'] ?? '')),
        'items' => is_array($_g['items'] ?? null) ? array_values(array_filter($_g['items'], 'is_array')) : [],
    ];
    foreach ($_g['items'] as &$_ci) {
        $_ci = array_intersect_key($_ci, array_flip(['name','icon','url']));
        $_ci['icon'] = fa_icon_class($_ci['icon'] ?? '');
    }
    unset($_ci);
}
unset($_g);

/**
 * 提取图标"裸名"（不带 fa-solid/fa-regular/fa-brands/fa fa- 等前缀）用于客户端拼接预览。
 */
function _nav_icon_basename($cls) {
    $cls = trim((string)$cls);
    if ($cls === '') return '';
    // 客户端等价的归一：去掉所有 family 前缀词
    $clean = preg_replace('/\b(fa-solid|fa-regular|fa-light|fa-thin|fa-duotone|fa-brands|fas|far|fal|fat|fab|fa)\b/', ' ', $cls);
    $clean = trim(preg_replace('/\s+/', ' ', $clean));
    foreach (explode(' ', $clean) as $p) {
        $p = trim($p);
        if ($p === '') continue;
        if (strpos($p, 'fa-') === 0) return substr($p, 3);
        return $p;
    }
    return '';
}
?>
<form id="navMenusForm" onsubmit="return false;">
    <?= csrf_field() ?>

    <div class="admin-tabs" style="display:flex;gap:8px;margin-bottom:16px;">
        <button type="button" class="btn btn-sm ntab active" data-pane="top">顶部导航</button>
        <button type="button" class="btn btn-sm ntab" data-pane="side">侧边导航</button>
        <button type="button" class="btn btn-sm ntab" data-pane="card">聚焦导航</button>
    </div>

    <div class="npane" id="npane-top">
        <div class="card" style="margin-bottom:16px;">
            <div class="card-header">
                顶部导航
                <span style="font-size:12px;color:#888;margin-left:8px;">（留空则使用默认的 首页/热门/精选/发布；版式为「聚焦」时顶部导航不显示）</span>
            </div>
            <div class="card-body">
                <div id="navTopList"></div>
                <button type="button" class="btn btn-sm" onclick="addNavRow('top')" style="margin-top:8px;">+ 添加项</button>
            </div>
        </div>
    </div>

    <div class="npane" id="npane-side" style="display:none;">
        <div class="card" style="margin-bottom:16px;">
            <div class="card-header">侧边导航标题</div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">标题名称</label>
                    <input type="text" name="side_nav_title" class="form-control" value="<?= e($sideNavTitle) ?>" style="max-width:240px;" placeholder="导航">
                    <p class="form-hint">显示在左侧导航卡片顶部的标题文字，可自定义</p>
                </div>
            </div>
        </div>
        <div class="card" style="margin-bottom:16px;">
            <div class="card-header">
                侧边导航
                <span style="font-size:12px;color:#888;margin-left:8px;">（列表版式所有页面左侧显示；留空则不显示）</span>
            </div>
            <div class="card-body">
                <div id="navSideList"></div>
                <button type="button" class="btn btn-sm" onclick="addNavRow('side')" style="margin-top:8px;">+ 添加项</button>
            </div>
        </div>
    </div>

    <div class="npane" id="npane-card" style="display:none;">
        <div class="card" style="margin-bottom:16px;">
            <div class="card-header">
                聚焦导航
                <span style="font-size:12px;color:#ea6f5a;margin-left:8px;">仅前台版式设置为「聚焦」时生效（系统设置 → 版式设置）</span>
            </div>
            <div class="card-body">
                <p class="form-hint" style="margin-top:0;">聚焦导航：先添加分组（如「板块导航」，可配组图标），再在分组内添加子导航项（名称 + FA6 图标 + 链接）。配置后前台左侧以分组列表呈现，自动高亮当前页；留空则聚焦版式左侧只显示「首页 / 发布」。</p>
                <div class="form-label" style="margin-top:4px;">主入口（默认预留，可修改）</div>
                <div id="navCardPrimary"></div>
                <div class="form-label" style="margin-top:14px;">分组导航</div>
                <div id="navCardGroups"></div>
                <button type="button" class="btn btn-sm" onclick="addCardGroup(null)" style="margin-top:8px;">+ 添加分组</button>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px;">
        <div class="card-body">
            <button type="submit" class="btn btn-primary">保存导航设置</button>
        </div>
    </div>
</form>

<script>
var navTopSaved  = <?= json_encode($navMenusSaved['top'],  JSON_UNESCAPED_UNICODE) ?>;
var navSideSaved = <?= json_encode($navMenusSaved['side'], JSON_UNESCAPED_UNICODE) ?>;
var navCardGroupsSaved = <?= json_encode($cardNavSaved['groups'], JSON_UNESCAPED_UNICODE) ?>;
var navCardPrimarySaved = <?= json_encode($cardNavSaved['primary'], JSON_UNESCAPED_UNICODE) ?>;

// ===== tab 切换 =====
(function() {
    var tabs = document.querySelectorAll('.ntab');
    tabs.forEach(function(t) {
        t.addEventListener('click', function() {
            tabs.forEach(function(x) { x.classList.remove('active'); });
            t.classList.add('active');
            var p = t.getAttribute('data-pane');
            document.getElementById('npane-top').style.display  = (p === 'top')  ? 'block' : 'none';
            document.getElementById('npane-side').style.display = (p === 'side') ? 'block' : 'none';
            document.getElementById('npane-card').style.display = (p === 'card') ? 'block' : 'none';
        });
    });
})();

// 每个菜单项固定一行三项：名称 / 图标 / URL，全部视为自定义
// 预览 class 输出 FA6 风格（fa-solid fa-xxx / fa-brands fa-xxx 等），由输入值经客户端 normalize 得出
function _navCliClass(raw) {
    raw = (raw || '').trim();
    if (!raw) return '';
    // 0) 入口自愈：历史脏数据中残留的 fa--xxx / fa--fa-xxx 双横杠折叠为 fa-xxx，
    //    避免后续归一再产出 fa---xxx 三横杠。合法 fa-house / fa-weibo 不动。
    raw = raw.replace(/\bfa--(?:fa-)?/g, 'fa-');
    // 1) 提取家族
    var mFamily = raw.match(/\b(fa-solid|fa-regular|fa-light|fa-thin|fa-duotone|fa-brands|fas|far|fal|fat|fab)\b/);
    var family = mFamily ? mFamily[1] : '';
    // 2) 剥离 family + 独立的 fa
    //    关键：\bfa\b 不能写在 fa-solid 同一个分组里（会被误剥 fa-home 的 fa → 产出 fa--home 非法类名）。
    //    分拆两条匹配：① 长前缀优先（fa-solid/fa-brands/.../fas/far/fab...）  ② 独立成词的 fa 必须后跟空白或行尾。
    var clean = raw.replace(/\b(fa-solid|fa-regular|fa-light|fa-thin|fa-duotone|fa-brands|fas|far|fal|fat|fab)\b|\bfa\b(?=\s|$)/g, ' ').replace(/\s+/g, ' ').trim();
    var name = '';
    var parts = clean.split(' ');
    for (var i = 0; i < parts.length; i++) {
        var p = parts[i]; if (!p) continue;
        if (p.indexOf('fa-') === 0) { name = p.slice(3); break; }
        name = p; break;
    }
    if (!name) return '';
    // fa5 缩写 fas/far/fab/... 还原成 FA6 family
    var alias = { fas: 'fa-solid', far: 'fa-regular', fab: 'fa-brands', fal: 'fa-light', fat: 'fa-thin' };
    if (alias[family]) family = alias[family];
    if (family === 'fa' || family === '') family = ''; // 兜底默认 solid
    // 3) brand 嗅探：用户输入「fa-weibo」「fa-qq」时即便没写 family 前缀，也应识别为 fa-brands，
    //    避免被默认 solid 兜底。500+ 项品牌名与 helpers.php 内 $brandOldNames 保持同步。
    var brandNames = _navCliBrandNames();
    if (brandNames[name] && family === '') family = 'fa-brands';
    return (family || 'fa-solid') + ' fa-' + name;
}
// 与 helpers.php 内 $brandOldNames 同步的品牌白名单（用于客户端 brand 嗅探）。
// 上游列表 ~500 项；此处仅展开常用 + 截图中出现的几个；其余仍以 fa-solid 兜底（不会报错，FA6 不识别的类名只是不渲染图标而已）。
function _navCliBrandNames() {
    return {
        'weibo':'1','weixin':'1','qq':'1','wechat':'1','github':'1','google':'1','google-plus':'1','google-wallet':'1',
        'facebook':'1','facebook-f':'1','twitter':'1','instagram':'1','linkedin':'1','linkedin-in':'1','youtube':'1',
        'tumblr':'1','reddit':'1','telegram':'1','tiktok':'1','discord':'1','snapchat':'1','whatsapp':'1','vimeo':'1',
        'dribbble':'1','behance':'1','vk':'1','twitch':'1','flickr':'1','steam':'1','spotify':'1','amazon':'1','apple':'1',
        'microsoft':'1','android':'1','chrome':'1','firefox':'1','safari':'1','opera':'1','edge':'1','paypal':'1','stripe':'1',
        'slack':'1','line':'1','medium':'1','product-hunt':'1','stack-overflow':'1','stack-exchange':'1','codepen':'1',
        'gitlab':'1','jsfiddle':'1','symfony':'1','vuejs':'1','react':'1','angular':'1','node':'1','node-js':'1','npm':'1',
        'suse':'1','redhat':'1','ubuntu':'1','centos':'1','fedora':'1','debian':'1','mint':'1','windows':'1','playstation':'1',
        'xbox':'1','wikipedia-w':'1','apple-pay':'1','google-pay':'1','youtube-v':'1','telegram-plane':'1','telegram-square':'1',
        'soundcloud':'1','apple-music':'1','itunes':'1','itunes-note':'1','deezer':'1','mixcloud':'1','figma':'1','docker':'1',
        'drupal':'1','google-drive':'1','google-play':'1','hubspot':'1','imdb':'1','mastodon':'1','paypal':'1','tencent':'1',
        'zhihu':'1','weibo':'1','baidu':'1'
    };
}
function escAttr(s) { return (s || '').replace(/"/g, '&quot;').replace(/</g, '&lt;'); }
function bindIconPreview(input, target) {
    if (!input || !target) return;
    var sync = function() { target.className = _navCliClass(input.value); };
    input.addEventListener('input', sync);
    sync();
}

function navRowHtml(pos, item) {
    item = item || {name:'', icon:'', url:''};
    var html = '<div class="nav-row nav-row-grid" data-pos="' + pos + '">';
    html += '<input type="text" name="nav_' + pos + '[][name]" value="' + escAttr(item.name) + '" class="form-control" placeholder="菜单名称" required>';
    var previewCls = item.icon ? _navCliClass(item.icon) : '';
    html += '<span class="nav-icon-preview"><i class="' + previewCls + '" aria-hidden="true"></i></span>';
    html += '<input type="text" name="nav_' + pos + '[][icon]" value="' + escAttr(item.icon) + '" class="form-control" placeholder="FA6 类名（如 fa-solid fa-house / fa-brands fa-github）">';
    html += '<input type="text" name="nav_' + pos + '[][url]"  value="' + escAttr(item.url)  + '" class="form-control" placeholder="链接 URL（如 / 开头或完整 URL）" required>';
    html += '<button type="button" class="btn btn-sm btn-danger" onclick="this.closest(\'.nav-row\').remove()">删除</button>';
    html += '</div>';
    return html;
}

function addNavRow(pos, item) {
    var box = document.getElementById(pos === 'top' ? 'navTopList' : 'navSideList');
    var div = document.createElement('div');
    div.innerHTML = navRowHtml(pos, item);
    box.appendChild(div.firstChild);
    // 绑定图标实时预览
    var row = box.lastChild;
    var iconInput = row.querySelector('input[placeholder*="FA6"]');
    if (iconInput) {
        iconInput.addEventListener('input', function() {
            var cls = _navCliClass(this.value);
            var prev = this.parentNode.querySelector('.nav-icon-preview i');
            prev.className = cls;
        });
    }
}

// ===== 主入口（首页/发布，默认预留可修改；样式与分组编辑器一致） =====
(function initCardPrimary() {
    var box = document.getElementById('navCardPrimary');
    if (!box) return;
    var saved = (navCardPrimarySaved && navCardPrimarySaved.length >= 2) ? navCardPrimarySaved
        : [{name: '首页', icon: 'fa-house', url: <?= json_encode(url('home/index')) ?>},
           {name: '发布', icon: 'fa-pen-to-square', url: <?= json_encode(url('post/create')) ?>}];
    saved.slice(0, 2).forEach(function(it) {
        var row = document.createElement('div');
        row.className = 'cp-row';
        row.style.cssText = 'display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap;';
        row.innerHTML =
            '<span class="nav-icon-preview"><i class="' + (it.icon ? _navCliClass(it.icon) : '') + '" aria-hidden="true"></i></span>' +
            '<input type="text" data-f="cp_icon" value="' + escAttr(it.icon) + '" class="form-control" style="max-width:240px;" placeholder="图标 FA6 类名（如 fa-house）">' +
            '<input type="text" data-f="cp_name" value="' + escAttr(it.name) + '" class="form-control" style="max-width:160px;" placeholder="名称（必填）" required>' +
            '<input type="text" data-f="cp_url" value="' + escAttr(it.url) + '" class="form-control" placeholder="链接（必填）">';
        box.appendChild(row);
        bindIconPreview(row.querySelector('[data-f="cp_icon"]'), row.querySelector('.nav-icon-preview i'));
    });
})();

// ===== 卡片导航（分组编辑器） =====
function cardItemHtml(item) {
    item = item || {name:'', icon:'', url:''};
    var html = '<div class="nav-row nav-row-grid cg-item-row">';
    html += '<input type="text" data-f="ci_name" value="' + escAttr(item.name) + '" class="form-control" placeholder="子项名称" required>';
    var previewCls = item.icon ? _navCliClass(item.icon) : '';
    html += '<span class="nav-icon-preview"><i class="' + previewCls + '" aria-hidden="true"></i></span>';
    html += '<input type="text" data-f="ci_icon" value="' + escAttr(item.icon) + '" class="form-control" placeholder="FA6 类名（如 fa-solid fa-fire）">';
    html += '<input type="text" data-f="ci_url" value="' + escAttr(item.url) + '" class="form-control" placeholder="链接 URL（如 / 开头或完整 URL）" required>';
    html += '<button type="button" class="btn btn-sm btn-danger" onclick="this.closest(\'.cg-item-row\').remove()">删除</button>';
    html += '</div>';
    return html;
}
function addCardItemInto(itemsBox, item) {
    var div = document.createElement('div');
    div.innerHTML = cardItemHtml(item);
    itemsBox.appendChild(div.firstChild);
    var row = itemsBox.lastChild;
    bindIconPreview(row.querySelector('[data-f="ci_icon"]'), row.querySelector('.nav-icon-preview i'));
}
function addCardItem(btn) {
    var itemsBox = btn.closest('.card-group').querySelector('.cg-items');
    addCardItemInto(itemsBox, null);
}
function cardGroupHtml(g) {
    g = g || {title:'', icon:'', items:[]};
    var html = '<div class="card-group" style="border:1px solid #eee;border-radius:8px;padding:12px;margin-bottom:12px;">';
    // 组头：图标预览 + 图标输入 + 标题输入 + 删除
    html += '<div style="display:flex;gap:8px;align-items:center;margin-bottom:10px;flex-wrap:wrap;">';
    html += '<span class="nav-icon-preview cg-gicon"><i class="' + (g.icon ? _navCliClass(g.icon) : '') + '" aria-hidden="true"></i></span>';
    html += '<input type="text" data-f="cg_icon" value="' + escAttr(g.icon) + '" class="form-control" style="max-width:240px;" placeholder="分组图标 FA6 类名（可留空，如 fa-solid fa-fire）">';
    html += '<input type="text" data-f="cg_title" value="' + escAttr(g.title) + '" class="form-control" style="max-width:200px;" placeholder="分组标题（必填）" required>';
    html += '<button type="button" class="btn btn-sm btn-danger" style="margin-left:auto;" onclick="this.closest(\'.card-group\').remove()">删除分组</button>';
    html += '</div>';
    html += '<div class="cg-items"></div>';
    html += '<button type="button" class="btn btn-sm" onclick="addCardItem(this)">+ 添加子项</button>';
    html += '</div>';
    return html;
}
function addCardGroup(g) {
    var wrap = document.getElementById('navCardGroups');
    var div = document.createElement('div');
    div.innerHTML = cardGroupHtml(g);
    wrap.appendChild(div.firstChild);
    var grp = wrap.lastChild;
    bindIconPreview(grp.querySelector('[data-f="cg_icon"]'), grp.querySelector('.cg-gicon i'));
    (g && g.items ? g.items : []).forEach(function(it) { addCardItemInto(grp.querySelector('.cg-items'), it); });
}

function renderNav() {
    document.getElementById('navTopList').innerHTML = '';
    for (var i = 0; i < navTopSaved.length; i++) addNavRow('top', navTopSaved[i]);
    document.getElementById('navSideList').innerHTML = '';
    for (var j = 0; j < navSideSaved.length; j++) addNavRow('side', navSideSaved[j]);
    document.getElementById('navCardGroups').innerHTML = '';
    for (var k = 0; k < navCardGroupsSaved.length; k++) addCardGroup(navCardGroupsSaved[k]);
}
renderNav();

document.getElementById('navMenusForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var fd = new FormData(this);
    var data = {_token: getCsrfToken(), side_nav_title: ''};
    fd.forEach(function(v, k) { if (k === 'side_nav_title') data[k] = v; });
    var collect = function(pos) {
        var rows = document.querySelectorAll('.nav-row[data-pos="' + pos + '"]');
        var arr = [];
        for (var i = 0; i < rows.length; i++) {
            var r = rows[i];
            var get = function(name) {
                var el = r.querySelector('[name="nav_' + pos + '[][' + name + ']"]');
                return el ? el.value : '';
            };
            var name = get('name').trim();
            var url  = get('url').trim();
            if (name === '' || url === '') continue; // 必填项为空则跳过
            var rawIcon = get('icon').trim();
            // 归一为 FA6 风格（fa-solid fa-xxx / fa-brands fa-xxx 等）并保存
            var normIcon = _navCliClass(rawIcon);
            arr.push({name: name, icon: normIcon, url: url});
        }
        return arr;
    };
    data['nav_top']  = collect('top');
    data['nav_side'] = collect('side');
    // 收集主入口（首页/发布）
    var primary = [];
    document.querySelectorAll('#navCardPrimary .cp-row').forEach(function(r) {
        var n = ((r.querySelector('[data-f="cp_name"]') || {}).value || '').trim();
        var u = ((r.querySelector('[data-f="cp_url"]') || {}).value || '').trim();
        if (n === '' || u === '') return;
        var ic = ((r.querySelector('[data-f="cp_icon"]') || {}).value || '').trim();
        primary.push({name: n, icon: _navCliClass(ic), url: u});
    });
    data['card_primary'] = primary;

    // 收集卡片导航分组
    var groups = [];
    document.querySelectorAll('#navCardGroups .card-group').forEach(function(grp) {
        var title = (grp.querySelector('[data-f="cg_title"]') || {}).value || '';
        title = title.trim();
        if (title === '') return;
        var icon = ((grp.querySelector('[data-f="cg_icon"]') || {}).value || '').trim();
        var items = [];
        grp.querySelectorAll('.cg-item-row').forEach(function(r) {
            var n = ((r.querySelector('[data-f="ci_name"]') || {}).value || '').trim();
            var u = ((r.querySelector('[data-f="ci_url"]') || {}).value || '').trim();
            if (n === '' || u === '') return;
            items.push({name: n, icon: _navCliClass(((r.querySelector('[data-f="ci_icon"]') || {}).value || '').trim()), url: u});
        });
        groups.push({title: title, icon: _navCliClass(icon), items: items});
    });
    data['card_groups'] = groups;
    postJSON(url('admin/saveNavSettings'), data, function(res) {
        toast(res.message, res.code === 0 ? 'success' : 'error');
    });
});
</script>
