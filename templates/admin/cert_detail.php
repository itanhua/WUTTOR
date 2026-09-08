<?php /** 认证审核详情 */ $title = '认证详情 #' . $cert['id'];
$groupName = $cert['group_name'] ?? '实名认证';
$items = isset($items) ? $items : [];
$formData = isset($formData) ? $formData : [];
?>
<a href="<?= url('admin/certifications') ?>" class="btn btn-ghost" style="margin-bottom:16px;">返回列表</a>

<div class="flex-cards">
    <div class="card">
        <div class="card-header">申请人信息 · 「<?= e($groupName) ?>」</div>
        <div class="card-body">
            <table class="data-table">
                <tr><td width="100">用户名</td><td><?= e($cert['username']) ?></td></tr>
                <tr><td>昵称</td><td><?= e($cert['nickname']) ?></td></tr>
                <tr><td>邮箱</td><td><?= e($cert['email']) ?></td></tr>
                <tr><td>注册时间</td><td><?= $cert['user_created'] ?></td></tr>
                <tr><td>认证项目</td><td><strong style="color:#ea6f5a;"><?= e($groupName) ?></strong></td></tr>
                <tr><td>申请时间</td><td><?= $cert['created_at'] ?></td></tr>
                <?php if ($cert['status'] != 0): ?>
                <tr><td>审核人</td><td><?= e($cert['reviewer_name'] ?? '-') ?></td></tr>
                <tr><td>审核时间</td><td><?= $cert['reviewed_at'] ?></td></tr>
                <?php if ($cert['status'] == 2): ?>
                <tr><td>驳回原因</td><td style="color:#f5222d;"><?= e($cert['reject_reason']) ?></td></tr>
                <?php endif; ?>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header">「<?= e($groupName) ?>」提交内容（根据本组项目设置动态渲染）</div>
        <div class="card-body">
            <?php
            // 简单脱敏（手机号、身份证号）
            $masked = [];
            foreach ($formData as $k => $v) {
                if ($k === 'phone') $masked[$k] = function_exists('mask_phone') ? mask_phone($v) : $v;
                elseif ($k === 'id_card') $masked[$k] = function_exists('mask_id_card') ? mask_id_card($v) : $v;
                elseif ($k === 'real_name') $masked[$k] = function_exists('mask_name') ? mask_name($v) : $v;
                else $masked[$k] = $v;
            }
            ?>
            <?php if (empty($items) && empty($formData)): ?>
                <p class="text-muted" style="color:#999;">无提交内容</p>
            <?php else: ?>
            <table class="data-table">
                <?php
                // 按本组认证项顺序展示
                $rendered = [];
                foreach ($items as $it):
                    $name = $it['name']; $label = $it['label']; $type = $it['type'];
                    $val = $formData[$name] ?? '';
                    $rendered[$name] = true;
                ?>
                <tr>
                    <td width="100"><?= e($label) ?><?= $it['required'] ? ' <span style="color:#f5222d;">*</span>' : '' ?></td>
                    <td>
                        <?php if ($val === '' || $val === null): ?>
                            <span class="text-muted" style="color:#bbb;">（未填写）</span>
                        <?php elseif ($type === 'image' && !empty($val)): ?>
                            <img src="<?= upload_url($val) ?>" style="max-width:240px;max-height:240px;border-radius:6px;border:1px solid #eee;cursor:pointer;" onclick="window.open(this.src)">
                        <?php else: ?>
                            <?php if (in_array($name, ['phone', 'id_card', 'real_name'], true)): ?>
                                <strong><?= e($masked[$name] ?? $val) ?></strong>
                            <?php else: ?>
                                <pre style="margin:0;white-space:pre-wrap;font-family:inherit;"><?= e($val) ?></pre>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php
                // 兼容老数据：表单里 items 没声明但 formData 里有的字段也补显示
                foreach ($formData as $k => $v):
                    if (isset($rendered[$k])) continue;
                ?>
                <tr><td width="100"><?= e($k) ?></td><td><pre style="margin:0;white-space:pre-wrap;font-family:inherit;"><?= e($v) ?></pre></td></tr>
                <?php endforeach; ?>
            </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($cert['status'] == 0): ?>
<div class="card" style="margin-top:16px;">
    <div class="card-header">审核操作</div>
    <div class="card-body" style="display:flex;gap:16px;flex-wrap:wrap;">
        <button class="btn btn-primary btn-lg" onclick="approveCert(<?= $cert['id'] ?>)">审核通过「<?= e($groupName) ?>」</button>
        <button class="btn btn-danger btn-lg" onclick="rejectCert(<?= $cert['id'] ?>)">审核驳回「<?= e($groupName) ?>」</button>
    </div>
</div>
<?php endif; ?>

<script>
function approveCert(id) {
    confirmModal('审核通过', '确定通过该用户的「<?= e($groupName) ?>」认证？通过后用户将获得对应认证标识。', function() {
        postJSON(url('admin/approveCert', {id: id}), {_token: getCsrfToken()}, function(res) {
            toast(res.message, res.code === 0 ? 'success' : 'error');
            if (res.code === 0) setTimeout(function() { location.href = url('admin/certifications'); }, 1000);
        });
    });
}
function rejectCert(id) {
    var reason = prompt('请输入驳回「<?= e($groupName) ?>」的原因（将通知用户）：');
    if (!reason) return;
    postJSON(url('admin/rejectCert', {id: id}), {reject_reason: reason, _token: getCsrfToken()}, function(res) {
        toast(res.message, res.code === 0 ? 'success' : 'error');
        if (res.code === 0) setTimeout(function() { location.href = url('admin/certifications'); }, 1000);
    });
}
</script>
