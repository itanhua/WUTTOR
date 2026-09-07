<?php /** 用户主页 */ $title = $user['nickname'] ?: $user['username']; $profileLayout = setting('post_layout', 'default'); ?>
<div class="profile-header">
    <div class="avatar-lg">
        <?php if (!empty($user['avatar'])): ?>
        <img src="<?= upload_url($user['avatar']) ?>" alt="">
        <?php else: ?>
        <?= e(mb_substr($user['nickname'] ?: $user['username'], 0, 1)) ?>
        <?php endif; ?>
    </div>
    <div class="info" style="flex:1;">
        <h2>
            <?= e($user['nickname'] ?: $user['username']) ?><?= cert_badge_html($user, 18) ?><?= uid_badge($user) ?>
            <?php
            // 角色显示名严格取自后台「角色权限」的角色名称，后台编辑后前台同步
            if (role_show_badge($user['role'])) {
                echo '<span class="badge-role">' . e(role_display_name($user['role'])) . '</span>';
            }
            if (empty($user['status']) || $user['status'] != 1) echo '<span class="badge-banned">封禁</span>';
            ?>
        </h2>
        <div class="bio"><?= render_mentions($user['bio']) ?: '这家伙很懒，什么也没留下' ?></div>
        <div class="stats">
            <div><div class="num"><?= format_count($likeCount) ?></div><div class="label">获赞</div></div>
            <div class="stat-item"><div class="num">Lv.<?= $levelInfo['level'] ?> <span style="font-size:12px;color:#ea6f5a;"><?= e($levelInfo['name']) ?></span></div><div class="label">等级</div></div>
            <div class="stat-item"><div class="num"><?= format_count($user['byte']) ?></div><div class="label"><?= e(\PointService::currencyLabel('byte')) ?></div></div>
            <?php if ($isOwn): ?><div class="stat-item"><div class="num"><?= format_count($user['token']) ?></div><div class="label"><?= e(\PointService::currencyLabel('token')) ?></div></div><?php endif; ?>
        </div>
    </div>
    <div>
        <?php if ($isOwn): ?>
        <a href="<?= url('user/edit') ?>" class="btn">编辑资料</a>
        <?php elseif (is_logged_in()): ?>
        <button class="btn <?= $isFollowing ? 'btn-ghost' : 'btn-primary' ?>" onclick="followUser(<?= $user['id'] ?>, this)"><?= $isFollowing ? '已关注' : '关注' ?></button>
        <a href="<?= url('message/chat', ['id' => $user['id']]) ?>" class="btn">私信</a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="profile-tabs">
        <a href="<?= url('user/profile', ['id' => $user['id'], 'tab' => 'posts']) ?>" class="profile-tab <?= $tab === 'posts' ? 'active' : '' ?>">文章 <span class="tab-count"><?= $postCount ?></span></a>
        <a href="<?= url('user/profile', ['id' => $user['id'], 'tab' => 'replies']) ?>" class="profile-tab <?= $tab === 'replies' ? 'active' : '' ?>">回复 <span class="tab-count"><?= $commentCount ?></span></a>
        <a href="<?= url('user/profile', ['id' => $user['id'], 'tab' => 'following']) ?>" class="profile-tab <?= $tab === 'following' ? 'active' : '' ?>">关注 <span class="tab-count"><?= $followingCount ?></span></a>
        <a href="<?= url('user/profile', ['id' => $user['id'], 'tab' => 'followers']) ?>" class="profile-tab <?= $tab === 'followers' ? 'active' : '' ?>">粉丝 <span class="tab-count"><?= $followerCount ?></span></a>
        <a href="<?= url('user/profile', ['id' => $user['id'], 'tab' => 'collects']) ?>" class="profile-tab <?= $tab === 'collects' ? 'active' : '' ?>">收藏 <span class="tab-count"><?= $collectCount ?? 0 ?></span></a>
    </div>
    <div class="card-body">
        <?php if ($tab === 'posts' || $tab === 'collects' || $tab === 'replies'): ?>
            <?php if (empty($tabItems)): ?>
            <div class="empty-state" style="padding:40px;"><p>暂无<?= $tab === 'collects' ? '收藏' : ($tab === 'replies' ? '回复' : '文章') ?></p></div>
            <?php else: ?>
            <?php if ($tab === 'replies'): ?>
            <div class="post-list" style="box-shadow:none;">
                <?php foreach ($tabItems as $r):
                    $anchorUrl = url('post/show', ['id' => $r['post_id']]) . '#comment-' . $r['id'];
                    $cat = $r['post_category'] ?? null;
                ?>
                <a class="reply-card" href="<?= $anchorUrl ?>" style="text-decoration:none;color:inherit;display:block;">
                    <div class="reply-quote">
                        <span class="reply-quote-label">回复于</span>
                        <?php if ($cat): ?>
                            <span class="reply-cat" style="<?= cat_color_style($cat['color'] ?? '') ?>"><?= e($cat['name']) ?></span>
                        <?php endif; ?>
                        <span class="reply-post-title"><?= e($r['post_title']) ?></span>
                    </div>
                    <div class="reply-snippet"><?= e(truncate(strip_tags($r['content'] ?? ''), 140)) ?></div>
                    <div class="reply-meta">
                        <span><?= time_ago($r['created_at']) ?></span>
                        <span class="reply-anchor"><?= e('#' . $r['id']) ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <?php if ($profileLayout === 'card'): ?>
            <?php /* 卡片版式：文章/收藏 tab 以封面卡片网格展示（与首页卡片一致） */ ?>
            <div class="card-grid profile-card-grid">
                <?php foreach ($tabItems as $post):
                    $postUrl = url('post/show', ['id' => $post['id']]);
                    $pcImgs = !empty($post['images']) ? json_decode($post['images'], true) : [];
                    $pcCover = trim((string)($post['cover_image'] ?? ''));
                    if ($pcCover === '' && !empty($pcImgs[0])) $pcCover = (string)$pcImgs[0];
                    $pcCoverUrl = $pcCover !== '' ? upload_url($pcCover) : '';
                    $pcCat = !empty($post['category_id']) ? Model::table('categories')->where('id', $post['category_id'])->first() : null;
                ?>
                <div class="post-card" onclick="if(event.target.tagName!=='A')window.location.href='<?= $postUrl ?>'">
                    <div class="post-cover">
                        <?php if ($pcCoverUrl !== ''): ?>
                        <img src="<?= e($pcCoverUrl) ?>" alt="" loading="lazy" onerror="this.parentNode.classList.add('no-cover');this.remove();">
                        <?php else: ?>
                        <div class="post-cover-placeholder">
                            <i class="<?= e(fa_icon_class($pcCat['icon'] ?? 'fa-solid fa-layer-group')) ?>"></i>
                            <span><?= e($pcCat['name'] ?? '') ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="post-cover-badges">
                            <?php if (!empty($post['is_pinned'])): ?><span class="cover-badge pin" title="置顶"><i class="fa-solid fa-thumbtack"></i></span><?php endif; ?>
                            <?php if (!empty($post['is_essence'])): ?><span class="cover-badge essence" title="精选"><i class="fa-solid fa-fire"></i></span><?php endif; ?>
                        </div>
                    </div>
                    <div class="post-body">
                        <?php if ($pcCat): ?>
                        <a class="card-cat-tag" href="<?= url('home/index', ['cat' => $pcCat['id']]) ?>" onclick="event.stopPropagation();" title="进入 <?= e($pcCat['name']) ?> 板块"><?= e($pcCat['name']) ?></a>
                        <?php endif; ?>
                        <h3 class="post-title" title="<?= e($post['title']) ?>"><?= e($post['title']) ?></h3>
                        <div class="post-author-row">
                            <span class="post-likes"><i class="fa-regular fa-thumbs-up"></i> <?= (int)($post['like_count'] ?? 0) ?></span>
                            <span style="color:#bbb;font-size:12px;"><i class="fa-regular fa-eye"></i> <?= (int)($post['view_count'] ?? 0) ?></span>
                            <span style="color:#bbb;font-size:12px;"><i class="fa-regular fa-comment"></i> <?= (int)($post['comment_count'] ?? 0) ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="post-list" style="box-shadow:none;">
            <?php foreach ($tabItems as $post): ?>
                <?php $postUrl = url('post/show', ['id' => $post['id']]); ?>
                <div class="post-item <?= post_pinned_class($post) ?>" onclick="if(event.target.tagName!=='A'&&event.target.tagName!=='IMG')window.location.href='<?= $postUrl ?>'">
                    <div class="post-main">
                        <div class="post-title-row">
                            <?= post_author_icon(18) ?>
                            <h3 class="post-title">
                                <?php if (!empty($post['is_pinned'])): ?><span class="badge badge-pin">置顶</span><?php endif; ?>
                                <?php if (!empty($post['is_essence'])): ?><span class="badge badge-essence">精选</span><?php endif; ?>
                                <?= post_feature_tags($post) ?>
                                <a href="<?= $postUrl ?>" title="<?= e($post['title']) ?>"><?= e($post['title']) ?></a>
                            </h3>
                        </div>
                        <div class="post-meta">
                            <?php if (!empty($post['author'])): ?>
                            <a href="<?= url('user/profile', ['id' => $post['author']['id']]) ?>" class="author"><?= e($post['author']['nickname'] ?: $post['author']['username']) ?></a>
                            <?php endif; ?>
                            <span><?= time_ago($post['created_at']) ?></span>
                            <span>浏览 <?= $post['view_count'] ?></span>
                            <span>评论 <?= $post['comment_count'] ?? 0 ?></span>
                            <span>赞 <?= $post['like_count'] ?? 0 ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
            <?php endif; /* card/list 分支结束 */ ?>
            <?php endif; /* replies 分支结束 */ ?>
            <?php endif; /* empty 分支结束 */ ?>
        <?php else: ?>
            <?php if (empty($tabItems)): ?>
            <div class="empty-state" style="padding:40px;"><p>暂无<?= $tab === 'following' ? '关注' : '粉丝' ?></p></div>
            <?php else: ?>
            <div class="user-list">
            <?php foreach ($tabItems as $u): ?>
                <div class="side-user-item" style="padding:12px 0;border-bottom:1px solid #f5f5f5;display:flex;align-items:center;gap:12px;">
                    <a href="<?= url('user/profile', ['id' => $u['id']]) ?>" class="avatar" style="width:48px;height:48px;font-size:16px;flex-shrink:0;">
                        <?php if (!empty($u['avatar'])): ?>
                        <img src="<?= upload_url($u['avatar']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" alt="">
                        <?php else: ?>
                        <?= e(mb_substr($u['nickname'] ?: $u['username'], 0, 1)) ?>
                        <?php endif; ?>
                    </a>
                    <div class="info" style="flex:1;min-width:0;">
                        <div class="name" style="font-size:14px;">
                            <a href="<?= url('user/profile', ['id' => $u['id']]) ?>" style="color:#333;"><?= e($u['nickname'] ?: $u['username']) ?><?= cert_badge_html($u) ?></a>
                        </div>
                        <div class="desc" style="color:#999;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e(truncate($u['bio'] ?? '这家伙很懒', 30)) ?></div>
                    </div>
                    <?php if (is_logged_in() && $u['id'] != Auth::id()): ?>
                    <button class="btn btn-sm btn-ghost" onclick="followUser(<?= $u['id'] ?>, this)">关注</button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($tabTotal > $perPage): ?>
        <?= pagination($tabTotal, $page, $perPage, 'user/profile', ['id' => $user['id'], 'tab' => $tab]) ?>
        <?php endif; ?>
    </div>
</div>
