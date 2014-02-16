<?php namespace Ourlearn\LaravelStarter;

class Model
{
    private $modelName;
    private $namespace;

    public function __construct($modelName, $namespace = "")
    {
        $this->modelName = strtolower($modelName);
        $this->namespace = $namespace;
    }

    public function upper()
    {
        return ucfirst($this->modelName);
    }

    public function lower()
    {
        return $this->modelName;
    }

    public function plural()
    {
        return str_plural($this->modelName);
    }

    public function upperPlural()
    {
        return str_plural($this->upper());
    }

    public function nameWithNamespace()
    {
        $namespace = $this->namespace ? $this->namespace . "\\" : "";
        return $namespace . $this->upper();
    }
}
