<?php
/**
 * Typecho RESTful 插件扩展：媒体库同步支持
 *
 * 为 Obsidian 媒体库同步增加以下能力，不影响原有接口的兼容性：
 * 1. POST /api/postArticle 新增 fields 参数：批量写入/更新自定义字段
 * 2. POST /api/postArticle 新增 type 参数：支持创建独立页面 (page)
 *
 * 安装方式：将本文件放到 typecho-plugin-Restful/ 目录下，
 * 不会覆盖原文件，而是通过 Plugin.php 的 Action 类需要手动合并。
 *
 * 更简单的做法是直接修改 Action.php 的 postArticleAction 方法，
 * 在原有逻辑中追加以下两段代码即可。
 *
 * ========== 修改指南 ==========
 *
 * 一、支持 fields 参数（批量自定义字段写入）
 *
 * 在 Action.php 的 postArticleAction() 方法中，
 * 找到 // 自定义文章缩略图（cid 确定后写入 fields 表） 之后的代码块，
 * 在 // 分类/标签 之前，加入以下逻辑：
 *
 * -----------------------------------------------------------
 *     // 批量写入自定义字段（由 Obsidian 媒体同步插件使用）
 *     $fields = $this->getParams('fields', null);
 *     if (is_array($fields) && !empty($fields)) {
 *         foreach ($fields as $fieldName => $fieldValue) {
 *             if (is_string($fieldName) && is_string($fieldValue)) {
 *                 $this->setField($cid, $fieldName, $fieldValue);
 *             }
 *         }
 *     }
 * -----------------------------------------------------------
 *
 * 二、支持 type 参数（创建独立页面而非文章）
 *
 * 在 postArticleAction() 中，找到 $postData 数组定义的地方：
 *
 *     $postData = array(
 *         'title' => $title,
 *         'text' => $text,
 *         'authorId' => $authorId,
 *         'slug' => $slug,
 *     );
 *
 * 在这之后加入：
 *
 * -----------------------------------------------------------
 *     // 支持创建独立页面（由 Obsidian 媒体同步插件使用）
 *     $contentType = $this->getParams('type', 'post');
 *     if (in_array($contentType, array('post', 'page'), true)) {
 *         $postData['type'] = $contentType;
 *     }
 * -----------------------------------------------------------
 *
 * 三、将 setField 方法可见性从 private 改为 public
 *
 * 如果选择不直接在 Action.php 中迭代 fields，
 * 而是让 Obsidian 插件多次调用 setField 接口，
 * 需要把 Action.php 第 1475 行的
 *     private function setField
 * 改为
 *     public function setField
 *
 * 推荐方案：直接在 postArticleAction 中支持 fields 参数（方案一），
 * 这样一次 API 调用就能完成文章创建 + 所有自定义字段写入。
 */

// 本文件仅作为文档参考，实际修改请编辑 Action.php
return;

/**
 * 如果希望通过独立路由来设置自定义字段，可以使用以下 Action 方法
 * （需要在 Plugin.php 中注册，但 getRoutes() 自动发现，所以只需添加到 Action.php）
 *
 * POST /api/setField  JSON: {cid: int, name: string, value: string}
 */
/*
public function setFieldAction()
{
    $this->lockMethod('post');
    $this->checkState('setField');
    $this->checkLogin();

    $cid = intval($this->getParams('cid', 0));
    $name = $this->getParams('name', '');
    $value = $this->getParams('value', '');

    if (empty($cid) || empty($name)) {
        $this->throwError('missing cid or name');
    }

    $this->setField($cid, $name, $value);
    $this->throwData(array('success' => true));
}
*/
