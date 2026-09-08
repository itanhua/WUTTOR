<?php
/**
 * 充值系统 · 支付通道配置（仅站长）
 * 三套方案并存配置，但同一时间仅一个「启用」（互斥）。
 */
$title = '充值系统 · 支付配置';
$cfg = $cfg ?? [];
$schemes = $cfg['schemes'] ?? [];
$enabled = $cfg['enabled_scheme'] ?? 'personal';
$baseRate = RechargeService::DEFAULT_BASE_RATE;
// 按币种比例（每个已启用币种都会有值；新增币种自动占一个默认槽位）
$baseRates = is_array($cfg['base_rates'] ?? null) ? $cfg['base_rates'] : [];
// 用于基础比例区块按币种渲染；缺省回退到空数组（不出错）
$currenciesEnabled = $currenciesEnabled ?? [];
function rcv($arr, $k, $d=''){ return isset($arr[$k]) ? $arr[$k] : $d; }
function rcbr($code, $rates, $def){
    return isset($rates[$code]) ? (int)$rates[$code] : (int)$def;
}
// 收款码地址归一化：复用全局 qr_url()（与充值前台控制器保持一致）
function rcQrUrl($v){
    return qr_url($v);
}
?>
<style>
.rc-qr-img{max-width:160px;max-height:160px;border:1px solid #eee;border-radius:8px;display:block;background:#fafafa;}
.rc-img-fail{max-width:220px;padding:16px 12px;text-align:center;color:#e2554b;font-size:13px;background:#fff5f4;border:1px dashed #e2554b;border-radius:8px;cursor:pointer;}
</style>
<div class="card">
  <div class="card-header">基础兑换比例（按币种）</div>
  <div class="card-body">
    <div class="note">每个币种独立设置「1 元 = N」比例；某币种输入框留空时，按默认 <?= RechargeService::DEFAULT_BASE_RATE ?> 计算。</div>
    <div class="rc-currency-rates">
      <?php if (empty($currenciesEnabled)): ?>
        <div class="text-muted" style="padding:8px 0;font-size:13px;">暂无可配置币种，请到「币种管理」启用至少一个币种后再来。</div>
      <?php else: ?>
        <?php foreach ($currenciesEnabled as $cur):
          $code  = (string)($cur['code'] ?? '');
          $name  = (string)($cur['name'] ?? $code);
          $value = rcbr($code, $baseRates, $baseRate);
        ?>
        <div class="rc-currency-rate">
          <label><?= e($name) ?> <code style="font-size:11px;color:#999;">(<?= e($code) ?>)</code></label>
          <div style="display:flex;gap:6px;align-items:center;">
            <span style="color:#666;font-size:13px;">1 元 =</span>
            <input type="number" min="1" class="form-control"
                   name="base_rates[<?= e($code) ?>]" value="<?= (int)$value ?>" style="width:120px;">
            <span style="color:#666;font-size:13px;"><?= e($name) ?></span>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <div class="note" style="margin-top:10px;padding:10px 14px;background:#fff8f3;border-left:3px solid #ea6f5a;border-radius:4px;color:#8a4a3a;font-size:12.5px;line-height:1.7;">
      💡 <strong>新增币种怎么办？</strong> 切到「币种管理」启用新币种后，回到本页会自动出现该币种的比例输入框；如未出现，刷新一次页面即可。已停用币种的比例会被服务端自动从配置中清理。
    </div>
  </div>
</div>

<form id="cfgForm">
  <input type="hidden" name="_token" value="<?= csrf_token() ?>">
  <input type="hidden" id="enabled_scheme" name="enabled_scheme" value="<?= e($enabled) ?>">

  <!-- 方案 A：个人收款码 + 尾数匹配 -->
  <div class="card rc-scheme" data-scheme="personal">
    <div class="card-header">
      <span>方案 A · 个人收款码（尾数匹配）</span>
      <span style="display:inline-block;margin-left:10px;padding:2px 8px;background:#ea6f5a;color:#fff;font-size:11px;font-weight:600;border-radius:10px;letter-spacing:.5px;vertical-align:middle;">测试专用</span>
    </div>
    <div class="card-body">
    <div class="scheme-status"></div>

    <label>微信收款码（图片上传，可选）</label>
    <div class="rc-upload" data-target="personal_wechat" data-name="personal[wechat_qr]">
      <div class="rc-upload-preview">
        <?php $wp = trim((string)rcv($schemes['personal']??[],'wechat_qr'));
        if ($wp !== ''): ?>
          <img class="rc-qr-img" src="<?= e(rcQrUrl($wp)) ?>" data-target="personal_wechat" alt="微信收款码预览">
        <?php else: ?>
          <span>暂无图片</span>
        <?php endif; ?>
      </div>
      <div class="rc-upload-actions">
        <label class="btn">
          <i class="fa-solid fa-upload"></i> 上传微信收款码
          <input type="file" accept="image/jpeg,image/png,image/gif,image/webp" data-upload-target="personal_wechat">
        </label>
        <div class="url-manual">
          <input type="text" name="personal[wechat_qr]" value="<?= e(rcv($schemes['personal']??[],'wechat_qr')) ?>" placeholder="或直接填写图片 URL">
        </div>
        <div class="rc-upload-msg" id="rc-upload-msg-personal_wechat"></div>
      </div>
    </div>

    <label style="margin-top:18px;">支付宝收款码（图片上传，可选）</label>
    <div class="rc-upload" data-target="personal_alipay" data-name="personal[alipay_qr]">
      <div class="rc-upload-preview">
        <?php $ap = trim((string)rcv($schemes['personal']??[],'alipay_qr'));
        if ($ap !== ''): ?>
          <img class="rc-qr-img" src="<?= e(rcQrUrl($ap)) ?>" data-target="personal_alipay" alt="支付宝收款码预览">
        <?php else: ?>
          <span>暂无图片</span>
        <?php endif; ?>
      </div>
      <div class="rc-upload-actions">
        <label class="btn">
          <i class="fa-solid fa-upload"></i> 上传支付宝收款码
          <input type="file" accept="image/jpeg,image/png,image/gif,image/webp" data-upload-target="personal_alipay">
        </label>
        <div class="url-manual">
          <input type="text" name="personal[alipay_qr]" value="<?= e(rcv($schemes['personal']??[],'alipay_qr')) ?>" placeholder="或直接填写图片 URL">
        </div>
        <div class="rc-upload-msg" id="rc-upload-msg-personal_alipay"></div>
      </div>
    </div>

    <div class="note blue" style="margin-top:14px;">无商户资质即可跑通：用户下单 → 应付金额 = 充值额 + 0.01~0.98 随机尾数 → 展示个人收款码 → 用户支付该尾数金额 → 后台按尾数匹配未支付订单自动入账（对账逻辑在 RechargeService::findPendingByAmount）。</div>
    <button type="button" class="btn rc-enable-btn" onclick="setScheme('personal')">启用此方案</button>
    </div>
  </div>

  <!-- 方案 B：聚合支付 SDK -->
  <div class="card rc-scheme" data-scheme="aggregate">
    <div class="card-header">方案 B · 聚合支付 SDK（码支付 / 易支付 / 虎皮椒）</div>
    <div class="card-body">
    <div class="scheme-status"></div>
    <label>服务商</label>
    <input name="aggregate[provider]" class="form-control" value="<?= e(rcv($schemes['aggregate']??[],'provider')) ?>" placeholder="如：码支付 / 易支付">
    <label style="margin-top:10px;">商户 ID</label>
    <input name="aggregate[merchant_id]" class="form-control" value="<?= e(rcv($schemes['aggregate']??[],'merchant_id')) ?>">
    <label style="margin-top:10px;">通信密钥 / Key</label>
    <input name="aggregate[key]" class="form-control" value="<?= e(rcv($schemes['aggregate']??[],'key')) ?>">
    <label style="margin-top:10px;">异步回调地址 notify_url</label>
    <input name="aggregate[notify_url]" class="form-control" value="<?= e(rcv($schemes['aggregate']??[],'notify_url')) ?>" placeholder="https://.../recharge/notify">
    <div class="note blue" style="margin-top:10px;">统一由 <code>RechargeService::completeOrder($orderNo)</code> 在回调里入账，与方案 A/C 共用同一套订单表与入账逻辑。</div>
    <button type="button" class="btn rc-enable-btn" onclick="setScheme('aggregate')">启用此方案</button>
    </div>
  </div>

  <!-- 方案 C：自有企业商户号 -->
  <div class="card rc-scheme" data-scheme="merchant">
    <div class="card-header">方案 C · 自有企业官方商户号</div>
    <div class="card-body">
    <div class="scheme-status"></div>
    <h4 style="margin:6px 0 8px;color:#ea6f5a;">微信支付</h4>
    <label>AppID</label>
    <input name="merchant[wechat][appid]" class="form-control" value="<?= e(rcv($schemes['merchant']['wechat']??[],'appid')) ?>">
    <label style="margin-top:8px;">商户号 MCH_ID</label>
    <input name="merchant[wechat][mch_id]" class="form-control" value="<?= e(rcv($schemes['merchant']['wechat']??[],'mch_id')) ?>">
    <label style="margin-top:8px;">APIv3 密钥</label>
    <input name="merchant[wechat][api_v3_key]" class="form-control" value="<?= e(rcv($schemes['merchant']['wechat']??[],'api_v3_key')) ?>">
    <label style="margin-top:8px;">商户证书 cert / key 路径</label>
    <div style="display:flex;gap:8px;">
      <input name="merchant[wechat][cert_path]" class="form-control" placeholder="cert.pem 路径" value="<?= e(rcv($schemes['merchant']['wechat']??[],'cert_path')) ?>">
      <input name="merchant[wechat][key_path]" class="form-control" placeholder="key.pem 路径" value="<?= e(rcv($schemes['merchant']['wechat']??[],'key_path')) ?>">
    </div>
    <label style="margin-top:8px;">微信 notify_url</label>
    <input name="merchant[wechat][notify_url]" class="form-control" value="<?= e(rcv($schemes['merchant']['wechat']??[],'notify_url')) ?>">
    <h4 style="margin:14px 0 8px;color:#ea6f5a;">支付宝</h4>
    <label>APP_ID</label>
    <input name="merchant[alipay][app_id]" class="form-control" value="<?= e(rcv($schemes['merchant']['alipay']??[],'app_id')) ?>">
    <label style="margin-top:8px;">应用私钥</label>
    <input name="merchant[alipay][private_key]" class="form-control" value="<?= e(rcv($schemes['merchant']['alipay']??[],'private_key')) ?>">
    <label style="margin-top:8px;">支付宝公钥</label>
    <input name="merchant[alipay][public_key]" class="form-control" value="<?= e(rcv($schemes['merchant']['alipay']??[],'public_key')) ?>">
    <label style="margin-top:8px;">网关（生产 / 沙箱）</label>
    <input name="merchant[alipay][gateway]" class="form-control" value="<?= e(rcv($schemes['merchant']['alipay']??[],'gateway')) ?>" placeholder="https://openapi.alipay.com/gateway.do">
    <div class="note blue" style="margin-top:10px;">微信/支付宝各自独立配置；回调同样统一走 completeOrder 入账。三方案共用 recharge_orders 表。</div>
    <button type="button" class="btn rc-enable-btn" onclick="setScheme('merchant')">启用此方案</button>
    </div>
  </div>

  <div style="margin-top:12px;">
    <button type="button" class="btn btn-primary" onclick="saveCfg()">保存配置</button>
    <span id="cfgMsg" style="margin-left:10px;font-size:13px;"></span>
  </div>
</form>

<script>
const RC_ENABLED = '<?= e($enabled) ?>';
function renderSchemeStatus(){
  document.querySelectorAll('.rc-scheme').forEach(card=>{
    const s = card.getAttribute('data-scheme');
    const badge = card.querySelector('.scheme-status');
    const btn = card.querySelector('.rc-enable-btn');
    const isActive = (s === RC_ENABLED && RC_ENABLED !== '');
    if(isActive){
      // 已启用：实心品牌红横幅 + 白字
      badge.innerHTML = '<span style="display:inline-block;color:#fff;">● 当前启用</span>';
      badge.classList.add('is-enabled');
      card.classList.add('is-enabled');
      // 启用按钮：实心品牌红（点击切换为「停用」）
      btn.textContent = '停用（改为无）';
      btn.classList.remove('ghost');
      btn.classList.add('is-enabled');
    } else {
      // 未启用：浅灰文字 + 描边按钮
      badge.innerHTML = '<span style="color:#999;">○ 未启用</span>';
      badge.classList.remove('is-enabled');
      card.classList.remove('is-enabled');
      btn.textContent = RC_ENABLED === '' ? '启用此方案' : ('切换为启用' + (s==='personal'?' A':s==='aggregate'?' B':' C'));
      btn.classList.remove('is-enabled');
      btn.classList.add('ghost');
    }
    // 不再 disable 按钮，让用户能自由切换（互斥逻辑交给后端 + reload）
    btn.disabled = false;
  });
}
function setScheme(s){
  // 点当前启用的 → 停用（清空）；点其他 → 启用该方案
  document.getElementById('enabled_scheme').value = (s === RC_ENABLED) ? '' : s;
  saveCfg();
}
function saveCfg(){
  const form = document.getElementById('cfgForm');
  const fd = new FormData(form);
  // 合并基础比例（按币种）；留空的币种不提交，服务端按默认比例(<?= RechargeService::DEFAULT_BASE_RATE ?>)兜底
  const rateInputs = document.querySelectorAll('input[name^="base_rates["]');
  rateInputs.forEach(function(inp){
    var v = (inp.value || '').trim();
    if(v === ''){
      fd.delete(inp.name); // 不提交 → 服务端使用默认比例
    } else {
      fd.set(inp.name, v);
    }
  });
  const m = document.getElementById('cfgMsg');
  m.style.color='#999'; m.textContent='保存中…';
  window.postJSON('<?= url('admin/rechargeConfigSave') ?>', fd, function(res){
    if(res && typeof res === 'object' && res.code === 0){
      m.style.color='#2bb673'; m.textContent=res.message||'已保存';
      setTimeout(()=>location.reload(), 600);
    } else {
      const msg = (res && res.message) || (typeof res === 'string' ? res.substring(0, 200) : '保存失败');
      m.style.color='#e2554b'; m.textContent = msg;
    }
  });
}

// ===== 收款码图片上传（用 UploadController::image, type=recharge_qr；仅站长） =====
function initRcUploads(){
  document.querySelectorAll('input[type=file][data-upload-target]').forEach(function(fileInput){
    fileInput.addEventListener('change', function(){
      if(!fileInput.files || !fileInput.files[0]) return;
      var target = fileInput.getAttribute('data-upload-target');
      var msg = document.getElementById('rc-upload-msg-' + target);
      var wrap = fileInput.closest('.rc-upload');
      var nameInput = wrap ? wrap.querySelector('input[name^="personal["]') : null;
      if(msg){ msg.className='rc-upload-msg'; msg.textContent='上传中…'; }
      var fd = new FormData();
      fd.append('type', 'recharge_qr');
      fd.append('file', fileInput.files[0]);
      var xhr = new XMLHttpRequest();
      xhr.open('POST', url('upload/image'), true);
      xhr.onload = function(){
        var res = {}; try { res = JSON.parse(xhr.responseText); } catch(_) { res = {code:1, message:'[响应不是 JSON] '+String(xhr.responseText||'').substring(0,200)}; }
        if(xhr.status === 200 && res && res.code === 0 && res.data && res.data.url){
          if(msg){ msg.className='rc-upload-msg ok'; msg.textContent='上传成功'; }
          // 把上传后绝对 URL 同时写入隐藏 input（用上传返回的相对路径在服务端再渲染时绝对化）
          var relUrl = res.data.url;
          if(nameInput){
            // 存相对路径，后端 upload_url() 渲染时再一致绝对化（避免旧写法缺 /public 前缀 → 404）
            nameInput.value = relUrl;
          }
          // 刷新预览（前端用 absoluteAssetUrl 拼绝对地址，等价后端 upload_url）
          var preview = wrap ? wrap.querySelector('.rc-upload-preview') : null;
          if(preview){ renderRcPreview(preview, window.absoluteAssetUrl(relUrl), target); }
        } else {
          if(msg){ msg.className='rc-upload-msg err'; msg.textContent=(res && res.message) || ('上传失败（HTTP '+xhr.status+'）'); }
        }
        fileInput.value = '';
      };
      xhr.onerror = function(){
        if(msg){ msg.className='rc-upload-msg err'; msg.textContent='网络异常，上传失败'; }
        fileInput.value = '';
      };
      xhr.send(fd);
    });
  });
  // 「或直接填写图片 URL」同步预览
  document.querySelectorAll('.rc-upload input[name^="personal["]').forEach(function(inp){
    inp.addEventListener('input', function(){
      var wrap = inp.closest('.rc-upload');
      var preview = wrap ? wrap.querySelector('.rc-upload-preview') : null;
      if(!preview) return;
      var v = (inp.value || '').trim();
      var target = wrap.getAttribute('data-target') || '';
      var src = v ? (v.match(/^https?:\/\//) ? v : window.absoluteAssetUrl(v)) : '';
      renderRcPreview(preview, src, target);
    });
  });
}
function rcTriggerUpload(target){
  var inp = document.querySelector('input[type=file][data-upload-target="'+target+'"]');
  if(inp) inp.click();
}
function renderRcPreview(preview, src, target){
  if(!preview) return;
  if(!src){
    preview.innerHTML = '<span>暂无图片</span>';
    return;
  }
  preview.innerHTML = '';
  var img = document.createElement('img');
  img.className = 'rc-qr-img';
  img.alt = '收款码预览';
  img.src = src;
  img.onerror = function(){
    preview.innerHTML = '<div class="rc-img-fail" onclick="rcTriggerUpload(\''+target+'\')">图片加载失败 · 点击重新上传</div>';
  };
  preview.appendChild(img);
}
// 为服务端已渲染的图片绑定加载失败处理（点击重试上传）
document.querySelectorAll('.rc-upload-preview img').forEach(function(img){
  var target = img.getAttribute('data-target') || '';
  img.addEventListener('error', function(){
    var p = img.parentNode;
    if(p) p.innerHTML = '<div class="rc-img-fail" onclick="rcTriggerUpload(\''+target+'\')">图片加载失败 · 点击重新上传</div>';
  });
  if(img.complete && img.naturalWidth === 0 && img.src){
    var p = img.parentNode;
    if(p) p.innerHTML = '<div class="rc-img-fail" onclick="rcTriggerUpload(\''+target+'\')">图片加载失败 · 点击重新上传</div>';
  }
});
renderSchemeStatus();
if(document.readyState === 'loading'){
  document.addEventListener('DOMContentLoaded', initRcUploads);
} else {
  initRcUploads();
}
</script>
