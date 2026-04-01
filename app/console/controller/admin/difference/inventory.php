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
/**
 * Class console_ctl_admin_difference_inventory
 */
class console_ctl_admin_difference_inventory extends desktop_controller
{
    /**
     * index
     * @return mixed 返回值
     */

    public function index()
    {
        $actions     = array();
        $base_filter = array();
        //差异明细
        if ($_GET['newAction'] == 'receiving_inventory_detail') {
            $this->shopReceivingItemsDetailIndex($_GET['id']);
            exit;
        }
        $params = array(
            'title'               => '差异单列表',
            'actions'             => $actions,
            'base_filter'         => $base_filter,
            'use_buildin_set_tag' => false,
            'use_buildin_recycle' => false,
            'use_buildin_filter'  => true,
            'allow_detail_popup'  => false,
            'use_buildin_export'  => true,
            'use_buildin_import'  => false,
            'use_buildin_setcol'  => true,
            'object_method'       => [
                'count'   => 'finder_count',
                'getlist' => 'finder_getList',
            ],
        );
        
        $this->finder('console_mdl_difference_receiving_inventory', $params);
    }
    
    /**
     * shopReceivingItemsDetailIndex
     * @param mixed $diff_id ID
     * @return mixed 返回值
     */
    public function shopReceivingItemsDetailIndex($diff_id)
    {
        $page = $_GET['page'] ? $_GET['page'] : 1;
        $view = $_GET['view'];
        //获取明细及主表数据
        $data           = $this->getDIffData($diff_id, $view);
        $diff_items_obj = app::get('taoguaniostockorder')->model("diff_items");
        
        $diff_reason = $diff_items_obj->diff_reason;//结果
        $responsible = $diff_items_obj->responsible;//责任方
        
        $this->pagedata['data']        = $data;
        $this->pagedata['diff_reason'] = $diff_reason;
        $this->pagedata['responsible'] = $responsible;
        $this->pagedata['page']        = $page;
        $this->pagedata['view']        = $view;
        $this->page('admin/difference/diff_detail.html');
    }
    
    /**
     * 获取DIffData
     * @param mixed $diff_id ID
     * @param mixed $view view
     * @return mixed 返回结果
     */
    public function getDIffData($diff_id, $view = 2)
    {
        $diff_obj       = app::get('taoguaniostockorder')->model("diff");
        $diff_items_obj = app::get('taoguaniostockorder')->model("diff_items");
        $basicMaterialLib    = kernel::single('material_basic_material');
        $data           = $diff_obj->db_dump(array('diff_id' => $diff_id));
        $data['items']  = $diff_items_obj->getList('*', array('diff_id' => $diff_id));
        $branch_ids     = array_column($data['items'], 'to_branch_id');
        $branch_ids[]   = $data['branch_id'];
        $branch_ids[]   = $data['extrabranch_id'];
        $branch_obj     = app::get('ome')->model('branch');
        $branch_list    = $branch_obj->getList('branch_bn,name,branch_id', array('branch_id' => $branch_ids, 'check_permission' => 'false'));
        $branch_list    = array_column($branch_list, null, 'branch_id');
        
        $diff_reason = $diff_items_obj->diff_reason;//结果
        $diff_status = $diff_items_obj->diff_status;
        foreach ($data['items'] as $key => $value) {
            $data['items'][$key]['diff_status_value'] = $diff_status[$value['diff_status']];
            $value['diff_reason'] == 'other' && $data['items'][$key]['diff_reason'] .= '_' . $value['handle_type'];
            $data['items'][$key]['to_branch_bn'] = $value['to_branch_id'] ? $branch_list[$value['to_branch_id']]['name'] : '-';
            $data['items'][$key]['handle_type']  = isset($diff_items_obj->handle_type[$value['handle_type']]) 
                ? $diff_items_obj->handle_type[$value['handle_type']] 
                : '';
            
            if ($value['responsible'] == '2') {
                $data['items'][$key]['description'] = sprintf($diff_items_obj->newDescribe[$value['diff_reason']], $diff_items_obj->newResponsible[$value['responsible']], $branch_list[$data['extrabranch_id']]['name']);
                
            } elseif ($value['responsible'] == '3') {
                $data['items'][$key]['description'] = sprintf($diff_items_obj->newDescribe[$value['diff_reason']], $diff_items_obj->newResponsible[$value['responsible']], $branch_list[$data['branch_id']]['name']);
            } elseif ($value['responsible'] == '4') {
                $data['items'][$key]['description'] = sprintf($diff_items_obj->newDescribe[$value['diff_reason']], $diff_items_obj->newResponsible[$value['responsible']], $branch_list[$data['branch_id']]['name']);
            } else {
                $data['items'][$key]['description'] = '';
            }
            //差异原因
            $data['items'][$key]['diff_reason_value'] = $diff_reason[$value['diff_reason']];
            //未处理不展示处理人
            if (!in_array($data['diff_status'], ['2', '3'])) {
                $data['items'][$key]['operator'] = '';
            }
            $product    = $basicMaterialLib->getBasicMaterialExt($value['product_id']);
            $data['items'][$key]['barcode'] = $product['barcode'];
        }
        
        //主数据处理
        $data['to_branch_bn']       = $branch_list[$data['branch_id']]['branch_bn'];
        $data['from_branch_bn']     = $branch_list[$data['extrabranch_id']]['branch_bn'];
        $data['diff_status_value']  = $diff_obj->diff_status[$data['diff_status']];
        $data['check_status_value'] = $data['check_status'] == 1 ? '未审' : '已审';
        
        //发货信息
        $isoMdl          = app::get('taoguaniostockorder')->model("iso");
        $isoItemsMdl     = app::get('taoguaniostockorder')->model("iso_items");
        $isoInfo_4       = $isoMdl->db_dump(array('iso_bn' => $data['original_bn'], 'type_id' => '4'), 'original_bn,complete_time,create_time,business_bn,bill_type');
        $data['to_time'] = $isoInfo_4['complete_time'] ? date('Y-m-d H:i:s', $isoInfo_4['complete_time']) : date('Y-m-d H:i:s', $isoInfo_4['create_time']);//收货日期
        
        $isoInfo_40        = $isoMdl->db_dump(array('iso_bn' => $isoInfo_4['original_bn'], 'type_id' => '40'), 'original_bn,complete_time,create_time');
        $data['from_time'] = $isoInfo_40['complete_time'] ? date('Y-m-d H:i:s', $isoInfo_40['complete_time']) : date('Y-m-d H:i:s', $isoInfo_40['create_time']);//发货日期
        
        //收发货明细
        $isoItemsList = $isoItemsMdl->getList('product_id,bn,nums,normal_num,defective_num', array('iso_bn' => $data['original_bn']));
        $isoItemsList = array_column($isoItemsList, null, 'product_id');
        

        $adjustMdl      = app::get('console')->model('adjust');
        $adjustItemsMdl = app::get('console')->model('adjust_items');
        
        $adjustInfo = $adjustMdl->getList('id, adjust_bn', ['origin_bn' => $data['diff_bn']]);
        $adjustInfo = array_column($adjustInfo, null, 'id');
        
        $adjustId   = array_column($adjustInfo, 'id');
        $ajustItems = $adjustItemsMdl->getList('bm_id, adjust_id', ['adjust_id|in' => $adjustId]);
        $ajustItems = array_column($ajustItems, null, 'bm_id');
        
        foreach ($ajustItems as $k => $v) {
            if (isset($adjustInfo[$v['adjust_id']])) {
                $ajustItems[$k]['adjust_bn'] = $adjustInfo[$v['adjust_id']]['adjust_bn'];
            }
        }
        
        //获取发货数量和收货数量明细值
        $from_nums = 0;
        foreach ($data['items'] as $k => $item) {
            //发货数量
            $from_item_num                      = $isoItemsList[$item['product_id']]['nums'];
            $data['items'][$k]['from_item_num'] = $from_item_num ? $from_item_num : 0;
            //收货数量
            $to_item_nums                     = bcadd($isoItemsList[$item['product_id']]['normal_num'], $isoItemsList[$item['product_id']]['defective_num']);
            $data['items'][$k]['to_item_num'] = $to_item_nums;
            //差异数量
            $diff_item_num                      = bcsub($from_item_num, $to_item_nums);
            $data['items'][$k]['diff_item_num'] = $diff_item_num;
            
            $from_nums = bcadd($from_nums, $from_item_num);
            //调整单号
            $data['items'][$k]['adjustment_bn'] = $ajustItems[$item['product_id']] ? $ajustItems[$item['product_id']]['adjust_bn'] : '';
        }
        $data['from_nums']              = $from_nums;//合计发货数
        $data['business_type']          = '调拨';
        $data['business_bn']            = $isoInfo_4['business_bn'] ? $isoInfo_4['business_bn'] : '';
        $data['packaging_status_value'] = $data['packaging_status'] == 'intact' ? '完好' : '有破损';
        return $data;
    }
    
    /**
     * 保存责任判定
     * @author db
     * @date 2023-07-03 4:27 下午
     */
    public function saveEdit()
    {
        $this->begin();
        $diffObj     = app::get('taoguaniostockorder')->model('diff');
        $diffItemObj = app::get('taoguaniostockorder')->model('diff_items');
        
        $diff_reason = $_POST['diff_reason'];
        $diff_memo   = $_POST['diff_memo'];
        $responsible = $_POST['responsible'];
        
        //外箱状态
        $diff_id          = $_POST['diff_id'];
        $packaging_status = $_POST['packaging_status'];
        $diffObj->update(array('packaging_status' => $packaging_status), array('diff_id' => $diff_id));
        
        foreach ($diff_reason as $diff_items_id => $value) {
            if (!$value) {
                $this->end(false, '差异原因为必填');
            }
            
            if ($responsible[$diff_items_id] == '4') {
                // 判断是否设置了物流店铺
                $this->end(false, '请先配置第三方物流仓库');
            }
            
            if ($responsible[$diff_items_id] == '1') {
                $this->end(false, '责任方必填');
            }
            
            if (strstr($value, 'other_') !== false) {
                $handle_type = str_replace('other_', '', $value);
                $value       = 'other';
            }
            $params = array(
                'diff_reason' => $value,
                'operator'    => kernel::single('desktop_user')->get_login_name(),
            );
            if (!empty($diff_memo[$diff_items_id])) {
                $params['diff_memo'] = $diff_memo[$diff_items_id];
            }
            if ($value == 'other') {
                $params['handle_type'] = $handle_type;
            }
            
            if (!empty($responsible[$diff_items_id])) {
                $params['responsible'] = $responsible[$diff_items_id];
            }
            $diffItemObj->update($params, array('diff_items_id' => $diff_items_id));
            //差异责任表处理
            $this->addDiffItemsDetail($diff_items_id);
        }
        $this->end(true, '提交成功');
        
    }
    
    /**
     * 差异责任判定
     * @param $diff_items_id
     * @return mixed
     */
    public function addDiffItemsDetail($diff_items_id)
    {
        $materialExtwMdl = app::get('material')->model('basic_material_ext');
        $diffItemMdl        = app::get('taoguaniostockorder')->model('diff_items');
        $diffItemsDetailMdl = app::get('taoguaniostockorder')->model('diff_items_detail');
        
        $diffItemsInfo = $diffItemMdl->db_dump(array('diff_items_id' => $diff_items_id), '*');
        $op_name       = kernel::single('desktop_user')->get_name();
        
        $extInfo = $materialExtwMdl->db_dump(array('bm_id' => $diffItemsInfo['product_id']), 'retail_price');
        
        $detail     = array(
            'diff_items_id' => $diff_items_id,
            'diff_id'       => $diffItemsInfo['diff_id'],
            'diff_reason'   => $diffItemsInfo['diff_reason'],
            'diff_memo'     => $diffItemsInfo['diff_memo'],
            'responsible'   => $diffItemsInfo['responsible'],
            'diff_status'   => $diffItemsInfo['diff_status'],
            'operator'      => $op_name,
            'product_id'    => $diffItemsInfo['product_id'],
            'product_name'  => $diffItemsInfo['product_name'],
            'bn'            => $diffItemsInfo['bn'],
            'unit'          => $diffItemsInfo['unit'],
            'price'         => $diffItemsInfo['price'],
            'price_retail'  => $extInfo['retail_price'],
            'nums'          => $diffItemsInfo['nums'],
        );
        $detailInfo = $diffItemsDetailMdl->db_dump(array('diff_items_id' => $diff_items_id), 'items_detail_id');
        if ($detailInfo) {
            $res = $diffItemsDetailMdl->update($detail, array('items_detail_id' => $detailInfo['items_detail_id']));
        } else {
            $res = $diffItemsDetailMdl->insert($detail);
        }
        return $res;
    }
    
    /**
     * 确认责任处理
     * @author db
     * @date 2023-07-04 4:06 下午
     */
    public function doCheckDetail()
    {
        $diffObj           = app::get('taoguaniostockorder')->model('diff');
        $diffItemMdl       = app::get('taoguaniostockorder')->model('diff_items');
        $diffItemDetailMdl = app::get('taoguaniostockorder')->model('diff_items_detail');
        
        $filter = array(
            'diff_id' => $_POST['diff_id'],
        );
        
        // 检测单据是否有效
        $info = $diffObj->db_dump($filter);
        if (!$info) {
            $err_msg = '单据无效';
            parent::splash('error',null,$err_msg);
        }
        
        if ($info['check_status'] == '2') {
            $err_msg = '审核成功';
            parent::splash('error',null,$err_msg);
        }
        
        if (in_array($info['diff_status'], array('2', '3', '4'))) {
            $err_msg = '未处理的单据才能审核';
            parent::splash('error',null,$err_msg);
        }
        //增加diff_status状态，防止重复处理差异明细
        $filter['diff_status'] = ['1','2'];
        $diffItemInfo = $diffItemMdl->getList('diff_items_id,diff_bn',$filter);
        if(!$diffItemInfo){
            parent::splash('error',null,'差异已处理完成！');
        }
        $detailList = $diffItemDetailMdl->getList('*', $filter);
        if (!$detailList) {
            $err_msg = '请先判定责任';
            parent::splash('error',null,$err_msg);
        }
        $info['items'] = $detailList;
        foreach ($info['items'] as $key => $value) {
            if (!$value['diff_reason']) {
                $err_msg = '差异原因不能为空';
                parent::splash('error',null,$err_msg);
            }
            if ($value['responsible'] == '1') {
                $err_msg = '差异原因不能为(空)';
                parent::splash('error',null,$err_msg);
            }
        }
        
        // 把单据的货全部入库（用库存调整单）
        $result = $diffObj->stockAdjustment($info, $msg);
        if (!$result) {
            parent::splash('error',null,$msg);
        }
        parent::splash('success',null,'审核成功');
    }
    
}