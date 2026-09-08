<?php
/** 积分系统配置（仅站长）
 *  五个 tab：
 *   - 积分规则配置：point_rules 表的数值/启停/区间/上限 + 风控频控
 *   - 会员等级配置：user_levels 表的等级名称/所需Byte/加成/特权，支持自定义并动态新增等级
 *   - 币种管理：currencies 表的图标/名称/类型/启停
 *   - 打赏配置：打赏金额/上限/频次
 *   - 充值中心：卡密管理/折扣活动/支付通道配置/充值订单（子 tab）
 */
$title = $title ?? '积分系统';
// 优先使用 controller 注入的 $currentTab（充值中心 4 个子页用），否则从 $_GET['tab'] 推导
$allowedTabs = ['currencies', 'points', 'levels', 'reward', 'recharge'];
if (!isset($currentTab)) {
    $currentTab = isset($_GET['tab']) ? trim((string)$_GET['tab']) : 'currencies';
}
if (!in_array($currentTab, $allowedTabs, true)) $currentTab = 'currencies';
// 用 url() 始终带 r=admin/pointsSystem，不会因相对路径 ?tab=xxx 覆盖 r 参数导致跳首页
$tabUrl = function ($t) { return url('admin/pointsSystem', ['tab' => $t]); };

// 充值中心子 tab：默认 cards，可由 controller 注入 $rechargeSub
$rechargeSubAllowed = ['cards', 'campaigns', 'config', 'orders'];
// $rcSubUrl 必须在顶部 tab 引用，因此提到条件块外
$rcSubUrl = function ($s) { return url('admin/recharge' . ucfirst($s)); };
if ($currentTab === 'recharge') {
    if (!isset($rechargeSub)) {
        $rechargeSub = isset($_GET['sub']) ? trim((string)$_GET['sub']) : 'cards';
    }
    if (!in_array($rechargeSub, $rechargeSubAllowed, true)) $rechargeSub = 'cards';
}
?>
<style>
.tabs-nav { display:flex; gap:0; border-bottom:2px solid #eee; margin-bottom:16px; }
.tabs-nav a { padding:12px 24px; color:#666; text-decoration:none; font-size:14px; font-weight:500; border-bottom:3px solid transparent; margin-bottom:-2px; transition:all .15s; }
.tabs-nav a:hover { color:#ea6f5a; }
.tabs-nav a.active { color:#ea6f5a; border-bottom-color:#ea6f5a; font-weight:600; }
.tab-pane { display:none; }
.tab-pane.active { display:block; }
.level-row-highlight { background:#fff8f6; }
.cc-row-highlight { background:#fff8f6; }
.cur-ico { width:30px; height:30px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; color:#fff; font-weight:500; font-size:13px; flex-shrink:0; }
/* 充值中心子 tab（二级导航），用更紧凑的间距与下划线 */
.rc-subtabs { display:flex; gap:0; margin:0 0 16px; border-bottom:1px solid #eee; background:#fafafa; border-radius:6px 6px 0 0; }
.rc-subtabs a { padding:10px 18px; color:#666; text-decoration:none; font-size:13px; border-bottom:2px solid transparent; margin-bottom:-1px; transition:all .15s; }
.rc-subtabs a:hover { color:#ea6f5a; }
.rc-subtabs a.active { color:#ea6f5a; border-bottom-color:#ea6f5a; background:#fff; font-weight:600; }
/* ===== 充值中心：所有子页卡片内容统一用 .card-body 包裹，复用全局 .card-body{padding:20px} ===== */
.rc-pane { margin-top: 0; padding-bottom: 60px; }
.rc-pane > .card { margin-top: 16px; }
.rc-pane > .card:first-child { margin-top: 0; }

/* 两列表单（生成卡密 / 新增活动）*/
.rc-pane .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 0 28px; }
.rc-pane .two-col > div > label { display:block; margin: 16px 0 0; font-size:13px; color:#555; }
.rc-pane .two-col > div > label:first-child { margin-top: 0; }
.rc-pane .two-col > div > .form-control { margin: 6px 0 0; width:100%; box-sizing:border-box; }
.rc-pane .two-col > div > .note.blue { margin: 14px 0 0; padding:12px 14px; background:#f0f7ff; border-left:3px solid #1890ff; border-radius:4px; color:#444; font-size:13px; line-height:1.7; }

/* 统计 4 格 */
.rc-pane .stat-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; }
.rc-pane .stat { text-align:center; padding:16px 8px; background:#fafafa; border-radius:4px; border:1px solid #f0f0f0; }
.rc-pane .stat .num { font-size:26px; font-weight:600; color:#333; line-height:1.2; }
.rc-pane .stat .label { font-size:12px; color:#999; margin-top:4px; }

/* 提示块（note blue）*/
.rc-pane .card-body > .note.blue { margin: 14px 0 0; padding: 12px 14px; background:#f0f7ff; border-left:3px solid #1890ff; border-radius:4px; color:#444; font-size:13px; line-height:1.7; }
.rc-pane .card-body > .note.blue:first-child { margin-top: 0; }

/* 方案启用/停用横幅 */
.rc-pane .scheme-status { margin: 0 0 14px; padding: 10px 14px; border-radius:4px; background:#fff7f5; border-left:3px solid #ea6f5a; color:#8a4a3a; font-size:13px; }
.rc-pane .scheme-status:empty { display: none; }

/* 工具栏（筛选/操作条）*/
.rc-pane .toolbar { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:16px; }
.rc-pane .toolbar > div:last-child { margin-left:auto; }

/* 翻页器 */
.rc-pane .pager { margin-top:16px; display:flex; gap:6px; flex-wrap:wrap; align-items:center; }

/* 卡片内独立 label/input/button（方案卡片、订单筛选）保留自然间距 */
.rc-pane .card-body > label { display:block; margin: 16px 0 0; font-size:13px; color:#555; }
.rc-pane .card-body > label:first-child { margin-top: 0; }
.rc-pane .card-body > .form-control { display:block; width:100%; box-sizing:border-box; margin: 6px 0 0; }
.rc-pane .card-body > .btn { margin: 16px 0 0; }

/* 生成卡密表单：单列全宽布局（避免桌面端/平板上一行挤 6 项） */
.rc-pane .gen-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px 24px; margin-bottom: 8px; }
.rc-pane .gen-row label { display:block; margin: 0 0 6px; font-size:13px; color:#555; }
.rc-pane .gen-row .form-control { width: 100%; box-sizing: border-box; }
@media (max-width: 768px) { .rc-pane .gen-grid { grid-template-columns: 1fr; } }

/* ====== 基础比例区块：按币种分别配置 ====== */
.rc-currency-rates {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 12px 16px;
    margin: 14px 0 0;
}
.rc-currency-rate {
    background: #fafafa;
    border: 1px solid #f0f0f0;
    border-radius: 6px;
    padding: 12px 14px;
}
.rc-currency-rate label {
    display: block;
    margin: 0 0 6px;
    font-size: 13px;
    color: #555;
    font-weight: 600;
}
@media (max-width: 480px) {
    .rc-currency-rates { grid-template-columns: 1fr; }
}
/* 兼容历史：现有 <table> 没加 .rc-table 时仍给一份兜底 padding，避免「卡密 / 订单」表头全挤一行 */
.rc-pane .card-body > table:not(.rc-table) thead th {
    padding: 12px 14px;
    white-space: nowrap;
    background: #fafafa;
    border-bottom: 1px solid #ebebeb;
    text-align: left;
    font-weight: 600;
    color: #666;
}
.rc-pane .card-body > table:not(.rc-table) tbody td {
    padding: 12px 14px;
    border-bottom: 1px solid #f5f5f5;
    vertical-align: middle;
}

/* 支付通道配置 · 三方案卡片互斥高亮 */
.rc-scheme { position: relative; transition: box-shadow .15s; }
.rc-scheme.is-enabled {
    border-color: #ea6f5a;
    box-shadow: 0 0 0 1px #ea6f5a inset, 0 4px 14px rgba(234,111,90,.10);
}
/* 已启用横幅：实心品牌红 + 白字，圆角胶囊 */
.rc-scheme .scheme-status.is-enabled {
    background: #ea6f5a;
    border-left: none;
    color: #fff;
    padding: 10px 16px;
    font-weight: 600;
    letter-spacing: .5px;
    box-shadow: 0 2px 8px rgba(234,111,90,.25);
}
/* 启用按钮：默认描边样式，已启用（btn-primary 状态）走品牌红 */
.rc-scheme .rc-enable-btn.is-enabled {
    background: #ea6f5a;
    color: #fff;
    border-color: #ea6f5a;
    font-weight: 600;
}
.rc-scheme .rc-enable-btn.is-enabled:hover {
    background: #d75a45;
    border-color: #d75a45;
}
.rc-scheme .btn:disabled { opacity:.45; cursor:not-allowed; }

/* ===== 表格统一样式：避免「卡密 / 订单」列表表头全挤一行 ===== */
.rc-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 13px;
    background: #fff;
}
.rc-table thead th {
    background: #fafafa;
    color: #666;
    font-weight: 600;
    text-align: left;
    padding: 12px 14px;
    border-bottom: 1px solid #ebebeb;
    white-space: nowrap;
    line-height: 1.4;
}
.rc-table tbody td {
    padding: 12px 14px;
    border-bottom: 1px solid #f5f5f5;
    vertical-align: middle;
    line-height: 1.5;
    word-break: break-word;
}
.rc-table tbody tr:hover { background: #fcfcfc; }
.rc-table tbody tr:last-child td { border-bottom: none; }
.rc-table code { background:#f7f7f7; padding:1px 5px; border-radius:3px; font-size:12px; }

/* ===== 收款码上传控件 ===== */
.rc-upload {
    display: flex; gap: 14px; align-items: flex-start; flex-wrap: wrap;
    margin: 6px 0 0;
}
.rc-upload-preview {
    width: 120px; height: 120px;
    border: 2px dashed #d9d9d9; border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    background: #fafafa; overflow: hidden; flex-shrink: 0;
    color: #bbb; font-size: 12px;
}
.rc-upload-preview img { width: 100%; height: 100%; object-fit: contain; }
.rc-upload-actions { display: flex; flex-direction: column; gap: 6px; min-width: 200px; }
.rc-upload-actions .btn {
    background:#fff1ee; color:#ea6f5a; font-size:13px;
    padding: 6px 12px; cursor: pointer;
}
.rc-upload-actions .btn:hover { background:#fde4dd; }
.rc-upload-actions input[type=file] { display: none; }
.rc-upload-actions .url-manual {
    margin-top: 4px;
}
.rc-upload-actions .url-manual input {
    width: 100%; box-sizing: border-box;
    padding: 6px 10px; font-size: 12px;
    border: 1px solid #e5e5e5; border-radius: 4px;
}
.rc-upload-msg { font-size: 12px; margin-top: 4px; min-height: 14px; }
.rc-upload-msg.ok { color:#2bb673; }
.rc-upload-msg.err { color:#e2554b; }

@media (max-width: 768px) {
    .rc-pane .two-col { grid-template-columns: 1fr; }
    .rc-pane .stat-row { grid-template-columns: repeat(2, 1fr); }
}
</style>

<div class="card">
    <div class="card-body" style="padding-bottom:0;">
        <div class="tabs-nav">
            <a href="<?= $tabUrl('currencies') ?>" class="<?= $currentTab === 'currencies' ? 'active' : '' ?>"><i class="fa-solid fa-coins"></i> 币种管理</a>
            <a href="<?= $tabUrl('points') ?>" class="<?= $currentTab === 'points' ? 'active' : '' ?>"><i class="fa-solid fa-sliders"></i> 积分规则配置</a>
            <a href="<?= $tabUrl('levels') ?>" class="<?= $currentTab === 'levels' ? 'active' : '' ?>"><i class="fa-solid fa-medal"></i> 会员等级配置</a>
            <a href="<?= $tabUrl('reward') ?>" class="<?= $currentTab === 'reward' ? 'active' : '' ?>"><i class="fa-solid fa-gift"></i> 打赏配置</a>
            <a href="<?= $rcSubUrl('cards') ?? url('admin/rechargeCards') ?>" class="<?= $currentTab === 'recharge' ? 'active' : '' ?>"><i class="fa-solid fa-money-bill-wave"></i> 充值中心</a>
        </div>
    </div>
</div>

<div class="tab-pane <?= $currentTab === 'points' ? 'active' : '' ?>">
<div class="card" style="margin-bottom:16px;">
    <div class="card-header">积分规则配置</div>
    <div class="card-body">
        <p class="form-hint" style="margin-top:0;color:#999;">
            所有获取 / 消耗数值均来自下方规则表，代码零硬编码，修改后立即生效。单日上限仅对「获取」类规则生效，0 表示不限。
            为「获取」类规则填写「随机区间」后，实际发放将在区间内随机取整（留空或下限>上限则按固定数值发放）。
        </p>
        <p class="form-hint" style="margin:0 0 12px;padding:10px 12px;background:#fff7f5;border-left:3px solid #ea6f5a;color:#8a4a3a;font-size:12.5px;line-height:1.7;">
            💡 <strong>多币种发放约定</strong>：每条规则的「币种」下拉<strong>现在即时生效</strong>——直接把某条规则（如 <code>post_create</code>）的币种改成你的自定义币种，用户触发该动作时就会发放该币种（替换掉原来的 token/byte）。
            也可保留默认，到「币种管理」点行末「派生规则」一次性生成 <code>{动作}_{币种代码}</code> 副本（默认<strong>禁用且金额 0</strong>，需回到本页给这些副本填数值、勾启用后才会发放）。
            前台用户触发动作时，<code>earnAction()</code> 按规则行的「币种」字段发放。<strong>币种改名后，前台所有展示通过 <code>currencyLabel()</code> 实时读取，无需改模板</strong>。
        </p>
        <form id="pointsSystemForm" onsubmit="return false;">
            <?= csrf_field() ?>
            <table class="table table-bordered" style="font-size:13px;">
                <thead>
                    <tr>
                        <th style="width:120px;">规则 code</th>
                        <th>名称</th>
                        <th style="width:70px;">类型</th>
                        <th style="width:120px;">币种</th>
                        <th style="width:110px;">默认值</th>
                        <th style="width:185px;">随机区间</th>
                        <th style="width:130px;">单日上限</th>
                        <th style="width:70px;">启用</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rules as $r): ?>
                    <tr data-row-id="<?= (int)$r['id'] ?>" data-rule-code="<?= e($r['code']) ?>" data-base-action="<?= e($r['base_action']) ?>">
                        <td><code><?= e($r['code']) ?></code></td>
                        <td><?= e($r['name']) ?><div class="text-muted" style="font-size:11px;"><?= e($r['description'] ?? '') ?></div></td>
                        <td><?= $r['type'] === 'earn' ? '<span class="badge" style="background:#e6f7ec;color:#18a058;">获取</span>' : '<span class="badge" style="background:#fff1ee;color:#ea6f5a;">消耗</span>' ?></td>
                        <td>
                            <select class="form-control" name="currency[<?= (int)$r['id'] ?>]" style="width:120px;">
                                <?php foreach ($currencies as $cur): ?>
                                <option value="<?= e($cur['code']) ?>" <?= $r['currency'] === $cur['code'] ? 'selected' : '' ?> data-cur-enabled="<?= (int)$cur['enabled'] ?>"><?= e($cur['name']) ?><?= (int)$cur['enabled'] ? '' : '（停用）' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="number" step="1" min="0" class="form-control" name="amount[<?= $r['id'] ?>]" value="<?= (int)$r['amount'] ?>" style="width:110px;"></td>
                        <td>
                            <?php if ($r['type'] === 'earn'): ?>
                            <div style="display:flex;gap:4px;align-items:center;">
                                <input type="number" step="1" min="0" class="form-control" name="amount_min[<?= $r['id'] ?>]" value="<?= (int)($r['amount_min'] ?? 0) ?>" style="width:72px;" placeholder="最小">
                                <span class="text-muted">~</span>
                                <input type="number" step="1" min="0" class="form-control" name="amount_max[<?= $r['id'] ?>]" value="<?= (int)($r['amount_max'] ?? 0) ?>" style="width:72px;" placeholder="最大">
                            </div>
                            <div class="text-muted" style="font-size:11px;margin-top:2px;">留空/0=固定值</div>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($r['type'] === 'earn'): ?>
                            <input type="number" step="1" min="0" class="form-control" name="daily_cap[<?= $r['id'] ?>]" value="<?= (int)($r['daily_cap'] ?? 0) ?>" style="width:110px;" placeholder="0=不限">
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <input type="checkbox" name="enabled[<?= $r['id'] ?>]" value="1" <?= (int)$r['enabled'] ? 'checked' : '' ?>>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div id="pointsConflictBanner" style="display:none;margin:10px 0;padding:10px 12px;border:1px solid #ea6f5a;background:#fff5f3;color:#8a4a3a;font-size:13px;line-height:1.6;border-radius:4px;"></div>
            <button type="button" class="btn btn-primary" onclick="savePointsSystem()">保存积分规则</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">风控频控配置</div>
    <div class="card-body">
        <p class="form-hint" style="margin-top:0;color:#999;">
            防刷分机制：新注册账号在设定时长内单日积分获取上限减半；同一动作在极短时间内高频触发将暂停该用户当日积分获取权限。
        </p>
        <form id="riskForm" onsubmit="return false;" style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end;">
            <?= csrf_field() ?>
            <div class="form-group" style="width:180px;">
                <label class="form-label">新号风控时长（小时）</label>
                <input type="number" step="1" min="0" class="form-control" name="risk_newbie_hours" id="risk_newbie_hours" value="<?= (int)($risk['newbie_hours'] ?? 24) ?>">
            </div>
            <div class="form-group" style="width:180px;">
                <label class="form-label">新号额度系数（0.5=减半）</label>
                <input type="number" step="0.1" min="0" max="1" class="form-control" name="risk_newbie_factor" id="risk_newbie_factor" value="<?= htmlspecialchars((string)($risk['newbie_factor'] ?? 0.5), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-group" style="width:180px;">
                <label class="form-label">高频熔断次数阈值</label>
                <input type="number" step="1" min="0" class="form-control" name="risk_burst_count" id="risk_burst_count" value="<?= (int)($risk['burst_count'] ?? 10) ?>">
            </div>
            <div class="form-group" style="width:180px;">
                <label class="form-label">高频时间窗（秒）</label>
                <input type="number" step="1" min="1" class="form-control" name="risk_burst_window" id="risk_burst_window" value="<?= (int)($risk['burst_window'] ?? 60) ?>">
            </div>
            <button type="button" class="btn btn-primary" onclick="savePointsSystem()">保存风控配置</button>
        </form>
    </div>
</div>
</div><?php /* end tab-pane points */ ?>

<div class="tab-pane <?= $currentTab === 'levels' ? 'active' : '' ?>">
<div class="card" style="margin-bottom:16px;">
    <div class="card-header">会员等级配置</div>
    <div class="card-body">
        <p class="form-hint" style="margin-top:0;color:#999;">
            自定义用户等级名称、所需 Byte 成长值、加成系数（后台互动/发帖/签到等 earn 流水将按当前用户所在等级的加成系数放大）以及特权描述文本。
            添加新等级即可解决"用户积分越来越多但等级不变"的问题——运营可随时追加更高 Byte 阈值的高级阶梯。
            <strong style="color:#ea6f5a;">等级数字（Lv.N）需保证全局唯一。</strong>
        </p>
        <form id="userLevelsForm" onsubmit="return false;">
            <?= csrf_field() ?>
            <table class="table table-bordered" style="font-size:13px;">
                <thead>
                    <tr>
                        <th style="width:80px;">等级</th>
                        <th style="width:120px;">名称</th>
                        <th style="width:120px;">所需 Byte</th>
                        <th style="width:120px;">加成系数</th>
                        <th>等级特权</th>
                        <th style="width:80px;">启用</th>
                        <th style="width:80px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($levels as $lv): ?>
                    <tr data-level-id="<?= (int)$lv['id'] ?>" class="<?= (int)$lv['level'] === 1 ? 'level-row-highlight' : '' ?>">
                        <td>
                            <input type="number" step="1" min="1" class="form-control" name="level[<?= (int)$lv['id'] ?>]" value="<?= (int)$lv['level'] ?>" style="width:70px;">
                        </td>
                        <td><input type="text" maxlength="32" class="form-control" name="name[<?= (int)$lv['id'] ?>]" value="<?= e($lv['name']) ?>" style="width:110px;"></td>
                        <td><input type="number" step="1" min="0" class="form-control" name="byte_required[<?= (int)$lv['id'] ?>]" value="<?= (int)$lv['byte_required'] ?>" style="width:100px;" placeholder="0"></td>
                        <td>
                            <input type="number" step="0.01" min="1" max="9.99" class="form-control" name="bonus_factor[<?= (int)$lv['id'] ?>]" value="<?= htmlspecialchars(number_format((float)$lv['bonus_factor'], 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>" style="width:90px;">
                            <div class="text-muted" style="font-size:11px;margin-top:2px;">1.00=无加成</div>
                        </td>
                        <td><input type="text" maxlength="255" class="form-control" name="privilege_text[<?= (int)$lv['id'] ?>]" value="<?= e($lv['privilege_text']) ?>" placeholder="如：互动/发帖/签到 +10%"></td>
                        <td style="text-align:center;">
                            <input type="checkbox" name="enabled[<?= (int)$lv['id'] ?>]" value="1" <?= (int)$lv['enabled'] ? 'checked' : '' ?>>
                        </td>
                        <td style="text-align:center;">
                            <button type="button" class="btn btn-sm" style="background:#fff1ee;color:#ea6f5a;" onclick="deleteUserLevel(<?= (int)$lv['id'] ?>, this)">删除</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="display:flex;gap:12px;flex-wrap:wrap;">
                <button type="button" class="btn btn-primary" onclick="saveUserLevels()">保存等级配置</button>
                <button type="button" class="btn" style="background:#fff1ee;color:#ea6f5a;" onclick="addUserLevel()"><i class="fa-solid fa-plus"></i> 新增等级</button>
                <span class="text-muted" style="font-size:12px;align-self:center;">新增等级可解决老用户积分增长后没有更高等级的问题，支持任意追加。</span>
            </div>
        </form>
    </div>
</div>
</div><?php /* end tab-pane levels */ ?>

<div class="tab-pane <?= $currentTab === 'currencies' ? 'active' : '' ?>">
<div class="card" style="margin-bottom:16px;">
    <div class="card-header">币种管理</div>
    <div class="card-body">
        <p class="form-hint" style="margin-top:0;color:#999;">
            内置 <strong>Token</strong>（流通积分）与 <strong>Byte</strong>（成长值）不可删除；可在此新增自定义币种（如钻石、贡献值），并到「积分规则配置」里把规则币种改为自定义币种，或新建一条 <code>{动作}_{币种代码}</code> 规则让该动作自动发放自定义币种。
            每行支持上传 <strong>SVG 图标</strong>（不超过 64KB），前台积分卡会按「代号首字符 → 名称首字符 → 自定义 SVG」顺序显示。
        </p>
        <form id="currenciesForm" onsubmit="return false;">
            <?= csrf_field() ?>
            <table class="table table-bordered" style="font-size:13px;">
                <thead>
                    <tr>
                        <th style="width:60px;">图标</th>
                        <th style="width:120px;">代码</th>
                        <th>名称</th>
                        <th style="width:110px;">类型</th>
                        <th style="width:70px;">排序</th>
                        <th style="width:70px;">启用</th>
                        <th style="width:120px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($currencies as $cur): ?>
                    <tr data-cur-id="<?= (int)$cur['id'] ?>" data-cur-code="<?= e($cur['code']) ?>" class="<?= (int)$cur['is_system'] ? 'cc-row-highlight' : '' ?>">
                        <td>
                            <div class="cur-ico" style="background:<?= (int)$cur['is_system'] ? '#ea6f5a' : '#534ab7' ?>">
                                <?php if (!empty($cur['icon_svg'])): ?>
                                    <?= preg_match('#<svg\b[^>]*>.*?</svg>#si', $cur['icon_svg'], $sm) ? $sm[0] : '' ?>
                                <?php else: ?>
                                    <span class="cur-ico-letter"><?= e($cur['icon'] ?: mb_substr($cur['name'], 0, 1)) ?></span>
                                <?php endif; ?>
                            </div>
                            <div style="display:flex;gap:4px;margin-top:6px;flex-wrap:wrap;">
                                <label class="btn btn-sm" style="background:#fff1ee;color:#ea6f5a;cursor:pointer;padding:2px 8px;font-size:11px;">
                                    <i class="fa-solid fa-upload"></i> <?= empty($cur['icon_svg']) ? '上传' : '更换' ?>
                                    <input type="file" accept=".svg,image/svg+xml" style="display:none;" onchange="onPickCurrencyIcon(this)">
                                </label>
                                <?php if (!empty($cur['icon_svg'])): ?>
                                <button type="button" class="btn btn-sm" style="background:#fff1ee;color:#999;padding:2px 8px;font-size:11px;" onclick="clearCurrencyIconBtn(this)" title="恢复默认首字符图标">移除</button>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><code><?= e($cur['code']) ?></code><?= (int)$cur['is_system'] ? '<div class="text-muted" style="font-size:11px;">内置</div>' : '' ?></td>
                        <td><input type="text" maxlength="32" class="form-control" name="name[<?= (int)$cur['id'] ?>]" value="<?= e($cur['name']) ?>" style="width:120px;"></td>
                        <td>
                            <select class="form-control" name="type[<?= (int)$cur['id'] ?>]" style="width:100px;" <?= (int)$cur['is_system'] ? 'disabled' : '' ?>>
                                <option value="consumable" <?= $cur['type']==='consumable'?'selected':'' ?>>可消耗</option>
                                <option value="growth" <?= $cur['type']==='growth'?'selected':'' ?>>成长值</option>
                                <option value="normal" <?= $cur['type']==='normal'?'selected':'' ?>>普通</option>
                            </select>
                        </td>
                        <td><input type="number" step="1" min="0" class="form-control" name="sort_order[<?= (int)$cur['id'] ?>]" value="<?= (int)$cur['sort_order'] ?>" style="width:60px;"></td>
                        <td style="text-align:center;">
                            <input type="checkbox" name="enabled[<?= (int)$cur['id'] ?>]" value="1" <?= (int)$cur['enabled'] ? 'checked' : '' ?> <?= (int)$cur['is_system'] ? 'disabled' : '' ?>>
                        </td>
                        <td style="text-align:center;">
                            <?php if (!(int)$cur['is_system']): ?>
                            <button type="button" class="btn btn-sm" style="background:#fff1ee;color:#ea6f5a;margin-bottom:4px;" onclick="mirrorCurrencyRules('<?= e($cur['code']) ?>', this)" title="把站内所有动作（register/post_create/comment_create/...）都衍生出 xxx_<?= e($cur['code']) ?> 规则（默认禁用）">派生规则</button>
                            <button type="button" class="btn btn-sm" style="background:#fff1ee;color:#ea6f5a;" onclick="deleteCurrency(<?= (int)$cur['id'] ?>, this)">删除</button>
                            <?php else: ?>
                            <span class="text-muted">不可删</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
                <button type="button" class="btn btn-primary" onclick="saveCurrencies()">保存币种配置</button>
                <button type="button" class="btn" style="background:#fff1ee;color:#ea6f5a;" onclick="addCurrency()"><i class="fa-solid fa-plus"></i> 新增币种</button>
                <button type="button" class="btn" style="background:#fff1ee;color:#ea6f5a;" onclick="normalizeCurrencyCodes(this)" title="把 currencies/points_log/user_balances/point_rules 中所有币种 code 一次性转小写（用于清理历史数据中的大写 code）">一键规范 code（转小写）</button>
                <span class="text-muted" style="font-size:12px;align-self:center;">新增币种后，点击行末「派生规则」即可一次性为该币种生成对应规则；也可到「积分规则配置」手动逐条添加。</span>
            </div>
        </form>
    </div>
</div>
</div><?php /* end tab-pane currencies */ ?>

<div class="tab-pane <?= $currentTab === 'reward' ? 'active' : '' ?>">
<div class="card" style="margin-bottom:16px;">
    <div class="card-header">打赏配置</div>
    <div class="card-body">
        <p class="form-hint" style="margin-top:0;color:#999;">
            控制用户对帖子/评论打赏时的 <strong>默认金额、单笔上限、单日次数上限、两次间隔</strong>。
            同一用户对同一帖/评论可重复打赏（不限制次数，但受单日上限/间隔约束）；唯一不重复的是 <code>reward_log.id</code>，确保积分流水 <code>points_log</code> 唯一键不冲突。
        </p>
        <form id="rewardCfgForm" onsubmit="return false;">
            <?= csrf_field() ?>
            <table class="table" style="font-size:13px;max-width:680px;">
                <tr>
                    <th style="width:200px;">默认打赏金额</th>
                    <td>
                        <input type="number" name="reward_default_amount" class="form-control" style="width:160px;display:inline-block;" min="5" step="1" value="<?= (int)($rewardCfg['default_amount'] ?? 5) ?>">
                        <span class="form-hint" style="margin-left:8px;">弹窗打开时的默认数值（≥ 5 <?= e(\PointService::currencyLabel('token')) ?>）</span>
                    </td>
                </tr>
                <tr>
                    <th>单笔上限</th>
                    <td>
                        <input type="number" name="reward_max_amount" class="form-control" style="width:160px;display:inline-block;" min="0" step="1" value="<?= (int)($rewardCfg['max_amount'] ?? 0) ?>">
                        <span class="form-hint" style="margin-left:8px;">单笔打赏最多 <?= e(\PointService::currencyLabel('token')) ?>，0 表示不限</span>
                    </td>
                </tr>
                <tr>
                    <th>单日打赏次数上限</th>
                    <td>
                        <input type="number" name="reward_daily_count" class="form-control" style="width:160px;display:inline-block;" min="0" step="1" value="<?= (int)($rewardCfg['daily_count'] ?? 0) ?>">
                        <span class="form-hint" style="margin-left:8px;">0 表示不限</span>
                    </td>
                </tr>
                <tr>
                    <th>两次打赏最小间隔</th>
                    <td>
                        <input type="number" name="reward_interval" class="form-control" style="width:160px;display:inline-block;" min="0" step="1" value="<?= (int)($rewardCfg['interval'] ?? 0) ?>">
                        <span class="form-hint" style="margin-left:8px;">秒数，0 表示不限</span>
                    </td>
                </tr>
            </table>
            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:8px;">
                <button type="button" class="btn btn-primary" onclick="saveRewardCfg()">保存打赏配置</button>
            </div>
        </form>
    </div>
</div>
</div><?php /* end tab-pane reward */ ?>

<?php if ($currentTab === 'recharge'): ?>
<div class="tab-pane active">
  <div class="rc-subtabs">
    <a href="<?= url('admin/rechargeCards') ?>" class="<?= $rechargeSub === 'cards' ? 'active' : '' ?>"><i class="fa-solid fa-ticket"></i> 卡密管理</a>
    <a href="<?= url('admin/rechargeCampaigns') ?>" class="<?= $rechargeSub === 'campaigns' ? 'active' : '' ?>"><i class="fa-solid fa-percent"></i> 折扣活动</a>
    <a href="<?= url('admin/rechargeConfig') ?>" class="<?= $rechargeSub === 'config' ? 'active' : '' ?>"><i class="fa-solid fa-sliders"></i> 支付通道配置</a>
    <a href="<?= url('admin/rechargeOrders') ?>" class="<?= $rechargeSub === 'orders' ? 'active' : '' ?>"><i class="fa-solid fa-receipt"></i> 充值订单</a>
  </div>
  <div class="rc-pane">
    <?php
    // 注入子页需要的局部变量（卡密/活动/配置/订单各自的数据）
    if (isset($rcData) && is_array($rcData)) extract($rcData);
    // 卡片列表需要 $currencies（仅 enabled 的），用 $currenciesEnabled 兜底
    $currenciesEnabled = isset($currenciesEnabled) ? $currenciesEnabled : (isset($currencies) ? $currencies : []);
    $rcTplMap = ['cards' => 'recharge_cards', 'campaigns' => 'recharge_campaigns', 'config' => 'recharge_config', 'orders' => 'recharge_orders'];
    $rcTpl = $rcTplMap[$rechargeSub] ?? 'recharge_cards';
    $rcTplPath = __DIR__ . '/' . $rcTpl . '.php';
    if (is_file($rcTplPath)) {
        include $rcTplPath;
    } else {
        echo '<div class="card"><div class="card-body">子页面模板缺失：' . htmlspecialchars($rcTpl) . '.php</div></div>';
    }
    ?>
  </div>
</div><?php /* end tab-pane recharge */ ?>
<?php endif; ?>

<script>
window.pointsCurrencyLabels = <?php
    $map = [];
    foreach ($currencies as $c) { $map[(string)$c['code']] = (string)$c['name']; }
    echo json_encode($map, JSON_UNESCAPED_UNICODE);
?>;
window.pointsCurrencyCodes = <?php echo json_encode(array_values(array_unique((array)$currencyCodes)), JSON_UNESCAPED_UNICODE); ?>;
</script>

<script>
// ── 冲突检测工具：同 base action + 同 currency 不允许多条启用且 amount>0 的规则 ──
// 后端会再做一次硬校验，这是前端提示层。仅当存在冲突时返回非空数组。
// 注意：base action 直接读后端注入的 data-base-action（template 渲染时已用已知 currency codes 精确计算），
//       前端不再二次计算，避免和老代码 /_(byte|[a-z]+)$/ 类似的过宽匹配误把动作后缀也算作币种后缀。
function detectPointsConflicts() {
    var form = document.getElementById('pointsSystemForm');
    if (!form) return [];
    var rows = form.querySelectorAll('tbody tr[data-rule-code]');
    var group = {}; // key: baseAction + '|' + currency -> array of {id, code, row}
    var conflicts = [];
    rows.forEach(function (tr) {
        var base = tr.getAttribute('data-base-action') || '';
        var code = tr.getAttribute('data-rule-code') || '';
        var id = tr.getAttribute('data-row-id') || '';
        var sel = tr.querySelector('select[name^="currency["]');
        var cur = sel ? sel.value : '';
        var enabledEl = tr.querySelector('input[name^="enabled["]');
        var amountEl = tr.querySelector('input[name^="amount["]');
        var enabled = enabledEl && enabledEl.checked;
        var amount = amountEl ? parseInt(amountEl.value, 10) || 0 : 0;
        if (!enabled || amount <= 0) return;
        var k = base + '|' + cur;
        if (!group[k]) group[k] = [];
        group[k].push({ id: id, code: code, row: tr });
    });
    Object.keys(group).forEach(function (k) {
        if (group[k].length > 1) {
            var codes = group[k].map(function (x) { return x.code; });
            var baseAction = k.split('|')[0];
            var cur = k.split('|')[1];
            var labelMap = window.pointsCurrencyLabels || {};
            var curLabel = labelMap[cur] || cur;
            conflicts.push({ base: baseAction, currency: cur, currencyLabel: curLabel, codes: codes, rows: group[k].map(function (x) { return x.row; }) });
        }
    });
    return conflicts;
}

function renderPointsConflictBanner(conflicts) {
    var banner = document.getElementById('pointsConflictBanner');
    if (!banner) return;
    // 重置所有行的冲突样式
    document.querySelectorAll('#pointsSystemForm tbody tr[data-rule-code]').forEach(function (tr) {
        tr.classList.remove('points-rule-conflict');
    });
    if (!conflicts.length) {
        banner.style.display = 'none';
        banner.innerHTML = '';
        return;
    }
    banner.style.display = 'block';
    var html = '<strong>检测到 ' + conflicts.length + ' 组冲突：</strong><ul style="margin:6px 0 0 18px;padding:0;">';
    conflicts.forEach(function (c) {
        html += '<li>同一动作「<code>' + c.base + '</code>」给「<strong>' + c.currencyLabel + '</strong>」配了多条规则：' + c.codes.map(function (cd) { return '<code>' + cd + '</code>'; }).join(' 与 ') + '。请只保留一条并取消勾选其它。</li>';
    });
    html += '</ul>';
    banner.innerHTML = html;
    // 给冲突行加红色背景
    conflicts.forEach(function (c) {
        c.rows.forEach(function (tr) { tr.classList.add('points-rule-conflict'); });
    });
}

// 页面加载 + 任意字段变化时即时检测
function refreshPointsConflicts() {
    renderPointsConflictBanner(detectPointsConflicts());
}

function savePointsSystem() {
    // 合并两个表单的数据（规则表 + 风控配置）
    var payload = { amount: {}, daily_cap: {}, amount_min: {}, amount_max: {}, enabled: {}, currency: {} };
    var form = document.getElementById('pointsSystemForm');
    form.querySelectorAll('input[name^="amount["]').forEach(function (el) {
        var m = el.name.match(/\[(\d+)\]/); if (!m) return;
        payload.amount[m[1]] = el.value;
    });
    form.querySelectorAll('input[name^="amount_min["]').forEach(function (el) {
        var m = el.name.match(/\[(\d+)\]/); if (!m) return;
        payload.amount_min[m[1]] = el.value;
    });
    form.querySelectorAll('input[name^="amount_max["]').forEach(function (el) {
        var m = el.name.match(/\[(\d+)\]/); if (!m) return;
        payload.amount_max[m[1]] = el.value;
    });
    form.querySelectorAll('input[name^="daily_cap["]').forEach(function (el) {
        var m = el.name.match(/\[(\d+)\]/); if (!m) return;
        payload.daily_cap[m[1]] = el.value;
    });
    // ✦ 关键修复：原先漏收集 currency[] 下拉框 → 选了新币种也发不出去，刷新后回退默认、前台不生效
    form.querySelectorAll('select[name^="currency["]').forEach(function (el) {
        var m = el.name.match(/\[(\d+)\]/); if (!m) return;
        payload.currency[m[1]] = el.value;
    });
    form.querySelectorAll('input[name^="enabled["]').forEach(function (el) {
        if (!el.checked) return;
        var m = el.name.match(/\[(\d+)\]/); if (!m) return;
        payload.enabled[m[1]] = 1;
    });

    // ⚡ 前端先做一次冲突检测；非空则不让发请求（后端也会硬校验兜底）
    var conflicts = detectPointsConflicts();
    if (conflicts.length) {
        renderPointsConflictBanner(conflicts);
        toast('保存失败：检测到 ' + conflicts.length + ' 组「同一动作配多种规则」冲突，请按下方红色高亮处理后重试', 'error');
        return;
    }

    var risk = document.getElementById('riskForm');
    payload.risk_newbie_hours  = risk.querySelector('#risk_newbie_hours').value;
    payload.risk_newbie_factor = risk.querySelector('#risk_newbie_factor').value;
    payload.risk_burst_count   = risk.querySelector('#risk_burst_count').value;
    payload.risk_burst_window  = risk.querySelector('#risk_burst_window').value;

    var tokenEl = document.querySelector('#pointsSystemForm input[name="_token"]');
    if (tokenEl) payload._token = tokenEl.value;

    postJSON(url('admin/savePointsSystem'), payload, function (res) {
        if (res && res.code === 0) {
            toast(res.message || '已保存', 'success');
            // 清除可能存在的残余 banner
            renderPointsConflictBanner([]);
        } else {
            toast((res && res.message) || '保存失败', 'error');
            // 后端报错时重新检测一次（多端协作时同步）并展示冲突信息
            if (res && res.data && res.data.conflicts) {
                var map = {};
                var rows = document.querySelectorAll('#pointsSystemForm tbody tr[data-rule-code]');
                rows.forEach(function (tr) {
                    map[tr.getAttribute('data-rule-code')] = tr;
                });
                var fake = res.data.conflicts.map(function (c) {
                    return { base: c.base, currency: c.currency, currencyLabel: (window.pointsCurrencyLabels || {})[c.currency] || c.currency, codes: c.codes, rows: c.codes.map(function (cd) { return map[cd]; }).filter(Boolean) };
                });
                renderPointsConflictBanner(fake);
            }
        }
    });
}

// 初始渲染 + 监听字段变化触发即时检测
document.addEventListener('DOMContentLoaded', function () {
    refreshPointsConflicts();
    var form = document.getElementById('pointsSystemForm');
    if (!form) return;
    form.addEventListener('change', function (e) {
        if (e.target.matches('select[name^="currency["], input[type="checkbox"][name^="enabled["], input[name^="amount["]')) {
            refreshPointsConflicts();
        }
    });
    form.addEventListener('input', function (e) {
        if (e.target.matches('input[name^="amount["]')) refreshPointsConflicts();
    });
});
</script>

<script>
function saveUserLevels() {
    var payload = { level: {}, name: {}, byte_required: {}, bonus_factor: {}, privilege_text: {}, enabled: {} };
    var form = document.getElementById('userLevelsForm');
    var rows = form.querySelectorAll('tbody tr[data-level-id]');
    rows.forEach(function (tr) {
        var id = tr.getAttribute('data-level-id');
        if (!id) return;
        var lv = tr.querySelector('input[name="level[' + id + ']"]'); if (lv) payload.level[id] = lv.value;
        var nm = tr.querySelector('input[name="name[' + id + ']"]'); if (nm) payload.name[id] = nm.value;
        var br = tr.querySelector('input[name="byte_required[' + id + ']"]'); if (br) payload.byte_required[id] = br.value;
        var bf = tr.querySelector('input[name="bonus_factor[' + id + ']"]'); if (bf) payload.bonus_factor[id] = bf.value;
        var pt = tr.querySelector('input[name="privilege_text[' + id + ']"]'); if (pt) payload.privilege_text[id] = pt.value;
        var en = tr.querySelector('input[name="enabled[' + id + ']"]');
        if (en && en.checked) payload.enabled[id] = 1;
    });
    var tokenEl = form.querySelector('input[name="_token"]');
    if (tokenEl) payload._token = tokenEl.value;

    // 客户端基本校验：等级名称不能为空
    for (var k in payload.name) {
        if (!String(payload.name[k] || '').trim()) { toast('等级名称不能为空', 'error'); return; }
    }
    // 检查 level 是否重复
    var seen = {};
    for (var k2 in payload.level) {
        var v = String(payload.level[k2]);
        if (seen[v]) { toast('等级数字重复：' + v + '，请确保每个等级数字唯一', 'error'); return; }
        seen[v] = 1;
    }

    postJSON(url('admin/saveUserLevels'), payload, function (res) {
        if (res && res.code === 0) { toast(res.message || '已保存', 'success'); setTimeout(function(){ location.reload(); }, 500); }
        else toast((res && res.message) || '保存失败', 'error');
    });
}

function addUserLevel() {
    var tokenEl = document.querySelector('#userLevelsForm input[name="_token"]');
    var payload = {};
    if (tokenEl) payload._token = tokenEl.value;
    // 默认按当前最大 level + 1
    var form = document.getElementById('userLevelsForm');
    var rows = form.querySelectorAll('tbody tr[data-level-id]');
    var maxLv = 0;
    rows.forEach(function (tr) {
        var inp = tr.querySelector('input[name^="level["]');
        if (inp) { var v = parseInt(inp.value, 10) || 0; if (v > maxLv) maxLv = v; }
    });
    payload.suggested_level = maxLv + 1;

    postJSON(url('admin/addUserLevel'), payload, function (res) {
        if (res && res.code === 0) { toast('已添加，刷新中…', 'success'); setTimeout(function(){ location.reload(); }, 300); }
        else toast((res && res.message) || '添加失败', 'error');
    });
}

function deleteUserLevel(id, btn) {
    if (!confirm('确认删除该等级？\n（已有用户的 level 数字保留不变，仅移除该等级的阶梯定义。）')) return;
    var form = document.getElementById('userLevelsForm');
    var tokenEl = form.querySelector('input[name="_token"]');
    var payload = { id: id };
    if (tokenEl) payload._token = tokenEl.value;
    postJSON(url('admin/deleteUserLevel'), payload, function (res) {
        if (res && res.code === 0) {
            toast('已删除', 'success');
            var tr = btn.closest('tr'); if (tr) tr.remove();
        } else toast((res && res.message) || '删除失败', 'error');
    });
}

function saveCurrencies() {
    var form = document.getElementById('currenciesForm');
    var rows = form.querySelectorAll('tbody tr[data-cur-id]');
    var payload = { name: {}, code: {}, type: {}, sort_order: {}, enabled: {} };
    rows.forEach(function (tr) {
        var id = tr.getAttribute('data-cur-id');
        if (!id) return;
        var get = function (k) { return tr.querySelector('[name="' + k + '[' + id + ']"]'); };
        var nm = get('name'); if (!nm) return;
        payload.name[id] = nm.value;
        var cd = get('code'); if (cd) payload.code[id] = cd.value;
        var tp = get('type'); if (tp) payload.type[id] = tp.value;
        var so = get('sort_order'); if (so) payload.sort_order[id] = so.value;
        var en = get('enabled'); if (en && en.checked) payload.enabled[id] = 1;
    });
    var tokenEl = form.querySelector('input[name="_token"]');
    if (tokenEl) payload._token = tokenEl.value;
    postJSON(url('admin/saveCurrency'), payload, function (res) {
        if (res && res.code === 0) { toast('币种已保存', 'success'); setTimeout(function(){ location.reload(); }, 500); }
        else toast((res && res.message) || '保存失败', 'error');
    });
}

function addCurrency() {
    var form = document.getElementById('currenciesForm');
    var tbody = form.querySelector('tbody');
    var tr = document.createElement('tr');
    tr.setAttribute('data-cur-id', 'new');
    tr.setAttribute('data-cur-code', '');
    tr.innerHTML =
        '<td><div class="cur-ico" style="background:#534ab7"><span class="cur-ico-letter">新</span></div><div style="display:flex;gap:4px;margin-top:6px;flex-wrap:wrap;"><label class="btn btn-sm" style="background:#fff1ee;color:#ea6f5a;cursor:pointer;padding:2px 8px;font-size:11px;"><i class="fa-solid fa-upload"></i> 上传<input type="file" accept=".svg,image/svg+xml" style="display:none;" onchange="onPickCurrencyIcon(this)"></label></div></td>' +
        '<td><input type="text" class="form-control" name="code[new]" placeholder="如 diamond" style="width:110px;"></td>' +
        '<td><input type="text" class="form-control" name="name[new]" placeholder="名称" style="width:120px;"></td>' +
        '<td><select class="form-control" name="type[new]" style="width:100px;"><option value="consumable">可消耗</option><option value="growth">成长值</option><option value="normal">普通</option></select></td>' +
        '<td><input type="number" step="1" min="0" class="form-control" name="sort_order[new]" value="99" style="width:60px;"></td>' +
        '<td style="text-align:center;"><input type="checkbox" name="enabled[new]" value="1" checked></td>' +
        '<td style="text-align:center;"><span class="text-muted" style="font-size:11px;">先保存后再派生规则</span></td>';
    tbody.appendChild(tr);
}

function deleteCurrency(id, btn) {
    if (!confirm('确认删除该币种？\n（该币种余额记录将一并清除，建议先将相关积分规则的币种改为其他币种）')) return;
    var form = document.getElementById('currenciesForm');
    var tokenEl = form.querySelector('input[name="_token"]');
    var payload = { id: id };
    if (tokenEl) payload._token = tokenEl.value;
    postJSON(url('admin/deleteCurrency'), payload, function (res) {
        if (res && res.code === 0) {
            toast('已删除', 'success');
            var tr = btn.closest('tr'); if (tr) tr.remove();
        } else toast((res && res.message) || '删除失败', 'error');
    });
}

// 「派生规则」：为指定币种一键生成所有动作的 xxx_{code} 规则副本（默认禁用）
function mirrorCurrencyRules(code, btn) {
    if (!code) return;
    if (!confirm('将为「' + code + '」自动创建站内所有动作（register/post_create/comment_create/...）的 xxx_' + code + ' 规则副本，默认禁用、amount=0，需管理员在「积分规则配置」手动设置数值与上限后再启用。已存在的同名规则会被跳过。\n\n确认继续？')) return;
    var form = document.getElementById('currenciesForm');
    var tokenEl = form.querySelector('input[name="_token"]');
    var payload = { code: code };
    if (tokenEl) payload._token = tokenEl.value;
    if (btn) { btn.disabled = true; btn.textContent = '派生中…'; }
    postJSON(url('admin/mirrorCurrencyRules'), payload, function (res) {
        if (res && res.code === 0) {
            var d = res.data || {};
            toast('已创建 ' + (d.created || 0) + ' 条规则副本，跳过 ' + (d.skipped || 0) + ' 条已存在', 'success');
            setTimeout(function(){ location.reload(); }, 600);
        } else {
            toast((res && res.message) || '派生失败', 'error');
            if (btn) { btn.disabled = false; btn.textContent = '派生规则'; }
        }
    });
}

// 「一键规范 code（转小写）」：把 currencies/points_log/user_balances/point_rules
// 中所有含大写字母的 code 一次性转为小写（幂等）。如有冲突会报告。
function normalizeCurrencyCodes(btn) {
    if (!confirm('将把所有币种 code 统一转小写（包括 currencies / points_log / user_balances / point_rules 中所有相关字段）。\n\n• 若目标小写 code 已被占用，该行会被跳过（详见返回报告）\n• 操作不可逆，但因只是大小写转换，影响很小\n• 完成后会清空 PointService 币种缓存\n\n确认继续？')) return;
    var form = document.getElementById('currenciesForm');
    var tokenEl = form ? form.querySelector('input[name="_token"]') : null;
    var payload = {};
    if (tokenEl) payload._token = tokenEl.value;
    if (btn) { btn.disabled = true; btn.textContent = '处理中…'; }
    postJSON(url('admin/normalizeCurrencyCodes'), payload, function (res) {
        if (res && res.code === 0) {
            var d = res.data || {};
            toast('已更新 ' + (d.updated || 0) + ' 条' + ((d.conflicts && d.conflicts.length) ? '，冲突 ' + d.conflicts.length + ' 条（请到 php_error.log 查看详情）' : ''), 'success');
            setTimeout(function(){ location.reload(); }, 800);
        } else {
            toast((res && res.message) || '规范失败', 'error');
            if (btn) { btn.disabled = false; btn.textContent = '一键规范 code（转小写）'; }
        }
    });
}

// ── 上传 SVG 图标（针对单行），用 FileReader 读取后 POST 到 admin/uploadCurrencyIcon ──
// 上传成功后立即用返回的 svg 替换当前行 .cur-ico 容器的内容（无需刷新整页）。
// 新增未保存的币种（data-cur-id="new"）会先弹出「请先保存后再上传」提示。
// 函数只接 fileInput，从 fileInput.closest('tr') 上下文自行获取 id/code，
// 避免模板在每个 onchange 里手写 PHP 转义参数。
function onPickCurrencyIcon(fileInput) {
    if (!fileInput || !fileInput.files || !fileInput.files[0]) return;
    var file = fileInput.files[0];
    if (!/\.svg$/i.test(file.name) && file.type !== 'image/svg+xml') {
        toast('请上传 .svg 文件', 'error'); fileInput.value = ''; return;
    }
    if (file.size > 64 * 1024) {
        toast('SVG 文件不能超过 64KB', 'error'); fileInput.value = ''; return;
    }
    var row = fileInput.closest('tr[data-cur-id]');
    if (!row) return;
    var curId = row.getAttribute('data-cur-id') || '';
    var finalId = parseInt(curId, 10);
    if (!finalId || finalId <= 0) {
        toast('请先填写币种代码与名称并保存后再上传图标', 'error');
        fileInput.value = '';
        return;
    }
    var finalCode = row.getAttribute('data-cur-code') || '';
    var reader = new FileReader();
    reader.onload = function (e) {
        var content = String(e.target.result || '');
        var form = document.getElementById('currenciesForm');
        var tokenEl = form ? form.querySelector('input[name="_token"]') : null;
        var payload = { id: finalId, code: finalCode, svg: content };
        if (tokenEl) payload._token = tokenEl.value;
        postJSON(url('admin/uploadCurrencyIcon'), payload, function (res) {
            fileInput.value = '';
            if (res && res.code === 0) {
                toast('图标已上传', 'success');
                var ico = row.querySelector('.cur-ico');
                if (ico && res.data && res.data.svg) {
                    ico.innerHTML = res.data.svg; // inline SVG 立即生效，无需刷新整页
                }
                // 把上传按钮标签从「上传」换成「更换」+ 末尾追加「移除」按钮（idempotent）
                var label = row.querySelector('label.btn.btn-sm');
                if (label) {
                    var icon = label.querySelector('i'); // 保留 upload icon
                    label.innerHTML = '';
                    if (icon) label.appendChild(icon);
                    label.appendChild(document.createTextNode(' 更换'));
                    var fi = document.createElement('input');
                    fi.type = 'file'; fi.accept = '.svg,image/svg+xml'; fi.style.display = 'none';
                    fi.onchange = function () { onPickCurrencyIcon(fi); };
                    label.appendChild(fi);
                }
                var actions = row.querySelector('td:first-child > div:nth-of-type(2)');
                if (actions && !actions.querySelector('button.btn[data-clear-icon]')) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'btn btn-sm';
                    btn.style = 'background:#fff1ee;color:#999;padding:2px 8px;font-size:11px;';
                    btn.title = '恢复默认首字符图标';
                    btn.textContent = '移除';
                    btn.setAttribute('data-clear-icon', '1');
                    btn.onclick = function () { clearCurrencyIconBtn(btn); };
                    actions.appendChild(btn);
                }
            } else {
                toast((res && res.message) || '上传失败', 'error');
            }
        });
    };
    reader.onerror = function () { toast('读取文件失败', 'error'); fileInput.value = ''; };
    reader.readAsText(file, 'utf-8');
}

// 清除币种的 SVG 图标（恢复默认首字符）+ 移除按钮本身。
// 函数只接 btn，row/code 自己定位。
function clearCurrencyIconBtn(btn) {
    var row = btn && btn.closest ? btn.closest('tr[data-cur-id]') : null;
    if (!row) { toast('行不存在', 'error'); return; }
    var finalId = parseInt(row.getAttribute('data-cur-id') || '0', 10);
    if (!finalId) { toast('请先保存该币种', 'error'); return; }
    var finalCode = row.getAttribute('data-cur-code') || '';
    var form = document.getElementById('currenciesForm');
    var tokenEl = form ? form.querySelector('input[name="_token"]') : null;
    var payload = { id: finalId, code: finalCode };
    if (tokenEl) payload._token = tokenEl.value;
    postJSON(url('admin/clearCurrencyIcon'), payload, function (res) {
        if (res && res.code === 0) {
            toast('图标已清除', 'success');
            var nameInput = row.querySelector('input[name="name[' + finalId + ']"]');
            var fallback = (nameInput && nameInput.value) ? nameInput.value.charAt(0) : (finalCode ? finalCode.charAt(0) : '?');
            var ico = row.querySelector('.cur-ico');
            if (ico) ico.innerHTML = '<span class="cur-ico-letter">' + fallback + '</span>';
            btn.remove();
            // 把 label 还原成「上传」文字
            var label = row.querySelector('label.btn.btn-sm');
            if (label) {
                label.innerHTML = '<i class="fa-solid fa-upload"></i> 上传';
                var fi = document.createElement('input');
                fi.type = 'file'; fi.accept = '.svg,image/svg+xml'; fi.style.display = 'none';
                fi.onchange = function () { onPickCurrencyIcon(fi); };
                label.appendChild(fi);
            }
        } else {
            toast((res && res.message) || '清除失败', 'error');
        }
    });
}

// —— 打赏配置 ——
function saveRewardCfg() {
    var form = document.getElementById('rewardCfgForm');
    var data = {};
    new FormData(form).forEach(function (v, k) { data[k] = v; });
    // 复选框未勾选时 FormData 不会有该字段，需要手动补 0
    if (!('reward_max_amount' in data)) data.reward_max_amount = 0;
    if (!('reward_daily_count' in data)) data.reward_daily_count = 0;
    if (!('reward_interval' in data)) data.reward_interval = 0;
    postJSON(url('admin/savePointsSystem'), data, function (res) {
        if (res && res.code === 0) toast('打赏配置已保存', 'success');
        else toast((res && res.message) || '保存失败', 'error');
    });
}
</script>
