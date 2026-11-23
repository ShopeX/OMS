<?php
/**
 * 首页挂件：平台通知
 */
class ome_desktop_widgets_platformnotify implements desktop_interface_widget
{
    var $order = 8;

    function __construct($app)
    {
        $this->app = $app;
        $this->render = new base_render(app::get('ome'));
    }

    function get_title()
    {
        return app::get('ome')->_('平台通知');
    }

    function get_html()
    {
        $render = $this->render;
        $lib = kernel::single('ome_shop_platform_luban_product');

        $hasShop = $lib->hasBoundLubanShop();
        $notify = null;
        if ($hasShop) {
            $notify = $lib->getHomepageNotify();
        }

        // 只读 consultandpatch 缓存中的 platform 公告，不主动拉 EGO API
        $platformList = $this->getPlatformAnnounceList();

        $render->pagedata['has_shop'] = $hasShop;
        $render->pagedata['notify'] = $notify;
        $render->pagedata['platform_list'] = $platformList;
        return $render->fetch('desktop/widgets/platformnotify.html');
    }

    /**
     * 从 consultandpatch 缓存读取 platform 公告并解析标题/链接/平台名
     *
     * @return array
     */
    protected function getPlatformAnnounceList()
    {
        $result = kernel::single('ome_desktop_widgets_consultandpatch')->fetchDataFromCache();
        $platformList = array();
        if (empty($result['platform']) || !is_array($result['platform'])) {
            return $platformList;
        }

        foreach ($result['platform'] as $item) {
            $parsed = $this->parsePlatformAnnounceItem($item);
            if ($parsed) {
                $platformList[] = $parsed;
            }
        }
        return $platformList;
    }

    /**
     * 解析单条 platform 公告
     * 示例 url: <a href="https://ego.ishopex.cn/notify/190" target="_blank">【京东】开放生态用户标识升级安...</a>
     *
     * @param array $item
     * @return array|null
     */
    protected function parsePlatformAnnounceItem($item)
    {
        if (empty($item['url']) || !is_string($item['url'])) {
            return null;
        }

        $urlHtml = $item['url'];
        $href = '';
        $linkText = '';

        if (preg_match('/href=["\']([^"\']+)["\']/i', $urlHtml, $mHref)) {
            $href = $mHref[1];
        }
        if (preg_match('/<a[^>]*>(.*?)<\/a>/is', $urlHtml, $mText)) {
            $linkText = trim(strip_tags($mText[1]));
        } else {
            $linkText = trim(strip_tags($urlHtml));
        }

        if ($linkText === '' && $href === '') {
            return null;
        }

        $platformName = '公告';
        $title = $linkText;
        if (preg_match('/【(.+?)】/u', $linkText, $mPlat)) {
            $platformName = $mPlat[1];
            $stripped = trim(preg_replace('/【.+?】/u', '', $linkText, 1));
            if ($stripped !== '') {
                $title = $stripped;
            }
        }

        return array(
            'platform_name' => $platformName,
            'title'         => $title,
            'href'          => $href,
            'publish_date'  => isset($item['publish_date']) ? $item['publish_date'] : '',
        );
    }

    function get_className()
    {
        return 'platform-notify-widget';
    }

    function get_width()
    {
        return 'l-2';
    }
}
