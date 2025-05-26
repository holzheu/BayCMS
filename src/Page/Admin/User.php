<?php

namespace BayCMS\Page\Admin;

class User extends \BayCMS\Page\Page
{

    private array $user = [];


    private function delUser()
    {
        $obj = new \BayCMS\Base\BayCMSObject($this->context);
        $obj->load($_GET['id']);

        if (!$obj->checkWriteAccess() || ($_POST['delete'] ?? '') == 'access') {
            pg_query_params(
                $this->context->getRwDbConn(),
                'delete from in_ls where id_benutzer=$1 and id_lehr=$2',
                [$_GET['id'], $this->context->getOrgId()]
            );
            $this->context->TE->printMessage($this->t('Deleted access to oranization', 'Zugang zur Einheit gelöscht'));
            unset($_GET['id']);
            return;
        }

        $res = pg_query_params(
            $this->context->getDbConn(),
            'select * from in_ls where id_benutzer=$1 and id_lehr!=$2',
            [$_GET['id'], $this->context->getOrgId()]
        );

        if (!pg_num_rows($res) || ($_POST['delete'] ?? '') == 'erase') {
            $res = pg_query_params(
                $this->context->getDbConn(),
                'select id from lehrstuhl where id=$1',
                [$_GET['id']]
            );
            if (pg_num_rows($res)) {
                $this->context->TE->printMessage($this->t(
                    'This is the main group of a unit. It cannot be deleted.',
                    'Diese ist die Hauptgruppe einer Einheit. Diese kann nicht gelöscht werden'
                ), 'danger');
                return;
            }
            try {
                $obj->erase(true);
                $this->context->TE->printMessage($this->t('Deleted entry', 'Eintrag gelöscht'));
                unset($_GET['id']);
            } catch (\Exception $e) {
                $this->context->TE->printMessage($this->t('Failed to delete user in database','Konnte Nutzer nicht löschen'), 'danger');
                echo "<p>".$e->getMessage()."</p>";
            }
            return;

        }

        $this->context->TE->printMessage($this->t('User has access to other units', 'Nutzer hat auch Zugang zu anderen Einheiten'), 'danger');
        $form = new \BayCMS\Fieldset\Form(
            $this->context,
            qs: 'tab=basic',
            action: '?aktion=del&id=' . $_GET['id'],
            submit: $this->t('delete', 'löschen')
        );
        $form->addField(new \BayCMS\Field\Select(
            $this->context,
            'delete',
            $this->t('Action', 'Aktion'),
            values: [
                ['access', $this->t('Delete access only', 'Nur Zugang löschen')],
                ['erase', $this->t('Erase user everywhere', 'Nutzer vollständig löschen')]
            ]
        ));
        echo $form->getForm();


    }
    private function tabAccess()
    {
        $form = new \BayCMS\Fieldset\Form(
            $this->context,
            table: 'in_ls',
            qs: 'tab=access&id=' . $_GET['id']
        );
        $form->addField(new \BayCMS\Field\Select(
            $this->context,
            'power',
            $this->t('Rights', 'Berechtigung'),
            db_query: "select power as id,non_empty(" . $this->context->getLangLang2('') . ") as description 
            from power where power<=" . $this->context->getPower() . " order by 1",
            null: 1,
        ));
        $form->addField(new \BayCMS\Field\Date(
            $this->context,
            'bis',
            $this->t('Access until', 'Zugang bis')
        ));

        $res = pg_query_params(
            $this->context->getDbConn(),
            'select * from in_ls where id_benutzer=$1 and id_lehr=$2',
            [$_GET['id'], $this->context->getOrgId()]
        );
        if (pg_num_rows($res)) {
            $r = pg_fetch_array($res, 0);
            $form->setValues($r);
        }
        $action = $_GET['aktion'] ?? '';
        if ($action == 'save') {
            $form->setValues($_POST);
            pg_query_params(
                $this->context->getRwDbConn(),
                'delete from in_ls where id_lehr=$1 and id_benutzer=$2',
                [$this->context->getOrgId(), $_GET['id']]
            );
            if ($_POST['power'] != 'null') {
                if ($_POST['power'] > $this->context->getPower())
                    $_POST['power'] = $$this->context->getPower();
                $bis = $form->getField('bis')->getValue();
                pg_query_params(
                    $this->context->getRwDbConn(),
                    'insert into in_ls(id_benutzer,id_lehr,power,bis) values($1,$2,$3,$4)',
                    [$_GET['id'], $this->context->getOrgId(), $_POST['power'], $bis]
                );
            }
        }
        if ($action == 'edit')
            echo $form->getForm();
        else
            echo $form->getTable(delete_link: false, no_copy_link: true);

    }


    private function tabPassword()
    {
        $form = new \BayCMS\Fieldset\Form(
            $this->context,
            table: 'benutzer',
            write_access_query: ($this->user['check_objekt'] ?? '') == 't' ? 'true' : 'false',
            qs: 'tab=password',
            delete_button: false
        );
        $form->addField(new \BayCMS\Field\Select(
            $this->context,
            'id_pw_source',
            $this->t('Password source', 'Passwort Quelle'),
            db_query: 'select id,name as description from pw_source order by name',
            null: 1
        ));
        $form->addField(new \BayCMS\Field\Checkbox(
            $this->context,
            'sync_with_source',
            $this->t('Keep local password hash in sync with source', 'Lokalen Passwort-Hash mit Quelle synchronisieren')
        ));
        $form->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'init_pw',
            $this->t('Initial Password', 'Anfangspasswort')
        ));
        $form->addField(new \BayCMS\Field\Password(
            $this->context,
            'pw1',
            $this->t('New Password', 'Neues Passwort'),
            no_add_to_query: 1,
            not_in_table: 1
        ));
        $form->addField(new \BayCMS\Field\Password(
            $this->context,
            'pw2',
            $this->t('New Password (confirmation)', 'Neues Passwort (Wiederholung)'),
            no_add_to_query: 1,
            not_in_table: 1
        ));
        $form->addField(new \BayCMS\Field\Checkbox(
            $this->context,
            'set_init_pw',
            $this->t('Save new password as initial password in clear text', 'Neues Passwort als Anfangspasswort in Klartext speichern'),
            no_add_to_query: 1,
            not_in_table: 1
        ));
        $form->addField(new \BayCMS\Field\Checkbox(
            $this->context,
            'gen_pw',
            $this->t('Generate random password', 'Neues Zufallspasswort generieren'),
            no_add_to_query: 1,
            not_in_table: 1
        ));

        $form->load($_GET['id']);
        $action = $_GET['aktion'] ?? '';


        if ($action == 'save') {
            $error = $form->setValues($_POST);
            if ($error)
                $action = 'edit';
        }
        if ($action == 'save') {
            if ($_POST['pw1'] && $_POST['pw1'] != $_POST['pw2']) {
                $this->context->TE->printMessage($this->t('Passwords do not match', 'Passwörter sind nicht gleich'), 'danger');
                $action = 'edittest';
            }
        }
        if ($action == 'save') {
            $pw = '';
            if ($_POST['gen_pw']??false) {
                $_POST['set_init_pw'] = 1;
                $_POST['init_pw'] = substr(md5(time() . rand()), 2, 8);
                $pw = $_POST['init_pw'];
            } elseif ($_POST['pw1']) {
                $pw = $_POST['pw1'];
                $_POST['init_pw'] = $pw;
            }
            if (!($_POST['set_init_pw']??false))
                $_POST['init_pw'] = '';
            $form->setValues($_POST);
            $form->save();
            if ($pw)
                pg_query_params(
                    $this->context->getRwDbConn(),
                    'update benutzer set pw_md5=md5($1||salt) where id=$2',
                    [$pw, $_GET['id']]
                );
            $action = '';

        }
        if ($action == 'edit')
            echo $form->getForm();
        else
            echo $form->getTable(delete_link: false);



    }
    private function tabBasic()
    {
        $form = new \BayCMS\Fieldset\Form(
            $this->context,
            table: 'benutzer',
            uname: ($_POST['gruppe'] ?? false) ? 'gruppe' : 'benutzer',
            write_access_query: ($this->user['check_objekt'] ?? '') == 't' ? 'true' : 'false',
            qs: 'tab=basic'
        );
        $form->addField(new \BayCMS\Field\Select(
            $this->context,
            'gruppe',
            $this->t('User/Group', 'Benutzer/Gruppe'),
            values: [['f', 'User'], ['t', 'Group']]
        ));
        $form->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'Login',
            non_empty: 1,
            help: $this->t('This must be unique in the instance', 'Muss in der Instanz eindeutig sein')
        ));
        $form->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'kommentar',
            $this->t('Full Name', 'vollständiger Name'),
            non_empty: 1
        ));
        $form->addField(new \BayCMS\Field\Email(
            $this->context,
            'email',
            $this->t('E-Mail Address', 'E-Mail Adresse')
        ));
        $form->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'mobil',
            $this->t('Phone number', 'Telefonnumer')
        ));

        $form->load($_GET['id']);
        $action = $_GET['aktion'] ?? '';


        if ($action == 'save') {
            $error = $form->setValues($_POST);
            if ($error)
                $action = 'edit';
        }
        if ($action == 'save')
            $form->save();
        if ($action == 'edit')
            echo $form->getForm();
        else
            echo $form->getTable(no_copy_link: 1);



    }

    private function tabIP()
    {
        $action = $_GET['aktion'] ?? '';
        if ($action == 'del') {
            try {
                pg_query_params(
                    $this->context->getRwDbConn(),
                    'delete from benutzer_ip where id_benutzer=$1 and ip=$2',
                    [$_GET['id'], $_GET['ip']]
                );
            } catch (\Exception $e) {
                $this->context->TE->printMessage($e->getMessage(), 'danger');
            }
        }


        if ($action == 'save') {
            try {
                pg_query_params(
                    $this->context->getRwDbConn(),
                    'insert into benutzer_ip(id_benutzer,ip,bemerkung) values ($1,$2,$3)',
                    [$_GET['id'], $_POST['ip'], $_POST['bemerkung']]
                );
            } catch (\Exception $e) {
                $this->context->TE->printMessage($e->getMessage(), 'danger');
            }
        }

        $list = new \BayCMS\Fieldset\BayCMSList(
            $this->context,
            'benutzer_ip t',
            't.id_benutzer=' . $_GET['id'],
            write_access_query: ($this->user['check_objekt'] ?? '') == 't' ? 'true' : 'false',
            actions: ['del'],
            id_name: 'ip',
            id_query: 't.ip',
            qs: 'tab=' . $_GET['tab'] . '&id=' . $_GET['id']
        );
        $list->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'ip',
            'Network'
        ));
        $list->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'Bemerkung'
        ));

        echo $list->getTable();

        $form = new \BayCMS\Fieldset\Form(
            $this->context,
            qs: 'tab=' . $_GET['tab'] . '&id=' . $_GET['id']
        );

        $form->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'ip',
            'Network',
            help: 'e.g. 132.180.0.0/16'
        ));
        $form->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'Bemerkung'
        ));
        echo $form->getForm($this->t('New Entry', 'Neuer Eintrag'));

    }

    private function tabFine($table = 'admin_objekt')
    {
        $action = $_GET['aktion'] ?? '';
        if ($action == 'del') {
            try {
                pg_query_params(
                    $this->context->getRwDbConn(),
                    'delete from ' . $table . ' where id_lehr=$1 and id_benutzer=$2 and id_art=$3',
                    [$this->context->getOrgId(), $_GET['id'], $_GET['id_art']]
                );
            } catch (\Exception $e) {
                $this->context->TE->printMessage($e->getMessage(), 'danger');
            }
        }


        if ($action == 'save') {
            try {
                pg_query_params(
                    $this->context->getRwDbConn(),
                    'insert into ' . $table . '(id_lehr,id_benutzer,id_art) values ($1,$2,$3)',
                    [$this->context->getOrgId(), $_GET['id'], $_POST['id']]
                );
            } catch (\Exception $e) {
                $this->context->TE->printMessage($e->getMessage(), 'danger');
            }
        }

        $list = new \BayCMS\Fieldset\BayCMSList(
            $this->context,
            'art_objekt t, modul m, ' . $table . ' f',
            't.id_mod=m.id and  t.id=f.id_art and f.id_benutzer=' . $_GET['id'],
            write_access_query: ($this->user['check_objekt'] ?? '') == 't' ? 'true' : 'false',
            actions: ['del'],
            id_name: 'id_art',
            id_query: 't.id',
            qs: 'tab=' . $_GET['tab'] . '&id=' . $_GET['id']
        );
        $list->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'Modul',
            sql: "m.name"
        ));
        $list->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'art',
            $this->t('Object Type', 'Objekt Art'),
            sql: 'non_empty(' . $this->context->getLangLang2('t.') . ')'
        ));
        echo $list->getTable();

        $form = new \BayCMS\Fieldset\Form(
            $this->context,
            table: $table,
            qs: 'tab=' . $_GET['tab'] . '&id=' . $_GET['id']
        );
        $form->addField(new \BayCMS\Field\SelectJS(
            $this->context,
            'id',
            '../../intern/gru/object_type.php',
            $this->t('Object Type', 'Objektart')
        ));
        echo $form->getForm($this->t('New Entry', 'Neuer Eintrag'));

    }

    private function tabGroup($members = false)
    {

        $action = $_GET['aktion'] ?? '';
        if ($action == 'del') {
            try {
                pg_query_params(
                    $this->context->getRwDbConn(),
                    'delete from benutzer_gruppe where id_gruppe=$1 and id_benutzer=$2',
                    $members ? [$_GET['id'], $_GET['id2']] : [$_GET['id2'], $_GET['id']]
                );
            } catch (\Exception $e) {
                $this->context->TE->printMessage($e->getMessage(), 'danger');
            }
        }
        if ($action == 'save') {
            $res = pg_query_params(
                $this->context->getDbConn(),
                'select 1 where check_objekt($1,$2)',
                [$_POST['id'], $this->context->getUserId()]
            );
            if (!pg_num_rows($res)) {
                $this->context->TE->printMessage($this->t(
                    'Missing rights to save group membership',
                    'Unzureichende Rechte für Gruppenmitgliedschaft'
                ), 'danger');
                $action = '';
            }
        }

        if ($action == 'save') {
            try {
                pg_query_params(
                    $this->context->getRwDbConn(),
                    'insert into benutzer_gruppe(id_gruppe,id_benutzer) values ($1,$2)',
                    $members ? [$_GET['id'], $_POST['id']] : [$_POST['id'], $_GET['id']]
                );
            } catch (\Exception $e) {
                $this->context->TE->printMessage($e->getMessage(), 'danger');
            }
        }

        $list = new \BayCMS\Fieldset\BayCMSList(
            $this->context,
            'benutzer t, benutzer_gruppe bg',
            't.id=bg.id_' . ($members ? 'benutzer' : 'gruppe') . ' and bg.id_' . ($members ? 'gruppe' : 'benutzer') . '=' . $_GET['id'],
            write_access_query: ($this->user['check_objekt'] ?? '') == 't' ? 'true' : 'false',
            actions: ['del'],
            id_name: 'id2',
            id_query: 't.id',
            qs: 'tab=' . $_GET['tab'] . '&id=' . $_GET['id'],
            jquery_row_click: 1
        );
        $list->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'user',
            $this->t('User/Group', 'Nuzter/Gruppe'),
            sql: "'<img src=\"/" . $this->context->org_folder . "/de/image/'||case when t.gruppe then 'group' else 
        'user' end||'.png\" alt=\"'||t.kommentar||'\" title=\"'||t.kommentar||'\"> '||t.login||' ('||t.kommentar||')'"
        ));
        echo $list->getTable();

        $form = new \BayCMS\Fieldset\Form(
            $this->context,
            qs: 'tab=' . $_GET['tab'] . '&id=' . $_GET['id']
        );
        $form->addField(new \BayCMS\Field\SelectJS(
            $this->context,
            'id',
            '../../intern/gru/user_select.php?js_select=1&gr_only=' . ($members ? 0 : 1),
            $members ? $this->t('New group member', 'Neues Gruppenmitglied') :
            $this->t('New group membershit', 'Neue Gruppenmitgliedschaft')
        ));
        echo $form->getForm();
    }








    /**
     * Page funtion.
     * 
     * The function prints a page when the user exists.
     * It returns when the user is e.g deleted or not existant.
     * @return void
     */
    public function page()
    {
        if (isset($_GET['id2']) && !isset($_GET['aktion']))
            $_GET['id'] = $_GET['id2'];

        if(! ($_GET['id']??false)) return;

        $this->context->printHeader();
        if (($_GET['tab'] ?? '') == 'basic' && ($_GET['aktion'] ?? '') == 'del') {
            $this->delUser();
            if(! ($_GET['id']??false)) return;
        }
        $res = pg_query_params(
            $this->context->getDbConn(),
            'select *,check_objekt($1,$2) from benutzer where id=$1',
            [$_GET['id'], $this->context->getUserId()]
        );
        if (!pg_num_rows($res))
            return;

        echo "<div style=\"float:right\">" . $this->context->TE->getActionLink('?', $this->t('Back to list', 'Zurück zur Liste'), '', 'arrow-left') . '</div>';
        $this->user = pg_fetch_array($res, 0);
        echo "<h3><img src=\"/" . $this->context->org_folder . "/de/image/" .
            ($this->user['gruppe'] == 't' ? 'group' : 'user') . ".png\"> " . $this->user['login'] . " (" . $this->user['kommentar'] . ")</h3>";

        $tab = ['basic', 'access'];
        if ($this->user['check_objekt'] == 't') {
            array_push($tab, 'password', 'group', 'ip', 'admin', 'no_create');
            if ($this->user['gruppe'] == 't')
                array_push($tab, 'members');

        }

        $nav = new \BayCMS\Util\TabNavigation(
            $this->context,
            $tab,
            $this->context->lang == 'de' ?
            ['Grunddaten', 'Zugangsrechte', 'Passwort', 'Gruppen', 'IP-Authensisierung', 'Admin-Rechte', 'Kein Erzeugen von', 'Gruppenmitglieder'] :
            ['Basic Data', 'Rights', 'Password', 'Groups', 'IP Authentification', 'Admin rights', 'No create of', 'Group Members'],
            qs: 'id=' . $_GET['id']
        );
        echo $nav->getNavigation();
        $tab = $_GET['tab'] ?? 'basic';

        switch ($tab) {
            case 'basic':
                $this->tabBasic();
                break;
            case 'access':
                $this->tabAccess();
                break;
            case 'password':
                $this->tabPassword();
                break;
            case 'group':
            case 'members':
                $this->tabGroup($tab == 'members');
                break;

            case 'ip':
                $this->tabIP();
                break;
            case 'admin':
            case 'no_create':
                $this->tabFine($tab == 'admin' ? 'admin_objekt' : 'no_create_objekt');
                break;
        }

        $this->context->printFooter();
    }
}