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
 
class base_vcode_gd{
    
    function length($m = 8){
        $this->codeDir= DATA_DIR.'/cache';
        if ($handle = opendir($codeDir)) {
            while (false !== ($file = readdir($handle))) {
                if (substr($file,-4)=='.png') {
                    $lib[] = substr($file,0,-4);
                }
            }
            closedir($handle);
        }
        $n = count($lib)-1;
        $str = '';
        for($i=0;$i<$m;$i++){
            $str.=$c = $lib[rand(0,$n)];
            $ret[] = $codeDir.'/'.$c.'.png';
        }
        $this->ret = &$ret;
                
        $this->code = $str;
    }
    
    function get_code(){
        return $this->code;
    }

    function output(){
        $arr = $this->ret;
        $bg = DATA_DIR.'/cache/vcodebg.png';
        $image = imagecreatefrompng($bg); 
        list($w, $baseH) = getimagesize($bg);        
        header('Content-type: image/png');
        $x = 1;
        foreach($arr as $i=>$filename){
            list($w, $h) = getimagesize($filename);
            $source = imagecreatefrompng($filename);
            $t_id = imagecolortransparent($source);
            $rotate = imagerotate($source, rand(-20,20),$t_id);
            $w2 = $w*$baseH/$h;
            imagecopyresized($image, $rotate, $x, 0, 0, 0, $w2, $baseH, $w, $h);
            imagedestroy($source);
            imagedestroy($rotate);
            $x+=$w2;
        }
        $x+=1;
        $dst = imagecreatetruecolor($x, $baseH);
        imagecopyresampled($dst, $image, 0, 0, 0, 0, $x, $baseH, $x, $baseH);
        imagepng($dst);
        imagedestroy($image);
        imagedestroy($dst);
        exit();
    }
}

