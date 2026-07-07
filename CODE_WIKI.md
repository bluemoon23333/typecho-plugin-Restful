# Typecho Plugin Restful — Code Wiki

## 1. 项目概述

**项目名称**: `typecho-plugin-Restful`
**版本**: 1.2.0
**类型**: Typecho 插件
**作者**: MoeFront Studio (kirainmoe, kokororin)
**许可证**: MIT
**仓库**: https://github.com/moefront/typecho-plugin-Restful
**Composer**: `moefront/typecho-plugin-restful`

### 1.1 项目定位

这是一个将 Typecho 博客系统 RESTful 化的插件。启用后，可通过 HTTP API (JSON) 访问和操作 Typecho 站点的内容，包括文章、页面、评论、分类、标签、用户信息、文件上传等。本质上是在 Typecho 原生功能之上封装了一层 RESTful API 接口。

### 1.2 核心功能

| 功能                               | HTTP 方法 | 端点                  |
| ---------------------------------- | --------- | --------------------- |
| 文章列表（支持分类/标签/搜索过滤） | GET       | `/api/posts`          |
| 页面列表                           | GET       | `/api/pages`          |
| 分类列表                           | GET       | `/api/categories`     |
| 标签列表                           | GET       | `/api/tags`           |
| 文章/页面详情（含 CSRF Token）     | GET       | `/api/post`           |
| 评论列表（树形结构、分页）         | GET       | `/api/comments`       |
| 最近评论                           | GET       | `/api/recentComments` |
| 发表评论                           | POST      | `/api/comment`        |
| 站点设置                           | GET       | `/api/settings`       |
| 用户信息与文章                     | GET       | `/api/users`          |
| 文章归档                           | GET       | `/api/archives`       |
| 用户列表                           | GET       | `/api/userList`       |
| 发表/更新文章                      | POST      | `/api/postArticle`    |
| 新增分类/标签                      | POST      | `/api/addMetas`       |
| 上传文件                           | POST      | `/api/upload`         |
| 删除文件                           | POST      | `/api/deleteFile`     |
| 文件列表                           | GET       | `/api/fileList`       |
| 插件自更新                         | GET       | `/api/upgrade`        |

---

## 2. 项目整体架构

```
typecho-plugin-Restful/
├── Plugin.php              # 插件入口：注册路由、配置面板、生命周期
├── Action.php              # 核心：所有 API Action 方法的实现 (~1349行)
├── Util.php                # 工具类：文件上传兼容处理
├── tests/
│   ├── RestfulTest.php     # PHPUnit 集成测试（14 个测试方法）
│   ├── Util.php            # 测试辅助工具（下载/安装 Typecho、数据库）
│   ├── bootstrap.php       # 测试引导（启动内建服务器）
│   ├── typecho.sql         # 测试数据库 SQL
│   └── test.jpg            # 测试用图片文件
├── composer.json           # Composer 依赖管理
├── phpunit.xml             # PHPUnit 配置
├── phpcs.xml               # PHP CodeSniffer 规则
├── .travis.yml             # Travis CI 配置
├── .github/workflows/test.yml  # GitHub Actions 测试
├── CHANGELOG.md            # 变更日志
└── README.md               # 使用文档（API 参数说明）
```

### 2.1 架构图

```
┌──────────────────────────────────────────────────────────────────┐
│                     HTTP Request (JSON/Form)                      │
└──────────────────────────────┬───────────────────────────────────┘
                               │
┌──────────────────────────────▼───────────────────────────────────┐
│                      Plugin.php (Plugin)                          │
│  实现 PluginInterface:                                            │
│  - activate(): 注册 16 条自定义路由到 Typecho Router               │
│  - deactivate(): 移除路由                                         │
│  - config(): 配置面板 (API开关/CORS/CSRF/Token)                   │
│  - comment(): 评论过滤器 (注入真实IP)                              │
└──────────────────────────────┬───────────────────────────────────┘
                               │ 路由分发 (Helper::addRoute)
                               ▼
┌──────────────────────────────────────────────────────────────────┐
│           Action.php (Action extends Request)                     │
│  实现 ActionInterface:                                            │
│  - __construct(): 初始化 DB/Options/Config/Request/Response       │
│  - execute(): 入口 → sendCORS() → parseRequest() → 动态调用Action │
│  - getRoutes(): 反射扫描所有 *Action() 方法 → 生成路由表           │
│                                                                   │
│  公共 Action 方法 (16个):                                         │
│  postsAction | pagesAction | categoriesAction | tagsAction        │
│  postAction | commentsAction | recentCommentsAction               │
│  commentAction | settingsAction | usersAction                     │
│  archivesAction | userListAction | postArticleAction              │
│  addMetasAction | uploadAction | deleteFileAction                 │
│  fileListAction | upgradeAction                                   │
│                                                                   │
│  内部辅助方法:                                                     │
│  sendCORS | parseRequest | getParams | throwError | throwData     │
│  lockMethod | checkState | checkLogin | getCustomFields           │
│  articleFilter | buildNodes | recursion | generateCsrfToken       │
│  checkCsrfToken | safelyParseMarkdown | refreshMetas              │
└──────────────────────────────┬───────────────────────────────────┘
                               │
                   ┌───────────┼───────────┐
                   ▼           ▼           ▼
            ┌──────────┐ ┌─────────┐ ┌──────────┐
            │Util.php  │ │Typecho  │ │Typecho   │
            │          │ │Widget   │ │Db        │
            │- 文件上传 │ │- Comments│ │- 查询构建 │
            │  兼容处理 │ │- Contents│ │- CRUD    │
            └──────────┘ │- Upload  │ └──────────┘
                         │- Options │
                         └─────────┘
```

### 2.2 路由注册机制（反射）

`getRoutes()` 使用 PHP 反射 API 自动发现所有 Action 方法:

```php
// 遍历 Action 类的所有 public 方法
// 匹配方法名模式: {name}Action
// 自动生成路由:
//   名称: rest_{name}
//   短名: {name}
//   URI:  {prefix}{name}  (如 /api/posts)
//   描述: 从 DocComment 提取
```

这意味着**新增一个 API 只需在 Action.php 中添加一个名为 `xxxAction` 的 public 方法**，无需手动注册路由。

---

## 3. 模块详细说明

### 3.1 `Plugin.php` — 插件入口与配置

**类**: `TypechoPlugin\Restful\Plugin implements PluginInterface`

#### 3.1.1 生命周期方法

| 方法           | 说明                                                                   |
| -------------- | ---------------------------------------------------------------------- |
| `activate()`   | 遍历 getRoutes() → 为每条路由调用 `Helper::addRoute()`；注册评论过滤器 |
| `deactivate()` | 遍历 getRoutes() → 为每条路由调用 `Helper::removeRoute()`              |

返回值为颜文字 (`_(:з」∠)_` / `( ≧Д≦)`)，显示在插件管理页面。

#### 3.1.2 配置面板 `config(Form $form)`

提供以下配置项:

| 配置项                               | 类型        | 默认值         | 说明                                                          |
| ------------------------------------ | ----------- | -------------- | ------------------------------------------------------------- |
| **API 状态开关** (每个路由)          | Radio (0/1) | 1 (启用)       | 可单独禁用任一 API 端点                                       |
| **域名列表 (origin)**                | Textarea    | —              | CORS 允许的源站域名，一行一个；`*` 为通配符                   |
| **自定义字段过滤 (fieldsPrivacy)**   | Text        | —              | 在文章详情中隐藏的字段名，逗号分隔                            |
| **设置项白名单 (allowedOptions)**    | Text        | —              | 允许通过 `/api/settings?key=` 查询的 options 表字段           |
| **CSRF 加密盐 (csrfSalt)**           | Text        | `05faabd66...` | 用于 CSRF Token 生成的 HMAC 密钥                              |
| **API Token (apiToken)**             | Text        | `123456`       | API 请求需携带的 Token（header: `token`），留空不校验         |
| **高敏接口登录校验 (validateLogin)** | Radio (0/1) | 0              | 开启后 postArticle/addMetas/upload/deleteFile 需要登录 Cookie |

**嵌入 JavaScript**: 面板中包含一个 AJAX 调用 `/api/upgrade` 的按钮，实现插件自更新。

#### 3.1.3 评论过滤器

`comment($comment, $post)` — 从请求头 `HTTP_X_TYPECHO_RESTFUL_IP` 读取自定义 IP，注入到评论数据中。

---

### 3.2 `Action.php` — API 核心实现

**类**: `TypechoPlugin\Restful\Action extends Request implements ActionInterface`

#### 3.2.1 核心属性

| 属性                   | 类型     | 说明                                                     |
| ---------------------- | -------- | -------------------------------------------------------- |
| `$config`              | Config   | 插件配置对象                                             |
| `$db`                  | Db       | Typecho 数据库实例                                       |
| `$options`             | Options  | Typecho 站点选项                                         |
| `$httpParams`          | array    | POST 请求体 JSON 解析结果                                |
| `$request`             | Request  | Typecho Widget Request                                   |
| `$response`            | Response | Typecho Widget Response                                  |
| `$jsonParseSkipRoutes` | array    | POST 时跳过 JSON 解析的路由短名列表 (默认: `['upload']`) |

#### 3.2.2 请求处理流程

```
HTTP Request
  │
  ▼
execute()
  ├─► sendCORS() — 检查 Origin、设置 Access-Control 头、处理 OPTIONS 预检
  ├─► parseRequest() — POST 时解析 JSON body → $this->httpParams
  └─► 动态调用: $this->{路由短名 . "Action"}()
        │
        ├─► lockMethod() — 限制 GET/POST
        ├─► checkState() — 检查 API 是否启用、Token 是否有效
        ├─► checkLogin() — 高敏接口校验登录状态
        ├─► getParams() — 获取 GET/POST 参数
        ├─► 业务逻辑 ...
        └─► throwData() / throwError() — JSON 响应
```

#### 3.2.3 安全机制

| 机制           | 实现位置                                   | 说明                                                                         |
| -------------- | ------------------------------------------ | ---------------------------------------------------------------------------- |
| **CORS**       | `sendCORS()`                               | 校验 HTTP_ORIGIN 是否在配置的白名单中；处理 OPTIONS 预检                     |
| **API Token**  | `checkState()`                             | 从请求 header `token` 读取并比对 `config->apiToken`                          |
| **API 开关**   | `checkState()`                             | 检查 `config->{route}` 是否为 1                                              |
| **CSRF Token** | `generateCsrfToken()` / `checkCsrfToken()` | 基于 HMAC-SHA256 的双重哈希：`HMAC(HMAC(date+IP+UA, SHA256(key)), csrfSalt)` |
| **登录校验**   | `checkLogin()`                             | 高敏接口检查 `Widget_User->hasLogin()`                                       |
| **字段过滤**   | `getCustomFields()`                        | 按 `fieldsPrivacy` 过滤文章自定义字段                                        |
| **邮箱脱敏**   | `buildNodes()`                             | 评论返回中移除 `mail`，仅保留 `mailHash` (MD5)                               |

#### 3.2.4 响应格式

**成功**:
```json
{ "status": "success", "message": "", "data": { ... } }
```

**错误**:
```json
{ "status": "error", "message": "错误描述", "data": null }
```

抛出方式: `throwError()` 设置 HTTP 状态码，`throwData()` 默认 200。

#### 3.2.5 各 Action 方法详解

##### GET 类（读取数据）

| Action                 | 锁定的方法 | 参数                                                                                                      | 说明                                                                                                  |
| ---------------------- | ---------- | --------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| `postsAction`          | GET        | page, pageSize, filterType(category/tag/search), filterSlug, showContent, showDigest(more/excerpt), limit | 文章列表。支持按分类/标签/搜索过滤。showDigest='more' 使用 `<!--more-->` 截断；'excerpt' 使用字符截断 |
| `pagesAction`          | GET        | —                                                                                                         | 独立页面列表                                                                                          |
| `categoriesAction`     | GET        | —                                                                                                         | 所有分类                                                                                              |
| `tagsAction`           | GET        | —                                                                                                         | 所有标签                                                                                              |
| `postAction`           | GET        | cid 或 slug                                                                                               | 文章详情。包含 CSRF Token、自定义字段、permalink                                                      |
| `commentsAction`       | GET        | page, pageSize, order(asc/desc), cid/slug                                                                 | 评论列表。树形结构，带 Cookie 显示待审核评论                                                          |
| `recentCommentsAction` | GET        | size (默认9)                                                                                              | 最近评论                                                                                              |
| `settingsAction`       | GET        | key (可选)                                                                                                | 站点设置。受 allowedOptions 白名单控制                                                                |
| `usersAction`          | GET        | uid 或 name                                                                                               | 用户信息及该用户的文章列表                                                                            |
| `archivesAction`       | GET        | order, showContent, showDigest, limit                                                                     | 按年月归档的文章                                                                                      |
| `userListAction`       | GET        | —                                                                                                         | 全部用户列表                                                                                          |
| `fileListAction`       | GET        | page, pageSize, authorId                                                                                  | 附件文件列表（分页）                                                                                  |

##### POST 类（写入数据）

| Action              | 锁定的方法 | 参数                                                                            | 说明                                                                                |
| ------------------- | ---------- | ------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------- |
| `commentAction`     | POST       | cid/slug, text(*), mail(*), author(*), token(*), parent, authorId, ownerId, url | 发表评论。校验 CSRF Token 和邮箱格式                                                |
| `postArticleAction` | POST       | title(*), text(*), authorId(*), slug, mid                                       | 新增/更新文章。slug 优先匹配更新，否则按 title 匹配。mid 为分类/标签 ID（逗号分隔） |
| `addMetasAction`    | POST       | name(*), type(*)(category/tag), slug                                            | 新增分类或标签                                                                      |
| `uploadAction`      | POST       | file(*), cid, authorId                                                          | 文件上传。支持 multipart 和 Uint8Array/base64/JSON 多种格式                         |
| `deleteFileAction`  | POST       | cid(*)                                                                          | 删除文件附件                                                                        |
| `upgradeAction`     | GET        | —                                                                               | 从 GitHub 拉取最新 Plugin.php/Action.php 覆盖本地。需管理员权限                     |

#### 3.2.6 关键内部方法

**`articleFilter($value)` — 文章数据补全**

对数据库原始行数据进行标准化处理:
1. `Contents::filter()` — Typecho 原生文章过滤器（生成 date 对象、格式化等）
2. `safelyParseMarkdown()` — Markdown → HTML 转换
3. `getCustomFields()` — 附加自定义字段（过滤隐私字段）
4. 生成 `permalink` / `url` / `pathinfo`
5. 补充 `year/month/day` 字段

**`buildNodes($comments) → recursion()` — 评论树构建**

两层递归构建:
```
1. buildNodes: 遍历评论，按 parent==0 分为根评论/子评论
              构建 childMap[parentId] → [index, ...]
2. recursion: 递归挂载 children 数组
```

**`generateCsrfToken($key) / checkCsrfToken($key, $token)`**

```
token = base64(HMAC-SHA256(
  HMAC-SHA256(date+Ymd + IP + UA, SHA256(key)),
  csrfSalt
))
```
- `$key` = 文章 permalink（每篇文章独立 Token）
- 使用 `hash_equals()` 防止时序攻击

**`getParams($key, $default)` — 统一参数获取**

- GET 请求: 从 `$this->request->get()` 获取
- POST 请求: 从 `$this->httpParams`（JSON body）获取

**`refreshMetas($midArray)` — 刷新分类/标签文章计数**

更新文章的分类/标签关联后，重新计算 `table.metas.count`。

**`checkLogin()` — 高敏接口登录校验**

当 `config->validateLogin == 1` 时检查登录状态，未登录返回 401。

---

### 3.3 `Util.php` — 文件上传兼容工具

**类**: `TypechoPlugin\Restful\Util` (静态方法)

**`getUploadFile($files, $request)` — 多格式文件上传兼容**

支持 3 种上传方式:

| 格式                    | 来源                                                  | 处理方式                              |
| ----------------------- | ----------------------------------------------------- | ------------------------------------- |
| **multipart/form-data** | `$_FILES`                                             | 标准 PHP 文件上传处理，URL 解码文件名 |
| **Uint8Array (JSON)**   | POST body JSON `{file: [0-255,...], fileName: "..."}` | `pack('C*', ...$bytes)` 还原为二进制  |
| **base64**              | POST body JSON `{file: "base64string"}` 或 data URL   | `base64_decode()` 还原                |

返回值统一为:
```php
['name' => string, 'bytes' => binary_string, 'size' => int]
```

**二进制安全处理**: Uint8Array 元素会被 clamp 到 [0, 255] 范围再 pack。

---

### 3.4 测试体系

#### 3.4.1 测试架构

```
bootstrap.php
  ├─► Util::downloadTypecho()    下载 Typecho v1.2.1 压缩包并解压
  ├─► Serve::start()             启动 PHP 内建服务器 (127.0.0.1:2333)
  └─► Util::installTypecho()     创建数据库 → 导入 SQL → 生成配置文件 → 安装插件
```

**数据库**: 使用独立的 `typecho_test_db`，每次测试前 DROP + CREATE。

#### 3.4.2 测试覆盖 (RestfulTest.php)

| 测试方法          | 验证点                                                        |
| ----------------- | ------------------------------------------------------------- |
| `testPosts`       | 文章列表分页结构正确性                                        |
| `testPages`       | 页面列表返回结构                                              |
| `testCategories`  | 分类列表 status=success                                       |
| `testTags`        | 标签列表 status=success                                       |
| `testPost`        | 文章详情返回 data 为数组                                      |
| `testComments`    | 评论列表分页结构                                              |
| `testComment`     | 评论发表：无 token 拒绝、无效邮箱拒绝、正常评论写入数据库验证 |
| `testSettings`    | 设置返回 title/description/keywords/timezone                  |
| `testUsers`       | 用户信息含 posts 数组                                         |
| `testArchives`    | 归档含 count 和 dataSet                                       |
| `testUserList`    | 用户列表为数组                                                |
| `testPostArticle` | 发表文章后数据库 count=1                                      |
| `testAddMetas`    | 新增标签后数据库存在记录                                      |
| `testUpload`      | 文件上传 → 数据库验证 → 文件列表查询 → 文件删除               |

**HTTP 客户端**: GuzzleHttp\Client, 配置 base_uri、token header、Origin header

---

## 4. 关键数据流

### 4.1 读请求数据流（以 postsAction 为例）

```
GET /api/posts?filterType=category&filterSlug=tech&page=1&pageSize=5
  │
  ▼
execute()
  ├─► sendCORS() — Origin 校验，设置 Access-Control 头
  ├─► parseRequest() — GET 请求无需解析 JSON
  └─► $this->postsAction()
        │
        ├─► lockMethod('get')
        ├─► checkState('posts') — 检查 API 开关 + Token
        │
        ├─► 参数获取: getParams('filterType') → 'category'
        │             getParams('filterSlug') → 'tech'
        │
        ├─► 查询 metas 表获取 mid → relationships 表获取 cid 列表
        │
        ├─► 构建查询:
        │   SELECT cid, title, created, ... FROM contents
        │   WHERE type='post' AND status='publish'
        │   AND password IS NULL
        │   AND cid IN (...)
        │   ORDER BY created DESC
        │
        ├─► 先 COUNT → 再 OFFSET+LIMIT
        │
        ├─► 逐行 articleFilter() 处理:
        │   Contents::filter() → Markdown→HTML → 自定义字段 → permalink
        │
        └─► throwData({page, pageSize, pages, count, dataSet})
```

### 4.2 写请求数据流（以 commentAction 为例）

```
POST /api/comment  (Content-Type: application/json)
Body: {"cid": 1, "text": "Great!", "author": "User", "mail": "u@test.com", "token": "xxx..."}
  │
  ▼
execute()
  ├─► parseRequest() — json_decode → $this->httpParams
  └─► $this->commentAction()
        │
        ├─► lockMethod('post')
        ├─► checkState('comment')
        │
        ├─► 参数校验:
        │   缺少必填(text/mail/author/token) → 400
        │   邮箱格式不合法 → 400
        │
        ├─► 查询文章: SELECT ... FROM contents WHERE cid=? OR slug=?
        │   ├─► 不存在 → 404
        │   └─► articleFilter() → 获取 permalink
        │
        ├─► CSRF 校验: checkCsrfToken(permalink, token)
        │   └─► 不匹配 → 400 "token invalid"
        │
        ├─► 构建评论数据:
        │   { text, mail, cid, author, parent?, authorId?, ownerId?, url? }
        │
        ├─► 检查登录 Cookie: $uid = Cookie::get('__typecho_uid')
        │   └─► 已登录 → 覆盖 authorId = uid
        │
        ├─► Comments::insert($postData)
        │
        └─► 查询刚插入的评论 → throwData()
```

### 4.3 文件上传数据流

```
POST /api/upload  (Content-Type: multipart/form-data 或 application/json)
  │
  ▼
execute()
  ├─► parseRequest() — 路由"upload"在 jsonParseSkipRoutes 中，跳过 JSON 解析
  └─► $this->uploadAction()
        │
        ├─► lockMethod('post'), checkState('upload'), checkLogin()
        │
        ├─► Util::getUploadFile($_FILES, $request):
        │   ├─ $_FILES 非空 → 标准文件上传
        │   └─ $_FILES 为空 → 从 POST body 中提取:
        │       ├─ 直接取 'file' 参数
        │       ├─ 或解析 JSON → json['file'] / json['fileName']
        │       ├─ 数组(Uint8Array) → pack('C*', ...)
        │       ├─ base64(data:;base64,) → base64_decode()
        │       └─ 纯字符串 → 尝试 base64_decode，失败则原样
        │
        ├─► Upload::uploadHandle($file) → 物理文件存储
        │   └─► 失败 → 500
        │
        ├─► Upload::insert({title, slug, type:'attachment', text: json_encode(meta), ...})
        │
        └─► throwData({cid, title, type, size, url, host})
```

---

## 5. 依赖关系

### 5.1 运行时依赖

| 依赖              | 类型   | 说明                                              |
| ----------------- | ------ | ------------------------------------------------- |
| PHP >= 5.3.0      | 运行时 | Typecho 运行环境                                  |
| ext-curl          | 运行时 | HTTP 请求（插件升级功能）                         |
| Typecho Framework | 运行时 | Db, Widget, Router, Request, Response, Options 等 |

### 5.2 模块间依赖

```
Plugin.php
  ├─► Action.php (通过 ACTION_CLASS 常量引用)
  │     ├─► Util.php (uploadAction 中文件处理)
  │     ├─► Typecho\Db (数据库操作)
  │     ├─► Typecho\Widget\Request + Response (请求/响应)
  │     ├─► Widget\Base\Comments (评论 CRUD)
  │     ├─► Widget\Base\Contents (文章/附件 CRUD)
  │     ├─► Widget\Base\Metas (分类/标签)
  │     ├─► Widget\Upload (文件上传处理)
  │     ├─► Widget\Options (站点配置)
  │     └─► Utils\Markdown (Markdown→HTML)
  └─► Typecho\Plugin (插件框架)
```

### 5.3 开发依赖 (composer.json require-dev)

| 依赖                                    | 用途                      |
| --------------------------------------- | ------------------------- |
| `catfan/medoo` ~1.5                     | 测试中数据库操作          |
| `guzzlehttp/guzzle` ~6.0                | 测试中 HTTP 客户端        |
| `phpunit/phpunit` ^6.0                  | 单元测试框架              |
| `squizlabs/php_codesniffer` ^3.2        | 代码风格检查 (PSR2 派生)  |
| `ssx/php-serve` ^0.0.3                  | 测试中启动 PHP 内建服务器 |
| `guiguiboy/php-cli-progress-bar` ^0.0.4 | 测试下载进度条            |

---

## 6. 项目运行方式

### 6.1 安装

**方式一：手动安装**
```bash
# 下载插件压缩包，解压并重命名为 Restful
# 放到 Typecho 的 usr/plugins/ 目录下
# 到 Typecho 后台 → 插件管理 → 启用
```

**方式二：Composer 安装**
```bash
cd /path/to/typecho/usr/plugins
composer create-project moefront/typecho-plugin-restful Restful --prefer-dist --stability=dev
chown www:www -R Restful
```

### 6.2 自定义 API 前缀

在 Typecho 的 `config.inc.php` 中添加:
```php
define('__TYPECHO_RESTFUL_PREFIX__', '/rest/');
```
然后重新启用插件，API 端点将变为 `/rest/*`。

### 6.3 伪静态 (URL Rewrite)

若站点未开启地址重写，所有 API 路径前需加 `/index.php`:
```
/api/posts  →  /index.php/api/posts
```

### 6.4 开发与测试

```bash
# 安装开发依赖
composer install

# 运行测试 (PHPUnit + PHPCS)
composer test

# 仅 PHPUnit
composer phpunit

# 仅代码风格检查
composer phpcs

# 自动修复代码风格
composer phpcbf
```

**测试环境要求**:
- MySQL 5.7 (127.0.0.1:3306, root/123456)
- PHP 7.4+
- 测试数据库: `typecho_test_db`
- PHP 内建服务器端口: 2333

**CI/CD**:
- GitHub Actions: 推送到 `chore/ci-*` 分支或 PR 到 master 时运行
- Travis CI: 所有 push 运行

---

## 7. 扩展点

| 位置                               | 扩展方向                         |
| ---------------------------------- | -------------------------------- |
| `Action.php` 新增 `xxxAction()`    | 添加新 API 端点（路由自动注册）  |
| `Plugin.php::config()`             | 新增配置项                       |
| `Action.php::$jsonParseSkipRoutes` | 添加不需要 JSON 解析的 POST 路由 |
| `Util.php`                         | 新增文件格式兼容逻辑             |

**添加新 API 的步骤**:
1. 在 Action.php 中添加 `public function xxxAction()` 方法
2. 方法内部调用 `lockMethod()`, `checkState()`, `getParams()`, `throwData()`
3. DocComment 将自动成为路由描述
4. 路由 URI 自动生成为 `{prefix}/xxx`

---

## 8. 注意事项

1. **Typecho 版本兼容**: 插件不兼容 Typecho 1.2 以前版本。适配了 1.2.x 和 1.3.x。

2. **permalink 兼容**: Typecho 1.3 的 `Contents::filter()` 不再自动生成 permalink，插件在 `articleFilter()` 中手动补充 `Router::url()` + `Common::url()` 生成。

3. **评论树结构**: `buildNodes()` 使用索引引用而非递归查询，O(n) 时间复杂度。评论邮件地址被替换为 MD5 哈希 (`mailHash`) 后再返回，保护用户隐私。

4. **CSRF Token 设计**: Token 绑定日期（每天过期）、IP、UA 和具体文章的 permalink，防止跨站请求和跨文章 Token 复用。使用 `hash_equals()` 防止时序攻击。

5. **文件上传兼容**: `Util::getUploadFile()` 支持 3 种上传格式（multipart、Uint8Array JSON、base64），兼容 Web 前端和移动端的各种 HTTP 客户端。

6. **JSON 解析跳过**: `uploadAction` 的路由在 `jsonParseSkipRoutes` 中，因为 multipart 上传的 body 不是 JSON 格式，跳过解析避免报错。

7. **代码风格**: 禁用 PHP 短数组语法 (`array()` 而非 `[]`)，主要遵循 PSR2 标准（有部分排除项）。

8. **文章/页面更新逻辑**: `postArticleAction` 优先按 slug 匹配已有文章，若无 slug 则按 title 匹配。若匹配到则执行 UPDATE，否则 INSERT。更新时会先删除旧的分类/标签关联（relationships 表）再重建。
