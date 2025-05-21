<?php

namespace BayCMS\Field;

class UploadImage extends Upload
{
    protected string $uname;
    protected bool $internal;
    protected int $height;
    protected int $theight;
    protected ?int $db_id=null;
    protected float $crop;
    protected float $tcrop;
    
    private \BayCMS\Base\BayCMSImage $image;

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
        string $accept = "image/png, image/jpeg, image/gif",
        string $preg_match = '/\\.(jpe?g|gif|png)$/i',
        string $uname = null,
        bool $internal=false,
        int $height=500,
        int $theight=100,
        int $max_size=0,
        float $crop=0,
        float $tcrop=0

    ) {
        parent::__construct($context, $name, $description, $id, $sql, $help, $label_css, $input_options, $post_input, $placeholder, $no_add_to_query, $not_in_table, $non_empty, $footnote, $default_value, $div_id, $accept, $max_size, $preg_match);
        if(is_null($uname)) $uname=$name;
        $this->uname=$uname;
        $this->internal=$internal;
        $this->height=$height;
        $this->theight=$theight;
        $this->crop=$crop;
        $this->tcrop=$tcrop;
    }

    public function getImageId(){
        return $this->db_id;
    }

    protected function readFile(int $id)
    {
        $res = pg_query_params(
            $this->context->getRwDbConn(),
            'select id from bild  
                where id_obj=$1 and de=$2',
            [$id, $this->uname]
        );

        if (pg_num_rows($res))
            [$this->db_id]=pg_fetch_row($res,0);
    }
    public function save(\BayCMS\Fieldset\Form $form): bool
    {
        if (!$form->getId())
            return false;
        $this->readFile($form->getId());

        $this->image = new \BayCMS\Base\BayCMSImage($this->context);
        if ($this->db_id)
            $this->image->load($this->db_id);
        $source = $this->fields->getField($this->name . "_location")->getValue();
        if (strstr($source, '/tmp/')) {
            $this->image->set([
                'name' => $this->fields->getField($this->name . "_name")->getValue(),
                'add_id_obj' => 1,
                'de' => $this->uname,
                'source' => $source,
                'id_obj' => $form->getId(),
                'internal' => $this->internal,
                'height' => $this->height,
                'theight' => $this->theight,
                'crop'=>$this->crop,
                'tcrop'=>$this->tcrop
            ]);
            $this->db_id = $this->image->save();
        } elseif ($this->db_id && ($_POST[$this->name . '_del'] ?? false)){
            $this->image->erase();
            $this->db_id=null;
        }

        $this->setFieldsFromDb($form->getId());
        return true;
    }

    private function setFieldsFromDb(int $id)
    {
        $this->readFile($id);
        if ($this->db_id) {
            $this->value = $this->getHTML(true, true); //sets $this->image!
            $f = $this->image->get();
            $path = $this->context->BayCMSRoot . '/image/' . ($f['internal'] ? 'intern/' : '') . $f['name'];
            $this->fields->getField($this->name . '_location')->setValue($path);
            $this->fields->getField($this->name . '_name')->setValue($f['name']);
        }
    }

    public function getHTML($thumbnail=false, $prop = false): string
    {
        $out = '';
        if(! $this->db_id) return $out;

        $this->image = new \BayCMS\Base\BayCMSImage($this->context);
        $this->image->load($this->db_id);
        $f = $this->image->get();
        $tn = ($f['theight'] && $thumbnail > 0 ? 't' : '');
        $name = $tn . $f['name'];
        $location = $this->context->BayCMSRoot . '/image/' .
            ($f['internal'] ? 'intern/' : '') . $name;
        $out .= '<img src="/' . $this->context->org_folder . "/" .
            $this->context->lang . "/top/gru/get.php?f=";
        $out .= urlencode($location);
        $out .= "&n=";
        $out .= urlencode($name);
        $out .= '" height=' . $f[$tn . 'y'] . ' width=' . $f[$tn . 'x'] . '>';
        if ($prop) {
            $out .= "Size $f[x]x$f[y], Original $f[ox]x$f[ox]";
            if ($tn)
                $out .= ", Thumbnail $f[tx]x$f[ty]";
        }
        return $out;

    }


    public function setValue($value): bool
    {
        if ($this->non_empty && !$this->fields->getField($this->name . '_location')->getValue())
            $this->error = true;

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

    public function getDisplayValue():string
    {
        return ($this->value?$this->value:'');
    }


}