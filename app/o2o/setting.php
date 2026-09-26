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

$setting = array(
'o2o.autostore.type'=>array('type'=>SET_T_ENUM,'default'=>'area','options'=>array('area'=>'按区域覆盖','lbs'=>'按LBS定位'),'desc'=>'门店优选模式'),
'o2o.baidumap.show'=>array('type'=>SET_T_BOOL,'default'=>'false','desc'=>'是否开启展示百度地图线路功能'),
'o2o.baidumap.ak'=>array('type'=>SET_T_STR,'default'=>'','desc'=>'百度地图密钥（ak）'),
'o2o.baidumap.sk'=>array('type'=>SET_T_STR,'default'=>'','desc'=>'百度地图sn校验方式（sk）'),
'o2o.delivery.confirm.code'=>array('type'=>SET_T_BOOL,'default'=>'false','desc'=>'是否开启销单校验码'),
'o2o.delivery.dly_overtime'=>array('type'=>SET_T_INT,'default'=>'0','desc'=>'发货超时时间设置(分钟)'),
'o2o.ctrl.supply.relation'=>array('type'=>SET_T_BOOL,'default'=>'false','desc'=>'是否管控门店供货关系'),
'o2o.order.router_num'=>array('type'=>SET_T_INT,'default'=>'3','desc'=>'自动重路由设置(次)'),
);
?>