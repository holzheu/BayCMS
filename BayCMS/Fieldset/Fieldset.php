<?php
namespace BayCMS\Fieldset;

class Fieldset extends \BayCMS\Base\BayCMSBase
{

    protected array $fields=[];

    protected string $div_id='';

    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Get field by index, name or description
     * @param mixed $id index, name or description
     * @return \BayCMS\Field\Field|bool
     */
    public function getField($id): \BayCMS\Field\Field|bool
    {
        if (is_int($id))
            return $this->fields[$id] ?? false;
        foreach ($this->fields as $f) {
            if ($f->getName() == $id)
                return $f;
            if ($f->getDescription(false) == $id)
                return $f;
        }
        return false;
    }

    /**
     * Get field index by name or description
     * @param mixed $name
     * @return int
     */
    public function getFieldIndex($name): int
    {
        foreach ($this->fields as $k => $f) {
            if ($f->getName() == $name)
                return $k;
            if ($f->getDescription(false) == $name)
                return $k;
        }
        return -1;
    }

    /**
     * Add a field to fieldset
     * @param \BayCMS\Field\Field $field
     * @param mixed $index
     * @return \BayCMS\Field\Field
     */
    public function addField(\BayCMS\Field\Field $field, ?int $index = null): \BayCMS\Field\Field
    {
        if($this->div_id && ! $field->getDivID()){
            $field->setDivID($this->div_id);
        } elseif($field->getDivID()!=$this->div_id){
            $this->div_id=$field->getDivID();
        }
        if ($index === null)
            $this->fields[] = $field;
        else {
            $this->fields[$index] = $field;
            ksort($this->fields);
        }
        return $field;
    }

    public function delField($id)
    {
        unset($this->fields[$id]);
    }

}