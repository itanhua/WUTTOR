<?php /** 图标库 */ $title = '图标库';

// 客户端为分类导航 + 搜索做准备：把所有 FA6 图标按分类压平为扁平数据
// 模板端已通过 icon_index.json 渲染所有图标块，搜索/筛选由前端 JS 完成（无刷新）
$totalCount = 0;
foreach (($icons ?? []) as $it) $totalCount++;

// 给前端一个统计对象：分类 => 图标数
$catStat = [];
foreach (($categories ?? []) as $c) $catStat[$c['key']] = $c['count'];
?>
<div class="card" style="margin-bottom:16px;">
    <div class="card-header">
        <i class="fa-solid fa-icons" style="color:#ea6f5a;margin-right:6px;"></i>
        Font Awesome 图标库
        <span style="font-size:12px;color:#888;margin-left:8px;">
            本项目使用 FA <strong>6.7.2</strong> 免费版；
            类名前缀 <code>fa-solid fa-xxx</code> / <code>fa-brands fa-xxx</code>；
            点击任意图标即可复制完整类名
        </span>
        <span class="fa-icon-count">（共 <?= number_format($totalCount) ?> 个图标，<?= count($categories ?? []) ?> 个分类）</span>
    </div>
    <div class="card-body">
        <?php if (!empty($dataMissing)): ?>
        <div class="alert alert-error" style="margin-bottom:12px;">
            <strong>数据文件缺失：</strong>
            请确认 <code>data/fa6/free_icons_categorized.json</code> 与 <code>data/fa6/category_list.json</code> 存在并可读。
        </div>
        <?php endif; ?>

        <!-- 工具栏：搜索框 + 分类锚点 -->
        <div class="fa-icon-toolbar">
            <input type="search" id="faIconSearch" class="fa-icon-search"
                   placeholder="🔍 搜索图标名（如 house / github / weibo）或类名关键词（含拼音）..."
                   autocomplete="off">
            <div class="fa-cat-bar" id="faCatBar">
                <a href="#" class="active" data-cat="__all">全部</a>
                <?php foreach (($categories ?? []) as $cat): ?>
                <a href="#cat-<?= e($cat['key']) ?>" data-cat="<?= e($cat['key']) ?>">
                    <?= e($cat['label']) ?>
                    <?php if (!empty($cat['count'])): ?><span style="opacity:.6;">(<?= (int)$cat['count'] ?>)</span><?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 图标网格（按分类分块） -->
        <div id="faIconList">
            <?php
            // 按分类分组
            $byCat = [];
            foreach (($icons ?? []) as $it) {
                $cats = $it['categories'] ?? [];
                if (!$cats) $cats = ['Uncategorized'];
                $primary = $cats[0]; // 主分类
                $byCat[$primary][] = $it;
            }
            // 按 categories.json 顺序输出
            $orderedKeys = array_map(function ($c) { return $c['key']; }, ($categories ?? []));
            // 补：未在分类表中的项（理论上 free-solid 包内不会出现）
            foreach (array_keys($byCat) as $k) if (!in_array($k, $orderedKeys, true)) $orderedKeys[] = $k;
            foreach ($orderedKeys as $catKey):
                if (empty($byCat[$catKey])) continue;
                // 找分类 label
                $catLabel = $catKey;
                foreach (($categories ?? []) as $c) if ($c['key'] === $catKey) { $catLabel = $c['label']; break; }
            ?>
            <div class="fa-category-block" id="cat-<?= e($catKey) ?>" data-cat="<?= e($catKey) ?>">
                <div class="fa-category-title">
                    <?= e($catLabel) ?>
                    <span class="fa-icon-count">（<?= count($byCat[$catKey]) ?> 个）</span>
                </div>
                <div class="fa-icon-grid">
                    <?php foreach ($byCat[$catKey] as $it):
                        $name   = $it['name'];
                        $styles = $it['styles'] ?? ['solid'];
                        // 选 dominant style：brands 优先；否则第一个；通常 ['solid'] / ['solid','regular']
                        $family = in_array('brands', $styles, true) ? 'fa-brands'
                                : (in_array('solid', $styles, true) ? 'fa-solid'
                                : (in_array('regular', $styles, true) ? 'fa-regular'
                                : 'fa-solid'));
                        $cls   = $family . ' fa-' . $name;
                        $terms = $it['label'] . ' ' . implode(' ', $it['terms'] ?? []) . ' ' . $name;
                    ?>
                    <button type="button"
                            class="fa-icon-cell"
                            data-cls="<?= e($cls) ?>"
                            data-name="<?= e($name) ?>"
                            data-search="<?= e(mb_strtolower($terms . ' ' . $name)) ?>"
                            title="点击复制：<?= e($cls) ?>">
                        <i class="<?= e($cls) ?>" aria-hidden="true"></i>
                        <span class="fa-icon-name"><?= e($name) ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- 无搜索结果兜底 -->
        <div id="faNoResult" class="fa-no-result" style="display:none;">
            <i class="fa-solid fa-circle-exclamation" style="font-size:24px;color:#ccc;margin-bottom:8px;"></i><br>
            没有匹配的图标，换个关键词试试？
        </div>
    </div>
</div>

<style>
/* 图标库页面专属：限制一个 cell 高度让长名也能优雅换行 */
.fa-icon-cell { min-height: 84px; }
.fa-icon-name { font-family: SFMono-Regular, Consolas, monospace; }
</style>

<script>
(function () {
    var search = document.getElementById('faIconSearch');
    var catBar = document.getElementById('faCatBar');
    var list   = document.getElementById('faIconList');
    var noRes  = document.getElementById('faNoResult');
    var currentCat = '__all';

    // 通用：复制任意文本到剪贴板
    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text).catch(function () { return fallbackCopy(text); });
        }
        return Promise.resolve(fallbackCopy(text));
    }
    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text; document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); toast('已复制：' + text, 'success'); }
        catch (e) { toast('复制失败，请手动复制', 'error'); }
        document.body.removeChild(ta);
        return true;
    }

    // 1) 点击 cell 复制
    list.addEventListener('click', function (e) {
        var cell = e.target.closest && e.target.closest('.fa-icon-cell');
        if (!cell) return;
        var cls = cell.getAttribute('data-cls');
        if (!cls) return;
        copyText(cls).then(function () {
            toast('已复制：' + cls, 'success');
        });
    });

    // 2) 搜索过滤：合并 关键词 + 当前分类
    function applyFilter() {
        var q = (search.value || '').trim().toLowerCase();
        var totalVisible = 0;
        var blocks = list.querySelectorAll('.fa-category-block');
        blocks.forEach(function (block) {
            var catKey = block.getAttribute('data-cat');
            var cellInCat = 0;
            block.querySelectorAll('.fa-icon-cell').forEach(function (cell) {
                var hay = cell.getAttribute('data-search') || '';
                // 拼音/简中提示：关键词含搜索词 → 命中（也支持全空格分隔的多词 AND 匹配）
                var hit = true;
                if (q) {
                    var words = q.split(/\s+/).filter(Boolean);
                    hit = words.every(function (w) { return hay.indexOf(w) !== -1; });
                }
                if (hit) { cell.style.display = ''; cellInCat++; }
                else     { cell.style.display = 'none'; }
            });
            if (currentCat !== '__all' && currentCat !== catKey) {
                block.style.display = 'none';
            } else {
                block.style.display = (cellInCat > 0) ? '' : 'none';
                if (cellInCat > 0) totalVisible += cellInCat;
            }
        });
        noRes.style.display = (totalVisible === 0) ? '' : 'none';
    }

    search.addEventListener('input', applyFilter);

    // 3) 分类切换（点击分类条）
    catBar.addEventListener('click', function (e) {
        var a = e.target.closest && e.target.closest('a[data-cat]');
        if (!a) return;
        e.preventDefault();
        currentCat = a.getAttribute('data-cat');
        Array.prototype.forEach.call(catBar.querySelectorAll('a'), function (x) { x.classList.remove('active'); });
        a.classList.add('active');
        // 若是具体分类锚点 → 滚动到对应 block（applyFilter 不依赖滚动，但 scrollIntoView 提升 UX）
        if (currentCat !== '__all') {
            var target = document.getElementById('cat-' + currentCat);
            if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        applyFilter();
    });

    // 首次进入：应用一次过滤（让分类状态生效）
    applyFilter();
})();
</script>
