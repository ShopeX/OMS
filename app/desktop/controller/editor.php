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
 * ShopEx licence
 *
 * @copyright  Copyright (c) 2005-2010 ShopEx Technologies Inc. (http://www.shopex.cn)
 * @license  http://ecos.shopex.cn/ ShopEx License
 */
 
class desktop_ctl_editor extends desktop_controller{

    function uploader(){
        $html ='<form action="index.php?ctl=editor&act=save_upload&name='.$_GET['name']
            .'&domid='.$_GET['domid'].'"  method="post" enctype="multipart/form-data">';
        $params = array(
                'type'=>'file',
                'name'=>'upload_item',
            );

        $html .= '<div class="division" style="border:none; text-align:center;">';
        $html .=utils::buildTag($params,'input');
        $html .= '</div>';
        $html .= '<div class="table-action" style="border: none;"><input type="submit" value='.app::get('desktop')->_("上传").' /></div>';
        $html.= '</form>';
        echo $html;
    }

    function save_upload(){
        $image = $this->app->model('image');
        $file_id = $image->store($_FILES['upload_item']['tmp_name']);

        if($_GET['domid']){
            $content = $_FILES['upload_item']['name'].' ('.$_FILES['upload_item']['size'].')';
            $content .='<input type="hidden" name="'.($_GET['name']).'" value="'.urlencode($file_id).'" />';
            echo '<script>window.parent.document.getElementById("'.$_GET['domid'].'").innerHTML="'.str_replace('"','\\"',$content).'"</script>';
        }else{
            echo 'ok';
        }
    }
    function save_gpic(){
        $image = $this->app->model('image');
        $image_id = $image->store($_FILES['Filedata']['tmp_name']);
        $image_s = storager($image_id,'s');
        $image_b = storager($image_id,'b');
        $this->pagedata['gimage']['image_id'] = $image_id;
        echo $this->fetch('goods/detail/img/gimage.html');
    }

    function imglib(){ 

        $image_tags[] = array('tag_id'=>null,'tag_name'=>app::get('desktop')->_('所有图片'),'count'=>'12');

        $this->pagedata['tags'] = &$image_tags;
        ob_start();
        $this->_img_list();
        $this->pagedata['image_list'] = ob_get_contents();
        ob_end_clean();

        $this->display('common/imglib.html');
    }

    function _img_list(){
        $img = $this->app->model('image');
        $html='';
        foreach($img->getList('url,s_url,l_url,m_url,image_id,width,height',$filter,0,20) as $item){
            $url = $this->app->base_url().($item['s_url']?$item['s_url']:(
                    $item['m_url']?$item['m_url']:(
                        $item['l_url']?$item['l_url']:$item['url']
                    )
                ));
            if( max($item['width'],$item['height'])>96){
                $tag = (($item['width']>$item['height'])?'width=':'height=').'"96"';
            }else{
                $tag = '';
            }
            $html.=<<<EOF
            <div image_id="{$image_id}" style="text-align:center;vertical-align: middle;float:left;width:96px;height:96px;border:3px solid #ddd;margin:3px">
            <img {$tag} src="{$url}" />
            </div>
EOF;
        }

        $pager = $this->ui()->pager(array(
            'current'=>2,
            'total'=>200,
            'link'=>'javascript:'.$this->var_name.'.page(%d)',
            'nobutton'=>false,
            ));
        echo $html.$pager;
    }

    function table(){
        echo '
            <div class="tableform" style=" background:none">
                 <table cellspacing="0" cellpadding="0" border="0">

                    <tr><th>'.app::get('desktop')->_('行数：').'</th>

                        <td>&nbsp;<input id="txtRows" type="text" maxlength="3" size="2" value="3" name="txtRows" /></td>

                  <th>'.app::get('desktop')->_('列数:').'</th>

                        <td>&nbsp;<input id="txtColumns" type="text" maxlength="2" size="2" value="2" name="txtColumns" /></td>
                    </tr>

                  
                    <tr><th>'.app::get('desktop')->_('边框粗细:').'</th>

                        <td>&nbsp;<input id="txtBorder" type="text" maxlength="2" size="2" value="1" name="txtBorder" /></td>

                    <th>'.app::get('desktop')->_('对齐标题:').'</th>

                        <td><select id="selAlignment" name="selAlignment">
                          <option value="" selected="selected">'.app::get('desktop')->_('默认').'</option>
                          <option value="left">'.app::get('desktop')->_('左边').'</option>
                          <option value="center">'.app::get('desktop')->_('中间').'</option>
                          <option value="right">'.app::get('desktop')->_('右边').'</option>
                        </select></td>
                    </tr>

               
                <tr><th>'.app::get('desktop')->_('表格宽度:').'</th>
                    <td><input id="txtWidth" type="text" maxlength="4" size="3" value="200" name="txtWidth" />
                    <select id="selWidthType" name="selWidthType">

                      <option value="px" selected="selected">'.app::get('desktop')->_('像素').'</option>
                      <option value="%">'.app::get('desktop')->_('百分比').'</option>
                    </select></td>
                    <th>'.app::get('desktop')->_('表格宽度:').'</th>
                    <td><input id="txtHeight" type="text" maxlength="4" size="3" name="txtHeight" />'.app::get('desktop')->_('像素').'</td>
                </tr>

                
                
                <tr><th>'.app::get('desktop')->_('单元格边距').'</th>

                    <td>&nbsp;<input id="txtCellSpacing" type="text" maxlength="2" size="2" value="1" name="txtCellSpacing" /></td>

                <th>'.app::get('desktop')->_('单元格间距').'</th>

                    <td>&nbsp;<input id="txtCellPadding" type="text" maxlength="2" size="2" value="1" name="txtCellPadding" /></td>
               
        </tr></table>
        <div class="mainFoot"><div class="table-action">
            <button type="button" class="btn" id="mce_dlg_ok"><span><span>'.app::get('desktop')->_('确定').'</span></span></button>
            <button type="button" class="btn" isclosedialogbtn="true"><span><span>'.app::get('desktop')->_('取消').'</span></span></button>
        </div></div>
</div>
        <script>
           $("mce_dlg_ok").addEvent("click",function(){
                var ret = "<table "+($("selAlignment").value?("align=\""+$("selAlignment").value+"\" "):"")+"width=\""+$("txtWidth").value+$("selWidthType").value+"\" "+($("txtHeight").value?("height=\""+$("txtHeight").value+"\" "):"")+"border=\""+$("txtBorder").value+"\" cellspacing=\""+$("txtCellSpacing").value+"\" cellpadding=\""+$("txtCellPadding").value+"\">";
                var row="";
                for(var i=$("txtColumns").value.toInt();i>0;i--){
                row+="<td>&nbsp;</td>";
                }
                for(var i=$("txtRows").value.toInt();i>0;i--){
                ret+="<tr>"+row+"</tr>";
                }
                ret+="</table>";
              window.curEditor.exec.bind(window.curEditor)("insertHTML",ret);
            });
        </script>
';
    }


     function link(){

$html='
<div class="tableform"  id="dlg_lnk_base">
    <table width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr><th width="100">'.app::get('desktop')->_('文本：').'</th>
          <td><input type="text" size="30" name="text" vtype="required" value="'.$_POST['text'].'" /> <span style="color:red;">*</span></td>
        </tr>
        <tr><th>'.app::get('desktop')->_('标题：').'</th>
          <td><input type="text" size="30" name="title" value="'.$_POST['title'].'" /><br />'.app::get('desktop')->_('当鼠标移至链接上时，会显示链接标题。').'</td>
        </tr>
        <tr><th>&nbsp;</th>';
$html.='<td><input type="checkbox" name="_blank" value="true"';
$html.($_POST['target']=='_blank')?' checked="checked" ':' ';
$html.='id="lnkInNewWindow"><label for="lnkInNewWindow">'.app::get('desktop')->_('在新窗口中打开链接').'</label></td>';
$html.='
        </tr>
        <tr><th>'.app::get('desktop')->_('链接到：').'</th>
        <td><input type="radio" name="type" value="url" id="lnkToUrl"';
$html.=(!$_POST[type] || $_POST[type]=='url')?' checked="checked" ':' ';
$html.='><label for="lnkToUrl">'.app::get('desktop')->_('链接').'</label><input type="radio" name="type" value="goods" id="lnkToGoods"';
$html.=($_POST[type]=='goods')?' checked="checked" ':' ';
$html.='<label for="lnkToGoods">'.app::get('desktop')->_('商品').'</label><input type="radio" name="type" value="page" id="lnkToPage"';
$html.=($_POST[type]=='page')?'  checked="checked" ':' ';
$html.='><label for="lnkToPage">'.app::get('desktop')->_('页面').'</label><br /><input type="radio" name="type" value="article" id="lnkToArt"';
$html.=($_POST['type']=='article')?'  checked="checked" ':' ';
$html.='><label for="lnkToArt">'.app::get('desktop')->_('文章').'</label><input type="radio" name="type" value="email" id="lnkToMail"';
$html.=($_POST[type]=='email')?' checked="checked" ':' ';
$html.='><label for="lnkToMail">'.app::get('desktop')->_('电子邮件').'</label></td>
        </tr>
    </table>
    <div id="dolink">
        <table id="dolnkToUrl"';
$html.=($_POST[type] && $_POST[type]!='url')?' style="display:none" ':'';
$html.='

                            <tr><th >'.app::get('desktop')->_('链接地址:').'</th>

                              <td><input type="text" size="30" name="url" value="';
$html.=(!$_POST[type] || $_POST[type]=='url')?$_POST[href]:' ';
$html.='" /></td>
                            </tr>
                        </table>
                        <table id="dolnkToGoods"
';
$html.=($_POST[type]!='goods')?' style="display:none" ':'';
$__a=app::get('desktop')->_('查找商品：');
$__b=app::get('desktop')->_('查找');
$html.=<<<EOF
                            <tr ><th>$__a</th>
                              <td><input type="text" size="20" id="iptGoodsFinder" /><button onclick="new Request.HTML({url:'index.php?ctl=editor&act=find&p[0]=goods&p[1]='+encodeURIComponent($('iptGoodsFinder').value),'method':'get','update':'iptGoodsList'}).send()">$__b</button><br /><div id="iptGoodsList">{$goodsInfo}</div>
                              </td>
                            </tr>
                        </table>
<table id="dolnkToPage"
EOF;
$html.=($_POST['type']!='page')?' style="display:none" ':'';
$html.='
<tr >
  <th>'.app::get('desktop')->_('页面地址:').'</th>
  <td><select name="page" style="width:200px">
';

$article_obj = app::get('content')->model("article_indexs");
$page_data = $article_obj->getList('*');
foreach($page_data as  $page_key=>$page_val)
{
    $aTmp['title'] = $page_val['title'];
    $aTmp['url'] = app::get('site')->router()->gen_url(array('app'=>'content', 'ctl'=>'site_article', 'act'=>'index', 'arg0'=>$page_val['article_id']));
    $linked['page'][]=$aTmp;
}

  foreach((array)$linked['page'] as $page){
    $html.='<option value="'.$page['url'].'"'.(($_POST['page']==$page['title'])?' selected="selected"':'').'>'.$page['title'].'</option>';
  }
$html.='
</select></td>
</tr>
</table>
<table id="dolnkToArt"
';
$html.=($_POST['type']!='article')?' style="display:none" ':' ';
$__c=app::get('desktop')->_('查找文章:');
$html.=<<<EOF
><tr >
  <th>$__c</th>
  <td><input type="text" size="20" id="iptArtFinder" /><button onclick="new Request.HTML({url:'index.php?ctl=editor&act=find&p[0]=article&p[1]='+encodeURIComponent($('iptArtFinder').value),'method':'get','update':'iptArtList'}).send()">$__b</button><br />
  <div id="iptArtList">{$articleInfo}</div></td>
</tr>
</table>
<table  id="dolnkToMail"
EOF;
$html.=($_POST[type]!='email')?' style="display:none" ':'';
$html.='
<tr><th>'.app::get('desktop')->_('邮件地址:').'</th>
                              <td><input type="text" size="30" name="email" value="{$_POST[email]}" /></td>
                            </tr>
                        </table>
                    </div></div>
              
                <div class="mainFoot">
                <div class="table-action">
                <button type="button" class="btn" id="mce_dlg_ok"><span><span>'.app::get('desktop')->_('确定').'</span></span></button>
                <button type="button" class="btn" isclosedialogbtn="true"><span><span>'.app::get('desktop')->_('取消').'</span></span></button>
                </div></div>
';
        $html.="
<script>
\$('lnkToUrl').addEvent('click',function(){
  \$ES('table','dolink').setStyle('display','none');
  \$('dolnkToUrl').setStyle('display','');
});
\$('lnkToGoods').addEvent('click',function(){
\$ES('table','dolink').setStyle('display','none');
  \$('dolnkToGoods').setStyle('display','');
});
\$('lnkToPage').addEvent('click',function(){
\$ES('table','dolink').setStyle('display','none');
  \$('dolnkToPage').setStyle('display','');
});
\$('lnkToArt').addEvent('click',function(){
\$ES('table','dolink').setStyle('display','none');
  \$('dolnkToArt').setStyle('display','');
});
\$('lnkToMail').addEvent('click',function(){
\$ES('table','dolink').setStyle('display','none');
  \$('dolnkToMail').setStyle('display','');
});


\$('mce_dlg_ok').addEvent('click',function(){
  var setting = \$('dlg_lnk_base').getValues();
  var addon=[' ','type=\"'+setting.type+'\"',' '];
  switch(setting.type){
    case 'goods':
      setting.url = setting.goods;
      break;

    case 'page':
      setting.url = setting.page;
      break;

    case 'article':
      setting.url = setting.article;
      break;

    case 'email':
      setting.url = 'mailto:'+setting.email;
      break;

  }
   setting.url=decodeURI(setting.url);
  if(setting.title){
    addon.push('title=\"'+setting.title+'\"');
  }
  if(setting._blank){
    addon.push('target=\"_blank\"');
  }
  var link_uid='link'+(Native.UID++);

  var linkHtml = new String('<a href=\"{1}\" thref=\"{1}\"  {2} id=\"'+link_uid+'\">{0}</a>').format(setting.text,setting.url,addon.join(''));
  try{
	window.curEditor.exec.bind(window.curEditor)('insertHTML',linkHtml);
  }catch(e){}

    var  alink=window.curEditor.inc.win.document.getElementById(link_uid);

    if(alink){
         alink.href=alink.getAttribute('thref');
         alink.removeAttribute('thref');
		 alink.removeAttribute('id');
         }
});

</script>

";
        echo $html;

    }


     function image(){
         $__d=app::get('desktop')->_('选择上传图片的方式');
         $__e=app::get('desktop')->_('上传图片');
         $__f=app::get('desktop')->_('网络图片地址');
         $__g=app::get('desktop')->_('使用图库');
         $__h=app::get('desktop')->_('从您的电脑中挑选一张图片：');
         $__j=app::get('desktop')->_('为图片设置标签');
         $__k=app::get('desktop')->_('请输入标签名称：');
         $__l=app::get('desktop')->_('请选择标签名称：');
         $__z=app::get('desktop')->_('建议为图片设置标签,以方便管理图库');
         $__x=app::get('desktop')->_('输入一张网络图片的网址：');
         $__v=app::get('desktop')->_('复制网络上的一张图片路径到上面的输入框');
         $__n=app::get('desktop')->_('例如:"http://www.example.com/images/pic.jpg"');
         $__m=app::get('desktop')->_('从网店图库中挑选一张图片：');
         $__q=app::get('desktop')->_('按标签过滤:');
         $__W=app::get('desktop')->_('显示所有');
         $__r=app::get('desktop')->_('请点击选择要使用的图片');
         $__t=app::get('desktop')->_('读取图片库，请稍侯...');
         $__y=app::get('desktop')->_('设置图片属性');
         $__u=app::get('desktop')->_('位置：');
         $__i=app::get('desktop')->_('默认');
         $__o=app::get('desktop')->_('上对齐');
         $__P=app::get('desktop')->_('底对齐');
         $__aa=app::get('desktop')->_('文字环绕');
         $__ab=app::get('desktop')->_('左对齐');
         $__ac=app::get('desktop')->_('右对齐');
         $__ad=app::get('desktop')->_('缩放：');
         $__af=app::get('desktop')->_('最宽:');
         $__ag=app::get('desktop')->_('最高:');
         $__ah=app::get('desktop')->_('(等比例缩放图片的设置.)');
         $__aj=app::get('desktop')->_('连接：');
         $__ak=app::get('desktop')->_('点击图片链接到原图.');
         $__al=app::get('desktop')->_('点击图片链接到指定地址:');
         $__aq=app::get('desktop')->_('确定');
         $__aw=app::get('desktop')->_('取消');
         $___b=app::get('desktop')->_('正在上传...');
         $___b1=app::get('desktop')->_('图片上传失败!');
         $___b2=app::get('desktop')->_('正在校验这张图片...');
         $___b3=app::get('desktop')->_('图片来源不正确!');
         $___b4=app::get('desktop')->_('没有要使用的图片源.');

        $html=<<<EOF
            <div class="tableform mainHead">
                <h4>$__d</h4>
                <div id="imgFrom">
                    <input type="radio" name="from"  id="imgFromLocal" checked><label for="imgFromLocal">$__e</label>&nbsp;&nbsp;&nbsp;&nbsp;
                    <input type="radio" name="from"  id="imgFromNet"><label for="imgFromNet">$__f</label>&nbsp;&nbsp;&nbsp;&nbsp;
                    <input type="radio" name="from" id="imgFromLib"><label for="imgFromLib">$__g</label>
                </div>
            </div>
            <iframe id="img-uploader" style="display:none;width:100%" src='about.html' name="img-uploader"></iframe>
            <form id="imgFromSomeWhere" target="img-uploader" action="index.php?ctl=editor&act=uploader" method="post" enctype="multipart/form-data">
                <div id="imgViewLocal" class="tableform"><h4>$__h</h4>
                    <input name="file" type="file" />
                    <span onclick="$(this).getNext().toggleDisplay()" class="sysiconBtn addorder">$__j</span>
                    <div class="division" style='display:none'>
                    <table width="100%" border="0" cellspacing="0" cellpadding="0">
                      <tr><th>$__k</th>
                          <td><input type="text" id="tagTextarea" name="tags"/></td>
                      </tr>
                      <tr><th>$__l</th>
                         <td><div id="tagLibs" class="tagEditor" style='height:50px'>

                         </div></td>
                      </tr>
                    </table></div>
                    <div class='upload_view note' >$__z.</div>
                </div>
            </form>

            <div style='display:none' id="imgViewNet" class="tableform">
                <h4>$__x</h4>
                <input type="text" style="width:80%" id="imgViewUrl" value="http://" />
                <div id="imgViewUrlPreivew" class="note">$__v<br/>.$__n</div>
            </div>
            <div style="display:none" id="imgViewLib" class="tableform">
                <h4>$__m</h4>
                <div class="division">
                    <span>$__q</span>
                    <select style="width:200px;" onchange="showResLib(encodeURIComponent(this.value),0)">
                    <option value="0">$__W</option>

                    </select>
                </div>
                <h4>$__r</h4>
                <div class="division" id="imgViewLibBox">$__t</div>
            </div>
            <div class="tableform">
                <div style="clear:both;{if !$show_picset}display:none;{/if}" id='imgOptionsHide'>
                      <FIELDSET >
                      <LEGEND>$__y</LEGEND>
                       <strong>$__u</strong>
                      <select name='align'>
                      <option value=''>$__i</option>
                      <option value='top'>$__o</option>
                      <option value='bottom'>$__p</option>
                      <option value='middle'>$__aa</option>
                      <option value='left'>$__ab</option>
                      <option value='right'>$__ac</option>
                      </select><br/><br/>
                      <strong>$__ad</strong>$__af<input name='width' value='' style='width:40px'/>&nbsp;$__ag<input name='height' value='' style='width:40px'/><em>$__ah</em><br/><br/>
                      <strong>$__aj</strong>
                      <input name='linkimg'  type='radio' />$__ak
                      <input name='linkimg'  type='radio' class='mdf'/>$__al<input type='text' value='http://' name='linkimgurl' class='inputstyle' onfocus='$(this).getPrevious("input").checked=true'/>
                       </FIELDSET>
                </div>
            </div>

            <div class="mainFoot"><div class="table-action">
            <button type="button" class="btn" id="mce_dlg_ok"><span><span>$__aq</span></span></button>
            <button type="button" class="btn" isclosedialogbtn="true"><span><span>$__aw</span></span></button>
            </div></div>
EOF;
        $html.=<<<EOF
        <script>
            (function(){
                  var imgFormDialog=$('imgFrom').getParent('.dialog');
                  var submitcallback=imgFormDialog.retrieve('callback');
                  var imgSERI,linkIMG;
                  var insertImage=function(v,i){
                     var i=i||v;
                     if(submitcallback)return submitcallback(v,imageHtml(v,i),i);
                     window.curEditor.exec.call(window.curEditor,'insertHTML',imageHtml(v,i));
                     var img=window.curEditor.inc.win.$(imgSERI);
                     img.src=img.get('turl');
                     img.removeProperties('turl','id');
                     if(linkIMG){
                         var  a=window.curEditor.inc.win.$(imgSERI+"lnk");
                         a.href=a.get('turl');
                         a.removeProperties('turl','id');
                     }
                  }
                  var imageHtml=function(url,storager){
                     var img=new Element('img',{src:url});
                     var h=\$E('input[name=height]','imgOptionsHide').value.toInt(),
                         w=\$E('input[name=width]','imgOptionsHide').value.toInt(),
                        align=\$E('select','imgOptionsHide').getValue();
                        linkIMG=$$('#imgOptionsHide input[name=linkimg]').filter(function(ipt){return !!ipt.checked})[0];
                        if(h||w){
                          img.zoomImg(w,h);
                        }
                     if(align&&align.trim()!==""){img.set('align',align)}
                     if(SHOPBASE&&url.contains(SHOPBASE)){
                         url=url.replace(SHOPBASE,'');
                     }
                     img.set('src',url);
                     var d;
                     if(!submitcallback){
                        img.set('id',imgSERI='img'+Native.UID++).set('turl',url);
                     }
                     if(linkIMG){
                        var imglink = linkIMG.hasClass('mdf')?linkIMG.getNext('input').value.trim():url;
                        var a=new Element('a',{
                           href:imglink,
                           target:'_blank'
                        });
                     if(window.gecko&&!submitcallback){a.set('id',imgSERI+"lnk").set('turl',imglink);}
                        d=new Element('div').adopt(a.adopt(img));
                     }else{
                      d=new Element('div').adopt(img);
                     }
                     return d.get('html');
                  }
                  var imgInject=function(Imageurl){
                    return window.curEditor.exec.bind(window.curEditor)('insertimage',Imageurl);
                  }
                  $('imgFromLocal').addEvent('click',function(){
                    $('imgViewLocal').setStyle('display','');
                    $('imgViewNet').setStyle('display','none');
                    $('imgViewLib').setStyle('display','none');
                  });
                  $('imgFromNet').addEvent('click',function(){
                    $('imgViewNet').setStyle('display','');
                    $('imgViewLocal').setStyle('display','none');
                    $('imgViewLib').setStyle('display','none');
                  });
                  $('imgFromLib').addEvent('click',function(){
                    if(!this.initLib){
                      this.initLib=true;
                      showResLib();
                    }
                    $('imgViewNet').setStyle('display','none');
                    $('imgViewLocal').setStyle('display','none');
                    $('imgViewLib').setStyle('display','');
                  });
                    var upForm=$('imgFromSomeWhere');
                    var upView=\$E('.upload_view',upForm);
                    upForm.addEvent('submit',function(){
                        upView.setHTML('<font color="red">$___b</font>');
                    });
                      window.uploadCallback = function(value){
                          if(!value)return upView.empty();
                          if(!value.url)return upView.empty().setHTML("<div class='notice'>"+value+"</div>");
                          new Asset.image(value.url,{onload:function(){
                              insertImage(value.url,value.ident);
                          },onerror:function(){
                              upView.setText($___b1)
                          }});
                     };
                  $('imgViewUrl').addEvent('change',function(){
                      var ivup=$('imgViewUrlPreivew');
                      var imgsrc=$('imgViewUrl').value;
                      ivup.setText($___b2);
                     new Asset.image(imgsrc,{onload:function(img){
                         if(ivup)
                         ivup.empty().adopt(img.zoomImg(200,200));
                      },onerror:function(){
                          ivup.setText($___b3);
                       }});
                  });

                  $('mce_dlg_ok').addEvent('click',function(e){
                    e.stop();
                    if($('imgFromLocal').checked){
                      $('imgFromSomeWhere').submit();
                    }else if($('imgFromNet').checked){
                      if(img=$('imgViewUrlPreivew').getElement('img')){
                         insertImage(img.src,img.get('ident'));
                      }
                    }else{
                       if(!\$E('.image-item-selected','imgViewLibBox'))return alert($___b4);
                       var img=\$E('.image-item-selected','imgViewLibBox').getElement('img');
                       if(!img)return alert(a$___b4);
                       insertImage(img.src,img.get('ident'));
                    }
                  });
            })();
            function showResLib(tag_id,page_id){
              tag_id = tag_id?tag_id:0;
              page_id = page_id?page_id:1;
              W.page('index.php?ctl=editor&act=gallery&p[0]={0}&p[1]={1}'.format(tag_id,page_id),{method:'get',update:$('imgViewLibBox')});
            }
            </script>
EOF;
        echo $html;
    }


    function find($type,$keywords){
        if(!$keywords){
            echo app::get('desktop')->_('请输入关键字。');
            return;
        }
        if($type=='goods'){
            $mod = app::get('b2c')->model('goods');
            foreach($mod->getList('goods_id,name',array('name'=>$keywords)) as $k=>$r){
                $list[] = array(
                    'url'=>app::get('site')->router()->gen_url(array('app'=>'b2c','ctl'=>'site_product','full'=>1,'act'=>'index','arg'=>$r['goods_id']))
                    ,'label'=>$r['name']);
            }
            $this->pagedata['list'] = $list;
        }elseif($type=='article'){
            $mod = app::get('content')->model('article_indexs');
            foreach($mod->getList('article_id,title',array('title'=>$keywords)) as $k=>$r){
                var_dump($r);
                $list[] = array(
                    'url'=>app::get('site')->router()->gen_url(array('app'=>'content','ctl'=>'site_article','full'=>1,'act'=>'index','arg'=>$r['article_id'] ) ),
                    'label'=>$r['title']);
            }
            $this->pagedata['list'] = $list;
        }
        if(count($list)>0){
            $this->pagedata['type'] = $type;
            $this->display('editor/dlg_result.html');
        }else{
            echo app::get('desktop')->_('没有符合条件"')."<b>".$keywords."<b>".app::get('desktop')->_('"的记录。');
        }
    }

    function lista(){
        $_filter = unserialize($_GET['filter']);
        foreach($_POST as $k=>$v){
            if( ( $k[0]!='_' && $v ) || $v === false ){
                if($_POST['_'.$k.'_search']){
                    $filter['_'.$k.'_search']=$_POST['_'.$k.'_search'];
                }
                $filter[$k]=$v;
            }
        }
        $filter = array_merge((array)$filter,(array)$_filter);
        $this->_select_obj($filter);
        $this->display('editor/object_items.html');
    }
    function object_rows(){
        if($_POST['data']){
            if($_POST['app_id'])
                $app = app::get($_POST['app_id']);
            else
                $app = $this->app;
            $obj = $app->model($_POST['object']);
            $schema = $obj->get_schema();
            $textColumn = $_POST['textcol']?$_POST['textcol']:$schema['textColumn'];
            $textColumn = explode(',',$textColumn);
            $_textcol = $textColumn;
            $textColumn = $textColumn[0];
            
            $keycol = $_POST['key']?$_POST['key']:$schema['idColumn'];
			
			
			//统一做掉了。
            if( $_POST['data'][0]==='_ALL_' ) {
                $filter = !empty($obj->__all_filter) ? $obj->__all_filter : array();
                if(!empty($_POST['filter']['advance'])) {
                    $filter = array();
                    $filterStrArr = explode(',', $_POST['filter']['advance']);
                    foreach ($filterStrArr as $fsa) {
                        $tmpArr = explode('=', $fsa);
                        $filter[$tmpArr[0]] = $tmpArr[1];
                    }
                }
                $arr_list = $obj->getList( $keycol,$filter );
                $_POST['data'] = array_map('current',$arr_list);
            }
            $items = $obj->getList($keycol . ',' . implode(',', $_textcol), array($keycol=>$_POST['data']));
            $name = $items[0][$textColumn];
            if($_POST['type']=='radio'){
                if(strpos($textColumn,'@')!==false){
                    list($field,$table,$app_) = explode('@',$textColumn);
                    if($app_){
                        $app = app::get($app_);
                    }
                    $mdl = $app->model($table);
                    $schema = $mdl->get_schema();
                    $row = $mdl->getList('*',array($schema['idColumn']=>$items[0][$keycol]));
                    $name = $row[0][$field];

                }
                echo json_encode(array('id'=>$items[0][$keycol],'name'=>$name));
                exit;
            }

            $this->pagedata['_input'] = array('items'=>$items,
                                                'idcol' => $schema['idColumn'],
                                                'keycol' => $keycol,
                                                'textcol' => $textColumn,
                                                '_textcol' => $_textcol,
                                                'name'=>$_POST['name']
                                                );
            $this->pagedata['_input']['view_app'] = 'desktop';
            $this->pagedata['_input']['view'] = $_POST['view'];
            if($_POST['view_app']){ 
                $this->pagedata['_input']['view_app'] =  $_POST['view_app'];
            }

            if(strpos($_POST['view'],':')!==false){
                list($view_app,$view) = explode(':',$_POST['view']);
                $this->pagedata['_input']['view_app'] = $view_app;
                $this->pagedata['_input']['view'] = $view;

            }

            $this->display('finder/input-row.html');
        }
    }
    function finder_common(){
        $params = array(
                        'title'=>app::get('desktop')->_('列表'),
                        'use_buildin_new_dialog' => false,
                        'use_buildin_set_tag'=>false,
                        'use_buildin_recycle'=>false,
                        'use_buildin_export'=>false,
                        'use_buildin_import'=>false,
                        'use_buildin_filter'=>true,
                        'use_buildin_setcol'=>true,
                        'use_buildin_refresh'=>true,
                        'finder_aliasname'=>'finder_common',
                        'alertpage_finder'=>true,
                        'use_buildin_tagedit'=>false,
                    );
        if(substr($_GET['name'],0,7) == 'adjunct') $params['orderBy'] = 'goods_id desc';
        $this->finder($_GET['app_id'].'_mdl_'.$_GET['object'],$params);
    }
    function selectobj(){
        $filter = $_GET['filter'];
        $_GET['_finder']['finder_id'] = $_GET['obj_id'] = substr(md5($_GET['object']),0,6);
        $this->_select_obj($filter);
        if($this->pagedata['data']){
            $this->pagedata['filter'] = true;
        }
        $render = kernel::single('desktop_finder_builder_filter_render');
        ob_start();
        $filterbody = $render->main($_GET['object'],app::get($_GET['app_id']));
        $filterbody = ob_get_clean();
        $this->pagedata['filterbody'] = $filterbody;
        $this->display('editor/object_selector.html');
    }
    function _select_obj($filter){
        if(strpos($_GET['object'],'@')!==false){
            $tmp = explode('@',$_GET['object']);
            $app = app::get($tmp[1]);
            $object = $tmp[0];
        }else{
            $object = $_GET['object'];
            $app = app::get($_GET['app_id']);
        }

        if($_GET['cols']){
            list($textColumn) = explode(',',$_GET['cols']);
            $select_cols = ','.$_GET['cols'];
        }

        $o = $app->model($object);
        $limit = 10;
        if(!$_GET['page']){
            $_GET['page'] = 1;
        }
        $start = ($_GET['page']-1) * $limit;
        $this->dbschema = $o->get_schema();
        $this->pagedata['data'] = $this->dbschema['columns'];
        if($_COOKIE['LOCALGOODS']){
            $this->pagedata['items'] = $o->getBindList($start,$limit,$count,$filter);
        }else{
            $this->pagedata['items'] = $o->getList($this->dbschema['idColumn'].','.$this->dbschema['textColumn'].$select_cols,$filter,$start,$limit);
            $count = $o->count($filter);
        }
        $this->pagedata['textColumn'] = $textColumn?$textColumn:$this->dbschema['textColumn'];
        $this->pagedata['idColumn'] = $this->dbschema['idColumn'];
        $this->pagedata['ipt_type'] = $_GET['select']=='checkbox'?'checkbox':'radio';

        $this->pagedata['pager'] = array(
            'current'=> $_GET['page'],
            'total'=> ceil($count/$limit),



            'link'=> 'javascript:update_'.$_GET['obj_id'].'(_PPP_)',
            'token'=> '_PPP_'
        );

        $this->_filter();
    }
    function _filter(){
        if(strpos($_GET['object'],'@')!==false){
            $tmp = explode('@',$_GET['object']);
            $app = app::get($tmp[1]);
            $object = $tmp[0];
        }else{
            $object = $_GET['object'];
            $app = app::get($_GET['app_id']);
        }
        $obj = $app->model($object);
        $from = 'from';
        $this->dbschema = $obj->get_schema();
        $data = $this->dbschema['columns'];
        $filter_items = array();
        include(APP_DIR."/base/datatypes.php");
        foreach($data as $k=>$v){
            if($v['filtertype']){
                $data[$k]['searchparams'] = $datatypes[$v['filtertype']]['searchparams'];
                if($v['filtertype']=='normal'){
                    $data[$k]['searchparams'] = $datatypes['email']['searchparams'];
                }
                if(is_array($v['type'])){
                    $data[$k]['options'] = $v['type'];
                    $data[$k]['type'] = 'select';
                }
                if($v['filtertype']=='custom'){
                    $data[$k]['searchparams'] =    $v['filtercustom'];
                }
                $filter_items[$k] = $data[$k];
            }

        }
        $this->pagedata['data']  = $filter_items;
    }

}
