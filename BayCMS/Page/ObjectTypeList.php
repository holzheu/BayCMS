<?php

namespace BayCMS\Page;

class ObjectTypeList extends \BayCMS\Fieldset\Domain
{

    public function __construct(\BayCMS\Base\BayCMSContext $context)
    {
        parent::__construct(
            $context,
            'art_objekt',
            $context->t('Object Type', 'Objektart'),
            write_access_query: 'false',
            from: 'art_objekt t, modul m',
            where: 't.id_mod=m.id',
            copy: false
        );
        $squery = '';
        $search = '';
        $non_empty = 'non_empty(' . $this->context->getLangLang2('t.') . ')';
        if ($_GET['json_query'] ?? '')
            $search = $_GET['json_query'];

        if ($_GET['search'] ?? '')
            $search = $_GET['search'];
        if ($search) {
            $search = pg_escape_string($context->getDbConn(), $search);
            $squery = " and m.name||' '||$non_empty ilike '%$search%'";
        }

        $this->setListProperties(
            squery: $squery,
            new_button: false,
            export_buttons: false,
            actions: [],
            order_by: [$non_empty]
        );

        $this->addField(new \BayCMS\Field\TextInput($context, 'search'), search_field: 1, edit_field: 0);
        $this->addField(new \BayCMS\Field\TextInput($context, 'Uname'));
        $this->addField(new \BayCMS\Field\TextInput($context, 'kommentar', $context->t('Full Name', 'Vollständiger Name')));

        $sql = "m.name||': '||$non_empty";
        $this->addField(new \BayCMS\Field\TextInput(
            $context,
            "User",
            sql: $sql
        ), list_field: 1, edit_field: 0);
    }

}