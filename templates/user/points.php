<?php /** 我的积分 */ $title = '我的积分'; ?>
<div class="points-page">
    <!-- 每日签到 -->
    <div class="card sign-card">
        <div class="sign-info">
            <div class="sign-streak">连续签到 <strong id="signStreak"><?= $streak ?></strong> 天</div>
            <div class="sign-tip">每日签到可得 <?= e(\PointService::currencyLabel('token')) ?> + <?= e(\PointService::currencyLabel('byte')) ?> 成长值</div>
        </div>
        <button id="signBtn" class="btn btn-primary" onclick="doSign()" <?= $signedToday ? 'disabled' : '' ?>>
            <?= $signedToday ? '今日已签到 ✓' : '立即签到' ?>
        </button>
    </div>

    <!-- 概览卡：全部启用币种（内置 Token/Byte + 后台自定义）+ 等级 + 进度条 -->
    <div class="card points-overview">
        <div class="points-balance">
            <?php foreach ($balances as $b): ?>
                <div class="bal-item bal-item-<?= e($b['code']) ?>" data-balance-code="<?= e($b['code']) ?>">
                    <div class="bal-ico">
                        <?php if (!empty($b['icon_svg']) && preg_match('#<svg\b[^>]*>.*?</svg>#si', $b['icon_svg'], $sm)): ?>
                            <?= $sm[0] ?>
                        <?php else: ?>
                            <span class="cur-ico-letter"><?= e($b['icon'] !== '' ? $b['icon'] : mb_substr($b['name'], 0, 1)) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="bal-num"><?= number_format($b['balance']) ?></div>
                    <div class="bal-label"><?= e($b['name']) ?></div>
                </div>
            <?php endforeach; ?>
            <div class="bal-item bal-item-level">
                <div class="bal-num">Lv.<?= $levelInfo['level'] ?> <span class="lv-name"><?= e($levelInfo['name']) ?></span></div>
                <div class="bal-label">当前等级</div>
            </div>
        </div>
        <div class="level-progress">
            <?php if ($levelInfo['is_max']): ?>
                <div class="lv-tip">已达成最高等级（<?= e($levelInfo['name']) ?>），成长值 <?= number_format($levelInfo['byte']) ?> <?= e(\PointService::currencyLabel('byte')) ?></div>
            <?php else: ?>
                <div class="lv-tip">距离下一等级（<?= e($levelInfo['next_min'] === null ? $levelInfo['name'] : ($lvRows[$levelInfo['level']]['name'] ?? '')) ?>）还需 <?= number_format($levelInfo['to_next']) ?> <?= e(\PointService::currencyLabel('byte')) ?></div>
            <?php endif; ?>
            <div class="progress-bar"><div class="progress-fill" style="width:<?= $levelInfo['progress'] ?>%"></div></div>
        </div>
    </div>

    <!-- Tab：获取 / 消耗 / 等级特权 -->
    <div class="card">
        <div class="profile-tabs">
            <a href="<?= url('user/points', ['tab' => 'earn']) ?>" class="profile-tab <?= $tab === 'earn' ? 'active' : '' ?>">获取记录</a>
            <a href="<?= url('user/points', ['tab' => 'spend']) ?>" class="profile-tab <?= $tab === 'spend' ? 'active' : '' ?>">消耗记录</a>
            <a href="<?= url('user/points', ['tab' => 'privilege']) ?>" class="profile-tab <?= $tab === 'privilege' ? 'active' : '' ?>">等级特权</a>
        </div>
        <?php if ($tab === 'earn' || $tab === 'spend'): ?>
        <div class="currency-filter">
            <a href="<?= url('user/points', ['tab' => $tab]) ?>" class="cur-chip cur-chip-all <?= $currency === '' ? 'active' : '' ?>">全部</a>
            <?php foreach (($currencies ?? []) as $c): ?>
                <?php $code = (string)$c['code']; ?>
                <a href="<?= url('user/points', ['tab' => $tab, 'currency' => $code]) ?>" class="cur-chip cur-chip-<?= e($code) ?> <?= $currency === $code ? 'active' : '' ?>">
                    <?= e($c['name']) ?>
                </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="card-body">
            <?php if ($tab === 'earn' || $tab === 'spend'): ?>
                <?php if (empty($logs)): ?>
                    <div class="empty-state" style="padding:40px;"><p>暂无<?= $tab === 'earn' ? '获取' : '消耗' ?>记录</p></div>
                <?php else: ?>
                    <ul class="points-log-list">
                        <?php foreach ($logs as $log): ?>
                            <li class="points-log-item">
                                <div class="log-main">
                                    <span class="log-source"><?= e($ruleNames[$log['source']] ?? $log['source']) ?></span>
                                    <span class="log-amount <?= $log['type'] === 'earn' ? 'plus' : 'minus' ?>">
                                        <?= $log['type'] === 'earn' ? '+' : '' ?><?= $log['amount'] ?> <?= e(\PointService::currencyLabel($log['currency'])) ?>
                                    </span>
                                </div>
                                <div class="log-meta">
                                    <span><?= time_ago($log['created_at']) ?></span>
                                    <span>操作后余 <?= $log['balance_after'] ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($logTotal > $perPage): ?>
                        <?php
                        // 分页时也带上 currency 参数，避免切换币种后翻页丢失筛选
                        $pageParams = ['tab' => $tab];
                        if ($currency !== '') $pageParams['currency'] = $currency;
                        ?>
                        <div class="pagination" style="margin-top:16px;"><?= pagination($logTotal, $page, $perPage, 'user/points', $pageParams) ?></div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php else: ?>
                <!-- 等级特权：动态阶梯（来自 user_levels 表） -->
                <div class="level-ladder">
                    <?php foreach ($lvRows as $row): ?>
                        <?php $lv = (int)$row['level']; $reached = $lv <= $levelInfo['level'];
                              $factor = (float)$row['bonus_factor'];
                              $bonusText = ($factor > 1.0)
                                ? '互动/发帖/签到 +' . (int)round(($factor - 1) * 100) . '%'
                                : '—';
                              $privText = (string)($row['privilege_text'] ?? '');
                              // 仅有「非 —」的实际特权文案才标记 has-privilege；bonus>1 或自定义特权文本均可
                              $finalText = $privText !== '' ? $privText : $bonusText;
                              $hasPriv = $finalText !== '—'; ?>
                        <div class="ladder-row <?= $reached ? 'reached' : '' ?> <?= $hasPriv ? 'has-privilege' : '' ?>">
                            <div class="ladder-lv">Lv.<?= $lv ?></div>
                            <div class="ladder-name"><?= e($row['name']) ?></div>
                            <div class="ladder-req"><?= number_format((int)$row['byte_required']) ?> <?= e(\PointService::currencyLabel('byte')) ?></div>
                            <div class="ladder-bonus"><?= e($finalText) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function doSign() {
    var btn = document.getElementById('signBtn');
    if (!btn || btn.disabled) return;
    btn.disabled = true;
    btn.textContent = '签到中...';
    postJSON(url('user/sign'), {_token: getCsrfToken()}, function(res) {
        if (res.code === 0) {
            var d = res.data || {};
            if (d.streak != null) {
                var s = document.getElementById('signStreak');
                if (s) s.textContent = d.streak;
            }
            btn.textContent = '今日已签到 ✓';
            if (d.points) window.showPointsToast(d.points);
            else window.toast('签到成功', 'success');
        } else {
            btn.disabled = false;
            btn.textContent = '立即签到';
            window.toast(res.message || '签到失败', 'error');
        }
    }, function() {
        btn.disabled = false;
        btn.textContent = '立即签到';
        window.toast('网络错误，请重试', 'error');
    });
}
</script>
