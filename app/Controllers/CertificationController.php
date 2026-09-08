<?php
/**
 * 认证中心控制器
 */
class CertificationController extends Controller
{
    protected $middleware = [
        ['middleware' => 'auth'],
        ['middleware' => 'banned', 'only' => ['apply', 'reapply']],
        ['middleware' => 'csrf', 'only' => ['apply', 'reapply']],
    ];

    /**
     * 认证中心首页 - 按认证项目组切换
     */
    public function index($group_id = null)
    {
        $user = Auth::user();
        $groups = Model::table('certification_groups')->where('status', 1)->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->get();
        $group_id = (int)($group_id ?? input('group_id', 0));
        if (!$group_id && !empty($groups)) $group_id = (int)$groups[0]['id'];

        $cert = Model::table('certifications')->where('user_id', $user['id'])->where('group_id', $group_id)->orderBy('created_at', 'DESC')->first();

        // 状态映射
        $status = 'none'; // none / pending / approved / rejected
        if ($cert) {
            if ($cert['status'] == 0) $status = 'pending';
            elseif ($cert['status'] == 1) $status = 'approved';
            elseif ($cert['status'] == 2) $status = 'rejected';
        }
        // 兼容：旧库用户 is_certified=1 且该 group_id 下无 cert 时（旧数据都是默认组）
        if ($user['is_certified'] == 1 && !$cert && $group_id == 1) {
            $status = 'approved';
        }

        $certSubmitCost = $this->getCertSubmitCost();

        $this->view('certification/index', [
            'cert' => $cert,
            'status' => $status,
            'user' => $user,
            'groups' => $groups,
            'currentGroupId' => $group_id,
            'certSubmitCost' => $certSubmitCost,
        ]);
    }

    /**
     * 读取「认证提交消耗」规则（point_rules.code=cert_submit）的展示信息：
     *  - currency    币种 code（来自规则表的 currency 列，后台可改）
     *  - currency_label 币种显示名（从 currencies 表实时联动后台改名）
     *  - amount      消耗数量
     *  - enabled     是否启用（停用时不展示费用提示，提交也走零扣减路径）
     * 用于前台按钮文案/费用提示/错误提示联动后台配置。
     */
    private function getCertSubmitCost()
    {
        $rule = PointService::rule('cert_submit');
        if (!$rule || (int)($rule['enabled'] ?? 0) !== 1) {
            return ['currency' => 'token', 'currency_label' => PointService::currencyLabel('token'), 'amount' => 0, 'enabled' => 0];
        }
        $code = !empty($rule['currency']) ? (string)$rule['currency'] : 'token';
        return [
            'currency'       => $code,
            'currency_label' => PointService::currencyLabel($code),
            'amount'         => (int)($rule['amount'] ?? 0),
            'enabled'        => 1,
        ];
    }

    /**
     * 提交认证申请
     */
    public function apply()
    {
        abortIfBanned();
        if (!can('certification.apply')) $this->error('无权限：未授予「提交认证」');
        $user = Auth::user();

        // 兼容：旧 is_certified=1 不再强约束，因为后续可能多套认证
        $groupId = (int)input('group_id', 1);
        $group = Model::table('certification_groups')->where('id', $groupId)->where('status', 1)->first();
        if (!$group) $this->error('认证项目不存在');

        // 默认组（实名认证）下：已认证不允许再申请
        if ($groupId == 1 && $user['is_certified'] == 1) $this->error('您已通过实名认证');

        // 检查该 group 下是否有审核中的申请
        $pending = Model::table('certifications')
            ->where('user_id', $user['id'])
            ->where('group_id', $groupId)
            ->where('status', 0)
            ->first();
        if ($pending) $this->error('您在该认证项目下已有审核中的申请，请耐心等待');

        $realName = trim(input('real_name'));
        $idCard = trim(input('id_card'));
        $phone = trim(input('phone'));
        $idCardFront = trim(input('id_card_front'));
        $idCardBack = trim(input('id_card_back'));
        $handPhoto = trim(input('hand_photo'));
        $extraNote = trim(input('extra_note'));

        // 动态校验：根据当前 group 的认证项配置校验
        $certItems = Model::table('certification_items')->where('group_id', $groupId)->where('status', 1)->orderBy('sort_order', 'ASC')->get();
        $formData = [];
        foreach ($certItems as $item) {
            $val = trim(input($item['name'], ''));
            if ($item['required'] && $val === '') {
                $this->error('请填写/上传：' . $item['label']);
            }
            $formData[$item['name']] = $val;
        }

        // 兼容固定字段校验（若存在对应认证项，仅实名认证组）
        if ($groupId == 1) {
            if ($realName && !preg_match('/^[\x{4e00}-\x{9fa5}]{2,10}$/u', $realName)) $this->error('姓名格式不正确');
            if ($idCard && !preg_match('/^\d{17}[\dXx]$/', $idCard)) $this->error('身份证号格式不正确');
            if ($phone && !preg_match('/^1[3-9]\d{9}$/', $phone)) $this->error('手机号格式不正确');
        }

        // 频率限制：同一用户两次申请的间隔天数（0 或负数视为不限制）。
        // 间隔天数由后台 → 系统设置 → 认证申请频率限制（天，key=cert_limit_days） 控制，立即生效。
        $limitDays = (int)setting('cert_limit_days', 7);
        $limitDays = max(0, $limitDays);
        if ($limitDays === 0) {
            $recent = null;
        } else {
            $recent = Model::query(
                'SELECT created_at FROM certifications WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY created_at DESC LIMIT 1',
                [$user['id'], $limitDays]
            );
        }
        if ($recent) {
            $this->error('申请过于频繁，请' . $limitDays . '天后再试');
        }

        $now = date('Y-m-d H:i:s');

        // 认证提交消耗（point_rules.code=cert_submit，币种/金额后台可改）；余额不足直接拦截
        $cost = $this->getCertSubmitCost();
        $certSpend = PointService::spend($user['id'], 'cert_submit', $cost['currency'], $groupId, null, '提交认证申请');
        if (!$certSpend['ok']) {
            // 联动后台币种改名：币种显示名走 PointService::currencyLabel()，避免"Token"硬编码
            $curLabel = $cost['currency_label'] ?: PointService::currencyLabel($cost['currency']);
            $reason = $certSpend['reason'] === 'insufficient'
                ? $curLabel . ' 余额不足，无法提交认证申请'
                : '提交认证消耗 ' . $curLabel . ' 失败';
            $this->error($reason);
        }

        Model::table('certifications')->insert([
            'user_id' => $user['id'],
            'real_name' => $realName ?: ($formData['real_name'] ?? null),
            'id_card' => $idCard ? encrypt_value($idCard) : (isset($formData['id_card']) && $formData['id_card'] ? encrypt_value($formData['id_card']) : null),
            'phone' => $phone ?: ($formData['phone'] ?? null),
            'id_card_front' => $idCardFront ?: ($formData['id_card_front'] ?? null),
            'id_card_back' => $idCardBack ?: ($formData['id_card_back'] ?? null),
            'hand_photo' => $handPhoto ?: ($formData['hand_photo'] ?? null),
            'extra_note' => $extraNote ?: ($formData['extra_note'] ?? null),
            'form_data' => json_encode($formData, JSON_UNESCAPED_UNICODE),
            'group_id' => $groupId,
            'status' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->success(null, '认证申请已提交，请等待审核（通常1-3个工作日）');
    }

    /**
     * 重新提交（驳回后）
     */
    public function reapply()
    {
        abortIfBanned();
        if (!can('certification.apply')) $this->error('无权限：未授予「提交认证」');
        $user = Auth::user();
        $lastCert = Model::table('certifications')->where('user_id', $user['id'])->orderBy('created_at', 'DESC')->first();
        if (!$lastCert || $lastCert['status'] != 2) $this->error('当前状态不允许重新申请');

        // 复用 apply 逻辑
        $this->apply();
    }

    /**
     * 查询认证状态（API）
     */
    public function status()
    {
        $user = Auth::user();
        $cert = Model::table('certifications')->where('user_id', $user['id'])->orderBy('created_at', 'DESC')->first();

        $data = [
            'status' => 'none',
            'statusText' => '未申请',
            'isCertified' => $user['is_certified'] == 1,
            'realName' => null,
            'idCard' => null,
            'phone' => null,
            'rejectReason' => null,
            'appliedAt' => null,
            'reviewedAt' => null,
        ];

        if ($cert) {
            if ($cert['status'] == 0) { $data['status'] = 'pending'; $data['statusText'] = '审核中'; }
            elseif ($cert['status'] == 1) { $data['status'] = 'approved'; $data['statusText'] = '已通过'; }
            elseif ($cert['status'] == 2) { $data['status'] = 'rejected'; $data['statusText'] = '已驳回'; }
            $data['realName'] = mask_name($cert['real_name']);
            $data['idCard'] = mask_id_card(decrypt_value($cert['id_card']));
            $data['phone'] = mask_phone($cert['phone']);
            $data['rejectReason'] = $cert['reject_reason'];
            $data['appliedAt'] = $cert['created_at'];
            $data['reviewedAt'] = $cert['reviewed_at'];
        }

        $this->success($data);
    }
}
