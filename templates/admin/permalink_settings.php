<?php
/** 固定连接 */
$title = '固定连接';
?>
<style>
.ps-card{margin-bottom:16px;}
.ps-list{display:flex;flex-direction:column;gap:10px;}
.ps-item{display:flex;align-items:flex-start;gap:12px;padding:12px 14px;border:1px solid #eee;border-radius:6px;background:#fafafa;cursor:pointer;transition:all .15s ease;}
.ps-item:hover{background:#fff5f2;border-color:#fcd9cf;}
.ps-item input[type=radio]{margin-top:4px;accent-color:#ea6f5a;cursor:pointer;}
.ps-item.checked{background:#fff5f2;border-color:#ea6f5a;box-shadow:0 0 0 2px rgba(234,111,90,.12);}
.ps-item-main{flex:1;min-width:0;}
.ps-item-title{font-size:14px;font-weight:600;color:#333;margin-bottom:4px;}
.ps-item-desc{font-size:12px;color:#888;line-height:1.6;margin-bottom:6px;}
.ps-item-preview{display:inline-block;padding:3px 8px;background:#fff;border:1px solid #e5e5e5;border-radius:3px;font-family:Menlo,Consolas,monospace;font-size:12px;color:#666;word-break:break-all;}
.ps-item-preview b{color:#ea6f5a;font-weight:600;}
.ps-tags{display:flex;gap:6px;flex-wrap:wrap;margin-top:4px;}
.ps-tag{font-size:10px;padding:2px 6px;border-radius:3px;background:#f0f0f0;color:#666;}
.ps-tag.rec{background:#fff0eb;color:#ea6f5a;}
.ps-tag.need{background:#fffbe6;color:#d48806;}
.ps-actions{display:flex;gap:10px;align-items:center;margin-top:8px;}
.ps-save-status{font-size:12px;color:#888;margin-left:auto;}
.ps-save-status.ok{color:#52c41a;}
.ps-save-status.err{color:#f5222d;}
.ps-note{background:#fffbe6;border:1px solid #ffe58f;border-radius:4px;padding:10px 12px;font-size:12px;color:#874d00;line-height:1.7;margin-bottom:14px;}
.ps-note code{background:#fff5cc;padding:1px 5px;border-radius:3px;font-size:11px;}
</style>

<form id="permalinkForm" onsubmit="return false;">
    <?= csrf_field() ?>
    <input type="hidden" name="permalink_structure" id="permalink_structure" value="<?= e($current) ?>">

    <div class="card ps-card">
        <div class="card-header">
            固定连接结构
            <span style="float:right;font-size:12px;color:#888;font-weight:normal;">影响前台公开页 URL 形态（首页/帖子/板块/用户主页），admin 仍走 ?r=admin/xxx</span>
        </div>
        <div class="card-body">

            <?php if ($missing_slug_count > 0 && in_array($current, ['title','cat_title'])): ?>
            <div class="ps-note">
                当前 <b><?= $current === 'title' ? '标题型' : '板块+标题型' ?></b> 模式下，检测到 <b><?= (int)$missing_slug_count ?></b> 条帖子尚未生成 <code>url_slug</code>。
                保存设置时会自动为这些帖子生成 slug（<b>中文标题保留中文，英文标题转小写连字符</b>），保证切换后历史链接立即可用。
            </div>
            <?php endif; ?>

            <div class="ps-list" id="psList">
                <label class="ps-item" data-value="default">
                    <input type="radio" name="structure_radio" value="default" <?= $current === 'default' ? 'checked' : '' ?>>
                    <div class="ps-item-main">
                        <div class="ps-item-title">默认（朴素）<span class="ps-tag">无改写</span></div>
                        <div class="ps-item-desc">保持 <code>index.php?r=...</code> 形式，不做伪静态。兼容性最好，但 URL 较长。</div>
                        <div class="ps-item-preview"><?= e(rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/')) ?>/index.php<b>?r=post/show&amp;id=123</b></div>
                    </div>
                </label>

                <label class="ps-item" data-value="id">
                    <input type="radio" name="structure_radio" value="id" <?= $current === 'id' ? 'checked' : '' ?>>
                    <div class="ps-item-main">
                        <div class="ps-item-title">ID 型<span class="ps-tag rec">推荐</span></div>
                        <div class="ps-item-desc">纯数字 ID，零数据库依赖（无需 <code>url_slug</code>），迁移成本最低。</div>
                        <div class="ps-item-preview"><b>/post/123</b> &nbsp; <b>/c/5</b> &nbsp; <b>/u/8</b> &nbsp; <b>/</b></div>
                    </div>
                </label>

                <label class="ps-item" data-value="cat_id">
                    <input type="radio" name="structure_radio" value="cat_id" <?= $current === 'cat_id' ? 'checked' : '' ?>>
                    <div class="ps-item-main">
                        <div class="ps-item-title">板块 + ID 型</div>
                        <div class="ps-item-desc">帖子 URL 带板块 ID。零 slug 依赖；URL 自带分类信息，但较短（无标题）。</div>
                        <div class="ps-item-preview"><b>/c/5/123</b> &nbsp; 板块页 <b>/c/5</b></div>
                    </div>
                </label>

                <label class="ps-item" data-value="title">
                    <input type="radio" name="structure_radio" value="title" <?= $current === 'title' ? 'checked' : '' ?>>
                    <div class="ps-item-main">
                        <div class="ps-item-title">标题型<span class="ps-tag need">需 url_slug</span></div>
                        <div class="ps-item-desc">URL 含标题转 slug，<b>中文标题会原样保留中文</b>（如 <code>/post/123-我的帖子</code>），SEO 友好。需 posts 表 <code>url_slug</code> 列。</div>
                        <div class="ps-item-preview"><b>/post/{id}-{slug}</b></div>
                    </div>
                </label>

                <label class="ps-item" data-value="cat_title">
                    <input type="radio" name="structure_radio" value="cat_title" <?= $current === 'cat_title' ? 'checked' : '' ?>>
                    <div class="ps-item-main">
                        <div class="ps-item-title">板块 + 标题型<span class="ps-tag need">需 url_slug</span></div>
                        <div class="ps-item-desc">板块 + 标题，最完整的 URL 结构。中文标题仍保留中文，SEO 最佳，URL 较长。</div>
                        <div class="ps-item-preview"><b>/c/{cat_id}/{id}-{slug}</b></div>
                    </div>
                </label>
            </div>

            <div class="ps-actions" style="margin-top:18px;">
                <button type="button" class="btn btn-primary" id="psSaveBtn"><i class="fa-solid fa-check"></i> 保存设置</button>
                <span class="ps-save-status" id="psSaveStatus"></span>
            </div>
        </div>
    </div>

    <div class="card ps-card">
        <div class="card-header">nginx 配置参考</div>
        <div class="card-body">
            <p style="color:#666;font-size:13px;line-height:1.7;margin-bottom:10px;">
                切换到 pretty URL 后，访问 <code>/post/123</code> 等路径需要 web 服务器把它转到 <code>index.php</code>。下面给出宝塔 nginx 的站点配置（放在 <code>location / { ... }</code> 块内最末尾）。
                改完记得 <code>nginx -t</code> 测试配置，<code>nginx -s reload</code> 生效。
            </p>
            <pre style="background:#1e1e1e;color:#d4d4d4;padding:14px;border-radius:6px;font-size:12px;line-height:1.7;overflow-x:auto;"><code>location / {
    try_files $uri $uri/ /index.php?$query_string;
}</code></pre>
            <p style="color:#888;font-size:12px;line-height:1.7;margin-top:10px;">
                <i class="fa-solid fa-circle-info"></i> 不改 nginx 也可以用：所有模板里的链接会自动用 pretty 形式，但用户从浏览器手动输入 <code>/post/123</code> 访问会 404。
                <br>· 后台 admin 链接始终走 <code>?r=admin/xxx</code>，不受影响。
                <br>· 旧 <code>?r=...</code> 链接会自动 301 跳转到新 pretty URL（保证外链和 SEO 不掉）。
            </p>
        </div>
    </div>
</form>

<script>
(function () {
    var items = document.querySelectorAll('.ps-item');
    var hidden = document.getElementById('permalink_structure');
    function sync() {
        var picked = '';
        items.forEach(function (it) {
            var radio = it.querySelector('input[type=radio]');
            if (radio.checked) {
                it.classList.add('checked');
                picked = radio.value;
            } else {
                it.classList.remove('checked');
            }
        });
        hidden.value = picked;
    }
    items.forEach(function (it) {
        var radio = it.querySelector('input[type=radio]');
        radio.addEventListener('change', sync);
        it.addEventListener('click', function (e) {
            if (e.target.tagName.toLowerCase() !== 'input') {
                radio.checked = true;
                sync();
            }
        });
    });
    sync();

    var saveBtn = document.getElementById('psSaveBtn');
    var statusEl = document.getElementById('psSaveStatus');
    saveBtn.addEventListener('click', function () {
        var structure = hidden.value;
        if (!structure) { statusEl.className = 'ps-save-status err'; statusEl.textContent = '请先选择一种风格'; return; }
        statusEl.className = 'ps-save-status'; statusEl.textContent = '保存中...';
        saveBtn.disabled = true;
        var fd = new FormData();
        // csrf_field() 实际输出 <input name="_token">，不是 _csrf_token
        // （原来 fallback 写错成 _csrf_token，导致 querySelector 拿到 null，.value 抛错
        //   → fetch 根本没发出，按钮永远停在"保存中"）
        var csrfName = (window.CSRF_TOKEN_NAME || '_token');
        var csrfEl = document.querySelector('input[name="' + csrfName + '"]');
        if (!csrfEl) { statusEl.className = 'ps-save-status err'; statusEl.textContent = '页面 CSRF 字段缺失，请刷新'; saveBtn.disabled = false; return; }
        fd.append(csrfName, csrfEl.value);
        fd.append('permalink_structure', structure);
        fetch('<?= url('admin/savePermalinkSettings') ?>', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (j) {
            if (j && j.code === 0) {
                statusEl.className = 'ps-save-status ok';
                var msg = '已保存';
                if (j.data && j.data.filled > 0) msg += '，已为 ' + j.data.filled + ' 条历史帖子生成 url_slug';
                statusEl.textContent = msg;
                setTimeout(function () { location.reload(); }, 1200);
            } else {
                statusEl.className = 'ps-save-status err';
                statusEl.textContent = (j && j.message) ? j.message : '保存失败';
                saveBtn.disabled = false;
            }
        })
        .catch(function (e) {
            statusEl.className = 'ps-save-status err';
            statusEl.textContent = '网络错误：' + (e.message || e);
            saveBtn.disabled = false;
        });
    });
})();
</script>
