<?php /** 评论项 partial（支持递归嵌套） */ ?>
<?php /** @var array $comment */ ?>
<?php
// ===== 悬赏主题上下文（2026-08-31 新增）=====
// $bounty_ctx 由 show.php 调用 View::partial 时注入，用于决定：
//   1. 评论是否带底色/「回答」/「已采纳」标识
//   2. 是否显示「采纳此回答」按钮（仅楼主 + 悬赏期 + 未采纳）
//   3. 悬赏期是否隐藏「回复」入口（楼中楼硬禁）
$isBountyAns = !empty($comment['is_bounty_answer']) && (int)$comment['is_bounty_answer'] === 1;
$acceptRank = (isset($comment['accept_rank']) && $comment['accept_rank'] !== null && $comment['accept_rank'] !== '') ? (int)$comment['accept_rank'] : 0;
$hasAccepted = $acceptRank > 0;
$isBountyTheme = !empty($bounty_ctx) && !empty($bounty_ctx['is_bounty_theme']);
$bountyStatus = $isBountyTheme ? (int)($bounty_ctx['bounty_status'] ?? 0) : 0;
$bountyInProgress = $isBountyTheme && $bountyStatus === 1;  // 悬赏期
$isBountyAuthor = $isBountyTheme && !empty($bounty_ctx['is_author']);
// 悬赏期不允许楼中楼（即便登记者本人看到自己的回答），UI 端隐藏「回复」入口
$allowReply = is_logged_in() && empty($post['is_closed']) && !is_banned() && !$bountyInProgress;
// ===== 辩论主题上下文（2026-08-31 新增）=====
// $debate_side 由 show.php 注入；0/null = 普通评论，非0 = 站队发言（1正方 2反方）。
//   站队发言不带楼中楼、不显示「回复」/举报/删除以外的横向操作（与悬赏「回答」一致作为顶层独立发言单元）；
//   视觉上套 .comment-item--debate-pro / .comment-item--debate-con 底色 + 头部「正方 / 反方」标签（参考悬赏主题「回答」标识）。
$debateSide = isset($debate_side) ? (int)$debate_side : 0;
$debatePro = $debateSide === 1;
$debateCon = $debateSide === 2;
$itemType = isset($comment['__type']) ? $comment['__type'] : 'comment';
// 辩论站队发言：不可楼中楼（无回复框），仅支持「引用」（引用后作为顶层评论出现在评论区）。
//   2026-08-31 改造曾开放楼中楼，现撤销：站队发言是独立发言单元，不应有嵌套回复。
if ($itemType === 'debate') {
    $allowReply = false;
}
$extraClass = '';
if ($isBountyAns) $extraClass .= ' comment-item--bounty-answer';
if ($hasAccepted) $extraClass .= ' comment-item--bounty-accepted';
if ($debatePro) $extraClass .= ' comment-item--debate-pro';
if ($debateCon) $extraClass .= ' comment-item--debate-con';
// 楼主在悬赏期看到「采纳此回答」的隐藏式 JS 入口（仅未采纳的回答）
$showAcceptBtn = $isBountyAns && !$hasAccepted && empty($isChild) && $bountyInProgress && $isBountyAuthor;
// 锚点 id：普通评论 = comment-<id>；辩论站队发言（来自 topic_debate_statements） = statement-<id>，
// 两者命名空间不同，避免 id 撞车；引用跳转 / 回复挂载都依赖这个锚点。
$anchorId = ($itemType === 'debate') ? 'statement-' . (int)$comment['id'] : 'comment-' . (int)$comment['id'];
?>
<div class="comment-item<?= $extraClass ?>" id="<?= $anchorId ?>"
     data-is-bounty-answer="<?= $isBountyAns ? 1 : 0 ?>"
     data-accepted-rank="<?= $acceptRank ?>"
     style="<?= !empty($isChild) ? 'border-bottom:none;padding:12px 0;' : '' ?>">
<?php
        // ✦ 评论作者名显示规则（2026-08-26 修正版）：
        //   1. 用户已被删除（status=0）→ 「已注销用户」
        //   2. 填了昵称（nickname 非空）→ 显示昵称
        //   3. 未填昵称 → 显示用户名
        //   ⚠️ 之前错误地把"未填昵称"降级为"已注销用户"——会让正常注册但没起昵称的用户
        //       被误判为游客，与实际意图不符。
        $isDeleted = isset($comment['status']) && (int)$comment['status'] === 0;
        $nick = trim((string)($comment['nickname'] ?? ''));
        $user = trim((string)($comment['username'] ?? ''));
        if ($isDeleted || ($nick === '' && $user === '')) {
            $displayName = '已注销用户';
        } elseif ($nick !== '') {
            $displayName = $nick;
        } else {
            $displayName = $user;
        }
        $avatarFallback = mb_substr($displayName, 0, 1, 'UTF-8');
        ?>
        <div class="comment-avatar">
            <?php if (!empty($comment['user_id'])): ?><a href="<?= url('user/profile', ['id' => $comment['user_id']]) ?>" data-uid="<?= $comment['user_id'] ?>" style="display:block;width:100%;height:100%;"><?php endif; ?>
            <?php if (!empty($comment['avatar'])): ?>
            <img src="<?= upload_url($comment['avatar']) ?>" alt="">
            <?php else: ?>
            <?= e($avatarFallback ?: '匿') ?>
            <?php endif; ?>
            <?php if (!empty($comment['user_id'])): ?></a><?php endif; ?>
        </div>
        <div class="comment-body">
            <div class="comment-meta">
                <?php if (!empty($comment['user_id'])): ?><a href="<?= url('user/profile', ['id' => $comment['user_id']]) ?>" data-uid="<?= $comment['user_id'] ?>" style="color:inherit;text-decoration:none;"><?php endif; ?>
            <?php
            // 帖子回复区（含楼中楼）一律只显示「实名认证」徽章（group_id=1），
            // 其它认证组不渲染——这样 PC 与移动端回复区统一简化；
            // 帖子作者栏 / 侧栏用户卡片等非回复区仍走 cert_badge_html($user, $size)（按勾选）。
            $certBadge = cert_badge_html($comment, 13, 1);
            // 楼主判定：评论作者 == 帖子作者。该标识在顶层回复与楼中楼同样显示。
            $isTopicOwner = !empty($comment['user_id']) && !empty($post['user_id'])
                && (int)$comment['user_id'] === (int)$post['user_id'];
            ?>
            <span class="name"><?= e($displayName) ?><?= $certBadge ?><?php if ($isTopicOwner): ?><span class="badge-topic-owner">楼主</span><?php endif; ?></span>
            <?php if (!empty($comment['user_id'])): ?></a><?php endif; ?>
            <?php if ($isBountyAns && empty($isChild)): ?><span class="badge-bounty-answer" title="悬赏主题下的回答">回答</span><?php endif; ?>
            <?php if ($hasAccepted && empty($isChild)): ?><span class="badge-bounty-accepted" title="采纳次序 #<?= $acceptRank ?>">✓ 已采纳 #<?= $acceptRank ?></span><?php endif; ?>
            <?php if ($debatePro && empty($isChild)): ?><span class="badge-debate-side pro" title="辩论主题下的正方站队发言">正方</span><?php endif; ?>
            <?php if ($debateCon && empty($isChild)): ?><span class="badge-debate-side con" title="辩论主题下的反方站队发言">反方</span><?php endif; ?>
            <?php if (isset($floor) && !empty($floor) && empty($isChild)): ?><span>#<?= (int)$floor ?></span><?php endif; ?>
            <span><?= time_ago($comment['created_at']) ?></span>
        </div>
        <?php // 引用块：被引用内容小字展示，点击定位到被引用锚点（comment-<id> 或 statement-<id>）?>
        <?php if (!empty($comment['quote_id'])):
            $qAnchor = ($comment['quote_type'] === 'statement' ? 'statement-' : 'comment-') . (int)$comment['quote_id'];
        ?>
        <div class="comment-quote" data-locate="<?= $qAnchor ?>" onclick="locateQuote('<?= $qAnchor ?>')" title="点击定位到被引用内容">
            <span class="comment-quote-author">@<?= e($comment['quote_author'] ?? '') ?></span>
            <span class="comment-quote-text"><?= e($comment['quote_snippet'] ?? '') ?></span>
        </div>
        <?php endif; ?>
        <div class="comment-content"><?= render_emoji(render_mentions($comment['content'])) ?></div>
        <?php
        // 多图渲染：1 张原比例；多张方形画廊最多显示 3 张，第 3 张 +* 蒙版
        $commentImgs = parse_comment_images($comment['image'] ?? null);
        if (!empty($commentImgs)):
            $total = count($commentImgs);
            $show  = min($total, 3);
            $hidden = ($total > 3) ? ($total - 3) : 0; // 第 3 张蒙版上写「剩余张数」= total - 已显示 3 张
        ?>
        <div class="comment-gallery">
            <?php for ($gi = 0; $gi < $show; $gi++):
                $isCover = ($gi === 2 && $total > 3); // 第 3 张且总数 >3：加蒙版
                $absUrl = upload_url($commentImgs[$gi]);
            ?>
            <div class="cg-item" data-img-index="<?= $gi ?>" data-img-group="<?= md5('comment-img-' . $comment['id']) ?>"
                 onclick="openCommentLightbox(this.dataset.imgGroup, parseInt(this.dataset.imgIndex, 10))">
                <img src="<?= $absUrl ?>" alt="" loading="lazy">
                <?php if ($isCover): ?>
                <div class="cg-mask">+<?= $hidden ?></div>
                <?php endif; ?>
            </div>
            <?php endfor; ?>
            <script type="application/json" data-img-group="<?= md5('comment-img-' . $comment['id']) ?>">
            <?= json_encode(array_map(function($p){ return upload_url($p); }, $commentImgs), JSON_UNESCAPED_SLASHES) ?>
            </script>
        </div>
        <?php endif; ?>
        <?php if ($showAcceptBtn): /* 楼主看到的「采纳此回答」按钮 —— 放在内容底部 + actions 之前 */ ?>
        <div class="bounty-accept-row" style="margin-top:8px;">
            <button class="btn btn-primary btn-sm" onclick="bountyAccept(<?= $post['id'] ?>, <?= (int)$comment['id'] ?>)">采纳此回答</button>
        </div>
        <?php endif; ?>
        <div class="comment-actions">
            <?php if ($allowReply): ?>
            <a href="javascript:toggleReply(<?= $comment['id'] ?>)">回复</a>
            <?php endif; ?>
            <?php // 引用：普通评论 → 楼中楼回复框；站队发言 → 主评论框（顶层评论，因站队发言不可楼中楼）?>
            <?php if (is_logged_in()): ?>
            <a href="javascript:quoteComment('<?= $anchorId ?>', '<?= e($displayName) ?>', '<?= $itemType ?>')">引用</a>
            <?php endif; ?>
            <?php // 站队发言来自 topic_debate_statements，不是 comments 表，删除/举报走评论接口会错位，故评论区不暴露这两个操作 ?>
            <?php if ($itemType !== 'debate' && is_logged_in()): ?>
            <a href="javascript:reportItem('comment', <?= $comment['id'] ?>)">举报</a>
            <?php if ($comment['user_id'] == Auth::id() || !empty($isModerator)): ?>
            <a href="javascript:deleteComment(<?= $comment['id'] ?>, document.getElementById('<?= $anchorId ?>'))">删除</a>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php if ($allowReply): ?>
        <?php
        // ✦ 楼中楼"回复目标"标识仅作 placeholder，不再显示独立可见 label（避免占用视觉）。
        //   提交时由 show.php::submitReply 把 "回复 {name} : " 前缀拼到 content 前面，
        //   因此服务端实际存的内容形如 "回复 admin : 内容"，通知规则不变（仍按 @昵称 触发 mention）。
        $replyTarget = $isDeleted ? '已注销用户' : (($nick !== '') ? $nick : $user);
        // 回复目标类型：站队发言 -> 'statement'（parent_id 指向 topic_debate_statements）；普通评论 -> 'comment'
        $replyParentType = ($itemType === 'debate') ? 'statement' : 'comment';
        ?>
        <div class="comment-reply-form" id="replyForm-<?= $comment['id'] ?>">
            <div class="reply-quote-preview" id="replyQuote-<?= $comment['id'] ?>" style="display:none;"></div>
            <input type="text" class="form-control" id="replyInput-<?= $comment['id'] ?>" data-mention="1" data-reply-target="<?= e($replyTarget) ?>" placeholder="回复 <?= e($replyTarget) ?>：" style="margin-top:6px;">
            <div style="margin-top:6px;display:flex;justify-content:space-between;align-items:center;">
                <button type="button" class="emoji-trigger" data-emoji-trigger data-emoji-target="replyInput-<?= $comment['id'] ?>" title="插入表情（:code: 短代码，提交后自动渲染）"><i class="fa-regular fa-face-smile" aria-hidden="true"></i> 表情</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="submitReply(<?= $comment['id'] ?>, <?= $post['id'] ?>, '<?= $replyParentType ?>')">回复</button>
            </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($comment['children']) && empty($isChild)):
            // 楼中楼分页模型（2026-08-27 重构）：
            //   - 全部楼中楼作为 .comment-children 的直接子节点渲染（同一维数组，isChild=true 不再递归子块）；
            //   - 默认只显示前 REPLY_INIT_VISIBLE 条（内置 2），其余由前端 JS 折叠/分页（每页 REPLY_PER_PAGE=5）。
            //   - 这样「发表新楼中楼」时只要把节点 append 进 .comment-children 再刷新分页即可，插入逻辑极简。
            $children = $comment['children'];
            $initVisible = (int)($childInitVisible ?? REPLY_INIT_VISIBLE);   // 默认 2
            $replyPerPage = (int)($childPerPage ?? REPLY_PER_PAGE);          // 默认 5
            $total = count($children);
        ?>
        <div class="comment-children"
             data-init-visible="<?= $initVisible ?>" data-per-page="<?= $replyPerPage ?>">
            <?php foreach ($children as $child): ?>
                <?php View::partial('post/_comment', ['comment' => $child, 'post' => $post, 'isModerator' => $isModerator, 'isChild' => true, 'childInitVisible' => $childInitVisible, 'childPerPage' => $childPerPage, 'bounty_ctx' => $bounty_ctx]); ?>
            <?php endforeach; ?>
            <?php if ($total > $initVisible): ?>
            <div class="reply-expand" id="replyExpand-<?= $comment['id'] ?>">
                <a href="javascript:;" class="more-link" onclick="expandReplies(<?= $comment['id'] ?>)">展开更多 <?= ($total - $initVisible) ?> 条回复</a>
            </div>
            <div class="reply-pager" id="replyPager-<?= $comment['id'] ?>" style="display:none;"></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
