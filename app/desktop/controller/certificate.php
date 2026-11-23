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
 
class desktop_ctl_certificate extends desktop_controller{

    function index(){

        $this->Certi = base_certificate::get('certificate_id');
        $this->Token = base_certificate::get('token');
        if(empty($this->Certi) ||empty($this->Token)){
            $this->pagedata['license']=false;
        }else{
            $this->pagedata['license']=true;
        }
        $this->pagedata['certi_id']=$this->Certi;
        $this->pagedata['debug']=false;

        $this->page('certificate.html');
    }

    function upLicense(){
    	if ( $_FILES ){
    		if ( $_FILES['license']['name'] ){
	    		$fileName = explode( '.', $_FILES['license']['name'] );
	    		if ( 'CER' != $fileName['1'] ){
					$this->begin();
					$this->end(false, app::get('desktop')->_("证书格式不对"));
	    		}
	    		else {
			        $content = file_get_contents($_FILES['license']['tmp_name']);
			        list($certificate_id,$token) = explode('|||',$content);
					/** 验证证书是否合法 **/
					$sys_params = base_setup_config::deploy_info();
					$code = md5(microtime());
					base_kvstore::instance('ecos')->store('net.login_handshake',$code);
					$app_exclusion = app::get('base')->getConf('system.main_app');
					/** 得到框架的总版本号 **/
					$obj_apps = app::get('base')->model('apps');
					$tmp = $obj_apps->getList('*',array('app_id'=>'base'));
					$app_xml = $tmp[0];
					$app_xml['version'] = $app_xml['local_ver'];
					$conf = base_setup_config::deploy_info();
					$data = array(
						'certi_app'=>'open.login',
						'certificate_id'=>$certificate_id,
						'url' => kernel::base_url(1),
						'version'=>'0.14',
						'ver_detail'=> $app_xml['version'],            
						'result' => $code,
						'format'=>'json',
					);
					ksort($data);
					foreach($data as $key => $value){
						$str.=$value;
					}
					$data['certi_ac'] = md5($str.$token);
					$http = kernel::single('base_httpclient');
					$http->timeout = 6;
					$result = $http->post(
						LICENSE_CENTER,
						$data);
					
					$result = json_decode($result,1);
					
					if ($result['res'] != 'succ' || !isset($result['info']['valid']) || $result['info']['valid'] !== false){
						$this->begin();
						$this->end(false, app::get('desktop')->_("上传证书无效"));
					}
			        $result = base_certificate::set_certificate(array('certificate_id'=>$certificate_id,'token'=>$token));
			        if(!$result){
						$this->begin();
						$this->end(false, app::get('desktop')->_("证书重置失败,请先上传文件"));
			        }else{						
						$this->begin();
						$this->end(true, app::get('desktop')->_("证书上传成功"));
			        }
	    		}
    		}
    		else {
				$this->begin();
				$this->end(false, app::get('desktop')->_("请选择要上传的文件"));
    		}
    	}else{
			
		}
    }
    function download(){
        header("Content-type:application/octet-stream;charset=utf-8");
        header("Content-Type: application/force-download");
        header("Cache-control: private");
        $this->fileName = 'CERTIFICATE.CER';
        header("Content-Disposition:filename=".$this->fileName);

        $this->Certi = base_certificate::get('certificate_id');
        $this->Token = base_certificate::get('token');
        echo $this->Certi;
        echo '|||';
        echo $this->Token;
    }
    function delete(){
        $this->begin();
        base_certificate::del_certificate();
        //base_certificate::register();
        $this->end();
    }

}

