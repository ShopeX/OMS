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


class base_mdl_rpcnotify extends base_db_model{
    
    var $defaultOrder = array('notifytime','DESC');

    /**
     * filter
     * @param mixed $filter filter
     * @return mixed 返回值
     */
    public function filter($filter){
        unset($filter['use_like']);
        return parent::filter($filter);
    }
    
    public function getList($cols='*', $filter=array(), $offset=0, $limit=-1, $orderby=null)
    {
        $orderType = $orderby ? : $this->defaultOrder;
        $sql = 'SELECT '.$cols.' FROM `'.$this->table_name(true).'` WHERE '.$this->filter($filter);
        if($orderType)$sql.=' ORDER BY '.(is_array($orderType)?implode(' ', $orderType):$orderType);
        $data = $this->db->selectLimit($sql,$limit,$offset);
        $this->tidy_data($data, $cols);
        return $data;
    }
    
    
    /**
     * modifier_status
     * @param mixed $val val
     * @return mixed 返回值
     */
    public function modifier_status( $val ) {
        if( $val=='false' ) {
            return '<a href="javascript:;" onclick="_get_rpcnotify_num(this)" >'.app::get('base')->_('未读').'</a>';
        } else {
            return app::get('base')->_('已读');
        }
    }

    public function modifier_event_type($type)
    {
        if (!$type) {
            return '-';
        }
        if (app::get('monitor')->is_installed()) {
            $eventType = kernel::single('monitor_event_template')->getEventType();
            return isset($eventType[$type]) ? $eventType[$type] : $type;
        }
        return $type;
    }
}
