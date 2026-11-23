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

class base_application_imgbundle_factory 
{
    private $app_id;
    private $directory;
    private $output;
    private $files = array();
    private $match;
    private $spriteinfo = array();
    private $position = 0;

    function __construct() 
    {
        $this->reset();
    }//End Function
    
    public function set_app($app_id) 
    {
        $this->app_id = $app_id;
        if($this->_parse_dirctory()){
            return $this;
        }else{
            return false;
        }
    }//End Function

    public function set_directory($directory) 
    {
        $this->directory = $directory;
        if($this->_parse_dirctory()){
            return $this;
        }else{
            return false;
        }
    }//End Function

    public function set_output($output) 
    {
        $this->output = $output;
        return $this;
    }//End Function

    public function set_file($file) 
    {
        $this->files[] = $file;
        return $this;
    }//End Function

    public function set_files(array $files) 
    {
        $this->files = $files;
        return $this;
    }//End Function

    public function reset() 
    {
        $this->app_id = null;
        $this->directory = 'bundle';
        $this->output = '';
        $this->files = array();
        $this->match = '/^([a-z0-9\-_]+)\.(jpg|jpeg|jpe|gif)$/i';
        $this->spriteinfo = array();
        $this->position = 0;
        return $this;
    }//End Function

    private function _parse_dirctory() 
    {
        if(!$this->app_id)  return false;
        $this->files = array();
        $dir = realpath(app::get($this->app_id)->res_dir . '/' . $this->directory);
        $handle = opendir($dir);
        if($handle){
            while(($file = readdir($handle)) !== false){
                if(in_array($file, array('.', '..')))   continue;
                if(preg_match($this->match, $file, $matches)){
                    $this->files[] = $dir . '/' . $file;
                }
            }
        }
        return true;
    }//End Function

    public function create() 
    {
        if(!is_callable('imagecreatefromjpeg') || !is_callable('imagecreatefrompng') || !is_callable('imagecreatefromgif')){
            return array();
        }//todo:GD库需要支持jpeg,png,gif格式
        if(is_callable('imagecreatetruecolor')){
            try{
                $image = imagecreatetruecolor(100, 200);
                imagedestroy($image);
                $flag = true;
            }catch(Exception $e){
                $flag = false;
            }
        }else{
            $flag = false;
        }//todo:
        if($flag && defined('PUBLIC_DIR')){
            $output_dir = PUBLIC_DIR . '/imgbundle';
            if(!is_dir($output_dir)){
                mkdir($output_dir, 0755, true);
            }
            $filename = str_replace('\\', '/', realpath($output_dir)) . '/' . $this->output;
            foreach($this->files AS $file){
                $file = str_replace('\\', '/', $file);
                $orginfo = getimagesize($file);
                if($image == null){
                    $image = $this->_create_image($file);
                }else{
                    $image = $this->_create($filename, $file);
                }
                if($image && $this->_write($image, $filename)){
                    $imageinfo = getimagesize($filename);
                    $this->spriteinfo['info'][substr($file, strlen(realpath(app::get($this->app_id)->res_dir).'/'))] = array($this->position * -1, $orginfo[0], $imageinfo[1]-$this->position);
                    $this->position = $imageinfo[1];
                }
            }
            $this->spriteinfo['mtime'] = time();
            $this->spriteinfo['bundleimg'] = substr($filename, strlen(realpath(ROOT_DIR))+1);
            return $this->spriteinfo;
        }else{
            return array();
        }
    }//End Function

    private function _create($file1, $file2) 
    {
        $image1 = $this->_create_image($file1);
        if(!$image1)    return false;
        $image2 = $this->_create_image($file2);
        if(!$image2)    return false;
        
        $imageWidth1 = imagesx($image1);
        $imageWidth2 = imagesx($image2);

        $imageHeight1 = imagesy($image1);
        $imageHeight2 = imagesy($image2);
        
        $width = ($imageWidth1 > $imageWidth2 ? $imageWidth1 : $imageWidth2);
        $height = $imageHeight1 + $imageHeight2;
        
        $image = imagecreatetruecolor($width, $height);   
        $bgcolor = ImageColorAllocate($image,0,0,0);   
        $bgcolor = ImageColorTransparent($image,$bgcolor) ;

        if(!imagecopymerge($image, $image1, 0, 0, 0, 0, $imageWidth1, $imageHeight1, 100)) return false;
        if(!imagecopymerge($image, $image2, 0, $imageHeight1, 0, 0, $imageWidth2, $imageHeight2, 100)) return false;
        
        imagedestroy($image1);
        imagedestroy($image2);
        
        return $image;
    }//End Function
    
    private function _create_image($filename) 
    {
        if (!is_readable($filename)) return false;
        $info = getimagesize($filename);
        switch($info[2])
        {
            case 2:
                try{
                    return imagecreatefromjpeg($filename);
                }catch(Exception $e){
                    return false;
                }
                
            case 3:
                try{
                    return imagecreatefrompng($filename);
                }catch(Exception $e){
                    return false;
                }

            case 1:
                try{
                    return imagecreatefromgif($filename);
                }catch(Exception $e){
                    return false;
                }
            
            default:
                return false;
        }
        
        return false;
   }//End Function

    function _write($image, $filename)
    {
        $function = 'image' . substr($this->output, strrpos($this->output, '.')+1);
        
        if (file_exists($filename) && !is_writable($filename)){
            return false;
        }
        if(function_exists($function)){
            $res = $function($image, $filename);
        }else{
            $res = false;
        }
        imagedestroy($image);
        return $res;
    }

}//End Class