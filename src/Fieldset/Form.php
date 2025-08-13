<?php

namespace BayCMS\Fieldset;

class Form extends TabFieldset
{

    protected ?int $id = null;
    protected string $id_name;
    protected string $method;
    protected string $action;
    protected string $write_access_query;

    protected ?string $uname;
    protected bool $object_only = false;
    protected string $qs;
    protected bool $cancel_button;
    protected bool $delete_button;
    protected ?bool $object_button;
    protected ?string $submit;
    protected string $postform_html;
    protected string $table;
    protected ?string $de;
    protected ?string $en;
    protected ?int $id_parent;
    protected ?string $stichwort;

    protected string $form_options;
    private ?bool $write_access;

    public function __construct(
        \BayCMS\Base\BayCMSContext $context,
        string $name = 'form1',
        string $method = 'post',
        string $action = '?aktion=save',
        string $write_access_query = '',
        array $div_names = [],
        string $id_name = 'id',
        ?string $uname = null,
        string $qs = '',
        string $form_options = '',
        bool $cancel_button = true,
        bool $delete_button = true,
        ?bool $object_button = null,
        ?string $submit = null,
        string $postform_html = '',
        string $table = '',
        ?string $de = null,
        ?string $en = null,
        ?int $id_parent = -1,
        ?string $stichwort = null,
        bool $object_only = false
    ) {
        $this->name = $name;
        $this->method = $method;
        $this->action = $action;
        $this->write_access_query = $write_access_query;
        $this->div_names = $div_names;
        $this->id_name = $id_name;
        $this->uname = $uname;
        $this->qs = $qs;
        $this->form_options = $form_options;
        $this->context = $context;
        $this->cancel_button = $cancel_button;
        $this->object_button = $object_button;
        $this->delete_button = $delete_button;
        $this->submit = $submit;
        $this->postform_html = $postform_html;
        $this->table = $table;
        $this->de = $de;
        $this->en = $en;
        $this->id_parent = $id_parent;
        $this->stichwort = $stichwort;
        $this->object_only = $object_only;
    }


    public function __get(string $name)
    {
        return $this->$name;
    }
    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId($id=null)
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns an HTML-Table of the form values
     * @param bool $delete_link
     * @param bool $edit_link
     * @param bool $object_link
     * @param bool $takeover_link
     * @param string $pos
     * @param bool $skip_empty
     * @param bool $no_copy_link
     * @return string
     */
    public function getTable(
        bool $delete_link = true,
        bool $edit_link = true,
        ?bool $object_link = null,
        bool $takeover_link = true,
        string $pos = 'bottom',
        bool $skip_empty = false,
        bool $no_copy_link = false
    ): string {
        $out = '<table ' . $this->context->TE->getCSSClass('table') . ">\n";
        $rows = '';
        foreach ($this->fields as $f) {
            if ($skip_empty && !$f->getDisplayValue())
                continue;
            $rows .= $f->getTableRow();
        }
        $actions = '';
        if ($delete_link && $this->writeAccess())
            $actions .= $this->context->TE->getActionLink(
                "?aktion=del&" . $this->id_name . "=" . $this->id . "&" . $this->qs,
                $this->t("delete", "löschen"),
                " onClick=\"return confirm('" . $this->t("Are you sure?", "Wirklich löschen?") . "')\"",
                'del'
            ) . " ";

        if ($object_link ?? ($this->uname ?? false))
            $actions .= $this->context->TE->getActionLink(
                '/' . $this->context->getOrgLinkLang() . "/intern/gru/objekt_detail.php?id_obj=" . $this->id,
                $this->t("Object Properties", "Objekt Eigenschaften"),
                '',
                'properties'
            ) . " ";

        if ($edit_link) {
            if ($this->writeAccess())
                $actions .= $this->context->TE->getActionLink(
                    "?aktion=edit&" . $this->id_name . "=" . $this->id . "&" . $this->qs,
                    $this->t("edit", "bearbeiten"),
                    "",
                    'edit'
                ) . " ";
            elseif (!$no_copy_link)
                $actions .= $this->context->TE->getActionLink(
                    "?aktion=copy&" . $this->id_name . "=" . $this->id . "&" . $this->qs,
                    $this->t("copy", "kopieren"),
                    "",
                    'edit'
                ) . " ";
        }

        if ($takeover_link && ($_GET['js_select'] ?? false)){
            $v=$this->getField(0)->getValue();
            if(is_array($v)) $v=(($v['de']??'')?$v['de']:($v['en']??''));
            $actions .= $this->context->TE->getActionLink(
                "#",
                $this->t("take over", "übernehmen"),
                " onClick=\"add('" . $this->id . "','" . addslashes($v) . "')\"",
                'ok'
            ) . " ";
        }
            



        if ($actions && ($pos == 'top' || $pos == 'both'))
            $out .= "<tr><td colspan=2>$actions</td></tr>\n";
        $out .= $rows;
        if ($actions && ($pos == 'bottom' || $pos == 'both'))
            $out .= "<tr><td colspan=2>$actions</td></tr>\n";
        $out .= "</table>\n";
        return $out;
    }

    /**
     * Creates the help-JS for the help buttons
     * @return string
     */
    private function getHelpJS()
    {
        $out = "<script>
        var " . $this->name . "_changed = false;";

        $out .= "
   \$(document).ready(function(){
      $('[data-toggle=\"popover\"]').popover();   
  });</script>";

        return $out;
    }

    /**
     * Returns the form as HTML
     * @param mixed $heading
     * @return string
     */
    public function getForm($heading = '')
    {

        $out = $this->getHelpJS();
        $action = $this->action;
        if ($this->id)
            $action .= "&" . ($this->id_name) . '=' . $this->id;
        if ($this->qs)
            $action .= '&' . $this->qs;



        $out .= '<form method="' . $this->method .
            '" id="' . $this->name .
            '" name="' . $this->name .
            '" action="' . $action . '" enctype="multipart/form-data"
             accept-charset="UTF-8" ' . $this->form_options . '>' . "\n";
        $footnotes = [];
        $div_id = '';
        $out .= "<fieldset>\n<legend>$heading</legend>\n";
        foreach ($this->fields as $f) {
            if ($div_id != $f->getDivID()) {
                if (!$div_id)
                    $out .= "<!-- INSERT_FIELDSET_TAB -->";
                else
                    $out .= "</div>";
                if (!isset($this->divs[$this->nameToId($f->getDivID())])) {
                    $this->divs[$this->nameToId($f->getDivID())] =
                        $this->div_names[$f->getDivID()] ?? $f->getDivID();
                }
                $out .= '
                <div class="formclass_fieldset" id="' . $this->nameToId($f->getDivID()) . '" ' . ($div_id ? 'style="display:none;"' : '') . '>';
                $div_id = $f->getDivID();
            }
            $out .= $f->getFormRow($this);
            $out .= $f->getPostInput();
            $fn = $f->getFootnote();
            if ($fn)
                $footnotes[$fn[0]] = $fn[1];
        }
        if ($div_id)
            $out .= "</div>";
        $out .= '</fieldset>';
        $out = str_replace('<!-- INSERT_FIELDSET_TAB -->', $this->getFieldsetTab(), $out);

        //Buttons
        if ($this->context->TE->isBootstrap())
            $class = "btn btn-primary";
        else
            $class = "button";
        $out .= '
        <fieldset><div class="formrow">
        <input class="' . $class . '" type="reset" value="' . $this->t("reset", "zurücksetzen") . '"        
         onClick="' . $this->name . '_changed==false;">' . "\n";
        if ($this->cancel_button)
            $out .= '<input class="' . $class . '" type="button" value="' . $this->t("cancel", "abbrechen") . '"
        onClick="if(' . $this->name . '_changed==false || confirm(\'' . $this->t("Are you sure?", "Wirklich abbrechen?") . '\')){ 
          location.href=\'?' . ($this->qs ?? '') . '\';
            } else { return; }">' . "\n";

        if ($this->id !== null && $this->delete_button && $this->writeAccess())
            $out .= '<input class="' . $class . '" type="button" onClick="if(confirm(\'' . $this->t("Are you sure?", "Wirklich löschen?") . '\')){
            location.href=\'?aktion=del&' . $this->id_name . '=' . $this->id . ($this->qs ? '&' . $this->qs : '') . '\';
          } else {
            return;
          }" value="' . $this->t("delete", 'löschen') . '">' . "\n";

        if (
            $this->id !== null && ($this->object_button ??
                ($this->uname ?? false))
        )
            $out .= '<input type="button" class="' . $class . '" onClick="location.href=\'/' . $this->context->getOrgLinkLang() . '/intern/gru/objekt_detail.php?id_obj=' . $this->id . '\'" 
          value="' . $this->t('Object Properties', "Objekt Eigenschaften") . '"> ' . "\n";

        $out .= '<input class="' . $class . '" type="submit" value="' .
            ($this->submit ?? $this->t('save', 'speichern')) . '">
        </div></fieldset>' . "\n";

        $out .= '</form>' . "\n";
        $out .= '<script>
        $(\'form#' . $this->name . ' :input\').change(function(){
           ' . $this->name . '_changed=true; 
      });
      $(\'form#' . $this->name . '\').submit(function(e) {
        $(\':disabled\').each(function(e) {
            $(this).removeAttr(\'disabled\');
        })
      });
    
        </script>';
        //Footnotes
        foreach ($footnotes as $key => &$value) {
            $out .= $key . " " . $value . "<br/>\n";
        }
        $out .= $this->postform_html ?? '';
        return $out;
    }



    /**
     * Saves the form to the database
     * returns the ID of the row or false on failure
     * @throws \BayCMS\Exception\missingData
     * @return bool|int|null
     */
    public function save()
    {
        $table = $this->table;
        if (!$table) {
            throw new \BayCMS\Exception\missingData("Cannot save form without setting table");
        }

        $ok=pg_query($this->context->getRwDbConn(), 'begin');
        $uname = $this->uname ?? false;
        if ($uname) {
            $obj = new \BayCMS\Base\BayCMSObject($this->context);
            $prop = ['uname' => $uname, 'id_art' => null];
            $de = $this->de ?? ($_POST['de'] ?? null);
            if ($de !== null)
                $prop['de'] = $de;
            $en = $this->en ?? ($_POST['en'] ?? null);
            if ($en !== null)
                $prop['en'] = $en;
            $id_parent = $this->id_parent == -1 ? ($_POST['id_parent'] ?? -1) : $this->id_parent;
            if ($id_parent != -1)
                $prop['id_parent'] = $id_parent;
            $stichwort = $this->stichwort ?? ($_POST['stichwort'] ?? null);
            if ($stichwort !== null)
                $prop['stichwort'] = $stichwort;
            if (!is_null($this->id))
                $obj->load($this->id);
            $obj->set($prop);
            $id = $obj->save(false);
        } else {
            //Table only
            if (is_null($this->id)) {
                $res = pg_query($this->context->getRwDbConn(), "select nextval('" . $table . "_id_seq')");
                [$id] = pg_fetch_row($res, 0);
            }

        }
        $i = 1;
        $insert_query_fields = '';
        $insert_query_values = '';
        $update_query = 'update ' . $table . ' set ';
        $values = [];
        foreach ($this->getValues() as $f => $v) {
            $comma = $i > 1 ? ', ' : '';
            $insert_query_fields .= $comma . $f;
            $insert_query_values .= $comma . '$' . $i;
            $update_query .= $comma . $f . '=$' . $i;
            $i++;
            $values[] = $v;
        }

        if (!$this->object_only) {
            if (is_null($this->id)) {
                $query = 'insert into ' . $table . '(' . $insert_query_fields . ',id) 
                values (' . $insert_query_values . ',$' . $i . ')';
                $this->id = $id;
            } else {
                $query = $update_query . ' where id=$' . $i;
            }
            $values[] = $this->id;

            $ok = pg_query_params($this->context->getRwDbConn(), $query, $values);
            if (!$ok) {
                $e = pg_last_error($this->context->getRwDbConn());
                pg_query($this->context->getRwDbConn(), 'rollback');
                throw new \BayCMS\Exception\databaseError($e);
            }
        } else if($this->id === null) {
            $this->id=$id;
        }

        foreach ($this->fields as $f) {
            $ok = $f->save($this);
            if (!$ok) {
                break;
            }
        }

        if ($ok) {
            pg_query($this->context->getRwDbConn(), 'commit');
            return $this->id;
        }
        return false;
    }

    /**
     * Loads the form values from the database
     * @param int $id
     * @throws \BayCMS\Exception\missingData
     * @throws \BayCMS\Exception\notFound
     * @return void
     */
    public function load(int $id)
    {
        $table = $this->table;
        if (!$table) {
            throw new \BayCMS\Exception\missingData("Cannot load form without setting table");
        }

        $res = pg_query_params($this->context->getRwDbConn(), 'select * from ' . $table . ' where id=$1', [$id]);
        if (!pg_num_rows($res)) {
            throw new \BayCMS\Exception\notFound('Could not load row with id=' . $id);
        }
        $r = pg_fetch_array($res, 0);
        $this->id = $id;
        $this->setValues($r);
    }

    /**
     * Checks write access
     * @return bool|null
     */
    public function writeAccess()
    {
        if (isset($this->write_access))
            return $this->write_access;
        if (($this->uname ?? false) && !($this->write_access_query || '')) {
            $this->write_access_query = 'check_objekt($1,' .
                $this->context->get('row1', 'id_benutzer', 0) . ')';
        }

        if ($this->write_access_query) {
            $res = pg_query_params(
                $this->context->getRwDbConn(),
                'select ' . $this->write_access_query . ' from ' .
                $this->table . ' t where id=$1',
                [$this->id]
            );
            if (!pg_num_rows($res))
                return false;
            $r = pg_fetch_row($res, 0);
            $this->write_access = ($r[0] == 't' ? true : false);
        } else
            $this->write_access = true;

        return $this->write_access;
    }

    /**
     * Set all field values using an array (e.g. $_POST or $_GET)
     * @param array $values
     * @return float|int
     */
    public function setValues(array &$values): int
    {
        $error = 0;
        foreach ($this->fields as $f) {
            $error += $f->setValueFromArray($values, $this->id);
        }
        return $error;
    }

    /**
     * Returns the values as array
     * @return array
     */
    public function getValues(): array
    {
        $values = [];
        foreach ($this->fields as $f) {
            if ($f->getNoAddToQuery())
                continue;
            $v = $f->getValue();
            if (is_array($v)) {
                foreach ($v as $k => $v) {
                    $values[$k] = $v;
                }
            } else
                $values[$f->getName()] = $v;

        }
        return $values;
    }

    /**
     * Returns a search form as HTML
     * @param mixed $with_description
     * @return string
     */
    public function getSearchForm($with_description = false)
    {
        $out = '<form id="' . $this->name . '" name="' . $this->name . '">';
        foreach ($this->fields as $f) {
            $checkbox = get_class($f) == 'BayCMS\\Field\\Checkbox';
            if ($checkbox)
                $out .= '<br/>';
            $out .= $f->getInput($this);
            if ($with_description || $checkbox)
                $out .= ' <label for="' . $f->getID($this) . '"' . ($checkbox ? ' style="width:90%;"' : '') . '>' .
                    $f->getDescription(false) . '</label>';
        }
        $key_hash = [];
        if ($this->qs) {
            foreach (explode('&', $this->qs) as $f) {
                [$key, $value] = explode("=", $f);
                $out .= '<input type="hidden" name="' . $key . '" value="' . htmlspecialchars($value) . '">';
                $key_hash[$key] = 1;
            }
        }
        if (($_GET['js_select'] ?? false) && !isset($key_hash['js_select']))
            $out .= '<input type="hidden" name="js_select" value="' . htmlspecialchars($_GET['js_select']) . '">';
        if (($_GET['target'] ?? false) && !isset($key_hash['target']))
            $out .= '<input type="hidden" name="target" value="' . htmlspecialchars($_GET['target']) . '">';


        $out .= '<input type="submit" value="' . $this->t('search', 'suchen') . '"></form>';
        return $out;
    }

    /**
     * Creates a querystring based on a form (e.g. a search form)
     * @return string
     */
    public function getQS()
    {
        $qs = [];
        foreach ($this->getValues() as $k => $v) {
            if ($v)
                $qs[] = $k . "=" . urlencode($v);
        }
        return implode('&', $qs);
    }

}