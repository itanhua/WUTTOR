<?php /** 首页 - 帖子列表 */ $title = $currentCat ? $currentCat['name'] : '首页'; $postLayout = setting('post_layout', 'default'); ?>

<?php
// ===== 卡片版式首页顶部：立体轮播图 + 推荐位（后台「系统设置 → 版式设置」选择卡片后配置） =====
// 仅卡片版式 + 首页（无板块筛选）+ 第一页显示
$__carousel = json_decode(setting('home_carousel', ''), true);
if (!is_array($__carousel)) $__carousel = ['slides' => [], 'recommends' => []];
$__slides = array_values(array_filter(is_array($__carousel['slides'] ?? null) ? $__carousel['slides'] : [], function ($s) {
    return is_array($s) && trim((string)($s['image'] ?? '')) !== '';
}));
$__recos = array_slice(array_values(array_filter(is_array($__carousel['recommends'] ?? null) ? $__carousel['recommends'] : [], function ($s) {
    return is_array($s) && trim((string)($s['image'] ?? '')) !== '';
})), 0, 6);
$__showHero = ($postLayout === 'card') && empty($currentCat) && (int)($page ?? 1) === 1;
?>

<?php if ($__showHero && count($__slides) > 0): ?>
<div class="home-hero" style="margin-bottom:16px;">
    <div class="hero-carousel" id="heroCarousel">
        <div class="hero-track">
            <?php foreach ($__slides as $__si => $__sl): ?>
            <a class="hero-slide<?= $__si === 0 ? ' active' : '' ?>" href="<?= e(trim((string)($__sl['url'] ?? '')) ?: 'javascript:;') ?>"<?= preg_match('#^https?://#i', (string)($__sl['url'] ?? '')) ? ' target="_blank" rel="noopener"' : '' ?>>
                <img src="<?= e(upload_url($__sl['image'])) ?>" alt="" onerror="this.closest('.hero-slide').style.visibility='hidden'">
            </a>
            <?php endforeach; ?>
        </div>
        <?php if (count($__slides) > 1): ?>
        <button type="button" class="hero-arrow prev" aria-label="上一张"><i class="fa-solid fa-chevron-left"></i></button>
        <button type="button" class="hero-arrow next" aria-label="下一张"><i class="fa-solid fa-chevron-right"></i></button>
        <div class="hero-dots"></div>
        <?php endif; ?>
    </div>
</div>
<?php if (count($__slides) > 1): ?>
<script>
// ===== 首页立体轮播（卡片版式）：中间主图 + 两侧渐隐上/下一张，自动 + 手动切换 =====
(function() {
    var box = document.getElementById('heroCarousel');
    if (!box) return;
    var slides = Array.prototype.slice.call(box.querySelectorAll('.hero-slide'));
    var dotsBox = box.querySelector('.hero-dots');
    var cur = 0, timer = null, INTERVAL = 4500;
    if (slides.length < 2) return;

    // 生成指示点
    var dots = [];
    slides.forEach(function(_, i) {
        var d = document.createElement('span');
        d.className = 'hero-dot' + (i === 0 ? ' active' : '');
        d.addEventListener('click', function() { go(i); restart(); });
        dotsBox.appendChild(d);
        dots.push(d);
    });

    function render() {
        slides.forEach(function(s, i) {
            s.classList.remove('active', 'prev', 'next');
            if (i === cur) s.classList.add('active');
            else if (i === (cur - 1 + slides.length) % slides.length) s.classList.add('prev');
            else if (i === (cur + 1) % slides.length) s.classList.add('next');
        });
        dots.forEach(function(d, i) { d.classList.toggle('active', i === cur); });
    }
    function go(i) { cur = (i + slides.length) % slides.length; render(); }
    function restart() {
        if (timer) clearInterval(timer);
        timer = setInterval(function() { go(cur + 1); }, INTERVAL);
    }
    box.querySelector('.hero-arrow.prev').addEventListener('click', function() { go(cur - 1); restart(); });
    box.querySelector('.hero-arrow.next').addEventListener('click', function() { go(cur + 1); restart(); });
    box.addEventListener('mouseenter', function() { if (timer) clearInterval(timer); });
    box.addEventListener('mouseleave', restart);
    render();
    restart();
})();
</script>
<?php endif; ?>
<?php endif; ?>

<?php if ($__showHero && count($__recos) > 0): ?>
<div class="home-reco-grid" style="margin-bottom:18px;">
    <?php foreach ($__recos as $__rc): ?>
    <a class="home-reco-item" href="<?= e(trim((string)($__rc['url'] ?? '')) ?: 'javascript:;') ?>"<?= preg_match('#^https?://#i', (string)($__rc['url'] ?? '')) ? ' target="_blank" rel="noopener"' : '' ?>>
        <img src="<?= e(upload_url($__rc['image'])) ?>" alt="" loading="lazy" onerror="this.closest('.home-reco-item').style.display='none'">
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($currentCat) && !empty($currentCat['id'])): ?>
<div class="card cat-info-card" style="margin-bottom:16px;">
    <div class="cat-info-head">
        <?php if (!empty($currentCat['icon'])): ?>
        <i class="<?= e(fa_icon_class($currentCat['icon'])) ?>" style="font-size:20px;color:#ea6f5a;"></i>
        <?php endif; ?>
        <h3 style="margin:0;font-size:16px;">
            <?= e($currentCat['name']) ?>
            <?php if ($currentCat['is_certification_required']): ?><span class="badge badge-cert-sm" style="margin-left:6px;">认证专区</span><?php endif; ?>
        </h3>
        <?php $canEditRule = Auth::check() && Auth::isModeratorOf($currentCat['id']); ?>
        <?php if ($canEditRule): ?>
        <button type="button" class="btn btn-sm" style="margin-left:auto;" onclick="toggleRuleEdit()">编辑版规</button>
        <?php endif; ?>
    </div>
    <?php if (!empty($currentCat['description'])): ?>
    <div class="cat-info-desc"><?= nl2br(e($currentCat['description'])) ?></div>
    <?php endif; ?>
    <div class="cat-info-mods">
        <span style="color:#888;font-size:12px;">版主：</span>
        <?php if (!empty($currentCat['moderators'])): ?>
            <?php foreach ($currentCat['moderators'] as $cm): ?>
            <a href="<?= url('user/profile', ['id' => $cm['id']]) ?>" class="cat-mod-chip" style="background:<?= cat_color_style($currentCat['color']) ? '' : '#f5f5f5' ?>"><?= e($cm['nickname'] ?: $cm['username']) ?><?= cert_badge_html($cm, 12) ?></a>
            <?php endforeach; ?>
        <?php else: ?>
            <span style="color:#bbb;font-size:12px;">暂未设置</span>
        <?php endif; ?>
    </div>
    <div class="cat-info-rule" id="catRuleBox" data-can-edit="<?= $canEditRule ? '1' : '0' ?>" data-cat-id="<?= (int)$currentCat['id'] ?>">
        <div class="cat-info-rule-title">版规</div>
        <div class="cat-info-rule-body markdown-body" id="catRuleBody"><?= $currentCat['rule'] ? parse_markdown($currentCat['rule']) : '<span style="color:#bbb;">（暂无版规，版主可在右上方编辑）</span>' ?></div>
        <div class="cat-info-rule-editor" id="catRuleEditor" style="display:none;">
            <textarea id="catRuleText" class="form-control" rows="4" placeholder="请输入版规内容..."><?= e($currentCat['rule'] ?? '') ?></textarea>
            <div style="margin-top:8px;display:flex;gap:8px;">
                <button type="button" class="btn btn-primary btn-sm" id="catRuleSaveBtn">保存</button>
                <button type="button" class="btn btn-sm" onclick="toggleRuleEdit()">取消</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($postLayout === 'card'): ?>
<!-- 卡片式：不要白底大卡容器，卡片直接铺在页面背景上 -->
<div class="card-grid-shell" style="margin-bottom:16px;">
<?php else: ?>
<div class="card tab-merged-card" style="padding:0;overflow:hidden;margin-bottom:16px;">
<?php endif; ?>
    <div class="tabs" style="border-bottom:1px solid #f0f0f0;">
        <a href="<?= url('home/index', ['cat' => $cat, 'sort' => 'new']) ?>" class="<?= $sort === 'new' ? 'active' : '' ?>">最新</a>
        <a href="<?= url('home/index', ['cat' => $cat, 'sort' => 'hot']) ?>" class="<?= $sort === 'hot' ? 'active' : '' ?>">热门</a>
        <a href="<?= url('home/index', ['cat' => $cat, 'sort' => 'essence']) ?>" class="<?= $sort === 'essence' ? 'active' : '' ?>">精选</a>
        <span style="flex:1;"></span>
        <?php if (is_logged_in()): ?>
        <a href="<?= url('post/create') ?>" class="btn btn-primary btn-sm" style="margin:8px 0;color:#fff;">发布新文章</a>
        <?php endif; ?>
    </div>
    <?php if ($postLayout === 'card'): ?>
    <div class="card-grid" style="margin-bottom:0;">
        <?php if (empty($posts)): ?>
        <div class="empty-state">
            <div class="empty-icon">空</div>
            <p>暂无帖子<?php if ($sort === 'essence'): ?>，还没有精选内容<?php endif; ?></p>
            <?php if (is_logged_in()): ?>
            <p style="margin-top:12px;"><a href="<?= url('post/create') ?>" class="btn btn-primary">发布第一篇帖子</a></p>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <?php foreach ($posts as $i => $post): ?>
        <?php
        $postUrl = url('post/show', ['id' => $post['id']]);
        // 卡片式：全局置顶 / 本版置顶 一律显示「置顶」徽标；本版置顶只在所属板块页显示（与列表版式一致）
        $showPinBadge = !empty($post['is_pinned']) && (
            (int)$post['pin_scope'] === 2
            || (!empty($currentCat) && !empty($currentCat['id']) && (int)$currentCat['id'] === (int)$post['category_id'])
        );
        $authorName = e($post['author']['nickname'] ?? $post['author']['username'] ?? '匿名');
        // 封面图：cover_url（controller 注入）优先；controller 未注入时（旧文件/OPcache）模板自兜底，用 cover_image 现算
        $coverUrl = $post['cover_url'] ?? (!empty($post['cover_image']) ? upload_url($post['cover_image']) : '');
        $hasCover = !empty($coverUrl);
        ?>
        <div class="post-card <?= post_pinned_class($post, true) ?>" data-idx="<?= $i ?>" onclick="if(event.target.tagName!=='A')window.location.href='<?= $postUrl ?>'">
            <div class="post-cover">
                <?php if ($hasCover): ?>
                <img src="<?= e($coverUrl) ?>" alt="" loading="lazy" onerror="this.parentNode.classList.add('no-cover');this.remove();">
                <?php else: ?>
                <?php
                // 无封面占位：板块色低透明渐变 + 板块图标 + 板块名（比灰色「暂无封面图」体面）
                $ccColor = trim((string)($post['category']['color'] ?? ''));
                $ccOk = preg_match('/^#[0-9a-fA-F]{6}$/', $ccColor);
                $phStyle = $ccOk
                    ? 'background:linear-gradient(135deg,' . e($ccColor) . '1a,' . e($ccColor) . '3d);color:' . e($ccColor) . ';'
                    : '';
                ?>
                <div class="post-cover-placeholder" <?= $phStyle !== '' ? 'style="' . $phStyle . '"' : '' ?>>
                    <i class="<?= e(fa_icon_class($post['category']['icon'] ?? 'fa-solid fa-layer-group')) ?>"></i>
                    <span><?= e($post['category']['name'] ?? '') ?></span>
                </div>
                <?php endif; ?>
                <div class="post-cover-badges">
                    <?php if ($showPinBadge): ?>
                    <span class="cover-badge pin" title="置顶"><i class="fa-solid fa-thumbtack"></i></span>
                    <?php endif; ?>
                    <?php if ($post['is_essence']): ?>
                    <span class="cover-badge essence" title="精选"><i class="fa-solid fa-fire"></i></span>
                    <?php endif; ?>
                    <?php if ($hasVideo = false): /* 预留：检测到视频附件时显示播放图标 */ ?>
                    <span class="cover-badge play" title="视频"><i class="fa-solid fa-play"></i></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="post-body">
                <?php if (!empty($post['category'])): ?>
                <a class="card-cat-tag" href="<?= url('home/index', ['cat' => $post['category']['id']]) ?>" onclick="event.stopPropagation();" style="<?= cat_color_style($post['category']['color']) ?>" title="进入 <?= e($post['category']['name']) ?> 板块"><?= e($post['category']['name']) ?><?= !empty($post['category']['is_certification_required']) ? ' [认证]' : '' ?></a>
                <?php endif; ?>
                <h3 class="post-title" title="<?= e($post['title']) ?>"><?= e($post['title']) ?></h3>
                <div class="post-author-row">
                    <a class="post-author-avatar" href="<?= url('user/profile', ['id' => $post['user_id']]) ?>" onclick="event.stopPropagation();"><?= avatar_html($post['author'] ?? null, 22, false) ?></a>
                    <a class="post-author-name" href="<?= url('user/profile', ['id' => $post['user_id']]) ?>" onclick="event.stopPropagation();"><?= $authorName ?></a>
                    <?= cert_badge_html($post['author'] ?? null, 12) ?>
                    <span class="post-likes"><i class="fa-regular fa-thumbs-up"></i> <?= (int)($post['like_count'] ?? 0) ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="post-list<?= (!empty($currentCat) && !empty($currentCat['id'])) ? ' is-board' : '' ?>" style="border:0;border-radius:0;box-shadow:none;margin-bottom:0;">
    <?php if (empty($posts)): ?>
    <div class="empty-state">
        <div class="empty-icon">空</div>
        <p>暂无帖子<?php if ($sort === 'essence'): ?>，还没有精选内容<?php endif; ?></p>
        <?php if (is_logged_in()): ?>
        <p style="margin-top:12px;"><a href="<?= url('post/create') ?>" class="btn btn-primary">发布第一篇帖子</a></p>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <?php foreach ($posts as $i => $post): ?>
    <?php $postUrl = url('post/show', ['id' => $post['id']]); ?>
    <div class="post-item <?= post_pinned_class($post, true) ?>" onclick="if(event.target.tagName!=='A')window.location.href='<?= $postUrl ?>'">
        <div class="post-avatar-left">
            <?= avatar_html($post['author'] ?? null, 40) ?>
        </div>
        <div class="post-main">
            <div class="post-title-row">
                <h3 class="post-title">
                    <?php
                    // 置顶徽标按范围显示：全局置顶(scope=2) 任何位置都显示；
                    // 本版置顶(scope=1) 仅在该帖子所属板块页面显示，避免「本版置顶」在首页/其它板块误显示。
                    $showPinBadge = false;
                    $pinBadgeText = '';
                    if (!empty($post['is_pinned'])) {
                        if ((int)$post['pin_scope'] === 2) {
                            $showPinBadge = true;
                            $pinBadgeText = '全局置顶';
                        } elseif (!empty($currentCat) && !empty($currentCat['id']) && (int)$currentCat['id'] === (int)$post['category_id']) {
                            $showPinBadge = true;
                            $pinBadgeText = '本版置顶';
                        }
                    }
                    ?>
                    <?php if ($showPinBadge): ?><span class="badge badge-pin"><?= $pinBadgeText ?></span><?php endif; ?>
                    <?php if ($post['is_essence']): ?><span class="badge badge-essence">精选</span><?php endif; ?>
                    <?= post_feature_tags($post) ?>
                    <a href="<?= $postUrl ?>" title="<?= e($post['title']) ?>"><?= e($post['title']) ?></a>
                </h3>
            </div>
            <div class="post-meta">
                <span class="meta-author-wrap">
                    <span class="post-meta-author-icon"><?= post_author_icon(14) ?></span>
                    <span class="meta-label">作者</span>
                    <a href="<?= url('user/profile', ['id' => $post['user_id']]) ?>" class="author"><?= e($post['author']['nickname'] ?: $post['author']['username']) ?></a>
                </span>
                <span class="meta-time"><?= time_ago($post['created_at']) ?></span>
                <span class="meta-views" title="浏览"><i class="fa-solid fa-eye"></i><span class="meta-label">浏览</span><?= $post['view_count'] ?></span>
                <span class="meta-comments" title="评论"><i class="fa-solid fa-comment"></i><span class="meta-label">评论</span><?= $post['comment_count'] ?></span>
                <?php if (!empty($post['last_reply_user'])): ?>
                <span class="meta-last-reply" title="最后回复">
                    <span class="post-meta-author-icon"><?= post_author_icon(14) ?></span>
                    <span class="meta-label">最后回复</span>
                    <a href="<?= url('user/profile', ['id' => $post['last_reply_user']['id']]) ?>" class="last-reply-user"><?= e($post['last_reply_user']['nickname'] ?: $post['last_reply_user']['username']) ?></a>
                    <span><?= time_ago($post['last_reply_at']) ?></span>
                </span>
                <?php endif; ?>
                <?php if (!empty($post['category'])): ?>
                <a class="cat-tag" href="<?= url('home/index', ['cat' => $post['category']['id']]) ?>" style="margin-left:auto;<?= cat_color_style($post['category']['color']) ?>" onclick="event.stopPropagation();" title="进入 <?= e($post['category']['name']) ?> 板块"><?= e($post['category']['name']) ?><?= !empty($post['category']['is_certification_required']) ? ' [认证]' : '' ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($pagination['last_page'] > 1): ?>
<?= pagination($pagination['total'], $page, $perPage, 'home/index', ['cat' => $cat, 'sort' => $sort]) ?>
<?php endif; ?>

<script>
(function() {
    var box = document.getElementById('catRuleBox');
    if (!box || box.getAttribute('data-can-edit') !== '1') return;
    var catId = box.getAttribute('data-cat-id');
    var body = document.getElementById('catRuleBody');
    var editor = document.getElementById('catRuleEditor');
    var text = document.getElementById('catRuleText');
    var btn = document.getElementById('catRuleSaveBtn');
    window.toggleRuleEdit = function() {
        if (editor.style.display === 'none') {
            text.value = body.innerText.trim();
            editor.style.display = 'block';
        } else {
            editor.style.display = 'none';
        }
    };
    btn.addEventListener('click', function() {
        btn.disabled = true; btn.textContent = '保存中...';
        postJSON(url('home/updateCategoryRule'), {id: parseInt(catId, 10), rule: text.value, _token: getCsrfToken()}, function(res) {
            if (res.code === 0) {
                // 用服务端渲染的 HTML（parse_markdown）回填
                var html = res.data && res.data.html ? res.data.html : '';
                if (html === '') {
                    body.innerHTML = '<span style="color:#bbb;">（暂无版规，版主可在右上方编辑）</span>';
                } else {
                    body.innerHTML = html;
                }
                editor.style.display = 'none';
                toast(res.message, 'success');
            } else {
                toast(res.message || '保存失败', 'error');
            }
            btn.disabled = false; btn.textContent = '保存';
        }, function() {
            toast('网络错误', 'error');
            btn.disabled = false; btn.textContent = '保存';
        });
    });
})();
</script>

<?php if ($postLayout === 'card'): ?>
<script>
// 卡片式版式：入场放大加载（stagger）
(function() {
    var cards = document.querySelectorAll('.post-card');
    cards.forEach(function(card, idx) {
        card.style.animationDelay = (idx < 16 ? idx * 40 : 640) + 'ms';
    });
})();
</script>
<?php endif; ?>
