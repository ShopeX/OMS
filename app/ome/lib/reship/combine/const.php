<?php
/**
 * 定义退货单/售后退货单审核位掩码常量
 *
 * @author wangbiao@shopex.cn
 * @version 2025.06.25
 */
class ome_reship_combine_const
{
    // 禁止合并审核（京东引凤退款转退货拦截）
    const __NOT_COMBINE = 0x01;

    private $status = array(
        self::__NOT_COMBINE => array('identifier' => '禁审', 'text' => '禁止合并审核', 'color' => '#DC143C', 'search' => 'true'),
    );

    /**
     * 获取业务标识名称
     *
     * @param int $isNotCombine
     * @return string
     */
    public function getIdentifier($isNotCombine)
    {
        $str = '';
        foreach ($this->status as $key => $val) {
            if ($isNotCombine & $key) {
                $str .= sprintf("<span class='tag-label' title='%s' style='background-color:%s;color:#ffffff;'>%s</span>", $val['text'], $val['color'], $val['identifier']);
            }
        }
        return $str;
    }
}
