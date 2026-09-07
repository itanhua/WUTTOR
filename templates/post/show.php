<?php /** 帖子详情页 */ $title = $post['title']; $js = 'js/mention.js'; ?>
<style>
/* ===== 特殊主题统一样式 ===== */
.special-box { margin-top:16px; border:1px solid #eee; border-radius:14px; padding:14px 16px; background:#fafafa; box-shadow:var(--shadow); }
.special-box .sb-head { display:flex; align-items:center; gap:8px; font-size:15px; font-weight:600; color:#2f2f2f; margin-bottom:10px; }
.sb-badge { display:inline-block; font-size:11px; font-weight:600; color:#fff; padding:2px 10px; border-radius:10px; }
.sb-badge.pay { background:#ea6f5a; } .sb-badge.event { background:#7c5cff; }
.sb-badge.bounty { background:#e08600; } .sb-badge.poll { background:#1e9e6a; }
.sb-badge.debate { background:#1e6fd9; } .sb-badge.interview { background:#d94f8a; }
.sb-badge.lottery { background:#c2185b; }
.sb-note { font-size:12px; color:#999; margin-top:8px; }
.sb-note.ok { color:#16a34a; }
.sb-status { font-size:12px; color:#666; margin-bottom:8px; }

/* 付费墙 */
.paywall-media.is-locked { position:relative; }
.paywall-media.is-locked .post-gallery,
.paywall-media.is-locked .post-attachments { filter:blur(9px); pointer-events:none; user-select:none; }
.paywall-overlay { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; cursor:pointer; background:rgba(255,255,255,.35); border-radius:8px; }
.pw-inner { text-align:center; padding:20px 28px; background:#fff; border:1px solid #eee; border-radius:10px; box-shadow:0 4px 16px rgba(0,0,0,.08); }
.pw-icon { font-size:30px; }
.pw-title { font-size:15px; font-weight:600; margin:6px 0 2px; }
.pw-sub { font-size:13px; color:#666; margin-bottom:12px; }

/* 付费主题 - 正文预览后的渐变到白色遮罩（营造"还有更多未显示"感） */
.paywall-fade { position:relative; margin-top:-40px; height:80px; background:linear-gradient(to bottom,rgba(255,255,255,0) 0%,#fff 70%); pointer-events:none; }

/* 活动 */
.event-meta { display:flex; flex-wrap:wrap; gap:14px; font-size:13px; color:#555; margin-bottom:10px; }
.event-signups { margin-top:10px; padding-top:10px; border-top:1px dashed #eee; }
.es-title { font-size:12px; color:#999; margin-bottom:8px; }
.es-list { display:grid; grid-template-columns:repeat(auto-fill, minmax(150px, 1fr)); gap:8px; }
.es-item { display:flex; align-items:center; gap:8px; padding:6px 8px; background:#fff; border:1px solid #f0f0f0; border-radius:6px; }
.es-avatar { width:32px; height:32px; border-radius:50%; overflow:hidden; background:#ea6f5a; color:#fff; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.es-avatar img { width:100%; height:100%; object-fit:cover; display:block; }
.es-avatar.es-no-img { background:#ea6f5a; }
.es-initial { font-size:13px; font-weight:600; line-height:1; }
.es-meta { min-width:0; flex:1; }
.es-name { font-size:13px; color:#333; font-weight:500; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.es-time { font-size:11px; color:#999; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }

/* 抽奖主题（倒计时 + 规则 chips + 奖品卡片 + 中奖名单） */
.countdown-chip {
    margin-left:auto; font-size:12px; font-weight:600; color:#fff; background:#ea6f5a;
    padding:2px 10px; border-radius:12px; font-variant-numeric:tabular-nums; letter-spacing:.3px;
    flex-shrink:0;
}
.countdown-chip.ended { background:#999; }
/* 已开奖：仅显示灰色「已开奖」三字，无倒计时 / 无彩色提示 */
.lottery-ended { color:#999; font-weight:600; }
.lottery-prize-box {
    display:flex; align-items:center; gap:12px; padding:12px; background:#fff;
    border:1px solid #f0f0f0; border-radius:8px; margin-bottom:10px;
}
.lp-emoji {
    font-size:30px; line-height:1; flex-shrink:0; width:48px; height:48px;
    display:flex; align-items:center; justify-content:center; background:#fdf2f6; border-radius:10px;
}
.lp-info { min-width:0; flex:1; }
.lp-name { font-size:14px; color:#333; }
.lp-type { font-size:13px; color:#c2185b; font-weight:600; margin-top:2px; }
.lp-meta { font-size:12px; color:#888; margin-top:3px; }
.lottery-rules { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:10px; }
.lr-chip { font-size:12px; padding:3px 10px; border-radius:12px; border:1px solid transparent; }
.lr-must { background:#fdecea; color:#c2185b; border-color:#f5c6c0; font-weight:600; }
.lr-optional { background:#fff7e6; color:#d48806; border-color:#ffe7ba; }
.lr-no { background:#f5f5f5; color:#999; border-color:#eee; }
/* 抽奖主题：开奖后中奖名单直接复用悬赏采纳 .bounty-accepted/.ba-item 视觉（绿头像 + +N 积分 + ✓ 中奖） */

/* 悬赏主题（2026-08-31 重构）*/
.bounty-box .sb-head .sb-note.ok {
    color:#16a34a; font-size:12px;
}
/* 被采纳用户卡片 —— 沿用活动贴 .event-signups 视觉 */
.bounty-accepted { margin-top:10px; padding-top:10px; border-top:1px dashed #eee; }
.ba-title { font-size:12px; color:#999; margin-bottom:8px; }
.ba-list { display:grid; grid-template-columns:repeat(auto-fill, minmax(180px, 1fr)); gap:8px; }
.ba-item { display:flex; align-items:center; gap:8px; padding:6px 8px; background:#fff; border:1px solid #f0f0f0; border-radius:6px; }
.ba-avatar { width:32px; height:32px; border-radius:50%; overflow:hidden; background:#16a34a; color:#fff; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.ba-avatar img { width:100%; height:100%; object-fit:cover; display:block; }
.ba-avatar.ba-no-img { background:#16a34a; }
.ba-initial { font-size:13px; font-weight:600; line-height:1; }
.ba-meta { min-width:0; flex:1; }
.ba-name { font-size:13px; color:#333; font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.ba-rank { font-size:11px; color:#16a34a; margin-left:4px; font-weight:500; }
.ba-time { font-size:11px; color:#16a34a; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
/* 评论区内的悬赏回答标识 */
.badge-bounty-answer {
    display:inline-block; padding:1px 7px; margin-left:6px; border-radius:8px;
    background:#fff7e6; color:#d48806; border:1px solid #ffe7ba; font-size:11px; font-weight:600; vertical-align:middle;
}
.badge-bounty-accepted {
    display:inline-block; padding:1px 7px; margin-left:4px; border-radius:8px;
    background:#f6ffed; color:#16a34a; border:1px solid #b7eb8f; font-size:11px; font-weight:600; vertical-align:middle;
}
.comment-item--bounty-answer {
    background:#fffaf2;        /* 暖色底，区分普通评论 */
    border-left:3px solid #ea6f5a;
    border-radius:6px;
    padding:10px 12px;
}
.comment-item--bounty-answer .comment-avatar {
    border:2px solid #fff; box-shadow:0 0 0 1px #ffd591;
}
.comment-item--bounty-accepted {
    background:#f6ffed !important;       /* 已采纳用绿色覆盖暖色 */
    border-left:3px solid #16a34a !important;
}
.bounty-accept-row { padding-left:48px; }
.bounty-accept-row .btn { background:#ea6f5a; border-color:#ea6f5a; }
.bounty-accept-row .btn:hover { background:#d45a45; border-color:#d45a45; }

/* 辩论主题：评论区内的站队发言标识 + 底色（参考悬赏主题的「回答」标识）*/
.badge-debate-side {
    display:inline-block; padding:1px 7px; margin-left:6px; border-radius:8px;
    font-size:11px; font-weight:600; vertical-align:middle; color:#fff;
}
.badge-debate-side.pro { background:#1e6fd9; }
.badge-debate-side.con { background:#e0852a; }
.comment-item--debate-pro {
    background:#f4f8fe;        /* 正方：淡蓝底 */
    border-left:3px solid #1e6fd9;
    border-radius:6px;
    padding:10px 12px;
}
.comment-item--debate-pro .comment-avatar {
    border:2px solid #fff; box-shadow:0 0 0 1px #cfe0fb;
}
.comment-item--debate-con {
    background:#fff7ec;        /* 反方：淡橙底 */
    border-left:3px solid #e0852a;
    border-radius:6px;
    padding:10px 12px;
}
.comment-item--debate-con .comment-avatar {
    border:2px solid #fff; box-shadow:0 0 0 1px #f6d9bf;
}

/* 投票 */
.poll-options { display:flex; flex-direction:column; gap:8px; margin-bottom:10px; }
.poll-opt { display:flex; align-items:center; gap:8px; padding:8px 12px; border:1px solid #eee; border-radius:6px; background:#fff; cursor:pointer; font-size:14px; }
.poll-opt:hover { border-color:#1e9e6a; }
.poll-results { display:flex; flex-direction:column; gap:8px; }
.poll-opt-result { font-size:13px; }
.poll-opt-result.my-vote .por-label { color:#1e9e6a; font-weight:600; }
.por-bar { height:8px; background:#eee; border-radius:4px; overflow:hidden; margin:4px 0 2px; }
.por-bar span { display:block; height:100%; background:#1e9e6a; }
.por-num { color:#999; font-size:12px; }
.por-mine { color:#1e9e6a; font-size:12px; margin-left:6px; }

/* 辩论 */
.debate-sides { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px; }
/* 移动端（2026-09-01）：手机端正反方观点改为上下排列（不要左右并列），避免挤成两条极窄列看不清 */
@media (max-width: 768px) {
  .debate-sides { grid-template-columns: 1fr; }
}
.debate-side { border-radius:6px; padding:10px 12px; }
.debate-side.pro { background:#eef4fd; border:1px solid #cfe0fb; }
.debate-side.con { background:#fff3ea; border:1px solid #f6d9bf; }
.ds-title { font-weight:700; margin-bottom:4px; }
.ds-title.pro { color:#1e6fd9; } .ds-title.con { color:#e0852a; }
.ds-text { font-size:13px; color:#444; line-height:1.6; }
.ds-votes { font-size:12px; color:#888; margin-top:6px; }
.debate-statements { display:flex; flex-direction:column; gap:8px; margin-bottom:8px; }
.debate-statement { border-radius:6px; padding:8px 10px; background:#fff; border-left:3px solid #ccc; }
.debate-statement.pro { border-left-color:#1e6fd9; }
.debate-statement.con { border-left-color:#e0852a; }
.ds-tag { font-size:11px; padding:1px 6px; border-radius:8px; color:#fff; margin-right:6px; }
.ds-tag.pro { background:#1e6fd9; } .ds-tag.con { background:#e0852a; }
.ds-user { font-size:12px; color:#666; margin-right:8px; }
.ds-content { font-size:14px; color:#444; }

/* 采访（2026-09-02 改造：参与者卡片 + 结束按钮 + 提问/回答入口） */
.iv-participants { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:12px; }
.iv-participant-card { display:flex; align-items:center; gap:10px; flex:1; min-width:200px; padding:10px 12px; background:#fff; border:1px solid #f0f0f0; border-radius:8px; }
/* 卡片边框：记者用品牌红，受访者用蓝色（与身份色一致） */
.iv-participant-card--reporter { border-color:#ea6f5a; box-shadow:0 0 0 1px rgba(234,111,90,.18) inset; }
.iv-participant-card--interviewee { border-color:#1e6fd9; box-shadow:0 0 0 1px rgba(30,111,217,.18) inset; }
.iv-avatar { width:44px; height:44px; border-radius:50%; background:#ea6f5a; color:#fff; display:flex; align-items:center; justify-content:center; font-size:16px; overflow:hidden; flex:none; text-decoration:none; }
.iv-avatar img { width:100%; height:100%; object-fit:cover; }
.iv-info { display:flex; flex-direction:column; gap:2px; min-width:0; }
.iv-role-badge { display:inline-block; font-size:11px; padding:1px 6px; border-radius:3px; color:#fff; background:#888; width:fit-content; line-height:1.4; }
/* 角色角标：记者品牌红、受访者蓝色（统一身份色） */
.iv-role-badge--reporter    { background:#ea6f5a; }
.iv-role-badge--interviewee { background:#1e6fd9; }
.iv-name { font-size:14px; font-weight:600; color:#222; text-decoration:none; }
/* 头衔：仅顶部参与者卡片显示（reporter_title / interviewee_title），
   问答区不再渲染头衔（避免重复装饰） */
.iv-title { font-size:12px; color:#888; line-height:1.4; word-break:break-all; margin-top:2px; }
.iv-status-row { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:10px; font-size:12px; color:#888; flex-wrap:wrap; }
.iv-ended-badge { background:#999; color:#fff; padding:1px 6px; border-radius:3px; font-size:11px; margin-left:4px; }
.iv-qa-list { margin-top:8px; }
.iv-qa-item { border:1px solid #f0f0f0; border-radius:8px; padding:10px 12px; background:#fff; margin-bottom:8px; }
.iv-q, .iv-a { display:flex; align-items:flex-start; gap:6px; line-height:1.6; }
.iv-q { margin-bottom:6px; }
.iv-q-text { flex:1; color:#222; font-size:16px; font-weight:600; line-height:1.55; }
/* 问题文字颜色跟随提问者身份（与问字牌一致：记者品牌红 / 受访者蓝 / 读者绿色） */
.iv-q-text--reporter    { color:#ea6f5a; }
.iv-q-text--interviewee { color:#1e6fd9; }
.iv-q-text--reader      { color:#16a34a; }
.iv-a-text { flex:1; color:#444; font-size:14px; line-height:1.7; }
.iv-q-role, .iv-a-role { display:inline-block; min-width:18px; height:18px; line-height:18px; text-align:center; border-radius:50%; color:#fff; font-size:11px; flex:none; font-weight:600; }
/* 提问者身份色：记者品牌红、受访者蓝色、读者绿色 */
.iv-q-role--reporter    { background:#ea6f5a; }
.iv-q-role--interviewee { background:#1e6fd9; }
.iv-q-role--reader      { background:#16a34a; }
.iv-a-role { background:#7c5cff; }
.iv-a-role--reporter    { background:#ea6f5a; }
.iv-a-role--interviewee { background:#1e6fd9; }
.iv-a-pending .iv-a-text { color:#bbb; font-style:italic; }
.iv-q-meta, .iv-a-meta { font-size:12px; color:#999; margin-left:6px; flex:none; }
.iv-q-meta a, .iv-a-meta a { color:#999; text-decoration:none; }
.iv-q-meta a:hover, .iv-a-meta a:hover { color:#ea6f5a; }
.iv-a { padding-left:24px; color:#444; }
.iv-answer-form { margin-top:8px; padding-left:24px; }
.iv-ask-row { display:flex; gap:8px; margin-top:10px; }
</style>
<div class="post-detail">
    <div class="post-title-row">
        <h1>
            <?php if ($post['is_pinned']): ?><span class="badge badge-pin">置顶</span><?php endif; ?>
            <?php if (!empty($post['self_pin_until']) && strtotime($post['self_pin_until']) > time()): ?><span class="badge badge-self-pin">自助置顶</span><?php endif; ?>
            <?php if ($post['is_essence']): ?><span class="badge badge-essence">精选</span><?php endif; ?>
            <?php if (!empty($post['is_closed'])): ?><span class="badge badge-closed">已关闭</span><?php endif; ?>
            <?= e($post['title']) ?>
        </h1>
    </div>
    <div class="post-info">
        <a href="<?= url('user/profile', ['id' => $post['author']['id']]) ?>" data-uid="<?= $post['author']['id'] ?>" style="display:flex;align-items:center;gap:8px;color:#777;">
            <span style="width:32px;height:32px;border-radius:50%;background:#ea6f5a;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:13px;overflow:hidden;">
                <?php if (!empty($post['author']['avatar'])): ?>
                <img src="<?= upload_url($post['author']['avatar']) ?>" style="width:100%;height:100%;object-fit:cover;" alt="">
                <?php else: ?>
                <?= e(mb_substr($post['author']['nickname'] ?: $post['author']['username'], 0, 1)) ?>
                <?php endif; ?>
            </span>
            <strong style="color:#555;"><?= e($post['author']['nickname'] ?: $post['author']['username']) ?></strong><?= cert_badge_html($post['author'], 16) ?><?= uid_badge($post['author']) ?><?= level_badge($post['author']) ?>
        </a>
        <?php if ($post['category']): ?>
        <a href="<?= url('home/index', ['cat' => $post['category']['id']]) ?>" class="cat-tag" style="<?= cat_color_style($post['category']['color']) ?>padding:2px 10px;border-radius:10px;font-size:12px;"><?= e($post['category']['name']) ?></a>
        <?php endif; ?>
        <span>#1</span>
        <span><?= time_ago($post['created_at']) ?></span>
        <span>浏览 <?= $post['view_count'] ?></span>
        <?php if ($post['updated_at'] && $post['updated_at'] != $post['created_at']): ?>
        <span>（已编辑）</span>
        <?php endif; ?>

        <!-- 正文字号滑杆：放在帖子信息（.post-info）行内最后一项，靠 CSS margin-left:auto 推到行末
             min=正文默认(15px)，max=标题大小(24px)，拖动即改 .post-content 字号，
             通过 localStorage 持久化，下次进入同一浏览器自动恢复 -->
        <div class="post-font-slider" title="拖动滑杆调节正文字号">
            <span class="post-font-slider-label">阅读设置</span>
            <input type="range" id="postContentFontSizeSlider" min="15" max="24" step="1" value="15" aria-label="正文字号">
            <span class="post-font-value" id="postContentFontSizeValue">15px</span>
        </div>
    </div>

    <?php
    // 付费主题预处理：必须在 HTML 输出前完成，
    // 否则下面 119/123/136 行用到的 $payRow/$contentUnlocked/$payCP 都是 NULL，
    // 导致 paywall-locked 类加不上、预览段不渲染、付费红框不出来 → "付费内容完全不遮挡"。
    $payRow = !empty($special['pay']) ? $special['pay'] : null;
    $payScope = $payRow['paid_scope'] ?? ['content'=>false,'attachment'=>false,'all'=>false];
    $payIsAuthor = !empty($payRow['is_author']);
    $contentUnlocked    = $payIsAuthor || !empty($payScope['content']);
    $attachmentUnlocked = $payIsAuthor || !empty($payScope['attachment']);
    $payCP = (int)($payRow['content_price'] ?? 0);
    $payAP = (int)($payRow['attachment_price'] ?? 0);
    $payTotal = $payCP + $payAP;
    // 预览段截断（PHP 端）：0 < ratio < 100 才截
    $rawContent = $post['content'] ?? '';
    $previewRatio = (int)($payRow['preview_ratio'] ?? 20);
    $previewContent = '';
    $contentTruncated = false;
    if ($payRow && !$contentUnlocked && $previewRatio > 0 && $previewRatio < 100) {
        $len = mb_strlen($rawContent);
        $cut = (int)floor($len * $previewRatio / 100);
        if ($cut > 0 && $cut < $len) {
            $seg = mb_substr($rawContent, 0, $cut);
            $lastOpen = mb_strrpos($seg, '![');
            if ($lastOpen !== false) {
                $tail = mb_substr($rawContent, $lastOpen, $cut - $lastOpen);
                if (mb_strpos($tail, '](') !== false && mb_strpos($tail, ')') === false) {
                    $close = mb_strpos($rawContent, ')', $lastOpen);
                    if ($close !== false) { $cut = $close + 1; }
                }
            }
            $previewContent = mb_substr($rawContent, 0, $cut);
            $contentTruncated = true;
        }
    }
    ?>

    <div class="post-content <?= ($payRow && !$contentUnlocked) ? 'paywall-locked' : '' ?>">
        <div class="post-content-inner">
        <?php
        // 付费主题：正文未解锁时仅渲染免费预览段；其余情况正常渲染
        if ($payRow && !$contentUnlocked) {
            if ($previewContent !== '') {
                echo parse_markdown($previewContent, $canViewHidden);
            } else {
                // preview_ratio=0 或全文太短 → 不展示任何正文
            }
        } else {
            echo parse_markdown($post['content'], $canViewHidden);
        }
        ?>
        </div>
    </div>

    <?php if ($payRow && !$contentUnlocked && $payCP > 0): ?>
    <div class="paywall-content">
        <div class="pw-title">本文为付费内容，以上为免费预览段</div>
        <div class="pw-amount">
            支付 <strong><?= $payCP ?></strong> <?= e($payRow['currency_label']) ?> 阅读全文
            · 已有 <strong><?= (int)($payRow['buyer_count'] ?? 0) ?></strong> 人购买
        </div>
        <?php if (is_logged_in() && !$payIsAuthor): ?>
        <button type="button" class="btn-unlock" onclick="payBuy(<?= (int)$post['id'] ?>, 'content')">立即解锁正文</button>
        <?php elseif (!is_logged_in()): ?>
        <a href="<?= login_url(url('post/show', ['id' => (int)$post['id']])) ?>" class="btn-unlock" style="text-decoration:none;display:inline-block;">登录后解锁</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($post['attachments_arr'])): ?>
    <div class="post-attachments" style="margin-top:16px;">
        <div style="font-size:13px;color:#666;margin-bottom:8px;">附件（<?= count($post['attachments_arr']) ?>）</div>
        <div style="display:flex;flex-direction:column;gap:6px;">
            <?php foreach ($post['attachments_arr'] as $att):
                $attName = $att['name'] ?? basename($att['url']);
                $attSize = '';
                if (!empty($att['size'])) {
                    $kb = $att['size'] / 1024;
                    $attSize = $kb >= 1024 ? round($kb / 1024, 1) . ' MB' : round($kb) . ' KB';
                }
                // 附件付费未解锁：附件不渲染文件名/大小，只显示「🔒 附件 N 已隐藏」汇总
                $hideAttList = $payRow && $payAP > 0 && !$attachmentUnlocked;
            ?>
            <?php if (!empty($att['reply_visible']) && !$canViewHidden): ?>
            <div class="attachment-item reply-hidden-media" style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:#f8f8f8;border:1px solid #eee;border-radius:8px;color:#999;font-size:14px;">
                <span>🔒</span>
                <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">（回复后可见）</span>
            </div>
            <?php elseif ($hideAttList): ?>
            <?php // 付费未解锁时不渲染具体附件（汇总到 paybox 里的「付费下载」按钮） ?>
            <?php else: ?>
            <div class="attachment-item" style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:#f8f8f8;border:1px solid #eee;border-radius:8px;color:#444;font-size:14px;cursor:default;">
                <span>📎</span>
                <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($attName) ?></span>
                <?php if ($attSize): ?><span style="color:#999;font-size:12px;"><?= $attSize ?></span><?php endif; ?>
                <button type="button" class="btn-download" onclick="downloadAttachment('<?= upload_url($att['url']) ?>')" style="border:none;background:#ea6f5a;color:#fff;border-radius:9px;padding:4px 12px;font-size:13px;cursor:pointer;white-space:nowrap;">下载</button>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($post['is_auction'])): ?>
    <div class="auction-box" style="margin-top:16px;border:1px solid #eee;border-radius:14px;padding:16px;background:#fafafa;box-shadow:var(--shadow);">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
            <span style="font-size:15px;font-weight:500;color:#2f2f2f;">拍卖信息</span>
            <?php $ended = !empty($post['end_time']) && strtotime($post['end_time']) <= time(); ?>
            <span style="font-size:12px;color:#fff;background:<?= $ended ? '#999' : '#ea6f5a' ?>;border-radius:10px;padding:2px 10px;"><?= $ended ? '已结束' : '进行中' ?></span>
            <?php if ($post['user_id'] == Auth::id()): ?><span style="font-size:12px;color:#999;">（我的拍卖）</span><?php endif; ?>
        </div>
        <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:14px;">
            <div style="background:#fff;border:1px solid #eee;border-radius:6px;padding:12px;">
                <div style="font-size:12px;color:#666;margin-bottom:4px;">起拍价</div>
                <div style="font-size:18px;font-weight:500;color:#2f2f2f;">¥<?= number_format((float)$post['start_price'], 2) ?></div>
            </div>
            <div style="background:#fff;border:1px solid #eee;border-radius:6px;padding:12px;">
                <div style="font-size:12px;color:#666;margin-bottom:4px;">当前最高</div>
                <div style="font-size:18px;font-weight:500;color:#ea6f5a;">¥<?= number_format((float)($post['current_price'] !== null ? $post['current_price'] : $post['start_price']), 2) ?></div>
            </div>
            <div style="background:#fff;border:1px solid #eee;border-radius:6px;padding:12px;">
                <div style="font-size:12px;color:#666;margin-bottom:4px;">出价人数</div>
                <div style="font-size:18px;font-weight:500;color:#2f2f2f;"><?= (int)$post['bid_count'] ?></div>
            </div>
        </div>

        <div style="font-size:13px;color:#666;margin-bottom:8px;">出价排行（共 <?= (int)$bidRankTotal ?> 人参与）</div>
        <div class="bid-ranking">
            <?php if (empty($bidRanking)): ?>
            <div class="bid-ranking-empty">暂无出价，快来抢拍吧～</div>
            <?php else: ?>
            <?php foreach ($bidRanking as $i => $b): ?>
            <?php $rank = ($bidPage - 1) * $bidPerPage + $i + 1; ?>
            <div class="bid-rank-item">
                <span class="bid-rank-no"><?= $rank ?></span>
                <span class="bid-rank-avatar"><?= e(mb_substr($b['nickname'] ?: '匿', 0, 1)) ?></span>
                <span class="bid-rank-name">
                    <?= e($b['nickname'] ?: '用户' . $b['user_id']) ?>
                    <?php if (!empty($b['is_certified'])): ?>
                    <span class="bid-rank-cert">✓认证</span>
                    <?php endif; ?>
                </span>
                <span class="bid-rank-price <?= $rank == 1 ? 'is-lead' : '' ?>">¥<?= number_format((float)$b['price'], 2) ?></span>
                <span class="bid-rank-time"><?= time_ago($b['created_at']) ?></span>
                <?php if ($rank == 1): ?><span class="bid-rank-lead-tag">领先</span><?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php if ($bidRankTotal > $bidPerPage): ?>
        <div class="pagination">
            <?php if ($bidPage > 1): ?>
            <a href="<?= url('post/show', ['id' => $post['id'], 'page' => $page, 'bid_page' => $bidPage - 1]) ?>" class="page-btn">上一页</a>
            <?php endif; ?>
            <?php
            $totalPages = (int)ceil($bidRankTotal / $bidPerPage);
            $start = max(1, $bidPage - 2);
            $end = min($totalPages, $bidPage + 2);
            if ($start > 1) {
                echo '<a href="' . url('post/show', ['id' => $post['id'], 'page' => $page, 'bid_page' => 1]) . '" class="page-btn">1</a>';
                if ($start > 2) echo '<span class="page-ellipsis">...</span>';
            }
            for ($p = $start; $p <= $end; $p++) {
                $cls = $p == $bidPage ? 'page-btn active' : 'page-btn';
                echo '<a href="' . url('post/show', ['id' => $post['id'], 'page' => $page, 'bid_page' => $p]) . '" class="' . $cls . '">' . $p . '</a>';
            }
            if ($end < $totalPages) {
                if ($end < $totalPages - 1) echo '<span class="page-ellipsis">...</span>';
                echo '<a href="' . url('post/show', ['id' => $post['id'], 'page' => $page, 'bid_page' => $totalPages]) . '" class="page-btn">' . $totalPages . '</a>';
            }
            if ($bidPage < $totalPages): ?>
            <a href="<?= url('post/show', ['id' => $post['id'], 'page' => $page, 'bid_page' => $bidPage + 1]) ?>" class="page-btn">下一页</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($ended): ?>
        <div style="font-size:13px;color:#ea6f5a;font-weight:500;padding:8px 0;">拍卖已结束<?= !empty($bidRanking) ? '，最终由 ' . e($bidRanking[0]['nickname'] ?: '用户') . ' 以 ¥' . number_format((float)$bidRanking[0]['price'], 2) . ' 成交' : '' ?>。</div>
        <?php elseif (is_logged_in()): ?>
        <div style="display:flex;gap:8px;align-items:center;">
            <input type="number" id="bidInput" placeholder="不低于 ¥<?= number_format((float)($post['current_price'] !== null ? $post['current_price'] : $post['start_price']) + (float)($post['step_price'] ?: 10), 2) ?>" style="flex:1;padding:8px 12px;border:1px solid #ddd;border-radius:9px;font-size:13px;">
            <button class="btn btn-primary" id="bidBtn" onclick="submitBid(<?= $post['id'] ?>)">出价</button>
        </div>
        <div style="font-size:12px;color:#999;margin-top:8px;" id="bidCountdown" data-end="<?= strtotime($post['end_time']) ?>">
            距结拍 <span id="bidCountdownTime"><?= date('Y-m-d H:i', strtotime($post['end_time'])) ?></span><?= !empty($post['extended_minutes']) ? '  <span style="color:#ea6f5a;font-weight:500;">+' . (int)$post['extended_minutes'] . 'min</span>' : '' ?> · 每次加价不低于 ¥<?= number_format((float)($post['step_price'] ?: 10), 2) ?>
        </div>
        <?php else: ?>
        <a href="<?= login_url() ?>" class="btn btn-primary btn-sm">登录后出价</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
    // ===================== 特殊主题渲染 =====================
    $sp = $special ?? ['type' => 'normal'];
    ?>

    <?php if (!empty($sp['pay'])): $pay = $sp['pay']; ?>
    <div class="special-box pay-box">
        <div class="sb-head"><span class="sb-badge pay">付费内容</span> 本帖含付费内容，可单独解锁或一次性解锁全部</div>

        <?php
        // 全部已解锁或作者本人 → 简洁展示
        $allUnlocked = $payIsAuthor || (!empty($payScope['content']) && !empty($payScope['attachment']));
        $payAttList = $pay['paid_attachments'] ?? [];
        ?>

        <?php if ($allUnlocked): ?>
            <div class="sb-note ok">✓ 已解锁，可查看全部付费内容（共 <?= count($payAttList) ?> 个附件）</div>
        <?php else: ?>
            <div class="sb-status">币种：<strong><?= e($pay['currency_label']) ?></strong> · 已购 <?= (int)($pay['buyer_count'] ?? 0) ?> 人</div>

            <?php if (!empty($payAttList)): ?>
            <div class="pay-locked-list" style="margin-top:10px;">
                <?php foreach ($payAttList as $a):
                    $aName = $a['name'] ?? basename($a['url']);
                    $aSize = '';
                    if (!empty($a['size'])) {
                        $kb = $a['size'] / 1024;
                        $aSize = $kb >= 1024 ? round($kb / 1024, 1) . ' MB' : round($kb) . ' KB';
                    }
                ?>
                <div class="pay-locked-item" style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:#fff;border:1px solid #eee;border-radius:6px;margin-bottom:6px;">
                    <span style="color:#999;">📎</span>
                    <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($aName) ?><?= $aSize ? ' · ' . $aSize : '' ?></span>
                    <?php if ($attachmentUnlocked): ?>
                        <button type="button" class="pay-item-btn" style="border:none;background:#ea6f5a;color:#fff;border-radius:9px;padding:4px 14px;font-size:13px;cursor:pointer;white-space:nowrap;" onclick="downloadAttachment('<?= e($a['url']) ?>')">下载</button>
                    <?php elseif ($payAP > 0): ?>
                        <button type="button" class="pay-item-btn" style="border:none;background:#fff;color:#ea6f5a;border:1px solid #ea6f5a;border-radius:9px;padding:4px 14px;font-size:13px;cursor:pointer;white-space:nowrap;" onclick="payBuy(<?= (int)$post['id'] ?>, 'attachment')">付费下载</button>
                    <?php else: ?>
                        <span style="color:#999;font-size:12px;">免费</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php
            // 底部联合解锁按钮：仅在 (任一未解锁) && (总价>0) 时显示
            $showAll = false; $parts = [];
            if ($payCP > 0 && !$contentUnlocked) { $showAll = true; $parts[] = '全文'; }
            if ($payAP > 0 && !$attachmentUnlocked && !empty($payAttList)) { $showAll = true; $parts[] = '附件'; }
            ?>
            <?php if ($showAll && is_logged_in() && !$payIsAuthor): ?>
            <div style="margin-top:14px;">
                <button type="button" class="btn btn-primary" style="background:#ea6f5a;border-color:#ea6f5a;width:100%;padding:10px;font-size:15px;" onclick="payBuy(<?= (int)$post['id'] ?>, 'all')">支付 <strong style="font-size:18px;"><?= $payTotal ?></strong> <?= e($pay['currency_label']) ?> 解锁 <?= implode(' + ', $parts) ?></button>
            </div>
            <?php elseif ($showAll && !is_logged_in()): ?>
            <div style="margin-top:14px;">
                <a href="<?= login_url() ?>" class="btn btn-primary" style="background:#ea6f5a;border-color:#ea6f5a;width:100%;padding:10px;font-size:15px;">登录后支付 <?= $payTotal ?> <?= e($pay['currency_label']) ?> 解锁 <?= implode(' + ', $parts) ?></a>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($sp['event'])): $ev = $sp['event']; ?>
    <div class="special-box event-box">
        <div class="sb-head"><span class="sb-badge event">活动</span> <?= !empty($ev['subject']) ? e($ev['subject']) : '活动报名' ?></div>
        <div class="event-meta">
            <div><?= ($ev['mode'] ?? 'offline') === 'online' ? '💻 线上活动' : '🏠 线下活动' ?></div>
            <?php if (!empty($ev['event_time'])): ?><div>⏰ <?= e($ev['event_time']) ?></div><?php endif; ?>
            <?php if (!empty($ev['location'])): ?><div>📍 <?= e($ev['location']) ?></div><?php endif; ?>
            <div><?= (float)($ev['fee'] ?? 0) > 0 ? '💰 活动费用 ¥ ' . number_format((float)$ev['fee'], 2) : '💰 活动免费' ?></div>
            <div>👥 已报名 <?= (int)$ev['signups'] ?><?= $ev['capacity'] > 0 ? ' / ' . (int)$ev['capacity'] : '' ?></div>
        </div>
        <?php if (!empty($ev['can_export'])): ?>
            <a class="btn btn-ghost btn-sm" href="<?= url('post/eventExport', ['id' => $post['id']]) ?>">导出报名数据（CSV）</a>
        <?php endif; ?>
        <?php if ($ev['signed_up']): ?>
            <div class="sb-note ok">✓ 您已报名</div>
        <?php elseif (is_logged_in() && $ev['status'] == 1): ?>
            <button class="btn btn-primary btn-sm" onclick="openEventSignup()">我要报名</button>
        <?php elseif (is_logged_in() && $ev['status'] != 1): ?>
            <div class="sb-note">报名已结束</div>
        <?php else: ?>
            <a class="btn btn-primary btn-sm" href="<?= login_url() ?>">登录后报名</a>
        <?php endif; ?>
        <?php if (!empty($ev['signups_list'])): ?>
        <div class="event-signups">
            <div class="es-title">已报名（<?= (int)$ev['signups'] ?>）<?= (int)$ev['signups'] > count($ev['signups_list']) ? ' · 显示最近 ' . count($ev['signups_list']) . ' 人' : '' ?></div>
            <div class="es-list">
                <?php foreach ($ev['signups_list'] as $su): ?>
                <div class="es-item" title="<?= e($su['nickname'] ?: $su['username']) ?> · <?= e($su['signup_at']) ?>">
                    <div class="es-avatar"><?php if (!empty($su['avatar'])): ?><img src="<?= e($su['avatar']) ?>" alt="" onerror="this.style.display='none';this.parentNode.classList.add('es-no-img');"><?php else: ?><span class="es-initial"><?= e(mb_substr($su['nickname'] ?: $su['username'] ?: '?', 0, 1, 'UTF-8')) ?></span><?php endif; ?></div>
                    <div class="es-meta">
                        <div class="es-name"><?= e($su['nickname'] ?: $su['username']) ?></div>
                        <div class="es-time">已报名 · <?= e($su['signup_at']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($ev['custom_fields'])): ?>
        <div id="eventSignupForm" style="display:none;margin-top:12px;border-top:1px dashed #eee;padding-top:12px;">
            <?php foreach ($ev['custom_fields'] as $cf): $fname = 'cf_' . md5($cf['label']); ?>
            <div class="form-group">
                <label class="form-label"><?= e($cf['label']) ?><?= !empty($cf['required']) ? ' <span class="required">*</span>' : '' ?></label>
                <?php if ($cf['type'] === 'textarea'): ?>
                    <textarea name="<?= $fname ?>" class="form-control"></textarea>
                <?php elseif ($cf['type'] === 'select'): ?>
                    <?php $cfOpts = (isset($cf['options']) && is_array($cf['options'])) ? $cf['options'] : []; ?>
                    <select name="<?= $fname ?>" class="form-control">
                        <option value="">请选择</option>
                        <?php if (empty($cfOpts)): ?>
                            <?php /* 存量老帖兼容：发布时未配置选项，显式提示，避免作者以为是渲染 bug */ ?>
                            <option value="" disabled>（该下拉未配置选项）</option>
                        <?php else: ?>
                            <?php foreach ($cfOpts as $cfOpt): $cfOpt = (string)$cfOpt; ?>
                                <option value="<?= e($cfOpt) ?>"><?= e($cfOpt) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                <?php elseif ($cf['type'] === 'number'): ?>
                    <input type="number" name="<?= $fname ?>" class="form-control">
                <?php elseif ($cf['type'] === 'date'): ?>
                    <input type="date" name="<?= $fname ?>" class="form-control">
                <?php else: ?>
                    <input type="text" name="<?= $fname ?>" class="form-control">
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <button class="btn btn-primary btn-sm" onclick="eventSignup(<?= $post['id'] ?>)">提交报名</button>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($sp['bounty'])): $b = $sp['bounty'];
        // 当前用户是否已提交过悬赏回答（已存在任意 is_bounty_answer=1 且 user_id=myId 的顶层评论）
        $myBountyAnswer = false;
        if (is_logged_in() && !empty($comments)) {
            foreach ($comments as $_ansCheck) {
                if ((int)($_ansCheck['user_id'] ?? 0) === (int)Auth::id() && (int)($_ansCheck['is_bounty_answer'] ?? 0) === 1) {
                    $myBountyAnswer = true; break;
                }
            }
        }
    ?>
    <div class="special-box bounty-box">
        <div class="sb-head">
            <span class="sb-badge bounty">悬赏</span>
            每人 <?= (int)$b['per_person_tokens'] ?> <?= e($b['currency_label']) ?> · 悬赏 <?= (int)$b['people_count'] ?> 人 · 已采纳 <?= (int)$b['accepted_count'] ?>
            <?php
            // 悬赏期 status=1 时，在面板头部右侧塞一个入口按钮（与活动贴「我要报名」风格一致）
            $bStatus = (int)$b['status'];
            if ($bStatus === 1): ?>
                <span style="flex:1;"></span>
                <?php if (!is_logged_in()): ?>
                    <a class="btn btn-primary btn-sm" href="<?= login_url() ?>">登录后回答</a>
                <?php elseif ($b['is_author']): ?>
                    <span class="sb-note ok">等待回答者提交回答</span>
                <?php elseif ($myBountyAnswer): ?>
                    <span class="sb-note ok">✓ 您已回答</span>
                <?php else: ?>
                    <button class="btn btn-primary btn-sm" onclick="openBountyAnswer()">我来回答</button>
                <?php endif; ?>
            <?php elseif ($bStatus === 2): ?>
                <span style="flex:1;"></span>
                <span class="sb-note ok">已解决（悬赏结束）</span>
            <?php else: ?>
                <span style="flex:1;"></span>
                <span class="sb-note">已结束（楼主提前结束）</span>
            <?php endif; ?>
        </div>
        <div class="sb-status">
            <?php if ($bStatus === 1): ?>进行中（进行中仅作者与回答者可见；已有采纳的回答全员可见）
            <?php elseif ($bStatus === 2): ?>已解决（已公开，原生评论与楼中楼开放）
            <?php else: ?>已结束（提前关闭）
            <?php endif; ?>
        </div>

        <?php /* 回答列表已下放到评论区（带「回答」标识 + 底色），本面板不再单独渲染，避免双显示 */ ?>

        <?php /* 被采纳用户卡片（活动贴 .event-signups 风格） */ ?>
        <?php if (!empty($b['accepted_list'])): ?>
        <div class="bounty-accepted">
            <div class="ba-title">已被采纳（<?= count($b['accepted_list']) ?> / <?= (int)$b['people_count'] ?: '∞' ?>）</div>
            <div class="ba-list">
                <?php foreach ($b['accepted_list'] as $acc):
                    $accName = trim((string)($acc['nickname'] ?? '')) ?: trim((string)($acc['username'] ?? '')) ?: '已注销用户';
                ?>
                <div class="ba-item" title="<?= e($accName) ?> · 获得 <?= (int)$acc['tokens'] ?> <?= e($b['currency_label']) ?> · <?= e($acc['accepted_at']) ?>">
                    <div class="ba-avatar">
                        <?php if (!empty($acc['avatar_url'])): ?>
                            <img src="<?= e($acc['avatar_url']) ?>" alt="" onerror="this.style.display='none';this.parentNode.classList.add('ba-no-img');">
                        <?php else: ?>
                            <span class="ba-initial"><?= e(mb_substr($accName, 0, 1, 'UTF-8')) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="ba-meta">
                        <div class="ba-name">
                            <?= e($accName) ?>
                            <span class="ba-rank">#<?= (int)$acc['comment_id'] ?> · +<?= (int)$acc['tokens'] ?></span>
                        </div>
                        <div class="ba-time">✓ 已采纳 · <?= e($acc['accepted_at']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($b['is_author'] && $b['status'] == 1 && $b['people_count'] > 0 && $b['accepted_count'] < $b['people_count']): ?>
            <button class="btn btn-ghost btn-sm" style="margin-top:6px;" onclick="bountyEndEarly(<?= $post['id'] ?>)">提前结束悬赏</button>
            <div class="sb-note">采纳满 <?= (int)$b['people_count'] ?> 人后自动公开；不足可提前结束（剩余质押扣 50% 返还）</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($sp['poll'])): $p = $sp['poll']; ?>
    <div class="special-box poll-box">
        <div class="sb-head"><span class="sb-badge poll">投票</span> <?= $p['multi'] ? '多选' : '单选' ?><?= !empty($p['deadline']) ? ' · 截止 ' . e($p['deadline']) : '' ?></div>
        <?php if ($p['voted'] || $p['closed'] || !is_logged_in()): ?>
            <div class="poll-results">
                <?php foreach ($p['options'] as $o): ?>
                <?php $pct = $p['total_votes'] > 0 ? round($o['votes'] / $p['total_votes'] * 100) : 0; ?>
                <div class="poll-opt-result <?= in_array((int)$o['id'], $p['my_option_ids'], true) ? 'my-vote' : '' ?>">
                    <div class="por-label"><?= e($o['text']) ?><?= in_array((int)$o['id'], $p['my_option_ids'], true) ? '<span class="por-mine">✓ 我的</span>' : '' ?></div>
                    <div class="por-bar"><span style="width:<?= $pct ?>%"></span></div>
                    <div class="por-num"><?= (int)$o['votes'] ?> 票 · <?= $pct ?>%</div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="sb-note"><?= (int)$p['total_votes'] ?> 人参与<?= $p['closed'] ? ' · 已截止' : '' ?><?= $p['voted'] ? ' · 您已投票' : '' ?></div>
        <?php else: ?>
            <div class="poll-options">
                <?php foreach ($p['options'] as $o): ?>
                <label class="poll-opt"><input type="<?= $p['multi'] ? 'checkbox' : 'radio' ?>" name="poll_vote" value="<?= (int)$o['id'] ?>"> <?= e($o['text']) ?></label>
                <?php endforeach; ?>
            </div>
            <button class="btn btn-primary btn-sm" onclick="pollVote(<?= $post['id'] ?>)">投票</button>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($sp['debate'])): $d = $sp['debate']; ?>
    <div class="special-box debate-box">
        <div class="sb-head">
            <span class="sb-badge debate">辩论</span>
            正反方站队
            <?php
            $dClosed = !empty($d['closed']);
            if ($dClosed): ?>
                <span style="flex:1;"></span>
                <span class="sb-note" style="color:#d48806;">已截止（<?= e(date('Y-m-d H:i', strtotime($d['deadline']))) ?>）</span>
            <?php elseif (!empty($d['deadline'])): ?>
                <span style="flex:1;"></span>
                <span class="countdown-chip" data-ts="<?= (int)strtotime($d['deadline']) ?>">--</span>
            <?php endif; ?>
        </div>
        <div class="debate-sides">
            <div class="debate-side pro"><div class="ds-title pro">正方</div><div class="ds-text"><?= e($d['pro_text']) ?></div><div class="ds-votes">👍 <?= (int)$d['pro_votes'] ?></div></div>
            <div class="debate-side con"><div class="ds-title con">反方</div><div class="ds-text"><?= e($d['con_text']) ?></div><div class="ds-votes">👎 <?= (int)($d['con_votes'] ?? 0) ?></div></div>
        </div>
        <?php
        // 提示当前用户的站队状态 + 引导到评论区。
        //   - 未站队 + 已登录 -> 在评论区看到「我要站队发言」按钮（含正反方选择）
        //   - 已站队 + 未截止 -> 提示已站正/反方，引导在评论区继续发言
        //   - 已站队 + 已截止 -> 提示已站 + 已截止
        //   - 未登录 -> 引导登录
        ?>
        <div class="sb-note" style="margin-top:4px;">
            <?php if (!is_logged_in()): ?>
                请 <a href="<?= login_url() ?>">登录</a> 后到下方评论区发表站队发言。
            <?php elseif (!empty($d['my_side'])): ?>
                您已站队（<?= (int)$d['my_side'] === 1 ? '正方' : '反方' ?>，已发言 <?= (int)($d['my_statement_count'] ?? 0) ?> 次）。<?= $dClosed ? '辩论已截止，不能再发言。' : '如需补充论据请到下方评论区继续发言（不可切换站队）。' ?>
            <?php elseif ($dClosed): ?>
                辩论已截止，站队发言入口关闭。
            <?php else: ?>
                请到下方评论区选「正方 / 反方」并发表你的站队发言。
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($sp['interview'])): $iv = $sp['interview']; ?>
    <div class="special-box interview-box">
        <div class="sb-head"><span class="sb-badge interview">采访</span> <?= e($iv['interview_topic'] ?? $post['title']) ?></div>
        <!-- 参与者卡片：记者 + 受访者（头像+角色+名+头衔），复用活动贴 .es-item 中奖名单风格 -->
        <div class="iv-participants">
            <?php if (!empty($iv['reporter_card'])): $rep = $iv['reporter_card']; ?>
            <div class="iv-participant-card iv-participant-card--reporter">
                <a href="<?= e($rep['profile']) ?>" class="iv-avatar">
                    <?php if (!empty($rep['avatar'])): ?><img src="<?= e($rep['avatar']) ?>" alt=""><?php else: ?><span><?= e(mb_substr($rep['name'], 0, 1, 'UTF-8')) ?></span><?php endif; ?>
                </a>
                <div class="iv-info">
                    <span class="iv-role-badge iv-role-badge--reporter">记者</span>
                    <a href="<?= e($rep['profile']) ?>" class="iv-name"><?= e($rep['name']) ?></a>
                    <?php if (!empty($rep['title'])): ?><span class="iv-title"><?= e($rep['title']) ?></span><?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($iv['interviewee_card'])): $intv = $iv['interviewee_card']; ?>
            <div class="iv-participant-card iv-participant-card--interviewee">
                <a href="<?= e($intv['profile'] ?: '#') ?>" class="iv-avatar">
                    <?php if (!empty($intv['avatar'])): ?><img src="<?= e($intv['avatar']) ?>" alt=""><?php else: ?><span><?= e(mb_substr($intv['name'], 0, 1, 'UTF-8')) ?></span><?php endif; ?>
                </a>
                <div class="iv-info">
                    <span class="iv-role-badge iv-role-badge--interviewee">受访者</span>
                    <?php if (!empty($intv['profile'])): ?><a href="<?= e($intv['profile']) ?>" class="iv-name"><?= e($intv['name']) ?></a><?php else: ?><span class="iv-name"><?= e($intv['name']) ?></span><?php endif; ?>
                    <?php if (!empty($intv['title'])): ?><span class="iv-title"><?= e($intv['title']) ?></span><?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <!-- 状态行：开放读者提问 / 结束状态 + 作者/记者的结束采访按钮（红底白字） -->
        <div class="iv-status-row">
            <span><?= !empty($iv['open_question']) ? '已开放读者提问' : '仅记者提问' ?><?php if (!empty($iv['ended'])): ?><span class="iv-ended-badge">采访已结束</span><?php endif; ?></span>
            <?php if (empty($iv['ended']) && (!empty($iv['is_author']) || !empty($iv['is_reporter']))): ?>
            <button class="btn btn-primary btn-sm" onclick="interviewEnd(<?= (int)$post['id'] ?>)">结束采访</button>
            <?php endif; ?>
        </div>
        <!-- 问答列表 -->
        <?php if (!empty($iv['qa'])): ?>
        <div class="iv-qa-list">
            <?php foreach ($iv['qa'] as $qa): ?>
            <?php
                // 提问者身份 → CSS 修饰类映射（统一一处定义，便于回溯）
                $_qaRole  = (string)($qa['asker_card']['role'] ?? '');
                $_qaClass = $_qaRole === '记者' ? 'reporter' : ($_qaRole === '受访者' ? 'interviewee' : 'reader');
            ?>
            <div class="iv-qa-item" id="qa-<?= (int)$qa['id'] ?>">
                <div class="iv-q">
                    <span class="iv-q-role iv-q-role--<?= $_qaClass ?>">问</span>
                    <span class="iv-q-text iv-q-text--<?= $_qaClass ?>"><?= e($qa['question']) ?></span>
                    <span class="iv-q-meta">
                        <?php if (!empty($qa['asker_card']['profile'])): ?><a href="<?= e($qa['asker_card']['profile']) ?>"><?= e($qa['asker_card']['name']) ?></a><?php else: ?><?= e($qa['asker_card']['name']) ?><?php endif; ?>
                        · <?= e($qa['asker_card']['role']) ?>
                        · <?= time_ago($qa['created_at']) ?>
                    </span>
                </div>
                <?php if (!empty($qa['is_answered'])): ?>
                <div class="iv-a">
                    <span class="iv-a-role <?= ($qa['answerer_card']['role'] ?? '') === '记者' ? 'iv-a-role--reporter' : (($qa['answerer_card']['role'] ?? '') === '受访者' ? 'iv-a-role--interviewee' : '') ?>">答</span>
                    <span class="iv-a-text"><?= nl2br(render_emoji(e($qa['answer']))) ?></span>
                    <span class="iv-a-meta">
                        <?= e($qa['answerer_card']['name'] ?? '受访者') ?>
                        · <?= e($qa['answerer_card']['role'] ?? '受访者') ?>
                        · <?= time_ago(!empty($qa['updated_at']) ? $qa['updated_at'] : $qa['created_at']) ?>
                    </span>
                </div>
                <?php else: ?>
                <div class="iv-a iv-a-pending">
                    <span class="iv-a-role">答</span>
                    <span class="iv-a-text">（待回答）</span>
                    <?php if (!empty($qa['can_answer'])): ?>
                    <button class="btn btn-primary btn-sm" style="margin-left:8px;" onclick="showAnswerForm(<?= (int)$qa['id'] ?>)">回答</button>
                    <?php endif; ?>
                </div>
                <div class="iv-answer-form" id="answerForm-<?= (int)$qa['id'] ?>" style="display:none;">
                    <textarea class="form-control" id="answerInput-<?= (int)$qa['id'] ?>" placeholder="回答内容…" style="min-height:80px;"></textarea>
                    <div style="text-align:right;margin-top:6px;">
                        <button class="btn btn-ghost btn-sm" onclick="hideAnswerForm(<?= (int)$qa['id'] ?>)">取消</button>
                        <button class="btn btn-primary btn-sm" onclick="submitAnswer(<?= (int)$qa['id'] ?>, <?= (int)$post['id'] ?>)">提交回答</button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="text-align:center;padding:14px 0;color:#bbb;font-size:13px;">暂无提问</div>
        <?php endif; ?>
        <!-- 翻页：每页 15 条；超 1 页才显示 -->
        <?php if (!empty($iv['qa']) && (int)($iv['qa_total_pages'] ?? 1) > 1):
            $cur  = (int)($iv['qa_page'] ?? 1);
            $tot  = (int)($iv['qa_total_pages'] ?? 1);
            $totalNum = (int)($iv['qa_total'] ?? 0);
            $base = url('post/show', ['id' => $post['id']]);
        ?>
        <div class="iv-qa-pager" style="display:flex;align-items:center;justify-content:center;gap:6px;margin:12px 0 18px;flex-wrap:wrap;">
            <?php
            // 简易分页：首页 / 上一页 / 当前页前后各 2 页 / 末页
            $pages = [1];
            for ($p = max(2, $cur - 2); $p <= min($tot - 1, $cur + 2); $p++) $pages[] = $p;
            if ($tot > 1) $pages[] = $tot;
            $pages = array_unique($pages);
            $prev = max(1, $cur - 1);
            $next = min($tot, $cur + 1);
            ?>
            <?php if ($cur > 1): ?>
                <a class="iv-pg-btn" href="<?= e($base) . '?ivpage=' . $prev ?>" style="padding:4px 10px;border:1px solid #ddd;border-radius:8px;color:#555;text-decoration:none;font-size:13px;">上一页</a>
            <?php endif; ?>
            <?php
            $prevNum = 0;
            foreach ($pages as $p):
                if ($prevNum && $p - $prevNum > 1): ?>
                    <span style="color:#bbb;font-size:13px;">…</span>
                <?php endif; ?>
                <?php if ($p === $cur): ?>
                    <span style="padding:4px 10px;border-radius:8px;background:#ea6f5a;color:#fff;font-size:13px;font-weight:600;"><?= $p ?></span>
                <?php else: ?>
                    <a class="iv-pg-btn" href="<?= e($base) . '?ivpage=' . $p ?>" style="padding:4px 10px;border:1px solid #ddd;border-radius:8px;color:#555;text-decoration:none;font-size:13px;"><?= $p ?></a>
                <?php endif; ?>
                <?php $prevNum = $p;
            endforeach; ?>
            <?php if ($cur < $tot): ?>
                <a class="iv-pg-btn" href="<?= e($base) . '?ivpage=' . $next ?>" style="padding:4px 10px;border:1px solid #ddd;border-radius:8px;color:#555;text-decoration:none;font-size:13px;">下一页</a>
            <?php endif; ?>
            <span style="color:#999;font-size:12px;margin-left:6px;">共 <?= $totalNum ?> 条 · <?= $tot ?> 页</span>
        </div>
        <?php endif; ?>
        <!-- 提问入口：未结束 + 登录 +（记者 始终 / 开放读者提问 / 受访者且开启"允许受访者提问"）才显示 -->
        <?php if (empty($iv['ended']) && is_logged_in() && (!empty($iv['is_reporter']) || !empty($iv['open_question']) || (!empty($iv['is_interviewee']) && !empty($iv['allow_interviewee_ask'])))): ?>
        <div class="iv-ask-row">
            <input id="interviewQuestion" class="form-control" placeholder="<?php
                    // 统一格式『以{身份}身份提问…』，身份取自当前用户在本采访中的判定结果
                    // （与入口条件严格镜像：is_reporter > (is_interviewee && allow_interviewee_ask) > 读者）
                    if (!empty($iv['is_reporter'])) {
                        echo '以记者身份提问…';
                    } elseif (!empty($iv['is_interviewee']) && !empty($iv['allow_interviewee_ask'])) {
                        echo '以受访者身份提问…';
                    } else {
                        echo '以读者身份提问…';
                    }
                ?>" style="flex:1;" maxlength="500">
            <button class="btn btn-primary btn-sm" onclick="interviewAsk(<?= (int)$post['id'] ?>)">提交提问</button>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($sp['lottery'])): $lt = $sp['lottery'];
        // 2026-09-01 抽奖积分奖品跟随后台配置：币种名 / 图标都走 PointService 实时读取，
        // 后台改「token」名/图标后这里立即联动，不再写死「积分 / 💎」。
        $lotteryCurrencyCode = $lt['stake_currency'] ?? 'token';
        $lotteryCurrencyName = PointService::currencyLabel($lotteryCurrencyCode);
        $lotteryCurrencyIcon = PointService::currencyIconHtml($lotteryCurrencyCode);
        // 实物/虚拟奖品沿用原 emoji（这些不是积分币种）
        $prizeEmoji = ['physical' => '📦', 'virtual' => '🎟️', 'point' => $lotteryCurrencyIcon][$lt['prize_type']] ?? '🎁';
        $prizeTypeLabel = ['physical' => '实物', 'virtual' => '虚拟', 'point' => $lotteryCurrencyName][$lt['prize_type']] ?? '实物';
        $ruleLabels = ['follow' => '关注我', 'reply' => '回复15字以上', 'like' => '点赞主题', 'favorite' => '收藏主题'];
        $ruleClass = ['must' => 'lr-must', 'optional' => 'lr-optional', 'no' => 'lr-no'];
    ?>
    <div class="special-box lottery-box">
        <div class="sb-head">
            <span class="sb-badge lottery">抽奖</span> 抽奖活动
            <?php if ((int)$lt['status'] === 0): ?>
                <span style="flex:1;"></span>
                <span class="countdown-chip" data-ts="<?= (int)strtotime($lt['draw_at']) ?>" data-kind="lottery" title="开奖时间">--</span>
            <?php endif; ?>
        </div>

        <div class="lottery-prize-box">
            <div class="lp-emoji"><?= $prizeEmoji ?></div>
            <div class="lp-info">
                <div class="lp-name"><strong><?= e($lt['prize_name']) ?></strong><?php if ((int)$lt['winner_count'] > 0): ?> · 共 <?= (int)$lt['winner_count'] ?> 名<?php endif; ?></div>
                <div class="lp-type"><?= e($prizeTypeLabel) ?><?php if ($lt['prize_type'] === 'point'): ?><?php if (($lt['point_mode'] ?? 'custom') === 'random'): ?> · 共质押 <?= (int)$lt['stake_points'] ?> <?= e($lotteryCurrencyName) ?>（开奖随机分配）<?php else: ?> · 每份 <?= (int)$lt['point_unit'] ?> <?= e($lotteryCurrencyName) ?> · 共质押 <?= (int)$lt['stake_points'] ?> <?= e($lotteryCurrencyName) ?><?php endif; ?><?php elseif ($lt['prize_value'] !== ''): ?> · 价值 <?= e($lt['prize_value']) ?><?php endif; ?></div>
                <div class="lp-meta">🏆 名额 <?= (int)$lt['winner_count'] ?> 人 · 👥 已参与 <?= (int)$lt['joined_count'] ?> 人<?php if ((int)$lt['join_cost'] > 0): ?> · 💰 参与消耗 <?= (int)$lt['join_cost'] ?> <?= e($lotteryCurrencyName) ?><?php endif; ?></div>
            </div>
        </div>

        <?php if (!empty($lt['rules'])): ?>
        <div class="lottery-rules">
            <?php foreach (['follow', 'reply', 'like', 'favorite'] as $rk):
                $rv = $lt['rules'][$rk] ?? 'no';
            ?>
            <span class="lr-chip <?= $ruleClass[$rv] ?>"><?= e($ruleLabels[$rk]) ?> · <?= $rv === 'must' ? '必须' : ($rv === 'optional' ? '鼓励' : '不限') ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ((int)$lt['status'] === 0): ?>
            <div class="sb-status">活动进行中，到开奖时间由系统自动开奖；符合上方规则即自动获得抽奖资格。</div>
        <?php else: ?>
            <div class="sb-status lottery-ended">已开奖</div>
            <?php if (!empty($lt['winners_detail'])): ?>
                <div class="bounty-accepted">
                    <div class="ba-title">已开奖（<?= count($lt['winners_detail']) ?> / <?= (int)$lt['winner_count'] ?: '∞' ?>）</div>
                    <div class="ba-list">
                        <?php foreach ($lt['winners_detail'] as $w):
                            $wName = trim((string)($w['nickname'] ?? '')) ?: trim((string)($w['username'] ?? '')) ?: '已注销用户';
                            $wGranted = (int)($w['granted'] ?? 0);
                            $wRank = (int)($w['rank'] ?? 0);
                        ?>
                        <div class="ba-item" title="<?= e($wName) ?>">
                            <div class="ba-avatar">
                                <?php if (!empty($w['avatar_url'])): ?>
                                    <img src="<?= e($w['avatar_url']) ?>" alt="" onerror="this.style.display='none';this.parentNode.classList.add('ba-no-img');">
                                <?php else: ?>
                                    <span class="ba-initial"><?= e(mb_substr($wName, 0, 1, 'UTF-8')) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="ba-meta">
                                <div class="ba-name">
                                    <?= e($wName) ?>
                                    <?php if ($wGranted > 0): ?><span class="ba-rank">#<?= $wRank ?> · +<?= $wGranted ?></span><?php endif; ?>
                                </div>
                                <div class="ba-time">✓ 中奖<?php if ($wGranted > 0): ?> · 获得 <?= $wGranted . ' ' . e($lotteryCurrencyName) ?><?php endif; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="sb-note">暂无中奖者（可能无人满足抽奖条件）。</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <script>
    function payBuy(id, scope) {
        // scope: all / content / attachment / image；缺省 all
        postJSON(url('post/payBuy'), {id: id, scope: scope || 'all'}, function (res) {
            if (res && res.code === 0) { toast('购买成功', 'success'); setTimeout(function(){ location.reload(); }, 500); }
            else toast((res && res.message) || '购买失败', 'error');
        }, function (res) { toast((res && res.message) || '网络错误', 'error'); });
    }
    function eventSignup(id) {
        var fd = {id: id};
        document.querySelectorAll('#eventSignupForm [name^=cf_]').forEach(function (el) { fd[el.name] = el.value; });
        postJSON(url('post/eventSignup'), fd, function (res) {
            if (res && res.code === 0) { toast('报名成功', 'success'); setTimeout(function(){ location.reload(); }, 500); }
            else toast((res && res.message) || '报名失败', 'error');
        }, function (res) { toast((res && res.message) || '网络错误', 'error'); });
    }
    function openEventSignup() { var f = document.getElementById('eventSignupForm'); if (f) f.style.display = 'block'; }
    // 悬赏主题：我来回答 —— 滚动到评论输入框并聚焦，提示用户在此发表回答
    function openBountyAnswer() {
        var ta = document.getElementById('commentInput');
        if (!ta) { toast('评论区未加载', 'error'); return; }
        ta.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(function () {
            try { ta.focus(); } catch (e) {}
            try { ta.setSelectionRange(ta.value.length, ta.value.length); } catch (e) {}
        }, 350);
    }
    function bountyAccept(id, cid) {
        if (!confirm('确认采纳该回答并向回答者发放积分？')) return;
        postJSON(url('post/bountyAccept'), {id: id, answer_id: cid}, function (res) {
            if (res && res.code === 0) { toast('已采纳', 'success'); location.reload(); }
            else toast((res && res.message) || '操作失败', 'error');
        }, function (res) { toast((res && res.message) || '网络错误', 'error'); });
    }
    function bountyEndEarly(id) {
        if (!confirm('确认提前结束悬赏？剩余未分配的质押将扣除 50% 手续费后返还给您。')) return;
        postJSON(url('post/bountyEndEarly'), {id: id}, function (res) {
            if (res && res.code === 0) { toast('已结束，返还 ' + (res.data ? res.data.refund : 0) + ' 积分', 'success'); location.reload(); }
            else toast((res && res.message) || '操作失败', 'error');
        }, function (res) { toast((res && res.message) || '网络错误', 'error'); });
    }
    function pollVote(id) {
        var opts = [];
        document.querySelectorAll('input[name=poll_vote]:checked').forEach(function (el) { opts.push(parseInt(el.value, 10)); });
        if (!opts.length) { toast('请选择选项', 'warning'); return; }
        postJSON(url('post/pollVote'), {id: id, options: opts}, function (res) {
            if (res && res.code === 0) { toast('投票成功', 'success'); location.reload(); }
            else toast((res && res.message) || '投票失败', 'error');
        }, function (res) { toast((res && res.message) || '网络错误', 'error'); });
    }
    /* ===== 辩论主题「我要站队发言」提交（替代旧 debateJoin + openDebateDialog）=====
     * 站在站点页评论区侧：
     *   - side：优先取 hidden #debateSideInput（已站队用户锁定方），否则取 radio[name=debate_side]:checked
     *   - content：取 #commentInput.value（与发表评审公用一个输入框）
     *   - POST 到 post/debateJoin（后端校验：deadline / 已站队方匹配 / 同方多次 OK）
     *   - 成功后 reload 让评论列表重渲（debate statements 已 union 进评论区）
     */
    function submitDebateComment(postId) {
        var side = 0;
        var sideHidden = document.getElementById('debateSideInput');
        if (sideHidden && sideHidden.value) {
            side = parseInt(sideHidden.value, 10);
        } else {
            var r = document.querySelector('input[name=debate_side]:checked');
            if (r) side = parseInt(r.value, 10);
        }
        if (!side || (side !== 1 && side !== 2)) { toast('请选择正方/反方', 'warning'); return; }
        var ta = document.getElementById('commentInput');
        var content = ta ? ta.value.trim() : '';
        if (!content) { toast('请输入发言内容', 'warning'); return; }
        var token = document.querySelector('input[name="_token"]') ? document.querySelector('input[name="_token"]').value : '';
        var mount = document.getElementById('captchaMountComment');
        var proceed = function (sol) {
            var data = { id: postId, side: side, content: content, _token: token };
            // 辩论进行中引用站队发言 → 随站队发言一并落库引用块
            if (topDebateQuote) {
                data.quote_id = topDebateQuote.quote_id;
                data.quote_type = topDebateQuote.quote_type;
                data.quote_author = topDebateQuote.quote_author;
                data.quote_snippet = topDebateQuote.quote_snippet;
            }
            if (sol) { data.captcha_token = sol.token; data.captcha_x = sol.x; }
            var btn = document.getElementById('commentSubmitBtn');
            if (btn) { btn.disabled = true; var orgTxt = btn.textContent; btn.textContent = '提交中…'; }
            postJSON(url('post/debateJoin'), data, function (res) {
                if (btn) { btn.disabled = false; btn.textContent = orgTxt; }
                if (res && res.code === 0) {
                    if (ta) ta.value = '';
                    topDebateQuote = null;
                    var tpq = document.getElementById('topQuotePreview');
                    if (tpq) { tpq.style.display = 'none'; tpq.innerHTML = ''; }
                    toast('已发表站队发言', 'success');
                    setTimeout(function () { location.reload(); }, 500);
                } else {
                    toast((res && res.message) || '提交失败', 'error');
                }
            }, function (res) {
                if (btn) { btn.disabled = false; btn.textContent = orgTxt; }
                toast((res && res.message) || '网络错误', 'error');
            });
        };
        // captcha：用现有评论区挂载点（captcha 场景 comment）
        try {
            if (window.Captcha && mount) {
                Captcha.ensure('comment', mount).then(proceed).catch(function (msg) {
                    toast(msg || '请完成滑块验证', 'error');
                });
            } else {
                proceed(null);
            }
        } catch (e) { proceed(null); }
    }
    function interviewAsk(id) {
        var q = document.getElementById('interviewQuestion').value.trim();
        if (!q) { toast('请输入问题', 'warning'); return; }
        postJSON(url('post/interviewAddQa'), {id: id, question: q}, function (res) {
            if (res && res.code === 0) { toast('提问已提交', 'success'); location.reload(); }
            else toast((res && res.message) || '提交失败', 'error');
        }, function (res) { toast((res && res.message) || '网络错误', 'error'); });
    }
    function showAnswerForm(qaId) {
        var f = document.getElementById('answerForm-' + qaId);
        if (f) { f.style.display = 'block'; var ta = document.getElementById('answerInput-' + qaId); if (ta) ta.focus(); }
    }
    function hideAnswerForm(qaId) {
        var f = document.getElementById('answerForm-' + qaId);
        if (f) { f.style.display = 'none'; var ta = document.getElementById('answerInput-' + qaId); if (ta) ta.value = ''; }
    }
    function submitAnswer(qaId, postId) {
        var ta = document.getElementById('answerInput-' + qaId);
        var a = ta ? ta.value.trim() : '';
        if (!a) { toast('请输入回答内容', 'warning'); return; }
        postJSON(url('post/interviewAnswer'), {id: postId, qa_id: qaId, answer: a}, function (res) {
            if (res && res.code === 0) { toast('回答已提交', 'success'); location.reload(); }
            else toast((res && res.message) || '提交失败', 'error');
        }, function (res) { toast((res && res.message) || '网络错误', 'error'); });
    }
    function interviewEnd(postId) {
        if (!confirm('确认结束本次采访？结束后将无法再提问/回答（评论区不受影响）。')) return;
        postJSON(url('post/interviewEnd'), {id: postId}, function (res) {
            if (res && res.code === 0) { toast('采访已结束', 'success'); location.reload(); }
            else toast((res && res.message) || '操作失败', 'error');
        }, function (res) { toast((res && res.message) || '网络错误', 'error'); });
    }
    /* ===== 截止 / 开奖倒计时：扫描 .countdown-chip[data-ts] 每秒刷新 =====
     * data-ts 为服务端 strtotime() 得到的绝对时间戳（秒），与「页面渲染时剩余的秒数」解耦，
     * 即使页面长时间不刷新也能正确倒计时。
     */
    (function () {
        function pad(n) { return (n < 10 ? '0' : '') + n; }
        function fmt(sec) {
            if (sec <= 0) return '已结束';
            var d = Math.floor(sec / 86400);
            var h = Math.floor((sec % 86400) / 3600);
            var m = Math.floor((sec % 3600) / 60);
            var s = sec % 60;
            if (d > 0) return d + '天 ' + pad(h) + ':' + pad(m) + ':' + pad(s);
            return pad(h) + ':' + pad(m) + ':' + pad(s);
        }
        // 2026-09-01：抽奖 chip 到期文案与辩论不同——辩论是「已截止」，抽奖是「待开奖」
        // 通过给 .countdown-chip 加 data-kind="lottery" 区分（默认 debate）
        function tickCountdowns() {
            var els = document.querySelectorAll('.countdown-chip[data-ts]');
            for (var i = 0; i < els.length; i++) {
                var el = els[i];
                var ts = parseInt(el.getAttribute('data-ts'), 10);
                if (!ts) continue;
                var left = ts - Math.floor(Date.now() / 1000);
                var kind = el.getAttribute('data-kind');
                if (left > 0) {
                    el.textContent = '⏳ ' + fmt(left);
                    el.classList.remove('ended');
                } else {
                    // 过期后：辩论 → 静态「已结束」；抽奖 → 「待开奖 · 00:00:00」（倒计时归零，
                    // 用户原话「时间倒着走」—— 用倒计时形式而非顺计时形式表达「还差多久」）
                    if (kind === 'lottery') {
                        el.textContent = '待开奖 · 00:00:00';
                    } else {
                        el.textContent = '已结束';
                    }
                    el.classList.add('ended');
                }
            }
        }
        document.addEventListener('DOMContentLoaded', function () {
            tickCountdowns();
            setInterval(tickCountdowns, 1000);
        });
    })();
    </script>

    <?php if (!empty($rewardList)): ?>
    <div class="post-reward-list">
        <div class="post-reward-head">
            <span>打赏记录</span>
            <span class="post-reward-count">共 <?= count($rewardList) ?> 笔</span>
        </div>
        <ul class="post-reward-ul">
            <?php foreach ($rewardList as $r): ?>
            <li class="post-reward-item">
                <?= e(user_display_name($r)) ?>
                于 <?= time_ago($r['created_at']) ?>
                打赏了 <strong><?= (int)$r['token'] ?></strong> <?= e($tokenLabel) ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="post-actions">
        <div class="action-group action-group-interact">
        <?php if (is_logged_in()): ?>
        <button class="action-btn <?= $liked ? 'active' : '' ?>" onclick="likePost(<?= $post['id'] ?>, this)">
            <span>赞</span><span class="count"><?= $post['like_count'] ?></span>
        </button>
        <button class="action-btn <?= $collected ? 'active' : '' ?>" onclick="collectPost(<?= $post['id'] ?>, this)">
            <span>收藏</span><span class="count"><?= $post['collect_count'] ?></span>
        </button>
        <button class="action-btn" onclick="reportItem('post', <?= $post['id'] ?>)">
            <span>举报</span>
        </button>
        <?php if ($post['user_id'] != Auth::id()): ?>
        <button class="action-btn action-reward" onclick="openReward(<?= $post['id'] ?>, 0)">
            <span>赏</span>
        </button>
        <?php endif; ?>
        <?php else: ?>
        <span class="action-btn"><span>赞</span><span class="count"><?= $post['like_count'] ?></span></span>
        <span class="action-btn"><span>收藏</span><span class="count"><?= $post['collect_count'] ?></span></span>
        <a href="<?= login_url() ?>" class="btn btn-primary btn-sm">登录后操作</a>
        <?php endif; ?>
        </div>

        <?php if (is_logged_in() && ($post['user_id'] == Auth::id() || $isModerator || is_admin())): ?>
        <div class="action-group action-group-manage">
        <?php if (is_logged_in() && ($post['user_id'] == Auth::id() || $isModerator)): ?>
        <a href="<?= url('post/edit', ['id' => $post['id']]) ?>" class="btn btn-sm">编辑</a>
        <?php endif; ?>
        <?php if (is_logged_in() && ($post['user_id'] == Auth::id() || $isModerator || is_admin())): ?>
        <button class="btn btn-sm btn-danger" onclick="deletePost(<?= $post['id'] ?>)">删除</button>
        <?php endif; ?>

        <?php if ($isModerator): ?>
        <button class="btn btn-sm <?= !empty($post['is_closed']) ? 'btn-primary' : '' ?>" onclick="toggleClosePost(<?= $post['id'] ?>, <?= !empty($post['is_closed']) ? 1 : 0 ?>)"><?= !empty($post['is_closed']) ? '开启帖子' : '关闭帖子' ?></button>
        <?php endif; ?>
        <?php
            $pinScope = (int)($post['pin_scope'] ?? 0);
            $canPinSection = Auth::can('post.pin_section');
            $canPinGlobal  = Auth::can('post.pin_global');
            $canEssence    = Auth::can('post.essence_section');
            // 置顶菜单：本版版主，或拥有「全局置顶」权限的任意角色（后台可授予非版主角色）都能看到
            $canShowPinMenu = $isModerator || $canPinGlobal;
        ?>
        <?php if ($canShowPinMenu): ?>
        <div class="pin-dropdown" id="pinDropdown-<?= $post['id'] ?>">
            <button type="button" class="btn btn-sm pin-trigger" onclick="this.parentNode.classList.toggle('open')" aria-haspopup="true"><?= $pinScope > 0 ? '改/取消置顶 ▾' : '置顶 ▾' ?></button>
            <div class="pin-menu" role="menu">
                <?php if ($canPinSection): ?>
                <button type="button" class="pin-menu-item <?= $pinScope === 1 ? 'is-active' : '' ?>" onclick="doPin(<?= $post['id'] ?>, 1)">本版置顶<?= $pinScope === 1 ? ' ✓' : '' ?></button>
                <?php endif; ?>
                <?php if ($canPinGlobal): ?>
                <button type="button" class="pin-menu-item <?= $pinScope === 2 ? 'is-active' : '' ?>" onclick="doPin(<?= $post['id'] ?>, 2)">全局置顶<?= $pinScope === 2 ? ' ✓' : '' ?></button>
                <?php endif; ?>
                <?php if (($pinScope === 2 && $canPinGlobal) || ($pinScope === 1 && $canPinSection)): ?>
                <button type="button" class="pin-menu-item pin-cancel" onclick="doUnpin(<?= $post['id'] ?>)">取消置顶</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($canEssence): ?>
        <button class="btn btn-sm" onclick="toggleEssence(<?= $post['id'] ?>, <?= !empty($post['is_essence']) ? 1 : 0 ?>)"><?= $post['is_essence'] ? '取消精选' : '精选' ?></button>
        <?php endif; ?>
        <?php if ($isAuthor && $canSelfPin): ?>
        <button class="btn btn-sm" onclick="openSelfPin(<?= $post['id'] ?>)">自助置顶</button>
        <?php endif; ?>
        </div>
        <?php endif; ?>


        <?php if ($isModerator): ?>
        <div class="action-group action-group-move">
        <select id="moveSelect" class="form-control" style="width:auto;display:inline-block;margin:0;">
            <?php
            $allCats = Model::table('categories')->where('status', 1)->get();
            foreach ($allCats as $mc):
            ?>
            <option value="<?= $mc['id'] ?>" <?= $mc['id'] == $post['category_id'] ? 'selected' : '' ?>><?= e($mc['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-sm" onclick="movePost(<?= $post['id'] ?>)">移动</button>
        </div>
        <?php endif; ?>
    </div>

</div>

<!-- 评论区 -->
<div class="card" style="margin-top:16px;">
    <div class="card-header">
        全部评论（<?= $totalComments ?>）
    </div>
    <div class="card-body">
        <?php if (!empty($post['is_closed'])): ?>
        <div class="alert alert-warning" style="margin-bottom:20px;">该帖子已关闭，仅可浏览，无法发表回复。</div>
        <?php elseif (is_banned()): ?>
        <div class="alert alert-warning" style="margin-bottom:20px;">您已被禁言，仅可浏览，无法发表回复。</div>
        <?php elseif (is_logged_in()): ?>
        <?php
        // 悬赏主题：未结束（status=1）时评论框变成"我来回答"模式 —— 提交即标 is_bounty_answer
        // 楼中楼入口由后端硬校验拒绝，前端仅给提示文案，不出现「回复」按钮
        $bountyActive = (!empty($sp['type']) && $sp['type'] === 'bounty' && !empty($sp['bounty']) && (int)$sp['bounty']['status'] === 1);
        // 辩论主题：评论区按钮文案变「我要站队发言」，提交时调 debateJoin（而非 submitComment），
        //   并强制选 side。已站队方则锁定到该方（不可切换）。
        $debateActive = (!empty($sp['type']) && $sp['type'] === 'debate' && !empty($sp['debate']));
        $debateClosed = $debateActive && !empty($sp['debate']['closed']);
        $debateFixedSide = $debateActive && !empty($sp['debate']['my_side']) ? (int)$sp['debate']['my_side'] : 0;
        // 辩论主题下：未截止 → 主框发表「站队发言」（submitDebateComment）；已截止 → 恢复原生评论（submitComment）。
        // 引用站队发言时也走 submitComment（顶层评论 + 引用块），故统一由 submitMain 分发。
        $commentPlaceholder = $bountyActive ? '在此发表你的回答（悬赏期不可楼中楼回复；悬赏结束后开放）'
            : ($debateActive ? ($debateClosed ? '写下你的评论…（辩论已截止，可正常评论或引用他人站队发言）' : '在此输入你的站队发言…') : '写下你的评论...');
        $submitText = $bountyActive ? '提交回答'
            : ($debateActive ? ($debateClosed ? '发表评论' : '我要站队发言') : '发表评论');
        // 主提交入口统一为 submitMain：pendingTopQuote（引用站队发言）优先走 submitComment；否则按辩论状态分流。
        $submitOnClick = 'submitMain(' . (int)$post['id'] . ')';
        ?>
        <div style="margin-bottom:20px;">
            <?= csrf_field() ?>
            <?php if ($debateActive && !$debateClosed): ?>
            <div style="margin-bottom:10px;display:flex;align-items:center;gap:18px;flex-wrap:wrap;">
                <span style="font-size:13px;color:#555;">选择站队：</span>
                <?php if ($debateFixedSide === 1): ?>
                    <span class="badge-debate-side pro">正方（已锁定）</span>
                    <input type="hidden" id="debateSideInput" value="1">
                <?php elseif ($debateFixedSide === 2): ?>
                    <span class="badge-debate-side con">反方（已锁定）</span>
                    <input type="hidden" id="debateSideInput" value="2">
                <?php else: ?>
                    <label style="display:inline-flex;align-items:center;gap:6px;color:#1e6fd9;font-weight:600;cursor:pointer;">
                        <input type="radio" name="debate_side" value="1" required checked> 正方
                    </label>
                    <label style="display:inline-flex;align-items:center;gap:6px;color:#e0852a;font-weight:600;cursor:pointer;">
                        <input type="radio" name="debate_side" value="2"> 反方
                    </label>
                <?php endif; ?>
            </div>
            <?php elseif ($debateActive && $debateClosed): ?>
            <div class="alert alert-warning" style="margin-bottom:10px;padding:6px 12px;font-size:12px;">
                本辩论已截止，不能发表新的站队发言；但可正常评论，或引用他人的站队发言参与讨论。
            </div>
            <?php endif; ?>
            <textarea id="commentInput" class="form-control" data-mention="1" placeholder="<?= e($commentPlaceholder) ?>" style="min-height:80px;"></textarea>
            <div id="topQuotePreview" class="reply-quote-preview" style="display:none;margin-top:8px;"></div>
            <!-- 顶层回复支持图片；楼中楼（_comment.php 内联回复框）不提供图片上传 -->
            <div id="commentImageUploader" style="margin-top:8px;display:flex;flex-wrap:wrap;gap:8px;"></div>
            <?php if (function_exists('captcha_scene_on') && captcha_scene_on('comment')): ?>
            <!-- 评论区走悬浮弹窗模式（data-captcha-mode="floating"）：
                 长帖楼层多时，滑块不会埋在底部；用户点提交时弹出居中弹窗，避免回头翻页。
                 不在页面加载时主动渲染 captcha.create，避免无谓请求；仅在 submitComment/submitReply 调 Captcha.ensure 时才创建弹窗。 -->
            <div id="captchaMountComment" class="captcha-mount" data-captcha-mode="floating" style="margin-top:8px;"></div>
            <?php endif; ?>
            <script src="<?= asset('js/captcha.js') ?>"></script>
            <?php if ($bountyActive): ?>
            <div class="alert alert-info" style="margin-top:8px;padding:6px 12px;font-size:12px;">
                💡 本帖为<b>悬赏主题</b>，您发表的内容将作为「<b>回答</b>」计入悬赏；<b>悬赏期间不可楼中楼回复</b>，悬赏结束后「原生评论 + 楼中楼回复」将开放。
            </div>
            <?php endif; ?>
            <?php if ($debateActive && !$debateClosed): ?>
            <div class="alert alert-info" style="margin-top:8px;padding:6px 12px;font-size:12px;">
                💡 本帖为<b>辩论主题</b>，您的发言将作为站队内容显示在评论区，按所选正/反方套底色；
                <?php if ($debateFixedSide): ?>
                    您已站队 <?= $debateFixedSide === 1 ? '<b>正方</b>' : '<b>反方</b>' ?>，此处方已锁定，可继续在同一方发言补充论据。
                <?php else: ?>
                    首次发言需选择正/反方后即站定，<b>不可切换站队</b>。
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;">
                <div style="display:flex;align-items:center;gap:6px;">
                    <?php if (can_upload_image()): ?>
                    <label id="commentImageLabel" class="btn btn-sm" style="cursor:pointer;background:#f5f5f5;border:1px solid #ddd;color:#666;margin:0;">
                        <span data-text-default="添加图片" data-text-more="再添加一张">添加图片</span>
                        <input type="file" id="commentImageInput" accept="image/*" multiple style="display:none;" onchange="commentImageChange(this)">
                    </label>
                    <?php else: ?>
                    <span style="font-size:12px;color:#999;" title="由后台「允许发图角色」设置限制">当前角色无法上传图片</span>
                    <?php endif; ?>
                    <button type="button" class="emoji-trigger" data-emoji-trigger data-emoji-target="commentInput" title="插入表情（:code: 短代码，发表后自动渲染）"><i class="fa-regular fa-face-smile" aria-hidden="true"></i> 表情</button>
                </div>
                <button class="btn btn-primary btn-sm" id="commentSubmitBtn" onclick="<?= $submitOnClick ?>"><?= e($submitText) ?></button>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-info">请先 <a href="<?= login_url() ?>">登录</a> 后参与评论</div>
        <?php endif; ?>

        <div class="comment-list" id="commentList">
            <?php
            // 辩论主题：把站队发言（topic_debate_statements）合并到评论列表，按 created_at 升序 + id 升序统一渲染。
            //   这样站点发言就直接显示在评论区，与悬赏帖的回答合并方式一致。
            //   - 普通评论：type='comment'（默认）
            //   - 站队发言：type='debate'，并附带 debate_side(1/2)
            $mergedList = (array)($comments ?? []);
            if (!empty($sp['debate']['statements'])) {
                foreach ((array)$sp['debate']['statements'] as $_st) {
                    $mergedList[] = [
                        'id'           => (int)($_st['id'] ?? 0),
                        'post_id'      => (int)($post['id'] ?? 0),
                        'user_id'      => (int)($_st['user_id'] ?? 0),
                        'content'      => (string)($_st['content'] ?? ''),
                        'created_at'   => (string)($_st['created_at'] ?? ''),
                        'nickname'     => (string)($_st['nickname'] ?? ''),
                        'username'     => (string)($_st['username'] ?? ''),
                        'avatar'       => (string)($_st['avatar'] ?? ''),
                        'is_certified' => (int)($_st['is_certified'] ?? 0),
                        'certified_at' => $_st['certified_at'] ?? null,
                        'status'       => 1,                // 无 status 字段默认为正常（deleted 用户已被清理过）
                        'image'        => null,
                        'parent_id'    => 0,
                        // 站队发言不再支持楼中楼回复，children 恒为空
                        'children'     => [],
                        'floor'        => 0,                // 不参与「楼层」编号
                        // 标识辩论类型 + side
                        '__type'       => 'debate',
                        'debate_side'  => (int)($_st['side'] ?? 0),
                        // 引用块（辩论未截止时引用站队发言 → 作为一条新站队发言，引用块随 statements 渲染）
                        'quote_id'     => isset($_st['quote_id']) ? (int)$_st['quote_id'] : null,
                        'quote_type'   => (string)($_st['quote_type'] ?? ''),
                        'quote_author' => (string)($_st['quote_author'] ?? ''),
                        'quote_snippet'=> (string)($_st['quote_snippet'] ?? ''),
                    ];
                }
                // 按 created_at ASC + id ASC 排序；同时间靠 id 决断
                usort($mergedList, function ($a, $b) {
                    $ca = (string)($a['created_at'] ?? '');
                    $cb = (string)($b['created_at'] ?? '');
                    if ($ca !== $cb) return strcmp($ca, $cb);
                    return ((int)($a['id'] ?? 0)) - ((int)($b['id'] ?? 0));
                });
            }
            ?>
            <?php if (empty($mergedList)): ?>
            <div class="empty-state" style="padding:30px;">
                <p><?= (!empty($sp['type']) && $sp['type'] === 'bounty' && (int)($sp['bounty']['status'] ?? 0) === 1) ? '暂无回答，期待你来发表第一条' : '暂无评论，来说点什么吧' ?></p>
            </div>
            <?php else: ?>
                <?php
                // 给 _comment partial 注入悬赏主题上下文（2026-08-31）：
                //   用于决定是否加底色 + 「回答」/「已采纳」标识 + 楼主「采纳此回答」按钮 + 悬赏期禁楼中楼
                $_bounty_ctx = null;
                if (!empty($sp['bounty'])) {
                    $__b = $sp['bounty'];
                    $_bounty_ctx = [
                        'is_bounty_theme' => true,
                        'is_author'        => !empty($__b['is_author']),
                        'bounty_status'    => (int)($__b['status'] ?? 0),
                        // 已采纳 ids 列表（hashtable）方便 O(1) 查找
                        'accepted_ids'     => !empty($__b['accepted_comment_ids']) ? array_flip(array_map('intval', $__b['accepted_comment_ids'])) : [],
                    ];
                }
                ?>
                <?php foreach ($mergedList as $comment): ?>
                    <?php
                    // 站队发言：仅透传 debate_side 给 partial；不在原$comment['floor']上动手脚。
                    // 辩论已截止 → 站队发言恢复「原生评论」样式（不再加正反方底色/标识），视作普通评论展示。
                    $_isDebateItem = (isset($comment['__type']) && $comment['__type'] === 'debate');
                    $_debate_side = ($_isDebateItem && !$debateClosed) ? (int)$comment['debate_side'] : 0;
                    ?>
                    <?php View::partial('post/_comment', [
                        'comment'         => $comment,
                        'post'            => $post,
                        'isModerator'     => $isModerator,
                        'floor'           => $comment['floor'],
                        'childInitVisible'=> $childInitVisible,
                        'childPerPage'    => $childPerPage,
                        'bounty_ctx'      => $_bounty_ctx,
                        'debate_side'     => $_debate_side,
                        '__type'          => $_isDebateItem ? 'debate' : 'comment',
                    ]); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
        // 引用定位锚点补丁（v2：自动翻页定位，不要 toast）
        //   1) 收集本帖所有评论（含楼中楼 + 站队发言）的 quote_id，找出「存在但不在当前可见区」的 id
        //   2) 对每个缺失 anchor，walk parent 链上溯到顶层祖先
        //   3) 用顶层祖先在「全帖顶层评论 id ASC」里的索引 / perPage 算出所在 page
        //   4) 只为「顶层祖先所在 page != 当前 page」的 anchor 注入隐藏 span + data-locate-page
        //      同 page 的折叠楼中楼留给原生路径（DOM 里有真实 id，scrollIntoView 失败时静默即可）
        //   $mergedList 在辩论主题里同时含普通评论 + 站队发言；非辩论主题就是 $comments 别名
        $_visibleIds = [];
        foreach ((array)($mergedList ?? ($comments ?? [])) as $_ml) {
            if (isset($_ml['id'])) $_visibleIds[(int)$_ml['id']] = true;
            foreach ((array)($_ml['children'] ?? []) as $_ch) $_visibleIds[(int)$_ch['id']] = true;
        }
        $_missingQuoteIds = [];
        $_collectQuoteIds = function($_item) use (&$_collectQuoteIds, &$_missingQuoteIds, &$_visibleIds) {
            if (!empty($_item['quote_id']) && empty($_visibleIds[(int)$_item['quote_id']])) {
                $_missingQuoteIds[] = (int)$_item['quote_id'];
            }
            if (isset($_item['children']) && is_array($_item['children'])) {
                foreach ($_item['children'] as $_ch) $_collectQuoteIds($_ch);
            }
        };
        foreach ((array)($mergedList ?? ($comments ?? [])) as $_ml) $_collectQuoteIds($_ml);
        $_missingQuoteIds = array_values(array_unique(array_filter($_missingQuoteIds)));
        $_extraAnchors = []; // commentId => pageNo（null 表示异常）
        if (!empty($_missingQuoteIds)) {
            try {
                // 一次查询：拿到所有候选 anchor 的 post_id / status / parent_id
                $_ph = implode(',', array_fill(0, count($_missingQuoteIds), '?'));
                $_candidates = Model::query(
                    "SELECT id, post_id, status, parent_id FROM comments WHERE id IN ($_ph)",
                    $_missingQuoteIds
                );
                $_candsById = [];
                foreach ($_candidates as $_row) $_candsById[(int)$_row['id']] = $_row;
                // 一次查询：拿到本帖所有顶层评论 id 升序的索引
                $_topIndex = []; // topId => 0-based index
                $_topRows = Model::query(
                    "SELECT id FROM comments WHERE post_id = ? AND (parent_id IS NULL OR parent_id = 0) ORDER BY id ASC",
                    [$post['id']]
                );
                foreach ($_topRows as $_i => $_tr) $_topIndex[(int)$_tr['id']] = (int)$_i;
                // 一次查询：拿到全帖 parent 关系（childId => parentId，0 表示顶层）
                $_parentOf = [];
                $_parentRows = Model::query(
                    "SELECT id, parent_id FROM comments WHERE post_id = ?",
                    [$post['id']]
                );
                foreach ($_parentRows as $_pr) {
                    $_parentOf[(int)$_pr['id']] = (int)($_pr['parent_id'] ?? 0);
                }
                $_pp = (int)$perPage;
                if ($_pp < 1) $_pp = 1;
                $_curPage = (int)$page;
                foreach ($_missingQuoteIds as $_aid) {
                    $_row = $_candsById[$_aid] ?? null;
                    if (!$_row) continue;
                    if ((int)$_row['status'] !== 1) continue;                   // 真删了 → 不注入，由 JS toast
                    if ((int)$_row['post_id'] !== (int)$post['id']) continue;  // 跨帖 → 不注入
                    // walk 找顶层祖先（防御性限深 100 防数据脏）
                    $_cur = (int)$_row['id'];
                    for ($_step = 0; $_step < 100; $_step++) {
                        $_next = $_parentOf[$_cur] ?? 0;
                        if ($_next === 0 || !isset($_parentOf[$_cur])) break;
                        $_cur = $_next;
                        if ($_cur === 0) break;
                    }
                    $_rootId = $_cur;
                    if (!isset($_topIndex[$_rootId])) continue;
                    $_pageNo = (int)floor($_topIndex[$_rootId] / $_pp) + 1;
                    // 同页的折叠楼中楼不强制翻页（交回原生 scrollIntoView 路径，silent fail）
                    if ($_pageNo !== $_curPage) $_extraAnchors[(int)$_aid] = $_pageNo;
                }
            } catch (\Throwable $e) {
                $_extraAnchors = []; // 出错就不注入占位，JS 走老路径（基本不会触发）
            }
        }
        ?>
        <?php if (!empty($_extraAnchors)): ?>
        <div id="quoteAnchorPatch" aria-hidden="true" style="height:0;line-height:0;overflow:hidden;">
            <?php foreach ($_extraAnchors as $_qa => $_page): ?>
            <span id="comment-<?= (int)$_qa ?>" data-missing-quote="1" data-locate-page="<?= (int)$_page ?>"></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if ($totalPages > 1): ?>
        <div class="pagination comment-pagination" style="margin-top:18px;">
            <?php if ($page > 1): ?>
            <a href="<?= url('post/show', ['id' => $post['id'], 'page' => $page - 1]) ?>" class="page-btn">上一页</a>
            <?php endif; ?>
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <?php if ($p === $page): ?>
            <span class="page-btn active"><?= $p ?></span>
            <?php else: ?>
            <a href="<?= url('post/show', ['id' => $post['id'], 'page' => $p]) ?>" class="page-btn"><?= $p ?></a>
            <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a href="<?= url('post/show', ['id' => $post['id'], 'page' => $page + 1]) ?>" class="page-btn">下一页</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ============ 打赏弹窗 ============ -->
<div class="modal-overlay" id="rewardModal" onclick="if(event.target===this)closePointsModal('rewardModal')">
    <div class="modal">
        <div class="modal-header">
            <span>打赏作者</span>
            <button type="button" class="modal-close" onclick="closePointsModal('rewardModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p class="points-balance">你的余额：<strong id="rewardBalance"><?= number_format((int)($myToken ?? 0)) ?></strong> <?= e($tokenLabel ?? \PointService::currencyLabel('token')) ?></p>
            <label class="form-label">打赏金额（≥ 5 <?= e($tokenLabel ?? \PointService::currencyLabel('token')) ?>）</label>
            <input type="hidden" id="rewardDefaultValue" value="<?= (int)($rewardDefault ?? 5) ?>">
            <input type="number" id="rewardAmount" class="form-control" min="5" step="1" value="<?= (int)($rewardDefault ?? 5) ?>" oninput="syncRewardPreset()">
            <div class="self-pin-presets" style="margin-top:10px;">
                <button type="button" class="preset-btn" onclick="setRewardAmount(5,this)">+5</button>
                <button type="button" class="preset-btn" onclick="setRewardAmount(10,this)">+10</button>
                <button type="button" class="preset-btn" onclick="setRewardAmount(50,this)">+50</button>
                <button type="button" class="preset-btn" onclick="setRewardAmount(100,this)">+100</button>
            </div>
            <p class="points-hint" id="rewardHint"></p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-sm" onclick="closePointsModal('rewardModal')">取消</button>
            <button type="button" class="btn btn-primary btn-sm" id="rewardConfirmBtn" onclick="submitReward()">确认打赏</button>
        </div>
    </div>
</div>

<!-- ============ 自助置顶弹窗 ============ -->
<div class="modal-overlay" id="selfPinModal" onclick="if(event.target===this)closePointsModal('selfPinModal')">
    <div class="modal">
        <div class="modal-header">
            <span>自助置顶</span>
            <button type="button" class="modal-close" onclick="closePointsModal('selfPinModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p class="points-balance">你的余额：<strong id="selfPinBalance"><?= number_format((int)($myToken ?? 0)) ?></strong> <?= e($tokenLabel ?? \PointService::currencyLabel('token')) ?> · 费率 <?= (int)($selfPinRate ?? 1000) ?> <?= e($tokenLabel ?? \PointService::currencyLabel('token')) ?>/小时</p>
            <label class="form-label">置顶时长</label>
            <div class="self-pin-presets">
                <button type="button" class="preset-btn" data-hours="1" onclick="setSelfPinHours(1,this)">1 小时</button>
                <button type="button" class="preset-btn" data-hours="6" onclick="setSelfPinHours(6,this)">6 小时</button>
                <button type="button" class="preset-btn" data-hours="12" onclick="setSelfPinHours(12,this)">12 小时</button>
                <button type="button" class="preset-btn" data-hours="24" onclick="setSelfPinHours(24,this)">24 小时</button>
                <button type="button" class="preset-btn" data-hours="48" onclick="setSelfPinHours(48,this)">48 小时</button>
                <button type="button" class="preset-btn" data-hours="72" onclick="setSelfPinHours(72,this)">72 小时</button>
            </div>
            <p class="points-hint" id="selfPinHint"></p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-sm" onclick="closePointsModal('selfPinModal')">取消</button>
            <button type="button" class="btn btn-primary btn-sm" id="selfPinConfirmBtn" onclick="submitSelfPin()">确认置顶</button>
        </div>
    </div>
</div>

<script>
/**
 * 评论多图灯箱：
 * - groupId：图组 ID（来自 _comment.php 的 data-img-group，用 md5(commentId) 区分不同评论）
 * - startIndex：用户点击的那张的索引，灯箱先定位到这里
 * 数据源：页面上 \u003Cscript type=application/json data-img-group="...">[...]\u003C/script\u003E
 */
window._lightboxGroup = null;
window.openCommentLightbox = function(groupId, startIndex) {
    var script = document.querySelector('script[type="application/json"][data-img-group="' + groupId + '"]');
    if (!script) return;
    var urls = [];
    try { urls = JSON.parse(script.textContent); } catch (e) { return; }
    if (!urls || !urls.length) return;
    startIndex = Math.max(0, Math.min(startIndex || 0, urls.length - 1));
    _lightboxGroup = {groupId: groupId, urls: urls, index: startIndex};

    var box = document.createElement('div');
    box.className = 'image-lightbox';
    box.innerHTML =
        '<div class="ilb-stage">' +
            '<img class="ilb-img" alt="" draggable="false" />' +
            '<button class="ilb-close" type="button" aria-label="关闭">\u00D7</button>' +
            '<button class="ilb-prev" type="button" aria-label="上一张">\u2039</button>' +
            '<button class="ilb-next" type="button" aria-label="下一张">\u203A</button>' +
            '<div class="ilb-counter">1 / 1</div>' +
        '</div>';
    document.body.appendChild(box);
    document.body.style.overflow = 'hidden';

    var stage = box.querySelector('.ilb-stage');
    var img    = box.querySelector('.ilb-img');
    var btnC   = box.querySelector('.ilb-close');
    var btnP   = box.querySelector('.ilb-prev');
    var btnN   = box.querySelector('.ilb-next');
    var cnt    = box.querySelector('.ilb-counter');

    // 缩放/平移状态：切换图片时重置
    var zoom = 1, panX = 0, panY = 0;
    function applyTransform() {
        img.style.transform = 'translate(' + panX + 'px,' + panY + 'px) scale(' + zoom + ')';
    }
    function resetView() { zoom = 1; panX = 0; panY = 0; applyTransform(); }

    function render() {
        img.src = _lightboxGroup.urls[_lightboxGroup.index];
        cnt.textContent = (_lightboxGroup.index + 1) + ' / ' + _lightboxGroup.urls.length;
        var multi = _lightboxGroup.urls.length > 1;
        btnP.style.display = multi ? '' : 'none';
        btnN.style.display = multi ? '' : 'none';
        resetView();
    }
    function close() {
        if (box.parentNode) box.parentNode.removeChild(box);
        document.body.style.overflow = '';
        document.removeEventListener('keydown', onKey);
        _lightboxGroup = null;
    }
    function nav(delta) {
        if (!_lightboxGroup || _lightboxGroup.urls.length < 2) return;
        _lightboxGroup.index = (_lightboxGroup.index + delta + _lightboxGroup.urls.length) % _lightboxGroup.urls.length;
        render();
    }
    function onKey(e) {
        if (e.key === 'Escape') close();
        else if (e.key === 'ArrowLeft')  nav(-1);
        else if (e.key === 'ArrowRight') nav(1);
    }

    // ===== 鼠标拖动切换（水平拖 > 80px 触发 nav，过程中图片跟随光标） =====
    var dragging = false, dragStartX = 0, dragDX = 0, didDrag = false;
    box.addEventListener('mousedown', function(e) {
        if (e.button !== 0) return;
        dragging = true; didDrag = false;
        dragStartX = e.clientX; dragDX = 0;
        box.style.cursor = 'grabbing';
    });
    box.addEventListener('mousemove', function(e) {
        if (!dragging) return;
        dragDX = e.clientX - dragStartX;
        if (Math.abs(dragDX) > 6) didDrag = true;
        // 缩放状态不跟手指；缩回 1× 时才做拖动视觉
        if (zoom === 1) img.style.transform = 'translateX(' + dragDX + 'px)';
    });
    box.addEventListener('mouseup', function() {
        if (!dragging) return;
        dragging = false;
        box.style.cursor = '';
        if (didDrag && Math.abs(dragDX) > 80) {
            nav(dragDX > 0 ? -1 : 1);
        } else {
            // 没拖到位 = 还原位移（不导航）
            applyTransform();
        }
    });
    box.addEventListener('mouseleave', function() {
        // 鼠标离开 box 时强制结束拖动
        if (dragging) { dragging = false; box.style.cursor = ''; applyTransform(); }
    });

    // ===== 滚轮缩放 =====
    box.addEventListener('wheel', function(e) {
        e.preventDefault();
        // deltaY > 0 = 向下滚 = 缩小；< 0 = 向上 = 放大
        var step = e.deltaY > 0 ? -0.2 : 0.2;
        zoom = Math.max(0.5, Math.min(5, +(zoom + step).toFixed(2)));
        if (zoom === 1) { panX = 0; panY = 0; }
        applyTransform();
    }, {passive: false});

    // ===== 按钮点击（stopPropagation 避免触发表层 click） =====
    btnP.addEventListener('click', function(e) { e.stopPropagation(); nav(-1); });
    btnN.addEventListener('click', function(e) { e.stopPropagation(); nav(1); });
    btnC.addEventListener('click', function(e) { e.stopPropagation(); close(); });

    // 点遮罩关闭（仅当点击目标 === stage 时，避免和按钮冲突）
    stage.addEventListener('click', function(e) {
        if (e.target === stage) close();
    });

    // ===== 触屏滑动（手机） =====
    var tStartX = 0, tStartY = 0, tDX = 0, tDY = 0;
    img.addEventListener('touchstart', function(e) {
        var t = e.touches[0]; tStartX = t.clientX; tStartY = t.clientY; tDX = 0; tDY = 0;
    }, {passive: true});
    img.addEventListener('touchmove', function(e) {
        var t = e.touches[0]; tDX = t.clientX - tStartX; tDY = t.clientY - tStartY;
    }, {passive: true});
    img.addEventListener('touchend', function() {
        if (Math.abs(tDX) > 50 && Math.abs(tDX) > Math.abs(tDY) * 1.5) {
            nav(tDX > 0 ? -1 : 1);
        }
    });

    document.addEventListener('keydown', onKey);
    render();
};
/** 兼容老调用：单图直接打开灯箱（套用多图灯箱同款样式 + 滚轮缩放） */
window.openImageLightbox = function(src) {
    var box = document.createElement('div');
    box.className = 'image-lightbox';
    box.innerHTML =
        '<div class="ilb-stage">' +
            '<img class="ilb-img" src="' + src + '" alt="" draggable="false" />' +
            '<button class="ilb-close" type="button" aria-label="关闭">\u00D7</button>' +
        '</div>';
    document.body.appendChild(box);
    document.body.style.overflow = 'hidden';

    var stage = box.querySelector('.ilb-stage');
    var img    = box.querySelector('.ilb-img');
    var btnC   = box.querySelector('.ilb-close');

    function close() {
        if (box.parentNode) box.parentNode.removeChild(box);
        document.body.style.overflow = '';
    }
    function onKey(e) { if (e.key === 'Escape') close(); }

    var zoom = 1;
    function applyZoom() {
        img.style.transform = 'scale(' + zoom + ')';
    }
    box.addEventListener('wheel', function(e) {
        e.preventDefault();
        var step = e.deltaY > 0 ? -0.2 : 0.2;
        zoom = Math.max(0.5, Math.min(5, +(zoom + step).toFixed(2)));
        applyZoom();
    }, {passive: false});

    btnC.addEventListener('click', function(e) { e.stopPropagation(); close(); });
    stage.addEventListener('click', function(e) { if (e.target === stage) close(); });
    document.addEventListener('keydown', onKey);
};
window.downloadAttachment = function(url) {
    var a = document.createElement('a');
    a.href = url;
    a.download = '';
    a.target = '_blank';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
};
/**
 * 楼中楼分页 / 发表后定位 相关工具
 * - getReplyItems(rootId)：取出某顶层评论下全部楼中楼节点（按 DOM 顺序 = 按 id 升序）
 * - refreshReplies(rootId)：根据「默认显示条数 / 每页条数 / 当前展开页」控制显隐 + 渲染翻页器
 * - expandReplies(rootId)：点「展开更多」后展开并定位到第 1 页
 * - showReplyPage(rootId, page)：翻到指定页
 * - flashComment(el)：新评论淡黄高亮
 */
// 楼中楼容器可能挂在「普通评论」(comment-<id>) 或「站队发言」(statement-<id>) 之下，二者都兼容
function replyRootEl(rootId) {
    return document.getElementById('comment-' + rootId) || document.getElementById('statement-' + rootId);
}
function getReplyItems(rootId) {
    var root = replyRootEl(rootId);
    if (!root) return [];
    var box = root.querySelector(':scope > .comment-body > .comment-children');
    if (!box) return [];
    return Array.from(box.children).filter(function (el) { return el.classList.contains('comment-item'); });
}
function ensureReplyPager(rootId, box) {
    var p = document.getElementById('replyPager-' + rootId);
    if (!p) {
        p = document.createElement('div');
        p.className = 'reply-pager';
        p.id = 'replyPager-' + rootId;
        p.style.display = 'none';
        box.appendChild(p);
    }
    return p;
}
function renderReplyPager(pagerEl, cur, pages, rootId) {
    if (!pagerEl) return;
    if (pages <= 1) { pagerEl.style.display = 'none'; pagerEl.innerHTML = ''; return; }
    pagerEl.style.display = '';
    var html = '';
    if (cur > 1) html += '<a href="javascript:;" class="rp-btn" onclick="showReplyPage(' + rootId + ',' + (cur - 1) + ')">上一页</a>';
    for (var i = 1; i <= pages; i++) {
        html += (i === cur)
            ? '<span class="rp-btn active">' + i + '</span>'
            : '<a href="javascript:;" class="rp-btn" onclick="showReplyPage(' + rootId + ',' + i + ')">' + i + '</a>';
    }
    if (cur < pages) html += '<a href="javascript:;" class="rp-btn" onclick="showReplyPage(' + rootId + ',' + (cur + 1) + ')">下一页</a>';
    pagerEl.innerHTML = html;
}
function refreshReplies(rootId) {
    var root = replyRootEl(rootId);
    if (!root) return;
    var box = root.querySelector(':scope > .comment-body > .comment-children');
    if (!box) return;
    var items = getReplyItems(rootId);
    var total = items.length;
    var initV = parseInt(box.dataset.initVisible || '2', 10);
    var perP  = parseInt(box.dataset.perPage || '5', 10);
    var expanded = box.dataset.expanded === '1';
    var expandEl = document.getElementById('replyExpand-' + rootId);
    var pagerEl  = document.getElementById('replyPager-' + rootId);

    if (total <= initV) {
        items.forEach(function (it) { it.style.display = ''; });
        if (expandEl) expandEl.style.display = 'none';
        if (pagerEl) pagerEl.style.display = 'none';
        return;
    }
    // 默认只显示前 initV 条
    items.forEach(function (it, i) { it.style.display = (i < initV) ? '' : 'none'; });
    if (!expanded) {
        if (expandEl) expandEl.style.display = '';
        if (pagerEl) pagerEl.style.display = 'none';
        return;
    }
    if (expandEl) expandEl.style.display = 'none';
    // 展开后，剩余回复分页（每页 perP 条）
    var rest = items.slice(initV);
    var pages = Math.max(1, Math.ceil(rest.length / perP));
    var cur = box._replyPage ? Math.min(box._replyPage, pages) : 1;
    box._replyPage = cur;
    rest.forEach(function (it, i) {
        it.style.display = (i >= (cur - 1) * perP && i < cur * perP) ? '' : 'none';
    });
    pagerEl = ensureReplyPager(rootId, box);
    renderReplyPager(pagerEl, cur, pages, rootId);
}
window.expandReplies = function (rootId) {
    var root = replyRootEl(rootId);
    if (!root) return;
    var box = root.querySelector(':scope > .comment-body > .comment-children');
    if (!box) return;
    box.dataset.expanded = '1';
    box._replyPage = 1;
    refreshReplies(rootId);
};
window.showReplyPage = function (rootId, page) {
    var root = replyRootEl(rootId);
    if (!root) return;
    var box = root.querySelector(':scope > .comment-body > .comment-children');
    if (!box) return;
    box.dataset.expanded = '1';
    box._replyPage = page;
    refreshReplies(rootId);
};
// 页面加载后对所有楼中楼容器做初始折叠（默认只显示前 initVisible 条，其余走「展开更多」）
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.comment-children').forEach(function (box) {
        var rootItem = box.closest('.comment-item');
        if (!rootItem) return;
        // 兼容普通评论(comment-) 与 站队发言(statement-) 两种锚点前缀
        var rid = (rootItem.id || '').replace(/^(comment|statement)-/, '');
        if (rid) refreshReplies(rid);
    });
});
function flashComment(el) {
    if (!el) return;
    el.style.transition = 'background 1.2s ease';
    el.style.background = '#fffbe6';
    setTimeout(function () { el.style.background = ''; }, 1300);
}

// 主评论框统一入口：
//   · 辩论进行中引用「站队发言」（topDebateQuote 置位）→ 走 submitDebateComment（作为一条新站队发言，底色 + 正/反方标识）
//   · 引用关闭/普通评论（pendingTopQuote 置位）→ 走 submitComment（原生评论 + 引用块），即便辩论未截止
//   · 否则：辩论未截止 → submitDebateComment（站队发言）；其它 → submitComment（普通评论）
window.submitMain = function(postId) {
    if (topDebateQuote) { submitDebateComment(postId); return; }
    if (pendingTopQuote) { submitComment(postId); return; }
    var debateAlive = !!(document.getElementById('debateSideInput') || document.querySelector('input[name="debate_side"]'));
    if (debateAlive) { submitDebateComment(postId); return; }
    submitComment(postId);
};
window.submitComment = function(postId) {
    var input = document.getElementById('commentInput');
    var content = input.value.trim();
    if (!content && (!commentImages || !commentImages.length)) { toast('请输入评论内容或添加图片', 'warning'); return; }
    var token = document.querySelector('input[name="_token"]') ? document.querySelector('input[name="_token"]').value : '';
    var mount = document.getElementById('captchaMountComment');
    Captcha.ensure('comment', mount).then(function(sol) {
        var data = {content: content, _token: token};
        if (commentImages && commentImages.length) data.images = commentImages;
        if (pendingTopQuote) {
            data.quote_id = pendingTopQuote.quote_id;
            data.quote_type = pendingTopQuote.quote_type;
            data.quote_author = pendingTopQuote.quote_author;
            data.quote_snippet = pendingTopQuote.quote_snippet;
            pendingTopQuote = null;
            var pv = document.getElementById('topQuotePreview');
            if (pv) { pv.style.display = 'none'; pv.innerHTML = ''; }
        }
        if (sol) { data.captcha_token = sol.token; data.captcha_x = sol.x; }
        postJSON(url('post/comment', {id: postId}), data, function(res) {
            if (res.code === 0) {
                toast('评论成功', 'success');
                if (res.data && res.data.points) showPointsToast(res.data.points);
                // 顶层评论按 id 升序，新评论 id 最大必然落在最后一页主评论页。
                // 直接跳到该页并锚定到新评论，实现「发表后定位到自己刚发的位置」。
                // 用绝对 URL（permalink=true）避免相对路径在 pretty URL 页内被当软跳转，导致 hash 复用 / 丢失。
                var targetPage = (res.data && res.data.top_page) || 1;
                var newCid = (res.data && res.data.comment && res.data.comment.id) || 0;
                var dest = newCid
                    ? url('post/show', {id: postId, page: targetPage}, true) + '#comment-' + newCid
                    : url('post/show', {id: postId, page: targetPage}, true);
                setTimeout(function () { window.location.href = dest; }, 600);
            } else {
                toast(res.message || '评论失败', 'error');
            }
        });
    }).catch(function(msg) {
        toast(msg || '请完成滑块验证', 'error');
        if (mount) { mount.removeAttribute('data-solved'); mount._capPromise = null; }
    });
};
/* ===== 顶层回复图片上传（楼中楼不支持，最多 9 张） ===== */
var MAX_COMMENT_IMAGES = 9;
var commentImages = [];  // 已上传成功的相对路径数组（提交时一并传给后端）
window.commentImageChange = function(input) {
    var files = Array.from(input.files || []);
    if (!files.length) return;
    var remaining = MAX_COMMENT_IMAGES - commentImages.length;
    if (remaining <= 0) { toast('最多上传 ' + MAX_COMMENT_IMAGES + ' 张图片', 'warning'); input.value = ''; return; }
    if (files.length > remaining) {
        toast('最多只能再上传 ' + remaining + ' 张', 'warning');
        files = files.slice(0, remaining);
    }
    var token = document.querySelector('input[name="_token"]') ? document.querySelector('input[name="_token"]').value : '';
    files.forEach(function(file) {
        var fd = new FormData();
        fd.append('file', file);
        fd.append('_token', token);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', url('upload/image', {type: 'post'}));
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function() {
            // 服务端权限/参数失败时返回 HTTP 4xx，但响应体仍是 JSON {code, message}。
            // 旧实现只在 status===200 时读 res.message，导致「当前角色无权上传图片」被吞成"上传失败"。
            var res = null;
            try { res = JSON.parse(xhr.responseText); } catch (e) { res = null; }
            if (res && res.code === 0 && res.data && res.data.url) {
                // 后端 UploadController::image 返回 data.url = "uploads/post/202608/xxx.jpg"（相对路径）。
                // 若将来返回的是绝对 URL（含 origin），自动剥到 uploads/ 起点。
                var rel = String(res.data.url);
                var m = rel.match(/(?:\/|^)public\/(uploads\/.*)$/) || rel.match(/^(uploads\/.*)$/);
                if (m) rel = m[1]; else { toast('上传成功但返回路径异常', 'error'); return; }
                commentImages.push(rel);
                renderCommentImage();
            } else {
                var msg = (res && res.message) ? res.message : ('上传失败（HTTP ' + xhr.status + '）');
                toast(msg, 'error');
            }
        };
        xhr.onerror = function() { toast('网络错误，请重试', 'error'); };
        xhr.send(fd);
    });
    input.value = '';
};
function renderCommentImage() {
    var box = document.getElementById('commentImageUploader');
    if (!box) return;
    box.innerHTML = '';
    if (!commentImages || !commentImages.length) return;
    // 仅 1 张：用原比例；多张：方形预览
    var isSingle = commentImages.length === 1;
    commentImages.forEach(function(p, idx) {
        var absUrl = absoluteAssetUrl(p);
        var sizeCss = isSingle
            ? 'max-width:320px;max-height:240px;'
            : 'width:80px;height:80px;';
        var imgCss = isSingle
            ? 'max-width:100%;max-height:240px;display:block;'
            : 'width:100%;height:100%;object-fit:cover;display:block;';
        var boxStyle = isSingle
            ? 'position:relative;display:inline-block;'
            : 'position:relative;width:80px;height:80px;flex-shrink:0;';
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.setAttribute('aria-label', '删除');
        btn.style.cssText = 'position:absolute;top:2px;right:2px;background:rgba(0,0,0,.55);color:#fff;border:none;border-radius:50%;width:20px;height:20px;line-height:18px;cursor:pointer;font-size:14px;';
        btn.innerHTML = '&times;';
        btn.addEventListener('click', function() { removeCommentImage(idx); });
        var wrap = document.createElement('div');
        wrap.style.cssText = boxStyle + 'border-radius:6px;overflow:hidden;background:#f0f0f0;';
        var img = document.createElement('img');
        img.src = absUrl;
        img.alt = '';
        img.onerror = function() { this.style.background='#fee'; this.alt='图片加载失败'; };
        img.style.cssText = imgCss;
        wrap.appendChild(img);
        wrap.appendChild(btn);
        box.appendChild(wrap);
    });
    updateCommentUploadBoxVisibility();
}
function updateCommentUploadBoxVisibility() {
    var label = document.getElementById('commentImageLabel');
    if (!label) return;
    label.style.display = commentImages.length >= MAX_COMMENT_IMAGES ? 'none' : 'inline-flex';
    // 文案：≥1 张后改为"再添加一张"，让用户清楚是「继续加」
    var span = label.querySelector('span');
    if (span) {
        var txt = (commentImages.length >= 1) ? span.getAttribute('data-text-more') : span.getAttribute('data-text-default');
        if (txt) span.textContent = txt;
    }
}
window.removeCommentImage = function(idx) {
    commentImages.splice(idx, 1);
    renderCommentImage();
};
// 引用状态：key=被回复评论/站队发言的 id，value=引用字段；提交回复时一并带上
var pendingQuotes = {};
// 引用「站队发言」时，引用目标走主评论框（顶层评论），用独立状态对象挂起，提交时随主评论一并落库
var pendingTopQuote = null;
// 辩论进行中引用「站队发言」→ 作为一条新站队发言发表（走 submitDebateComment），用独立状态对象挂起
var topDebateQuote = null;
function escapeHtml(s) {
    return String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}
// 引用别人观点：普通评论 → 打开主评论框，作为新的顶层楼层发表（带引用块，不进楼中楼，避免与「回复」逻辑重复）；站队发言 → 打开主评论框（顶层评论，因站队发言不可楼中楼）
window.quoteComment = function(anchorId, author, itemType) {
    var m = (anchorId || '').match(/^(comment|statement)-(\d+)$/);
    if (!m) return;
    var type = m[1], cid = m[2];
    var el = document.getElementById(anchorId);
    if (!el) return;
    // 站队发言：引用后作为顶层评论出现（刷新也持久化），不走嵌套楼中楼
    if (type === 'statement') {
        var cEl = el.querySelector('.comment-content');
        var text = cEl ? (cEl.innerText || cEl.textContent || '').trim() : '';
        text = text.replace(/\s+/g, ' ');
        if (text.length > 60) text = text.slice(0, 60) + '…';
        var clearHtml = '<span class="reply-quote-clear" style="float:right;cursor:pointer;color:#999;font-weight:bold;padding-left:8px;" title="取消引用">×</span>';
        // 辩论进行中 → 引用站队发言作为「一条新站队发言」发表（底色 + 正/反方标识）；辩论已截止 → 作为原生评论引用
        var debateAlive = !!(document.getElementById('debateSideInput') || document.querySelector('input[name="debate_side"]'));
        var quoteObj = {quote_id: cid, quote_type: 'statement', quote_author: author || '', quote_snippet: text};
        if (debateAlive) { topDebateQuote = quoteObj; } else { pendingTopQuote = quoteObj; }
        var pv = document.getElementById('topQuotePreview');
        if (pv) {
            pv.style.display = 'block';
            pv.innerHTML = clearHtml + '<span class="comment-quote-author">@' + escapeHtml(author || '') + '</span> <span class="comment-quote-text">' + escapeHtml(text) + '</span>';
            pv.onclick = function(e){ if (e.target && e.target.classList.contains('reply-quote-clear')) { topDebateQuote = null; pendingTopQuote = null; pv.style.display = 'none'; pv.innerHTML = ''; pv.onclick = null; } };
        }
        var input = document.getElementById('commentInput');
        if (input) { input.focus(); input.scrollIntoView({behavior:'smooth', block:'center'}); }
        return;
    }
    // 普通评论：引用后作为一条新的顶层评论楼层发表（带引用块），不再打开楼中楼回复框——
    //   否则「引用」与「回复」都会变成楼中楼嵌套，逻辑重复。引用块点击仍可定位到被引用评论。
    var cEl2 = el.querySelector('.comment-content');
    var text2 = cEl2 ? (cEl2.innerText || cEl2.textContent || '').trim() : '';
    text2 = text2.replace(/\s+/g, ' ');
    if (text2.length > 60) text2 = text2.slice(0, 60) + '…';
    var clearHtml2 = '<span class="reply-quote-clear" style="float:right;cursor:pointer;color:#999;font-weight:bold;padding-left:8px;" title="取消引用">×</span>';
    // 复用 pendingTopQuote（与站队发言引用同一条主评论框路径），提交时随主评论落库为顶层楼层
    pendingTopQuote = {quote_id: cid, quote_type: type, quote_author: author || '', quote_snippet: text2};
    var pv2 = document.getElementById('topQuotePreview');
    if (pv2) {
        pv2.style.display = 'block';
        pv2.innerHTML = clearHtml2 + '<span class="comment-quote-author">@' + escapeHtml(author || '') + '</span> <span class="comment-quote-text">' + escapeHtml(text2) + '</span>';
        pv2.onclick = function(e){ if (e.target && e.target.classList.contains('reply-quote-clear')) { pendingTopQuote = null; pv2.style.display = 'none'; pv2.innerHTML = ''; pv2.onclick = null; } };
    }
    var input2 = document.getElementById('commentInput');
    if (input2) { input2.focus(); input2.scrollIntoView({behavior:'smooth', block:'center'}); }
};
// 点击引用块：滚动并高亮被引用内容
window.locateQuote = function(anchorId) {
    var el = document.getElementById(anchorId);
    if (el) {
        el.scrollIntoView({behavior: 'smooth', block: 'center'});
        flashComment(el);
    }
};
window.submitReply = function(commentId, postId, parentType) {
    var input = document.getElementById('replyInput-' + commentId);
    var raw = input.value.trim();
    if (!raw) { toast('请输入回复内容', 'warning'); return; }
    // ✦ 楼中楼"回复对象"由 input 的 data-reply-target 持有（_comment.php 渲染时塞入）。
    //   placeholder 仅在用户未输入时显示，但提交后服务端实际存的是 "回复 {name} : 内容"
    //   格式——通知规则不变（仍按 @昵称 触发 mention，mention 解析走 content，跟前缀无关）。
    var replyTarget = (input.getAttribute('data-reply-target') || '').trim();
    var content = raw;
    if (replyTarget && raw.indexOf('回复 ' + replyTarget + ' :') !== 0 && raw.indexOf('@' + replyTarget) !== 0) {
        content = '回复 ' + replyTarget + ' : ' + raw;
    }
    var token = document.querySelector('input[name="_token"]') ? document.querySelector('input[name="_token"]').value : '';
    var mount = document.getElementById('captchaMountComment');
    var data = {content: content, parent_id: commentId, _token: token};
    // parent_type：回复普通评论 = 'comment'；回复站队发言 = 'statement'（parent_id 指向 topic_debate_statements）
    if (parentType) data.parent_type = parentType;
    // 引用：若本条回复是「引用别人观点」发起的，带上引用字段（被引用内容小字展示 + 点击定位）
    var pq = pendingQuotes[commentId];
    if (pq) {
        data.quote_id = pq.quote_id;
        data.quote_type = pq.quote_type;
        data.quote_author = pq.quote_author;
        data.quote_snippet = pq.quote_snippet;
        delete pendingQuotes[commentId];
        var pv = document.getElementById('replyQuote-' + commentId);
        if (pv) pv.style.display = 'none';
    }
    Captcha.ensure('comment', mount).then(function(sol) {
        if (sol) { data.captcha_token = sol.token; data.captcha_x = sol.x; }
        postJSON(url('post/comment', {id: postId}), data, function(res) {
            if (res.code === 0) {
                toast('回复已发送 ✓', 'success');
                if (res.data && res.data.points) showPointsToast(res.data.points);
                // 直接把服务端渲染好的 HTML 片段插入到对应顶级评论的 children 区，
                //   这样用户不必刷新就能看到自己刚发的楼中楼。
                // buildCommentTree() 把任意深度的回复都 flatten 到顶级评论的 children，
                //   所以定位锚点是 res.root_id（楼中楼或孙级都进同一个根）；root_id=0 表示
                //   这是一条全新的顶层回复，走 #commentList append。
                if (res && res.data && res.data.html) {
                    var tmp = document.createElement('div');
                    tmp.innerHTML = res.data.html;
                    var newNode = tmp.firstElementChild;
                    var inserted = false;
                    if (newNode && res.data.root_id) {
                        // 楼中楼：定位到顶级 root 的 .comment-children，把新节点 append 进去走分页显示。
                        //   root_type='statement' 时锚点为 statement-<root_id>（站队发言）；否则 comment-<root_id>。
                        var rootAnchor = (res.data.root_type === 'statement' ? 'statement-' : 'comment-') + res.data.root_id;
                        var rootEl = document.getElementById(rootAnchor);
                        if (rootEl) {
                            var body = rootEl.querySelector(':scope > .comment-body') || rootEl;
                            var childrenBox = body.querySelector(':scope > .comment-children');
                            if (!childrenBox) {
                                childrenBox = document.createElement('div');
                                childrenBox.className = 'comment-children';
                                childrenBox.style.cssText = 'margin-top:8px;';
                                childrenBox.dataset.initVisible = '<?= (int)$childInitVisible ?>';
                                childrenBox.dataset.perPage = '<?= (int)$childPerPage ?>';
                                var replyForm = body.querySelector('.comment-reply-form');
                                if (replyForm && replyForm.nextSibling) body.insertBefore(childrenBox, replyForm.nextSibling);
                                else body.appendChild(childrenBox);
                            }
                            // 插到「展开更多 / 翻页器」之前，保持 newNode 落在回复序列末尾（分页时归到最后一页）
                            var pagerEl = childrenBox.querySelector('.reply-pager');
                            var expandEl = childrenBox.querySelector('.reply-expand');
                            if (pagerEl) childrenBox.insertBefore(newNode, pagerEl);
                            else if (expandEl) childrenBox.insertBefore(newNode, expandEl);
                            else childrenBox.appendChild(newNode);
                            inserted = true;
                        }
                    }
                    // 顶层回复（root_id 为 0 或父元素缺失）：追加到评论列表尾部（极少走到，顶层评论已改整页跳转）
                    if (!inserted && newNode) {
                        var list = document.getElementById('commentList');
                        if (list) {
                            var emptyEl = list.querySelector('.empty-state');
                            if (emptyEl) emptyEl.remove();
                            list.appendChild(newNode);
                            inserted = true;
                        }
                    }
                    if (inserted) {
                        // 展开楼中楼分页并翻到新回复所在页（新回复 id 最大，必落在最后一页）
                        if (res.data && res.data.root_id) {
                            var rootAnchor2 = (res.data.root_type === 'statement' ? 'statement-' : 'comment-') + res.data.root_id;
                            var cbox = document.getElementById(rootAnchor2);
                            var box2 = cbox ? cbox.querySelector(':scope > .comment-body > .comment-children') : null;
                            if (box2) {
                                box2.dataset.expanded = '1';
                                var its = getReplyItems(res.data.root_id);
                                var iv = parseInt(box2.dataset.initVisible || '2', 10);
                                var pp = parseInt(box2.dataset.perPage || '5', 10);
                                box2._replyPage = Math.max(1, Math.ceil(Math.max(0, its.length - iv) / pp));
                                refreshReplies(res.data.root_id);
                            }
                        }
                        // 滚动到新回复并高亮
                        newNode.scrollIntoView({behavior: 'smooth', block: 'center'});
                        flashComment(newNode);
                        input.value = '';
                        toggleReply(commentId);
                    } else {
                        // 兜底：DOM 找不到目标位置（极小概率），整页 reload
                        setTimeout(function () { window.location.reload(); }, 400);
                    }
                } else {
                    // 服务端没吐 html（极小概率）。
                    setTimeout(function () { window.location.reload(); }, 400);
                }
            } else {
                toast(res.message || '回复失败', 'error');
            }
        });
    }).catch(function(msg) {
        toast(msg || '请完成滑块验证', 'error');
        if (mount) { mount.removeAttribute('data-solved'); mount._capPromise = null; }
    });
};
// ✦ 原 showReplyRefreshHint「点这里刷新」破提示已彻底删除（2026-08-27 用户反馈）：
//   楼中楼回复成功后，新评论已经渲染到评论树里了，绝不该再让用户点链接刷新。
//   现在 submitReply 的兜底退化为 location.reload()，与 submitComment 顶层评论的处理方式一致。

window.submitBid = function(postId) {
    var input = document.getElementById('bidInput');
    var price = input.value.trim();
    if (!price) { toast('请输入出价金额', 'warning'); return; }
    var btn = document.getElementById('bidBtn');
    btn.disabled = true; btn.textContent = '出价中...';
    var token = document.querySelector('input[name="_token"]') ? document.querySelector('input[name="_token"]').value : '';
    postJSON(url('post/bid', {id: postId}), {price: price, _token: token}, function(res) {
        if (res.code === 0) {
            toast('出价成功', 'success');
            setTimeout(function() { window.location.reload(); }, 600);
        } else {
            toast(res.message || '出价失败', 'error');
            btn.disabled = false; btn.textContent = '出价';
        }
    }, function(err) {
        toast(err && err.message ? err.message : '网络错误，请稍后重试', 'error');
        btn.disabled = false; btn.textContent = '出价';
    });
};
(function(){
    var timeEl = document.getElementById('bidCountdownTime');
    var wrap = document.getElementById('bidCountdown');
    if (!timeEl || !wrap) return;
    var end = parseInt(wrap.getAttribute('data-end'), 10) * 1000;
    if (!end) return;
    function pad(n){ return n < 10 ? '0'+n : ''+n; }
    function tick(){
        var diff = end - Date.now();
        if (diff <= 0) { timeEl.textContent = '已结束'; wrap.style.color = '#ea6f5a'; return; }
        var d = Math.floor(diff/86400000);
        var h = Math.floor(diff%86400000/3600000);
        var m = Math.floor(diff%3600000/60000);
        var s = Math.floor(diff%60000/1000);
        timeEl.textContent = (d>0? d+'天':'') + pad(h)+':'+pad(m)+':'+pad(s);
        setTimeout(tick, 1000);
    }
    tick();
})();
window.doPin = function(postId, scope) {
    var token = document.querySelector('input[name="_token"]') ? document.querySelector('input[name="_token"]').value : '';
    postJSON(url('post/pin', {id: postId}), {scope: scope, _token: token}, function(res) {
        toast(res.message, res.code === 0 ? 'success' : 'error');
        if (res.code === 0) setTimeout(function() { window.location.reload(); }, 600);
    });
};
window.doUnpin = function(postId) {
    var token = document.querySelector('input[name="_token"]') ? document.querySelector('input[name="_token"]').value : '';
    postJSON(url('post/unpin', {id: postId}), {_token: token}, function(res) {
        toast(res.message, res.code === 0 ? 'success' : 'error');
        if (res.code === 0) setTimeout(function() { window.location.reload(); }, 600);
    });
};
window.toggleEssence = function(postId, isEssence) {
    // 修复：当前已是精华(isEssence=1) → 调用 unessence 取消加精；否则 → essence 加精。
    // 原先无论当前状态都调 essence(val=1)，导致加精后无法取消。
    var token = document.querySelector('input[name="_token"]') ? document.querySelector('input[name="_token"]').value : '';
    var action = isEssence ? 'unessence' : 'essence';
    postJSON(url('post/' + action, {id: postId}), {_token: token}, function(res) {
        toast(res.message, res.code === 0 ? 'success' : 'error');
        if (res.code === 0) setTimeout(function() { window.location.reload(); }, 600);
    });
};
// 点击空白处收起置顶下拉
document.addEventListener('click', function(e) {
    document.querySelectorAll('.pin-dropdown.open').forEach(function(d) {
        if (!d.contains(e.target)) d.classList.remove('open');
    });
});
window.toggleClosePost = function(postId, isClosed) {
    var token = document.querySelector('input[name="_token"]') ? document.querySelector('input[name="_token"]').value : '';
    var action = isClosed ? 'openPost' : 'closePost';
    var msg = isClosed ? '确定要重新开启该帖子？' : '关闭后帖子将不能回复，确定关闭？';
    if (!confirm(msg)) return;
    postJSON(url('post/' + action, {id: postId}), {_token: token}, function(res) {
        toast(res.message, res.code === 0 ? 'success' : 'error');
        if (res.code === 0) setTimeout(function() { window.location.reload(); }, 600);
    });
};
window.movePost = function(postId) {
    var catId = document.getElementById('moveSelect').value;
    var token = document.querySelector('input[name="_token"]') ? document.querySelector('input[name="_token"]').value : '';
    postJSON(url('post/move', {id: postId}), {category_id: catId, _token: token}, function(res) {
        toast(res.message, res.code === 0 ? 'success' : 'error');
        if (res.code === 0) setTimeout(function() { window.location.reload(); }, 600);
    });
};

// ===== 正文字号滑杆：拖动滑杆改 .post-content 字号，localStorage 持久化 =====
// 滑杆放在帖子信息（.post-info）下方，作为独立一行
(function() {
    var slider = document.getElementById('postContentFontSizeSlider');
    var valueEl = document.getElementById('postContentFontSizeValue');
    var content = document.querySelector('.post-content');
    if (!slider || !valueEl || !content) return;

    var STORAGE_KEY = 'post_content_fontsize';
    var MIN = parseInt(slider.getAttribute('min'), 10) || 15;
    var MAX = parseInt(slider.getAttribute('max'), 10) || 24;

    function clamp(n) {
        n = parseInt(n, 10);
        if (isNaN(n)) return MIN;
        return Math.max(MIN, Math.min(MAX, n));
    }

    function apply(size) {
        size = clamp(size);
        content.style.fontSize = size + 'px';
        slider.value = String(size);
        valueEl.textContent = size + 'px';
        try { localStorage.setItem(STORAGE_KEY, String(size)); } catch (e) {}
    }

    // 初始化：先读 localStorage，再 fallback 到滑杆 value
    var saved = MIN;
    try { saved = clamp(localStorage.getItem(STORAGE_KEY)); } catch (e) {}
    apply(saved);

    // 拖动时实时刷新；change 事件在拖完后触发（含键盘调整）
    slider.addEventListener('input', function() { apply(slider.value); });
    slider.addEventListener('change', function() { apply(slider.value); });
})();

// ===== 打赏 / 自助置顶 弹窗逻辑 =====
// 复用全局 .modal-overlay / .modal 样式；弹窗内交互全部走 postJSON 调用 PostController::reward / selfPin。
var _rewardPostId = 0, _rewardCommentId = 0;
var _selfPinPostId = 0, _selfPinHours = 1;

window.openReward = function(postId, commentId) {
        _rewardPostId = postId;
        _rewardCommentId = parseInt(commentId || 0, 10);
        var amt = document.getElementById('rewardAmount');
        // 弹窗默认金额=后台「打赏配置→默认打赏金额」注入的 #rewardDefaultValue，读不到时回退 5
        // 不再硬写 5：之前这里写死了 5，导致后台改默认金额前台永远不生效（2026-08-28 用户反馈）。
        var def = parseInt((document.getElementById('rewardDefaultValue') || {}).value, 10);
        amt.value = (def && def >= 5) ? def : 5;
        document.querySelectorAll('#rewardModal .preset-btn').forEach(function(b){ b.classList.remove('active'); });
        syncRewardPreset();
        openPointsModal('rewardModal');
    };
window.setRewardAmount = function(v, btn) {
    document.getElementById('rewardAmount').value = v;
    document.querySelectorAll('#rewardModal .preset-btn').forEach(function(b){ b.classList.remove('active'); });
    if (btn) btn.classList.add('active');
    syncRewardPreset();
};
    var tokenLabel = <?= json_encode($tokenLabel ?? \PointService::currencyLabel('token'), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
window.syncRewardPreset = function() {
    var v = parseInt(document.getElementById('rewardAmount').value, 10) || 0;
    var bal = parseInt((document.getElementById('rewardBalance').textContent || '').replace(/[^\d]/g, ''), 10) || 0;
    var hint = document.getElementById('rewardHint');
    var btn = document.getElementById('rewardConfirmBtn');
    if (v < 5) { hint.textContent = '打赏金额不得低于 5 ' + tokenLabel; btn.disabled = true; return; }
    if (v > bal) { hint.textContent = '余额不足（需 ' + v + '，现有 ' + bal + '）'; btn.disabled = true; return; }
    hint.textContent = '将扣除 ' + v + ' ' + tokenLabel + '，受赏者等额入账';
    btn.disabled = false;
};
window.submitReward = function() {
    var amount = parseInt(document.getElementById('rewardAmount').value, 10) || 0;
    var token = document.querySelector('input[name="_token"]') ? document.querySelector('input[name="_token"]').value : '';
    var btn = document.getElementById('rewardConfirmBtn');
    btn.disabled = true; btn.textContent = '打赏中...';
    postJSON(url('post/reward'), {post_id: _rewardPostId, comment_id: _rewardCommentId, amount: amount, _token: token}, function(res) {
        if (res.code === 0) {
            toast(res.message || '打赏成功', 'success');
            closePointsModal('rewardModal');
            setTimeout(function(){ window.location.reload(); }, 600);
        } else {
            toast(res.message || '打赏失败', 'error');
            btn.disabled = false; btn.textContent = '确认打赏';
        }
    }, function(err) {
        toast(err && err.message ? err.message : '网络错误，请稍后重试', 'error');
        btn.disabled = false; btn.textContent = '确认打赏';
    });
};

window.openSelfPin = function(postId) {
    _selfPinPostId = postId;
    setSelfPinHours(1, document.querySelector('#selfPinModal .preset-btn[data-hours="1"]'));
    openPointsModal('selfPinModal');
};
window.setSelfPinHours = function(h, btn) {
    _selfPinHours = h;
    document.querySelectorAll('#selfPinModal .preset-btn').forEach(function(b){ b.classList.remove('active'); });
    if (btn) btn.classList.add('active');
    // 费率兜底 1000 Token/小时，与 PointService::selfPin 一致
    var rate = parseInt('<?= (int)($selfPinRate ?? 1000) ?>', 10) || 1000;
    var cost = rate * h;
    var bal = parseInt((document.getElementById('selfPinBalance').textContent || '').replace(/[^\d]/g, ''), 10) || 0;
    var hint = document.getElementById('selfPinHint');
    var cbtn = document.getElementById('selfPinConfirmBtn');
    if (cost > bal) { hint.textContent = '需 ' + cost + ' ' + tokenLabel + '，余额不足'; cbtn.disabled = true; }
    else { hint.textContent = '将扣除 ' + cost + ' ' + tokenLabel + '，置顶至 ' + h + ' 小时后'; cbtn.disabled = false; }
};
window.submitSelfPin = function() {
    var token = document.querySelector('input[name="_token"]') ? document.querySelector('input[name="_token"]').value : '';
    var btn = document.getElementById('selfPinConfirmBtn');
    btn.disabled = true; btn.textContent = '置顶中...';
    postJSON(url('post/selfPin'), {post_id: _selfPinPostId, hours: _selfPinHours, _token: token}, function(res) {
        if (res.code === 0) {
            toast(res.message || '置顶成功', 'success');
            closePointsModal('selfPinModal');
            setTimeout(function(){ window.location.reload(); }, 600);
        } else {
            toast(res.message || '置顶失败', 'error');
            btn.disabled = false; btn.textContent = '确认置顶';
        }
    }, function(err) {
        toast(err && err.message ? err.message : '网络错误，请稍后重试', 'error');
        btn.disabled = false; btn.textContent = '确认置顶';
    });
};

window.openPointsModal = function(id) {
    var m = document.getElementById(id);
    if (m) { m.classList.add('active'); document.body.style.overflow = 'hidden'; }
};
window.closePointsModal = function(id) {
    var m = document.getElementById(id);
    if (m) { m.classList.remove('active'); document.body.style.overflow = ''; }
};
</script>
