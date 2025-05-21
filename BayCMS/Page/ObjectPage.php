<?php

namespace BayCMS\Page;

class ObjectPage extends Page
{

    private \BayCMS\Base\BayCMSObject $obj;
    private bool $write_access;

    public function __construct(\BayCMS\Base\BayCMSContext $context, ?int $id = null)
    {
        parent::__construct($context);
        if ($id == null && ($_GET['id'] ?? false))
            $id = $_GET['id'];
        if ($id == null && ($_GET['id_obj'] ?? false))
            $id = $_GET['id_obj'];
        $this->obj = new \BayCMS\Base\BayCMSObject($context);
        try {
            $this->obj->load($id);
        } catch (\Exception $e) {
            $this->error(404, $e->getMessage());
        }

        $this->write_access = $this->obj->checkWriteAccess();
        //$this->write_access = false;

    }


    private function baseTab()
    {
        $form = new \BayCMS\Fieldset\Form(
            $this->context,
            table: 'objekt',
            qs: "tab=base",
            write_access_query: $this->write_access ? 'true' : 'false',
            cancel_button: 0,
            delete_button: 0
        );
        $form->addField(new \BayCMS\Field\BilangInput(
            $this->context,
            '',
            'Name'
        ));
        $form->addField(new \BayCMS\Field\BilangInput(
            $this->context,
            'og_title_',
            $this->t(
                'Short Title used for preview for Facebook...',
                'Kurzer Titel für Vorschau für Facebook...'
            )
        ));
        $form->addField(new \BayCMS\Field\BilangTextarea(
            $this->context,
            'og_description_',
            $this->t(
                'Short summary used for Facebook...',
                'Kurze Zusammenfassung für Facebook...'
            )
        ));
        $form->addField(new \BayCMS\Field\UploadImage(
            $this->context,
            'og_preview_img',
            $this->t('Preview Image', 'Vorschaubild'),
            height: 1200,
            theight: 200,
            help: 'Bestes Format für Facebook ist 1200px x 628px'
        ));
        $form->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'stichwort',
            $this->t('Key words', 'Stichworte')
        ));
        $form->addField(new \BayCMS\Field\Checkbox(
            $this->context,
            'sichtbar',
            $this->t('Visible', 'Sichtbar')
        ));
        $form->addField(new \BayCMS\Field\Checkbox(
            $this->context,
            'child_allowed',
            $this->t('Allow child objects', 'Anlegen von Kind-Objekten erlaubt')
        ));
        $form->addField(new \BayCMS\Field\Checkbox(
            $this->context,
            'write_all',
            $this->t('Write access for all users', 'Schreibzugriff für alle Nutzer')
        ));
        $form->addField(new \BayCMS\Field\Checkbox(
            $this->context,
            'set_ctime',
            $this->t('Set createtime to current time', 'Erzeugt auf aktuelle Zeit setzen'),
            no_add_to_query: 1
        ));

        $form->load($this->obj->id);
        if (($_GET['aktion'] ?? '') == 'save') {
            if ($form->setValues($_POST))
                $_GET['aktion'] = 'edit';
        }
        if (($_GET['aktion'] ?? '') == 'save') {

            if (!$form->save()) {
                $_GET['aktion'] = 'edit';
            } else {
                $update = [];
                if ($_POST['set_ctime'])
                    $update[] = 'ctime=now()';
                if ($og_img = $form->getField('og_preview_img')->getImageId())
                    $update[] = 'og_img=' . $og_img;
                if (count($update)) {
                    pg_query_params(
                        $this->context->getRwDbConn(),
                        'update objekt set ' . implode(',', $update) . ' where id=$1',
                        [$this->obj->id]
                    );
                }
                $this->context->TE->printMessage($this->t('Changes saved.', 'Änderungen gespeichert'));
            }

        }
        if (($_GET['aktion'] ?? '') == 'edit') {
            echo $form->getForm($this->t('Edit', 'Bearbeiten'));
            return;
        }

        echo $form->getTable(delete_link: 0, no_copy_link: 1);

    }

    private function accessTab()
    {
        if ($this->write_access) {
            if (($_POST['id_user'] ?? '') && $_GET['aktion'] == 'save') {
                try {
                    pg_query_params(
                        $this->context->getRwDbConn(),
                        'insert into zugriff(id_benutzer,id_obj) values ($1,$2)',
                        [$_POST['id_user'], $this->obj->id]
                    );
                    $this->context->TE->printMessage($this->t('Access granted', 'Zugriff gespeichert'));
                } catch (\Exception $e) {
                    $this->context->TE->printMessage($e->getMessage(), 'danger');
                }
            }

            if (($_GET['id_user'] ?? '') && $_GET['aktion'] == 'del') {
                try {
                    pg_query_params(
                        $this->context->getRwDbConn(),
                        'delete from zugriff where id_benutzer=$1 and id_obj=$2',
                        [$_GET['id_user'], $this->obj->id]
                    );
                    $this->context->TE->printMessage($this->t('Access removed', 'Zugriff gelöscht'));
                } catch (\Exception $e) {
                    $this->context->TE->printMessage($e->getMessage(), 'danger');
                }
            }
        }

        $form = new \BayCMS\Fieldset\Form(
            $this->context,
            table: 'zugriff',
            cancel_button: 0,
            qs: 'tab=access&id=' . $this->obj->id,
            submit: $this->t('add', 'hinzufügen')
        );
        $form->addField(new \BayCMS\Field\SelectJS(
            $this->context,
            'id_user',
            description: $this->t('User/Group', 'Benutzer/Gruppe'),
            target: '/' . $this->context->getOrgLinkLang() . '/intern/gru/user_select.php'
        ));

        $list = new \BayCMS\Fieldset\BayCMSList(
            $this->context,
            'benutzer t, zugriff z',
            't.id=z.id_benutzer and z.id_obj=' . $this->obj->id,
            write_access_query: $this->write_access ? 'true' : 'false',
            actions: ['del'],
            id_query: 't.id',
            step: -1,
            qs: 'tab=access&id=' . $this->obj->id,
            id_name: 'id_user',
            order_by: ['t.login']
        );

        $list->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'user',
            $this->t('User/Group', 'Benutzer/Gruppe'),
            sql: "'<img src=\"/" . $this->context->org_folder . "/de/image/'||case when t.gruppe then 'group' else 
            'user' end||'.png\" alt=\"'||t.kommentar||'\" title=\"'||t.kommentar||'\"> '||t.login||' ('||t.kommentar||')'"
        ));
        echo $list->getTable();
        if ($this->write_access)
            echo $form->getForm($this->t('Grant Access', 'Zugriff gewähren'));

    }


    private function assignTab()
    {
        $form = new \BayCMS\Fieldset\Form(
            $this->context,
            table: 'objekt_ls',
            id_name: 'id_org',
            qs: 'tab=assign&id=' . $this->obj->id,
            cancel_button: 0
        );
        $form->addField(new \BayCMS\Field\SelectJS(
            $this->context,
            'id_org',
            '/' . $this->context->getOrgLinkLang() . '/intern/gru/ls_select.php',
            $this->t('Organization', 'Organisation'),
            non_empty: 1,
            input_options: $this->write_access ? '' : ' disabled readonly',
            button: $this->write_access,
            db_query: 'select id,non_empty(de,en) as description from lehrstuhl'
        ));


        if (($_GET['aktion'] ?? '') == 'save' && ($_POST['id_org'] ?? '')) {

            if (($_POST['id_org_old'] ?? -1) == $_POST['id_org']) {
                if ($this->write_access)
                    pg_query_params(
                        $this->context->getRwDbConn(),
                        'update objekt_ls set 
                nur_lesen=' . (($_POST['nur_lesen'] ?? false) ? 'true' : 'false') .
                        ' where
                id_lehr=$1 and id_obj=$2 and (check_objekt($1,$3) or check_objekt($2,$3))',
                        [$_POST['id_org'], $this->obj->id, $this->context->getUserId()]
                    );
                if ($_POST['check_org'] == 't')
                    pg_query_params(
                        $this->context->getRwDbConn(),
                        'update objekt_ls set 
                sichtbar=' . (($_POST['sichtbar'] ?? false) ? 'true' : 'false') .
                        ' where
                id_lehr=$1 and id_obj=$2 and check_objekt($1,$3)',
                        [$_POST['id_org'], $this->obj->id, $this->context->getUserId()]
                    );


            } elseif ($this->write_access) {
                if ($_POST['id_org_old']) {
                    pg_query_params(
                        $this->context->getRwDbConn(),
                        'delete from objekt_ls  where
                id_lehr=$1 and id_obj=$2',
                        [$_POST['id_org_old'], $this->obj->id]
                    );
                }
                $res = pg_query_params(
                    $this->context->getRwDbConn(),
                    'select set_objekt_ls($1,$2,$3)',
                    [$this->obj->id, $_POST['id_org'], $this->context->getOrgId()]
                );
                if (pg_affected_rows($res)) {
                    $this->context->TE->printMessage($this->t('Assignment saved', 'Zuordnung gespeichert'));

                }
            }
        }

        if (($_GET['aktion'] ?? '') == 'del') {
            $res = pg_query_params(
                $this->context->getRwDbConn(),
                'delete from objekt_ls  where
        id_lehr=$1 and id_obj=$2 and (check_objekt($1,$3) or check_objekt($2,$3))',
                [$_GET['id_org'], $this->obj->id, $this->context->getUserId()]
            );
            if (pg_affected_rows($res))
                $this->context->TE->printMessage($this->t('Assignment deleted', 'Zuordnung gelöscht'));
        }

        if (($_GET['aktion'] ?? '') == 'edit') {
            if ($_GET['id_org'] ?? '') {
                $res = pg_query_params(
                    $this->context->getDbConn(),
                    'select *,check_objekt(id_obj,$3) as check_org from objekt_ls where id_lehr=$1 and id_obj=$2',
                    [$_GET['id_org'], $this->obj->id, $this->context->getUserId()]
                );
                $r = pg_fetch_array($res, 0);
                $r['id_org_old'] = $_GET['id_org'];
                $r['id_org'] = $_GET['id_org'];

                $form->addField(new \BayCMS\Field\Checkbox(
                    $this->context,
                    'sichtbar',
                    $this->t('visible', 'sichtbar'),
                    input_options: $r['check_org'] == 't' ? '' : ' disabled readonly'
                ));
                $form->addField(new \BayCMS\Field\Checkbox(
                    $this->context,
                    'nur_lesen',
                    $this->t('read only', 'nur lesend'),
                    input_options: $this->write_access ? '' : ' disabled readonly'
                ));
                $form->addField(new \BayCMS\Field\Hidden(
                    $this->context,
                    'id_org_old'
                ));
                $form->addField(new \BayCMS\Field\Hidden(
                    $this->context,
                    'check_org'
                ));
                $form->setValues($r);
                echo $form->getForm($this->t('Edit assignment', 'Zuordnung bearbeiten'));
                $this->context->printFooter();
            }

        }



        $non_empty = 'non_empty(' . $this->context->getLangLang2('t.') . ')';

        $list = new \BayCMS\Fieldset\BayCMSList(
            $this->context,
            'lehrstuhl t, objekt_ls o',
            't.id=o.id_lehr and o.id_obj=' . $this->obj->id,
            write_access_query: ($this->write_access ? 'true' : 'false') . ' or check_objekt(o.id_lehr,' . $this->context->getUserId() . ')',
            actions: ['edit', 'del'],
            id_query: 't.id',
            step: -1,
            new_button: false,
            qs: 'tab=assign&id=' . $this->obj->id,
            id_name: 'id_org',
            order_by: [$non_empty]
        );
        $list->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'org',
            $this->t('Organization', 'Organisation'),
            sql: $non_empty
        ));

        $then = " then '" . $this->t('Yes', 'Ja') . "' else '" . $this->t('No', 'Nein') . "' end";
        $list->addField(new \BayCMS\Field\Checkbox(
            $this->context,
            'sichtbar',
            $this->t('visible', 'sichtbar'),
            sql: 'case when o.sichtbar' . $then
        ));
        $then = " then '" . $this->t('read only', 'Nur lesen') . "' else '" . $this->t('With write access', 'mit Schreibzugriff') . "' end";

        $list->addField(new \BayCMS\Field\Checkbox(
            $this->context,
            'nur_lesen',
            $this->t('Type', 'Art'),
            sql: 'case when o.nur_lesen' . $then
        ));
        echo $list->getTable();
        if ($this->write_access)
            echo $form->getForm($this->t('New Assignment', 'Neue Zuordnung'));


    }

    private function hierarchyTab()
    {
        $non_empty_t = 'non_empty(' . $this->context->getLangLang2('t.') . ')';
        $non_empty_ao = 'non_empty(' . $this->context->getLangLang2('ao.') . ')';
        $res = pg_query(
            $this->context->getDbConn(),
            "select t.id,$non_empty_t,$non_empty_ao from objekt t, art_objekt ao, objekt o2 where t.id_art=ao.id and t.id=o2.id_obj and o2.id=" . $this->obj->id
        );
        echo "<h3>" . $this->t('Parent Object', 'Elternobjekt') . "</h3>";
        if (pg_num_rows($res)) {
            $r = pg_fetch_row($res, 0);
            echo "<a href=\"?tab=hierarchy&id=$r[0]\">$r[2]: $r[1]</a>";
        } else
            echo $this->t('No parent object', 'Kein Elternobjekt');

        $res = pg_query(
            $this->context->getDbConn(),
            "select t.id,$non_empty_t,$non_empty_ao from objekt t, art_objekt ao where t.id_art=ao.id and t.id_obj=" . $this->obj->id
        );
        echo "<h3>" . $this->t('Child Objects', 'Kindobjekte') . "</h3>";

        if (pg_num_rows($res)) {
            for ($i = 0; $i < pg_num_rows($res); $i++) {
                $r = pg_fetch_row($res, $i);
                echo "<a href=\"?tab=hierarchy&id=$r[0]\">$r[2]: $r[1]</a><br/>";
            }

        } else
            echo $this->t('No child objects', 'Keine Kindobjekte');
    }

    private function filesTab()
    {
        $non_empty_t = 'non_empty(' . $this->context->getLangLang2('t.') . ')';
        $res = pg_query(
            $this->context->getDbConn(),
            "select t.id,$non_empty_t as link, get_filetype_image(t.name), t.name from file t, objekt o where t.id=o.id and o.id_obj=" . $this->obj->id
        );
        if (pg_num_rows($res)) {
            for ($i = 0; $i < pg_num_rows($res); $i++) {
                $r = pg_fetch_array($res, $i);
                echo "<a href=\"/" . $this->context->getOrgLinkLang() . "/top/gru/get.php?f=";
                echo urlencode($this->context->BayCMSRoot . '/' . $r['name']) . "&n=" .
                    urlencode($r['link']) . "\" target=\"_blank\">$r[get_filetype_image] $r[link]</a><br/> ";
            }

        } else
            echo $this->t('No files', 'Keine Dateien');
    }

    private function imagesTab()
    {
        $non_empty = 'non_empty(' . $this->context->getLangLang2('') . ')';
        $res = pg_query(
            $this->context->getDbConn(),
            "select *,$non_empty from bild where id_obj=" . $this->obj->id
        );
        if (pg_num_rows($res)) {
            for ($i = 0; $i < pg_num_rows($res); $i++) {
                $r = pg_fetch_array($res, $i);
                $url = '/' . $this->context->org_folder . '/de/';
                if ($r['intern'] == 't')
                    $url .= 'intern/gru/get_image.php?i=';
                else
                    $url .= 'image/';
                if ($r['tx'])
                    $url .= 't';
                $url .= $r['name'];
                echo '<a href="../../intern/gru/image.php?id=' . $r['id'] . '"><div style=" vertical-align: middle; text-align: center; padding:5px; margin: 2px; float:left; height:120px; width: 120px';
                if ($r['intern'] == 't')
                    echo '; background-color:#ddd';
                echo '"><img src="' . $url . '" style="max-width:100%; max-height:100%;"></div></a>' . "\n";

            }

        } else
            echo $this->t('No images', 'Keine Bilder');
    }

    public function page()
    {
        $this->context->printHeader();
        $tfm = $this->t("YYYY-MM-DD - HH24:MI", "DD. MM. YYYY - HH24:MI");
        $tfm_d = $this->t("YYYY-MM-DD", "DD. MM. YYYY");
        $objekt_query = "select o.*,non_empty(" . $this->context->getLangLang2('o.') . ") as title,
        to_char(o.ctime,'$tfm') as fctime,to_char(o.geloescht,'$tfm') as fdtime,
        to_char(o.utime,'$tfm') as futime,
to_char(o.dtime,'$tfm_d') as al,
b1.kommentar as cuser,b2.kommentar as uuser,
f1.name as viewfile,f2.name as editfile,
non_empty(" . $this->context->getLangLang2('ao.') . ") as type
from objekt o left outer join benutzer b1 on b1.id=o.id_benutzer 
left outer join benutzer b2 on b2.id=o.id_ubenutzer, art_objekt ao 
left outer join file f1 on f1.id=ao.view_file 
left outer join file f2 on f2.id=ao.edit_file 
where o.id=\$1 and o.id_art=ao.id";

        $res = pg_query_params(
            $this->context->getDbConn(),
            $objekt_query,
            [$this->obj->id]
        );
        $r = pg_fetch_array($res, 0);
        echo "<h3>" . $this->t('Object', 'Objekt') . ": $r[title]</h3>
        " . $this->t('Object type', 'Objektart') . ": $r[type]<br>
        " . $this->t('Created', 'Erzeugt') . ": $r[fctime] - $r[cuser]<br>
        " . $this->t('Last modified', 'Zuletzt geändert') . ": $r[futime] - $r[uuser]<br>\n";
        if ($r['fdtime'])
            echo $this->t('Deleted', 'Gelöscht') . ": $r[fdtime]<br>\n";
        if ($r['viewfile'])
            echo $this->context->TE->getActionLink(
                "/" . $this->context->getOrgLinkLang() . "/$r[viewfile]?id_obj=" . $this->obj->id,
                $this->t('show', 'anzeigen'),
                '',
                'eye-open'
            ) . " ";
        if ($r['editfile'])
            echo $this->context->TE->getActionLink(
                "/" . $this->context->getOrgLinkLang() . "/$r[editfile]?id_obj=" . $this->obj->id,
                $this->t('edit', 'bearbeiten'),
                '',
                'edit'
            ) . " ";
        echo "<br/><br/>";

        $nav = new \BayCMS\Util\TabNavigation(
            $this->context,
            ['base', 'access', 'assign', 'hierarchy', 'files', 'images'],
            [
                $this->t('Basic Properties', 'Grunddaten'),
                $this->t('Access', 'Rechte'),
                $this->t('Assignment', 'Zuordnung'),
                $this->t('Hierarchy', 'Hierarchie'),
                $this->t('Files', 'Dateien'),
                $this->t('Images', 'Bilder')
            ],
            qs: 'id=' . $this->obj->id
        );
        echo $nav->getNavigation();
        $tab = $_GET['tab'] ?? 'base';

        if ($tab == 'base')
            $this->baseTab();
        if ($tab == 'access')
            $this->accessTab();
        if ($tab == 'assign')
            $this->assignTab();
        if ($tab == 'hierarchy')
            $this->hierarchyTab();
        if ($tab == 'files')
            $this->filesTab();
        if ($tab == 'images')
            $this->imagesTab();

        $this->context->printFooter();
    }
}