<?php

namespace BayCMS\Template;

class BootstrapSg extends Bootstrap
{
    public function __construct(\BayCMS\Base\BayCMSContext|null $context = null)
    {
        parent::__construct($context);

        $this->pre_nav_html = '
<div class="container">
<div class="pull-left" style="max-width: 30%; ">
<a href="http://www.kickers-sg.de"><img  class="img-responsive" style="padding:0px; max-height: 100px;" src="/baycms-template/bootstrap.sg/SGLogo.jpg"></a>
</div>
<div class="pull-right row" style="max-width: 70%">
<a href="http://www.tsv-harsdorf.de"><img  class="img-responsive pull-right" style="max-width: 20%; padding-left:10px; padding-top:3px;" src="/baycms-template/bootstrap.sg/logo-harsdorf.png"></a>
<a href="http://www.tsv-trebgast.de"><img  class="img-responsive pull-right" style="max-width: 17%; padding-left:10px; padding-top:3px;" src="/baycms-template/bootstrap.sg/logo-trebgast.png"></a>
<a href="https://www.facebook.com/SVLanzendorf/"><img  class="img-responsive pull-right" style="max-width: 20%; padding-left:10px; padding-top:3px;" src="/baycms-template/bootstrap.sg/SVL.jpeg"></a>
</div>

</div>
';
        $this->css = 'bootstrap.sg';
        $this->home_nav = true;
    }
}