<?php

namespace BayCMS\Page\Admin;

class UserQB extends \BayCMS\Fieldset\QueryBuilder
{
    public function __construct(\BayCMS\Base\BayCMSContext $context)
    {
        parent::__construct(
            $context,
            from: '(select b.id,b.login,b.kommentar,b.email,b.init_pw,b.gruppe,null as access_de,null as access_en,null as last_login,null as bis,
        l.de, l.en 
            from benutzer b, objekt o, lehrstuhl l 
            where (o.id_obj=l.id or o.id=l.id) and b.id=o.id and (o.id_obj=' . $context->getOrgId() . ' or o.id=' . $context->getOrgId() . ') 
            and b.id not in (select id_benutzer from in_ls where id_lehr=' .
            $context->getOrgId() . ' and id_benutzer>0) 
        union select b.id, b.login,b.kommentar,b.email,case when o.id_obj=' . $context->getOrgId() . ' then b.init_pw else null end, 
        b.gruppe,p.de as access_de,p.en as access_en, il.last_login, il.bis, l.de, l.en
        from benutzer b, in_ls il, power p, objekt o, lehrstuhl l
        where o.id=b.id and (o.id_obj=l.id or o.id=l.id) and b.id=il.id_benutzer and il.id_lehr=' .
            $context->getOrgId() . ' and p.power=il.power) t',
            where: 'true',
            row_click_query: 't.id',
            email_fields: ['email']
        );
        $div = $this->t('User', 'Benutzer');
        $this->addField(new \BayCMS\Field\TextInput($context, 'Login', div_id: $div))->set(['list_field' => 1]);
        $this->addField(new \BayCMS\Field\TextInput($context, 'kommentar', $this->t('Full name', 'Vollständiger Name')))->set(['list_field' => 1]);
        $this->addField(new \BayCMS\Field\TextInput($context, 'email', $this->t('E-Mail Address', 'E-Mail Adresse'), div_id: $div))->set(['list_field' => 1]);
        $this->addField(new \BayCMS\Field\TextInput($context, 'init_pw', $this->t('Initial Password', 'Anfangspasswort'), div_id: $div));
        $this->addField(new \BayCMS\Field\Checkbox($context, 'gruppe', $this->t('Group', 'Gruppe'), div_id: $div));
        $this->addField(new \BayCMS\Field\Datetime($context, 'last_login', $this->t('Last login', 'Letztes Login'), div_id: $div));
        $this->addField(new \BayCMS\Field\TextInput($context, 'access_de', $this->t('Rights (de)', 'Rechte (de)'), div_id: $div));
        $this->addField(new \BayCMS\Field\TextInput($context, 'access_en', $this->t('Rights (en)', 'Rechte (en)'), div_id: $div));
        $this->addField(new \BayCMS\Field\Datetime($context, 'bis', $this->t('Until', 'bis'), div_id: $div));
        $this->addField(new \BayCMS\Field\TextInput($context, 'de', $this->t('Unit (de)', 'Einheit (de)'), div_id: $div));
        $this->addField(new \BayCMS\Field\TextInput($context, 'en', $this->t('Unit (en)', 'Einheit (en)'), div_id: $div));

        $this->addNavigationTab($this->t('User/Groups', 'Benutzer/Gruppen'), $_SERVER['SCRIPT_NAME']);



    }


    private function printList()
    {
        echo $this->context->TE->getActionLink("?new=1", $this->t('New User', 'Neuer Nutzer'), '', 'new');
        echo $this->context->TE->getActionLink("?group=1", $this->t('New Group', 'Neue Gruppe'), '', 'new');

        $form = new \BayCMS\Fieldset\Form($this->context);
        $form->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'search'
        ));
        $form->setValues($_GET);
        echo $form->getSearchForm();
        $where = 'true';
        $s = $_GET['search'] ?? '';
        if ($s) {
            $s = pg_escape_string($this->context->getDbConn(), $s);
            $where = "t.login ilike '%$s%' or t.kommentar ilike '%$s%'";
        }



        $list = new \BayCMS\Fieldset\BayCMSList(
            $this->context,
            '(select b.id,b.login,b.kommentar,b.gruppe,null as access,null as bis,null as last_login 
            from benutzer b, objekt o 
            where b.id=o.id and (o.id_obj=' . $this->context->getOrgId() . ' or o.id=' . $this->context->getOrgId() . ') 
            and b.id not in (select id_benutzer from in_ls where id_lehr=' .
            $this->context->getOrgId() . ' and id_benutzer>0) 
        union select b.id,b.login,b.kommentar,b.gruppe,non_empty(' . $this->context->getLangLang2('p.') . ') as access, il.bis, il.last_login
        from benutzer b, in_ls il, power p 
        where b.id=il.id_benutzer and il.id_lehr=' .
            $this->context->getOrgId() . ' and p.power=il.power) t',
            $where,
            order_by: ['t.login', 't.access', 't.bis','t.last_login'],
            auto_order: 1,
            id_query: 't.id',
            jquery_row_click: 1
        );
        $list->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'user',
            $this->t('User/Group', 'Nuzter/Gruppe'),
            sql: "'<img src=\"/" . $this->context->org_folder . "/de/image/'||case when t.gruppe then 'group' else 
        'user' end||'.png\" alt=\"'||non_empty(t.kommentar,'')||'\" title=\"'||non_empty(t.kommentar,'')||'\"> '||t.login||
        case when t.kommentar is null then '' else ' ('||t.kommentar||')'end"
        ));
        $list->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'access',
            $this->t('Rights', 'Rechte ')
        ));
        $list->addField(new \BayCMS\Field\Date(
            $this->context,
            'bis',
            $this->t('Until', 'Bis')
        ));
        $list->addField(new \BayCMS\Field\Datetime(
            $this->context,
            'last_login',
            $this->t('Last Login', 'Letztes Login')
        ));
        echo $list->getTable();
    }

    private function newGroup()
    {
        $form = new \BayCMS\Fieldset\Form(
            $this->context,
            table: 'benutzer',
            uname: 'gruppe',
            action: '?aktion=save&group=1',
        );
        $form->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'login',
            $this->t('Group short name', 'Gruppenname (kurz)'),
            non_empty: 1
        ));
        $form->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'kommentar',
            $this->t('Group full name', 'Gruppenname (lang)'),
            non_empty: 1
        ));
        $form->addField(new \BayCMS\Field\Hidden(
            $this->context,
            'gruppe',
            default_value: 't'
        ));
        $form->addField(new \BayCMS\Field\Hidden(
            $this->context,
            'pw',
            default_value: 'aaxxxxxx'
        ));

        $action = $_GET['aktion'] ?? '';
        if ($action == 'save') {
            $error = $form->setValues($_POST);
            if (!$error) {
                try{
                    $_GET['id'] = $form->save();
                    $this->context->TE->printMessage($this->t('Group created', 'Gruppe angelegt'));
                    usleep(microseconds: 50000);
                    return;
                } catch(\Exception $e){
                    $this->context->TE->printMessage($this->t('Failed to create group', 'Konnte Gruppe nicht angelegen'),'danger');
                    echo "<p>".$e->getMessage()."</p>";
                    unset($_GET['id']);
                }
            }
        }
        echo $form->getForm($this->t('New Group', 'Neue Gruppe'));
    }

    private function createUser($login, $email, $kommentar, $power, $source_id = null)
    {
        $obj = new \BayCMS\Base\BayCMSObject($this->context);
        $obj->set([
            'uname' => 'benutzer',
            'de' => $kommentar,
            'en' => $kommentar,
            'id_parent' => $this->context->getOrgId()
        ]);
        $id_benutzer = $obj->save();

        pg_query_params(
            $this->context->getRwDbConn(),
            'insert into benutzer(id,login,kommentar,email,pw,id_pw_source,gruppe) values($1,$2,$3,$4,$5,$6,$7)',
            [$id_benutzer, $login, $kommentar, $email, 'aaxxxxx', $source_id, 'f']
        );
        pg_query_params(
            $this->context->getRwDbConn(),
            'insert into in_ls(id_benutzer,id_lehr,power) values ($1,$2,$3)',
            [$id_benutzer, $this->context->getOrgId(), $power]
        );
        if ($source_id === null) {
            $init_pw = substr(md5(time() . rand()), 2, 8);
            pg_query_params(
                $this->context->getRwDbConn(),
                'update benutzer set pw_md5=md5($1||salt),init_pw=$1 where id=$2',
                [$init_pw, $id_benutzer]
            );
        }
        return $id_benutzer;
    }
    private function printNew()
    {
        $form = new \BayCMS\Fieldset\Form(
            $this->context,
            action: "?aktion=save&new=1",
            submit: $this->t('search', 'suchen')
        );
        $form->addField(new \BayCMS\Field\Email(
            $this->context,
            'email',
            $this->t('E-Mail Adress of User', 'E-Mail Adresse des Nutzers'),
            non_empty: 1
        ));
        $form->addField(new \BayCMS\Field\Select(
            $this->context,
            'power',
            $this->t('Rights', 'Berechtigung'),
            db_query: "select power as id,non_empty(" . $this->context->getLangLang2('') . ") as description 
            from power where power<=" . $this->context->getPower() . " order by 1",
            null: 1,
            non_empty: 1
        ));
        $action = $_GET['aktion'] ?? '';
        unset($_GET['aktion']);
        if ($action == 'save') {
            $error = $form->setValues($_POST);
            if ($error)
                $action = '';
        }

        if ($action) {
            if ($_POST['id'] ?? false)
                $res = pg_query_params(
                    $this->context->getDbConn(),
                    'select b.*, non_empty(' . $this->context->getLangLang2('l.') . ') as org 
            from benutzer b, objekt o, lehrstuhl l
            where b.id=o.id and o.id_obj=l.id and not b.gruppe and b.id=$1',
                    [$_POST['id']]
                );
            else
                $res = pg_query_params(
                    $this->context->getDbConn(),
                    'select b.*, non_empty(' . $this->context->getLangLang2('l.') . ') as org 
            from benutzer b, objekt o, lehrstuhl l
            where b.id=o.id and o.id_obj=l.id and not b.gruppe and (b.email=$1 or b.login=$1)',
                    [$_POST['email']]
                );
            if ($_POST['power'] > $this->context->getPower())
                $_POST['power'] = $$this->context->getPower();
            if (pg_num_rows($res) == 1) {
                $r = pg_fetch_array($res, 0);
                try {
                    pg_query_params(
                        $this->context->getRwDbConn(),
                        'insert into in_ls(id_benutzer,id_lehr,power) values ($1,$2,$3)',
                        [$r['id'], $this->context->getOrgId(), $_POST['power']]
                    );
                } catch (\Exception $e) {
                    $this->context->TE->printMessage($e->getMessage(), 'danger');
                }
                $_GET['id'] = $r['id'];
                return;
            } elseif (pg_num_rows($res) > 0) {
                $this->context->TE->printMessage($this->t('More than one user found', 'Mehr als einen Nutzer gefunden'), 'danger');
                $v = [];
                for ($i = 0; $i < pg_num_rows($res); $i++) {
                    $r = pg_fetch_array($res, $i);
                    $v[] = [$r['id'], "$r[login] ($r[kommentar], $r[org])"];
                }
                $form->addField(new \BayCMS\Field\SelectRadio(
                    $this->context,
                    'id',
                    $this->t('Select user ', 'Nutzer auswählen'),
                    values: $v
                ));
                $action = '';
            } else {
                $this->context->TE->printMessage($this->t('Searching LDAP', 'Suche im LDAP'));
                //LDAP
                $res = pg_query(
                    $this->context->getDbConn(),
                    "select id,bind_url from pw_source where bind_url ilike 'ldap%'"
                );
                $m = [];
                $ds = false;
                for ($i = 0; $i < pg_num_rows($res); $i++) {
                    [$source_id, $bind_url] = pg_fetch_row($res, $i);
                    if (preg_match('|^ldap://([^/]+)/(.*)$|', $bind_url, $m)) {
                        $ds = ldap_connect($m[1], 389);
                    }
                    // ldaps://someserver.org/ou=People,dc=someserver,dc=org
                    if (preg_match('|(^ldaps://[^/]+)/(.*)$|', $bind_url, $m)) {
                        $ds = ldap_connect($m[1]);

                    }
                    if (!$ds)
                        continue;
                    ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);

                    $ldap_res = ldap_search($ds, $m[2], 'mail=' . $_POST['email']);
                    $info = ldap_get_entries($ds, $ldap_res);
                    if (!$info['count'])
                        continue;
                    $login = $info[0]['uid'][0];
                    $email = $info[0]['mail'][0];
                    $nname = $info[0]['sn'][0];
                    $vname = $info[0]['givenname'][0];
                    $res = pg_query(
                        $this->context->getDbConn(),
                        "select id from benutzer where login='$login' and not gruppe"
                    );
                    if (pg_num_rows($res))
                        list($id_benutzer) = pg_fetch_row($res, 0);
                    else {
                        $id_benutzer = $this->createUser($login, $email, "$vname $nname", $_POST['power'], $source_id);
                    }
                    $_GET['id'] = $id_benutzer;
                    return;
                }
                $this->context->TE->printMessage($this->t('User not found, creating new one', 'Nutzer nicht gefunden, lege neuen an'), 'danger');
                $_GET['id'] = $this->createUser($_POST['email'], $_POST['email'], $_POST['email'], $_POST['power']);
                $_GET['aktion'] = 'edit';
            }

        }
        if (!$action)
            echo $form->getForm($this->t('New User', 'Neuer Nutzer'));

    }



    public function page(string $pre_content = '', string $post_content = '')
    {
        if ($_GET['id'] ?? false) {
            $res = pg_query_params(
                $this->context->getDbConn(),
                'select id from benutzer where id=$1',
                [$_GET['id']]
            );
            if (pg_num_rows($res)) {
                if($_SERVER['PATH_INFO']??''){
                    header("Location: ".$_SERVER['SCRIPT_NAME'].'?id='.$_GET['id']);
                    exit();
                }
                $p = new User($this->context);
                $p->page();
            }
        }

        $this->pageExport();
        $this->pageHelp();
        $path = explode('/', $_SERVER['PATH_INFO'] ?? '/');
        if (in_array($path[1], ['Query', 'Document', 'Email']))
            $this->pageQB($pre_content, $post_content);

        $this->context->printHeader();
        if ($_GET['new'] ?? false)
            $this->printNew();
        if ($_GET['group'] ?? false)
            $this->newGroup();
        if ($_GET['id'] ?? false) {
            $p = new User($this->context);
            $p->page();
        }
        $head = $pre_content . $this->getTabNavigation();
        echo $head;


        $this->printList();
        $this->context->printFooter();
    }
}