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
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */

class desktop_keyboard_setting {
    private $_setting_key = array('全局操作','界面操作','导航菜单上的栏目');

    
    public function set_default_control( $default,&$row,$skip=false ) {
        if( !$skip ) {
            if( $row['params']['control'] && is_array($row['params']['control']) ) {
                foreach( $row['params']['control'] as $control => $flag ) {
                    if( !in_array($control,$default) ) unset($row['params']['control'][$control]);
                }
            } else {
                $this->set_default_control( $default,$row,true );
            }
        }
        
        foreach( $default as $_key ) {
            if( !isset($row['params']['control'][$_key]) ) $row['params']['control'][$_key] = 'false';
        }
        if( is_array($row['params']['control']) )
            ksort( $row['params']['control'] );
    }
    public function init_keyboard_setting_data( &$setting,&$keyword,$keyboard_setting=array() ) {
        #echo "<pre>";print_r($keyboard_setting);exit;
        $_setting = array();
        foreach( kernel::servicelist('desktop_keyboard_setting') as $object ) {
            foreach( (array)$object->setting as $key => $row ) {
                //默认只有三项。
                if( !in_array($key,$this->_setting_key) ) continue;
                
                $_setting[$key] = array_merge( (array)$_setting[$key],$row );
            }
        }
        $setting = array();
        foreach( $_setting as $key => $row ) {
            foreach( $row as $_key => $val ) {
                $setting[$key][$val['title']] = $val;
            }
        }
        if( is_array($keyboard_setting) ) {
            foreach( $keyboard_setting as $_keyboard_setting_key => $_keyboard_setting ) {
                if( !is_array($_keyboard_setting) ) continue;
                foreach( $_keyboard_setting as $_keyboard_key => $keyboard ) {
                    if( $keyboard['params']['control'] ) {  //控制键
                        foreach( $keyboard['params']['control'] as $key => $val ) {
                            $setting[$_keyboard_setting_key][$_keyboard_key]['params']['control'][$key] = ($val=='true' ? 'true' : 'false');
                        }
                    }
                    
                    if( $keyboard['params']['keyword'] ) //附加件
                        $setting[$_keyboard_setting_key][$_keyboard_key]['params']['keyword'] = $keyboard['params']['keyword'];
                    $setting[$_keyboard_setting_key][$_keyboard_key]['use'] = $keyboard['use'];
                }
            }
        }
        
        #$_keyword = strtoupper('a b c d e f g h i j k l m n o p q r s t u v w x y z / ; [ ] - =');
        $_keyword = strtoupper('a b c d e f g h i j k l m n o p q r s t u v w x y z 1 2 3 4 5 6 7 8 9');
        $_keyword = explode(' ',$_keyword);
        foreach( $_keyword as $key => $val ) {
            if( !$val ) continue;
            $keyword[$val] = $val;
        }
    }
    
    public function get_setting_json( $setting ) {
        $this->init_keyboard_setting_data( $keyboard_setting,$keyboard,$setting );
        foreach( (array)$setting as $_keyboard_setting_key => $row ) {
            foreach( (array)$row as $_keyboard_key => $val ) {
                if( $val['use']!='true' ) continue;
                $tmp[] = $keyboard_setting[$_keyboard_setting_key][$_keyboard_key];
            }
        }
        $json = array();
        foreach( (array)$tmp as $setting ) {
            $return = array();
            $return['type'] = $setting['type'];
            $return['arg'] = $setting['arg'];
            if( $setting['options'] )
                $return['options'] = $setting['options'];
            
            foreach( (array)$setting['params']['control'] as $key => $val ) {
                if( $val=='true' ) $return['keycode'][] = $key;
            }
            $return['keycode'][] = strtolower( $setting['params']['keyword'] );
            $json[] = $return;
        }
        return json_encode($json);
    }
}
