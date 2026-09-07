<?php
/**
 * 表情系统控制器
 *
 * 仅暴露公开接口 list()（任何访客/登录用户可访问，供发布帖/评论/私信三处表情选择器使用）。
 * 后台管理接口全部在 AdminController 内（emojiPacks/emojiPackEdit/...），统一受 admin 中间件保护。
 */
class EmojiController extends Controller
{
    /**
     * GET /emoji/list
     * 公开：返回当前所有启用表情包及其 items。
     * 数据量：3 个内置包 ≈ 130 项 × 3 ≈ 400 行，体量小，未加缓存（确保后台启停立即生效）。
     * 渲染由前端按 pack.type 决定：unicode 直接 char / image 用 pack.cd 模板拼 URL。
     */
    public function list()
    {
        $packs = Model::query(
            "SELECT id, name, slug, type, cd, sort_order
               FROM emoji_packs
              WHERE enabled = 1
              ORDER BY sort_order ASC, id ASC"
        );

        foreach ($packs as &$p) {
            $items = Model::query(
                "SELECT code, name, keywords, `char`, `image`, category
                   FROM emoji_items
                  WHERE pack_id = ? AND enabled = 1
                  ORDER BY sort_order ASC, id ASC",
                [$p['id']]
            );
            $p['items'] = $items;
        }
        unset($p);

        $this->success(['packs' => $packs]);
    }
}