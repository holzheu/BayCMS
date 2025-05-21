<?php

namespace BayCMS\Template;

class BootstrapIzw extends Bootstrap {
    public function __construct(\BayCMS\Base\BayCMSContext|null $context = null){
        parent::__construct($context);
        $this->css='bootstrap.izw';
        $this->pre_nav_html='
            <div class="container">
        <div class="pull-left" style="max-width: 70%; ">
        <a href="https://www.leibniz-izw-akademie.de/"><img  class="img-responsive" style="padding:0px; padding-top:5px; padding-bottom:5px;" src="/baycms-template/bootstrap.izw/IZWAK_Logo_b.gif"></a>
        </div>
        
        </div>';
        $this->home_nav=true;
        
        
    }
}