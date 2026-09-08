# 固定连接（Pretty URL）配置指南

站点支持 5 种 permalink 风格（后台「系统管理 → 固定连接」可切换）：

| 风格 | URL 形态 | 是否需要 `posts.url_slug` |
|---|---|---|
| 默认（朴素） | `/index.php?r=post/show&id=123` | 否 |
| ID 型 | `/post/123` `/c/5` `/u/8` `/` | 否 |
| 板块 + ID 型 | `/c/5/123` `/c/5` `/u/8` | 否 |
| 标题型 | `/post/123-my-slug` `/c/5` `/u/8` | 是 |
| 板块 + 标题型 | `/c/5/123-my-slug` | 是 |

**重点**：选「标题型」或「板块 + 标题型」时，`posts` 表必须有 `url_slug` 列；
访问 `install/upgrade.php` 一次会执行升级项 #28（自动加列 + 唯一索引）。

---

## 宝塔 Nginx 配置

切换到非「默认」风格后，浏览器访问 `/post/123` 等路径需要 web 服务器把它转给 `index.php`。
最简单的方式：在宝塔「网站 → 设置 → 配置文件」里找到 `location / { ... }` 块，**完整替换**为：

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

保存后：

```bash
nginx -t          # 测试配置是否正确
nginx -s reload   # 生效
```

### 注意事项

1. **`uploads` / `css` / `js` 等静态目录要避开 rewrite**。如果你的站点根目录是 `/www/wwwroot/你的站点根目录`，上面的 `try_files` 写法是安全的——
   因为 `try_files` 在 `$uri` 和 `$uri/` 都不存在时才会 fall back 到 `index.php`，所以真实存在的静态文件会**直接由 nginx 提供**（更快）。
2. **不要**用 `rewrite ^.*$ /index.php last;` 这种粗粒度规则——它会让所有请求（包括 404）都走 PHP，浪费资源。
3. **伪静态对 admin 无影响**——后台路由始终走 `?r=admin/xxx`，SEO 也没意义。
4. **旧 URL 兼容**：所有 `?r=post/show&id=123` 形式的请求会自动 **301 跳转到新 pretty URL**，
   已经在 `index.php` 顶部实现，无需任何配置。

---

## Apache 备用方案

如果未来切换到 Apache，在网站根目录的 `.htaccess` 里写：

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^ index.php [L]
</IfModule>
```

---

## 常见问题

### Q：保存设置后旧链接报 404？
A：检查 nginx 是不是没改 `try_files` 规则。`index.php` 内部已经做了 301 重定向，
所以 `?r=post/show&id=123` 一定能跳到 `/post/123`，但浏览器直接输入 `/post/123` 还需要 web 服务器配合。

### Q：切到「标题型」后老帖子 URL 没变？
A：后台保存时会自动跑「补全 url_slug」流程，把缺失的 slug 全补上。补全数量会显示在保存成功的提示里。
如果数量很大（>5000），可能要等几秒，期间别刷新页面。

### Q：为什么改了标题后 URL 不变？
A：和 WordPress 一致——slug 在帖子首次发布时生成，**之后编辑标题不更新 slug**，
保证外链稳定。如果想强制更新，可以手动在数据库里改 `posts.url_slug` 字段。

### Q：能在标题里保留中文吗？
A：不能。当前 `slugify()` 会去掉所有非 ASCII 字符，纯中文标题会回退为 `post-{id}` 的纯 ID URL。
如需保留中文 slug，需要在 `slugify()` 里集成 pinyin 库（如 `overtrue/pinyin`），目前未集成。

### Q：用户主页 URL 能否带用户名（如 `/u/john-doe`）？
A：当前是纯 ID（`/u/8`）。如果想带用户名，可以在 `user_url()` 里加 slug 字段，
`users` 表加 `url_slug` 列。本期未实现。
