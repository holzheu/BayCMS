<?php

namespace BayCMS\Template;

class BootstrapFv extends Bootstrap
{
    public function __construct(\BayCMS\Base\BayCMSContext|null $context = null)
    {
        parent::__construct($context);
        $this->pre_nav_html = '<div class="hidden-xs center-block" style="width:856px;">
        <a href="/' . $this->context->org_folder . '/?lang=' . $this->context->lang . '">
        <img src="/baycms-template/bootstrap.fv/banner2.png">
        </a>
        </div>
    ';
        $this->css = 'bootstrap.fv';
        $this->tiny_css = '/baycms-template/' . $this->css . '/css/bootstrap.min.css';

    }
}