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


class desktop_finder_builder_tab extends desktop_finder_builder_prototype{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    /**
     * main
     * @return mixed 返回值
     */
    public function main(){
        $finder_aliasname = $_GET['finder_aliasname']?$_GET['finder_aliasname']:$_POST['finder_aliasname'];

        if($_POST['do_save']){
            $finder_aliasname = $finder_aliasname.'.'.$this->controller->user->user_id;

            $this->app->setConf('tabview.'.$_GET['app'].$_GET['ctl'].$_GET['act'].$this->object_name.'.'.$finder_aliasname,implode(',',(array) $_POST['tab_name']));

            header('Content-Type:text/jcmd; charset=utf-8');
            echo '{success:"'.app::get('desktop')->_('设置成功').'"}';

        }else{  // 编辑

            $tabview = $this->app->getConf('tabview.'.$_GET['app'].$_GET['ctl'].$_GET['act'].$this->object_name.'.'.$finder_aliasname.'.'.$this->controller->user->user_id);

            $in_use = explode(',', $tabview);
            foreach ($in_use as $key => $value) {
                if ($value === '') {
                    unset($in_use[$key]);
                }
            }

            $all_tab = $this->get_views();

            $domid = $this->ui->new_dom_id();

            $html = '<div class="gridlist">';
            $html .= '<form id="'.$domid.'" method="post" action="index.php?'.$_SERVER['QUERY_STRING'].'">';

            $mv_handler = $this->ui->img(array('src'=>'bundle/grippy.gif', 'class'=>'move-handler','style'=>'cursor:default;'));

            $i=0;
            foreach($all_tab as $key=>$tab){
                $i++;
                $html .= '<div class="row">';
                $html .= '<div class="row-line item">'.$mv_handler.'<input type="checkbox" '.(in_array($key,(array) $in_use)?' checked="checked" ':'').' value="'.$key.'" name="tab_name[]" id="finder-tab-set-'.$i.'" />
                    <label for="finder-tab-set-'.$i.'">'.app::get('desktop')->_($tab['label']).'</label></div>';
                $html .= '</div>';
            }


            $finder_id=$_GET['_finder']['finder_id'];   
            // $html .= '<!-----.mainHead-----&darr;&nbsp;'.app::get('desktop')->_('拖动改变顺序').'-----.mainHead----->';
            $html .= '<!-----.mainFoot-----<div class="table-action"><button class="btn btn-primary" onclick="$(\''.$domid.'\').fireEvent(\'submit\',{stop:$empty})"><span><span>'.app::get('desktop')->_('保存提交').'</span></span></button></div>-----.mainFoot----->';
            $html .= '<input type="hidden" name="finder_aliasname" value="'.$finder_aliasname.'"/>';
            $html .= '<input type="hidden" name="do_save" value="true"/>';
            $html .= '</form>';
            $html .= '</div>';
            
            parse_str($_SERVER['QUERY_STRING'],$output); unset($output['action']);

            $url = 'index.php?' . http_build_query($output);

            $html.=<<<EOF
            <script>
              (function(){
                $('{$domid}').store('target',{onComplete:function(){
                    $('{$domid}').getParent('.dialog').retrieve('instance').close();
                    W.page('$url');
                }});
              })();
            </script>
EOF;
            
            echo $html;
        }
    }
}
