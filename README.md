# Typecho Plugin Restful

为 Typecho 博客系统提供 RESTful API 接口，支持通过 HTTP JSON 访问和操作站点内容。

> **本仓库为二次开发版本，基于 [moefront/typecho-plugin-Restful](https://github.com/moefront/typecho-plugin-Restful) Fork 并进行了大量扩展。**

---

## 目录

- [功能特性](#功能特性)
- [API 端点](#api-端点)
- [安装](#安装)
- [配置](#配置)
- [API 认证](#api-认证)
- [核心接口说明](#核心接口说明)
  - [发表/更新文章](#发表更新文章)
  - [上传文件](#上传文件)
  - [获取文章详情](#获取文章详情)
- [与 Obsidian Typecho Publisher 配合](#与-obsidian-typecho-publisher-配合)
- [自定义 API 前缀](#自定义-api-前缀)
- [注意事项](#注意事项)
- [致谢](#致谢)

---

## 功能特性

- 全文 JSON RESTful API，共 19 个接口
- 文章/页面/评论/分类/标签/用户/文件全量管理
- API Token 认证
- CORS 跨域支持（兼容非浏览器客户端）
- 文章自定义字段（缩略图、摘要、SEO 描述等）
- CSRF Token 保护评论接口
- 插件自更新

---

## API 端点

| 端点 | 方法 | 说明 |
|------|------|------|
| `/api/posts` | GET | 文章列表（支持分类/标签/搜索过滤） |
| `/api/pages` | GET | 独立页面列表 |
| `/api/categories` | GET | 所有分类 |
| `/api/tags` | GET | 所有标签 |
| `/api/post` | GET | 文章/页面详情（含 CSRF Token） |
| `/api/comments` | GET | 评论列表（树形结构、分页） |
| `/api/recentComments` | GET | 最近评论 |
| `/api/comment` | POST | 发表评论（需 CSRF Token） |
| `/api/settings` | GET | 站点设置 |
| `/api/users` | GET | 用户信息与文章 |
| `/api/userList` | GET | 用户列表 |
| `/api/archives` | GET | 文章归档 |
| `/api/postArticle` | POST | 发表/更新文章（核心接口） |
| `/api/deletePost` | POST | 删除文章（含关联的 metas/字段/评论） |
| `/api/addMetas` | POST | 新增分类/标签 |
| `/api/upload` | POST | 上传文件 |
| `/api/deleteFile` | POST | 删除文件 |
| `/api/fileList` | GET | 文件列表 |
| `/api/upgrade` | GET | 插件自更新 |

---

## 安装

### 方式一：手动安装

1. 下载本仓库压缩包
2. 解压并重命名文件夹为 `Restful`
3. 放到 Typecho 的 `usr/plugins/` 目录下
4. 到 Typecho 后台 → 插件管理 → 启用

### 方式二：Composer 安装

```bash
cd /path/to/typecho/usr/plugins
composer create-project bluemoon23333/typecho-plugin-Restful Restful --prefer-dist --stability=dev
chown www:www -R Restful
```

### 安装后部署

插件启用后，将 Action.php 和 Plugin.php 上传覆盖即可。

---

## 配置

在 Typecho 后台「控制台 → 插件 → Restful → 设置」中配置：

| 配置项 | 说明 |
|--------|------|
| **API 状态开关** | 可单独禁用任一 API 端点 |
| **域名列表** | CORS 允许的源站域名，`*` 为通配符；非浏览器请求自动放行 |
| **API Token** | API 请求需携带的令牌（header:`token`），置空不校验 |
| **高敏接口登录校验** | 开启后 postArticle/upload/deleteFile 需要 Cookie 登录态 |
| **CSRF 加密盐** | 评论接口的 CSRF Token 签名密钥 |
| **自定义字段过滤** | 在文章详情中隐藏的字段名 |

---

## API 认证

所有 API 请求需在 HTTP Header 中携带 Token：

```
token: 你的API令牌
```

高敏写操作（如 `postArticle`、`upload`）如果开启了 `validateLogin`，还需携带 Typecho 登录 Cookie。

---

## 核心接口说明

### 发表/更新文章

```
POST /api/postArticle
Content-Type: application/json
```

**参数：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `title` | string | 是 | 文章标题 |
| `text` | string | 是 | 正文（Markdown） |
| `authorId` | number | 是 | 作者 UID |
| `cid` | number | 否 | 文章 ID。0=新建，>0=按 cid 匹配更新 |
| `slug` | string | 否 | URL 缩略名 |
| `mid` | string | 否 | 分类/标签 ID，逗号分隔 |
| `created` | number | 否 | 发布时间（Unix 时间戳） |
| `banner` | string | 否 | 封面图 URL |
| `description` | string | 否 | 文章摘要/SEO 描述 |
| `status` | string | 否 | 公开状态：publish/hidden/password/private/waiting |
| `password` | string | 否 | 访问密码（status=password 时生效） |
| `allowComments` | number | 否 | 评论开关：0=关闭，1=允许 |
| `type` | string | 否 | 内容类型：`post`（默认，文章）或 `page`（独立页面） |
| `fields` | object | 否 | 批量写入自定义字段，格式：`{"fieldName": "value", ...}` |

**返回：**
```json
{ "status": "success", "data": { "cid": 45, "type": "add", "slug": "my-post" } }
```

### 删除文章

```
POST /api/deletePost
Content-Type: application/json
```

**参数：**

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| `cid` | number | 是 | 要删除的文章 ID |

**返回：**
```json
{ "status": "success", "data": { "deleted": true, "cid": 1, "title": "文章标题" } }
```

> `deletePost` 会同时删除文章的关联关系（分类/标签）、自定义字段和评论。

### 上传文件

```
POST /api/upload
```

支持 multipart/form-data 和 base64 JSON 两种格式。

**JSON 格式参数：** `{ "file": "<base64>", "fileName": "image.png", "authorId": 1 }`

**返回：** `{ "cid": 123, "title": "image.png", "type": "image/png", "size": 2048, "url": "/usr/uploads/...", "host": "https://example.com" }`

### 获取文章详情

```
GET /api/post?cid=1
GET /api/post?slug=my-post
```

返回文章全文（HTML）、自定义字段、分类/标签、CSRF Token 等。

---

## 与 Obsidian Typecho Publisher 配合

本插件是 [Obsidian Typecho Publisher](https://github.com/bluemoon23333/obsidian-typecho-publisher) 的服务端依赖。

**配置要求：**

1. 插件设置中设置 API Token（例如 `123456`）
2. **将 `validateLogin` 设为 0（否）**——Obsidian 端仅通过 API Token 鉴权，无法携带 Typecho Cookie
3. 推荐使用 Butterfly 主题以获得完整的文章摘要（SEO 描述）支持

---

## 自定义 API 前缀

在 Typecho 根目录的 `config.inc.php` 中添加：

```php
define('__TYPECHO_RESTFUL_PREFIX__', '/rest/');
```

然后禁用并重新启用插件，API 端点将变为 `/rest/*`。

---

## 注意事项

- 不兼容 Typecho 1.2 以前版本
- 未开启地址重写的站点，API 路径前需加 `/index.php`（如 `/index.php/api/posts`）
- 评论接口的 CSRF Token 每天过期，绑定 IP 和 UA
- 建议将 `csrfSalt` 修改为自定义值

---

## 致谢

- 原版插件：[moefront/typecho-plugin-Restful](https://github.com/moefront/typecho-plugin-Restful)
- Obsidian 端插件：[obsidian-typecho-publisher](https://github.com/bluemoon23333/obsidian-typecho-publisher)

---

## 许可证

MIT License
