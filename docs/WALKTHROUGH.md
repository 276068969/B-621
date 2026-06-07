# 项目 Walkthrough (代码导览)

## 1. 布局入口: `includes/layout.php`
这是全站最核心的视图控制文件：
- **L40-L60**: 定义了符合设计要求的全局 CSS（配色、卡片动画、校验样式）。
- **L65-L110**: 实现前后台 Topbar 的条件渲染逻辑。
- **L175-L210**: 注入了全局 Modal 与 Toast 逻辑，确保交互的一致性。

## 2. 交互逻辑: `public/login.php` & `public/register.php`
展示了表单优化的最佳实践：
- 使用 `needs-validation` 类配合 JS 实现即时反馈。
- `Floating Labels` 提升了在移动端的操作体验。

## 3. 后台逻辑: `public/admin/`
- **index.php**: 展示了如何通过简单 CSS 组合实现高颜值的仪表盘。
- **posts.php / comments.php**: 展示了 Modal 替代原生 Confirm 的具体调用方式。

## 4. 样式定制: `includes/helpers.php`
- 包含分页、数据库连接等核心工具，代码风格统一，逻辑简洁。
