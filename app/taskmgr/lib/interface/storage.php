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
 * 导出数据文件存储介质外部调用接口类
 *
 * @author kamisama.xia@gmail.com
 * @version 0.1
 */

//加载配置信息
require_once(dirname(__FILE__) . '/../../config/config.php');

class taskmgr_interface_storage{

	private static $__storageObj;

	public function __construct(){
		if(!isset(self::$__storageObj)){
	        $storageClass = sprintf('taskmgr_storage_%s', __STORAGE_MODE);
	        self::$__storageObj = new $storageClass();
    	}
	}

    //存储数据成文件
    public function save($source_file, $task_id, &$url, $extName = ''){
        return self::$__storageObj->save($source_file, $task_id, $url, $extName);
    }

    //读取文件获取数据
    public function get($url, $local_file){
    	return self::$__storageObj->get($url, $local_file);
    }

    //读取文件获取数据
    public function delete($url){
    	return self::$__storageObj->delete($url);
    }

    //识别当前文件存储模式，是否是本地存储模式
    public function isLocalMode(){
        if(defined('__STORAGE_MODE') && strtolower(__STORAGE_MODE) == 'local'){
            return true;
        }else{
            return false;
        }
    }
}
