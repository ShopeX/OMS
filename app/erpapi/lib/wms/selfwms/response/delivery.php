<?php
/**
 * WMS 发货单
 *
 * @category
 * @package
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class erpapi_wms_selfwms_response_delivery  extends erpapi_wms_response_delivery
{
    public function status_update($params){
        $this->__apilog['title']       = $this->__channelObj->wms['channel_name'].'发货单更新('.$params['status'].')';
        $this->__apilog['original_bn'] = $params['delivery_bn'];

        // 自建仓 trigger 传 logistics=corp_id（数字）；统一转成 dly_corp.type，与奇门回写对齐，供 OME _convertConsignSdf 按 type 解析
        $logisticsCode = '';
        $corpId = 0;
        if (!empty($params['logistics'])) {
            $corpId = is_numeric($params['logistics']) ? (int) $params['logistics'] : 0;
        } elseif (!empty($params['logi_id']) && is_numeric($params['logi_id'])) {
            $corpId = (int) $params['logi_id'];
        }
        if ($corpId > 0) {
            $corp = app::get('ome')->model('dly_corp')->db_dump(array('corp_id' => $corpId), 'type');
            if (!empty($corp['type'])) {
                $logisticsCode = (string) $corp['type'];
                $params['logi_id'] = $logisticsCode;
                $params['logistics'] = $logisticsCode;
            }
        } elseif (!empty($params['logistics']) && empty($params['logi_id'])) {
            $params['logi_id'] = $params['logistics'];
            $logisticsCode = (string) $params['logistics'];
        } elseif (!empty($params['logi_id'])) {
            $logisticsCode = (string) $params['logi_id'];
        }

        $logi_no = '';
        if (!empty($params['logi_no'])) {
            $batch = preg_replace('/\s/', '', trim($params['logi_no']));
            $batch = array_filter(explode(';', $batch));
            $logi_no = (string) array_pop($batch);
            $params['logi_no'] = $logi_no;
        }

        if (!empty($params['bill_logi_weight']) && is_array($params['bill_logi_weight'])) {
            // consignDelivery 已直接组装
        } elseif (!empty($params['other_list_0'])) {
            $logiList = is_array($params['other_list_0'])
                ? $params['other_list_0']
                : json_decode($params['other_list_0'], true);
            $logiWeight = array();
            if ($logiList) {
                foreach ($logiList as $val) {
                    if (empty($val['logi_no'])) {
                        continue;
                    }
                    if ($logi_no !== '' && $val['logi_no'] == $logi_no) {
                        continue;
                    }
                    $logiWeight[$val['logi_no']] = isset($val['weight']) ? $val['weight'] : 0;
                }
            }
            if ($logiWeight) {
                $params['bill_logi_weight'] = $logiWeight;
            }
        }

        if (!empty($params['packages']) && !is_array($params['packages'])) {
            $params['packages'] = json_decode($params['packages'], true);
        }

        // 嵌套 packages → flat package（与奇门 _dealDeliveryPackage 对齐；无 package 时补齐）
        if (empty($params['package']) && !empty($params['packages']) && is_array($params['packages'])) {
            $flat = array();
            $now = time();
            foreach ($params['packages'] as $pkg) {
                $pkgLogiNo = !empty($pkg['logi_no']) ? $pkg['logi_no'] : $logi_no;
                $pkgBn = !empty($pkg['package_bn']) ? $pkg['package_bn'] : $params['delivery_bn'];
                foreach ((array) (isset($pkg['items']) ? $pkg['items'] : array()) as $item) {
                    if (empty($item['bn'])) {
                        continue;
                    }
                    $flat[] = array(
                        'package_bn'    => $pkgBn,
                        'logi_bn'       => !empty($pkg['logi_bn']) ? $pkg['logi_bn'] : $logisticsCode,
                        'logi_no'       => $pkgLogiNo,
                        'product_id'    => isset($item['product_id']) ? (int) $item['product_id'] : 0,
                        'bn'            => $item['bn'],
                        'outer_sku'     => '',
                        'status'        => 'delivery',
                        'number'        => isset($item['number']) ? (int) $item['number'] : 0,
                        'create_time'   => $now,
                        'delivery_time' => !empty($pkg['delivery_time']) ? $pkg['delivery_time'] : $now,
                    );
                }
            }
            if ($flat) {
                $params['package'] = $flat;
            }
        }

        return $params;
    }
}
