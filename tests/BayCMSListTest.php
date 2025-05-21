<?php
use function PHPUnit\Framework\assertStringContainsString;

class BayCMSListTest extends \PHPUnit\Framework\TestCase
{

    protected \BayCMS\Base\BayCMSContext $context; 

    protected function setUp(): void {
        $GLOBALS['TE'] = new \BayCMS\Base\BasicTemplate();
        $_SERVER['PHP_SELF']="/btineu/en/top/gru/index.php";
        $_SERVER['HTTP_HOST']='localhost';
        $this->context = new \BayCMS\Base\BayCMSContext('/local/www/btineu');
        $this->context->initTemplate();    
    }

    public function testList()
    {
        $list = new \BayCMS\Fieldset\BayCMSList(
            $this->context,
            "termine t, objekt5001 o",
            "t.id=o.id");
        $list->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'de'));

        $list->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'datum'
        ));
        $list->addField(new \BayCMS\Field\BilangInput(
            $this->context,
            '',
            'Titel'));
        
        $res=$list->getTable();
        echo $res;
        assertStringContainsString('<th>Titel</th>', $res);


    }


}
