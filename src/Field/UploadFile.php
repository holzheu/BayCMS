<?php

namespace BayCMS\Field;

use function PHPUnit\Framework\returnSelf;

class UploadFile extends Upload
{
    protected string $path;
    protected string $uname;
    protected ?int $self_edit;
    public function __construct(
        \BayCMS\Base\BayCMSContext $context,
        string $name,
        string $description = null,
        string $id = '',
        string $sql = '',
        string $help = '',
        string $label_css = '',
        string $input_options = '',
        string $post_input = '',
        string $placeholder = '',
        bool $no_add_to_query = true,
        bool $not_in_table = false,
        bool $non_empty = false,
        array $footnote = null,
        mixed $default_value = null,
        string $div_id = '',
        string $accept = '',
        string $path = 'inc/named_files',
        string $uname = null,
        string $preg_match = '',
        int $self_edit=null,
        int $max_size=0
    ) {
        parent::__construct($context, $name, $description, $id, $sql, $help, $label_css, $input_options, $post_input, $placeholder, $no_add_to_query, $not_in_table, $non_empty, $footnote, $default_value, $div_id, $accept,$max_size, $preg_match);
        $this->path = $path;
        if (is_null($uname))
            $uname = $name;
        $this->uname = $uname;
        $this->self_edit = $self_edit;
    }

    private function checkSelfEdit(array $f): bool
    {
        if (is_null($this->self_edit))
            return true;
        if ($this->context->getPower() >= 1000)
            return true;
        if (($f['extract'] ?? 0) < $this->self_edit)
            return true;
        return false;
    }

    private function readFile(int $id): array
    {
        $res = pg_query_params(
            $this->context->getRwDbConn(),
            'select f.id,f.de as uname, o.id_benutzer,extract(epoch from (now()-o.ctime))
                from file f, objekt o 
                where f.id=o.id and o.id_obj=$1 and f.de=$2',
            [$id, $this->uname]
        );
        if (pg_num_rows($res))
            return pg_fetch_array($res, 0);
        else
            return ['uname' => $this->uname, 'id' => null];

    }
    public function save(\BayCMS\Fieldset\Form $form): bool
    {
        if (!$form->getId())
            return false;
        $f = $this->readFile($form->getId());
        if (!$this->checkSelfEdit($f))
            return true;

        $file = new \BayCMS\Base\BayCMSFile($this->context);
        if ($f['id'])
            $file->load($f['id']);
        $source = $this->fields->getField($this->name . "_location")->getValue();
        if (strstr($source, '/tmp/')) {
            $file->set([
                'name' => $this->fields->getField($this->name . "_name")->getValue(),
                'path' => $this->path,
                'add_id_obj' => 1,
                'de' => $f['uname'],
                'source' => $source,
                'id_parent' => $form->getId()
            ]);
            $file->save();
        } elseif ($f['id'] && ($_POST[$this->name . '_del'] ?? false))
            $file->erase(true);

        $this->setFieldsFromDb($form->getId());
        $this->setValue('');
        return true;
    }


    private function setFieldsFromDb(int $id)
    {
        $f = $this->readFile($id);
        if (!$this->checkSelfEdit($f)) {
            $this->disable();
            $this->fields->getField($this->name . '_del')->disable();
        }
        if ($f['id']) {
            $file = new \BayCMS\Base\BayCMSFile($this->context);
            $file->load($f['id']);
            $f = $file->get();
            $path = $this->context->BayCMSRoot . '/' . $f['full_path'] . '/' . $f['name'];
            $this->fields->getField($this->name . '_location')->setValue($path);
            $this->fields->getField($this->name . '_name')->setValue($f['name']);
        }
    }

    public function setValue($value): bool
    {
        if ($this->non_empty && !$this->fields->getField($this->name . '_location')->getValue())
            $this->error = true;

        $this->value = '';
        $location = $this->fields->getField($this->name . '_location')->getValue();
        $name = $this->fields->getField($this->name . '_name')->getValue();
        if ($location) {
            $res = pg_query_params(
                $this->context->getRwDbConn(),
                'select get_filetype_image($1)',
                [$name]
            );
            [$img] = pg_fetch_row($res, 0);
            $size = round(filesize($location) / 1024);
            $this->value .= "<a href=\"/" . $this->context->org_folder . "/" . $this->context->lang . "/top/gru/get.php?f=";
            $this->value .= urlencode($location);
            $this->value .= "&n=";
            $this->value .= urlencode($name);
            $this->value .= "\" target=\"_blank\">$img $name ($size kb)</a> ";
        }
        return (bool) $this->error;

    }

    public function setValueFromArray(array &$v, ?int $id = null): bool
    {
        parent::setValueFromArray($v, $id);
        if ($id && !isset($_POST[$this->name . '_name'])) {
            $this->setFieldsFromDb($id);
        }
        return $this->setValue('');

    }

    public function getDisplayValue(): string
    {
        return ($this->value?$this->value:'');
    }


}