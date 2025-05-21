<?php

namespace BayCMS\Page;

class ErrorPage extends Page {
    private int $error;
    private string $error_message;

    public function __construct(
        \BayCMS\Base\BayCMSContext $context,
        int $error,
        string $error_message      
    ){
        $this->error=$error;
        $this->error_message=$error_message;
        parent::__construct($context);
    }

    public function page()
    {
        header('HTTP/1.0 '.$this->error);
        $this->context->printHeader();
        echo "<h1>HTTP-Error: ".$this->error."</h1>";
        echo "<h4>".$this->error_message."</h4>";
        $this->context->printFooter();
    }
}