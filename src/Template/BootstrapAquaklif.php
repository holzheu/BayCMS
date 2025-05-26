<?php

namespace BayCMS\Template;

use BayCMS\Template\Bootstrap;

class BootstrapAquaklif extends Bootstrap {
    public function __construct(\BayCMS\Base\BayCMSContext|null $context = null){
        parent::__construct($context);
        $logo_link = $this->context->get('row1', 'te_logolink');
        if (!$logo_link)
            $logo_link = '/' . $this->context->org_folder . '/?lang=' . $this->context->lang;
        
        $this->css='bootstrap.aquaklif';
        $this->home_nav=true;
        $this->pre_nav_html='
        <div class="container">
        <div class="pull-left" style="max-width: 45%; ">
        <a href="' . $logo_link . '"><img  class="img-responsive" style="padding:0px; max-height: 70px; padding-top:5px; padding-bottom:5px;" src="/baycms-template/bootstrap.aquaklif/aquaklif.png"></a>
        </div>
        <div class="pull-right row" style="max-width: 30%">
        <a href="https://www.bayklif.de"><img  class="img-responsive pull-right" style="max-height: 70px; padding-top:5px; padding-bottom:5px;" src="/baycms-template/bootstrap.aquaklif/Bayklif_k.png"></a>
        </div>
        
        </div>
        ';
        
        $this->footer_text=$this->t('Funded by the Bavarian State Ministry of Science and the Arts.',
        'Gefördert durch das Bayerische Staatsministerium für Wissenschaft und Kunst.
        ');
    }
}