<?php
/** 表情包编辑 / 新建 */
$title = $pack ? ('编辑表情包：' . $pack['name']) : '新建表情包';
$isEdit = !empty($pack);
$isSystem = $isEdit && !empty($pack['is_system']);  // 仅用于决定是否显示「删除」入口；编辑字段一律可改
$packType = $isEdit ? $pack['type'] : 'unicode';
$cd = $isEdit ? ($pack['cd'] ?? '') : '';
$categories = [
    'smile'  => '笑脸与情感',
    'hand'   => '手势',
    'animal' => '动物',
    'food'   => '食物',
    'object' => '物品',
    'symbol' => '符号',
    'face'   => '颜文字',
    'other'  => '其它',
];
function emojiItemPreview($packType, $cd, $item) {
    if ($packType === 'image' && !empty($cd)) {
        $url = str_replace('{code}', (string)($item['image'] ?? $item['code'] ?? ''), (string)$cd);
        return '<img src="' . e($url) . '" alt="" style="width:22px;height:22px;object-fit:contain;">';
    }
    return e($item['char'] ?? (':' . ($item['code'] ?? '') . ':'));
}
?>
<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <span><?= e($title) ?></span>
        <a href="<?= url('admin/emojiPacks') ?>" class="btn btn-ghost btn-sm">← 返回列表</a>
    </div>
    <div class="card-body">
        <form id="packForm" onsubmit="return false;">
            <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$pack['id'] ?>"><?php endif; ?>
            <div class="form-group">
                <label class="form-label">表情包名称 <span class="required">*</span></label>
                <input type="text" name="name" class="form-control" value="<?= $isEdit ? e($pack['name']) : '' ?>" placeholder="如 我的颜文字包">
            </div>
            <div class="form-group">
                <label class="form-label">slug <span class="required">*</span></label>
                <input type="text" name="slug" class="form-control" value="<?= $isEdit ? e($pack['slug']) : '' ?>" placeholder="小写字母/数字/下划线，1-32 字符">
                <p class="form-hint">slug 用于数据库唯一标识，前台不直接使用；修改不影响现有表情引用（emoji_items 以 pack_id 关联）。</p>
            </div>
            <div style="display:flex;gap:16px;flex-wrap:wrap;">
                <div class="form-group" style="flex:1;min-width:200px;">
                    <label class="form-label">类型</label>
                    <select name="type" class="form-control" id="packTypeSel">
                        <option value="unicode" <?= $packType === 'unicode' ? 'selected' : '' ?>>字符(Unicode / 颜文字)</option>
                        <option value="image" <?= $packType === 'image' ? 'selected' : '' ?>>图片(CDN / 本地路径)</option>
                    </select>
                    <p class="form-hint">字符型显示原始 unicode 字符；图片型按「图片 URL 模板」拼接。修改类型会让现有表情的「字符/图片文件名」字段错位，请逐个补全。</p>
                </div>
                <div class="form-group" style="flex:1;min-width:160px;">
                    <label class="form-label">排序</label>
                    <input type="number" name="sort_order" class="form-control" value="<?= $isEdit ? (int)$pack['sort_order'] : 0 ?>">
                </div>
                <div class="form-group" style="flex:0 0 auto;display:flex;align-items:center;gap:6px;padding-top:28px;">
                    <input type="checkbox" name="enabled" value="1" <?= (!$isEdit || !empty($pack['enabled'])) ? 'checked' : '' ?>>
                    <label>启用</label>
                </div>
            </div>
            <div class="form-group" id="cdWrap" style="<?= $packType === 'image' ? '' : 'display:none;' ?>">
                <label class="form-label">图片 URL 模板（image 类型必填）</label>
                <input type="text" name="cd" class="form-control" value="<?= e($cd) ?>" placeholder="如 https://cdn.example.com/{code}.png">
                <p class="form-hint">用 <code>{code}</code> 占位符代表每个表情的文件名 / codepoint（由「图片文件名」字段填充）。</p>
            </div>
            <div class="form-group">
                <label class="form-label">封面图（可选）</label>
                <input type="text" name="cover" class="form-control" value="<?= $isEdit ? e($pack['cover'] ?? '') : '' ?>" placeholder="表情包在列表里的封面（URL 或相对路径）">
            </div>
            <?php if (!$isSystem): ?>
            <button type="button" class="btn btn-primary" onclick="savePack()">保存表情包</button>
            <?php else: ?>
            <button type="button" class="btn btn-primary" onclick="savePack()">保存表情包</button>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if ($isEdit): ?>
<div class="card" style="margin-top:16px;">
    <div class="card-header">表情列表（<?= count($items) ?>）</div>
    <div class="card-body">
        <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>预览</th>
                    <th>短代码</th>
                    <th>名称</th>
                    <th>关键词</th>
                    <th><?= $packType === 'image' ? '图片文件名' : '字符' ?></th>
                    <th>分类</th>
                    <th>启用</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $it): ?>
                <tr data-item-id="<?= (int)$it['id'] ?>">
                    <td style="text-align:center;font-size:20px;"><?= emojiItemPreview($packType, $cd, $it) ?></td>
                    <td><input type="text" class="form-control form-control-sm" data-f="code" value="<?= e($it['code']) ?>" style="width:130px;"></td>
                    <td><input type="text" class="form-control form-control-sm" data-f="name" value="<?= e($it['name']) ?>" style="width:120px;"></td>
                    <td><input type="text" class="form-control form-control-sm" data-f="keywords" value="<?= e($it['keywords'] ?? '') ?>" style="width:140px;"></td>
                    <td><input type="text" class="form-control form-control-sm" data-f="<?= $packType === 'image' ? 'image' : 'char' ?>" value="<?= e($packType === 'image' ? ($it['image'] ?? '') : ($it['char'] ?? '')) ?>" style="width:150px;"></td>
                    <td>
                        <select class="form-control form-control-sm" data-f="category" style="width:110px;">
                            <?php foreach ($categories as $ck => $cv): ?>
                            <option value="<?= $ck ?>" <?= ($it['category'] ?? 'other') === $ck ? 'selected' : '' ?>><?= e($cv) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td style="text-align:center;">
                        <input type="checkbox" data-f="enabled" <?= !empty($it['enabled']) ? 'checked' : '' ?>>
                    </td>
                    <td style="white-space:nowrap;">
                        <button type="button" class="btn btn-ghost btn-sm" onclick="saveItem(this)">保存</button>
                        <button type="button" class="btn btn-ghost btn-sm" style="color:#f5222d;" onclick="delItem(<?= (int)$it['id'] ?>)">删除</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($items)): ?>
                <tr><td colspan="8" style="text-align:center;color:#bbb;padding:20px;">该表情包暂无表情，在下方添加</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>

        <h4 style="margin:20px 0 10px;font-size:14px;">添加表情</h4>
        <form id="addItemForm" onsubmit="return false;" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group" style="margin:0;">
                <label class="form-label">短代码</label>
                <input type="text" class="form-control form-control-sm" id="addCode" placeholder="如 smile" style="width:120px;">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">名称</label>
                <input type="text" class="form-control form-control-sm" id="addName" placeholder="如 大笑" style="width:110px;">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">关键词</label>
                <input type="text" class="form-control form-control-sm" id="addKeywords" placeholder="笑 grin" style="width:130px;">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label"><?= $packType === 'image' ? '图片文件名' : '字符' ?></label>
                <input type="text" class="form-control form-control-sm" id="addCharOrImage" placeholder="<?= $packType === 'image' ? '1f600' : '😀' ?>" style="width:140px;">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">分类</label>
                <select class="form-control form-control-sm" id="addCategory" style="width:110px;">
                    <?php foreach ($categories as $ck => $cv): ?>
                    <option value="<?= $ck ?>"><?= e($cv) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="button" class="btn btn-primary btn-sm" onclick="addItem()">+ 添加</button>
        </form>
        <p class="form-hint">短代码仅限小写字母/数字/下划线（1-32 字符），发表后在正文/评论/私信用 <code>:短代码:</code> 引用。</p>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    var packType = <?= json_encode($packType) ?>;
    var packId = <?= $isEdit ? (int)$pack['id'] : 0 ?>;

    var typeSel = document.getElementById('packTypeSel');
    if (typeSel) {
        typeSel.addEventListener('change', function () {
            var cdWrap = document.getElementById('cdWrap');
            if (cdWrap) cdWrap.style.display = (typeSel.value === 'image') ? '' : 'none';
        });
    }

    window.savePack = function () {
        var f = document.getElementById('packForm');
        var fd = new FormData(f);
        var data = {};
        fd.forEach(function (v, k) { data[k] = (k === 'enabled') ? (f.elements['enabled'].checked ? 1 : 0) : v; });
        data.id = packId;
        data._token = getCsrfToken();
        postJSON(url('admin/emojiPackSave'), data, function (res) {
            toast(res.message, res.code === 0 ? 'success' : 'error');
            if (res.code === 0) setTimeout(function () { location.reload(); }, 600);
        }, function (res) { toast(res.message || '保存失败', 'error'); });
    };

    window.saveItem = function (btn) {
        var tr = btn.closest('tr');
        var id = tr.getAttribute('data-item-id');
        var f = {};
        tr.querySelectorAll('[data-f]').forEach(function (el) {
            var key = el.getAttribute('data-f');
            f[key] = (key === 'enabled') ? (el.checked ? 1 : 0) : el.value;
        });
        f.id = id;
        f.pack_id = packId;
        f._token = getCsrfToken();
        postJSON(url('admin/emojiItemSave'), f, function (res) {
            toast(res.message, res.code === 0 ? 'success' : 'error');
            if (res.code === 0) setTimeout(function () { location.reload(); }, 600);
        }, function (res) { toast(res.message || '保存失败', 'error'); });
    };

    window.delItem = function (id) {
        confirmModal('删除表情', '确定删除该表情？', function () {
            postJSON(url('admin/emojiItemDelete'), { id: id, _token: getCsrfToken() }, function (res) {
                toast(res.message, res.code === 0 ? 'success' : 'error');
                if (res.code === 0) setTimeout(function () { location.reload(); }, 600);
            });
        });
    };

    window.addItem = function () {
        var code = document.getElementById('addCode').value.trim();
        var name = document.getElementById('addName').value.trim();
        if (!code || !name) { toast('短代码和名称必填', 'error'); return; }
        var data = {
            id: 0,
            pack_id: packId,
            code: code,
            name: name,
            keywords: document.getElementById('addKeywords').value.trim(),
            category: document.getElementById('addCategory').value,
            sort_order: 0,
            _token: getCsrfToken()
        };
        if (packType === 'image') {
            data.image = document.getElementById('addCharOrImage').value.trim();
        } else {
            data.char = document.getElementById('addCharOrImage').value.trim();
        }
        postJSON(url('admin/emojiItemSave'), data, function (res) {
            toast(res.message, res.code === 0 ? 'success' : 'error');
            if (res.code === 0) setTimeout(function () { location.reload(); }, 600);
        }, function (res) { toast(res.message || '添加失败', 'error'); });
    };
})();
</script>
