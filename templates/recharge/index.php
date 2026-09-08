<?php
/**
 * 积分充值中心（前台）
 * 路由：recharge/index
 */
$title = '积分充值';
?>
<style>
.rc-wrap{margin:0;padding:0 16px;}
.rc-h{font-size:22px;font-weight:700;color:#222;margin:0 0 16px;display:flex;align-items:center;gap:8px;}
.rc-h .badge{font-size:12px;font-weight:400;color:#ea6f5a;border:1px solid #ea6f5a;border-radius:10px;padding:1px 8px;}
.rc-balances{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:16px;}
.rc-bal{flex:1;min-width:120px;background:#fff;border:1px solid #eee;border-radius:10px;padding:14px 16px;box-shadow:0 1px 3px rgba(0,0,0,.04);}
.rc-bal .n{font-size:22px;font-weight:700;color:#ea6f5a;}
.rc-bal .l{font-size:13px;color:#888;margin-top:2px;}
.rc-promo{background:linear-gradient(90deg,#fff7f4,#fff);border:1px solid #f3d9d0;border-left:4px solid #ea6f5a;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:14px;color:#7a4034;}
.rc-promo b{color:#ea6f5a;}
.rc-tabs{display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;}
.rc-tab{border:1px solid #ddd;background:#fff;padding:9px 22px;border-radius:20px;cursor:pointer;font-size:14px;color:#555;transition:.15s;}
.rc-tab.active{background:#ea6f5a;color:#fff;border-color:#ea6f5a;}
.rc-panel{background:#fff;border:1px solid #eee;border-radius:12px;padding:22px;box-shadow:0 1px 4px rgba(0,0,0,.04);}
.rc-panel.hidden{display:none;}
.rc-row{margin-bottom:16px;}
.rc-row label{display:block;font-size:13px;color:#666;margin-bottom:6px;}
/* 充值金额卡片网格（参考原型：3 列 × 2 行） */
.rc-amts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:12px;}
@media (max-width: 640px){ .rc-amts{grid-template-columns:repeat(2,minmax(0,1fr));} }
.rc-amt{position:relative;background:#fff;border:1px solid #eee;border-radius:12px;padding:18px 8px 14px;cursor:pointer;text-align:center;transition:.18s;user-select:none;}
.rc-amt:hover{border-color:#ea6f5a;background:#fff8f5;}
.rc-amt.sel{border-color:#ea6f5a;background:#fff5f2;box-shadow:0 0 0 1px #ea6f5a inset, 0 4px 10px rgba(234,111,90,.18);}
.rc-amt.sel::after{content:"";position:absolute;top:8px;right:8px;width:8px;height:8px;border-radius:50%;background:#ea6f5a;}
.rc-amt .p{font-size:22px;font-weight:700;color:#ea6f5a;line-height:1.2;}
.rc-amt .g{margin-top:6px;font-size:13px;color:#666;}
.rc-amt .g b{color:#ea6f5a;font-weight:600;}
.rc-amt.custom .p{color:#666;font-size:18px;font-weight:600;}
.rc-amt.custom .g{color:#999;font-size:12px;}
.rc-input{width:100%;padding:11px 12px;border:1px solid #ddd;border-radius:8px;font-size:15px;box-sizing:border-box;}
.rc-input:focus{outline:none;border-color:#ea6f5a;}
.rc-msg{margin-top:12px;font-size:14px;min-height:20px;}
.rc-link{display:inline-block;margin-top:10px;color:#ea6f5a;font-size:13px;text-decoration:none;}
.rc-modal-mask{position:fixed;inset:0;background:rgba(0,0,0,.45);display:flex;align-items:center;justify-content:center;z-index:9999;}
.rc-modal-mask.hidden{display:none;}
.rc-modal{background:#fff;border-radius:12px;padding:26px;width:320px;text-align:center;}
.rc-qr{width:180px;height:180px;margin:14px auto;background:#fafafa;border:1px dashed #ccc;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#bbb;font-size:13px;}
.rc-pay-amt{font-size:26px;font-weight:700;color:#ea6f5a;margin:6px 0;}
</style>

<div class="rc-wrap">
  <h2 class="rc-h">积分充值 <span class="badge">充值账户：<?= e(Auth::user()['username']) ?> (uid:<?= Auth::id() ?>)</span></h2>

  <!-- 当前余额 -->
  <div class="rc-balances">
    <?php foreach ($balances as $b): ?>
    <div class="rc-bal">
      <div class="n"><?= format_count($b['balance']) ?></div>
      <div class="l"><?= e($b['name']) ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- 活动横幅 -->
  <?php if (!empty($campaigns)): ?>
  <div class="rc-promo">
    🎉 <b>当前充值活动：</b>
    <?php
    $parts = [];
    foreach ($campaigns as $c) {
        if ($c['type'] === 'bonus') {
            $parts[] = e($c['name']) . '（加赠 ' . round((float)$c['value'] * 100) . '%' . ((int)$c['cap'] > 0 ? '，封顶 ' . (int)$c['cap'] : '') . '）';
        } elseif ($c['type'] === 'discount') {
            $parts[] = e($c['name']) . '（充' . round((float)$c['value'] * 10, 1) . '折）';
        } elseif ($c['type'] === 'first') {
            $parts[] = e($c['name']) . '（首充 ×' . (1 + (float)$c['value']) . '）';
        }
    }
    echo implode('；', $parts);
    ?>
  </div>
  <?php endif; ?>

  <!-- 通道切换 -->
  <div class="rc-tabs">
    <div class="rc-tab active" data-tab="card" onclick="switchRcTab('card',this)">卡密充值</div>
    <?php if ($showOnlineEntry): ?>
    <div class="rc-tab" data-tab="wechat" onclick="switchRcTab('wechat',this)">微信支付</div>
    <div class="rc-tab" data-tab="alipay" onclick="switchRcTab('alipay',this)">支付宝</div>
    <?php endif; ?>
  </div>

  <!-- 卡密 -->
  <div id="tab-card" class="rc-panel">
    <input type="hidden" name="_token" value="<?= csrf_token() ?>">
    <div class="rc-row">
      <label>卡密</label>
      <input id="cardInput" class="rc-input" placeholder="请输入卡密">
    </div>
    <button class="btn btn-primary" onclick="doRedeem()">立即兑换</button>
    <div class="rc-msg" id="cardMsg"></div>
  </div>

  <!-- 微信 / 支付宝 -->
  <?php if ($showOnlineEntry): ?>
  <?php
    $rcDefaultCur  = ($currencies[0]['code'] ?? '');
    $rcDefaultRate = (int)(($rcDefaultCur !== '' && isset($baseRates[$rcDefaultCur])) ? $baseRates[$rcDefaultCur] : RechargeService::DEFAULT_BASE_RATE);
  ?>
  <?php foreach (['wechat' => '微信支付', 'alipay' => '支付宝'] as $ch => $chName): ?>
  <div id="tab-<?= $ch ?>" class="rc-panel hidden">
    <input type="hidden" name="_token" value="<?= csrf_token() ?>">
    <div class="rc-row">
      <label>充值币种</label>
      <select id="<?= $ch ?>Currency" class="rc-input" style="max-width:240px;" onchange="onRcCurrencyChange('<?= $ch ?>')">
        <?php foreach ($currencies as $cur): ?>
        <option value="<?= e($cur['code']) ?>"><?= e($cur['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="rc-row">
      <label>选择充值金额（元）</label>
      <div class="rc-amts" id="<?= $ch ?>Amts">
        <?php foreach ([6,30,68,128,198] as $a): ?>
        <div class="rc-amt" data-amt="<?= $a ?>" onclick="pickRcAmt(this,<?= $a ?>,'<?= $ch ?>')">
          <div class="p">¥<?= $a ?></div>
          <div class="g" id="<?= $ch ?>GainCard<?= $a ?>"><?= (int)round($a * $rcDefaultRate) ?> 积分</div>
        </div>
        <?php endforeach; ?>
        <div class="rc-amt custom" data-amt="-1" onclick="pickRcAmt(this,-1,'<?= $ch ?>')">
          <div class="p">自定义</div>
          <div class="g">其他金额</div>
        </div>
      </div>
      <input id="<?= $ch ?>Custom" class="rc-input hidden" style="max-width:200px;" type="number" min="1" placeholder="输入自定义金额（元）" oninput="rcCustomAmt('<?= $ch ?>')">
    </div>
    <div class="rc-row" id="<?= $ch ?>GainLine" style="font-size:14px;color:#666;"></div>
    <button class="btn btn-primary" onclick="createRcOrder('<?= $ch ?>')">生成<?= $chName ?>收款码</button>
    <div class="rc-msg" id="<?= $ch ?>Msg"></div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <a href="<?= url('recharge/orders') ?>" class="rc-link">查看我的充值订单 →</a>
</div>

<!-- 支付弹层 -->
<div id="rcPayMask" class="rc-modal-mask hidden">
  <div class="rc-modal">
    <div style="font-size:15px;color:#333;">请使用<span id="rcPayCh">微信</span>扫码支付</div>
    <div class="rc-pay-amt" id="rcPayAmt">¥0.00</div>
    <div style="font-size:13px;color:#888;">应付金额（含尾数，便于系统自动对账）</div>
    <div class="rc-qr" id="rcQrBox">
      <img id="rcQrImg" style="display:none;max-width:180px;max-height:180px;border-radius:6px;" alt="收款码"
           onerror="this.style.display='none';var t=document.getElementById('rcQrText');if(t){t.style.display='block';t.textContent='收款码加载失败，请刷新或联系站长';}">
      <span id="rcQrText">请展示微信收款码</span>
    </div>
    <div id="rcPayGain" style="font-size:14px;color:#ea6f5a;margin-bottom:14px;"></div>
    <button class="btn btn-primary" style="width:100%;" onclick="rcSimulatePaid()">我已完成支付（模拟回调）</button>
    <?php
      $rcPayNote = '当前未启用在线支付方案，请联系站长。';
      if ($enabledScheme === 'personal') {
        $rcPayNote = '个人收款码方案：扫码付款后请点上方按钮通知系统入账（个人码无官方回调）。';
      } elseif ($enabledScheme === 'aggregate') {
        $rcPayNote = '聚合支付方案：付款成功后由聚合商 webhook 自动回调入账（需后台正确配置 notify_url）。';
      } elseif ($enabledScheme === 'merchant') {
        $rcPayNote = '官方商户方案：付款成功后由微信/支付宝 webhook 自动回调入账（需后台正确配置回调地址）。';
      }
    ?>
    <div style="margin-top:10px;font-size:12px;color:#bbb;"><?= e($rcPayNote) ?></div>
  </div>
</div>

<script>
// 全站响应契约：{code:0, message, data:{...}}
// 关键修复：相对 URL 在 pretty URL 模式下会被浏览器解析成 `/xxx/yyy/ajax` 触发 nginx try_files 回退，
// 这里用服务端 <?= url('...') ?> 输出绝对路径（包含 origin）——避免任何前端相对路径陷阱。
const RC_BASE_RATES = <?= json_encode($baseRates ?? []) ?>;
const RC_CURRENCIES = <?= json_encode(array_map(function($c){return ['code'=>$c['code'],'name'=>$c['name']];}, $currencies), JSON_UNESCAPED_UNICODE) ?>;
const RC_CAMPAIGNS = <?= json_encode($campaigns, JSON_UNESCAPED_UNICODE) ?>;
const RC_ENABLED_SCHEME = <?= json_encode($enabledScheme, JSON_UNESCAPED_UNICODE) ?>;
// personal 方案下后台配置的两路收款码（已是完整绝对 URL，由控制器 absolute_url(qr_url()) 生成）；其他方案为空 → 弹层显示提示
const RC_ONLINE_QR = <?= json_encode($onlineQr ?? [], JSON_UNESCAPED_UNICODE) ?>;
const URL_REDEEM   = <?= json_encode(url('recharge/redeem')) ?>;
const URL_CREATE   = <?= json_encode(url('recharge/createOrder')) ?>;
const URL_SIMULATE = <?= json_encode(url('recharge/simulatePaid')) ?>;
let rcCurAmt = {wechat:0, alipay:0};
let rcLastOrder = '';

function curLabel(code){
  for(const c of RC_CURRENCIES){ if(c.code===code) return c.name; }
  return code;
}
function rcShowMsg(el, msg, ok){
  el.style.color = ok ? '#2bb673' : '#e2554b';
  el.textContent = msg;
}
// 实时刷新某渠道下每张金额卡的「积分预览」（按当前选中币种）
function rcRefreshAmountCards(ch){
  const cur = document.getElementById(ch+'Currency').value;
  const label = curLabel(cur);
  const cards = [6,30,68,128,198];
  cards.forEach(function(amt){
    const g = document.getElementById(ch+'GainCard'+amt);
    if(!g) return;
    const ev = rcCalcGain(amt, ch, cur);
    g.innerHTML = '<b>'+ev.gained+'</b> '+label;
  });
}
function onRcCurrencyChange(ch){
  rcRefreshAmountCards(ch);
  rcRefreshGain(ch);
}
function switchRcTab(tab, el){
  document.querySelectorAll('.rc-tab').forEach(t=>t.classList.remove('active'));
  el.classList.add('active');
  ['card','wechat','alipay'].forEach(t=>{
    document.getElementById('tab-'+t).classList.toggle('hidden', t!==tab);
  });
  if(tab==='wechat' || tab==='alipay') rcRefreshAmountCards(tab);
}
function doRedeem(){
  const v = document.getElementById('cardInput').value.trim();
  const msg = document.getElementById('cardMsg');
  if(!v){ rcShowMsg(msg, '请输入卡密', false); return; }
  window.postJSON(URL_REDEEM, {card:v}, function(res){
    if(res && res.code === 0){ rcShowMsg(msg, res.message || '兑换成功', true); setTimeout(()=>location.reload(), 1200); }
    else { rcShowMsg(msg, res && res.message ? res.message : '兑换失败', false); }
  });
}
function pickRcAmt(el, amt, ch){
  document.querySelectorAll('#'+ch+'Amts .rc-amt').forEach(a=>a.classList.remove('sel'));
  el.classList.add('sel');
  document.getElementById(ch+'Custom').classList.toggle('hidden', amt!==-1);
  rcCurAmt[ch] = amt===-1 ? (parseFloat(document.getElementById(ch+'Custom').value)||0) : amt;
  rcRefreshGain(ch);
}
function rcCustomAmt(ch){
  const v = parseFloat(document.getElementById(ch+'Custom').value)||0;
  rcCurAmt[ch] = v; rcRefreshGain(ch);
}
function rcRefreshGain(ch){
  const cur = document.getElementById(ch+'Currency').value;
  const g = rcCalcGain(rcCurAmt[ch], ch, cur);
  const line = document.getElementById(ch+'GainLine');
  if(rcCurAmt[ch]>0){
    line.innerHTML = '基础可得 <b>'+g.base+'</b> '+curLabel(cur)
      + (g.note? ' ｜ 活动：<b style="color:#ea6f5a;">'+g.note+'</b>':'')
      + ' ｜ 实得 <b style="color:#ea6f5a;">'+g.gained+'</b> '+curLabel(cur)
      + (g.tag==='discount'? ' ｜ 应付 <b>¥'+g.pay.toFixed(2)+'</b>（少付多得）':'');
  } else { line.textContent=''; }
}
// 前端预览（与后端 RechargeService::calcGain 同规则）
function rcCalcGain(yuan, ch, cur){
  yuan = parseFloat(yuan)||0;
  const rate = (RC_BASE_RATES && RC_BASE_RATES[cur] !== undefined) ? RC_BASE_RATES[cur] : 100;
  const base = Math.round(yuan*rate);
  let gained=base, pay=yuan, tag='', note='';
  const now = Date.now();
  for(const c of RC_CAMPAIGNS){
    if(c.enabled!=1) continue;
    let chs=[]; try{chs=JSON.parse(c.channels||'[]');}catch(e){}
    if(chs.length && chs.indexOf(ch)<0) continue;
    if(c.start_at && new Date(c.start_at.replace(/-/g,'/')).getTime()>now) continue;
    if(c.end_at && new Date(c.end_at.replace(/-/g,'/')).getTime()<now) continue;
    if(parseFloat(c.min_amount)>0 && yuan<parseFloat(c.min_amount)) continue;
    if(c.type==='first'){
      gained=Math.round(base*(1+parseFloat(c.value))); tag='first'; note='首充 ×'+(1+parseFloat(c.value)); break;
    } else if(c.type==='bonus'){
      let add=Math.round(base*parseFloat(c.value));
      if(parseInt(c.cap)>0) add=Math.min(add,parseInt(c.cap));
      gained=base+add; tag='bonus'; note='加赠 '+Math.round(parseFloat(c.value)*100)+'%'+(parseInt(c.cap)>0?('（封顶 '+parseInt(c.cap)+'）'):''); break;
    } else if(c.type==='discount'){
      pay=+(yuan*parseFloat(c.value)).toFixed(2); gained=base; tag='discount'; note='充'+(Math.round(parseFloat(c.value)*10*10)/10)+'折（少付多得）'; break;
    }
  }
  return {base:base,gained:gained,pay:pay,tag:tag,note:note};
}
function getRcAmt(ch){
  const custom = document.getElementById(ch+'Custom');
  if(custom && !custom.classList.contains('hidden')){
    const v = parseFloat(custom.value)||0;
    if(v>0) return v;
  }
  const sel = document.querySelector('#'+ch+'Amts .rc-amt.sel');
  if(sel){
    const a = parseFloat(sel.getAttribute('data-amt'))||0;
    if(a>0) return a;
  }
  return 0;
}
function createRcOrder(ch){
  const amt = getRcAmt(ch);
  if(amt<=0){
    const m = document.getElementById(ch+'Msg');
    rcShowMsg(m, '请选择或输入充值金额', false);
    return;
  }
  const cur = document.getElementById(ch+'Currency').value;
  window.postJSON(URL_CREATE, {channel:ch, amount:amt, currency:cur}, function(res){
    if(!res || res.code !== 0){
      var msgEl = document.getElementById(ch+'Msg');
      msgEl.textContent = res && res.message ? res.message : '下单失败';
      msgEl.style.color = '#e2554b';
      return;
    }
    var d = res.data || {};
    rcLastOrder = d.order_no || '';
    document.getElementById('rcPayCh').textContent = (ch==='wechat'?'微信':'支付宝');
    document.getElementById('rcPayAmt').textContent = '¥'+(parseFloat(d.pay_amount)||0).toFixed(2);
    document.getElementById('rcPayGain').textContent = '支付成功后将到账 '+(d.gained||0)+' '+(d.currency_label||'');
    // 弹层二维码：personal 方案用后台配置；其他方案显示「未配置」提示
    var qrUrl = (RC_ONLINE_QR && RC_ONLINE_QR[ch]) ? RC_ONLINE_QR[ch] : '';
    // 兜底：若仍是相对路径（理论上控制器已给绝对 URL），用 absoluteAssetUrl 绝对化，防 pretty URL 解析 404
    if (qrUrl && window.absoluteAssetUrl && qrUrl.charAt(0) === '/') {
        qrUrl = window.absoluteAssetUrl(qrUrl);
    }
    var qrImg = document.getElementById('rcQrImg');
    var qrText = document.getElementById('rcQrText');
    if (qrUrl) {
      qrImg.src = qrUrl;
      qrImg.style.display = 'block';
      qrText.style.display = 'none';
      qrText.textContent = '';
    } else {
      qrImg.removeAttribute('src');
      qrImg.style.display = 'none';
      qrText.style.display = 'block';
      qrText.textContent = '站长尚未配置' + (ch==='wechat'?'微信':'支付宝') + '收款码，请联系客服';
    }
    document.getElementById('rcPayMask').classList.remove('hidden');
  });
}
function rcSimulatePaid(){
  if(!rcLastOrder){ return; }
  document.getElementById('rcPayMask').classList.add('hidden');
  window.postJSON(URL_SIMULATE, {order_no:rcLastOrder}, function(res){
    if(res && res.code === 0){ window.toast(res.message || '支付成功', 'success'); setTimeout(()=>location.reload(), 1200); }
    else { window.toast(res && res.message ? res.message : '支付失败', 'error'); }
  });
}
// 进入页面立即为隐藏的微信/支付宝通道渲染积分预览（仅做一遍；切换 tab 时会再渲）
if(document.getElementById('tab-wechat')) rcRefreshAmountCards('wechat');
if(document.getElementById('tab-alipay')) rcRefreshAmountCards('alipay');
</script>
