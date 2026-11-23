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
 
class users extends PHPUnit_Framework_TestCase
{
 
    static $id;
    
    static function gen_random_string($len) { 
        $chars = array( 
        "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k",  
        "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v",  
        "w", "x", "y", "z", "A", "B", "C", "D", "E", "F", "G",  
        "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R",  
        "S", "T", "U", "V", "W", "X", "Y", "Z", "0", "1", "2",  
        "3", "4", "5", "6", "7", "8", "9" 
        ); 
        $charsLen = count($chars) - 1; 
        shuffle($chars);// 将数组打乱
        $output = ""; 
        for ($i=0; $i<$len; $i++)    { 
            $output .= $chars[mt_rand(0, $charsLen)]; //获得一个数组元素
        }  
        return $output;
    }
       
    function setUp() {
        $this->model = app::get('desktop')->model('users');        
    }
    
    public function testSave(){        
        #下面的数据将插入sdb_pam_account表,pam_account来历请查看 users对象的dbschema中的user_id字段
        $sdf['pam_account']['login_name'] = self::gen_random_string(6);
        $sdf['pam_account']['login_password'] = md5('admin');
        #下面的数据插入sdb_desktop_users
        $sdf['name'] = 'admin';
        $sdf['super'] = 1;
        $sdf['roles'] = array(
                array( 'role_id'=>1 ),
                array( 'role_id'=>2 ),
            );
        
       $this->model->save($sdf);
       
       self::$id = $sdf['user_id'];

    }    
    
    public function testDump(){
        $sdf = $this->model->dump(self::$id,'*',array( 'pam_account'=>array('*'),'roles'=>array('*') ));
    }
    
    public function testUpdate(){
        $sdf = $this->model->dump(self::$id,'*',array( 'pam_account'=>array('*'),'roles'=>array('*') ));
        #修改键名为sdf标准
        $sdf['pam_account'] = $sdf['pam_account'];
        unset( $sdf['pam_account']);
        $sdf['name'] = 'ken';        
        $this->model->save($sdf);
        $sdf = $this->model->dump(self::$id,'*',array( 'pam_account'=>array('*'),'roles'=>array('*') ));
    }    
    


    


}
