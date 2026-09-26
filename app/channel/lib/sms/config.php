<?php
class channel_sms_config 
{
    public function formatConfig($config, $channel)
    {
        if(isset($channel['channel_id']) && $channel['channel_id']){
            $channelMdl = app::get('channel')->model('channel');
            $oldChannel = $channelMdl->db_dump($channel['channel_id'], '*');
            $channel['channel_adapter'] = $oldChannel['channel_adapter'];
            if(!isset($channel['node_type']) || !$channel['node_type']){
                $channel['node_type'] = $oldChannel['node_type'];
            }
        }

        if(!isset($channel['channel_adapter']) || !$channel['channel_adapter']){
            return $channel;
        }

        if(!$channel['node_id']){
            switch ($channel['channel_adapter']) {
                case 'openapi':
                    $channel['node_id'] = sprintf('o%u', crc32(utils::array_md5($config) . kernel::base_url()));
                    break;
                default:
                    break;
            }
        }

        if($channel['disabled'] == 'false'){
            $channelMdl = app::get('channel')->model('channel');
            $enabledChannels = $channelMdl->getList('channel_id', array('channel_type' => 'sms', 'disabled' => 'false'), 0, -1);
            foreach($enabledChannels as $enabledChannel){
                if(isset($channel['channel_id']) && $channel['channel_id'] == $enabledChannel['channel_id']){
                    continue;
                }
                $oldSmsChannel = $channelMdl->dump($enabledChannel['channel_id'], '*');
                $oldSmsChannel['disabled'] = 'true';
                $channelMdl->save($oldSmsChannel);
            }
        }

        return $channel;
   }

    public function saveAdapterConfig($config, $channel)
    {
        if(!$this->validateConfig($config, $channel['node_type'])){
            return false;
        }

        $adapterData = array(
            'channel_id' => $channel['channel_id'],
            'adapter' => $channel['channel_adapter'],
            'config' => serialize($config),
        );

        return app::get('channel')->model('adapter')->save($adapterData);
    }

    private function validateConfig($config, $nodeType)
    {
        $requiredMap = array(
            'shopex' => array('entid', 'password', 'email'),
            'soap' => array('wsdl_url', 'username', 'password', 'companyCode', 'timeMsg'),
        );

        if(!isset($requiredMap[$nodeType])){
            return false;
        }

        foreach($requiredMap[$nodeType] as $field){
            if(!array_key_exists($field, $config)){
                return false;
            }
            if($field != 'timeMsg' && $config[$field] === ''){
                return false;
            }
        }

        return true;
    }
}
