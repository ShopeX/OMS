<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

/**
 * 地区管理（eccommon/regions）写能力层 —— 唯一认可的写实现。
 *
 * 链路：
 *   Vue `ctl=api_regions&act=addDlArea|saveDlArea|toRemoveArea|updateOrderNum`
 *     → eccommon_ctl_api_regions → 本 capability → eccommon_regions_operation / eccommon_mdl_regions
 *   legacy `eccommon_ctl_regions::addDlArea()/saveDlArea()/toRemoveArea()/updateOrderNum()`
 *     → 本 capability（委托同一实现，消除双写出口）
 *
 * 校验 / 归一化 / 持久化 / 事务全部收敛在此层；控制器只做「权限注解 + 入参解析 + 响应格式化」。
 *
 * 事务口径：
 * - api 侧（ownTransaction=true）由本 capability 显式 begin/commit/rollBack 包住整组写；
 * - legacy 侧（ownTransaction=false）沿用 desktop_controller::begin()/end() 托管的事务，
 *   与迁移前行为一致（legacy 控制器 app/eccommon/controller/regions.php:85-205）。
 */
class eccommon_capability_regions
{
    /** @var eccommon_regions_operation */
    private $operation;

    /** @var eccommon_mdl_regions */
    private $model;

    public function __construct($operation = null, $model = null)
    {
        $this->operation = $operation ?: kernel::single('eccommon_regions_operation');
        $this->model = $model ?: app::get('eccommon')->model('regions');
    }

    /**
     * 新建地区（legacy: `eccommon_ctl_regions::addDlArea()` regions.php:85-99）
     *
     * @param array<string,mixed> $data local_name / p_region_id / ordernum
     *
     * @return array{ok:bool,msg:string}
     */
    public function addArea(array $data, bool $ownTransaction = true): array
    {
        $msg = '';
        if ($ownTransaction) {
            $this->begin();
        }

        try {
            $ok = (bool) $this->operation->insertDlArea($data, $msg);
        } catch (Throwable $exception) {
            if ($ownTransaction) {
                $this->rollBack();
            }
            throw $exception;
        }

        if (!$ok) {
            if ($ownTransaction) {
                $this->rollBack();
            }
            return ['ok' => false, 'msg' => (string) $msg];
        }

        if ($ownTransaction) {
            $this->commit();
        }

        return ['ok' => true, 'msg' => '新建成功，地区名称：' . (string) ($data['local_name'] ?? '')];
    }

    /**
     * 修改地区（legacy: `eccommon_ctl_regions::saveDlArea()` regions.php:101-112）
     *
     * @param array<string,mixed> $data region_id / p_region_id / local_name / ordernum
     *
     * @return array{ok:bool,msg:string}
     */
    public function saveArea(array $data, bool $ownTransaction = true): array
    {
        $msg = '';
        if ($ownTransaction) {
            $this->begin();
        }

        try {
            $ok = (bool) $this->operation->updateDlArea($data, $msg);
        } catch (Throwable $exception) {
            if ($ownTransaction) {
                $this->rollBack();
            }
            throw $exception;
        }

        if (!$ok) {
            if ($ownTransaction) {
                $this->rollBack();
            }
            return ['ok' => false, 'msg' => (string) $msg];
        }

        if ($ownTransaction) {
            $this->commit();
        }

        return ['ok' => true, 'msg' => '修改成功，地区名称：' . (string) ($data['local_name'] ?? '')];
    }

    /**
     * 删除地区（legacy: `eccommon_ctl_regions::toRemoveArea()` regions.php:132-160；
     * 「已有订单使用」守卫逐字沿用 legacy 的 ship_area LIKE 判据）。
     *
     * @return array{ok:bool,msg:string}
     */
    public function removeArea($regionId, bool $ownTransaction = true): array
    {
        if ($ownTransaction) {
            $this->begin();
        }

        try {
            $regionDetail = $this->model->getDlAreaById($regionId);

            $area = '';
            if ((string) ($regionDetail['region_grade'] ?? '') === '3') {
                $area = '%' . $regionDetail['parent_name'] . '/' . $regionDetail['local_name'] . ':' . $regionDetail['region_id'];
            } elseif ((string) ($regionDetail['region_grade'] ?? '') === '2') {
                $area = 'mainland:' . $regionDetail['parent_name'] . '/' . $regionDetail['local_name'] . '/%';
            } elseif ((string) ($regionDetail['region_grade'] ?? '') === '1') {
                $area = 'mainland:' . $regionDetail['local_name'] . '/%';
            }

            $orders = $this->model->db->selectrow("SELECT order_id FROM sdb_ome_orders WHERE ship_area LIKE '" . $area . "'");
            if ($orders) {
                if ($ownTransaction) {
                    $this->rollBack();
                }
                return ['ok' => false, 'msg' => '该地区已有订单使用,不可以删除!'];
            }

            $ok = (bool) $this->operation->toRemoveArea($regionId);
        } catch (Throwable $exception) {
            if ($ownTransaction) {
                $this->rollBack();
            }
            throw $exception;
        }

        if (!$ok) {
            if ($ownTransaction) {
                $this->rollBack();
            }
            return ['ok' => false, 'msg' => '删除地区失败！'];
        }

        if ($ownTransaction) {
            $this->commit();
        }

        return ['ok' => true, 'msg' => '删除地区成功！'];
    }

    /**
     * 批量保存排序（legacy: `eccommon_ctl_regions::updateOrderNum()` regions.php:165-193）
     *
     * @param array<int|string,mixed> $pOrder legacy `$_POST['p_order']`：{region_id: ordernum}
     *
     * @return array{ok:bool,msg:string}
     */
    public function updateOrderNum(array $pOrder, bool $ownTransaction = true): array
    {
        if ($ownTransaction) {
            $this->begin();
        }

        try {
            $isUpdate  = true;
            $arrRegions = [];
            foreach ($pOrder as $key => $strPOdr) {
                $arrdArea             = $this->model->dump($key, 'region_id,ordernum');
                $arrdArea['ordernum'] = $strPOdr ? $strPOdr : '50';
                $arrRegions[]         = $arrdArea;
            }

            foreach ($arrRegions as $arrRegionsinfo) {
                $isUpdate = $this->model->save($arrRegionsinfo);
            }
        } catch (Throwable $exception) {
            if ($ownTransaction) {
                $this->rollBack();
            }
            throw $exception;
        }

        if (!$isUpdate) {
            if ($ownTransaction) {
                $this->rollBack();
            }
            // 沿用 legacy 文案（legacy 无论成败均回「排序成功！」）
            return ['ok' => false, 'msg' => '排序成功！'];
        }

        if ($ownTransaction) {
            $this->commit();
        }

        return ['ok' => true, 'msg' => '排序成功！'];
    }

    private function begin(): void
    {
        kernel::database()->beginTransaction();
    }

    private function commit(): void
    {
        kernel::database()->commit();
    }

    private function rollBack(): void
    {
        kernel::database()->rollBack();
    }
}
