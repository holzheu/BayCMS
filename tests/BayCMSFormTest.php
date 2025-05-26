<?php
namespace Tests;

use function PHPUnit\Framework\assertStringContainsString;

class BayCMSFormTest extends \PHPUnit\Framework\TestCase
{   
    protected \BayCMS\Base\BayCMSContext $context; 

    protected function setUp(): void {
        $_SERVER['PHP_SELF']="/btineu/en/top/gru/index.php";
        $_SERVER['HTTP_HOST']='localhost';
        $this->context = new \BayCMS\Base\BayCMSContext('/local/www/btineu');
        $this->context->initTemplate();
    }

    public function testForm()
    {
        $form = new \BayCMS\Fieldset\Form($this->context);
        $form->addField(new \BayCMS\Field\TextInput($this->context,
        "Name",
        non_empty:1 ));
        $form->addField(new \BayCMS\Field\TextInput(
            $this->context,
            "Vorname",
            non_empty:1));
        $form->addField(new \BayCMS\Field\TextInput(
            $this->context,
            "Email",
            footnote:['**','Für Spam']));
        $form->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'email2',
            no_add_to_query:1));

        $form->addField(new \BayCMS\Field\BilangInput(
            $this->context,
            '',
            non_empty:1
            ));

        $form->addField(new \BayCMS\Field\BilangTextarea(
            $this->context,
            'text',
            htmleditor:1
        ));
        $values=['name'=>'Stefan > Holzheu','text'=>['de'=>'Wert-DE']];
        $form->setValues($values);
        assertStringContainsString('Stefan &gt; Holzheu', $form->getTable());
        assertStringContainsString('Stefan &gt; Holzheu', $form->getForm());
        assertStringContainsString('for="form1_de"', $form->getForm());
        assertStringContainsString('mandatory', $form->getForm());

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
