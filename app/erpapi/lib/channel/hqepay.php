<?php
/**
 * 物流跟踪渠道
 * kdn：查第三方快递 Matrix 绑定
 * sf：第三方快递 iframe 绑定（自有key）+ 查询/路由注册
 */
class erpapi_channel_hqepay extends erpapi_channel_abstract
{
    public $channel;

    public function init($node_id, $hqepay_id)
    {
        $this->__adapter  = '';
        $this->__platform = '';

        // set('hqepay','sf')->sf_* 时强制走顺丰；否则看系统配置（默认 kdn）
        $provider = ($hqepay_id === 'sf') ? 'sf' : $this->getTrackProvider();
        $this->__platform = $provider;
        $this->channel['hqepay_id'] = $hqepay_id;

        if ($provider == 'sf') {
            // 查询 to_node_id 必须取「第三方快递」里已绑定成功的 sf 节点，不能写死电子面单节点
            $bound = app::get('channel')->model('channel')->dump([
                'channel_type' => 'kuaidi',
                'node_type'    => 'sf',
                'filter_sql'   => 'node_id IS NOT NULL AND node_id!=""',
            ]);
            if (!$bound || empty($bound['node_id'])) {
                return false;
            }
            if (!empty($bound['config']) && is_string($bound['config'])) {
                $bound['config'] = @unserialize($bound['config']) ?: array();
            }
            $this->channel = array(
                'hqepay_id'    => $hqepay_id,
                'channel_id'   => $bound['channel_id'],
                'channel_type' => 'kuaidi',
                'node_type'    => 'sf',
                'node_id'      => $bound['node_id'],
                'shipper'      => $bound['shipper'] ?? '',
                'config'       => is_array($bound['config'] ?? null) ? $bound['config'] : array(),
            );
            return true;
        }

        // 默认快递鸟
        $channel = app::get('channel')->model('channel')->dump([
            'channel_type' => 'kuaidi',
            'node_type'    => 'kdn',
            'filter_sql'   => 'node_id IS NOT NULL AND node_id!=""',
        ]);

        if (!$channel) {
            return false;
        }

        if (!empty($channel['config']) && is_string($channel['config'])) {
            $channel['config'] = @unserialize($channel['config']) ?: array();
        }

        $this->channel = $channel;

        return true;
    }

    public function getTrackProvider()
    {
        $provider = app::get('ome')->getConf('ome.logistics.track.provider');
        return $provider ?: 'kdn';
    }
}
