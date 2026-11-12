<?php
/**
* 调拨单FINDER
*/
class taoguanallocate_finder_appropriation
{
    protected $vue_mode = false;

    public function setVueMode(bool $vue): void
    {
        $this->vue_mode = $vue;
    }


    function __construct($app)
    {
        $this->app = $app;
    }
}
