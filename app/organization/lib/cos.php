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


class organization_cos
{
    /**
     * 保存COS信息到数据库。
     * 
     * @param array $data 包含COS信息的数据数组，键名分别为：
     *      'cos_type': COS类型,
     *      'cos_code': COS代码,
     *      'cos_name': COS名称,
     *      'op_name': 操作人名称,
     *      'parent_id': 父级ID,
     *      'is_leaf': 是否为叶子节点。
     * @return int 返回插入或更新的COS记录的ID。
     */
    public function saveCos($data)
    {
        $mdl      = app::get('organization')->model('cos');
        $cos_type = $data['cos_type'];

        $filter = [
            'cos_type' => $data['cos_type'],
            'cos_code' => $data['cos_code'],
        ];
        $cosData = [
            'cos_code'  => $data['cos_code'],
            'cos_name'  => $data['cos_name'],
            'cos_type'  => $data['cos_type'],
            'op_name'   => $data['op_name'],
            'parent_id' => $data['parent_id'],
            'is_leaf'   => $data['is_leaf'],
        ];
        $parent_info = $mdl->db_dump(['cos_id' => $cosData['parent_id']]);

        // 检查是否存在相同的COS信息
        $info = $mdl->db_dump($filter, 'cos_id');
        if ($info) {
            $cosData['cos_id'] = $info['cos_id'];

            // 补全路径
            $cosData['cos_path'] = $parent_info['cos_path'] . $cosData['cos_id'] . ',';

            // 如果存在，则更新相应记录
            $mdl->update($cosData, ['cos_id' => $info['cos_id']]);
        } else {
            // 如果不存在，则插入新记录
            $cosData['child_count'] = 0;
            $mdl->insert($cosData);

            // 补全路径
            $cos_path = $parent_info['cos_path'] . $cosData['cos_id'] . ',';
            $mdl->update(['cos_path' => $cos_path], ['cos_id' => $cosData['cos_id']]);

        }

        // 更新父节点的子节点数（确认过，只更新父节点一级）
        // $parentIdAr = array_filter(explode(',', $parent_info['cos_path']));
        // foreach ($parentIdAr as $k => $p_cos_id) {
        //     $sql = "SELECT count(cos_id) AS _count FROM sdb_organization_cos WHERE FIND_IN_SET('".$cosData['parent_id']."', cos_path)";
        //     $countRes = kernel::database()->selectrow($sql);
        //     $mdl->update(['child_count' => $countRes['_count']-1], ['cos_id' => $p_cos_id]);
        // }
        $childCount = $mdl->count(['parent_id' => $cosData['parent_id']]);
        $isLeaf     = $childCount > 0 ? '0' : '1';
        $mdl->update(['child_count' => $childCount, 'is_leaf' => $isLeaf], ['cos_id' => $cosData['parent_id']]);

        return $cosData['cos_id'];
    }

    /**
     * 更新贸易公司的外部绑定ID
     * @param $bsCosId string 经销商的cos_id
     * @param $betcList array BETC列表，其中应包含cosIdArr数组，表示需要操作的BETC的ID数组
     * @return array 返回操作结果，'res'表示结果状态（'succ'成功，'fail'失败），'err'表示错误信息（如果有）
     */
    public function upBetcOutBindId($bsCosId, $betcList = [])
    {
        $cosMdl = app::get('organization')->model('cos');

        if (!$bsCosId) {
            return ['res' => 'fail', 'err' => 'bsCosId 无效'];
        }

        // 检查BETC列表是否包含有效的cosIdArr
        if ($betcList['cosIdArr']) {
            // 确保cosIdArr是数组
            !is_array($betcList['cosIdArr']) && $betcList['cosIdArr'] = [$betcList['cosIdArr']];

        } else {
            $betcList['cosIdArr'] = [];
            // // BETC列表无效，返回错误信息
            // return ['res' => 'fail', 'err' => 'betcList 无效'];
        }

        // 根据bsCosId先查老的绑定关系，经销商隶属于哪些贸易公司
        $oldOutList = $cosMdl->getList('*', ['cos_type' => 'betc', 'out_bind_id|findinset' => $bsCosId]);
        $oldOutList = array_column($oldOutList, null, 'cos_id');
        $oldCosList = array_column($oldOutList, 'cos_id');

        // 获取符合条件的BETC列表
        $list = $cosMdl->getList('*', ['cos_id|in' => $betcList['cosIdArr'], 'cos_type' => 'betc']);

        // 遍历列表，更新每个BETC的外部绑定ID
        foreach ($list as $k => $v) {

            // 解析当前BETC的外部绑定ID
            $orOutBindId = array_filter(explode(',', trim($v['out_bind_id'], ',')));

            // 如果当前业务场景ID不在外部绑定ID中，则添加
            if (!in_array($bsCosId, $orOutBindId)) {

                // 添加业务场景ID，并保持数组有序
                $orOutBindId[] = $bsCosId;
                sort($orOutBindId);
                $newOutBindId = ',' . implode(',', $orOutBindId) . ',';

                // 更新外部绑定ID
                $cosMdl->update(['out_bind_id' => $newOutBindId, 'is_leaf' => '0'], ['cos_id' => $v['cos_id']]);
            }
        }

        // 在老的关系中存在，但是在新的关系中不存在的，删掉
        $diff = array_diff($oldCosList, $betcList['cosIdArr']);
        if ($diff) {
            foreach ($diff as $oldCosId) {
                $upOldOutBindId = str_replace(',' . $bsCosId, '', $oldOutList[$oldCosId]['out_bind_id']);
                if ($upOldOutBindId == ',') {
                    // 更新外部绑定ID
                    $cosMdl->update(['out_bind_id' => '', 'is_leaf' => '1'], ['cos_id' => $oldCosId]);
                } else {
                    $cosMdl->update(['out_bind_id' => $upOldOutBindId, 'is_leaf' => '0'], ['cos_id' => $oldCosId]);
                }
            }
        }
        // 操作成功，返回结果
        return ['res' => 'succ'];
    }

    /**
     * 获取COS列表
     * @param string $cosId COS ID，为空时返回失败信息
     * @param array $resCosType COS TYPE，返回要求的企业组织类型的数据，为空则返回全部
     * @return array 返回操作结果，成功时包含COS数据的数组，失败时包含错误信息的数组
     */
    public function getCosList($cosId = '', $resCosType = [])
    {
        if (!$cosId) {
            // 是否是超管
            $isSuper = kernel::single('desktop_user')->is_super();
            if ($isSuper) {
                return [true, '_ALL_'];
            }

            $cosOpsMdl = app::get('organization')->model('cos_ops');
            $cosMdl    = app::get('organization')->model('cos');

            $opInfo     = kernel::single('ome_func')->getDesktopUser();
            $cosOpsInfo = $cosOpsMdl->db_dump(['op_id' => $opInfo['op_id']]);
            if (!$cosOpsInfo || !$cosOpsInfo['cos_ids']) {
                return [false, 'res is null'];
            }

            $cosIdArr = explode(',', $cosOpsInfo['cos_ids']);
            $cosList  = $cosMdl->getList('*', ['cos_id|in' => $cosIdArr]);

            $cosParentIdArr = array_column($cosList, 'parent_id');

            // 如果当前企业组织cos_id的类型不是shop，并且不是cosList里其他cos_id的parent_id，或者当前企业组织cos_id的out_bind_id也不在cosList里，需要根据当前cos_id获取list
            $allChild = [];
            foreach ($cosList as $ck => $cv) {
                // 如果当前企业组织cos_id的叶子节点是1，说明是最底层，无需考虑下面是否还有下一级数据
                if ($cv['is_leaf'] == '1') {
                    continue;
                }
                // 如果当前企业组织cos_id是cosList里其他cos_id的parent_id，说明已经勾选过下一级的权限
                if (in_array($cv['cos_id'], $cosParentIdArr)) {
                    continue;
                }
                // 如果当前企业组织cos_id的out_bind_id在cosList的cos_id里，说明已经勾选过下一级的权限
                if ($cv['out_bind_id']) {
                    $outBindIdArr = explode(',', trim($cv['out_bind_id'], ','));
                    if (array_intersect($outBindIdArr, $cosIdArr)) {
                        continue;
                    }
                }
                // 获取当前企业组织cos_id下的所有结构
                if (!$cv['cos_id']) {
                    continue;
                }
                $childList = $this->getCosList($cv['cos_id']);
                if (is_array($childList) && $childList[0] && $childList[1] && is_array($childList[1])) {
                    $allChild = array_merge($allChild, $childList[1]);
                }
            }

            if ($allChild) {
                $cosList = array_merge($cosList, $allChild);
                $cosList = array_column($cosList, null, 'cos_id'); // 根据cos_id去重
            }

            // 获取当前账号的cos_id
            return [true, array_values($cosList)];
        }

        // 根据$cosId获取初始COS列表，并以'cos_id'为键重新组织数组
        $list = $this->getCosFindIdSET($cosId, 'cos_path');
        // 如果没有找到任何COS，则返回成功但数据为空的数组
        if (!$list) {
            return [true, []];
        }
        $list = array_column($list, null, 'cos_id');

        // 收集所有需要查询的外部绑定ID，并去重
        $outBindIdArr = [];
        foreach ($list as $k => $v) {
            if ($v['out_bind_id']) {
                $outBindIdArr = array_unique(array_merge($outBindIdArr, explode(',', trim($v['out_bind_id'], ','))));
            }
        }

        // 根据收集到的外部绑定ID，查询并合并子COS列表到主列表中
        if ($outBindIdArr) {
            foreach ($outBindIdArr as $bindId) {
                $childList = $this->getCosFindIdSET($bindId, 'cos_path');
                // 如果查询不到子COS，则跳过当前循环
                if (!$childList) {
                    continue;
                }
                $childList = array_column($childList, null, 'cos_id');
                $list      = array_merge($list, $childList);
            }
        }

        // 根据入参返回需要的企业组织类型
        if ($resCosType) {
            foreach ($list as $_key => $_value) {
                if (!in_array($_value['cos_type'], $resCosType)) {
                    unset($list[$_key]);
                }
            }
        }
        return [true, array_values($list)];
    }

    /**
     * 根据字段名和值查询COS信息
     * @param string $val 要查询的值
     * @param string $fieldName 要查询的字段名
     * @return array 返回查询结果的数组
     */
    public function getCosFindIdSET($val, $fieldName)
    {
        // 构造SQL查询语句，使用FIND_IN_SET函数查询指定字段中包含$val的记录
        $sql = "SELECT * FROM sdb_organization_cos WHERE FIND_IN_SET(" . $val . ", " . $fieldName . ") ";
        // 执行查询并返回结果
        $list = kernel::database()->select($sql);
        return $list;
    }

    /**
     * 得到地区信息 - parent region id， 层级，下级地区
     * @params string region id
     * @return array 指定信息的数组
     */
    public function getChildCosById($cosId = '')
    {
        $mdl = app::get('organization')->model('cos');

        $filter = [
            'parent_id' => $cosId,
        ];

        $list = $mdl->getList('*', $filter);

        // 查询当前cos_id下是否有out_bind_id
        $outBinId = $mdl->db_dump(['cos_id' => $cosId]);
        if ($outBinId['out_bind_id']) {
            $_list = $mdl->getList('*', ['cos_id|in' => explode(',', trim($outBinId['out_bind_id'], ','))]);
            if ($_list) {
                $list = array_merge($list, $_list);
            }
        }
        foreach ($list as $k => $v) {
            $list[$k]['child_count'] = $this->getChildCount($v['cos_id']);
        }
        return [true, array_values($list)];
    }

    /**
     * 获取ChildCount
     * @param mixed $cosId ID
     * @return mixed 返回结果
     */
    public function getChildCount($cosId = '')
    {
        $mdl = app::get('organization')->model('cos');

        $filter = [
            'parent_id' => $cosId,
        ];

        $count = $mdl->count($filter);
        // 查询当前cos_id下是否有out_bind_id
        $outBinId = $mdl->db_dump(['cos_id' => $cosId]);
        if ($outBinId['out_bind_id']) {
            $_count = $mdl->count(['cos_id|in' => explode(',', trim($outBinId['out_bind_id'], ','))]);
            if ($_count) {
                $count += $_count;
            }
        }
        return $count;
    }

    /**
     * 保存_operation_permission
     * @param mixed $params 参数
     * @return mixed 返回操作结果
     */
    public function save_operation_permission($params)
    {
        if (!$params['user_id'] || !isset($params['dealer_shop_conf']['cosIds'])) {
            return false;
        }

        $cosOpsMdl = app::get('organization')->model('cos_ops');
        //删除原权限
        $cosOpsMdl->delete(array('op_id' => $params['user_id']));

        if (!$params['dealer_shop_conf']['cosIds']) {
            return true;
        }

        // 整理cos_id
        $cosIdArr = explode(',', $params['dealer_shop_conf']['cosIds'][0]);
        foreach ($cosIdArr as $k => $v) {
            if (count(explode('|', $v)) > 1) {
                $cosIdArr[$k] = explode('|', $v)[0];
            }
        }

        //保存现有
        $addOperPer = array(
            'op_id'   => $params['user_id'],
            'cos_ids' => implode(',', $cosIdArr),
        );
        $cosOpsMdl->insert($addOperPer);

        return true;
    }
    /**
     * 根据店铺ID获取对应的BS关系ID
     * 
     * 本函数通过多步骤查询，从店铺ID开始，逐步关联到对应的Cos ID和BS ID，最终构建一个
     * 店铺ID到BS ID的映射数组。这涉及到三个模型的查询：店铺模型、Cos模型和BS模型。
     * 
     * @param array $shop_ids 店铺ID列表，默认为空数组
     * @return array 返回一个键为店铺ID，值为BS ID的关联数组
     */
    public function getBsFromShopId($shop_ids = [])
    {
        $shopMdl  = app::get('ome')->model('shop');
        $shopList = $shopMdl->getList('shop_id,cos_id', ['shop_id'=>$shop_ids]);
        $shopList = array_column($shopList, 'cos_id', 'shop_id');

        $cosMdl  = app::get('organization')->model('cos');
        $cosList = $cosMdl->getList('cos_id,parent_id', ['cos_id'=>$shopList]);
        $cosList = array_column($cosList, 'parent_id', 'cos_id');

        $bsList = [];
        if(app::get('dealer')->is_installed()){
            //检查是否安装dealer应用
            $bsMdl  = app::get('dealer')->model('business');
            $bsList = $bsMdl->getList('bs_id,cos_id', ['cos_id'=>$cosList]);
            $bsList = array_column($bsList, 'bs_id', 'cos_id');
        }

        $shopToCos = [];
        foreach ($shopList as $shop_id => $shop_cos_id) {
            $shopToCos[$shop_id] = [];
            $bs_cos_id = $cosList[$shop_cos_id];
            $bs_id     = $bsList[$bs_cos_id];
            if (!$bs_id) {
                continue;
            }
            $shopToCos[$shop_id] = [
                'bs_id' => $bs_id,
            ];
        }
        return $shopToCos;
    }

    /**
     * 获取BbuFromCosId
     * @param mixed $cosId ID
     * @param mixed $cosList cosList
     * @return mixed 返回结果
     */
    public function getBbuFromCosId($cosId = '', $cosList = [])
    {
        !$cosList && $cosList = $this->getCosList($cosId);
        if (!$cosList[0]) {
            return [false, 'cosList is null'];
        } elseif ($cosList[0] && $cosList[1] == '_ALL_') {
            // 是超管
            return [true, '_ALL_'];
        }

        $cosPaths = array_column($cosList[1], 'cos_path');

        /*
        $cosShop = $cosBs = $cosBetc = $cosBbu = [];
        foreach ($cosList[1] as $k => $v) {
            if ($v['cos_type'] == 'betc') {
                $betcCosList = $this->getCosFindIdSET($v['cos_id'], 'out_bind_id');
                foreach ($betcCosList as $bk => $bv) {
                    $cosPaths[] = $bv['cos_path'];
                }
            }
        }
        */

        $cosMdl = app::get('organization')->model('cos');
        $cosIds = array_unique(array_filter(explode(',', implode(',', $cosPaths))));
        $list   = $cosMdl->getList('*', ['cos_type'=>'bbu', 'cos_id|in' => $cosIds]);
        return [true, $list];
    }
}
