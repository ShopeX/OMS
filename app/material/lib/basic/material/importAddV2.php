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

class material_basic_material_importAddV2  implements omecsv_data_split_interface
{
    // public $column_num = 45;
    public $current_order_bn = null;

    const IMPORT_TITLE = [
        ['label' => '*:物料编码', 'col' => 'material_bn'],
        ['label' => '*:物料条码', 'col' => 'material_code'],
        ['label' => '*:物料属性', 'col' => 'type'],
        ['label' => '*:物料类型', 'col' => 'goods_type'],
        ['label' => '*:物料分类', 'col' => 'cat_id'],
        ['label' => '*:物料品牌', 'col' => 'brand_id'],
        ['label' => '*:是否在售', 'col' => 'visibled'],
        ['label' => '物料名称', 'col' => 'material_name'],
        ['label' => '关联半成品信息', 'col' => 'at'],
        ['label' => '物料规格', 'col' => 'specifications'],
        ['label' => '物料款号', 'col' => 'material_spu'],
        ['label' => '特殊扫码配置', 'col' => 'special_setting'],
        ['label' => '特殊扫码开始位数', 'col' => 'first_num'],
        ['label' => '特殊扫码结束位数', 'col' => 'last_num'],
        ['label' => '是否启用唯一码', 'col' => 'serial_number'],
        ['label' => '是否全渠道', 'col' => 'omnichannel'],
        ['label' => '门店销售', 'col' => 'is_o2o_sales'],
        ['label' => '颜色', 'col' => 'color'],
        ['label' => '尺码', 'col' => 'size'],
        ['label' => '材质', 'col' => 'uppermatnm'],
        ['label' => '季节', 'col' => 'season'],
        ['label' => '适用对象', 'col' => 'gendernm'],
        ['label' => '鞋型', 'col' => 'widthnm'],
        ['label' => '风格款式', 'col' => 'modelnm'],
        ['label' => '成本价', 'col' => 'cost'],
                    ['label' => '零售价', 'col' => 'retail_price'],
        ['label' => '采购进价', 'col' => 'purchasing_price'],
        ['label' => '长度', 'col' => 'length'],
        ['label' => '宽度', 'col' => 'width'],
        ['label' => '高度', 'col' => 'high'],
        ['label' => '重量', 'col' => 'weight'],
        ['label' => '体积', 'col' => 'volume'],
        ['label' => '箱规', 'col' => 'box_spec'],
        ['label' => '包装单位', 'col' => 'unit'],
        ['label' => '是否启用保质期监控', 'col' => 'use_expire'],
        ['label' => '预警天数配置', 'col' => 'warn_day'],
        ['label' => '自动退出库存天数配置', 'col' => 'quit_day'],
        ['label' => '是否启用保质期下发管理', 'col' => 'use_expire_wms'],
        ['label' => '保质期', 'col' => 'shelf_life'],
        ['label' => '禁收天数', 'col' => 'reject_life_cycle'],
        ['label' => '禁售天数', 'col' => 'lockup_life_cycle'],
        ['label' => '临期预警天数', 'col' => 'advent_life_cycle'],
        ['label' => '开票税率', 'col' => 'tax_rate'],
        ['label' => '开票名称', 'col' => 'tax_name'],
        ['label' => '发票分类编码', 'col' => 'tax_code'],
        ['label' => '是否自动生成销售物料', 'col' => 'create_material_sales'],
    ];

    const IMPORT_ITEM_TITLE = [];

    /**
     * 检查文件是否有效
     * @param $file_name 文件名
     * @param $file_type 文件类型
     * @param $queue_data 请求参数
     * @return array
     * @date 2024-06-06 3:52 下午
     */
    public function checkFile($file_name, $file_type, $queue_data)
    {
        $bmMdl = app::get('material')->model('basic_material');

        $ioType = kernel::single('omecsv_io_split_' . $file_type);
        $rows   = $ioType->getData($file_name, 0, -1);

        $summaryTitle = array_merge(self::IMPORT_TITLE, self::IMPORT_ITEM_TITLE);
        $oSchema = array_column($summaryTitle, 'label', 'col');

        // 获取系统必填标题
        $requiredTitle = [];  // 必填标题
        foreach ($summaryTitle as $k => $v) {
            if ('*:' == substr($v['label'], 0, 2)) {
                $requiredTitle[] = $v;
            }
        }
        $requiredLabel = array_column($requiredTitle, 'label');

        $previousRow = []; // 前一条数据
        $importTitle = [];  // 导入的标题
        //[防止重复]记录组织编码
        $material_bn_list     = [];
        $material_code_list   = [];
        foreach ($rows as $key => $row) {
            if ($key == 0) {  
                $importTitle = $row;
                $_required_title = [];
                foreach ($row as $k => $v) {
                    if (in_array($v, $requiredLabel)) {
                        $_required_title[] = $v;
                    }
                }
                if (!kernel::single('ome_order_importV2')->checkTitle($_required_title, $requiredLabel)) {
                    return array(false, '导入模板不正确', $row);
                }
                if ($row[0]!='*:物料编码') {
                    return array(false, '导入模板第一列必须是"*:物料编码"', $row);
                }

            } else {

                // 如果当前行没有物料编码,或者物料编码与上一行相同，则主表数据以第一行的为准进行覆盖
                if ((!$row[0] || $row[0] == $previousRow['*:物料编码']) && $previousRow) {
                    $_num = 0;
                    foreach ($previousRow as $_k => $_v) {
                        // 非明细字段
                        if ('(明细)' !== substr($_k, -4)) {
                            $row[$_num] = $_v;
                        }
                        $_num++;
                    }
                }

                // 过滤掉非模版里的字段
                $titleKey = array();
                foreach ($importTitle as $k => $t) {
                    $titleKey[$k] = array_search($t, $oSchema);
                    if ($titleKey[$k] === false) {
                        unset($titleKey[$k]);
                    }
                }

                $buffer = array_combine($titleKey, $row);
                // 数据验证
                foreach ($buffer as $k => $v) {
                    if ('*:' == substr($oSchema[$k], 0, 2) && $v === '') {
                        return [false, sprintf('%s必填', $oSchema[$k])];
                    }
        
                    if ('时间' == substr($oSchema[$k], -1, 2) && $v && !strtotime($v)) {
                        return [false, sprintf('%s需转文本格式', $oSchema[$k])];
                    }
                }

                // 物料编码
                if (in_array($buffer['material_bn'], $material_bn_list)){
                    return [false, sprintf('[%s]物料编码已经存在', $buffer['material_bn']), $buffer];
                }
                $material_bn_list[] = $buffer['material_bn'];

                // 物料条码
                if (in_array($buffer['material_code'], $material_code_list)){
                    return [false, sprintf('[%s]物料条码已经存在', $buffer['material_code']), $buffer];
                }
                $material_code_list[] = $buffer['material_code'];

                // 物料属性
                switch ($buffer['type']) {
                    case '成品':
                        $buffer['type'] = 1;
                        break;
                    case '半成品':
                        $buffer['type'] = 2;
                        break;
                    case '普通':
                        $buffer['type'] = 3;
                        break;
                    case '礼盒':
                        $buffer['type'] = 4;
                        break;
                    case '虚拟':
                        $buffer['type'] = 5;
                        break;
                    default:
                        return [false, sprintf('[%s]物料属性不正确', $buffer['type']), $buffer];
                        break;
                }

                // 物料类型
                if ($buffer['goods_type']){
                    $typeMdl  = app::get('ome')->model('goods_type');
                    $typeInfo = $typeMdl->dump(array('name'=>$buffer['goods_type']), 'type_id');
                    if (!$typeInfo){
                        return [false, sprintf('[%s]物料类型不存在', $buffer['goods_type']), $buffer];
                    }
                    $buffer['goods_type'] = $typeInfo['type_id'];
                }

                // 分类
                if ($buffer['cat_id']){
                    $catMdl  = app::get('material')->model('basic_material_cat');
                    $catInfo = $catMdl->dump(array('name'=>$buffer['cat_id']), 'cat_name');
                    if (!$catInfo){
                        return [false, sprintf('[%s]物料分类不存在', $buffer['cat_id']), $buffer];
                    }
                    $buffer['cat_id'] = $catInfo['cat_id'];
                }

                // 物料品牌
                if ($buffer['brand_id']){
                    $brandMdl  = app::get('ome')->model('branch');
                    $brandInfo = $brandMdl->dump(array('brand_name'=>$buffer['brand_id']), 'brand_id');
                    if (!$brandInfo){
                        return [false, sprintf('[%s]物料品牌不存在', $buffer['brand_id']), $buffer];
                    }
                    $buffer['brand_id'] = $brandInfo['brand_id'];
                }

                // 是否在售
                if ($buffer['visibled'] == '在售'){
                    $buffer['visibled'] = 1;
                } elseif ($buffer['visibled'] == '停售'){
                    $buffer['visibled'] = 2;
                } else {
                    return [false, sprintf('[%s]是否在售不正确', $buffer['visibled']), $buffer];
                }

                if ($buffer['type'] == '1') {
                    if (isset($buffer['at']) && $buffer['at'] != '') {
                        $tmp_basicMInfos = explode('|',$buffer['at']);
                        foreach($tmp_basicMInfos as $tmp_basicMInfo){
                            $tmp_bnInfo = explode(':',$tmp_basicMInfo);
                            $tmp_binds[$tmp_bnInfo[0]] = $tmp_bnInfo[1];
                        }
                        unset($buffer['at']);

                        $buffer['at'] = $tmp_binds;
                        foreach($buffer['at'] as $bn => $val){
                            $basicInfo = $bmMdl->getList('bm_id', array('material_bn'=>$bn), 0, 1);
                            if(!$basicInfo){
                                return [false, sprintf('找不到关联的基础物料：%s,物料编码：%s', $bn, $buffer['material_bn']), $buffer];
                            }else{
                                $tmp_at[$basicInfo[0]['bm_id']] = $val;
                            }
                        }
                        unset($buffer['at']);
                        $buffer['at'] = $tmp_at;
                    }
                } elseif ($buffer['type'] == '4') {
                    if (isset($buffer['at']) && $buffer['at'] != '') {
                        $tmp_basicMInfos = explode('|',$buffer['at']);
                        foreach($tmp_basicMInfos as $tmp_basicMInfo){
                            $tmp_bnInfo = explode(':',$tmp_basicMInfo);
                            $tmp_binds[$tmp_bnInfo[0]] = $tmp_bnInfo[1];
                        }
                        unset($buffer['at']);

                        $buffer['at'] = $tmp_binds;
                        foreach($buffer['at'] as $bn => $val){
                            $basicInfo = $bmMdl->getList('bm_id', array('material_bn'=>$bn, 'type'=>['1', '3']), 0, 1);
                            if(!$basicInfo){
                                return [false, sprintf('礼盒关联的基础物料：%s须为普通类型,物料编码：%s', $bn, $buffer['material_bn']), $buffer];
                            }else{
                                $tmp_at[$basicInfo[0]['bm_id']] = $val;
                            }
                        }
                        unset($buffer['at']);
                        $buffer['at'] = $tmp_at;
                    }
                }

                // 是否启用保质期监控
                if ($buffer['use_expire'] == '开启') {
                    $buffer['use_expire'] = 1;
                } elseif ($buffer['use_expire'] == '关闭') {
                    $buffer['use_expire'] = 2;
                } else {
                    return [false, sprintf('[%s]是否启用保质期监控不正确,请填写开启或关闭,物料编码：%s', $buffer['use_expire'], $buffer['material_bn']), $buffer];
                }

                // 特殊扫码配置
                if ($buffer['special_setting'] == '开启') {
                    $buffer['special_setting'] = 3;
                } elseif ($buffer['special_setting'] == '关闭') {
                    $buffer['special_setting'] = 4;
                } else {
                    return [false, sprintf('[%s]是否启用保质期监控不正确,请填写开启或关闭,物料编码：%s', $buffer['special_setting'], $buffer['material_bn']), $buffer];
                }
                if ($buffer['special_setting'] == 3) {
                    $buffer['first_num'] = intval($buffer['first_num']);
                    $buffer['last_num'] = intval($buffer['last_num']);
                } else {
                    $buffer['first_num'] = 1;
                    $buffer['last_num'] = 1;
                }

                // 是否自动生成销售物料
                if ($buffer['auto_create_sales_material'] == '是') {
                    $buffer['auto_create_sales_material'] = 1;
                } elseif ($buffer['auto_create_sales_material'] == '否' || empty($buffer['auto_create_sales_material'])) {
                    $buffer['auto_create_sales_material'] = 0;
                } else {
                    return [false, sprintf('[%s]是否自动生成销售物料不正确,请填写是或否,物料编码：%s', $buffer['auto_create_sales_material'], $buffer['material_bn']), $buffer];
                }

                if ($buffer['auto_create_sales_material'] == 1 && $buffer['visibled'] === 2) {
                    return [false, sprintf('自动生成销售物料的物料不能停售,物料编码：%s',  $buffer['material_bn']), $buffer];
                }

                if ($buffer['auto_create_sales_material'] === 1) {
                    $salesMdl = app::get('material')->model('sales_material');
                    $salesMInfo = $salesMdl->db_dump(array('sales_material_bn'=>$buffer['material_bn']), 'sm_id');
                    if ($salesMInfo) {
                        return [false, sprintf('销售物料已经存在,无法自动生成,物料编码：%s',  $buffer['material_bn']), $buffer];
                    }
                }

                // 是否全渠道
                if ($buffer['omnichannel'] == '开启') {
                    $buffer['omnichannel'] = 1;
                } elseif ($buffer['omnichannel'] == '关闭') {
                    $buffer['omnichannel'] = 2;
                } else {
                    return [false, sprintf('[%s]是否全渠道不正确,请填写开启或关闭,物料编码：%s', $buffer['omnichannel'], $buffer['material_bn']), $buffer];
                }

                // 是否启用唯一码
                if ($buffer['serial_number'] == '是') {
                    $buffer['serial_number'] = 'true';
                } elseif ($buffer['serial_number'] == '否') {
                    $buffer['serial_number'] = 'false';
                } else {
                    return [false, sprintf('[%s]是否启用唯一码不正确,请填写是或否,物料编码：%s', $buffer['serial_number'], $buffer['material_bn']), $buffer];
                }

                // 是否启用保质期下发管理
                if ($buffer['use_expire_wms'] == '下发') {
                    $buffer['use_expire_wms'] = 1;
                } elseif ($buffer['use_expire_wms'] == '不下发') {
                    $buffer['use_expire_wms'] = 2;
                } else {
                    return [false, sprintf('[%s]是否启用保质期监控不正确,请填写下发或不下发,物料编码：%s', $buffer['use_expire_wms'], $buffer['material_bn']), $buffer];
                }

                if ($buffer['use_expire_wms'] == 1) {
                    $buffer['shelf_life'] = intval($buffer['shelf_life']);
                    $buffer['reject_life_cycle'] = intval($buffer['reject_life_cycle']);
                    $buffer['lockup_life_cycle'] = intval($buffer['lockup_life_cycle']);
                    $buffer['advent_life_cycle'] = intval($buffer['advent_life_cycle']);
                } else {
                    $buffer['shelf_life'] = 0;
                    $buffer['reject_life_cycle'] = 0;
                    $buffer['lockup_life_cycle'] = 0;
                    $buffer['advent_life_cycle'] = 0;
                }

                $err_msg = '';
                $checkBasicLib = kernel::single('material_basic_check');#检查数据有效性Lib类
                if(!$checkBasicLib->checkParams($buffer, $err_msg)){
                    return [false, $err_msg. '物料编码：'.$buffer['material_bn'], $buffer];
                }

                $previousRow = array_combine($importTitle, $row); // 在最后，保存前一条数据,给下个循环使用,因为同一张订单主表信息只有第一条有
            }
        }
        //导入文件内容验证
        return array(true, '文件模板匹配', $rows[0]);
    }

    /**
     * 每页切分数量
     * @param $key
     * @return int|int[]
     * @date 2024-09-05 6:03 下午
     */
    public function getConfig($key = '')
    {
        $config = array(
            'page_size' => 200,
        );
        return $key ? $config[$key] : $config;
    }

    /**
     * 是否是同一个订单明细行检测
     * @param $row
     * @return bool
     * @date 2024-09-05 4:59 下午
     */
    public function is_split($row)
    {
        $is_split = false;
        if ($row['0']) {
            if ($row['0'] !== $this->current_order_bn) {
                if ($this->current_order_bn !== null) {
                    $is_split = true;
                }
                $this->current_order_bn = $row['0'];//物料编码
            }
        }
        return $is_split;
    }

    /**
     * 订单切片导入逻辑处理
     * @param $cursor_id
     * @param $params
     * @param $errmsg
     * @return bool[]
     * @date 2024-09-05 9:58 上午
     */
    public function process($cursor_id, $params, &$errmsg)
    {
        @ini_set('memory_limit', '128M');
        $oFunc = kernel::single('omecsv_func');
        $queueMdl     = app::get('omecsv')->model('queue');
    
        $oFunc->writelog('处理任务-开始', 'settlement', $params);
        //业务逻辑处理
        $data = $params['data'];
        $sdf = [];
        $offset      = intval($data['offset']) + 1;//文件行数 行数默认从1开始
        $splitCount  = 0;//执行行数
        if($data){
            $previousRow = []; // 前一条数据
            
            foreach($data as $row){
                $res = $this->getSdf($row, $offset, $params['title'], $previousRow);
                
                if ($res['status'] and $res['data']) {
                    $tmp = $res['data'];
                    $this->_formatData($tmp);
                    $sdf[] = $tmp;
                } elseif (!$res['status']) {
                    array_push($errmsg, $res['msg']);
                }
                
                //包含表头
                if ($res['status']) {
                    $splitCount++;
                }
                $offset++;
            }
        }
        unset($data);
        //创建订单
        if ($sdf) {
            $opInfo = !empty($params['queue_data']['opInfo']) ? $params['queue_data']['opInfo'] : null;
            list($result,$msgList) = $this->implodeMaterial($sdf, $opInfo);
            if($msgList){
                $errmsg = array_merge($errmsg, $msgList);
            }
            $queueMdl->update(['original_bn' => 'material_add', 'split_count' => $splitCount], ['queue_id' => $cursor_id]);
        }
        
        //任务数据统计更新等
        $oFunc->writelog('处理任务-完成', 'settlement', 'Done');
        return [true];
    }
    

     /**
      * 导入文件表头定义
      * @date 2024-06-06 3:52 下午
      */
    
     public function getSdf($row, $offset = 1, $title, &$previousRow)
     {
        $bmMdl = app::get('material')->model('basic_material');
        $res = array('status' => true, 'data' => array(), 'msg' => '');

        $row = array_map('trim', $row);
         
        $summaryTitle = array_merge(self::IMPORT_TITLE, self::IMPORT_ITEM_TITLE);
        $oSchema = array_column($summaryTitle, 'label', 'col');
         
        $titleKey = array();
        foreach ($title as $k => $t) {
            $titleKey[$k] = array_search($t, $oSchema);
            if ($titleKey[$k] === false) {
                unset($titleKey[$k]);
            }
        }

        // $row_num = count($row);
        // if ($this->column_num <= $row_num && $row[0] != '*:物料编码') {
        if ($row[0] != '*:物料编码') {

            // 如果当前行没有物料编码,或者物料编码与上一行相同，则主表数据以第一行的为准进行覆盖
            if ((!$row[0] || $row[0] == $previousRow['*:物料编码']) && $previousRow) {
                $_num = 0;
                foreach ($previousRow as $_k => $_v) {
                    // 非明细字段
                    if ('(明细)' !== substr($_k, -4)) {
                        $row[$_num] = $_v;
                    }
                    $_num++;
                }
            }
            $buffer = array_combine($titleKey, $row);
            // 物料属性
            switch ($buffer['type']) {
                case '成品':
                    $buffer['type'] = 1;
                    break;
                case '半成品':
                    $buffer['type'] = 2;
                    break;
                case '普通':
                    $buffer['type'] = 3;
                    break;
                case '礼盒':
                    $buffer['type'] = 4;
                    break;
                case '虚拟':
                    $buffer['type'] = 5;
                    break;
                default:
                    $buffer['type'] = 1;
                    break;
            }
            // 物料类型
            if ($buffer['goods_type']){
                $typeMdl  = app::get('ome')->model('goods_type');
                $typeInfo = $typeMdl->dump(array('name'=>$buffer['goods_type']), 'type_id');
                $buffer['goods_type'] = $typeInfo['type_id'];
            }
            // 分类
            if ($buffer['cat_id']){
                $catMdl  = app::get('material')->model('basic_material_cat');
                $catInfo = $catMdl->dump(array('name'=>$buffer['cat_id']), 'cat_name');
                $buffer['cat_id'] = $catInfo['cat_id'];
            }
            // 物料品牌
            if ($buffer['brand_id']){
                $brandMdl  = app::get('ome')->model('branch');
                $brandInfo = $brandMdl->dump(array('brand_name'=>$buffer['brand_id']), 'brand_id');
                $buffer['brand_id'] = $brandInfo['brand_id'];
            }
            // 是否在售
            if ($buffer['visibled'] == '在售'){
                $buffer['visibled'] = 1;
            } elseif ($buffer['visibled'] == '停售'){
                $buffer['visibled'] = 2;
            }

            if ($buffer['type'] == '1') {
                if (isset($buffer['at']) && $buffer['at'] != '') {
                    $tmp_basicMInfos = explode('|',$buffer['at']);
                    foreach($tmp_basicMInfos as $tmp_basicMInfo){
                        $tmp_bnInfo = explode(':',$tmp_basicMInfo);
                        $tmp_binds[$tmp_bnInfo[0]] = $tmp_bnInfo[1];
                    }
                    unset($buffer['at']);

                    $buffer['at'] = $tmp_binds;
                    foreach($buffer['at'] as $bn => $val){
                        $basicInfo = $bmMdl->getList('bm_id', array('material_bn'=>$bn), 0, 1);
                        $tmp_at[$basicInfo[0]['bm_id']] = $val;
                    }
                    unset($buffer['at']);
                    $buffer['at'] = $tmp_at;
                }
            } elseif ($buffer['type'] == '4') {
                if (isset($buffer['at']) && $buffer['at'] != '') {
                    $tmp_basicMInfos = explode('|',$buffer['at']);
                    foreach($tmp_basicMInfos as $tmp_basicMInfo){
                        $tmp_bnInfo = explode(':',$tmp_basicMInfo);
                        $tmp_binds[$tmp_bnInfo[0]] = $tmp_bnInfo[1];
                    }
                    unset($buffer['at']);

                    $buffer['at'] = $tmp_binds;
                    foreach($buffer['at'] as $bn => $val){
                        $basicInfo = $bmMdl->getList('bm_id', array('material_bn'=>$bn, 'type'=>['1', '3']), 0, 1);
                        $tmp_at[$basicInfo[0]['bm_id']] = $val;
                    }
                    unset($buffer['at']);
                    $buffer['at'] = $tmp_at;
                }
            }
            // 是否启用保质期监控
            if ($buffer['use_expire'] == '开启') {
                $buffer['use_expire'] = 1;
            } elseif ($buffer['use_expire'] == '关闭') {
                $buffer['use_expire'] = 2;
            }
            // 特殊扫码配置
            if ($buffer['special_setting'] == '开启') {
                $buffer['special_setting'] = 3;
            } elseif ($buffer['special_setting'] == '关闭') {
                $buffer['special_setting'] = 4;
            }
            if ($buffer['special_setting'] == 3) {
                $buffer['first_num'] = intval($buffer['first_num']);
                $buffer['last_num'] = intval($buffer['last_num']);
            } else {
                $buffer['first_num'] = 1;
                $buffer['last_num'] = 1;
            }
            // 是否自动生成销售物料
            if ($buffer['auto_create_sales_material'] == '是') {
                $buffer['auto_create_sales_material'] = 1;
            } elseif ($buffer['auto_create_sales_material'] == '否' || empty($buffer['auto_create_sales_material'])) {
                $buffer['auto_create_sales_material'] = 0;
            }
            // 是否全渠道
            if ($buffer['omnichannel'] == '开启') {
                $buffer['omnichannel'] = 1;
            } elseif ($buffer['omnichannel'] == '关闭') {
                $buffer['omnichannel'] = 2;
            }
            // 是否启用唯一码
            if ($buffer['serial_number'] == '是') {
                $buffer['serial_number'] = 'true';
            } elseif ($buffer['serial_number'] == '否') {
                $buffer['serial_number'] = 'false';
            }
            // 是否启用保质期下发管理
            if ($buffer['use_expire_wms'] == '下发') {
                $buffer['use_expire_wms'] = 1;
            } elseif ($buffer['use_expire_wms'] == '不下发') {
                $buffer['use_expire_wms'] = 2;
            }
            if ($buffer['use_expire_wms'] == 1) {
                $buffer['shelf_life'] = intval($buffer['shelf_life']);
                $buffer['reject_life_cycle'] = intval($buffer['reject_life_cycle']);
                $buffer['lockup_life_cycle'] = intval($buffer['lockup_life_cycle']);
                $buffer['advent_life_cycle'] = intval($buffer['advent_life_cycle']);
            } else {
                $buffer['shelf_life'] = 0;
                $buffer['reject_life_cycle'] = 0;
                $buffer['lockup_life_cycle'] = 0;
                $buffer['advent_life_cycle'] = 0;
            }
            // 是否门店销售
            if ($buffer['is_o2o_sales'] == '是') {
                $buffer['is_o2o_sales'] = 1;
            } else {
                $buffer['is_o2o_sales'] = 0;
            }

            $sdf = array(
                'material_name'         =>  $buffer['material_name'],
                'material_bn'           =>  trim($buffer['material_bn']),
                'type'                  =>  $buffer['type'],
                'material_code'         =>  trim($buffer['material_code']),
                'visibled'              =>  $buffer['visibled'],
                'unit'                  =>  $buffer['unit'],
                'retail_price'          =>  $buffer['retail_price'] ? $buffer['retail_price'] : 0.00,
                'cost'                  =>  $buffer['cost'] ? $buffer['cost'] : 0.00,
                'weight'                =>  $buffer['weight'] ? $buffer['weight'] : 0.00,
                'at'                    =>  $buffer['at'],
                'use_expire'            =>  $buffer['use_expire'],
                'warn_day'              =>  $buffer['warn_day'],
                'quit_day'              =>  $buffer['quit_day'],
                'tax_rate'              =>  $buffer['tax_rate'],
                'tax_name'              =>  $buffer['tax_name'],
                'tax_code'              =>  $buffer['tax_code'],
                'cat_id'                =>  $buffer['cat_id'],
                'specifications'        =>  $buffer['specifications'],
                'brand_id'              =>  $buffer['brand_id'],
                'special_setting'       =>  $buffer['special_setting'],
                'first_num'             =>  $buffer['first_num'],
                'last_num'              =>  $buffer['last_num'],
                'material_bn_crc32'     =>  '',
                'create_material_sales' =>  $buffer['create_material_sales'],
                'omnichannel'           =>  $buffer['omnichannel'],
                'serial_number'         =>  $buffer['serial_number'],
                'material_spu'          =>  trim($buffer['material_spu']),
                'color'                 =>  $buffer['color'],
                'size'                  =>  $buffer['size'],
                'season'                =>  $buffer['season'],
                'uppermatnm'            =>  $buffer['uppermatnm'],
                'gendernm'              =>  $buffer['gendernm'],
                'widthnm'               =>  $buffer['widthnm'],
                'modelnm'               =>  $buffer['modelnm'],
                'is_o2o_sales'          =>  $buffer['is_o2o_sales'],
            );
            $res['data'] = $sdf;
        }
        
        $previousRow = array_combine($title, $row); // 在最后，保存前一条数据,给下个循环使用,因为同一张订单主表信息只有第一条有
        return $res;
     }

    /**
     * _formatData
     * @param mixed $data 数据
     * @return mixed 返回值
     */
    public function _formatData(&$data)
     {
        foreach ($data as $k => $str) {
            $data[$k] = str_replace(array("\r\n", "\r", "\n", "\t"), "", $str);
        }
     }

    function implodeMaterial($contents, $opInfo = null)
    {
        $errmsg = '';
        $importObj = app::get('material')->model('basic_material');
        $barcodeObj = app::get('material')->model('barcode');

        $basicMaterialExtObj = app::get('material')->model('basic_material_ext');
        $basicMaterialStockObj = app::get('material')->model('basic_material_stock');
        $basicMaterialConfObj = app::get('material')->model('basic_material_conf');

        $basicMaterialConfObjSpe    = app::get('material')->model('basic_material_conf_special');
        $importLogLib = kernel::single('material_basic_material');
        $createSalesByBmIds    = array();//自动生成销售物料的基础物料bm_id

        foreach($contents as $v){
            $importData = array(
                'material_name' => $v['material_name'],
                'material_bn' => $v['material_bn'],
                'material_bn_crc32' => $v['material_bn_crc32'],
                'type' => $v['type'],
                'visibled' => $v['visibled'],
                'create_time' => time(),
                'omnichannel' => intval($v['omnichannel']),
                'serial_number'=>$v['serial_number'],
                'material_spu'=>$v['material_spu'],
                'tax_rate'=> !empty($v['tax_rate']) ? $v['tax_rate'] : 0,
                'tax_name'=>$v['tax_name'],
                'tax_code'=>$v['tax_code'],
                'color'=>$v['color'],
                'size'=>$v['size'],
                'is_o2o_sales'=>$v['is_o2o_sales'],
            );

            $is_save = $importObj->save($importData);
            if($is_save){
                //保存条码信息
                $sdf = array(
                    'bm_id' => $importData['bm_id'],
                    'type' => material_codebase::getBarcodeType(),
                    'code' => $v['material_code'],
                );
                $barcodeObj->insert($sdf);

                //保存保质期配置
                $useExpireConfData = array(
                    'bm_id' => $importData['bm_id'],
                    'use_expire' => $v['use_expire'],
                    'warn_day' => $v['warn_day'] ?  $v['warn_day'] : 0,
                    'quit_day' => $v['quit_day'] ? $v['quit_day'] : 0,
                    'create_time' => time(),
                );
                $basicMaterialConfObj->save($useExpireConfData);

                //如果是组合物料保存相关数据
                if(in_array($v['type'],array('1','4'))){
                    $basicMaterialCombinationItemsObj = app::get('material')->model('basic_material_combination_items');
                    if(isset($v['at'])){
                        foreach($v['at'] as $k=>$num){
                            $tmpChildMaterialInfo = $importObj->dump($k, 'material_name,material_bn');

                            $addCombinationData = array(
                                'pbm_id' => $importData['bm_id'],
                                'bm_id' => $k,
                                'material_num' => $num,
                                'material_name' => $tmpChildMaterialInfo['material_name'],
                                'material_bn' => $tmpChildMaterialInfo['material_bn'],
                                'material_bn_crc32' => sprintf('%u',crc32($tmpChildMaterialInfo['material_bn'])),
                            );
                            $basicMaterialCombinationItemsObj->insert($addCombinationData);
                            $addCombinationData = null;
                        }
                    }
                }

                //保存基础物料的关联的特性
                //to do 暂时去掉这块逻辑，有待实现

                //保存物料扩展信息
                $addExtData = array(
                    'bm_id' => $importData['bm_id'],
                    'cost' => floatval($v['cost']),
                    'retail_price' =>floatval($v['retail_price']),
                    'weight' => floatval($v['weight']),
                    'unit' => floatval($v['unit']),
                    'cat_id' => (int)$v['cat_id'],
                    'specifications' => $v['specifications'],
                    'brand_id' => (int)$v['brand_id'],
                );
                $basicMaterialExtObj->insert($addExtData);

                //保存物料库存信息
                $addStockData = array(
                    'bm_id' => $importData['bm_id'],
                    'store' => 0,
                    'store_freeze' => 0,
                );
                $basicMaterialStockObj->insert($addStockData);

                //保存特殊扫码配置信息
                $addScanConfInfo    = array(
                                        'bm_id' => $importData['bm_id'],
                                        'openscan' => $v['special_setting'],
                                        'fromposition' => $v['first_num'],
                                        'toposition' => $v['last_num'],
                                    );
                $basicMaterialConfObjSpe->insert($addScanConfInfo);

                //
                 //新增属性参数
                $season = $v['season'];
                $uppermatnm = $v['uppermatnm'];
                $widthnm = $v['widthnm'];
                $gendernm = $v['gendernm'];
                $subbrand = $v['subbrand'];
                $modelnm = $v['modelnm'];
                $props = array();
                
                if($season){

                    $props['season'] = $season;
                }
                if($uppermatnm){
                    
                    $props['uppermatnm'] = $uppermatnm;
                    
                }
                if($widthnm){
                    $props['widthnm'] = $widthnm;
                    
                }
                if($gendernm){
                    $props['gendernm'] = $gendernm;
                    
                }
                if($modelnm){
                    $props['modelnm'] = $modelnm;
                }
                

                if($props){
                    $propsMdl = app::get('material')->model('basic_material_props');

                    $propsdata = array();

                    foreach($props as $pk=>$pv){

                        if($pv){
                            $propsdata = array(
                                'bm_id'         =>  $importData['bm_id'],
                                'props_col'     =>  $pk,
                                'props_value'   =>  $pv,
                            );
                            $propsMdl->save($propsdata);
                        }
                    }
                }


                //是否自动生成销售物料
                $v['create_material_sales']    = intval($v['create_material_sales']);
                if($v['create_material_sales'] === 1)
                {
                    $createSalesByBmIds[]    = $importData['bm_id'];
                }
                $importLogLib->writeImportLog(
                    'basic_material_import@wms',
                    $importData['bm_id'],
                    '批量导入新增基础物料：' . $importData['material_bn'] . ' ' . $importData['material_name'],
                    $opInfo
                );
            }else{
                $m = $importObj->db->errorinfo();
                if(!empty($m)){
                    $errmsg.=$m.";";
                }
            }
        }
        //自动生成销售物料
        if($createSalesByBmIds)
        {
            $bm_ids    = implode(',', $createSalesByBmIds);
            $result    = kernel::single('material_basic_exchange')->process($bm_ids);

            if($result['fail'] > 0)
            {
                $errmsg    .= "自动生成销售物料成功:". $result['total'] ."个,失败:". $result['fail'] ."个";
            }
        }
        
        return [true, $errmsg];
    }

    /**
     * 获取Title
     * @param mixed $filter filter
     * @param mixed $ioType ioType
     * @return mixed 返回结果
     */
    public function getTitle($filter=null,$ioType='csv'){
        $summaryTitle = array_merge(self::IMPORT_TITLE, self::IMPORT_ITEM_TITLE);
        return array_column($summaryTitle, 'label');
    }



    

    

    

    

    

    

    

    

    
    

    
    


}