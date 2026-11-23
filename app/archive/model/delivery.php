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

class archive_mdl_delivery extends dbeav_model{
    var $has_many = array(
        'delivery_items' => 'delivery_items',
        'delivery_order' => 'delivery_order',

    );
    /**
     * ������ֶ�
     *
     * @var string
     **/
    private $__encrypt_cols = array(
        'ship_name'   => 'simple',
        'ship_tel'    => 'phone',
        'ship_mobile' => 'phone',
        'ship_addr'     => 'simple',
    );

    /**
     * ���ݶ���ID��ȡ������.
     * @param   
     * @return 
     * @access  public
     * @author sunjing@shopex.cn
     */
    function get_delivery($order_id)
    {
        $deliveryIds  =$this->_get_deliveryId($order_id);

        $deliveryIds_str = implode(',',$deliveryIds);

        $delivery_list = $this->db->select("SELECT * FROM sdb_archive_delivery WHERE delivery_id in(".$deliveryIds_str.")");
        $delivery_items = $this->_get_delivery_items($deliveryIds);

        $delivery_logino = $this->_get_delivery_logino($deliveryIds_str);
        
        foreach ( $delivery_list as $k=>$delivery ) {
            $delivery_list[$k]['items'] = $delivery_items[$delivery['delivery_id']];
            
            $delivery_list[$k]['logino'] = $delivery_logino[$delivery['delivery_id']];
        }
        
        return $delivery_list;

    }

    
    /**
     * ����order_id
     * @param  
     * @return  
     * @access  public
     * @author sunjing@shopex.cn
     */
    function _get_deliveryId($order_id)
    {
        $order_delivery = $this->db->select("SELECT * FROM sdb_archive_order_delivery WHERE order_id=".$order_id);
        $ids = array();
        foreach ( $order_delivery as $delivery ) {
            $ids[] = $delivery['delivery_id'];
        }
        return $ids;
    }

    
    /**
     * ��������ϸ
     * @param  
     * @return  
     * @access  public
     * @author sunjing@shopex.cn
     */
    function _get_delivery_items($deliveryIds)
    {
        $deliveryIds_str = implode(',',$deliveryIds);
        $items = $this->db->select("SELECT * FROM sdb_archive_delivery_items WHERE delivery_id in(".$deliveryIds_str.")");
        $item_list = array();
        foreach ($items as $item ) {
            $item_list[$item['delivery_id']][] = $item;
        }
        return $item_list;
    }

    
    /**
     * ��ȡ��������������
     * @param  
     * @return  
     * @access  public
     * @author sunjing@shopex.cn
     */
    function _get_delivery_logino($deliveryIds)
    {
        
        $logi_no_list = $this->db->select("SELECT delivery_id,logi_no FROM sdb_archive_delivery_bill WHERE delivery_id in(".$deliveryIds.")");
        $logi_no = array();
        foreach ( $logi_no_list as $logi ) {
            $logi_no[$logi['delivery_id']][] = $logi;
        }
        return $logi_no;
    }

    /*
     * ���ݶ���id��ȡ��������Ϣ
     *
     * @param string $cols
     * @param bigint $order_id ����id
     *
     * @return array $delivery ����������
     */

    function getDeliveryByOrder($cols="*",$order_id){
        $delivery_ids = $this->_get_deliveryId($order_id);
        if($delivery_ids){
            $delivery = $this->getList($cols,array('delivery_id'=>$delivery_ids),0,-1);
            if($delivery){
                foreach($delivery as $k=>$v){
                    if(isset($v['branch_id'])){
                      $branch = $this->db->selectrow("SELECT name FROM sdb_ome_branch WHERE disabled='false' AND branch_id=".intval($v['branch_id']));
                      $delivery[$k]['branch_name'] = $branch['name'];
                    }
                }
                return $delivery;
            }else{
                return array();
            }
        }else{
            return array();
        }
    }

    public function insert(&$data)
    {
        foreach ($this->__encrypt_cols as $field => $type) {
            if (isset($data[$field])) {
                $data[$field] = (string) kernel::single('ome_security_factory')->encryptPublic($data[$field],$type);
            }
        }

        return parent::insert($data);
    }

    public function update($data,$filter=array(),$mustUpdate = null)
    {
        foreach ($this->__encrypt_cols as $field => $type) {
            if (isset($data[$field])) {
                $data[$field] = (string) kernel::single('ome_security_factory')->encryptPublic($data[$field],$type);
            }
        }

        return parent::update($data,$filter,$mustUpdate);
    }

    public function getList($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null)
    {
        $data = parent::getList($cols,$filter,$offset,$limit,$orderType);

        foreach ((array) $data as $key => $value) {
            foreach ($this->__encrypt_cols as $field => $type) {
                if (isset($value[$field])) {
                    $data[$key][$field] = (string) kernel::single('ome_security_factory')->decryptPublic($value[$field],$type);
                }
            }
        }

        return $data;
    }
}

?>