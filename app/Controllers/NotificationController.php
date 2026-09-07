<?php
/**
 * 通知控制器（仅负责 notifications 表的展示与已读标记）
 *
 * 与私信（MessageController）严格分离：
 *   - 本控制器**只看** notifications 表；私信（messages 表）由 MessageController 独立处理
 *   - 查询永远排除 type='message'，防止历史残留数据混入通知页
 *   - markAllRead / markAsRead 只标通知，私信未读不受影响
 *
 * 路由（控制器自动分发）：
 *   notification/index          通知列表（tab 分类：all/comment/reply/like/follow/certification/system/mention）
 *   notification/markAllRead    全部已读（POST + csrf）
 *   notification/markAsRead/{id} 单条已读（POST + csrf）
 */
class NotificationController extends Controller
{
    protected $middleware = [
        ['middleware' => 'auth'],
        ['middleware' => 'csrf', 'only' => ['markAllRead', 'markAsRead']],
    ];

    /**
     * 通知列表（tab 分类）
     *   tab=all / comment / reply / like / follow / certification / system / mention
     *   - tab=system  只看 is_system_notification=1（站长通过系统通知菜单广播的官方通知）
     *   - tab=all     全部不过滤（已排除私信）
     *   切到 tab 时，仅标记当前 tab 的为已读；私信（messages 表）不受影响
     */
    public function index()
    {
        $userId = Auth::id();
        $page = (int)input('page', 1);
        $tab = trim((string)input('tab', 'all'));
        $allowed = ['all', 'comment', 'reply', 'like', 'follow', 'certification', 'system', 'mention', 'favorite'];
        if (!in_array($tab, $allowed, true)) $tab = 'all';

        $builder = Model::table('notifications')->where('user_id', $userId);

        // ✦ 任何 tab 都不展示私信（type='message'）类通知——私信独立
        $builder->where('type', '!=', 'message');

        if ($tab === 'all') {
            // 全部：所有非私信通知（按时间倒序）
        } elseif ($tab === 'system') {
            $builder->where('is_system_notification', 1);
        } else {
            $builder->where('type', $tab);
        }

        $result = $builder->orderBy('created_at', 'DESC')->paginate($page, 20);

        // 切到 tab 时，仅标记当前 tab 的为已读（私信不参与）
        $markBuilder = Model::table('notifications')->where('user_id', $userId)->where('is_read', 0);
        $markBuilder->where('type', '!=', 'message');
        if ($tab === 'all') {
            $markBuilder->where('is_system_notification', 0);
        } elseif ($tab === 'system') {
            $markBuilder->where('is_system_notification', 1);
        } else {
            $markBuilder->where('type', $tab);
        }
        $markBuilder->update(['is_read' => 1]);

        $this->view('notification/index', [
            'notifications' => $result['data'],
            'pagination' => $result,
            'page' => $page,
            'tab' => $tab,
        ]);
    }

    /**
     * 全部已读（通知页面"全部已读"按钮）
     *   ✦ 私信完全独立：本接口只清 notifications 表，**不动 messages**。
     *   可选 ?tab=comment / like / follow ... 只标指定 tab 类型为已读
     *   返回 {affected: N}，前端据此移除 .unread 类、隐藏 tab 角标红点
     */
    public function markAllRead()
    {
        $userId = Auth::id();
        $tab = trim((string)input('tab', ''));
        $allowed = ['comment', 'reply', 'like', 'follow', 'certification', 'system', 'mention', 'favorite'];
        $builder = Model::table('notifications')->where('user_id', $userId)->where('is_read', 0);
        // 通知永远排除私信（即便 type 不在 allowed，私信也不参与"全部已读"）
        $builder->where('type', '!=', 'message');
        if ($tab === 'system') {
            $builder->where('is_system_notification', 1);
        } elseif (in_array($tab, $allowed, true)) {
            $builder->where('type', $tab);
        }
        $affected = $builder->update(['is_read' => 1]);
        $this->success(['affected' => (int)$affected], '已全部标记为已读');
    }

    /**
     * 单条标记已读（通知页"查看"链接点击后调用）
     *   POST notification/markAsRead {id: int, _token: string}
     *   返回 {ok, was_unread, type, is_system}：
     *     - was_unread=1 才视为有效 mark（避免重复点击无副作用）
     *     - type / is_system 让前端决定 -1 哪个 tab 角标
     *   限定 type != 'message'（私信不参与）
     */
    public function markAsRead($id = null)
    {
        $id = (int)($id ?? input('id'));
        if ($id <= 0) $this->error('通知 id 不能为空');
        $userId = Auth::id();
        $row = Model::table('notifications')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();
        if (!$row) $this->error('通知不存在或已删除');
        if ((string)$row['type'] === 'message') $this->error('私信类通知需在私信页面处理');

        $wasUnread = ((int)$row['is_read'] === 0) ? 1 : 0;
        if ($wasUnread) {
            try {
                Model::table('notifications')->where('id', $id)->where('user_id', $userId)->update(['is_read' => 1]);
            } catch (Exception $e) {
                $this->error('标记失败');
            }
        }
        $this->success([
            'ok' => 1,
            'was_unread' => $wasUnread,
            'type' => (string)$row['type'],
            'is_system' => (int)($row['is_system_notification'] ?? 0),
        ]);
    }
}