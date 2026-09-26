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
 * 订单明细
 *
 * @access public
 * @author xueding<xueding@shopex.cn>
 * @date  2021-05-22
 */
class erpapi_shop_response_plugins_order_settle extends erpapi_shop_response_plugins_order_abstract
{
	public function convert(erpapi_shop_response_abstract $platform)
	{
		$settleObj = app::get('ome')->model('order_settle');
		//获取订单明细根据obj层插入数据
		$order_obj = $platform->_ordersdf['order_objects'];
		$settle    = array();
		if (empty($order_obj)) {
			return false;
		}
		foreach ($order_obj as $key => $value) {
			$result = kernel::single('erpapi_router_request')->set('shop',
				$platform->__channelObj->channel['shop_id'])->product_itemsOrderGet([$value['oid']],$platform->_ordersdf['order_bn']);
			
			if ($result['rsp'] != 'succ' || empty($result['data'])) {
				continue;
			}
			
			$data     = reset($result['data']);
			// extend 会 serialize 入库；平台返回里若含 emoji(🍀等) 且表/连接非 utf8mb4 会报 Incorrect string value
			$data     = $this->_strip4ByteChars($data);
			$settle[] = array(
				'type'                => 'commission',
				'material_bn'         => $value['bn'],
				'oid'                 => $value['oid'],
				'real_comission'      => $data['real_comission'],
				'estimated_comission' => $data['estimated_comission'],
				'commission_rate'     => $data['commission_rate'],
				'extend'              => $data,
			);
		}
		// 更新的时候
		if ($platform->_tgOrder) {
			$tgSettle     = $settleObj->getList('order_id,oid,material_bn',
				array('order_id' => $platform->_tgOrder['order_id']));
			$tgSettleData = array_column($tgSettle, null, 'oid');
			foreach ($settle as $key => $value) {
				if (isset($tgSettleData[$value['oid']])) {
					$oldData = $tgSettleData[$value['oid']];
					if ($oldData['order_id'] == $platform->_tgOrder['order_id'] && $oldData['oid'] == $value['oid'] && $oldData['material_bn'] == $value['material_bn']) {
						unset($settle[$key]);
						continue;
					}
				}
			}
		}
		
		return $settle;
	}
	
	/**
	 * 订单完成后处理
	 *
	 * @return void
	 * @author
	 **/
	public function postCreate($order_id, $settle)
	{
		$settleObj = app::get('ome')->model('order_settle');
		foreach ($settle as $key => $value) {
			$settle[$key]['order_id'] = $order_id;
			if (isset($settle[$key]['extend'])) {
				$settle[$key]['extend'] = $this->_strip4ByteChars($settle[$key]['extend']);
			}
		}
		
		$sql = ome_func::get_insert_sql($settleObj, $settle);
		
		kernel::database()->exec($sql);
	}

	/**
	 * 去掉 emoji 等 4 字节 UTF-8（U+10000 以上）
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	private function _strip4ByteChars($value)
	{
		if (is_array($value)) {
			foreach ($value as $k => $v) {
				$value[$k] = $this->_strip4ByteChars($v);
			}
			return $value;
		}
		if (is_object($value)) {
			$arr = json_decode(json_encode($value), true);
			return is_array($arr) ? $this->_strip4ByteChars($arr) : $value;
		}
		if (!is_string($value) || $value === '') {
			return $value;
		}
		return preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $value);
	}
}