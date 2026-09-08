<?php
/**
 * 首页控制器
 */
class HomeController extends Controller
{
    /** 首页显示完整页脚（其他内页 controller 默认 $hideFooter = true 不渲染） */
    protected $hideFooter = false;

    protected $middleware = [
        ['middleware' => 'auth', 'only' => ['report']],
        ['middleware' => 'csrf', 'only' => ['report']],
    ];

    /**
     * 首页 - 帖子列表
     */
    public function index()
    {
        $catRaw = input('cat', 0);
        $cat = is_numeric($catRaw) ? (int)$catRaw : 0;
        $sort = input('sort', 'new'); // new/hot/essence
        $page = (int)input('page', 1);
        // 首页（全部帖子）每页 100 条；板块内（含认证专区）每页 50 条
        $isBoard = ($catRaw === 'certified') || $cat > 0;
        $perPage = $isBoard ? 50 : 100;

        $builder = Model::table('posts')->where('status', 1);

        // 板块筛选
        if ($catRaw === 'certified') {
            // 认证专区：筛选所有需认证的板块；同时纳入「全局置顶」帖子（pin_scope=2）
            $certCats = Model::table('categories')->where('is_certification_required', 1)->where('status', 1)->get();
            $certIds = array_column($certCats, 'id');
            if (!empty($certIds)) {
                $placeholders = implode(',', array_fill(0, count($certIds), '?'));
                $builder->whereRaw("(category_id IN ({$placeholders}) OR pin_scope = 2)", $certIds);
            } else {
                $builder->whereRaw("(category_id = -1 OR pin_scope = 2)");
            }
            $currentCat = ['id' => 0, 'name' => '认证专区', 'is_certification_required' => 1, 'moderators' => [], 'rule' => null];
        } elseif ($cat > 0) {
            // 板块内：显示本板块帖子 + 「全局置顶」帖子（pin_scope=2 应跨板块出现在所有板块顶部）
            $builder->whereRaw("(category_id = ? OR pin_scope = 2)", [$cat]);
        }

        // 认证专区权限校验
        if ($cat > 0) {
            $category = Model::table('categories')->where('id', $cat)->first();
            if ($category && $category['is_certification_required'] == 1 && !is_certified() && !is_admin()) {
                flash('该板块仅对已认证用户开放发帖，您可以浏览内容', 'info');
            }
        }

        // 排序
        if ($sort === 'hot') {
            $builder->orderBy('view_count', 'DESC');
        } elseif ($sort === 'essence') {
            $builder->where('is_essence', 1);
            $builder->orderBy('created_at', 'DESC');
        } else {
            // 默认"最新"排序：
            //   1) 置顶贴永远在最顶部
            //   2) 其余帖子按"最近活跃时间"自动顶贴：last_reply_at DESC
            //      - 已发布的非置顶帖只要有新回复（含楼中楼），PostController::comment 会同步更新该字段
            //      - NULL 兜底到 created_at，确保纯老帖（last_reply_at 没值）也能参与排序
            // 置顶排序（按 pin_scope 区分范围）：
            //   - 板块内（cat>0）：全局置顶(pin_scope=2) + 本版置顶(pin_scope=1 且属于本板块) 都排最前
            //   - 首页 / 认证专区（cat=0）：只有全局置顶排最前，本版置顶不越界到其他位置
            if ($cat > 0) {
                // 板块内：全局置顶 + 本版置顶 + 自助置顶（self_pin_until 未过期）都排最前
                // 板块内：全局置顶 > 本版置顶 > 自助置顶 > 普通，权重递减
                $builder->orderByRaw("CASE WHEN pin_scope = 2 THEN 3 WHEN pin_scope = 1 AND category_id = {$cat} THEN 2 WHEN self_pin_until IS NOT NULL AND self_pin_until > NOW() THEN 1 ELSE 0 END DESC");
            } else {
                // 首页 / 认证专区：仅全局置顶排最前（自助置顶只在本版生效，不越界到首页）
                $builder->orderByRaw('(pin_scope = 2) DESC');
            }
            $builder->orderByRaw('COALESCE(last_reply_at, created_at) DESC')
                    ->orderBy('created_at', 'DESC');
        }

        $result = $builder->paginate($page, $perPage);
        $posts = $result['data'];

        // 一次性聚合「每帖最后一条回复」的用户与时间，避免 N+1
        $postIds = array_column($posts, 'id');
        $lastReplyMap = [];
        if (!empty($postIds)) {
            $placeholders = implode(',', array_fill(0, count($postIds), '?'));
            $sql = "SELECT c.post_id, c.user_id, c.created_at
                    FROM comments c
                    INNER JOIN (
                        SELECT post_id, MAX(id) AS max_id FROM comments WHERE post_id IN ($placeholders) GROUP BY post_id
                    ) t ON c.post_id = t.post_id AND c.id = t.max_id";
            $stmt = \Database::pdo()->prepare($sql);
            $stmt->execute($postIds);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $userIds = array_unique(array_filter(array_column($rows, 'user_id')));
            $userMap = [];
            if (!empty($userIds)) {
                $users = Model::table('users')
                    ->select('id', 'username', 'nickname', 'avatar', 'is_certified', 'show_cert_badges')
                    ->whereIn('id', array_values($userIds))
                    ->get();
                foreach ($users as $u) {
                    $userMap[$u['id']] = $u;
                }
            }
            foreach ($rows as $r) {
                $lastReplyMap[$r['post_id']] = [
                    'user' => $userMap[$r['user_id']] ?? null,
                    'at'   => $r['created_at'],
                ];
            }
        }

        // 关联用户和板块
        foreach ($posts as &$p) {
            $p['author'] = Model::table('users')->select('id', 'username', 'nickname', 'avatar', 'is_certified', 'show_cert_badges')->where('id', $p['user_id'])->first();
            $p['category'] = Model::table('categories')->where('id', $p['category_id'])->first();
            $p['images_arr'] = $p['images'] ? json_decode($p['images'], true) : [];
            $p['last_reply_user'] = $lastReplyMap[$p['id']]['user'] ?? null;
            $p['last_reply_at']   = $lastReplyMap[$p['id']]['at'] ?? null;
            // 卡片式预览摘要：从 content 实时生成纯文本，限长 80 字
            $p['excerpt'] = make_post_excerpt($p['content'] ?? '', 80);
            // 2026-09-06 卡片式封面图：用户显式上传的 cover_image 优先；空则回退到正文第一张图（images_arr[0]）
            $cover = trim((string)($p['cover_image'] ?? ''));
            if ($cover === '' && !empty($p['images_arr'][0])) {
                $cover = (string)$p['images_arr'][0];
            }
            $p['cover_url'] = $cover !== '' ? upload_url($cover) : '';
        }
        unset($p);

        $categories = Model::table('categories')->where('status', 1)->orderBy('sort_order', 'ASC')->get();
        if ($cat > 0 && !isset($currentCat)) {
            $currentCat = Model::table('categories')->where('id', $cat)->first();
            if ($currentCat) {
                $currentCat['moderators'] = Auth::getModeratorsOf($cat);
            }
        } elseif (!isset($currentCat)) {
            $currentCat = null;
        }

        // 2026-09-06：版式设置（post_layout）
        //   - default：列表式，右栏正常显示
        //   - card   ：卡片式，右栏隐藏（侧栏板块/热门/统计由 layout 搬到左栏 sidenav）
        $postLayout = setting('post_layout', 'default');

        $this->view('home/index', [
            'posts' => $posts,
            'pagination' => $result,
            'categories' => $categories,
            'currentCat' => $currentCat,
            'sort' => $sort,
            'cat' => $cat,
            'page' => $page,
            'perPage' => $perPage,
            '__hideSidebar' => ($postLayout === 'card'),
        ]);
    }

    /**
     * 板块版规更新（版主及以上权限）
     */
    public function updateCategoryRule()
    {
        if (!Auth::check()) $this->error('请先登录');
        $id = (int)input('id');
        $cat = Model::table('categories')->where('id', $id)->first();
        if (!$cat) $this->error('板块不存在');
        if (!Auth::isModeratorOf($id)) $this->error('无权限修改该板块版规');
        $rule = trim(input('rule', ''));
        Model::table('categories')->where('id', $id)->update([
            'rule' => $rule === '' ? null : $rule,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        // 返回 Markdown 渲染后的 HTML，便于前端无刷新更新
        $html = function_exists('parse_markdown') ? parse_markdown($rule) : nl2br(e($rule));
        $this->success(['html' => $html], '版规已更新');
    }

    /**
     * 提交举报
     */
    public function report()
    {
        $targetType = input('target_type');
        $targetId = (int)input('target_id');
        $reason = trim(input('reason'));

        if (!in_array($targetType, ['post', 'comment'])) {
            $this->error('无效的举报对象');
        }
        if (!$targetId) $this->error('缺少举报目标');
        if (!$reason) $this->error('请填写举报原因');

        // 检查是否已存在该目标
        $table = $targetType === 'post' ? 'posts' : 'comments';
        $target = Model::table($table)->where('id', $targetId)->first();
        if (!$target) $this->error('举报对象不存在');

        // 检查重复举报
        $exists = Model::table('reports')
            ->where('reporter_id', Auth::id())
            ->where('target_type', $targetType)
            ->where('target_id', $targetId)
            ->first();
        if ($exists) $this->error('您已举报过该内容，请等待处理');

        Model::table('reports')->insert([
            'reporter_id' => Auth::id(),
            'target_type' => $targetType,
            'target_id' => $targetId,
            'reason' => filter_sensitive($reason),
            'status' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->success(null, '举报已提交，管理员将尽快处理');
    }

    /**
     * 关于页
     */
    public function about()
    {
        $this->view('home/about');
    }
}
