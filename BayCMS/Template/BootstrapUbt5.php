<?php

namespace BayCMS\Template;

class BootstrapUbt5 extends Bootstrap {
    public function __construct(\BayCMS\Base\BayCMSContext|null $context = null){
        parent::__construct($context);
        $this->home_nav = 0;
        if ($this->context->get('row1', 'org_logo'))
            $logo = '<img src="/' . $this->context->org_folder . '/de/file/logo' .
                $this->context->get('row1', 'id') . '.' . $this->context->get('row1', 'org_logo') . '" style="padding-right:10px;">';
        else {
            $logo = '';
            $this->home_nav = 1;
        }
        $logo_link = $this->context->get('row1', 'te_logolink');
        $default_home_link = '/' . $this->context->org_folder . '/?lang=' . $this->context->lang;
        if (!$logo_link)
            $logo_link = $default_home_link;
        $this->warp_container=true;
        $this->no_brand=false;
        $this->css='bootstrap.ubt5';
        $this->pre_nav_html='
        
            <div >
            <div class="pull-left" style="max-width: 30%">
            <a href="http://www.uni-bayreuth.de"><img  class="img-responsive" 
                style="padding:0px;padding-right:20px;" src="/baycms-template/bootstrap.ubt5/logo-university-of-bayreuth_b.png"></a>
            </div>
            <div class="pull-left" style="max-width: 40%; padding-right:20px;">
            <a href="' . $logo_link . '">
            ' . $logo . '
            </a>
            </div>
            <p  style="font-size:1.3em; font-weight:bold; padding-top:15px;">' . $this->context->getRow1String('head_') . '</p>
            <p  style="font-size:1.1em; font-weight:bold;">' . $this->context->getRow1String('subhead_') . '</p>
                
            </div>
            <div class="clearfix"></div>';
        
        
        //$row1['fgcolor'] = '#5bc0de';
        //$row1['f2color'] = '#ffffff';
        
    }
}