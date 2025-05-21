<?php

namespace BayCMS\Base;

use Exception;

abstract class BayCMSRow {
    protected BayCMSContext $context;

    protected ?int $id=null;

    public function __construct(BayCMSContext $context)
    {
        $this->context = $context;
    }

    public function __get(string $name){
        return $this->$name;
    }

    public function setId(?int $id){
        $this->id=$id;
    }

    /**
     * get properties of row
     * @return array with key value pairs
     */
    abstract public function get():array;

    /**
     * load the properties of the row from the database
     * @param int|null $id
     * @throws \BayCMS\Exception\missingId
     * @throws \BayCMS\Exception\notFound
     * @return void
     */
    abstract public function load(int|null $id=null);

    /**
     * Set properties of row with array with key value pairs
     * @param array $values
     * @return void
     */
    abstract public function set(array $values);

    /**
     * Save the row in the database
     * @throws \BayCMS\Exception\accessDenied
     * @return int
     */
    abstract public function save():int;

    /**
     * Delete the row from the database
     * @throws \BayCMS\Exception\accessDenied
     * @return void
     */
    abstract public function erase();

}