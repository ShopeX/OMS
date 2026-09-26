<?php
class ome_mdl_order_extend extends dbeav_model {

    public function insert(&$data)
    {
        $this->_stripExtendFieldEmoji($data);
        return parent::insert($data);
    }

    public function update($data, $filter = array(), $mustUpdate = null)
    {
        $this->_stripExtendFieldEmoji($data);
        return parent::update($data, $filter, $mustUpdate);
    }

    public function save(&$data, $mustUpdate = null)
    {
        $this->_stripExtendFieldEmoji($data);
        return parent::save($data, $mustUpdate);
    }

    public function addRouterNum($orderId) {
        $extend_detail = $this->db->selectrow("SELECT order_id FROM sdb_ome_order_extend WHERE order_id='".$orderId."'");
        if ($extend_detail){
            $sql = 'update sdb_ome_order_extend set router_num = router_num + 1 where order_id = "'.$orderId.'"';
        }else{
            $sql = "insert into sdb_ome_order_extend(order_id,router_num) VALUES('$orderId','1')";
        }
        $this->db->exec($sql);
    }

    public function updateBoolExtendStatus($orderId, $boolStatus) {
        if($boolStatus > 0) {
        	$extend_detail = $this->db->selectrow("SELECT order_id FROM sdb_ome_order_extend WHERE order_id='".$orderId."'");
        	if ($extend_detail){
        		$sql = 'update sdb_ome_order_extend set bool_extendstatus = bool_extendstatus | ' . intval($boolStatus) . ' where order_id = "' . $orderId . '"';
        	}else{
        		$sql = "insert into sdb_ome_order_extend(order_id,bool_extendstatus) VALUES('$orderId','$boolStatus')";
        	}
            
            $this->db->exec($sql);
        }
    }

    /**
     * 入库前去掉 emoji 等 4 字节 UTF-8（库表实际非 utf8mb4 时避免 Incorrect string value）
     */
    private function _stripExtendFieldEmoji(&$data)
    {
        if (!is_array($data) || !isset($data['extend_field']) || $data['extend_field'] === '' || $data['extend_field'] === null) {
            return;
        }
        if (is_array($data['extend_field'])) {
            $json = json_encode($data['extend_field'], JSON_UNESCAPED_UNICODE);
            $json = $this->_remove4ByteChars($json);
            $decoded = json_decode($json, true);
            $data['extend_field'] = ($decoded !== null) ? $decoded : $json;
        } else {
            $data['extend_field'] = $this->_remove4ByteChars((string) $data['extend_field']);
        }
    }

    private function _remove4ByteChars($str)
    {
        if ($str === '' || $str === null) {
            return $str;
        }
        return preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $str);
    }
}
