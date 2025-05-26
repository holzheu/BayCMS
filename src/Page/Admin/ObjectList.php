<?php

namespace BayCMS\Page\Admin;

class ObjectList extends \BayCMS\Page\Page
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
        $non_empty = "non_empty(" . $this->context->getLangLang2('o.') . ")";
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
            'file f, objekt'.$this->context->getOrgId().' o, art_objekt ao',
            $non_empty.'>\'\' and f.id=ao.view_file and o.id_art=ao.id'. $search,
            id_query: 'o.id',
            jquery_row_click: 1,
            order_by: [$non_empty],
            qs:$this->qs,
            data_id_query:($_GET['js_select']??false)=='tiny'?"'/".$this->context->getOrgLinkLang()."/'||f.name||'?id_obj='||o.id":''
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
            'object',
            $this->t('Object', 'Objekt'),
            sql: $non_empty
        ));
        $list->addField(new \BayCMS\Field\TextInput(
            $this->context,
            'kat',
            $this->t('Type', 'Art'),
            sql: "non_empty(" . $this->context->getLangLang2('ao.') . ")"
        ));
        

        $sform->setValues($_GET);
        echo $sform->getSearchForm();
        echo $list->getTable(id: $_GET['id'] ?? null);

        $this->context->printFooter();
    }
}