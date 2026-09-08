<?php /** 编辑帖子页 */ $title = '编辑帖子'; $hideSidebar = true; ?>
<div class="card">
    <div class="card-header">编辑帖子</div>
    <div class="card-body">
        <form id="editForm" onsubmit="return false;">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $post['id'] ?>">
            <div class="form-group">
                <label class="form-label">板块 <span class="required">*</span></label>
                <select name="category_id" class="form-control" required>
                    <option value="">请选择板块</option>
                    <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $c['id'] == $post['category_id'] ? 'selected' : '' ?>>
                        <?= e($c['name']) ?><?= $c['is_certification_required'] ? ' [仅认证用户]' : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">标题 <span class="required">*</span></label>
                <input type="text" name="title" class="form-control" required maxlength="200" value="<?= e($post['title']) ?>">
            </div>
            <?php
            // 主题类型：所有类型一经发布不可修改主体类型，下面只读展示当前类型；
            // 对可配置的两种类型（auction / pay）继续展开编辑面板。
            ?>
            <?php if (!empty($currentType) && $currentType !== 'normal'): ?>
            <div class="special-box" style="margin-bottom:14px;padding:12px 14px;background:#fff5f3;border:1px solid #ea6f5a;border-radius:6px;">
                <div style="font-weight:600;color:#ea6f5a;margin-bottom:8px;">本帖为「<?= e($currentTypeLabel) ?>」（已发布不可修改文章类型）</div>
                <input type="hidden" name="topic_type" value="<?= e($currentType) ?>">
                <?php if (!empty($post['is_auction'])): ?>
                <div id="auctionFields" style="display:flex;flex-wrap:wrap;gap:16px;">
                    <div style="flex:1;min-width:160px;">
                        <label class="form-label">起拍价（元）<?= $post['bid_count'] ? '' : '<span class="required">*</span>' ?></label>
                        <input type="number" name="start_price" class="form-control" min="0" step="0.01" value="<?= e($post['start_price']) ?>" placeholder="如 100" <?= $post['bid_count'] ? 'disabled' : '' ?>>
                    </div>
                    <div style="flex:1;min-width:160px;">
                        <label class="form-label">最小加价（元）</label>
                        <input type="number" name="step_price" class="form-control" min="0.01" step="0.01" value="<?= e($post['step_price']) ?>" placeholder="10" <?= $post['bid_count'] ? 'disabled' : '' ?>>
                    </div>
                    <div style="flex:1;min-width:200px;">
                        <label class="form-label">结拍时间</label>
                        <input type="datetime-local" name="end_time" class="form-control" value="<?= $post['end_time'] ? date('Y-m-d\TH:i', strtotime($post['end_time'])) : '' ?>" <?= $post['bid_count'] ? 'disabled' : '' ?>>
                    </div>
                </div>
                <p class="form-hint" style="margin-top:8px;"><?= $post['bid_count'] ? '已有 ' . (int)$post['bid_count'] . ' 人出价：起拍价 / 最小加价 / 结拍时间已锁定不可修改；标题、正文、图片、附件等其它内容仍可正常编辑保存。' : '拍卖帖将在一楼底部展示出价区，用户可出价竞拍；结拍后价高者得。' ?></p>
                <?php elseif ($currentType === 'pay' && $payRow): ?>
                <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-end;">
                    <div style="flex:1;min-width:180px;">
                        <label class="form-label">正文解锁价格</label>
                        <input type="number" name="pay_content_price" class="form-control" min="0" step="1" value="<?= (int)($payRow['content_price'] ?? 0) ?>">
                    </div>
                    <div style="flex:1;min-width:180px;">
                        <label class="form-label">附件解锁价格</label>
                        <input type="number" name="pay_attachment_price" class="form-control" min="0" step="1" value="<?= (int)($payRow['attachment_price'] ?? 0) ?>">
                    </div>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-end;margin-top:12px;">
                    <div style="flex:1;min-width:160px;">
                        <label class="form-label">币种</label>
                        <select name="pay_currency" class="form-control">
                            <?php foreach (PointService::currencies(true, true) as $c): ?>
                            <option value="<?= e($c['code']) ?>" <?= ($c['code'] === ($payRow['currency'] ?? 'token')) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex:1;min-width:180px;">
                        <label class="form-label">正文免费预览比例（%）</label>
                        <input type="number" name="pay_preview_ratio" class="form-control" min="0" max="100" step="1" value="<?= (int)($payRow['preview_ratio'] ?? 20) ?>">
                    </div>
                    <div style="flex:1;min-width:200px;">
                        <label class="form-label" style="color:#888;">解锁总价（自动求和）</label>
                        <input type="text" id="payTotalPreviewEdit" class="form-control" value="<?= (int)($payRow['content_price'] ?? 0) + (int)($payRow['attachment_price'] ?? 0) ?>" readonly style="background:#fff;">
                    </div>
                </div>
                <p class="form-hint" style="margin-top:8px;">修改后只影响新浏览者，历史已购订单按购买时快照入账不受影响。任一价格设为 0 表示该部分免费公开。</p>
                <?php elseif ($currentType === 'event' && !empty($eventRow)): ?>
                <?php
                // 活动主题：只读展示。活动配置一经发布不可修改（与拍卖帖"有人出价后锁定"同理），
                // 这里把当前设置原样展示出来，让用户知道已发布的内容，避免误以为丢失。
                // 全部加 disabled —— disabled 的字段不会被表单序列化，后端 update() 收不到，天然防篡改。
                $cfTypeLabels = ['text' => '单行文本', 'textarea' => '多行文本', 'select' => '下拉选择', 'number' => '数字', 'date' => '日期'];
                ?>
                <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-end;">
                    <div style="flex:2;min-width:220px;">
                        <label class="form-label">活动主题</label>
                        <input type="text" class="form-control" value="<?= e($eventRow['subject'] ?? '') ?>" disabled>
                    </div>
                    <div style="flex:1;min-width:140px;">
                        <label class="form-label">活动形式</label>
                        <select class="form-control" disabled>
                            <option value="offline" <?= ($eventRow['mode'] ?? 'offline') === 'offline' ? 'selected' : '' ?>>线下</option>
                            <option value="online" <?= ($eventRow['mode'] ?? 'offline') === 'online' ? 'selected' : '' ?>>线上</option>
                        </select>
                    </div>
                    <div style="flex:1;min-width:140px;">
                        <label class="form-label">活动费用</label>
                        <input type="text" class="form-control" value="<?= (float)($eventRow['fee'] ?? 0) > 0 ? '¥ ' . number_format((float)$eventRow['fee'], 2) : '免费' ?>" disabled>
                    </div>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-end;margin-top:12px;">
                    <div style="flex:1;min-width:180px;">
                        <label class="form-label">活动时间</label>
                        <input type="text" class="form-control" value="<?= e($eventRow['event_time'] ?? '') ?>" disabled>
                    </div>
                    <div style="flex:1;min-width:180px;">
                        <label class="form-label">活动地点</label>
                        <input type="text" class="form-control" value="<?= e($eventRow['location'] ?? '') ?>" disabled>
                    </div>
                    <div style="flex:1;min-width:140px;">
                        <label class="form-label">名额（0=不限）</label>
                        <input type="text" class="form-control" value="<?= (int)($eventRow['capacity'] ?? 0) ?>（已报名 <?= (int)($eventRow['signup_count'] ?? 0) ?>）" disabled>
                    </div>
                </div>
                <?php if (!empty($eventRow['custom_fields'])): ?>
                <div style="margin-top:12px;">
                    <label class="form-label" style="margin-bottom:8px;">自定义报名表单字段</label>
                    <?php foreach ($eventRow['custom_fields'] as $cf): ?>
                    <?php
                    // 下拉类型额外展示已配置的选项（只读），便于作者核对发布内容
                    $cfOpts = (isset($cf['options']) && is_array($cf['options'])) ? $cf['options'] : [];
                    $cfOptStrs = [];
                    foreach ($cfOpts as $cfOpt) { if (is_scalar($cfOpt) && (string)$cfOpt !== '') $cfOptStrs[] = (string)$cfOpt; }
                    ?>
                    <div style="display:flex;gap:10px;align-items:center;margin-bottom:6px;flex-wrap:wrap;">
                        <input type="text" class="form-control" style="flex:1;min-width:130px;" value="<?= e($cf['label'] ?? '') ?>" disabled>
                        <input type="text" class="form-control" style="width:100px;flex:none;" value="<?= e($cfTypeLabels[$cf['type'] ?? 'text'] ?? '单行文本') ?>" disabled>
                        <?php if (($cf['type'] ?? '') === 'select'): ?>
                            <input type="text" class="form-control" style="flex:2;min-width:150px;color:#888;" value="<?= empty($cfOptStrs) ? '（未配置选项）' : '选项：' . e(implode(' / ', $cfOptStrs)) ?>" disabled>
                        <?php endif; ?>
                        <span style="font-size:12px;width:40px;flex:none;white-space:nowrap;color:<?= !empty($cf['required']) ? '#ea6f5a' : '#bbb' ?>;"><?= !empty($cf['required']) ? '必填' : '选填' ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <p class="form-hint" style="margin-top:8px;">活动配置（主题 / 形式 / 时间 / 地点 / 名额 / 报名表单）一经发布不可修改，如需调整请重新发布活动帖；标题、正文、附件等其它内容仍可正常编辑保存。</p>
                <?php elseif ($currentType === 'poll' && !empty($pollRow)): ?>
                <?php
                // 投票主题：只读展示。投票设置（单/多选、选项、截止时间）一经发布不可修改——
                //   投票行为已发生，选项数 / 文本改动都属作弊。
                //   全部 disabled：disabled 字段不被表单序列化，后端 update() 天然收不到。
                //   products 要求隐藏投票人，「匿名投票」开关在主题发布后也不再修改——它直接被产品强制为 0。
                $pollTotalVotes = (int)($pollRow['total_votes'] ?? 0);
                $pollOpts = !empty($pollRow['options']) ? $pollRow['options'] : [];
                $pollDeadline = !empty($pollRow['deadline']) ? date('Y-m-d H:i', strtotime($pollRow['deadline'])) : '不限';
                ?>
                <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-end;">
                    <div style="flex:1;min-width:160px;">
                        <label class="form-label">模式</label>
                        <input type="text" class="form-control" value="<?= !empty($pollRow['multi']) ? '多选' : '单选' ?>" disabled>
                    </div>
                    <div style="flex:1;min-width:180px;">
                        <label class="form-label">截止时间</label>
                        <input type="text" class="form-control" value="<?= e($pollDeadline) ?>" disabled>
                    </div>
                    <div style="flex:1;min-width:140px;">
                        <label class="form-label">已投票 / 选项数</label>
                        <input type="text" class="form-control" value="<?= $pollTotalVotes ?> 票 / <?= count($pollOpts) ?> 项" disabled>
                    </div>
                </div>
                <div style="margin-top:12px;">
                    <label class="form-label" style="margin-bottom:8px;">投票选项</label>
                    <?php foreach ($pollOpts as $po): ?>
                    <div style="display:flex;gap:10px;align-items:center;margin-bottom:6px;">
                        <input type="text" class="form-control" style="flex:1;min-width:180px;" value="<?= e($po['text']) ?>" disabled>
                        <span style="font-size:12px;color:#999;width:80px;flex:none;white-space:nowrap;"><?= (int)($po['id'] ?? 0) ? '#' . (int)$po['id'] : '' ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <p class="form-hint" style="margin-top:8px;">投票设置（单/多选、选项、截止时间）一经发布不可修改；标题、正文、附件等其它内容仍可正常编辑保存。投票不记录身份，无法匿名/实名切换。</p>
                <?php elseif ($currentType === 'debate' && !empty($debateRow)): ?>
                <?php
                // 辩论主题：只读展示。辩论设置（正/反方观点、截止时间）一经发布不可修改——
                //   避免辩论被作者偷换观点影响已发生的站队。
                //   站队发言本身在评论区显示，不在本面板呈现。
                $dDeadline = !empty($debateRow['deadline']) ? date('Y-m-d H:i', strtotime($debateRow['deadline'])) : '不限';
                ?>
                <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start;">
                    <div style="flex:1;min-width:220px;">
                        <label class="form-label" style="color:#1e6fd9;">正方观点</label>
                        <textarea class="form-control" style="min-height:90px;" disabled><?= e($debateRow['pro_text'] ?? '') ?></textarea>
                    </div>
                    <div style="flex:1;min-width:220px;">
                        <label class="form-label" style="color:#e0852a;">反方观点</label>
                        <textarea class="form-control" style="min-height:90px;" disabled><?= e($debateRow['con_text'] ?? '') ?></textarea>
                    </div>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-end;margin-top:12px;">
                    <div style="flex:1;min-width:240px;">
                        <label class="form-label">辩论截止时间</label>
                        <input type="text" class="form-control" value="<?= e($dDeadline) ?>" disabled>
                    </div>
                    <div style="flex:1;min-width:160px;">
                        <label class="form-label">初始站队计数</label>
                        <input type="text" class="form-control" value="正 <?= (int)($debateRow['pro_votes'] ?? 0) ?> / 反 <?= (int)($debateRow['con_votes'] ?? 0) ?>" disabled>
                    </div>
                </div>
                <p class="form-hint" style="margin-top:8px;">辩论设置（正反方观点、截止时间）一经发布不可修改；标题、正文、附件等其它内容仍可正常编辑保存。站队发言显示在评论区，每用户首次站定一方后不可切换。</p>
                <?php elseif ($currentType === 'lottery' && !empty($lotteryRow)): ?>
                <?php
                // 抽奖主题：只读展示。抽奖配置（奖品/中奖人数/规则/开奖时间）一经发布不可修改——
                //   抽奖行为已开始 → 任何配置改动都属作弊，与拍卖帖「有人出价后锁定」同理。
                //   全部 disabled：disabled 字段不被表单序列化，后端 update() 天然收不到。
                $lt = $lotteryRow;
                $ltPrizeType = in_array($lt['prize_type'], ['physical', 'virtual', 'point'], true) ? $lt['prize_type'] : 'physical';
                $ltPrizeTypeLabel = ['physical' => '实物', 'virtual' => '虚拟', 'point' => PointService::currencyLabel($lt['stake_currency'])][$ltPrizeType];
                $ltCostCurrencyName = PointService::currencyLabel($lt['cost_currency']);
                $ltDrawAtLabel = !empty($lt['draw_at']) ? date('Y-m-d H:i', strtotime($lt['draw_at'])) : '未设置';
                $ltRuleLabels = ['follow' => '关注我', 'reply' => '回复15字以上', 'like' => '点赞主题', 'favorite' => '收藏主题'];
                $ltRuleModeLabels = ['must' => '必须', 'optional' => '鼓励', 'no' => '不限'];
                ?>
                <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-end;">
                    <div style="flex:2;min-width:200px;">
                        <label class="form-label">奖品名</label>
                        <input type="text" class="form-control" value="<?= e($lt['prize_name'] ?? '') ?>" disabled>
                    </div>
                    <div style="flex:1;min-width:140px;">
                        <label class="form-label">奖品类型</label>
                        <input type="text" class="form-control" value="<?= e($ltPrizeTypeLabel) ?>" disabled>
                    </div>
                    <div style="flex:1;min-width:140px;">
                        <label class="form-label">中奖人数</label>
                        <input type="text" class="form-control" value="<?= (int)$lt['winner_count'] ?>（已参与 <?= (int)$lt['joined_count'] ?>）" disabled>
                    </div>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-end;margin-top:12px;">
                    <?php if ($ltPrizeType === 'point'): ?>
                    <div style="flex:1;min-width:160px;">
                        <label class="form-label">每份积分数</label>
                        <input type="text" class="form-control" value="<?= (int)$lt['point_unit'] ?> <?= e(PointService::currencyLabel($lt['stake_currency'])) ?>" disabled>
                    </div>
                    <div style="flex:1;min-width:160px;">
                        <label class="form-label">共质押</label>
                        <input type="text" class="form-control" value="<?= (int)$lt['stake_points'] ?> <?= e(PointService::currencyLabel($lt['stake_currency'])) ?>" disabled>
                    </div>
                    <?php else: ?>
                    <div style="flex:2;min-width:200px;">
                        <label class="form-label">奖品价值</label>
                        <input type="text" class="form-control" value="<?= e($lt['prize_value'] !== '' ? $lt['prize_value'] : '未填写') ?>" disabled>
                    </div>
                    <?php endif; ?>
                    <div style="flex:1;min-width:160px;">
                        <label class="form-label">参与消耗</label>
                        <input type="text" class="form-control" value="<?= (int)$lt['join_cost'] > 0 ? (int)$lt['join_cost'] . ' ' . e($ltCostCurrencyName) : '免费' ?>" disabled>
                    </div>
                    <div style="flex:1;min-width:180px;">
                        <label class="form-label">开奖时间</label>
                        <input type="text" class="form-control" value="<?= e($ltDrawAtLabel) ?>" disabled>
                    </div>
                </div>
                <?php if (!empty($lt['rules'])): ?>
                <div style="margin-top:12px;">
                    <label class="form-label" style="margin-bottom:8px;">抽奖规则</label>
                    <?php foreach (['follow', 'reply', 'like', 'favorite'] as $lrk):
                        $lrv = $lt['rules'][$lrk] ?? 'no';
                    ?>
                    <div style="display:flex;gap:10px;align-items:center;margin-bottom:6px;flex-wrap:wrap;">
                        <input type="text" class="form-control" style="flex:1;min-width:130px;" value="<?= e($ltRuleLabels[$lrk]) ?>" disabled>
                        <input type="text" class="form-control" style="width:80px;flex:none;" value="<?= e($ltRuleModeLabels[$lrv] ?? '不限') ?>" disabled>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <p class="form-hint" style="margin-top:8px;">抽奖设置（奖品 / 类型 / 中奖人数 / 价值 / 规则 / 开奖时间）一经发布不可修改；标题、正文、图片、附件等其它内容仍可正常编辑保存。</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($interviewRow)): ?>
            <div class="theme-panel" style="margin-top:8px;padding:14px;background:#f8f9fa;border:1px solid #eee;border-radius:6px;">
                <div style="font-weight:600;color:#ea6f5a;margin-bottom:10px;">采访设置（记者 / 受访者 / 头衔 等一经发布不可修改，下方两个提问开关可在此调整）</div>
                <div style="margin-bottom:10px;">
                    <label class="form-label">采访主题</label>
                    <input type="text" class="form-control" value="<?= e($interviewRow['interview_topic'] ?? '') ?>" disabled>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:16px;">
                    <?php
                    $rep = $interviewRow['reporter_card'] ?? null;
                    $iv  = $interviewRow['interviewee_card'] ?? null;
                    ?>
                    <div style="flex:1;min-width:200px;">
                        <label class="form-label">记者</label>
                        <input type="text" class="form-control" value="<?= e($rep['name'] ?? '—') ?>" disabled>
                        <?php if (!empty($rep['title'])): ?>
                        <input type="text" class="form-control" style="margin-top:6px;" value="头衔：<?= e($rep['title']) ?>" disabled>
                        <?php endif; ?>
                    </div>
                    <div style="flex:1;min-width:200px;">
                        <label class="form-label">受访者</label>
                        <input type="text" class="form-control" value="<?= e($iv['name'] ?? '—') ?>" disabled>
                        <?php if (!empty($iv['title'])): ?>
                        <input type="text" class="form-control" style="margin-top:6px;" value="头衔：<?= e($iv['title']) ?>" disabled>
                        <?php endif; ?>
                    </div>
                    <div style="flex:0 0 auto;display:flex;align-items:flex-end;gap:12px;">
                        <style>
                            .iv-toggle { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; font-size:13px; color:#555; border:1px dashed #ea6f5a; border-radius:4px; background:#fff; cursor:pointer; user-select:none; transition:all .15s; }
                            .iv-toggle:hover { border-style:solid; background:#fff5f3; color:#ea6f5a; }
                            .iv-toggle input[type="checkbox"] { accent-color:#ea6f5a; cursor:pointer; margin:0; }
                            .iv-toggle.is-on { background:#ea6f5a; color:#fff; border-color:#ea6f5a; border-style:solid; font-weight:600; }
                        </style>
                        <label class="iv-toggle <?= !empty($interviewRow['open_question']) ? 'is-on' : '' ?>">
                            <input type="checkbox" name="interview_open" value="1" <?= !empty($interviewRow['open_question']) ? 'checked' : '' ?> onchange="this.parentNode.classList.toggle('is-on', this.checked)"> 开放读者提问
                        </label>
                        <label class="iv-toggle <?= !empty($interviewRow['allow_interviewee_ask']) ? 'is-on' : '' ?>">
                            <input type="checkbox" name="interview_allow_interviewee_ask" value="1" <?= !empty($interviewRow['allow_interviewee_ask']) ? 'checked' : '' ?> onchange="this.parentNode.classList.toggle('is-on', this.checked)"> 允许受访者提问
                        </label>
                    </div>
                </div>
                <?php if (!empty($interviewRow['ended'])): ?>
                <div style="margin-top:8px;"><span class="badge" style="background:#999;color:#fff;">采访已结束（<?= e(date('Y-m-d H:i', strtotime($interviewRow['ended_at']))) ?>）</span></div>
                <?php endif; ?>
                <p class="form-hint" style="margin-top:8px;">记者、受访者与头衔一经发布不可修改；标题、正文、图片、附件等其它内容仍可正常编辑保存。</p>
            </div>
            <?php endif; ?>
            <?php if (can_upload_image()): ?>
            <?php /* 2026-09-06 封面编辑：所有主题类型编辑时都可更换/移除封面（卡片式版式作为卡片主视觉；预填现有封面） */ ?>
            <?php $editCoverVal = trim((string)($post['cover_image'] ?? '')); ?>
            <div class="form-group" id="coverUploadGroup">
                <label class="form-label">封面图 <span style="color:#999;font-weight:normal;">（聚焦版式的卡片主视觉；置空则卡片回退取正文第一张图）</span></label>
                <input type="hidden" name="cover_image" id="coverImageInput" value="<?= e($editCoverVal) ?>">
                <div id="coverPreview" style="display:none;position:relative;width:240px;margin-bottom:8px;">
                    <img id="coverPreviewImg" src="" alt="" style="display:block;width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:9px;border:1px solid #eee;">
                    <button type="button" id="coverRemoveBtn" title="移除封面图" style="position:absolute;top:6px;right:6px;width:26px;height:26px;border:0;border-radius:50%;background:rgba(0,0,0,.55);color:#fff;font-size:13px;cursor:pointer;line-height:1;">✕</button>
                </div>
                <div id="coverUploader">
                    <button type="button" class="btn btn-ghost" id="coverUploadBtn"><i class="fa-regular fa-image"></i> 上传封面图</button>
                    <input type="file" id="coverImageFile" accept="image/*" style="display:none;">
                </div>
                <p class="form-hint" id="coverHint">建议 4:3 横图，JPG/PNG/WEBP，不超过 5MB</p>
            </div>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                var hidden = document.getElementById('coverImageInput');
                if (!hidden) return;
                var fileInput = document.getElementById('coverImageFile');
                var preview = document.getElementById('coverPreview');
                var previewImg = document.getElementById('coverPreviewImg');
                var uploader = document.getElementById('coverUploader');
                var hint = document.getElementById('coverHint');
                var btn = document.getElementById('coverUploadBtn');
                // 预填现有封面
                if (hidden.value) {
                    previewImg.src = window.absoluteAssetUrl ? window.absoluteAssetUrl(hidden.value) : hidden.value;
                    preview.style.display = 'block';
                    uploader.style.display = 'none';
                }
                var bind = function () {
                    btn.addEventListener('click', function () { fileInput.click(); });
                    fileInput.addEventListener('change', function () {
                        var f = fileInput.files && fileInput.files[0];
                        if (!f) return;
                        var fd = new FormData();
                        fd.append('file', f);
                        fd.append('type', 'post');
                        var orig = btn.innerHTML;
                        btn.disabled = true; btn.textContent = '上传中...';
                        postJSON(url('upload/image'), fd, function (res) {
                            btn.disabled = false; btn.innerHTML = orig;
                            if (res.code === 0 && res.data && res.data.url) {
                                hidden.value = res.data.url;
                                previewImg.src = window.absoluteAssetUrl ? window.absoluteAssetUrl(res.data.url) : res.data.url;
                                preview.style.display = 'block';
                                uploader.style.display = 'none';
                                if (hint) hint.textContent = '已更新封面图，保存后生效';
                            } else {
                                toast((res && res.message) ? res.message : '上传失败，请重试', 'error');
                            }
                        }, function (res) {
                            btn.disabled = false; btn.innerHTML = orig;
                            toast((res && res.message) ? res.message : '上传失败，请重试', 'error');
                        });
                        fileInput.value = '';
                    });
                    document.getElementById('coverRemoveBtn').addEventListener('click', function () {
                        hidden.value = '';
                        preview.style.display = 'none';
                        previewImg.src = '';
                        uploader.style.display = 'inline-block';
                        if (hint) hint.textContent = '建议 4:3 横图，JPG/PNG/WEBP，不超过 5MB';
                    });
                };
                bind();
            });
            </script>
            <?php endif; ?>
            <div class="form-group">
                <label class="form-label">正文 <span class="required">*</span></label>
                <div class="editor-toolbar">
                    <button type="button" onclick="insertFormat('content','**','**')">加粗</button>
                    <button type="button" onclick="insertFormat('content','*','*')">斜体</button>
                    <span class="sep">|</span>
                    <button type="button" onclick="insertFormat('content','## ','')">标题</button>
                    <button type="button" onclick="insertFormat('content','> ','')">引用</button>
                    <span class="sep">|</span>
                    <button type="button" onclick="insertFormat('content','`','`')">代码</button>
                    <button type="button" onclick="insertFormat('content','\n```\n','\n```')">代码块</button>
                    <span class="sep">|</span>
                    <button type="button" onclick="insertFormat('content','[hide]','[/hide]')">回复可见</button>
                    <?php if (can_upload_image()): ?><span class="sep">|</span>
                    <button type="button" id="insertImageBtn" title="在光标处插入图片">🖼 插入图片</button><?php endif; ?>
                </div>
                <textarea name="content" id="content" class="form-control" required style="min-height:300px;border-radius:0 0 4px 4px;"><?= e($post['content']) ?></textarea>
                <input type="file" id="inlineImageInput" accept="image/*" multiple style="display:none;">
            </div>
            <?php
            // 附件管理（预览/删除/回复可见 + 新增上传）始终显示给 owner/版主管理现有资源。
            //   「新增上传」按钮受 can_upload_attach() 控制；无新增权限时仍可删除/切换已有附件。
            //   修复 bug：之前把整组上传区块包在 can_upload_attach() 内，无权上传的角色编辑时连删除已有
            //   附件都做不了。
            $canAttNew = can_upload_attach();
            ?>
            <div class="form-group">
                <label class="form-label">附件（最多10个，每个不超过20MB）</label>
                <div id="attachList" style="margin-bottom:10px;"></div>
                <div id="attachUploader" style="display:inline-block;">
                    <?php if ($canAttNew): ?>
                    <button type="button" class="btn btn-ghost" onclick="document.getElementById('attachInput').click()">+ 添加附件</button>
                    <?php endif; ?>
                    <input type="file" id="attachInput" multiple style="display:none;">
                </div>
                <p class="form-hint">支持文档 / 压缩包 / 音视频等常见格式<?= $canAttNew ? '' : '（当前角色无新增上传权限，但仍可删除/切换已有附件）' ?></p>
            </div>

            <div class="form-group" style="margin-top:24px;">
                <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">保存修改</button>
                <a href="<?= url('post/show', ['id' => $post['id']]) ?>" class="btn btn-ghost">取消</a>
            </div>
        </form>
    </div>
</div>
<script>
// 付费主题：实时求和两档价格作为解锁总价
(function () {
    function calcPayTotal() {
        var fields = ['pay_content_price', 'pay_attachment_price'];
        var sum = 0;
        for (var i = 0; i < fields.length; i++) {
            var el = document.querySelector('input[name=' + fields[i] + ']');
            if (!el) continue;
            var v = parseInt(el.value || '0', 10);
            if (v < 0) v = 0;
            sum += v;
        }
        var out = document.getElementById('payTotalPreviewEdit');
        if (out) out.value = sum;
    }
    ['pay_content_price', 'pay_attachment_price'].forEach(function (n) {
        var el = document.querySelector('input[name=' + n + ']');
        if (el) el.addEventListener('input', calcPayTotal);
    });
    calcPayTotal();
})();

document.addEventListener('DOMContentLoaded', function () {
var uploadedAttachments = <?= json_encode($post['attachments_arr'] ?: []) ?>;
var MAX_ATTACH = 10;
renderAttachments();
/* ===== 附件上传 ===== */
function escHtml(s){ return String(s).replace(/[&<>"']/g, function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }
function updateAttachBoxVisibility() {
    var box = document.getElementById('attachUploader');
    if (box) box.style.display = uploadedAttachments.length >= MAX_ATTACH ? 'none' : 'inline-block';
}
window.attachInputChange = function(input) {
    var files = Array.from(input.files);
    var remaining = MAX_ATTACH - uploadedAttachments.length;
    if (remaining <= 0) { input.value = ''; return; }
    if (files.length > remaining) { toast('最多上传 ' + MAX_ATTACH + ' 个附件', 'warning'); files = files.slice(0, remaining); }
    files.forEach(function(file) {
        var fd = new FormData();
        fd.append('file', file);
        fd.append('_token', document.querySelector('input[name="_token"]').value);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', url('upload/attachment'));
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function() {
            if (xhr.status === 200) {
                var res = JSON.parse(xhr.responseText);
                if (res.code === 0) { res.data.reply_visible = 0; uploadedAttachments.push(res.data); renderAttachments(); }
                else toast(res.message || '上传失败', 'error');
            }
        };
        xhr.send(fd);
    });
    input.value = '';
};
function renderAttachments() {
    var box = document.getElementById('attachList');
    if (!box) return;
    box.innerHTML = '';
    uploadedAttachments.forEach(function(att, idx) {
        var size = att.size ? (att.size/1024).toFixed(1) + ' KB' : '';
        var rv = att.reply_visible ? 1 : 0;
        var div = document.createElement('div');
        div.style.cssText = 'display:flex;align-items:center;gap:8px;padding:6px 10px;background:#f8f8f8;border:1px solid #eee;border-radius:4px;margin-bottom:6px;font-size:13px;';
        div.innerHTML = '<span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">📎 ' + escHtml(att.name) + ' <span style="color:#999;">' + size + '</span></span>'
            + '<label style="display:inline-flex;align-items:center;gap:4px;color:#666;cursor:pointer;white-space:nowrap;"><input type="checkbox" ' + (rv ? 'checked' : '') + ' onchange="toggleAttachReplyVisible(' + idx + ', this.checked)"> 回复可见</label>'
            + '<button type="button" onclick="removeAttachment(' + idx + ')" style="border:none;background:none;color:#f5222d;cursor:pointer;">删除</button>';
        box.appendChild(div);
    });
    updateAttachBoxVisibility();
}
window.toggleAttachReplyVisible = function(idx, checked) {
    uploadedAttachments[idx].reply_visible = checked ? 1 : 0;
};
window.removeAttachment = function(idx) { uploadedAttachments.splice(idx, 1); renderAttachments(); };
var attachInputEl = document.getElementById('attachInput');
if (attachInputEl) attachInputEl.addEventListener('change', function() { window.attachInputChange(this); });

/* ===== 正文内联图片插入（图片写入正文 Markdown） ===== */
// ✦ 为什么之前的方案一定会跳到末尾：
//   曾经在 mousedown 捕获选区、click 再"兜底"读一次。但 click 阶段 textarea 早已失焦，
//   Chrome 会把 selectionStart 重置成 value.length（末尾）——那次"兜底"恰好把 mousedown
//   捕获的正确值**覆盖成了末尾**。而各浏览器对「失焦后选区是否保留」行为并不一致，
//   所以任何依赖实时 selectionStart 的方案都不可靠。
// ✦ 现在改成「持续记录选区 + 文本锚点」双保险：
//   1) 平时就在 keyup/click/select/input/mouseup/blur 里持续记录最后的选区到 _lastSel，
//      失焦瞬间（blur）也记一次——那时选区通常还没被重置；
//   2) 点插入按钮时，用 _lastSel 把一个唯一标记「钉」进正文；
//   3) 上传完成后把标记替换成 Markdown 图片语法。
//   标记本身就是正文的一部分，位置 100% 准确，与焦点、选区、浏览器行为完全无关。
var IMG_MARKER = '\u0001__INLINE_IMG__\u0001';
var _lastSel = { start: 0, end: 0 };
var _pendingUploads = 0;
var _contentTa = document.getElementById('content');
if (_contentTa) {
    var _recSel = function() {
        _lastSel.start = _contentTa.selectionStart;
        _lastSel.end = _contentTa.selectionEnd;
    };
    // ⚠️ 只在 textarea **仍聚焦**的事件里记录选区。
    //    千万不要在 blur 里「兜底」读一次——点按钮的顺序是 mousedown → blur → click，
    //    blur 阶段 Chrome 已经把 selectionStart 重置成 value.length（末尾），
    //    在那里读取会把正确的光标位置**污染成末尾**（这正是上一版点完按钮就跳到末尾的原因）。
    ['keydown', 'keyup', 'click', 'select', 'input', 'mouseup', 'focus'].forEach(function(evt) {
        _contentTa.addEventListener(evt, _recSel);
    });
}
// 清理残留标记（用户取消选图 / 上传失败 / 全部完成）
function clearInlineMarker() {
    if (!_contentTa) return;
    if (_contentTa.value.indexOf(IMG_MARKER) !== -1) {
        _contentTa.value = _contentTa.value.split(IMG_MARKER).join('');
    }
}
// 用 _lastSel 把锚点钉进正文（保持视图滚动位置不变，点按钮不应产生任何跳动）
function pinInlineMarker() {
    if (!_contentTa) return false;
    clearInlineMarker();                       // 先清掉上次可能残留的标记
    var v = _contentTa.value;
    var s = Math.min(Math.max(_lastSel.start, 0), v.length);
    var e = Math.min(Math.max(_lastSel.end, s), v.length);
    var keepScroll = _contentTa.scrollTop;     // 保存视图位置
    _contentTa.value = v.slice(0, s) + IMG_MARKER + v.slice(e);
    _contentTa.focus();
    _contentTa.setSelectionRange(s, s + IMG_MARKER.length);
    _contentTa.scrollTop = keepScroll;         // 恢复视图：位置不变动
    return true;
}
// 把锚点替换成真正的 Markdown，并在其后留一个新锚点给后续图片（多图依次往后排）
function fillInlineMarker(relUrl) {
    if (!_contentTa) return false;
    var idx = _contentTa.value.indexOf(IMG_MARKER);
    if (idx === -1) return false;
    var before = _contentTa.value.slice(0, idx);
    var after  = _contentTa.value.slice(idx + IMG_MARKER.length);
    var needNl = before.length > 0 && before.charAt(before.length - 1) !== '\n';
    var md = (needNl ? '\n' : '') + '![图片](' + relUrl + ')\n';
    var keepScroll = _contentTa.scrollTop;     // 保存视图位置
    _contentTa.value = before + md + IMG_MARKER + after;
    var pos = before.length + md.length;
    _contentTa.focus();
    _contentTa.setSelectionRange(pos, pos + IMG_MARKER.length);
    _contentTa.scrollTop = keepScroll;         // 恢复视图：只在原处插入，不跳走
    _contentTa.dispatchEvent(new Event('input'));
    return true;
}
var insertImageBtnEl = document.getElementById('insertImageBtn');
if (insertImageBtnEl) {
    // mousedown 阶段 textarea 还没失焦（blur 发生在 mousedown 之后），是最可靠的捕获时机
    insertImageBtnEl.addEventListener('mousedown', function() { if (_contentTa) _recSel(); });
    insertImageBtnEl.addEventListener('click', function(e) {
        e.preventDefault();                    // 防止按钮默认行为抢焦点
        pinInlineMarker();
        var inp = document.getElementById('inlineImageInput');
        if (inp) inp.click();
    });
}
window.inlineImageInputChange = function(input) {
    var files = Array.from(input.files || []);
    if (!files.length) { clearInlineMarker(); input.value = ''; return; }
    var tok = document.querySelector('input[name="_token"]');
    _pendingUploads += files.length;
    files.forEach(function(file) {
        var fd = new FormData();
        fd.append('file', file);
        if (tok) fd.append('_token', tok.value);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', url('upload/image', {type: 'post'}));
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        var done = function() {
            _pendingUploads--;
            if (_pendingUploads <= 0) { _pendingUploads = 0; clearInlineMarker(); }
        };
        xhr.onload = function() {
            if (xhr.status === 200) {
                var res = null;
                try { res = JSON.parse(xhr.responseText); } catch (err) { res = null; }
                if (res && res.code === 0 && res.data && res.data.url) {
                    fillInlineMarker(res.data.url);
                    toast('图片已插入正文', 'success');
                } else { toast((res && res.message) || '上传失败', 'error'); }
            } else { toast('上传失败，请重试', 'error'); }
            done();
        };
        xhr.onerror = function() { toast('网络错误，上传失败', 'error'); done(); };
        xhr.send(fd);
    });
    input.value = '';
};
var inlineImageInputEl = document.getElementById('inlineImageInput');
if (inlineImageInputEl) {
    inlineImageInputEl.addEventListener('change', function() { window.inlineImageInputChange(this); });
    // Chrome 113+：用户在文件框点「取消」时触发，清掉残留锚点
    inlineImageInputEl.addEventListener('cancel', function() { if (_pendingUploads <= 0) clearInlineMarker(); });
}

document.getElementById('editForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = document.getElementById('submitBtn');
    var fd = new FormData(this);
    var data = {};
    fd.forEach(function(v, k) { data[k] = v; });
    data.attachments = JSON.stringify(uploadedAttachments);
    btn.disabled = true; btn.textContent = '保存中...';
    postJSON(url('post/update', {id: <?= $post['id'] ?>}), data, function(res) {
        if (res.code === 0) { toast('保存成功', 'success'); setTimeout(function() { window.location.href = res.data.redirect; }, 600); }
        else { toast(res.message || '保存失败', 'error'); btn.disabled = false; btn.textContent = '保存修改'; }
    }, function(res) { toast(res.message || '网络错误', 'error'); btn.disabled = false; btn.textContent = '保存修改'; });
});
});
</script>
