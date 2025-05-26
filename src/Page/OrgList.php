<?php

namespace BayCMS\Page;

class OrgList extends \BayCMS\Fieldset\Domain{
    public function __construct(\BayCMS\Base\BayCMSContext $context)
    {
        parent::__construct(
            $context,
            'lehrstuhl',
            $context->t('Organizations', 'Organisationen'),
            write_access_query: 'false',
            from: 'lehrstuhl t, objekt o',
            where: 't.id=o.id and o.geloescht is null',
            copy: false
        );
        $squery = '';
        $search = '';
        if ($_GET['json_query'] ?? '')
            $search = $_GET['json_query'];
        if ($_GET['search'] ?? '')
            $search = $_GET['search'];

        $non_empty='non_empty('.$context->getLangLang2('t.').')';
        if ($search) {
            $search = pg_escape_string($context->getDbConn(), $search);
            $squery = " and $non_empty ilike '%$search%'";
        }


        $this->setListProperties(
            squery: $squery,
            new_button: false,
            export_buttons: false,
            actions: [],
            order_by: [$non_empty]
        );

        $this->addField(new \BayCMS\Field\TextInput($context, 'search'), search_field: 1, edit_field: 0);
        $this->addField(new \BayCMS\Field\TextInput($context, 'de','Name (deutsch)'));
        $this->addField(new \BayCMS\Field\TextInput($context, 'en', 'Name (english)'));

        
        $this->addField(new \BayCMS\Field\TextInput(
            $context,
            "User",
            sql: $non_empty
        ), list_field: 1, edit_field: 0);
    }    
}