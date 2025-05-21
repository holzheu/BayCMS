<?php

namespace BayCMS\Fieldset;

use BayCMS\Field\Field;

class QBDomain extends Domain
{
    protected \BayCMS\Fieldset\QueryBuilder $qb;

    public function __construct(
        \BayCMS\Base\BayCMSContext $context,
        string $table,
        string $name,
        string $uname = '',
        string $id_name = 'id',
        string $qs = '',
        bool $delete_button = true,
        ?bool $object_button = null,
        ?string $write_access_query = null,
        ?string $from = null,
        ?string $where = null,
        bool $copy = true,
        string $comment = '',
        bool $no_table_on_edit = true,
        ?string $tinyurl = null,
        bool $no_create_on_upload = false,
        bool $erase_object = false,
        bool $headline = false
    ) {
        parent::__construct(
            $context,
            $table,
            $name,
            $uname,
            $id_name,
            $qs,
            $delete_button,
            $object_button,
            $write_access_query,
            $from,
            $where,
            $copy,
            $comment,
            $no_table_on_edit,
            $tinyurl,
            $no_create_on_upload,
            $erase_object,
            $headline
        );
        $this->qb = new \BayCMS\Fieldset\QueryBuilder(
            $context,
            $table,
            object: $uname > '',
            row_click_query: 't.id'
        );
        $this->qb->addNavigationTab($name, $_SERVER['SCRIPT_NAME']);
    }

    public function addField(Field $field, ?int $index = null, bool $edit_field = true, bool $list_field = false, bool $search_field = false, string $setvalue_sql = ''): Field
    {
        $f = parent::addField($field, $index, $edit_field, $list_field, $search_field, $setvalue_sql);
        if (strstr(get_class($f), 'Bilang')) {
            foreach ($f->getFields() as $f2) {
                $f2 = clone ($f2);
                $f2->set(['list_field' => $list_field]);
                $f2->setDivID($this->t('Fields', 'Felder'));
                $this->qb->addField($f2);
            }
        } else {
            $f2 = clone ($f);
            $f2->setDivID($this->t('Fields', 'Felder'));
            $this->qb->addField($f2);
        }
        return $f;
    }

    public function page(string $pre_content='', string $post_content='')
    {
        $path = explode('/', $_SERVER['PATH_INFO'] ?? '/');
        if (in_array($path[1], ['Query', 'Document', 'Email'])) {
            if (isset($_GET[$this->id_name])) {
                $res = pg_query_params(
                    $this->context->getDbConn(),
                    'select id from ' . $this->table . ' where id=$1',
                    [$_GET[$this->id_name]]
                );
                if (pg_num_rows($res)) {
                    header("Location:" . $_SERVER['SCRIPT_NAME'] . "?" . $this->id_name . '=' . $_GET[$this->id_name]);
                    exit();
                }
            }
            $this->qb->page();
        }

        $this->pagePreHeader();
        $this->context->printHeader();
        echo $this->qb->getTabNavigation();
        echo $pre_content;
        $this->pagePostHeader($post_content);
    }



}