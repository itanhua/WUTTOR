<?php /** 搜索页 */ $title = '搜索'; $hideSidebar = true; ?>
<div class="card" style="margin-bottom:16px;">
    <div class="tabs">
        <a href="<?= url('search/index', ['q' => $q, 'type' => 'all']) ?>" class="<?= $type === 'all' ? 'active' : '' ?>">全部</a>
        <a href="<?= url('search/index', ['q' => $q, 'type' => 'post']) ?>" class="<?= $type === 'post' ? 'active' : '' ?>">帖子 (<?= $postTotal ?>)</a>
        <a href="<?= url('search/index', ['q' => $q, 'type' => 'user']) ?>" class="<?= $type === 'user' ? 'active' : '' ?>">用户 (<?= $userTotal ?>)</a>
    </div>
</div>

<?php if ($q === ''): ?>
<div class="card">
    <div class="empty-state" style="padding:60px;">
        <p>请输入关键词进行搜索</p>
    </div>
</div>
<?php else: ?>

<?php if ($type === 'all' || $type === 'post'): ?>
<div class="card">
    <div class="card-header">帖子结果（<?= $postTotal ?>）</div>
    <div class="post-list" style="box-shadow:none;">
        <?php if (empty($posts)): ?>
        <div class="empty-state" style="padding:30px;"><p>未找到相关帖子</p></div>
        <?php else: ?>
        <?php foreach ($posts as $post): ?>
        <?php $postUrl = url('post/show', ['id' => $post['id']]); ?>
        <div class="post-item <?= post_pinned_class($post) ?>" onclick="if(event.target.tagName!=='A'&&event.target.tagName!=='IMG')window.location.href='<?= $postUrl ?>'">
            <div class="post-main">
                <div class="post-title-row">
                    <?= post_author_icon(18) ?>
                    <h3 class="post-title"><?= post_feature_tags($post) ?><a href="<?= $postUrl ?>" title="<?= e($post['title']) ?>"><?= e($post['title']) ?></a></h3>
                </div>
                <div class="post-meta">
                    <a href="<?= url('user/profile', ['id' => $post['author']['id']]) ?>" class="author"><?= e($post['author']['nickname'] ?: $post['author']['username']) ?></a>
                    <?php if ($post['category']): ?><a class="cat-tag" href="<?= url('home/index', ['cat' => $post['category']['id']]) ?>" style="<?= cat_color_style($post['category']['color']) ?>" onclick="event.stopPropagation();" title="进入 <?= e($post['category']['name']) ?> 板块"><?= e($post['category']['name']) ?></a><?php endif; ?>
                    <span class="meta-views">浏览 <?= $post['view_count'] ?? 0 ?></span>
                    <span class="meta-comments">评论 <?= $post['comment_count'] ?? 0 ?></span>
                    <span><?= time_ago($post['created_at']) ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($type === 'all' || $type === 'user'): ?>
<div class="card">
    <div class="card-header">用户结果（<?= $userTotal ?>）</div>
    <div class="card-body">
        <?php if (empty($users)): ?>
        <div class="empty-state" style="padding:30px;"><p>未找到相关用户</p></div>
        <?php else: ?>
        <?php foreach ($users as $u): ?>
        <div class="side-user-item" style="padding:12px 0;border-bottom:1px solid #f5f5f5;">
            <a href="<?= url('user/profile', ['id' => $u['id']]) ?>" style="width:48px;height:48px;border-radius:50%;background:#ea6f5a;color:#fff;display:flex;align-items:center;justify-content:center;font-size:16px;overflow:hidden;">
                <?php if (!empty($u['avatar'])): ?>
                <img src="<?= upload_url($u['avatar']) ?>" style="width:100%;height:100%;object-fit:cover;" alt="">
                <?php else: ?>
                <?= e(mb_substr($u['nickname'] ?: $u['username'], 0, 1)) ?>
                <?php endif; ?>
            </a>
            <div class="info">
                <div class="name" style="font-size:14px;">
                    <a href="<?= url('user/profile', ['id' => $u['id']]) ?>" style="color:#333;"><?= e($u['nickname'] ?: $u['username']) ?><?= cert_badge_html($u) ?></a>
                </div>
                <div class="desc"><?= e(truncate($u['bio'] ?? '这家伙很懒', 30)) ?></div>
            </div>
            <?php if (is_logged_in() && $u['id'] != Auth::id()): ?>
            <a href="<?= url('message/chat', ['id' => $u['id']]) ?>" class="btn btn-sm">私信</a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>
