<?php /** 系统设置 */
$title = '系统设置';
$site = Config::get('site', []);
// 兼容读取：旧版本 site_description/logo 存在 settings 表
$siteDescOld   = $settings['site_description']   ?? '';
$siteLogoOld   = $settings['site_logo']          ?? '';
$siteKwOld     = $settings['site_keywords']      ?? '';
$siteFavOld    = $settings['site_favicon']       ?? '';
// 新版本以 site.php 配置为准，但首屏加载回退旧 settings 表，避免升级后丢失已配数据
$siteDesc      = $site['description'] ?? '';
$siteLogo      = $site['logo']        ?? '';
$siteKeywords  = $site['keywords']    ?? '';
$siteFavicon   = $site['favicon']     ?? '';
if ($siteDesc     === '') $siteDesc    = $siteDescOld;
if ($siteLogo     === '') $siteLogo    = $siteLogoOld;
if ($siteKeywords === '') $siteKeywords = $siteKwOld;
if ($siteFavicon  === '') $siteFavicon  = $siteFavOld;
?>
<div class="alert alert-info" style="margin-bottom:16px;">
    <i class="fa-solid fa-icons" style="margin-right:6px;"></i>
    <strong>提示：</strong>Font Awesome 图标已升级至 6.7.2 免费版，已迁移到独立菜单「图标库」管理。
    在板块管理 / 导航设置 / 页脚社交等处填写的图标类名由 <code>fa_icon_class()</code> 智能归一（支持 FA4 / FA5 / FA6 / 裸名混填）。
    <a href="<?= url('admin/icons') ?>" style="margin-left:8px;color:#ea6f5a;">进入图标库 →</a>
</div>

<div class="layout-tabs" style="display:flex;gap:8px;margin-bottom:16px;">
    <button type="button" class="layout-tab active" data-pane="basic">基础设置</button>
    <button type="button" class="layout-tab" data-pane="layout">版式设置</button>
</div>

<div class="layout-pane" id="pane-basic">
<div class="card" style="margin-bottom:16px;">
    <div class="card-header">站点信息</div>
    <div class="card-body">
        <form id="settingsForm" onsubmit="return false;">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label">网站标题</label>
                <input type="text" name="site_title" class="form-control" value="<?= e($site['title'] ?? '') ?>" required maxlength="60">
                <p class="form-hint">站点主名称，建议 ≤ 12 字。出现在浏览器标签 &lt;title&gt;、邮件签名、SEO 搜索结果中。</p>
            </div>
            <div class="form-group">
                <label class="form-label">网站副标题</label>
                <input type="text" name="site_subtitle" class="form-control" value="<?= e($site['subtitle'] ?? '') ?>" maxlength="80">
                <p class="form-hint">站点的 slogan / 一句话定位。与网站标题组合为「标题 - 副标题」，作为 SEO 描述的兜底值。</p>
            </div>
            <div class="form-group">
                <label class="form-label">站点描述（Meta Description）</label>
                <textarea name="site_description" class="form-control" rows="3" maxlength="200" placeholder="例如：AI BBS 是一个专注于人工智能技术交流的开源社区，涵盖深度学习、大语言模型、多模态、行业应用等热门方向。"><?= e($siteDesc) ?></textarea>
                <p class="form-hint">搜索引擎结果下方的摘要文本，建议 80–160 字，包含核心关键词但避免堆砌。不要与标题重复。</p>
            </div>
            <div class="form-group">
                <label class="form-label">SEO 关键词（Meta Keywords）</label>
                <input type="text" name="site_keywords" class="form-control" value="<?= e($siteKeywords) ?>" maxlength="200" placeholder="例如：AI论坛,人工智能,深度学习,大语言模型,LLM,机器学习,AI开源">
                <p class="form-hint">3–8 个关键词/词组，英文逗号分隔。覆盖核心主题 + 长尾词。不要与标题完全重复，留空则不输出 keywords meta。</p>
            </div>
            <div class="form-group">
                <label class="form-label">网站 Logo</label>
                <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                    <div id="logoPreview" style="width:120px;height:40px;border:1px solid #eee;border-radius:4px;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#fafafa;">
                        <?php if (!empty($siteLogo)): ?><img src="<?= upload_url($siteLogo) ?>" style="max-height:36px;" alt=""><?php else: ?><span style="color:#ccc;font-size:12px;">无Logo</span><?php endif; ?>
                    </div>
                    <input type="hidden" name="site_logo" id="logoInput" value="<?= e($siteLogo) ?>">
                    <button type="button" class="btn" onclick="document.getElementById('logoFile').click()">上传Logo</button>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('logoInput').value='';document.getElementById('logoPreview').innerHTML='<span style=\'color:#ccc;font-size:12px;\'>无Logo</span>'">清除</button>
                    <input type="file" id="logoFile" accept="image/png,image/jpeg,image/svg+xml" style="display:none;">
                </div>
                <p class="form-hint">建议透明背景 PNG，高度不超过 40px；显示在导航栏站名位置。</p>
            </div>
            <div class="form-group">
                <label class="form-label">浏览器图标 / 苹果主屏图标</label>
                <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                    <div id="faviconPreview" style="width:40px;height:40px;border:1px solid #eee;border-radius:6px;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#fafafa;">
                        <?php if (!empty($siteFavicon)): ?><img src="<?= upload_url($siteFavicon) ?>" style="max-width:100%;max-height:100%;" alt=""><?php else: ?><span style="color:#ccc;font-size:12px;">无图标</span><?php endif; ?>
                    </div>
                    <input type="hidden" name="site_favicon" id="faviconInput" value="<?= e($siteFavicon) ?>">
                    <button type="button" class="btn" onclick="document.getElementById('faviconFile').click()">上传图标</button>
                    <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('faviconInput').value='';document.getElementById('faviconPreview').innerHTML='<span style=\'color:#ccc;font-size:12px;\'>无图标</span>'">清除</button>
                    <input type="file" id="faviconFile" accept="image/png,image/jpeg" style="display:none;">
                </div>
                <p class="form-hint">方形 PNG，建议 180×180（iPhone 主屏图标标准尺寸，同时兼容浏览器标签页 favicon）。上传后会在前台输出 <code>&lt;link rel=&quot;icon&quot;&gt;</code> 和 <code>&lt;link rel=&quot;apple-touch-icon&quot;&gt;</code>。</p>
            </div>
            <hr style="margin:20px 0;border:none;border-top:1px solid #f0f0f0;">
            <div class="form-group">
                <label class="form-label">认证申请频率限制（天）</label>
                <input type="number" name="cert_limit_days" min="0" class="form-control" value="<?= e($settings['cert_limit_days'] ?? 7) ?>" style="width:120px;">
                <p class="form-hint">同一用户两次申请认证的最短间隔天数。设为 <code>0</code> 表示不限制。保存后立即对前台生效。</p>
            </div>
            <div class="form-group">
                <label class="form-label">注册模式</label>
                <select name="register_mode" id="registerMode" class="form-control" style="width:auto;">
                    <option value="open" <?= (register_mode() === 'open') ? 'selected' : '' ?>>开放注册</option>
                    <option value="closed" <?= (register_mode() === 'closed') ? 'selected' : '' ?>>关闭注册</option>
                    <option value="invite" <?= (register_mode() === 'invite') ? 'selected' : '' ?>>邀请注册</option>
                </select>
                <p class="form-hint">开放注册：任何人可注册；关闭注册：禁止注册；邀请注册：仅持有效邀请码者可注册（需在下方勾选可发起邀请的角色）。</p>
            </div>
            <div class="form-group" id="invitePermBlock" style="display:none;">
                <label class="form-label">邀请权限（开启邀请注册后，哪些角色可发起邀请）</label>
                <div style="display:flex;flex-wrap:wrap;gap:14px;">
                    <?php
                    $inviteRolesSel = array_filter(explode(',', $settings['invite_allowed_roles'] ?? ''));
                    if (!empty($roles)):
                        foreach ($roles as $r):
                    ?>
                    <label style="display:flex;align-items:center;gap:4px;font-weight:normal;margin:0;">
                        <input type="checkbox" name="invite_allowed_roles[]" value="<?= e($r['code']) ?>" <?= in_array($r['code'], $inviteRolesSel) ? 'checked' : '' ?>> <?= e($r['name']) ?>
                    </label>
                    <?php
                        endforeach;
                    else:
                    ?>
                    <span class="form-hint">未读取到角色数据，请先在「角色权限」中创建角色。</span>
                    <?php endif; ?>
                </div>
                <p class="form-hint">仅勾选的角色在前台头像下拉菜单「设置」下方显示红底「邀请」按钮，并允许访问邀请注册页；未勾选角色按钮消失且访问邀请页返回 403。</p>
            </div>
            <button type="submit" class="btn btn-primary">保存设置</button>
        </form>
    </div>
</div>

<?php
$capEnabled = !empty($settings['captcha_enabled']) && $settings['captcha_enabled'] == '1';
$capScenes  = json_decode($settings['captcha_scenes'] ?? '{}', true) ?: [];
$capSceneLabels = ['register' => '注册', 'login' => '登录', 'post' => '发帖', 'comment' => '评论'];
?>
<div class="card">
    <div class="card-header">验证码（反恶意攻击 / 灌水）</div>
    <div class="card-body">
        <form id="captchaSettingsForm" onsubmit="return false;">
            <?= csrf_field() ?>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;font-weight:normal;cursor:pointer;">
                    <input type="checkbox" name="captcha_enabled" value="1" <?= $capEnabled ? 'checked' : '' ?>> 启用验证码
                </label>
                <p class="form-hint">开启后，下列勾选的场景在提交前需完成滑块拼图验证（后端 GD 实时生成拼图）。</p>
            </div>
            <div class="form-group">
                <label class="form-label">防护场景</label>
                <div style="display:flex;flex-wrap:wrap;gap:14px;">
                    <?php foreach ($capSceneLabels as $sk => $sl): ?>
                    <label style="display:flex;align-items:center;gap:4px;font-weight:normal;margin:0;">
                        <input type="checkbox" name="scene_<?= $sk ?>" value="1" <?= !empty($capScenes[$sk]) ? 'checked' : '' ?>> <?= $sl ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div style="display:flex;gap:10px;align-items:center;">
                <button type="submit" class="btn btn-primary" id="saveCaptchaBtn">保存验证码设置</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">系统信息</div>
    <div class="card-body">
        <table class="data-table">
            <tr><td>程序版本</td><td><?= $site['version'] ?? '1.0.0' ?></td></tr>
            <tr><td>安装时间</td><td><?= $site['installed_at'] ?? '-' ?></td></tr>
            <tr><td>PHP 版本</td><td><?= PHP_VERSION ?></td></tr>
            <tr><td>数据库</td><td>MySQL</td></tr>
            <tr><td>服务器时间</td><td><?= date('Y-m-d H:i:s') ?></td></tr>
        </table>
    </div>
</div>

</div><!-- /pane-basic -->

<div class="layout-pane" id="pane-layout" style="display:none;">
    <div class="card" style="margin-bottom:16px;">
        <div class="card-header">版式设置（帖子列表）</div>
        <div class="card-body">
            <p class="form-hint" style="margin-top:0;">选择前台帖子列表的展示方式。修改后立即对全站前台生效（无需清缓存 / 刷新）。</p>
            <div class="layout-options">
                <label class="layout-opt">
                    <input type="radio" name="post_layout" value="default" <?= (setting('post_layout', 'default') === 'default') ? 'checked' : '' ?>>
                    <div class="layout-opt-body">
                        <div class="layout-opt-title">默认（列表式）</div>
                        <div class="layout-opt-desc">经典信息流，每行一条帖子，适合快速浏览与对比。</div>
                        <div class="preview-mini preview-list">
                            <span class="pm-avatar"></span>
                            <span class="pm-lines">
                                <span class="pm-line pm-title"></span>
                                <span class="pm-line pm-meta"></span>
                            </span>
                        </div>
                    </div>
                </label>
                <label class="layout-opt">
                    <input type="radio" name="post_layout" value="card" <?= (setting('post_layout', 'default') === 'card') ? 'checked' : '' ?>>
                    <div class="layout-opt-body">
                        <div class="layout-opt-title">聚焦</div>
                        <div class="layout-opt-desc">网格卡片布局，每张帖子独立成卡，视觉更聚焦。</div>
                        <div class="preview-mini preview-card">
                            <span class="pm-icon"></span>
                            <span class="pm-line pm-title"></span>
                            <span class="pm-line pm-excerpt"></span>
                            <span class="pm-line pm-meta"></span>
                        </div>
                    </div>
                </label>
            </div>
            <button type="button" class="btn btn-primary" id="saveLayoutBtn">保存版式设置</button>

            <?php /* ===== 卡片版式专属：首页轮播图 + 推荐位（选择「卡片」时自动展开） ===== */ ?>
            <?php
            $__cardHome = json_decode(setting('home_carousel', ''), true);
            if (!is_array($__cardHome)) $__cardHome = ['slides' => [], 'recommends' => []];
            if (!is_array($__cardHome['slides'] ?? null)) $__cardHome['slides'] = [];
            if (!is_array($__cardHome['recommends'] ?? null)) $__cardHome['recommends'] = [];
            ?>
            <div id="cardHomeBox" style="display:none;margin-top:22px;border-top:1px dashed #e5e5e5;padding-top:18px;">
                <p class="form-hint" style="margin-top:0;">以下内容仅在前台版式为「聚焦」时的首页顶部展示：轮播图（自动+手动切换的立体轮播）与推荐位（最多 6 个纯图片位）。</p>

                <div class="form-group">
                    <label class="form-label">轮播图（建议横图，最多 8 张）</label>
                    <div id="cardSlideList"></div>
                    <button type="button" class="btn btn-sm" onclick="addCardHomeRow('slide')" style="margin-top:6px;">+ 添加轮播图</button>
                </div>

                <div class="form-group">
                    <label class="form-label">推荐位（纯图片，最多 6 个）</label>
                    <div id="cardRecoList"></div>
                    <button type="button" class="btn btn-sm" onclick="addCardHomeRow('reco')" style="margin-top:6px;">+ 添加推荐位</button>
                </div>

                <button type="button" class="btn btn-primary" id="saveCardHomeBtn">保存轮播与推荐位</button>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('captchaSettingsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = document.getElementById('saveCaptchaBtn');
    btn.disabled = true; btn.textContent = '保存中...';
    var fd = new FormData(this);
    var data = {}; fd.forEach(function(v, k) { data[k] = v; });
    postJSON(url('admin/saveCaptcha'), data, function(res) {
        toast(res.message || '已保存', res.code === 0 ? 'success' : 'error');
        if (res.code === 0) setTimeout(function() { location.reload(); }, 600);
        else { btn.disabled = false; btn.textContent = '保存验证码设置'; }
    }, function(res) {
        toast(res.message || '网络错误', 'error'); btn.disabled = false; btn.textContent = '保存验证码设置';
    });
});
</script>
<script>
// FA 图标对照表已迁移到独立菜单「图标库」（admin/icons），此处不再需要复制脚本。

// ===== Logo 上传 =====
document.getElementById('logoFile').addEventListener('change', function() {
    if (!this.files[0]) return;
    var fd = new FormData();
    fd.append('file', this.files[0]);
    fd.append('_token', getCsrfToken());
    var xhr = new XMLHttpRequest();
    xhr.open('POST', url('upload/image', {type: 'post'}));
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onload = function() {
        if (xhr.status === 200) {
            var res = JSON.parse(xhr.responseText);
            if (res.code === 0) {
                document.getElementById('logoInput').value = res.data.url;
                document.getElementById('logoPreview').innerHTML = '<img src="' + absoluteAssetUrl(res.data.url) + '" style="max-height:36px;">';
                toast('Logo上传成功', 'success');
            } else toast(res.message || '上传失败', 'error');
        }
    };
    xhr.send(fd);
});

// ===== 浏览器图标 / apple-touch-icon 上传 =====
document.getElementById('faviconFile').addEventListener('change', function() {
    if (!this.files[0]) return;
    var fd = new FormData();
    fd.append('file', this.files[0]);
    fd.append('_token', getCsrfToken());
    var xhr = new XMLHttpRequest();
    xhr.open('POST', url('upload/image', {type: 'post'}));
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onload = function() {
        if (xhr.status === 200) {
            var res = JSON.parse(xhr.responseText);
            if (res.code === 0) {
                document.getElementById('faviconInput').value = res.data.url;
                document.getElementById('faviconPreview').innerHTML = '<img src="' + absoluteAssetUrl(res.data.url) + '" style="max-width:100%;max-height:100%;">';
                toast('图标上传成功', 'success');
            } else toast(res.message || '上传失败', 'error');
        }
    };
    xhr.send(fd);
});

document.getElementById('settingsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var fd = new FormData(this); var data = {};
    fd.forEach(function(v, k) {
        // 多值字段（checkbox name=xxx[] / repeated hidden）必须聚合为数组，
        // 否则 fd.forEach 反复写 data[k]=v 会互相覆盖，只剩最后一个值。
        // 这里同时兼容 xxx[] 标记和未带 [] 的同名意外情况。
        var key = k.replace(/\[\]$/, '');
        if (key !== k) {
            if (!Array.isArray(data[key])) data[key] = [];
            data[key].push(v);
        } else if (Object.prototype.hasOwnProperty.call(data, key)) {
            if (!Array.isArray(data[key])) data[key] = [data[key]];
            data[key].push(v);
        } else {
            data[key] = v;
        }
    });
    postJSON(url('admin/saveSettings'), data, function(res) {
        toast(res.message, res.code === 0 ? 'success' : 'error');
        // 不再 location.reload()：
        // 1) 用户刚输入/勾选的值就在表单里，DB 也已写入，刷新毫无意义；
        // 2) 整页刷新会导致输入框/checkbox 短暂清空闪烁，破坏编辑连续性；
        // 3) 如果服务端真有归一化（如 trim/clamp）差异，依赖后续手动刷新即可。
    });
});

// 注册模式 → 邀请权限模块联动显示
(function() {
    var sel = document.getElementById('registerMode');
    var block = document.getElementById('invitePermBlock');
    if (!sel || !block) return;
    function toggleInvite() {
        block.style.display = sel.value === 'invite' ? 'block' : 'none';
    }
    sel.addEventListener('change', toggleInvite);
    toggleInvite();
})();
</script>

<script>
// ===== 版式设置：二级 tab 切换 =====
(function() {
    var tabs = document.querySelectorAll('.layout-tab');
    if (!tabs.length) return;
    tabs.forEach(function(t) {
        t.addEventListener('click', function() {
            tabs.forEach(function(x) { x.classList.remove('active'); });
            t.classList.add('active');
            var pane = t.getAttribute('data-pane');
            document.getElementById('pane-basic').style.display  = (pane === 'basic')  ? 'block' : 'none';
            document.getElementById('pane-layout').style.display = (pane === 'layout') ? 'block' : 'none';
        });
    });
})();

// ===== 版式设置：选项高亮 fallback（旧浏览器无 :has 时） =====
(function() {
    var opts = document.querySelectorAll('.layout-opt');
    var cardHomeBox = document.getElementById('cardHomeBox');
    function sync() {
        var checked = document.querySelector('input[name="post_layout"]:checked');
        opts.forEach(function(o) {
            var input = o.querySelector('input[type="radio"]');
            o.classList.toggle('selected', !!input && input.checked);
        });
        // 卡片版式专属设置区：选中「卡片」时自动展开
        if (cardHomeBox) cardHomeBox.style.display = (checked && checked.value === 'card') ? 'block' : 'none';
    }
    opts.forEach(function(o) {
        var input = o.querySelector('input[type="radio"]');
        if (input) input.addEventListener('change', sync);
    });
    sync();
})();

// ===== 卡片版式：首页轮播图 + 推荐位编辑器 =====
(function() {
    var cardHomeSaved = <?= json_encode($__cardHome, JSON_UNESCAPED_UNICODE) ?>;

    function chRow(kind, item) {
        item = item || {image:'', url:''};
        var isSlide = kind === 'slide';
        var label = isSlide ? ('轮播图' ) : '推荐位';
        var html = '<div class="ch-row nav-row" data-kind="' + kind + '" style="display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap;">';
        html += '<span class="ch-preview" style="width:88px;height:50px;border-radius:6px;background:#f5f5f5;overflow:hidden;display:flex;align-items:center;justify-content:center;flex-shrink:0;">';
        html += item.image ? '<img src="' + absoluteAssetUrl(item.image) + '" style="width:100%;height:100%;object-fit:cover;">' : '<i class="fa-regular fa-image" style="color:#bbb;"></i>';
        html += '</span>';
        html += '<input type="text" data-f="image" value="' + (item.image || '').replace(/"/g, '&quot;') + '" class="form-control" style="max-width:230px;" placeholder="图片路径（上传后自动填充）">';
        html += '<button type="button" class="btn btn-sm" onclick="chPickImage(this)">上传图片</button>';
        html += '<input type="text" data-f="url" value="' + (item.url || '').replace(/"/g, '&quot;') + '" class="form-control" style="max-width:260px;" placeholder="点击跳转链接（可留空）">';
        html += '<button type="button" class="btn btn-sm btn-danger" onclick="this.closest(\'.ch-row\').remove()">删除</button>';
        html += '</div>';
        return html;
    }

    window.addCardHomeRow = function(kind) {
        var box = document.getElementById(kind === 'slide' ? 'cardSlideList' : 'cardRecoList');
        var max = kind === 'slide' ? 8 : 6;
        if (box.querySelectorAll('.ch-row').length >= max) { toast(kind === 'slide' ? '轮播图最多 ' + max + ' 张' : '推荐位最多 ' + max + ' 个', 'error'); return; }
        var div = document.createElement('div');
        div.innerHTML = chRow(kind, null);
        box.appendChild(div.firstChild);
    };

    window.chPickImage = function(btn) {
        var row = btn.closest('.ch-row');
        var inp = document.createElement('input');
        inp.type = 'file';
        inp.accept = 'image/*';
        inp.onchange = function() {
            if (!inp.files[0]) return;
            var fd = new FormData();
            fd.append('file', inp.files[0]);
            fd.append('_token', getCsrfToken());
            var xhr = new XMLHttpRequest();
            xhr.open('POST', url('upload/image', {type: 'post'}));
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.onload = function() {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.code === 0) {
                        row.querySelector('[data-f="image"]').value = res.data.url;
                        row.querySelector('.ch-preview').innerHTML = '<img src="' + absoluteAssetUrl(res.data.url) + '" style="width:100%;height:100%;object-fit:cover;">';
                        toast('图片上传成功', 'success');
                    } else toast(res.message || '上传失败', 'error');
                } catch (e) { toast('上传失败', 'error'); }
            };
            xhr.onerror = function() { toast('网络错误，上传失败', 'error'); };
            xhr.send(fd);
        };
        inp.click();
    };

    // 回填已保存数据（DOMContentLoaded：absoluteAssetUrl 来自 app.js，其在 body 末尾加载，内联脚本执行时还不可用）
    document.addEventListener('DOMContentLoaded', function() {
        var sBox = document.getElementById('cardSlideList');
        var rBox = document.getElementById('cardRecoList');
        (cardHomeSaved.slides || []).forEach(function(it) {
            var div = document.createElement('div'); div.innerHTML = chRow('slide', it); sBox.appendChild(div.firstChild);
        });
        (cardHomeSaved.recommends || []).forEach(function(it) {
            var div = document.createElement('div'); div.innerHTML = chRow('reco', it); rBox.appendChild(div.firstChild);
        });
    });

    var btn = document.getElementById('saveCardHomeBtn');
    if (!btn) return;
    btn.addEventListener('click', function() {
        var collect = function(kind) {
            var arr = [];
            document.querySelectorAll('.ch-row[data-kind="' + kind + '"]').forEach(function(r) {
                var img = ((r.querySelector('[data-f="image"]') || {}).value || '').trim();
                var u = ((r.querySelector('[data-f="url"]') || {}).value || '').trim();
                if (img === '') return;
                arr.push({image: img, url: u});
            });
            return arr;
        };
        btn.disabled = true; btn.textContent = '保存中...';
        postJSON(url('admin/saveHomeCarousel'), {
            slides: collect('slide'),
            recommends: collect('reco'),
            _token: getCsrfToken()
        }, function(res) {
            toast(res.message || '已保存', res.code === 0 ? 'success' : 'error');
            btn.disabled = false; btn.textContent = '保存轮播与推荐位';
        }, function(res) {
            toast(res.message || '网络错误', 'error');
            btn.disabled = false; btn.textContent = '保存轮播与推荐位';
        });
    });
})();

// ===== 版式设置：保存 =====
(function() {
    var btn = document.getElementById('saveLayoutBtn');
    if (!btn) return;
    btn.addEventListener('click', function() {
        var sel = document.querySelector('input[name="post_layout"]:checked');
        if (!sel) { toast('请选择一种版式', 'error'); return; }
        btn.disabled = true; btn.textContent = '保存中...';
        postJSON(url('admin/saveLayout'), { post_layout: sel.value, _token: getCsrfToken() }, function(res) {
            toast(res.message || (res.code === 0 ? '版式设置已保存' : '保存失败'), res.code === 0 ? 'success' : 'error');
            btn.disabled = false; btn.textContent = '保存版式设置';
        }, function(res) {
            toast(res.message || '网络错误', 'error');
            btn.disabled = false; btn.textContent = '保存版式设置';
        });
    });
})();
</script>