# Bug 修复报告 - 评论区互动工具栏 JavaScript 错误

**修复日期**: 2026-06-11
**影响页面**: `public/post.php` (帖子详情页)
**严重程度**: 🔴 高 - 导致评论区工具栏和长评论导航功能完全不可用

---

## 1. 问题描述

### 1.1 错误现象
帖子详情页加载评论区时，浏览器控制台报错：
```
TypeError: originalUpdateProgress is not a function
    at updateReadingProgress (post.php?id=2:1:20139)
```

### 1.2 影响范围
- ❌ 评论工具栏按钮（引用/复制/分享）功能异常
- ❌ 评论高亮定位功能失效
- ❌ 长评论串浮动回复按钮不显示
- ❌ 底部「返回发表评论」按钮点击无响应
- ❌ 引用回复功能不可用

---

## 2. 根因分析 (Root Cause)

### 2.1 技术背景
问题出在 JavaScript 函数声明提升（Hoisting）机制。

### 2.2 错误代码分析
**原代码（错误实现）**：
```javascript
// 原始函数定义（在脚本前部）
function updateReadingProgress() {
  // 阅读进度条更新逻辑
}
window.addEventListener("scroll", updateReadingProgress);

// ... 其他代码 ...

// 尝试重写函数（在脚本后部，新增的代码）
var originalUpdateProgress = updateReadingProgress;  // ❌ 问题所在
function updateReadingProgress() {                   // ❌ 函数声明提升
  originalUpdateProgress();                          // ❌ 递归调用自身！
  // 新增的浮动按钮逻辑
}
window.addEventListener("scroll", updateReadingProgress);  // ❌ 重复添加监听器
```

### 2.3 根本原因
1. **函数声明提升**：在同一作用域内，两个同名的 `function updateReadingProgress()` 声明都会被提升到作用域顶部，**第二个声明会覆盖第一个**
2. **时序错误**：`var originalUpdateProgress = updateReadingProgress` 执行时，`updateReadingProgress` 已经是新函数（因为提升），不是原函数
3. **无限递归**：新函数调用 `originalUpdateProgress()` 实际上是调用自己，导致栈溢出
4. **重复事件监听器**：两次 `addEventListener` 导致滚动时函数被调用两次

---

## 3. 修复方案

### 3.1 修复思路
1. **合并函数**：将原阅读进度逻辑和新增的浮动按钮逻辑合并到一个新函数 `updateReadingProgressAll()` 中
2. **正确移除旧监听器**：先 `removeEventListener` 移除旧的滚动监听器，再添加新的
3. **避免重复声明**：不再重名声明，使用全新的函数名
4. **修复评论点击事件**：点击评论区时过滤工具栏按钮和锚点链接的点击，避免冲突

### 3.2 修复后的代码
**修复位置**: [post.php#L599-L655](file:///c:/Users/guich/Desktop/title/B-621/public/post.php#L599-L655)

```javascript
// 合并后的完整函数（包含原逻辑 + 新功能）
function updateReadingProgressAll() {
  // --- 原有阅读进度逻辑 ---
  var progressBar = document.getElementById("readingProgressBar");
  var progressPercent = document.getElementById("readingPercent");
  var toolbar = document.getElementById("readingToolbar");
  var postContent = document.getElementById("post-content");
  if (postContent) {
    var windowHeight = window.innerHeight;
    var docHeight = document.documentElement.scrollHeight;
    var scrollTop = window.scrollY || document.documentElement.scrollTop;
    var scrollPercent = Math.min(100, Math.max(0, Math.round((scrollTop / (docHeight - windowHeight)) * 100)));
    if (progressBar) progressBar.style.height = scrollPercent + "%";
    if (progressPercent) progressPercent.textContent = scrollPercent + "%";
    if (toolbar) {
      if (scrollTop > 200) {
        toolbar.style.opacity = "1";
        toolbar.style.pointerEvents = "auto";
      } else {
        toolbar.style.opacity = "0";
        toolbar.style.pointerEvents = "none";
      }
    }
  }
  
  // --- 新增浮动按钮逻辑 ---
  var floatingReplyBtn = document.getElementById("floating-reply-btn");
  var replySection = document.getElementById("reply-section");
  var commentsSection = document.getElementById("comments-section");
  if (floatingReplyBtn && replySection && commentsSection) {
    var replyRect = replySection.getBoundingClientRect();
    var commentsRect = commentsSection.getBoundingClientRect();
    var scrollTop = window.scrollY || document.documentElement.scrollTop;
    if (scrollTop > commentsRect.top + 300 && replyRect.top > window.innerHeight) {
      floatingReplyBtn.style.display = "flex";
      setTimeout(function() { floatingReplyBtn.style.opacity = "1"; }, 10);
    } else {
      floatingReplyBtn.style.opacity = "0";
      setTimeout(function() { floatingReplyBtn.style.display = "none"; }, 300);
    }
  }
}

// 正确替换监听器
window.removeEventListener("scroll", updateReadingProgress);
window.addEventListener("scroll", updateReadingProgressAll);
updateReadingProgressAll();  // 初始化调用
```

### 3.3 附加修复
**评论点击事件优化** ([post.php#L641-L655](file:///c:/Users/guich/Desktop/title/B-621/public/post.php#L641-L655))：
```javascript
var commentsList = document.getElementById("comments-list");
if (commentsList) {
  commentsList.addEventListener("click", function(e) {
    var commentItem = e.target.closest(".comment-item");
    var isToolbarBtn = e.target.closest(".comment-tool-btn");  // ✅ 新增
    var isAnchor = e.target.closest(".comment-anchor");         // ✅ 新增
    if (commentItem && !isToolbarBtn && !isAnchor) {            // ✅ 过滤
      var commentId = commentItem.getAttribute("data-comment-id");
      if (history.pushState && window.location.hash !== "#comment-" + commentId) {
        history.pushState(null, "", "#comment-" + commentId);
        highlightComment(commentId);  // ✅ 新增高亮调用
      }
    }
  });
}
```

---

## 4. 验证结果

### 4.1 错误消除
- ✅ 控制台不再出现 `TypeError: originalUpdateProgress is not a function`
- ✅ JavaScript 代码无语法错误（括号匹配：`() 317/317`, `{} 103/103`）

### 4.2 功能测试

| 功能模块 | 测试操作 | 预期结果 | 实际结果 | 状态 |
| :--- | :--- | :--- | :--- | :--- |
| **评论工具栏** | 鼠标悬停评论 | 显示「引用/复制/分享」按钮 | 按钮正确显示 | ✅ 通过 |
| **复制按钮** | 点击「复制」 | 1. 内容复制到剪贴板<br>2. 按钮变绿色 1.5 秒 | 功能正常 | ✅ 通过 |
| **分享按钮** | 点击「分享」 | 1. URL hash 更新为 `#comment-X`<br>2. 评论高亮显示<br>3. Toast 提示 | 功能正常 | ✅ 通过 |
| **评论高亮** | URL 带 `#comment-3` 访问 | 第 3 条评论脉动高亮 3 秒 | 高亮动画正常 | ✅ 通过 |
| **浮动回复按钮** | 滚动超过评论区 300px | 底部中央显示「写评论」浮动按钮 | 按钮正确显示和隐藏 | ✅ 通过 |
| **底部返回按钮** | 评论 ≥5 条时 | 评论区底部显示「返回发表评论」按钮 | 按钮显示正常 | ✅ 通过 |
| **hashchange 事件** | 点击评论（非按钮区域） | URL hash 更新，评论高亮 | 功能正常 | ✅ 通过 |

### 4.3 响应式测试
- **桌面端**: 工具栏悬停显示，按钮带文字
- **移动端**: 工具栏始终显示，按钮仅图标

---

## 5. 经验教训

### 5.1 避免的反模式
1. ❌ **不要重名声明函数**：在同一作用域内避免同名函数声明
2. ❌ **不要假设执行顺序**：函数声明会被提升，变量赋值不会
3. ❌ **不要重复添加事件监听器**：替换监听器前务必先移除

### 5.2 推荐的最佳实践
1. ✅ **使用唯一函数名**：扩展功能时用新名字，如 `updateReadingProgress` → `updateReadingProgressAll`
2. ✅ **先移除后添加**：替换事件监听器的标准模式
   ```javascript
   window.removeEventListener('scroll', oldHandler);
   window.addEventListener('scroll', newHandler);
   ```
3. ✅ **代码合并优于函数包装**：能合并逻辑就不要用函数嵌套调用
4. ✅ **点击事件过滤**：事件委托时注意过滤子元素的点击

---

## 6. 相关文件

| 文件 | 修改内容 |
| :--- | :--- |
| [public/post.php](file:///c:/Users/guich/Desktop/title/B-621/public/post.php) | 修复 JavaScript 函数声明提升错误，优化事件处理 |

---

## 7. 变更历史

| 日期 | 变更 | 作者 |
| :--- | :--- | :--- |
| 2026-06-11 | 初始修复，解决 `originalUpdateProgress is not a function` 错误 | 开发团队 |
