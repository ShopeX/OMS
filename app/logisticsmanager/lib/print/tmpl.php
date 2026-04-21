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

class logisticsmanager_print_tmpl {
    
    /**
     * 上传快递单模板
     * 
     * @param object $file 待上传的快递单模板文件
     * @return string 返回上传的消息
     */
    function upload_tmpl($file){
        
        $print_tmplObj = app::get('logisticsmanager')->model('express_template');
        
        $extname = strtolower($this->extName($file['name']));
        $tar = kernel::single('ome_utility_tar');
        if($extname=='.dtp'){
            if($tar->openTAR($file['tmp_name'],'') && $tar->containsFile('info')){
                if(!($info = unserialize($tar->getContents($tar->getFile('info'))))){
                    $error_msg = "无法读取结构信息,模板包可能已损坏";
                    return $error_msg;
                }
                if ($tar->containsFile('background.jpg')){ //包含背景图
                    $rand = md5(time());
                    if(function_exists('sys_get_temp_dir')){
                        $tmpPath = sys_get_temp_dir().'/'.$rand.'.jpg';
                    }else{
                        $mark = kernel::single('ome_utility_tool');
                        $tmpPath = $mark->get_temp_dir().'/'.$rand.'.jpg';
                    }
                    
                    file_put_contents($tmpPath,$tar->getContents($tar->getFile('background.jpg')));
                }
                if (file_exists($tmpPath)){//保存图片
                    $ss = kernel::single('base_storager');
                    $Path = substr($tmpPath,strrpos($tmpPath,'dly_bg_'));
                    $file['name'] = $Path;
                    $file['type'] = 'image/jpeg';
                    $file['size'] = filesize($tmpPath);
                    $file['tmp_name'] = $tmpPath;
                    $id = $ss->save_upload($file,"file","",$msg);//返回file_id;
                }
                unlink($tmpPath);
                
                $tmpl_info['file_id'] = $id;

                $re = $print_tmplObj->save($info);//保存快递单模板 
                
                if ($re){
                    $error_msg = "success";
                    return $error_msg;
                }
                $error_msg = "上传失败";
                return $error_msg;
            }else{
                $error_msg = "无法解压缩,模板包可能已损坏";
                return $error_msg;
            }
        }else{
            $error_msg = "必须是shopex快递单模板包(.dtp)";
            return $error_msg;
        }
        $error_msg = "success";
        return $error_msg;
    }
    /*
     * 提取扩展名
     */
    function extName($file){
        return substr($file,strrpos($file,'.'));
    }

    /*
     * 格式化新版发货单的总计数据
     */
    function formatDeliveryPrintTotal($template_select_data,$type ='edit'){
        $template_select = '';
        if($type=='edit'){
            $template_select_arr = !empty($template_select_data) ? unserialize($template_select_data) : array();
            foreach($template_select_arr as $key=>$value){
                if(empty($template_select)){
                    $template_select = $key.':'.$value;
                }else{
                    $template_select .= ','.$key.':'.$value;
                }
            }
        }else{
            $template_select_temp = !empty($template_select_data) ? explode(',',$template_select_data) : array();
            foreach($template_select_temp as $value){
                $temp = explode(':',$value);
                $template_select[$temp[0]] = $temp[1];
            }
        }
        return $template_select;
    }

    #保存模板(快递、发货、备货）公共部分
    /**
     * 保存
     * @param mixed $params 参数
     * @return mixed 返回操作结果
     */
    public function save($params) {
        $data = array(
            'out_template_id' => $params['out_template_id'] ? $params['out_template_id'] : 0,
            'template_name'   => $params['template_name'],
            'template_type'   => $params['template_type'],
            'status'          => $params['status'] ? $params['status'] : 'true',
            'template_width'  => $params['template_width'],
            'template_height' => $params['template_height'],
            'file_id'         => $params['file_id'] ? $params['file_id'] : 0,
            'is_logo'         => $params['is_logo'] ? $params['is_logo'] : 'true',
            'template_select' => $params['template_select'] ? serialize($params['template_select']) : null,
            'template_data'   => $params['template_data'],
            'is_default'      => isset($params['is_default']) ? $params['is_default'] : 'false',
            'page_type'       => isset($params['page_type']) ? $params['page_type'] : '1',
            'aloneBtn'        => isset($params['aloneBtn']) ?  $params['aloneBtn'] : 'false',
            'btnName'         => $params['btnName'],
            'source'          => $params['source'] ? $params['source'] : 'local',
            'cp_code'         => (string)$params['cp_code'],
            'control_type'    => $params['control_type'] ? $params['control_type'] : 'shopexplugin',
        );

        if ($data['template_name'] == ''){
            switch ($data['template_type']) {
                case 'delivery':
                    $title = '请输入发货单名称';
                    break;
                case 'stock':
                    $title = '请输入备货单名称';
                    break;
                default :
                    $title = '请输入快递单名称';
                    break;
            }
            return array('rs'=>'fail', 'msg'=>$title);
        }
        if (!in_array($data['template_type'],array('normal', 'electron', 'delivery', 'stock','cainiao', 'cainiao_standard', 'cainiao_user', 'pdd_standard', 'pdd_user','jd_standard','jd_user', 'douyin_standard', 'douyin_user','kuaishou_standard','kuaishou_user','wphvip_standard','wphvip_user','sf','xhs_standard','xhs_user','aikucun_standard','aikucun_user','wxshipin_standard','wxshipin_user','dewu_ppzf','dewu_ppzf_zy','meituan4bulkpurchasing_user','youzan_standard'))) {
            return array('rs'=>'fail', 'msg'=>'面单类型不符合规则！');
        }
        if(!$data['template_width'] || !$data['template_height']){
            if($data['file_id']>0){
                $bgUrl = $this->getImgUrl($data['file_id']);
                list($width, $height) = getimagesize($bgUrl);
                if($width && $height){
                    $data['template_width'] = intval($width*25.4/96);
                    $data['template_height'] = intval($height*25.4/96);
                }
            } else {
                // false就给dbschema的默认值
                !$data['template_width'] && $data['template_width'] = 100;
                !$data['template_height'] && $data['template_height'] = 100;
            }
        }
        $templateObj = app::get('logisticsmanager')->model('express_template');
        if ($params['template_id']){
            $filter = array('template_id' => $params['template_id']);
            $re = $templateObj->update($data,$filter );
            $data['template_id'] = $params['template_id'];
        }else {
            $re = $templateObj->insert($data);
        }
        return $re ? array('rs'=>"succ", 'data'=>$data) : array('rs'=>'fail', 'msg'=>'保存失败');
    }

    /**
     * 获取ImgUrl
     * @param mixed $file file
     * @return mixed 返回结果
     */
    public function getImgUrl($file){
        $ss = kernel::single('base_storager');
        $url = $ss->getUrl($file,"file");

        return $url;
    }
}