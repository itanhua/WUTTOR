<?php
/**
 * 充值系统 · 折扣活动（仅站长）
 */
$title = '充值系统 · 折扣活动';
$typeText = ['bonus' => '加赠', 'discount' => '折扣', 'first' => '首充'];
$channelText = ['wechat' => '微信', 'alipay' => '支付宝', 'card' => '卡密'];
?>
<div class="card">
  <div class="card-header">新增活动</div>
  <div class="card-body">
  <div class="two-col">
    <div>
      <label>活动名称</label>
      <input id="c_name" class="form-control" placeholder="如：双十一充值狂欢">
      <label style="margin-top:10px;">优惠类型</label>
      <select id="c_type" class="form-control" onchange="onCType()">
        <option value="bonus">加赠（实付不变，多送积分）</option>
        <option value="discount">折扣（少付多得，按原档位发积分）</option>
        <option value="first">首充（仅首次充值，按倍数发）</option>
      </select>
      <label style="margin-top:10px;" id="c_val_lbl">比例（任意数，如 0.2 = 加赠 20%）</label>
      <input id="c_val" type="number" step="0.01" class="form-control" value="0.2">
      <div class="note blue" id="c_val_help" style="margin-top:8px;">实得 = 原应得 × (1 + 比例)。比例为负则缩水，正数则加赠，按运营需要填写。</div>
    </div>
    <div>
      <label>适用渠道（可多选，不选 = 全部渠道）</label>
      <div style="display:flex;gap:14px;margin:4px 0 10px;">
        <label><input type="checkbox" class="c_ch" value="wechat"> 微信</label>
        <label><input type="checkbox" class="c_ch" value="alipay"> 支付宝</label>
        <label><input type="checkbox" class="c_ch" value="card"> 卡密</label>
      </div>
      <label>起止时间（留空 = 长期有效）</label>
      <div style="display:flex;gap:8px;">
        <input id="c_start" type="datetime-local" class="form-control">
        <input id="c_end" type="datetime-local" class="form-control">
      </div>
      <div style="display:flex;gap:12px;margin-top:10px;">
        <div style="flex:1;"><label>最低金额（元，0 不限）</label><input id="c_min" type="number" step="0.01" class="form-control" value="0"></div>
        <div style="flex:1;"><label>加赠封顶（0 不限）</label><input id="c_cap" type="number" class="form-control" value="0"></div>
      </div>
    </div>
  </div>
    <button class="btn btn-primary" onclick="addCampaign()">添加活动</button>
    <span id="cMsg" style="margin-left:10px;font-size:13px;"></span>
  </div>
</div>

<div class="card">
  <div class="card-header">活动列表</div>
  <div class="card-body">
  <table class="rc-table">
    <thead><tr><th>名称</th><th>类型</th><th>力度</th><th>渠道</th><th>最低金额</th><th>有效期</th><th>状态</th><th>操作</th></tr></thead>
    <tbody>
      <?php if (empty($campaigns)): ?>
      <tr><td colspan="8" style="text-align:center;color:#999;padding:30px;">暂无活动</td></tr>
      <?php else: foreach ($campaigns as $c): ?>
      <tr>
        <td><?= e($c['name']) ?></td>
        <td><?= e($typeText[$c['type']] ?? $c['type']) ?></td>
        <td>
          <?php if ($c['type']==='bonus'): ?>
            加赠 <?= round((float)$c['value']*100) ?>%<?= (int)$c['cap']>0?'（封顶 '.(int)$c['cap'].'）':'' ?>
          <?php elseif ($c['type']==='discount'): ?>
            充<?= round((float)$c['value']*10,1) ?>折
          <?php elseif ($c['type']==='first'): ?>
            ×<?= (1+(float)$c['value']) ?>
          <?php endif; ?>
        </td>
        <td><?= empty($c['channels_arr']) ? '全部' : implode('/', array_map(function($x)use($channelText){return $channelText[$x]??$x;}, $c['channels_arr'])) ?></td>
        <td><?= (float)$c['min_amount']>0 ? '¥'.$c['min_amount'] : '不限' ?></td>
        <td>
          <?= $c['start_at'] ? e(substr($c['start_at'],0,10)) : '即起' ?>
          ~
          <?= $c['end_at'] ? e(substr($c['end_at'],0,10)) : '长期' ?>
        </td>
        <td><span class="badge b-<?= $c['enabled']?'used':'unused' ?>"><?= $c['enabled']?'启用中':'已停用' ?></span></td>
        <td>
          <button class="btn ghost sm" onclick="toggleCamp(<?= $c['id'] ?>, <?= $c['enabled']?0:1 ?>)"><?= $c['enabled']?'停用':'启用' ?></button>
          <?php if ((int)$c['enabled']===0): ?>
          <button class="btn ghost sm" style="margin-left:6px;color:#e2554b;border-color:#e2554b;" onclick="deleteCamp(<?= $c['id'] ?>, '<?= e(addslashes($c['name'])) ?>')">删除</button>
          <?php else: ?>
          <button class="btn ghost sm" style="margin-left:6px;opacity:.45;cursor:not-allowed;" disabled title="启用中的活动不可删除，请先停用">删除</button>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
  </div>
</div>

<script>
const URL_CAMP_ADD    = <?= json_encode(url('admin/rechargeCampaignAdd')) ?>;
const URL_CAMP_TOG    = <?= json_encode(url('admin/rechargeCampaignToggle')) ?>;
const URL_CAMP_DEL    = <?= json_encode(url('admin/rechargeCampaignDelete')) ?>;
function onCType(){
  const t = document.getElementById('c_type').value;
  const lbl = document.getElementById('c_val_lbl');
  const help = document.getElementById('c_val_help');
  if(t==='discount'){ lbl.textContent='折扣比例（0 < n ≤ 1，如 0.8 = 充8折）'; help.textContent='0.8 表示用户付 80% 金额，仍按原档位发放全额积分（少付多得）。超出 (0,1] 将拒绝添加。'; }
  else if(t==='first'){ lbl.textContent='首充倍数（n ≥ 1，如 1 = ×2）'; help.textContent='仅首次充值的用户享受，实得 = 原应得 × (1 + n)。n < 1 将拒绝添加。'; }
  else { lbl.textContent='比例（任意数，如 0.2 = 加赠 20%）'; help.textContent='实得 = 原应得 × (1 + 比例)。'; }
}
function addCampaign(){
  const chs = Array.from(document.querySelectorAll('.c_ch:checked')).map(c=>c.value);
  const data = {
    name: document.getElementById('c_name').value,
    type: document.getElementById('c_type').value,
    value: document.getElementById('c_val').value,
    channels: chs,
    start_at: document.getElementById('c_start').value ? document.getElementById('c_start').value.replace('T',' ')+':00' : '',
    end_at: document.getElementById('c_end').value ? document.getElementById('c_end').value.replace('T',' ')+':00' : '',
    min_amount: document.getElementById('c_min').value,
    cap: document.getElementById('c_cap').value
  };
  window.postJSON(URL_CAMP_ADD, data, function(res){
    const m = document.getElementById('cMsg');
    if(res && res.code === 0){ m.style.color='#2bb673'; m.textContent=res.message||'已添加'; setTimeout(()=>location.reload(),800); }
    else { m.style.color='#e2554b'; m.textContent=(res && res.message) || '添加失败'; }
  });
}
function toggleCamp(id, enabled){
  window.postJSON(URL_CAMP_TOG, {id:id, enabled:enabled}, function(res){
    if(res && res.code === 0) location.reload();
    else alert((res && res.message) || '操作失败');
  });
}
function deleteCamp(id, name){
  if(!confirm('确认删除活动「'+name+'」？删除后不可恢复。')) return;
  window.postJSON(URL_CAMP_DEL, {id:id}, function(res){
    if(res && res.code === 0){ alert('已删除'); location.reload(); }
    else alert((res && res.message) || '删除失败');
  });
}
</script>
