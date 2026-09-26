# goldentra 应用

## 主档（唯一码 / materialAdd）

- 表：`sdb_goldentra_material`；字段字典：`lib/material/fields.php`（27 个 POS 字段 + 处理状态）
- OpenAPI：`goldentra.material.add`（SAP 主档）
- OMS 必填：UNIQUE_CODE、GOODS_NAME、MOLD_CODE/MOLD_NAME、CATE/CLASS/STYLE/TEXTURE 编码名称、PRICEDIMENSION_CODE、SALES_MODE(默认1)、PRICE_METHOD、WEIGHT(默认0)
- 钻石件（FCOLOR+FPURITY 均非空）：另必填 FCUT、FCERTIFICATE
- 可选：SMALL_SERIES_NAME、PRICE、MAIN/SECONDARY_WEIGHT、PROCESS_COST、MATERIAL_CODE、UNIT
- 计价方式：POS `jj`/`dj` → OMS `1`/`2`
- Mapping：`sdb_goldentra_mapping` / `_item`；定时 `goldentramaterial` 转基础物料
- SAP `goldentra.material.add`：HTTP 只拆批入 `sdb_goldentra_queue`（每批 200），任务 `goldentramaterialsave` 异步落库后再投 `goldentramaterial`（每批 100）；接口成功 ≠ 主档已全部写入

## 每日金价（priceAdd）

- 表：`sdb_goldentra_daily_goldprice`；字段字典：`lib/daily/goldprice/fields.php`
- 唯一键：`shop_bn` + `price_date` + `pricedimension_code`
- OpenAPI：`goldentra.goldprice.add`；XML：`priceAdd`
- POS 必填：SHOP_BN、DATE、GOLD_PRICE、PRICEDIMENSION_CODE
- 新增 OMS 必填：PRICEDIMENSION_NAME、FCODE（变价更新时不校验、不更新这两项）
- SHOP_BN 可映射（如 9898→9801200）；DATE 经 `date2time`；同价跳过，变价只更新 `gold_price`
- 计价类（jj）用 `pricedimension_code` 刷店铺 SKU 价

## 投资金价（SAP 下发，≠每日金价）

- 表：`sdb_goldentra_invest_goldprice`；字段：`lib/invest/goldprice/fields.php`
- OpenAPI：`goldentra.investgoldprice.add`
- 字段：requestId/ZIDNUM、syncType/ZJGBG、salesOrganize/VKORG、brandId/ZPP、categoryCode/MATKL、price/ZJG_ZTJJ、itemCode/MATNR、addPrice/ZJG_ZTJ1
- 唯一键：`sales_organize + brand_id + category_code + item_code`
- 文档：`docs/goldentra/OpenAPI接口文档-投资金价与销售回调.md`

## 库存同步

- 字段字典：`lib/stock/fields.php`
- 明细五字段：`BRANCH_BN`、`UNIQUE_CODE`、`QTY`、`TYPE`（increment/full）、`SNAPSHOT_DATE`（YYYY-MM-DD）
- 落库 `snapshot_date` 规范为 `YYYY-MM-DD 00:00:00`（19 位版本比较）

### 通道

| 类型 | 通道 | 说明 |
|------|------|------|
| 增量 | OpenAPI `goldentra.stock.add` | 明细 `TYPE=increment`；顶层 `sync_batch_no` |
| 全量 | FTP `ZPOSSK_YYYYMMDD.csv` | 无表头，分号分隔 5 列；明细 `TYPE=full` |

**FTP 配置：**

- 后台菜单：**库存FTP配置**（`admin_stock_ftp`），保存至 app conf，优先级最高
- 亦可使用 `config/goldentra_stock_ftp.php` 或环境变量（见 sample 文件）

CSV 示例：`9199000;1234567890123;1;full;2026-06-02`

### 流程

- 表：`sdb_goldentra_stock`（版本键 `branch_bn + unique_code + snapshot_date`）
- 落库 `pending` → `goldentrastock` / `goldentrastockftp` 批量比对 SN
- **比对源**：机器中心 `sdb_machinecenter_material_sn`（`status=1` 在库；`status=0` 已出库禁止调增）
- 依赖应用：`machinecenter` 须已安装
- 全量：流式读 + 3000 条批量 INSERT + SQL 批量 UPDATE

## OpenAPI（应用内）

- 配置：`app/goldentra/lib/openapi/conf.php`
- 主档：`goldentra.material.add`
- 每日金价：`goldentra.goldprice.add`
- 投资金价：`goldentra.investgoldprice.add`
- 库存增量：`goldentra.stock.add`；全量走 FTP，不走 OpenAPI
- SAP 对接统一走 OpenAPI（`/openapi`），不单独提供 `/posapi` 渠道

## 更新

```bash
docker exec yy-httpd-php82 sh -c 'cd /var/www/html/app/base && php ./cmd update'
```

配置 taskmgr 白名单任务 `goldentramaterialsave`、`goldentramaterial`、`goldentramaterialsaveready`、`goldentrastock`、`goldentrastockftp`。
