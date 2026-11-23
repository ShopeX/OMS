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
 
class queue extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
          $this->model = app::get('base')->model('queue');
    }

    public function testEventregister(){
        $data = array(
            'queue_title'=>app::get('base')->_('测试任务'),
            'start_time'=>time(),
            'params'=>array(
                'filter'=>$filter,
                'watermark'=>$_POST['watermark'],
                'size'=>$_POST['size'],
            ),
            'worker'=>'image_rebuild.run',
        );
        $this->model->insert($data);
    }
   
    public function atestRuntask(){
        $_SERVER['SERVER_ADDR'] = 'localhost';
        $this->model->runtask(1);
    }
    
    public function testRun(){
        $data = array(
            'queue_title'=>app::get('base')->_('测试任务'),
            'status'=>'paused',
            'start_time'=>time(),
            'params'=>array(
                'filter'=>$filter,
                'watermark'=>$_POST['watermark'],
                'size'=>$_POST['size'],
            ),
            'worker'=>'image_rebuild.run',
        );
        $this->model->insert($data);
        unset($data['queue_id']);
        $this->model->insert($data);
    }

}
