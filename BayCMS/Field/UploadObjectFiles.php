<?php

namespace BayCMS\Field;

class UploadObjectFiles extends UploadFile
{

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
        string $path='inc/object_files',
        string $uname=null,
        string $preg_match='',
        int $max_size=0
    ) {
        parent::__construct($context, $name, $description, $id, $sql, $help, $label_css, $input_options, $post_input, $placeholder, $no_add_to_query, $not_in_table, $non_empty, $footnote, $default_value,$div_id, $accept,$max_size);
        $this->path=$path;
        if(is_null($uname)) $uname=$name;
        $this->uname=$uname;
        $this->preg_match=$preg_match;

    }

    public function getInput(\BayCMS\Fieldset\Form $form): string
    {
        $out = parent::getInput($form);
        $out.="<br/>\n";
        if ($form->getId()) {
            $out .= $this->getFileList($form->getId(), true);
        }
        return $out;

    }


    public function getFileList(int $id, $del = false): string
    {
        $res = pg_query_params(
            $this->context->getRwDbConn(),
            'select non_empty(f.de,f.en) as link,get_filetype_image(f.name),
        f.name,f.id from file f, objekt o where o.id=f.id_obj and o.id_obj=$1 order by 1',
            [$id]
        );
        $out = '';
        if (pg_num_rows($res))
            if($del) $out .= $this->t('File List', 'Datei Liste')."<br/>\n" ;

        for ($i = 0; $i < pg_num_rows($res); $i++) {
            $r = pg_fetch_array($res, $i);
            $out .= "<a href=\"/" . $this->context->org_folder . "/" . $this->context->lang . "/top/gru/get.php?f=";
            $out .= urlencode($this->context->BayCMSRoot . '/' . $r['name']);
            $out .= "&n=";
            $out .= urlencode($r['link']);
            $out .= "\" target=\"_blank\">$r[get_filetype_image] $r[link]</a> ";
            if ($del) {
                $out .= '<input type="checkbox" name="' . $this->name . "_del_" . $r['id'] . '"> ' . $this->t('delete', 'löschen') . '?';
            }
            $out.="<br/>\n";
        }
        return $out;
    }

    public function setValueFromArray(array &$v, ?int $id = null): bool
    {
        parent::setValueFromArray($v, $id);
        if ( $id && ! isset($_POST[$this->name . '_name'])) {
            $this->value=$this->getFileList($id);
        }
        return false;
    }

    public function save(\BayCMS\Fieldset\Form $form): bool
    {
        if (!$form->getId())
            return false;
        $file = new \BayCMS\Base\BayCMSFile($this->context);
        $name = $this->fields->getField($this->name . "_name")->getValue();
        if($name){
            $file->set([
                'name' => $name,
                'path' => $this->path,
                'add_id_obj' => 1,
                'de' => $name,
                'source' => $this->fields->getField($this->name . "_location")->getValue(),
                'id_parent' => $form->getId()
            ]);
            $file->save();
        }
        
        $res = pg_query_params(
            $this->context->getRwDbConn(),
            'select f.id from file f, objekt o where o.id=f.id_obj and o.id_obj=$1',
            [$form->getId()]
        );
        for ($i = 0; $i < pg_num_rows($res); $i++) {
            [$id] = pg_fetch_row($res, $i);
            if ($_POST[$this->name . '_del_' . $id] ?? false) {
                $file->load($id);
                $file->erase(true);
            }
        }
        $this->value = $this->getFileList($form->getId());
        return true;
    }


    public function getDisplayValue(): string
    {
        return $this->value;
    }

}