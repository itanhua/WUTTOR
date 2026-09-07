<?php /** 发布帖子页 */ $title = '发布新文章'; $js = 'js/mention.js'; ?>
<?php
// 是否封禁（禁言）：status!=1 且非超级管理员/站长（与 banned 中间件保持一致）
$isBanned = ((int)(Auth::user()['status'] ?? 1) !== 1) && !in_array(Auth::role(), ['super_admin', 'webmaster'], true);
// 当前用户是否被授权发布拍卖帖（特殊主题：板块 + 角色组双重打勾；管理级恒为 true）
$canAuction = function($cat) { return can_publish_auction($cat); };

// 2026-09-01 抽奖主题跟随后台币种配置：把可消耗币种（默认 token）的显示名 + 图标注入到 JS，
// 让 calcLotteryStake / toggleLotteryPointMode / validateLotteryPanel 等动态文案也跟着改。
$lotteryCurrencyCode = 'token';
$lotteryCurrencyName = PointService::currencyLabel($lotteryCurrencyCode);
$lotteryCurrencyIcon = PointService::currencyIconHtml($lotteryCurrencyCode);
?>
<style>
.theme-pill { padding:7px 16px; border:1px solid #ddd; background:#fff; color:#555; border-radius:20px; cursor:pointer; font-size:13px; transition:all .15s; }
.theme-pill:hover { border-color:#ea6f5a; color:#ea6f5a; }
.theme-pill.active { background:#ea6f5a; border-color:#ea6f5a; color:#fff; font-weight:600; }
</style>
<div class="card">
    <div class="card-header">发布新文章</div>
    <div class="card-body">
        <?php if ($isBanned): ?>
        <div style="margin-bottom:18px;padding:12px 14px;border-radius:6px;background:#fff7f4;border:1px solid #ffd5cd;color:#a63720;font-size:13px;line-height:1.7;">
            <div style="display:flex;align-items:center;gap:8px;font-weight:600;margin-bottom:4px;">
                <span style="display:inline-flex;width:18px;height:18px;border-radius:50%;background:#ea6f5a;color:#fff;align-items:center;justify-content:center;font-size:11px;">🔒</span>
                账号已被封禁（全站禁言）
            </div>
            <div style="opacity:.9;">当前账号处于封禁状态，无法发布新文章。如需恢复，请联系管理员解封。</div>
        </div>
        <?php endif; ?>
        <fieldset style="border:none;padding:0;margin:0;<?= $isBanned ? 'disabled' : '' ?><?= $isBanned ? 'opacity:.6;' : '' ?>"<?= $isBanned ? ' disabled' : '' ?>>
        <form id="postForm" onsubmit="return false;">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label">板块 <span class="required">*</span></label>
                <select name="category_id" class="form-control" required>
                    <option value="">请选择板块</option>
                    <?php foreach ($categories as $c): ?>
                    <?php if (!can_publish_category($c)) continue; ?>
                    <option value="<?= $c['id'] ?>" <?= $c['id'] == $cat ? 'selected' : '' ?>>
                        <?= e($c['name']) ?><?= $c['is_certification_required'] ? ' [仅认证用户]' : '' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!is_certified()): ?>
                <p class="form-hint">提示：标记 [仅认证用户] 的板块需通过实名认证才能发帖</p>
                <?php endif; ?>
            </div>
            <div class="form-group">
                <label class="form-label">标题 <span class="required">*</span></label>
                <input type="text" name="title" class="form-control" required maxlength="200" placeholder="请输入标题（2-200字）">
            </div>
            <div class="form-group">
                <label class="form-label">文章类型</label>
                <div id="themeTypePills" style="display:flex;flex-wrap:wrap;gap:8px;">
                    <button type="button" class="theme-pill" data-type="normal">普通</button>
                    <button type="button" class="theme-pill" data-type="auction">拍卖</button>
                    <button type="button" class="theme-pill" data-type="pay">付费</button>
                    <button type="button" class="theme-pill" data-type="event">活动</button>
                    <button type="button" class="theme-pill" data-type="bounty">悬赏</button>
                    <button type="button" class="theme-pill" data-type="poll">投票</button>
                    <button type="button" class="theme-pill" data-type="debate">辩论</button>
                    <button type="button" class="theme-pill" data-type="interview">采访</button>
                    <button type="button" class="theme-pill" data-type="lottery">抽奖</button>
                </div>
                <input type="hidden" name="topic_type" id="topicType" value="normal">
                <p class="form-hint" id="themeHint">普通类型无需额外配置。</p>
            </div>

            <!-- 拍卖帖面板 -->
            <div class="theme-panel" data-type="auction" style="display:none;margin-top:8px;padding:14px;background:#f8f9fa;border:1px solid #eee;border-radius:6px;">
                <div style="display:flex;flex-wrap:wrap;gap:16px;">
                    <div style="flex:1;min-width:160px;">
                        <label class="form-label">起拍价（元）<span class="required">*</span></label>
                        <input type="number" name="start_price" class="form-control" min="0" step="0.01" placeholder="如 100">
                    </div>
                    <div style="flex:1;min-width:160px;">
                        <label class="form-label">最小加价（元）</label>
                        <input type="number" name="step_price" class="form-control" min="0.01" step="0.01" value="10" placeholder="10">
                    </div>
                    <div style="flex:1;min-width:200px;">
                        <label class="form-label">结拍时间<span class="required">*</span></label>
                        <input type="datetime-local" name="end_time" class="form-control">
                    </div>
                </div>
                <p class="form-hint">拍卖帖将在一楼底部展示出价区，用户可出价竞拍；结拍后价高者得。</p>
            </div>

            <!-- 付费主题面板 -->
            <div class="theme-panel" data-type="pay" style="display:none;margin-top:8px;padding:14px;background:#f8f9fa;border:1px solid #eee;border-radius:6px;">
                <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-end;">
                    <div style="flex:1;min-width:180px;">
                        <label class="form-label">正文解锁价格</label>
                        <input type="number" name="pay_content_price" class="form-control" min="0" step="1" value="10">
                    </div>
                    <div style="flex:1;min-width:180px;">
                        <label class="form-label">附件解锁价格</label>
                        <input type="number" name="pay_attachment_price" class="form-control" min="0" step="1" value="10">
                    </div>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-end;margin-top:12px;">
                    <div style="flex:1;min-width:160px;">
                        <label class="form-label">币种</label>
                        <select name="pay_currency" class="form-control">
                            <?php foreach (PointService::currencies(true, true) as $c): ?>
                            <option value="<?= e($c['code']) ?>"><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex:1;min-width:180px;">
                        <label class="form-label">正文免费预览比例（%）</label>
                        <input type="number" name="pay_preview_ratio" class="form-control" min="0" max="100" step="1" value="20">
                    </div>
                    <div style="flex:1;min-width:200px;">
                        <label class="form-label" style="color:#888;">解锁总价（自动求和）</label>
                        <input type="text" id="payTotalPreview" class="form-control" value="30" readonly style="background:#fff;">
                    </div>
                </div>
                <p class="form-hint" style="margin-top:8px;">用户可单独解锁某一项（按对应价格扣费），也可一次性解锁全部。任一价格设为 0 表示该部分免费公开。</p>
            </div>

            <!-- 活动主题面板 -->
            <div class="theme-panel" data-type="event" style="display:none;margin-top:8px;padding:14px;background:#f8f9fa;border:1px solid #eee;border-radius:6px;">
                <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-end;">
                    <div style="flex:2;min-width:220px;">
                        <label class="form-label">活动主题</label>
                        <input type="text" name="event_subject" class="form-control" placeholder="如 春季摄影采风交流会" maxlength="100">
                    </div>
                    <div style="flex:1;min-width:140px;">
                        <label class="form-label">活动形式</label>
                        <select name="event_mode" class="form-control">
                            <option value="offline">线下</option>
                            <option value="online">线上</option>
                        </select>
                    </div>
                    <div style="flex:1;min-width:140px;">
                        <label class="form-label">活动费用</label>
                        <input type="number" name="event_fee" class="form-control" min="0" step="0.01" value="0" placeholder="默认 0（免费活动）">
                    </div>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-end;margin-top:12px;">
                    <div style="flex:1;min-width:180px;">
                        <label class="form-label">活动时间</label>
                        <input type="datetime-local" name="event_time" class="form-control">
                    </div>
                    <div style="flex:1;min-width:180px;">
                        <label class="form-label">活动地点</label>
                        <input type="text" name="event_location" class="form-control" placeholder="如 本站 / 某会议室">
                    </div>
                    <div style="flex:1;min-width:140px;">
                        <label class="form-label">名额（0=不限）</label>
                        <input type="number" name="event_capacity" class="form-control" min="0" step="1" value="0">
                    </div>
                </div>
                <div style="margin-top:12px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                        <label class="form-label" style="margin:0;">自定义报名表单字段</label>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="addEventField()">+ 添加字段</button>
                    </div>
                    <div id="eventFields"></div>
                </div>
                <p class="form-hint">报名用户需填写以上字段；发起者可导出报名数据（CSV）。</p>
            </div>

            <!-- 悬赏主题面板 -->
            <div class="theme-panel" data-type="bounty" style="display:none;margin-top:8px;padding:14px;background:#f8f9fa;border:1px solid #eee;border-radius:6px;">
                <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-end;">
                    <div style="flex:1;min-width:160px;">
                        <label class="form-label">每人悬赏积分数<span class="required">*</span></label>
                        <input type="number" name="bounty_per_person" id="bountyPer" class="form-control" min="1" step="1" value="10" oninput="calcBountyTotal()">
                    </div>
                    <div style="flex:1;min-width:160px;">
                        <label class="form-label">悬赏人数<span class="required">*</span></label>
                        <input type="number" name="bounty_people" id="bountyPeople" class="form-control" min="1" step="1" value="1" oninput="calcBountyTotal()">
                    </div>
                    <div style="flex:1;min-width:160px;">
                        <label class="form-label">质押总额（从账户扣除）</label>
                        <input type="text" id="bountyTotal" class="form-control" value="10" readonly style="background:#fff;">
                    </div>
                </div>
                <p class="form-hint" id="bountyBalanceHint">当前论坛积分余额将用于质押；采纳满相应人数后回答公开，未采纳完可提前结束（剩余质押扣 50% 返还）。</p>
            </div>

            <!-- 抽奖帖面板 -->
            <div class="theme-panel" data-type="lottery" style="display:none;margin-top:8px;padding:14px;background:#f8f9fa;border:1px solid #eee;border-radius:6px;">
                <!-- 奖品类型 + 价值（动态切换：实物/虚拟 = 文本；积分 = 每份积分数） -->
                <!-- 关键调整（2026-09-01）：与「奖品名 + 数量 + 中奖人数 + 消耗 + 时间」互换到下方。
                     奖品类型/价值/质押总额 是抽奖的「属性」放最上面更贴合填写节奏。 -->
                <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-end;">
                    <div style="flex:1;min-width:160px;">
                        <label class="form-label">奖品类型<span class="required">*</span></label>
                        <select name="lottery_prize_type" id="lotteryPrizeType" class="form-control" onchange="toggleLotteryPrizeValue()">
                            <option value="physical">🎁 实物奖品</option>
                            <option value="virtual">🎮 虚拟奖品</option>
                            <!-- 2026-09-01 积分奖品下拉文字 + 图标跟随后台 token 币种配置（图标渲染 span + SVG 字符，详见 PointService::currencyIconHtml） -->
                            <option value="point"><?= $lotteryCurrencyIcon ?> <?= e($lotteryCurrencyName) ?>奖品</option>
                        </select>
                    </div>
                    <div style="flex:1;min-width:200px;" id="lotteryValueTextWrap">
                        <label class="form-label">奖品价值<span class="required">*</span></label>
                        <input type="text" name="lottery_prize_value" id="lotteryPrizeValueText" class="form-control" placeholder="如 ¥99 / 价值 100 元">
                    </div>
                    <!-- 积分奖品专用：每份积分数 + 质押总额
                         关键设计（2026-09-01 修订）：「每份积分数」字段为前后两段控件，始终显示
                         - 前段：下拉 [自定义 / 随机]（lottery_point_mode）
                         - 后段：数字输入（lottery_point_input），按模式承担不同语义
                           * 自定义 = 每份积分数
                           * 随机   = 质押总额（系统发布时拆分成「每份积分数 = 质押总额 / 中奖人数」）
                         「质押总额」行随之自动更新预览（随机时直接 = 用户填值；自定义时 = unit × 中奖人数）。
                    -->
                    <div style="flex:1;min-width:280px;display:none;" id="lotteryValuePointWrap">
                        <label class="form-label">
                            <span id="lotteryPointModeLabel">每份<?= e($lotteryCurrencyName) ?>数</span><span class="required">*</span>
                        </label>
                        <div style="display:flex;align-items:stretch;gap:0;">
                            <select name="lottery_point_mode" id="lotteryPointMode" class="form-control"
                                    onchange="toggleLotteryPointMode()"
                                    style="flex:0 0 130px;border-top-right-radius:0;border-bottom-right-radius:0;border-right:none;">
                                <option value="custom">自定义</option>
                                <option value="random">随机</option>
                            </select>
                            <input type="number" name="lottery_point_input" id="lotteryPointInput" class="form-control"
                                   min="1" step="1" value="100"
                                   oninput="calcLotteryStake()"
                                   title="自定义：每份<?= e($lotteryCurrencyName) ?>数；随机：质押总额"
                                   style="border-top-left-radius:0;border-bottom-left-radius:0;flex:1;">
                        </div>
                    </div>
                    <div style="flex:1;min-width:200px;">
                        <label class="form-label">质押总额（<?= e($lotteryCurrencyName) ?>奖品从账户扣除）</label>
                        <input type="text" id="lotteryStakeTotal" class="form-control" value="0" readonly style="background:#fff;">
                    </div>
                </div>

                <!-- 奖品名 + 中奖人数 + 参与消耗 + 开奖时间
                     关键调整（2026-09-01）：去掉「奖品数量（份）」字段。一人一份奖品，「奖品数量」与「中奖人数」重叠；
                     留两个字段用户就会填错（如「1 份奖品 10 人分」），干脆去掉，让「中奖人数」成为唯一的人数指标。 -->
                <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-end;margin-top:12px;">
                    <div style="flex:1;min-width:160px;">
                        <label class="form-label">奖品名<span class="required">*</span></label>
                        <input type="text" name="lottery_prize_name" class="form-control" placeholder="如 100 <?= e($lotteryCurrencyName) ?> / 实体周边">
                    </div>
                    <div style="flex:1;min-width:140px;">
                        <label class="form-label">中奖人数<span class="required">*</span></label>
                        <input type="number" name="lottery_winner_count" id="lotteryWinnerCount" class="form-control" min="1" step="1" value="1" oninput="calcLotteryStake()">
                    </div>
                    <div style="flex:1;min-width:160px;">
                        <label class="form-label">参与消耗<?= e($lotteryCurrencyName) ?>（0=免费）</label>
                        <input type="number" name="lottery_join_cost" class="form-control" min="0" step="1" value="0">
                    </div>
                    <div style="flex:1;min-width:200px;">
                        <label class="form-label">开奖时间<span class="required">*</span></label>
                        <input type="datetime-local" name="lottery_draw_at" class="form-control" min="<?= date('Y-m-d\TH:i', time() + 900) ?>" required>
                    </div>
                </div>

                <!-- 抽奖规则：4 个下拉（关注我 / 回复15字以上 / 点赞主题 / 收藏主题），每项必须选一项 -->
                <div style="margin-top:12px;padding:10px 12px;background:#fff;border:1px dashed #ddd;border-radius:6px;">
                    <div style="font-size:13px;color:#555;margin-bottom:8px;font-weight:600;">抽奖规则（开奖时严格按规则筛选用户，不满足的直接 pass）</div>
                    <div style="display:flex;flex-wrap:wrap;gap:12px;">
                        <div style="display:flex;align-items:center;gap:6px;">
                            <span style="font-size:13px;color:#333;">关注我</span>
                            <select name="lottery_rule_follow" class="form-control" style="width:auto;">
                                <option value="no">不限</option>
                                <option value="optional">鼓励</option>
                                <option value="must" selected>必须</option>
                            </select>
                        </div>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <span style="font-size:13px;color:#333;">回复 15 字以上</span>
                            <select name="lottery_rule_reply" class="form-control" style="width:auto;">
                                <option value="no">不限</option>
                                <option value="optional">鼓励</option>
                                <option value="must" selected>必须</option>
                            </select>
                        </div>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <span style="font-size:13px;color:#333;">点赞主题</span>
                            <select name="lottery_rule_like" class="form-control" style="width:auto;">
                                <option value="no">不限</option>
                                <option value="optional">鼓励</option>
                                <option value="must">必须</option>
                            </select>
                        </div>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <span style="font-size:13px;color:#333;">收藏主题</span>
                            <select name="lottery_rule_favorite" class="form-control" style="width:auto;">
                                <option value="no">不限</option>
                                <option value="optional">鼓励</option>
                                <option value="must">必须</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 抽奖主题统一提示（2026-09-01 改用红色突出关键规则，特别是积分质押逻辑用户必须看清）。物理/虚拟/积分 自定义 / 积分 随机 三档。 -->
                <p class="form-hint" id="lotteryBalanceHint" style="margin-top:8px;color:#ea6f5a;line-height:1.6;background:#fff5f3;padding:8px 10px;border-radius:4px;border:1px solid #fbd9d0;"></p>
            </div>

            <!-- 投票主题面板 -->
            <div class="theme-panel" data-type="poll" style="display:none;margin-top:8px;padding:14px;background:#f8f9fa;border:1px solid #eee;border-radius:6px;">
                <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:center;margin-bottom:10px;">
                    <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:#555;"><input type="checkbox" name="poll_multi" value="1"> 多选</label>
                    <span style="font-size:12px;color:#999;padding:2px 8px;background:#f0f0f0;border-radius:8px;" title="产品要求隐藏投票人，不再支持匿名">不支持匿名投票</span>
                    <label style="font-size:13px;color:#555;">截止时间<input type="datetime-local" name="poll_deadline" class="form-control" style="display:inline-block;width:auto;margin-left:6px;" min="<?= date('Y-m-d\TH:i', time() + 900) ?>"></label>
                </div>
                <div id="pollOptions" style="display:flex;flex-direction:column;gap:8px;"></div>
                <button type="button" class="btn btn-ghost btn-sm" style="margin-top:8px;" onclick="addPollOption()">+ 添加选项</button>
                <p class="form-hint">至少 2 个选项；截止时间需不早于当前 15 分钟；发布后用户可投票，投票记录不可见身份。</p>
            </div>

            <!-- 辩论主题面板 -->
            <div class="theme-panel" data-type="debate" style="display:none;margin-top:8px;padding:14px;background:#f8f9fa;border:1px solid #eee;border-radius:6px;">
                <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start;">
                    <div style="flex:1;min-width:220px;">
                        <label class="form-label" style="color:#1e6fd9;">正方观点</label>
                        <textarea name="debate_pro" class="form-control" style="min-height:90px;" placeholder="简述正方立场"></textarea>
                    </div>
                    <div style="flex:1;min-width:220px;">
                        <label class="form-label" style="color:#e0852a;">反方观点</label>
                        <textarea name="debate_con" class="form-control" style="min-height:90px;" placeholder="简述反方立场"></textarea>
                    </div>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-end;margin-top:12px;">
                    <div style="flex:1;min-width:240px;">
                        <label class="form-label">辩论截止时间（之后不能再站队发言）</label>
                        <input type="datetime-local" name="debate_deadline" class="form-control" min="<?= date('Y-m-d\TH:i', time() + 900) ?>" required>
                    </div>
                </div>
                <p class="form-hint">用户首次站队需选择正/反方，之后可多次在该方发言，但不能切换站队。站队发言提交后显示在评论区，并在正方/反方区域套底色。</p>
            </div>

            <!-- 采访主题面板 -->
            <div class="theme-panel" data-type="interview" style="display:none;margin-top:8px;padding:14px;background:#f8f9fa;border:1px solid #eee;border-radius:6px;">
                <style>
                .iv-grid { display:grid; grid-template-columns:1fr 1fr; column-gap:18px; row-gap:12px; }
                .iv-cell { display:flex; flex-direction:column; min-width:0; }
                .iv-cell > label { margin-bottom:6px; }
                .iv-cell .iv-title-input,
                .iv-cell .iv-open-row { min-height:38px; }
                .iv-cell textarea { min-height:38px; padding:8px 10px; border:1px solid #ddd; border-radius:4px; font:inherit; resize:vertical; box-sizing:border-box; line-height:1.5; width:100%; }
                .iv-cell textarea:focus { outline:none; border-color:#ea6f5a; }
                .iv-open-row { display:flex; align-items:center; gap:6px; font-size:13px; color:#555; height:38px; padding:0 2px; }
                @media (max-width:680px){ .iv-grid { grid-template-columns:1fr; } }
                </style>
                <div class="iv-cell" style="margin-bottom:12px;">
                    <label class="form-label">采访主题 <span style="color:#ea6f5a;">*</span> <span style="color:#999;font-weight:normal;">（独立于帖子标题，显示在帖头「采访」字标右侧）</span></label>
                    <input type="text" name="interview_topic" class="form-control" placeholder="如 撒个网 / 创业者对话 / ……（最多 20 字）" maxlength="20" required>
                </div>
                <div class="iv-grid">
                    <div class="iv-cell">
                        <label class="form-label">记者 <span style="color:#999;font-weight:normal;">（默认你本人，可改成其他用户）</span></label>
                        <div class="user-picker" data-picker-id="reporter" style="position:relative;">
                            <input type="text" id="reporterName" class="form-control" placeholder="输入 uid / 用户名 / 昵称 搜索…" autocomplete="off">
                            <input type="hidden" name="reporter_id" id="reporterId" value="<?= (int)Auth::id() ?>">
                            <div class="user-picker-dropdown" id="reporterDropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:50;background:#fff;border:1px solid #ddd;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,.08);max-height:260px;overflow-y:auto;margin-top:2px;"></div>
                        </div>
                    </div>
                    <div class="iv-cell">
                        <label class="form-label">记者头衔</label>
                        <textarea name="reporter_title" id="reporterTitle" class="iv-title-input" placeholder="如 资深记者（可换行写多行头衔）" maxlength="200" rows="1"></textarea>
                    </div>
                    <div class="iv-cell">
                        <label class="form-label">受访者 <span style="color:#ea6f5a;">*</span></label>
                        <div class="user-picker" data-picker-id="interviewee" style="position:relative;">
                            <input type="text" id="intervieweeName" class="form-control" placeholder="输入 uid / 用户名 / 昵称 搜索…" autocomplete="off" required>
                            <input type="hidden" name="interviewee_id" id="intervieweeId" value="">
                            <div class="user-picker-dropdown" id="intervieweeDropdown" style="display:none;position:absolute;top:100%;left:0;right:0;z-index:50;background:#fff;border:1px solid #ddd;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,.08);max-height:260px;overflow-y:auto;margin-top:2px;"></div>
                        </div>
                    </div>
                    <div class="iv-cell">
                        <label class="form-label">受访者头衔</label>
                        <textarea name="interviewee_title" id="intervieweeTitle" class="iv-title-input" placeholder="如 产品经理（可换行写多行头衔）" maxlength="200" rows="1"></textarea>
                    </div>
                </div>
                <div class="iv-open-row" style="margin-top:8px;">
                    <input type="checkbox" name="interview_open" value="1"> 开放读者提问（关闭后只有记者可发起提问）
                </div>
                <div class="iv-open-row" style="margin-top:6px;">
                    <input type="checkbox" name="interview_allow_interviewee_ask" value="1"> 允许受访者提问（开启后受访者可向记者提问，由记者回答）
                </div>
                <p class="form-hint" style="margin-top:10px;">记者与受访者必须是网站注册用户。输入 <b>uid / 用户名 / 昵称</b> 均可搜索，下拉优先显示昵称，<b>不存在用户不允许选择</b>，需从下拉列表点击或键盘 ↓↑+Enter 选中。</p>
            </div>
            <?php if (setting('post_layout', 'default') === 'card' && can_upload_image()): ?>
            <?php /* 卡片式版式专属：封面图上传入口（列表版式不显示）。上传走 upload/image(type=post)，存 posts.cover_image 相对路径 */ ?>
            <div class="form-group" id="coverUploadGroup">
                <label class="form-label">封面图 <span class="required">*</span> <span style="color:#999;font-weight:normal;">（聚焦版式必填，作为卡片主视觉）</span></label>
                <input type="hidden" name="cover_image" id="coverImageInput" value="">
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
                var fileInput = document.getElementById('coverImageFile');
                var hidden = document.getElementById('coverImageInput');
                var preview = document.getElementById('coverPreview');
                var previewImg = document.getElementById('coverPreviewImg');
                var uploader = document.getElementById('coverUploader');
                var hint = document.getElementById('coverHint');
                var btn = document.getElementById('coverUploadBtn');
                if (!fileInput) return;
                document.getElementById('coverUploadBtn').addEventListener('click', function () { fileInput.click(); });
                fileInput.addEventListener('change', function () {
                    var f = fileInput.files && fileInput.files[0];
                    if (!f) return;
                    var fd = new FormData();
                    fd.append('file', f);
                    fd.append('type', 'post');
                    var orig = btn.textContent;
                    btn.disabled = true; btn.textContent = '上传中...';
                    postJSON(url('upload/image'), fd, function (res) {
                        btn.disabled = false; btn.textContent = orig;
                        if (res.code === 0 && res.data && res.data.url) {
                            hidden.value = res.data.url;
                            previewImg.src = window.absoluteAssetUrl ? window.absoluteAssetUrl(res.data.url) : res.data.url;
                            preview.style.display = 'block';
                            uploader.style.display = 'none';
                            if (hint) hint.textContent = '已设置封面图，发布后即可在聚焦版式生效';
                        } else {
                            toast((res && res.message) ? res.message : '上传失败，请重试', 'error');
                        }
                    }, function (res) {
                        btn.disabled = false; btn.textContent = orig;
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
            });
            </script>
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label">正文 <span class="required">*</span></label>
                <div class="editor-toolbar">
                    <button type="button" onclick="insertFormat('content','**','**')" title="加粗">加粗</button>
                    <button type="button" onclick="insertFormat('content','*','*')" title="斜体">斜体</button>
                    <span class="sep">|</span>
                    <button type="button" onclick="insertFormat('content','## ','')" title="标题">标题</button>
                    <button type="button" onclick="insertFormat('content','> ','')" title="引用">引用</button>
                    <span class="sep">|</span>
                    <button type="button" onclick="insertFormat('content','`','`')" title="行内代码">代码</button>
                    <button type="button" onclick="insertFormat('content','\n```\n','\n```')" title="代码块">代码块</button>
                    <span class="sep">|</span>
                    <button type="button" onclick="insertFormat('content','- ','')" title="列表项">列表</button>
                    <span class="sep">|</span>
                    <button type="button" onclick="insertFormat('content','[hide]','[/hide]')" title="回复可见">回复可见</button>
                    <span class="sep">|</span>
                    <button type="button" class="emoji-trigger" data-emoji-trigger data-emoji-target="content" title="插入表情（:code: 短代码，发表后自动渲染）"><i class="fa-regular fa-face-smile" aria-hidden="true"></i> 表情</button>
                    <?php if (can_upload_image()): ?><span class="sep">|</span>
                    <button type="button" id="insertImageBtn" title="在光标处插入图片（与下方图库相互独立）">🖼 插入图片</button><?php endif; ?>
                </div>
                <textarea name="content" id="content" class="form-control" data-mention="1" required style="min-height:300px;border-radius:0 0 4px 4px;" placeholder="支持 Markdown 语法&#10;请文明发言，遵守社区规范"></textarea>
                <input type="file" id="inlineImageInput" accept="image/*" multiple style="display:none;">
                <p class="form-hint">支持 Markdown：**加粗** *斜体* ## 标题 > 引用 `代码` - 列表 [链接](url)；可在正文中任意位置插入图片</p>
            </div>

            <?php if (can_upload_attach()): ?>
            <div class="form-group">
                <label class="form-label">附件（最多10个，每个不超过20MB）</label>
                <div id="attachList" style="margin-bottom:10px;"></div>
                <div id="attachUploader" style="display:inline-block;">
                    <button type="button" class="btn btn-ghost" onclick="document.getElementById('attachInput').click()">+ 添加附件</button>
                    <input type="file" id="attachInput" multiple style="display:none;">
                </div>
                <p class="form-hint">支持文档 / 压缩包 / 音视频等常见格式</p>
            </div>
            <?php endif; ?>

            <?php if (function_exists('captcha_scene_on') && captcha_scene_on('post')): ?>
            <div class="form-group">
                <div id="captchaMountPost" class="captcha-mount"></div>
            </div>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                var m = document.getElementById('captchaMountPost');
                if (m && window.Captcha) Captcha.ensure('post', m);
            });
            </script>
            <script>
            // 采访主题：用户选择器初始化 + 记者默认填当前用户
            document.addEventListener('DOMContentLoaded', function () {
                bindUserPicker('reporter');
                bindUserPicker('interviewee');
                // 记者默认 = 当前用户（从 PHP 注入）
                var rn = document.getElementById('reporterName');
                if (rn && !rn.value) rn.value = <?= json_encode(user_display_name(Auth::user()), JSON_UNESCAPED_UNICODE) ?>;
            });
            </script>
            <?php endif; ?>
            <script src="<?= asset('js/captcha.js') ?>"></script>
            <div class="form-group" style="margin-top:24px;">
                <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">发布</button>
                <a href="<?= url('home/index') ?>" class="btn btn-ghost">取消</a>
            </div>
        </form>
        </fieldset>
    </div>
</div>
<script>
// 特殊主题权限：按板块过滤可选主题类型
var specialAllowMap = <?= json_encode($specialAllowMap ?? []) ?>;
// 2026-09-01 抽奖积分奖品币种配置：name/icon 跟随后台设置联动，所有 JS 文案（含 toast/placeholder/hint）使用 LOTTERY_CURRENCY.name
var LOTTERY_CURRENCY = <?= json_encode([
    'code' => $lotteryCurrencyCode,
    'name' => $lotteryCurrencyName,
    'icon' => $lotteryCurrencyIcon,
], JSON_UNESCAPED_UNICODE) ?>;
var themeHints = {
  normal: '普通类型无需额外配置。',
  auction: '设置起拍价、最小加价与结拍时间后发布拍卖帖。',
  pay: '设置解锁价格，本帖附件将作为付费内容。',
  event: '设置活动时间与自定义报名字段，发起者可导出报名。',
  bounty: '设置每人悬赏积分与人数，发布时从账户扣除质押。',
  poll: '设置投票选项，发布后用户可参与投票。',
  debate: '填写正反方观点，用户可点击站队发言。',
  interview: '选择记者与受访者（必须为网站用户）；发布后通过"提问/回答"流程录入问答。',
  lottery: '设置奖品、中奖人数与开奖时间，用户可参与抽奖（积分奖品质押' + (typeof LOTTERY_CURRENCY !== "undefined" ? LOTTERY_CURRENCY.name : "积分") + '）。'
};
function allowedThemesFor(cid) {
  var m = specialAllowMap[cid] || {};
  var hasData = false;
  for (var k in m) { if (Object.prototype.hasOwnProperty.call(m, k)) { hasData = true; break; } }
  var arr = [];
  ['normal','auction','pay','event','bounty','poll','debate','interview','lottery'].forEach(function(t){
    if (t === 'normal') { arr.push(t); return; }
    // 该板块未配置权限映射时，默认全部放开（调试 / 向后兼容）
    if (!hasData || m[t] === 1) arr.push(t);
  });
  return arr;
}
function refreshThemeOptions() {
  var sel = document.querySelector('select[name=category_id]');
  var cid = sel ? sel.value : '';
  var allowed = allowedThemesFor(cid);
  document.querySelectorAll('#themeTypePills .theme-pill').forEach(function(p){
    p.style.display = (allowed.indexOf(p.getAttribute('data-type')) !== -1) ? '' : 'none';
  });
  var cur = document.getElementById('topicType').value;
  if (allowed.indexOf(cur) === -1) selectTheme('normal');
}
function selectTheme(type) {
  document.getElementById('topicType').value = type;
  document.querySelectorAll('.theme-panel').forEach(function(p){
    var active = (p.getAttribute('data-type') === type);
    p.style.display = active ? 'block' : 'none';
    // 关键修复（2026-09-01）：隐藏面板内的 required 字段（如 lottery_draw_at / debate_deadline）
    // 会触发浏览器原生表单校验，导致整个提交被静默拦截（按钮无任何变化、无请求）。
    // 隐藏时一并 disabled，既跳过校验也不随表单提交无关数据；切回时再启用。
    p.querySelectorAll('input,select,textarea,button').forEach(function(el){
      el.disabled = !active;
    });
  });
  document.querySelectorAll('#themeTypePills .theme-pill').forEach(function(p){
    p.classList.toggle('active', p.getAttribute('data-type') === type);
  });
  var hint = document.getElementById('themeHint');
  if (hint) hint.textContent = themeHints[type] || '';
  // 进入抽奖面板时同步奖品类型子控件的 required / 显隐状态
  if (type === 'lottery') toggleLotteryPrizeValue();
}
document.querySelectorAll('#themeTypePills .theme-pill').forEach(function(p){
  p.addEventListener('click', function(){ selectTheme(p.getAttribute('data-type')); });
});
// 板块切换联动主题选项
(function () {
    var sel = document.querySelector('select[name=category_id]');
    if (sel && !sel.value) {
        for (var i = 0; i < sel.options.length; i++) {
            if (sel.options[i].value) { sel.value = sel.options[i].value; break; }
        }
    }
    if (sel) sel.addEventListener('change', refreshThemeOptions);
    refreshThemeOptions();
    selectTheme('normal');
})();

// 悬赏质押总额
function calcBountyTotal() {
  var per = parseInt(document.getElementById('bountyPer').value || '0', 10);
  var people = parseInt(document.getElementById('bountyPeople').value || '0', 10);
  document.getElementById('bountyTotal').value = (per * people);
}

// 抽奖主题：根据奖品类型切换「奖品价值」输入形式（实物/虚拟=文本；积分=数字）
function toggleLotteryPrizeValue() {
  var t = document.getElementById('lotteryPrizeType');
  if (!t) return;
  var isPoint = (t.value === 'point');
  var textWrap = document.getElementById('lotteryValueTextWrap');
  var pointWrap = document.getElementById('lotteryValuePointWrap');
  var textEl = document.getElementById('lotteryPrizeValueText');
  if (textWrap) textWrap.style.display = isPoint ? 'none' : '';
  if (pointWrap) pointWrap.style.display = isPoint ? '' : 'none';
  // 必填属性随类型切换，避免积分类被一个空文本字段卡住
  if (textEl) textEl.required = !isPoint;
  // 积分类型时，同步初始化「每份积分数」的下拉（随机 / 自定义）
  if (isPoint) toggleLotteryPointMode();
  calcLotteryStake();
}

// 抽奖主题：实时计算「质押总额 = 每份积分数 × 中奖人数」，非积分品时始终为 0
// 关键逻辑（2026-09-01 修订）：后段输入 lotteryPointInput 按模式承担不同语义
//   - 自定义：输入 = 每份积分数，质押总额 = 输入 × 中奖人数
//   - 随机  ：输入 = 质押总额（由系统发布时决定每份积分数 = 总额 / 中奖人数）
function calcLotteryStake() {
  var t = document.getElementById('lotteryPrizeType');
  var out = document.getElementById('lotteryStakeTotal');
  if (!out) return;
  var stake = 0;
  if (t && t.value === 'point') {
    var modeEl = document.getElementById('lotteryPointMode');
    var mode = modeEl ? modeEl.value : 'custom';
    var inputVal = parseInt((document.getElementById('lotteryPointInput') || {}).value || '0', 10);
    if (inputVal < 1) inputVal = 0;
    var wc = parseInt((document.getElementById('lotteryWinnerCount') || {}).value || '0', 10);
    if (wc < 1) wc = 0;
    if (mode === 'random') {
      // 随机模式：input 直接就是质押总额；预览也用同一个值。
      stake = inputVal;
    } else {
      // 自定义模式：input 是每份积分数
      stake = inputVal * wc;
    }
  }
  out.value = stake > 0 ? String(stake) : '0';
  // 统一提示（2026-09-01 删了原本的两段红提示，集中到这里；切奖品类型 / 积分模式 / 数值时均更新）
  var hint = document.getElementById('lotteryBalanceHint');
  if (hint) {
    var type = t ? t.value : 'physical';
    var modeEl2 = document.getElementById('lotteryPointMode');
    var mode2 = modeEl2 ? modeEl2.value : 'custom';
    if (type === 'point' && mode2 === 'random') {
      hint.textContent = stake > 0
        ? LOTTERY_CURRENCY.name + '奖品（随机）：将从您的论坛' + LOTTERY_CURRENCY.name + '账户扣除 ' + stake + ' ' + LOTTERY_CURRENCY.name + '作为质押总额；开奖时把总额随机分给实际中奖者（每人不同、≥1 ' + LOTTERY_CURRENCY.name + '），不返还未发完部分；要求「总额 ≥ 中奖人数」（人均至少 1 ' + LOTTERY_CURRENCY.name + '）。'
        : LOTTERY_CURRENCY.name + '奖品（随机）：请在后段填写质押总额（≥ 中奖人数）。无人符合时部分' + LOTTERY_CURRENCY.name + ' 50% 返还。';
    } else if (type === 'point') {
      hint.textContent = stake > 0
        ? LOTTERY_CURRENCY.name + '奖品（自定义）：将从您的论坛' + LOTTERY_CURRENCY.name + '账户扣除 ' + stake + ' ' + LOTTERY_CURRENCY.name + '作为质押（每份 ' + inputVal + ' × 中奖人数）；开奖后未发完部分 50% 返还作者。'
        : LOTTERY_CURRENCY.name + '奖品（自定义）：请在后段填写「每份' + LOTTERY_CURRENCY.name + '数」。';
    } else {
      hint.textContent = '实物/虚拟奖品仅作展示，不参与质押；如需伴随' + LOTTERY_CURRENCY.name + '可切换「奖品类型 = ' + LOTTERY_CURRENCY.name + '」并填写奖励规则。';
    }
  }
}

/**
 * 「每份积分数」下拉切换（2026-09-01 修订）：前后段控件始终显示，后段 input 按模式切换语义
 *   - 自定义：placeholder / label = "每份积分数"，预览 = input × 中奖人数
 *   - 随机  ：placeholder / label = "质押总额（积分）"，预览 = input 本身
 */
function toggleLotteryPointMode() {
  var modeEl = document.getElementById('lotteryPointMode');
  var inputEl = document.getElementById('lotteryPointInput');
  var labelEl = document.getElementById('lotteryPointModeLabel');
  if (!modeEl) return;
  var isRandom = (modeEl.value === 'random');
  // 前后段控件始终显示，仅切换 label / placeholder / title 提示；具体质押规则由
  // #lotteryBalanceHint 统一提示，不要再开局部红 chip 了（2026-09-01 取消了双段提示）
  if (inputEl) {
    if (isRandom) {
      inputEl.placeholder = '质押总额（' + LOTTERY_CURRENCY.name + '）';
      inputEl.title = '此处填的是您愿意质押的总额；开奖时把总额随机分给实际中奖者（每人不同）';
    } else {
      inputEl.placeholder = '每份' + LOTTERY_CURRENCY.name + '数';
      inputEl.title = '每位中奖者拿到的' + LOTTERY_CURRENCY.name + '数（每人相同）';
    }
  }
  if (labelEl) labelEl.textContent = isRandom ? '质押总额（' + LOTTERY_CURRENCY.name + '）' : '每份' + LOTTERY_CURRENCY.name + '数';
  calcLotteryStake();
}
toggleLotteryPrizeValue();

// 付费主题：实时求和「正文+附件」两项价格，作为解锁总价
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
  var out = document.getElementById('payTotalPreview');
  if (out) out.value = sum;
}
['pay_content_price', 'pay_attachment_price'].forEach(function (n) {
  var el = document.querySelector('input[name=' + n + ']');
  if (el) el.addEventListener('input', calcPayTotal);
});
calcPayTotal();

// 活动自定义字段
// 关键修正（2026-08-31）：原实现只有「字段名 / 类型 / 必填」三个控件，类型下拉里虽然有「下拉」，
// 但没有任何地方能填选项 —— 导致发布出来的下拉字段只有一个「请选择」，报名者无从选择。
// 这里补一个「选项」输入框（英文逗号分隔），仅在类型切到 select 时显示，避免其它类型占地方。
function addEventField() {
  var box = document.getElementById('eventFields');
  var row = document.createElement('div');
  row.setAttribute('data-cf-row', '1');
  row.style.cssText = 'border:1px solid #eee;border-radius:6px;padding:8px;margin-bottom:8px;background:#fafafa;';
  row.innerHTML =
    '<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">' +
      '<input class="form-control" style="flex:2;min-width:140px;" placeholder="字段名（如 手机号）" data-cf-label>' +
      '<select class="form-control" style="flex:1;min-width:100px;" data-cf-type>' +
        '<option value="text">单行文本</option><option value="textarea">多行文本</option>' +
        '<option value="select">下拉选择</option><option value="number">数字</option><option value="date">日期</option>' +
      '</select>' +
      '<label style="display:flex;align-items:center;gap:4px;font-size:12px;color:#555;white-space:nowrap;">' +
        '<input type="checkbox" data-cf-required> 必填</label>' +
      // 注意：删除按钮必须用 closest 找整行。整行现在是「外层 data-cf-row → 内层 flex 容器」两层
      // 结构，若沿用 this.parentNode.remove() 只会删掉内层容器，留下一个空壳。
      '<button type="button" class="btn btn-ghost btn-sm" onclick="this.closest(\'[data-cf-row]\').remove()">删除</button>' +
    '</div>' +
    '<div data-cf-opts-wrap style="display:none;margin-top:6px;">' +
      '<input class="form-control" placeholder="下拉选项，用英文逗号分隔（如 男,女,其他）" data-cf-options>' +
      '<div style="font-size:12px;color:#999;margin-top:4px;">多个选项之间用英文逗号分隔；不填则发布后该下拉没有可选项。</div>' +
    '</div>';
  box.appendChild(row);

  // 类型切换：只有 select 才展开选项输入框
  var typeSel = row.querySelector('[data-cf-type]');
  var optsWrap = row.querySelector('[data-cf-opts-wrap]');
  typeSel.addEventListener('change', function () {
    optsWrap.style.display = (typeSel.value === 'select') ? '' : 'none';
  });
}

/**
 * 收集活动自定义报名字段。
 * 独立成函数，供提交流程复用；返回 null 表示校验不通过（调用方需中止提交）。
 */
function collectEventFields() {
  var fields = [];
  var rows = document.querySelectorAll('#eventFields > div[data-cf-row]');
  for (var i = 0; i < rows.length; i++) {
    var row = rows[i];
    var label = (row.querySelector('[data-cf-label]').value || '').trim();
    if (!label) continue;                       // 没填字段名的行视为空行，直接忽略
    var type = row.querySelector('[data-cf-type]').value;
    var opts = [];
    if (type === 'select') {
      var optInput = row.querySelector('[data-cf-options]');
      var raw = optInput ? (optInput.value || '') : '';
      // 同时兼容英文逗号与中文逗号，避免用户手滑打成全角
      opts = raw.split(/[,，]/).map(function (s) { return s.trim(); }).filter(function (s) { return s !== ''; });
      if (opts.length === 0) {
        toast('字段「' + label + '」类型为下拉，请填写至少一个选项（多个用英文逗号分隔）', 'error');
        return null;
      }
    }
    fields.push({
      label: label,
      type: type,
      required: row.querySelector('[data-cf-required]').checked ? 1 : 0,
      options: opts
    });
  }
  // 字段名重复会让后端 cf_ + md5(label) 撞车，报名数据互相覆盖
  var seen = {};
  for (var j = 0; j < fields.length; j++) {
    if (seen[fields[j].label]) {
      toast('自定义字段名「' + fields[j].label + '」重复，请修改', 'error');
      return null;
    }
    seen[fields[j].label] = 1;
  }
  return fields;
}

// 投票选项
function addPollOption() {
  var box = document.getElementById('pollOptions');
  var row = document.createElement('div');
  row.style.cssText = 'display:flex;gap:8px;align-items:center;';
  row.innerHTML = '<input class="form-control" placeholder="选项内容" data-poll-opt><button type="button" class="btn btn-ghost btn-sm" onclick="this.parentNode.remove()">×</button>';
  box.appendChild(row);
}

// 采访主题：用户选择器（autocomplete，复用 user/mentionCandidates 接口做站内用户搜索）
//   输入：uid / 用户名 / 昵称 三种关键字都可（mentionCandidates 接口已支持）。下拉只显示昵称 + @username，**不显示 UID**。
//   必须从下拉选中才会写 hidden user_id，否则表单提交时会被兜底拦截并提示。
//   键盘：↓↑ 移动高亮，Enter 直接选中，Esc 关闭。
window.__userPickerState = {};
function bindUserPicker(pickerId) {
  var wrap = document.querySelector('.user-picker[data-picker-id="' + pickerId + '"]');
  if (!wrap) return;
  var nameEl  = wrap.querySelector('input[type="text"]');
  var idEl    = wrap.querySelector('input[type="hidden"]');
  var dropEl  = wrap.querySelector('.user-picker-dropdown');
  if (!nameEl || !idEl || !dropEl) return;
  // 本地 escapeHtml：避免依赖 mention.js 的 IIFE 局部函数（项目里 picker 是独立组件）
  function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  // selectedId 独立于 items，记住「用户选过的最后一次有效 id」（独立记忆，避免被 close()/search() 抹掉）
  var st = { timer: null, lastQ: '', items: [], activeIdx: -1, selectedId: 0 };
  window.__userPickerState[pickerId] = st;
  // 初始：如果表单 hidden 已经有值（PHP 渲染时填充，如记者默认 = 当前用户），同步进 selectedId
  if (idEl && idEl.value && /^\d+$/.test(idEl.value)) st.selectedId = parseInt(idEl.value, 10);
  function close() { dropEl.style.display = 'none'; dropEl.innerHTML = ''; st.items = []; st.activeIdx = -1; }
  function highlight(idx) {
    var nodes = dropEl.querySelectorAll('.user-picker-item');
    nodes.forEach(function(n, i){ n.style.background = (i === idx) ? '#fff5f3' : '#fff'; });
    st.activeIdx = idx;
  }
  function render(list) {
    st.items = list || [];
    st.activeIdx = list && list.length ? 0 : -1;
    if (!list || !list.length) {
      dropEl.innerHTML = '<div style="padding:12px 10px;color:#999;font-size:13px;line-height:1.5;">无匹配用户<br><span style="color:#bbb;font-size:12px;">不存在的用户不可选择，请检查 uid / 用户名 / 昵称</span></div>';
      dropEl.style.display = 'block';
      return;
    }
    var html = '';
    list.forEach(function(u, i) {
      var avatar = u.avatar ? u.avatar : '';
      var avatarHtml = avatar
        ? '<img src="' + escapeHtml(avatar) + '" style="width:32px;height:32px;border-radius:50%;object-fit:cover;background:#eee;">'
        : '<span style="width:32px;height:32px;border-radius:50%;background:#ea6f5a;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:14px;font-weight:600;">' + escapeHtml((u.nickname || u.username || '?').charAt(0)) + '</span>';
      var displayName = u.nickname || u.username || '';
      var secondary = (u.nickname && u.username && u.nickname !== u.username) ? '@' + escapeHtml(u.username) : '';
      html += '<div class="user-picker-item" data-uid="' + parseInt(u.id, 10) + '" data-name="' + escapeHtml(displayName) + '" data-idx="' + i + '" style="display:flex;align-items:center;gap:10px;padding:8px 10px;cursor:pointer;border-bottom:1px solid #f5f5f5;' + (i === 0 ? 'background:#fff5f3;' : '') + '">'
            +   avatarHtml
            +   '<div style="flex:1;min-width:0;"><div style="font-size:14px;color:#222;font-weight:600;">' + escapeHtml(displayName) + '</div>'
            +   (secondary ? '<div style="font-size:12px;color:#999;">' + secondary + '</div>' : '')
            + '</div></div>';
    });
    dropEl.innerHTML = html;
    dropEl.style.display = 'block';
  }
  function search(q) {
    if (q === st.lastQ) return;
    st.lastQ = q;
    if (!q) { close(); return; }
    var apiUrl = (typeof url === 'function' ? url('user/mentionCandidates', {q: q}) : '/index.php?r=user%2FmentionCandidates&q=' + encodeURIComponent(q));
    var xhr = new XMLHttpRequest();
    xhr.open('GET', apiUrl, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onload = function() {
      try {
        var res = JSON.parse(xhr.responseText);
        if (res && res.code === 0 && res.data && Array.isArray(res.data.list)) { render(res.data.list); }
        else { render([]); }
      } catch (e) { close(); }
    };
    xhr.onerror = function() { close(); };
    xhr.send();
  }
  function pickItem(item) {
    if (!item) return;
    var uid = parseInt(item.getAttribute('data-uid'), 10);
    nameEl.value = item.getAttribute('data-name') || '';
    idEl.value   = uid || '';
    st.selectedId = uid || 0;     // 独立记忆，不依赖 st.items
    close();
  }
  nameEl.addEventListener('input', function() {
    // 用户编辑名称时清掉 user_id + selectedId，强制重新选择
    if (idEl.value) idEl.value = '';
    st.selectedId = 0;
    clearTimeout(st.timer);
    var v = nameEl.value.trim();
    st.timer = setTimeout(function() { search(v); }, 250);
  });
  nameEl.addEventListener('focus', function() {
    var v = nameEl.value.trim();
    if (v) search(v);
  });
  // 键盘导航：↓↑切换高亮，Enter 选中，Esc 关闭
  nameEl.addEventListener('keydown', function(e) {
    if (dropEl.style.display === 'none' || !st.items.length) return;
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      highlight((st.activeIdx + 1) % st.items.length);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      highlight((st.activeIdx - 1 + st.items.length) % st.items.length);
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (st.activeIdx >= 0 && st.activeIdx < st.items.length) {
        var nodes = dropEl.querySelectorAll('.user-picker-item');
        if (nodes[st.activeIdx]) pickItem(nodes[st.activeIdx]);
      }
    } else if (e.key === 'Escape') {
      close();
      nameEl.blur();
    }
  });
  // mousedown 而不是 click：避免 nameEl 先 blur 关闭下拉
  dropEl.addEventListener('mousedown', function(e) {
    var item = e.target.closest('.user-picker-item');
    if (!item) return;
    e.preventDefault();
    pickItem(item);
  });
  // 点外面关闭
  document.addEventListener('click', function(e) {
    if (!wrap.contains(e.target)) close();
  });
}
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
                } else {
                    toast((res && res.message) || '上传失败', 'error');
                }
            } else {
                toast('上传失败，请重试', 'error');
            }
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

/* ===== 附件上传 ===== */
var uploadedAttachments = [];
var MAX_ATTACH = 10;
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
                else { toast(res.message || '上传失败', 'error'); }
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

// 抽奖主题：客户端预校验（与服务端 store() 校验一一对应，避免无意义的请求往返）
function validateLotteryPanel() {
  var typeEl = document.getElementById('lotteryPrizeType');
  if (!typeEl) return true;       // 抽奖面板没显示
  var type = typeEl.value;
  // 「中奖人数」是唯一的人数指标（2026-09-01 去掉「奖品数量（份）」字段，避免与中奖人数冲突）
  var wc = parseInt((document.getElementById('lotteryWinnerCount') || {}).value || '0', 10);
  if (wc <= 0) { toast('请填写中奖人数（大于 0）', 'error'); return false; }
  if (type === 'point') {
    // 「每份积分数」= 前后两段控件：下拉选模式 + 数字输入（始终必填）
    // - 自定义：数字 = 每份积分数
    // - 随机  ：数字 = 质押总额（≥ 中奖人数，按平均 1 解锁），开奖时把总数随机分给 actual 个人，每人不同
    var modeEl = document.getElementById('lotteryPointMode');
    var mode = modeEl ? modeEl.value : 'custom';
    var inputEl = document.getElementById('lotteryPointInput');
    var inputVal = inputEl ? parseInt(inputEl.value || '0', 10) : 0;
    var wc2 = parseInt((document.getElementById('lotteryWinnerCount') || {}).value || '0', 10);
    if (inputVal <= 0) {
      toast(mode === 'random' ? '请填写「质押总额」（大于 0）' : '请填写「每份' + LOTTERY_CURRENCY.name + '数」（大于 0）', 'error');
      return false;
    }
    if (mode === 'random' && inputVal < wc2) {
      toast('「质押总额」必须 ≥ 中奖人数（' + wc2 + '），平均每人至少 1 ' + LOTTERY_CURRENCY.name, 'error');
      return false;
    }
  } else {
    var v = ((document.getElementById('lotteryPrizeValueText') || {}).value || '').trim();
    if (!v) { toast('请填写奖品价值（如 ¥99 / 价值 100 元）', 'error'); return false; }
  }
  return true;
}

document.getElementById('postForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.textContent = '发布中...';
    // 抽奖主题预校验（仅在 lottery 面板显示时执行）
    if (document.getElementById('topicType').value === 'lottery' && !validateLotteryPanel()) {
      btn.disabled = false;
      btn.textContent = '发布';
      return;
    }
    var mount = document.getElementById('captchaMountPost');
    // 真正的提交逻辑（抽出来，验证码通过 / 不需要验证码时共用）
    var doSubmit = function(sol) {
        var fd = new FormData(document.getElementById('postForm'));
        var data = {};
        fd.forEach(function(v, k) { data[k] = v; });
        data.attachments = JSON.stringify(uploadedAttachments);
        // 收集特殊主题的动态数组字段。
        // 关键修正（2026-08-31）：原来这里硬编码 options: []，无论发布页填了什么都存不进选项，
        // 报名端渲染出的下拉永远只有一个「请选择」。改为调 collectEventFields() 真正收集。
        var evFields = collectEventFields();
        if (evFields === null) {                 // 校验不通过（下拉没填选项 / 字段名重复）
            btn.disabled = false;
            btn.textContent = '发布';
            return;
        }
        data.custom_fields = evFields;
        data.poll_options = [];
        document.querySelectorAll('#pollOptions [data-poll-opt]').forEach(function(inp){
            var v = inp.value.trim(); if (v) data.poll_options.push(v);
        });
        // 卡片式版式：封面图必填（页面渲染了 #coverImageInput 即说明处于卡片版式）
        var coverInput = document.getElementById('coverImageInput');
        if (coverInput && !coverInput.value) {
            toast('请先上传封面图再发布（聚焦版式必须设置封面）', 'error');
            btn.disabled = false; btn.textContent = '发布';
            document.getElementById('coverUploadGroup').scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        // 采访主题：reporter_id / interviewee_id 已在表单 hidden input 里，由 FormData 自动序列化；
        //   提交前做三重兜底：① 名称非空 ② hidden id 非空（必须从下拉选中） ③ name 与 hidden id 一致（防 ID 篡改/手填）
        if (data.topic_type === 'interview') {
            var rn = document.getElementById('reporterName');   var rid = document.getElementById('reporterId');
            var in_ = document.getElementById('intervieweeName'); var iid = document.getElementById('intervieweeId');
            if (in_ && !in_.value.trim()) { toast('请填写受访者', 'error'); btn.disabled = false; btn.textContent = '发布'; return; }
            if (!iid || !iid.value) { toast('请从下拉列表中选择受访者（不存在的用户不可选择）', 'error'); btn.disabled = false; btn.textContent = '发布'; if (in_) in_.focus(); return; }
            if (rn && !rn.value.trim()) { toast('请填写记者', 'error'); btn.disabled = false; btn.textContent = '发布'; return; }
            if (!rid || !rid.value) { toast('请从下拉列表中选择记者（不存在的用户不可选择）', 'error'); btn.disabled = false; btn.textContent = '发布'; if (rn) rn.focus(); return; }
            // 数据集一致性：用 picker 自身的 selectedId（独立记忆，被 close() 也保留），
            //   而非从 st.items 中查（items 会在 close() 时被清空，无法作为持久来源）
            var stI = window.__userPickerState && window.__userPickerState['interviewee'];
            var matchI = stI && parseInt(stI.selectedId || '0', 10) === parseInt(iid.value || '0', 10) && parseInt(iid.value || '0', 10) > 0;
            if (!matchI) { toast('受访者数据异常，请重新搜索并从下拉中选择', 'error'); btn.disabled = false; btn.textContent = '发布'; return; }
            var stR = window.__userPickerState && window.__userPickerState['reporter'];
            var curRid = parseInt(rid.value || '0', 10);
            var matchR = stR && parseInt(stR.selectedId || '0', 10) === curRid && curRid > 0;
            // 记者默认值例外：开页时 PHP 已把 Auth::id() 灌入 hidden，picker 初始化也已同步 selectedId，
            //   正常人不会去改记者；万一 selectedId 被 input 事件清掉但 rid 还是默认值，仍放过
            if (!matchR) {
                var defRid = parseInt(rid.defaultValue || '0', 10);
                if (curRid !== defRid || defRid <= 0) { toast('记者数据异常，请重新搜索并从下拉中选择', 'error'); btn.disabled = false; btn.textContent = '发布'; return; }
            }
        }
        if (sol) { data.captcha_token = sol.token; data.captcha_x = sol.x; }
        postJSON(url('post/store'), data, function(res) {
            if (res.code === 0) {
                toast('发布成功', 'success');
                if (res.data && res.data.points) showPointsToast(res.data.points);
                setTimeout(function() { window.location.href = res.data.redirect; }, 600);
            } else {
                toast(res.message || '发布失败', 'error');
                btn.disabled = false;
                btn.textContent = '发布';
            }
        }, function(res) {
            toast(res.message || '网络错误', 'error');
            btn.disabled = false;
            btn.textContent = '发布';
        });
    };
    var failCaptcha = function(msg) {
        toast(msg || '请完成滑块验证', 'error');
        btn.disabled = false;
        btn.textContent = '发布';
        if (mount) { mount.removeAttribute('data-solved'); mount._capPromise = null; }
    };
    // 验证码：仅当场景开启（mount 存在）且 captcha.js 已加载时才走滑块；
    // 否则直接提交，避免「验证码未配置/未加载」把整次发布卡死（表现为按钮无任何反应）。
    if (mount && window.Captcha && typeof Captcha.ensure === 'function') {
        Captcha.ensure('post', mount).then(doSubmit).catch(failCaptcha);
    } else {
        doSubmit(null);
    }
});
</script>
