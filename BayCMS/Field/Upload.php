<?php

namespace BayCMS\Field;


class Upload extends File
{

    protected \BayCMS\Fieldset\Fieldset $fields;
    protected string $preg_match;

    public function __construct(
        \BayCMS\Base\BayCMSContext $context,
        string $name,
        string $description = null,
        string $id = '',
        string $sql = '',
        string $help = '',
        string $label_css = '',
        string $input_options = '',
        string $post_input='',
        string $placeholder = '',
        bool $no_add_to_query = true,
        bool $not_in_table = false,
        bool $non_empty = false,
        array $footnote = null,
        mixed $default_value = null,
        string $div_id='',
        string $accept = '',
        int $max_size=0,
        string $preg_match=''
    ) {
        parent::__construct($context, $name, $description, $id, $sql, $help, $label_css, $input_options, $post_input, $placeholder, $no_add_to_query, $not_in_table, $non_empty, $footnote, $default_value,$div_id, $accept, $max_size);
        $this->fields = new \BayCMS\Fieldset\Fieldset();
        $this->fields->addField(new Hidden(context: $context, name: $this->name . '_location'));
        $this->fields->addField(new Hidden(context: $context, name: $this->name . '_name'));
        $this->fields->addField(new Checkbox($context, name: $this->name . '_del', description: $this->t('delete file', 'Datei löschen')));
        $this->preg_match=$preg_match;
    }


    public function getFileLocation(){
        return $this->fields->getField($this->name . "_location")->getValue();
    }

    public function getFileName(){
        return $this->fields->getField($this->name . "_name")->getValue();
    }

    public function setValueFromArray(array &$v, ?int $id = null): bool
    {

        $this->error = false;
        $this->inline_error = '';
        if (!isset($v[$this->name . '_name']))
            return $this->error; //not called with $_POST

        if ($_FILES[$this->name]['tmp_name'] ?? false) {
            if (is_writable($_POST[$this->name . '_location'] ?? false))
                unlink($_POST[$this->name . '_location']);

            $this->fields->getField($this->name . '_location')->setValue(null);
            $this->fields->getField($this->name . '_name')->setValue(null);
            if (
                $this->preg_match &&
                !preg_match($this->preg_match, $_FILES[$this->name]['name'])
            ) {
                $this->error = true;
                $this->inline_error = $this->t('Not accepted file type', 'Nicht akzeptierter Dateityp') . ': ' . htmlspecialchars($_FILES[$this->name]['name']);
                return $this->error;
            }
            $newname = tempnam($this->context->BayCMSRoot . "/tmp", session_id());
            move_uploaded_file($_FILES[$this->name]['tmp_name'], $newname);
            $this->fields->getField($this->name . '_location')->setValue($newname);
            $this->fields->getField($this->name . '_name')->setValue($_FILES[$this->name]['name']);
        } elseif ($_POST[$this->name . '_del'] ?? false) {
            if (is_writable($_POST[$this->name . '_location'] ?? false))
                unlink($_POST[$this->name . '_location']);

            $this->fields->getField($this->name . '_location')->setValue(null);
            $this->fields->getField($this->name . '_name')->setValue(null);
        } elseif ($_POST[$this->name . '_name'] ?? false) {
            $this->fields->getField($this->name . '_location')->setValue($_POST[$this->name . '_location'] ?? null);
            $this->fields->getField($this->name . '_name')->setValue($_POST[$this->name . '_name']);
            if (!is_readable($_POST[$this->name . '_location'] ?? null)) {
                //File does not exist any more on the server....
                $this->fields->getField($this->name . '_location')->setValue(null);
                $this->fields->getField($this->name . '_name')->setValue(null);
            }
        }
        if ($this->non_empty && !$this->fields->getField($this->name . '_location')->getValue())
            $this->error = true;
        return (bool) $this->error;
    }

    public function getInput(\BayCMS\Fieldset\Form $form): string
    {
        $out = parent::getInput($form);
        $out .= $this->fields->getField($this->name . '_name')->getInput($form);
        $out .= $this->fields->getField($this->name . '_location')->getInput($form);

        $location = $this->fields->getField($this->name . '_location')->getValue();
        $name = $this->fields->getField($this->name . '_name')->getValue();
        if ($location) {
            $res = pg_query_params(
                $this->context->getDbConn(),
                'select get_filetype_image($1)',
                [$name]
            );
            [$img] = pg_fetch_row($res, 0);
            $size = round(filesize($location) / 1024);
            $out .= "<br/><a href=\"/" . $this->context->org_folder . "/" . $this->context->lang . "/top/gru/get.php?f=";
            $out .= urlencode($location);
            $out .= "&n=";
            $out .= urlencode($name);
            $out .= "\" target=\"_blank\">$img $name ($size kb)</a> ";
            $f = $this->fields->getField($this->name . '_del');
            $out .= $f->getInput($form);
            $out .= " " . $f->getDescription();
        }
        return $out;

    }


}