# 设计文档 (Design)

## 1. 设计原则
- **简约性**: 减少冗余装饰，强调内容本身。
- **一致性**: 全站统一使用 Bootstrap 5 规范，通过 CSS 变量强制覆盖默认主题色。
- **响应式**: 严格适配移动端，保证在小屏幕下的可操作性（折叠导航、单列卡片）。

## 2. 视觉规范
### 核心配色
- **主色调 (Primary)**: `#2c3e50` (深蓝)
  - 应用：前台导航栏、主按钮、链接、输入框聚焦边框。
- **辅助色 (Success)**: `#1abc9c` (浅绿)
  - 应用：成功提示、后台统计徽章、正面状态标识。
- **危险色 (Danger)**: `#dc3545` (标准红)
  - 应用：删除操作、错误提示、必填项星号。
- **背景色**: `#f6f8fb` (淡灰)，减少视觉疲劳。

### 排版与字体
- **字体**: 优先使用系统默认无衬线字体，正文基准 16px。
- **布局**: PC 端最大宽度 1200px 居中；移动端全屏流式布局。

## 3. UI 组件库
### 导航栏 (Navbar)
- **前台**: 使用主色调 `#2c3e50` 背景，当前激活项底部显示白色下划线指示器。
- **后台**: 独立设计，使用纯黑背景，仅保留管理相关入口，与前台形成强烈的视觉区分。

### 卡片 (Cards)
- **基础样式**: `border-0`, `shadow-sm`, `rounded-sm` (0.25rem)。
- **微交互**: Hover 时触发 `transform: translateY(-4px)` 上浮动画，并加深阴影。

### 按钮 (Buttons)
- **Primary**: 填充主色，用于“发布”、“登录”等核心操作。
- **Outline**: 描边样式，用于“取消”、“返回”等次级操作。
- **交互**: Hover 透明度统一为 0.9。

### 对话框 (Modals)
- **全站统一**: 严禁使用原生 `alert`/`confirm`。
- **实现**: 封装 `showModal()` 与 `showConfirmModal()`，提供一致的圆角、阴影与动效体验。

### 表单 (Forms)
- **校验反馈**: 采用 `Floating Labels` 提升体验，错误提示统一显示在输入框**下方**（红色小字）。
- **必填标识**: 标签后跟随红色星号 `*`。

## 4. 数据库设计
- **users**: 用户表
  - `id`, `username`, `password` (Hash), `mobile`, `role`, `status`, `create_time`
- **posts**: 帖子表
  - `id`, `user_id`, `title`, `content` (HTML), `status`, `create_time`, `update_time`
- **comments**: 评论表
  - `id`, `post_id`, `user_id`, `content`, `status`, `create_time`
