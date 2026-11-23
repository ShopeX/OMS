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
 
class base_pget extends base_httpclient{

    var $defaultChunk = 512;

    function dl($url,$to=null){
        if(!$to){
            $to = tempnam(false, 'PDL');
        }
        $this->total = 0;
        $this->download = 0;
        $this->_img = 0;
        $this->sock = fopen($to,'wb');
        $this->last = 0;
        kernel::log(sprintf('--%s--  %s',date('H:i:s'),$url));
        kernel::log(sprintf('           => %s',$to));
        echo "\n";
        $return = $this->get($url,null,array(&$this,'write'));
        echo "\n";
        return $return;
    }

    function write($status,$data){
        fwrite($this->sock,$data);
        $datalen = strlen($data);
        $this->download += $datalen;
        if(!$this->total && $this->responseHeader['content-length']){
            $this->total = $this->responseHeader['content-length'];
        }

        $time = $this->microtime_float();
        if($this->last_time){
            $this->speed = $datalen*0.01 / ($time - $this->last_time);
        }
        $this->last_time = $time;

        if($this->total){
            $this->progress();
        }
        return true;
    }

    function img(){
        if($this->_img==4)$this->_img=0;
        $map = array('-','\\','|','/');
        return $map[$this->_img++];
    }

    function microtime_float() {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }

    function progress(){
        $i = intval(100*$this->download/$this->total);
        if(PHP_SAPI=='cli'){
            $width = base_shell_loader::get_width()-5;
            $padding = 34;
            $download_width = round($i*($width-$padding)/100);
            echo str_repeat(chr(8),$width);
            echo str_pad($i,2,' ', STR_PAD_LEFT), '% '
                ,'['
                ,str_repeat('=',$download_width), '>'
                ,str_repeat(' ',$width - $padding - $download_width)
                ,'] '
                ,str_pad(number_format($this->download,0,'.',','),15)
                ,str_pad(number_format($this->speed/1024,2,'.',',').'K/s',10)
                ,$this->img();
        }else{
            if($i>$this->last+1){
                for($j=$this->last+2;$j<=$i;$j+=2){
                    echo ($j % 10==0)?($j):'.';
                }
                $this->last = $i;
            }
        }
    }

}
