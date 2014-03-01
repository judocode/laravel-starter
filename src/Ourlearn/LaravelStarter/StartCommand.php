<?php namespace Ourlearn\LaravelStarter;

use Illuminate\Console\Command;
use Psr\Log\InvalidArgumentException;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Faker\Factory;

class StartCommand extends Command
{
    protected $name = 'start';
    protected $description = "Makes table, controller, model, views, seeds, and repository";

    private $laravelClasses = array();
    private $propertiesArr = array();
    private $propertiesStr = "";
    private $model;
    private $relationship = array();
    private $namespace;
    private $isResource;
    private $fieldNames;
    private $fileContents;
    private $controllerType;
    private $pathTo;
    private $names;
    private $useRepository;
    private $views;
    private $validTypes = array('bigInteger','binary', 'boolean', 'date', 'datetime', 'decimal', 'double', 'enum', 'float', 'integer', 'longtext', 'mediumtext', 'smallinteger', 'tinyinteger', 'string', 'text', 'time', 'timestamp', 'morphs', 'bigincrements');

    private $templatePathWithControllerType;
    private $downloads;

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        $this->getConfigSettings();

        $this->laravelClasses = $this->getLaravelClassNames();

        $this->copyTemplateFiles();

        $this->generateLayoutFiles();

        $modelAndFields = $this->askForModelAndFields();

        $moreTables = $modelAndFields == "q" ? false : true;

        while( $moreTables ) {
            $modelNameCollision = false;

            $this->resetModels();

            do {
                if($modelNameCollision) {
                    $modelAndFields = $this->ask($this->model->upper() ." is already in the global namespace. Please namespace your class or provide a different name: ");
                    $this->namespace = "";
                }

                $values = preg_split('/\s+/', $modelAndFields);

                $modelWithNamespace = array_shift($values);

                $this->namespace = $this->getNamespace($modelWithNamespace);

                $this->model = $this->getModel($modelWithNamespace);

                $modelNameCollision = in_array($this->model->lower(), $this->laravelClasses);

            } while($modelNameCollision);

            $additionalFields = !empty($values);

            if( $additionalFields ) {
                $this->getModelsWithRelationships($values);

                $this->fieldNames = $values;

                $this->fillProperties();
            }

            $this->createModel();

            $this->isResource = $this->confirm('Do you want resource (y) or restful (n) controllers? ');

            if($this->isResource)
                $this->controllerType = "resource";
            else
                $this->controllerType = "restful";

            $this->templatePathWithControllerType = $this->pathTo['templates'] . $this->controllerType ."/";

            $this->createMigrations();

            $this->createSeeds();

            $this->runMigrations();

            if($this->useRepository) {
                $this->createRepository();
                $this->createRepositoryInterface();
            }

            $this->putRepositoryFolderInStartFiles();

            $this->createController();

            $this->createViews();

            $this->updateRoutesFile();

            $this->createTestsFile();

            $modelAndFields = $this->ask('Add model with fields or "q" to quit: ');
            $moreTables = $modelAndFields == "q" ? false : true;
        }

        $this->info('Please wait a few moments...');

        $this->call('clear-compiled');

        $this->call('optimize');

        $this->info('Done!');
    }

    private function nameOf($type)
    {
        return $this->replaceModels($this->names[$type]);
    }

    private function getConfigSettings()
    {
        $package = "laravel-starter";

        $config = $this->getLaravel()['config'];

        $this->pathTo = $config->get("$package::paths");

        foreach($this->pathTo as $pathName => $path) {
            if($path[strlen($path)-1] != "/") {
                $path .= "/";
                $this->pathTo[$pathName] = $path;
            }
        }

        $this->names = $config->get("$package::names");

        $this->downloads = $config->get("$package::downloads");

        $this->views = $config->get("$package::views");

        $this->useRepository = $config->get("$package::repository");
    }

    private function askForModelAndFields()
    {
        $modelAndFields = $this->ask('Add model with its relations and fields or type "q" to quit (type info for examples) ');

        if($modelAndFields == "info") {
            $this->showInformation();

            $modelAndFields = $this->ask('Now your turn: ');
        }

        return $modelAndFields;
    }

    private function copyTemplateFiles()
    {
        if(!\File::isDirectory($this->pathTo['templates'])) {
            $this->rcopy("vendor/ourlearn/laravel-starter/src/Ourlearn/LaravelStarter/templates/", $this->pathTo['templates']);
        }
    }

    private function showInformation()
    {
        $this->info('MyNamespace\Book title:string year:integer');
        $this->info('With relation: Book belongsTo Author title:string published:integer');
        $this->info('Multiple relations: University hasMany Course, Department name:string city:string state:string homepage:string )');
        $this->info('Or group like properties: University hasMany Department string( name city state homepage )');
    }

    private function getLaravelClassNames()
    {
        $classNames = array();

        $aliases = \Config::get('app.aliases');
        foreach ($aliases as $alias => $facade) {
            array_push($classNames, strtolower($alias));
        }

        return $classNames;
    }

    private function resetModels()
    {
        $this->relationship = array();
        $this->namespace = "";
        $this->propertiesArr = array();
        $this->propertiesStr = "";
        $this->model = null;
    }

    private function getModelsWithRelationships(&$values)
    {
        if($this->nextArgumentIsRelation($values[0])) {
            $relationship = $values[0];
            $relatedTable = trim($values[1], ',');

            $i = 2;

            $this->relationship = array();
            array_push($this->relationship, new Relation($relationship, new Model($relatedTable)));

            while($i < count($values) && $this->nextArgumentIsRelation($values[$i])) {
                if(strpos($values[$i], ",") === false) {
                    $next = $i + 1;
                    if($this->isLastRelation($values, $next)) {
                        $relationship = $values[$i];
                        $relatedTable = trim($values[$next], ',');
                        $i++;
                        unset($values[$next]);
                    } else {
                        $relatedTable = $values[$i];
                    }
                } else {
                    $relatedTable = trim($values[$i], ',');
                }
                array_push($this->relationship, new Relation($relationship, new Model($relatedTable)));
                unset($values[$i]);
                $i++;
            }

            unset($values[0]);
            unset($values[1]);
        }
    }

    private function fillProperties()
    {
        $bundled = false;
        $fieldName = "";
        $type = "";

        foreach($this->fieldNames as $field)
        {
            $skip = false;
            $pos = strrpos($field, ":");
            if ($pos !== false && !$bundled)
            {
                $type = substr($field, $pos+1);
                $fieldName = substr($field, 0, $pos);
            } else if(strpos($field, '(') !== false) {
                $type = substr($field, 0, strpos($field, '('));
                $bundled = true;
                $skip = true;
            } else if($bundled) {
                if($pos !== false && strpos($field, ")") === false) {
                    $fieldName = substr($field, $pos+1);
                    $num = substr($field, 0, $pos);
                } else if(strpos($field, ")") !== false){
                    $skip = true;
                    $bundled = false;
                } else {
                    $fieldName = $field;
                }
            }

            if(!$skip && !empty($fieldName)) {
                $this->propertiesArr[$fieldName] = $type;
                $this->propertiesStr .= "'".$fieldName ."',";
            }
        }

        $this->propertiesStr = trim($this->propertiesStr, ',');
    }

    private function getNamespace($modelWithNamespace)
    {
        return substr($modelWithNamespace, 0, strrpos($modelWithNamespace, "\\"));
    }

    private function getModel($modelWithNamespace)
    {
        if(strpos($modelWithNamespace, "\\"))
            $model = substr(strrchr($modelWithNamespace, "\\"), 1);
        else
            $model = $modelWithNamespace;

        return new Model($model, $this->namespace);
    }

    private function isLastRelation($values, $next)
    {
        return ($next < count($values) && $this->nextArgumentIsRelation($values[$next]));
    }

    private function nextArgumentIsRelation($value)
    {
        return strpos($value, ":") === false && strpos($value, "(") === false;
    }

    protected function getArguments()
    {
        return array(
            array('name', InputArgument::OPTIONAL, 'Name of the model/controller.'),
        );
    }

    private function createMigrations()
    {
        $tableName = $this->model->plural();

        $createTable = "create_" . $tableName . "_table";

        $migrationFile = \App::make('Illuminate\Database\Migrations\MigrationCreator')->create($createTable, $this->pathTo['migrations'], $tableName, true);

        $functionContents = $this->migrationUp();
        $fileContents = $this->createFunction("up", $functionContents);

        $functionContents = "\t\tSchema::dropIfExists('".$this->model->plural()."');\n";
        $fileContents .= $this->createFunction("down", $functionContents);

        $this->createMigrationClass($migrationFile, $fileContents, $this->model->upperPlural());
    }

    protected function migrationUp()
    {
        $content = "\t\tSchema::create('".$this->model->plural()."', function(Blueprint \$table) {\n";
        $content .= "\t\t\t" . $this->setColumn('increments', 'id') . ";\n";
        $content .= $this->addColumns();
        $content .= "\t\t\t" . $this->setColumn('timestamps', null) . ";\n";
        $content .= $this->addForeignKeys();
        $content .= "\t\t});\n";

        foreach($this->relationship as $relation) {
            if($relation->getType() == "belongsToMany") {

                $tableOne = $this->model->lower();
                $tableTwo = $relation->model->lower();

                $tableName = $this->getPivotTableName($tableOne, $tableTwo);

                if(!$this->isTableCreated($tableName)) {
                    $content .= "\t\tSchema::create('".$tableName."', function(Blueprint \$table) {\n";
                    $content .= "\t\t\t\$table->integer('".$tableOne."_id')->unsigned();\n";
                    $content .= "\t\t\t\$table->integer('".$tableTwo."_id')->unsigned();\n";
                    $content .= "\t\t});\n";
                }
            } else if($relation->getType() == "hasOne" || $relation->getType() == "hasMany") {
                if($this->tableHasColumn($relation->model->plural() ,$this->model->lower()."_id")) {
                    $content .= "\t\tSchema::table('".$relation->model->plural()."', function(Blueprint \$table) {\n";
                    $content .= "\t\t\t\$table->foreign('". $this->model->lower()."_id')->references('id')->on('".$this->model->plural()."');\n";
                    $content .= "\t\t});\n";
                } else if($this->isTableCreated($relation->model->plural())) {
                    $content .= "\t\tSchema::table('".$relation->model->plural()."', function(Blueprint \$table) {\n";
                    $content .= "\t\t\t\$table->integer('". $this->model->lower()."_id')->unsigned();\n";
                    $content .= "\t\t\t\$table->foreign('". $this->model->lower()."_id')->references('id')->on('".$this->model->plural()."');\n";
                    $content .= "\t\t});\n";
                }
            }
        }
        return $content;
    }

    private function tableHasColumn($tableName, $columnName)
    {
        if(\Schema::hasColumn($tableName, $columnName)) {
            return true;
        }

        $found = false;

        if ($handle = opendir($this->pathTo['migrations'])) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != ".." && $entry != ".gitkeep") {
                    $fileName = $this->pathTo['migrations'].$entry;

                    $contents = \File::get($fileName);
                    $matched = preg_match("/Schema::(table|create).*'$tableName',.*\(.*\).*{.*'$columnName'.*}\);/s", $contents);
                    if($matched !== false && $matched != 0) {
                        $found = true;
                        break;
                    }
                }
            }
            closedir($handle);
        }

        return $found;
    }

    private function getPivotTableName($tableOne, $tableTwo)
    {
        if($tableOne[0] > $tableTwo[0])
            $tableName = $tableTwo ."_".$tableOne;
        else
            $tableName = $tableOne ."_".$tableTwo;

        return $tableName;
    }

    private function isTableCreated($tableName)
    {
        $found = false;
        if(\Schema::hasTable($tableName)) {
            return true;
        }

        if ($handle = opendir($this->pathTo['migrations'])) {
            while (false !== ($entry = readdir($handle))) {
                if ($entry != "." && $entry != "..") {
                    $fileName = $this->pathTo['migrations'].$entry;

                    $contents = \File::get($fileName);
                    if(strpos($contents, "Schema::create('$tableName'") !== false) {
                        $found = true;
                        break;
                    }
                }
            }
            closedir($handle);
        }

        return $found;
    }

    private $fillForeignKeys = array();

    private function addForeignKeys()
    {
        $fields = "";
        foreach($this->relationship as $relation) {
            if($relation->getType() == "belongsTo") {
                $foreignKey = $relation->model->lower() . "_id";
                $fields .= "\t\t\t" .$this->setColumn('integer', $foreignKey);
                $fields .= $this->addColumnOption('unsigned') . ";\n";
                if($this->isTableCreated($relation->model->plural())) {
                    $fields .= "\t\t\t\$table->foreign('". $foreignKey."')->references('id')->on('".$relation->model->plural()."');\n";
                    array_push($this->fillForeignKeys, $foreignKey);
                }
            }
        }
        return $fields;
    }

    protected function increment()
    {
        return "\$table->increments('id')";
    }

    protected function setColumn($type, $field = '')
    {
        return empty($field)
            ? "\$table->$type()"
            : "\$table->$type('$field')";
    }

    protected function addColumnOption($option)
    {
        return "->{$option}()";
    }

    protected function addColumns()
    {
        $content = '';

        if(!empty($this->propertiesArr))
        {
            // Build up the schema
            foreach( $this->propertiesArr as $field => $type ) {

                $rule = "\t\t\t";

                // Primary key check
                if ( $field === 'id' and $type === 'integer' )
                {
                    $rule .= $this->increment();
                } else {
                    $rule .= $this->setColumn($type, $field);

                    if ( !empty($setting) ) {
                        $rule .= $this->addColumnOption($setting);
                    }
                }

                $content .= $rule . ";\n";
            }
        }

        return $content;
    }

    private function runMigrations()
    {
        $editMigrations = $this->confirm('Would you like to edit your migrations file before running it [y/n]? ', true);
        if ($editMigrations) {
            $this->error('Remember to run "php artisan migrate" after editing your migration file');
        } else {
            while (true) {
                try {
                    $this->call('migrate');
                    $this->call('db:seed');
                    break;
                } catch (\Exception $e) {
                    $this->info('Error: ' . $e->getMessage());
                    $this->error('This table already exists and/or you have duplicate migration files.');
                    $this->confirm('Fix the error and enter "yes" ', true);
                }
            }
        }
    }

    private function createModel()
    {
        $fileName = $this->pathTo['models'] . $this->nameOf("modelName") . ".php";
        $fileContents = "";
        foreach ($this->relationship as $relation) {
            $relatedModel = $relation->model;

            $functionContent = "\t\treturn \$this->" . $relation->getType() . "('".$relatedModel->nameWithNamespace()."');\n";
            $fileContents .= $this->createFunction($relation->getName(), $functionContent);

            $relatedModelFile = $this->pathTo['models'].$relatedModel->upper().'.php';
            $continue = true;

            if(!\File::exists($relatedModelFile)) {
                $continue = $this->confirm("Model ". $relatedModel->upper() . " doesn't exist yet. Would you like to create it now [y/n]? ", true);
                if($continue) {
                    $this->createClass($relatedModelFile, "", array('name' => "\\Eloquent"));
                }
            }

            if($continue) {
                $content = \File::get($relatedModelFile);
                if (preg_match("/function ".$this->model->lower()."/", $content) !== 1 && preg_match("/function ".$this->model->plural()."/", $content) !== 1) {
                    $index = 0;
                    $reverseRelations = $relation->reverseRelations();

                    if(count($reverseRelations) > 1) {
                        $index = $this->ask("How does " . $relatedModel->upper() . " relate back to ". $this->model->upper() ."? (0=".$reverseRelations[0]. " 1=".$reverseRelations[1] .") ");
                    }

                    $reverseRelationType = $reverseRelations[$index];
                    $reverseRelationName = $relation->getReverseName($this->model, $reverseRelationType);

                    $content = substr($content, 0, strrpos($content, "}"));
                    $functionContent = "\t\treturn \$this->" . $reverseRelationType . "('".$this->model->nameWithNamespace()."');\n";
                    $content .= $this->createFunction($reverseRelationName, $functionContent) . "}\n";

                    \File::put($relatedModelFile, $content);
                }
            }
        }

        $this->createClass($fileName, $fileContents, array("name" => "\\Eloquent"));

        $this->info('Model "'.$this->model->upper().'" created!');
    }

    private function createSeeds()
    {
        $faker = Factory::create();

        $databaseSeeder = $this->pathTo['seeds'] . 'DatabaseSeeder.php';
        $databaseSeederContents = \File::get($databaseSeeder);
        if(preg_match("/faker/", $databaseSeederContents) !== 1) {
            $contentBefore = substr($databaseSeederContents, 0, strpos($databaseSeederContents, "{"));
            $contentAfter = substr($databaseSeederContents, strpos($databaseSeederContents, "{")+1);

            $databaseSeederContents = $contentBefore;
            $databaseSeederContents .= "{\n\tprotected \$faker;\n\n";
            $functionContents = "\t\tif(empty(\$this->faker)) {\n";
            $functionContents .= "\t\t\t\$this->faker = Faker\\Factory::create();\n\t\t}\n\n";
            $functionContents .= "\t\treturn \$this->faker;\n";

            $databaseSeederContents .= $this->createFunction("getFaker", $functionContents);

            $databaseSeederContents .= $contentAfter;

            \File::put($databaseSeeder, $databaseSeederContents);
        }

        $functionContent = "\t\t\$faker = \$this->getFaker();\n\n";
        $functionContent .= "\t\tfor(\$i = 1; \$i <= 10; \$i++) {\n";

        $functionContent .= "\t\t\t\$".$this->model->lower()." = array(\n";

        foreach($this->propertiesArr as $property => $type) {

            if($property == "password") {
                $functionContent .= "\t\t\t\t'$property' => \\Hash::make('password'),\n";
            } else {
                $fakerProperty = "";
                try {

                    $fakerProperty2 = $faker->getFormatter($property);
                    $fakerProperty = $property;
                } catch (\InvalidArgumentException $e) { }

                if(empty($fakerProperty)) {
                    try {
                        $fakerProperty2 = $faker->getFormatter($type);
                        $fakerProperty = $type;
                    } catch (\InvalidArgumentException $e) { }
                }

                if(empty($fakerProperty)) {
                    $fakerType = "";
                    switch($type) {
                        case "integer":
                            $fakerType = "randomDigitNotNull";
                            break;
                        case "string":
                            $fakerType = "word";
                            break;
                    }

                    $fakerType = $fakerType ? "\$faker->".$fakerType : "";
                } else {
                    $fakerType = "\$faker->".$fakerProperty;
                }

                $functionContent .= "\t\t\t\t'$property' => $fakerType,\n";

            }
        }

        foreach($this->fillForeignKeys as $key) {
            $functionContent .= "\t\t\t\t'$key' => \$i,\n";
        }

        $functionContent .= "\t\t\t);\n";

        $namespace = $this->namespace ? $this->namespace . "\\" : "";

        $functionContent .= "\t\t\t". $namespace . $this->model->upper()."::create(\$".$this->model->lower().");\n";
        $functionContent .= "\t\t}\n";

        $fileContents = $this->createFunction("run", $functionContent);

        $fileName = $this->pathTo['seeds'] . $this->model->upperPlural() . "TableSeeder.php";

        $this->createClass($fileName, $fileContents, array('name' => 'DatabaseSeeder'), array(), array(), "class", false);

        $tableSeederClassName = $this->model->upperPlural() . 'TableSeeder';

        $content = \File::get($databaseSeeder);
        if(preg_match("/$tableSeederClassName/", $content) !== 1) {
            $content = preg_replace("/(run\(\).+?)}/us", "$1\t\$this->call('{$tableSeederClassName}');\n\t}", $content);
            \File::put($databaseSeeder, $content);
        }

        $this->info('Database seed file created!');
    }
    /**
     * @return array
     */
    private function createRepositoryInterface()
    {
        $this->createDirectory($this->pathTo['repositoryInterfaces']);

        $fileName = $this->pathTo['repositoryInterfaces'] . $this->nameOf("repositoryInterface") . ".php";

        $fileContents = \File::get($this->pathTo['templates']."repository-interface.txt");
        $fileContents = $this->replaceNames($fileContents);
        $fileContents = $this->replaceModels($fileContents);
        $fileContents = $this->replaceProperties($fileContents);
        $this->createFile($fileName, $fileContents);
    }

    /**
     * @return array
     */
    private function createRepository()
    {
        $this->createDirectory($this->pathTo['repositories']);

        $fileName = $this->pathTo['repositories'] . $this->nameOf("repository") . '.php';

        $fileContents = \File::get($this->pathTo['templates']."eloquent-repository.txt");
        $fileContents = $this->replaceNames($fileContents);
        $fileContents = $this->replaceModels($fileContents);
        $fileContents = $this->replaceProperties($fileContents);
        $this->createFile($fileName, $fileContents);

        $this->info($this->model->upper().'Repository created!');
    }

    /**
     * @return mixed
     */
    private function putRepositoryFolderInStartFiles()
    {
        $repositories = substr($this->pathTo['repositories'], 0, strlen($this->pathTo['repositories']-1));


        $content = \File::get('app/start/global.php');
        if (preg_match("/repositories/", $content) !== 1)
            $content = preg_replace("/app_path\(\).'\/controllers',/", "app_path().'/controllers',\n\t$repositories,", $content);

        \File::put('app/start/global.php', $content);

        $content = \File::get('composer.json');
        if (preg_match("/repositories/", $content) !== 1)
            $content = preg_replace("/\"app\/controllers\",/", "\"app/controllers\",\n\t\t\t\"$repositories\",", $content);

        \File::put('composer.json', $content);
    }

    /**
     * @return array
     */
    private function createController()
    {
        $fileName = $this->pathTo['controllers'] . $this->nameOf("controller"). ".php";

        $fileContents = \File::get($this->templatePathWithControllerType."controller.txt");
        $fileContents = $this->replaceNames($fileContents);
        $fileContents = $this->replaceModels($fileContents);
        $fileContents = $this->replaceProperties($fileContents);

        if(!$this->useRepository) {
            $fileContents = str_replace($this->nameOf("repositoryInterface"), $this->nameOf("model"), $fileContents);
        }

        $this->createFile($fileName, $fileContents);

        $this->info($this->model->upper() . 'Controller created!');
    }

    /**
     * @return array
     */
    private function createTestsFile()
    {
        $this->createDirectory($this->pathTo['tests']. 'controller');

        $fileName = $this->pathTo['tests']."controller/" . $this->nameOf("test") .".php";

        $fileContents = \File::get($this->templatePathWithControllerType."test.txt");
        $fileContents = $this->replaceNames($fileContents);
        $fileContents = $this->replaceModels($fileContents);
        $fileContents = $this->replaceProperties($fileContents);
        $this->createFile($fileName, $fileContents);

        $this->info('Tests created!');
    }

    /**
     * @return string
     */
    private function updateRoutesFile()
    {
        $routeFile = $this->pathTo['routes']."routes.php";

        $namespace = $this->namespace ? $this->namespace . "\\" : "";

        $fileContents = "";

        if($this->useRepository)
            $fileContents = "\nApp::bind('" . $namespace . $this->nameOf("repositoryInterface")."','" . $namespace . $this->nameOf("repository") ."');\n";

        $routeType = $this->isResource ? "resource" : "controller";

        $fileContents .= "Route::" . $routeType . "('" . $this->nameOf("viewFolder") . "', '" . $namespace. $this->nameOf("controller") ."');\n";

        $content = \File::get($routeFile);
        if (preg_match("/" . $this->model->lower() . "/", $content) !== 1) {
            \File::append($routeFile, $fileContents);
        }

        $this->info('Routes file updated!');
    }

    private function createViews()
    {
        $dir = $this->pathTo['views'] . $this->nameOf('viewFolder') . "/";
        if (!\File::isDirectory($dir))
            \File::makeDirectory($dir);

        $pathToViews = $this->pathTo['templates'].$this->controllerType."/";

        foreach($this->views as $view) {
            $fileName = $dir . "$view.blade.php";

            try{
                $fileContents = \File::get($pathToViews."$view.txt");
                $fileContents = $this->replaceNames($fileContents);
                $fileContents = $this->replaceModels($fileContents);
                $fileContents = $this->replaceProperties($fileContents);
                $this->createFile($fileName, $fileContents);
            } catch(\Illuminate\Filesystem\FileNotFoundException $e) {
                $this->error("Template file ".$pathToViews . $view.".txt does not exist! You need to create it to generate that file!");
            }

        }

        $this->info('Views created!');
    }

    private function replaceModels($fileContents)
    {
        $modelReplaces = array('[model]'=>$this->model->lower(), '[Model]'=>$this->model->upper(), '[models]'=>$this->model->plural(), '[Models]'=>$this->model->upperPlural());
        foreach($modelReplaces as $model => $name) {
            $fileContents = str_replace($model, $name, $fileContents);
        }

        return $fileContents;
    }

    public function replaceNames($fileContents)
    {
        foreach($this->names as $name => $text) {
            $fileContents = str_replace("[$name]", $text, $fileContents);
        }

        return $fileContents;
    }

    private function replaceProperties($fileContents)
    {
        $lastPos = 0;
        $needle = "[repeat]";
        $endRepeat = "[/repeat]";

        while (($lastPos = strpos($fileContents, $needle, $lastPos))!== false) {
            $beginning = $lastPos;
            $lastPos = $lastPos + strlen($needle);
            $endProp = strpos($fileContents, $endRepeat, $lastPos);
            $end = $endProp + strlen($endRepeat);
            $replaceThis = substr($fileContents, $beginning, $end-$beginning);
            $propertyTemplate = substr($fileContents, $lastPos, $endProp - $lastPos);
            $properties = "";
            foreach($this->propertiesArr as $property => $type) {
                $temp = str_replace("[property]", $property, $propertyTemplate);
                $temp = str_replace("[Property]", ucfirst($property), $temp);
                $properties .= $temp;
            }
            $properties = trim($properties, ",");
            $fileContents = str_replace($replaceThis, $properties, $fileContents);
        }

        return $fileContents;
    }

    private function downloadAsset($assetName, $downloadLocation)
    {
        $type = substr(strrchr($downloadLocation, "."), 1);

        if($assetName == "jquery")
        {
            $assetName .= "1";
            if($this->downloads[$assetName] !== true) {
                $assetName = substr($assetName, 0, strlen($assetName)-1) ."2";
                if($this->downloads[$assetName] === true)
                    $downloadLocation = "http://code.jquery.com/jquery-2.1.0.min.js";
            }
        }

        if( $this->downloads[$assetName] === true )
        {
            $localLocation = "public/" . $type . "/" . $assetName . "." . $type;
            $ch = curl_init($downloadLocation);
            $fp = fopen($localLocation, "w");

            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            curl_exec($ch);
            curl_close($ch);
            fclose($fp);

            $this->fileContents = str_replace("<!--[javascript]-->", "<script src=\"{{ url('$type/$assetName.$type') }}\"></script>\n<!--[javascript]-->", $this->fileContents);
            $this->info("public/$type/$assetName.$type created!");
        }
    }

    /*
    *   Generates a default layout
    */
    private function generateLayoutFiles()
    {
        $makeLayout = $this->confirm('Create default layout file [y/n]? (specify css/js files in config) ', true);
        if( $makeLayout )
        {
            $layoutDir = $this->pathTo['views'].'layouts';
            if(!\File::isDirectory($layoutDir))
                \File::makeDirectory($layoutDir);

            $layoutPath = $layoutDir.'/default.blade.php';

            $content = \File::get($this->pathTo['controllers'].'BaseController.php');
            if(strpos($content, "\$layout") === false)
            {
                $content = preg_replace("/Controller {/", "Controller {\n\tprotected \$layout = 'layouts.default';", $content);
                \File::put($this->pathTo['controllers'].'BaseController.php', $content);
            }

            if(!\File::isDirectory('public/js'))
                \File::makeDirectory('public/js');
            if(!\File::isDirectory('public/css'))
                \File::makeDirectory('public/css');
            if(!\File::isDirectory('public/img'))
                \File::makeDirectory('public/img');

            if(\File::exists($layoutPath))
            {
                $overwrite = $this->confirm('Layout file exists. Overwrite? [y/n]? ', true);
            }

            if(!\File::exists($layoutPath) || $overwrite)
            {
                $this->fileContents = \File::get($this->pathTo['templates'].'layout.txt');

                $this->downloadAsset("jquery", "http://code.jquery.com/jquery-1.11.0.min.js");

                $this->downloadCSSFramework();

                $this->downloadAsset("underscore", "http://underscorejs.org/underscore-min.js");
                $this->downloadAsset("handlebars", "http://builds.handlebarsjs.com.s3.amazonaws.com/handlebars-v1.3.0.js");
                $this->downloadAsset("angular", "https://ajax.googleapis.com/ajax/libs/angularjs/1.2.12/angular.min.js");
                $this->downloadAsset("ember", "http://builds.emberjs.com/tags/v1.4.0/ember.min.js");
                $this->downloadAsset("backbone", "http://backbonejs.org/backbone-min.js");

                \File::put($layoutPath, $this->fileContents);
            }
            else
            {
                $this->error('Layout file already exists!');
            }
        }
    }

    /*
    *	Download either bootstrap or foundation
    */
    private function downloadCSSFramework()
    {
        if( $this->downloads['bootstrap'] )
        {
            $ch = curl_init("https://github.com/twbs/bootstrap/releases/download/v3.1.0/bootstrap-3.1.0-dist.zip");
            $fp = fopen("public/bootstrap.zip", "w");

            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            curl_exec($ch);
            curl_close($ch);
            fclose($fp);

            $zip = zip_open("public/bootstrap.zip");
            if ($zip)
            {
                while ($zip_entry = zip_read($zip))
                {
                    $foundationFile = "public/".zip_entry_name($zip_entry);
                    $foundationDir = dirname($foundationFile);

                    if(!\File::isDirectory($foundationDir))
                    {
                        \File::makeDirectory($foundationDir);
                    }

                    if($foundationFile[strlen($foundationFile)-1] == "/")
                    {
                        if(!is_dir($foundationDir))
                            \File::makeDirectory($foundationDir);
                    }
                    else
                    {
                        $fp = fopen($foundationFile, "w");
                        if (zip_entry_open($zip, $zip_entry, "r"))
                        {
                            $buf = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
                            fwrite($fp,"$buf");
                            zip_entry_close($zip_entry);
                            fclose($fp);
                        }
                    }
                }
                zip_close($zip);
                \File::delete('public/bootstrap.zip');

                $dirPath = 'public/dist';
                $this->rcopy($dirPath, 'public/bootstrap');
                foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dirPath, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $path) {
                    $path->isFile() ? unlink($path->getPathname()) : rmdir($path->getPathname());
                }
                rmdir($dirPath);
            }

            $fileReplace = "\t<link href=\"{{ url('bootstrap/css/bootstrap.min.css') }}\" rel=\"stylesheet\">\n";
            $fileReplace .= "\t<style>\n";
            $fileReplace .= "\t\tbody {\n";
            $fileReplace .= "\t\tpadding-top: 60px;\n";
            $fileReplace .= "\t\t}\n";
            $fileReplace .= "\t</style>\n";
            $fileReplace .= "\t<link href=\"{{ url('bootstrap/css/bootstrap-theme.min.css') }}\" rel=\"stylesheet\">\n";
            $fileReplace .= "<!--[css]-->\n";
            $this->fileContents = str_replace("<!--[css]-->",  $fileReplace, $this->fileContents);
            $this->fileContents = str_replace("<!--[javascript]-->", "<script src=\"{{ url('bootstrap/js/bootstrap.min.js') }}\"></script>\n<!--[javascript]-->", $this->fileContents);
            $this->info("Bootstrap files loaded to public/bootstrap!");
        }
        else if($this->downloads['foundation'])
        {
            $ch = curl_init("http://foundation.zurb.com/cdn/releases/foundation-5.1.1.zip");
            $fp = fopen("public/foundation.zip", "w");

            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            curl_exec($ch);
            curl_close($ch);
            fclose($fp);
            $zip = zip_open("public/foundation.zip");
            if ($zip)
            {
                while ($zip_entry = zip_read($zip))
                {
                    $foundationFile = "public/".zip_entry_name($zip_entry);
                    $foundationDir = dirname($foundationFile);
                    if(!\File::isDirectory($foundationDir))
                        \File::makeDirectory($foundationDir);

                    $fp = fopen("public/".zip_entry_name($zip_entry), "w");
                    if (zip_entry_open($zip, $zip_entry, "r"))
                    {
                        $buf = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
                        fwrite($fp,"$buf");
                        zip_entry_close($zip_entry);
                        fclose($fp);
                    }
                }
                zip_close($zip);
                \File::delete('public/index.html');
                \File::delete('public/robots.txt');
                \File::delete('humans.txt');
                \File::delete('foundation.zip');
                \File::deleteDirectory('public/js/foundation');
                \File::deleteDirectory('public/js/vendor');
                \File::move('public/js/foundation.min.js', 'public/js/foundation.js');
            }
            $fileReplace = "\t<link href=\"{{ url ('css/foundation.min.css') }}\" rel=\"stylesheet\">\n<!--[css]-->";
            $this->fileContents = str_replace("<!--[css]-->",  $fileReplace, $this->fileContents);
            $this->fileContents = str_replace("<!--[javascript]-->", "<script src=\"{{ url ('/js/foundation.js') }}\"></script>\n<!--[javascript]-->", $this->fileContents);

            $this->info('Foundation successfully set up (v4.0.5)!');
        }
    }

    public function createFunction($name, $content, $args = "", $type = "public")
    {
        $fileContents = "\t$type function $name($args)\n";
        $fileContents .= "\t{\n";
        $fileContents .= $content;
        $fileContents .= "\t}\n\n";

        return $fileContents;
    }

    public function createInterface($path, $content)
    {
        $this->createClass($path, $content, array(), array(), array(), "interface");
    }

    public function createMigrationClass($path, $content, $name)
    {
        $className = "Create" . $name . "Table";
        $this->createClass($path, $content, array('name' => 'Migration'), array(), array('Illuminate\Database\Migrations\Migration', 'Illuminate\Database\Schema\Blueprint'), "class", $className, false, true);
    }

    public function createClass($path, $content, array $extends = array(), $vars = array(), array $uses = array(), $type = "class", $customName = "", $useNamespace = true, $overwrite = false)
    {
        $usesOutput = "";
        $extendsOutput = "";
        $namespace = "";

        $fileName = substr(strrchr($path, "/"), 1);

        if(empty($customName))
            $className = substr($fileName, 0, strrpos($fileName, "."));
        else
            $className = $customName;

        if($this->namespace && $useNamespace)
            $namespace = "namespace " . $this->namespace . ";";

        if($uses) {
            foreach($uses as $use) {
                $usesOutput .= "use $use;\n";
            }
            $usesOutput .= "\n";
        }

        if($extends) {
            $extendName = "extends";
            if(array_key_exists('type', $extends))
                $extendName = $extends['type'];

            $extendsOutput .= "$extendName";
            foreach($extends as $key => $extend) {
                if($key != "type") {
                    $extendsOutput .= " ".$extend.",";
                }
            }
            $extendsOutput = rtrim($extendsOutput, ",") . " ";
        }

        $fileContents = "<?php ".$namespace."\n\n";
        $fileContents .= "$usesOutput";
        $fileContents .= "$type ". $className . " " . $extendsOutput . "\n{\n";
        foreach($vars as $type => $name) {
            $fileContents .= "\t$type \$$name;\n";
        }
        $fileContents .= "\n";
        $fileContents .= $content;
        $fileContents .= "}\n";

        $this->createFile($path, $fileContents, $overwrite);
    }

    /*
    *   Checks if file exists, and then prompts to overwrite
    */
    public function createFile($fileName, $fileContents, $overwrite = false)
    {
        if(\File::exists($fileName) && !$overwrite)
        {
            $overwrite = $this->confirm("$fileName already exists! Overwrite it? ", true);

            if($overwrite)
            {
                \File::put($fileName, $fileContents);
            }
        }
        else
        {
            \File::put($fileName, $fileContents);
        }
    }

    /**
     * @param $dir
     */
    public function createDirectory($dir)
    {
        if (!\File::isDirectory($dir))
            \File::makeDirectory($dir);
    }

    /**
     * @param $functions
     * @param $fileContents
     */
    public function createFunctions($functions)
    {
        $fileContents = "";
        foreach ($functions as $function) {
            $args = "";
            if(array_key_exists('args', $function))
                $args = $function['args'];
            $fileContents .= $this->createFunction($function['name'], $function['content'], $args);
        }
        return $fileContents;
    }

    private function rcopy($src, $dst)
    {
        if (file_exists ( $dst ))
            $this->rrmdir ( $dst );
        if (is_dir ( $src )) {
            mkdir ( $dst );
            $files = scandir ( $src );
            foreach ( $files as $file )
                if ($file != "." && $file != "..")
                    $this->rcopy ( "$src/$file", "$dst/$file" );
        } else if (file_exists ( $src ))
            copy ( $src, $dst );
    }

    private function rrmdir($dir)
    {
        if (is_dir($dir)) {
            $files = scandir($dir);
            foreach ($files as $file)
                if ($file != "." && $file != "..") $this->rrmdir("$dir/$file");
            rmdir($dir);
        }
        else if (file_exists($dir)) unlink($dir);
    }
}
