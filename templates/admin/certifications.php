<?php /** 认证审核列表 */ $title = '认证审核'; ?>
<div class="card" style="margin-bottom:16px;">
    <div class="tabs" style="border-radius:6px;">
        <a href="<?= url('admin/certifications') ?>" class="<?= $status === '' ? 'active' : '' ?>">全部</a>
        <a href="<?= url('admin/certifications', ['status' => '0']) ?>" class="<?= $status === '0' ? 'active' : '' ?>">待审核</a>
        <a href="<?= url('admin/certifications', ['status' => '1']) ?>" class="<?= $status === '1' ? 'active' : '' ?>">已通过</a>
        <a href="<?= url('admin/certifications', ['status' => '2']) ?>" class="<?= $status === '2' ? 'active' : '' ?>">已驳回</a>
        <span style="flex:1;"></span>
        <span class="text-muted" style="font-size:13px;align-self:center;">共 <?= $total ?> 条</span>
    </div>
</div>

<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <?php if (can('certification.review')): ?><th width="30"><input type="checkbox" id="selectAll"></th><?php endif; ?>
                <th>ID</th><th>申请人</th><th>认证项目</th><th>真实姓名</th><th>手机号</th><th>状态</th><th>申请时间</th><th>审核时间</th><th>操作</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($certs as $c): ?>
        <tr>
            <?php if (can('certification.review')): ?><td><input type="checkbox" class="cert-checkbox" value="<?= $c['id'] ?>" <?= $c['status'] != 0 ? 'disabled' : '' ?>></td><?php endif; ?>
            <td><?= $c['id'] ?></td>
            <td>
                <div style="display:flex;align-items:center;gap:6px;">
                    <div style="width:24px;height:24px;border-radius:50%;background:#ea6f5a;color:#fff;display:flex;align-items:center;justify-content:center;font-size:10px;overflow:hidden;">
                        <?php if (!empty($c['avatar'])): ?>
                        <img src="<?= upload_url($c['avatar']) ?>" style="width:100%;height:100%;object-fit:cover;" alt="">
                        <?php else: ?>
                        <?= e(mb_substr($c['nickname'] ?: $c['username'], 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <?= e($c['nickname'] ?: $c['username']) ?>
                </div>
            </td>
            <td>
                <span class="status-tag" style="background:#fff7f4;color:#ea6f5a;border:1px solid #f5d5cc;"><?= e($c['group_name'] ?: '实名认证') ?></span>
            </td>
            <td><?= e($c['real_name'] ?: '—') ?></td>
            <td><?= e(mask_phone($c['phone'])) ?></td>
            <td>
                <?php
                $statusMap = [0 => ['待审核', 'warning'], 1 => ['已通过', 'success'], 2 => ['已驳回', 'danger']];
                $s = $statusMap[$c['status']];
                ?>
                <span class="status-tag <?= $s[1] ?>"><?= $s[0] ?></span>
                <?php if ($c['status'] == 2): ?>
                <div class="text-muted" style="font-size:11px;margin-top:2px;"><?= e(truncate($c['reject_reason'], 20)) ?></div>
                <?php endif; ?>
            </td>
            <td><?= $c['created_at'] ?></td>
            <td><?= $c['reviewed_at'] ?? '-' ?></td>
            <td class="table-actions">
                <a href="<?= url('admin/certificationDetail', ['id' => $c['id']]) ?>">查看详情</a>
                <?php if ($c['status'] == 0): ?>
                <a href="javascript:approveCert(<?= $c['id'] ?>)" style="color:#52c41a;">通过</a>
                <a href="javascript:rejectCert(<?= $c['id'] ?>)" style="color:#f5222d;">驳回</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php if (can('certification.review') && $status === '0'): ?>
<div style="margin-top:16px;text-align:center;">
    <button class="btn btn-primary" onclick="batchCert('approve')">批量通过</button>
    <button class="btn btn-danger" onclick="batchCert('reject')">批量驳回</button>
</div>
<?php endif; ?>

<script>
var selectAllCerts = document.getElementById('selectAll');
if (selectAllCerts) selectAllCerts.addEventListener('change', function() {
    document.querySelectorAll('.cert-checkbox:not(:disabled)').forEach(function(cb) { cb.checked = this.checked; }.bind(this));
});
function approveCert(id) {
    confirmModal('审核通过', '确定通过该用户的认证？', function() {
        postJSON(url('admin/approveCert', {id: id}), {_token: getCsrfToken()}, function(res) {
            toast(res.message, res.code === 0 ? 'success' : 'error');
            if (res.code === 0) setTimeout(function() { location.reload(); }, 800);
        });
    });
}
function rejectCert(id) {
    var reason = prompt('请输入驳回原因：');
    if (!reason) return;
    postJSON(url('admin/rejectCert', {id: id}), {reject_reason: reason, _token: getCsrfToken()}, function(res) {
        toast(res.message, res.code === 0 ? 'success' : 'error');
        if (res.code === 0) setTimeout(function() { location.reload(); }, 800);
    });
}
function batchCert(action) {
    var ids = [];
    document.querySelectorAll('.cert-checkbox:checked').forEach(function(cb) { ids.push(cb.value); });
    if (!ids.length) { toast('请先选择', 'warning'); return; }
    if (action === 'reject') {
        var reason = prompt('批量驳回原因：', '批量驳回');
        if (!reason) return;
        postJSON(url('admin/batchCert'), {ids: ids, action: 'reject', reject_reason: reason, _token: getCsrfToken()}, function(res) {
            toast(res.message, res.code === 0 ? 'success' : 'error');
            if (res.code === 0) setTimeout(function() { location.reload(); }, 800);
        });
    } else {
        confirmModal('批量通过', '确定批量通过选中的 ' + ids.length + ' 条申请？', function() {
            postJSON(url('admin/batchCert'), {ids: ids, action: 'approve', _token: getCsrfToken()}, function(res) {
                toast(res.message, res.code === 0 ? 'success' : 'error');
                if (res.code === 0) setTimeout(function() { location.reload(); }, 800);
            });
        });
    }
}
</script>
