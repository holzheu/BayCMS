<?php

namespace BayCMS\Page\Admin;

class IndexFiles extends \BayCMS\Page\Page
{
    protected string $qs;
    public function __construct(\BayCMS\Base\BayCMSContext $context, string $qs=''){
        $this->context=$context;
        $this->qs=$qs;
    }
    private function edit()
    {
        if (!isset($_GET['aktion']))
            return;
        $id = $_GET['id'] ?? false;

        if ($_GET['aktion'] == 'del') {
            $file = new \BayCMS\Base\BayCMSFile($this->context);
            $file->load($id);
            try {
                $file->erase(true);
                $this->context->TE->printMessage($this->t('File deleted', 'Datei gelöscht'));
            } catch (\Exception $e) {
                $this->context->TE->printMessage($e->getMessage(), 'danger');
            }
            return;
        }

        $form = new \BayCMS\Fieldset\Form(
            $this->context,
            qs:$this->qs
        );
        $form->addField(new \BayCMS\Field\BilangInput(
            $this->context,
            '',
            'Name',
            non_empty: 1
        ));
        $form->addField(new \BayCMS\Field\Upload(
            $this->context,
            'file',
            $this->t('File', 'Datei'),
            non_empty: !$id
        ));
        $form->addField(new \BayCMS\Field\Checkbox(
            $this->context,
            'extract_zip',
            $this->t('Extract zip-files', 'Zip-Dateien entpacken')
        ));
        $form->addField(new \BayCMS\Field\Checkbox(
            $this->context,
            'no_add_id_obj',
            $this->t('No automatic name generation', 'Keine automatisch Namesgenerierung')
        ));
        $lang = $this->context->lang;
        $lang2 = $this->context->lang2;
        $id_org = $this->context->getOrgId();
        $form->addField(new \BayCMS\Field\Select(
            $this->context,
            'id_kat',
            $this->t('Category', 'Kategorie'),
            db_query: "select k.id,non_empty(non_empty(a.$lang,k.$lang),non_empty(a.$lang2,k.$lang2)) as description
            from kategorie k left outer join kat_aliases a on k.id=a.id_kat and k.id_lehr=$id_org
            where k.id>100",
            null: 1,
            non_empty: 1
        ));
        $form->addField(new \BayCMS\Field\Textarea(
            $this->context,
            'beschreibung',
            $this->t('Description', 'Beschreibung')
        ));

        if ($id) {
            $res = pg_query_params(
                $this->context->getDbConn(),
                'select *,not (name ilike \'%/\'||id||\'/%\') as no_add_id_obj from file where id=$1',
                [$id]
            );
            if (!pg_num_rows($res))
                return;
            $r = pg_fetch_array($res, 0);
            $form->setValues($r);
            $form->setId($id);
        }

        if ($_GET['aktion'] == 'save') {
            if ($form->setValues($_POST))
                $_GET['aktion'] = 'edit';
        }

        if ($_GET['aktion'] == 'save') {
            $file = new \BayCMS\Base\BayCMSFile($this->context);
            if ($id)
                $file->load($id);
            $source = $form->getField('file')->getFileLocation();
            if ($source)
                $file->set(['source' => $source]);
            $name = $form->getField('file')->getFileName();
            if ($name)
                $file->set(['name' => $name]);

            $res = pg_query_params(
                $this->context->getDbConn(),
                'select link from kategorie where id=$1',
                [$_POST['id_kat']]
            );
            [$link] = pg_fetch_row($res, 0);
            $file->set([
                'de' => $_POST['de'],
                'en' => $_POST['en'],
                'path' => $link . '/' . $this->context->getOrgId(),
                'description' => $_POST['beschreibung'],
                'id_parent' => $this->context->getOrgId(),
                'add_id_obj' => ($_POST['no_add_id_obj'] ?? false) ? 0 : 1,
                'extract' => ($_POST['extract_zip'] ?? false) ? 1 : 0
            ]);
            try {
                $_GET['id'] = $file->save();
                $this->context->TE->printMessage($this->t('File saved', 'Datei gespeichert'));
                return;
            } catch (\Exception $e) {
                $this->context->TE->printMessage($e->getMessage(), 'danger');
            }

        }

        echo $form->getForm();

    }
    public function page(string $pre_content = '')
    {
        
        $search = $_GET['search'] ?? '';
        if (!$search)
            $search = $_GET['json_query'] ?? '';
        $non_empty = "non_empty(" . $this->context->getLangLang2('f.') . ")";
        $search = pg_escape_string($this->context->getDbConn(), $search);
        if ($search) {
            $search = " and $non_empty ilike '%$search%'";
        }
        $sform = new \BayCMS\Fieldset\Form($this->context,qs:$this->qs);
        $sform->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'search'
        ));

        $list = new \BayCMS\Fieldset\BayCMSList(
            $this->context,
            'file f, kategorie k, objekt_verwaltung' . $this->context->getOrgId() . ' o',
            'f.id=o.id and f.id_kat=k.id and o.id_obj=' . $this->context->getOrgId() . $search,
            write_access_query: 'true',
            actions: ['edit'],
            id_query: 'f.id',
            jquery_row_click: 1,
            new_button: 1,
            order_by: [$non_empty],
            data_id_query:($_GET['js_select']??'')=='tiny'?"'/".$this->context->getOrgLinkLang()."/'||f.name":''
        );
        if($_GET['json_query']??false){
            $list->addField(new \BayCMS\Field\TextInput(
                $this->context,
                'value',
                sql:$non_empty
            ));
            $list->addField(new \BayCMS\Field\TextInput(
                $this->context,
                'label',
                sql:$non_empty
            ));
            echo $list->getJSON();
            exit();
        }


        $this->context->printHeader();
        echo $pre_content;
        if ($_GET['js_select'] ?? false) {
            echo \BayCMS\Util\JSHead::get();
        }
        $this->edit();

        $list->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'file',
            $this->t('File', 'Datei'),
            sql: "'<a target=\"_blank\" href=\"/" . $this->context->getOrgLinkLang() . "/'||f.name||'\">'||
            get_filetype_image(f.name)||' '||non_empty(" . $this->context->getLangLang2('f.') . ")||'</a>'"
        ));
        $list->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'kat',
            $this->t('Category', 'Kategorie'),
            sql: "non_empty(" . $this->context->getLangLang2('k.') . ")"
        ));

        $sform->setValues($_GET);
        echo $sform->getSearchForm();
        echo $list->getTable(id: $_GET['id'] ?? null);

        $this->context->printFooter();
    }
}