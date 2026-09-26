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
 * 唯品会JIT审核配置
 *
 * @access public
 * @author wangbiao<wangbiao@shopex.cn>
 * @version 1.0 vopurchase.php
 */
class console_ctl_admin_vopsetting extends desktop_controller{

    var $workground = "console_purchasecenter";

    function index(){
        $params = array(
                'title' => '自动规则配置',
                'allow_detail_popup' => true,
                'use_buildin_recycle' => false,
                'orderBy' => 'create_time desc'
        );
        $params['actions'] =array(
                array(
                        'label'=>'添加配置',
                        'href'=>'index.php?app=console&ctl=admin_vopsetting&act=add',
                        'target' => 'dialog::{width:650,height:680,title:\'添加JIT自动规则配置\'}',
                ),
        );

        $this->finder('console_mdl_vopsetting', $params);
    }

    //新增弹窗页
    function add(){
        $this->_edit('add');
    }

    //编辑弹窗页
    function edit($sid){
        $this->_edit('edit', $sid);
    }

    //新增和编辑弹窗页的展示
    function _edit($action='', $sid=0)
    {
        $setObj       = app::get('purchase')->model('setting');
        $setShopObj   = app::get('purchase')->model('setting_shop');
        $purchaseLib  = kernel::single('purchase_purchase_order');

        //读取配置
        $row            = array();
        $shop_filter    = array();
        if($sid)
        {
            $row    = $setObj->dump(array('sid'=>$sid), '*');
            if($row)
            {
                $shop_filter['sid|noequal']    = $sid;
            }

            //关联的shop_id
            $sel_shop_ids = array();
            $shopList     = $setShopObj->getList('*', array('sid'=>$sid));
            if($shopList)
            {
                foreach ($shopList as $key => $val)
                {
                    $sel_shop_ids[]    = $val['shop_id'];
                }
            }
            $this->pagedata['sel_shop_ids']    = $sel_shop_ids;
        }

        //已被其它配置选择的店铺列表
        $used_shop_ids   = array();
        $tempData        = $setShopObj->getList('*', $shop_filter);
        if($tempData)
        {
            foreach ($tempData as $key => $val)
            {
                $used_shop_ids[]    = $val['shop_id'];
            }
        }
        $this->pagedata['used_shop_ids']    = $used_shop_ids;

        //唯品会店铺
        $shopList    = $purchaseLib->get_vop_shop_list();
        $this->pagedata['shopList']    = $shopList;

        //状态值
        $stockLib    = kernel::single('purchase_purchase_stockout');
        $dly_mode        = $stockLib->getDlyMode();//配送方式
        $carrier_code    = array();

        if($sel_shop_ids)
        {
            $carrier_code    = $stockLib->getCarrierCode($sel_shop_ids);//根据店铺加载承运商
        }

        $this->pagedata['dly_mode'] = $dly_mode;
        $this->pagedata['carrier_code'] = $carrier_code;

        //审核时间点
        $hour_list    = array();
        for($i=1; $i<=24; $i++)
        {
            $hour_list[$i]    = ($i<10 ? '0'.$i : $i) .':00';
        }
        $this->pagedata['hour_list'] = $hour_list;
        $this->pagedata['json_hour_list'] = json_encode($hour_list);

        //OMS仓库(只支持自有仓储、伊藤忠仓储)
        $branch_list    = $purchaseLib->get_branch_list();
        $this->pagedata['branch_list'] = $branch_list;

        //[读取]自动审核配置
        // 每日审核时间点
        $sel_exec_hour    = array();
        if($row['exec_hour'])
        {
            $temp    = explode(',', $row['exec_hour']);
            sort($temp);

            $key_i   = 0;
            foreach ($temp as $key => $val)
            {
                $key_i    = $key + 1;
                $sel_exec_hour[$key_i]    = $val;
            }

            $this->pagedata['sel_exec_hour'] = $sel_exec_hour;
        }

        $this->pagedata['vop_config']    = $row;
        $this->page('admin/vop/setting_edit.html');
    }

    //保存
    function save()
    {
        $this->begin('javascript:finderGroup["'.$_GET['finder_id'].'"].refresh();');

        $setObj       = app::get('purchase')->model('setting');
        $setShopObj   = app::get('purchase')->model('setting_shop');
        $sid          = $_POST['sid'];

        //读取配置
        $shop_filter    = array();
        if($sid)
        {
            $row    = $setObj->dump(array('sid'=>$sid), 'sid');
            if($row)
            {
                $shop_filter['sid|noequal']    = $sid;
            }
        }

        //选择的店铺
        $shop_ids    = array();
        if($_POST['shopids'])
        {
            foreach ($_POST['shopids'] as $key => $val)
            {
                if($val != '_ALL_')
                {
                    $shop_ids[]    = $val;
                }
            }
        }

        if(empty($shop_ids))
        {
            $this->end(false, '请选择应用的店铺');
        }

        $shop_filter['shop_id']  = $shop_ids;
        $tempData                = $setShopObj->getList('*', $shop_filter);
        if($tempData)
        {
            $this->end(false, '操作失败,有店铺已被其它配置使用。');
        }

        //每日审核时间点
        $exec_hour    = array();
        $key_i        = 0;

        $temp         = $_POST['exec_hour'];
        if($temp)
        {
            foreach ($temp as $key => $val)
            {
                $temp_name    = 'exec_hour_'. $val;
                $hour    = $_POST[$temp_name];
                if($hour)
                {
                    $key_i++;
                    $exec_hour[$key_i]    = $hour;
                }
            }

            $exec_hour    = implode(',', $exec_hour);
        }

        $is_merge        = ($_POST['is_merge'] == '1' ? 1 : 0);//相同唯品会入库仓是否合并出库
        $is_auto_combine = ($_POST['is_auto_combine'] == '1' ? 1 : 0);//开启自动审核
        $branch_id       = intval($_POST['branch_id']);//指定出库仓
        $carrier_code    = $_POST['carrier_code'];//承运商
        $dly_mode        = $_POST['dly_mode'];//配送方式

        //组织数据
        $vop_config    = array(
                'is_merge' => $is_merge,
                'is_auto_combine' => $is_auto_combine,
                'exec_hour' => $exec_hour,
                'branch_id' => $branch_id,
                'carrier_code'=>$carrier_code,
                'dly_mode'=>$dly_mode,
        );

        if($vop_config['is_auto_combine'] == 1)
        {
            if(empty($vop_config['branch_id']))
            {
                $this->end(false, '请选择指定仓库');
            }
            elseif(empty($vop_config['carrier_code']))
            {
                $this->end(false, '请设置承运商');
            }
            elseif(empty($vop_config['dly_mode']))
            {
                $this->end(false, '请设置配送方式');
            }
        }
        else
        {
            //清空数据
            $vop_config['exec_hour']     = '';
            $vop_config['branch_id']     = 0;
            $vop_config['carrier_code']  = '';
            $vop_config['dly_mode']      = '';
            $vop_config['arrival_type']  = '';
            $vop_config['arrival_day']   = 0;
            $vop_config['arrival_hour']  = '';
        }

        //保存配置
        if($row)
        {
            $vop_config['sid']    = $row['sid'];

            $result    = $setObj->update($vop_config, array('sid'=>$row['sid']));

            //清除关联店铺
            $setShopObj->delete(array('sid'=>$row['sid']));
        }
        else
        {
            $vop_config['create_time']    = time();
            $result    = $setObj->save($vop_config);
        }

        if(!$result)
        {
            $this->end(false, '保存失败');
        }

        //保存关联店铺
        foreach ($shop_ids as $key => $shop_id)
        {
            $data    = array('sid'=>$vop_config['sid'], 'shop_id'=>$shop_id);
            $setShopObj->insert($data);
        }

        $this->end(true, '保存成功');
    }

    /**
     * Ajax加载承运商
     */

    function ajax_carrier_code()
    {
        $ids        = $_POST['shop_ids'];
        $ids        = explode(',', $ids);
        $shop_ids   = array();

        if($ids)
        {
            foreach ($ids as $key => $val)
            {
                if($val && $val != '_ALL_')
                {
                    $shop_ids[]    = $val;
                }
            }
        }

        if(empty($shop_ids))
        {
            echo json_encode(array('res'=>'error'));
            exit;
        }

        $stockLib       = kernel::single('purchase_purchase_stockout');
        $carrierList    = $stockLib->getCarrierCode($shop_ids);

        //格式化
        $dataList    = array();
        foreach ($carrierList as $carrier_code => $carrier_name)
        {
            $dataList[]    = array('carrier_code'=>$carrier_code, 'carrier_name'=>$carrier_name);
        }

        echo json_encode(array('res'=>'succ', 'carrier_list'=>$dataList));
        exit;
    }
}
