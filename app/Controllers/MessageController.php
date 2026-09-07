<?php
/**
 * 私信控制器（仅负责 messages 表：会话列表、收发私信、轮询）
 *
 * 与通知（NotificationController）严格分离：
 *   - 本控制器**绝不**写入 notifications 表（私信不走通知通道）
 *   - 用户收到的私信提醒由 unreadSummary().pm 直接从 messages 表统计，
 *     与 notifications 表独立计数。
 *   - 通知页（notification/index）只展示来自 notifications 表的非私信条目。
 *
 * 路由：
 *   message/index          私信主入口（双列布局）
 *   message/chat/{id}      与某用户的对话（重定向到 message/index?id=X）
 *   message/send           发送私信（POST + csrf）
 *   message/newMessages    轮询新消息
 *   message/unreadSummary  铃铛角标聚合（notif + pm）
 *   message/unreadCount    铃铛角标简易版（notifications 排除 type='message'）
 */
class MessageController extends Controller
{
    protected $middleware = [
        ['middleware' => 'auth'],
        ['middleware' => 'banned', 'only' => ['send']],
        ['middleware' => 'csrf', 'only' => ['send']],
    ];

    /**
     * 未读消息数量（供前端轮询角标）
     *   通知页已迁出，本接口只看 notifications 表（**排除** type='message' 旧数据）。
     *   私信未读另走 unreadSummary().pm 单独统计。
     */
    public function unreadCount()
    {
        $count = Model::table('notifications')
            ->where('user_id', Auth::id())
            ->where('is_read', 0)
            ->where('type', '!=', 'message')
            ->count();
        $this->success(['count' => (int)$count]);
    }

    /**
     * 未读消息汇总（供顶栏下拉实时轮询 + 会话列表实时更新预览）
     * 返回 {notif, pm, convUnread, convList}
     *   convUnread: [uid => n]               每个会话对方的未读条数（用于会话列表角标）
     *   convList:   [{id, last_message, last_time}] 会话列表预览 + 时间（用于 message/index 实时刷新最后一条消息）
     */
    public function unreadSummary()
    {
        $userId = Auth::id();
        // ✦ 不再把"私信"计入通知未读。私信单独从 messages 表统计。
        $notif = (int)Model::table('notifications')->where('user_id', $userId)->where('is_read', 0)->where('type', '!=', 'message')->count();
        $pm = (int)Model::table('messages')->where('to_user_id', $userId)->where('is_read', 0)->where('status', 1)->count();
        // 每个会话对方的未读数
        $rows = Model::query(
            "SELECT from_user_id AS uid, COUNT(*) AS c FROM messages
             WHERE to_user_id = ? AND is_read = 0 AND status = 1
             GROUP BY from_user_id",
            [$userId]
        );
        $convUnread = [];
        foreach ($rows as $r) $convUnread[(int)$r['uid']] = (int)$r['c'];
        // 每个会话的对方用户、最新一条消息的内容/时间（用于实时刷新预览）
        // ✦ 改为更稳的关联子查询（避免 IN(SELECT MAX...GROUP BY...) 在多表 JOIN 下偶发走错执行计划）；
        //    排除"自言自语"（from=to）污染会话列表；过滤 status=0 残留。
        $listRows = Model::query(
            "SELECT u.id, m.content AS last_message, m.created_at AS last_time
             FROM messages m
             JOIN users u ON (u.id = IF(m.from_user_id = ?, m.to_user_id, m.from_user_id))
             INNER JOIN (
                 SELECT IF(from_user_id = ?, to_user_id, from_user_id) AS peer_uid,
                        MAX(id) AS max_id
                 FROM messages
                 WHERE status = 1 AND from_user_id != to_user_id
                   AND (from_user_id = ? OR to_user_id = ?)
                 GROUP BY peer_uid
             ) lm ON lm.max_id = m.id
             WHERE m.status = 1 AND (m.from_user_id = ? OR m.to_user_id = ?)
             ORDER BY m.created_at DESC",
            [$userId, $userId, $userId, $userId, $userId, $userId]
        );
        $convList = [];
        foreach ($listRows as $r) {
            // ✦ 侧栏预览要"所见即所得"：原始 :code: 短代码会显示成字面文本，应在服务端先过 render_emoji()
            //    同时按 28 字截断（参考模板中的 truncate(..., 28)）；HTML 中的 <img> 不计入宽度，
            //    截断仅按"原始文本长度"算，渲染后视觉上仍稳定在单行内。
            $raw = (string)$r['last_message'];
            $truncated = mb_strlen($raw) > 28 ? mb_substr($raw, 0, 28) . '…' : $raw;
            $convList[] = [
                'id'                => (int)$r['id'],
                'last_message'      => $raw,
                'last_message_html' => render_emoji(e($truncated)),
                'last_time'         => (string)$r['last_time'],
            ];
        }
        $this->success(['notif' => $notif, 'pm' => $pm, 'convUnread' => $convUnread, 'convList' => $convList]);
    }

    /**
     * 私信主入口（双列布局）
     *   无 id：右列显示"请选择会话"占位 + 左侧会话列表
     *   有 id：右列加载该用户对话内容（消息列表 + 输入框），左列会话列表选中项淡品牌红
     */
    public function index()
    {
        $userId = Auth::id();

        // 1. 当前选中目标用户（可选）
        $id = (int)input('id', 0);
        $targetUser = null;
        $messages = [];
        if ($id > 0) {
            $targetUser = Model::table('users')
                ->select('id', 'username', 'nickname', 'avatar', 'is_certified', 'show_cert_badges')
                ->where('id', $id)
                ->where('status', 1)
                ->first();
            if ($targetUser) {
                $before = (int)input('before', 0);
                $msgSql = "SELECT * FROM messages WHERE status = 1 AND ((from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?))";
                $msgParams = [$userId, $id, $id, $userId];
                if ($before > 0) {
                    $msgSql .= " AND id < ?";
                    $msgParams[] = $before;
                }
                // ✦ 关键修复：原先 ORDER BY created_at ASC LIMIT 100 只取「最早 100 条」，
                //   会话超过 100 条时最新消息被截断 → 聊天窗口看不到双方最新消息，
                //   而会话列表预览用的是 MAX(id) 所以照常显示最新（正是用户报的现象）。
                //   改为「按 id 倒序取最新 100 条」再 array_reverse 成时间正序展示；带 before 时向前翻页取更旧的消息。
                $msgSql .= " ORDER BY id DESC LIMIT 100";
                $messages = Model::query($msgSql, $msgParams);
                $messages = array_reverse($messages);

                $hasMore = count($messages) >= 100;
                $oldestId = $messages ? (int)$messages[0]['id'] : 0;

                // 仅在首次进入会话（非翻页）时把对方发来的未读消息标记为已读
                if ($before <= 0) {
                    Model::table('messages')
                        ->where('from_user_id', $id)
                        ->where('to_user_id', $userId)
                        ->where('is_read', 0)
                        ->update(['is_read' => 1]);
                }
            }
        }

        // 2. 会话列表（与 userId 有私信往来的所有对方用户）
        // ✦ 修法：把原先 `IN (SELECT MAX(id) ... GROUP BY IF(...))` 换成 INNER JOIN 关联子查询，
        //    避免 IN 子查询 + 主查询在多表 JOIN 下偶发走错执行计划导致"我给A发的却出现在B列表下"。
        //    同时排除 from=to 自言自语与 status=0 残留。
        $conversations = Model::query(
            "SELECT u.id, u.username, u.nickname, u.avatar, u.is_certified, u.show_cert_badges,
                    m.content AS last_message, m.created_at AS last_time,
                    (SELECT COUNT(*) FROM messages
                       WHERE from_user_id = u.id AND to_user_id = ? AND is_read = 0 AND status = 1) AS unread
             FROM messages m
             JOIN users u ON (u.id = IF(m.from_user_id = ?, m.to_user_id, m.from_user_id))
             INNER JOIN (
                 SELECT IF(from_user_id = ?, to_user_id, from_user_id) AS peer_uid,
                        MAX(id) AS max_id
                 FROM messages
                 WHERE status = 1 AND from_user_id != to_user_id
                   AND (from_user_id = ? OR to_user_id = ?)
                 GROUP BY peer_uid
             ) lm ON lm.max_id = m.id
             WHERE m.status = 1 AND (m.from_user_id = ? OR m.to_user_id = ?)
             ORDER BY m.created_at DESC",
            [$userId, $userId, $userId, $userId, $userId, $userId, $userId]
        );

        // ✦ 主入口 URL，让「← 返回会话列表」用
        // ✦ 侧栏预览在渲染前在模板里过 render_emoji(e(...))，避免显示字面短代码
        $this->view('message/index', [
            'conversations' => $conversations,
            'targetUser' => $targetUser,
            'messages' => $messages,
            'selectedId' => $id,
            'hasMore' => $hasMore ?? false,
            'oldestId' => $oldestId ?? 0,
        ]);
    }

    /**
     * 与某用户的私信对话（向后兼容：重定向到 message/index?id=X）
     */
    public function chat($id = null)
    {
        $id = (int)($id ?? input('id'));
        // 直接跳到统一入口 message/index?id=X，避免维护两套渲染
        header('Location: ' . url('message/index', ['id' => $id]));
        exit;
    }

    /**
     * 轮询获取与某用户会话中的新消息（id > lastId）
     * 供 chat 页面定时拉取实现"实时"显示
     */
    public function newMessages($id = null)
    {
        $id = (int)($id ?? input('id'));
        $lastId = (int)input('last_id', 0);
        $userId = Auth::id();
        $rows = Model::query(
            "SELECT id, from_user_id, content, created_at FROM messages
             WHERE status = 1 AND id > ? AND ((from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?))
             ORDER BY id ASC LIMIT 50",
            [$lastId, $userId, $id, $id, $userId]
        );
        // 每条消息附带 content_html（已转义 + emoji 短代码已渲染），前端 appendMessage 直接拼接
        foreach ($rows as &$r) {
            $r['content_html']   = render_emoji(e((string)$r['content']));
            $r['created_at_ago'] = time_ago($r['created_at']);
        }
        unset($r);
        // 顺便标记对方发来的新消息为已读
        if (!empty($rows)) {
            Model::table('messages')->where('from_user_id', $id)->where('to_user_id', $userId)->where('is_read', 0)->update(['is_read' => 1]);
        }
        $this->success(['messages' => $rows, 'now' => time()]);
    }

    /**
     * 加载更早的消息（向前翻页），供聊天窗口顶部「加载更早消息」调用。
     * 参数：id=对方uid, before=当前窗口最旧消息id
     * 返回：{messages:[{id,from_user_id,content,created_at}], has_more, oldest_id}
     */
    public function history($id = null)
    {
        $id = (int)($id ?? input('id'));
        $before = (int)input('before', 0);
        $userId = Auth::id();
        if ($id <= 0 || $before <= 0) {
            $this->success(['messages' => [], 'has_more' => false, 'oldest_id' => 0]);
            return;
        }
        $rows = Model::query(
            "SELECT id, from_user_id, content, created_at FROM messages
             WHERE status = 1 AND id < ? AND ((from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?))
             ORDER BY id DESC LIMIT 50",
            [$before, $userId, $id, $id, $userId]
        );
        $rows = array_reverse($rows);
        // 同步附带 content_html（与 newMessages / send 一致：emoji 短代码已在服务端渲染好）
        foreach ($rows as &$r) {
            $r['content_html']   = render_emoji(e((string)$r['content']));
            $r['created_at_ago'] = time_ago($r['created_at']);
        }
        unset($r);
        $hasMore = count($rows) >= 50;
        $oldestId = $rows ? (int)$rows[0]['id'] : $before;
        $this->success(['messages' => $rows, 'has_more' => $hasMore, 'oldest_id' => $oldestId]);
    }

    /**
     * 发送私信
     */
    public function send()
    {
        abortIfBanned();
        csrf_check();
        $toUserId = (int)input('to_user_id');
        $content = trim(input('content'));

        if ($toUserId == Auth::id()) $this->error('不能给自己发私信');
        $target = Model::table('users')->where('id', $toUserId)->where('status', 1)->first();
        if (!$target) $this->error('用户不存在');
        if (!$content) $this->error('请输入消息内容');
        if (mb_strlen($content) > 1000) $this->error('消息不能超过1000字');

        // 高危敏感词硬拦截
        if (has_high_risk_sensitive($content)) {
            $this->error('私信包含高危敏感词，无法发送');
        }

        $now = date('Y-m-d H:i:s');
        $msgId = Model::table('messages')->insert([
            'from_user_id' => Auth::id(),
            'to_user_id' => $toUserId,
            'content' => filter_sensitive($content),
            'is_read' => 0,
            'status' => 1,
            'created_at' => $now,
        ]);

        // ✦ 私信不再写入 notifications 表：私信与通知是两个独立通道。
        //   收信人的"未读私信"角标由 unreadSummary().pm 直接从 messages 表统计。
        //   这样 markAllRead 等通知操作不会再误带 / 漏带 PM 提醒。

        // 返回已渲染的 HTML（含 emoji 短代码替换），前端 appendMessage 直接拼接 content_html
        $contentHtml = render_emoji(e(filter_sensitive($content)));
        $this->success([
            'id'           => $msgId,
            'content_html' => $contentHtml,
            'created_at'   => $now,
            'created_at_ago' => time_ago($now),
        ], '发送成功');
    }
}
