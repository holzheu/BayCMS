<?php

namespace BayCMS\Page\Admin;

use function PHPUnit\Framework\returnSelf;

class HtmlPages extends \BayCMS\Page\Page
{
    protected string $qs;
    public function __construct(\BayCMS\Base\BayCMSContext $context, string $qs = '')
    {
        $this->context = $context;
        $this->qs = $qs;
    }
    public function pageStart(string $pre_content = '')
    {
        $this->context->printHeader();
        echo $pre_content;
        $_GET['id'] = $this->context->getOrgId();
        $this->edit('start');
        $this->detail('start');
        $this->context->printFooter();
    }

    public function pageImprint(string $pre_content = '')
    {
        $this->context->printHeader();
        echo $pre_content;
        $config = $this->context->getModConfig('gru', 'impressum_id');
        $_GET['id'] = $config['impressum_id'];
        if ($_GET['id']) {
            $res = pg_query_params(
                $this->context->getDbConn(),
                'select id from objekt' . $this->context->getOrgId() . ' where id=$1',
                [$_GET['id']]
            );
            if (!pg_num_rows($res))
                $_GET['id'] = '';
        }


        if (!$_GET['id']) {
            $_POST['de'] = 'Impressum';
            $_POST['en'] = 'Imprint';
            $_POST['text_de'] = \BayCMS\Util\Imprint::de();
            $_GET['aktion'] = 'save';
            $_POST['min_power']=0;
        }
        $this->edit('imprint');
        if ($_GET['id'] != $config['impressum_id']) {
            pg_query_params(
                $this->context->getRwDbConn(),
                'delete from modul_ls_config where 
                id_lehr=$1 and id_modconfig = (select id from modul_default_config where mod=$2 and uname=$3)',
                [$this->context->getOrgId(), 'gru', 'impressum_id']
            );
            pg_query_params(
                $this->context->getRwDbConn(),
                'insert into modul_ls_config(id_modconfig,id_lehr,value) select
                id,$1,$2 from modul_default_config where mod=$3 and uname=$4',
                [$this->context->getOrgId(), $_GET['id'], 'gru', 'impressum_id']
            );
        }
        $this->detail('imprint');
        $this->context->printFooter();
    }

    public function editForm($type = 'html')
    {
        $form = new \BayCMS\Fieldset\Form(
            $this->context,
            table: 'html_seiten',
            uname: 'html_seite',
            delete_button: $type == 'html',
            object_button: $type == 'html',
            qs: $this->qs
        );


        if ($type == 'html') {
            $form->addField(new \BayCMS\Field\BilangInput(
                $this->context,
                '',
                $this->t('Title', 'Titel'),
                non_empty: 1
            ));
            $form->addField(new \BayCMS\Field\Select(
                $this->context,
                'min_power',
                $this->t('Visible', 'Sichtbarkeit'),
                db_query:
                "select 0 as id,'" . $this->t('External', 'Extern') . "' as description union 
            select power,non_empty(" . $this->context->getLangLang2('') . ") from power 
            where power <=" . $this->context->getPower() . " order by id"
            ));
        } else {
            $form->addField(new \BayCMS\Field\Hidden(
                $this->context,
                'de'
            ));
            $form->addField(new \BayCMS\Field\Hidden(
                $this->context,
                'en'
            ));
            $form->addField(new \BayCMS\Field\Hidden(
                $this->context,
                'min_power',
                default_value: 0,
                type: 'integer'
            ));
        }

        $form->addField(new \BayCMS\Field\BilangTextarea(
            $this->context,
            'text_',
            $this->t('Page Text', 'Seiten Text'),
            input_options: ['de' => " rows=25 cols=80", 'en' => " rows=25 cols=80"],
            non_empty: 1
        ));
        $tiny = new \BayCMS\Util\TinyMCE($this->context);
        $form->addField(new \BayCMS\Field\Checkbox(
            $this->context,
            'disable_editor',
            $this->t('Disable Editor for this page', 'Editor für diese Seite ausschalten'),
            post_input: '
            <script language="javascript" type="text/javascript" src="/baycms-tinymce4/tinymce.min.js"></script>
            <script>
            function set_editor(){
               if($("#form1_disable_editor").is(":checked")){
                 tinymce.remove("#form1_text_de");
                 tinymce.remove("#form1_text_en");
               } else {
               ' . $tiny->getInitFull("textarea", id_parent: ($_GET['id'] ?? 0)) . '
               }
            }
 
            $("#form1_disable_editor").change(set_editor);
            $(document).ready(set_editor);
          </script>           
            
            '
        ));
        return $form;
    }
    public function detail($type = 'html')
    {
        $id = $_GET['id'] ?? 0;
        if (!$id)
            return;

        $res = pg_query_params(
            $this->context->getDbConn(),
            'select non_empty(' . $this->context->getLangLang2('h.text_') . ') as text, 
            h.min_power,i.id as index_id
            from html_seiten h, objekt o left outer join index_files i on o.id=i.id_obj 
            and i.id_lehr=' . $this->context->getOrgId() . '
             where h.id=o.id and o.geloescht is null and h.id=$1',
            [$id]
        );
        $r = pg_fetch_array($res, 0);

        echo $this->context->TE->getActionLink(
            '?aktion=edit&id=' . $id . '&' . $this->qs,
            $this->t('edit', 'bearbeiten'),
            '',
            'edit'
        );
        if ($type == 'html')
            echo $this->context->TE->getActionLink(
                '?aktion=del&id=' . $id . '&' . $this->qs,
                $this->t('delete', 'löschen'),
                ' onClick="return confirm(\'' . $this->t('Are you sure?', 'Sind Sie sicher') . '\');"',
                'del'
            );

        if ($r['index_id'])
            echo $this->context->TE->getActionLink(
                $_SERVER['SCRIPT_NAME'] . '?aktion=edit&id=' . $r['index_id'],
                $this->t('edit index', 'Indexeintrag bearbeiten'),
                '',
                'edit'
            );
        echo "<hr/>";

        echo $this->context->TE->htmlPostprocess($r['text']);

    }
    public function edit($type = 'html')
    {
        if (!isset($_GET['aktion']))
            return;
        $id = $_GET['id'] ?? false;

        if ($_GET['aktion'] == 'del' && $type == 'html') {
            $obj = new \BayCMS\Base\BayCMSObject($this->context);
            $obj->load($id);
            try {
                $obj->delete();
                $this->context->TE->printMessage($this->t('HTML-page deleted', 'HTML-Datei gelöscht'));
            } catch (\Exception $e) {
                $this->context->TE->printMessage($e->getMessage(), 'danger');
            }
            return;
        }

        $form = $this->editForm($type);

        if ($id) {
            $res = pg_query_params(
                $this->context->getDbConn(),
                'select * from html_seiten where id=$1',
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
            try {
                $_GET['id'] = $form->save();
                $this->context->TE->printMessage($this->t('Page saved', 'Seite gespeichert'));
                return;
            } catch (\Exception $e) {
                $this->context->TE->printMessage($e->getMessage(), 'danger');
            }
        }
        echo $form->getForm();
        $this->context->printFooter();

    }

    public function page(string $pre_content = '')
    {

        $search = $_GET['search'] ?? '';
        if (!$search)
            $search = $_GET['json_query'] ?? '';
        $non_empty = "non_empty(" . $this->context->getLangLang2('t.') . ")";
        $search = pg_escape_string($this->context->getDbConn(), $search);
        if ($search) {
            $search = " and $non_empty ilike '%$search%'";
        }
        $sform = new \BayCMS\Fieldset\Form($this->context);
        $sform->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'search'
        ));


        $list = new \BayCMS\Fieldset\BayCMSList(
            $this->context,
            'objekt_verwaltung' . $this->context->getOrgId() . ' o, html_seiten t',
            'o.id=t.id and o.id not in (select id from lehrstuhl)' . $search,
            write_access_query: 'check_objekt(t.id,' . $this->context->getUserId() . ')',
            actions: ['edit'],
            id_query: 't.id',
            jquery_row_click: 1,
            new_button: 1,
            order_by: [$non_empty],
            qs: $this->qs
        );
        if ($_GET['json_query'] ?? false) {
            $list->addField(new \BayCMS\Field\TextInput(
                $this->context,
                'value',
                sql: $non_empty
            ));
            $list->addField(new \BayCMS\Field\TextInput(
                $this->context,
                'label',
                sql: $non_empty
            ));
            echo $list->getJSON();
            exit();
        }


        $this->context->printHeader();
        echo $pre_content;
        if ($_GET['js_select'] ?? false) {
            echo \BayCMS\Util\JSHead::get(tinyurl: '../gru/html.php?id_obj=');
        }
        $this->edit();
        $this->detail();

        $list->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'file',
            $this->t('HTML-Page', 'HTML-Seite'),
            sql: $non_empty
        ));


        $sform->setValues($_GET);
        echo $sform->getSearchForm();
        echo $list->getTable(id: $_GET['id'] ?? null);

        $this->context->printFooter();
    }

}