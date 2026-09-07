<?php /** 认证申请表单 partial - 按 group_id 渲染对应认证项 */
$groupId = isset($group_id) ? (int)$group_id : 1;
$certItems = Model::table('certification_items')->where('group_id', $groupId)->where('status', 1)->orderBy('sort_order', 'ASC')->get();
// 已有数据（驳回重填时回显）
$existing = !empty($cert) && $cert['status'] == 2 ? json_decode($cert['form_data'] ?? '{}', true) : [];
?>
<form id="certForm" onsubmit="return false;">
    <?= csrf_field() ?>
    <input type="hidden" name="group_id" value="<?= $groupId ?>">
    <?php foreach ($certItems as $item):
        $val = $existing[$item['name']] ?? '';
        $req = $item['required'] ? ' <span class="required">*</span>' : '';
        $reqAttr = $item['required'] ? 'required' : '';
    ?>
    <div class="form-group">
        <label class="form-label"><?= e($item['label']) ?><?= $req ?></label>
        <?php if ($item['type'] === 'text'): ?>
        <input type="text" name="<?= e($item['name']) ?>" class="form-control" <?= $reqAttr ?> value="<?= e($val) ?>" placeholder="请输入<?= e($item['label']) ?>">
        <?php elseif ($item['type'] === 'textarea'): ?>
        <textarea name="<?= e($item['name']) ?>" class="form-control" <?= $reqAttr ?> style="min-height:80px;" placeholder="请输入<?= e($item['label']) ?>"><?= e($val) ?></textarea>
        <?php elseif ($item['type'] === 'select'):
            $opts = $item['options'] ? json_decode($item['options'], true) : [];
            if (!is_array($opts)) $opts = array_filter(array_map('trim', explode("\n", $item['options'])));
        ?>
        <select name="<?= e($item['name']) ?>" class="form-control" <?= $reqAttr ?>>
            <option value="">请选择<?= e($item['label']) ?></option>
            <?php foreach ($opts as $opt): ?>
            <option value="<?= e($opt) ?>" <?= $val === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
            <?php endforeach; ?>
        </select>
        <?php elseif ($item['type'] === 'image'): ?>
        <div class="upload-box" id="box-<?= e($item['name']) ?>" onclick="document.getElementById('input-<?= e($item['name']) ?>').click()">
            <?php if ($val): ?>
            <img src="<?= upload_url($val) ?>">
            <?php else: ?>
            <span class="upload-text">点击上传<?= e($item['label']) ?></span>
            <?php endif; ?>
        </div>
        <input type="file" id="input-<?= e($item['name']) ?>" accept="image/*" style="display:none;" data-name="<?= e($item['name']) ?>" class="cert-image-input">
        <input type="hidden" name="<?= e($item['name']) ?>" id="hidden-<?= e($item['name']) ?>" value="<?= e($val) ?>">
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <div class="alert alert-info" style="font-size:12px;">
        请确保信息真实有效。身份证号等敏感信息将加密存储，仅审核管理员可见。
        <?php if (!empty($certSubmitCost['enabled']) && (int)$certSubmitCost['amount'] > 0): ?>
        <div style="margin-top:6px;">
            本次申请需扣除
            <strong style="color:#ea6f5a;font-weight:600;"><?= (int)$certSubmitCost['amount'] ?> <?= e($certSubmitCost['currency_label']) ?></strong>
            ，提交成功将不予退还，余额不足将无法提交。
        </div>
        <?php endif; ?>
    </div>
    <div class="form-group">
        <button type="submit" class="btn btn-primary btn-lg" id="certSubmitBtn">支付并提交</button>
    </div>
</form>
<script>
document.querySelectorAll('.cert-image-input').forEach(function(inp) {
    inp.addEventListener('change', function() {
        if (!this.files[0]) return;
        var name = this.dataset.name;
        var fd = new FormData();
        fd.append('file', this.files[0]);
        fd.append('_token', document.querySelector('#certForm input[name="_token"]').value);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', url('upload/image', {type: 'certification'}));
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function() {
            if (xhr.status === 200) {
                var res = JSON.parse(xhr.responseText);
                if (res.code === 0) {
                    document.getElementById('box-' + name).innerHTML = '<img src="' + absoluteAssetUrl(res.data.url) + '">';
                    document.getElementById('hidden-' + name).value = res.data.url;
                    toast('上传成功', 'success');
                } else toast(res.message || '上传失败', 'error');
            }
        };
        xhr.send(fd);
        this.value = '';
    });
});
document.getElementById('certForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = document.getElementById('certSubmitBtn');
    var fd = new FormData(this); var data = {};
    fd.forEach(function(v, k) { data[k] = v; });
    btn.disabled = true; btn.textContent = '支付中...';
    var action = <?= !empty($cert) && $cert['status'] == 2 ? "'reapply'" : "'apply'" ?>;
    postJSON(url('certification/' + action), data, function(res) {
        if (res.code === 0) { toast(res.message, 'success'); setTimeout(function() { window.location.reload(); }, 1000); }
        else { toast(res.message || '提交失败', 'error'); btn.disabled = false; btn.textContent = '支付并提交'; }
    }, function(res) { toast(res.message || '网络错误', 'error'); btn.disabled = false; btn.textContent = '支付并提交'; });
});
</script>
