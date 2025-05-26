<?php

namespace BayCMS\Template;

use BayCMS\Template\Bootstrap;

class BootstrapAuen extends Bootstrap {
    public function __construct(\BayCMS\Base\BayCMSContext|null $context = null){
        parent::__construct($context);
        $this->no_brand=false;
        $this->css='bootstrap.bayceer';
        $this->home_nav=true;
        $this->header_style='<style>' . ($context->no_frame ? '' : '
#wrap {
	 background: #fff url(/baycms-template/bootstrap.auen/bg_md2.png) center 0 repeat-x;
}
@media (min-width: 1200px) {
#wrap {
	 background: #fff url(/baycms-template/bootstrap.auen/bg_lg2.png) center 0 no-repeat;
}
}
') . '
.navbar {
   /*background-color: rgba(54,193,241,0.7);*/
   /*background: rgba(54,193,241,0.5);*/
   background: rgba(40,56,145,0.3);
   border-color: transparent;
}
.navbar-default .navbar-nav > .active > a, 
.navbar-default .navbar-nav > .active > a:hover, 
.navbar-default .navbar-nav > .active > a:focus {
    background-color: rgb(40,56,145);
}
.navbar-default .navbar-nav > .open > a, 
.navbar-default .navbar-nav > .open > a:hover, 
.navbar-default .navbar-nav > .open > a:focus {
    background-color: rgb(40,56,145);
}

.navbar-default .navbar-nav > li > a:hover, .navbar-default .navbar-nav > li > a:focus {
    background-color: rgb(40,56,145);
}

</style>
';

$this->pre_nav_html='<div class="center-block"  style="width:100%; background-color: none; height:129px;">


<div class="container">
<div class="pull-left">
<a href="http://www.uni-bayreuth.de"><img style="padding:20px 50px 20px 10px;" src="/baycms-template/bootstrap.auen/logo-ubt.png"></a>
</div>
<h1 class="hidden-xs" style="font-size:1.5em; font-weight: bold; color: #000;">' . $GLOBALS['context']->getRow1String('head_') . '</h1>
<h3 class="hidden-xs" style="font-size:1.2em; font-weight: bold; color: #000;">' . $GLOBALS['context']->getRow1String('subhead_') . '</h3>
</div>
</div>
<div class="clearfix"></div>';

        $this->info3='
    <h4>' . $this->t('Developed by', 'Inhalt von') . '</h4>
    
    <a href="http://www.bayceer.uni-bayreuth.de"><img class="img-responsive" src="/baycms-template/bootstrap.ubtauen/bayceer.gif"></a>
    ';
    }
}