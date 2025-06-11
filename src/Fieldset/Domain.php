<?php

namespace BayCMS\Fieldset;

use BayCMS\Field\Field;

class Domain extends Fieldset
{

    protected \BayCMS\Fieldset\Form $sform;
    protected \BayCMS\Fieldset\Form $eform;

    protected \BayCMS\Fieldset\BayCMSList $list;

    protected string $error_string = '';
    protected array $fields;
    protected ?int $id = null;

    protected string $table;
    protected string $name;
    protected string $uname;
    protected string $id_name;
    protected bool $delete_button;
    protected bool $object_button;
    protected string $qs;
    protected string $write_access_query;
    protected bool $copy;
    protected string $comment;
    protected bool $no_table_on_edit;
    protected bool $no_create_on_upload;
    protected bool $erase_object;
    protected bool $headline;

    protected ?string $tinyurl;

    protected string $from;
    protected string $where;
    // properties for list
    // not set by constructor
    protected ?string $squery = null;
    protected string $prefex = 't.';
    protected array $order_by = [1];
    protected ?array $json_order_by = null;
    protected bool $auto_order = true;
    protected string $view_file = '';
    protected array $actions = ['edit_copy'];
    protected string $action_sep = '</td><td>';
    protected int $step = 20;
    protected string $id_query = 't.id';
    protected bool $export_no_strip_tags = false;
    protected bool $new_button = true;
    protected bool $upload_button = false;
    protected bool $with_count = true;
    protected bool $export_buttons = true;
    protected array $additional_export_formats = [];
    protected bool $jquery_row_click = true;

    
    public function __construct(
        \BayCMS\Base\BayCMSContext $context,
        string $table,
        string $name = 'domain1',
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
        bool $headline=true
    ) {
        $this->context = $context;
        $this->table = $table;
        $this->name = $name;
        $this->uname = $uname;
        $this->id_name = $id_name;
        $this->delete_button = $delete_button;
        $this->object_button = ($object_button ?? $uname);
        $this->qs = $qs;

        if(! $this->qs && isset($_GET['js_select'])){
            $this->qs='js_select='.$_GET['js_select'];
            if($_GET['target']??'') $this->qs.='&target='.$_GET['target'];
        }
        $this->write_access_query = $write_access_query ?? ($uname ? 'check_objekt(t.id,' . $this->context->getUserId() . ')' : 'true');
        $this->from = $from ?? $this->table . ' t' .
            ($this->uname ? ", objekt_verwaltung" . $this->context->getOrgId() . " o" : '');
        $this->where = $where ?? ($this->uname ? "t.id=o.id" : 'true');
        $this->copy = $copy;
        $this->comment = $comment;
        $this->no_table_on_edit = $no_table_on_edit;
        $this->tinyurl = $tinyurl;
        $this->no_create_on_upload = $no_create_on_upload;
        $this->erase_object = $erase_object;
        $this->headline=$headline;
        
    }

    public function __get(string $key){
        return $this->$key;
    }

    public function setListProperties(
        ?string $squery = null,
        ?string $prefex = null,
        ?array $order_by = null,
        ?bool $auto_order = null,
        ?string $view_file = null,
        ?array $actions = null,
        ?string $action_sep = null,
        ?int $step = null,
        ?string $id_query = null,
        ?bool $export_no_strip_tags = null,
        ?bool $new_button = null,
        ?bool $upload_button = null,
        ?bool $with_count = null,
        ?bool $export_buttons = null,
        ?array $additional_export_formats = null,
        ?bool $jquery_row_click = null,
        ?array $json_order_by = null
    ) {
        foreach (get_defined_vars() as $key => $v) {
            if (!is_null($v))
                $this->$key = $v;
        }
    }


    /**
     * Add a Field to the domain
     * @param \BayCMS\Field\Field $field
     * @param mixed $index Index number
     * @param bool $edit_field show field in edit-form
     * @param bool $list_field show field in list
     * @param bool $search_field show field in search-form
     * @param string $setvalue_sql TODO: Not implemented?
     * @return Field
     */
    public function addField(
        Field $field,
        ?int $index = null,
        bool $edit_field = true,
        bool $list_field = false,
        bool $search_field = false,
        string $setvalue_sql = ''
    ): Field {
        $field->set(['edit_field' => $edit_field, 'list_field' => $list_field, 'search_field' => $search_field, 'setvalue_sql' => $setvalue_sql]);
        return parent::addField($field, $index);
    }

    /**
     * Autocreate a domain from a database table
     * @param \BayCMS\Base\BayCMSContext $context
     * @param string $table
     * @param string $name
     * @param mixed $class_map
     * @param mixed $lfield_map
     * @param mixed $lfield_count
     * @param mixed $sfield_map
     * @param mixed $sfield_count
     * @param mixed $force_class
     * @return Domain
     */
    static function autoCreate(
        \BayCMS\Base\BayCMSContext $context,
        string $table,
        string $name = 'domain1',
        $class_map = null,
        $lfield_map = null,
        $lfield_count = 3,
        $sfield_map = null,
        $sfield_count = 1,
        $force_class = ''
    ) {
        $map = [
            'int8' => 'Number',
            'bit' => '',
            'varbit' => '',
            'bool' => 'Checkbox',
            'box' => '',
            'bytea' => '',
            'varchar' => 'TextInput',
            'bpchar' => 'TextInput',
            'cidr' => '',
            'circle' => '',
            'date' => 'Date',
            'float8' => 'Number',
            'inet' => 'TextInput',
            'int4' => 'Number',
            'interval' => '',
            'line' => '',
            'lseg' => '',
            'macaddr' => '',
            'money' => 'Number',
            'numeric' => 'Number',
            'path' => '',
            'point' => '',
            'polygon' => '',
            'float4' => 'Number',
            'int2' => 'Number',
            'text' => 'TextInput',
            'time' => 'Time',
            'timetz' => 'Time',
            'timestamp' => 'Datetime',
            'timestamptz' => 'Datetime'
        ];

        $domain = new Domain($context, $table, $name);

        $res = pg_query($context->getDbConn(), "select * from " . $table . " limit 1");

        $count = 0;
        for ($i = 0; $i < pg_num_fields($res); $i++) {
            $name = pg_field_name($res, $i);
            if ($name == 'id')
                continue;

            if (is_array($lfield_map))
                $lfield = $lfield_map[$name] ?? false;
            else
                $lfield = $count < $lfield_count;

            if (is_array($sfield_map))
                $sfield = $sfield_map[$name] ?? false;
            else
                $sfield = $count <= $sfield_count;

            $class = false;
            if (is_array($class_map))
                $class = $class_map[$name] ?? false;
            if (!$class) {
                $class = $map[pg_field_type($res, $i)] ?? false;
                if ($class)
                    $class = "\\BayCMS\\Field\\$class";
            }
            if ($force_class)
                $class = $force_class;
            if (!$class)
                continue;
            $count++;
            $domain->addField(field: new $class(context: $context, name: ucfirst($name)), list_field: $lfield, search_field: $sfield);
        }
        return $domain;
    }

    /**
     * Creates the search form 
     * @return int number of fields in form
     */
    protected function createSearchForm()
    {
        $this->sform = new \BayCMS\Fieldset\Form(
            context: $this->context,
            name: 'sform',
            qs: $this->qs
        );
        $count=0;
        foreach ($this->fields as $f) {
            if (!$f->get('search_field'))
                continue;
            $f2=clone($f);
            $f2->non_empty=false;
            $this->sform->addField($f2);
            $count++;
        }
        if ($this->qs ?? false) {
            foreach (explode('&', $this->qs) as $f) {
                [$name, $value] = explode("=", $f);
                $this->sform->addField(new \BayCMS\Field\Hidden(
                    $this->context,
                    name: $name,
                    default_value: urldecode($value)
                ));
            }
        }
        return $count;
    }


    /**
     * Create edit form
     * @param mixed $upload 
     * @return void
     */
    protected function createEditForm($upload = false)
    {
        $this->eform = new \BayCMS\Fieldset\Form(
            context: $this->context,
            name: 'eform',
            table: $this->table,
            uname: $this->uname,
            id_name: $this->id_name,
            qs: $this->qs,
            delete_button: $this->delete_button,
            object_button: $this->object_button,
            write_access_query: $this->write_access_query
        );
        foreach ($this->fields as $f) {
            if (!$f->get('edit_field'))
                continue;
            if ($upload && strstr(get_class($f), 'BayCMS\\Field\\Bilang')) {
                $f_nr = 0;
                foreach ($f->getFields() as $f) {
                    $this->eform->addField($f);
                    $f_nr++;
                    if ($f_nr == 2)
                        $f->set(['non_empty' => 0]);
                }
            } else
                $this->eform->addField($f);

        }
    }

    /**
     * Creates the list
     * @param mixed $target defaults to 'html'
     * @return void
     */
    protected function createList($target = 'html')
    {
        $where = $this->where . $this->squery ?? '';
        $write_access_query = '';
        if ($target == 'html') {
            $write_access_query = $this->write_access_query;

        }
        if ($target == 'export')
            $this->step = -1;

        $this->list = new \BayCMS\Fieldset\BayCMSList(
            context: $this->context,
            from: $this->from,
            where: $where,
            prefix: $this->prefex,
            step: $this->step,
            name: 'd1',
            id_query: $this->id_query,
            write_access_query: $write_access_query,
            offset: $_GET['d1offset'] ?? 0,
            qs: $this->qs,
            order_by: ($target == 'json' && $this->json_order_by !== null) ? $this->json_order_by : $this->order_by,
            auto_order: $this->auto_order,
            id_name: $this->id_name,
            view_file: $this->view_file,
            action_sep: $this->action_sep,
            actions: $this->actions,
            export_no_strip_tags: $this->export_no_strip_tags,
            new_button: $this->new_button,
            upload_button: $this->upload_button,
            with_count: $this->with_count,
            export_buttons: $this->export_buttons,
            jquery_row_click: $this->jquery_row_click
        );
        foreach ($this->fields as $f) {
            // if (! $f->get('edit_field'))
            //     continue;
            if ($target != 'export' && !$f->get('list_field'))
                continue;
            if ($target == 'json') {
                $f->setDescription('label');
                $this->list->addField($f);
                $f2 = clone $f;
                $f2->setDescription('value');
                $this->list->addField($f2);
                break;
            }
            if ($f->getNoAddToQuery() && !$f->get('list_field'))
                continue;
            if ($target != 'html') {
                if (in_array(get_class($f), ['BayCMS\\Field\\Time', 'BayCMS\\Field\\Date', 'BayCMS\\Field\\Datetime', 'BayCMS\\Field\\Checkbox']))
                    $f = new \BayCMS\Field\TextInput($this->context, $f->name, $f->getDescription(false));

            }
            if ($target != 'html' && strstr(get_class($f), "BayCMS\\Field\\Bilang")) {
                foreach ($f->getFields() as $f) {
                    $this->list->addField($f);
                }
            } else
                $this->list->addField($f);
        }
        if ($target == 'json' && !($this->squery ?? '')) {
            $where = $this->list->get('where');
            $f = $this->list->getField(0);
            $where .= " and " . $f->getSQL('t.', 'json') . " ilike '%" . pg_escape_string($this->context->getDbConn(), $_GET['json_query']) . "%'";
            $this->list->set(['where' => $where]);
        }

    }

    /**
     * Creates the HTML+JS-Head for the Domain
     * @return string
     */
    public function getHead()
    {
        $head = ($_GET['js_select'] ?? false ?
            ($this->de() ?
                $this->name . ' auswählen' : 'Select ' . $this->name) :
            ($this->de() ?
                $this->name . ' verwalten' : 'Manage ' . $this->name));
        $out='';
        if($this->headline)
            $out .= "<h3>$head</h3>\n";
        if ($_GET['js_select'] ?? false) {
            $out.=\BayCMS\Util\JSHead::get($this->tinyurl);
        }
        $out .= $this->comment;
        return $out;

    }

    /**
     * Upload action
     * @throws \BayCMS\Exception\invalidData
     * @throws \BayCMS\Exception\missingData
     * @return array{finished: bool, html: string}
     */
    public function getUpload()
    {
        if (($_GET['aktion'] ?? '') == 'upload' && !($_POST['delim'] ?? false)) {
            $form = new \BayCMS\Fieldset\Form(
                context: $this->context,
                form_options: ' enctype="multipart/form-data"',
                submit: 'Upload',
                action: '?aktion=upload',
                qs: $this->qs
            );

            $form->addField(new \BayCMS\Field\File(
                $this->context,
                name: 'uploaded',
                description: 'File (CSV, XLS, XLSX)',
                help: 'Bei Excel-Dateien wird nur das aktive Tabellenblatt verarbeitet.'
            ));
            $form->addField(new \BayCMS\Field\Select(
                $this->context,
                name: 'delim',
                description: 'Spaltentrennzeichen (nur für CSV)',
                values: array(',', ';', 'TAB')
            ));
            $form->addField(new \BayCMS\Field\Comment(
                $this->context,
                name: 'comment',
                description: 'Alternativ zum Datei Upload können Sie per Copy/Paste den Inhalt der Excel-Datei in das folgende Text-Feld kopieren'
            ));
            $form->addField(new \BayCMS\Field\Textarea(
                $this->context,
                name: 'uploaded_txt',
                description: 'Excel-Datei Inhalt'
            ));
            return ['finished' => true, 'html' => $form->getForm('Upload Data')];
        }

        $this->delete_button = false;
        $this->object_button = false;

        if (isset($_POST['delim'])) { //call from Form
            unset($_SESSION['domain_upload']);
            unset($_SESSION['domain_upload_resume']);
            $_SESSION['domain_upload_report'] = [];
            $fields = false;
            if ($_POST['uploaded_txt'] ?? false) {
                $content = $_POST['uploaded_txt'];
                $delim = "\t";
            } elseif (strstr(strtolower($_FILES['uploaded']['name']), '.xls')) {
                $objPHPExcel = \PhpOffice\PhpSpreadsheet\IOFactory::load($_FILES['uploaded']['tmp_name']);
                $objWorksheet = $objPHPExcel->getActiveSheet();
                $fields = [];
                $r = 0;

                foreach ($objWorksheet->getRowIterator() as $row) {
                    $cellIterator = $row->getCellIterator();
                    $cellIterator->setIterateOnlyExistingCells(FALSE); // This loops through all cells,
                    $has_data = false;
                    $col = [];
                    foreach ($cellIterator as $cell) {
                        $v = $cell->getValue();
                        if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
                            $v = date("Y-m-d H:i:s", \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($v));
                        }
                        $col[] = $v;
                        if (!$has_data)
                            $has_data = $v > '';
                    }
                    if ($has_data)
                        $fields[] = $col;
                }
            } elseif (strstr(strtolower($_FILES['uploaded']['name']), '.csv')) {
                $fp = fopen($_FILES['uploaded']['tmp_name'], 'r');
                $content = '';
                while (!feof($fp)) {
                    $content .= fread($fp, 8192);
                }
                $content = mb_convert_encoding($content, 'UTF-8', "UTF-8, ISO-8859-1");
                $delim = $_POST['delim'];
                if ($delim == 'TAB')
                    $delim = "\t";

            } elseif (isset($_FILES['uploaded']['name'])) {
                throw new \BayCMS\Exception\invalidData("Unsupported file type " . $_FILES['uploaded']['name']);
            }

            if ($fields === false) {
                $fields = [];
                foreach (preg_split("/\r\n|\n|\r/", $content) as $l) {
                    if (!$l)
                        continue;
                    $fields[] = str_getcsv(trim($l), $delim);
                }
            }

        } elseif (isset($_SESSION['domain_upload'])) {
            $fields = $_SESSION['domain_upload'];
        }


        $field_names = [];
        $field_map = [];
        $id_column = -1;
        if (!isset($fields[0])) {
            throw new \BayCMS\Exception\invalidData("file is empty");
        }
        $this->createEditForm(true);
        for ($i = 0; $i < count($fields[0]); $i++) {
            if ($fields[0][$i] == 'id') {
                $id_column = $i;
                $field_names[] = 'id';
                continue;
            }
            $index = $this->eform->getFieldIndex($fields[0][$i]);
            if ($index == -1) {
                throw new \BayCMS\Exception\invalidData("Column " . $fields[0][$i] . " not found");
            }
            $field_names[] = $this->eform->getField($index)->getName();
            $field_map[$i] = $index;
        }

        $key_query = '';
        $key_count = 1;
        $key_names = [];
        foreach ($this->fields as $f) {
            if (!$f->get('key_field'))
                continue;
            if (!in_array($f->getName(), $field_names))
                throw new \BayCMS\Exception\missingData('Required column ' . $f->getName() . ' is missing.');
            $key_query .= ' and t.' . $f->getName() . '=$' . $key_count;
            $key_count++;
            $key_names[] = $f->getName();
        }


        if ($key_query) {
            pg_prepare($this->context->getRwDbConn(), 'key_query', 'select t.id from ' .
                ($this->uname ? "objekt_verwaltung" . $GLOBALS['row1']['id'] . " o, " : '') . $this->table . ' t 
                where true' . $key_query .
                ($this->uname ? ' and o.id=t.id' : ''));
        }

        $i = 0;
        while ($f = $this->eform->getField($i)) {
            if (!in_array($f->getName(), $field_names))
                $this->eform->delField($i);
            $i++;
        }
        $this->eform->addField(new \BayCMS\Field\Hidden(
            $this->context,
            name: 'domain_upload_resume',
            default_value: 1,
            no_add_to_query: 1
        ));

        if (isset($_POST['domain_upload_resume']) && isset($_SESSION['domain_upload_resume'])) {
            if ($_GET[$this->eform->get('id_name')] ?? false) {
                $this->eform->setId($_GET[$this->eform->get('id_name')]);
            }
            if ($this->eform->setValues($_POST)) {
                $_SESSION['domain_upload_report']['rows'][$_POST['domain_upload_resume']] = 'skipped';
                return ['finished' => false, 'html' => $this->eform->getForm("Upload Row " . $_POST['domain_upload_resume'])];
            }
            $_SESSION['domain_upload_report']['rows'][$_POST['domain_upload_resume']] = [$_GET[$this->eform->get('id_name')] ?? null, $this->eform->save()];
        }

        $upload_sql_prepared = array();
        $r_start = (isset($_SESSION['domain_upload_resume']) ? $_SESSION['domain_upload_resume'] : 1);
        unset($_SESSION['domain_upload']);
        unset($_SESSION['domain_upload_resume']);
        for ($r = $r_start; $r < count($fields); $r++) {
            while (count($field_names) > count($fields[$r])) {
                $fields[$r][] = null;
            }

            $key_array = array();
            foreach ($key_names as $n) {
                $key_array[] = $fields[$r][array_search($n, $field_names)];
            }

            $id = null;
            for ($c = 0; $c < count($fields[$r]); $c++) {
                if ($c == $id_column) {
                    $id = $fields[$r][$c];
                    continue;
                }

                if ($this->eform->getField($field_map[$c])->get('upload_sql')) {
                    if (!isset($upload_sql_prepared[$c])) {
                        pg_prepare($this->context->getRwDbConn(), 'upload_sql' . $c, );
                        $upload_sql_prepared[$c] = true;
                    }
                    if ($fields[$r][$c]) {
                        $res = pg_execute($this->context->getRwDbConn(), 'upload_sql' . $c, [$fields[$r][$c]]);
                        if (pg_num_rows($res))
                            list($fields[$r][$c]) = pg_fetch_row($res);
                        else {
                            $_SESSION['domain_upload_report']['foreign_keys'][$field_names[$c]][$r] = 1;
                            $fields[$r][$c] = '';
                        }
                    }
                }
            }

            if ($key_query && !$id) {
                $res = pg_execute($this->context->getRwDbConn(), 'key_query', $key_array);
                if (pg_num_rows($res) > 1) {
                    $_SESSION['domain_upload_report']['keys'][implode(", ", $key_array)] = 'dublicate';
                    continue;
                }
                if (pg_num_rows($res) == 1)
                    list($id) = pg_fetch_row($res, 0);
                else {
                    $id = null;
                    $_SESSION['domain_upload_report']['keys'][implode(", ", $key_array)] = 'not found';
                }

            }
            if (!$id && $this->no_create_on_upload) {
                $_SESSION['domain_upload_report']['rows'][$r] = 'skipped';
                continue;
            }
            $this->id = $id;
            $this->eform->setId($id);
            $v = array_combine($field_names, $fields[$r]);
            $v['domain_upload_resume'] = $r;
            if ($this->eform->setValues($v)) {
                $_SESSION['domain_upload'] = $fields;
                $_SESSION['domain_upload_resume'] = $r + 1;
                $_SESSION['domain_upload_report']['rows'][$r] = 'skipped';

                return ['finished' => false, 'html' => $this->eform->getForm("Upload Row " . $r)];
            } else {
                $this->id = $this->eform->save();
                $_SESSION['domain_upload_report']['rows'][$r] = [$id, $this->id];

            }
        }

        if (isset($_SESSION['domain_upload_report'])) {
            $out = '<h2>Upload Report</h2>';
            foreach ($_SESSION['domain_upload_report']['rows'] as $r => $v) {
                $out .= "<b>$r</b>: ";
                if (is_array($v)) {
                    $out .= "<span style=\"color:#00bb00;\">";
                    if ($v[0] != $v[1])
                        $out .= " created with id=" . $v[1];
                    else
                        $out .= " updated id=" . $v[1];
                } else {
                    $out .= "<span style=\"color:#aa0000;\">" . $v;
                }
                $out .= "</span><br/>\n";
            }


            if (isset($_SESSION['domain_upload_report']['keys'])) {
                $out .= "
    <h3>Übersprungen:</h3><table>";
                foreach ($_SESSION['domain_upload_report']['keys'] as $key => $value) {
                    $out .= "<tr><td>$key</td><td>$value</td></tr>";
                }
                $out .= "</table>";
            }
            if (isset($_SESSION['domain_upload_report']['foreign_keys'])) {
                $out .= "<h3>Nicht gefundene Fremdschlüssel:</h3><table>";
                foreach ($_SESSION['domain_upload_report']['foreign_keys'] as $key => $value) {
                    $out .= "<tr><td>$key</td><td>" . implode("; ", array_keys($value)) . "</td></tr>";
                }
                $out .= "</table>";
            }

            unset($_SESSION['domain_upload_report']);
            unset($_SESSION['domain_upload']);
            unset($_SESSION['domain_upload_resume']);
            unset($_GET['aktion']);
            $_GET['id'] = $this->id;
            return ['finished' => true, 'html' => $out];
        }


    }

    /**
     * Edit-Action
     * @return array{edit: bool, error: int|string, html: string, id: int|null, message: string|array{edit: bool, error: string, html: string, id: int|null, message: string}|array{edit: int, error: string, html: string, id: null, message: string}}
     */
    public function getEdit()
    {
        $action = $_GET['aktion'] ?? '';
        $this->id = $_GET[$this->id_name] ?? null;
        $ok = 1;
        $error = '';
        if ($action == 'del') {
            if ($this->uname ?? '') {
                $obj = new \BayCMS\Base\BayCMSObject($this->context);
                try {
                    $obj->load($this->id);
                    if ($this->erase_object)
                        $obj->erase(true);
                    else
                        $obj->delete();
                } catch (\Exception $e) {
                    $ok = 0;
                    $error = $e->getMessage();
                }
            } else {
                try {
                    pg_query_params(
                        $this->context->getRwDbConn(),
                        'delete from ' . $this->table . ' where id=$1',
                        [$this->id]
                    );
                } catch (\Exception $e) {
                    $ok = 0;
                    $error = $e->getMessage();
                }

            }
            if ($ok) {
                $message = $this->t('Deleted entry', 'Eintrag gelöscht');
                $this->id = null;
            } else
                $message = '';
            return ['html' => '', 'error' => $error, 'message' => $message, 'edit' => 0, 'id'=>null];
        }

        $error = '';
        $message = '';
        $html = '';
        $this->createEditForm();

        if ($this->id && !$action) {
            $this->eform->load($this->id);
            $html .= $this->eform->getTable(
                delete_link: $this->delete_button,
                object_link: $this->object_button,
                no_copy_link: !$this->copy
            );
        }

        if ($this->id && $this->copy) {
            $this->eform->addField(new \BayCMS\Field\Checkbox(
                $this->context,
                name: 'save_as_copy',
                no_add_to_query: 1,
                not_in_table: 1,
                description: $this->t('Save as new entry', 'Als neuen Eintrag speichern')

            ));
        }
        if ($action == 'save' && ($_POST['save_as_copy'] ?? false)) {
            $this->id = null;
        }
        if ($action == 'save') {
            if ($this->id)
                $this->eform->setId($this->id);
            $error = $this->eform->setValues($_POST);
            if ($error) {
                $action = 'edit';
                $error = $this->t('Please fill in all mandatory fields', 'Bitte füllen Sie das Formular vollständig aus');
            }
        }
        if ($action == 'save') {
            try {
                $this->id = $this->eform->save();
                $html .= $this->eform->getTable(
                    delete_link: $this->delete_button,
                    object_link: $this->object_button,
                    no_copy_link: !$this->copy
                );
                $message = $this->t('Saved entry', 'Eintrag gespeichert');
            } catch (\Exception $e) {
                $error = $e->getMessage();
                $action = 'edit';
            }
        }
        if ($action == 'edit' || $action == 'copy') {
            if ($this->id && ($_GET['aktion'] ?? '') != 'save')
                $this->eform->load($this->id);
            if ($action == 'copy')
                $this->id = null;
            if ($this->id)
                $head = $this->t('Edit Entry', 'Eintrag bearbeiten');
            else
                $head = $this->t('New Entry', 'Neuer Eintrag');
            $html .= $this->eform->getForm($head);
        }
        return ['html' => $html, 'error' => $error, 'message' => $message, 'edit' => ($action == 'edit'), 'id' => $this->id];
    }

    /**
     * Create search form and append $this->qs
     * @return array{error: string, html: string, message: string}
     */
    public function getSearch()
    {
        if(! $this->createSearchForm()) return ['html' => '', 'error' => '', 'message' => ''];;
        $this->sform->setValues($_GET);
        $html = $this->sform->getSearchForm() . "<br/>\n";
        $qs = $this->sform->getQS();
        if (!($this->qs ?? false))
            $this->qs = $qs;
        else
            $this->qs .= "&" . $qs;
        if (!isset($this->squery) && isset($_GET[$this->sform->getField(0)->getName()])) {
            $this->squery = " and " . $this->sform->getField(0)->getSQL("t.") . " ilike '%" .
                pg_escape_string($this->context->getDbConn(), $_GET[$this->sform->getField(0)->getName()]) .
                "%'";
        }
        return ['html' => $html, 'error' => '', 'message' => ''];
    }

    /**
     * Creates the list and returns the html-Table
     * @return array{error: string, html: string, message: string}
     */
    public function getList()
    {
        $this->createList();
        $html = $this->list->getTable($this->id);
        return ['html' => $html, 'error' => '', 'message' => ''];
    }

    /**
     * Returns a json string
     * @return bool|string
     */
    public function getJSON()
    {
        if (!isset($this->squery)) {
            $this->createSearchForm();
            $this->squery = " and " . $this->sform->getField(0)->getSQL("t.") . " ilike '%" .
                pg_escape_string($this->context->getDbConn(), $_GET['json_query']) .
                "%'";
        }
        $this->createList('json');
        return $this->list->getJSON();
    }


    /**
     * Prints out all edit-HTML
     */
    public function pageEdit()
    {
        $search = $this->getSearch();
        $edit = $this->getEdit();
        if ($edit['error'])
            $this->context->TE->printMessage($edit['error'], 'danger');
        if ($edit['message'])
            $this->context->TE->printMessage($edit['message']);
        echo $edit['html'];
        if ($edit['edit'] && $this->no_table_on_edit)
            return $this->context->printFooter();
        ;

        echo $search['html'];
        echo $this->getList()['html'];
    }

    /**
     * Prints out all non HTML stuff
     * @return void
     */
    public function pagePreHeader(){
        if (isset($_GET['baycmsExportFormat'])) {
            $this->getSearch(); //sets squery for export!
            $this->createList('export');
            $this->list->pageExport($_GET['baycmsExportFormat'], $this->name);
        }

        if (isset($_GET['json_query'])) {
            echo $this->getJSON();
            exit();
        }
    }

    /**
     * Prints out all HTML-Stuff
     */
    public function pagePostHeader(string $post_content=''){
        $search = $this->getSearch();
        echo $this->getHead();
        if (($_SESSION['domain_upload_resume'] ?? false) || ($_GET['aktion'] ?? '') == 'upload') {
            try {
                $upload = $this->getUpload();
                echo $upload['html'];
                if (!$upload['finished'])
                    return $this->context->printFooter();
            } catch (\Exception $e) {
                $this->context->TE->printMessage($e->getMessage(), 'danger');
            }
        }
        if ($this->error_string)
            $this->context->TE->printMessage($this->error_string, 'danger');

        $edit = $this->getEdit();
        if ($edit['error'])
            $this->context->TE->printMessage($edit['error'], 'danger');
        if ($edit['message'])
            $this->context->TE->printMessage($edit['message']);
        echo $edit['html'];
        if ($edit['edit'] && $this->no_table_on_edit)
            return $this->context->printFooter();
        ;

        echo $search['html'];
        echo $this->getList()['html'];
        echo $post_content;
        $this->context->printFooter();

    }

    /**
     * Full page function (html, json, export)
     * @return void
     */
    public function page(string $pre_content='', string $post_content='')
    {
        $this->pagePreHeader();
        $this->context->printHeader();
        echo $pre_content;
        $this->pagePostHeader($post_content);
    }

    /**
     * Automatically create a QueryBuilder from an existing Domain
     * @return QueryBuilder
     */
    public function createQB(): \BayCMS\Fieldset\QueryBuilder
    {
        $qb = new \BayCMS\Fieldset\QueryBuilder(
            context: $this->context,
            from: $this->from,
            where: $this->where,
            object: $this->uname > ''
        );
        $div_id = $this->t('Fields', 'Felder');

        $qb->addField(new \BayCMS\Field\TextInput(
            $this->context,
            name: 'Id',
            div_id: $div_id
        ));

        foreach ($this->getFields() as $f) {
            $f->set(['div_id' => $div_id]);
            $qb->addField($f);
        }
        return $qb;
    }

}