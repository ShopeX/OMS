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

class base_mdl_services extends base_db_model{

    function __construct(&$app){
        $this->app = $app;
        $this->columns = array (
            'content_id'=>array(
                'label'=>'',
                'width'=>200,
                'type' => 'number',
                'pkey' => true,
                'extra' => 'auto_increment',
            ),
            'content_type' =>
            array (
                'label'=>app::get('base')->_('支式'),
                'width'=>200,
                'type' => 'varchar(80)',
                'required' => true,
                'hidden' => true,
                'width' => 100,
                'in_list' => true,
                'default_in_list' => true,
            ),
            'app_id' =>
            array (
                'label'=>'app',
                'width'=>200,
                'type' => 'table:apps',
                'required' => true,
                'width' => 100,
                'in_list' => true,
                'default_in_list' => true,
            ),
            'content_name'=>array(
                'label'=>'',
                'width'=>200,
                'type'=>'varchar(80)',
            ),
            'content_title'=>array(
                'label'=>'',
                'width'=>200,
                'type'=>'varchar(100)',
                'is_title'=>true,
            ),
            'content_path'=>array(
                'label'=>app::get('base')->_('支式'),
                'width'=>200,
                'type'=>'varchar(255)',
            ),
            'disabled'=>array(
                'label'=>app::get('base')->_('支式'),
                'width'=>200,
                'type'=>'bool',
                 'hidden' => true,
                'default'=>'true'
            )
        );

        $this->schema = array(
                'default_in_list'=>array_keys($this->columns),
                'in_list'=>array_keys($this->columns),
                'idColumn'=>'app_id',
                'columns'=>&$this->columns
            );
    }

    function get_schema(){
        return $this->schema;
    }

    function count($filter=''){
        //useless code just echo 1; edit by danny
        //return count(kernel::servicelist('ectools_payment.ectools_mdl_payment_cfgs'));
        return 1;
    }

    public function getList($cols='*', $filter='null', $offset=0, $limit=-1, $orderby=null)
    {
        $data = app::get('base')->model('app_content')->getlist('*',$filter);
        return $data;
    }
}
