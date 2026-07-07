# Changelog

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
