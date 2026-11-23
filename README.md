# Shopex OMS

Shopex OMS（订单管理系统）：PHP 8 后端 + Vue 3 前端 + MCP 接口。

仓库结构
------------
前后端分离 & MCP 改造后，系统由三个仓库组成：

| 仓库 | 地址 | 说明 |
| ---- | ---- | ---- |
| 本仓库 | （PHP 后端主仓库） | `app/`（63 个业务模块）、`tests/`、`docker/`、`upgrade/`；`public/`、`assets/`、根 `index.html` 为前端构建产物部署位置 |
| 前端 | `oms-system-iframeui.git` | Vue 3 + Element Plus SPA，clone 到本仓库同级或 `frontend/` 目录（本仓库不跟踪该目录内容） |
| MCP 服务 | `oms-system-mcp.git` | TypeScript MCP Server（stdio / Streamable HTTP），调用本仓库 `app/mcpapi/` 暴露的 MCPAPI |

后端 MCP 接口由 `app/mcpapi/` 模块提供，通过 `/index.php/mcp-api/v1/*` 路由访问，详见 `app/mcpapi/README.md`。

开发前准备事项
------------
- GIT提交风格统一
  1. 下载template仓库
  2. 执行``` ln -s <your_path>/template/hooks/commit-msg .git/hooks/ ```
  3. 执行``` chmod +x <your_path>/template/hooks/commit-msg  ```

安装注意事项
------------
- 准备工作
  1. 先安装好composer并执行``` composer install --no-dev ```
  2. 如果是Apache, 开启RewriteEngine
    ```
      LoadModule rewrite_module modules/mod_rewrite.so
    ```
    ```
      <VirtualHost *:80>
          DocumentRoot "/var/www/html/oms"
          ServerName xxx.com

![PHP >=8.0](https://img.shields.io/badge/PHP-%3E%3D8.0-blue.svg)&nbsp;
![License Apache-2.0](https://img.shields.io/badge/License-Apache--2.0-green.svg)&nbsp;
![Version 8.3.0](https://img.shields.io/badge/Version-8.3.0-orange.svg)

</div>

          <Directory "/var/www/html/oms">
              Options FollowSymLinks
              AllowOverride None
              Require all granted

---

## 🎯 项目介绍
ONEX OMS 是由 [商派 ShopeX](https://www.shopex.cn) 开发并开源的企业级全渠道订单管理系统，基于 PHP 开发，专为电商、零售、分销等丰富的业务场景设计。系统采用模块化架构，功能完善、高度模块化，支持多平台订单统一处理、智能库存管理、全流程仓储管理等核心功能。

### 适用场景
+ **电商平台订单管理**：支持品牌私域、淘宝、京东、拼多多等多个电商平台的订单统一处理
+ **多仓库库存管理**：支持多仓库、多门店的库存统一管理和调拨
+ **供应链管理**：支持采购、入库、出库、盘点等完整的仓储业务流程
+ **财务对账管理**：支持订单财务、物流费用管理等
+ **全渠道业务协同**：公域与私域业务、线上与线下业务、2B和2C业务融合打通，实现资源协同

### 技术架构
+ **后端框架**：基于自研 PHP 框架，采用 MVC 分层架构
+ **数据库**：MySQL 5.7+ / MySQL 8.0+
+ **任务队列**：基于 Swoole 的秒级队列任务系统
+ **缓存系统**：支持文件缓存和 KV 存储
+ **API 集成**：支持 OpenAPI、ERP 接口等多种集成方式

---

## ✨ 核心特性
### 📦 订单管理
+ **多平台订单统一处理**：支持品牌私域、淘宝、京东、拼多多、抖音等多个电商平台
+ **订单全生命周期管理**：从订单创建、审核、调度、发货到售后全流程管理
+ **智能订单调度**：支持自动分配仓库、自动匹配物流
+ **异常订单处理**：缺货订单、异常订单自动识别和处理
+ **批量订单处理**：支持批量审核、批量发货、批量打印等操作

### 📊 库存管理
+ **库存状态**：区分**实际库存、可用库存、锁定/预占库存、在途库存、次品库存**，支撑销售、退仓、调拨等多场景口径统一。
+ **多仓库库存管理**：支持多仓库、多门店的库存统一管理
+ **智能库存计算**：基于规则引擎的库存计算和分配
+ **库存同步**：支持多平台库存自动同步和回写
+ **库存预警**：支持库存上下限预警和缺货提醒
+ **退货入库**：按**良/不良**质检结果分别入**正品/次品库存**，更新**在途/可用**并触发退款或二次销售流程。

### 🏪 商品管理
+ **商品档案管理**：支持基础物料和销售物料的分类管理
    - **基础物料**：供应链管理的货品最小单位，包含普通商品、赠品等，是仓库操作发货单时的最小单位货品
    - **销售物料**：由基础物料组成的带有销售性质的物料，是前端销售平台销售的商品
+ **商品批量操作**：支持批量导入、批量编辑
+ **商品图片管理**：支持单张图片上传和管理

### 🏬 仓库管理
+ **多仓库支持**：支持多仓库、多门店的仓储管理
+ **出入库管理**：支持采购入库、销售出库、调拨、盘点等操作
+ **库存盘点**：支持库存盘点和盘点表确认

前端
------------
前端代码在独立仓库 `oms-system-iframeui.git`，clone 到 `frontend/` 后本地开发：

```bash
cd frontend
pnpm install
pnpm dev          # Vite 开发服务，端口 3333
```

- 开发前把 `oms.local`、`oms-frontend.local` 映射到 `127.0.0.1`，浏览器访问 `http://oms-frontend.local:3333`（与后端 Cookie 同源）
- Vite 会把 `/index.php`、`/mcp-api` 等请求代理到 `OMS_PHP_PROXY_TARGET`（默认 `http://oms.local`，即 `httpd-php82` 的容器网络别名）
- 生产构建执行 `pnpm build:prod`，将 `dist/` 产物（`index.html` + `assets/`）发布到本仓库根目录

MCP 接入
------------
- 后端 MCPAPI 路由统一为 `POST/GET /index.php/mcp-api/v1/*`（注意：不带 `/index.php` 前缀的裸 `/mcp-api/*` 会被 rewrite 进前端 SPA，不是有效 API 入口）
- 认证使用 OMS 客户端凭证：`Authorization: Basic base64(client_id:client_secret)`
- 客户端凭证在 OMS 后台「修改密码 → MCP接入凭证」创建/轮换/吊销，secret 仅创建时展示一次；凭证继承所属账号当前的角色权限，账号禁用或凭证吊销即时生效
- MCP Server（`oms-system-mcp.git`）以 stdio 或 Streamable HTTP 方式对外提供 120+ 业务工具，配置 `SHOPEX_OMS_BASE_URL`（以 `/index.php` 结尾）、`SHOPEX_OMS_CLIENT_ID`、`SHOPEX_OMS_CLIENT_SECRET` 三个环境变量后启动，详见该仓库 README

Task任务执行
------------
- 如何启动task任务
  1. task任务采用swoole进程池管理模式，默认启动全部任务：
     ```bash
     php app/taskmgr/sw.php worker:start -d
     ```
  2. 资源有限时可按类型或任务名选择性启动（`--type` 与 `--task` 取并集；都不传则全量）：
     ```bash
     # 查看可用类型与任务名
     php app/taskmgr/sw.php worker:list

     # 按类型：task / timer / init / export / rpc / finance（别名 import=finance）
     php app/taskmgr/sw.php worker:start -d --type=export
     php app/taskmgr/sw.php worker:start -d --type=export,finance

     # 按具体任务名
     php app/taskmgr/sw.php worker:start -d --task=exportsplit,createfile
     php app/taskmgr/sw.php worker:start -d --task=assign,import

     # 类型 + 任务名（并集）
     php app/taskmgr/sw.php worker:start -d --type=export --task=assign
     ```
  3. task任务是秒级队列任务，具体配置请参考``` app/taskmgr/lib/whitelist.php ```
