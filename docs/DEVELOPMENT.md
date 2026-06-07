# 开发文档 (Development)

## 1. 环境搭建
项目基于 Docker 开发，请确保已安装 Docker 与 Docker Compose。

### 启动步骤
```bash
docker compose build --no-cache
docker compose up -d
```
访问地址：`http://localhost:8922`

## 2. 编码规范
- **PHP**: 遵循 PSR-12 标准，使用 `declare(strict_types=1)`。
- **HTML/CSS**: 遵循 Bootstrap 5 结构，自定义 CSS 放置在 `layout.php` 的 `<style>` 块中。
- **JS**: 尽量使用原生 JS，避免过度依赖外部库（除已引入的 Bootstrap/TinyMCE 外）。

## 3. 核心 API/函数
- `db($config)`: 获取 PDO 连接实例。
- `render_header($config, $params)`: 渲染通用页头（包含导航栏）。
- `render_footer()`: 渲染通用页脚（包含 JS 逻辑）。
- `flash()`: 获取/设置闪存消息（用于跳转提示）。

## 4. 提交规范
- 修复 Bug: `fix: xxx`
- 新功能: `feat: xxx`
- 优化 UI: `ui: xxx`
