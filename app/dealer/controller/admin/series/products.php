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
 * 产品线列表
 * @author wangjianjun@shopex.cn
 * @version 2024.04.12
 */
class dealer_ctl_admin_series_products extends desktop_controller
{

    /**
     * 产品线查询项方法
     * @param Post
     * @return String
     */
    public function index($type = 'series')
    {
        $actions = $base_filter = [];
        $basic_material = ['series' => '产品线维度', 'bs' => '经销商维度', 'sku' => '商品维度'];
        $actions[] = array(
            'label' => $basic_material[$type],
            'group' => array(
                array('label' => '产品线维度', 'href' => 'index.php?app=dealer&ctl=admin_series_products&act=index&p[0]=series'),
                array('label' => '经销商维度', 'href' => 'index.php?app=dealer&ctl=admin_series_products&act=index&p[0]=bs'),
                array('label' => '商品维度', 'href' => 'index.php?app=dealer&ctl=admin_series_products&act=index&p[0]=sku'),
            ),
        );
        if ($type == 'series') {
            $this->seriesIndex($actions,$base_filter);
            exit;
        } elseif ($type == 'bs') {
            $this->bsIndex($actions,$base_filter);
            exit;
        } elseif ($type == 'sku') {
            $this->skuIndex($actions,$base_filter);
            exit;
        }
    }

    /**
     * 产品线维度视图
     *
     * @return void
     * @author
     **/
    public function seriesIndex($actions,$base_filter)
    {
        $params = array(
            'title'               => '产品线查询',
            'actions'             => $actions,
            'base_filter'         => $base_filter,
            'use_buildin_set_tag' => false,
            'use_buildin_filter'  => false,
            'use_buildin_export'  => false,
            'use_buildin_filter'  => true,
            'use_buildin_recycle' => false,
            'object_method'       => [
                'count'   => 'finder_count',
                'getlist' => 'finder_getList',
            ],
        );
        $this->finder('dealer_mdl_series_products', $params);
    }

    /**
     * 经销商维度视图
     *
     * @return void
     * @author
     **/
    public function bsIndex($actions,$base_filter)
    {
        $base_filter['filter_sql'] = ' e.bs_id is not null'; 
        $params = array(
            'title'               => '产品线查询',
            'actions'             => $actions,
            'base_filter'         => $base_filter,
            'use_buildin_set_tag' => false,
            'use_buildin_filter'  => false,
            'use_buildin_export'  => false,
            'use_buildin_filter'  => true,
            'use_buildin_recycle' => false,
            'object_method'       => [
                'count'   => 'finder_count',
                'getlist' => 'finder_getList',
            ],
        );
        $this->finder('dealer_mdl_series_endorse', $params);

        $html = <<<EOF
        <script>
              $$(".show_list").addEvent('click',function(e){
                var series_id = this.get('series_id');
                var bs_id = this.get('bs_id');
                var t_url ='index.php?app=dealer&ctl=admin_series_products&act=findseriesProducts&series_id='+series_id+'&bs_id='+bs_id;
              var url='index.php?app=desktop&act=alertpages&goto='+encodeURIComponent(t_url);
        Ex_Loader('modedialog',function() {
            new finderDialog(url,{width:1000,height:660,

            });
        });
              });

        </script>
EOF;
        echo $html;exit;
    }

    /**
     * 商品维度视图
     *
     * @return void
     * @author
     **/
    public function skuIndex($actions,$base_filter)
    {
        $params = array(
            'title'               => '商品查询',
            'actions'             => $actions,
            'base_filter'         => $base_filter,
            'use_buildin_set_tag' => false,
            'use_buildin_filter'  => false,
            'use_buildin_export'  => false,
            'use_buildin_filter'  => true,
            'use_buildin_recycle' => false,
            'object_method'       => [
                'count'   => 'finder_count',
                'getlist' => 'finder_getList',
            ],
        );
        $this->finder('dealer_mdl_series_endorse_sku', $params);
    }

    public function findseriesProducts(){
        $series_id = $_GET['series_id'];
        $bs_id     = isset($_get['bs_id']) ? $_get['bs_id'] : '';
        $params    = array(
            'title'                  => '产品线物料列表',
            'use_buildin_new_dialog' => false,
            'use_buildin_set_tag'    => false,
            'use_buildin_recycle'    => false,
            'use_buildin_export'     => false,
            'use_buildin_filter'     => true,
            'object_method'       => [
                'count'   => 'finder_count',
                'getlist' => 'finder_getList',
            ],
        );

        $params['base_filter']['series_id'] = $series_id;   
        $params['base_filter']['bs_id']     = $bs_id;   
        
        $this->finder('dealer_mdl_series_products', $params);
    }

    public function exportSeriesProductsTemplate()
    {
        $seriesMdl   = app::get('dealer')->model('series_products');
        $row = $seriesMdl->exportTemplate();
        $lib = kernel::single('omecsv_phpexcel');
        $lib->newExportExcel(null, '物料导入模板', 'xls', $row);
    }

}
