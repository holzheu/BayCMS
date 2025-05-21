<?php

namespace BayCMS\Page\Admin;

class Upgrader extends \BayCMS\Page\Page
{

    private \BayCMS\Util\Upgrade $upgrader;

    public function __construct(\BayCMS\Base\BayCMSContext $context)
    {
        parent::__construct($context);
        try {
            $this->upgrader = new \BayCMS\Util\Upgrade($this->context);
        } catch (\Exception $e) {
            $this->error(404, $e->getMessage());
        }
    }
    public function page()
    {
        $this->context->printHeader();
        echo "<h1>BayCMS 4.0 Upgrader</h1>";

        if (($_GET['aktion'] ?? '') == 'save') {
            $res = pg_query(
                $this->context->getDbConn(),
                'select function from baycms_upgrades' . (($_POST['force'] ?? false) ? '' : ' where executed is null')
            );
            for ($i = 0; $i < pg_num_rows($res); $i++) {
                [$function] = pg_fetch_row($res, $i);
                echo "<h3>$function</h3>";
                try {
                    echo nl2br($this->upgrader->run($function));
                } catch (\Exception $e) {
                    $this->context->TE->printMessage($e->getMessage(), 'danger');
                }
            }
        }

        $list = \BayCMS\Fieldset\BayCMSList::autoCreate($this->context, 'baycms_upgrades', include_id: 1);
        echo $list->getTable();

        $form = new \BayCMS\Fieldset\Form($this->context, submit: 'Run Upgrades');
        $form->addField(new \BayCMS\Field\Checkbox($this->context, 'force', 'Force all upgrades'));
        echo $form->getForm();
        $this->context->printFooter();

    }
}