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

class allocatein extends PHPUnit_Framework_TestCase
{
    function setUp() {

    }

    public function testAllocatein(){
        $isoObj = app::get('taoguaniostockorder')->model("iso");
        $data = array (
         'iso_id'=>'',
         'iso_bn'=>''
        );
        
        $wms_id = kernel::single('ome_branch')->getWmsIdById($data['branch_id']);
        $result = kernel::single('console_event_trigger_otherstockin')->create(array('iso_id'=>$iso_id),false);
        
        $isoObj->update(array('check_status'=>'2'),array('iso_bn'=>$data['io_bn']));
        //print_r($result);

        #ocs发货单请求日志参数
        $contents = @file_get_contents('http://ocs2.test.vmod.cn/ocs1.3.0encode/data/store.wms.inorder.create');
        $ocs_params = @unserialize($contents);

        #讨管发货单请求日志参数
        $tg_contents = @file_get_contents('http://localhost/oms/data/store.wms.inorder.create');
        $tg_params = @unserialize($tg_contents);

        $diff_arr_item = middleware_func::compare_params($ocs_params,$tg_params);
        print_r($diff_arr_item);
    }
}
