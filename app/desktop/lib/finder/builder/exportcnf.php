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

class desktop_finder_builder_exportcnf extends desktop_finder_builder_prototype{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    function main(){
        $finder_aliasname = $_GET['finder_aliasname']?$_GET['finder_aliasname']:$_POST['finder_aliasname'];
        if($_POST['col']){
            $all_columns = $this->all_columns();

            //扩展额外导出的字段
            if(method_exists($this->object,'export_extra_cols')){
                $this->export_extra_cols = $this->object->export_extra_cols();
                $all_columns = array_merge($all_columns, $this->export_extra_cols);
            }

            //去除多余没用的导出字段
            unset($all_columns['column_confirm'], $all_columns['column_control'], $all_columns['column_picurl']);

            if(method_exists($this->object,'disabled_export_cols')){
                $this->object->disabled_export_cols($all_columns);
            }

            $msg = '';
            foreach($_POST['col'] as $key=>$col){
                $msg.= $all_columns[$col]['label'].' ,';
            }
            $msg = substr($msg,0,strlen($msg)-2);

            $return = array(
                'code' => 'SUCC',
                'msg' => json_encode(array('type'=>$this->object_name,'desc'=>$msg,'content'=>implode(',',$_POST['col'])))
            );
            echo json_encode($return);
        }else{
            $in_use = array_flip($this->getColumns());
            $all_columns = $this->all_columns();

            //扩展额外导出的字段
            if(method_exists($this->object,'export_extra_cols')){
                $this->export_extra_cols = $this->object->export_extra_cols();
                $all_columns = array_merge($all_columns, $this->export_extra_cols);
            }

            //去除多余没用的导出字段
            unset($in_use['column_confirm'], $in_use['column_control'], $in_use['column_picurl']);
            unset($all_columns['column_confirm'], $all_columns['column_control'], $all_columns['column_picurl']);

            if(method_exists($this->object,'disabled_export_cols')){
                $this->object->disabled_export_cols($in_use);
                $this->object->disabled_export_cols($all_columns);
            }

            $listorder = explode(',',$this->app->getConf('listorder.'.$this->object_name.'.'.$finder_aliasname.'.'.$this->controller->user->user_id));
            if($listorder){
                $ordered_columns = array();
                foreach($listorder as $col){
                    if(isset($all_columns[$col])){
                        $ordered_columns[$col] = $all_columns[$col];
                        unset($all_columns[$col]);
                    }
                }
                $all_columns = array_merge((array)$ordered_columns,(array)$all_columns);
                $ordered_columns = null;
            }

            $domid = $this->ui->new_dom_id();
            $html = '<div class="gridlist">';
            $html .= '<form id="'.$domid.'" method="post" action="index.php?'.$_SERVER['QUERY_STRING'].'">';
            $mv_handler = $this->ui->img(array('src'=>'bundle/grippy.gif', 'class'=>'move-handler'));
            $i=0;
            foreach($all_columns as $key=>$col){
                $i++;
                $html .= '<div class="row">';
                $html .= '<div class="row-line item"><input type="hidden" value="'.$key.'" name="allcol[]" />'.$mv_handler.'<input type="checkbox" '.(isset($in_use[$key])?' checked="checked" ':'').' value="'.$key.'" name="col[]" id="finder-col-set-'.$i.'" />
                    <label for="finder-col-set-'.$i.'">'.app::get('desktop')->_($col['label']).'</label></div>';
                $html .= '</div>';
            }
            $finder_id=$_GET['_finder']['finder_id'];   
            $html .= '<!-----.mainHead-----&darr;&nbsp;'.app::get('desktop')->_('拖动改变顺序').'-----.mainHead----->';
            $html .= '<!-----.mainFoot-----<div class="table-action"><button id="saveBtn" class="btn btn-primary" onclick="$(\''.$domid.'\').fireEvent(\'submit\',{stop:$empty})"><span><span>'.app::get('desktop')->_('确定提交').'</span></span></button></div>-----.mainFoot----->';
            $html .= '<input type="hidden" name="finder_aliasname" value="'.$finder_aliasname.'"/>';
            $html .= '</form>';
            $html .= '</div>';
            
            $html.=<<<EOF
            <script>
              (function(){
				var scrollAuto =  new Scroller($('{$domid}').getContainer()); 
                new Sortables($('{$domid}'),{clone:false,opacity:.5,handle:'.move-handler',onStart:function(){
                    $('{$domid}').addClass('move-active');
                    scrollAuto.start();
                },onComplete:function(){
                    scrollAuto.stop();
                    $('{$domid}').removeClass('move-active');
                }});
              })();

              $('{$domid}').removeEvents('submit').addEvent('submit', function(e){
                    e.stop();
                    new Request.JSON ({
                        url:this.action,
                        onRequest:function () {
                            $('saveBtn').set('disabled', 'true');
                        },
                        onSuccess:function(result) {
                            if (result.code =='SUCC') {
                                updateCnf(result.msg);
                                $('{$domid}').getParent('.dialog').retrieve('instance').close();
                            } else {
                                $('saveBtn').set('disabled', '');
                                alert(result.msg);
                            }
                        }
                    })[this.method](this);
                });

                function updateCnf(cnf) {
                    $('cnfList').getElement('td').destroy();
                    var info = Json.evaluate(cnf);
                    var addItem=new Element('td .cnfItem',{colspan:'2',html:'<div title="fields">' + info.desc + '<input type="hidden" name="export_fields" value="'+info.content+'"></div>'}).inject('cnfList');
                    $('cnfaddbtn').getElement('.save-btn').set('ref',cnf);
                }
            </script>
EOF;
            
            echo $html;
        }
    }
}
