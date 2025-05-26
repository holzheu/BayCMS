<?php

namespace BayCMS\Page\Admin;

class ModulFiles extends \BayCMS\Page\Page
{
    protected string $qs;
    public function __construct(\BayCMS\Base\BayCMSContext $context, string $qs=''){
        $this->context=$context;
        $this->qs=$qs;
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
            'file f, kategorie k, objekt o, modul m',
            'k.id='.$_GET['id_kat'].' and f.id=o.id and o.id_obj=m.id and f.index_file and o.geloescht is null and f.id_kat=k.id'. $search,
            id_query: 'f.id',
            jquery_row_click: 1,
            order_by: [$non_empty],
            qs:$this->qs,
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
        $list->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'mod',
            $this->t('Module', 'Modul'),
            sql: 'm.name'
        ));

        $sform->setValues($_GET);
        echo $sform->getSearchForm();
        echo $list->getTable(id: $_GET['id'] ?? null);

        $this->context->printFooter();
    }
}