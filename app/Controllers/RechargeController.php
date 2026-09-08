<?php
/**
 * 积分充值 - 前台充值中心
 * 路由：recharge/index（充值中心）、recharge/redeem（卡密兑换）、recharge/createOrder（生成订单）、
 *       recharge/orders（我的订单）、recharge/simulatePaid（开发期模拟支付回调）
 */
class RechargeController extends Controller
{
    protected $middleware = [
        ['middleware' => ['auth'], 'except' => []],
        ['middleware' => 'csrf', 'only' => ['redeem', 'createOrder', 'simulatePaid']],
    ];

    /**
     * 充值中心
     */
    public function index()
    {
        $userId = Auth::id();
        $balances = PointService::userBalances($userId);
        $currencies = PointService::currencies(true);
        $cfg = RechargeService::getConfig();
        $baseRate = (int)($cfg['base_rate'] ?? 100);
        // 全部生效中的活动（用于顶部活动横幅 + 金额档位预览）
        $campaigns = Model::table('recharge_campaigns')->where('enabled', 1)->get();
        foreach ($campaigns as &$c) {
            $c['channels_arr'] = json_decode($c['channels'] ?? '[]', true) ?: [];
        }
        unset($c);
        // 当前启用的支付方案（用于提示支付方式）
        $enabledScheme = (string)($cfg['enabled_scheme'] ?? '');
        // 仅当启用了某套在线方案时，前台才显示微信/支付宝入口（卡密 tab 始终在）
        $showOnlineEntry = in_array($enabledScheme, [
            RechargeService::SCHEME_PERSONAL,
            RechargeService::SCHEME_AGGREGATE,
            RechargeService::SCHEME_MERCHANT,
        ], true);
        // personal 方案下，从配置里取出两路收款码 → 归一化 + 拼成完整绝对 URL（避免 pretty URL 下相对路径 404）
        $onlineQr = ['wechat' => '', 'alipay' => ''];
        if ($enabledScheme === RechargeService::SCHEME_PERSONAL) {
            $onlineQr['wechat'] = absolute_url(qr_url((string)($cfg['schemes']['personal']['wechat_qr'] ?? '')));
            $onlineQr['alipay'] = absolute_url(qr_url((string)($cfg['schemes']['personal']['alipay_qr'] ?? '')));
        }

        $this->view('recharge/index', [
            'balances'        => $balances,
            'currencies'      => $currencies,
            'baseRate'        => $baseRate,
            'baseRates'       => $cfg['base_rates'],
            'campaigns'       => $campaigns,
            'enabledScheme'   => $enabledScheme,
            'showOnlineEntry' => $showOnlineEntry,
            'onlineQr'        => $onlineQr,
            'title'           => '积分充值',
        ]);
    }

    /**
     * 卡密兑换（ajax）
     * 响应契约：全站统一 {code:0, message, data:{...}} （与 $this->success() 一致）
     */
    public function redeem()
    {
        try {
            $card = trim((string)input('card', ''));
            $res = RechargeService::redeem($card, Auth::id());
            if (!$res['ok']) {
                $this->error($res['msg'] ?? '兑换失败');
                return;
            }
            $label = PointService::currencyLabel($res['currency']);
            $this->success([
                'amount'         => (int)$res['amount'],
                'currency'       => $res['currency'],
                'currency_label' => $label,
            ], '充值成功！+' . $res['amount'] . ' ' . $label . ' 已到账');
        } catch (\Throwable $e) {
            error_log('[recharge/redeem] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $this->error('兑换异常：' . $e->getMessage());
        }
    }

    /**
     * 生成充值订单（微信/支付宝）（ajax）
     * 响应契约：全站统一 {code:0, message, data:{...}}
     */
    public function createOrder()
    {
        try {
            $channel  = trim((string)input('channel', ''));
            $yuan     = (float)input('amount', 0);
            $currency = trim((string)input('currency', 'token'));

            $res = RechargeService::createOrder(Auth::id(), $channel, $yuan, $currency);
            if (!$res['ok']) {
                $this->error($res['msg'] ?? '下单失败');
                return;
            }
            $res['currency_label'] = PointService::currencyLabel($res['currency']);
            $this->success($res);
        } catch (\Throwable $e) {
            error_log('[recharge/createOrder] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $this->error('下单异常：' . $e->getMessage());
        }
    }

    /**
     * 我的充值订单
     */
    public function orders()
    {
        $userId = Auth::id();
        $list = Model::table('recharge_orders')
            ->where('user_id', $userId)
            ->orderBy('created_at', 'DESC')
            ->limit(50)
            ->get();

        // 合并卡密充值记录（已使用的卡密 = 充值成功）
        $cards = Model::table('recharge_cards')
            ->where('used_by', $userId)
            ->where('status', 'used')
            ->orderBy('used_at', 'DESC')
            ->limit(50)
            ->get();
        foreach ($cards as $c) {
            $list[] = [
                'order_no'   => 'CARD' . $c['id'],
                'channel'    => 'card',
                'amount'     => null, // 卡密无现金支付，模板显示 —
                'gained'     => (int)$c['amount'],
                'currency'   => $c['currency'],
                'status'     => 'paid',
                'created_at' => $c['used_at'] ?: $c['created_at'],
            ];
        }

        // 按时间倒序合并展示（最多保留 50 条）
        usort($list, function ($a, $b) {
            return strtotime($b['created_at'] ?? 0) <=> strtotime($a['created_at'] ?? 0);
        });
        $list = array_slice($list, 0, 50);

        $this->view('recharge/orders', [
            'orders' => $list,
            'title'  => '我的充值订单',
        ]);
    }

    /**
     * 开发期模拟支付回调（仅用于联调；正式上线由微信/支付宝 notify_url 触发 completeOrder）
     * 响应契约：全站统一 {code:0, message, data:{...}}
     */
    public function simulatePaid()
    {
        try {
            $orderNo = trim((string)input('order_no', ''));
            if ($orderNo === '') {
                $this->error('缺少订单号');
                return;
            }
            $res = RechargeService::completeOrder($orderNo, 'simulate');
            if (!$res['ok']) {
                $this->error($res['msg'] ?? '支付失败');
                return;
            }
            $label = PointService::currencyLabel($res['currency']);
            $this->success([
                'gained'         => (int)$res['gained'],
                'currency'       => $res['currency'],
                'currency_label' => $label,
            ], '支付成功！+' . $res['gained'] . ' ' . $label . ' 已入账');
        } catch (\Throwable $e) {
            error_log('[recharge/simulatePaid] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $this->error('支付回调异常：' . $e->getMessage());
        }
    }
}
