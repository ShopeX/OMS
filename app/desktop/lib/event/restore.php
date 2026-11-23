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

class desktop_event_restore{

    function __construct($app){
        $this->app = $app;
    }

    function restoreEvent(){
        base_kvstore::instance('b2c_goods')->store('goods_cat.data',false);
        base_kvstore::instance('b2c_goods')->store('goods_virtualcat.data',false);
        base_kvstore::instance('b2c_goods')->store('goods_virtualcat.all.data',false);

        $objB2c = app::get('b2c');

        $objvircat = $objB2c->model('goods_virtual_cat');
        $vircat_id = $objvircat->getList('virtual_cat_id',array());
        if(is_array($vircat_id)){
	        foreach($vircat_id as $vk=>$val){
	        	$url = app::get('site')->router()->gen_url(array('app'=>'b2c','ctl'=>'site_gallery','args'=>array(null,null,0,'','',$val['virtual_cat_id']) ) );
	          // $objvircat->update(array('url'=>$url),array('virtual_cat_id'=>$val['virtual_cat_id']));
	            $objvircat->db->exec('UPDATE sdb_b2c_goods_virtual_cat SET url = \''.$url.'\' WHERE virtual_cat_id = '.$val['virtual_cat_id'] );
	        }
        }
    }


}
