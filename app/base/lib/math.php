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
 
class base_math{

    var $operationDecimals = 0;   //运算精度
    var $operationCarryset = 0;    //运算进位方式
    var $goodsShowDecimals = 0;   //商品显示精度
    var $operationFunc = null;         //运算function

    function base_math(){
        $this->operationDecimals = 1;//$this->app->getConf('system.money.operation.decimals');      //运算精度
        $this->operationCarryset = 2;//$this->app->getConf('system.money.operation.carryset');      //运算进位方式
        $this->goodsShowDecimals = 1;//$this->app->getConf('system.money.decimals');                //商品显示精度

        switch( $this->operationCarryset ){
            case "0":          //四舍五入
                $this->operationFunc = 'round';
                break;
            case "1":          //向上取整
                $this->operationFunc = 'ceil';
                break;
            case "2":          //向下取整
                $this->operationFunc = 'floor';
                break;
            default:          //四舍五入
                $this->operationFunc = 'round';
                break;
        }
    }

    //加
    function plus($numbers){
        if(!is_array($numbers))
            return $this->getOperationNumber($numbers);
        $rs = 0;
        foreach( $numbers as $n ){
            $rs += $this->getOperationNumber($n);
        }
        return $rs;
    }

    //减
    function minus( $numbers ){
        if(!is_array($numbers))
            return $this->getOperationNumber($numbers);
        $rs = $this->getOperationNumber( $numbers[0] );
        for( $i = 1; $i<count($numbers); $i++ ){
            $rs -= $this->getOperationNumber( $numbers[$i] );
        }
        return $rs;
    }

    //乘
    function multiple($numbers){
        if(!is_array($numbers))
            return $this->getOperationNumber($numbers);

        $rs = 1;
        foreach( $numbers as $n ){
            $rs  = $this->getOperationNumber( $rs * $this->getOperationNumber($n) );
        }
        return $rs;
    }

    /**
     *get 取得系统设定的 商品 价格进位方式后的数值
     * return 进位后 商品 价格
     */
    function get( $number ){
        return call_user_func_array( "floor" , $number * pow( 10 , $this->goodsShowDecimals) )/pow( 10 , $this->goodsShowDecimals);
    }

    /**
     *getOperationNumber 取得系统设定的 运算 价格进位方式后的数值
     * return 进位后 运算 价格
     */
    function getOperationNumber( $number ){
        return call_user_func_array( $this->operationFunc , $number * pow( 10 , $this->operationDecimals) )/pow( 10 , $this->operationDecimals);
    }
}
