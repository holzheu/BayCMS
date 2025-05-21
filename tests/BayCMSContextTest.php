<?php

use function PHPUnit\Framework\assertEquals;

class BayCMSContextTest extends \PHPUnit\Framework\TestCase {

     public function testContextExtern(){
        $_SERVER['HTTP_HOST']='localhost';
        $_SERVER['PHP_SELF']='/btineu/index.php';
        $context = new \BayCMS\Base\BayCMSContext('/local/www/btineu');
        assertEquals(5001, $context->getOrgId());
    }

    public function testContextIntern(){
        $_SERVER['HTTP_HOST']='localhost';
        $_SERVER['PHP_SELF']='/btineu/de/admin/gru/modul.php';
        $context = new \BayCMS\Base\BayCMSContext('/local/www/btineu');
        $context->setLang();
        $context->checkMinPower();

        assertEquals(1000, $context->getMinPower());
    }


}
