<?php
/**
 * 帖子控制器
 */

// ===== 评论区分页常量（内置固定值，不走后台配置）=====
// 主评论（楼层）每页条数：楼中楼不占额度
if (!defined('COMMENT_PER_PAGE'))    define('COMMENT_PER_PAGE', 15);
// 楼中楼默认展示条数（超出的折叠在「展开更多」后面）
if (!defined('REPLY_INIT_VISIBLE'))  define('REPLY_INIT_VISIBLE', 2);
// 楼中楼展开后每页条数（超出走楼中楼自己的翻页器）
if (!defined('REPLY_PER_PAGE'))      define('REPLY_PER_PAGE', 5);

class PostController extends Controller
{
    protected $middleware = [
        ['middleware' => 'auth', 'except' => ['show']],
        ['middleware' => 'banned', 'only' => ['create', 'store', 'edit', 'update', 'delete', 'comment', 'like', 'collect', 'closePost', 'openPost', 'pin', 'unpin', 'essence', 'unessence', 'move', 'deleteComment', 'bid']],
        ['middleware' => 'csrf', 'only' => ['store', 'update', 'delete', 'comment', 'deleteComment', 'pin', 'unpin', 'essence', 'unessence', 'move', 'like', 'collect', 'closePost', 'openPost', 'bid',
            'payBuy', 'eventSignup', 'bountyAccept', 'bountyEndEarly', 'pollVote', 'debateJoin', 'interviewAddQa', 'interviewAnswer', 'interviewEnd']],
    ];

    /**
     * 发布页
     */
    public function create()
    {
        $cat = (int)input('cat', 0);
        $categories = Model::table('categories')->where('status', 1)->orderBy('sort_order', 'ASC')->get();
        // 仅向当前用户可发表的板块开放发帖入口
        $allowedCats = array_filter($categories, function($c) {
            return can_publish_category($c);
        });
        // 预先计算每个板块是否允许发布各类特殊主题（板块 + 角色组双重打勾），注入前端做联动
        $themeKeys = ['auction', 'pay', 'event', 'bounty', 'poll', 'debate', 'interview', 'lottery'];
        $themeNames = [
            'auction' => '拍卖帖', 'pay' => '付费主题', 'event' => '活动主题',
            'bounty' => '悬赏主题', 'poll' => '投票主题', 'debate' => '辩论主题', 'interview' => '采访主题',
            'lottery' => '抽奖帖',
        ];
        $auctionCatMap = [];
        $specialAllowMap = [];
        foreach ($categories as $c) {
            $auctionCatMap[$c['id']] = can_publish_auction($c) ? 1 : 0;
            foreach ($themeKeys as $tk) {
                $specialAllowMap[$c['id']][$tk] = can_publish_special($tk, $c) ? 1 : 0;
            }
        }
        $this->view('post/create', [
            'categories'     => $allowedCats,
            'cat'            => $cat,
            'auctionCatMap'  => $auctionCatMap,
            'themeKeys'      => $themeKeys,
            'themeNames'     => $themeNames,
            'specialAllowMap'=> $specialAllowMap,
        ]);
    }

    /**
     * 保存新帖
     */
    public function store()
    {
        abortIfBanned();
        if (!can('post.create')) $this->error('无权限：未授予「发布帖子」');
        // 滑块验证码校验（后台「系统管理 → 验证码」开启发帖场景时生效）
        list($capOk, $capMsg) = captcha_guard('post');
        if (!$capOk) { $this->error($capMsg); }
        $title = trim(input('title'));
        $content = input('content');
        $categoryId = (int)input('category_id');
        // 图片已下线：不再读前端 data.images，固定写 null。
        $attachmentsInput = input('attachments', '');
        $attachments = $this->parseAttachments($attachmentsInput);
        // 2026-09-06 卡片式封面图：前端经 UploadController::image(type=post) 上传后回传相对路径。
        //   只接受本站 uploads/ 开头的相对路径，防外链/路径注入；列未升级时（SHOW COLUMNS 探测失败）静默忽略，不阻塞发帖。
        $coverImage = trim((string)input('cover_image', ''));
        if ($coverImage !== '' && strpos($coverImage, 'uploads/') !== 0) $coverImage = '';
        $hasCoverCol = false;
        if ($coverImage !== '' || setting('post_layout', 'default') === 'card') {
            try { $hasCoverCol = !empty(Model::query("SHOW COLUMNS FROM posts LIKE 'cover_image'")); } catch (Throwable $e) { $hasCoverCol = false; }
        }
        if (!$hasCoverCol) $coverImage = '';
        // 卡片式版式必须设置封面图才能发布。
        //   🚨 列不存在时不再静默降级（会把封面悄悄丢掉、必填校验也失效，用户以为传成功了）——
        //   直接报明确错误，引导站长跑 install/upgrade.php（升级 68：posts.cover_image 列）。
        if (setting('post_layout', 'default') === 'card') {
            if (!$hasCoverCol) {
                $this->error('封面字段未升级（posts.cover_image 列不存在），请站长先运行 install/upgrade.php 完成升级后再发布');
            }
            if ($coverImage === '') {
                $this->error('请先上传封面图再发布（卡片式版式必须设置封面）');
            }
        }

        if (!$title || mb_strlen($title) < 2) $this->error('标题至少 2 个字');
        if (mb_strlen($title) > 200) $this->error('标题不能超过 200 字');
        if (!$content) $this->error('请填写帖子内容');
        if (!$categoryId) $this->error('请选择板块');

        // 先把板块查出来——后面的所有板块级校验（认证、发表角色、拍卖主题权限）
        // 都依赖 $category，避免历史 bug：未定义 $category 就调用 can_publish_auction($category)。
        $category = Model::table('categories')->where('id', $categoryId)->where('status', 1)->first();
        if (!$category) $this->error('板块不存在或已关闭');

        // 认证专区权限
        if ($category['is_certification_required'] == 1 && !is_certified() && !is_admin()) {
            $this->error('该板块仅已认证用户可发帖');
        }

        // 板块发表角色权限
        if (!can_publish_category($category)) {
            $this->error('您当前角色无权在该板块发表主题');
        }

        // 统一特殊主题类型（19 轮后 topic_type 为真相源；is_auction 仅作历史字段兼容保留）
        $topicType = input('topic_type', 'normal');
        $allowedTypes = ['normal', 'auction', 'pay', 'event', 'bounty', 'poll', 'debate', 'interview', 'lottery'];
        if (!in_array($topicType, $allowedTypes, true)) $topicType = 'normal';

        // 付费主题：附件价格 > 0 但没附件 → 拒绝
        // 防止「设置附件价格但忘了上传附件」导致没人付得了、或者付了之后附件列表为空
        if ($topicType === 'pay') {
            $apPreview = max(0, (int)input('pay_attachment_price', 0));
            if ($apPreview > 0 && empty($attachments)) {
                $this->error('已设置附件价格但未上传附件，请先上传附件或把附件价格改为 0');
            }
        }

        // 拍卖字段（只走 topic_type='auction' 分支，不再读 is_auction hidden 输入）
        $auctionData = [];
        if ($topicType === 'auction') {
            // 特殊主题权限：板块 + 角色组双重打勾才允许发布拍卖帖。
            if (!can_publish_auction($category)) {
                $this->error('当前板块或您的角色组未被授权发布拍卖帖，请在「系统管理 → 特殊主题」中开启');
            }
            $startPrice = input('start_price');
            $stepPrice = input('step_price');
            $endTime = input('end_time');
            $startPrice = $startPrice !== '' && $startPrice !== null ? (float)$startPrice : 0;
            $stepPrice = $stepPrice !== '' && $stepPrice !== null ? max(0.01, (float)$stepPrice) : 10;
            if ($startPrice < 0) $this->error('起拍价不能为负');
            if (!$endTime) $this->error('请设置拍卖结拍时间');
            $endTs = strtotime($endTime);
            if ($endTs === false || $endTs < time() + 900) $this->error('结拍时间必须晚于当前时间至少 15 分钟');
            $auctionData = [
                'is_auction' => 1,
                'start_price' => $startPrice,
                'step_price' => $stepPrice,
                'current_price' => $startPrice,
                'bid_count' => 0,
                'end_time' => date('Y-m-d H:i:s', $endTs),
            ];
        } elseif ($topicType !== 'normal') {
            // 其余特殊类型走通用权限矩阵
            if (!can_publish_special($topicType, $category)) {
                $this->error('当前板块或您的角色组未被授权发布「' . $topicType . '」主题，请在「系统管理 → 特殊主题」中开启');
            }
            // 悬赏主题发布前校验：必须设置「每人积分数」与「悬赏人数」，且发布者余额足够质押
            if ($topicType === 'bounty') {
                $per = (int)input('bounty_per_person', 0);
                $people = (int)input('bounty_people', 0);
                if ($per <= 0) $this->error('请填写悬赏每人积分数（大于 0）');
                if ($people <= 0) $this->error('请填写悬赏人数（大于 0）');
                $total = $per * $people;
                $balance = PointService::balanceOf(Auth::id(), 'token');
                if ($balance < $total) $this->error('论坛积分余额不足，质押需 ' . $total . '（当前 ' . (int)$balance . '）');
            }

            // 特殊主题时间类设定统一约束：不得早于「当前时间 + 15 分钟」（至少 15 分钟后）。
            // 前置到发帖入库之前校验，避免 storeSpecial() 的 try/catch 吞掉异常导致"主帖已建、特殊数据缺失"。
            $minTs = time() + 900;
            if ($topicType === 'event') {
                $et = input('event_time', '');
                if ($et !== '') {
                    $ets = strtotime($et);
                    if ($ets === false || $ets < $minTs) $this->error('活动时间必须晚于当前时间至少 15 分钟');
                }
            } elseif ($topicType === 'poll') {
                $dl = input('poll_deadline', '');
                if ($dl !== '') {
                    $dls = strtotime($dl);
                    if ($dls === false || $dls < $minTs) $this->error('投票截止时间必须晚于当前时间至少 15 分钟');
                }
            } elseif ($topicType === 'lottery') {
                $da = input('lottery_draw_at', '');
                if ($da === '') $this->error('请设置开奖时间');
                $das = strtotime($da);
                if ($das === false || $das < $minTs) $this->error('开奖时间必须晚于当前时间至少 15 分钟');
                // 抽奖主题：奖品类型/价值/中奖人数前置校验，积分类奖品同步校验余额并质押
                $prizeType = (string)input('lottery_prize_type', 'physical');
                if (!in_array($prizeType, ['physical', 'virtual', 'point'], true)) $this->error('奖品类型必须为「实物/虚拟/积分」');
                $prizeValueRaw = trim((string)input('lottery_prize_value', ''));
                $pointMode = (string)input('lottery_point_mode', 'custom');
                $pointInput = (int)input('lottery_point_input', 0);   // 前后段控件：自定义=每份积分数，随机=质押总额
                $winnerCount = (int)input('lottery_winner_count', 0);
                if ($prizeType === 'point') {
                    if ($winnerCount <= 0) $this->error('中奖人数必须大于 0');
                    if ($pointInput <= 0) $this->error('请填写「每份积分数」或「质押总额」（大于 0）');
                    // 「每份积分数」= 前后段控件
                    // - 自定义：pointInput = 每份积分数，质押 = pointInput × winnerCount
                    // - 随机  ：pointInput = 质押总额（固定），开奖时把总数随机分给实际中奖者，每人不同。
                    //          强制约束：质押总额 ≥ 中奖人数（即「平均 ≥ 1」），否则拒绝发布；
                    //          边界 total == winnerCount → 每人恰好 1，按平均分开奖。
                    $balance = PointService::balanceOf(Auth::id(), 'token');
                    if ($pointMode === 'random') {
                        if ($pointInput > $balance) {
                            $this->error('「质押总额」不能超过论坛积分余额（当前 ' . (int)$balance . '）');
                        }
                        if ($pointInput < $winnerCount) {
                            $this->error('随机模式要求「质押总额 ≥ 中奖人数」（当前 ' . $pointInput . ' / 中奖 ' . $winnerCount . '），平均每人至少 1 积分');
                        }
                    } else {
                        if ($pointInput > 2000000000) $this->error('「每份积分数」过大');
                        $stake = $pointInput * $winnerCount;
                        if ($stake > $balance) {
                            $this->error('论坛积分余额不足，质押需 ' . $stake . '（当前 ' . (int)$balance . '）');
                        }
                    }
                } else {
                    // 实物 / 虚拟：奖品价值是必填文本（用于展示，不参与质押）
                    if ($prizeValueRaw === '') $this->error('请填写奖品价值（如 ¥99 / 价值 100 元）');
                }
                // 4 个下拉规则：每项 = no/optional/must，全部 no 视为"不设限制"，但积分品必填 must（避免无人满足）
                $ruleKeys = ['follow' => 'lottery_rule_follow', 'reply' => 'lottery_rule_reply', 'like' => 'lottery_rule_like', 'favorite' => 'lottery_rule_favorite'];
                $rules = [];
                foreach ($ruleKeys as $rk => $field) {
                    $rv = (string)input($field, 'no');
                    if (!in_array($rv, ['no', 'optional', 'must'], true)) $rv = 'no';
                    $rules[$rk] = $rv;
                }
                if ($prizeType !== 'point') {
                    // 积分奖品建议至少 1 条 must，避免大量无效参与；非强制，可放宽
                    // （这里只校验至少 1 条 must，给积分类一个有意义的门槛）
                }
            } elseif ($topicType === 'debate') {
                // 辩论主题截止时间（必填）。用户首次指定后即作为该辩论的「站队发言」截止。
                $dd = input('debate_deadline', '');
                if ($dd === '') $this->error('请设置辩论截止时间');
                $dds = strtotime($dd);
                if ($dds === false || $dds < $minTs) $this->error('辩论截止时间必须晚于当前时间至少 15 分钟');
            }
        }

        // 高危敏感词硬拦截：命中即拒绝发布（普通词在 filter_sensitive 中打码后照常发布）
        if (has_high_risk_sensitive($title) || has_high_risk_sensitive($content)) {
            $this->error('内容包含高危敏感词，无法发布');
        }

        // 敏感词过滤
        $title = filter_sensitive($title);
        $content = filter_sensitive($content);

        $now = date('Y-m-d H:i:s');
        $postId = Model::table('posts')->insert([
            'user_id' => Auth::id(),
            'category_id' => $categoryId,
            'title' => $title,
            'content' => $content,
            'images' => null,
            'cover_image' => $coverImage !== '' ? $coverImage : null,
            'attachments' => !empty($attachments) ? json_encode($attachments) : null,
            'is_pinned' => 0,
            'pin_scope' => 0,
            'is_essence' => 0,
            'view_count' => 0,
            'like_count' => 0,
            'comment_count' => 0,
            'collect_count' => 0,
            'last_reply_at' => $now,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'topic_type' => $topicType,
        ] + $auctionData);

        // 生成 url_slug（需要 post id 防碰撞；标题改时不更新以保外链稳定）
        try {
            $slug = generate_unique_slug($title, $postId);
            Model::table('posts')->where('id', $postId)->update(['url_slug' => $slug]);
        } catch (Throwable $e) {
            // slug 生成失败不影响主流程
        }

        // 更新板块帖子数
        Model::execute('UPDATE categories SET post_count = post_count + 1 WHERE id = ?', [$categoryId]);

        // 写入特殊主题扩展表（付费/活动/悬赏/投票/辩论/采访）。
        //   注意（2026-09-02 修复）：采访/悬赏/抽奖等主题的扩展数据是主题不可分割的组成部分，
        //   一旦写入失败绝不能静默吞掉——否则主帖已发布、扩展表无数据，前台会出现
        //   「发布成功但看不到主题模块」的假象，且极难排查（典型就是升级脚本没跑导致列缺失）。
        //   因此对 critical 类型：写入失败时回滚主帖并返回明确错误，引导先跑 install/upgrade.php。
        if ($topicType !== 'normal' && $topicType !== 'auction') {
            $criticalTypes = ['interview', 'bounty', 'lottery', 'pay', 'event', 'poll', 'debate'];
            try {
                $this->storeSpecial($postId, $topicType, $category, $title);
            } catch (Throwable $e) {
                error_log('storeSpecial failed post=' . $postId . ' type=' . $topicType . ' : ' . $e->getMessage());
                if (in_array($topicType, $criticalTypes, true)) {
                    try { Model::table('posts')->where('id', $postId)->delete(); } catch (Throwable $e2) { error_log('rollback post failed: ' . $e2->getMessage()); }
                    $this->error('主题扩展数据写入失败（' . $topicType . '）：' . $e->getMessage() . '。请确认已执行 install/upgrade.php 完成数据库升级。');
                }
            }
        }

        // 积分：发布主题帖获取 Token + Byte（幂等，source_id = 帖子 id；规则停用/缺失自动跳过）
        $earnedPoints = PointService::earnAction(Auth::id(), 'post_create', $postId, '发布主题帖');

        // @提及通知：解析正文中被 @ 的用户并推送
        // 同样改走 display name，避免没填昵称的发送者出现「 在帖子中提到了你」这种空前缀
        $meName = user_display_name(Auth::user());
        foreach (extract_mention_user_ids($content, Auth::id()) as $muid) {
            notify_user($muid, 'mention', $meName . ' 在帖子《' . mb_substr($title, 0, 20) . '》中提到了你', url('post/show', ['id' => $postId]));
        }

        $this->success([
            'id' => $postId,
            'redirect' => url('post/show', ['id' => $postId]),
            'points' => $earnedPoints,
        ], '发布成功');
    }

    /**
     * 帖子详情
     */
    public function show($id = null)
    {
        $id = (int)($id ?? input('id'));
        $post = Model::table('posts')->where('id', $id)->where('status', 1)->first();
        if (!$post) {
            // 被删除的内容（status=0）→ 403 而非 404，避免对外暴露"内容存在但被删"的存在性
            $deletedPost = Model::table('posts')->where('id', $id)->where('status', 0)->first();
            if ($deletedPost) {
                http_response_code(403);
                View::render('errors/403', ['message' => '该内容已被删除，无法访问'], 403);
                return;
            }
            http_response_code(404);
            View::render('errors/404', [], 404);
            return;
        }

        // 浏览量 +1
        Model::execute('UPDATE posts SET view_count = view_count + 1 WHERE id = ?', [$id]);
        $post['view_count'] += 1;

        // 作者信息
        $post['author'] = Model::table('users')->select('id', 'username', 'nickname', 'avatar', 'bio', 'is_certified', 'role', 'show_cert_badges', 'level', 'byte')->where('id', $post['user_id'])->first();
        $post['category'] = Model::table('categories')->where('id', $post['category_id'])->first();
        // 板块浏览角色权限校验（管理员/超管始终可浏览）
        if ($post['category'] && !can_browse_category($post['category'])) {
            http_response_code(403);
            View::render('errors/403', ['message' => '您当前角色无权浏览该板块内容'], 403);
            return;
        }
        $post['images_arr'] = normalize_post_images($post['images']);
        $post['attachments_arr'] = normalize_post_attachments($post['attachments']);

        // 当前用户的点赞/收藏状态
        $liked = false;
        $collected = false;
        if (Auth::check()) {
            $liked = Model::table('interactions')
                ->where('user_id', Auth::id())
                ->where('target_type', 'post')
                ->where('target_id', $id)
                ->where('action_type', 'like')
                ->first() ? true : false;
            $collected = Model::table('interactions')
                ->where('user_id', Auth::id())
                ->where('target_type', 'post')
                ->where('target_id', $id)
                ->where('action_type', 'collect')
                ->first() ? true : false;
        }

        // —— 评论列表（分页）——
        // ⚠️ 旧实现的严重 bug：`LIMIT 20` 是对「全部评论（顶层 + 楼中楼混在一起）」取前 20 条，
        //    所以①总评论数超过 20 后面的直接不显示；②第 21 条起若是楼中楼，它的顶层父评论
        //    不在本页结果集里，buildCommentTree() 找不到 root 就把它整条丢掉 —— 楼中楼凭空消失。
        // 新实现：**只对顶层评论分页**（每页 15 条 = 15 个楼层），
        //    再把这些顶层评论名下的全部子孙回复（任意深度）单独查出来挂上去，楼中楼不占分页额度。
        $perPage = COMMENT_PER_PAGE;
        $totalTopComments = (int)Model::scalar(
            'SELECT COUNT(*) FROM comments WHERE post_id = ? AND status = 1 AND (parent_id IS NULL OR parent_id = 0)',
            [$id]
        );
        $totalPages = max(1, (int)ceil($totalTopComments / $perPage));
        $page = (int)input('page', 1);
        if ($page < 1) $page = 1;
        if ($page > $totalPages) $page = $totalPages;

        // 顶层评论按 id 升序（= 发帖时间顺序，且楼层号可用 COUNT(id <= ?) 精确反算）
        // 悬赏主题叠加：已采纳回答（accept_rank 非 NULL）置顶，按采纳次序排；其余按 id ASC
        // 「accept_rank IS NULL, accept_rank ASC」先让 NULL 排到最后（MySQL 老 hack）
        // 兼容老库：accept_rank/is_bounty_answer 字段未升级时回退到原 ORDER BY
        try {
            $topComments = Model::query(
                'SELECT c.*, u.username, u.nickname, u.avatar, u.is_certified, u.role, u.show_cert_badges, u.status
                 FROM comments c LEFT JOIN users u ON c.user_id = u.id
                 WHERE c.post_id = ? AND c.status = 1 AND (c.parent_id IS NULL OR c.parent_id = 0)
                 ORDER BY c.accept_rank IS NULL, c.accept_rank ASC, c.id ASC LIMIT ? OFFSET ?',
                [$id, $perPage, ($page - 1) * $perPage]
            );
        } catch (\Throwable $e) {
            // 老库尚未升级 accept_rank 列 → 回退到原 ORDER BY
            $topComments = Model::query(
                'SELECT c.*, u.username, u.nickname, u.avatar, u.is_certified, u.role, u.show_cert_badges, u.status
                 FROM comments c LEFT JOIN users u ON c.user_id = u.id
                 WHERE c.post_id = ? AND c.status = 1 AND (c.parent_id IS NULL OR c.parent_id = 0)
                 ORDER BY c.id ASC LIMIT ? OFFSET ?',
                [$id, $perPage, ($page - 1) * $perPage]
            );
        }
        // 挂载楼中楼（2 级扁平化：任意深度的回复都归到其顶层祖先的 children）
        $commentTree = $this->attachReplies($id, $topComments);

        // 楼层号：#1 固定给主题帖；其余楼层 = 该顶层评论在「全部顶层评论（含已删除）按 id 升序」中的序号 + 1。
        // 关键点：计数包含已删除评论（status 任意），因此某楼层被删后其编号不会被下一楼层顶替，
        //   后续楼层号依旧往后推（如 #2 被删 → 下一条仍是 #3，#2 这个号永远空着）。
        $allTopIds = Model::query(
            'SELECT id FROM comments WHERE post_id = ? AND (parent_id IS NULL OR parent_id = 0) ORDER BY id ASC',
            [$id]
        );
        $floorRankMap = [];
        foreach ($allTopIds as $i => $row) {
            $floorRankMap[(int)$row['id']] = $i + 1; // 1-based 序号；+1 留给主题帖 #1
        }
        foreach ($commentTree as &$c) {
            $c['floor'] = ($floorRankMap[(int)$c['id']] ?? 0) + 1;
        }
        unset($c);

        // 悬赏主题：未公开前，非作者 / 非回答者的回答（顶层评论）及其楼中楼对访客隐藏
        // 规则升级（2026-08-31）：
        //   1) 已采纳的回答（accept_rank 非 NULL）——**永久可见**，即全员解锁（采纳即公开）
        //   2) 作者 / 管理员看全部（含所有未采纳回答 + 全部楼中楼）
        //   3) 当前用户 = 答主 —— 只看自己的回答（is_bounty_answer=1 且 user_id=myId）
        //   4) 路人 —— 全隐藏
        //   楼中楼挂载由 attachReplies 统一处理，过滤完 commentTree 即可，无需再动
        $special = $this->loadSpecial($post);
        if (!empty($special['type']) && $special['type'] === 'bounty' && empty($special['bounty']['public'])) {
            $authorId = (int)$post['user_id'];
            $myId = (int)Auth::id();
            $isAdmin = is_admin();
            $filtered = [];
            foreach ($commentTree as $c) {
                // (1) 已采纳的回答：永久可见
                $rank = isset($c['accept_rank']) && $c['accept_rank'] !== null && $c['accept_rank'] !== '' ? (int)$c['accept_rank'] : 0;
                if ($rank > 0) { $filtered[] = $c; continue; }
                // (2) 作者 / 管理员：看全部
                if ($myId === $authorId || $isAdmin) { $filtered[] = $c; continue; }
                // (3) 当前用户：只看自己写的悬赏回答（is_bounty_answer=1）
                $isBountyAnswer = isset($c['is_bounty_answer']) && (int)$c['is_bounty_answer'] === 1;
                if ($myId && $myId === (int)$c['user_id'] && $isBountyAnswer) { $filtered[] = $c; continue; }
                // (4) 其它：隐藏
            }
            $commentTree = $filtered;
            // 重算可见评论数（仅用于展示，避免暴露未公开回答数量）
            $totalTopComments = count($commentTree);
            $totalPages = max(1, (int)ceil($totalTopComments / $perPage));
        }

        // 辩论主题：站队发言不再支持楼中楼回复（2026-08-31 改造撤销），
        // 故不再把「回复站队发言」的评论挂到 statement 的 children。
        // 对站队发言的「引用」会作为顶层评论（带引用块）出现在评论区，刷新后正常持久化。

        // 抽奖主题：进入帖子页时自动处理（替代原"用户点参与才触发"的不可靠机制）
        //   1) 当前登录用户（非作者）自动登记参与资格（写入 topic_lottery_joins，开奖时再按规则严格筛选）
        //   2) 若已到开奖时间且未开奖 → 自动开奖（随机分完质押资产 / 无人符合全额退还）
        if (!empty($post['topic_type']) && $post['topic_type'] === 'lottery') {
            $this->autoProcessLottery($post);
        }

        $totalComments = Model::table('comments')->where('post_id', $id)->where('status', 1)->count();

        // 是否版主
        $isModerator = Auth::isModeratorOf($post['category_id']);
        // 当前用户是否可查看「回复可见」的图片/附件（作者/管理员/已回复者）
        $canViewHidden = can_view_hidden_media($post);

        // 拍卖出价排行：每位参与用户取「最后一条」出价，按金额由高到低排行（分页，每页 10 人）
        $bidRanking = [];
        $bidRankTotal = 0;
        $bidPage = 1;
        $bidPerPage = 10;
        if (!empty($post['is_auction'])) {
            $bidPage = (int)input('bid_page', 1);
            if ($bidPage < 1) $bidPage = 1;
            // 参与出价的总人数（按用户去重）
            $bidRankTotal = (int)Model::scalar(
                'SELECT COUNT(DISTINCT user_id) FROM bids WHERE post_id = ?',
                [$id]
            );
            // 每位用户最近一次出价，按金额降序、同额按出价时间降序排行
            $bidRanking = Model::query(
                'SELECT b.id, b.user_id, b.price, b.created_at, b.status,
                        u.nickname, u.avatar, u.is_certified, u.role, u.show_cert_badges
                 FROM bids b
                 INNER JOIN (
                     SELECT user_id, MAX(id) AS max_id FROM bids WHERE post_id = ? GROUP BY user_id
                 ) latest ON b.user_id = latest.user_id AND b.id = latest.max_id
                 LEFT JOIN users u ON b.user_id = u.id
                 ORDER BY b.price DESC, b.id DESC
                 LIMIT ? OFFSET ?',
                [$id, $bidPerPage, ($bidPage - 1) * $bidPerPage]
            );
        }

        // —— 打赏记录（含帖被打赏 + 评论被打赏），按时间倒序，供帖底展示
        // 当前 PointService::reward 只发 token；表结构未预留 currency 列，所以直接展示 token 即可
        $rewardList = Model::query(
            'SELECT r.id, r.post_id, r.comment_id, r.from_uid, r.to_uid,
                    r.token, r.created_at,
                    u.nickname, u.username, u.avatar, u.status
             FROM reward_log r
             LEFT JOIN users u ON r.from_uid = u.id
             WHERE r.post_id = ?
             ORDER BY r.id DESC
             LIMIT 100',
            [$id]
        );

        $this->view('post/show', [
            'post' => $post,
            'liked' => $liked,
            'collected' => $collected,
            'comments' => $commentTree,
            'totalComments' => $totalComments,
            'totalTopComments' => $totalTopComments,
            'totalPages' => $totalPages,
            'page' => $page,
            'perPage' => $perPage,
            'childPerPage' => REPLY_PER_PAGE,
            'childInitVisible' => REPLY_INIT_VISIBLE,
            'isModerator' => $isModerator,
            'isAuthor' => ((int)$post['user_id'] === (int)Auth::id()),
            'canSelfPin' => (is_logged_in() && Auth::can('post.self_pin')),
            // 积分弹窗用：当前余额 + 自助置顶费率（缺规则兜底 1000 Token/小时，与 PointService::selfPin 一致）
            'myToken' => is_logged_in() ? (int)(Auth::user()['token'] ?? 0) : 0,
            'selfPinRate' => (PointService::rule('self_pin') ? (int)PointService::rule('self_pin')['amount'] : 1000),
            'canViewHidden' => $canViewHidden,
            'bidRanking' => $bidRanking,
            'bidRankTotal' => $bidRankTotal,
            'bidPage' => $bidPage,
            'bidPerPage' => $bidPerPage,
            'rewardList' => $rewardList,
            'special' => $special,
            'tokenLabel' => \PointService::currencyLabel('token'),
            'rewardDefault' => \PointService::rewardConfig()['default_amount'] ?: 5,
        ]);
    }

    /**
     * 给「本页的顶层评论」挂上它们名下的全部楼中楼（2 级扁平化）
     *
     * 用户要求「楼中楼只显示 2 级梯队」——所以任意深度的回复都向上 walk 到顶层祖先，
     * 统一 flatten 进该顶层评论的 children；前端再按「默认 2 条 + 展开后每页 5 条」分页显示。
     *
     * 为什么不能沿用旧的 buildCommentTree($comments)：
     *   旧写法是在「一页混合评论」里就地建树，一旦子评论和它的顶层父评论被分页切开就整条丢失。
     *   现在改成先定好本页顶层评论，再单独按 post_id 捞子孙 —— 无论子孙落在第几条都不会漏。
     *
     * @param int   $postId
     * @param array $topComments 本页顶层评论（已带 user 字段，按 id ASC）
     * @return array
     */
    protected function attachReplies($postId, $topComments)
    {
        if (empty($topComments)) return [];

        $rootSet = [];
        foreach ($topComments as $c) $rootSet[(int)$c['id']] = true;

        // 全帖评论骨架（不筛 status）：用于沿 parent 链向上找顶层祖先。
        // 不筛 status 的原因：中间某层被删（status=0）时，它下面的回复仍应挂到活着的顶层评论上，
        // 否则「父评论被删 → 整串孙回复凭空消失」。
        $skeleton = Model::query('SELECT id, parent_id, status FROM comments WHERE post_id = ? ORDER BY id ASC', [$postId]);
        $parentOf = [];
        foreach ($skeleton as $s) {
            $pid = (int)($s['parent_id'] ?? 0);
            if ($pid) $parentOf[(int)$s['id']] = $pid;
        }

        // 找出「顶层祖先命中本页」的所有子孙评论 id
        $wanted = [];   // childId => rootId
        foreach ($skeleton as $s) {
            if ((int)$s['status'] !== 1) continue;          // 已删除的回复本身不展示
            $cid = (int)$s['id'];
            if (!isset($parentOf[$cid])) continue;          // 顶层评论，跳过
            $cur = $parentOf[$cid];
            $guard = 0;
            while (isset($parentOf[$cur]) && $guard < 50) { $cur = $parentOf[$cur]; $guard++; }
            if (isset($rootSet[$cur])) $wanted[$cid] = $cur;
        }

        // 一次性把这些子孙评论的完整行拉回来（含 user 字段）
        $grouped = [];
        if (!empty($wanted)) {
            $ids = array_keys($wanted);
            $ph  = implode(',', array_fill(0, count($ids), '?'));
            $rows = Model::query(
                'SELECT c.*, u.username, u.nickname, u.avatar, u.is_certified, u.role, u.show_cert_badges, u.status
                 FROM comments c LEFT JOIN users u ON c.user_id = u.id
                 WHERE c.id IN (' . $ph . ') ORDER BY c.id ASC',
                $ids
            );
            foreach ($rows as $r) {
                $rid = $wanted[(int)$r['id']] ?? 0;
                if ($rid) $grouped[$rid][] = $r;
            }
        }

        $out = [];
        foreach ($topComments as $c) {
            $c['children'] = $grouped[(int)$c['id']] ?? [];
            $out[] = $c;
        }
        return $out;
    }

    /**
     * 算某条顶层评论所在的主评论页码 + 楼层号
     * 楼层规则：#1 = 主题帖（楼主内容），第 1 条顶层评论 = #2，依次递增。
     *
     * @return array [page, floor]
     */
    protected function commentPageAndFloor($postId, $topCommentId)
    {
        $rank = (int)Model::scalar(
            'SELECT COUNT(*) FROM comments WHERE post_id = ? AND status = 1 AND (parent_id IS NULL OR parent_id = 0) AND id <= ?',
            [$postId, $topCommentId]
        );
        if ($rank < 1) $rank = 1;
        return [max(1, (int)ceil($rank / COMMENT_PER_PAGE)), $rank + 1];
    }

    /**
     * 编辑页
     */
    public function edit($id = null)
    {
        $id = (int)($id ?? input('id'));
        $post = Model::table('posts')->where('id', $id)->first();
        if (!$post) $this->error('帖子不存在');

        // 权限：本人 或 版主+
        if ($post['user_id'] != Auth::id() && !Auth::isModeratorOf($post['category_id']) && !is_admin()) {
            $this->error('无权编辑此帖子');
        }
        // 权限矩阵二次校验：
        //   自己 → 需要 post.edit_own
        //   版主/管理 → 需要 post.delete_section
        if ((int)$post['user_id'] === (int)Auth::id()) {
            if (!can('post.edit_own')) $this->error('无权限：未授予「编辑自己帖子」');
        } else {
            if (!can('post.delete_section')) $this->error('无权限：未授予「本版删除」');
        }

        // 注意：拍卖帖有人出价后，只锁定拍卖模块（取消拍卖/起拍价/加价/结拍时间），
        // 标题、正文、图片、附件、分类等其它内容仍可在模板中正常编辑。

        $categories = Model::table('categories')->where('status', 1)->orderBy('sort_order', 'ASC')->get();
        $post['images_arr'] = normalize_post_images($post['images']);
        $post['attachments_arr'] = normalize_post_attachments($post['attachments']);

        // 主题类型映射（用于编辑页只读展示「本帖为 X 主题」）
        $typeMap = [
            'normal' => '普通帖', 'auction' => '拍卖帖', 'pay' => '付费主题',
            'event' => '活动', 'bounty' => '悬赏', 'poll' => '投票',
            'debate' => '辩论', 'interview' => '采访', 'lottery' => '抽奖',
        ];
        $currentType = $post['topic_type'] ?? 'normal';
        // 老数据兜底：is_auction=1 但 topic_type 缺/为 normal → 显示为拍卖
        if (($currentType === 'normal' || $currentType === '' || $currentType === null) && !empty($post['is_auction'])) {
            $currentType = 'auction';
        }
        $currentTypeLabel = $typeMap[$currentType] ?? '普通';

        // 付费主题：把扩展表当前配置取出供编辑面板回填
        $payRow = null;
        if ($currentType === 'pay') {
            try {
                $payRow = Model::table('topic_pay')->where('post_id', $post['id'])->first();
            } catch (Throwable $e) { $payRow = null; }
        }

        // 投票主题：编辑页只读展示投票设置（投票一经发布不可修改）
        $pollRow = null;
        if ($currentType === 'poll') {
            try {
                $pollRow = Model::table('topic_poll')->where('post_id', $post['id'])->first();
                if ($pollRow) {
                    $pollRow['options'] = Model::query('SELECT id, text FROM topic_poll_options WHERE poll_id = ? ORDER BY sort ASC, id ASC', [$pollRow['id']]);
                }
            } catch (Throwable $e) { $pollRow = null; }
        }

        // 辩论主题：编辑页只读展示辩论设置（正反方观点 / 截止时间 一经发布不可修改）
        $debateRow = null;
        if ($currentType === 'debate') {
            try {
                $debateRow = Model::table('topic_debate')->where('post_id', $post['id'])->first();
            } catch (Throwable $e) { $debateRow = null; }
        }

        // 活动主题：编辑页只读展示（活动配置一经发布不可改，展示出来让用户知道当前设置）。
        // 注意 subject/mode 是后加的列，升级脚本没跑时不会出现在结果里，下面统一兜底。
        $eventRow = null;
        if ($currentType === 'event') {
            try {
                $eventRow = Model::table('topic_event')->where('post_id', $post['id'])->first();
                if ($eventRow) {
                    $eventRow['custom_fields'] = json_decode($eventRow['custom_fields'] ?? '[]', true) ?: [];
                    $eventRow['subject'] = (string)($eventRow['subject'] ?? '');
                    $eventRow['mode']    = (!empty($eventRow['mode']) && $eventRow['mode'] === 'online') ? 'online' : 'offline';
                    // 活动费用：升级脚本未执行前 fee 列不存在时 fallback 0；展示层用 number_format 输出
                    $eventRow['fee']     = isset($eventRow['fee']) && is_numeric($eventRow['fee']) ? round((float)$eventRow['fee'], 2) : 0.0;
                }
            } catch (Throwable $e) { $eventRow = null; }
        }

        // 抽奖主题：编辑页只读展示（奖品/中奖人数/规则/开奖时间 一经发布不可修改——
        //   抽奖行为已开始 → 任何配置改动都属作弊，与拍卖帖「有人出价后锁定」同理）。
        $lotteryRow = null;
        if ($currentType === 'lottery') {
            try {
                $lotteryRow = Model::table('topic_lottery')->where('post_id', $post['id'])->first();
                if ($lotteryRow) {
                    $lotteryRow['prize_type']  = (string)($lotteryRow['prize_type'] ?? 'physical');
                    $lotteryRow['prize_value'] = (string)($lotteryRow['prize_value'] ?? '');
                    $lotteryRow['point_unit']  = (int)($lotteryRow['point_unit'] ?? 0);
                    $lotteryRow['stake_points'] = (int)($lotteryRow['stake_points'] ?? 0);
                    $lotteryRow['stake_currency'] = (string)($lotteryRow['stake_currency'] ?? 'token');
                    $lotteryRow['cost_currency']  = (string)($lotteryRow['cost_currency'] ?? 'token');
                    $lotteryRow['rules']       = json_decode($lotteryRow['rules_json'] ?? '{}', true) ?: [];
                    $lotteryRow['joined_count'] = (int)($lotteryRow['joined_count'] ?? 0);
                    $lotteryRow['winner_count'] = (int)($lotteryRow['winner_count'] ?? 0);
                    $lotteryRow['status']       = (int)($lotteryRow['status'] ?? 0);
                }
            } catch (Throwable $e) { $lotteryRow = null; }
        }

        // 采访主题：编辑页只读展示（参与者 / 头衔 / 开放读者提问 一经发布不可修改）。
        //   复用 show 路径的 loadSpecial() 拿到 reporter_card / interviewee_card，模板里 disabled 渲染。
        $interviewRow = null;
        if ($currentType === 'interview') {
            try {
                $sp = $this->loadSpecial($post);
                $interviewRow = !empty($sp['interview']) ? $sp['interview'] : null;
            } catch (Throwable $e) { $interviewRow = null; }
        }

        $this->view('post/edit', ['post' => $post, 'categories' => $categories, 'currentType' => $currentType, 'currentTypeLabel' => $currentTypeLabel, 'payRow' => $payRow, 'eventRow' => $eventRow, 'pollRow' => $pollRow, 'debateRow' => $debateRow, 'lotteryRow' => $lotteryRow, 'interviewRow' => $interviewRow]);
    }

    /**
     * 更新帖子
     */
    public function update($id = null)
    {
        abortIfBanned();
        $id = (int)($id ?? input('id'));
        $post = Model::table('posts')->where('id', $id)->first();
        if (!$post) $this->error('帖子不存在');

        // 权限分级判定：
        //   1) 是自己的帖子 → 需要 post.edit_own（编辑自己帖子的权限）
        //   2) 是本版版主   → 需要 post.delete_section（本版删除权限同时含编辑权）
        //   3) 是 admin     → 需要 post.delete_section
        $isOwner = ((int)$post['user_id'] === (int)Auth::id());
        $isMod   = Auth::isModeratorOf((int)$post['category_id']);
        $isAdmin = is_admin();
        if ($isOwner) {
            if (!can('post.edit_own')) $this->error('无权限：未授予「编辑自己帖子」');
        } elseif ($isMod || $isAdmin) {
            if (!can('post.delete_section')) $this->error('无权限：未授予「本版删除」');
        } else {
            $this->error('无权编辑此帖子');
        }

        // 注意：拍卖帖有人出价后，仅拍卖模块字段（起拍价/加价/结拍时间）不可改，
        // 标题、正文、图片、附件、分类等其它内容仍可在模板中正常编辑。
        // 19 轮收敛后：编辑页不再暴露这些字段的输入（参见 templates/post/edit.php），
        // 模块一旦有出价即永久锁定，故此处不再读取拍品参数。

        $title = trim(input('title'));
        $content = input('content');
        $categoryId = (int)input('category_id');
        $attachmentsInput = input('attachments', null);
        $attachments = ($attachmentsInput !== null) ? $this->parseAttachments($attachmentsInput) : null;

        if (!$title) $this->error('标题不能为空');
        if (!$content) $this->error('内容不能为空');

        // 附件：以本次提交为准（可全部清空）；图片已下线，独立图库字段统一写 null。
        $newAttachments = !empty($attachments) ? json_encode($attachments) : null;

        // 2026-09-06 封面图编辑：所有主题类型编辑时都可更换/移除封面（隐藏字段随表单提交，置空=移除）。
        //   与 store() 同样的 uploads/ 前缀校验 + 列存在性探测（未升级的库静默忽略）。
        //   🚨 只在请求里真的带了 cover_image 字段时才更新（has_input 判断）：
        //   无图片上传权限的角色编辑时模板不渲染封面块、字段缺失，此时绝不能把已有封面误清空。
        $coverSubmitted = has_input('cover_image');
        $coverImage = $coverSubmitted ? trim((string)input('cover_image', '')) : '';
        if ($coverImage !== '' && strpos($coverImage, 'uploads/') !== 0) $coverImage = '';
        $hasCoverCol = false;
        if ($coverSubmitted && ($coverImage !== '' || trim((string)($post['cover_image'] ?? '')) !== '')) {
            try { $hasCoverCol = !empty(Model::query("SHOW COLUMNS FROM posts LIKE 'cover_image'")); } catch (Throwable $e) { $hasCoverCol = false; }
        }

        // 高危敏感词硬拦截
        if (has_high_risk_sensitive($title) || has_high_risk_sensitive($content)) {
            $this->error('内容包含高危敏感词，无法保存');
        }

        $updateData = [
            'title' => filter_sensitive($title),
            'content' => filter_sensitive($content),
            'category_id' => $categoryId ?: $post['category_id'],
            'images' => null,
            'attachments' => $newAttachments,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($hasCoverCol && $coverSubmitted) {
            $updateData['cover_image'] = $coverImage !== '' ? $coverImage : null;
        }

        // url_slug 补全策略：仅当原值为空时才生成（编辑标题不更新 slug，保外链稳定）
        if (empty($post['url_slug'])) {
            try {
                $updateData['url_slug'] = generate_unique_slug($updateData['title'], $id);
            } catch (Throwable $e) {
                // 兜底：slug 生成失败不阻塞保存
            }
        }

        Model::table('posts')->where('id', $id)->update($updateData);

        // 付费主题：同步更新扩展表的价格/预览比例/币种（订单已按购买时快照入账，调整不影响历史）
        if (($post['topic_type'] ?? '') === 'pay') {
            $cp = max(0, (int)input('pay_content_price', 0));
            $ap = max(0, (int)input('pay_attachment_price', 0));
            // 附件价格 > 0 但没附件 → 拒绝（防止「价格设了但忘了上传」/「删完附件后忘记清零价格」）
            if ($ap > 0 && empty($attachments)) {
                $this->error('已设置附件价格但未上传附件，请先上传附件或把附件价格改为 0');
            }
            try {
                $currency = input('pay_currency', 'token');
                if (!PointService::currencyExists($currency)) $currency = 'token';
                $previewRatio = (int)input('pay_preview_ratio', 20);
                $previewRatio = max(0, min(100, $previewRatio));
                $payData = [
                    'content_price' => $cp,
                    'attachment_price' => $ap,
                    'image_price' => 0,
                    'price' => $cp + $ap,
                    'currency' => $currency,
                    'preview_ratio' => $previewRatio,
                ];
                // 同步当前帖子的附件 JSON 进扩展表（发布后用户替换附件时需要刷新）。
                // 图片已下线，paid_images 不再写入。
                if ($newAttachments !== null) {
                    $atts = [];
                    foreach (json_decode($newAttachments, true) ?: [] as $a) {
                        if (is_array($a)) $atts[] = ['name' => $a['name'] ?? '', 'url' => $a['url'] ?? '', 'size' => $a['size'] ?? 0];
                    }
                    $payData['paid_images'] = '[]';
                    $payData['paid_attachments'] = json_encode($atts, JSON_UNESCAPED_UNICODE);
                }
                Model::table('topic_pay')->where('post_id', $id)->update($payData);
            } catch (Throwable $e) {
                error_log('update topic_pay failed post=' . $id . ' : ' . $e->getMessage());
            }
        }

        // 采访主题：同步两个提问开关（开放读者提问 / 允许受访者提问）。
        // 参与者、头衔等其余字段一经发布即锁定，此处不处理。
        if (($post['topic_type'] ?? '') === 'interview') {
            $interviewRow = Model::table('topic_interview')->where('post_id', $id)->first();
            if ($interviewRow) {
                $openQuestion = input('interview_open') ? 1 : 0;
                $allowIntervieweeAsk = input('interview_allow_interviewee_ask') ? 1 : 0;
                Model::table('topic_interview')->where('post_id', $id)->update([
                    'open_question'         => $openQuestion,
                    'allow_interviewee_ask' => $allowIntervieweeAsk,
                ]);
            }
        }

        $this->success(['redirect' => url('post/show', ['id' => $id])], '更新成功');
    }

    /**
     * 删除帖子（软删除）
     */
    public function delete($id = null)
    {
        $id = (int)($id ?? input('id'));
        $post = Model::table('posts')->where('id', $id)->first();
        if (!$post) $this->error('帖子不存在');

        // 权限分级：自己 → post.delete_own；版主/管理 → post.delete_section
        $isOwner = ((int)$post['user_id'] === (int)Auth::id());
        $isMod   = Auth::isModeratorOf((int)$post['category_id']);
        $isAdmin = is_admin();
        if ($isOwner) {
            if (!can('post.delete_own')) $this->error('无权限：未授予「删除自己帖子」');
        } elseif ($isMod || $isAdmin) {
            if (!can('post.delete_section')) $this->error('无权限：未授予「本版删除」');
        } else {
            $this->error('无权删除此帖子');
        }

        Model::table('posts')->where('id', $id)->update(['status' => 0, 'updated_at' => date('Y-m-d H:i:s'), 'deleted_at' => date('Y-m-d H:i:s'), 'deleted_by' => Auth::id()]);
        Model::execute('UPDATE categories SET post_count = GREATEST(post_count - 1, 0) WHERE id = ?', [$post['category_id']]);

        // 删帖回溯：回收该帖产生的全部积分（帖子本身 + 评论 + 被点赞）
        PointService::refundPostPoints($id);

        $this->success(['redirect' => url('home/index')], '删除成功');
    }

    /**
     * 关闭帖子（版主及以上）
     */
    public function closePost($id = null)
    {
        $id = (int)($id ?? input('id'));
        $post = Model::table('posts')->where('id', $id)->where('status', 1)->first();
        if (!$post) $this->error('帖子不存在');
        if (!Auth::isModeratorOf($post['category_id'])) $this->error('无权操作');
        if (!can('post.delete_section')) $this->error('无权限：未授予「本版删除」');
        Model::table('posts')->where('id', $id)->update(['is_closed' => 1, 'updated_at' => date('Y-m-d H:i:s')]);
        $this->success(null, '帖子已关闭');
    }

    /**
     * 重新开启帖子（版主及以上）
     */
    public function openPost($id = null)
    {
        $id = (int)($id ?? input('id'));
        $post = Model::table('posts')->where('id', $id)->where('status', 1)->first();
        if (!$post) $this->error('帖子不存在');
        if (!Auth::isModeratorOf($post['category_id'])) $this->error('无权操作');
        if (!can('post.delete_section')) $this->error('无权限：未授予「本版删除」');
        Model::table('posts')->where('id', $id)->update(['is_closed' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
        $this->success(null, '帖子已重新开启');
    }

    /**
     * 拍卖出价
     */
    public function bid($id = null)
    {
        abortIfBanned();
        $id = (int)($id ?? input('id'));
        $post = Model::table('posts')->where('id', $id)->where('status', 1)->first();
        if (!$post) $this->error('帖子不存在');
        if (empty($post['is_auction'])) $this->error('该帖子不是拍卖帖');
        if ($post['user_id'] == Auth::id()) $this->error('不能对自己的拍卖帖出价');
        if (!empty($post['is_closed'])) $this->error('帖子已关闭，无法出价');

        // 结拍时间校验
        if (!empty($post['end_time']) && strtotime($post['end_time']) <= time()) {
            $this->error('拍卖已结束');
        }

        $price = input('price');
        if ($price === null || $price === '') $this->error('请输入出价金额');
        $price = (float)$price;
        if ($price <= 0) $this->error('出价金额无效');

        $step = (float)($post['step_price'] ?: 10);
        $base = (float)($post['current_price'] !== null ? $post['current_price'] : $post['start_price']);
        $minBid = $base + $step;
        if ($price < $minBid - 0.001) {
            $this->error('出价不得低于 ¥' . number_format($minBid, 2));
        }

        $now = date('Y-m-d H:i:s');
        // 旧的最高价出价标记为被超越
        Model::table('bids')->where('post_id', $id)->where('status', 1)->update(['status' => 0]);
        Model::table('bids')->insert([
            'post_id' => $id,
            'user_id' => Auth::id(),
            'price' => $price,
            'status' => 1,
            'created_at' => $now,
        ]);
        Model::table('posts')->where('id', $id)->update([
            'current_price' => $price,
            'bid_count' => (int)Model::scalar('SELECT COUNT(DISTINCT user_id) FROM bids WHERE post_id = ?', [$id]),
            'updated_at' => $now,
        ]);

        // 结拍前3分钟内出价 → 延时5分钟，累计加时
        if (!empty($post['end_time'])) {
            $endTs = strtotime($post['end_time']);
            $remaining = $endTs - time();
            if ($remaining > 0 && $remaining <= 180) {
                $newEndTs = $endTs + 300;
                // 兼容旧数据库（缺 extended_minutes 字段）：读失败则按 0 处理
                try {
                    $curExtended = (int)Model::scalar('SELECT extended_minutes FROM posts WHERE id = ?', [$id]);
                    Model::table('posts')->where('id', $id)->update([
                        'end_time' => date('Y-m-d H:i:s', $newEndTs),
                        'extended_minutes' => $curExtended + 5,
                        'updated_at' => $now,
                    ]);
                } catch (\Throwable $e) {
                    // 字段缺失时只更新 end_time，加时统计跳过（请尽快跑 install/upgrade.php 或 install/migrate_extended_minutes.php 补字段）
                    try {
                        Model::table('posts')->where('id', $id)->update([
                            'end_time' => date('Y-m-d H:i:s', $newEndTs),
                            'updated_at' => $now,
                        ]);
                    } catch (\Throwable $e2) { /* 完全失败也不影响主出价 */ }
                }
            }
        }

        $this->success(['price' => $price, 'count' => (int)Model::scalar('SELECT COUNT(DISTINCT user_id) FROM bids WHERE post_id = ?', [$id])], '出价成功');
    }

    // ===================================================================
    // 特殊主题：付费 / 活动 / 悬赏 / 投票 / 辩论 / 采访
    // ===================================================================

    /**
     * 写入特殊主题扩展表（store() 发布后调用）。
     * 失败时由调用方 try/catch 兜底（不阻断主帖发布）。
     */
    protected function storeSpecial($postId, $topicType, $category, $title = '')
    {
        $now = date('Y-m-d H:i:s');
        switch ($topicType) {
            case 'pay':
                $contentPrice    = max(0, (int)input('pay_content_price', 0));
                $attachmentPrice = max(0, (int)input('pay_attachment_price', 0));
                $currency = input('pay_currency', 'token');
                if (!PointService::currencyExists($currency)) $currency = 'token';
                $previewRatio = (int)input('pay_preview_ratio', 20);
                $previewRatio = max(0, min(100, $previewRatio));
                $post = Model::table('posts')->where('id', $postId)->first();
                $paidAttachments = [];
                if (!empty($post['attachments'])) {
                    foreach (json_decode($post['attachments'], true) ?: [] as $a) {
                        if (is_array($a)) $paidAttachments[] = ['name' => $a['name'] ?? '', 'url' => $a['url'] ?? '', 'size' => $a['size'] ?? 0];
                    }
                }
                // 总解锁价 = 两项之和（保留 price 字段用于兼容旧查询/统计）。
                // 图片已下线：image_price 固定 0、paid_images 固定 '[]'，避免回查时残留旧数据。
                $totalPrice = $contentPrice + $attachmentPrice;
                Model::table('topic_pay')->insert([
                    'post_id' => $postId,
                    'price' => $totalPrice,
                    'currency' => $currency,
                    'content_price' => $contentPrice,
                    'attachment_price' => $attachmentPrice,
                    'image_price' => 0,
                    'preview_ratio' => $previewRatio,
                    'paid_attachments' => json_encode($paidAttachments, JSON_UNESCAPED_UNICODE),
                    'paid_images' => '[]',
                    'created_at' => $now,
                ]);
                break;

            case 'event':
                $eventTime = input('event_time', '');
                $location = trim(input('event_location', ''));
                $capacity = (int)input('event_capacity', 0);
                // 活动主题（用户自行填写，区别于帖子标题）+ 活动形式（线上/线下，只接受这两个值）
                $eventSubject = trim((string)input('event_subject', ''));
                $eventMode = (input('event_mode', 'offline') === 'online') ? 'online' : 'offline';
                // 活动费用（默认 0 = 免费，允许小数如 9.99，不允许负数）
                $eventFeeRaw = input('event_fee', 0);
                $eventFee = is_numeric($eventFeeRaw) ? max(0.0, round((float)$eventFeeRaw, 2)) : 0.0;
                $customFields = [];
                $cfRaw = input('custom_fields', []);
                if (is_array($cfRaw)) {
                    foreach ($cfRaw as $cf) {
                        if (!is_array($cf)) continue;
                        $label = trim($cf['label'] ?? '');
                        if ($label === '') continue;
                        $customFields[] = [
                            'label' => $label,
                            'type' => in_array($cf['type'] ?? 'text', ['text', 'textarea', 'select', 'number', 'date']) ? $cf['type'] : 'text',
                            'required' => !empty($cf['required']),
                            'options' => isset($cf['options']) && is_array($cf['options']) ? $cf['options'] : [],
                        ];
                    }
                }
                Model::table('topic_event')->insert([
                    'post_id' => $postId, 'event_time' => $eventTime ?: null, 'location' => $location ?: null,
                    'capacity' => $capacity, 'status' => 1,
                    'subject' => $eventSubject !== '' ? $eventSubject : null, 'mode' => $eventMode,
                    'fee' => $eventFee,
                    'custom_fields' => json_encode($customFields, JSON_UNESCAPED_UNICODE), 'created_at' => $now,
                ]);
                break;

            case 'bounty':
                $per = (int)input('bounty_per_person', 0);
                $people = (int)input('bounty_people', 0);
                if ($per <= 0) throw new \Exception('悬赏每人积分数必须大于 0');
                if ($people <= 0) throw new \Exception('悬赏人数必须大于 0');
                $total = $per * $people;
                // 从发布者账户扣除质押（spend 已带行锁 + 余额校验）
                $spend = PointService::spend(Auth::id(), 'bounty_stake', 'token', $postId, $total, '悬赏质押 #' . $postId);
                if (!$spend['ok']) {
                    throw new \Exception($spend['reason'] === 'insufficient' ? '质押失败：论坛积分余额不足（需 ' . $total . '）' : '质押失败，请稍后重试');
                }
                Model::table('topic_bounty')->insert([
                    'post_id' => $postId, 'per_person_tokens' => $per, 'people_count' => $people,
                    'total_tokens' => $total, 'accepted_count' => 0, 'status' => 1, 'ended_early' => 0, 'created_at' => $now,
                ]);
                break;

            case 'poll':
                $multi = input('poll_multi') ? 1 : 0;
                // 投票不再允许匿名（产品要求隐藏投票人）：前端复选框 + 后端皆强制 0，避免被绕过
                $anonymous = 0;
                $deadline = input('poll_deadline', '');
                $options = input('poll_options', []);
                if (!is_array($options)) $options = [];
                $options = array_values(array_filter(array_map('trim', $options), function ($o) { return $o !== ''; }));
                if (count($options) < 2) throw new \Exception('投票至少 2 个选项');
                $pollId = Model::table('topic_poll')->insert([
                    'post_id' => $postId, 'multi' => $multi, 'anonymous' => $anonymous,
                    'deadline' => $deadline ?: null, 'total_votes' => 0, 'created_at' => $now,
                ]);
                $sort = 0;
                foreach ($options as $o) {
                    Model::table('topic_poll_options')->insert(['poll_id' => $pollId, 'text' => $o, 'votes' => 0, 'sort' => $sort++]);
                }
                break;

            case 'debate':
                $pro = trim(input('debate_pro', ''));
                $con = trim(input('debate_con', ''));
                // 截止时间：前置校验已保证 ≥ now+15min，此处直接写入即可
                $dlRaw = input('debate_deadline', '');
                $dlTs = strtotime($dlRaw);
                Model::table('topic_debate')->insert([
                    'post_id' => $postId, 'pro_text' => $pro, 'con_text' => $con,
                    'pro_votes' => 0, 'con_votes' => 0,
                    'deadline' => $dlTs ? date('Y-m-d H:i:s', $dlTs) : null,
                    'created_at' => $now,
                ]);
                break;

            case 'interview':
                // 采访主题（2026-09-02 改造）：
                //   参与者升级为「网站用户」——记者默认 = 发帖人，可改；受访者必填且必须是 status=1 的网站用户。
                //   头衔仍是自由文本（旧字段兼容保留：interviewee/interviewee_title 文本列继续存，user_id 优先）。
                //   发布后不录入问答卡（问答走"前台提问/回答"流程，由 show.php 的 interviewAsk / interviewAnswer 动作处理）。
                $authorId = (int)Auth::id();
                $interviewTopic = trim((string)input('interview_topic', ''));
                if ($interviewTopic === '') throw new \Exception('请填写采访主题');
                if (mb_strlen($interviewTopic, 'UTF-8') > 60) throw new \Exception('采访主题过长（最多 20 字）');
                $reporterId = (int)input('reporter_id', $authorId);
                $reporterTitle = trim((string)input('reporter_title', ''));
                $intervieweeId = (int)input('interviewee_id', 0);
                $intervieweeTitle = trim((string)input('interviewee_title', ''));
                $openQuestion = input('interview_open') ? 1 : 0;
                $allowIntervieweeAsk = input('interview_allow_interviewee_ask') ? 1 : 0;
                if ($reporterId <= 0) $reporterId = $authorId;
                if ($intervieweeId <= 0) throw new \Exception('请选择受访者（必须是网站用户）');
                // 受访者必须为站内正常状态用户
                $ivUser = Model::table('users')->where('id', $intervieweeId)->where('status', 1)->first();
                if (!$ivUser) throw new \Exception('受访者必须是已注册且状态正常的网站用户');
                // 记者若不是发帖人，也得是站内正常用户（picker 选出来的，按理是；但仍校验防伪造）
                $rpUser = Model::table('users')->where('id', $reporterId)->where('status', 1)->first();
                if (!$rpUser) throw new \Exception('记者必须是已注册且状态正常的网站用户');
                $interviewId = Model::table('topic_interview')->insert([
                    'post_id'              => $postId,
                    'interview_topic'      => $interviewTopic,
                    'reporter_id'          => $reporterId,
                    'reporter_title'       => $reporterTitle !== '' ? $reporterTitle : null,
                    'interviewee_user_id'  => $intervieweeId,
                    // 兼容旧文本列：写入显示快照（昵称或用户名），老数据 fallback 仍能显示
                    'interviewee'          => (string)($ivUser['nickname'] ?: $ivUser['username']),
                    'interviewee_title'    => $intervieweeTitle !== '' ? $intervieweeTitle : null,
                    'open_question'        => $openQuestion,
                    'allow_interviewee_ask' => $allowIntervieweeAsk,
                    'ended_at'             => null,
                    'created_at'           => $now,
                ]);
                // 发布后推送采访邀请通知（跳过自通知：recipient == 发帖人）
                $postLink = url('post/show', ['id' => $postId], true);
                // storeSpecial() 是独立方法，原本没有 $title 入参。
                // 2026-09-03 修复：此处 $title 曾经是 undefined 变量 → mb_substr(null) → '' → 通知里显示《》空白。
                // 修复方案：store() 已把入参 $title 一并传入；这里若仍为空（极端脏数据/调用方漏传），从 posts 表反查兜底。
                if ($title === '') {
                    $p = Model::table('posts')->where('id', $postId)->first();
                    if ($p) $title = (string)$p['title'];
                }
                $postTitle = mb_substr($title, 0, 20, 'UTF-8');
                if ($reporterId !== $authorId) {
                    notify_user($reporterId, 'interview_invite', user_display_name($rpUser) . '，你被指定为《' . $postTitle . '》的采访记者，请前往提问', $postLink);
                }
                if ($intervieweeId !== $authorId) {
                    notify_user($intervieweeId, 'interview_invite', user_display_name($ivUser) . '，你被《' . $postTitle . '》采访，请前往回答提问', $postLink);
                }
                break;
            case 'lottery':
                $prizeName = trim(input('lottery_prize_name', ''));
                $winnerCount = (int)input('lottery_winner_count', 0);
                $joinCost = (int)input('lottery_join_cost', 0);
                $drawAt = input('lottery_draw_at', '');
                // 奖品类型 + 价值（实物/虚拟 = 文本；积分 = 每份积分数）
                $prizeType = (string)input('lottery_prize_type', 'physical');
                if (!in_array($prizeType, ['physical', 'virtual', 'point'], true)) $prizeType = 'physical';
                $prizeValueRaw = trim((string)input('lottery_prize_value', ''));
                $pointMode = (string)input('lottery_point_mode', 'custom');
                $pointInput = (int)input('lottery_point_input', 0);   // 前后段控件：自定义=每份积分数，随机=质押总额
                $stakeTotal = 0;
                $stakeCurrency = 'token';
                $pointUnit = 0;
                if ($prizeName === '') throw new \Exception('请填写奖品名称');
                if ($winnerCount <= 0) throw new \Exception('中奖人数必须大于 0');
                // 2026-09-01 去掉「奖品数量（份）」字段：「中奖人数」是唯一人数指标，一人一奖。
                // prize_count 数据库字段保留（兼容旧数据），写入时同步 = winner_count。
                $prizeCount = $winnerCount;
                if ($drawAt === '') throw new \Exception('请设置开奖时间');
                $drawTs = strtotime($drawAt);
                if ($drawTs === false || $drawTs < time() + 900) throw new \Exception('开奖时间必须晚于当前时间至少 15 分钟');
                // 4 项抽奖规则下拉：no / optional / must，全部 no 视为不限
                $ruleKeys = ['follow' => 'lottery_rule_follow', 'reply' => 'lottery_rule_reply', 'like' => 'lottery_rule_like', 'favorite' => 'lottery_rule_favorite'];
                $rules = [];
                foreach ($ruleKeys as $rk => $field) {
                    $rv = (string)input($field, 'no');
                    if (!in_array($rv, ['no', 'optional', 'must'], true)) $rv = 'no';
                    $rules[$rk] = $rv;
                }
                // 积分类奖品：质押总积分 = point_unit * winner_count（前置校验已通过余额）
                if ($prizeType === 'point') {
                    if ($pointInput <= 0) throw new \Exception('请填写「每份积分数」或「质押总额」（大于 0）');
                    $balance = PointService::balanceOf(Auth::id(), 'token');
                    if ($pointMode === 'random') {
                        if ($pointInput > $balance) {
                            throw new \Exception('「质押总额」不能超过论坛积分余额（当前 ' . (int)$balance . '）');
                        }
                        // 强制约束：随机模式下「总数 ≥ 中奖人数」（人均 ≥ 1）才允许发布
                        if ($pointInput < $winnerCount) {
                            throw new \Exception('随机模式要求「质押总额 ≥ 中奖人数」（当前 ' . $pointInput . ' / 中奖 ' . $winnerCount . '），平均每人至少 1 积分');
                        }
                        $stakeTotal = $pointInput;
                        // point_unit 字段记录「人均参考 = intdiv(stake, winner)」，仅用于详情页展示；
                        // 实际开奖按 stake_total + 实际中奖人数随机分配（详见 drawLottery()）。
                        $pointUnit = intdiv($stakeTotal, $winnerCount);
                    } else {
                        $pointUnit = $pointInput;
                        $stakeTotal = $pointUnit * $winnerCount;
                        if ($stakeTotal > $balance) {
                            throw new \Exception('论坛积分余额不足，质押需 ' . $stakeTotal . '（当前 ' . (int)$balance . '）');
                        }
                    }
                    $spend = PointService::spend(Auth::id(), 'lottery_stake', 'token', $postId, $stakeTotal, '抽奖质押 #' . $postId);
                    if (!$spend['ok']) {
                        throw new \Exception($spend['reason'] === 'insufficient' ? '质押失败：论坛积分余额不足（需 ' . $stakeTotal . '）' : '质押失败，请稍后重试');
                    }
                } else {
                    // 实物/虚拟：prize_value 是必填文本，价值=展示用
                    if ($prizeValueRaw === '') throw new \Exception('请填写奖品价值（如 ¥99 / 价值 100 元）');
                }
                Model::table('topic_lottery')->insert([
                    'post_id' => $postId, 'prize_name' => $prizeName, 'prize_count' => $prizeCount,
                    'winner_count' => $winnerCount, 'join_cost' => $joinCost, 'cost_currency' => 'token',
                    'draw_at' => date('Y-m-d H:i:s', $drawTs), 'status' => 0, 'joined_count' => 0,
                    'winners' => json_encode([], JSON_UNESCAPED_UNICODE),
                    // 2026-08-31 扩展字段：奖品类型/价值/质押/规则/中奖明细
                    'prize_type'     => $prizeType,
                    'prize_value'    => $prizeType === 'point' ? (string)$pointUnit : $prizeValueRaw,
                    'point_unit'     => $prizeType === 'point' ? $pointUnit : 0,
                    'point_mode'     => $pointMode,
                    'stake_points'   => $stakeTotal,
                    'stake_currency' => $stakeCurrency,
                    'rules_json'     => json_encode($rules, JSON_UNESCAPED_UNICODE),
                    'stake_returned' => 0,
                    'winners_detail' => json_encode([], JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                ]);
                break;
        }
    }

    /**
     * 读取特殊主题数据 + 当前用户相关状态（show() 调用）。
     */
    protected function loadSpecial($post)
    {
        $type = $post['topic_type'] ?? 'normal';
        $data = ['type' => $type];
        $uid = (int)Auth::id();
        $authorId = (int)$post['user_id'];
        switch ($type) {
            case 'pay':
                $row = Model::table('topic_pay')->where('post_id', $post['id'])->first();
                if ($row) {
                    $row['paid_attachments'] = json_decode($row['paid_attachments'] ?? '[]', true) ?: [];
                    $row['paid_images'] = json_decode($row['paid_images'] ?? '[]', true) ?: [];
                    // 两档价格（兼容老数据：缺列时填 0；image_price 已下线但保留字段置 0）
                    $row['content_price']    = (int)($row['content_price'] ?? 0);
                    $row['attachment_price'] = (int)($row['attachment_price'] ?? 0);
                    $row['image_price']      = 0;
                    $row['preview_ratio']    = (int)($row['preview_ratio'] ?? 20);
                    $row['price']            = $row['content_price'] + $row['attachment_price'];
                    $row['currency_label']   = PointService::currencyLabel($row['currency']);
                    $row['is_author'] = ($uid === $authorId);
                    // 查询当前用户已解锁的范围（去重，同一 scope 的多条订单只算一次）
                    $row['paid_scope'] = ['content' => false, 'attachment' => false, 'all' => false];
                    $row['paid_orders'] = [];
                    if ($uid && !$row['is_author']) {
                        $orders = Model::query('SELECT * FROM topic_pay_orders WHERE post_id = ? AND user_id = ?', [$post['id'], $uid]);
                        foreach ($orders ?: [] as $o) {
                            $scope = $o['unlock_scope'] ?? 'all';
                            $row['paid_orders'][] = $o;
                            if (isset($row['paid_scope'][$scope])) {
                                $row['paid_scope'][$scope] = true;
                            }
                            // 'all' 等价于 content+attachment 两档都解锁
                            if ($scope === 'all') {
                                $row['paid_scope']['content'] = true;
                                $row['paid_scope']['attachment'] = true;
                            }
                        }
                    } elseif ($row['is_author']) {
                        // 作者自己直接视为全解锁
                        $row['paid_scope'] = ['content' => true, 'attachment' => true, 'all' => true];
                    }
                    // 别名：'paid' 表示"是否已购买/作者可见"——任何一档解锁即视为已付
                    $row['paid'] = $row['is_author'] || !empty($row['paid_scope']['content']) || !empty($row['paid_scope']['attachment']);
                    if (!empty($row['paid_scope']['attachment']) || $row['is_author']) {
                        foreach ($row['paid_attachments'] as &$a) { if (!empty($a['url'])) $a['url'] = upload_url($a['url']); }
                    }
                }
                $data['pay'] = $row;
                break;
            case 'event':
                $row = Model::table('topic_event')->where('post_id', $post['id'])->first();
                if ($row) {
                    $row['custom_fields'] = json_decode($row['custom_fields'] ?? '[]', true) ?: [];
                    $row['signed_up'] = $uid ? (Model::table('topic_event_signups')->where('post_id', $post['id'])->where('user_id', $uid)->first() ? true : false) : false;
                    $row['is_author'] = ($uid === $authorId);
                    $row['signups'] = Model::table('topic_event_signups')->where('post_id', $post['id'])->count();
                    // 报名用户列表（最多展示最近 20 人）。所有报名者在帖子页都能看到，方便后来者
                    // 判断"都是谁去"——隐私/怕暴露报名者的考量：先按展示需求这么做，后续可加
                    // 「匿名报名」开关 + 活动级别字段控制。
                    // 头像相对路径走 upload_url() 绝对化，避免 pretty URL 下 404（项目反模式 #3）。
                    $signupRows = Model::query(
                        "SELECT s.user_id, s.created_at, u.username, u.nickname, u.avatar\n                         FROM topic_event_signups s\n                         LEFT JOIN users u ON s.user_id = u.id\n                         WHERE s.post_id = ?\n                         ORDER BY s.id DESC\n                         LIMIT 20",
                        [$post['id']]
                    );
                    $row['signups_list'] = array_map(function ($r) {
                        return [
                            'user_id'    => (int)($r['user_id'] ?? 0),
                            'username'   => (string)($r['username'] ?? ''),
                            'nickname'   => (string)($r['nickname'] ?? ''),
                            'avatar'     => !empty($r['avatar']) ? upload_url($r['avatar']) : '',
                            'signup_at'  => !empty($r['created_at']) ? date('Y-m-d H:i', strtotime($r['created_at'])) : '',
                        ];
                    }, $signupRows ?: []);
                    // 新字段兜底：升级脚本未执行前这两列不存在，避免模板取值为 null 报错
                    $row['subject'] = (string)($row['subject'] ?? '');
                    $row['mode']    = (!empty($row['mode']) && $row['mode'] === 'online') ? 'online' : 'offline';
                    // 活动费用：默认 0，老数据列不存在时 fallback 为 0。保留两位小数显示。
                    $row['fee']     = isset($row['fee']) && is_numeric($row['fee']) ? round((float)$row['fee'], 2) : 0.0;
                    // 导出权限：作者 / 本版版主 / 管理员（版主按帖子所属板块判定，不是全局版主）
                    $row['can_export'] = ($uid > 0) && (
                        $row['is_author']
                        || Auth::isModeratorOf((int)($post['category_id'] ?? 0))
                        || is_admin()
                    );
                }
                $data['event'] = $row;
                break;
            case 'bounty':
                $row = Model::table('topic_bounty')->where('post_id', $post['id'])->first();
                if ($row) {
                    $row['public'] = ((int)$row['status'] === 2) || ((int)$row['status'] === 3) || ((int)$row['people_count'] > 0 && (int)$row['accepted_count'] >= (int)$row['people_count']);
                    $row['is_author'] = ($uid === $authorId);
                    $row['currency_label'] = PointService::currencyLabel('token');
                    $accepted = Model::query('SELECT comment_id, user_id, tokens, created_at FROM topic_bounty_accepted WHERE post_id = ? ORDER BY id ASC', [$post['id']]);
                    $row['accepted_comment_ids'] = array_map(function ($r) { return (int)$r['comment_id']; }, $accepted);
                    // 被采纳用户信息卡片（活动贴 .event-signups 风格）
                    if (!empty($accepted)) {
                        $accUserIds = array_unique(array_map(function ($r) { return (int)$r['user_id']; }, $accepted));
                        $accMapRows = [];
                        if (!empty($accUserIds)) {
                            $ph = implode(',', array_fill(0, count($accUserIds), '?'));
                            $uRows = Model::query(
                                'SELECT id, username, nickname, avatar, is_certified, role FROM users WHERE id IN (' . $ph . ')',
                                $accUserIds
                            );
                            foreach ($uRows as $u) $accMapRows[(int)$u['id']] = $u;
                        }
                        $accepted_list = [];
                        foreach ($accepted as $a) {
                            $uid = (int)$a['user_id'];
                            $u = $accMapRows[$uid] ?? ['id' => $uid, 'username' => '', 'nickname' => '已注销用户', 'avatar' => ''];
                            $accepted_list[] = [
                                'comment_id'  => (int)$a['comment_id'],
                                'user_id'     => $uid,
                                'username'    => $u['username'] ?? '',
                                'nickname'    => $u['nickname'] ?? '',
                                'avatar'      => $u['avatar'] ?? '',
                                'tokens'      => (int)$a['tokens'],
                                'accepted_at' => $a['created_at'] ?? '',
                                'avatar_url'  => !empty($u['avatar']) ? upload_url($u['avatar']) : '',
                            ];
                        }
                        $row['accepted_list'] = $accepted_list;
                    } else {
                        $row['accepted_list'] = [];
                    }
                }
                $data['bounty'] = $row;
                break;
            case 'poll':
                $row = Model::table('topic_poll')->where('post_id', $post['id'])->first();
                if ($row) {
                    $row['options'] = Model::query('SELECT * FROM topic_poll_options WHERE poll_id = ? ORDER BY sort ASC, id ASC', [$row['id']]);
                    $row['voted'] = false;
                    $row['my_option_ids'] = [];
                    if ($uid) {
                        $votes = Model::query('SELECT option_id FROM topic_poll_votes WHERE poll_id = ? AND user_id = ?', [$row['id'], $uid]);
                        if ($votes) { $row['voted'] = true; $row['my_option_ids'] = array_map(function ($v) { return (int)$v['option_id']; }, $votes); }
                    }
                    $row['closed'] = $row['deadline'] ? (strtotime($row['deadline']) <= time()) : false;
                }
                $data['poll'] = $row;
                break;
            case 'debate':
                $row = Model::table('topic_debate')->where('post_id', $post['id'])->first();
                if ($row) {
                    $row['statements'] = Model::query('SELECT s.*, u.nickname, u.username, u.avatar, u.is_certified, u.role, u.certified_at FROM topic_debate_statements s LEFT JOIN users u ON s.user_id = u.id WHERE s.post_id = ? ORDER BY s.id ASC', [$post['id']]);
                    // my_side：以该用户最早一条发言 side 为准（即不可切换的「初始站队」）
                    $row['my_side'] = 0;
                    $row['my_first_statement'] = null;
                    if ($uid) {
                        $mine = Model::table('topic_debate_statements')
                            ->where('post_id', $post['id'])->where('user_id', $uid)
                            ->orderBy('id', 'ASC')->first();
                        if ($mine) {
                            $row['my_side'] = (int)$mine['side'];
                            // 把首发言内容也带过去，前端可直接进入评论区作为「我站队后第一次发言」展示锚
                            $row['my_first_statement'] = ['id' => (int)$mine['id'], 'side' => (int)$mine['side'], 'content' => (string)$mine['content']];
                        }
                    }
                    // 截止状态：deadline 已到 → 截止；未设置 deadline → 不限（兼容旧数据）
                    $row['closed'] = !empty($row['deadline']) && strtotime($row['deadline']) <= time();
                    $row['my_statement_count'] = $uid ? (int)Model::scalar(
                        'SELECT COUNT(*) FROM topic_debate_statements WHERE post_id = ? AND user_id = ?',
                        [$post['id'], $uid]
                    ) : 0;
                }
                $data['debate'] = $row;
                break;
            case 'interview':
                $row = Model::table('topic_interview')->where('post_id', $post['id'])->first();
                if ($row) {
                    $reporterId   = (int)($row['reporter_id'] ?? 0) ?: $authorId;
                    $intervieweeId = (int)($row['interviewee_user_id'] ?? 0);
                    // 联结记者 / 受访者用户信息（用于前台头像 / 主页跳转 / 头衔显示）。
                    //   老数据（reporter_id=NULL → fallback 为发帖人；interviewee_user_id=NULL → 回退旧文本）。
                    $reporterUser = null;
                    $intervieweeUser = null;
                    if ($reporterId > 0) {
                        $reporterUser = Model::table('users')->where('id', $reporterId)->first();
                    }
                    if ($intervieweeId > 0) {
                        $intervieweeUser = Model::table('users')->where('id', $intervieweeId)->first();
                    }
                    // 打包为展示卡片数据（头像走 upload_url 绝对化，避免 pretty URL 下 404）
                    $ivReporter = null;
                    if ($reporterUser) {
                        $ivReporter = [
                            'id'       => (int)$reporterUser['id'],
                            'name'     => user_display_name($reporterUser),
                            'avatar'   => !empty($reporterUser['avatar']) ? upload_url($reporterUser['avatar']) : '',
                            'title'    => (string)($row['reporter_title'] ?? ''),
                            'role'     => '记者',
                            'profile'  => url('user/profile', ['id' => (int)$reporterUser['id']]),
                        ];
                    } else {
                        // 极兜底：reporter_id 无效时用发帖人
                        $ivReporter = [
                            'id' => $authorId, 'name' => user_display_name($post['author'] ?? []),
                            'avatar' => !empty($post['author']['avatar']) ? upload_url($post['author']['avatar']) : '',
                            'title' => (string)($row['reporter_title'] ?? ''),
                            'role' => '记者', 'profile' => url('user/profile', ['id' => $authorId]),
                        ];
                    }
                    $ivInterviewee = null;
                    if ($intervieweeUser) {
                        $ivInterviewee = [
                            'id'       => (int)$intervieweeUser['id'],
                            'name'     => user_display_name($intervieweeUser),
                            'avatar'   => !empty($intervieweeUser['avatar']) ? upload_url($intervieweeUser['avatar']) : '',
                            'title'    => (string)($row['interviewee_title'] ?? ''),
                            'role'     => '受访者',
                            'profile'  => url('user/profile', ['id' => (int)$intervieweeUser['id']]),
                        ];
                    } else {
                        // 老数据 fallback：interviewee_user_id 为空时用旧文本 + 旧头衔
                        $ivInterviewee = [
                            'id' => 0, 'name' => (string)($row['interviewee'] ?? '受访者'),
                            'avatar' => '', 'title' => (string)($row['interviewee_title'] ?? ''),
                            'role' => '受访者', 'profile' => '',
                        ];
                    }
                    $row['reporter_card']    = $ivReporter;
                    $row['interviewee_card'] = $ivInterviewee;
                    $row['interview_topic']  = trim((string)($row['interview_topic'] ?? '')) !== '' ? (string)$row['interview_topic'] : (string)($post['title'] ?? '');
                    $row['is_reporter']      = ($uid > 0 && $uid === $reporterId);
                    $row['is_interviewee']   = ($uid > 0 && $uid === $intervieweeId);
                    $row['is_author']        = ($uid === $authorId);
                    $row['ended']            = !empty($row['ended_at']);
                    $row['open_question']    = (int)($row['open_question'] ?? 0);
                    $row['allow_interviewee_ask'] = (int)($row['allow_interviewee_ask'] ?? 0);
                    // 采访问答分页：每页 15 条，按 sort ASC, id ASC（提问入库顺序稳定）。?ivpage=1..N
                    $perPage = 15;
                    $ivPage  = max(1, (int)($_GET['ivpage'] ?? 1));
                    $ivTotalQa = (int)Model::scalar(
                        'SELECT COUNT(*) FROM topic_interview_qa WHERE interview_id = ?',
                        [$row['id']]
                    );
                    $ivTotalPages = max(1, (int)ceil($ivTotalQa / $perPage));
                    if ($ivPage > $ivTotalPages) $ivPage = $ivTotalPages;
                    $ivOffset = ($ivPage - 1) * $perPage;
                    $row['qa'] = Model::query(
                        "SELECT q.*, asker.username AS asker_username, asker.nickname AS asker_nickname, asker.avatar AS asker_avatar,
                                ans.username AS answerer_username, ans.nickname AS answerer_nickname, ans.avatar AS answerer_avatar
                           FROM topic_interview_qa q
                      LEFT JOIN users asker ON q.asker_id = asker.id
                      LEFT JOIN users ans   ON q.answerer_id = ans.id
                          WHERE q.interview_id = ?
                       ORDER BY q.sort ASC, q.id ASC
                          LIMIT " . (int)$perPage . " OFFSET " . (int)$ivOffset,
                        [$row['id']]
                    );
                    // 给每条 QA 加上 asker_card / 提问者是否当前用户（决定显示"回答"按钮等）
                    foreach ($row['qa'] as &$qa) {
                        $aUser = [
                            'id' => (int)($qa['asker_id'] ?? 0),
                            'username' => (string)($qa['asker_username'] ?? ''),
                            'nickname' => (string)($qa['asker_nickname'] ?? ''),
                            'avatar'   => (string)($qa['asker_avatar'] ?? ''),
                        ];
                        // 三种身份：记者 / 受访者 / 读者。前台按身份显示对应配色和回答权限。
                        $qaAskerId = (int)($qa['asker_id'] ?? 0);
                        $isQaReporter = ($reporterId > 0 && $qaAskerId === $reporterId);
                        $isQaInterviewee = ($intervieweeId > 0 && $qaAskerId === $intervieweeId);
                        $askerRoleLabel = $isQaReporter ? '记者' : ($isQaInterviewee ? '受访者' : '读者');
                        $qa['asker_card'] = [
                            'id' => $aUser['id'], 'name' => user_display_name($aUser),
                            'avatar' => $aUser['avatar'] ? upload_url($aUser['avatar']) : '',
                            'profile' => $aUser['id'] ? url('user/profile', ['id' => $aUser['id']]) : '',
                            'role' => $askerRoleLabel,
                        ];
                        $qa['is_answered'] = trim((string)($qa['answer'] ?? '')) !== '';
                        // 回答权限规则：
                        //   · 受访者提的问题（反向追问） → 仅记者可答
                        //   · 其他（读者/记者）提的问题  → 受访者可答
                        $qa['can_answer'] = !$qa['is_answered'] && !$row['ended'] && (
                            ($askerRoleLabel === '受访者' && $row['is_reporter'])
                            || ($askerRoleLabel !== '受访者' && $row['is_interviewee'])
                        );
                        // 回答者身份卡：跟随主题设置（记者/受访者卡片，含自定义头衔），而非裸用户
                        $qaAnswererId = (int)($qa['answerer_id'] ?? 0);
                        if ($qaAnswererId > 0) {
                            if ($reporterId > 0 && $qaAnswererId === $reporterId) {
                                $ac = $row['reporter_card'];
                                $ac['role'] = '记者';
                            } elseif ($intervieweeId > 0 && $qaAnswererId === $intervieweeId) {
                                $ac = $row['interviewee_card'];
                                $ac['role'] = '受访者';
                            } else {
                                // 兜底：理论上仅记者/受访者可答，这里用裸用户信息
                                $ac = [
                                    'id'     => $qaAnswererId,
                                    'name'   => user_display_name([
                                        'username' => (string)($qa['answerer_username'] ?? ''),
                                        'nickname' => (string)($qa['answerer_nickname'] ?? ''),
                                    ]),
                                    'avatar' => !empty($qa['answerer_avatar']) ? upload_url($qa['answerer_avatar']) : '',
                                    'title'  => '',
                                    'role'   => '其他',
                                    'profile' => url('user/profile', ['id' => $qaAnswererId]),
                                ];
                            }
                            $qa['answerer_card'] = $ac;
                        } else {
                            $qa['answerer_card'] = null;
                        }
                    }
                    unset($qa);
                    // 翻页元数据交给模板
                    $row['qa_page']      = $ivPage;
                    $row['qa_per_page']  = $perPage;
                    $row['qa_total']     = $ivTotalQa;
                    $row['qa_total_pages'] = $ivTotalPages;
                }
                $data['interview'] = $row;
                break;
            case 'lottery':
                $row = Model::table('topic_lottery')->where('post_id', $post['id'])->first();
                if ($row) {
                    $row['joined'] = $uid ? (Model::table('topic_lottery_joins')->where('post_id', $post['id'])->where('user_id', $uid)->first() ? true : false) : false;
                    $row['is_author'] = ($uid === $authorId);
                    $row['winners'] = json_decode($row['winners'] ?? '[]', true) ?: [];
                    $row['winners_detail'] = json_decode($row['winners_detail'] ?? '[]', true) ?: [];
                    // 头像绝对化（pretty URL 下 404 反模式）：避免 JS 端再拼绝对路径
                    foreach ($row['winners_detail'] as &$w) {
                        $w['avatar_url'] = !empty($w['avatar']) ? upload_url($w['avatar']) : '';
                    }
                    unset($w);
                    $row['winner_users'] = [];
                    if ($row['winners']) {
                        $ids = implode(',', array_map('intval', $row['winners']));
                        $row['winner_users'] = Model::query("SELECT id, nickname, username, avatar FROM users WHERE id IN ($ids)");
                    }
                    // 2026-09-01 移除 $row['drawable'] 字段：站长手动开奖按钮已删除，仅保留「参与时到时间自动开奖」。
                    // 2026-08-31 扩展字段：奖品类型/价值/质押/规则
                    $row['prize_type']     = (string)($row['prize_type'] ?? 'physical');
                    $row['prize_value']    = (string)($row['prize_value'] ?? '');
                    $row['point_unit']     = (int)($row['point_unit'] ?? 0);
                    $row['point_mode']     = (string)($row['point_mode'] ?? 'custom');
                    $row['stake_points']   = (int)($row['stake_points'] ?? 0);
                    $row['stake_currency'] = (string)($row['stake_currency'] ?? 'token');
                    $row['rules']          = json_decode($row['rules_json'] ?? '{}', true) ?: [];
                    $row['stake_returned'] = (int)($row['stake_returned'] ?? 0);
                    // 倒计时秒数（>0 表示未开奖且未到时间；≤0 表示已到开奖时间或已开奖）
                    $row['draw_in_seconds'] = max(0, strtotime($row['draw_at']) - time());
                }
                $data['lottery'] = $row;
                break;
        }
        return $data;
    }

    /* ---------- 抽奖主题：自动参与 / 开奖 ---------- */

    /**
     * 抽奖自动处理（帖子详情页每次访问调用，show() 内触发）。
     *   1) 自动登记参与资格：当前登录用户、非作者、抽奖进行中、尚未加入 → 写入 topic_lottery_joins。
     *      规则（关注/回复/点赞/收藏）的严格判定推迟到开奖时，因为"回复15字"等是动态动作，
     *      访问时未必已完成；此处只登记"来过 = 潜在参与者"。自动加入不扣 join_cost（避免"逛帖即扣费"）。
     *   2) 自动开奖：抽奖进行中 且 开奖时间已到 → 触发 drawLottery()（替代原"点参与才触发"的不可靠机制）。
     */
    protected function autoProcessLottery($post)
    {
        $postId = (int)$post['id'];
        $row = Model::table('topic_lottery')->where('post_id', $postId)->first();
        if (!$row || (int)$row['status'] !== 0) return;  // 已开奖 / 不存在 → 跳过
        $uid = Auth::id();
        $authorId = (int)$post['user_id'];
        // 1) 自动登记参与资格
        if ($uid && $uid !== $authorId) {
            $joined = Model::table('topic_lottery_joins')
                ->where('post_id', $postId)->where('user_id', $uid)->first();
            if (!$joined) {
                Model::table('topic_lottery_joins')->insert([
                    'post_id'    => $postId,
                    'user_id'    => $uid,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                Model::table('topic_lottery')->where('id', $row['id'])
                    ->update(['joined_count' => (int)$row['joined_count'] + 1]);
            }
        }
        // 2) 自动开奖（已到开奖时间）
        if (strtotime($row['draw_at']) <= time()) {
            $this->drawLottery($postId);
        }
    }

    // 2026-09-01 移除 lotteryDraw()：站长手动开奖功能下线。
    // 现在开奖由「帖子详情页访问时若到开奖时间」自动触发（见 autoProcessLottery()）；
    // 无任何人访问则不开奖（空奖），符合"无访问 = 无参与"的语义。
    protected function drawLottery($postId)
    {
        $row = Model::table('topic_lottery')->where('post_id', $postId)->first();
        if (!$row || (int)$row['status'] !== 0) return;
        $joins = Model::query('SELECT user_id FROM topic_lottery_joins WHERE post_id = ? ORDER BY id ASC', [$postId]);
        $userIds = array_map(function ($j) { return (int)$j['user_id']; }, $joins);
        $authorId = (int)Model::scalar('SELECT user_id FROM posts WHERE id = ?', [$postId]);
        // 解析 4 项规则（仅 must 参与过滤；no / optional 不影响资格判定，仅在规则 chips 中展示作者意图）
        $rules = json_decode($row['rules_json'] ?? '{}', true) ?: [];
        $needFollow   = (isset($rules['follow'])   && $rules['follow']   === 'must');
        $needReply    = (isset($rules['reply'])    && $rules['reply']    === 'must');
        $needLike     = (isset($rules['like'])     && $rules['like']     === 'must');
        $needFavorite = (isset($rules['favorite']) && $rules['favorite'] === 'must');
        // 严格按规则筛选 candidates：不满足任一 must → 直接 pass
        $candidates = [];
        foreach ($userIds as $uid) {
            if ($uid <= 0 || $uid === $authorId) continue; // 作者不参与自己抽奖
            $ok = true;
            if ($ok && $needFollow) {
                $f = (int)Model::scalar(
                    "SELECT COUNT(*) FROM interactions WHERE user_id = ? AND target_type = 'user' AND target_id = ? AND action_type = 'follow'",
                    [$uid, $authorId]
                );
                if ($f <= 0) $ok = false;
            }
            if ($ok && $needReply) {
                // 回复 15 字以上（CHAR_LENGTH 按字符计，不区分中英文）
                $r = (int)Model::scalar(
                    "SELECT COUNT(*) FROM comments WHERE post_id = ? AND user_id = ? AND status = 1 AND CHAR_LENGTH(content) >= 15",
                    [$postId, $uid]
                );
                if ($r <= 0) $ok = false;
            }
            if ($ok && $needLike) {
                $l = (int)Model::scalar(
                    "SELECT COUNT(*) FROM interactions WHERE user_id = ? AND target_type = 'post' AND target_id = ? AND action_type = 'like'",
                    [$uid, $postId]
                );
                if ($l <= 0) $ok = false;
            }
            if ($ok && $needFavorite) {
                $c = (int)Model::scalar(
                    "SELECT COUNT(*) FROM interactions WHERE user_id = ? AND target_type = 'post' AND target_id = ? AND action_type = 'collect'",
                    [$uid, $postId]
                );
                if ($c <= 0) $ok = false;
            }
            if ($ok) $candidates[] = $uid;
        }
        $winnerCount = (int)$row['winner_count'];
        $pointUnit = (int)($row['point_unit'] ?? 0);
        $isPoint = ($row['prize_type'] ?? 'physical') === 'point';
        $stakeTotal = (int)($row['stake_points'] ?? 0);
        $stakeCurrency = (string)($row['stake_currency'] ?? 'token');
        // 无合格用户：积分类奖品质押全额退还作者（2026-09-01 用户明确要求"0 人符合则全额退还"）
        if (empty($candidates)) {
            $refund = 0;
            if ($isPoint && $stakeTotal > 0) {
                $refund = (int)$stakeTotal;
                if ($refund > 0) {
                    PointService::grant($authorId, $stakeCurrency, $refund, 'lottery_refund', $postId, '抽奖无人中奖全额返还 #' . $postId);
                }
            }
            Model::table('topic_lottery')->where('id', $row['id'])->update([
                'status' => 1,
                'winners' => json_encode([], JSON_UNESCAPED_UNICODE),
                'winners_detail' => json_encode([], JSON_UNESCAPED_UNICODE),
                'stake_returned' => $refund > 0 ? 1 : 0,
            ]);
            return;
        }
        // 在 candidates 中随机抽 winner_count 个
        shuffle($candidates);
        $actualWinners = array_slice($candidates, 0, min($winnerCount, count($candidates)));
        $actualCount = count($actualWinners);
        // 积分类奖品发放
        $perWinnerShares = [];  // 每位中奖者本次拿到的具体值（用于 winners_detail.granted 显示）
        $refund = 0;
        $hasWinner = count($actualWinners) > 0;
        if ($isPoint && $stakeTotal > 0 && $hasWinner) {
            $pointModeDb = (string)($row['point_mode'] ?? '');  // DB 没该列则为空，按自定义处理
            if ($pointModeDb === 'random') {
                // 随机模式（2026-09-01 修订）：把质押总额全部随机分给 actual 个人，每人 ≥ 1、且各不相同；
                // 用户要求「随机情况下不存在剩余」→ 即使 winnerCount > actualCount（实际符合的人 < 中奖名额），未抽中名额也不返还原；
                // 商品按总和发放，不存在"未发完"概念；唯一保留 refund 的边界在「无人符合」分支。
                $res = LotteryService::splitStakeRandomly($stakeTotal, $actualCount);
                $perWinnerShares = $res['shares'];
                foreach ($actualWinners as $idx => $wid) {
                    $amt = (int)($perWinnerShares[$idx] ?? 0);
                    if ($amt > 0) {
                        PointService::grant($wid, $stakeCurrency, $amt, 'lottery_win', $postId, '抽奖中奖 #' . $postId);
                    }
                }
            } else {
                // 自定义模式：每位中奖者拿 point_unit（每人相同）
                foreach ($actualWinners as $wid) {
                    PointService::grant($wid, $stakeCurrency, $pointUnit, 'lottery_win', $postId, '抽奖中奖 #' . $postId);
                }
                $perWinnerShares = array_fill(0, $actualCount, $pointUnit);
                // custom 模式依然保留旧规则：未抽中名额按 point_unit × 缺额 / 2 返还作者
                $shortAmount = max(0, $winnerCount - $actualCount) * $pointUnit;
                $refund = (int)floor($shortAmount / 2);
                if ($refund > 0) {
                    PointService::grant($authorId, $stakeCurrency, $refund, 'lottery_refund', $postId, '抽奖未发完返还 #' . $postId);
                }
            }
        }
        // 中奖明细：取用户信息 + 名次 + 实际获得积分
        $lotteryPostTitle = Model::scalar('SELECT title FROM posts WHERE id = ?', [$postId]);
        $lotteryCurrencyLabel = PointService::currencyLabel($stakeCurrency);
        $winnerUserRows = [];
        if (!empty($actualWinners)) {
            $ph = implode(',', array_fill(0, count($actualWinners), '?'));
            $winnerUserRows = Model::query(
                'SELECT id, username, nickname, avatar FROM users WHERE id IN (' . $ph . ')',
                $actualWinners
            );
        }
        $userMap = [];
        foreach ($winnerUserRows as $u) $userMap[(int)$u['id']] = $u;
        $detail = [];
        $rank = 1;
        foreach ($actualWinners as $idx => $wid) {
            $u = $userMap[$wid] ?? ['id' => $wid, 'username' => '', 'nickname' => '', 'avatar' => ''];
            // 随机模式下每人份额不同；自定义模式统一 = point_unit
            $granted = isset($perWinnerShares[$idx]) ? (int)$perWinnerShares[$idx] : (int)$pointUnit;
            $detail[] = [
                'user_id'  => $wid,
                'username' => (string)($u['username'] ?? ''),
                'nickname' => (string)($u['nickname'] ?? '') ?: (string)($u['username'] ?? '') ?: '已注销用户',
                'avatar'   => (string)($u['avatar'] ?? ''),
                'rank'     => $rank++,
                'granted'  => $granted, // 实物/虚拟时 = 0，模板中不展示积分字段
            ];
            // 通知中奖者（积分奖品带金额，实物/虚拟仅告知中奖）
            $winMsg = $isPoint
                ? '恭喜！你在抽奖帖《' . mb_substr($lotteryPostTitle, 0, 20) . '》中中奖，获得 ' . $granted . ' ' . $lotteryCurrencyLabel
                : '恭喜！你在抽奖帖《' . mb_substr($lotteryPostTitle, 0, 20) . '》中中奖';
            notify_user($wid, 'lottery_won', $winMsg, url('post/show', ['id' => $postId], true));
        }
        Model::table('topic_lottery')->where('id', $row['id'])->update([
            'status' => 1,
            'winners' => json_encode($actualWinners, JSON_UNESCAPED_UNICODE),
            'winners_detail' => json_encode($detail, JSON_UNESCAPED_UNICODE),
            'stake_returned' => $refund > 0 ? 1 : 0,
        ]);
    }

    /* ---------- 付费主题：购买解锁（支持单项 / 三项联合） ---------- */
    public function payBuy($id = null)
    {
        if (!is_logged_in()) { $this->error('请先登录'); }
        $id = (int)($id ?? input('id'));
        $post = Model::table('posts')->where('id', $id)->where('status', 1)->first();
        if (!$post) $this->error('帖子不存在');
        if ((int)$post['user_id'] === (int)Auth::id()) $this->error('不能购买自己的付费帖');
        $row = Model::table('topic_pay')->where('post_id', $id)->first();
        if (!$row) $this->error('该帖未设置付费内容');
        // 两档价格（缺列时填 0）；image_price 已下线
        $cp = (int)($row['content_price'] ?? 0);
        $ap = (int)($row['attachment_price'] ?? 0);
        $total = $cp + $ap;
        if ($total <= 0) $this->error('该帖未设置任何付费内容');

        // scope: all / content / attachment（image 已下线，老 scope='image' 降级为 all）
        $scope = (string)input('scope', 'all');
        if ($scope === 'image') $scope = 'all';
        if (!in_array($scope, ['all', 'content', 'attachment'], true)) $scope = 'all';
        if ($scope === 'all') {
            $amount = $total;
            $itemCp = $cp; $itemAp = $ap;
        } elseif ($scope === 'content') {
            if ($cp <= 0) $this->error('该帖正文未设置付费');
            $amount = $cp; $itemCp = $cp; $itemAp = 0;
        } elseif ($scope === 'attachment') {
            if ($ap <= 0) $this->error('该帖附件未设置付费');
            $amount = $ap; $itemCp = 0; $itemAp = $ap;
        }

        // 已购过该 scope 直接提示
        $already = Model::table('topic_pay_orders')
            ->where('post_id', $id)
            ->where('user_id', Auth::id())
            ->where('unlock_scope', $scope)
            ->first();
        if ($already) {
            $this->error('您已购买过该部分，可直接查看');
        }
        // 买了 all 之后任何单项都视为已购（拦截重复）
        if ($scope !== 'all') {
            $allOrder = Model::table('topic_pay_orders')
                ->where('post_id', $id)
                ->where('user_id', Auth::id())
                ->where('unlock_scope', 'all')
                ->first();
            if ($allOrder) $this->error('您已购买过全部内容，可直接查看');
        }

        $spend = PointService::spend(Auth::id(), 'pay_buy', $row['currency'], $id, $amount, '付费查看 #' . $id);
        if (!$spend['ok']) $this->error($spend['reason'] === 'insufficient' ? '余额不足' : '支付失败');
        // 收入转给作者（按实际购买金额）
        PointService::grant($post['user_id'], $row['currency'], $amount, 'pay_income', $id, '内容付费收入 #' . $id);
        // 通知作者：主题被购买
        $buyerName = user_display_name(Auth::user());
        $buyerCurrencyLabel = PointService::currencyLabel($row['currency']);
        notify_user($post['user_id'], 'topic_purchased', '你的付费帖《' . mb_substr($post['title'], 0, 20) . '》被 ' . $buyerName . ' 购买，获得 ' . $amount . ' ' . $buyerCurrencyLabel . ' 收入', url('post/show', ['id' => $id], true));
        // buyer_count 只在第一次购买（all）或解锁首个单项时 +1（避免单帖同一用户多次计次）
        $orderCount = (int)Model::scalar('SELECT COUNT(*) FROM topic_pay_orders WHERE post_id = ? AND user_id = ?', [$id, Auth::id()]);
        if ($orderCount === 0) {
            Model::table('topic_pay')->where('id', $row['id'])->update(['buyer_count' => (int)$row['buyer_count'] + 1]);
        }
        Model::table('topic_pay_orders')->insert([
            'post_id' => $id,
            'user_id' => Auth::id(),
            'currency' => $row['currency'],
            'amount' => $amount,
            'unlock_scope' => $scope,
            'content_price' => $itemCp,
            'attachment_price' => $itemAp,
            'image_price' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->success(['paid' => true, 'scope' => $scope], '购买成功，已解锁');
    }

    /* ---------- 活动主题：报名 / 导出 ---------- */
    public function eventSignup($id = null)
    {
        if (!is_logged_in()) { $this->error('请先登录'); }
        $id = (int)($id ?? input('id'));
        $post = Model::table('posts')->where('id', $id)->where('status', 1)->first();
        if (!$post) $this->error('帖子不存在');
        $row = Model::table('topic_event')->where('post_id', $id)->first();
        if (!$row) $this->error('该帖不是活动帖');
        if ((int)$row['status'] !== 1) $this->error('活动已结束或已满员');
        if ((int)$row['capacity'] > 0 && (int)$row['signup_count'] >= (int)$row['capacity']) {
            Model::table('topic_event')->where('id', $row['id'])->update(['status' => 2]);
            $this->error('活动已满员');
        }
        if (Model::table('topic_event_signups')->where('post_id', $id)->where('user_id', Auth::id())->first()) {
            $this->error('您已报名，无需重复');
        }
        $customFields = json_decode($row['custom_fields'] ?? '[]', true) ?: [];
        $data = [];
        foreach ($customFields as $cf) {
            // is_scalar 保护：前端若被改成同名多个控件，input() 会返回数组，直接 trim 会 TypeError
            $raw = input('cf_' . md5($cf['label']), '');
            $val = is_scalar($raw) ? trim((string)$raw) : '';
            if (!empty($cf['required']) && $val === '') $this->error('请填写：' . $cf['label']);

            // 下拉字段：提交值必须落在发布者配置的选项内，防止改前端塞入任意值。
            // 兼容处理：老帖 options 为空（发布时没配选项）时跳过校验，不至于让老活动报不了名。
            if (($cf['type'] ?? 'text') === 'select' && $val !== '') {
                $cfOptions = (isset($cf['options']) && is_array($cf['options'])) ? $cf['options'] : [];
                $allowed = [];
                foreach ($cfOptions as $opt) {
                    if (is_scalar($opt) && (string)$opt !== '') $allowed[] = (string)$opt;
                }
                if (!empty($allowed) && !in_array($val, $allowed, true)) {
                    $this->error('「' . $cf['label'] . '」选择了无效的选项，请重新选择');
                }
            }

            $data[] = ['label' => $cf['label'], 'value' => $val];
        }
        Model::table('topic_event_signups')->insert(['post_id' => $id, 'user_id' => Auth::id(), 'data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'created_at' => date('Y-m-d H:i:s')]);
        Model::table('topic_event')->where('id', $row['id'])->update(['signup_count' => (int)$row['signup_count'] + 1]);
        $this->success(['signed' => true], '报名成功');
    }

    public function eventExport($id = null)
    {
        $id = (int)($id ?? input('id'));
        $post = Model::table('posts')->where('id', $id)->first();
        if (!$post) $this->error('帖子不存在');
        // 导出权限：作者 / 本版版主（按帖子所属板块判定）/ 管理员。
        // 注意与 show.php 里 $ev['can_export'] 的判定保持一致，否则会出现"按钮看得见、点了报无权限"。
        $isEvAuthor = ((int)$post['user_id'] === (int)Auth::id());
        $isEvMod    = Auth::isModeratorOf((int)$post['category_id']);
        if (!$isEvAuthor && !$isEvMod && !is_admin()) {
            $this->error('仅作者、本版版主及以上可导出报名数据');
        }
        $row = Model::table('topic_event')->where('post_id', $id)->first();
        if (!$row) $this->error('该帖不是活动帖');
        $signups = Model::query('SELECT s.*, u.username, u.nickname FROM topic_event_signups s LEFT JOIN users u ON s.user_id = u.id WHERE s.post_id = ? ORDER BY s.id ASC', [$id]);
        $customFields = json_decode($row['custom_fields'] ?? '[]', true) ?: [];
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="event_signups_' . $id . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        $head = ['用户ID', '用户名', '昵称'];
        foreach ($customFields as $cf) $head[] = $cf['label'];
        $head[] = '报名时间';
        fputcsv($out, $head);
        foreach ($signups as $s) {
            $rowData = [(int)$s['user_id'], $s['username'] ?? '', $s['nickname'] ?? ''];
            $vals = [];
            foreach (json_decode($s['data'] ?? '[]', true) ?: [] as $d) $vals[$d['label']] = $d['value'];
            foreach ($customFields as $cf) $rowData[] = $vals[$cf['label']] ?? '';
            $rowData[] = $s['created_at'];
            fputcsv($out, $rowData);
        }
        fclose($out);
        exit;
    }

    /* ---------- 悬赏主题：采纳 / 提前结束 ---------- */
    public function bountyAccept($id = null)
    {
        $id = (int)($id ?? input('id'));
        $post = Model::table('posts')->where('id', $id)->where('status', 1)->first();
        if (!$post) $this->error('帖子不存在');
        if ((int)$post['user_id'] !== (int)Auth::id() && !is_admin()) $this->error('仅发起者可采纳回答');
        $bounty = Model::table('topic_bounty')->where('post_id', $id)->first();
        if (!$bounty) $this->error('该帖不是悬赏帖');
        if ((int)$bounty['status'] !== 1) $this->error('悬赏已结束，无法采纳');
        // answer_id 与现有 comment_id 复用同一字段（= comments.id）
        // 兼容旧前端：JS 历史上传的是 comment_id，新版已切到 answer_id，任一即可
        $answerId = (int)(input('answer_id', 0) ?: input('comment_id', 0));
        if (!$answerId) $this->error('请指定要采纳的回答');
        // 必须 is_bounty_answer=1（拒绝普通评论被误采纳）
        $comment = Model::query(
            'SELECT c.*, u.username, u.nickname, u.avatar, u.is_certified, u.role, u.show_cert_badges, u.status
             FROM comments c LEFT JOIN users u ON c.user_id = u.id
             WHERE c.id = ? AND c.post_id = ?',
            [$answerId, $id]
        );
        if (!$comment) $this->error('回答不存在');
        // 单一验收入口：从同一评论的 accept_rank 字段反查是否已被采纳（替代以前 topic_bounty_accepted 表校验）
        $comment = $comment[0];
        // 双保险：accept_rank 列主校验 + topic_bounty_accepted 表兜底（老库未升级 accept_rank 列时仍能拦截）
        $rankForCheck = isset($comment['accept_rank']) && $comment['accept_rank'] !== null && $comment['accept_rank'] !== '' ? (int)$comment['accept_rank'] : 0;
        if ($rankForCheck > 0) {
            $this->error('该回答已被采纳');
        }
        if (Model::table('topic_bounty_accepted')->where('comment_id', $answerId)->where('post_id', $id)->first()) {
            $this->error('该回答已被采纳');
        }
        $per = (int)$bounty['per_person_tokens'];
        PointService::grant($comment['user_id'], 'token', $per, 'bounty_reward', $id, '悬赏采纳 #' . $id);
        // 通知回答者：其回答被采纳
        $bountyCurrencyLabel = PointService::currencyLabel('token');
        notify_user((int)$comment['user_id'], 'bounty_accepted', '你在悬赏帖《' . mb_substr($post['title'], 0, 20) . '》中的回答被采纳，获得 ' . $per . ' ' . $bountyCurrencyLabel . ' 奖励', url('post/show', ['id' => $id], true));

        // 采纳次序 = 当前 accepted_count + 1（先采纳的排最前）
        $newRank = (int)$bounty['accepted_count'] + 1;
        Model::table('topic_bounty_accepted')->insert([
            'bounty_id' => $bounty['id'], 'post_id' => $id,
            'comment_id' => $answerId, 'user_id' => (int)$comment['user_id'],
            'tokens' => $per, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        // 同步回 comments.accept_rank ——「采纳置顶」靠这一列排序
        try {
            Model::execute('UPDATE comments SET accept_rank = ? WHERE id = ?', [$newRank, $answerId]);
        } catch (\Throwable $e) {
            // 老库无 accept_rank 列时静默跳过，topic_bounty_accepted 已写入主流程不受影响
        }
        $acceptedCount = $newRank;
        $update = ['accepted_count' => $acceptedCount];
        if ((int)$bounty['people_count'] > 0 && $acceptedCount >= (int)$bounty['people_count']) {
            $update['status'] = 2; // 已达采纳人数 → 公开
        }
        Model::table('topic_bounty')->where('id', $bounty['id'])->update($update);
        $this->success(['accepted_count' => $acceptedCount, 'answer_id' => $answerId, 'accept_rank' => $newRank], '已采纳，积分已发放');
    }

    public function bountyEndEarly($id = null)
    {
        $id = (int)($id ?? input('id'));
        $post = Model::table('posts')->where('id', $id)->where('status', 1)->first();
        if (!$post) $this->error('帖子不存在');
        if ((int)$post['user_id'] !== (int)Auth::id() && !is_admin()) $this->error('仅发起者可提前结束');
        $bounty = Model::table('topic_bounty')->where('post_id', $id)->first();
        if (!$bounty) $this->error('该帖不是悬赏帖');
        if ((int)$bounty['status'] !== 1) $this->error('悬赏已结束');
        $per = (int)$bounty['per_person_tokens'];
        $people = (int)$bounty['people_count'];
        $accepted = (int)$bounty['accepted_count'];
        $total = (int)$bounty['total_tokens'];
        $allocated = $accepted * $per;
        $unused = max(0, $total - $allocated);
        $refund = (int)($unused * 0.5); // 扣 50% 手续费后返还
        if ($refund > 0) {
            PointService::grant($post['user_id'], 'token', $refund, 'bounty_refund', $id, '悬赏提前结束返还（扣除50%手续费）');
        }
        Model::table('topic_bounty')->where('id', $bounty['id'])->update(['status' => 3, 'ended_early' => 1]);
        $this->success(['refund' => $refund, 'unused' => $unused], '已提前结束，剩余质押返还 ' . $refund . ' 积分（扣除50%）');
    }

    /* ---------- 投票主题：投票 ---------- */
    public function pollVote($id = null)
    {
        if (!is_logged_in()) { $this->error('请先登录'); }
        $id = (int)($id ?? input('id'));
        $post = Model::table('posts')->where('id', $id)->where('status', 1)->first();
        if (!$post) $this->error('帖子不存在');
        $poll = Model::table('topic_poll')->where('post_id', $id)->first();
        if (!$poll) $this->error('该帖不是投票帖');
        if ($poll['deadline'] && strtotime($poll['deadline']) <= time()) $this->error('投票已截止');
        if (Model::table('topic_poll_votes')->where('poll_id', $poll['id'])->where('user_id', Auth::id())->first()) $this->error('您已投过票');
        $optionIds = input('options', []);
        if (!is_array($optionIds)) $optionIds = [$optionIds];
        $optionIds = array_filter(array_map('intval', $optionIds), function ($o) { return $o > 0; });
        if (empty($optionIds)) $this->error('请选择选项');
        if (!(int)$poll['multi'] && count($optionIds) > 1) $this->error('该投票为单选');
        $valid = Model::query('SELECT id FROM topic_poll_options WHERE poll_id = ?', [$poll['id']]);
        $validIds = array_map(function ($v) { return (int)$v['id']; }, $valid);
        foreach ($optionIds as $oid) {
            if (!in_array($oid, $validIds, true)) $this->error('非法选项');
        }
        foreach ($optionIds as $oid) {
            Model::table('topic_poll_votes')->insert(['poll_id' => $poll['id'], 'option_id' => $oid, 'user_id' => Auth::id(), 'created_at' => date('Y-m-d H:i:s')]);
            Model::table('topic_poll_options')->where('id', $oid)->update(['votes' => Model::scalar('SELECT votes FROM topic_poll_options WHERE id = ?', [$oid]) + 1]);
        }
        Model::table('topic_poll')->where('id', $poll['id'])->update(['total_votes' => (int)$poll['total_votes'] + count($optionIds)]);
        $this->success(['voted' => true], '投票成功');
    }

    /* ---------- 辩论主题：站队发言 ---------- */
    // 改造（2026-08-31）：去掉「同 post 每用户只能发言一次」的单点限制，改为
    //   - 同用户可针对同一帖多次发言（用于补充论据、被反驳后反驳等）。
    //   - 但首次选定「正方/反方」后不能再切换站队——查询原有发言 side，若不一致则拦截。
    //   - pro_votes / con_votes 仅在用户首次发言时 +1；后续重复发言不再累计 vote。
    //   - 超过辩论截止时间（deadline）后拒绝发言。
    public function debateJoin($id = null)
    {
        if (!is_logged_in()) { $this->error('请先登录'); }
        $id = (int)($id ?? input('id'));
        $post = Model::table('posts')->where('id', $id)->where('status', 1)->first();
        if (!$post) $this->error('帖子不存在');
        $debate = Model::table('topic_debate')->where('post_id', $id)->first();
        if (!$debate) $this->error('该帖不是辩论帖');
        // 截止时间未填 = 旧数据无 deadline 字段，视为未设限（不拦截，老帖不受影响）
        if (!empty($debate['deadline']) && strtotime($debate['deadline']) <= time()) {
            $this->error('辩论已截止，无法再站队发言');
        }
        $side = (int)input('side', 0);
        if (!in_array($side, [1, 2], true)) $this->error('请选择正反方');
        $content = trim(input('content', ''));
        if ($content === '') $this->error('请输入发言内容');
        // 引用（辩论未截止时引用他人站队发言 → 作为一条新站队发言，引用块落库）
        $qId = (int)input('quote_id', 0);
        $qType = trim((string)input('quote_type', ''));
        $qAuthor = trim((string)input('quote_author', ''));
        $qSnippet = trim((string)input('quote_snippet', ''));
        if (!in_array($qType, ['comment', 'statement'], true)) $qType = '';
        if ($qAuthor !== '') $qAuthor = mb_substr($qAuthor, 0, 64);
        if ($qSnippet !== '') $qSnippet = mb_substr($qSnippet, 0, 120);
        // 同站队校验：用户已发过言 side 必须 == 当前 side，否则拦截（不允许切换站队）。
        //   注：用 orderBy id ASC 取首条 = 该用户的「初始站队」侧。
        $mine = Model::table('topic_debate_statements')
            ->where('post_id', $id)->where('user_id', Auth::id())
            ->orderBy('id', 'ASC')->first();
        $isFirst = !$mine;
        if ($mine && (int)$mine['side'] !== $side) {
            $this->error('您已站队 ' . ((int)$mine['side'] === 1 ? '正方' : '反方') . '，不能切换为另一方');
        }
        // 引用字段仅当表已含该列才写入（升级 54 可能尚未执行）；否则忽略，避免整条插入失败。
        static $hasQuoteCols = null;
        if ($hasQuoteCols === null) {
            try {
                $chk = Model::scalar(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'topic_debate_statements' AND COLUMN_NAME = 'quote_id'"
                );
                $hasQuoteCols = !empty($chk);
            } catch (\Throwable $e) { $hasQuoteCols = false; }
        }
        $ins = [
            'post_id' => $id, 'user_id' => Auth::id(),
            'side' => $side, 'content' => $content,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        if ($hasQuoteCols) {
            $ins['quote_id'] = $qId ?: null;
            $ins['quote_type'] = $qType !== '' ? $qType : null;
            $ins['quote_author'] = $qAuthor !== '' ? $qAuthor : null;
            $ins['quote_snippet'] = $qSnippet !== '' ? $qSnippet : null;
        }
        Model::table('topic_debate_statements')->insert($ins);
        // 仅「首次发言」累加 vote（同后续多次发言不再重复 +1，避免侧计数虚高）
        if ($isFirst) {
            if ($side == 1) {
                Model::table('topic_debate')->where('id', $debate['id'])->update(['pro_votes' => (int)$debate['pro_votes'] + 1]);
                $debate['pro_votes'] = (int)$debate['pro_votes'] + 1;
            } else {
                Model::table('topic_debate')->where('id', $debate['id'])->update(['con_votes' => (int)$debate['con_votes'] + 1]);
                $debate['con_votes'] = (int)$debate['con_votes'] + 1;
            }
        }
        $this->success(['side' => $side], '已发表立场');
    }

    /* ---------- 采访主题：提问（兼容记者/读者） ---------- */
    // 2026-09-02 改造：
    //   · 记者（reporter_id）总是可提问（与 open_question 无关）
    //   · 其他登录用户仅当 open_question=1 时可提问
    //   · 已结束采访（ended_at 非空）禁止提问
    //   · asker_role 按身份写入（'记者' / '读者'），用于前台展示
    public function interviewAddQa($id = null)
    {
        if (!is_logged_in()) { $this->error('请先登录'); }
        $id = (int)($id ?? input('id'));
        $post = Model::table('posts')->where('id', $id)->where('status', 1)->first();
        if (!$post) $this->error('帖子不存在');
        $interview = Model::table('topic_interview')->where('post_id', $id)->first();
        if (!$interview) $this->error('该帖不是采访帖');
        if (!empty($interview['ended_at'])) $this->error('该采访已结束，不再接收提问');
        $uid = (int)Auth::id();
        $reporterId    = (int)($interview['reporter_id'] ?? 0) ?: (int)$post['user_id'];
        $intervieweeId = (int)($interview['interviewee_user_id'] ?? 0);
        $isReporter    = ($uid === $reporterId);
        $isInterviewee = ($intervieweeId > 0 && $uid === $intervieweeId);
        // 记者总是可问；受访者仅当 allow_interviewee_ask=1 时可向记者提问；其他用户仅 open_question=1 时可问
        if ($isInterviewee && empty($interview['allow_interviewee_ask'])) {
            $this->error('该采访未开启"允许受访者提问"');
        }
        if (!$isReporter && !$isInterviewee && empty($interview['open_question'])) {
            $this->error('该采访未开放读者提问');
        }
        $question = trim((string)input('question', ''));
        if ($question === '') $this->error('请输入问题');
        if (mb_strlen($question, 'UTF-8') > 500) $this->error('问题过长（最多 500 字）');
        $maxSort = (int)Model::scalar('SELECT MAX(sort) FROM topic_interview_qa WHERE interview_id = ?', [$interview['id']]);
        // 三种角色：记者 / 受访者 / 读者，前台按身份分色
        $askerRole = $isReporter ? '记者' : ($isInterviewee ? '受访者' : '读者');
        $newId = Model::table('topic_interview_qa')->insert([
            'interview_id' => $interview['id'], 'question' => $question, 'answer' => '',
            'asker_id' => $uid, 'answerer_id' => $intervieweeId > 0 ? $intervieweeId : null,
            'asker_role' => $askerRole,
            'sort' => $maxSort + 1, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->success(['added' => true, 'id' => $newId], '提问已提交，等待回答');
    }

    /* ---------- 采访主题：回答问题（受访者 / 记者，根据提问者身份决定谁能答） ---------- */
    // 权限矩阵与前端 can_answer 完全对齐：
    //   - 受访者提的问题（反向追问）：仅记者可答
    //   - 其他（读者/记者）提的问题：仅受访者可答
    // 读者一律既不能答也不能收答。前端按 is_reporter/is_interviewee + asker_role 联动，服务端二次兜底防绕过。
    public function interviewAnswer($id = null)
    {
        if (!is_logged_in()) { $this->error('请先登录'); }
        $id = (int)($id ?? input('id'));
        $post = Model::table('posts')->where('id', $id)->where('status', 1)->first();
        if (!$post) $this->error('帖子不存在');
        $interview = Model::table('topic_interview')->where('post_id', $id)->first();
        if (!$interview) $this->error('该帖不是采访帖');
        if (!empty($interview['ended_at'])) $this->error('该采访已结束，不可回答');
        $uid = (int)Auth::id();
        $reporterId    = (int)($interview['reporter_id'] ?? 0) ?: (int)$post['user_id'];
        $intervieweeId = (int)($interview['interviewee_user_id'] ?? 0);
        if ($intervieweeId <= 0) $this->error('采访数据异常：缺少受访者');
        $qaId = (int)input('qa_id', 0);
        $answer = trim((string)input('answer', ''));
        if ($qaId <= 0) $this->error('参数错误：缺少问答 id');
        if ($answer === '') $this->error('请输入回答内容');
        if (mb_strlen($answer, 'UTF-8') > 2000) $this->error('回答过长（最多 2000 字）');
        $qa = Model::table('topic_interview_qa')->where('id', $qaId)->where('interview_id', $interview['id'])->first();
        if (!$qa) $this->error('问答不存在');
        if (trim((string)($qa['answer'] ?? '')) !== '') $this->error('该问题已被回答，不能重复作答');
        // 按提问者身份校验答题人：服务端兜底，防前端按钮被绕过 / 直接调接口
        $askerId = (int)($qa['asker_id'] ?? 0);
        $askerIsInterviewee = ($intervieweeId > 0 && $askerId === $intervieweeId);
        $isUserReporter     = ($reporterId > 0 && $uid === $reporterId);
        $isUserInterviewee  = ($uid === $intervieweeId);
        $canAnswer = $askerIsInterviewee ? $isUserReporter : $isUserInterviewee;
        if (!$canAnswer) {
            // 文案按当前身份选最贴近的一句
            if ($askerIsInterviewee) $this->error('该问题是受访者提问，仅记者可以回答');
            $this->error('该问题仅受访者可以回答');
        }
        Model::table('topic_interview_qa')->where('id', $qaId)->update([
            'answer' => $answer,
            'answerer_id' => $uid,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        // 通知提问者（跳过自答自问）。文案：答题者身份 = 受访者/记者 → 「受访者 / 记者」区分清楚，避免张冠李戴
        $postLink = url('post/show', ['id' => $id], true);
        $postTitle = mb_substr((string)($post['title'] ?? ''), 0, 20, 'UTF-8');
        $answererLabel = $isUserReporter ? '记者' : '受访者';
        if ($askerId > 0 && $askerId !== $uid) {
            notify_user($askerId, 'interview_answered',
                '你的提问《' . mb_substr((string)($qa['question'] ?? ''), 0, 20, 'UTF-8') . '》已被《' . $postTitle . '》的' . $answererLabel . '回答',
                $postLink);
        }
        $this->success(['answered' => true, 'qa_id' => $qaId], '回答已提交');
    }

    /* ---------- 采访主题：结束采访（仅作者） ---------- */
    // 结束采访 → ended_at 写当前时间，前台隐藏提问/回答入口（但保留问答列表展示 + 评论区不受限）。
    public function interviewEnd($id = null)
    {
        if (!is_logged_in()) { $this->error('请先登录'); }
        $id = (int)($id ?? input('id'));
        $post = Model::table('posts')->where('id', $id)->where('status', 1)->first();
        if (!$post) $this->error('帖子不存在');
        $interview = Model::table('topic_interview')->where('post_id', $id)->first();
        if (!$interview) $this->error('该帖不是采访帖');
        $uid = (int)Auth::id();
        $isOwner    = ((int)$post['user_id'] === $uid);
        $isMod      = Auth::isModeratorOf((int)$post['category_id']);
        $isAdmin    = is_admin();
        // 记者也可以结束采访（reporter_id > 0 才算，老数据 NULL/fallback=$authorId 时避免把发帖人再加一次）。
        $reporterId = (int)($interview['reporter_id'] ?? 0) ?: (int)$post['user_id'];
        $isReporter = ($reporterId > 0 && $reporterId === $uid);
        if (!$isOwner && !$isMod && !$isAdmin && !$isReporter) {
            $this->error('仅作者 / 记者 / 版主 / 管理员可结束采访');
        }
        if (!empty($interview['ended_at'])) {
            $this->success(['ended' => true, 'ended_at' => $interview['ended_at']], '采访已处于结束状态');
        }
        $now = date('Y-m-d H:i:s');
        Model::table('topic_interview')->where('id', $interview['id'])->update(['ended_at' => $now]);
        $this->success(['ended' => true, 'ended_at' => $now], '采访已结束');
    }

    /**
     * 点赞 / 取消点赞
     */
    public function like($id = null)
    {
        $id = (int)($id ?? input('id'));
        $post = Model::table('posts')->where('id', $id)->where('status', 1)->first();
        if (!$post) $this->error('帖子不存在');

        $existing = Model::table('interactions')
            ->where('user_id', Auth::id())
            ->where('target_type', 'post')
            ->where('target_id', $id)
            ->where('action_type', 'like')
            ->first();

        if ($existing) {
            Model::table('interactions')->where('id', $existing['id'])->delete();
            Model::execute('UPDATE posts SET like_count = GREATEST(like_count - 1, 0) WHERE id = ?', [$id]);
            $liked = false;
            $msg = '已取消点赞';
        } else {
            Model::table('interactions')->insert([
                'user_id' => Auth::id(),
                'target_type' => 'post',
                'target_id' => $id,
                'action_type' => 'like',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            Model::execute('UPDATE posts SET like_count = like_count + 1 WHERE id = ?', [$id]);
            $liked = true;
            $msg = '点赞成功';
            // 通知作者（content 前缀统一走 user_display_name，跟 _comment.php 显示规则一致：
            //   已注销 → 已注销用户；填了昵称 → 昵称；否则 → 用户名。
            //   不再用 Auth::user()['nickname'] 直接拼，否则没填昵称的用户发送通知时
            //   content 会出现「 评论了你的帖子」这种空前缀）
            if ($post['user_id'] != Auth::id()) {
                $actorName = user_display_name(Auth::user());
                // 跳帖子首屏；用绝对 URL（permalink 切换下相对路径可能被原页 hash 复用导致不跳）
                $this->notify($post['user_id'], 'like', $actorName . ' 赞了你的帖子《' . $post['title'] . '》', url('post/show', ['id' => $id], true));
                // 积分：帖子被点赞，作者获取 Token + Byte（幂等，source_id = 帖子 id）
                PointService::earnAction($post['user_id'], 'post_liked', $id, '帖子被点赞');
            }
        }

        $updated = Model::table('posts')->select('like_count')->where('id', $id)->first();
        $this->success(['liked' => $liked, 'like_count' => $updated['like_count']], $msg);
    }

    /**
     * 收藏 / 取消收藏
     */
    public function collect($id = null)
    {
        $id = (int)($id ?? input('id'));
        $post = Model::table('posts')->where('id', $id)->where('status', 1)->first();
        if (!$post) $this->error('帖子不存在');

        $existing = Model::table('interactions')
            ->where('user_id', Auth::id())
            ->where('target_type', 'post')
            ->where('target_id', $id)
            ->where('action_type', 'collect')
            ->first();

        if ($existing) {
            Model::table('interactions')->where('id', $existing['id'])->delete();
            Model::execute('UPDATE posts SET collect_count = GREATEST(collect_count - 1, 0) WHERE id = ?', [$id]);
            $collected = false;
            $msg = '已取消收藏';
        } else {
            Model::table('interactions')->insert([
                'user_id' => Auth::id(),
                'target_type' => 'post',
                'target_id' => $id,
                'action_type' => 'collect',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            Model::execute('UPDATE posts SET collect_count = collect_count + 1 WHERE id = ?', [$id]);
            $collected = true;
            $msg = '收藏成功';
            // 通知帖子作者：有人收藏了 TA 的帖子（自己收藏自己不通知）
            if ((int)$post['user_id'] !== (int)Auth::id()) {
                $actorName = user_display_name(Auth::user());
                notify_user($post['user_id'], 'favorite', $actorName . ' 收藏了你的帖子《' . mb_substr($post['title'], 0, 20) . '》', url('post/show', ['id' => $id], true));
            }
        }

        $updated = Model::table('posts')->select('collect_count')->where('id', $id)->first();
        $this->success(['collected' => $collected, 'collect_count' => $updated['collect_count']], $msg);
    }

    /**
     * 发表评论
     */
    public function comment($id = null)
    {
        abortIfBanned();
        if (!can('comment.create')) $this->error('无权限：未授予「发布评论」');
        // 滑块验证码校验（后台「系统管理 → 验证码」开启评论场景时生效；顶层回复与楼中楼均受控）
        list($capOk, $capMsg) = captcha_guard('comment');
        if (!$capOk) { $this->error($capMsg); }
        $id = (int)($id ?? input('id'));
        $post = Model::table('posts')->where('id', $id)->where('status', 1)->first();
        if (!$post) $this->error('帖子不存在');
        if (!empty($post['is_closed'])) $this->error('该帖子已关闭，无法回复');

        // 是否为该板块版主（用于渲染新评论时是否显示「删除」按钮）
        $isModerator = Auth::isModeratorOf($post['category_id']);

        $content = trim(input('content'));
        $parentId = (int)input('parent_id', 0);

        // 引用 / 父类型：站队发言不再支持楼中楼回复，parent_type 恒为 comment
        //   （引用站队发言也作为顶层评论落库）；quote_* 仅作展示：落库「作者名 + 内容摘要」，渲染时小字 + 可点击定位
        $parentType = 'comment';
        $quoteId = (int)input('quote_id', 0);
        $quoteType = input('quote_type', '');
        if (!in_array($quoteType, ['comment', 'statement'], true)) $quoteType = '';
        $quoteAuthor = trim((string)input('quote_author', ''));
        $quoteSnippet = trim((string)input('quote_snippet', ''));
        if ($quoteSnippet !== '') $quoteSnippet = mb_substr($quoteSnippet, 0, 120, 'UTF-8');
        if ($quoteId <= 0 || $quoteType === '') { $quoteId = 0; $quoteType = ''; $quoteAuthor = ''; $quoteSnippet = ''; }

        // ===== 悬赏主题路由分流（2026-08-31 新增）=====
        // 悬赏期 status=1 时，整帖处于「仅作者与回答者可见内容征集期」：
        //   · 禁止楼中楼（parentId 有值即拒绝）
        //   · 顶层评论强制标记 is_bounty_answer=1，等同「提交回答」
        // 非悬赏期或 status=2/3（已解决/已关闭）：恢复普通评论 + 楼中楼
        $specialType = '';
        $bountyStatus = 0;
        try {
            $spForRouting = $this->loadSpecial($post);
            $specialType = (string)($spForRouting['type'] ?? '');
            if ($specialType === 'bounty' && !empty($spForRouting['bounty'])) {
                $bountyStatus = (int)$spForRouting['bounty']['status'];
            }
        } catch (Throwable $e) { /* 路由分析失败不动后续流程 */ }
        $isBountyInProgress = ($specialType === 'bounty' && $bountyStatus === 1);
        if ($isBountyInProgress) {
            if ($parentId > 0) {
                $this->error('悬赏期间禁止楼中楼回复，悬赏结束后开放');
            }
            $parentId = 0; // 二次强制覆盖，防前端传入伪造
        }

        // 内容与图片至少有其一：顶层回复允许只发图不写字
        if ($content === '' && empty(input('images')) && empty(input('image'))) {
            $this->error('请输入评论内容或添加图片');
        }

        // 评论图片：仅顶层回复（parent_id 为空）允许，楼中楼（parent_id 有值）一律忽略。
        // 前端仅在顶层回复框提供图片上传，这里再做一次服务端兜底校验，杜绝伪造请求带图。
        // 接受两种入参形式：
        //   - images: JSON 数组（preferred，多图）
        //   - image : 单图相对路径（兼容老调用）
        $rawImages = input('images', null);
        $imageArr = [];
        if ($parentId) {
            // 楼中楼：禁止带图
            $imageArr = [];
        } else {
            if (is_array($rawImages)) {
                $candidates = $rawImages;
            } else {
                $rawStr = (string)($rawImages !== null && $rawImages !== '' ? $rawImages : input('image', ''));
                if ($rawStr !== '') {
                    $decoded = json_decode($rawStr, true);
                    if (is_array($decoded)) {
                        $candidates = $decoded;
                    } else {
                        // 兼容老的单个字符串（按 \n 分隔）
                        $candidates = preg_split('/\r?\n/', $rawStr);
                    }
                } else {
                    $candidates = [];
                }
            }
            foreach ($candidates as $p) {
                if (!is_string($p)) continue;
                $p = trim($p);
                if ($p === '') continue;
                // 仅接受站内 uploads 相对路径，防目录穿越/外链注入
                if (strpos($p, 'uploads/') !== 0) continue;
                if (!preg_match('#^uploads/[A-Za-z0-9_./\-]+$#', $p)) continue;
                $imageArr[] = $p;
                if (count($imageArr) >= 9) break; // 上限 9 张
            }
        }
        $imageJson = !empty($imageArr) ? json_encode(array_values($imageArr), JSON_UNESCAPED_SLASHES) : null;

        // 高危敏感词硬拦截（仅对文字内容生效，纯图帖可不写文字）
        if ($content !== '' && has_high_risk_sensitive($content)) {
            $this->error('评论包含高危敏感词，无法发布');
        }

        $now = date('Y-m-d H:i:s');
        $commentId = Model::table('comments')->insert([
            'post_id' => $id,
            'user_id' => Auth::id(),
            'parent_id' => $parentId ?: null,
            'parent_type' => $parentType,
            'content' => $content !== '' ? filter_sensitive($content) : '',
            'image' => $imageJson,
            'like_count' => 0,
            'is_hidden' => 0,
            'status' => 1,
            // 悬赏期间：所有顶层评论强制标 is_bounty_answer=1（=「提交回答」）
            // 非悬赏期或悬赏结束后：0（=普通评论）
            'is_bounty_answer' => $isBountyInProgress ? 1 : 0,
            'quote_id' => $quoteId ?: null,
            'quote_type' => $quoteType !== '' ? $quoteType : null,
            'quote_author' => $quoteAuthor !== '' ? $quoteAuthor : null,
            'quote_snippet' => $quoteSnippet !== '' ? $quoteSnippet : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Model::execute('UPDATE posts SET comment_count = comment_count + 1 WHERE id = ?', [$id]);

        // 积分：顶层评论 → comment_create；楼中楼回复 → reply_create（Token + Byte，幂等）
        $earnSource = $parentId ? 'reply_create' : 'comment_create';
        $earnedPoints = PointService::earnAction(Auth::id(), $earnSource, $commentId, $parentId ? '楼中楼回复' : '发表评论');

        // 触发表：本次新回复（含楼中楼）的时间，作为帖子排序的"最近活跃时间"。
        // 排序逻辑：置顶优先 + 最近活跃时间降序（last_reply_at DESC），
        // 这样首页/板块页未置顶的帖子会在有新回复时自动顶到非置顶区的顶部（置顶贴下方）。
        try {
            Model::execute('UPDATE posts SET last_reply_at = ? WHERE id = ?', [$now, $id]);
        } catch (\Throwable $e) {
            // 字段缺失（极老库未跑过 upgrade.php），降级用 updated_at 兜底
            try { Model::execute('UPDATE posts SET updated_at = ? WHERE id = ?', [$now, $id]); } catch (\Throwable $e2) {}
        }

        // 通知
        if ($parentId) {
            if ($parentType === 'statement') {
                // 回复「站队发言」：通知该站队发言作者（topic_debate_statements 表）
                $stmt = Model::table('topic_debate_statements')->where('id', $parentId)->first();
                if ($stmt && (int)$stmt['user_id'] !== Auth::id()) {
                    $actorName = user_display_name(Auth::user());
                    $this->notify($stmt['user_id'], 'reply', $actorName . ' 回复了你的站队发言', comment_target_url($id, $commentId));
                }
            } else {
                $parent = Model::table('comments')->where('id', $parentId)->first();
                if ($parent && $parent['user_id'] != Auth::id()) {
                    // 通知前缀走 display name，跟 _comment.php 显示规则一致
                    $actorName = user_display_name(Auth::user());
                    // 用 helper 生成「绝对 URL + 主评论页码 + #comment-N」——
                    // 直接拼 url(...)+'#hash' 在 permalink 启用 + 跨页场景下会丢定位。
                    $this->notify($parent['user_id'], 'reply', $actorName . ' 回复了你的评论', comment_target_url($id, $commentId));
                }
            }
        } elseif ($post['user_id'] != Auth::id()) {
            $actorName = user_display_name(Auth::user());
            $this->notify($post['user_id'], 'comment', $actorName . ' 评论了你的帖子《' . $post['title'] . '》', comment_target_url($id, $commentId));
        }

        // @提及通知（评论/楼中楼均触发），定位到该条评论锚点
        // 同样改走 display name，避免空前缀
        $meName = user_display_name(Auth::user());
        foreach (extract_mention_user_ids($content, Auth::id()) as $muid) {
            notify_user($muid, 'mention', $meName . ' 在评论中提到了你', comment_target_url($id, $commentId));
        }

        $user = Auth::user();
        // 多图 preview：返回绝对 URL 列表（前端 <img src> 可直接用）
        $imagesOut = [];
        foreach ($imageArr as $p) {
            $imagesOut[] = ['url' => upload_url($p)];
        }

        // 给前端一段已渲染好的 HTML 片段：用户点「回复」提交后，
        //   客户端直接把它 prepend 到对应 top-level 评论的 children 区，**不刷新页面**也能看到新评论。
        //   由于 buildCommentTree() 是 2 级扁平化逻辑（任意深度都最终归到顶层 children），
        //   这里同时返回 root_id——告诉前端「这条新评论最终会被插在哪个顶级评论之下」。
        $newCommentRow = Model::query(
            'SELECT c.*, u.username, u.nickname, u.avatar, u.is_certified, u.role, u.show_cert_badges, u.status
             FROM comments c LEFT JOIN users u ON c.user_id = u.id WHERE c.id = ? LIMIT 1',
            [$commentId]
        );
        $newCommentRow = $newCommentRow[0] ?? null;
        $renderedHtml = '';
        $rootId = 0;
        if ($newCommentRow) {
            $newCommentRow['image'] = $imageJson; // 顶层回复的图片；楼中楼插入此处也只为 null
            $newCommentRow['created_at'] = $now;  // 渲染 time_ago 用
            ob_start();
            View::partial('post/_comment', [
                'comment'     => $newCommentRow,
                'post'        => $post,
                'isModerator' => $isModerator,
                'isChild'     => true,
            ]);
            $renderedHtml = ob_get_clean();

            // 算 root_id / root_type：parent_id=0 自己就是 root（comment）；
            //   否则沿 parent 链走到最顶层祖先。若顶层祖先不在 comments 表（即指向 topic_debate_statements），
            //   说明这是「回复站队发言」的楼中楼，root_type='statement'，锚点为 statement-<rootId>。
            $rootId = (int)($newCommentRow['parent_id'] ?? 0);
            $rootType = 'comment';
            if ($rootId) {
                $cur = $rootId; $guard = 0;
                while ($cur && $guard < 50) {
                    $row = Model::query('SELECT id, parent_id FROM comments WHERE id = ? LIMIT 1', [$cur]);
                    $row = $row[0] ?? null;
                    if (!$row || empty($row['parent_id'])) break;
                    $cur = (int)$row['parent_id'];
                    $rootId = $cur;
                    $guard++;
                }
                // 顶层祖先若不是 comments 表中的评论（而是站队发言 statement），归到 statement 容器下
                $isStmtRoot = (int)Model::scalar('SELECT COUNT(*) FROM comments WHERE id = ?', [$rootId]) === 0;
                if ($isStmtRoot) $rootType = 'statement';
            }
        }

        // 这里的 display name 也走 user_display_name，与 _comment.php 渲染一致
        $actorDisplayName = user_display_name($user);

        // 计算新顶层评论「落在第几页主评论页」：顶层评论按 id 升序，新评论 id 最大必然落最后一页。
        // 前端拿到后直接 location 跳到该页 + 锚定到新评论，实现「发表后定位到自己刚发的位置」。
        // 楼中楼回复不增加顶层评论数，top_page 对它无意义（前端走立即插入逻辑）。
        $newTopTotal = (int)Model::scalar(
            'SELECT COUNT(*) FROM comments WHERE post_id = ? AND status = 1 AND (parent_id IS NULL OR parent_id = 0)',
            [$id]
        );
        $topPage = max(1, (int)ceil($newTopTotal / COMMENT_PER_PAGE));

        $this->success([
            'points' => $earnedPoints,
            'comment' => [
                'id' => $commentId,
                'parent_id' => $parentId ?: null,
                'content' => e($content),
                'image' => !empty($imagesOut) ? upload_url($imagesOut[0]['url']) : '', // 兼容老端
                'images' => $imagesOut,
                'created_at' => time_ago($now),
                'user' => [
                    'id' => $user['id'],
                    // 改成走 display name（已注销 → 已注销用户；昵称非空 → 昵称；否则 → 用户名）
                    'nickname' => $actorDisplayName,
                    // avatar 必须经 upload_url() 转成 /public/uploads/... 前端 <img src> 才能拿到正确资源
                    'avatar' => upload_url($user['avatar']),
                    'is_certified' => $user['is_certified'],
                ],
            ],
            // 给前端的渲染好的 HTML 片段（已 escape、avatar 已转绝对 URL）
            'html' => $renderedHtml,
            // 新评论最终会归到哪个 top-level 评论下（楼中楼或孙级都让前端去 root 那一级插）
            'root_id' => $rootId,
            // root_type：comment=挂在普通评论下（锚点 comment-<rootId>）；statement=挂在站队发言下（锚点 statement-<rootId>）
            'root_type' => $rootType,
            // 新顶层评论将落在的主评论页码（用于发表后跳转定位）；楼中楼回复此值为其 root 所在页
            'top_page' => $topPage,
        ], '评论成功');
    }

    /**
     * 删除评论
     */
    public function deleteComment($id = null)
    {
        $id = (int)($id ?? input('id'));
        $comment = Model::table('comments')->where('id', $id)->first();
        if (!$comment) $this->error('评论不存在');

        $post = Model::table('posts')->where('id', $comment['post_id'])->first();
        $isOwner = ((int)$comment['user_id'] === (int)Auth::id());
        $isMod   = ($post && Auth::isModeratorOf((int)$post['category_id']));
        $isAdmin = is_admin();
        if ($isOwner) {
            if (!can('comment.delete_own')) $this->error('无权限：未授予「删除自己的评论」');
        } elseif ($isMod || $isAdmin) {
            if (!can('comment.delete_section')) $this->error('无权限：未授予「本版删除评论」');
        } else {
            $this->error('无权删除此评论');
        }

        Model::table('comments')->where('id', $id)->update(['status' => 0, 'updated_at' => date('Y-m-d H:i:s'), 'deleted_at' => date('Y-m-d H:i:s'), 'deleted_by' => Auth::id()]);
        Model::execute('UPDATE posts SET comment_count = GREATEST(comment_count - 1, 0) WHERE id = ?', [$comment['post_id']]);

        // 删评回溯：仅回收该评论产生的积分（token + byte 一起退）
        //  - 不调用 refundPostPoints() 以避免误退帖子作者和其他评论的积分
        \PointService::refundBySource('comment_create', $id);
        \PointService::refundBySource('reply_create', $id);
        \PointService::refundBySource('post_liked', $id);

        $this->success(null, '删除成功');
    }

    /**
     * 打赏（帖底「赏」按钮）
     * 打赏者花 Token，受赏者（帖子/评论作者）等额入账，双向流水同一事务。
     */
    public function reward($id = null)
    {
        abortIfBanned();
        if (!is_logged_in()) $this->error('请先登录');
        $postId = (int)(input('post_id') ?? $id);
        $commentId = (int)input('comment_id', 0);
        $amount = (int)input('amount', 0);
        $post = Model::table('posts')->where('id', $postId)->where('status', 1)->first();
        if (!$post) $this->error('帖子不存在');
        $toUid = (int)$post['user_id'];
        if ($commentId) {
            $c = Model::table('comments')->where('id', $commentId)->where('status', '>=', 0)->first();
            if ($c) $toUid = (int)$c['user_id'];
        }
        $res = PointService::reward(Auth::id(), $toUid, $postId, $commentId, $amount);
        if (!$res['ok']) {
            $map = [
                'min' => '打赏金额不得低于 5 Token',
                'insufficient' => 'Token 余额不足',
                'self' => '不能打赏自己',
                'no_user' => '用户不存在',
                'no_receiver' => '接收方不存在',
                'max_amount' => '单笔打赏金额超过后台设置的上限',
                'daily_limit' => '已达今日打赏次数上限',
                'too_fast' => '两次打赏间隔过短，请稍后再试（剩余 ' . ($res['wait'] ?? 0) . ' 秒）',
            ];
            if (isset($map[$res['reason']])) {
                $this->error($map[$res['reason']]);
            }
            // reason='db_error' 或其他未知 → 仅记录日志，前台给统一中文，绝不透传 PDO 原文
            $detail = $res['detail'] ?? ($res['reason'] ?? '未知错误');
            error_log('[reward] uid=' . Auth::id() . ' to_uid=' . $toUid . ' post=' . $postId . ' reason=' . ($res['reason'] ?? '') . ' detail=' . $detail);
            $this->error('打赏失败，请稍后再试');
        }
        // 受赏者系统通知：在《帖子》被（谁）打赏了（多少）（什么币种）
        $currencyLabel = PointService::currencyLabel('token');
        $fromName = user_display_name(Auth::user());
        $title = mb_substr(($post['title'] ?? '未知帖子'), 0, 20);
        $content = $fromName . ' 在帖子《' . $title . '》打赏了你 ' . $res['amount'] . ' ' . $currencyLabel;
        notify_user($toUid, 'reward', $content, url('post/show', ['id' => $postId], true));
        $this->success(['balance' => $res['balance']], '打赏成功，已送出 ' . $res['amount'] . ' ' . $currencyLabel);
    }

    /**
     * 自助置顶（用户花 Token 按时长购买，独立于管理员 pin_scope）
     */
    public function selfPin($id = null)
    {
        abortIfBanned();
        if (!is_logged_in()) $this->error('请先登录');
        $postId = (int)(input('post_id') ?? $id);
        $hours = (int)input('hours', 1);
        $post = Model::table('posts')->where('id', $postId)->where('status', 1)->first();
        if (!$post) $this->error('帖子不存在');
        if ((int)$post['user_id'] !== (int)Auth::id()) $this->error('只能对自己的帖子自助置顶');
        if (!Auth::can('post.self_pin')) $this->error('无权限：未授予「自助置顶」');
        $res = PointService::selfPin($postId, Auth::id(), $hours);
        if (!$res['ok']) {
            $map = [
                'hours' => '时长需为 1-72 小时',
                'insufficient' => 'Token 余额不足',
                'no_rule' => '未配置自助置顶费率',
            ];
            $this->error($map[$res['reason']] ?? '自助置顶失败');
        }
        $this->success(
            ['expire_at' => $res['expire_at'], 'cost' => $res['cost']],
            '已自助置顶至 ' . date('Y-m-d H:i', strtotime($res['expire_at']))
        );
    }

    /**
     * 置顶（支持本版 / 全局）
     * 前端通过 POST 参数 scope 区分：1 = 本版置顶，2 = 全局置顶
     */
    public function pin($id = null)
    {
        $scope = (int)input('scope', 1);
        $this->setPin($id, $scope);
    }
    public function unpin($id = null)
    {
        $this->setPin($id, 0);
    }
    protected function setPin($id, $scope)
    {
        $id = (int)($id ?? input('id'));
        $scope = (int)$scope;
        if (!in_array($scope, [0, 1, 2], true)) $scope = 0;
        $post = Model::table('posts')->where('id', $id)->first();
        if (!$post) $this->error('帖子不存在');
        $cat = (int)$post['category_id'];

        if ($scope === 2) {
            // 全局置顶：需「全局置顶」权限（后台可授予任意角色，含 admin）
            if (!can('post.pin_global')) $this->error('无权限：未授予「全局置顶」');
        } elseif ($scope === 1) {
            // 本版置顶：需本版版主 + 「本版置顶」权限
            if (!Auth::isModeratorOf($cat)) $this->error('无权操作：需本版版主');
            if (!can('post.pin_section')) $this->error('无权限：未授予「本版置顶」');
        } else {
            // 取消置顶：沿用原置顶范围对应的权限
            if ((int)$post['pin_scope'] === 2) {
                if (!can('post.pin_global')) $this->error('无权限：取消全局置顶需「全局置顶」权限');
            } else {
                if (!Auth::isModeratorOf($cat)) $this->error('无权操作：需本版版主');
                if (!can('post.pin_section')) $this->error('无权限：未授予「本版置顶」');
            }
        }

        Model::table('posts')->where('id', $id)->update([
            'pin_scope' => $scope,
            'is_pinned' => $scope > 0 ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $msg = $scope === 0 ? '已取消置顶' : ($scope === 2 ? '已全局置顶' : '已本版置顶');
        $this->success(['pin_scope' => $scope], $msg);
    }

    /**
     * 加精
     */
    public function essence($id = null)
    {
        $this->toggleEssence($id, 1);
    }
    public function unessence($id = null)
    {
        $this->toggleEssence($id, 0);
    }
    protected function toggleEssence($id, $val)
    {
        $id = (int)($id ?? input('id'));
        $post = Model::table('posts')->where('id', $id)->first();
        if (!$post) $this->error('帖子不存在');
        if (!Auth::isModeratorOf((int)$post['category_id']) && !is_admin()) $this->error('无权操作');
        if (!can('post.essence_section')) $this->error('无权限：未授予「本版加精」');
        Model::table('posts')->where('id', $id)->update(['is_essence' => $val, 'updated_at' => date('Y-m-d H:i:s')]);
        $this->success(null, $val ? '已加精' : '已取消加精');
    }

    /**
     * 移动帖子
     */
    public function move($id = null)
    {
        $id = (int)($id ?? input('id'));
        $targetCat = (int)input('category_id');
        $post = Model::table('posts')->where('id', $id)->first();
        if (!$post) $this->error('帖子不存在');
        if (!Auth::isModeratorOf((int)$post['category_id']) && !is_admin()) $this->error('无权操作');
        if (!can('post.move_section')) $this->error('无权限：未授予「本版移动」');
        $cat = Model::table('categories')->where('id', $targetCat)->first();
        if (!$cat) $this->error('目标板块不存在');
        // 「本版置顶」是板块级属性：跨板块移动后失去意义，自动取消；「全局置顶」不受板块影响，保留。
        $update = ['category_id' => $targetCat, 'updated_at' => date('Y-m-d H:i:s')];
        if ((int)$post['pin_scope'] === 1) {
            $update['pin_scope'] = 0;
            $update['is_pinned'] = 0;
        }
        Model::table('posts')->where('id', $id)->update($update);
        Model::execute('UPDATE categories SET post_count = GREATEST(post_count - 1, 0) WHERE id = ?', [$post['category_id']]);
        Model::execute('UPDATE categories SET post_count = post_count + 1 WHERE id = ?', [$targetCat]);
        $this->success(null, '已移动到 ' . $cat['name']);
    }

    /**
     * 发送通知（辅助）
     */
    protected function notify($userId, $type, $content, $link)
    {
        Model::table('notifications')->insert([
            'user_id' => $userId,
            'type' => $type,
            'content' => $content,
            'link' => $link,
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 解析前端传入的附件 JSON，过滤为安全结构，最多 10 个
     * 输入可为 JSON 字符串或数组
     */
    protected function parseAttachments($input)
    {
        if (empty($input)) return [];
        $decoded = is_array($input) ? $input : json_decode($input, true);
        if (!is_array($decoded)) return [];
        $list = [];
        foreach (array_slice($decoded, 0, 10) as $item) {
            if (!is_array($item) || empty($item['url'])) continue;
            $list[] = [
                'name' => isset($item['name']) ? mb_substr((string)$item['name'], 0, 200) : basename($item['url']),
                'url' => (string)$item['url'],
                'size' => isset($item['size']) ? (int)$item['size'] : 0,
                'reply_visible' => !empty($item['reply_visible']) ? 1 : 0,
            ];
        }
        return $list;
    }

    /**
     * 解析前端提交的图片数组（支持旧格式纯字符串 URL 与新格式 {url, reply_visible}）
     * 返回 [{url, reply_visible}]，最多 9 张
     */
    protected function parseImages($input)
    {
        if (empty($input)) return [];
        $list = [];
        foreach (array_slice((array)$input, 0, 9) as $item) {
            if (is_string($item)) {
                if ($item !== '') $list[] = ['url' => $item, 'reply_visible' => 0];
            } elseif (is_array($item) && !empty($item['url'])) {
                $list[] = [
                    'url' => (string)$item['url'],
                    'reply_visible' => !empty($item['reply_visible']) ? 1 : 0,
                ];
            }
        }
        return $list;
    }
}
