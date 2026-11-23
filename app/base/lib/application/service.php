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
 
class base_application_service extends base_application_prototype_xml{

    /**
     * 是否强制更新
     */
    static $force_update = false;

    var $xml='services.xml';
    var $xsd='base_app';
    var $path='service';

    function set_current($current){
        $this->current = $current;
    }

    public function current() {
        $this->current = $this->iterator()->current();
        $this->key = $this->current['id'];
        return $this;
    }

    public function install(){
        kernel::log('Installing '.$this->content_typename().' '.$this->key());
        
        $data = $this->row($fag,$key);
        $data['content_type'] = 'service_category';
        if($this->current['optname']){
            $data['content_title'] = $this->current['optname'];
        }
        if($this->current['opttype']){
            $data['content_path'] = $this->current['opttype'];
        }
		$obj_app_content = app::get('base')->model('app_content');
        $obj_app_content->insert($data);
        
        //base_kvstore::instance('service')->fetch($this->key,$service_define);
		
		$index = 0;
		$time = time();
		$service_define = array();
        foreach((array)$this->current['class'] as $class){
            $row = $this->row($fag,$key);
            $row['content_path'] = $class['value'];
			if ($class['orderby'])
				$row['ordernum'] = $class['orderby'];
			else
				$row['ordernum'] = 50;
			$row['input_time'] = $time+$index;
            $obj_app_content->insert($row);
            //$service_define['list'][$class['value']] = $class['value'];
            //todo: interface... check
			$index++;
        }
		$arr_servicelist = $obj_app_content->getList('content_path',array('content_name'=>$this->key,'content_type'=>'service'), 0, -1, 'ordernum ASC, input_time DESC');
		foreach ((array)$arr_servicelist as $arr){
			$service_define['list'][$arr['content_path']] = $arr['content_path'];
		}
		
        /*if(is_array($service_define['list']) && count($service_define['list'])){
            foreach($service_define['list'] AS $service_name){
                $strpos = strpos($service_name, "_");
                if($strpos){
                    $app_id = substr($service_name, 0, $strpos);
                }else{
                    $app_id = 'base';
                }
                $level = $this->check_service_level($app_id);
                $service_orders[$level][] = $service_name;
            }
            unset($service_define['list']);
            ksort($service_orders);
            foreach($service_orders AS $service_order){
                foreach($service_order AS $service_name){
                    $service_define['list'][$service_name] = $service_name;
                }
            }
        }//todo: depends order*/

        base_kvstore::instance('service')->store($this->key,$service_define);
    }
    
    function clear_by_app($app_id){
        if(!$app_id){
            return false;
        }
        
        $to_remove = array();
        $service_list = app::get('base')->model('app_content')->getlist('content_name,content_path,app_id', array('app_id'=>$app_id, 'content_type'=>'service'));
        foreach($service_list as $service){
            $to_remove[$service['content_name']][] = $service['content_path'];
        }
        foreach($to_remove as $service_name=>$rows){
            if(base_kvstore::instance('service')->fetch($service_name,$service_define)){
                foreach($rows as $row){
                    unset($service_define['list'][$row]);
                }
                base_kvstore::instance('service')->store($service_name,$service_define);
            }
        }
        
        app::get('base')->model('app_content')->delete(array(
            'app_id'=>$app_id,'content_type'=>'service'));
            
        app::get('base')->model('app_content')->delete(array(
            'app_id'=>$app_id,'content_type'=>'service_category'));
    }
    function update($app_id){
        if(!$app_id){
            return false;
        }
        $to_remove = array();
        $service_list = app::get('base')->model('app_content')->getlist('content_name,content_path,app_id', array('app_id'=>$app_id, 'content_type'=>'service'));
        foreach($service_list as $service){
            $to_remove[$service['content_name']][] = $service['content_path'];
        }
        app::get('base')->model('app_content')->delete(array(
            'app_id'=>$app_id,'content_type'=>'service'));
            
        app::get('base')->model('app_content')->delete(array(
            'app_id'=>$app_id,'content_type'=>'service_category'));
        foreach($this->detect($app_id) as $name=>$item){
            if($to_remove[$item->current['id']]) {
                unset($to_remove[$item->current['id']]);
            }
            $item->install();
        }
        foreach($to_remove as $service_name=>$rows){
            if(base_kvstore::instance('service')->fetch($service_name,$service_define)){
                foreach($rows as $row){
                    unset($service_define['list'][$row]);
                }
                base_kvstore::instance('service')->store($service_name,$service_define);
            }
        }
        return true;
    }
    private function check_depends($app_id, &$queue){
        $depends_app = app::get($app_id)->define('depends/app');
        foreach((array)$depends_app as $depend_app_id){
            $this->check_depends($depend_app_id['value'], $queue);
        }
        $queue[$app_id] = $app_id;
    }//End Function

    private function check_service_level($app_id) 
    {
        if($app_id == 'base')   return 0;
        $queue = array();
        $this->check_depends($app_id, $queue);
        $queue = array_keys($queue);
        $apps = array_flip($queue);
        return $apps[$app_id];
    }//End Function

    function last_modified($app_id){
    
        if (self::$force_update) {
            return -1;
        } else {

            return parent::last_modified($app_id);
        }
    }
} 
