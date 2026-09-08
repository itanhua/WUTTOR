<?php /** 我的收藏 */ $title = '我的收藏'; $hideSidebar = true; ?>
<div class="card">
    <div class="card-header">我的收藏（共 <?= $pagination['total'] ?> 篇）</div>
    <div class="post-list" style="box-shadow:none;">
        <?php if (empty($posts)): ?>
        <div class="empty-state" style="padding:40px;"><p>你还没有收藏过帖子</p></div>
        <?php else: ?>
        <?php foreach ($posts as $post): ?>
        <?php $postUrl = url('post/show', ['id' => $post['id']]); ?>
        <div class="post-item <?= post_pinned_class($post) ?>" onclick="if(event.target.tagName!=='A'&&event.target.tagName!=='IMG')window.location.href='<?= $postUrl ?>'">
            <div class="post-main">
                <div class="post-title-row">
                    <?= post_author_icon(18) ?>
                    <h3 class="post-title">
                        <?= post_feature_tags($post) ?>
                        <a href="<?= $postUrl ?>" title="<?= e($post['title']) ?>"><?= e($post['title']) ?></a>
                    </h3>
                </div>
                <div class="post-meta">
                    <a href="<?= url('user/profile', ['id' => $post['author']['id']]) ?>" class="author"><?= e($post['author']['nickname'] ?: $post['author']['username']) ?></a>
                    <span><?= time_ago($post['created_at']) ?></span>
                    <span>浏览 <?= $post['view_count'] ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php if ($pagination['last_page'] > 1): ?>
<?= pagination($pagination['total'], $page, 15, 'user/myCollects') ?>
<?php endif; ?>
