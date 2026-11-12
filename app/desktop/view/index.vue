<!--
Copyright 2012-2026 ShopeX (https://www.shopex.cn)

  Licensed under the Apache License, Version 2.0 (the "License");
  you may not use this file except in compliance with the License.
  You may obtain a copy of the License at

      http://www.apache.org/licenses/LICENSE-2.0

  Unless required by applicable law or agreed to in writing, software
  distributed under the License is distributed on an "AS IS" BASIS,
  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
  See the License for the specific language governing permissions and
  limitations under the License.
-->

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title><{$title}> - <{$title_desc}></title>
  
    <link rel="shortcut icon" href="../favicon.gif" type="image/gif" />
    <{if defined('DEBUG_CSS') && DEBUG_CSS}>
    <link rel="stylesheet" href="<{$env.app.res_url}>/css/framework.css" type="text/css" media="screen, projection"/>
    <link rel="stylesheet" href="<{$env.app.res_url}>/css/default/style.css" type="text/css" media="screen, projection"/>
    <link href="<{$env.app.res_url}>/perfect/main.css" rel="stylesheet">
    <{else}>
    <link rel="stylesheet" href="<{$env.app.res_url}>/css/default/style_min.css" type="text/css" media="screen, projection"/>
    <link href="<{$env.app.res_url}>/perfect/main.css" rel="stylesheet">
    <{/if}>

    <{foreach from=$theme_css item="desktop_theme_css_file"}>
    <link rel="stylesheet" href="<{$desktop_theme_css_file}>" type="text/css" media="screen, projection"/>
    <{/foreach}>
    <{lang_css src="lang.css" app="desktop"}>
    <{desktop_header}>
    <{assign var='desktop_sideleft' value="desktop_{$uname}_sideleft"}>
    <link rel="stylesheet" href="<{$env.app.res_url}>/css/default/tail.select-light.min.css" type="text/css" media="screen, projection"/>
    <script>
      startTime = (new Date).getTime();

      currentWorkground = null;

      /*商店事件、状态 推送包*/
      shopeEvents = {};
      SESS_ID=null;
      SHOPBASE='<{$shop_base}>';
      SHOPADMINDIR='<{$shopadmin_dir|escape:"html"}>';
      DESKTOPRESURL='<{$env.app.res_url}>';
      DESKTOPRESFULLURL='<{$env.app.res_full_url}>';
      CURRENTUSER =  '<{$uname}>';
      BREADCRUMBS ='0:0';
      window.loadedPart = [1,0,(new Date).getTime()];
    </script>
    <style>
        .side-content .side-bx .side-bx-title h3 {
            cursor: pointer !important;
            position: relative;
            padding-left: 10px !important;
            font-size: 14px !important;
        }
        .side-content .side-bx .side-bx-title h3::after {
            position: absolute;
            top: 16px;
            left: -7px;
            width: 0;
            height: 0;
            border-left: 4px solid transparent;
            border-right: 4px solid transparent;
            border-bottom: 5px solid #909399;
            border-top-width: 0;
            display: inline-block;
            content: '';
            margin-left: 5px;
        }
        .side-content .side-bx.active .side-bx-title h3::after {
            position: absolute;
            left: -7px;
            top: 17px;
            width: 0;
            height: 0;
            border-left: 4px solid transparent;
            border-right: 4px solid transparent;
            border-top: 5px solid #909399;
            border-bottom-width: 0;
            display: inline-block;
            content: '';
            margin-left: 5px;
        }
        .side-content .side-bx .side-bx-bd {
            height: auto;
            overflow-y: hidden;
        }
        .side-content .side-bx.collapse .side-bx-bd {
            height: 0;
        }
        .side-content .side-bx .side-bx-bd ul li a {
            padding-left: 30px !important;
        }
        .login-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            padding: 10px 0;
            margin: 0 16px;
            font-size: 12px;
            color: #999;
            background: #fff;
            border-top: 1px solid #eee;
            z-index: 1000;
        }
        .login-footer-text {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .login-footer-text img {
            height: 16px;
            vertical-align: middle;
        }
        /* 给所有页面的按钮区域添加底部间距，避免被版权页遮挡 */
        .form-actions,
        .noprint.table-action.form-layout-block,
        .noprint.table-action {
            margin-bottom: 39px !important;
            margin-left: 16px !important;
            margin-right: 16px !important;
            padding-bottom: 10px !important;
            box-sizing: border-box !important;
            max-width: calc(100% - 32px) !important;
        }
    </style>
</head>
<body>
<noscript>
    <div class='noscript error'>
        <{t}>您好，要正常运行，浏览器必须支持Javascript<{/t}>
    </div>
</noscript>

<{if strtolower($env.CONST.WITH_HTTPS_SUPPORT) == 'on' }>
    <script type="text/javascript" src="https://g.alicdn.com/sj/securesdk/0.0.3/securesdk_v2.js?spm=a1z6x.7629140.0.0.5bb754beYk0IDd&file=securesdk_v2.js" id="J_secure_sdk_v2" data-appkey="<{$env.const.TOP_APP_KEY}>"></script>
<{else}>
<script type="text/javascript" src="http://g.tbcdn.cn/sj/securesdk/0.0.3/securesdk_v2.js" id="J_secure_sdk_v2" data-appkey="<{$env.const.TOP_APP_KEY}>"></script>
<{/if}>

<div style="display:none;height:0;overflow:hidden;">
    <iframe src='<{$env.app.res_url}>/tpl.html' id='tplframe' class='hide'></iframe>
    <iframe src='<{$env.app.res_url}>/about.html' name='download' id='downloadframe' class='hide'></iframe>
    <iframe src='<{$env.app.res_url}>/about.html' name='upload' id='uploadframe' class='hide'></iframe>
    <iframe src='<{$env.app.res_url}>/pdd_get_pati.html'></iframe>
</div>

<div class="wrapper" id='body' style='visibility:hidden'>
    <div class="msgbox" id="messagebox"></div>
</div>

<div class="container clearfix" id="container" style="min-height: 300px">
    <div class='workground' id='workground'>
        <div id="content-container" class="content-container" style="height: 100%">
            <div class='content-head' style="font-size:0;height:0;"></div>
            <div class='content-main' id='main'></div>
            <div class='content-foot' style="font-size:0;height:0;"></div>
        </div>
    </div>

    <div class="side-r hide" id="side-r">
        <div class="side-r-modal"></div>
        <div class="side-r-contanier">
            <div class="side-r-resize" id="side-r-resize">&nbsp;</div>
            <div class="side-r-top clearfix">
                <b class="side-r-title flt f-14"></b>
                <span class="frt side-r-close pointer"></span>
            </div>
            <div class="side-r-head" style="border-bottom:1px #999 solid;padding:2px 0 2px 0;font-size:0;height:0;">&nbsp;
            </div>
            <div id="side-r-content" class="side-r-content" conatainer="true" style="overflow:auto">
            </div>
            <div class="side-r-foot" style="font-size:0;height:0;"></div>
        </div>
    </div>
</div>

</div>

<{lang_script src="lang.js"}>
<{script src="loader.js" }>

<{if defined('DEBUG_JS') && DEBUG_JS}>
<{script src="moo.js" }>
<{script src="moomore.js" }>
<{script src="mooadapter.js" }>
<{script src="jstools.js" }>
<{script src="coms/wpage.js" }>
<{script src="coms/messagebox.js" }>
<{script src="coms/dialog.js" }>
<{script src="coms/validate.js" }>
<{script src="coms/dropmenu.js" }>
<{script src="coms/finder.js" }>
<{script src="coms/csrf-request.js" }>
<{script src="coms/iframe-activity.js" }>
<{script src="tail.select-full.js" }>
<{script src="image-magnifier.js" }>

<{else}>
<script src="<{$env.app.res_url}>/js_mini/moo_min.js" ></script>
<script src="<{$env.app.res_url}>/js/coms/csrf-request.js"></script>
<script src="<{$env.app.res_url}>/js/coms/iframe-activity.js"></script>
<script src="<{$env.app.res_url}>/js_mini/tools_min.js"></script>
<script src="<{$env.app.res_url}>/js_mini/coms/finder.js" async="true"></script>

<script src="<{$env.app.res_url}>/js_mini/tail.select-full.min.js" async="true"></script>
<script src="<{$env.app.res_url}>/js_mini/image-magnifier.js"></script>
<{/if}>



<script>

(function(){
  <{if defined('DEBUG_JS') && DEBUG_JS}>
  var js_path='js';
  <{else}>
  var js_path='js_mini';
  <{/if}>

	var	hs = {
		'cmdrunner':{path:'<{$env.app.res_url}>/'+js_path+'/coms/cmdrunner.js?v=20211125',type:'js'},
		'historyMan':{path:'<{$env.app.res_url}>/'+js_path+'/coms/hst.js',type:'js'},
		'autocompleter':{path:'<{$env.app.res_url}>/'+js_path+'/coms/autocompleter.js',type:'js'},
		'uploader':{path:'<{$env.app.res_url}>/'+js_path+'/coms/uploader.js',type:'js'},
		'modedialog':{path:'<{$env.app.res_url}>/'+js_path+'/coms/modedialog.js',type:'js'},
		'pager':{path:'<{$env.app.res_url}>/'+js_path+'/coms/pager.js',type:'js'},
		'picker':{path:'<{$env.app.res_url}>/'+js_path+'/coms/datapicker.js',type:'js',requires:['colorpicker']},
		'editor':{path:'<{$env.app.res_url}>/'+js_path+'/coms/editor.js',type:'js',requires:['editor_style_1']},
    'FX_Slide':{path:'<{$env.app.res_url}>/js/coms/Fx.Slide.js',type:'js'},
    'security':{path:'<{$env.app.res_url}>/js/coms/security.js?v=20220826',type:'js'},
    'layui':{path:'<{$env.app.res_url}>/'+js_path+'/layui.js',type:'js'},
    'address':{path:'<{$env.app.res_url}>/'+js_path+'/cascader.js',type:'js'},
    'kissy':{path:'https://assets.alicdn.com/s/kissy/1.2.0/kissy-min.js',type:'js'},
    'aliww':{path:'https://market.m.taobao.com/app/aliww/bc-ww/webww',type:'js'},

	};

  <{if defined('DEBUG_JS') && DEBUG_JS}>
    hs['colorpicker'] = {path:'<{$env.app.res_url}>/'+js_path+'/coms/colorpicker.js',type:'js'};
    hs['editor_style_1'] = {path:'<{$env.app.res_url}>/'+js_path+'/coms/editor_style_1.js',type:'js',requires:['picker']};
  <{/if}>


  Object.each(hs,function(v,k){Ex_Loader.add(k,v);});

  if(window.ie6)Ex_Loader('<{$env.app.res_url}>/js/fixie6.js');



})();

</script>

<script>

  // 创建虚拟的 Elements 集合对象
  var dummyElements = [];
  dummyElements.removeClass = function(){return this;};
  dummyElements.addClass = function(){return this;};
  dummyElements.each = function(){return this;};
  dummyElements.forEach = function(){return this;};

  var LAYOUT = {
    head: {
      getElement: function(){return null;},
      getElements: function(){return dummyElements;}
    },
    container: $('container'),
    side: {
      getSize: function(){return {x:0};},
      offsetWidth: 0,
      hasClass: function(){return false;},
      toggleClass: function(){},
      setStyle: function(){},
      getElement: function(){return null;},
      getElements: function(){return dummyElements;}
    },
    workground: $('workground'),
    content_main: $('main'),
    content_head: $E('#workground .content-head'),
    content_foot: $E('#workground .content-foot'),
    side_r: $('side-r'),
    side_r_content:$('side-r-content')
  };

  /*init  script

  this Function will run at 'loadedPart[1]==loadedPart[0]'
  */
  var initDesktop = function() {
      window.resizeLayout = fixLayout = function() {
        var _NUM = function(num){
          num =  isNaN(num)?0:num;
          if(num<0)num=0;
          return num;
        };
        var mw=0,mh=0;
        var winSize = window.getSize();

        var containerHeight = winSize.y;

        LAYOUT.container.setStyle('height',_NUM(containerHeight-LAYOUT.container.getPatch().y));
        LAYOUT.container.setStyle('width',_NUM(winSize.x.limit(960, 4000)));

        // if(!LAYOUT.side.hasClass('hide')){
        // 	LAYOUT.side.setStyle('width',_NUM( (winSize.x * 0.12).limit(0,winSize.x)));
        // }
        LAYOUT.workground.setStyle('width',_NUM(
          (winSize.x - LAYOUT.workground.getPatch().x))
        ).setStyle('left',0);

        setTimeout(function () {
          if (!LAYOUT.side_r.hasClass('new')) {
            LAYOUT.workground.setStyle('width',_NUM(
              (winSize.x - LAYOUT.workground.getPatch().x)-
              LAYOUT.side_r.getSize().x)
            ).setStyle('left',0);
          }
        }, 10)

        LAYOUT.content_main.setStyles({'height':
            (mh=_NUM(containerHeight -
              LAYOUT.content_head.getSize().y  -
              LAYOUT.content_foot.getSize().y  -
              LAYOUT.workground.getPatch().y)),
          'width':(mw=_NUM(LAYOUT.workground.getSize().x-LAYOUT.workground.getPatch().x))
        }).fireEvent('resizelayout',[{x:mw,y:mh}]);
      };

      resizeLayout();

      Side_R = new Class({
        Implements: [Options, Events],
        options: {
          onShow: $empty,
          onHide: $empty,
          onReady: $empty,
          isClear:true,
          width:false
        },
        initialize: function(url, opts) {
          this.setOptions(opts);
          this.panel = $('side-r');
          this.container = $('side-r-content');
          var trigger = this.options.trigger;
          this.panel.removeClass('new')
          if(trigger&&!trigger.retrieve('events',{})['dispose']) {

            trigger.addEvent('dispose',function(){
              $('side-r').addClass('hide');
              $('side-r-content').empty();
              $('side-r').removeProperty('widthset').store('url','');
            });
          }


          if(this.panel.retrieve('url','') == url)return;

          if (url) {
            this.showSide(url);
          } else {
            throw Exception('NO TARGET URL');
            return;
          }

          var btn_close = this.panel.getElement('.side-r-close');
          var btn_modal = this.panel.getElement('.side-r-modal');
          var _title = this.panel.getElement('.side-r-title');

          _title.set('html',this.options.title||"");

          if(btn_close){
            btn_close.removeEvents('click').addEvent('click', this.hideSide.bind(this));
          }
          if(btn_modal){
            btn_modal.removeEvents('click').addEvent('click', this.hideSide.bind(this));
          }

        },
        showSide: function(url) {
          this.cleanContainer();

          var _this = this;
          if(_this.options.width&&!_this.panel.get('widthset')){
            _this.panel.set({'widthset':_this.options.width,styles:{width:_this.options.width}});
          }
          _this.panel.removeClass('hide');
          _this.fireEvent('show');
          window.resizeLayout();
          if(this.cache)return;
          W.page(url,{
            update:_this.container,
            render:false,
            onRequest: function() {
              _this.panel.addClass('loading');
            },
            onComplete: function() {
              _this.panel.removeClass('loading');
              _this.fireEvent('ready', $splat(arguments));
              _this.panel.store('url',url);
              _this.container.style.height = (_this.container.style.height.toInt()-_this.container.getPrevious().getSize().y-_this.container.getNext().getSize().y)+'px';

            }
          });
        },
        hideSide: function() {
          var side_r_contanier = this.panel.getElement('.side-r-contanier')
          if (side_r_contanier.hasClass('active')) {
            side_r_contanier.removeClass('active')
            var _this = this
            setTimeout(function() {
              _this.panel.addClass('hide');
              window.resizeLayout();
              _this.cleanContainer();
              _this.fireEvent('hide');
            }, 500)
          } else {
            this.panel.addClass('hide');
            window.resizeLayout();
            this.cleanContainer();
            this.fireEvent('hide');
          }
        },
        cleanContainer: function() {
          this.panel.store('url','');
          if(this.options.isClear)this.container.empty();
        }
      });


      new Drag($('side-r-resize'), {
        modifiers: {
          'x': 'left',
          'y':false
        },
        onBefore:function(el){
          el.addClass('side-r-resize-ing');
        },
        onDrag: function(el) {

          el.addClass('side-r-resize-ing');

        },
        onComplete: function(el) {
          el.removeClass('side-r-resize-ing');

          var left = el.getStyle('left');
          left = left.toInt();

          if (LAYOUT.side_r.hasClass('new')) {
            let con_wid = LAYOUT.side_r.getElement('.side-r-contanier')
            var _w = con_wid.getSize().x-(left-(-5));
            con_wid.style.width = _w+'px';
            con_wid.style.right = '-'+_w+'px';
            con_wid.set('widthset',_w);
            // LAYOUT.side_r.getElement('.side-r-foot').style.width = _w+'px';
            // LAYOUT.side_r.getElement('.side-r-foot').style.right = '-'+_w+'px';
          } else {
            var _w =  LAYOUT.side_r.style.width.toInt()-(left-(-5));
            LAYOUT.side_r.style.width = _w+'px';
            LAYOUT.side_r.set('widthset',_w);
          }

          el.style.left = '-5px';
          resizeLayout();
        }
      });


      /*MODAL PANEL*/
      MODALPANEL = {
        createModalPanel:function(){
          var mp = new Element('div',{'id':'MODALPANEL'});
          var mpStyles = {
            'position': 'absolute',
            'background': '#000',
            'width': '100%',
            'display':'none',
            'height': window.getScrollSize().y,
            'top': 0,
            'left': 0,
            'zIndex': 65500,
            'opacity': .4
          };
          this.element = mp.setStyles(mpStyles).inject(document.body);
          return this.element;
        },
        show:function(){
          var panel = this.element = this.element||this.createModalPanel();
          panel.setStyles({
            'width': '100%',
            'height': window.getScrollSize().y
          }).show();
        },hide:function(){
          if(this.element)this.element.hide();
        }
      };


      var windowResizeTimer = 0;
      window.addEvent('resize',function() {
        $clear(windowResizeTimer);
        windowResizeTimer = window.resizeLayout.delay(200);

        if(MODALPANEL.element&&MODALPANEL.element.style.display!='none'){
          MODALPANEL.element.setStyles({
            'height':window.getScrollSize().y
          });
        }

      });


      fixSideLeft = function(act){
        window.resizeLayout();
      };


      <{if count($fav_menus)>0}>
    /*顶部菜单处理*/
    void function(){
      var _timer = 0;
      var _mouse = true;
      var _absoluteFix =  function(f,t){
        var pos =  {};

        var fcis = f.getCoordinates();
        var tsize = t.getSize();
        var wsize = window.getSize();
        if((wsize.x-fcis.right)<tsize.x){
          $extend(pos,{left:Math.abs(fcis.right-tsize.x),top:fcis.bottom});
        }else{
          $extend(pos,{left:fcis.left,top:fcis.bottom});
        }

        return pos;
      }
      var getCurWgMenu = function(){return null;}
      var workMenus = $$('.head-nav dl');
      workMenus.each(function(item){
        var aEl = item.getElement('dt a')
        if (aEl.hasClass('current')) {
          item.addClass('active')
        }
        let route = aEl.href.split('?')[1]
        aEl.addEvent('click',function(){
          workMenus.each(function(el, idx) {
            let index = workMenus.indexOf(item)
            if (index == idx) {
              var secMenu = item.getElements('.sec-menu')
              if (secMenu) {
                secMenu.each(function(menu) {
                  var url = menu.href.split('?')[1]
                  if (url == route) {
                    menu.addClass('active')
                  } else {
                    menu.removeClass('active')
                  }
                })
              }
              el.addClass('active');
            } else {
              el.removeClass('active');
            }
          })
        });

        var secMenu = item.getElements('.sec-menu')
        if (secMenu) {
          secMenu.each(function (secItem) {
            secItem.addEvent('click',function(){
              secMenu.each(function (item1, index1) {
                if (index == secMenu.indexOf(secItem)) {
                  item1.addClass('active')
                } else {
                  item1.removeClass('active')
                }
              })
            });
          })
        }
      });

    }();

  <{/if}>


  /*每 30秒 同步一下后台 的事项*/
  TaskRemote = {
    url: "index.php?ctl=default&act=status",
    timer: 30000,
    delay: 0,
    stop:function(){
      $clear(this.delay);
    },
    start:function(){
      this.delay = this.doit.delay(this.timer, this);
    },
    init: function() {
      var _this = this;
      this.request = (new Request.HTML({
        url:this.url,
        onSuccess: function(nodes, elements, responsetext, javascript) {
          $clear(_this.delay);
          _this.delay = _this.doit.delay(_this.timer, _this);
        },
        onCancel: function() {
          _this.delay = _this.doit.delay(_this.timer, _this);
        },
        onFailure: function() {
          _this.delay = _this.doit.delay(_this.timer * 2, _this);
        }
      }));
      return this;
    },
    doit: function(_chain) {

      _chain =$type(_chain)=='function'?_chain : $empty;

      return this.request.post({
        events: shopeEvents
      }).chain(_chain);
    }
  };
  TaskRemote.init().start();


  EventsRemote = new Request({url:'index.php?ctl=default&act=desktop_events'});

  var keyObj = <{$keyboard_setting_json}>;
  $(window.gecko?document.documentElement:document.body).addEvent('keydown',function(e){
    if(e.target==this){
      if(e.code==32)e.stop();
      Hotkey.init(e,keyObj);
    }
  });


  Xtip = new Tips();

  /*default Action
      ctl=dashboard&act=index
  */
  W = new Wpage({},'ctl=dashboard&act=index');


  W.render(document.body);

  (function(){
    if(!Browser.Platform.ios)return;
    var start = {x:0,y:0};
    var fx = new Fx.Scroll('main',{link:'cancel'});
    $('main').addEvents({
      touchstart:function(e){
        start = e.page;
      },
      touchmove:function(e){
        e.stop();
        fx.start(this.scrollLeft-(e.page.x-start.x),this.scrollTop-(e.page.y-start.y));
      }
    });
  })();
  };//function end;

  window.addEvent('domready',initDesktop);

  function _get_rpcnotify_num(obj) {
    W.page('index.php?app=desktop&ctl=rpcnotify&act=read',{method:'POST',onComplete:function(){
        var notify=$E('#topbar .notify_num');
        if(notify){
          var msg=notify.get('text'),n;
          n=msg.substring(1,msg.length-1);
          n=n-1>0?'('+(n-1)+')':'';
          notify.set('text',n);
        }
      },data:{id:$(obj).getParent('tr').getElement('input').get('value')}
    });
  }

  <{if $cloud_url}>
  function redirect_cloud(){
    _request =
      new Request({
        url:'index.php?app=desktop&ctl=default&act=clear_session',
        method:'post',
        onComplete:function(res){
          top.location = '<{$cloud_url}>';
        }
      }).send();
  }
  <{/if}>

</script>
<{foreach from=$theme_scripts item="desktop_theme_js"}>
<script type="text/javascript" src="<{$desktop_theme_js}>"></script>
<{/foreach}>
<{desktop_footer}>
<div class="login-footer">
    <div class="login-footer-text">
        Powered by
        <a href="https://shopex.cn" target="_blank"><img src="<{$env.app.res_url}>/mini-logo.png" alt="oneX OMS Logo" /></a>
    </div>
</div>
</body>
</html>
