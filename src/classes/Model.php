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

    public function delete()
    {
        //muss getestet werden
        if (isset($this->attributes[$this->primaryKey])) {
            $this->database->delete($this->attributes[$this->primaryKey]);
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

    public function first($id)
    {
        $data = $this->database->where([$this->primaryKey, $id])->first();
        if ($data) {
            return $data;
        } else {
            throw new \Exception("Record not found");
        }
    }

    public function get($id)
    {
        $data = $this->database->where([$this->primaryKey, $id])->first();
        if ($data) {
            return $data;
        } else {
            throw new \Exception("Record not found");
        }
    }
    public function getAll()
    {
        return $this->database->all();
    }
}
