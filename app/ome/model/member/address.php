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

class ome_mdl_member_address extends dbeav_model{

    /**
     * 须加密字段（本地加密，支持透明读写）
     *
     * @var array
     */
    private $__encrypt_cols = array(
        'ship_name'   => 'simple',
        'ship_addr'   => 'simple',
        'ship_mobile' => 'phone',
        'ship_tel'    => 'phone',
    );

    /**
     * 加密数据（写入前）
     *
     * @param array $data
     * @return void
     */
    protected function _encryptData(&$data)
    {
        $security = kernel::single('ome_security_factory');
        foreach ($this->__encrypt_cols as $field => $type) {
            if (!isset($data[$field])) {
                continue;
            }
            // 避免重复加密（本地密文）
            if ($security->isLocalEncryptData($data[$field], $type)) {
                continue;
            }
            $data[$field] = (string)$security->encryptPublic($data[$field], $type);
        }
    }

    /**
     * 解密数据（读出后）
     *
     * @param array $data
     * @return void
     */
    protected function _decryptData(&$data)
    {
        $security = kernel::single('ome_security_factory');
        foreach ($this->__encrypt_cols as $field => $type) {
            if (isset($data[$field])) {
                $data[$field] = (string)$security->decryptPublic($data[$field], $type);
            }
        }
    }

    public function insert(&$data)
    {
        $this->_encryptData($data);
        return parent::insert($data);
    }

    public function update($data, $filter = array(), $mustUpdate = null)
    {
        $this->_encryptData($data);
        return parent::update($data, $filter, $mustUpdate);
    }

    public function getList($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null)
    {
        $rows = parent::getList($cols, $filter, $offset, $limit, $orderType);
        foreach ((array)$rows as $k => $row) {
            $this->_decryptData($row);
            $rows[$k] = $row;
        }
        return $rows;
    }

    public function finder_getList($cols='*', $filter=array(), $offset=0, $limit=-1, $orderType=null)
    {
        $rows = parent::finder_getList($cols, $filter, $offset, $limit, $orderType);
        foreach ((array)$rows as $k => $row) {
            $this->_decryptData($row);
            $rows[$k] = $row;
        }
        return $rows;
    }

    public function dump($filter, $cols='*', $subSdf=false)
    {
        $row = parent::dump($filter, $cols, $subSdf);
        if ($row) {
            $this->_decryptData($row);
        }
        return $row;
    }

    /**
     * 创建_address
     * @param mixed $data 数据
     * @return mixed 返回值
     */
    public function create_address($data){
        $member_id = (int)$data['member_id'];
        if (!$member_id) {
            return;
        }

        // 使用明文计算 address_hash，避免被透明加密影响去重逻辑
        $plain = $data;
        $address_hash = sprintf('%u', crc32(
            (string)($plain['ship_name'] ?? '') . '-' . (string)($plain['ship_area'] ?? '') . (string)($plain['ship_addr'] ?? '')
            . '-' . (string)($plain['ship_mobile'] ?? '') . '-' . (string)($plain['ship_tel'] ?? '')
            . '-' . (string)($plain['ship_zip'] ?? '') . '-' . (string)($plain['ship_email'] ?? '')
        ));

        $data['address_hash'] = $address_hash;
        $address_detail = $this->dump(array('address_hash'=>$address_hash,'member_id'=>$member_id),'address_id');
        if(!$address_detail['address_id']){
            $this->save($data);
        }

        $address_id = (int)($data['address_id'] ? $data['address_id'] : $address_detail['address_id']);
        if($data['is_default'] == '1' && $address_id){
            $this->db->exec("UPDATE sdb_ome_member_address SET is_default='0' WHERE member_id=".$member_id." AND address_id!=".$address_id);

            // 同步更新会员默认联系方式，交由 members 模型做透明加密
            $membersObj = app::get('ome')->model('members');
            $membersObj->update(array(
                'area'   => $plain['ship_area'] ?? '',
                'addr'   => $plain['ship_addr'] ?? '',
                'mobile' => $plain['ship_mobile'] ?? '',
                'tel'    => $plain['ship_tel'] ?? '',
                'email'  => $plain['ship_email'] ?? '',
                'zip'    => $plain['ship_zip'] ?? '',
            ), array('member_id' => $member_id));
        }
        
    }
}
