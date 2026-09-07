<?php
/**
 * 充值系统 · 充值订单（后台，仅站长）
 */
$title = '充值系统 · 充值订单';
$statusText = ['pending' => '待支付', 'paid' => '已支付', 'expired' => '已过期', 'failed' => '失败'];
$chText = ['wechat' => '微信', 'alipay' => '支付宝'];
?>
<div class="card">
  <div class="card-header">充值订单</div>
  <div class="card-body">
  <form method="get" style="margin-bottom:12px;">
    <input type="hidden" name="r" value="admin/rechargeOrders">
    <select name="channel" class="form-control" onchange="this.form.submit()" style="max-width:160px;display:inline-block;">
      <option value="">全部渠道</option>
      <option value="wechat" <?= (input('channel')==='wechat')?'selected':'' ?>>微信</option>
      <option value="alipay" <?= (input('channel')==='alipay')?'selected':'' ?>>支付宝</option>
    </select>
  </form>
  <table class="rc-table">
    <thead><tr>
      <th>订单号</th><th>渠道</th><th>实付金额</th><th>币种</th><th>实得</th><th>充值账户</th><th>方案</th><th>状态</th><th>下单时间</th><th>支付时间</th>
    </tr></thead>
    <tbody>
      <?php if (empty($orders)): ?>
      <tr><td colspan="10" style="text-align:center;color:#999;padding:30px;">暂无订单</td></tr>
      <?php else: foreach ($orders as $o): ?>
      <tr>
        <td><code><?= e($o['order_no']) ?></code></td>
        <td><?= e($chText[$o['channel']] ?? $o['channel']) ?></td>
        <td>¥<?= number_format((float)$o['amount'], 2) ?></td>
        <td><?= e(PointService::currencyLabel($o['currency'])) ?></td>
        <td><b style="color:#ea6f5a;"><?= (int)$o['gained'] ?></b></td>
        <td>
          <?php if ($o['user_id']): ?>
            <?php
              $u = Model::table('users')->select('username')->where('id', (int)$o['user_id'])->first();
              echo $u ? e($u['username']) . ' (uid:' . $o['user_id'] . ')' : 'uid:' . $o['user_id'];
            ?>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td><?= e($o['scheme']) ?></td>
        <td><span class="badge b-<?= $o['status']==='paid'?'used':($o['status']==='pending'?'unused':'exp') ?>"><?= e($statusText[$o['status']] ?? $o['status']) ?></span></td>
        <td><?= e($o['created_at']) ?></td>
        <td><?= e($o['paid_at'] ?? '—') ?></td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
  <?php if (!empty($pagination) && $pagination['last_page'] > 1): ?>
  <div class="pager" style="margin-top:14px;">
    <?php for ($p = 1; $p <= $pagination['last_page']; $p++): ?>
    <a class="btn ghost sm <?= $p===$pagination['page']?'active':'' ?>" href="?r=admin/rechargeOrders&page=<?= $p ?>&channel=<?= e(input('channel')) ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  </div>
</div>
