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
 * 在途监控
 *
 * @author chenping@shopex.cn
 * @time Sun Jun 30 11:11:53 2019
 */
class console_ctl_admin_delivery_logistics extends desktop_controller
{

    public $name               = "发货中心";
    public $workground         = "delivery_center";

    public function _views()
    {
    	if (method_exists($this, sprintf('_%s_views', $_GET['act']))) {
    		return $this->{'_'.$_GET['act'].'_views'}();
    	}

    	return array ();
    }

    public function _lanshou_views()
    {
        $sub_menu = array (
        	array ('label' => '未揽收', 'addon' => 'showtab', 'optional' => false, 'filter' => array ('status' => 'succ', 'logi_status' => '0','parent_id'=>'0')),
        	array ('label' => '已揽收', 'addon' => 'showtab', 'optional' => false, 'filter' => array ('status' => 'succ', 'logi_status' => '1','parent_id'=>'0')),
        );
        return $sub_menu;
    }

    public function _qianshou_views()
    {
        $sub_menu = array (
        	array ('label' => '五天未签收', 'addon' => 'showtab', 'optional' => false, 'filter' => array ('delivery_time|lthan'=>strtotime('-5 days'), 'status' => 'succ', 'logi_status|noequal' => '3','parent_id'=>'0')),
        	array ('label' => '三天未签收', 'addon' => 'showtab', 'optional' => false, 'filter' => array ('delivery_time|between'=>array (strtotime('-5 days'),strtotime('-3 days')), 'status' => 'succ', 'logi_status|noequal' => '3','parent_id'=>'0')),
        	array ('label' => '已签收', 'addon' => 'showtab', 'optional' => false, 'filter' => array ('status' => 'succ', 'logi_status' => '3','parent_id'=>'0')),
        );
        return $sub_menu;
    }

	/**
	 * 揽收列表
	 *
	 * @return void
	 * @author 
	 **/
	public function lanshou()
	{
        $params = array(
            'title' => '揽件列表',
            'actions' => array (
            	array (
            		'label' => '同步物流状态', 
            		'submit' => $this->url.'&act=batchLogiSync', 
                    'target' => 'dialog::{width:600,height:290,title:\'同步物流状态\'}',
            	),
            ),
			'base_filter'            => array (),
			'use_buildin_new_dialog' => false,
			'use_buildin_set_tag'    => false,
			'use_buildin_recycle'    => false,
			'use_buildin_export'     => false,
			'use_buildin_import'     => false,
			'use_buildin_filter'     => true,
			'use_view_tab'           => true,
			'filter_inheader'        => true,
            // 'object_method' => array('count' => 'logisticsCount', 'getlist' => 'logisticsList'),
        );

        // 如果发货时间没传默认2个月
        if (!$_POST['delivery_time']) {
			$_POST['delivery_time']                      = '1';
			$_POST['_delivery_time_search']              = 'between';
			$_POST['_DTIME_']['H']['delivery_time_from'] = '00';
			$_POST['_DTIME_']['H']['delivery_time_to']   = '00';
			$_POST['_DTIME_']['M']['delivery_time_from'] = '00';
			$_POST['_DTIME_']['M']['delivery_time_to']   = '00';
			$_POST['delivery_time_from']                 = date('Y-m-d',strtotime('-2 month'));
			$_POST['delivery_time_to']                   = date('Y-m-d',strtotime('+1 day'));
        }

        require_once APP_DIR.'/base/datatypes.php';

        $params['base_filter']['status'] = 'succ';
        $params['base_filter']['parent_id'] = '0';

        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super) {
            $branch_ids = app::get('ome')->model('branch')->getBranchByUser(true);
            if ($branch_ids) {
                $params['base_filter']['ext_branch_id'] = $_POST['branch_id'] ? array_intersect(array($_POST['branch_id']), $branch_ids) : $branch_ids;
            } else {
                $params['base_filter']['ext_branch_id'] = 'false';
            }
        }


        $params['title'] .= '，查询范围：发货时间'.$datatypes['time']['searchparams'][$_POST['_delivery_time_search']];
        if ($_POST['delivery_time'] == '1') {
        	$params['title'] .= $_POST['delivery_time_from'].'~'.$_POST['delivery_time_to'];
        } else {
        	$params['title'] .= $_POST['delivery_time'];
        }

        $this->finder('console_mdl_delivery', $params);
	}

	/**
	 * 签收列表
	 *
	 * @return void
	 * @author 
	 **/
	public function qianshou()
	{
        $params = array(
            'title' => '签收列表',
            'actions' => array (
            	array (
            		'label' => '同步物流状态', 
            		'submit' => $this->url.'&act=batchLogiSync', 
                    'target' => 'dialog::{width:600,height:290,title:\'同步物流状态\'}',
            	),
            ),
			'base_filter'            => array (),
			'use_buildin_new_dialog' => false,
			'use_buildin_set_tag'    => false,
			'use_buildin_recycle'    => false,
			'use_buildin_export'     => false,
			'use_buildin_import'     => false,
			'use_buildin_filter'     => true,
			'use_view_tab'           => true,
			'filter_inheader'        => true,
            // 'object_method' => array('count' => 'logisticsCount', 'getlist' => 'logisticsList'),
        );

        // 如果发货时间没传默认2个月
        if (!$_POST['delivery_time']) {
			$_POST['delivery_time']                      = '1';
			$_POST['_delivery_time_search']              = 'between';
			$_POST['_DTIME_']['H']['delivery_time_from'] = '00';
			$_POST['_DTIME_']['H']['delivery_time_to']   = '00';
			$_POST['_DTIME_']['M']['delivery_time_from'] = '00';
			$_POST['_DTIME_']['M']['delivery_time_to']   = '00';
			$_POST['delivery_time_from']                 = date('Y-m-d',strtotime('-2 month'));
			$_POST['delivery_time_to']                   = date('Y-m-d',strtotime('+1 day'));
        }

        require_once APP_DIR.'/base/datatypes.php';

        $params['base_filter']['status'] = 'succ';
        $params['base_filter']['parent_id'] = '0';

        $is_super = kernel::single('desktop_user')->is_super();
        if (!$is_super) {
            $branch_ids = app::get('ome')->model('branch')->getBranchByUser(true);
            if ($branch_ids) {
                $params['base_filter']['ext_branch_id'] = $_POST['branch_id'] ? array_intersect(array($_POST['branch_id']), $branch_ids) : $branch_ids;
            } else {
                $params['base_filter']['ext_branch_id'] = 'false';
            }
        }

        $params['title'] .= '，查询范围：发货时间'.$datatypes['time']['searchparams'][$_POST['_delivery_time_search']];
        if ($_POST['delivery_time'] == '1') {
        	$params['title'] .= $_POST['delivery_time_from'].'~'.$_POST['delivery_time_to'];
        } else {
        	$params['title'] .= $_POST['delivery_time'];
        }


        $this->finder('console_mdl_delivery', $params);
	}

	public function batchLogiSync()
	{
        $this->pagedata['request_url'] = $this->url.'&act=ajaxBatchLogiSync';
        // $this->pagedata['autotime']    = '500';

        parent::dialog_batch('ome_mdl_delivery',true,20,'incr');
	}

	public function ajaxBatchLogiSync()
	{
        parse_str($_POST['primary_id'], $postdata);

        if (!$postdata['f']) { echo 'Error: 请先选择发货单';exit;}

        $retArr = array(
            'itotal'  => 0,
            'isucc'   => 0,
            'ifail'   => 0,
            'err_msg' => array(),
        );

        $deliveryMdl = app::get('ome')->model('delivery');
        $deliveryMdl->filter_use_like = true;

        $rows = $deliveryMdl->getList('delivery_id,delivery_bn',$postdata['f'],$postdata['f']['offset'],$postdata['f']['limit']);

        $retArr['itotal'] = count($rows);

        foreach ($rows as $row) {
        	// list($res, $msg) = kernel::single('ome_delivery_logistics')->syncLogiStatus($row['delivery_id']);
            // 重新订阅
            kernel::single('ome_event_trigger_shop_hqepay')->hqepay_pub($row['delivery_id']);

            $retArr['isucc']++;
        }

        echo json_encode($retArr),'ok.';exit;
	}
}