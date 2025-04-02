<?php

namespace Danupe\Plugin\Database\Classes;

abstract class Model
{
    protected $table;
    protected $primaryKey = 'id';
    protected $attributes = [];

    protected $database;

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
        $this->database = new Database($this->table);
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    public function getAttribute($key)
    {
        return $this->attributes[$key] ?? null;
    }

    public function setAttribute($key, $value)
    {
        $this->attributes[$key] = $value;
    }

    public function save(array $data)
    {
        if (isset($data[$this->primaryKey])) {
            $this->database->update($data[$this->primaryKey], $data);
        } else {
            $this->database->insert($data);
        }
    }

    public function update(array $data)
    {
        if (isset($data[$this->primaryKey])) {
            $this->database->update($data, [$this->primaryKey => $data[$this->primaryKey]]);
        } else {
            throw new \Exception("Record not found");
        }
    }

    public function delete(int $id)
    {
        if (isset($id)) {
            $this->database->delete([$this->primaryKey => $id]);
        } else {
            throw new \Exception("Record not found");
        }
    }

    public function find($id)
    {
        //muss getestet werden
        $data = $this->database->where([$this->primaryKey, $id])->first();
        if ($data) {
            $this->attributes = $data;
        } else {
            throw new \Exception("Record not found");
        }
    }

    public function all(array $fields = [])
    {
        $data = $this->database->all($fields);
        return $data;
    }

    public function first($id=null)
    {
        if($id != null){
            $data = $this->database->where([$this->primaryKey, $id])->first();
        }else{
            $data = $this->database->first();
        }
        if ($data) {
            return $data;
        } else {
            throw new \Exception("Record not found");
        }
    }

    public function get()
    {
        $data = $this->database->get();
        if ($data) {
            return $data;
        } else {
            return [];
        }
    }
    public function getAll()
    {
        return $this->database->all();
    }

    public function orderBy(array $orderBy = [])
    {
        $this->database->orderBy($orderBy);
        return $this;
    }

    public function where(array $conditions = [])
    {
        $this->database->where($conditions);
        return $this;
    }

    public function whereRaw(string $sql, array $params = [])
    {
        $this->database->whereRaw($sql, $params);
        return $this;
    }

    public function random(array $fields = [])
    {
        $this->database->random($fields);
        return $this;
    }
}
