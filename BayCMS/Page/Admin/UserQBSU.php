<?php

namespace BayCMS\Page\Admin;

use BayCMS\Field\TextInput;
use BayCMS\Field\Date;
use BayCMS\Fieldset\QueryBuilder;

class UserQBSU extends QueryBuilder
{
    public function __construct(\BayCMS\Base\BayCMSContext $context)
    {
        if ($context->getPower() < 10000) {
            $p = new \BayCMS\Page\ErrorPage($context, 401, 'Access denied');
            $p->page();
        }

        parent::__construct(
            $context,
            'benutzer',
            from:'benutzer t, in_ls il, lehrstuhl l, power p',
            where: 't.id=il.id_benutzer and il.id_lehr=l.id and il.power=p.power and (il.bis is null or il.bis <=now())',
            email_fields:['email']
        );

        $this->addField(new TextInput($context,'login','Login'))->set(['list_field'=>1]);
        $this->addField(new TextInput($context,'kommentar','Name'))->set(['list_field'=>1]);
        $this->addField(new TextInput($context,'email','E-Mail'))->set(['list_field'=>1]);
        $this->addField(new TextInput($context,'org','Einheit',sql:'non_empty(l.de,l.en)'));
        $this->addField(new Date($context,'bis','Gültigkeit',sql:'il.bis'));
        $this->addField(new TextInput($context,'power','Rolle',sql:'non_empty(p.de,p.en)'));

    }
}