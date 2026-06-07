# Lite Forum - 轻量级论坛系统

本项目是一个基于 PHP 8.2 + MySQL 8.0 + Bootstrap 5 构建的现代化轻量级论坛系统。严格遵循定制化设计规范，提供流畅的用户体验与高效的管理后台。

## 🌟 核心特性
- **UI/UX 深度重构**: 
  - 严格执行配色规范：主色调 `#2c3e50` (深蓝)，辅助色 `#1abc9c` (浅绿)，危险色 `#dc3545`。
  - 现代化组件：卡片悬浮动效、圆角按钮、全站统一间距。
- **沉浸式交互**: 
  - 全局移除原生 `alert/confirm`，封装通用 Bootstrap Modal 对话框。
  - 表单校验采用 Floating Labels + 即时红字提示。
  - 消息通知 (Toast) 支持自动消失。
- **独立管理视图**: 
  - 后台采用独立的黑色 Topbar 布局，与前台严格区分。
  - 提供数据概览、内容软删除及前台预览入口。
- **全站汉化**: 从导航文案、表单提示到系统报错，实现 100% 中文化覆盖。
- **响应式架构**: 完美适配移动端（折叠导航、单列布局）与桌面端（1200px 居中）。

## 🚀 快速启动
确保环境中已安装 Docker。

```bash
git clone <repository-url>
cd lite-stack
docker compose up -d
```
访问：`http://localhost:8922`

## 🔗 服务与账号
| 服务/入口 | 地址 | 账号 | 密码 | 说明 |
| :--- | :--- | :--- | :--- | :--- |
| **前台首页** | [http://localhost:8922](http://localhost:8922) | `demo` | `123456` | 演示用户，可发帖/评论 |
| **后台管理** | [http://localhost:8922/admin/login.php](http://localhost:8922/admin/login.php) | `admin` | `123456` | 管理员，可删除内容/查看统计 |
| **数据库** | `localhost:3922` | `root` | `root` | 数据库名: `forum` |

> 注：首次启动时，系统会自动初始化数据库并写入上述演示数据。

## 📖 详细文档
请查阅 `/docs` 目录：
- [架构说明](./docs/ARCHITECTURE.md)
- [设计规范](./docs/DESIGN.md)
- [开发指南](./docs/DEVELOPMENT.md)
- [测试报告](./docs/TESTING.md)
- [用户手册](./docs/USER_MANUAL.md)

## ⚖️ 版权信息
版权所有 © 2026 Lite Forum
