<?php

namespace BayCMS\Fieldset;

class BayCMSList extends Fieldset
{

    protected int $num_rows = -1;
    private string $from;
    private string $where;
    private string $prefix;
    private int $step;
    private string $name;
    private string $id_query;
    private string $data_id_query;
    private string $write_access_query;

    private int $offset;
    private string $qs;
    private array $order_by;
    private bool $auto_order;
    private string $id_name;
    private string $view_file;
    private bool $with_head;
    private string $action_sep;
    private array $actions;
    private bool $export_no_strip_tags;
    private bool $new_button;
    private bool $upload_button;
    private bool $with_count;
    private bool $export_buttons;
    private array $additional_export_formats;
    private bool $jquery_row_click;




    public function __construct(
        \BayCMS\Base\BayCMSContext $context,
        string $from,
        string $where,
        string $prefix = 't.',
        int $step = 20,
        string $name = 'table1',
        string $id_query = '',
        string $write_access_query = '',
        ?int $offset = null,
        string $qs = '',
        array $order_by = [1],
        bool $auto_order = false,
        string $id_name = 'id',
        string $view_file = '',
        bool $with_head = true,
        string $action_sep = "<br/>\n",
        array $actions = [],
        bool $export_no_strip_tags = false,
        bool $new_button = false,
        bool $upload_button = false,
        bool $with_count = true,
        bool $export_buttons = false,
        array $additional_export_formats = [],
        bool $jquery_row_click = false,
        string $data_id_query = ''
    ) {
        $this->context = $context;
        $this->from = $from;
        $this->where = $where;
        $this->step = $step;
        $this->name = $name;
        $this->id_query = $id_query;
        $this->prefix = $prefix;
        $this->write_access_query = $write_access_query;
        if ($offset === null)
            $offset = $_GET[$name . 'offset'] ?? 0;
        $this->offset = $offset;
        $this->qs = $qs;
        $this->order_by = $order_by;
        $this->auto_order = $auto_order;
        $this->id_name = $id_name;
        $this->view_file = $view_file;
        $this->with_head = $with_head;
        $this->action_sep = $action_sep;
        $this->actions = $actions;
        $this->export_no_strip_tags = $export_no_strip_tags;
        $this->new_button = $new_button;
        $this->upload_button = $upload_button;
        $this->with_count = $with_count;
        $this->export_buttons = $export_buttons;
        $this->additional_export_formats = $additional_export_formats;
        $this->jquery_row_click = $jquery_row_click;
        $this->data_id_query = $data_id_query;

    }


    /**
     * Create a list based on a table
     * @param \BayCMS\Base\BayCMSContext $context
     * @param string $table table name
     * @param mixed $class_map
     * @param mixed $force_class
     * @param mixed $include_id
     * @return BayCMSList
     */
    static function autoCreate(
        \BayCMS\Base\BayCMSContext $context,
        string $table,
        ?array $class_map = null,
        string $force_class = '',
        bool $include_id = false
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

        $list = new BayCMSList($context, $table . ' t', 'true');

        $res = pg_query($context->getDbConn(), "select * from " . $table . " limit 1");

        $count = 0;
        for ($i = 0; $i < pg_num_fields($res); $i++) {
            $name = pg_field_name($res, $i);
            if ($name == 'id' && !$include_id)
                continue;
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
            $list->addField(field: new $class(context: $context, name: ucfirst($name)));
        }
        return $list;
    }

    /**
     * Creates the SQL-Query
     * @param string $target html|xlsx|json|values
     * @return array [string query, array $fields]
     */
    public function createQuery(string $target = 'html')
    {
        if (isset($_GET[$this->name . 'order_by']))
            $oa = explode("-", $_GET[$this->name . 'order_by']);
        else
            $oa = [0, 'a'];
        $oa[0] = intval($oa[0]);
        if (!isset($oa[1]))
            $oa[1] = '';
        $order_by_query = " order by ";
        if ($this->auto_order) {
            $order_by_query .= (isset($this->order_by[$oa[0]]) ? $this->order_by[$oa[0]] : $oa[0]);
            if ($oa[1] == "d")
                $order_by_query .= " desc";
            for ($i = 0; $i < count($this->order_by); $i++) {
                if ($oa[0] != $i)
                    $order_by_query .= "," . $this->order_by[$i];
            }

        } else {
            $order_by_query .= $this->order_by[0];
            for ($i = 1; $i < count($this->order_by); $i++) {
                $order_by_query .= "," . $this->order_by[$i];
            }
        }



        $fields = [];
        $query = "select ";
        foreach ($this->fields as $f) {
            $query .= $f->getSQL($this->prefix, $target) . ", ";
            $fields[] = $target != 'values' ? $f->getDescription(false) : $f->getName();

        }
        if ($this->id_query)
            $query .= $this->id_query;
        else
            $query .= "1";
        $fields[] = $target == 'values' ? "__id" : 'id';

        if ($target == 'html') {
            if ($this->write_access_query)
                $query .= "," . $this->write_access_query;
            else
                $query .= ',false';
            $fields[] = 'write_access';
            if ($this->data_id_query) {
                $query .= "," . $this->data_id_query;
                $fields[] = 'data_id';

            }
        }


        $query .= " from " . $this->from . " where " . $this->where;
        $query .= $order_by_query;
        if ($this->step > 0)
            $query .= " limit " . $this->step . " offset " . $this->offset;
        return [$query, $fields];
    }


    /**
     * Returns number of rows in the list
     * @param mixed $force
     * @return int
     */
    public function getNumRows($force = false)
    {
        if ($this->num_rows >= 0 && !$force)
            return $this->num_rows;
        $res = pg_query(
            $this->context->getDbConn(),
            'select 1 from ' . $this->from . ' where ' . $this->where
        );
        $this->num_rows = pg_num_rows($res);
        return $this->num_rows;
    }

    /**
     * Creates a bootstrap style pagination
     * @return string
     */
    function bootstrap_pagination()
    {
        $qs=$this->qs;
        if($_GET[$this->name . 'order_by']??'')$qs.="&".$this->name . 'order_by='.$_GET[$this->name . 'order_by'];

        $nameoffset = $this->name . "offset";
        $step = $this->step;
        $href = "<a href=\"?" . $qs . "&$nameoffset=";
        $max = $this->getNumRows();
        if ($max <= $step)
            return;
        if ($step <= 0)
            return;
        $out = '<ul class="pagination">';
        $current = $this->offset / $step + 1;
        if ($current < 1)
            $current = 1;
        if ($current > ceil($max / $step))
            $current = ceil($max / $step);

        $pag = array($current);
        $current_step = 1;
        $last = $current;
        $pag_count = 0;
        while (($current - $current_step) >= 1) {
            $pag_count++;
            if ($max / $step > 12 || $pag_count > 2) {
                $new = ceil(($current - $current_step) / $current_step) * $current_step;
                $current_step *= 10;
            } else {
                $new = round($current - $current_step);
                $current_step++;
                if ($pag_count > 1)
                    $current_step = 10;
            }
            if ($new != $last) {
                $pag[] = $new;
                $last = $new;
            }

        }
        $current_step = 1;
        $last = $current;
        $pag_count = 0;
        while (($current + $current_step) <= ceil($max / $step)) {
            $pag_count++;
            if ($max / $step > 12 || $pag_count > 2) {
                $new = floor(($current + $current_step) / $current_step) * $current_step;
                $current_step *= 10;
            } else {
                $new = round($current + $current_step);
                $current_step++;
                if ($pag_count > 1)
                    $current_step = 10;
            }
            if ($new != $last) {
                $pag[] = $new;
                $last = $new;
            }
        }
        sort($pag);


        $out .= '<li' . ($current > 1 ? '' : ' class="disabled"') . '>' . $href . '0' . '">&laquo;</a></li>';

        $out .= '<li' . ($current > 1 ? '' : ' class="disabled"') . '>' . $href . ($this->offset - $step) . '">&lsaquo;</a></li>';
        for ($i = 0; $i < count($pag); $i++) {
            $out .= '<li' . ($current == $pag[$i] ? ' class="active"' : '') . '>' . $href . (($pag[$i] - 1) * $step) . '">' . $pag[$i] . '</a></li>';
        }
        $out .= '<li' . (($current) >= ceil($max / $step) ? ' class="disabled"' : '') . '>' . $href . ($this->offset + $step) . '">&rsaquo;</a></li>';
        $out .= '<li' . (($current) >= ceil($max / $step) ? ' class="disabled"' : '') . '>' . $href . ((ceil($max / $step) - 1) * $step) . '">&raquo;</a></li>';
        $out .= '</ul>';
        return $out;

    }

    /**
     * Returns the pagination 
     * @return string
     */
    function get_nav_html()
    {
        if ($this->context->TE->isBootstrap())
            return $this->bootstrap_pagination();

        $nameoffset = $this->name . "offset";
        $erste_seite = "|&laquo;&laquo;";
        $vor = "&laquo;&laquo;";
        $zurueck = "&raquo;&raquo;";
        $letzte_seite = "&raquo;&raquo;|";

        //$erste_seite="&laquo;&nbsp;".translate("erste&nbsp;Seite");
        //$vor="&laquo;";
        //$zurueck="&raquo;";
        //$letzte_seite=translate("letzte&nbsp;Seite")."&nbsp;&raquo;";

        $qs=$this->qs;
        if($_GET[$this->name . 'order_by']??'')$qs.="&".$this->name . 'order_by='.$_GET[$this->name . 'order_by'];

        $num = $this->getNumRows();
        if ($num <= $this->step || $this->step < 0) {
            $this->offset = 0;
            $back_forward_html = "";
        } else {
            if ($this->offset == 0)
                $back_forward_html = "$erste_seite&nbsp;$vor&nbsp;..&nbsp;";
            else
                $back_forward_html = "<a href=\"?$nameoffset=0&" . $qs . "\">$erste_seite</a>&nbsp;<a href=\"?$nameoffset=" . ($this->offset - $this->step) . "&" . $qs . "\">$vor</a>&nbsp;..&nbsp;";
            $step = 1;
            while ($num / $this->step / $step > 10) {
                $step *= 2;
                if ($num / $this->step / $step > 10)
                    $step *= 5;
            }
            for ($i = $step; $i < $num / $this->step; $i += $step) {
                if ($i * $this->step == $this->offset)
                    $back_forward_html .= ($i * $this->step) . "&nbsp;..&nbsp;";
                else
                    $back_forward_html .= "<a href=\"?$nameoffset=" . ($i * $this->step) . "&" . $qs . "\">" . ($i * $this->step) . "</a>&nbsp;..&nbsp;";
            }
            if (($this->offset + $this->step) >= $num)
                $back_forward_html .= "$zurueck&nbsp;$letzte_seite";
            else
                $back_forward_html .= "<a href=\"?$nameoffset=" . ($this->offset + $this->step) . "&" . $qs . "\">$zurueck</a>&nbsp;<a href=\"?$nameoffset=" . ($this->step * floor(($num - 1) / $this->step)) . "&" . $qs . "\">$letzte_seite</a>";
            $back_forward_html .= "<br>";
        }
        return $back_forward_html;
    }

    /**
     * Returns action Link
     * @param mixed $id
     * @param mixed $write_access
     * @param mixed $type
     */
    function getAction($id, $write_access, $type)
    {
        switch ($type) {
            case "del":
                $action = $this->context->TE->getActionLink(
                    "?aktion=del&" . $this->id_name . "=$id&" . $this->qs,
                    $this->t('delete', 'löschen'),
                    " onClick=\"return confirm('" . $this->t('Do you really want to delete the row?', 'Zeile wirklich löschen?') . "')\"",
                    $type
                );
                if ($write_access != "f")
                    return $action;
                else
                    return "-";
            case "view":
                if (!$this->view_file)
                    $this->view_file = "?" . $this->qs . '&' . $this->id_name . '=';
                return $this->context->TE->getActionLink(
                    $this->view_file . $id,
                    $this->t('show', 'ansehen'),
                    '',
                    'eye-open'
                );
            case "copy":
                return $this->context->TE->getActionLink(
                    "?aktion=copy&" . $this->id_name . "=$id&" . $this->qs,
                    $this->t('copy', 'kopieren'),
                    '',
                    $type
                );
            case "edit":
                $action = $this->context->TE->getActionLink(
                    "?aktion=edit&" . $this->id_name . "=$id&" . $this->qs,
                    $this->t('edit', 'bearbeiten'),
                    '',
                    $type
                );
                if ($write_access != "f")
                    return $action;
                else
                    return "-";
            case "edit_copy":
                return $this->getAction($id, $write_access, ($write_access != "f" ? "edit" : "copy"));
            case "properties":
                return $this->context->TE->getActionLink(
                    '/' . $this->context->getOrgLinkLang() . "/intern/gru/objekt_detail.php?id_obj=$id",
                    $this->t('object properties', 'Objekt Eigenschaften'),
                    '',
                    $type
                );
        }
    }

    /**
     * returns all actions buttons
     * @param mixed $id
     * @param mixed $write_access
     * @return string
     */
    function getActions($id, $write_access = "t")
    {
        if (!count($this->actions))
            return '';

        $out = '';
        foreach ($this->actions as $a) {
            if ($out)
                $out .= $this->action_sep;
            $out .= $this->getAction($id, $write_access, $a);
        }
        $out = "<td>$out</td>";

        return $out;
    }

    /**
     * Returns a array of arrays of the values
     * @return array[]
     */
    public function getValues(): array
    {
        [$query, $fields] = $this->createQuery('values');

        $res = pg_query($this->context->getDbConn(), $query);
        $values = [];
        $num_fields = count($fields);
        for ($j = 0; $j < pg_num_rows($res); $j++) {
            $r = pg_fetch_row($res, $j);
            $values[] = array_combine($fields, array_slice($r, 0, $num_fields));
        }
        return $values;
    }

    /**
     * Returns the list in json format
     * @return bool|string
     */
    public function getJSON()
    {
        [$query, $fields] = $this->createQuery('json');

        $res = pg_query($this->context->getDbConn(), $query);
        $json = [];
        for ($j = 0; $j < pg_num_rows($res); $j++) {
            $r = pg_fetch_row($res, $j);
            $json[] = array_combine($fields, $r);
        }
        return json_encode($json);
    }

    /**
     * Writes export
     * @param mixed $ending
     * @param mixed $basename
     * @throws \BayCMS\Exception\unsupportedFiletype
     * @return never
     */
    public function pageExport($ending, $basename)
    {
        require_once("PHPExcel.php");
        // Create new PHPExcel object
        switch ($ending) {
            case 'xlsx':
                $type = 'Excel2007';
                $mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                break;
            case 'xls':
                $type = 'Excel5';
                $mime = 'vapplication/nd.ms-excel';
                break;
            case 'pdf':
                $type = 'PDF';
                $mime = 'application/pdf';
                break;
            case 'csv':
                $type = 'CSV';
                $mime = 'text/x-csv';
                break;
            default:
                throw new \BayCMS\Exception\unsupportedFiletype("Unsupported export format $type");
        }

        [$query, $fields]=$this->createQuery('xlsx');

        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->getProperties()->setCreator("BayCMS")
            ->setLastModifiedBy("BayCMS");
        $objPHPExcel->getActiveSheet()->fromArray($fields, NULL, 'A1');
        $first_letter = \PHPExcel_Cell::stringFromColumnIndex(0);
        $last_letter = \PHPExcel_Cell::stringFromColumnIndex(count($fields) - 1);
        $header_range = "{$first_letter}1:{$last_letter}1";
        $objPHPExcel->getActiveSheet()->getStyle($header_range)->getFont()->setBold(true);
        date_default_timezone_set('GMT');

        $res = pg_query($this->context->getDbConn(), $query);
        for ($i = 0; $i < pg_num_rows($res); $i++) {
            $r = pg_fetch_row($res, $i);

            for ($j = 0; $j < count($fields); $j++) {
                $ct = pg_field_type($res, $j);
                if ($this->export_no_strip_tags)
                    $value = strip_tags($r[$j]);
                else
                    $value = $r[$j];

                $cell = \PHPExcel_Cell::stringFromColumnIndex($j) . ($i + 2);
                if ($value) {
                    if ($ct == 'bool') {
                        $value = ($r[$j] == 't' ? 1 : 0);
                    } elseif (strstr($ct, 'timestamp')) {
                        $t = date_parse_from_format('Y-m-d H:i:s', $r[$j]);
                        $value = \PHPExcel_Shared_Date::PHPToExcel(
                            mktime(
                                $t['hour'],
                                $t['minute'],
                                $t['second'],
                                $t['month'],
                                $t['day'],
                                $t['year']
                            )
                        );
                        $objPHPExcel->getActiveSheet()->getStyle($cell)
                            ->getNumberFormat()
                            ->setFormatCode('dd.mm.yyyy hh:mm:ss');

                    } elseif (strstr($ct, 'time')) {
                        $t = date_parse_from_format('H:i:s', $r[$j]);
                        $value = $t['hour'] / 24 + $t['minute'] / 24 / 60 + $t['second'] / 24 / 60 / 60;
                        $objPHPExcel->getActiveSheet()->getStyle($cell)
                            ->getNumberFormat()
                            ->setFormatCode('hh:mm:ss');

                    } elseif ($ct == 'date') {
                        $t = date_parse_from_format('Y-m-d', $r[$j]);
                        $value = \PHPExcel_Shared_Date::PHPToExcel(
                            mktime(0, 0, 0, $t['month'], $t['day'], $t['year'])
                        );
                        $objPHPExcel->getActiveSheet()->getStyle($cell)
                            ->getNumberFormat()
                            ->setFormatCode('dd.mm.yyyy');
                    }
                }
                $objPHPExcel->getActiveSheet()->setCellValue($cell, html_entity_decode($value, ENT_COMPAT, 'UTF-8'));


            }
        }


        // Rename worksheet
        $objPHPExcel->getActiveSheet()->setTitle('Export');


        // Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $objPHPExcel->setActiveSheetIndex(0);


        // Redirect output to a client’s web browser (Excel2007)
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment;filename="' . $basename . '.' . $ending . '"');
        header('Cache-Control: max-age=0');

        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, $type);
        $objWriter->save('php://output');
        exit;

    }

    /**
     * Returns an HTML-Table including buttions and pagination
     * @param int $id
     * @return string
     */
    public function getTable(?int $id = null)
    {
        $num = $this->getNumRows();
        $i_id = count($this->fields);
        $i_writeaccess = $i_id + 1;
        $i_data = $this->data_id_query ? $i_id + 2 : $i_id;
        $out = '';
        if ($this->new_button) {
            $out .= $this->context->TE->getActionLink(
                "?aktion=edit&" . $this->qs,
                $this->t('New Entry', "Neuer Eintrag"),
                '',
                'new'
            );
        }
        if ($this->upload_button) {
            if ($out)
                $out .= " &nbsp; ";
            $out .= $this->context->TE->getActionLink(
                "?aktion=upload&" . $this->qs,
                $this->t('Upload Data', "Daten Hochladen"),
                '',
                'open',
                ['class' => '']
            );
        }
        if ($out)
            $out .= "<br/>\n";


        if ($this->with_count) {
            if ($this->step < 0)
                $step = $num;
            else
                $step = $this->step;
            $out .= "&nbsp;" . min($this->offset + 1, $num) . "-" . min($this->offset + $step, $num) . " " .
                $this->t('of', 'von') . " $num<br>";
        }

        $back_forward_html = $this->get_nav_html();
        $out .= $back_forward_html;
        $out .= "<table id=\"" . $this->name . '" ' . $this->context->TE->getCSSClass((($settings['border'] ?? true) ? 'list_table' : 'list_table_without_border')) . '>';

        if ($this->with_head) {
            $out .= '<tr class="list_head">';
            for ($i = 0; $i < count($this->fields); $i++) {
                $out .= '<th>' . $this->fields[$i]->getDescription(false);
                if ($this->auto_order && isset($this->order_by[$i])) {
                    $out .= " ";
                    if (($_GET[$this->name . 'order_by'] ?? '') == "$i")
                        $out .= "<b>&darr;</b>";
                    else
                        $out .= "<a href=\"?" . $this->qs . "&" . $this->name . "order_by=$i\">&darr;</a>";
                    if (($_GET[$this->name . 'order_by'] ?? '') == "$i-d")
                        $out .= "<b>&uarr;</b>";
                    else
                        $out .= "<a href=\"?" . $this->qs . "&" . $this->name . "order_by=$i-d\">&uarr;</a>";
                }

                $out .= "</th>";
            }
            for ($i = 0; $i < count($this->actions); $i++) {
                if ($this->action_sep != "</td><td>") {
                    $i = count($this->actions);
                }
                $out .= "<th>&nbsp;</th>";
            }
            $out .= "</tr>";
        }

        if ($id && $this->id_query && $num > 1) {
            //erste Zeile mit ID
            //save old settings
            $where = $this->where;
            $offset = $this->offset;
            $num_rows = $this->num_rows;
            //manually set new ons
            $this->where .= " and " . $this->id_query . "=" . $id;
            $this->offset = 0;
            $this->num_rows = 1;

            [$query, $fields]=$this->createQuery();
            $res = pg_query($this->context->getDbConn(), $query);
            if (pg_num_rows($res)) {
                $r = pg_fetch_row($res, 0);
                $out .= '<tr valign="top" class="list_row_odd" data-id="' . addslashes($r[$i_data]) . '">';
                for ($i = 0; $i < count($this->fields); $i++) {
                    $out .= "<td class=\"baycmslist-data-td\">$r[$i]</td>";
                }
                $out .= $this->getActions($r[$i_id], $r[$i_writeaccess] ?? 't');
                $out .= "</tr>\n";
                $cols = count($this->fields);
                if ($this->action_sep != "</td><td>")
                    $cols += count($this->actions);
                else
                    $cols++;
                $out .= "<tr class=\"list_row_even\"><td colspan=\"$cols\">&nbsp;</td></tr>\n";
                //restore old settings
            }
            $this->where = $where . " and " . $this->id_query . "!=" . $id;
            $this->offset = $offset;
            $this->num_rows = $num_rows;
        }
        
        [$query, $fields]=$this->createQuery();
        $res = pg_query($this->context->getDbConn(), $query);
        $class = 'odd';
        for ($j = 0; $j < pg_num_rows($res); $j++) {
            $r = pg_fetch_row($res, $j);
            $out .= '<tr valign="top" class="list_row_' . $class . '" data-id="' . addslashes($r[$i_data]) . '">';
            $class = ($class == 'odd' ? 'even' : 'odd');
            for ($i = 0; $i < count($this->fields); $i++) {
                $out .= "<td class=\"baycmslist-data-td\">$r[$i]</td>";
            }
            $out .= $this->getActions($r[$i_id], $r[$i_writeaccess] ?? 't');
            $out .= "</tr>\n";
        }
        $out .= "</table>";
        if ($this->context->TE->isBootstrap())
            $out .= '<div class="clearfix"></div>';
        $out .= $back_forward_html;
        if ($this->context->TE->isBootstrap())
            $out .= '<div class="clearfix"></div>';
        if ($this->jquery_row_click)
            $out .= '
        <script>
        $("#' . $this->name . ' td.baycmslist-data-td").on("click",function(){
            if(! $(this).parent().attr("data-id")) return;
            ' . (($_GET['js_select'] ?? false) ?
                'var tr=$(this).parent()
                add(tr.attr("data-id"),tr.find("td:first-child").text())' :
                'window.location.href="?' . $this->id_name . '="+$(this).parent().attr("data-id")+"&' . $this->qs . '";'
            ) . '
        });
        </script>';

        if ($_GET['js_select'] ?? false) {
            $out .= $this->t("Click on a row to select it", "Klicken Sie auf eine Zeile, um diese auszuwählen");
            if ($_GET['js_select'] == 1)
                $out .= " -- " . $this->context->TE->getActionLink(
                    "#",
                    $this->t('No selection', 'keine Auswahl'),
                    " onClick=\"add('','')\" ",
                    'remove'
                ) . "\n";
            $out .= "<br/>\n";
        }
        if ($this->export_buttons) {
            $out .= "Export: ";
            $qs = $this->qs;
            if ($qs)
                $qs = "&$qs";

            $out .= $this->context->TE->getActionLink(
                '?baycmsExportFormat=xlsx' . $qs,
                'Download xlsx',
                '',
                'save'
            );
            $out .= " ";
            $out .= $this->context->TE->getActionLink(
                '?baycmsExportFormat=csv' . $qs,
                'Download csv',
                '',
                'save'
            );
            foreach ($this->additional_export_formats as $f) {
                $out .= " ";
                $out .= $this->context->TE->getActionLink($f['link'], $f['name'], $f['attr'] ?? '', $f['icon'] ?? '');
            }
        }
        return $out;

    }


}