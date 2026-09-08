<?php
/**
 * 我的充值订单（前台）
 * 路由：recharge/orders
 */
$title = '我的充值订单';
$statusMap = [
    'pending' => ['text' => '待支付', 'cls' => 'b-unused'],
    'paid'    => ['text' => '已支付', 'cls' => 'b-used'],
    'expired' => ['text' => '已过期', 'cls' => 'b-exp'],
    'failed'  => ['text' => '失败',   'cls' => 'b-exp'],
];
$chMap = ['wechat' => '微信', 'alipay' => '支付宝', 'card' => '卡密充值'];
?>
<style>
.rc-o-wrap{max-width:880px;margin:24px auto;padding:0 16px;}
.rc-o-table{width:100%;background:#fff;border-collapse:collapse;border:1px solid #eee;border-radius:10px;overflow:hidden;font-size:14px;}
.rc-o-table th,.rc-o-table td{padding:11px 14px;text-align:left;border-bottom:1px solid #f2f2f2;}
.rc-o-table th{background:#fafafa;color:#666;font-weight:600;}
.rc-o-empty{text-align:center;color:#999;padding:40px;}
.badge{padding:2px 10px;border-radius:10px;font-size:12px;}
.b-used{background:#e8f8ee;color:#2bb673;}
.b-unused{background:#fff5e6;color:#e6973a;}
.b-exp{background:#fdeaea;color:#e2554b;}
</style>
<div class="rc-o-wrap">
  <h2 style="font-size:22px;margin:0 0 16px;">我的充值订单</h2>
  <table class="rc-o-table">
    <thead><tr><th>订单号</th><th>渠道</th><th>实付金额</th><th>实得</th><th>状态</th><th>下单时间</th></tr></thead>
    <tbody>
      <?php if (empty($orders)): ?>
      <tr><td colspan="6" class="rc-o-empty">暂无充值订单</td></tr>
      <?php else: foreach ($orders as $o):
        $st = $statusMap[$o['status']] ?? ['text'=>$o['status'],'cls'=>'b-unused'];
      ?>
      <tr>
        <td><?= e($o['order_no']) ?></td>
        <td><?= e($chMap[$o['channel']] ?? $o['channel']) ?></td>
        <td><?= $o['amount'] === null ? '—' : '¥' . number_format((float)$o['amount'], 2) ?></td>
        <td><?= (int)$o['gained'] ?> <?= e(PointService::currencyLabel($o['currency'])) ?></td>
        <td><span class="badge <?= $st['cls'] ?>"><?= e($st['text']) ?></span></td>
        <td><?= e($o['created_at']) ?></td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
  <a href="<?= url('recharge/index') ?>" style="display:inline-block;margin-top:14px;color:#ea6f5a;text-decoration:none;">← 返回充值中心</a>
</div>
