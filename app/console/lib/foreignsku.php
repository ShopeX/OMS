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
* 关联外部sku
* @author Mr.dong 2011.8.29
*/
class console_foreignsku {
    
    /**
    * 单个添加外部sku关联关系
    * @access public
    * @param String $inner_sku 内部sku
    * @param String $wms_id wmsID
    * @param String $sync_status 同步状态,默认未同步0
    * @return boolean
    */
    public function insert($inner_sku,$wms_id,$sync_status='0'){
        if ( empty($inner_sku) ) return false;

        $foreignObj = app::get('console')->model('foreign_sku');
        $foreign_detail = $foreignObj->dump(array('inner_sku'=>$inner_sku,'wms_id'=>$wms_id));
        if (!$foreign_detail){
            $data = array(
                'inner_sku' => $inner_sku,
                'wms_id' => $wms_id,
                'sync_status' => $sync_status,
            );
            return $foreignObj->insert($data);
        }else{
            $this->update($inner_sku,$wms_id,NULL,$sync_status);
        }
        return true;
    }

    /**
    * 更新外部sku关联关系
    * @access public
    * @param String $inner_sku 内部sku
    * @param String $wms_id wmsID
    * @param String $new_tag 是否新品标识,0:新品,1:非新品
    * @param String $sync_status 同步状态
    * @param String $outer_sku 外部sku
    * @return boolean
    */
    public function update($inner_sku,$wms_id,$new_tag=NULL,$sync_status,$outer_sku=''){
        if ( empty($inner_sku) || empty($wms_id) ) return false;

        if (is_int($outer_sku) || is_float($outer_sku)){
            $outer_sku = sprintf('%.0f',$outer_sku);
        }
        $foreignObj = app::get('console')->model('foreign_sku');
        $filter = array('inner_sku'=>$inner_sku,'wms_id'=>$wms_id);
        $foreign_detail = $foreignObj->dump($filter);
        if ($foreign_detail){
            $sync_status = !IS_NULL($sync_status) ? $sync_status : '0';
            $data = array(
                'sync_status' => $sync_status,
            );
            if (!IS_NULL($new_tag)){
                $data = array_merge($data, array('new_tag' => $new_tag));
            }
            if ($outer_sku){
                $data = array_merge($data, array('outer_sku'=>$outer_sku));
            }
            return $foreignObj->update($data,$filter);
        }
    }

    /**
    * 更新外部sku
    * @access public
    * @param String $inner_sku 内部sku
    * @param String $wms_id wmsID
    * @param String $outer_sku 外部sku
    * @return boolean
    */
    public function update_sku($inner_sku,$wms_id,$outer_sku){
        if ( empty($inner_sku) || empty($wms_id) || empty($outer_sku) ) return false;

        $foreignObj = app::get('console')->model('foreign_sku');
        $filter = array('inner_sku'=>$inner_sku,'wms_id'=>$wms_id);
        $foreign_detail = $foreignObj->dump($filter);
        if ($foreign_detail){
            $data = array(
                'outer_sku' => $outer_sku,
            );
            return $foreignObj->update($data,$filter);
        }
        return true;
    }

    /**
    * 删除sku信息
    * @access public
    * @param Int $product_id 货品ID
    * @return bool
    */
    public function delete_sku($product_id=''){
        if (empty($product_id)) return false;

        $foreignModel = app::get('console')->model('foreign_sku');
        $filter = array('inner_product_id'=>$product_id);
        return $foreignModel->delete($filter);
    }

    /**
    * 批量更新SKU状态
    * @access public
    * @param Array $data SKU数据,多维
     *              $data = array(
      *                array(
       *                 'inner_sku' => '内部SKU',
        *                'outer_sku' => '外部SKU',
         *               'status' => '同步状态,0 => '未同步',1 => '同步失败',2 => '同步中',3 => '同步成功',4 => '同步后编辑',
          *            ),
           *        );
    * @param String $node_id 节点标识号
    * @return boolean
    */
    public function set_sync_status($data,$node_id=''){
        if (!is_array($data) || empty($data) || empty($node_id)) return false;

        $wms_id = kernel::single('channel_func')->getWmsIdByNodeId($node_id);
        foreach ($data as $val){
            if (in_array($val['status'],array('0','1','2','3','4'))){
                $new_tag = '1';
                $sync_status = $val['status'];
            }else{
                $new_tag = NULL;
                $sync_status = '1';
            }
            $inner_sku = $this->get_inner_sku($wms_id,$val['inner_sku']);
            $this->update($inner_sku,$wms_id,$new_tag,$sync_status,$val['outer_sku']);
        }
        return true;
    }

    /**
    * 获取外部sku
    * @access public
    * @param String $inner_sku 内部sku
    * @param String $wms_id wmsID
    * @return String $outer_sku 外部sku
    */
    public function get_outer_sku($inner_sku,$wms_id){
        $foreignObj = app::get('console')->model('foreign_sku');
        $foreign_detail = $foreignObj->dump(array('inner_sku'=>$inner_sku,'wms_id'=>$wms_id));
        if (!$foreign_detail){
            return $inner_sku;
        }else{
            return !empty($foreign_detail['outer_sku']) ? $foreign_detail['outer_sku'] : $inner_sku;
        }
    }

    /**
    * 获取内部sku
    * @access public
    * @param String $wms_id wmsID
    * @param String $outer_sku 外部sku
    * @return String $inner_sku 内部sku
    */
    public function get_inner_sku($wms_id,$outer_sku){
        return $outer_sku;
        $foreignObj = app::get('console')->model('foreign_sku');
        $foreign_detail = $foreignObj->dump(array('wms_id'=>$wms_id,'outer_sku'=>$outer_sku));
        if (!$foreign_detail){
            return $outer_sku;
        }else{
            return !empty($foreign_detail['inner_sku']) ? $foreign_detail['inner_sku'] : $outer_sku;
        }
    }

    /**
    * 获取当前wms sku是否需要再次同步
    * @access public
    * @param String $inner_sku 内部sku
    * @param String $wms_id wms ID
    * @return array('status'=>'allow','method'=>'') method:add商品添加,update商品编辑
    */
    public function sync($inner_sku,$wms_id){

        $result = array('status'=>'', 'method'=>'');
        $foreignObj = app::get('console')->model('foreign_sku');
        $foreign_detail = $foreignObj->dump(array('inner_sku'=>$inner_sku,'wms_id'=>$wms_id));
        if (!$foreign_detail || ($foreign_detail['new_tag'] == '0' && $foreign_detail['sync_status'] == '0')){
            $result['status'] = 'allow';
            $result['method'] = 'add';
        }elseif($foreign_detail['new_tag'] == '1' && $foreign_detail['sync_status'] == '0'){
            $result['status'] = 'allow';
            $result['method'] = 'update';
        }else{
            $result['status'] = 'deny';
        }
        return $result;
    }

    /**
    * 是否已同步
    * @access public
    * @param String $inner_sku 货号
    * @param String $node_id
    * @return bool true已同步 false未同步
    */
    public function issync($inner_sku,$node_id=''){
        if (empty($inner_sku) || empty($node_id)) return false;

        $wms_id = kernel::single('channel_func')->getWmsIdByNodeId($node_id);
        $foreign_skuModel = app::get('console')->model('foreign_sku');
        $foreign_detail = $foreign_skuModel->dump(array('inner_sku'=>$inner_sku,'wms_id'=>$wms_id));
        if ($foreign_detail['sync_status'] == '3'){
            return true;
        }
        return false;
    }


    /**
    * 获取当前wms sku信息
    * @access public
    * @param String $inner_sku 内部sku
    * @param String $wms_id wms ID
    * @return Array sku信息
    */
    public function sku_info($inner_sku,$wms_id){

        $foreignObj = app::get('console')->model('foreign_sku');
        $foreign_detail = $foreignObj->dump(array('inner_sku'=>$inner_sku,'wms_id'=>$wms_id));
        return $foreign_detail;
    }


    /**
    * 获取当前wms sku信息
    * @access public
    * @param String $inner_sku 内部sku
    * @param String $wms_id wms ID
    * @return Array sku信息
    */
    public function update_sync_status($bn){

        $db = kernel::database();
        $sql ="UPDATE sdb_console_foreign_sku set sync_status = '4' WHERE inner_sku = '".$bn."' AND sync_status = '3'";
        return $db->exec($sql);
    }
 
    /**
    * 批量更新商品同步状态
    *
    */
    public function batch_syncupdate($wms_id,$new_tag,$sync_status,$bns){
        $db = kernel::database();
        if($bns){
            $bns_str = implode(',',$bns);
            $sql = sprintf('UPDATE `sdb_console_foreign_sku` SET new_tag=\'%s\',sync_status=\'%s\' WHERE wms_id=\'%s\' AND inner_sku IN (%s)',$new_tag,$sync_status,$wms_id,$bns_str);
            
            $result = $db->exec($sql);
            return $result;
        }
        
    }


}