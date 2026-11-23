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
 * 接口字段映射模型
 * 
 * @copyright shopex.cn 2025.01.01
 * @author system
 */
class desktop_mdl_interfacefield_mapper extends dbeav_model
{
    /**
     * 根据渠道和场景获取映射配置
     * 
     * @param string $channel_id 渠道ID
     * @param string $channel_type 渠道类型
     * @param string $scene 场景
     * @return array|null
     */
    public function getMapperByChannel($channel_id, $channel_type, $scene = '')
    {
        $filter = array(
            'channel_id' => $channel_id,
            'channel_type' => $channel_type,
        );
        
        if ($scene) {
            $filter['scene'] = $scene;
        }
        
        return $this->db_dump($filter);
    }
    
    /**
     * 获取所有映射配置
     * 
     * @param string $channel_id 渠道ID
     * @param string $channel_type 渠道类型
     * @return array
     */
    public function getMappersByChannel($channel_id, $channel_type)
    {
        $filter = array(
            'channel_id' => $channel_id,
            'channel_type' => $channel_type,
        );
        
        return $this->getList('*', $filter);
    }
    
    /**
     * 保存映射配置
     * 
     * @param array $data 数据
     * @return bool
     */
    public function saveMapper($data)
    {
        if (isset($data['id']) && $data['id']) {
            return $this->save($data);
        } else {
            return $this->insert($data);
        }
    }
    
    /**
     * 删除映射配置
     * 
     * @param int $id 主键ID
     * @return bool
     */
    public function deleteMapper($id)
    {
        return $this->delete(array('id' => $id));
    }
} 