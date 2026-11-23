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
 

class desktop_ctl_code extends base_controller
{
    public function __construct($app)
    {
        parent::__construct($app);
        header("cache-control: no-store, no-cache, must-revalidate");
    }
    
    //激活码校验
	public function codecheck()
	{
		//if ($_POST['auth_code'] && preg_match("/^\d{19}$/", substr($_POST['auth_code'], 1)))
		if ($_POST['auth_code'])
        {
			$code = kernel::single('desktop_cert_certcheck');
			$result = $code->check_code($_POST['auth_code']);
            if ($result['res'] == 'succ' && $result)
            {
				$activation_arr = $_POST['auth_code'];
				app::get('desktop')->setConf('activation_code', $activation_arr);

				$objArr = kernel::servicelist("desktop.cert.succ");
				foreach ($objArr as $obj)
				{
					if(method_exists($obj , 'notify')){
						$obj->notify($result);
					}
				}

				header('Location:' .kernel::router()->app->base_url(1));
				exit;
            }
            else
            {
                switch ($result['msg'])
                {
					case 'key_false_ac':
                        $auth_error_msg = '激活码类型不对!';break;
                    case 'key_false_times':
                        $auth_error_msg = '失败：您已经连续6次提交失败，为了您的网店安全，请3小时后再次尝试|';break;
                    case 'key_false_key':
                        $auth_error_msg = '无效的激活码，请您重新输入激活码以便正常使用。';break;
                    case 'key_false_actived':
                        $auth_error_msg = '您的激活码已经失效，请您重新输入激活码以便正常使用。';break;
                    case 'key_false_oem':
                        $auth_error_msg = '您的网店License与输入的激活码类型不一，请联系激活码销售商!';break;
                    case 'key_false_type_1':
                        $auth_error_msg = '您的网店License与输入的激活码类型不一，请联系激活码销售商!';break;
                    case 'key_false_type_2':
                        $auth_error_msg = '您的网店License与输入的激活码类型不一，请联系激活码销售商!';break;
					case 'certificate_id_is_false':
                        $auth_error_msg = '您的网店License与输入的激活码类型不一，请联系激活码销售商!';break;
                }

                die($this->error_view($auth_error_msg));
            }

            header("Location: index.php");
            exit();
        }
	}
	
	function error_view($auth_error_msg = '')
    {
		$render = app::get('desktop')->render();
		$render->pagedata['res_url'] = app::get('desktop')->res_url;
		$render->pagedata['auth_error_msg'] = $auth_error_msg;
		echo $render->display('active_code.html');
		exit;
	}
	
}
