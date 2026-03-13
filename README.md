
<p align="center">
  <img src="app/desktop/statics/opensource-logo.png" alt="ONEX OMS Logo" width="320" />
</p>
<div>

![PHP >=8.0](https://img.shields.io/badge/PHP-%3E%3D8.0-blue.svg)&nbsp;
![License Apache-2.0](https://img.shields.io/badge/License-Apache--2.0-green.svg)&nbsp;
![Version 8.0.0](https://img.shields.io/badge/Version-8.0.0-orange.svg)

</div>

## 📋 目录
+ [项目介绍](#项目介绍)
+ [核心特性](#核心特性)
+ [系统要求](#系统要求)
+ [安装指南](#安装指南)
+ [配置说明](#配置说明)
+ [任务管理](#任务管理)
+ [开发文档](#开发文档)
+ [常见问题](#常见问题)
+ [许可证](#许可证)

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

### 💰 财务管理
+ **订单财务**：订单收入、成本、利润统计
+ **物流费用管理**：物流费用统计和结算
+ **财务报表**：支持多种财务报表和统计

### 售后管理
+ **仅退款**：发货前直接同意退款；发货后多为**退差价**或异常场景（如丢件）按仅退款处理。
+ **退货退款**：用户寄回商品后按质检与入库结果执行退款。
+ **换货**：通常拆分为“**换入**（用户寄回）”与“**换出**（商家重发）”两个流程，支持校验“换入完成后再换出”。
+ **补发**：不退货直接补发，生成新订单进入正向履约。
+ 扩展类型：支持**维修、服务单、赔付单**等，用于更细分的服务场景



### 🔌 系统集成
+ **OpenAPI 接口**：提供基于类方法的统一 API 接口
+ **WMS 集成**：支持奇门WMS场景标准对接
+ **POS 系统集成**：支持 POS 系统对接
+ **快递鸟集成**：支持快递鸟电子面单、智选物流等功能
+ **开票系统集成**：支持电子发票、纸质发票等开票系统对接

### 🔐 权限管理
+ **角色权限**：支持多角色、多权限的细粒度权限控制
+ **组织架构**：支持多级组织架构和权限继承
+ **操作日志**：完整的操作日志记录和审计

---

## 🚀 安装快速入口

**Docker 一键安装**（需已安装 Docker 与 Docker Compose >= 2.0）：

**Linux / macOS：**
```bash
curl -fsSL https://oss.shopex.cn/oms/install.sh | sudo bash -s -- -s --auto-install --admin-pass MyPass123
```

**Windows（在 PowerShell 中执行）：**
```powershell
wsl -d Ubuntu -e bash -c "curl -fsSL https://oss.shopex.cn/oms/install.sh | sudo bash -s -- -s --auto-install --admin-pass MyPass123"
```
> **注意**：Windows 必须启用 WSL2，详见[部署指南](https://doc.shopex.cn/oms/200.install-deploy/deployment-guide.html)

将 `MyPass123` 改为你的管理员密码。安装后访问 http://opensource-oms.local（需在 `/etc/hosts` 添加 `127.0.0.1 opensource-oms.local`）。

- 浏览器安装（最快）：[部署指南 - 浏览器安装](https://doc.shopex.cn/oms/200.install-deploy/deployment-guide.html#production-deployment)
- 命令行安装（可脚本化）：[部署指南 - 命令行](https://doc.shopex.cn/oms/200.install-deploy/deployment-guide.html#production-deployment)
- Docker 手动部署：[部署指南 - Docker](https://doc.shopex.cn/oms/200.install-deploy/deployment-guide.html#docker-deployment)

更多细节：
- 完整部署步骤：[部署指南](https://doc.shopex.cn/oms/200.install-deploy/deployment-guide.html)
- 开发环境搭建：[开发者指南](https://doc.shopex.cn/oms/100.rule/1.code.html)

---

## 📄 许可证
本项目采用 Apache-2.0 许可证。

+ 英文原版：[LICENSE](LICENSE)

---

## 🤝 贡献
请先阅读 `.github/CONTRIBUTING.md`，了解提交流程、分支/提交规范、测试要求。更多开发规则见 [开发者指南](https://doc.shopex.cn/oms/100.rule/1.code.html)。

---

## 📞 支持
+ **文档中心**：[https://doc.shopex.cn/oms/100.rule/1.code.html](https://doc.shopex.cn/oms/100.rule/1.code.html)
+ **问题反馈**：[GitHub Issues](https://github.com/ShopeX/OMS/issues)
+ **开发指南**：[PHP编码规范](https://doc.shopex.cn/oms/100.rule/1.code.html)

---

## 🎉 致谢
感谢所有为 ONEX OMS 项目做出贡献的开发者和用户们！

----

**最后更新**：2026年3月  
**版本**：8.0.0

