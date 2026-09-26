-- 从旧表结构（dgp_id + create_time）迁移到文档结构
ALTER TABLE sdb_goldentra_daily_goldprice
  CHANGE dgp_id goldprice_id int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  ADD COLUMN data_source enum('sap','pos') NOT NULL DEFAULT 'sap' COMMENT '数据来源' AFTER goldprice_id,
  ADD COLUMN sync_batch_no varchar(64) DEFAULT NULL COMMENT '同步批次号' AFTER data_source,
  CHANGE create_time at_time int(10) unsigned NOT NULL DEFAULT 0 COMMENT '创建时间',
  ADD COLUMN up_time int(10) unsigned NOT NULL DEFAULT 0 COMMENT '更新时间' AFTER at_time,
  MODIFY fcode varchar(50) DEFAULT NULL COMMENT '金价单号',
  MODIFY pricedimension_name varchar(50) DEFAULT NULL COMMENT '定价维度名称',
  ADD INDEX ind_shop_bn (shop_bn),
  ADD INDEX ind_price_date (price_date);

UPDATE sdb_goldentra_daily_goldprice SET up_time = at_time WHERE up_time = 0;
