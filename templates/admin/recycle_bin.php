<?php /** 回收站 */ $title = '回收站'; ?>
<div class="card" style="margin-bottom:16px;">
    <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div>
            <strong>回收站</strong>
            <span class="text-muted" style="font-size:13px;">被删除的帖子 / 评论（含楼中楼）将在此保留 7 天，到期后自动清空。</span>
        </div>
        <?php if (is_high_admin()): ?>
        <button type="button" class="btn btn-danger" onclick="emptyTrash()">清空回收站</button>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-bottom:16px;">
    <div class="card-header">帖子回收站（<?= $postTotal ?>）</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width:60px;">ID</th>
                <th>标题</th>
                <th style="width:120px;">作者</th>
                <th style="width:100px;">板块</th>
                <th style="width:150px;">删除时间</th>
                <th style="width:120px;">操作人</th>
                <th style="width:80px;">剩余</th>
                <th style="width:160px;">操作</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($posts)): ?>
            <tr><td colspan="8" class="text-muted" style="text-align:center;padding:18px;">暂无已删除的帖子</td></tr>
        <?php else: ?>
        <?php foreach ($posts as $p):
            $left = ceil((strtotime($p['deleted_at']) + 7*86400 - time())/86400);
            // 操作人：deleted_by 可能是 admin_uid 或者作者自己
            if (!empty($p['deleted_by']) && !empty($p['operator_name'])) {
                $opDisplay = e($p['operator_name']) . ' <span class="text-muted" style="font-size:11px;">uid:' . (int)$p['deleted_by'] . '</span>';
            } elseif (!empty($p['deleted_by']) && empty($p['operator_name'])) {
                // 软删用户（status=0）拿不到昵称但还能看 username
                $opDisplay = '<span class="text-muted">已注销 uid:' . (int)$p['deleted_by'] . '</span>';
            } else {
                $opDisplay = '<span class="text-muted" style="font-size:12px;">系统自动</span>';
            }
        ?>
        <tr>
            <td><?= $p['id'] ?></td>
            <td><a href="<?= url('post/show', ['id' => $p['id']]) ?>" target="_blank"><?= e(truncate($p['title'], 30)) ?></a></td>
            <td><?= e($p['author_name'] ?? '-') ?></td>
            <td><?= e($p['category_name'] ?? '-') ?></td>
            <td><?= $p['deleted_at'] ?></td>
            <td><?= $opDisplay ?></td>
            <td><?= max(0, $left) ?> 天</td>
            <td class="table-actions">
                <?php if (is_high_admin()): ?>
                <a href="javascript:restorePost(<?= $p['id'] ?>)">恢复</a>
                <a href="javascript:purgePost(<?= $p['id'] ?>)" style="color:#f5222d;">彻底删除</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    <?= $renderPager($postTotal, $postPage, 'post_page') ?>
</div>

<div class="card">
    <div class="card-header">评论回收站（<?= $commentTotal ?>）</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width:60px;">ID</th>
                <th>内容</th>
                <th style="width:120px;">评论者</th>
                <th style="width:160px;">所属帖子</th>
                <th style="width:150px;">删除时间</th>
                <th style="width:120px;">操作人</th>
                <th style="width:80px;">剩余</th>
                <th style="width:160px;">操作</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($comments)): ?>
            <tr><td colspan="8" class="text-muted" style="text-align:center;padding:18px;">暂无已删除的评论</td></tr>
        <?php else: ?>
        <?php foreach ($comments as $c):
            $left = ceil((strtotime($c['deleted_at']) + 7*86400 - time())/86400);
            if (!empty($c['deleted_by']) && !empty($c['operator_name'])) {
                $opDisplay = e($c['operator_name']) . ' <span class="text-muted" style="font-size:11px;">uid:' . (int)$c['deleted_by'] . '</span>';
            } elseif (!empty($c['deleted_by']) && empty($c['operator_name'])) {
                $opDisplay = '<span class="text-muted">已注销 uid:' . (int)$c['deleted_by'] . '</span>';
            } else {
                $opDisplay = '<span class="text-muted" style="font-size:12px;">系统自动</span>';
            }
        ?>
        <tr>
            <td><?= $c['id'] ?></td>
            <td style="max-width:300px;"><?= e(truncate($c['content'], 50)) ?></td>
            <td><?= e($c['author_name'] ?? '-') ?></td>
            <td>
                <?php if (!empty($c['post_id'])): ?>
                <a href="<?= url('post/show', ['id' => $c['post_id']]) ?>#comment-<?= $c['id'] ?>" target="_blank"><?= e(truncate($c['post_title'] ?? '', 20)) ?></a>
                <?php else: ?>-<?php endif; ?>
            </td>
            <td><?= $c['deleted_at'] ?></td>
            <td><?= $opDisplay ?></td>
            <td><?= max(0, $left) ?> 天</td>
            <td class="table-actions">
                <?php if (is_high_admin()): ?>
                <a href="javascript:restoreComment(<?= $c['id'] ?>)">恢复</a>
                <a href="javascript:purgeComment(<?= $c['id'] ?>)" style="color:#f5222d;">彻底删除</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
    <?= $renderPager($commentTotal, $commentPage, 'comment_page') ?>
</div>
<script>
function restorePost(id){ postJSON(url('admin/restorePost',{id:id}),{_token:getCsrfToken()},function(res){ toast(res.message,res.code===0?'success':'error'); if(res.code===0) setTimeout(function(){location.reload();},800); }); }
function purgePost(id){ confirmModal('彻底删除','确定永久删除该帖子？此操作不可恢复。',function(){ postJSON(url('admin/purgePost',{id:id}),{_token:getCsrfToken()},function(res){ toast(res.message,res.code===0?'success':'error'); if(res.code===0) setTimeout(function(){location.reload();},800); }); }); }
function restoreComment(id){ postJSON(url('admin/restoreComment',{id:id}),{_token:getCsrfToken()},function(res){ toast(res.message,res.code===0?'success':'error'); if(res.code===0) setTimeout(function(){location.reload();},800); }); }
function purgeComment(id){ confirmModal('彻底删除','确定永久删除该评论？此操作不可恢复。',function(){ postJSON(url('admin/purgeComment',{id:id}),{_token:getCsrfToken()},function(res){ toast(res.message,res.code===0?'success':'error'); if(res.code===0) setTimeout(function(){location.reload();},800); }); }); }
function emptyTrash(){ confirmModal('清空回收站','确定清空所有已删除的帖子与评论？超过保留期的将被永久删除，此操作不可恢复。',function(){ postJSON(url('admin/emptyTrash'),{_token:getCsrfToken()},function(res){ toast(res.message,res.code===0?'success':'error'); if(res.code===0) setTimeout(function(){location.reload();},800); }); }); }
</script>
