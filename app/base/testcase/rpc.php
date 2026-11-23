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
 
class rpc extends PHPUnit_Framework_TestCase
{
    public function setUp(){
        //$this->model = app::get('base')->model('members');
    }

    public function testRequest(){
        
        //插入一条服务器消息
//        $server = array(
//                'node_id'=>'5',
//                'node_url'=>kernel::base_url(),
//                'node_name'=>'localhost',
//                'node_api'=>'index.php/api',
//                'sitekey'=>md5(123456),
//            );
//        app::get('base')->model('network')->replace($server,array('node_id'=>5));


        $params = array(
            'fields'=>'brand_id,brand_name',
            'shopurl'=>'http://shop1.test.shopex.cn:20210/api.php',
        );

        $rst = app::get('base')->matrix()->set_callback('dev_sandbox','show',array(1,2,3,'aa'=>time()))
            ->set_timeout(10)
            ->call('store.shopbrands.list.get',$params);
    }
}
