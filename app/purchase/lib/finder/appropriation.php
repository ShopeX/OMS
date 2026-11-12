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

class purchase_finder_appropriation{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }

    var $detail_basic = "调拔单详情";
     
    function detail_basic($appropriation_id){
       $render = app::get('purchase')->render();
       $oAppropriation = app::get('purchase')->model("appropriation_items");
       $appropriation = $oAppropriation->getList('*',array('appropriation_id'=>$appropriation_id),0,-1);
       $oBranch = app::get('ome')->model('branch');
       $oPos = app::get('ome')->model('branch_pos');
       if ($appropriation)
       foreach($appropriation as $k=>$v){
           if($v['from_branch_id']){
                $from_branch = $oBranch->dump(array('branch_id'=>$v['from_branch_id']),'name');
                $v['from_branch'] = $from_branch['name'];
           }
           if($v['from_pos_id']){
            $from_pos = $oPos->dump(array('branch_id'=>$v['from_branch_id'],'pos_id'=>$v['from_pos_id']),'store_position');
            $v['from_pos'] = $from_pos['store_position'];
           }
           if($v['to_branch_id']){
                $to_branch = $oBranch->dump(array('branch_id'=>$v['to_branch_id']),'name');
                $v['to_branch'] = $to_branch['name'];
           }
           if($v['to_pos_id']){
                $to_pos = $oPos->dump(array('branch_id'=>$v['to_branch_id'],'pos_id'=>$v['to_pos_id']),'store_position');
                $v['to_pos'] = $to_pos['store_position'];
           }
           $appropriation_list[] = $v;
       }
  
        $render->pagedata['appropriation'] = $appropriation_list;
        return $render->fetch('admin/appropriation/appropriation_detail.html');
    }
    
}

?>
