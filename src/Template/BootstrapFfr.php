<?php

namespace BayCMS\Template;

class BootstrapFfr extends Bootstrap
{
    public function __construct(\BayCMS\Base\BayCMSContext|null $context = null)
    {
        parent::__construct($context);
        $logo_link = $this->context->get('row1', 'te_logolink');
        if (!$logo_link)
            $logo_link = '/' . $this->context->org_folder . '/?lang=' . $this->context->lang;



        $this->header_style = '<link href="https://fonts.googleapis.com/css?family=Dosis" rel="stylesheet">';
        $this->pre_nav_html = '
<div class="container">
<div style="width: 100%; ">
<a href="' . $logo_link . '"><img  class="img-responsive" src="/baycms-template/bootstrap.ffr/banner.jpg"></a>
</div>

</div>
';
        $this->css = 'bootstrap.ffr';
        $this->home_nav = true;
        $this->footer_text = 'Gefördert durch: Bundesministerium für Umwelt, Naturschutz, nukleare Sicherheit und Verbraucherschutz';
        $this->tiny_css = '/baycms-template/' . $this->css . '/css/bootstrap.min.css';


    }
}