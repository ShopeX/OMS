<?php
/**
 * 店铺商品同步
 */
class erpapi_shop_matrix_b2b_response_goods extends erpapi_shop_response_goods 
{
    protected function _formatAddParams($params)
    {
        $params['skus'] =  $params['skus'] ? @json_decode($params['skus'], true) : [];
        return parent::_formatAddParams(['data'=>$params]);
    }
    
    /**
     * 删除店铺商品
     *
     * @return void
     * @author
     */
    public function delete($params)
    {
        return parent::delete(['data' => $params]);
    }
} 