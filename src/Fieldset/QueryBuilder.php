<?php
/**
 * QueryBuilder Class
 * 
 * QB is based on a base table. You can select column, add sql-columns,
 * filter rows and change the order.
 * 
 * 
 * 
 */



namespace BayCMS\Fieldset;
use BayCMS\Page\Document;
use BayCMS\Page\Email;

class QueryBuilder extends TabFieldset
{
    protected ?int $id = null;
    protected ?string $query_name;
    protected \BayCMS\Fieldset\Form $form;
    protected array $values;
    protected \BayCMS\Fieldset\BayCMSList $list;

    protected int $num_order;
    protected int $num_expert;
    protected int $num_filter;
    protected int $num_prefilter;
    protected int $num_postfilter;
    protected array $email_fields;
    protected bool $object;
    protected string $row_click_query;
    protected string $id_query;
    protected string $name;
    protected ?string $table;
    protected string $list_from;
    protected string $list_where;
    protected array $prefilter; //format [['name','sql'],...]
    protected array $postfilter; //format [['name','sql'],...]
    protected array $order_by = [1];
    private string $postfilter_sql = '';
    private string $prefilter_sql = '';
    protected array $additional_fields = [];
    protected array $additional_tabs = [];
    protected string $mapper_class = '';


    /**
     * Create a new QB2-object
     * @param \BayCMS\Base\BayCMSContext $context
     * @param mixed $table
     * @param string $name
     * @param bool $object
     * @param int $num_order
     * @param int $num_expert
     * @param int $num_filter
     * @param int $num_prefilter
     * @param int $num_postfilter
     * @param array $email_fields
     * @param mixed $from
     * @param mixed $where
     * @param array $prefilter format [['name','sql'],...]
     * @param array $postfilter format [['name','sql'],...]
     * @param string $row_click_query
     * @param string $id_query
     */
    public function __construct(
        \BayCMS\Base\BayCMSContext $context,
        ?string $table = null,
        string $name = 'qb',
        bool $object = false,
        int $num_order = 3,
        int $num_expert = 3,
        int $num_filter = 3,
        int $num_prefilter = 3,
        int $num_postfilter = 3,
        array $email_fields = [],
        ?string $from = null,
        ?string $where = null,
        array $prefilter = [],
        array $postfilter = [],
        string $row_click_query = '',
        string $id_query = 't.id'
    ) {
        $this->context = $context;
        $this->table = $table;
        $this->name = $name;
        $this->object = $object;
        $this->num_order = $num_order;
        $this->num_expert = $num_expert;
        $this->num_filter = $num_filter;
        $this->num_prefilter = $num_prefilter;
        $this->num_postfilter = $num_postfilter;
        $this->email_fields = $email_fields;
        $this->prefilter = $prefilter;
        $this->postfilter = $postfilter;
        $this->list_from = $from ?? $table . " t" .
            ($object ? ", objekt_verwaltung" . $this->context->getOrgId() . " o" : '');
        $this->list_where = $where ?? ($object ? "t.id=o.id" : 'true');
        $this->row_click_query = $row_click_query;
        $this->id_query = $id_query;
        $this->values=[];
    }


    /**
     * Selects additional fields. Typically called by Email or Document to
     * avoid, that fields got deselected after the Email or Documant was created
     * @param array $additional_fields
     * @return void
     */
    public function setAdditionalFields(array $additional_fields)
    {
        $this->additional_fields = $additional_fields;
    }

    /**
     * Loads the query from the database
     * @param mixed $id
     * @throws \BayCMS\Exception\notFound
     * @return void
     */
    public function load(?int $id = null)
    {
        if (!is_null($id))
            $this->id = $id;
        if (is_null($this->id))
            return;

        $res = pg_query_params(
            $this->context->getRwDbConn(),
            'select * from qb2 where id=$1',
            [$this->id]
        );
        if (!pg_num_rows($res))
            throw new \BayCMS\Exception\notFound('No query for id=' . $this->id);

        $r = pg_fetch_array($res, 0);
        $this->query_name = $r['name'];
        $v = json_decode($r['json'], true);
        $this->values = $v;
        $this->setValues($v);
    }

    public function getName()
    {
        return $this->query_name;
    }

    public function isObject(): bool
    {
        return $this->object;
    }

    public function getEmailFields(): array
    {
        return $this->email_fields;
    }

    /**
     * Returns the Edit-HTML for the query
     * @return array{error: bool, error_message: string, html: string|array{error: bool|int, error_message: mixed, html: string}|array{error: bool|int, error_message: string, html: string}|bool|string}
     */
    public function getEdit(): mixed
    {
        $action = $_GET['aktion'] ?? false;
        if ($action == 'del' && ($_GET['id'] ?? false)) {
            $obj = new \BayCMS\Base\BayCMSObject($this->context);
            $obj->load($_GET['id']);
            $obj->delete();
            $action = false;
            unset($_GET['id']);
            return '';
        }

        $error = false;
        $error_message = '';
        $post_form = '';

        if (($_GET['id_qb1'] ?? false) && $this->mapper_class) {
            $m = new $this->mapper_class($this->context);
            $map = $m->map($_GET['id_qb1']);
            $this->values=$map['json'];
            $this->setValues($map['json']);
            $error_message .= $map['error'];
        }

        if ($_GET['id'] ?? false) {
            $this->load($_GET['id']);
        }
        $this->createEditForm();

        if (($_GET['id'] ?? false) && $action == 'edit') {
            $this->form->setValues($this->values);
        }
        if (($_GET['id_qb1'] ?? false) && $this->mapper_class) {
            $this->form->setValues($map['json']);
        }


        if ($action == 'save') {
            $error = $this->form->setValues($_POST);

            if (!$error) {
                $v = $this->form->getValues();
                $res = $this->setValues($v);
                if ($res) {
                    $error = true;
                    $error_message = $res[1];
                    $post_form = '<script>
                $("#' . $res[0] . '_tab").trigger("click")
                </script>';
                }
            }

            if (!$error) {
                $res = $this->checkSQLFields($_POST);
                if ($res) {
                    $error = true;
                    $error_message = $res[1];
                    $post_form = '<script>
                $("#' . $res[0] . '_tab").trigger("click")
                </script>';
                }
            }


            if ($error) {
                $action = 'edit';
            }

        }
        if ($action == 'save' && ($_POST['save_as_copy'] ?? false)) {
            $this->id = null;
            unset($_GET['id']);
        }
        if ($action == 'save') {
            $obj = new \BayCMS\Base\BayCMSObject($this->context);
            if ($this->id)
                $obj->load($this->id);
            $obj->set([
                'de' => $this->form->getField($this->name . '_qname')->getValue(),
                'uname' => 'qb2'
            ]);
            pg_query($this->context->getRwDbConn(), 'begin');
            $this->id = $obj->save(false);
            pg_query_params(
                $this->context->getRwDbConn(),
                ($_GET['id'] ?? false ?
                    'update qb2 set name=$2, class=$3, min_power=$4, description=$5, json=$6 where id=$1' :
                    'insert into qb2(id,name,class,min_power,description,json) values($1,$2,$3,$4,$5,$6)'),
                [
                    $this->id,
                    $this->form->getField($this->name . '_qname')->getValue(),
                    get_class($this),
                    $this->form->getField($this->name . '_qvis')->getValue(),
                    $this->form->getField($this->name . '_qdesc')->getValue(),
                    json_encode($this->form->getValues())
                ]
            );
            pg_query($this->context->getRwDbConn(), 'commit');
        }

        if ($action == 'edit') {
            $out = $this->form->getForm("Edit Query");
            $out .= $post_form;
            return ['html' => $out, 'error' => $error, 'error_message' => $error_message];
        }
        return false;
    }

    /**
     * creates the fieldtable used in help
     * @return string
     */
    public function getFieldTable()
    {
        $out = '<!-- INSERT_FIELDSET_TAB -->
        <table ' . $this->context->TE->getCSSClass('table') . '>
        ';
        $div_id = '';
        $this->divs = [];

        foreach ($this->fields as $f) {
            if ($div_id != $f->getDivID()) {
                if ($div_id)
                    $out .= "</table></div>";
                if (!isset($this->divs[$this->nameToId($f->getDivID())])) {
                    $this->divs[$this->nameToId($f->getDivID())] =
                        $this->div_names[$f->getDivID()] ?? $f->getDivID();
                }
                $out .= '</table>
                <div class="formclass_fieldset" id="' . $this->nameToId($f->getDivID()) . '" ' . ($div_id ? 'style="display:none;"' : '') . '>
                <table ' . $this->context->TE->getCSSClass('table') . '>
                ';
                $div_id = $f->getDivID();
            }
            $out .= '<tr><td>' . $f->getDescription(false) . '</td><td>' . htmlspecialchars($f->getSQL("t.", 'filter')) . '</td></tr>' . "\n";
        }

        $out = str_replace('<!-- INSERT_FIELDSET_TAB -->', $this->getFieldsetTab(), $out);
        $out .= '</table>';
        if ($div_id)
            $out .= "</div>";
        return $out;
    }

    /**
     * Creates the edit-form for the query
     * @return void
     */
    public function createEditForm(): void
    {
        $this->form = new Form(
            context: $this->context,
            table: 'qb2',
            uname: 'qb2',
            div_names: [
                'sql' => $this->t('SQL Fields', 'SQL Felder'),
                'prefilter' => $this->t('Prefilter', 'Vorfilter'),
                'postfilter' => $this->t('Postfilter', 'Nachfilter'),
                'order' => $this->t('Sorting', 'Sortierung')
            ],
            qs: ($_GET['id_qb1'] ?? false) ? 'id_qb1=' . $_GET['id_qb1'] : ''
        );
        $this->form->addField(new \BayCMS\Field\TextInput(
            $this->context,
            name: $this->name . '_qname',
            description: 'Query Name',
            non_empty: 1
        ));
        $this->form->addField(new \BayCMS\Field\Select(
            $this->context,
            name: $this->name . '_qvis',
            description: $this->t('Visibility', 'Sichtbarkeit'),
            db_query: 'select power as id,
                 non_empty(' . $this->context->lang . ',' . $this->context->lang2 . ') as description
                 from power where power<=1000 order by 1 desc',
            non_empty: 1
        ));
        $this->form->addField(new \BayCMS\Field\Textarea(
            $this->context,
            name: $this->name . '_qdesc',
            description: $this->t('Comment', 'Bemerkung')

        ));
        if ($_GET['id'] ?? false) {
            $this->form->addField(new \BayCMS\Field\Checkbox(
                $this->context,
                name: 'save_as_copy',
                no_add_to_query: 1,
                not_in_table: 1,
                description: $this->t('Save as new entry', 'Als neuen Eintrag speichern')
            ));
        }

        $field_values = [];
        foreach ($this->fields as $f) {
            $description = $f->getDescription(false);
            if (mb_strlen($description, 'UTF-8') > 70)
                $description = mb_substr($description, 0, 45, encoding: 'UTF-8') . " [...] " . mb_substr($description, -20, encoding: 'UTF-8');
            $field_values[] = [$f->getName(), $description, $f->getDivID()];
            $this->form->addField(new \BayCMS\Field\Checkbox(
                $this->context,
                name: $f->getName(),
                description: $f->getDescription(false),
                default_value: $f->get('list_field'),
                div_id: $f->getDivID()
            ));
        }
        $i = 0;
        $count = 0;
        while (isset($this->values[$this->name . '_expert' . $i . '_name'])) {
            if ($this->values[$this->name . '_expert' . $i . '_sql']) {
                $field_values[] = [
                    '_expert' . $i,
                    $this->values[$this->name . '_expert' . $i . '_name'],
                    $this->t('SQL Fields', 'SQL Felder')
                ];
                $count = $i + 1;
            }
            $i++;
        }
        if ($count >= $this->num_expert)
            $this->num_expert = $count + 1;

        $this->form->addField(new \BayCMS\Field\Comment(
            $this->context,
            name: 'expert_help',
            description: '<a href="#" onClick="window.open(\'?help\', \'Help\', \'toolbar=no,menubar=no,scrollbars=yes,width=700,height=500\');">Table with field SQL</a>',
            div_id: 'sql'
        ));
        for ($i = 0; $i < $this->num_expert; $i++) {
            $this->form->addField(new \BayCMS\FieldExtra\QBSQLField(
                $this->context,
                name: $this->name . '_expert' . $i,
                div_id: 'sql'
            ));
        }

        $values = [];
        foreach ($this->prefilter as $k => $v) {
            $values[] = [$k, $v[0]];
        }
        if (count($values)) {
            for ($i = 0; $i < $this->num_prefilter; $i++) {
                $this->form->addField(new \BayCMS\FieldExtra\QBPreFilter(
                    $this->context,
                    name: $this->name . '_prefilter' . $i,
                    values: $values,
                    nr: $i,
                    div_id: 'prefilter'
                ));
            }
        }


        $values = [];
        foreach ($this->postfilter as $k => $v) {
            $values[] = [$k, $v[0]];
        }
        if (count($values)) {
            for ($i = 0; $i < $this->num_postfilter; $i++) {
                $this->form->addField(new \BayCMS\FieldExtra\QBPreFilter(
                    $this->context,
                    name: $this->name . '_postfilter' . $i,
                    values: $values,
                    nr: $i,
                    div_id: 'postfilter'
                ));
            }
        }



        for ($i = 0; $i < $this->num_filter; $i++) {
            $this->form->addField(new \BayCMS\FieldExtra\QBFilter(
                $this->context,
                name: $this->name . '_filter' . $i,
                values: $field_values,
                div_id: 'Filter',
                nr: $i
            ));
        }
        for ($i = 0; $i < $this->num_order; $i++) {
            $this->form->addField(new \BayCMS\FieldExtra\QBOrder(
                $this->context,
                name: $this->name . '_order' . $i,
                values: $field_values,
                div_id: 'order'
            ));
        }
        if ($_GET['id'] ?? false)
            $this->form->setId(($_GET['id']));

    }

    /**
     * Returns the replacement table. Typically used e.g. in edit-form of Email or Document
     * @param bool $document
     * @return string
     */
    public function getReplacementTable(bool $document = true)
    {
        $this->getList();

        if ($document) {
            $out = '<b>' . $this->t(
                'For dataset #1 you have to use the following field names in your pdf-form:',
                'Für Datensatz Nr. 1 müssen die folgenden Feldnamen im PDF-Formular genutzt werden:'
            ) . '</b><br/>';
        } else {
            $out = '<b>' . $this->t(
                'The following fields names can be used in subject and message',
                'Folgenden Feldnamen können in Betreff und Nachrichtentext genutzt werden'
            ) . ':</b><br/>';
        }
        $out .= '
        <table ' . $this->context->TE->getCSSClass('table') . '>';

        foreach ($this->list->getFields() as $f) {
            $out .= '<tr><td>' . $f->getDescription(false) . '</td><td>' . ($document ? 'f1_' : '${') . $f->getName() .
                ($document ? '' : '}') . '</td></tr>' . "\n";
        }
        $out .= "</table>";
        if ($document)
            $out .= $this->t('Field names of dataset #2 will be', 'Feldnamen für Datensatz Nr 2 entsprechend') . ' f2_xxx ...<br/>
        <br/>
        ' . $this->t('For doxx/odt please use ${field name}', 'Für docx und odt bitte Felder in der Form ${Feldname} verwenden') . '.';
        return $out;
    }

    /**
     * Returns an array of fieldname==key and description==value
     * @return array
     */
    public function getFieldList()
    {
        $ret = [];
        foreach ($this->list->getFields() as $f) {
            $ret[$f->getName()] = $f->getDescription(false);
        }
        return $ret;
    }

    /**
     * Runs the user SQL and sets error in case of failure
     * @param mixed $v
     * @return array<mixed|string>|bool
     */
    protected function checkSQLFields(&$v)
    {
        $from = $this->list_from;
        $where = $this->list_where;
        $i = 0;
        while (isset($v[$this->name . '_expert' . $i . '_sql'])) {
            if ($v[$this->name . '_expert' . $i . '_sql']) {
                $sql = $v[$this->name . '_expert' . $i . '_sql'];
                try {
                    if (
                        !pg_query(
                            $this->context->getDbConn(),
                            "select $sql from $from where $where limit 1"
                        )
                    ) {
                        $this->setError($this->name . '_expert' . $i, '_sql');
                        return ['sql', pg_last_error($this->context->getDbConn())];
                    }
                } catch (\Exception $e) {
                    return ['sql', pg_last_error($this->context->getDbConn())];
                }

            }
            $i++;
        }
        return false;

    }

    /**
     * Create the BayCMSList depending on the query parameters
     * @param mixed $exclude
     * @param mixed $id
     * @param mixed $email_fields
     * @return BayCMSList
     */
    public function getList($exclude = false, $id = null, $email_fields = false)
    {
        $from = str_replace('${prefilter}', $this->prefilter_sql, $this->list_from);
        $where = str_replace('${prefilter}', $this->prefilter_sql, $this->list_where);

        if ($exclude) {
            $where .= " and " . $this->id_query . " not in 
            (select qb2r.id_row from qb2_rows qb2r, qb2_run run, objekt qb2o
            where qb2r.id_run=run.id and run.id=qb2o.id and qb2o.id_obj=" .
                pg_escape_string($this->context->getDbConn(), $exclude) . ")";
        }
        if ($id !== null) {
            $where .= " and " . $this->id_query . "=" . pg_escape_string($this->context->getDbConn(), $id);
        }

        $where .= $this->postfilter_sql;

        $this->list = new \BayCMS\Fieldset\BayCMSList(
            context: $this->context,
            from: $from,
            where: $where,
            id_query: $this->row_click_query ? $this->row_click_query : $this->id_query,
            order_by: $this->order_by,
            step: -1,
            jquery_row_click: $this->row_click_query > ''
        );


        foreach ($this->fields as $f) {
            if (
                $f->get('list_field') || ($email_fields && in_array($f->getName(), $this->email_fields))
                || in_array($f->getName(), $this->additional_fields)
            )
                $this->list->addField($f);
        }

        $i = 0;
        while (isset($this->values[$this->name . '_expert' . $i . '_name'])) {
            if ($this->values[$this->name . '_expert' . $i . '_sql']) {
                $this->list->addField(new \BayCMS\Field\TextInput(
                    $this->context,
                    name: 'expert' . $i,
                    description: $this->values[$this->name . '_expert' . $i . '_name'],
                    sql: $this->values[$this->name . '_expert' . $i . '_sql']
                ));
            }
            $i++;
        }

        return $this->list;

    }

    /**
     * Sets the error in the edit form
     * @param mixed $fieldname
     * @param mixed $field
     * @return void
     */
    private function setError($fieldname, $field)
    {
        if (!isset($this->form))
            return;
        $this->form->getField($fieldname)->getFieldSet()->getField($fieldname . $field)->setError(true);
    }

    /**
     * Sets the values of the edit form
     * @param mixed $v
     * @return bool|string[]
     */
    public function setValues(&$v): array|bool
    {
        //Selected fields
        foreach ($this->fields as $f) {
            if (isset($v[$f->getName()]) && $v[$f->getName()] != 'f' && $v[$f->getName()])
                $f->set(['list_field' => 1]);
            else
                $f->set(['list_field' => 0]);
        }

        //PreFilter
        $this->prefilter_sql = '';
        try {
            $this->prefilter_sql = $this->getPrePostFilter($v, 't.');
        } catch (\Exception $e) {
            return ['prefilter', $e->getMessage()];
        }
        if ($this->prefilter_sql)
            $this->prefilter_sql = " and ($this->prefilter_sql)";

        //Postfilter
        $filter = '';
        $this->postfilter_sql = '';
        try {
            $filter = $this->getPrePostfilter($v, 't.', 'postfilter');
        } catch (\Exception $e) {
            return ['filter', $e->getMessage()];
        }
        if ($filter)
            $filter = " and ($filter)";
        $this->postfilter_sql .= $filter;

        //Filter
        $filter = '';
        try {
            $filter = $this->getFilter($v, 't.');
        } catch (\Exception $e) {
            return ['filter', $e->getMessage()];
        }
        if ($filter)
            $filter = " and ($filter)";
        $this->postfilter_sql .= $filter;

        //Order by
        $name = $this->name . '_order';
        $i = 0;

        $this->order_by = [];
        while (isset($v[$name . $i . '_name'])) {
            if (!$v[$name . $i . '_name'])
                break;
            if (preg_match('/^_expert/', $v[$name . $i . '_name']))
                $field_sql = $this->values[$this->name . '_expert' . $i . '_sql'];
            else
                $field_sql = $this->getField($v[$name . $i . '_name'])->getSQL('t.', 'order');

            $desc = $v[$name . $i . '_desc'] ?? false;
            if ($desc == 'f')
                $desc = false;
            $this->order_by[] = $field_sql . ($desc ? ' desc' : '');
            $i++;
        }
        if (!count($this->order_by))
            $this->order_by = [1];
        if ($i >= $this->num_order)
            $this->num_order = $i + 1;
        return false;
    }

    /**
     * Returns the pre/post-filter
     * @param mixed $v
     * @param mixed $prefix
     * @param mixed $type 'prefilter'|'postfilter'
     * @throws \BayCMS\Exception\invalidData
     * @return string
     */
    public function getPrePostFilter(&$v, $prefix = 't.', $type = 'prefilter')
    {
        $name = $this->name . '_' . $type;
        $i = 0;
        $out = '';
        $brackets = 0;

        while (isset($v[$name . $i . '_name'])) {
            if (!($this->$type[$v[$name . $i . '_name']][1] ?? false))
                break;
            if ($i && !$v[$name . $i . '_con']) {
                $this->setError($name . $i, '_con');
                throw new \BayCMS\Exception\invalidData('you have to set and/or in line ' . ($i + 1));

            }
            $out .= ' ' . $v[$name . $i . '_con'] . ' ';
            if ($v[$name . $i . '_bo']) {
                $brackets++;
                $out .= "(";
            }
            $out .= $this->$type[$v[$name . $i . '_name']][1];
            if ($v[$name . $i . '_bc']) {
                if (!$brackets) {
                    $this->setError($name . $i, '_bc');
                    throw new \BayCMS\Exception\invalidData('closing bracket but no opening bracket in line' . ($i + 1));
                }
                $brackets--;
                $out .= ")";
            }
            $i++;
        }
        if ($brackets) {
            $this->setError($name . ($i - 1), '_bc');
            throw new \BayCMS\Exception\invalidData('missing closing bracket');
        }

        $count_var = "num_$type";
        if ($i >= ($this->$count_var - 1))
            $this->$count_var = $i + 2;
        return $out;
    }

    /**
     * Creates the filter 
     * @param mixed $v
     * @param mixed $prefix
     * @throws \BayCMS\Exception\invalidData
     * @return string
     */
    public function getFilter(&$v, $prefix = 't.')
    {
        $name = $this->name . '_filter';
        $i = 0;
        $out = '';
        $brackets = 0;

        while (isset($v[$name . $i . '_name'])) {
            if (!$v[$name . $i . '_name'])
                break;
            if (preg_match('/^_expert/', $v[$name . $i . '_name']))
                $field_sql = '(' . $this->values[$this->name . $v[$name . $i . '_name'] . '_sql'] . ')';
            else
                $field_sql = $this->getField($v[$name . $i . '_name'])->getSQL($prefix, 'filter');
            if ($i && !$v[$name . $i . '_con']) {
                $this->setError($name . $i, '_con');
                throw new \BayCMS\Exception\invalidData('you have to set and/or in line ' . ($i + 1));

            }
            $out .= ' ' . $v[$name . $i . '_con'] . ' ';
            if ($v[$name . $i . '_bo']) {
                $brackets++;
                $out .= "(";
            }
            if ($i && !$v[$name . $i . '_op']) {
                $this->setError($name . $i, '_op');
                throw new \BayCMS\Exception\invalidData('you have to set an operator in line' . ($i + 1));
            }

            switch ($v[$name . $i . '_op']) {
                case 'is null':
                    $out .= $field_sql . ' is null';
                    break;
                case 'null/empty':
                    $out .= '(' . $field_sql . ' is null or ' . $field_sql . '=\'\')';
                    break;
                default:
                    $val = $v[$name . $i . '_val'];
                    $val = "'" . pg_escape_string($this->context->getDbConn(), $val) . "'";
                    $out .= $field_sql . ' ' . $v[$name . $i . '_op'] . ' ' . $val;
            }
            if ($v[$name . $i . '_bc']) {
                if (!$brackets) {
                    $this->setError($name . $i, '_bc');
                    throw new \BayCMS\Exception\invalidData('closing bracket but no opening bracket in line' . ($i + 1));
                }
                $brackets--;
                $out .= ")";
            }
            $i++;
        }
        if ($brackets) {
            $this->setError($name . ($i - 1), '_bc');
            throw new \BayCMS\Exception\invalidData('missing closing bracket');
        }

        if ($i >= ($this->num_filter - 1))
            $this->num_filter = $i + 2;
        return $out;
    }

    /**
     * Adds a tab to the navigation
     * @param string $name
     * @param string $url
     * @return void
     */
    public function addNavigationTab(string $name, string $url)
    {
        $this->additional_tabs[$name] = $url;
    }

    /**
     * Returns the Tab-Navigation
     * @return string
     */
    public function getTabNavigation()
    {
        $names = [];
        $urls = [];
        foreach ($this->additional_tabs as $name => $url) {
            $urls[] = $url;
            $names[] = $name;
        }
        $urls[] = $_SERVER['SCRIPT_NAME'] . '/Query';
        $names[] = $this->t('Query', 'Abfragen');
        $urls[] = $_SERVER['SCRIPT_NAME'] . '/Document';
        $names[] = $this->t('Documents', 'Dokumente');
        if (count($this->email_fields)) {
            $urls[] = $_SERVER['SCRIPT_NAME'] . '/Email';
            $names[] = 'E-Mails';
        }

        $nav = new \BayCMS\Util\TabNavigation(
            context: $this->context,
            names: $names,
            urls: $urls
        );
        return $nav->getNavigation();
    }

    /**
     * Returns all Actions based on a query
     * @return string
     */
    public function getActions()
    {
        $out = '<h4>' . $this->values[$this->name . '_qname'] . '</h4>';

        $list = new \BayCMS\Fieldset\BayCMSList(
            context: $this->context,
            from: '(select t.id,t.name,t.mode,\'Document\' as typ from qb2_document t, objekt o
                where t.id=o.id and o.geloescht is null and t.id_query=' . $this->id . '
                union select t.id, t.name,t.mode,\'E-Mail\' as typ from qb2_email t, objekt o
                where t.id=o.id and o.geloescht is null and t.id_query=' . $this->id . '
            ) t',
            where: 'true',
            id_query: 't.id',
            with_count: false,
            step: -1
        );
        $list->addField(new \BayCMS\Field\TextInput($this->context, name: 'Name'));
        $list->addField(new \BayCMS\Field\TextInput($this->context, name: 'Mode'));
        $list->addField(new \BayCMS\Field\TextInput($this->context, name: 'typ', description: $this->t('Type', 'Art')));
        if ($list->getNumRows()) {
            $out .= '<h5>' . $this->t('Documents/E-Mails based on this query', 'Dokumente/E-Mails basierend auf dieser Abfrage') . '</h5>';
            $out .= $list->getTable();
        }


        $obj = new \BayCMS\Base\BayCMSObject($this->context);
        $obj->load($this->id);
        $rw = $obj->checkWriteAccess();
        if ($rw) {
            $out .= $this->context->TE->getActionLink(
                '?id=' . $this->id . '&aktion=edit',
                $this->t('edit', 'bearbeiten'),
                '',
                'edit'
            );
            $out .= ' ';
            $out .= $this->context->TE->getActionLink(
                '?id=' . $this->id . '&aktion=del',
                $this->t('delete', 'löschen'),
                ' onClick="return confirm(\'' . $this->t('Are you sure?', 'Wirklich löschen?') . '\')"',
                'del'
            );
            $out .= ' ';
        }

        $out .= $this->context->TE->getActionLink(
            '?id=' . $this->id . '&baycmsExportFormat=xlsx',
            'Download xlsx',
            '',
            'save'
        );
        $out .= ' ';
        $out .= $this->context->TE->getActionLink(
            '?id=' . $this->id . '&baycmsExportFormat=csv',
            'Download csv',
            '',
            'save'
        );
        $out .= ' ';
        $out .= $this->context->TE->getActionLink(
            $_SERVER['SCRIPT_NAME'] . '/Document?id_query=' . $this->id . '&aktion=edit',
            $this->t('New Document', 'Neues Dokument'),
            '',
            'file'
        );
        $out .= ' ';

        if (count($this->email_fields)) {
            $out .= $this->context->TE->getActionLink(
                $_SERVER['SCRIPT_NAME'] . '/Email?id_query=' . $this->id . '&aktion=edit',
                $this->t('New Email', 'Neue E-Mail'),
                '',
                'envelope'
            );
            $out .= ' ';
        }


        return $out;
    }

    /**
     * Page function 
     * @param string $pre_content
     * @param string $post_content
     * @return void
     */
    public function page(string $pre_content = '', string $post_content = '')
    {
        $this->pageExport();
        $this->pageHelp();
        $this->pageQB($pre_content, $post_content);
    }

    /**
     * Prints out export
     * @return void
     */
    public function pageExport()
    {
        if (isset($_GET['baycmsExportFormat']) && ($_GET['id'] ?? false)) {
            $this->load($_GET['id']);
            $this->getList();
            $this->list->pageExport($_GET['baycmsExportFormat'], $this->query_name);
        }
    }

    /**
     * Prints out help-page for available fields
     * @return void
     */
    public function pageHelp()
    {
        if (isset($_GET['help'])) {
            $this->context->set('no_frame', true);
            $this->context->set('frameable', true);
            $this->context->printHeader();
            echo $this->getFieldTable();
            $this->context->printFooter();
        }
    }

    /**
     * prints out all HTML for the QueryBuilder
     * @param string $pre_content
     * @param string $post_content
     * @return void
     */
    public function pageQB(string $pre_content = '', string $post_content = '')
    {

        if (isset($_GET['id'])) {
            $obj = new \BayCMS\Base\BayCMSObject($this->context);
            $obj->load($_GET['id']);
            switch ($obj->get()['uname']) {
                case 'qb2_document':
                    if (!strstr($_SERVER['PATH_INFO'], '/Document')) {
                        header('Location: ' . $_SERVER['SCRIPT_NAME'] . '/Document?id=' . $_GET['id']);
                        exit;
                    }
                    break;
                case 'qb2_email':
                    if (!strstr($_SERVER['PATH_INFO'], '/Email')) {
                        header('Location: ' . $_SERVER['SCRIPT_NAME'] . '/Email?id=' . $_GET['id']);
                        exit;
                    }
                    break;
                case 'qb2_query':
                    if (!strstr($_SERVER['PATH_INFO'], '/Query')) {
                        header('Location: ' . $_SERVER['SCRIPT_NAME'] . '/Query?id=' . $_GET['id']);
                        exit;
                    }
                    break;
            }
        }

        $head = $pre_content . $this->getTabNavigation();

        //Check PATH_INFO
        if ($_SERVER['PATH_INFO'] ?? false) {
            $path = explode('/', $_SERVER['PATH_INFO']);
            switch ($path[1]) {
                case 'Document':
                    $doc = new Document(
                        context: $this->context,
                        class: get_class($this)
                    );
                    $doc->page($head, $post_content);
                    break;
                case 'Email':
                    if (!count($this->email_fields)) {
                        $this->context->printHeader();
                        echo $head;
                        $this->context->TE->printMessage('There is no email field in the table.');
                        $this->context->printFooter();
                    }
                    $email = new Email(
                        context: $this->context,
                        class: get_class($this)
                    );
                    $email->page($head, $post_content);
                    break;
            }

        }
        $this->context->printHeader();

        echo $head;

        if (isset($_GET['aktion'])) {
            $edit = $this->getEdit();
            if ($edit) {
                if ($edit['error_message'])
                    $this->context->TE->printMessage($edit['error_message'], 'danger');
                echo $edit['html'];
                $this->context->printFooter();
            }
        }

        if ($_GET['id'] ?? false)
            $this->id = $_GET['id'];

        if ($this->id) {
            $this->load();
            $this->getList();
            echo $this->getActions() . "<br/>";
            echo $this->list->getTable();
            $this->context->printFooter();
        }


        $form = new \BayCMS\Fieldset\Form($this->context);
        $form->addField(new \BayCMS\Field\TextInput($this->context, name: 'search'));
        $form->setValues($_GET);
        echo $form->getSearchForm();
        $s_query = '';
        if ($_GET['search'] ?? false) {
            $search = pg_escape_string($this->context->getDbConn(), $_GET['search']);
            $s_query = " and (t.name ilike '%$search%' or t.description ilike '%$search%')";
        }
        //List of Queries
        $list = new \BayCMS\Fieldset\BayCMSList(
            context: $this->context,
            from: 'qb2 t, objekt_verwaltung' . $this->context->getOrgId() . ' o',
            where: 't.id=o.id and t.class=\'' . pg_escape_string($this->context->getDbConn(), get_class($this)) . '\'' . $s_query,
            id_query: 't.id',
            actions: ['edit_copy'],
            action_sep: ' ',
            new_button: 1,
            write_access_query:'check_objekt(t.id,'.$this->context->getUserId().')',
            jquery_row_click: true
        );
        $list->addField(new \BayCMS\Field\TextInput($this->context, name: 'name', description: 'Query'));
        $list->addField(new \BayCMS\Field\TextInput($this->context, name: 'description', description: $this->t('Comment', 'Bemerkung')));
        echo $list->getTable($this->id);
        echo $post_content;
        $this->context->printFooter();
    }
}