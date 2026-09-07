<?php /** 关注/粉丝列表 */ $title = $type === 'following' ? '关注' : '粉丝'; ?>
<div class="card">
    <div class="card-header">
        <?= e($user['nickname'] ?: $user['username']) ?><?= cert_badge_html($user, 14) ?> 的<?= $type === 'following' ? '关注' : '粉丝' ?>
    </div>
    <div class="card-body">
        <div class="tabs" style="border-radius:0;">
            <a href="<?= url('user/followers', ['id' => $user['id'], 'type' => 'followers']) ?>" class="<?= $type === 'followers' ? 'active' : '' ?>">粉丝</a>
            <a href="<?= url('user/followers', ['id' => $user['id'], 'type' => 'following']) ?>" class="<?= $type === 'following' ? 'active' : '' ?>">关注</a>
        </div>
        <?php if (empty($users)): ?>
        <div class="empty-state" style="padding:40px;"><p>暂无<?= $type === 'following' ? '关注' : '粉丝' ?></p></div>
        <?php else: ?>
        <?php foreach ($users as $u): ?>
        <div class="side-user-item" style="padding:12px 0;border-bottom:1px solid #f5f5f5;">
            <a href="<?= url('user/profile', ['id' => $u['id']]) ?>" class="avatar" style="width:48px;height:48px;font-size:16px;">
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
            <button class="btn btn-sm btn-ghost" onclick="followUser(<?= $u['id'] ?>, this)">关注</button>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
