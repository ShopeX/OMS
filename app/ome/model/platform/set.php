<?php

class ome_mdl_platform_set extends dbeav_model{

    /**
     * 根据平台获取场景映射
     * 
     * @param string $shop_type 平台编码，为空时返回所有场景
     * @return array
     */
    public static function get_scene_map($shop_type = '')
    {
        // 默认所有平台都有的场景
        $scenes = array(
            'delivery' => '发货时效'
        );
        
        // 如果指定了平台，根据平台返回可用场景
        if ($shop_type) {
            if ($shop_type == 'taobao') {
                // 淘宝平台额外增加天猫优品佣金场景
                $scenes['tmyp_commission'] = '天猫优品佣金';
                // 淘宝平台额外增加天猫喵速达佣金场景
                $scenes['tmsd_commission'] = '天猫喵速达佣金';
            }
            return $scenes;
        }
        
        // 未指定平台时返回所有场景
        $scenes['tmyp_commission'] = '天猫优品佣金';
        $scenes['tmsd_commission'] = '天猫喵速达佣金';
        return $scenes;
    }

    /**
     * 平台编码修饰器
     * 
     * @param string $shop_type 平台编码
     * @param array $list 数据列表
     * @param array $row 当前行数据
     * @return string
     */
    public function modifier_shop_type($shop_type, $list, $row)
    {
        $shopTypeList = ome_shop_type::get_shop_type();
        $shopTypeList['taobao'] = '淘宝/天猫';
        unset($shopTypeList['tmall']);
        return $shopTypeList[$shop_type] ? : $shop_type;
    }

    /**
     * 场景修饰器
     * 
     * @param string $scene 场景
     * @param array $list 数据列表
     * @param array $row 当前行数据
     * @return string
     */
    public function modifier_scene($scene, $list, $row)
    {
        $sceneMap = self::get_scene_map();
        return isset($sceneMap[$scene]) ? $sceneMap[$scene] : $scene;
    }

}

