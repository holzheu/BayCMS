<?php

namespace BayCMS\Page;

abstract class Page extends \BayCMS\Base\BayCMSBase
{
    public function __construct(\BayCMS\Base\BayCMSContext $context,)
    {
        $this->context = $context;
    }

    abstract public function page();

    protected function error($error, $error_message = '')
    {
        $p = new \BayCMS\Page\ErrorPage(
            context: $this->context,
            error: $error,
            error_message: $error_message
        );
        $p->page();
    }
}