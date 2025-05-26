<?php

namespace BayCMS\Page;

class Notice extends Page {
    protected string $content;
    public function __construct(\BayCMs\Base\BayCMSContext $context, string $content){
        $this->context=$context;
        $this->content=$content;
    }

    public function page(){
        $this->context->printHeader();
        echo $this->content;
        $this->context->printFooter();
    }
}