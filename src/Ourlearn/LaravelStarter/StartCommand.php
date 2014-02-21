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

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        $aliases = \Config::get('app.aliases');
        foreach ($aliases as $alias => $facade) {
            array_push($this->laravelClasses, strtolower($alias));
        }

        $this->generateLayoutFiles();

        $this->info('Add model with its fields or type "q" to quit.');
        $this->info('Example with namespace: MyNamespace\Book title:string year:integer');
        $this->info('Or with a relation: Book belongsTo Author title:string published:integer');

        $modelAndFields = $this->ask('Now your turn: ');
        $moreTables = $modelAndFields == "q" ? false : true;

        while( $moreTables )
        {
            $this->relationship = array();
            $this->namespace = "";

            $values = explode(" ", $modelAndFields);
            $modelWithNamespace = $values[0];

            if(strpos($modelWithNamespace, "\\"))
                $model = substr(strrchr($modelWithNamespace, "\\"), 1);
            else
                $model = $modelWithNamespace;

            $this->namespace = substr($modelWithNamespace, 0, strrpos($modelWithNamespace, "\\"));

            $this->model = new Model($model, $this->namespace);

            while(in_array($this->model->lower(), $this->laravelClasses)) {
                $modelAndFields = $this->ask($this->model->upper() ." is already in the global namespace. Please namespace your class or provide a different name: ");
                $this->namespace = "";

                $values = explode(" ", $modelAndFields);
                $modelWithNamespace = $values[0];

                if(strpos($modelWithNamespace, "\\"))
                    $model = substr(strrchr($modelWithNamespace, "\\"), 1);
                else
                    $model = $modelWithNamespace;

                $this->namespace = substr($modelWithNamespace, 0, strrpos($modelWithNamespace, "\\"));

                $this->model = new Model($model, $this->namespace);
            }

            $this->propertiesArr = array();
            $this->propertiesStr = "";

            unset($values[0]);
            $additionalFields = !empty($values);

            if( $additionalFields )
            {
                if(!strpos($values[1], ":")) {
                    $relationship = $values[1];
                    $relatedTable = $values[2];

                    $this->relationship = array(new Relation($relationship, new Model($relatedTable)));

                    unset($values[1]);
                    unset($values[2]);
                }

                $this->fieldNames = $values;

                foreach($this->fieldNames as $field)
                {
                    $pos = strrpos($field, ":");
                    if ($pos !== false)
                    {
                        $type = substr($field, $pos+1);
                        $field = substr($field, 0, $pos);
                    }

                    $this->propertiesArr[$field] = $type;
                    $this->propertiesStr .= "'".$field ."',";
                }

                $this->propertiesStr = substr($this->propertiesStr, 0, strlen($this->propertiesStr)-1);
            }

            $this->createModel();

            $this->isResource = $this->confirm('Do you want resource (y) or restful (n) controllers? ');

            $this->createMigrations();

            $this->runMigrations();

            $this->createSeeds();

            $this->createDirectory("app/repositories");
            $this->createDirectory("app/repositories/interfaces");

            $this->createRepositoryInterface();

            $this->createRepository();

            $this->putRepositoryFolderInStartFiles();

            $this->createController();

            $this->createViews();

            $this->updateRoutesFile();

            $this->createDirectory('app/tests/controller');

            $this->createTestsFile();

            $modelAndFields = $this->ask('Add model with fields or "q" to quit (eg. MyNamespace\Book title:string year:integer) ');
            $moreTables = $modelAndFields == "q" ? false : true;
        }

        $this->info('Please wait a few moments...');

        $this->call('clear-compiled');
        $this->call('optimize');

        $this->info('Done!');
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

        $migrationFile = \App::make('Illuminate\Database\Migrations\MigrationCreator')->create($createTable, "app/database/migrations", $tableName, true);

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

                if($tableOne[0] > $tableTwo[0])
                    $tableName = $tableTwo ."_".$tableOne;
                else
                    $tableName = $tableOne ."_".$tableTwo;

                $content .= "\t\tif(!Schema::hasTable('".$tableName."')) {\n";
                $content .= "\t\t\tSchema::create('".$tableName."', function(Blueprint \$table) {\n";
                $content .= "\t\t\t\t\$table->integer('".$tableOne."_id')->unsigned();\n";
                $content .= "\t\t\t\t\$table->integer('".$tableTwo."_id')->unsigned();\n";
                $content .= "\t\t\t});\n";
                $content .= "\t\t}\n";
            } else if($relation->getType() == "hasOne" || $relation->getType() == "hasMany") {
                $content .= "\t\tif(Schema::hasColumn('".$relation->model->plural()."','".$this->model->lower()."_id')) {\n";
                $content .= "\t\t\tSchema::table('".$relation->model->plural()."', function(Blueprint \$table) {\n";
                $content .= "\t\t\t\t\$table->foreign('". $this->model->lower()."_id')->references('id')->on('".$this->model->plural()."');\n";
                $content .= "\t\t\t});\n";
                $content .= "\t\t}\n";
            }
        }
        return $content;
    }

    private function addForeignKeys()
    {
        $fields = "";
        foreach($this->relationship as $relation) {
            if($relation->getType() == "belongsTo") {
                $foreignKey = $relation->model->lower() . "_id";
                $fields .= "\t\t\t" .$this->setColumn('integer', $foreignKey);
                $fields .= $this->addColumnOption('unsigned') . ";\n";
                //$fields .= "\t\t\t\$table->foreign('". $foreignKey."')->references('id')->on('".$relation->model->plural()."');\n";
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

        if(!empty($this->fieldNames))
        {
            // Build up the schema
            foreach( $this->fieldNames as $arg ) {
                // Like age, integer, and nullable
                @list($field, $type, $setting) = explode(':', $arg);

                if ( !$type )
                {
                    echo "There was an error in your formatting. Please try again. Did you specify both a field and data type for each? age:int\n";
                    die();
                }

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
        $fileName = "app/models/" . $this->model->upper() . ".php";
        $fileContents = "";
        foreach ($this->relationship as $relation) {
            $relatedModel = $relation->model;

            $functionContent = "\t\treturn \$this->" . $relation->getType() . "('".$relatedModel->nameWithNamespace()."');\n";
            $fileContents .= $this->createFunction($relation->getName(), $functionContent);

            $relatedModelFile = 'app/models/'.$relatedModel->upper().'.php';
            $continue = true;

            if(!\File::exists($relatedModelFile)) {
                $continue = $this->confirm("Model ". $relatedModel->upper() . " doesn't exist yet. Would you like to create it now [y/n]? ", true);
                if($continue) {
                    $this->createClass($relatedModelFile, "", ['name' => "\\Eloquent"]);
                }
            }

            if($continue) {
                $index = 0;

                $reverseRelations = $relation->reverseRelations();

                if(count($reverseRelations) > 1) {
                    $index = $this->ask("How does " . $relatedModel->upper() . " relate back to ". $this->model->upper() ."? (0=".$reverseRelations[0]. " 1=".$reverseRelations[1] .") ");
                }

                $reverseRelationType = $reverseRelations[$index];
                $reverseRelationName = $relation->getReverseName($this->model, $reverseRelationType);

                $content = \File::get($relatedModelFile);
                if (preg_match("/function ".$this->model->lower()."/", $content) !== 1 && preg_match("/function ".$this->model->plural()."/", $content) !== 1) {
                    $content = substr($content, 0, strrpos($content, "}"));
                    $functionContent = "\t\treturn \$this->" . $reverseRelationType . "('".$this->model->nameWithNamespace()."');\n";
                    $content .= $this->createFunction($reverseRelationName, $functionContent) . "}\n";
                }

                \File::put($relatedModelFile, $content);
            }
        }

        $this->createClass($fileName, $fileContents, ["name" => "\\Eloquent"]);

        $this->info('Model "'.$this->model->upper().'" created!');
    }

    private function createSeeds()
    {
        $faker = Factory::create();

        $databaseSeeder = 'app/database/seeds/DatabaseSeeder.php';
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
        $functionContent .= "\t\tfor(\$i = 0; \$i < 10; \$i++) {\n";

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
        $functionContent .= "\t\t\t);\n";
        $functionContent .= "\t\t\t".$this->model->upper()."::create(\$".$this->model->lower().");\n";
        $functionContent .= "\t\t}\n";

        $fileContents = $this->createFunction("run", $functionContent);

        $fileName = "app/database/seeds/" . $this->model->upperPlural() . "TableSeeder.php";
        $this->createClass($fileName, $fileContents, ['name' => 'DatabaseSeeder']);

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
        $fileName = "app/repositories/interfaces/" . $this->model->upper() . "RepositoryInterface.php";

        $fileContents = "\tpublic function all();\n";
        $fileContents .= "\tpublic function find(\$id);\n";
        $fileContents .= "\tpublic function store(\$input);\n";
        $fileContents .= "\tpublic function update(\$id, \$input);\n";
        $fileContents .= "\tpublic function destroy(\$id);\n";

        $this->createInterface($fileName, $fileContents);
    }

    /**
     * @return array
     */
    private function createRepository()
    {
        $functions = array();

        $functionContents = "\t\t\$this->" . $this->model->lower() . " = \$" . $this->model->lower() . ";\n";
        array_push($functions, ['name' => '__construct', 'content' => $functionContents, 'args' => $this->model->upper() . " \$" . $this->model->lower()]);
        $functionContents = "\t\treturn \$this->" . $this->model->lower() . "->all();\n";
        array_push($functions, ['name' => 'all', 'content' => $functionContents]);
        $functionContents = "\t\treturn \$this->" . $this->model->lower() . "->find(\$id);\n";
        array_push($functions, ['name' => 'find', 'content' => $functionContents, 'args' => "\$id"]);
        $functionContents = "        \$" . $this->model->lower() . " = new " . $this->model->upper() . ";\n";
        foreach ($this->propertiesArr as $property => $type) {
            $functionContents .= "        \$" . $this->model->lower() . "->" . $property . " = \$input['" . $property . "'];\n";
        }
        $functionContents .= "        \$" . $this->model->lower() . "->save();\n";
        array_push($functions, ['name' => 'store', 'content' => $functionContents, 'args' => "\$input"]);
        $functionContents = "\t\t\$" . $this->model->lower() . " = \$this->find(\$id);\n";
        foreach ($this->propertiesArr as $property => $type) {
            $functionContents .= "        \$" . $this->model->lower() . "->" . $property . " = \$input['" . $property . "'];\n";
        }
        $functionContents .= "        \$" . $this->model->lower() . "->save();\n";
        array_push($functions, ['name' => 'update', 'content' => $functionContents, 'args' => "\$id, \$input"]);
        $functionContents = "\t\t\$this->find(\$id)->delete();\n";
        array_push($functions, ['name' => 'destroy', 'content' => $functionContents, 'args' => "\$id"]);

        $fileContents = $this->createFunctions($functions);

        $fileName = 'app/repositories/Eloquent' . $this->model->upper() . 'Repository.php';
        $vars = ["private" => $this->model->lower()];
        $extends = ['type' => 'implements', "name"=>$this->model->upper() . "RepositoryInterface"];

        $this->createClass($fileName, $fileContents, $extends, $vars);

        $this->info($this->model->upper().'Repository created!');
    }

    /**
     * @return mixed
     */
    private function putRepositoryFolderInStartFiles()
    {
        $content = \File::get('app/start/global.php');
        if (preg_match("/repositories/", $content) !== 1)
            $content = preg_replace("/app_path\(\).'\/controllers',/", "app_path().'/controllers',\n\tapp_path().'/repositories',", $content);

        \File::put('app/start/global.php', $content);

        $content = \File::get('composer.json');
        if (preg_match("/repositories/", $content) !== 1)
            $content = preg_replace("/\"app\/controllers\",/", "\"app/controllers\",\n\t\t\t\"app/repositories\",", $content);

        \File::put('composer.json', $content);
    }

    /**
     * @return array
     */
    private function createController()
    {
        $fileName = "app/controllers/" . $this->model->upper() . "Controller.php";

        if ($this->isResource)
            $functionNames = ['constructor' => '__construct', 'index' => 'index', 'create' => 'create', 'store' => 'store', 'show' => 'show', 'edit' => 'edit', 'update' => 'update', 'destroy' => 'destroy'];
        else
            $functionNames = ['constructor' => '__construct', 'index' => 'getIndex', 'create' => 'getCreate', 'store' => 'postIndex', 'show' => 'getDetails', 'edit' => 'getEdit', 'update' => 'putIndex', 'destroy' => 'deleteIndex'];

        $functions = array();

        $functionContents = "\t\t\$this->" . $this->model->lower() . " = \$" . $this->model->lower() . ";\n";
        array_push($functions, ['name' => $functionNames['constructor'], 'content' => $functionContents, 'args' => $this->model->upper() . "RepositoryInterface \$" . $this->model->lower()]);

        $functionContents = "    \t\$" . $this->model->plural() . " = \$this->" . $this->model->lower() . "->all();\n";
        $functionContents .= "        \$this->layout->content = \\View::make('" . $this->model->lower() . ".all', compact('" . $this->model->plural() . "'));\n";
        array_push($functions, ['name' => $functionNames['index'], 'content' => $functionContents]);

        $functionContents = "        \$this->layout->content = \\View::make('" . $this->model->lower() . ".new');\n";
        array_push($functions, ['name' => $functionNames['create'], 'content' => $functionContents]);

        $functionContents = "        \$this->" . $this->model->lower() . "->store(\\Input::only(" . $this->propertiesStr . "));\n";
        $functionContents .= "        return \\Redirect::to('" . $this->model->lower() . "');\n";
        array_push($functions, ['name' => $functionNames['store'], 'content' => $functionContents]);

        $functionContents = "        \$" . $this->model->lower() . " = \$this->" . $this->model->lower() . "->find(\$id);\n";
        $functionContents .= "        \$this->layout->content = \\View::make('" . $this->model->lower() . ".view')->with('" . $this->model->lower() . "', \$" . $this->model->lower() . ");\n";
        $functionContents .= "        //return Response::json(['" . $this->model->lower() . "' => \$" . $this->model->lower() . "]);\n";
        array_push($functions, ['name' => $functionNames['show'], 'content' => $functionContents, 'args' => "\$id"]);

        $functionContents = "        \$" . $this->model->lower() . " = \$this->" . $this->model->lower() . "->find(\$id);\n";
        $functionContents .= "        \$this->layout->content = \\View::make('" . $this->model->lower() . ".edit')->with('" . $this->model->lower() . "', \$" . $this->model->lower() . ");\n";
        array_push($functions, ['name' => $functionNames['edit'], 'content' => $functionContents, 'args' => "\$id"]);

        $functionContents = "        \$" . $this->model->lower() . " = \$this->" . $this->model->lower() . "->update(\$id, \\Input::only([" . $this->propertiesStr . "]));\n";
        $functionContents .= "        return \\Redirect::to('" . $this->model->lower() . "/'.\$id);\n";
        array_push($functions, ['name' => $functionNames['update'], 'content' => $functionContents, 'args' => "\$id"]);

        $functionContents = "        \$this->" . $this->model->lower() . "->destroy(\$id);\n";
        array_push($functions, ['name' => $functionNames['destroy'], 'content' => $functionContents, 'args' => "\$id"]);

        $fileContents = $this->createFunctions($functions);

        $this->createClass($fileName, $fileContents, ["name"=>"\\BaseController"], ['protected' => $this->model->lower()]);

        $this->info($this->model->upper() . 'Controller created!');
    }

    /**
     * @return array
     */
    private function createTestsFile()
    {
        $functions = array();

        $functionContents = "\t\t\$this->call('GET', '" . $this->model->lower() . "');\n";
        $functionContents .= "\t\t\$this->assertResponseOk();\n";
        array_push($functions, ['name' => 'testIndex', 'content' => $functionContents]);

        $getPath = $this->isResource ? "" : "details/";

        $functionContents = "\t\t\$this->call('GET', '" . $this->model->lower() . "/" . $getPath . "1');\n";
        $functionContents .= "\t\t\$this->assertResponseOk();\n";
        array_push($functions, ['name' => 'testShow', 'content' => $functionContents]);

        $functionContents = "\t\t\$this->call('GET', '" . $this->model->lower() . "/create');\n";
        $functionContents .= "\t\t\$this->assertResponseOk();\n";
        array_push($functions, ['name' => 'testCreate', 'content' => $functionContents]);

        $getPath = $this->isResource ? "/1/edit" : "/edit/1";

        $functionContents = "\t\t\$this->call('GET', '" . $this->model->lower() . $getPath."');\n";
        $functionContents .= "\t\t\$this->assertResponseOk();\n";
        array_push($functions, ['name' => 'testEdit', 'content' => $functionContents]);

        $fileContents = $this->createFunctions($functions);

        $fileName = "app/tests/controller/" . $this->model->upperPlural() . "ControllerTest.php";

        $this->createClass($fileName, $fileContents, ["name" => "\\TestCase"]);

        $this->info('Tests created!');
        return array($fileContents, $fileName);
    }

    /**
     * @return string
     */
    private function updateRoutesFile()
    {
        $routeFile = "app/routes.php";

        $fileContents = "\nApp::bind('" . $this->model->nameWithNamespace() . "RepositoryInterface','" . $this->namespace . "Eloquent" . $this->model->upper() . "Repository');\n";

        $routeType = $this->isResource ? "resource" : "controller";

        $fileContents .= "Route::" . $routeType . "('" . $this->model->lower() . "', '" . $this->model->nameWithNamespace() . "Controller');\n";

        $content = \File::get($routeFile);
        if (preg_match("/" . $this->model->lower() . "/", $content) !== 1) {
            \File::append($routeFile, $fileContents);
        }

        $this->info('Routes file updated!');
    }

    private function createViews()
    {
        /*******************************************************************
         *
         *                   view.blade.php
         *
         ********************************************************************/
        $dir = "app/views/" . $this->model->lower() . "/";
        if (!\File::isDirectory($dir))
            \File::makeDirectory($dir);
        $fileName = $dir . "view.blade.php";
        $fileContents = "@section('content')\n";
        $fileContents .= "<div class=\"row\">\n";
        $fileContents .= "    <h1>Viewing " . $this->model->lower() . "</h1>\n";
        $fileContents .= "    <a class=\"btn\" href=\"{{ url('" . $this->model->lower() . "/'.\$" . $this->model->lower() . "->id.'/edit') }}\">Edit</a>\n";
        $fileContents .= "</div>\n";
        $fileContents .= "<div class=\"row\">\n";
        $fileContents .= "    <ul>\n";
        if ($this->propertiesArr) {
            foreach ($this->propertiesArr as $property => $type) {
                $upper = ucfirst($property);
                $fileContents .= "        <li>$upper: {{ \$" . $this->model->lower() . "->" . $property . " }}</li>";
            }
        }
        $fileContents .= "    <ul>\n";
        $fileContents .= "</div>\n";
        $fileContents .= "@stop\n";
        $this->createFile($fileName, $fileContents);

        /*******************************************************************
         *
         *                  edit.blade.php
         *
         ********************************************************************/
        $fileName = $dir . "edit.blade.php";
        $fileContents = "@section('content')\n";
        $fileContents .= "<div class=\"row\">\n";
        $fileContents .= "    <h2>Edit " . $this->model->lower() . "</h2>\n";
        $fileContents .= "</div>\n";
        $fileContents .= "<div class=\"row\">\n";
        $fileContents .= "    <form class=\"form-horizontal\" method=\"POST\" action=\"{{ url('" . $this->model->lower() . "/'.\$" . $this->model->lower() . "->id) }}\">\n";
        $fileContents .= "    <input type=\"hidden\" name=\"_method\" value=\"PUT\">\n";
        if ($this->propertiesArr) {
            foreach ($this->propertiesArr as $property => $type) {
                $upper = ucfirst($property);
                $fileContents .= "    <div class=\"form-group\">\n";
                $fileContents .= "        <label class=\"control-label\" for=\"$property\">$upper</label>\n";
                $fileContents .= "        <input type=\"text\" name=\"$property\" id=\"$property\" placeholder=\"$upper\" value=\"{{ \$" . $this->model->lower() . "->$property }}\">\n";
                $fileContents .= "    </div>\n";
            }
        }
        $fileContents .= "    <div class=\"form-group\">\n";
        $fileContents .= "        <label class=\"control-label\"></label>\n";
        $fileContents .= "        <input class=\"btn\" type=\"reset\" value=\"Reset\">\n";
        $fileContents .= "        <input class=\"btn\" type=\"submit\" value=\"Edit " . $this->model->lower() . "\">\n";
        $fileContents .= "    </div>\n";
        $fileContents .= "    </form>\n";
        $fileContents .= "</div>\n";
        $fileContents .= "@stop\n";
        $this->createFile($fileName, $fileContents);

        /*******************************************************************
         *
         *                  all.blade.php
         *
         ********************************************************************/
        $fileName = $dir . "all.blade.php";
        $fileContents = "@section('content')\n";
        $fileContents .= "<div class=\"row\">\n";
        $fileContents .= "    <h1>All " . $this->model->upperPlural() . "</h1>\n";
        $fileContents .= "    <a class=\"btn\" href=\"{{ url('".$this->model->lower()."/create') }}\">New</a>\n";
        $fileContents .= "</div>\n";
        $fileContents .= "<div class=\"row\">\n";
        $fileContents .= "<table class=\"table\">\n";
        $fileContents .= "<thead>\n";
        if ($this->propertiesArr) {
            foreach ($this->propertiesArr as $property => $type) {
                $fileContents .= "\t<th>" . ucfirst($property) . "</th>\n";
            }
        }
        $fileContents .= "</thead>\n";
        $fileContents .= "<tbody>\n";
        $fileContents .= "@foreach(\$" . $this->model->plural() . " as \$" . $this->model->lower() . ")\n";
        $fileContents .= "\t<tr>\n\t\t";
        if ($this->propertiesArr) {
            foreach ($this->propertiesArr as $property => $type) {
                $fileContents .= "<td><a href=\"{{ url('" . $this->model->lower() . "/'.\$" . $this->model->lower() . "->id) }}\">{{ \$" . $this->model->lower() . "->$property }}</a></td>";
            }
        }
        $fileContents .= "\n\t</tr>\n";
        $fileContents .= "@endforeach\n";
        $fileContents .= "</tbody>\n";
        $fileContents .= "</table>\n";
        $fileContents .= "</div>\n";
        $fileContents .= "@stop\n";
        $this->createFile($fileName, $fileContents);

        /*******************************************************************
         *
         *                   new.blade.php
         *
         ********************************************************************/
        $fileName = $dir . "new.blade.php";
        $fileContents = "@section('content')\n";
        $fileContents .= "<div class=\"row\">\n";
        $fileContents .= "    <h2>New " . $this->model->upper() . "</h2>\n";
        $fileContents .= "</div>\n";
        $fileContents .= "<div class=\"row\">\n";
        $fileContents .= "    <form class=\"form-horizontal\" method=\"POST\" action=\"{{ url('" . $this->model->lower() . "') }}\">\n";
        if ($this->propertiesArr) {
            foreach ($this->propertiesArr as $property => $type) {
                $upper = ucfirst($property);
                $fileContents .= "    <div class=\"form-group\">\n";
                $fileContents .= "        <label class=\"control-label\" for=\"$property\">$upper</label>\n";
                $fileContents .= "        <input type=\"text\" name=\"$property\" id=\"$property\" placeholder=\"$upper\">\n";
                $fileContents .= "    </div>\n";
            }
        }
        $fileContents .= "    <div class=\"form-group\">\n";
        $fileContents .= "        <label class=\"control-label\"></label>\n";
        $fileContents .= "        <input class=\"btn\" type=\"reset\" value=\"Reset\">\n";
        $fileContents .= "        <input class=\"btn\" type=\"submit\" value=\"Add New " . $this->model->lower() . "\">\n";
        $fileContents .= "    </div>\n";
        $fileContents .= "</div>\n";
        $fileContents .= "@stop\n";
        $this->createFile($fileName, $fileContents);
        $this->info('Views created!');
    }

    private function downloadAsset($assetName, $downloadLocation)
    {
        $type = substr(strrchr($downloadLocation, "."), 1);

        $confirmedAsset = $this->confirm('Do you want '.$assetName.' [y/n]? ', true);
        if( $confirmedAsset )
        {
            if($assetName == "jquery")
            {
                $version = $this->confirm("Do you want v1.11 (y) or 2.1 (n)? ");
                if(!$version)
                {
                    $downloadLocation = "http://code.jquery.com/jquery-2.1.0.min.js";
                }
            }
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

            $this->fileContents .= "<script src=\"{{ url('$type/$assetName.$type') }}\"></script>\n";
            $this->info("public/$type/$assetName.$type created!");
        }
    }

    /*
    *   Generates a default layout
    */
    private function generateLayoutFiles()
    {
        $makeLayout = $this->confirm('Do you want to create a default layout file [y/n]? ', true);
        if( $makeLayout )
        {
            $layoutDir = 'app/views/layouts';
            if(!\File::isDirectory($layoutDir))
                \File::makeDirectory($layoutDir);

            $layoutPath = $layoutDir.'/default.blade.php';

            $content = \File::get('app/controllers/BaseController.php');
            if(preg_match("/\$layout/", $content) !== 1)
            {
                $content = preg_replace("/Controller {/", "Controller {\n\tprotected \$layout = 'layouts.default';", $content);
                \File::put('app/controllers/BaseController.php', $content);
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
                $this->fileContents = "<!DOCTYPE html>\n";
                $this->fileContents .= "<html lang=\"en\">\n";
                $this->fileContents .= "<head>\n";
                $this->fileContents .= "\t<meta charset=\"utf-8\">\n";
                $this->fileContents .= "\t<meta name=\"description\" content=\"\">\n";
                $this->fileContents .= "\t<meta name=\"author\" content=\"\">\n";
                $this->fileContents .= "\t<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
                $this->fileContents .= "\t<title>Untitled</title>\n";
                $this->fileContents .= "<!-- CSS -->\n";
                $this->fileContents .= "\t<link rel=\"stylesheet\" href=\"/css/style.css\">\n";
                $this->fileContents .= "</head>\n";
                $this->fileContents .= "<body>\n";
                $this->fileContents .= "<div class=\"container\">\n";
                $this->fileContents .= "\t@yield('content')\n";
                $this->fileContents .= "</div>\n";

                $this->downloadAsset("jquery", "http://code.jquery.com/jquery-1.11.0.min.js");

                $this->downloadCSSFramework();

                $this->downloadAsset("underscore", "http://underscorejs.org/underscore-min.js");
                $this->downloadAsset("handlebars", "http://builds.handlebarsjs.com.s3.amazonaws.com/handlebars-v1.3.0.js");
                $this->downloadAsset("angular", "https://ajax.googleapis.com/ajax/libs/angularjs/1.2.12/angular.min.js");
                $this->downloadAsset("ember", "http://builds.emberjs.com/tags/v1.4.0/ember.min.js");
                $this->downloadAsset("backbone", "http://backbonejs.org/backbone-min.js");

                $this->fileContents .= "<script src=\"{{ url('js/main.js') }}\"></script>\n";
                $this->fileContents .= "</body>\n";
                $this->fileContents .= "</html>\n";
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
        $bootstrap = $this->confirm('Do you want twitter bootstrap [y/n]? ', true);
        if( $bootstrap )
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
            }

            $fileReplace = "\t<link href=\"{{ url('dist/css/bootstrap.min.css') }}\" rel=\"stylesheet\">\n";
            $fileReplace .= "\t<style>\n";
            $fileReplace .= "\t\tbody {\n";
            $fileReplace .= "\t\tpadding-top: 60px;\n";
            $fileReplace .= "\t\t}\n";
            $fileReplace .= "\t</style>\n";
            $fileReplace .= "\t<link href=\"{{ url('dist/css/bootstrap-theme.min.css') }}\" rel=\"stylesheet\">\n";
            $this->fileContents = preg_replace('/<!-- CSS -->/',  $fileReplace, $this->fileContents);
            $this->fileContents .= "<script src=\"{{ url('dist/js/bootstrap.min.js') }}\"></script>\n";
            $this->info("Bootstrap files loaded to public/bootstrap!");
        }
        else
        {
            $foundation = $this->confirm('Do you want foundation [y/n]? ', true);
            if( $foundation )
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
                $fileReplace = "\t<link href=\"{{ url ('css/foundation.min.css') }}\" rel=\"stylesheet\">\n";
                $this->fileContents = preg_replace('/<!-- CSS -->/', $fileReplace, $this->fileContents);
                $this->fileContents .= "<script src=\"{{ url ('/js/foundation.js') }}\"></script>\n";
                $this->info('Foundation successfully set up (v4.0.5)!');
            }
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
        $this->createClass($path, $content, ['name' => 'Migration'], array(), ['Illuminate\Database\Migrations\Migration', 'Illuminate\Database\Schema\Blueprint'], "class", $className, false, true);
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
}
