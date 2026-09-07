<?php /** 页脚设置 - 仅站长 */ $title = '页脚设置'; ?>
<style>
/* 页脚设置页内联样式（避免污染全局 style.css） */
.fs-card{margin-bottom:16px;}
.fs-section-title{display:flex;align-items:center;gap:8px;font-size:14px;font-weight:600;color:#444;margin-bottom:12px;padding-bottom:8px;border-bottom:1px solid #f0f0f0;}
.fs-section-title .num{width:22px;height:22px;border-radius:50%;background:#ea6f5a;color:#fff;font-size:12px;display:inline-flex;align-items:center;justify-content:center;}

/* 4 栏菜单编辑 */
.fs-col-block{background:#fafafa;border:1px solid #eee;border-radius:6px;padding:14px;margin-bottom:12px;}
.fs-col-head{display:flex;gap:10px;align-items:center;margin-bottom:10px;}
.fs-col-head .col-title-input{flex:1;padding:6px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px;}
.fs-col-head .btn-del-col{padding:4px 10px;font-size:12px;background:#fff;color:#c2410c;border:1px solid #fecaca;border-radius:4px;cursor:pointer;}
.fs-col-head .btn-del-col:hover{background:#fff5f5;}
.fs-link-row{display:flex;gap:8px;align-items:center;margin-bottom:6px;}
.fs-link-row input{padding:5px 8px;border:1px solid #ddd;border-radius:4px;font-size:12px;}
.fs-link-row .link-name{width:30%;}
.fs-link-row .link-url{flex:1;}
.fs-link-row .btn-del-link{padding:3px 8px;font-size:11px;background:#fff;color:#999;border:1px solid #eee;border-radius:3px;cursor:pointer;}
.fs-link-row .btn-del-link:hover{color:#c2410c;border-color:#fecaca;}
.fs-add-link-btn{padding:5px 12px;font-size:12px;background:#fff;color:#ea6f5a;border:1px dashed #ea6f5a;border-radius:4px;cursor:pointer;margin-top:4px;}
.fs-add-link-btn:hover{background:#fff0eb;}
.fs-add-link-btn[disabled]{color:#bbb;border-color:#ddd;cursor:not-allowed;background:#fafafa;}

.fs-add-col-btn{width:100%;padding:10px;background:#fff;color:#ea6f5a;border:1px dashed #ea6f5a;border-radius:6px;cursor:pointer;font-size:13px;}
.fs-add-col-btn:hover{background:#fff0eb;}

/* 社交媒体 */
.fs-social-row{display:flex;gap:10px;align-items:center;padding:10px;background:#fafafa;border:1px solid #eee;border-radius:6px;margin-bottom:8px;flex-wrap:wrap;}
.fs-social-row select,.fs-social-row input{padding:5px 8px;border:1px solid #ddd;border-radius:4px;font-size:12px;}
.fs-social-row .icon-select{width:130px;}
.fs-social-row .value-input{width:280px;}
.fs-social-row .title-input{width:120px;}
.fs-social-row .type-radios{display:flex;gap:6px;align-items:center;}
.fs-social-row .type-radios label{font-size:12px;color:#666;display:inline-flex;align-items:center;gap:3px;cursor:pointer;}
.fs-social-row .upload-btn{padding:4px 10px;font-size:11px;background:#fff;color:#1a73e8;border:1px solid #d6e4ff;border-radius:3px;cursor:pointer;}
.fs-social-row .upload-btn:hover{background:#f0f7ff;}
/* 二维码预览容器：含小图（默认显示）+ hover 大图浮层（仅二维码类型渲染） */
.fs-social-row .qr-preview-box{position:relative;display:inline-block;}
.fs-social-row .qr-preview{width:36px;height:36px;border:1px solid #eee;border-radius:3px;object-fit:cover;cursor:zoom-in;display:block;}
.fs-social-row .qr-preview-pop{position:absolute;left:50%;bottom:calc(100% + 8px);transform:translateX(-50%) scale(.85);width:200px;height:200px;background:#fff;border:1px solid #ececec;border-radius:6px;padding:6px;box-shadow:0 6px 20px rgba(0,0,0,.15);opacity:0;pointer-events:none;transition:opacity .2s ease,transform .2s ease;z-index:10;}
.fs-social-row .qr-preview-pop img{width:100%;height:100%;object-fit:contain;display:block;}
.fs-social-row .qr-preview-pop::after{content:'';position:absolute;top:100%;left:50%;transform:translateX(-50%);border:6px solid transparent;border-top-color:#fff;}
.fs-social-row .qr-preview-box:hover .qr-preview-pop{opacity:1;transform:translateX(-50%) scale(1);}
.fs-social-row .btn-del-social{padding:3px 8px;font-size:11px;background:#fff;color:#999;border:1px solid #eee;border-radius:3px;cursor:pointer;margin-left:auto;}
.fs-social-row .btn-del-social:hover{color:#c2410c;border-color:#fecaca;}

.fs-add-social-btn{padding:8px 14px;background:#fff;color:#ea6f5a;border:1px dashed #ea6f5a;border-radius:4px;cursor:pointer;font-size:13px;}

/* 版权备案 */
.fs-cp-row{display:flex;gap:10px;align-items:center;margin-bottom:8px;}
.fs-cp-row label{width:100px;color:#666;font-size:12px;}
.fs-cp-row input{flex:1;padding:6px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px;}

/* 站点信息（站名 / logo / 简介） */
.fs-site-block{background:#fafafa;border:1px solid #eee;border-radius:6px;padding:14px;margin-bottom:12px;}
.fs-site-row{display:flex;gap:10px;align-items:center;margin-bottom:10px;}
.fs-site-row label{width:88px;color:#666;font-size:12px;flex-shrink:0;}
.fs-site-row .site-name-input{flex:1;padding:6px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px;}
.fs-site-logo-box{flex:1;display:flex;gap:10px;align-items:center;}
.fs-site-logo-box .site-logo-input{flex:1;padding:6px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px;background:#fff;}
.fs-site-logo-box .site-logo-upload{padding:5px 12px;font-size:12px;background:#fff;color:#1a73e8;border:1px solid #d6e4ff;border-radius:4px;cursor:pointer;}
.fs-site-logo-box .site-logo-upload:hover{background:#f0f7ff;}
.fs-site-logo-box .site-logo-upload[disabled]{color:#bbb;border-color:#ddd;cursor:not-allowed;background:#fafafa;}
/* 删除 Logo：与上传按钮并列，红色幽灵风，初始空 logo 时禁用 */
.fs-site-logo-box .site-logo-clear{padding:5px 12px;font-size:12px;background:#fff;color:#c2410c;border:1px solid #fecaca;border-radius:4px;cursor:pointer;}
.fs-site-logo-box .site-logo-clear:hover:not([disabled]){background:#fff5f5;}
.fs-site-logo-box .site-logo-clear[disabled]{color:#bbb;border-color:#ddd;cursor:not-allowed;background:#fafafa;}
.fs-site-logo-preview{width:64px;height:64px;border:1px solid #eee;border-radius:4px;background:#fff;display:flex;align-items:center;justify-content:center;overflow:hidden;}
.fs-site-logo-preview img{max-width:100%;max-height:100%;object-fit:contain;}
.fs-site-logo-preview.empty{color:#bbb;font-size:11px;}
.fs-site-intro-row{display:flex;gap:10px;align-items:flex-start;margin-bottom:6px;}
.fs-site-intro-row label{width:88px;color:#666;font-size:12px;padding-top:8px;flex-shrink:0;}
.fs-site-intro-row textarea{flex:1;padding:8px 10px;border:1px solid #ddd;border-radius:4px;font-size:13px;line-height:1.7;min-height:90px;resize:vertical;box-sizing:border-box;}

/* 预留 HTML */
.fs-html-toolbar{display:flex;gap:8px;align-items:center;margin-bottom:6px;}
.fs-html-toolbar .toggle-preview{font-size:12px;color:#1a73e8;cursor:pointer;}
.fs-html-area{width:100%;min-height:120px;padding:10px;border:1px solid #ddd;border-radius:4px;font-family:Menlo,Consolas,monospace;font-size:12px;line-height:1.6;resize:vertical;box-sizing:border-box;}
.fs-html-preview{padding:14px;border:1px dashed #ea6f5a;border-radius:4px;background:#fff;min-height:60px;display:none;}
.fs-html-preview.show{display:block;}

/* 提示 */
.fs-hint{font-size:12px;color:#888;line-height:1.7;}
.fs-hint .em{color:#ea6f5a;font-weight:600;}
</style>

<div class="card fs-card">
    <div class="card-header">页脚设置 <span class="text-muted" style="font-size:12px;font-weight:normal;">（仅站长）</span></div>
    <div class="card-body">
        <div class="alert alert-info fs-hint">
            <span class="em">使用说明：</span>本页所有字段留空 / 全部清空时，前台自动回退到 <code>core/helpers.php → default_footer_data()</code> 提供的默认内容。
            删除某栏菜单后，刷新页面会看到默认值重新填充；所有字段留空则自动回退到默认页脚。
        </div>
    </div>
</div>

<form id="footerForm" onsubmit="return false;">
    <?= csrf_field() ?>

    <!-- 1. 4 栏菜单 -->
    <div class="card fs-card">
        <div class="card-header">页脚菜单栏 <span class="text-muted" style="font-size:12px;font-weight:normal;">（最多 4 栏，每栏最多 5 个链接）</span></div>
        <div class="card-body">
            <div id="colsContainer"></div>
            <button type="button" class="fs-add-col-btn" id="addColBtn">+ 添加一栏</button>
            <p class="fs-hint" style="margin-top:10px;">栏目标题 + 链接显示名 + 链接地址（站内/站外均可）。最多 4 栏 × 5 条 = 20 个链接。</p>
        </div>
    </div>

    <!-- 2. 社交媒体 -->
    <div class="card fs-card">
        <div class="card-header">官方社交媒体 <span class="text-muted" style="font-size:12px;font-weight:normal;">（默认 3 个位置，可手动继续添加；链接/二维码二选一）</span></div>
        <div class="card-body">
            <div id="socialsContainer"></div>
            <button type="button" class="fs-add-social-btn" id="addSocialBtn">+ 添加一个社交位置</button>
            <p class="fs-hint" style="margin-top:10px;">
                <span class="em">链接</span>：前台 hover 显示"点击跳转"；<span class="em">二维码</span>：前台 hover 显示二维码图片（建议 120×120 PNG/JPG）。
            </p>
        </div>
    </div>

    <!-- 3. 网站信息（站名 / Logo / 简介） -->
    <div class="card fs-card">
        <div class="card-header">网站信息 <span class="text-muted" style="font-size:12px;font-weight:normal;">（前台页脚左侧展示，全部留空则回退到 <code>default_footer_data()</code> 默认）</span></div>
        <div class="card-body">
            <div class="fs-site-row">
                <label>网站名</label>
                <input type="text" name="site_name" class="site-name-input" value="<?= e($siteName ?? '') ?>" placeholder="如：论坛" maxlength="30">
            </div>
            <div class="fs-site-row">
                <label>网站 Logo</label>
                <div class="fs-site-logo-box">
                    <input type="text" name="site_logo" class="site-logo-input" value="<?= e($siteLogo ?? '') ?>" placeholder="uploads/site/logo-xxx.png（先点「上传 Logo」）" readonly>
                    <button type="button" class="site-logo-upload" id="uploadSiteLogoBtn">上传 Logo</button>
                    <button type="button" class="site-logo-clear" id="clearSiteLogoBtn" title="清空 Logo 输入框（保存后前台页脚将只显示网站名）" <?= empty($siteLogo) ? 'disabled' : '' ?>>删除</button>
                    <span class="fs-site-logo-preview <?= empty($siteLogo) ? 'empty' : '' ?>" id="siteLogoPreview">
                        <?php if (!empty($siteLogo)): ?>
                            <img src="<?= e(upload_url($siteLogo)) ?>" alt="logo 预览">
                        <?php else: ?>
                            无 logo
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            <div class="fs-site-intro-row">
                <label>网站简介</label>
                <textarea name="site_intro" class="site-intro-input" maxlength="200" placeholder="一个专注于内容分享与社区交流的轻量级论坛系统..."><?= e($siteIntro ?? '') ?></textarea>
            </div>
            <p class="fs-hint">三行全留空 → 自动回退到 <code>default_footer_data()</code> 默认（站名「论坛」、无 logo、默认简介文字）。
            <br>点「删除」可清空已上传的 Logo（保存后前台页脚只在 <code>site_name</code> 不为空时显示网站名，不再渲染 logo 图）。</p>
        </div>
    </div>

    <!-- 4. 版权 / 备案 / 联系 -->
    <div class="card fs-card">
        <div class="card-header">版权 · 备案 · 联系方式</div>
        <div class="card-body">
            <div class="fs-cp-row">
                <label>版权行</label>
                <input type="text" name="copyright" value="<?= e($copyright['copyright'] ?? '') ?>" placeholder="Copyright © 2013 - <?= date('Y') ?> 站名. All rights reserved.">
            </div>
            <div class="fs-cp-row">
                <label>ICP 备案号</label>
                <input type="text" name="icp" value="<?= e($copyright['icp'] ?? '') ?>" placeholder="京ICP备0000000号-1">
            </div>
            <div class="fs-cp-row">
                <label>公安备案号</label>
                <input type="text" name="police_record" value="<?= e($copyright['police_record'] ?? '') ?>" placeholder="如：京公网安备 11010100000000 号">
            </div>
            <div class="fs-cp-row">
                <label>联系方式</label>
                <input type="text" name="contact_extra" value="<?= e($copyright['contact_extra'] ?? '') ?>" placeholder="联系邮箱：service@example.com | 不良信息举报：010-xxxxxxx">
            </div>
            <p class="fs-hint">全部留空 → 自动用 <code>default_footer_data()</code> 里的默认值。<b>公安备案号</b>留空时前台不显示该行（不渲染占位）。</p>
        </div>
    </div>

    <!-- 4. 提交 -->
    <div style="display:flex;gap:10px;align-items:center;">
        <button type="submit" class="btn btn-primary" id="footerSubmitBtn">保存页脚设置</button>
        <button type="button" class="btn" id="resetDefaultBtn" style="color:#c2410c;">重置为默认</button>
        <span class="fs-hint" style="margin-left:auto;">保存后立即对前台所有页面生效</span>
    </div>
</form>

<script>
// ========== 状态（与初始数据双向同步）==========
// 社交图标下拉预置列表（FA6 风格类名）。更多图标请前往「系统管理 → 图标库」浏览/复制。
var iconOptions = [
    'fa-brands fa-weixin','fa-brands fa-weibo','fa-brands fa-qq','fa-brands fa-github',
    'fa-brands fa-twitter','fa-brands fa-x-twitter','fa-brands fa-facebook','fa-brands fa-instagram',
    'fa-brands fa-youtube','fa-brands fa-bilibili','fa-brands fa-tiktok','fa-brands fa-telegram',
    'fa-brands fa-discord','fa-brands fa-linkedin','fa-brands fa-reddit','fa-brands fa-pinterest',
    'fa-solid fa-envelope','fa-solid fa-link','fa-solid fa-globe','fa-solid fa-share-nodes','fa-solid fa-rss'
];
var ICONS_HTML = iconOptions.map(function (i) { return '<option value="' + i + '">' + i + '</option>'; }).join('');

/**
 * 客户端版的图标归一函数（与服务端 helpers.php 的 fa_icon_class() 等价）。
 * 输入：FA4 / FA5 / FA6 / 裸名 / iconfont-xxx 任意
 * 输出：FA6 标准类名（如 fa-solid fa-link / fa-brands fa-weibo）；无法识别的原样返回。
 */
function normalizeIconClass(raw) {
    raw = (raw || '').trim();
    if (!raw) return '';
    // 0) 入口自愈：历史脏数据中残留的 fa--xxx / fa--fa-xxx 双横杠折叠为 fa-xxx，
    //    避免后续归一再产出 fa---xxx 三横杠。合法 fa-house / fa-weibo 不动。
    raw = raw.replace(/\bfa--(?:fa-)?/g, 'fa-');
    // 1) 提取 family
    var m = raw.match(/\b(fa-solid|fa-regular|fa-light|fa-thin|fa-duotone|fa-brands|fas|far|fal|fat|fab)\b/);
    var family = m ? m[1] : '';
    // 2) 剥离 family + 独立的 fa（与 nav_settings._navCliClass 同样规则，避免误剥 fa-xxx 中的 fa → 产出 fa--xxx）
    //    关键：单独成词的 fa 必须后跟空白或行尾，保留 fa-home / fa-fawikipedia-w / fa-weibo 等复合词
    var clean = raw.replace(/\b(fa-solid|fa-regular|fa-light|fa-thin|fa-duotone|fa-brands|fas|far|fal|fat|fab)\b|\bfa\b(?=\s|$)/g, ' ').replace(/\s+/g, ' ').trim();
    var name = '';
    var parts = clean.split(' ');
    for (var i = 0; i < parts.length; i++) {
        var p = parts[i]; if (!p) continue;
        if (p.indexOf('fa-') === 0) { name = p.slice(3); break; }
        name = p; break;
    }
    if (!name) return raw;
    var alias = { fas: 'fa-solid', far: 'fa-regular', fab: 'fa-brands', fal: 'fa-light', fat: 'fa-thin' };
    if (alias[family]) family = alias[family];
    if (family === 'fa' || family === '') family = '';
    // 3) brand 嗅探：用户输入「fa-weibo」「fa-qq」时即便没写 family 前缀，也应识别为 fa-brands，
    //    避免被默认 solid 兜底。500+ 项品牌名与 helpers.php 内 $brandOldNames 保持同步（常用子集）。
    var brandNames = _fsCliBrandNames();
    if (brandNames[name] && family === '') family = 'fa-brands';
    return (family || 'fa-solid') + ' fa-' + name;
}
// 客户端品牌嗅探白名单（与服务端 helpers.php 内 $brandOldNames 同步的常用子集）
function _fsCliBrandNames() {
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
        'zhihu':'1','bilibili':'1','baidu':'1'
    };
}

// PHP 注入的初始数据：用三元兜底 '[]'，避免 $columns/$socials 为 null 时 json_encode 输出空串
// 破坏整个 <script> 块（这是导致 socialsContainer 空白 + 添加按钮无响应的最常见根因之一）
var initialCols    = <?= (isset($columns) && is_array($columns)) ? json_encode($columns, JSON_UNESCAPED_UNICODE) : '[]' ?>;
var initialSocials = <?= (isset($socials) && is_array($socials)) ? json_encode($socials, JSON_UNESCAPED_UNICODE) : '[]' ?>;

function cap(arr, n) { return arr.slice(0, n); }

// ========== 4 栏菜单渲染 ==========
// 重新刷新所有 col-block 的序号标签（data-col-idx + 「第 N 栏」文本）。
// 仅在「添加/删除栏」后调用一次即可，避免删中间栏导致后面栏序号跳号。
// renderCols() 初始化批量渲染时不需要——闭包 idx 0,1,2 天然对齐。
function renumberCols(box) {
    Array.prototype.forEach.call(box.children, function (block, i) {
        block.setAttribute('data-col-idx', i);
        var tag = block.querySelector('.col-index-tag');
        if (tag) tag.textContent = '第 ' + (i + 1) + ' 栏';
    });
}

function renderCols() {
    var box = document.getElementById('colsContainer');
    box.innerHTML = '';
    initialCols.forEach(function (col, idx) { box.appendChild(buildColBlock(col, idx)); });
}

function buildColBlock(col, idx) {
    var wrap = document.createElement('div');
    wrap.className = 'fs-col-block';
    wrap.setAttribute('data-col-idx', idx);

    // 头部
    var head = document.createElement('div');
    head.className = 'fs-col-head';
    // 头部：「第 N 栏」用 <span class="col-index-tag"> 包裹，便于 renumberCols() 后期只刷这一段文本，
    // 不会误伤 input/button 输入与事件绑定
    head.innerHTML = '<span class="col-index-tag">第 ' + (idx + 1) + ' 栏</span>　'
        + '<input class="col-title-input" data-k="title" value="' + escapeAttr(col.title || '') + '" placeholder="栏目标题（如：网站导航）" maxlength="30">'
        + '<button type="button" class="btn-del-col">删除该栏</button>';
    wrap.appendChild(head);

    // 链接列表
    var linksBox = document.createElement('div');
    linksBox.className = 'links-box';
    (col.links || []).forEach(function (lk) { linksBox.appendChild(buildLinkRow(lk, linksBox, wrap)); });
    wrap.appendChild(linksBox);

    // 添加链接
    var addBtn = document.createElement('button');
    addBtn.type = 'button';
    addBtn.className = 'fs-add-link-btn';
    addBtn.textContent = '+ 添加链接';
    addBtn.addEventListener('click', function () {
        if (linksBox.children.length >= 5) return;
        linksBox.appendChild(buildLinkRow({name:'', url:''}, linksBox, wrap));
        syncAddBtnState(wrap);
    });
    wrap.appendChild(addBtn);

    // 删除整栏——直接 DOM 移除 wrap，不调 renderCols()。
    // 关键：renderCols() 走的是 box.innerHTML = '' + 整列重建，会把尚未保存的 input 编辑直接冲掉。
    // 这里改成 wrap.remove()，未保存的内容原地保留；最后用 renumberCols(box) 把剩余栏的「第 N 栏」序号对齐。
    head.querySelector('.btn-del-col').addEventListener('click', function () {
        var box = document.getElementById('colsContainer');
        if (box.children.length <= 1) { toast('至少保留 1 栏（可清空内容）', 'error'); return; }
        // 读 DOM 实时值作为 confirm 提示（而不是 closure 里的 col.title——后者还是初始值）
        var titleInput = head.querySelector('.col-title-input').value.trim();
        var storedIdx = parseInt(wrap.getAttribute('data-col-idx'), 10);
        var displayIdx = isNaN(storedIdx) ? (idx + 1) : (storedIdx + 1);
        var label = titleInput || ('第 ' + displayIdx + ' 栏');
        if (!confirm('确认删除「' + label + '」？')) return;
        wrap.remove();
        renumberCols(box);
    });

    syncAddBtnState(wrap);
    return wrap;
}

function syncAddBtnState(wrap) {
    var linksBox = wrap.querySelector('.links-box');
    var btn = wrap.querySelector('.fs-add-link-btn');
    if (linksBox.children.length >= 5) {
        btn.setAttribute('disabled', 'disabled');
        btn.textContent = '已达上限 5 个';
    } else {
        btn.removeAttribute('disabled');
        btn.textContent = '+ 添加链接';
    }
}

function buildLinkRow(lk, linksBox, parentWrap) {
    var row = document.createElement('div');
    row.className = 'fs-link-row';
    row.innerHTML = '<input class="link-name" data-k="name" value="' + escapeAttr(lk.name || '') + '" placeholder="链接名" maxlength="30">'
        + '<input class="link-url" data-k="url"  value="' + escapeAttr(lk.url  || '') + '" placeholder="链接地址（站内/站外）" maxlength="255">'
        + '<button type="button" class="btn-del-link">删除</button>';
    row.querySelector('.btn-del-link').addEventListener('click', function () {
        row.remove();
        syncAddBtnState(parentWrap);
    });
    return row;
}

// 直接 append 新 col-block，不调 renderCols()——避免 innerHTML='' 重建把已编辑但未保存的输入清空。
// 「新增」事件只该 DOM 增量，绝不该触发整列重渲染。
document.getElementById('addColBtn').addEventListener('click', function () {
    var box = document.getElementById('colsContainer');
    if (box.children.length >= 4) { toast('最多 4 栏', 'error'); return; }
    var block = buildColBlock({title: '新栏目', links: []}, box.children.length);
    box.appendChild(block);
    renumberCols(box);
    var last = box.querySelector('.fs-col-block:last-child');
    if (last) last.scrollIntoView({behavior: 'smooth', block: 'center'});
});

// ========== 社交媒体渲染 ==========
function renderSocials() {
    var box = document.getElementById('socialsContainer');
    if (!box) return;
    box.innerHTML = '';
    initialSocials.forEach(function (s, idx) {
        try {
            box.appendChild(buildSocialRow(s, idx));
        } catch (err) {
            // 单行渲染失败不影响其他行；降级为最简单的文本占位
            console.error('[buildSocialRow] idx=' + idx + ' failed:', err, s);
            var fallback = document.createElement('div');
            fallback.className = 'fs-social-row';
            fallback.style.color = '#c2410c';
            fallback.textContent = '社交项 #' + (idx + 1) + ' 渲染失败：' + (err && err.message ? err.message : err);
            box.appendChild(fallback);
        }
    });
}

function buildSocialRow(s, idx) {
    var row = document.createElement('div');
    row.className = 'fs-social-row';
    row.setAttribute('data-social-idx', idx);

    var isQR = s.type === 'qrcode';
    // 类型标记位：决定右侧「上传二维码按钮 + 预览图」是否渲染
    // 链接类型 → 完全不渲染（不显示裂开图）；二维码类型 + 已填 value → 渲染预览（hover 弹大图）
    var showQrVisuals = isQR;
    // 链接/二维码都用 uploadUrl() 兜底成完整 URL，避免相对路径在 /admin/footerSettings 下找不到 → 裂开图
    // 注：uploadUrl 在文件底部定义（函数声明会被 hoisted，可安全引用）
    var qrSrc = '';
    if (isQR && s.value) {
        try { qrSrc = uploadUrl(s.value) || ''; } catch (_) { qrSrc = ''; }
    }

    // 用 Array.join('') 统一拼接；不用嵌套三元 + 内联 onerror，避免一处转义错崩掉整个 socials 渲染
    var htmlParts = [
        '<select class="icon-select" data-k="icon">', ICONS_HTML, '</select>',
        '<div class="type-radios">',
            '<label><input type="radio" name="stype_', idx, '" value="link"',     (isQR ? '' : ' checked'), ' data-k="type"> 链接</label>',
            '<label><input type="radio" name="stype_', idx, '" value="qrcode"',   (isQR ? ' checked' : ''), ' data-k="type"> 二维码</label>',
        '</div>',
        '<input class="value-input" data-k="value" value="', escapeAttr(s.value || ''), '" placeholder="', (isQR ? '/uploads/qrcode/wechat.png' : 'https://...'), '" maxlength="500">',
        '<input class="title-input" data-k="title" value="', escapeAttr(s.title || ''), '" placeholder="hover 提示（如：官方微信）" maxlength="30">',
        '<button type="button" class="upload-btn" style="display:', (showQrVisuals ? 'inline-block' : 'none'), ';" data-role="upload">上传二维码</button>'
    ];
    if (qrSrc) {
        htmlParts.push(
            '<div class="qr-preview-box" data-role="qr-preview-box">',
                '<img class="qr-preview" data-role="qr-preview" src="', escapeAttr(qrSrc), '" title="点击放大">',
                '<span class="qr-preview-pop" data-role="qr-preview-pop"><img src="', escapeAttr(qrSrc), '" alt="', escapeAttr(s.title || '二维码'), '"></span>',
            '</div>'
        );
    } else if (showQrVisuals) {
        // 二维码类型但尚无 value：占位空盒子，后续 refreshQrPreview 会填充
        htmlParts.push('<div class="qr-preview-box" data-role="qr-preview-box" style="display:none;"></div>');
    }
    // 链接类型：完全不渲染 qr-preview-box（彻底无 <img src=""> 的裂开占位）
    row.innerHTML = htmlParts.join('');

    // 设置初始 icon：若数据库中存的是 FA4 / FA5 / 裸名，自动归一到 FA6 后再赋给 select
    try {
        var norm = normalizeIconClass(s.icon);
        if (iconOptions.indexOf(norm) < 0) norm = 'fa-solid fa-link';
        row.querySelector('.icon-select').value = norm;
    } catch (_) { try { row.querySelector('.icon-select').value = 'fa-solid fa-link'; } catch (__) {} }

    // 切换 link / qrcode 时显示/隐藏上传按钮 + 预览图（链接类型要彻底不渲染预览容器）
    try {
        row.querySelectorAll('input[type=radio][data-k=type]').forEach(function (r) {
            r.addEventListener('change', function () {
                var isQR2 = r.value === 'qrcode' && r.checked;
                var valInput = row.querySelector('.value-input');
                var upBtn = row.querySelector('[data-role="upload"]');
                var prevBox = row.querySelector('[data-role="qr-preview-box"]');
                if (isQR2) {
                    upBtn.style.display = 'inline-block';
                    valInput.placeholder = '/uploads/qrcode/wechat.png';
                    // 链接→二维码 切换时，如还没有预览图，临时隐藏占位；用户上传或填 value 后才显示
                    if (prevBox) prevBox.style.display = valInput.value.trim() ? '' : 'none';
                } else {
                    upBtn.style.display = 'none';
                    valInput.placeholder = 'https://...';
                    if (prevBox) prevBox.style.display = 'none';
                }
            });
        });
    } catch (err) { console.error('[buildSocialRow] bind type-radios failed:', err); }

    // 上传二维码成功后：刷新预览图（同时更新小预览 + hover 大图两个 img）+ 自动显示预览容器
    function refreshQrPreview(row, url) {
        var prevBox = row.querySelector('[data-role="qr-preview-box"]');
        if (!prevBox) return;
        // 完整化 URL，避免 /admin/footerSettings 相对路径找不到 → 裂开
        var fullUrl = '';
        try { fullUrl = uploadUrl(url) || ''; } catch (_) { fullUrl = ''; }
        if (!fullUrl) return;
        // 重新创建 img 节点，确保 error 事件绑上去
        var html = [
            '<img class="qr-preview" data-role="qr-preview" src="', escapeAttr(fullUrl), '" title="点击放大">',
            '<span class="qr-preview-pop" data-role="qr-preview-pop"><img src="', escapeAttr(fullUrl), '" alt="二维码预览"></span>'
        ].join('');
        prevBox.innerHTML = html;
        // 用 addEventListener 兜底（替代内联 onerror，更不易出转义 bug）
        prevBox.querySelectorAll('img').forEach(function (img) {
            img.addEventListener('error', function () { prevBox.style.display = 'none'; });
        });
        prevBox.style.display = '';
    }

    // 手动改 value 输入框（如直接粘贴 URL）→ 实时同步预览图 + 自动展示/隐藏
    try {
        row.querySelector('.value-input').addEventListener('input', function () {
            var url = this.value.trim();
            var prevBox = row.querySelector('[data-role="qr-preview-box"]');
            var isQRNow = row.querySelector('input[type=radio][data-k=type]:checked').value === 'qrcode';
            if (!prevBox || !isQRNow) return;
            if (url) { refreshQrPreview(row, url); } else { prevBox.style.display = 'none'; }
        });
    } catch (err) { console.error('[buildSocialRow] bind value-input failed:', err); }

    // 上传二维码
    try { row.querySelector('[data-role="upload"]').addEventListener('click', function () { uploadQrcode(row); }); } catch (err) { console.error('[buildSocialRow] bind upload-btn failed:', err); }

    // 删除整行——直接 DOM 移除 row，不调 renderSocials()。
    // renderSocials() 同样是 innerHTML='' + 全列重建，会把尚未保存的输入清空，这里改成纯 DOM 增量。
    try {
        row.querySelector('.btn-del-social').addEventListener('click', function () {
            var box = document.getElementById('socialsContainer');
            if (box.children.length <= 1) { toast('至少保留 1 个位置（可清空值）', 'error'); return; }
            if (!confirm('确认删除此社交位置？')) return;
            row.remove();
        });
    } catch (err) { console.error('[buildSocialRow] bind btn-del-social failed:', err); }

    return row;
}

// 直接 append 新 social row，不调 renderSocials()——避免把尚未保存的输入冲掉。
document.getElementById('addSocialBtn').addEventListener('click', function () {
    var box = document.getElementById('socialsContainer');
    box.appendChild(buildSocialRow({icon: 'fa-solid fa-link', type: 'link', value: '', title: ''}, box.children.length));
    var last = box.querySelector('#socialsContainer .fs-social-row:last-child');
    if (last) last.scrollIntoView({behavior: 'smooth', block: 'center'});
});

function uploadQrcode(row) {
    var inp = document.createElement('input');
    inp.type = 'file';
    inp.accept = 'image/*';
    inp.addEventListener('change', function () {
        if (!inp.files[0]) return;
        var fd = new FormData();
        fd.append('file', inp.files[0]);
        fd.append('_token', getCsrfToken());
        var xhr = new XMLHttpRequest();
        xhr.open('POST', url('upload/image', {type: 'qrcode'}));
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function () {
            // 始终把字节级诊断信息打 console，方便定位隐藏杂质（BOM/NUL/控制字符/前导 HTML 等）
            try {
                console.log('[uploadQrcode] status:', xhr.status, 'content-type:', xhr.getResponseHeader('Content-Type'));
                console.log('[uploadQrcode] raw bytes (len=' + (xhr.responseText || '').length + '):');
                console.log(xhr.responseText);
            } catch (_) {}
            var res = parseLooseJson(xhr.responseText);
            if (res && typeof res === 'object' && 'code' in res) {
                if (res.code === 0) {
                    var u = res.data && res.data.url ? res.data.url : '';
                    row.querySelector('.value-input').value = u;
                    refreshQrPreview(row, u);
                    toast('二维码上传成功', 'success');
                } else {
                    toast(res.message || '上传失败', 'error');
                }
            } else {
                // 既不是合法 JSON，也无法宽松提取。把首 80 字节的十六进制 dump 出来，便于定位隐藏字符。
                var raw = (xhr.responseText || '').slice(0, 80);
                var hexArr = [];
                for (var i = 0; i < Math.min(raw.length, 40); i++) {
                    var c2 = raw.charCodeAt(i);
                    hexArr.push((c2 < 0x20 || c2 === 0x7f) ? ('\\x' + c2.toString(16).padStart(2, '0')) : raw[i]);
                }
                console.warn('[uploadQrcode] parse failed, head hex/escaped:', hexArr.join(''), 'full:', JSON.stringify((xhr.responseText || '').slice(0, 200)));
                toast('上传失败，响应格式错误：' + raw, 'error', 8000);
            }
        };
        xhr.onerror = function () { toast('网络错误，上传失败', 'error'); };
        xhr.send(fd);
    });
    inp.click();
}

/**
 * 宽松 JSON 解析：碰到 JSON.parse 抛错时，尝试从字符串里提取首个合法的顶层 {…} 块再解析
 * 同时剥掉 BOM、NUL、控制字符、首位空白等杂质，让被污染的响应也能被正确解析
 */
function parseLooseJson(text) {
    if (text == null) return null;
    if (typeof text !== 'string') text = String(text);
    // 1. 先按原样尝试（最快路径）
    try { return JSON.parse(text); } catch (_) {}
    // 2. 剥 BOM + 控制字符（保留 \r \n \t），再做一次
    var cleaned = text.replace(/^\uFEFF/, '').replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, '');
    try { return JSON.parse(cleaned.trim()); } catch (_) {}
    // 3. 在文本里定位首个 { 与最后一个匹配的 }，切片再解析（处理响应前后有 HTML/PHP warning 的情况）
    var start = cleaned.indexOf('{');
    if (start < 0) return null;
    var depth = 0, end = -1, inStr = false, escape = false;
    for (var i = start; i < cleaned.length; i++) {
        var ch = cleaned[i];
        if (inStr) {
            if (escape) { escape = false; continue; }
            if (ch === '\\') { escape = true; continue; }
            if (ch === '"') inStr = false;
            continue;
        }
        if (ch === '"') { inStr = true; continue; }
        if (ch === '{') depth++;
        else if (ch === '}') { depth--; if (depth === 0) { end = i; break; } }
    }
    if (end < 0) return null;
    try { return JSON.parse(cleaned.substring(start, end + 1)); } catch (_) {}
    return null;
}

// ========== 工具 ==========
function escapeAttr(s) {
    return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

// ========== 序列化为表单 payload ==========
function serializeForm() {
    // 4 栏
    var colsData = [];
    document.querySelectorAll('#colsContainer .fs-col-block').forEach(function (block) {
        var title = block.querySelector('.col-title-input').value.trim();
        var links = [];
        block.querySelectorAll('.fs-link-row').forEach(function (r) {
            var name = r.querySelector('.link-name').value.trim();
            var url  = r.querySelector('.link-url').value.trim();
            if (name !== '') links.push({name: name, url: url});
        });
        colsData.push({title: title, links: links});
    });

    // 社交
    var socialsData = [];
    document.querySelectorAll('#socialsContainer .fs-social-row').forEach(function (r) {
        var icon  = r.querySelector('.icon-select').value;
        var type  = r.querySelector('input[type=radio][data-k=type]:checked').value;
        var value = r.querySelector('.value-input').value.trim();
        var ttl   = r.querySelector('.title-input').value.trim();
        if (value === '') return;
        socialsData.push({icon: icon, type: type, value: value, title: ttl});
    });

    return {
        columns: colsData,
        socials: socialsData,
        site_name:  document.querySelector('input[name=site_name]').value.trim(),
        site_logo:  document.querySelector('input[name=site_logo]').value.trim(),
        site_intro: document.querySelector('textarea[name=site_intro]').value.trim(),
        copyright:      document.querySelector('input[name=copyright]').value.trim(),
        icp:            document.querySelector('input[name=icp]').value.trim(),
        police_record:  document.querySelector('input[name=police_record]').value.trim(),
        contact_extra:  document.querySelector('input[name=contact_extra]').value.trim(),
        _token:         getCsrfToken()
    };
}

// ========== 提交 ==========
document.getElementById('footerForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = document.getElementById('footerSubmitBtn');
    if (btn.disabled) return;
    btn.disabled = true; btn.textContent = '保存中...';

    window.postJSON(url('admin/saveFooterSettings'), serializeForm(),
        function (res) {
            if (res && res.code === 0) {
                window.toast(res.message || '页脚设置已保存', 'success');
                setTimeout(function () { location.reload(); }, 600);
            } else {
                window.toast((res && res.message) || '保存失败', 'error');
                btn.disabled = false; btn.textContent = '保存页脚设置';
            }
        },
        function (err) {
            window.toast((err && err.message) || '网络错误，保存失败', 'error');
            btn.disabled = false; btn.textContent = '保存页脚设置';
        }
    );
});

// 重置为默认
document.getElementById('resetDefaultBtn').addEventListener('click', function () {
    if (!confirm('确认重置为默认内容？\n\n这将清空当前所有页脚设置（4 栏菜单 / 社交媒体 / 版权 / 预留 HTML），前台会自动恢复为系统默认页脚。')) return;
    var empty = {columns: [], socials: [], site_name: '', site_logo: '', site_intro: '', copyright: '', icp: '', police_record: '', contact_extra: '', _token: getCsrfToken()};
    var btn = document.getElementById('footerSubmitBtn');
    btn.disabled = true; btn.textContent = '重置中...';
    window.postJSON(url('admin/saveFooterSettings'), empty,
        function (res) {
            if (res && res.code === 0) {
                window.toast('已恢复默认页脚', 'success');
                setTimeout(function () { location.reload(); }, 600);
            } else {
                window.toast((res && res.message) || '重置失败', 'error');
                btn.disabled = false; btn.textContent = '保存页脚设置';
            }
        }
    );
});

// ========== 站点 Logo 上传（复用 upload/image?type=qrcode 通道；仅站长） ==========
var siteLogoBtn = document.getElementById('uploadSiteLogoBtn');
if (siteLogoBtn) {
    siteLogoBtn.addEventListener('click', function () {
        var inp = document.createElement('input');
        inp.type = 'file';
        inp.accept = 'image/*';
        inp.addEventListener('change', function () {
            if (!inp.files[0]) return;
            var fd = new FormData();
            fd.append('file', inp.files[0]);
            fd.append('_token', getCsrfToken());
            var xhr = new XMLHttpRequest();
            xhr.open('POST', url('upload/image', {type: 'qrcode'}));
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            siteLogoBtn.disabled = true;
            siteLogoBtn.textContent = '上传中...';
            xhr.onload = function () {
                siteLogoBtn.disabled = false;
                siteLogoBtn.textContent = '上传 Logo';
                var res = parseLooseJson(xhr.responseText);
                if (res && typeof res === 'object' && res.code === 0 && res.data && res.data.url) {
                    var u = res.data.url;
                    document.querySelector('input[name=site_logo]').value = u;
                    var pv = document.getElementById('siteLogoPreview');
                    pv.classList.remove('empty');
                    pv.innerHTML = '<img src="' + escapeAttr(uploadUrl(u)) + '" alt="logo 预览">';
                    // 上传成功后启用删除按钮
                    var clearBtn = document.getElementById('clearSiteLogoBtn');
                    if (clearBtn) clearBtn.disabled = false;
                    toast('Logo 上传成功', 'success');
                } else {
                    var raw = (xhr.responseText || '').slice(0, 80);
                    toast('Logo 上传失败：' + raw, 'error', 6000);
                }
            };
xhr.onerror = function () {
            siteLogoBtn.disabled = false;
            siteLogoBtn.textContent = '上传 Logo';
            toast('网络错误，Logo 上传失败', 'error');
        };
        xhr.send(fd);
    });
    inp.click();
});
}

// ========== 删除 Logo（清空输入框 + 预览，本地状态立即生效） ==========
// 落库依赖 serializeForm() 序列化时同步读取 site_logo，故服务端无需额外防御。
// 前台 layout main.php 第 326-335 行已有"logo 空 → 不渲染 img、只显示 site_name"的逻辑。
var clearBtn = document.getElementById('clearSiteLogoBtn');
if (clearBtn) {
    clearBtn.addEventListener('click', function () {
        var inp = document.querySelector('input[name=site_logo]');
        var pv  = document.getElementById('siteLogoPreview');
        if (!inp || !pv) return;
        if (inp.value.trim() === '') {
            toast('Logo 已经是空的', 'info');
            return;
        }
        if (!confirm('确认清空当前 Logo？\n\n点「保存页脚设置」后，前台页脚不再渲染 logo 图，只显示「网站名」（如已填写）。')) {
            return;
        }
        inp.value = '';
        pv.classList.add('empty');
        pv.innerHTML = '无 logo';
        clearBtn.disabled = true;
        toast('Logo 已清空，记得点「保存页脚设置」让前台生效', 'success', 4000);
    });
}

// helper: 把 uploads/xxx 相对路径转为完整 URL（如果 app.js 未暴露 uploadUrl，这里做个轻量回退）
function uploadUrl(path) {
    if (!path) return '';
    if (/^https?:\/\//i.test(path)) return path;
    if (typeof window.uploadUrl === 'function') return window.uploadUrl(path);
    var base = (window.SITE_BASE || window.location.origin + '/');
    return base.replace(/\/?$/, '/') + path.replace(/^\/+/, '');
}

// ========== 初始化 ==========
renderCols();
renderSocials();
</script>
