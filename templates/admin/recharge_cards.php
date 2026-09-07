<?php
/**
 * 充值系统 · 卡密管理（仅站长）
 */
$title = '充值系统 · 卡密管理';
$statusText = ['unused' => '未使用', 'used' => '已使用', 'expired' => '已过期', 'invalid' => '已作废'];
// 优先使用 $currenciesEnabled（仅启用），从 $rcData 注入；未注入时回退到 $currencies
$currenciesEnabled = $currenciesEnabled ?? ($currencies ?? []);
$f = $filters ?? ['status' => '', 'q' => ''];
?>
<style>
.rc-del{color:#e2554b;border-color:#e2554b;}
.rc-del:hover{background:#e2554b;color:#fff;}
.rc-ops .btn{margin-right:4px;}
.badge.b-lock{background:#fff4e5;color:#e6973a;border:1px solid #f0c98a;font-weight:600;}
</style>
<div class="card">
  <div class="card-header">生成卡密</div>
  <div class="card-body">
  <div class="gen-grid">
    <div class="gen-row">
      <label>卡密前缀（自定义开头关键字，如 VIP- / TEST-）</label>
      <input id="g_prefix" class="form-control" value="VIP-">
    </div>
    <div class="gen-row">
      <label>生成数量（1-500）</label>
      <input id="g_count" type="number" class="form-control" value="5" min="1" max="500">
    </div>
    <div class="gen-row">
      <label>面值（充值币种数量）</label>
      <input id="g_amount" type="number" class="form-control" value="1000" min="1">
    </div>
    <div class="gen-row">
      <label>充值币种</label>
      <select id="g_currency" class="form-control">
        <?php foreach ($currenciesEnabled as $c): ?>
        <option value="<?= e($c['code']) ?>"><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="gen-row">
      <label>有效期（天，0 = 永久）</label>
      <input id="g_expire" type="number" class="form-control" value="0" min="0">
    </div>
    <div class="gen-row">
      <label>批次备注</label>
      <input id="g_note" class="form-control" placeholder="可选，便于回溯">
    </div>
  </div>
  <div class="note blue">生成规则：<b>前缀 + 4 段独立随机字符</b>（每段 4 位，去易混淆 0/O/1/I，共 16 位）。支持 CSV 导出、单卡作废、整批作废。</div>
  <div style="margin-top:14px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
    <button class="btn btn-primary" onclick="genCards()">生成卡密</button>
    <span id="genMsg" style="font-size:13px;"></span>
  </div>
  </div>
</div>

<div class="card">
  <div class="card-header">卡密统计</div>
  <div class="card-body">
  <div class="stat-row">
    <div class="stat"><div class="num"><?= $stats['total'] ?></div><div class="label">总卡密</div></div>
    <div class="stat"><div class="num" style="color:#2bb673"><?= $stats['used'] ?></div><div class="label">已使用</div></div>
    <div class="stat"><div class="num" style="color:#e6973a"><?= $stats['unused'] ?></div><div class="label">未使用</div></div>
    <div class="stat"><div class="num" style="color:#e2554b"><?= $stats['expired'] ?></div><div class="label">已过期/作废</div></div>
  </div>
  </div>
</div>

<div class="card">
  <div class="card-header">卡密列表</div>
  <div class="card-body">
  <div class="toolbar">
    <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
      <input type="hidden" name="r" value="admin/rechargeCards">
      <select name="status" class="form-control" onchange="this.form.submit()" style="max-width:140px;">
        <option value="">全部状态</option>
        <option value="unused" <?= $f['status']==='unused'?'selected':'' ?>>未使用</option>
        <option value="used" <?= $f['status']==='used'?'selected':'' ?>>已使用</option>
        <option value="expired" <?= $f['status']==='expired'?'selected':'' ?>>已过期</option>
        <option value="invalid" <?= $f['status']==='invalid'?'selected':'' ?>>已作废</option>
      </select>
      <input name="q" value="<?= e($f['q']) ?>" class="form-control" placeholder="搜索卡号" style="max-width:220px;">
      <button class="btn ghost sm" type="submit">搜索</button>
    </form>
    <div style="margin-left:auto;display:flex;gap:8px;">
      <a class="btn ghost sm" href="<?= url('admin/rechargeCardExport') . ($f['status'] ? '?status=' . e($f['status']) : '') ?>">导出 CSV</a>
      <button class="btn ghost sm" onclick="batchInvalid()">作废选中</button>
      <button class="btn ghost sm rc-del" onclick="batchDelete()">删除选中</button>
    </div>
  </div>
  <div class="note" style="margin:10px 0;background:#fff8e9;color:#9a6a12;border:1px solid #f0d9a8;padding:8px 12px;border-radius:4px;font-size:12.5px;">
    删除规则：<b>已作废</b>卡密随时可删；<b>已使用</b>卡密需度过 <b>7 天对账期</b>后方可删除（对账期内状态显示「对账期」并标注剩余天数，不可删）。
  </div>
  <table class="rc-table">
    <thead><tr>
      <th><input type="checkbox" onclick="toggleAll(this)"></th>
      <th>卡号</th><th>面值</th><th>币种</th><th>前缀</th><th>生成时间</th>
      <th>状态</th><th>使用时间</th><th>充值账户</th><th>兑换截止</th><th>批次备注</th><th>操作</th>
    </tr></thead>
    <tbody>
      <?php if (empty($cards)): ?>
      <tr><td colspan="12" style="text-align:center;color:#999;padding:30px;">暂无卡密</td></tr>
      <?php else: foreach ($cards as $c): ?>
      <tr>
        <td><input type="checkbox" class="rc-sel" value="<?= $c['id'] ?>" <?= empty($c['can_delete']) ? 'disabled' : '' ?>></td>
        <td><code><?= e($c['card_no']) ?></code></td>
        <td><?= (int)$c['amount'] ?></td>
        <td><?= e(PointService::currencyLabel($c['currency'])) ?></td>
        <td><?= e($c['prefix']) ?></td>
        <td><?= e($c['created_at']) ?></td>
        <td><span class="badge b-<?= (($c['status']==='used') && empty($c['can_delete'])) ? 'lock' : ($c['status']==='used'?'used':($c['status']==='unused'?'unused':'exp')) ?>"><?= e($c['status_label'] ?? $statusText[$c['status']] ?? $c['status']) ?><?php if ((($c['status'] ?? '') === 'used') && empty($c['can_delete']) && !empty($c['lock_days'])): ?><small> (剩<?= (int)$c['lock_days'] ?>天)</small><?php endif; ?></span></td>
        <td><?= e($c['used_at'] ?? '—') ?></td>
        <td><?= $c['used_by'] ? (e($c['used_username'] ?? '') . ' (uid:' . $c['used_by'] . ')') : '—' ?></td>
        <td><?= e($c['expire_at'] ?? '永久') ?></td>
        <td><?= e($c['batch_note'] ?? '—') ?></td>
        <td class="rc-ops">
          <?php if ($c['status'] === 'unused'): ?>
          <button class="btn ghost sm" onclick="invalidOne(<?= $c['id'] ?>)">作废</button>
          <?php endif; ?>
          <?php if (!empty($c['can_delete'])): ?>
          <button class="btn ghost sm rc-del" onclick="delOne(<?= $c['id'] ?>)">删除</button>
          <?php endif; ?>
          <?php if ($c['status'] !== 'unused' && empty($c['can_delete'])): ?>—<?php endif; ?>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
  <?php if (!empty($pagination) && $pagination['last_page'] > 1): ?>
  <div class="pager" style="margin-top:14px;">
    <?php for ($p = 1; $p <= $pagination['last_page']; $p++): ?>
    <a class="btn ghost sm <?= $p===$pagination['page']?'active':'' ?>" href="?r=admin/rechargeCards&page=<?= $p ?>&status=<?= e($f['status']) ?>&q=<?= e($f['q']) ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  </div>
</div>

<script>
function genCards(){
  const data = {
    prefix: document.getElementById('g_prefix').value,
    count: document.getElementById('g_count').value,
    amount: document.getElementById('g_amount').value,
    currency: document.getElementById('g_currency').value,
    expire_days: document.getElementById('g_expire').value,
    batch_note: document.getElementById('g_note').value
  };
  window.postJSON('<?= url('admin/rechargeGenerate') ?>', data, function(res){
    const m = document.getElementById('genMsg');
    // 后端响应格式 {code, message, data:{count, sample}}
    if(res && res.code === 0){
      const info = res.data || {};
      const n = Number(info.count) || 0;
      const sample = info.sample ? String(info.sample) : '';
      // 自然语言：成功生成 N 张（示例：xxxx）。示例有字数限制裁掉多余，6 秒后刷新
      let msg = '已生成 ' + n + ' 张卡密';
      if(sample) msg += '（示例卡号：' + sample + '）';
      m.style.color='#2bb673'; m.textContent = msg;
      setTimeout(()=>{ try { location.reload(); } catch(_) {} }, 1200);
    } else {
      // 失败时只显示后端 message；绝不让 undefined 这种字眼冒出来
      const fail = (res && res.message) ? String(res.message) : '生成失败，请稍后再试';
      m.style.color='#e2554b'; m.textContent = fail;
    }
  });
}
function toggleAll(box){ document.querySelectorAll('.rc-sel:not([disabled])').forEach(c=>c.checked=box.checked); }
function selIds(){ return Array.from(document.querySelectorAll('.rc-sel:checked')).map(c=>c.value); }
function invalidOne(id){ if(!confirm('确认作废该卡密？')) return; doInvalid([id]); }
function batchInvalid(){ const ids=selIds(); if(!ids.length){ alert('请先勾选要作废的卡密'); return; } if(!confirm('确认作废选中的 '+ids.length+' 张卡密？')) return; doInvalid(ids); }
function doInvalid(ids){
  window.postJSON('<?= url('admin/rechargeCardInvalidate') ?>', {ids:ids}, function(res){
    if(res && res.code === 0){ location.reload(); } else { alert(res.message||'作废失败'); }
  });
}
function delOne(id){
  if(!confirm('确认删除该卡密？删除后不可恢复。')) return;
  window.postJSON('<?= url('admin/rechargeCardDelete') ?>', {id:id}, function(res){
    if(res && res.code === 0){ location.reload(); } else { alert(res.message||'删除失败'); }
  });
}
function batchDelete(){
  const ids = selIds();
  if(!ids.length){ alert('请先勾选要删除的卡密'); return; }
  if(!confirm('确认删除选中的 '+ids.length+' 张卡密？仅「已作废」或「已使用超 7 天」可删，其他会被跳过。')) return;
  window.postJSON('<?= url('admin/rechargeCardBatchDelete') ?>', {ids:ids}, function(res){
    if(res && res.code === 0){ location.reload(); } else { alert(res.message||'删除失败'); }
  });
}
</script>
