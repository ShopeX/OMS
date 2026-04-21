<?php
/**
 * @author ykm 2026-03
 * @describe 爱库存模板数据获取
 */
class erpapi_logistics_matrix_aikucun_request_template extends erpapi_logistics_request_template
{

    public function syncUserTpl()
    {
        $this->title = '获取爱库存用户模板';
        $params      = ['version' => '2.0'];

        $rs = $this->requestCall(STORE_WAYBILL_USER_TEMPLATE, $params);

        if ($rs['rsp'] == 'succ' && $rs['data']) {
            $resData      = json_decode($rs['data'], 1);
            $rs['data']   = [];
            $templateList = $this->extractTemplateList($resData);
            foreach ($templateList as $k => $v) {
                $outTemplateId = isset($v['customTemplateCode']) ? $v['customTemplateCode'] : '';
                if (!$outTemplateId) {
                    continue;
                }
                $tplSelect = isset($v['customParams']) && is_array($v['customParams']) ? array_filter($v['customParams']) : [];
                $width = '';
                $height = '';
                $templateCode = isset($v['parentTemplateCode']) ? $v['parentTemplateCode'] : '';
                if ($templateCode && preg_match('/_(\d+)_(\d+)(?:_|$)/', $templateCode, $match)) {
                    $width = $match[1];
                    $height = $match[2];
                }
                $rs['data'][] = array(
                    'tpl_index'       => 'user' . '-' . $outTemplateId,
                    'cp_code'         => $v['cpCode'],
                    'out_template_id' => $outTemplateId,
                    'template_name'   => $v['customTemplateName'] . '(爱库存)',
                    'template_type'   => 'aikucun_user',
                    'template_select' => $tplSelect,
                    'template_data'   => json_encode($v),
                    'template_width'  => $width,
                    'template_height' => $height,
                );
            }
        } else {
            $rs['data'] = [];
        }
        return $rs;
    }

    public function syncStandardTpl()
    {
        $this->title = '获取爱库存标准模板';
        $params      = ['version' => '2.0'];

        $rs = $this->requestCall(STORE_WAYBILL_STANDARD_TEMPLATE, $params);

        if ($rs['rsp'] == 'succ' && $rs['data']) {
            $resData      = json_decode($rs['data'], 1);
            $rs['data']   = [];
            $templateList = $this->extractTemplateList($resData);
            foreach ($templateList as $k => $v) {
                $width = '';
                $height = '';
                $templateCode = isset($v['templateCode']) ? $v['templateCode'] : '';
                if ($templateCode && preg_match('/_(\d+)_(\d+)(?:_|$)/', $templateCode, $match)) {
                    $width = $match[1];
                    $height = $match[2];
                }
                $rs['data'][] = array(
                    'tpl_index'       => 'standard' . '-' . $v['templateId'],
                    'cp_code'         => $v['cpCode'],
                    'out_template_id' => $v['templateId'],
                    'template_name'   => $v['templateName'] . '(爱库存)',
                    'template_type'   => 'aikucun_standard',
                    'template_data'   => json_encode($v),
                    'template_width'  => $width,
                    'template_height' => $height,
                );
            }
        } else {
            $rs['data'] = [];
        }
        return $rs;
    }

    private function extractTemplateList($resData)
    {
        $result = [];
        $data = isset($resData['data']) ? $resData['data'] : [];

        // 仅按爱库存当前返回结构解析：data[] -> logisticsCode + templateList[]
        if (is_array($data)) {
            foreach ($data as $group) {
                $cpCode = isset($group['logisticsCode']) ? $group['logisticsCode'] : '';
                if (empty($group['templateList']) || !is_array($group['templateList'])) {
                    continue;
                }
                foreach ($group['templateList'] as $tpl) {
                    $tpl['cpCode'] = $cpCode;
                    $result[] = $tpl;
                }
            }
        }

        return $result;
    }
}
