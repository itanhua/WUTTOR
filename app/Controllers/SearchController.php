<?php
/**
 * 搜索控制器
 */
class SearchController extends Controller
{
    /**
     * 搜索页 - 搜索帖子和用户
     */
    public function index()
    {
        $q = trim(input('q', ''));
        $type = input('type', 'all'); // all / post / user
        $page = (int)input('page', 1);

        $posts = [];
        $users = [];
        $postTotal = 0;
        $userTotal = 0;

        if ($q !== '') {
            $keyword = '%' . $q . '%';

            if ($type === 'all' || $type === 'post') {
                $postResult = Model::table('posts')
                    ->where('status', 1)
                    ->whereLike('title', $keyword)
                    ->orderBy('created_at', 'DESC')
                    ->paginate($page, 15);
                // 也搜索内容
                if ($postResult['total'] === 0) {
                    $postResult = Model::table('posts')
                        ->where('status', 1)
                        ->whereLike('content', $keyword)
                        ->orderBy('created_at', 'DESC')
                        ->paginate($page, 15);
                }
                $posts = $postResult['data'];
                $postTotal = $postResult['total'];
                foreach ($posts as &$p) {
                    $p['author'] = Model::table('users')->select('id', 'nickname', 'username', 'avatar', 'is_certified', 'show_cert_badges')->where('id', $p['user_id'])->first();
                    $p['category'] = Model::table('categories')->where('id', $p['category_id'])->first();
                }
                unset($p);
            }

            if ($type === 'all' || $type === 'user') {
                $users = Model::query(
                    "SELECT id, username, nickname, avatar, bio, is_certified, show_cert_badges FROM users WHERE status = 1 AND (username LIKE ? OR nickname LIKE ?) LIMIT 20",
                    [$keyword, $keyword]
                );
                $userTotal = Model::query(
                    "SELECT COUNT(*) AS cnt FROM users WHERE status = 1 AND (username LIKE ? OR nickname LIKE ?)",
                    [$keyword, $keyword]
                )[0]['cnt'] ?? 0;
            }
        }

        $this->view('search/index', [
            'q' => $q,
            'type' => $type,
            'posts' => $posts,
            'users' => $users,
            'postTotal' => $postTotal,
            'userTotal' => $userTotal,
            'page' => $page,
        ]);
    }
}
