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


class desktop_finder_builder_detail extends desktop_finder_builder_prototype{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    function main(){
        if (isset($this->detail_pages))
        {
            foreach ((array)$this->detail_pages as $k=>$detail_func)
            {
                $str_detail_order = 'detail_' . $detail_func[1] . '_order';
                if (isset($detail_func[0]->$str_detail_order) && $detail_func[0]->$str_detail_order)
                {
                    switch ($detail_func[0]->$str_detail_order)
                    {
                        case COLUMN_IN_HEAD:
                            $tmp = $this->detail_pages[$k];
                            unset($this->detail_pages[$k]);
                            $this->detail_pages = array_reverse($this->detail_pages);
                            $this->detail_pages[$k] = $tmp;
                            $this->detail_pages = array_reverse($this->detail_pages);
                            break;
                        case COLUMN_IN_TAIL:
                            $tmp = $this->detail_pages[$k];
                            unset($this->detail_pages[$k]);
                            $this->detail_pages[$k] = $tmp;
                            break;
                    }
                }
            }
        }
        if(kernel::single('base_component_request')->is_ajax()&&$_GET['singlepage']!='true'){ 
            //$_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest';
            if(isset($this->detail_pages)){
                $tab_header = '<div class="tabs-wrap finder-tabs-wrap clearfix"><div class="tabs-items"><ul>';
                foreach($this->detail_pages as $k=>$detail_func){
                    if($k==$_GET['finderview']){
                        $tab_header.='<li class="tab current" item-id="'.$_GET['id'].'" url="'.$this->url.'&action=detail&finderview='.$k.'"><span>';
                        $method = $detail_func[1];
                        $tab_header.= $detail_func[0]->$method;
                        $detail_html = $detail_func[0]->$method($_GET['id']);
                    }else{                                                                 
                        //if($_GET['view'])unset($_GET['view']);
                        $method = $detail_func[1];
                        $tab_action = $this->url.'&action=detail&finderview='.$k;
                        $tab_header.='<li class="tab"><span>';
                        $tab_header.='<a target="{update:\'finder-detail-'.$this->name.'\'}" href="'.$tab_action.'">'.$detail_func[0]->$method.'</a>';
                    }

                    $tab_header.='</span></li>';
                }
				$tab_header.='</ul></div>'
					   .'<div class="scroll-handle l"><span>&laquo;</span></div>'
					   .'<div class="scroll-handle r"><span>&raquo;</span></div></div>';
            }
            if(count($this->detail_pages)>1){
                echo $tab_header;
            }
            echo $detail_html;
        }else{
            if(kernel::single('base_component_request')->is_ajax()&&$_GET['singlepage']=='true'){
                 foreach($this->detail_pages as $k=>$detail_func){
                    if($_GET['finderview']==$k){
                        $html = $detail_func[0]->$detail_func[1]($_GET['id']);
                        $label = $detail_func[0]->$detail_func[1];
echo <<<EOF
<h3>{$label}</h3>    
{$html}
EOF;
exit;
                    }
                 }
            }else{
                foreach($this->detail_pages as $k=>$detail_func){
                    
                    $detail_html = $detail_func[0]->{$detail_func[1]}($_GET['id']);

                    $this->controller->pagedata['_detail_func'][$k] = array(
                            'label' => $detail_func[0]->{$detail_func[1]},
                            'html' => $detail_html,
                        );
                }

                $this->controller->singlepage('common/detail-in-one.html', 'desktop');
            }
        }
    }

}
