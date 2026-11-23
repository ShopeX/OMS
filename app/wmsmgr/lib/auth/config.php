<?php
/**
 * CONFIG
 *
 * @category 
 * @package 
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class wmsmgr_auth_config 
{
    private $__config = array(
            'selfwms' => array(
                'label' => '系统自有仓储',
                'desc' => '使用系统自带自有仓储发货',
            ),
            'matrixwms' => array(
                'label' => '商派矩阵',
                'desc' => '通过WEBSERVICE和第三方仓储进行对接,如(科捷,酷武,伊藤忠)',
                'platform' => array(
                    'other' => array(
                        'label' => '其他',
                        'desc' => '未指定具体仓储，需在绑定时指定',
                        'params' => array(),
                    ),
                    'bms' => array(
                        'label'=>'菜鸟BMS',
                        'desc'=>'对接菜鸟BMS系统，需先创建店铺绑定',
                        'params' => array(),
                    ),
                    'bim' => array(
                        'label'=>'菜鸟保税',
                        'desc'=>'对接菜鸟保税&GFC，需先创建店铺绑定',
                        'params' => array(),
                    ),
                    'yph' => array(
                        'label'=>'京东一盘货',
                        'desc'=>'对接京东一盘货，需先创建店铺绑定',
                        'params' => array(),
                    ),
                    // 'yjdf' => array(
                    //     'label'=>'京东一件代发',
                    //     'desc'=>'对接京东一键代发，先联系商派获取node_id',
                    //     'params' => array(
                    //         'node_id' => 'node_id'
                    //     ),
                    // ),
                ),
            ),
            'openapiwms' => array(
                'label' => '本地API直联',
                'platform' => array(),
                'desc' => '可通过API接口直接对接第三方仓储',
            ),
            'ilcwms' => array(
                'label' => '通过FTP直联',
                'desc' => '通过配置,使用FTP方式和第三方仓储进行系统对接(如伊藤忠FTP)'
            ),
            'mixturewms'=>array(
                'label'=>'FTP云对接混合模式',    
                'desc' => '通过配置,使用FTP方式和WEBSERVICE和第三方仓储对接,如(伊藤忠FTP)'
            ),

        );
    
    public function __construct()
    {

        $openapi_platform = array (
            'cnss' =>
                array (
                    'label' => '镜宴',
                    'desc' => 'desc',
                ),
            'publicwms' =>
                array (
                    'label' => '标准',
                    'desc' => 'desc',
                    'params' =>
                        array (
                        ),
                ),
            'sku360' =>
                array (
                    'label' => 'sku360',
                    'desc' => 'desc',
                    'params' =>
                        array (
                            'appkey' => 'appkey',
                            'owner' => 'owner',
                        ),
                ),
        );

        $this->__config['openapiwms']['platform'] = $openapi_platform;

    }

    public function getConfig()
    {
        return $this->__config;
    }

    public function getAdapterList()
    {
        $adapter = array();

        foreach ($this->__config as $key => $value) {
            $adapter[] = array('value'=>$key, 'label'=>$value['label'], 'desc'=>$value['desc']);
        }

        return $adapter;
    }

    public function getPlatformList($adapter)
    {
        $platform = array();

        foreach ($this->__config[$adapter]['platform'] as $key => $value) {
            $platform[] = array('value'=>$key,  'label'=>$value['label']);
        }

        return $platform;
    }

    public function getPlatformParam($platform)
    {
        return $this->__config['openapiwms']['platform'][$platform]['params'];
    }
}
