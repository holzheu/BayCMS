<?php

namespace BayCMS\Page;

class UserList extends \BayCMS\Fieldset\Domain
{
    public function __construct(\BayCMS\Base\BayCMSContext $context)
    {
        parent::__construct(
            $context,
            'benutzer',
            $context->t('User/Groups', 'Benutzer/Gruppen'),
            write_access_query: 'false',
            from: 'benutzer t, objekt o',
            where: 't.id=o.id and o.geloescht is null',
            copy: false
        );
        $squery = '';
        $search = '';
        if ($_GET['json_query'] ?? '')
            $search = $_GET['json_query'];

        if ($_GET['search'] ?? '')
            $search = $_GET['search'];
        if ($search) {
            $search = pg_escape_string($context->getDbConn(), $search);
            $squery = " and (t.kommentar ilike '%$search%' or t.login ilike '%$search%')";
        }
        if($_GET['gr_only']??false) $squery.=' and t.gruppe';

        $this->setListProperties(
            squery: $squery,
            new_button: false,
            export_buttons: false,
            actions: [],
            order_by: ['t.login']
        );

        $this->addField(new \BayCMS\Field\TextInput($context, 'search'), search_field: 1, edit_field: 0);
        $this->addField(new \BayCMS\Field\TextInput($context, 'Login'));
        $this->addField(new \BayCMS\Field\TextInput($context, 'kommentar', $context->t('Full Name', 'Vollständiger Name')));

        if ($_GET['json_query'] ?? '')
            $sql = "t.login||' ('||t.kommentar||', '||case when t.gruppe then '".$context->t('Group','Gruppe')."' else 
            '".$context->t('User','Benutzer')."' end||')'";
        else
            $sql = "'<img src=\"/" . $context->org_folder . "/de/image/'||case when t.gruppe then 'group' else 
            'user' end||'.png\" alt=\"'||t.kommentar||'\" title=\"'||t.kommentar||'\"> '||t.login||' ('||t.kommentar||')'";
        $this->addField(new \BayCMS\Field\TextInput(
            $context,
            "User",
            sql: $sql
        ), list_field: 1, edit_field: 0);
    }
}