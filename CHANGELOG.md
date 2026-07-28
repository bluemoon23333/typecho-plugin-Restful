# Changelog

## 1.4.0 (2026-07-28) — 媒体同步 + 删除接口增强

### 新增 `deletePost` 接口（`POST /api/deletePost`）

- 新增 `deletePostAction()` 方法，完整的文章删除功能
- 一次请求即可删除文章本体 + 关联的分类/标签关系（`table.relationships`）+ 自定义字段（`table.fields`）+ 评论（`table.comments`）
- 参数：`{ "cid": 1 }`
- 返回：`{ "deleted": true, "cid": 1, "title": "文章标题" }`

> 与已有的 `deleteFile`（删除附件文件）区分：`deletePost` 用于删除文章。

### 新增 `type` 参数（postArticle）

- `POST /api/postArticle` 新增 `type` 参数：支持创建独立页面
- 可选值：`post`（默认，文章）、`page`（独立页面）
- 由 Obsidian 媒体同步插件使用，可将 Obsidian 笔记同步为 Typecho 独立页面

### 新增 `fields` 参数（postArticle）

- `POST /api/postArticle` 新增 `fields` 参数：支持批量写入自定义字段
- 格式：`{ "fieldName": "value", ... }`
- 在文章创建/更新时一次性完成所有自定义字段的写入
- 由 Obsidian 媒体同步插件使用，例如存储原始文件路径、同步时间戳等

### 新增文档文件

- `MediaSyncExtension.php`：媒体同步扩展的独立参考文档，包含完整的修改指南和使用示例
- 不会影响原有接口兼容性，仅作为开发参考

### 文件变更

- **修改**：`Action.php` — 新增 `deletePostAction()`、postArticle 的 `type`/`fields` 参数支持
- **新建**：`MediaSyncExtension.php` — 媒体同步扩展文档

---

## 1.3.0 (2026-07-06) — Obsidian Publisher 适配版

本版本为配合 Obsidian Typecho Publisher v2.0.0 进行了大量扩展。

### postArticleAction 核心重构

**新增 `cid` 参数与匹配逻辑重构**
- 将文章新建/更新的匹配策略从「slug → title」改为「cid → slug」三级回退
- cid>0 时按 cid 优先匹配；未命中则按 authorId+slug 回退；都未命中则新建
- 不再按 title 匹配

**新增 `created` 参数**：Unix 时间戳，支持自定义发布日期

**返回值语义化**：`{ cid, type("add"|"update"), slug }`

**新增 5 个可选参数**：`banner`、`description`、`status`、`password`、`allowComments`

### 新增功能

- **banner**：存入 `table.fields` (name=thumb)
- **description**：Butterfly 主题存入 summaryContent/desc 字段；其他主题用 `<!--more-->` + 隐藏 div 兼容
- **status**：publish/hidden/password/private/waiting
- **password**：仅 status=password 时生效
- **allowComments**：0=关闭评论，1=允许（默认 1）
- **allowPing / allowFeed**：新建/更新时默认设为 1
- 正文自动添加 `<!--markdown-->` 标记
- 新增 `setField()` 辅助方法
- 新增 `isButterflyTheme()` 主题检测方法

### sendCORS 放宽限制

无 Origin 头时设置 `Access-Control-Allow-Origin: *`，支持 Obsidian/curl/Postman 等非浏览器客户端。

### Plugin.php 配置面板优化

- 顶部显示当前主题检测结果
- 全部 16 个 API 接口添加中文说明和端点路径

### 其他

- 插件自更新仓库地址改为 `bluemoon23333/typecho-plugin-Restful`
- 版本号升级至 1.3.0
