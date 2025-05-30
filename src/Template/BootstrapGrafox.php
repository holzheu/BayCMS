<?php

namespace BayCMS\Template;

class BootstrapGrafox extends Bootstrap {
    public function __construct(\BayCMS\Base\BayCMSContext|null $context = null){
        parent::__construct($context);

        $BOOTSTRAP_HOME_NAV = 0;
        if ($this->context->get('row1', 'org_logo'))
            $logo = '<img src="'.$this->context->getOrgLogo().'" style="padding-right:10px;">';
        else {
            $logo = '';
            $BOOTSTRAP_HOME_NAV = 1;
        }
        $logo_link = $this->context->get('row1', 'te_logolink');
        $default_home_link = '/' . $this->context->org_folder . '/?lang=' . $this->context->lang;
        if (!$logo_link)
            $logo_link = $default_home_link;
        
        $this->wrap_container=true;
        $this->no_brand=false;
        $this->pre_nav_html='
        
            <div class="hidden-xs">
            <div class="pull-left" style="max-width: 40%; padding-right:20px;">
            <a href="' . $logo_link . '">
            ' . $logo . '
            </a>
            </div>
            <p  style="font-size:1.3em; font-weight:bold; padding-top:15px;">' . $this->context->getRow1String('head_') . '</p>
            <p  style="font-size:1.1em; font-weight:bold;">' . $this->context->getRow1String('subhead_') . '</p>
                
            </div>
            <div class="clearfix"></div>
        ';
        $this->css='bootstrap.grafox';
        
        $row1['fgcolor'] = '#5bc0de';
        $row1['f2color'] = '#ffffff';
        $this->tiny_css = '/baycms-template/' . $this->css . '/css/bootstrap.min.css';

    }
}