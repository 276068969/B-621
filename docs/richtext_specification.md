# 论坛富文本发布规范

## 概述

本文档定义了论坛系统中富文本内容的统一处理规范，确保「发布帖子」「后台帖子编辑」「帖子列表摘要生成」与「帖子详情展示」四个场景下，内容格式保持一致。

## 一、允许保留的 HTML 标签白名单

以下标签是系统允许保留的格式标签，其他标签将被自动过滤：

| 标签 | 说明 |
|------|------|
| `<p>` | 段落 |
| `<br>` | 换行 |
| `<strong>`, `<b>` | 加粗 |
| `<em>`, `<i>` | 斜体 |
| `<u>` | 下划线 |
| `<ul>`, `<ol>`, `<li>` | 列表（有序/无序） |
| `<blockquote>` | 引用 |
| `<code>`, `<pre>` | 代码 |
| `<a>` | 链接 |
| `<h1>`-`<h6>` | 标题 |
| `<hr>` | 分隔线 |
| `<span>` | 行内容器（无属性） |

### 禁用的属性

以下属性将被自动移除：
- 所有事件属性：`onclick`, `onload`, `onerror` 等
- `style` 属性
- `class` 属性
- `id` 属性

### 链接处理规范

所有 `<a>` 标签将被统一处理：
- 自动添加 `target="_blank"` 在新窗口打开
- 自动添加 `rel="noopener noreferrer"` 安全属性
- `javascript:` 协议的链接将被替换为 `#`
- 空链接（`<a></a>`）将被移除

## 二、空段落与冗余标记清理

### 空段落清理

以下情况的空段落将被移除：
- `<p></p>` - 完全空的段落
- `<p>&nbsp;</p>` - 只包含空格的段落
- `<p><br></p>` - 只包含换行的段落
- `<div>&nbsp;</div>` - 空 div（div 标签本身也会被过滤）

### 冗余格式标记清理

以下空的格式标签将被移除：
- `<span></span>`, `<span>&nbsp;</span>`
- `<strong></strong>`, `<b></b>`
- `<em></em>`, `<i></i>`
- `<u></u>`

### 多余换行清理

- 连续 2 个以上 `<br>` 将被合并为 `<br><br>`
- 段落开头和结尾的 `<br>` 将被移除
- 连续 3 个以上 `</p>` 将被合并

## 三、内容处理流程

### 发布/编辑流程

```
用户输入 → 内容审核 → 规范化处理 → 入库存储
                    ↑
            normalize_rich_html()
```

**规范化处理时机**：在内容入库前进行，确保数据库中存储的是规范后的内容。

**涉及文件**：
- [post_add.php](file:///c:/Users/guich/Desktop/title/B-621/public/post_add.php#L74-L82) - 发布帖子
- [post_edit.php](file:///c:/Users/guich/Desktop/title/B-621/public/post_edit.php#L101-L112) - 用户编辑帖子
- [admin/post_edit.php](file:///c:/Users/guich/Desktop/title/B-621/public/admin/post_edit.php#L71-L99) - 后台编辑帖子

### 展示流程

```
数据库读取 → sanitize_rich_html() → 页面渲染
```

**涉及文件**：
- [post.php](file:///c:/Users/guich/Desktop/title/B-621/public/post.php#L138) - 帖子详情页
- [admin/post_preview.php](file:///c:/Users/guich/Desktop/title/B-621/public/admin/post_preview.php#L71) - 后台预览

### 摘要生成流程

```
数据库读取 → sanitize_rich_html() → 去除标签 → get_post_excerpt() → 列表展示
```

**涉及文件**：
- [index.php](file:///c:/Users/guich/Desktop/title/B-621/public/index.php#L458) - 帖子列表
- [admin/posts.php](file:///c:/Users/guich/Desktop/title/B-621/public/admin/posts.php#L259) - 后台帖子列表
- [profile.php](file:///c:/Users/guich/Desktop/title/B-621/public/profile.php#L240) - 用户主页
- [history.php](file:///c:/Users/guich/Desktop/title/B-621/public/history.php#L120) - 浏览历史
- [favorites.php](file:///c:/Users/guich/Desktop/title/B-621/public/favorites.php#L174) - 收藏列表

## 四、核心函数说明

所有函数定义在 [helpers.php](file:///c:/Users/guich/Desktop/title/B-621/includes/helpers.php) 中。

### 1. `get_allowed_html_tags()`

获取允许的 HTML 标签字符串，用于 `strip_tags()` 函数。

```php
function get_allowed_html_tags(): string
```

### 2. `get_allowed_html_tag_array()`

获取允许的 HTML 标签数组，用于需要数组形式的场景。

```php
function get_allowed_html_tag_array(): array
```

### 3. `normalize_rich_html(string $html): string`

**用途**：内容入库前的规范化处理

**处理步骤**：
1. 移除不在白名单内的标签
2. 移除禁用的属性（事件、style、class、id）
3. 清理空段落和冗余标记
4. 合并多余换行
5. 统一处理链接属性
6. 清理空链接
7. 移除首尾多余的换行

### 4. `sanitize_rich_html(string $html): string`

**用途**：内容展示前的安全清洗

**处理步骤**：
1. 移除不在白名单内的标签
2. 移除禁用的属性
3. 统一处理链接属性

> **注意**：此函数主要用于已规范化内容的二次安全校验，新增内容应优先使用 `normalize_rich_html()`。

### 5. `get_post_excerpt(string $content, string $keyword = '', int $length = 140): string`

**用途**：生成帖子摘要

**参数**：
- `$content` - 原始富文本内容
- `$keyword` - 搜索关键词（可选），用于上下文定位
- `$length` - 摘要长度，默认 140 字符

**处理逻辑**：
1. 清洗 HTML 标签，获取纯文本
2. 合并多余空白字符
3. 无关键词时：截取前 `$length` 字符
4. 有关键词时：定位关键词位置，截取前后文，优先展示关键词所在的上下文

### 6. `highlight_keyword_in_text(string $text, string $keyword): string`

**用途**：在文本中高亮搜索关键词

**参数**：
- `$text` - 纯文本内容
- `$keyword` - 搜索关键词

**处理**：用 `<mark class="search-highlight">` 标签包裹匹配的关键词。

## 五、一致性保证

### 统一的数据存储

所有帖子内容在入库前都经过 `normalize_rich_html()` 处理，确保：
- 数据库中存储的内容格式统一
- 不同入口展示同一篇内容时效果一致
- 避免重复清洗导致的性能损耗

### 统一的摘要生成

所有列表页面使用同一个 `get_post_excerpt()` 函数，确保：
- 摘要长度统一（可通过参数调整）
- 搜索时的上下文定位逻辑一致
- 省略号的添加规则统一

### 统一的安全策略

所有 HTML 输出都经过白名单过滤，确保：
- XSS 攻击防护
- 防止恶意脚本注入
- 链接安全属性统一

## 六、编辑器配置

前端使用 TinyMCE 编辑器，工具栏配置与后端白名单保持一致：

```javascript
toolbar: "undo redo | bold italic | bullist numlist | link | removeformat"
```

**已禁用功能**：
- 图片上传
- 表格
- 字体颜色/背景色
- 对齐方式

## 七、规范维护

如需调整富文本规范，请按以下步骤操作：

1. 修改 `get_allowed_html_tags()` 和 `get_allowed_html_tag_array()` 中的标签白名单
2. 调整 `normalize_rich_html()` 中的清理规则
3. 更新 TinyMCE 编辑器的 toolbar 配置
4. 更新本文档

### 历史数据处理

对于规范发布前已存在的数据，可以通过以下 SQL 更新：

```sql
-- 注意：执行前请先备份数据库
-- 需要在 PHP 脚本中调用 normalize_rich_html() 处理每条记录
```
