<?php

namespace BayCMS\Template;

use BayCMS\Template\Bootstrap;

class BootstrapBat extends Bootstrap {
    public function __construct(\BayCMS\Base\BayCMSContext|null $context = null){
        parent::__construct($context);
        $this->css='bootstrap.bat';
        $this->home_nav=true;
        $this->pre_nav_html= '<div class="container">
        <div class="pull-left" style="max-width: 100%; ">
        <a href="https://www.izw-berlin.de/en/international-bat-research-online-symposium-en.html">
        <img  class="img-responsive" style="padding:0px; padding-top:5px; padding-bottom:5px;" src="/baycms-template/bootstrap.bat/IBROS2023_Banner.jpg"></a>
        </div>
        
        </div>
        ';
    }
}