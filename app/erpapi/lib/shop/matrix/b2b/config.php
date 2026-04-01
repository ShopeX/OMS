<?php
/**
 * CONFIG
 *
 * @category
 * @package
 * @author chenping<chenping@shopex.cn>
 * @version $Id: Z
 */
class erpapi_shop_matrix_b2b_config extends erpapi_shop_config
{

    private $__method_mapping = array(
        SHOP_UPDATE_ITEMS_QUANTITY_LIST_RPC => 'b2b.update_store.updateStore',
        SHOP_LOGISTICS_OFFLINE_SEND => 'b2b.delivery.update',
        SHOP_ADD_AFTERSALE_RPC => 'b2b.aftersale.create',
        SHOP_RETURN_GOOD_CONFIRM => 'b2b.aftersale.update',
        SHOP_ADD_REFUND_RPC => 'b2b.refund.create',
        SHOP_REFUSE_REFUND => 'b2b.refund.refuse',
        EINVOICE_DETAIL_UPLOAD => 'b2b.invoice.send',
        SHOP_UPDATE_RESHIP_STATUS_RPC => 'b2b.aftersale.update',
        SHOP_EXCHANGE_NOTIFY=>'b2b.exchange.notify',
        SHOP_EXCHANGE_CONSIGNGOODS=>'b2b.exchange.consigngoods',
    );

    public function init(erpapi_channel_abstract $channel)
    {
        return parent::init($channel);
    }

    /**
     * 应用级参数
     *
     * @param String $method 请求方法
     * @param Array  $params 业务级请求参数
     *
     * @return void
     * @author
     **/
    public function get_query_params($method, $params)
    {
        $query_params = [
            'node_id' => $this->__channelObj->channel['node_id'],
            'method' => $this->__method_mapping[$method]? $this->__method_mapping[$method]:$method,
            'date' => date('Y-m-d H:i:s', time()),
        ];
    
        return $query_params;
    }

    /**
     * 获取请求地址
     *
     * @param String  $method   请求方法
     * @param Array   $params   业务级请求参数
     * @param Boolean $realtime 同步|异步
     * @return void
     * @author
     **/
    public function get_url($method, $params, $realtime)
    {
        $url = $this->__channelObj->channel['config']['b2b_url'];
  
        return $url;
    }

    /**
     * 格式化请求参数
     *
     * @param Array $params 请求参数
     *
     * @author
     **/
    public function format($query_params)
    {
        return $query_params;
    }

    /**
     * 请求签名
     *
     * @param Array $params 业务级请求参数
     *
     * @return String $sign 签名值
     * @author
     **/
    public function gen_sign($params)
    {
        $responseAppSecret = $this->__channelObj->channel['config']['b2b_response_secret'];

        $str = self::response_assemble($params);
        return hash_hmac('sha256', $str, $responseAppSecret);
    }

    public function request_sign($params, $appSecret)
    {
        return strtoupper(md5(self::request_assemble($params) .'secretKey='. $appSecret));
    }

    public function response_sign($params, $appSecret){

        $str = self::response_assemble($params);

        return hash_hmac('sha256', $str, $appSecret);
    }

    static function request_assemble($params)
    {
        if (!is_array($params)) {
            return null;
        }

        unset($params['data']);
        ksort($params, SORT_STRING);
        $sign = '';
        foreach ($params as $key => $val) {
            if (is_null($val)) {
                continue;
            }
            if (is_bool($val)) {
                $val = ($val) ? 1 : 0;
            }
            $sign .= $key . "=" . $val . "&";
        }

        return $sign;
    }

    static function response_assemble($params){
        if (!is_array($params)) {
            return null;
        }
        ksort($params, SORT_STRING);
        $sign = '';
        foreach ($params as $key => $val) {
            if (is_null($val)) {
                continue;
            }
            if (is_bool($val)) {
                $val = ($val) ? 1 : 0;
            }
            $sign .= $key . (is_array($val) ? self::assemble($val) : $val);
        }
        return $sign;
    }
} 