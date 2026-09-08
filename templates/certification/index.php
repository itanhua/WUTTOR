<?php /** 认证中心 - 跟随 .content-main 全宽（参考编辑资料页面） */ $title = '认证中心'; $hideSidebar = true; ?>
<?php
$currentGroupName = '';
foreach ($groups as $g) if ((int)$g['id'] === (int)$currentGroupId) $currentGroupName = $g['name'];
$hasMultiple = count($groups) > 1;
?>

<?php if ($hasMultiple): ?>
<!-- 认证项目切换菜单（多个项目时充当卡片头部，整体作为一张 14px 大圆角卡） -->
<div class="card tab-merged-card" style="padding:0;overflow:hidden;margin-bottom:16px;">
    <div class="switch-tabs" style="border:0;border-bottom:1px solid #f0f0f0;">
        <?php foreach ($groups as $g):
            $isActive = (int)$g['id'] === (int)$currentGroupId; ?>
        <a href="<?= url('certification/index', ['group_id' => $g['id']]) ?>" class="<?= $isActive ? 'active' : '' ?>"><?= e($g['name']) ?></a>
        <?php endforeach; ?>
        <span class="switch-tabs-spacer"></span>
        <span class="switch-tabs-meta">共 <?= count($groups) ?> 个项目</span>
    </div>
    <div class="card-body">
        <?php if ($status === 'approved'): ?>
        <?php
            $reviewedAt = '';
            if (!empty($cert['reviewed_at'])) {
                $reviewedAt = $cert['reviewed_at'];
            } elseif (!empty($user['certified_at'])) {
                $reviewedAt = $user['certified_at'];
            }
        ?>
        <div class="cert-status-card approved">
            <div class="icon-text">已认证</div>
            <h3>恭喜，您已通过<?= e($currentGroupName ?: '实名') ?>认证</h3>
            <p>您将获得认证专属标识，并可访问认证专属版块</p>
            <p class="text-muted" style="font-size:12px;margin-top:8px;white-space:nowrap;">
                认证时间：<?= $reviewedAt ?: '未知' ?>
            </p>
            <a href="<?= url('home/index', ['cat' => 'certified']) ?>" class="btn btn-primary">进入认证专区</a>
        </div>

        <?php elseif ($status === 'pending'): ?>
        <div class="cert-status-card pending">
            <div class="icon-text">审核中</div>
            <h3><?= e($currentGroupName ?: '实名') ?>认证申请审核中</h3>
            <p>您的申请已提交，管理员正在审核，请耐心等待</p>
            <p class="text-muted" style="font-size:12px;margin-top:8px;">提交时间：<?= $cert['created_at'] ?? '' ?></p>
        </div>

        <?php elseif ($status === 'rejected'): ?>
        <div class="cert-status-card rejected">
            <div class="icon-text">已驳回</div>
            <h3><?= e($currentGroupName ?: '实名') ?>认证申请未通过</h3>
            <p>驳回原因：<?= e($cert['reject_reason'] ?? '未提供') ?></p>
            <p class="text-muted" style="font-size:12px;margin-top:8px;">审核时间：<?= $cert['reviewed_at'] ?? '' ?></p>
            <button class="btn btn-primary" onclick="document.getElementById('applyForm').style.display='block';this.style.display='none';">重新申请</button>
        </div>
        <div id="applyForm" style="<?= $status === 'rejected' ? 'display:none;' : '' ?>margin-top:24px;">
            <?php View::partial('certification/_form', ['cert' => $cert ?? null, 'group_id' => $currentGroupId, 'certSubmitCost' => $certSubmitCost ?? null]); ?>
        </div>

        <?php else: ?>
        <div class="cert-status-card none">
            <div class="icon-text">未认证</div>
            <h3>完成<?= e($currentGroupName ?: '实名') ?>认证，解锁更多权益</h3>
            <p>认证后可获得专属标识，访问认证专属版块</p>
        </div>
        <div style="margin-top:24px;">
            <?php View::partial('certification/_form', ['cert' => $cert ?? null, 'group_id' => $currentGroupId, 'certSubmitCost' => $certSubmitCost ?? null]); ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
<!-- 单项目认证：保持原"实名认证中心"标题头 + 认证内容的标准卡片样式（14px 全圆角） -->
<div class="card">
    <div class="card-header"><?= e($currentGroupName ?: '实名') ?>认证中心</div>
    <div class="card-body">
        <?php if ($status === 'approved'): ?>
        <?php
            $reviewedAt = '';
            if (!empty($cert['reviewed_at'])) {
                $reviewedAt = $cert['reviewed_at'];
            } elseif (!empty($user['certified_at'])) {
                $reviewedAt = $user['certified_at'];
            }
        ?>
        <div class="cert-status-card approved">
            <div class="icon-text">已认证</div>
            <h3>恭喜，您已通过<?= e($currentGroupName ?: '实名') ?>认证</h3>
            <p>您将获得认证专属标识，并可访问认证专属版块</p>
            <p class="text-muted" style="font-size:12px;margin-top:8px;white-space:nowrap;">
                认证时间：<?= $reviewedAt ?: '未知' ?>
            </p>
            <a href="<?= url('home/index', ['cat' => 'certified']) ?>" class="btn btn-primary">进入认证专区</a>
        </div>

        <?php elseif ($status === 'pending'): ?>
        <div class="cert-status-card pending">
            <div class="icon-text">审核中</div>
            <h3><?= e($currentGroupName ?: '实名') ?>认证申请审核中</h3>
            <p>您的申请已提交，管理员正在审核，请耐心等待</p>
            <p class="text-muted" style="font-size:12px;margin-top:8px;">提交时间：<?= $cert['created_at'] ?? '' ?></p>
        </div>

        <?php elseif ($status === 'rejected'): ?>
        <div class="cert-status-card rejected">
            <div class="icon-text">已驳回</div>
            <h3><?= e($currentGroupName ?: '实名') ?>认证申请未通过</h3>
            <p>驳回原因：<?= e($cert['reject_reason'] ?? '未提供') ?></p>
            <p class="text-muted" style="font-size:12px;margin-top:8px;">审核时间：<?= $cert['reviewed_at'] ?? '' ?></p>
            <button class="btn btn-primary" onclick="document.getElementById('applyForm').style.display='block';this.style.display='none';">重新申请</button>
        </div>
        <div id="applyForm" style="<?= $status === 'rejected' ? 'display:none;' : '' ?>margin-top:24px;">
            <?php View::partial('certification/_form', ['cert' => $cert ?? null, 'group_id' => $currentGroupId, 'certSubmitCost' => $certSubmitCost ?? null]); ?>
        </div>

        <?php else: ?>
        <div class="cert-status-card none">
            <div class="icon-text">未认证</div>
            <h3>完成<?= e($currentGroupName ?: '实名') ?>认证，解锁更多权益</h3>
            <p>认证后可获得专属标识，访问认证专属版块</p>
        </div>
        <div style="margin-top:24px;">
            <?php View::partial('certification/_form', ['cert' => $cert ?? null, 'group_id' => $currentGroupId, 'certSubmitCost' => $certSubmitCost ?? null]); ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">认证说明</div>
    <div class="card-body" style="font-size:13px;color:#666;line-height:1.9;">
        <p>1. <?= e($currentGroupName ?: '实名') ?>认证需提交真实信息。</p>
        <p>2. 敏感信息（如身份证号）将加密存储，仅管理员审核时可见，不会公开展示。</p>
        <p>3. 审核通常在1-3个工作日内完成，结果将通过消息通知您。</p>
        <p>4. 认证信息一经通过不可修改，如需变更请联系管理员。</p>
        <p>5. 请确保提交信息真实有效，虚假信息将被永久拒绝认证。</p>
    </div>
</div>
