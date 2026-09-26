<?php

class invoice_auth_piaoyitong implements invoice_auth_iconfig
{
    /**
     * 加载授权配置
     *
     * @return void
     * @author
     */
    public function getAuthConfigs()
    {
        $params = array(
            'api_url' => array(
                'label'    => 'API地址',
                'name'     => 'extend_data[api_url]',
                'required' => true,
                'size'     => 50,
            ),
            'businessBillType' => array(
                'label'    => 'businessBillType',
                'name'     => 'extend_data[businessBillType]',
                'required' => true,
                'size'     => 50,
            ),
            'customNo' => array(
                'label'    => 'customNo',
                'name'     => 'extend_data[customNo]',
                'required' => true,
                'size'     => 50,
            ),
            'version' => array(
                'label'    => '版本号',
                'name'     => 'extend_data[version]',
                'required' => true,
                'size'     => 50,
                'value'    => '4.1',
            ),
        );

        return $params;
    }
}
