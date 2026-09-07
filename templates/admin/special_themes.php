<?php /** 特殊主题 - 仅站长（按主题 tab 拆分，各自保存） */ $title = '特殊主题';

// 防止组件内 $title 被覆盖为 tab 名：用 view 顶层的 $title 兜底
$allowedTabs = array_keys($themes);
if (!in_array($currentTab, $allowedTabs, true)) $currentTab = $allowedTabs[0];
$tabUrl = function ($t) { return url('admin/specialThemes', ['tab' => $t]); };
?>
<style>
/* ===== 顶部 tab 导航 ===== */
.st-tabs-nav { display:flex; gap:0; border-bottom:2px solid #eee; margin-bottom:16px; flex-wrap:wrap; }
.st-tabs-nav a { padding:10px 18px; color:#666; text-decoration:none; font-size:14px; font-weight:500; border-bottom:3px solid transparent; margin-bottom:-2px; transition:all .15s; white-space:nowrap; }
.st-tabs-nav a:hover { color:#ea6f5a; }
.st-tabs-nav a.active { color:#ea6f5a; border-bottom-color:#ea6f5a; font-weight:600; }

/* ===== tab 通用卡片 ===== */
.st-tab-pane { display:none; }
.st-tab-pane.active { display:block; }
.st-pane { background:#fff; border:1px solid #f0f0f0; border-radius:6px; padding:18px 22px; margin-bottom:16px; }
.st-pane + .st-pane { margin-top:14px; }
.st-pane-header { display:flex; align-items:center; gap:10px; margin-bottom:6px; flex-wrap:wrap; }
.st-pane-header .badge-theme { background:#ea6f5a; color:#fff; font-size:11px; padding:2px 8px; border-radius:10px; font-weight:600; }
.st-pane-header .pane-title { font-size:15px; font-weight:600; color:#333; }
.st-pane-header .pane-key { color:#bbb; font-size:11px; font-family:SFMono-Regular, Consolas, monospace; font-weight:normal; margin-left:4px; }
.st-pane-hint { font-size:12px; color:#888; line-height:1.7; margin:8px 0 14px; padding:10px 12px; background:#fff7f5; border-left:3px solid #ea6f5a; border-radius:0 4px 4px 0; }
.st-sub-title { font-size:13px; font-weight:600; color:#555; margin:14px 0 8px; padding-left:8px; border-left:3px solid #ea6f5a; }

/* 板块表格 */
.st-cat-table { width:100%; border-collapse:collapse; background:#fff; border:1px solid #eee; border-radius:6px; overflow:hidden; }
.st-cat-table th, .st-cat-table td { padding:9px 12px; text-align:left; border-bottom:1px solid #f3f3f3; font-size:13px; }
.st-cat-table th { background:#fafafa; color:#666; font-weight:600; }
.st-cat-table tr:last-child td { border-bottom:none; }
.st-cat-table tbody tr:hover { background:#fff8f6; }
.st-cat-name { display:flex; align-items:center; gap:8px; }
.st-cat-name .cid { color:#bbb; font-size:11px; font-family:SFMono-Regular, Consolas, monospace; }

/* 角色组卡片网格 */
.st-role-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:10px; }
.st-role-card { border:1px solid #eee; border-radius:6px; padding:10px 12px; background:#fff; display:flex; align-items:center; justify-content:space-between; }
.st-role-card .r-info { display:flex; flex-direction:column; gap:2px; }
.st-role-card .r-name { font-size:13px; color:#333; font-weight:500; }
.st-role-card .r-code { font-size:11px; color:#aaa; font-family:SFMono-Regular, Consolas, monospace; }

/* 开关 */
.st-switch { position:relative; display:inline-block; width:38px; height:20px; }
.st-switch input { opacity:0; width:0; height:0; }
.st-switch .slider { position:absolute; cursor:pointer; inset:0; background:#ccc; border-radius:20px; transition:.2s; }
.st-switch .slider:before { content:""; position:absolute; height:14px; width:14px; left:3px; bottom:3px; background:#fff; border-radius:50%; transition:.2s; }
.st-switch input:checked + .slider { background:#ea6f5a; }
.st-switch input:checked + .slider:before { transform:translateX(18px); }
.st-allow-yes { color:#16a34a; font-size:12px; }
.st-allow-no { color:#999; font-size:12px; }

/* 工具栏 */
.st-toolbar { display:flex; gap:8px; align-items:center; margin-bottom:8px; flex-wrap:wrap; }
.st-toolbar .btn-mini { padding:4px 12px; font-size:12px; background:#fff; color:#ea6f5a; border:1px solid #ea6f5a; border-radius:4px; cursor:pointer; }
.st-toolbar .btn-mini:hover { background:#fff0eb; }
.st-toolbar .btn-mini.ghost { color:#888; border-color:#ddd; }
.st-toolbar .btn-mini.ghost:hover { background:#f7f7f7; }

/* 保存按钮 + 反馈行 */
.st-save-row { display:flex; align-items:center; gap:12px; margin-top:18px; padding-top:14px; border-top:1px dashed #eee; }
.st-save-btn { padding:8px 22px; background:#ea6f5a; color:#fff; border:none; border-radius:6px; font-size:13px; cursor:pointer; font-weight:500; }
.st-save-btn:hover { background:#d85f4a; }
.st-save-btn:disabled { background:#e0a097; cursor:not-allowed; }
.st-save-status { font-size:12px; color:#16a34a; }
.st-save-status.error { color:#d85f4a; }
.st-save-meta { color:#aaa; font-size:12px; margin-left:auto; }
</style>

<!-- 顶部 tab 导航 -->
<div class="card" style="margin-bottom:0;">
    <div class="card-header">
        特殊主题
        <span class="text-muted" style="font-size:12px;font-weight:normal;">（仅站长 · 按板块 + 角色组控制发布权限 · 每个主题独立 tab 单独保存）</span>
    </div>
    <div class="card-body" style="padding-bottom:0;">
        <div class="st-tabs-nav">
            <?php foreach ($themes as $tk => $tname): ?>
                <a href="<?= $tabUrl($tk) ?>" class="<?= $tk === $currentTab ? 'active' : '' ?>"><?= e($tname) ?></a>
            <?php endforeach; ?>
            <span style="margin-left:auto;font-size:12px;color:#999;align-self:center;">共 <?= count($themes) ?> 个主题 · 已切到 <strong style="color:#ea6f5a;"><?= e($themes[$currentTab]) ?></strong></span>
        </div>
    </div>
</div>

<!-- 每个主题一个 tab pane -->
<?php foreach ($themes as $tk => $tname):
    $m = $matrix[$tk];
    $isActive = ($tk === $currentTab);
?>
<div class="st-tab-pane <?= $isActive ? 'active' : '' ?>" data-theme="<?= e($tk) ?>" style="<?= $isActive ? '' : 'display:none;' ?>margin-top:16px;">
    <form class="st-form" data-theme="<?= e($tk) ?>" onsubmit="return false;">
        <?= csrf_field() ?>
        <input type="hidden" name="theme" value="<?= e($tk) ?>">

        <!-- 板块矩阵 -->
        <div class="st-pane">
            <div class="st-pane-header">
                <span class="badge-theme">主题</span>
                <span class="pane-title"><?= e($tname) ?></span>
                <span class="pane-key">theme_key: <?= e($tk) ?></span>
            </div>
            <div class="st-sub-title">① 允许发布的板块</div>
            <div class="st-toolbar">
                <button type="button" class="btn-mini" data-act="cat-all"   data-theme="<?= e($tk) ?>">全选</button>
                <button type="button" class="btn-mini ghost" data-act="cat-none" data-theme="<?= e($tk) ?>">全不选</button>
                <span class="text-muted" style="font-size:11px;">共 <?= count($cats) ?> 个板块</span>
            </div>
            <table class="st-cat-table">
                <thead>
                    <tr><th style="width:60%;">板块名称</th><th>允许在该板块发布</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($cats as $c):
                        $checked = empty($m['cat_allow']) ? true : (!empty($m['cat_allow'][(string)$c['id']])); ?>
                    <tr>
                        <td>
                            <div class="st-cat-name">
                                <span><?= e($c['name']) ?></span>
                                <span class="cid">#<?= e($c['id']) ?></span>
                            </div>
                        </td>
                        <td>
                            <label class="st-switch">
                                <input type="checkbox" name="<?= e($tk) ?>_cat_allow[<?= e($c['id']) ?>]" value="1" <?= $checked ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                            <span class="st-allow-<?= $checked ? 'yes' : 'no' ?>"><?= $checked ? '允许' : '禁止' ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($cats)): ?>
                    <tr><td colspan="2" class="text-muted" style="text-align:center;padding:16px;">暂无启用板块</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- 角色矩阵（同卡片，节省空间） -->
            <div class="st-sub-title">② 允许发布的角色组</div>
            <div class="st-toolbar">
                <button type="button" class="btn-mini" data-act="role-all"   data-theme="<?= e($tk) ?>">全选</button>
                <button type="button" class="btn-mini ghost" data-act="role-none" data-theme="<?= e($tk) ?>">全不选</button>
                <span class="text-muted" style="font-size:11px;">共 <?= count($roles) ?> 个角色组</span>
            </div>
            <div class="st-role-grid">
                <?php foreach ($roles as $r):
                    $rChecked = empty($m['role_allow']) ? true : (!empty($m['role_allow'][$r['code']])); ?>
                <div class="st-role-card">
                    <div class="r-info">
                        <span class="r-name"><?= e($r['name']) ?></span>
                        <span class="r-code"><?= e($r['code']) ?></span>
                    </div>
                    <label class="st-switch">
                        <input type="checkbox" name="<?= e($tk) ?>_role_allow[<?= e($r['code']) ?>]" value="1" <?= $rChecked ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <?php endforeach; ?>
                <?php if (empty($roles)): ?>
                <div class="text-muted" style="padding:16px;">暂无角色组</div>
                <?php endif; ?>
            </div>

            <!-- 保存按钮行 -->
            <div class="st-save-row">
                <button type="button" class="st-save-btn" data-theme="<?= e($tk) ?>">保存「<?= e($tname) ?>」权限</button>
                <span class="st-save-status" data-status-for="<?= e($tk) ?>"></span>
                <span class="st-save-meta">仅保存本主题，切换其他 tab 的修改不会丢失</span>
            </div>
        </div>
    </form>
</div>
<?php endforeach; ?>

<script>
(function () {
    /**
     * 行内"允许/禁止"文本随开关联动；只对当前激活 pane 内的 checkbox 起作用，
     * 避免切换 tab 后再触发一次。
     */
    function bindRowText(input) {
        var td = input.closest('td');
        if (!td) return;
        var span = td.querySelector('span.st-allow-yes, span.st-allow-no');
        if (!span) return;
        var on = input.checked;
        span.className = 'st-allow-' + (on ? 'yes' : 'no');
        span.textContent = on ? '允许' : '禁止';
    }

    document.querySelectorAll('.st-tab-pane.active input[type=checkbox]').forEach(function (cb) {
        cb.addEventListener('change', function () { bindRowText(cb); });
    });

    /** 全选 / 全不选：只作用于本 pane；pane 切换后再重新绑一次 change */
    document.querySelectorAll('.st-tab-pane').forEach(function (pane) {
        pane.querySelectorAll('.st-toolbar .btn-mini').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var act = btn.getAttribute('data-act');
                var isCat = act.indexOf('cat') === 0;
                var val = act.indexOf('all') === 0;
                var themeKey = btn.getAttribute('data-theme');
                var prefix = themeKey + (isCat ? '_cat_allow' : '_role_allow');
                pane.querySelectorAll('input[type=checkbox][name^="' + prefix + '"]').forEach(function (cb) {
                    cb.checked = val;
                    bindRowText(cb);
                });
            });
        });

        // 保存按钮
        var saveBtn = pane.querySelector('.st-save-btn');
        if (!saveBtn) return;
        saveBtn.addEventListener('click', function () {
            var form = pane.querySelector('form.st-form');
            if (!form) return;
            var themeKey = saveBtn.getAttribute('data-theme');
            var origText = saveBtn.textContent;
            saveBtn.disabled = true;
            saveBtn.textContent = '保存中…';
            var statusEl = document.querySelector('.st-save-status[data-status-for="' + themeKey + '"]');
            if (statusEl) { statusEl.textContent = ''; statusEl.classList.remove('error'); }

            // 用 FormData 自动带 csrf token + 全部 checkbox
            var fd = new FormData(form);
            postJSON('<?= url('admin/saveSpecialTheme') ?>', fd, function (res) {
                saveBtn.disabled = false;
                saveBtn.textContent = origText;
                if (res && res.code === 0) {
                    if (statusEl) {
                        var t = new Date();
                        var hh = String(t.getHours()).padStart(2, '0');
                        var mm = String(t.getMinutes()).padStart(2, '0');
                        var ss = String(t.getSeconds()).padStart(2, '0');
                        statusEl.textContent = '✓ 已保存（' + hh + ':' + mm + ':' + ss + '）';
                        statusEl.classList.remove('error');
                    }
                    toast('「' + ((res.data && res.data.name) || themeKey) + '」权限已保存', 'success');
                } else {
                    if (statusEl) {
                        statusEl.textContent = '× ' + ((res && res.message) || '保存失败');
                        statusEl.classList.add('error');
                    }
                    toast('保存失败：' + ((res && res.message) || '未知错误'), 'error', 5000);
                }
            }, function () {
                saveBtn.disabled = false;
                saveBtn.textContent = origText;
                if (statusEl) {
                    statusEl.textContent = '× 网络错误';
                    statusEl.classList.add('error');
                }
                toast('网络错误，保存失败', 'error');
            });
        });
    });
})();
</script>
