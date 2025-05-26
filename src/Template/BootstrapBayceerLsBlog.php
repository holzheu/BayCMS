<?php

namespace BayCMS\Template;

class BootstrapBayceerLsBlog extends BootstrapBayceerLs{
    public function __construct(\BayCMS\Base\BayCMSContext|null $context = null){
        parent::__construct($context);
        $this->info='blog';

    }
}