<?php namespace Ourlearn\LaravelStarter;

class Relation
{
    private $relationType;
    public $model;

    public function __construct($relationType, Model $model)
    {
        $this->relationType = $relationType;
        $this->model = $model;
    }

    public function reverseRelations()
    {
        $reverseRelations = array();
        switch($this->relationType) {
            case "belongsTo":
                $reverseRelations = array('hasOne', 'hasMany');
                break;
            case "hasOne":
                $reverseRelations = array('belongsTo');
                break;
            case "belongsToMany":
                $reverseRelations = array('belongsToMany');
                break;
            case "hasMany":
                $reverseRelations = array('belongsTo');
                break;
        }

        return $reverseRelations;
    }

    public function getTableName()
    {
        return $this->model->upper();
    }

    public function getType()
    {
        return $this->relationType;
    }

    public function getName(Model $model = null, $type = "")
    {
        $relationName = "";

        if(!$type)
            $type = $this->relationType;

        if(!$model)
            $model = $this->model;

        switch($type) {
            case "belongsTo":
            case "hasOne":
                $relationName = $model->lower();
                break;
            case "belongsToMany":
            case "hasMany":
                $relationName = $model->plural();
                break;
        }

        return $relationName;
    }

    public function getReverseName(Model $model, $type)
    {
        return $this->getName($model, $type);
    }
}
