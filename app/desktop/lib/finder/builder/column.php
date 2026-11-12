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


class desktop_finder_builder_column extends desktop_finder_builder_prototype{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    function main(){
        $finder_aliasname = $_GET['finder_aliasname']?$_GET['finder_aliasname']:$_POST['finder_aliasname'];
        if($_POST['col']){
            $finder_aliasname = $finder_aliasname.'.'.$this->controller->user->user_id;
            $cols = $this->app->setConf('view.'.$this->object_name.'.'.$finder_aliasname,implode(',',$_POST['col']));
            if($_POST['allcol']){
                $this->app->setConf('listorder.'.$this->object_name.'.'.$finder_aliasname,implode(',',$_POST['allcol']));
            }
            header('Content-Type:text/jcmd; charset=utf-8');
            echo '{success:"'.app::get('desktop')->_('设置成功').'"}';    
        }else{
           $in_use = array_flip($this->getColumns());
            $all_columns = $this->all_columns();

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
            
            // 添加全选功能
            $html .= '<div style="margin: 8px 0; padding: 8px 12px; background: #f8f9fa; border-radius: 4px; display: flex; align-items: center; gap: 5px;">';
            $html .= '<input type="checkbox" id="select-all" style="margin: 0;" />';
            $html .= '<label for="select-all" style="cursor: pointer; font-size: 12px; color: #333; margin: 0;">'.app::get('desktop')->_('全选').'</label>';
            $html .= '</div>';
            
            // 直接生成row元素，使用CSS实现真正的四列布局
            foreach($all_columns as $key=>$col){
                $i++;
                $html .= '<div class="row" style="display: inline-block; width: calc(25% - 4px); margin: 2px; vertical-align: top; border: 1px solid #e8e8e8; border-radius: 3px; padding: 4px; background: #fafafa; box-sizing: border-box;">';
                $html .= '<div class="row-line item" style="display: flex; align-items: center; gap: 3px;"><input type="hidden" value="'.$key.'" name="allcol[]" />'.$mv_handler.'<span style="background: #007cba; color: white; border-radius: 2px; padding: 1px 3px; font-size: 10px; min-width: 16px; text-align: center; margin-right: 2px;">'.$i.'</span><input type="checkbox" style="margin: 0;" class="column-checkbox" '.(isset($in_use[$key])?' checked="checked" ':'').' value="'.$key.'" name="col[]" id="finder-col-set-'.$i.'" />
                    <label for="finder-col-set-'.$i.'" style="flex: 1; margin: 0; cursor: pointer; font-size: 11px; line-height: 1.3;">'.app::get('desktop')->_($col['label']).'</label></div>';
                $html .= '</div>';
            }
            $finder_id=$_GET['_finder']['finder_id'];   
            $html .= '<!-----.mainHead-----&darr;&nbsp;'.app::get('desktop')->_('拖动改变顺序').'-----.mainHead----->';
            $html .= '<!-----.mainFoot-----<div class="table-action"><button class="btn btn-primary" onclick="submitForm()"><span><span>'.app::get('desktop')->_('保存提交').'</span></span></button></div>-----.mainFoot----->';
            $html .= '<input type="hidden" name="finder_aliasname" value="'.$finder_aliasname.'"/>';
            $html .= '</form>';
            $html .= '</div>';
            
            $html.=<<<EOF
            <script>
              (function(){
				var scrollAuto =  new Scroller($('{$domid}').getContainer()); 
                var draggedElement = null;
                
                // 提交表单函数
                window.submitForm = function() {
                    var checkedBoxes = $('{$domid}').getElements('.column-checkbox').filter(function(checkbox) {
                        return checkbox.checked;
                    });
                    
                    if (checkedBoxes.length === 0) {
                        MessageBox.error('请至少选择一个列！');
                        return false;
                    }
                    
                    // 验证通过，使用fireEvent提交表单
                    $('{$domid}').fireEvent('submit', {stop: function(){}});
                };
                
                // 全选功能
                $('select-all').addEvent('change', function() {
                    var isChecked = this.checked;
                    $('{$domid}').getElements('.column-checkbox').each(function(checkbox) {
                        checkbox.checked = isChecked;
                    });
                });
                
                // 单个复选框变化时更新全选状态
                $('{$domid}').addEvent('change', function(e) {
                    if (e.target.hasClass('column-checkbox')) {
                        var allCheckboxes = $('{$domid}').getElements('.column-checkbox');
                        var checkedCount = allCheckboxes.filter(function(checkbox) {
                            return checkbox.checked;
                        }).length;
                        
                        var selectAllCheckbox = $('select-all');
                        if (checkedCount === 0) {
                            selectAllCheckbox.checked = false;
                            selectAllCheckbox.indeterminate = false;
                        } else if (checkedCount === allCheckboxes.length) {
                            selectAllCheckbox.checked = true;
                            selectAllCheckbox.indeterminate = false;
                        } else {
                            selectAllCheckbox.checked = false;
                            selectAllCheckbox.indeterminate = true;
                        }
                    }
                });
                

                
                new Sortables($('{$domid}'),{
                    clone: false,
                    opacity: .5,
                    handle: '.move-handler',
                    onStart: function(element){
                        $('{$domid}').addClass('move-active');
                        scrollAuto.start();
                        draggedElement = element;
                    },
                    onComplete: function(){
                        scrollAuto.stop();
                        $('{$domid}').removeClass('move-active');
                        
                        // 只高亮被拖动的元素
                        if (draggedElement) {
                            var span = draggedElement.getElement('span');
                            if (span) {
                                span.setStyle('background', '#ff6b6b');
                                span.setStyle('color', 'white');
                                span.setStyle('font-weight', 'bold');
                            }
                            draggedElement = null;
                        }
                    }
                });
                $('{$domid}').store('target',{onComplete:function(){
                    $('{$domid}').getParent('.dialog').retrieve('instance').close();
                    window.finderGroup['{$finder_id}'].refresh();
                }});
              })();
            </script>
EOF;
            
            echo $html;
        }
    }
}
