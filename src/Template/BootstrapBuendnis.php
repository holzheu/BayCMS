<?php

namespace BayCMS\Template;

class BootstrapBuendnis extends Bootstrap
{
    public function __construct(\BayCMS\Base\BayCMSContext|null $context = null)
    {
        parent::__construct($context);

        $this->pre_nav_html = '
<div style="width:100%; background: url(\'/baycms-template/bootstrap.buendnis/tstripes.png\'); height: 40px; 
background-size:100% 100%;"></div>

<div class="container">
<div class="pull-left">
<h1 style="color:#000;">Bündnis für Klima- und Artenschutz Bayreuth</h1>

</div>
</div>
';

        $this->no_brand = true;
        $this->css = 'bootstrap.buendnis';
        $this->home_nav = true;
        $this->footer_attr = ' style="width:100%; background: url(\'/baycms-template/bootstrap.buendnis/bstripes.png\'); background-size:100% 100%;"';


    }
}