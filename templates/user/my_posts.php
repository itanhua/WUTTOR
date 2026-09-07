<?php /** 我的帖子 */ $title = '我的帖子'; $hideSidebar = true; ?>
<div class="card">
    <div class="card-header">我的帖子（共 <?= $pagination['total'] ?> 篇）</div>
    <div class="post-list" style="box-shadow:none;">
        <?php if (empty($posts)): ?>
        <div class="empty-state" style="padding:40px;">
            <p>你还没有发布过帖子</p>
            <p style="margin-top:12px;"><a href="<?= url('post/create') ?>" class="btn btn-primary">发布第一篇</a></p>
        </div>
        <?php else: ?>
        <?php foreach ($posts as $post): ?>
        <?php $postUrl = url('post/show', ['id' => $post['id']]); ?>
        <div class="post-item <?= post_pinned_class($post) ?>" onclick="if(event.target.tagName!=='A'&&event.target.tagName!=='IMG')window.location.href='<?= $postUrl ?>'">
            <div class="post-main">
                <div class="post-title-row">
                    <?= post_author_icon(18) ?>
                    <h3 class="post-title">
                        <?php if ($post['is_pinned']): ?><span class="badge badge-pin">置顶</span><?php endif; ?>
                        <?php if ($post['is_essence']): ?><span class="badge badge-essence">精选</span><?php endif; ?>
                        <?= post_feature_tags($post) ?>
                        <a href="<?= $postUrl ?>" title="<?= e($post['title']) ?>"><?= e($post['title']) ?></a>
                    </h3>
                </div>
                <div class="post-meta">
                    <?php if ($post['category']): ?><a class="cat-tag" href="<?= url('home/index', ['cat' => $post['category']['id']]) ?>" style="<?= cat_color_style($post['category']['color']) ?>" onclick="event.stopPropagation();" title="进入 <?= e($post['category']['name']) ?> 板块"><?= e($post['category']['name']) ?></a><?php endif; ?>
                    <span><?= time_ago($post['created_at']) ?></span>
                    <span>浏览 <?= $post['view_count'] ?></span>
                    <span>评论 <?= $post['comment_count'] ?></span>
                    <a href="<?= url('post/edit', ['id' => $post['id']]) ?>">编辑</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php if ($pagination['last_page'] > 1): ?>
<?= pagination($pagination['total'], $page, 15, 'user/myPosts') ?>
<?php endif; ?>
