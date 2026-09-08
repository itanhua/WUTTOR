<?php
/**
 * 用户控制器
 */
class UserController extends Controller
{
    protected $middleware = [
        ['middleware' => 'auth', 'except' => ['profile', 'followers', 'card']],
        ['middleware' => 'banned', 'only' => ['update', 'password', 'updateAvatar', 'follow']],
        ['middleware' => 'csrf', 'only' => ['update', 'password', 'updateAvatar', 'follow', 'inviteGenerate', 'inviteRevoke']],
    ];

    /**
     * 我的个人中心
     */
    public function index()
    {
        $this->profile(Auth::id());
    }

    /**
     * 用户名片数据接口（hover 名片用，公开）
     * 返回 JSON：{code:0, data:{...}}，前端 usercard.js 懒加载并缓存。
     */
    public function card()
    {
        $id = (int)input('id');
        if ($id <= 0) {
            return $this->json(['code' => 1, 'message' => '参数错误'], 400);
        }
        try {
            $u = Model::table('users')
                ->select('id', 'username', 'nickname', 'avatar', 'role', 'is_certified', 'certified_at',
                          'show_cert_badges', 'created_at', 'status', 'token', 'byte', 'level', 'bio')
                ->where('id', $id)->first();
        } catch (\Throwable $e) {
            return $this->json(['code' => 1, 'message' => '查询失败'], 500);
        }
        if (!$u) {
            return $this->json(['code' => 1, 'message' => '用户不存在'], 404);
        }

        try {
            $levelInfo = \PointService::getLevelInfo([
                'id'    => $u['id'],
                'level' => (int)$u['level'],
                'byte'  => (int)$u['byte'],
            ]);
        } catch (\Throwable $e) {
            $levelInfo = ['level' => (int)$u['level'], 'name' => ''];
        }

        $likeCount = 0;
        try {
            $likeCount = (int)Model::table('interactions')
                ->where('target_type', 'post')->where('target_id', $id)
                ->where('action_type', 'like')->count();
        } catch (\Throwable $e) {}

        $isOwn     = Auth::check() && Auth::id() == $id;
        $isLogged  = Auth::check();
        $isFollowing = false;
        if ($isLogged) {
            try {
                $isFollowing = Model::table('interactions')
                    ->where('user_id', Auth::id())->where('target_type', 'user')
                    ->where('target_id', $id)->where('action_type', 'follow')->first() ? true : false;
            } catch (\Throwable $e) {}
        }

        // 名片 5 项统计：获赞 / 主题 / 回帖 / 关注 / 粉丝，与 profile() 同步（profile 用的 + likes 是用户收到的赞）
        $postCount      = 0;
        $commentCount   = 0;
        $followerCount  = 0;
        $followingCount = 0;
        try { $postCount = (int)Model::table('posts')->where('user_id', $id)->where('status', 1)->count(); } catch (\Throwable $e) {}
        try {
            $commentCount = (int)Model::table('comments')
                ->join('posts', 'comments.post_id', '=', 'posts.id')
                ->where('comments.user_id', $id)
                ->where('comments.status', 1)
                ->where('posts.status', 1)
                ->count();
        } catch (\Throwable $e) {}
        try { $followerCount  = (int)Model::table('interactions')->where('target_type', 'user')->where('target_id', $id)->where('action_type', 'follow')->count(); } catch (\Throwable $e) {}
        try { $followingCount = (int)Model::table('interactions')->where('user_id', $id)->where('action_type', 'follow')->count(); } catch (\Throwable $e) {}

        // 预渲染：认证徽章（一个或多个）+ UID 徽章 —— 让前端 popover 无需关心 certification_groups schema
        $certBadgesHtml = '';
        $uidBadgeHtml   = '';
        try {
            if (function_exists('cert_badge_html')) $certBadgesHtml = cert_badge_html($u, 16) ?: '';
            if (function_exists('uid_badge'))       $uidBadgeHtml   = uid_badge($u) ?: '';
        } catch (\Throwable $e) {}

        $data = [
            'id'            => (int)$u['id'],
            'username'      => $u['username'],
            'nickname'      => $u['nickname'],
            'avatar'        => upload_url($u['avatar'] ?? ''),
            'role'          => $u['role'],
            'roleName'      => role_display_name($u['role']),
            'showRoleBadge' => role_show_badge($u['role']),
            'isCertified'   => !empty($u['is_certified']),
            'certBadgesHtml'=> $certBadgesHtml,
            'uidBadgeHtml'  => $uidBadgeHtml,
            'bio'           => $u['bio'] ?? '',
            'createdAt'     => $u['created_at'],
            'status'        => (int)$u['status'],
            'isOwn'         => $isOwn,
            'isLogged'      => $isLogged,
            'isFollowing'   => $isFollowing,
            'level'         => (int)($levelInfo['level'] ?? (int)$u['level']),
            'levelName'     => (string)($levelInfo['name'] ?? ''),
            'byte'          => (int)$u['byte'],
            'byteLabel'     => \PointService::currencyLabel('byte'),
            'token'         => (int)$u['token'],
            'tokenLabel'    => \PointService::currencyLabel('token'),
            'likeCount'     => $likeCount,
            'postCount'     => $postCount,
            'commentCount'  => $commentCount,
            'followingCount'=> $followingCount,
            'followerCount' => $followerCount,
            'profileUrl'    => url('user/profile', ['id' => $id]),
            // 私信入口（直接打开与该用户的会话；message/index 有 auth middleware，
            //   未登录会被拦截跳登录页，相当于引导登录）。
            // 自己看自己时不返回 URL，由前端判定后隐藏按钮，避免点自己进 0 会话空白页。
            'pmUrl'         => $isOwn ? '' : url('message/index', ['id' => $id]),
        ];

        return $this->json(['code' => 0, 'message' => 'ok', 'data' => $data]);
    }

    /**
     * 用户主页（公开资料 + 帖子）
     */
    public function profile($id = null)
    {
        $id = (int)($id ?? input('id'));
        $user = Model::table('users')->select('id', 'username', 'email', 'phone', 'avatar', 'nickname', 'bio', 'role', 'is_certified', 'certified_at', 'show_cert_badges', 'created_at', 'status', 'token', 'byte', 'level')->where('id', $id)->first();
        if (!$user) {
            View::render('errors/404', [], 404);
            return;
        }

        // 统计
        $postCount = Model::table('posts')->where('user_id', $id)->where('status', 1)->count();
        $followerCount = Model::table('interactions')->where('target_type', 'user')->where('target_id', $id)->where('action_type', 'follow')->count();
        $followingCount = Model::table('interactions')->where('user_id', $id)->where('action_type', 'follow')->count();
        $likeCount = Model::table('interactions')->where('user_id', $id)->where('action_type', 'like')->count();
        $collectCount = Model::table('interactions')->where('user_id', $id)->where('target_type', 'post')->where('action_type', 'collect')->count();
        // 回帖数：用户在可见评论 + 可见帖子下的回复条数（不含已被删除/隐藏的）
        $commentCount = Model::table('comments')
            ->join('posts', 'comments.post_id', '=', 'posts.id')
            ->where('comments.user_id', $id)
            ->where('comments.status', 1)
            ->where('posts.status', 1)
            ->count();

        // 是否已关注
        $isFollowing = false;
        if (Auth::check()) {
            $isFollowing = Model::table('interactions')
                ->where('user_id', Auth::id())
                ->where('target_type', 'user')
                ->where('target_id', $id)
                ->where('action_type', 'follow')
                ->first() ? true : false;
        }

        $isOwn = Auth::check() && Auth::id() == $id;

        // 下方切换显示：帖子 / 关注 / 粉丝 / 收藏（每页 20 条）
        $allowedTabs = ['posts', 'replies', 'following', 'followers', 'collects'];
        $tab = input('tab', 'posts');
        if (!in_array($tab, $allowedTabs)) $tab = 'posts';
        $page = max(1, (int)input('page', 1));
        $perPage = 50;

        $tabItems = [];
        $tabTotal = 0;
        if ($tab === 'posts') {
            $result = Model::table('posts')->where('user_id', $id)->where('status', 1)->orderBy('created_at', 'DESC')->paginate($page, $perPage);
            foreach ($result['data'] as &$p) {
                $p['category'] = Model::table('categories')->where('id', $p['category_id'])->first();
            }
            $tabItems = $result['data'];
            $tabTotal = $result['total'];
        } elseif ($tab === 'following') {
            $result = Model::table('interactions')->where('user_id', $id)->where('action_type', 'follow')->orderBy('created_at', 'DESC')->paginate($page, $perPage);
            $tabItems = $this->loadUsersByIds(array_column($result['data'], 'target_id'));
            $tabTotal = $result['total'];
        } elseif ($tab === 'followers') {
            $result = Model::table('interactions')->where('target_type', 'user')->where('target_id', $id)->where('action_type', 'follow')->orderBy('created_at', 'DESC')->paginate($page, $perPage);
            $tabItems = $this->loadUsersByIds(array_column($result['data'], 'user_id'));
            $tabTotal = $result['total'];
        } else { // collects
            $result = Model::table('interactions')
                ->where('user_id', $id)
                ->where('target_type', 'post')
                ->where('action_type', 'collect')
                ->orderBy('created_at', 'DESC')
                ->paginate($page, $perPage);
            $collectedPostIds = array_column($result['data'], 'target_id');
            $tabItems = $this->loadPostsByIds($collectedPostIds);
            $tabTotal = $result['total'];
        }
        if ($tab === 'replies') {
            // 回帖：作者所有评论 + 关联原帖（用于显示引用位置和点击跳转）
            $result = Model::table('comments')
                ->select('comments.id', 'comments.content', 'comments.created_at', 'comments.post_id', 'posts.title AS post_title', 'posts.user_id AS post_user_id')
                ->join('posts', 'comments.post_id', '=', 'posts.id')
                ->where('comments.user_id', $id)
                ->where('comments.status', 1)
                ->where('posts.status', 1)
                ->orderBy('comments.created_at', 'DESC')
                ->paginate($page, $perPage);
            // 关联原帖所属板块，便于卡片上展示板块跳转
            foreach ($result['data'] as &$r) {
                $r['post_category'] = Model::table('categories')->where('id', Model::table('posts')->where('id', $r['post_id'])->first()['category_id'] ?? 0)->first();
            }
            $tabItems = $result['data'];
            $tabTotal = $result['total'];
        }

        $this->view('user/profile', [
            'user' => $user,
            'postCount' => $postCount,
            'followerCount' => $followerCount,
            'followingCount' => $followingCount,
            'likeCount' => $likeCount,
            'collectCount' => $collectCount,
            'commentCount' => $commentCount,
            'isFollowing' => $isFollowing,
            'isOwn' => $isOwn,
            'levelInfo' => PointService::getLevelInfo($user),
            'tab' => $tab,
            'tabItems' => $tabItems,
            'tabTotal' => $tabTotal,
            'page' => $page,
            'perPage' => $perPage,
        ]);
    }

    /**
     * 我的积分（独立页 user/points）
     * 路由 r=user/points 直接映射到本方法，无需改路由表。
     */
    public function points()
    {
        $user = Auth::user();
        if (!$user) {
            // 跳登录时带上当前 URL，登录成功后回跳继续访问积分流水
            $back = url('user/points');
            redirect(url('auth/login') . '?redirect=' . urlencode($back));
            return;
        }

        // 等级 / 成长值信息
        $levelInfo = PointService::getLevelInfo($user);

        // 签到状态：今日是否已签 + 当前连续天数（用于签到卡展示）
        $today = date('Y-m-d');
        $signedToday = (bool)Model::table('sign_logs')->where('user_id', $user['id'])->where('sign_date', $today)->first();
        $streak = 0;
        $lastSign = Model::table('sign_logs')->where('user_id', $user['id'])->orderBy('sign_date', 'DESC')->first();
        if ($lastSign) {
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $streak = ($lastSign['sign_date'] === $today || $lastSign['sign_date'] === $yesterday) ? (int)$lastSign['streak'] : 0;
        }

        // 流水 Tab：获取 / 消耗（privilege 由前端直接渲染等级阶梯，不查流水）
        $tab = input('tab', 'earn');
        $currency = trim((string)input('currency', ''));
        // 防止注入：仅接受 letter/digit/underscore，限制 32 字符，且必须是当前已存在的币种 code
        if ($currency !== '' && !preg_match('/^[a-z][a-z0-9_]{0,30}$/', $currency)) {
            $currency = '';
        }
        if ($currency !== '' && !PointService::currencyExists($currency)) {
            $currency = '';
        }
        $page = max(1, (int)input('page', 1));
        $perPage = 20;

        $logs = [];
        $logTotal = 0;
        if ($tab === 'earn' || $tab === 'spend') {
            $q = Model::table('points_log')
                ->where('user_id', $user['id'])
                ->where('type', $tab);
            if ($currency !== '') {
                $q->where('currency', $currency);
            }
            $result = $q->orderBy('created_at', 'DESC')
                ->orderBy('id', 'DESC')
                ->paginate($page, $perPage);
            $logs = $result['data'];
            $logTotal = $result['total'];
        }

        // 用户可见的全部启用币种（用于流水 tab 顶部币种 chip 筛选条）
        // 自定义币种即使余额为 0 也展示：让「新增币种在积分页可见」是显式的 UI 反馈
        $currencies = PointService::currencies(true);

        // 等级阶梯（动态读取 user_levels 表，后台可新增/修改名称与阈值）
        $lvRows = PointService::loadLevels();

        // 规则 code -> 中文名 映射（流水展示用）
        $ruleRows = Model::table('point_rules')->get();
        $ruleNames = [];
        foreach ($ruleRows as $r) $ruleNames[$r['code']] = $r['name'];
        // 合并消耗类型中文名（point_rules 只覆盖 earn；spend source 如 reward/self_pin/cert_submit 不在其中）。
        // 新增消耗类型时只需在 PointService::$spendSourceLabels 补一行，此处自动生效。
        foreach (PointService::spendSourceLabels() as $code => $label) {
            $ruleNames[$code] = $label;
        }
        // 合并主动发放（grant）来源中文名（如 bounty_refund 悬赏提前结束返还，不在 point_rules）。
        // 新增 grant 类型时只需在 PointService::$grantSourceLabels 补一行，此处自动生效。
        foreach (PointService::grantSourceLabels() as $code => $label) {
            $ruleNames[$code] = $label;
        }
        // 合并 earn 派来源中文名（PointService 直接写 points_log、跳过 point_rules 的来源，如被打赏入账 'rewarded'）。
        // 新增时只需在 PointService::$earnSourceLabels 补一行，此处自动生效。
        foreach (PointService::earnSourceLabels() as $code => $label) {
            $ruleNames[$code] = $label;
        }
        // 合并充值来源中文名（recharge_card/recharge_order 走 RechargeService::grant 直发，不在 point_rules）。
        foreach (RechargeService::sourceLabels() as $code => $label) {
            $ruleNames[$code] = $label;
        }

        // 全部启用币种余额（内置 token/byte + 后台自定义币种），按启用状态动态展示
        $balances = PointService::userBalances($user['id']);

        $this->view('user/points', [
            'user' => $user,
            'levelInfo' => $levelInfo,
            'tab' => $tab,
            'logs' => $logs,
            'logTotal' => $logTotal,
            'page' => $page,
            'perPage' => $perPage,
            'lvRows' => $lvRows,
            'ruleNames' => $ruleNames,
            'balances' => $balances,
            'signedToday' => $signedToday,
            'streak' => $streak,
            'currencies' => $currencies,
            'currency' => $currency,
        ]);
    }

    /**
     * 每日签到（幂等：每用户每天一次）
     * 路由 r=user/sign 直接映射；需登录 + CSRF。
     */
    public function sign()
    {
        if (!is_logged_in()) { $this->error('请先登录'); return; }
        csrf_check();
        $uid = Auth::id();
        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');

        // 已签到则直接返回状态（防前端重复点击 / 重复请求）
        $already = Model::table('sign_logs')->where('user_id', $uid)->where('sign_date', $today)->first();
        if ($already) {
            $this->success(['signed' => true, 'already' => true, 'streak' => (int)$already['streak']], '今日已签到');
            return;
        }

        // 连续天数：昨日有签到则 +1，否则重置为 1
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $yst = Model::table('sign_logs')->where('user_id', $uid)->where('sign_date', $yesterday)->first();
        $streak = $yst ? ((int)$yst['streak'] + 1) : 1;

        Model::table('sign_logs')->insert([
            'user_id' => $uid,
            'sign_date' => $today,
            'streak' => $streak,
            'created_at' => $now,
        ]);

        // 积分：每日签到获取 Token + Byte（幂等，source_id = 今日时间戳）
        $earned = PointService::earnAction($uid, 'daily_sign', strtotime($today), '每日签到');

        $this->success([
            'signed' => true,
            'already' => false,
            'streak' => $streak,
            'points' => $earned,
        ], '签到成功');
    }

    /**
     * 按 id 批量加载帖子（收藏列表用）
     */
    private function loadPostsByIds($ids)
    {
        if (empty($ids)) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $posts = Model::query("SELECT * FROM posts WHERE id IN ($placeholders) AND status = 1 ORDER BY FIELD(id, " . implode(',', array_map('intval', $ids)) . ")", $ids);
        foreach ($posts as &$p) {
            $p['category'] = Model::table('categories')->where('id', $p['category_id'])->first();
            $p['author'] = Model::table('users')->select('id', 'username', 'nickname', 'avatar', 'is_certified', 'show_cert_badges')->where('id', $p['user_id'])->first();
        }
        return $posts;
    }

    /**
     * 按 id 批量加载用户（关注/粉丝列表用）
     */
    private function loadUsersByIds($ids)
    {
        if (empty($ids)) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return Model::query("SELECT id, username, nickname, avatar, bio, is_certified, show_cert_badges FROM users WHERE id IN ($placeholders) AND status = 1", $ids);
    }

    /**
     * 编辑资料页
     */
    public function edit()
    {
        $user = Auth::user();
        $this->view('user/edit', ['user' => $user]);
    }

    /**
     * 更新资料
     */
    public function update()
    {
        csrf_check();
        $nickname = trim(input('nickname'));
        $bio = trim(input('bio'));
        $phone = trim(input('phone'));
        $newEmail = trim(input('email'));

        // 昵称长度按「加权字节数」计：1 汉字=2 字节；1 字母/数字=1 字节（name_strlen），与注册/checkName 一致
        if ($nickname !== '' && (name_strlen($nickname) < 3 || name_strlen($nickname) > 15)) {
            $this->error('昵称长度需为 3-15 个字节');
        }
        if ($bio && mb_strlen($bio) > 500) $this->error('简介不能超过500字');

        // 昵称唯一性 + 敏感词校验（仅当用户提交昵称时）
        if ($nickname !== '') {
            if (!is_valid_name_chars($nickname)) $this->error('昵称只能包含中文、字母和数字');
            if (is_pure_number($nickname)) $this->error('昵称不可为纯数字');
            if (has_sensitive($nickname, 'all', 'exact')) $this->error('昵称含敏感词/限制词');
            if (nickname_taken($nickname, Auth::id())) $this->error('该昵称已被其他用户使用');
        }

        $data = ['updated_at' => date('Y-m-d H:i:s')];
        if ($nickname !== '') $data['nickname'] = filter_sensitive($nickname);
        if ($bio !== '') $data['bio'] = filter_sensitive($bio);
        if ($phone !== '') $data['phone'] = $phone;

        // 邮箱修改/验证：需要验证码
        $user = Auth::user();
        $emailVerifyOn = EmailController::getSetting('email_verify_enabled', '0') == '1';
        if ($newEmail) {
            if ($newEmail !== $user['email']) {
                // 修改绑定邮箱：原邮箱 + 新邮箱 两个验证码均通过
                if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) $this->error('新邮箱格式不正确');
                $exists = Model::table('users')->where('email', $newEmail)->where('id', '!=', Auth::id())->first();
                if ($exists) $this->error('该邮箱已被其他账号使用');

                if ($emailVerifyOn) {
                    $oldCode = trim(input('old_email_code'));
                    $newCode = trim(input('new_email_code'));
                    if (!$oldCode) $this->error('请输入原邮箱验证码');
                    if (!$newCode) $this->error('请输入新邮箱验证码');
                    if (!EmailController::verifyCode($user['email'], $oldCode, 'change_email_old')) {
                        $this->error('原邮箱验证码错误或已过期');
                    }
                    if (!EmailController::verifyCode($newEmail, $newCode, 'change_email_new')) {
                        $this->error('新邮箱验证码错误或已过期');
                    }
                }
                $data['email'] = $newEmail;
                $data['email_verified'] = 1;
            } elseif (empty($user['email_verified']) && $emailVerifyOn) {
                // 邮箱未变但未验证：用验证码完成验证
                $emailCode = trim(input('email_code'));
                if (!$emailCode) $this->error('请输入邮箱验证码');
                if (!EmailController::verifyCode($newEmail, $emailCode, 'change_email')) {
                    $this->error('邮箱验证码错误或已过期');
                }
                $data['email_verified'] = 1;
            }
        }

        Model::table('users')->where('id', Auth::id())->update($data);

        // 同步"前端要展示的认证图标"（最多 3 个 group_id，空数组 = 全部按已认证组展示）
        // 注意：要让"完全不勾选" = 空数组 [] 真的写进 DB，不能在缺失/为 null 时被兜底成"全部"
        $showCert = input('show_cert_badges', null);
        if ($showCert === null) {
            // 字段根本未提交（理论上不会发生，前端已经强制带上），保持原值不动
            $showCert = null;
        } else {
            if (!is_array($showCert)) $showCert = [];
            $showCert = array_values(array_unique(array_filter(array_map('intval', $showCert), function($v){ return $v > 0; })));
            if (count($showCert) > 3) {
                $this->error('最多勾选 3 个认证项目');
            }
        }
        if ($showCert !== null) {
            $encoded = json_encode($showCert, JSON_UNESCAPED_UNICODE);
            $affected = Model::table('users')->where('id', Auth::id())->update(['show_cert_badges' => $encoded]);
            // 一次性回读，确认真的写到了 DB（避免 update 模型静默吞 SQL）
            $verify = Model::table('users')->where('id', Auth::id())->first();
            $stored = isset($verify['show_cert_badges']) ? (string)$verify['show_cert_badges'] : '';
            error_log('[CertBadges] uid=' . Auth::id() . ' sent=' . json_encode($showCert) . ' encoded=' . $encoded . ' affected=' . $affected . ' stored=' . $stored);
        }

        // 个人简介 @提及通知：仅当简介含 @ 时推送，链接指向本人主页
        // 同样改走 display name 兜底，没昵称的也能正确显示用户名
        if ($bio !== '') {
            $meName = user_display_name(Auth::user());
            foreach (extract_mention_user_ids($bio, Auth::id()) as $muid) {
                notify_user($muid, 'mention', $meName . ' 在个人简介中提到了你', url('user/profile', ['id' => Auth::id()]));
            }
        }

        $this->success(null, '资料已更新');
    }

    /**
     * 更新头像
     */
    public function updateAvatar()
    {
        csrf_check();
        if (empty($_FILES['avatar'])) $this->error('请选择图片');
        $file = $_FILES['avatar'];
        if ($file['error'] !== UPLOAD_ERR_OK) $this->error('上传失败');
        if ($file['size'] > 5242880) $this->error('文件不能超过5MB');
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowed)) $this->error('仅支持 JPG/PNG/GIF/WEBP');

        $ext = 'jpg';
        $parts = pathinfo($file['name']);
        $ext = strtolower($parts['extension'] ?? 'jpg');
        $dir = PUBLIC_PATH . '/uploads/avatar/' . date('Ym');
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $filename = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $savePath = $dir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $savePath)) $this->error('保存失败');

        $relativePath = 'uploads/avatar/' . date('Ym') . '/' . $filename;
        Model::table('users')->where('id', Auth::id())->update(['avatar' => $relativePath, 'updated_at' => date('Y-m-d H:i:s')]);

        $this->success(['avatar' => $relativePath], '头像已更新');
    }

    /**
     * 修改密码
     */
    public function password()
    {
        csrf_check();
        $old = input('old_password');
        $new = input('new_password');
        $confirm = input('confirm_password');

        $user = Auth::user();
        if (!password_verify($old, $user['password_hash'])) $this->error('原密码错误');
        if (strlen($new) < 6) $this->error('新密码至少6位');
        if ($new !== $confirm) $this->error('两次密码不一致');

        Model::table('users')->where('id', Auth::id())->update([
            'password_hash' => password_hash($new, PASSWORD_DEFAULT),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        // 改密码后吊销所有「记住我」token，避免旧 token 仍可登录
        Auth::clearRememberToken(Auth::id());
        $this->success(null, '密码已修改');
    }

    /**
     * 关注 / 取消关注
     */
    public function follow($id = null)
    {
        csrf_check();
        $id = (int)($id ?? input('id'));
        if ($id == Auth::id()) $this->error('不能关注自己');
        $target = Model::table('users')->where('id', $id)->where('status', 1)->first();
        if (!$target) $this->error('用户不存在');

        $existing = Model::table('interactions')
            ->where('user_id', Auth::id())
            ->where('target_type', 'user')
            ->where('target_id', $id)
            ->where('action_type', 'follow')
            ->first();

        if ($existing) {
            Model::table('interactions')->where('id', $existing['id'])->delete();
            $followed = false;
            $msg = '已取消关注';
        } else {
            Model::table('interactions')->insert([
                'user_id' => Auth::id(),
                'target_type' => 'user',
                'target_id' => $id,
                'action_type' => 'follow',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $followed = true;
            $msg = '关注成功';
            // 通知（prefix 走 user_display_name，跟 _comment.php 显示规则一致，
            //   避免没填昵称时 content 出现「 关注了你」这种空前缀）
            Model::table('notifications')->insert([
                'user_id' => $id,
                'type' => 'follow',
                'content' => user_display_name(Auth::user()) . ' 关注了你',
                'link' => url('user/profile', ['id' => Auth::id()]),
                'is_read' => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $this->success(['followed' => $followed], $msg);
    }

    /**
     * 关注/粉丝列表
     */
    public function followers($id = null)
    {
        $id = (int)($id ?? input('id'));
        $type = input('type', 'followers'); // followers / following
        $user = Model::table('users')->select('id', 'username', 'nickname', 'avatar', 'bio')->where('id', $id)->first();
        if (!$user) { View::render('errors/404', [], 404); return; }

        if ($type === 'following') {
            $interactions = Model::table('interactions')->where('user_id', $id)->where('action_type', 'follow')->get();
            $userIds = array_column($interactions, 'target_id');
        } else {
            $interactions = Model::table('interactions')->where('target_type', 'user')->where('target_id', $id)->where('action_type', 'follow')->get();
            $userIds = array_column($interactions, 'user_id');
        }

        $users = [];
        if (!empty($userIds)) {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $users = Model::query("SELECT id, username, nickname, avatar, bio, is_certified, show_cert_badges FROM users WHERE id IN ($placeholders) AND status = 1", $userIds);
        }

        $this->view('user/followers', [
            'user' => $user,
            'users' => $users,
            'type' => $type,
        ]);
    }

    /**
     * @提及候选用户：返回候选列表（供前端输入 @ 时弹窗）
     * GET，未登录返回空列表。
     *
     * 三档策略（按 q 是否非空 / 是否纯数字 分流）：
     *   1) 纯数字 q → 按 UID 精确查（@uid 自动补全）
     *   2) q 非空（非纯数字）→ 全站用户名/昵称模糊搜索（避免"测试账号没人关注 → @ 弹窗空"）
     *   3) q 为空 → 优先返回当前用户关注列表；关注列表为空时降级到"最近活跃用户"，
     *                让冷启动/新用户首次输入 @ 也能见到候选。
     */
    public function mentionCandidates()
    {
        $uid = Auth::id();
        $q = trim((string)input('q', ''));
        $list = [];

        if (!$uid) {
            $this->success(['list' => []]);
            return;
        }

        // 1) 纯数字 q → 按 UID 精确查
        if ($q !== '' && ctype_digit($q) && (int)$q > 0) {
            $u = Model::table('users')->where('id', (int)$q)->where('status', 1)->first();
            if ($u) $list[] = $this->mentionUserItem($u);
            $this->success(['list' => $list]);
            return;
        }

        // 2) q 非空（非纯数字）→ 全站模糊搜索
        if ($q !== '') {
            // 转义 LIKE 通配符，避免 % _ 被用户输入触发
            $safe = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
            $like = '%' . $safe . '%';
            $rows = Model::query(
                "SELECT id, username, nickname, avatar FROM users
                 WHERE status = 1 AND (username LIKE ? OR nickname LIKE ?)
                 ORDER BY (CASE WHEN username = ? THEN 0 WHEN username LIKE ? THEN 1 ELSE 2 END), id ASC
                 LIMIT 50",
                [$like, $like, $q, $q . '%']
            );
            foreach ($rows as $u) $list[] = $this->mentionUserItem($u);
            $this->success(['list' => $list]);
            return;
        }

        // 3a) q 为空 + 有关注列表 → 返回关注列表
        $interactions = Model::table('interactions')
            ->where('user_id', $uid)
            ->where('action_type', 'follow')
            ->get();
        $ids = array_column($interactions, 'target_id');
        if (!empty($ids)) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $rows = Model::query(
                "SELECT id, username, nickname, avatar FROM users WHERE id IN ($ph) AND status = 1",
                $ids
            );
            foreach ($rows as $u) $list[] = $this->mentionUserItem($u);
        } else {
            // 3b) q 为空 + 无关注列表 → 降级到"最近活跃用户"（id DESC 简单代理活跃度）
            $rows = Model::table('users')
                ->where('status', 1)
                ->where('id', '!=', $uid)
                ->orderBy('id', 'DESC')
                ->limit(50)
                ->get();
            foreach ($rows as $u) $list[] = $this->mentionUserItem($u);
        }
        $this->success(['list' => $list]);
    }

    /**
     * mention 候选用户统一格式化（@提及插入文本始终 username；弹窗里仍展示 nickname 便于识别）
     *
     * 头像字段必须经 upload_url() 处理——DB 里存的是相对路径 `uploads/avatars/xxx.jpg`，
     * 真实 URL 是 `/public/uploads/avatars/xxx.jpg`；前端会拿这个值塞进 `<img src="...">`，
     * 缺了 `/public/` 这一段就会 404（onerror 兜底会触发字母占位符）。
     */
    private function mentionUserItem($u)
    {
        return [
            'id' => (int)$u['id'],
            'username' => $u['username'],
            'nickname' => $u['nickname'] !== null && $u['nickname'] !== '' ? $u['nickname'] : $u['username'],
            'avatar' => upload_url($u['avatar']),
        ];
    }

    /**
     * 我的帖子
     */
    public function myPosts()
    {
        $page = (int)input('page', 1);
        $result = Model::table('posts')->where('user_id', Auth::id())->where('status', 1)->orderBy('created_at', 'DESC')->paginate($page, 15);
        foreach ($result['data'] as &$p) {
            $p['category'] = Model::table('categories')->where('id', $p['category_id'])->first();
        }
        $this->view('user/my_posts', ['posts' => $result['data'], 'pagination' => $result, 'page' => $page]);
    }

    /**
     * 我的收藏
     */
    public function myCollects()
    {
        $page = (int)input('page', 1);
        $interactions = Model::table('interactions')
            ->where('user_id', Auth::id())
            ->where('action_type', 'collect')
            ->orderBy('created_at', 'DESC')
            ->paginate($page, 15);
        $posts = [];
        foreach ($interactions['data'] as $it) {
            $post = Model::table('posts')->where('id', $it['target_id'])->where('status', 1)->first();
            if ($post) {
                $post['author'] = Model::table('users')->select('id', 'nickname', 'username', 'avatar', 'is_certified')->where('id', $post['user_id'])->first();
                $posts[] = $post;
            }
        }
        $this->view('user/my_collects', ['posts' => $posts, 'pagination' => $interactions, 'page' => $page]);
    }

    /**
     * 邀请注册管理页（生成邀请码 / 查看使用状态 / 已邀请用户）
     * 权限：register_mode === 'invite' 且当前用户角色在 invite_allowed_roles 中，否则 403。
     */
    public function invite()
    {
        $user = Auth::user();
        if (!can_invite($user)) {
            http_response_code(403);
            View::render('errors/403', ['message' => '您没有邀请权限，或站点未开启邀请注册'], 403);
            exit;
        }

        $invites = Model::table('invites')
            ->where('inviter_id', $user['id'])
            ->orderBy('created_at', 'DESC')
            ->get();

        $invitedUsers = Model::table('users')
            ->where('invited_by', $user['id'])
            ->orderBy('created_at', 'DESC')
            ->get();

        $this->view('user/invite', [
            'invites'      => $invites,
            'invitedUsers' => $invitedUsers,
        ]);
    }

    /**
     * 生成邀请码（AJAX）
     */
    public function inviteGenerate()
    {
        csrf_check();
        $user = Auth::user();
        if (!can_invite($user)) {
            $this->error('您没有邀请权限', 1, 403);
        }

        // max_uses 取值规则：站长可自定义（1-999 人），其他用户一律 1（一码一用户）。
        // clamp 到 1-999：避免前端被绕过传 0/负数/超大值。
        if (is_webmaster()) {
            $maxUses = (int)input('max_uses', 1);
            if ($maxUses < 1) $maxUses = 1;
            if ($maxUses > 999) $maxUses = 999;
        } else {
            $maxUses = 1;
        }
        $expireDays = (int)input('expire_days', 0);
        $expiresAt = null;
        if ($expireDays > 0) {
            $expiresAt = date('Y-m-d H:i:s', time() + $expireDays * 86400);
        }

        $code = $this->generateInviteCode();
        $now = date('Y-m-d H:i:s');
        Model::table('invites')->insert([
            'code'         => $code,
            'inviter_id'   => $user['id'],
            'inviter_role' => $user['role'],
            'max_uses'     => $maxUses,
            'used_count'   => 0,
            'status'       => 1,
            'expires_at'   => $expiresAt,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);

        $this->success([
            'code' => $code,
            'link' => url('auth/register', ['code' => $code], true),
        ], '邀请码已生成');
    }

    /**
     * 停用邀请码（AJAX，仅本人可操作）
     */
    public function inviteRevoke()
    {
        csrf_check();
        $user = Auth::user();
        if (!can_invite($user)) {
            $this->error('您没有邀请权限', 1, 403);
        }

        $id = (int)input('id', 0);
        $invite = Model::table('invites')->where('id', $id)->first();
        if (!$invite || (int)$invite['inviter_id'] !== (int)$user['id']) {
            $this->error('邀请码不存在或无权操作');
        }

        Model::table('invites')->where('id', $id)->update([
            'status'     => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->success(null, '邀请码已停用');
    }

    /**
     * 生成唯一邀请码（10 位大写字母数字，碰撞则重试）
     */
    protected function generateInviteCode()
    {
        for ($i = 0; $i < 5; $i++) {
            $candidate = strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
            $exists = Model::table('invites')->where('code', $candidate)->first();
            if (!$exists) return $candidate;
        }
        return strtoupper(substr(md5(uniqid('', true)), 0, 10));
    }
}
