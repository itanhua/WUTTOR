<?php /** 敏感词管理 */ $title = '敏感词库'; ?>
<?php
    // 禁用范围枚举：UI 文案 + 后端枚举
    $scopeMap = [
        'all'               => '全部（用户名/昵称+内容）',
        'username_nickname' => '仅用户名或昵称',
        'content'           => '仅发布内容',
    ];
?>
<div class="card" style="margin-bottom:16px;">
    <div class="card-header">添加敏感词</div>
    <div class="card-body">
        <form id="wordForm" onsubmit="return false;" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
            <?= csrf_field() ?>
            <div class="form-group" style="flex:1;"><label class="form-label">敏感词</label><input type="text" name="word" class="form-control" required maxlength="100"></div>
            <div class="form-group"><label class="form-label">级别</label>
                <select name="level" class="form-control">
                    <option value="1">普通</option>
                    <option value="2">高危</option>
                </select>
            </div>
            <div class="form-group"><label class="form-label">禁用范围</label>
                <select name="scope" class="form-control">
                    <?php foreach ($scopeMap as $k => $label): ?>
                        <option value="<?= e($k) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">添加</button>
        </form>
    </div>
</div>

<div class="card" style="margin-bottom:16px;">
    <div class="card-header">批量导入（CSV）</div>
    <div class="card-body">
        <form id="importForm" onsubmit="return false;" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
            <?= csrf_field() ?>
            <div class="form-group" style="flex:1;min-width:280px;">
                <label class="form-label">选择 CSV 文件</label>
                <input type="file" id="importFile" accept=".csv,text/csv" class="form-control" required>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary" id="importBtn"><i class="fa-solid fa-upload"></i> 开始导入</button>
            </div>
        </form>
        <p class="form-hint" style="color:#888;font-size:12px;margin-top:10px;line-height:1.6;">
            支持 CSV（UTF-8，建议先<a href="<?= url('admin/exportSensitiveWords') ?>" style="color:#ea6f5a;">导出 CSV</a>获得模板后再编辑）。
            列：<code>ID, 敏感词, 级别, 禁用范围, 状态, 添加时间</code>——首行可为表头，<strong>重复词自动跳过</strong>，中文/英文枚举值都接受（如「高危」=2，「全部」=all）。
        </p>
        <div id="importResult" style="display:none;margin-top:10px;padding:10px 12px;border-radius:4px;font-size:13px;"></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        敏感词列表（共 <?= $pagination['total'] ?> 个）
        <span style="float:right;display:flex;gap:8px;align-items:center;">
            <a href="<?= url('admin/exportSensitiveWords') ?>" class="btn btn-ghost btn-sm" style="text-decoration:none;">
                <i class="fa-solid fa-download"></i> 导出 CSV
            </a>
        </span>
    </div>
    <table class="data-table">
        <thead><tr><th>ID</th><th>敏感词</th><th>级别</th><th>禁用范围</th><th>状态</th><th>添加时间</th><th>操作</th></tr></thead>
        <tbody>
        <?php foreach ($words as $w):
            $scope = $w['scope'] ?? 'all';
            // 旧值 username 显示为「仅用户名或昵称」（与新枚举等价）
            if ($scope === 'username') $scope = 'username_nickname';
            $scopeLabel = $scopeMap[$scope] ?? '全部（用户名/昵称+内容）';
        ?>
        <tr data-id="<?= (int)$w['id'] ?>"
            data-word="<?= e($w['word']) ?>"
            data-level="<?= (int)$w['level'] ?>"
            data-scope="<?= e($scope) ?>"
            data-status="<?= (int)$w['status'] ?>">
            <td><?= (int)$w['id'] ?></td>
            <td><strong><?= e($w['word']) ?></strong></td>
            <td><?= (int)$w['level'] === 2 ? '<span class="status-tag danger">高危</span>' : '<span class="status-tag warning">普通</span>' ?></td>
            <td><span class="status-tag info"><?= e($scopeLabel) ?></span></td>
            <td><?= (int)$w['status'] === 1 ? '<span class="status-tag success">启用</span>' : '<span class="status-tag default">禁用</span>' ?></td>
            <td><?= e($w['created_at']) ?></td>
            <td>
                <a href="javascript:openEditSensitiveWord(<?= (int)$w['id'] ?>)" style="color:#ea6f5a;">编辑</a>
                &nbsp;|&nbsp;
                <a href="javascript:deleteWord(<?= (int)$w['id'] ?>)" style="color:#f5222d;">删除</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($words)): ?>
        <tr><td colspan="7" style="text-align:center;color:#999;padding:24px;">暂无敏感词</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php if ($pagination['last_page'] > 1): ?>
<?= pagination($pagination['total'], $page, 50, 'admin/sensitiveWords') ?>
<?php endif; ?>

<!-- 编辑敏感词弹窗（独立 modal，confirmModal 不支持输入） -->
<div class="modal-overlay" id="editWordModal">
    <div class="modal" style="max-width:520px;">
        <div class="modal-header"><span id="editWordModalTitle">编辑敏感词</span><button class="modal-close" onclick="closeEditSensitiveWord()">&times;</button></div>
        <div class="modal-body" style="font-size:14px;color:#555;padding:20px;">
            <input type="hidden" id="ew_id">
            <div class="form-group">
                <label class="form-label">敏感词</label>
                <input type="text" id="ew_word" class="form-control" maxlength="100">
            </div>
            <div class="form-group">
                <label class="form-label">级别</label>
                <select id="ew_level" class="form-control">
                    <option value="1">普通</option>
                    <option value="2">高危</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">禁用范围</label>
                <select id="ew_scope" class="form-control">
                    <?php foreach ($scopeMap as $k => $label): ?>
                        <option value="<?= e($k) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">状态</label>
                <select id="ew_status" class="form-control">
                    <option value="1">启用</option>
                    <option value="0">禁用</option>
                </select>
            </div>
            <p class="form-hint" style="color:#999;">保存后立即生效，影响后续注册 / 资料修改 / 帖子内容等所有敏感词校验。</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeEditSensitiveWord()">取消</button>
            <button class="btn btn-primary" id="ew_saveBtn">保存</button>
        </div>
    </div>
</div>

<script>
(function() {
    var SCOPE_LABELS = <?= json_encode($scopeMap, JSON_UNESCAPED_UNICODE) ?>;

    // 添加表单提交
    var addForm = document.getElementById('wordForm');
    if (addForm) {
        addForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var fd = new FormData(this); var data = {};
            fd.forEach(function(v, k) { data[k] = v; });
            postJSON(url('admin/addSensitiveWord'), data, function(res) {
                toast(res.message, res.code === 0 ? 'success' : 'error');
                if (res.code === 0) setTimeout(function() { location.reload(); }, 800);
            });
        });
    }

    // 编辑：把当前行 dataset 填到弹窗
    window.openEditSensitiveWord = function(id) {
        var row = document.querySelector('tr[data-id="' + id + '"]');
        if (!row) { toast('未找到该敏感词', 'error'); return; }
        document.getElementById('ew_id').value = row.dataset.id || '';
        document.getElementById('ew_word').value = row.dataset.word || '';
        document.getElementById('ew_level').value = row.dataset.level || '1';
        document.getElementById('ew_scope').value = row.dataset.scope || 'all';
        document.getElementById('ew_status').value = row.dataset.status || '1';
        document.getElementById('editWordModal').classList.add('active');
    };
    window.closeEditSensitiveWord = function() {
        document.getElementById('editWordModal').classList.remove('active');
    };

    // 保存编辑
    var saveBtn = document.getElementById('ew_saveBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', function() {
            var id = document.getElementById('ew_id').value;
            if (!id) { toast('参数错误', 'error'); return; }
            var word = document.getElementById('ew_word').value.trim();
            if (!word) { toast('请输入敏感词', 'error'); return; }
            var data = {
                id: id,
                word: word,
                level: document.getElementById('ew_level').value,
                scope: document.getElementById('ew_scope').value,
                status: document.getElementById('ew_status').value,
                _token: getCsrfToken(),
            };
            postJSON(url('admin/editSensitiveWord', {id: id}), data, function(res) {
                toast(res.message, res.code === 0 ? 'success' : 'error');
                if (res.code === 0) {
                    closeEditSensitiveWord();
                    setTimeout(function() { location.reload(); }, 800);
                }
            });
        });
    }

    // 删除
    window.deleteWord = function(id) {
        confirmModal('删除敏感词', '确定删除该敏感词？删除后立即生效。', function() {
            postJSON(url('admin/deleteSensitiveWord', {id: id}), {_token: getCsrfToken()}, function(res) {
                toast(res.message, res.code === 0 ? 'success' : 'error');
                if (res.code === 0) setTimeout(function() { location.reload(); }, 800);
            });
        });
    };
})();

/* ========== 批量导入 CSV ========== */
(function() {
    var importForm = document.getElementById('importForm');
    var importBtn = document.getElementById('importBtn');
    var fileInput = document.getElementById('importFile');
    var resultBox = document.getElementById('importResult');
    if (!importForm) return;

    function showResult(type, html) {
        resultBox.style.display = 'block';
        var colors = {
            info:    { bg: '#f0f5ff', border: '#adc6ff', color: '#2f54eb' },
            success: { bg: '#f6ffed', border: '#b7eb8f', color: '#389e0d' },
            error:   { bg: '#fff1f0', border: '#ffa39e', color: '#cf1322' }
        };
        var c = colors[type] || colors.info;
        resultBox.style.background = c.bg;
        resultBox.style.border = '1px solid ' + c.border;
        resultBox.style.color = c.color;
        resultBox.innerHTML = html;
    }

    importForm.addEventListener('submit', function(e) {
        e.preventDefault();
        if (!fileInput.files || !fileInput.files[0]) {
            toast('请选择 CSV 文件', 'error');
            return;
        }
        var f = fileInput.files[0];
        var name = (f.name || '').toLowerCase();
        if (!(name.endsWith('.csv') || f.type === 'text/csv' || f.type === 'application/vnd.ms-excel')) {
            toast('仅支持 .csv 文件', 'error');
            return;
        }
        if (f.size > 5 * 1024 * 1024) {
            toast('文件过大（最大 5MB）', 'error');
            return;
        }

        var fd = new FormData();
        fd.append('file', f);
        fd.append('_token', getCsrfToken());

        importBtn.disabled = true;
        var oldHtml = importBtn.innerHTML;
        importBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> 导入中...';
        showResult('info', '上传并解析中，请稍候...');

        fetch(url('admin/importSensitiveWords'), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': getCsrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
            body: fd,
            credentials: 'same-origin'
        }).then(function(resp) {
            return resp.json().then(function(json) {
                return { ok: resp.ok, status: resp.status, json: json };
            }).catch(function() { return { ok: resp.ok, status: resp.status, json: { code: -1, message: '响应解析失败' } }; });
        }).then(function(r) {
            importBtn.disabled = false;
            importBtn.innerHTML = oldHtml;
            var data = r.json || {};
            if (r.ok && data.code === 0) {
                var inData = data.data || {};
                var html = '<strong>✓ ' + (data.message || '导入完成') + '</strong>';
                if (typeof inData.total !== 'undefined') {
                    html += '<div style="margin-top:4px;font-size:12px;color:#666;">共读取 ' + (inData.total||0) + ' 行：成功 <b style="color:#52c41a;">' + (inData.inserted||0) + '</b> 条，跳过 <b style="color:#fa8c16;">' + (inData.skipped||0) + '</b> 条';
                    if ((inData.failed||0) > 0) html += '，失败 <b style="color:#f5222d;">' + (inData.failed) + '</b> 条';
                    html += '。</div>';
                }
                showResult('success', html);
                fileInput.value = '';
                setTimeout(function() { location.reload(); }, 1200);
            } else {
                showResult('error', '<strong>✗ ' + (data.message || ('请求失败 (' + r.status + ')')) + '</strong>');
            }
        }).catch(function(err) {
            importBtn.disabled = false;
            importBtn.innerHTML = oldHtml;
            showResult('error', '<strong>✗ 网络错误：' + (err && err.message ? err.message : '请重试') + '</strong>');
        });
    });
})();
</script>
