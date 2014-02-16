<?php namespace Ourlearn\LaravelStarter;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class StartCommand extends Command
{
    protected $name = 'start';
    private $propertiesArr = array();
    private $propertiesStr = array();
    private $className = array();
    private $namespace;
    private $isResource;

    protected $description = "Makes table, controller, model, views, seeds, and repository";

    private $fileContents;

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        $this->generateLayoutFiles();

        $modelAndFields = $this->ask('Add model with fields or "q" to quit (eg. MyNamespace\Book title:string year:integer) ');
        $moreTables = $modelAndFields == "q" ? false : true;

        while( $moreTables )
        {
            $this->namespace = "";

            $values = explode(" ", $modelAndFields);
            $modelWithNamespace = $values[0];

            if(strpos($modelWithNamespace, "\\"))
                $model = substr(strrchr($modelWithNamespace, "\\"), 1);
            else
                $model = $modelWithNamespace;

            $this->namespace = substr($modelWithNamespace, 0, strrpos($modelWithNamespace, "\\"));

            $this->className['lower'] = strtolower($model);

            //$this->className['lower'] = strtolower($this->ask('Model/table name? '));

            $this->className['plural'] = str_plural($this->className['lower']);
            $this->className['upper'] = ucfirst($this->className['lower']);

            $this->className['upperPlural'] = str_plural($this->className['upper']);

            $this->createModel();

            $this->propertiesArr = array();
            $this->propertiesStr = "";

            unset($values[0]);
            $additionalFields = !empty($values);

            if( $additionalFields )
            {
                $fieldNames = $values;
                $file = new GenerateMigration($this->className['lower'], $fieldNames);

                foreach($fieldNames as $field)
                {
                    $pos = strrpos($field, ":");
                    if ($pos !== false)
                    {
                        $field = substr($field, 0, $pos);
                    }

                    array_push($this->propertiesArr, $field);
                    $this->propertiesStr .= "'".$field ."',";
                }

                $this->propertiesStr = substr($this->propertiesStr, 0, strlen($this->propertiesStr)-1);
            }
            else
                $file = new GenerateMigration($this->className['lower']);

            $app = $this->getLaravel();
            $app['composer']->dumpAutoloads();

            $this->isResource = $this->confirm('Do you want resource (y) or restful (n) controllers? ');

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

        $this->info('Done!');
    }

    protected function getArguments()
    {
        return array(
            array('name', InputArgument::OPTIONAL, 'Name of the model/controller.'),
        );
    }

    /*
    *   Creates the model
    */
    private function createModel()
    {
        $fileName = "app/models/" . $this->className['upper'] . ".php";
        $this->createClass($fileName, "", ["name" => "\\Eloquent"]);

        $this->info('Model "'.$this->className['upper'].'" created!');
    }

    private function createSeeds()
    {
        $functionContent = "\t\t\$".$this->className['plural']." = array(\n";

        foreach($this->propertiesArr as $property) {
            $functionContent .= "\t\t\t'$property' => 'Testing ".$this->className['lower']. $property."',\n";
        }
        $functionContent .= "\t\t);\n";
        $functionContent .= "\t\tDB::table('".$this->className['plural']."')->insert(\$".$this->className['plural'].");\n";

        $fileContents = $this->createFunction("run", $functionContent);

        $fileName = "app/database/seeds/" . $this->className['upperPlural'] . "TableSeeder.php";
        $this->createClass($fileName, $fileContents, ['name' => 'Seeder']);

        $databaseSeederPath = app_path() . '/database/seeds/DatabaseSeeder.php';
        $tableSeederClassName = $this->className['upperPlural'] . 'TableSeeder';

        $content = \File::get($databaseSeederPath);
        if(preg_match("/$tableSeederClassName/", $content) !== 1)
        {
            $content = preg_replace("/(run\(\).+?)}/us", "$1\t\$this->call('{$tableSeederClassName}');\n\t}", $content);
            \File::put($databaseSeederPath, $content);
        }

        $this->info('Database seed file created!');
    }

    private function createFunction($name, $content, $args = "", $type = "public")
    {
        $fileContents = "\t$type function $name($args)\n";
        $fileContents .= "\t{\n";
        $fileContents .= $content;
        $fileContents .= "\t}\n\n";

        return $fileContents;
    }

    private function createInterface($path, $content)
    {
        $this->createClass($path, $content, array(), array(), array(), "interface");
    }

    //private function createClassWithVars()

    private function createClass($path, $content, array $extends = array(), $vars = array(), array $uses = array(), $type = "class")
    {
        $usesOutput = "";
        $extendsOutput = "";

        $fileName = substr(strrchr($path, "/"), 1);

        $className = substr($fileName, 0, strrpos($fileName, "."));

        if($this->namespace)
            $this->namespace = "namespace " . $this->namespace . ";";

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

        $fileContents = "<?php ".$this->namespace."\n\n";
        $fileContents .= "$usesOutput";
        $fileContents .= "$type ". $className . " " . $extendsOutput . "\n{\n";
        foreach($vars as $type => $name) {
            $fileContents .= "\t$type \$$name;\n";
        }
        $fileContents .= "\n";
        $fileContents .= $content;
        $fileContents .= "}\n";

        $this->createFile($path, $fileContents);
    }

    /*
    *   Checks if file exists, and then prompts to overwrite
    */
    private function createFile($fileName, $fileContents)
    {
        if(\File::exists($fileName))
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

    /**
     * @param $dir
     */
    private function createDirectory($dir)
    {
        if (!\File::isDirectory($dir))
            \File::makeDirectory($dir);
    }

    /**
     * @return array
     */
    private function createRepositoryInterface()
    {
        $fileName = "app/repositories/interfaces/" . $this->className['upper'] . "RepositoryInterface.php";

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

        $functionContents = "\t\t\$this->" . $this->className['lower'] . " = \$" . $this->className['lower'] . ";\n";
        array_push($functions, ['name' => '__construct', 'content' => $functionContents, 'args' => $this->className['upper'] . " \$" . $this->className['lower']]);
        $functionContents = "\t\treturn \$this->" . $this->className['lower'] . "->all();\n";
        array_push($functions, ['name' => 'all', 'content' => $functionContents]);
        $functionContents = "\t\treturn \$this->" . $this->className['lower'] . "->find(\$id);\n";
        array_push($functions, ['name' => 'find', 'content' => $functionContents, 'args' => "\$id"]);
        $functionContents = "        \$" . $this->className['lower'] . " = new " . $this->className['upper'] . ";\n";
        foreach ($this->propertiesArr as $property) {
            $functionContents .= "        \$" . $this->className['lower'] . "->" . $property . " = \$input['" . $property . "'];\n";
        }
        $functionContents .= "        \$" . $this->className['lower'] . "->save();\n";
        array_push($functions, ['name' => 'store', 'content' => $functionContents, 'args' => "\$input"]);
        $functionContents = "\t\t\$" . $this->className['lower'] . " = \$this->find(\$id);\n";
        foreach ($this->propertiesArr as $property) {
            $functionContents .= "        \$" . $this->className['lower'] . "->" . $property . " = \$input['" . $property . "'];\n";
        }
        $functionContents .= "        \$" . $this->className['lower'] . "->save();\n";
        array_push($functions, ['name' => 'update', 'content' => $functionContents, 'args' => "\$id, \$input"]);
        $functionContents = "\t\t\$this->find(\$id)->delete();\n";
        array_push($functions, ['name' => 'destroy', 'content' => $functionContents, 'args' => "\$id"]);

        $fileContents = $this->createFunctions($functions);

        $fileName = 'app/repositories/Eloquent' . $this->className['upper'] . 'Repository.php';
        $vars = ["private" => $this->className['lower']];
        $extends = ['type' => 'implements', "name"=>$this->className['upper'] . "RepositoryInterface"];

        $this->createClass($fileName, $fileContents, $extends, $vars);

        $this->info($this->className['upper'].'Repository created!');
    }

    /**
     * @return mixed
     */
    private function putRepositoryFolderInStartFiles()
    {
        $content = \File::get('app/start/global.php');
        if (preg_match("/repositories/", $content) !== 1)
            $content = preg_replace("/app_path\(\).'\/controllers',/", "app_path().'/controllers',\n\tapp_path().'/repositories',", $content);

        $content = \File::get('composer.json');
        if (preg_match("/repositories/", $content) !== 1)
            $content = preg_replace("/\"app\/controllers\",/", "\"app/controllers\",\n\t\t\t\"app/repositories\",", $content);

        \File::put('app/start/global.php', $content);
    }

    /**
     * @param $functions
     * @param $fileContents
     */
    private function createFunctions($functions)
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

    /**
     * @return array
     */
    private function createController()
    {
        $fileName = "app/controllers/" . $this->className['upper'] . "Controller.php";

        if ($this->isResource)
            $functionNames = ['constructor' => '__construct', 'index' => 'index', 'create' => 'create', 'store' => 'store', 'show' => 'show', 'edit' => 'edit', 'update' => 'update', 'destroy' => 'destroy'];
        else
            $functionNames = ['constructor' => '__construct', 'index' => 'getIndex', 'create' => 'getCreate', 'store' => 'postIndex', 'show' => 'getDetails', 'edit' => 'getEdit', 'update' => 'putIndex', 'destroy' => 'deleteIndex'];

        $functions = array();

        $functionContents = "\t\t\$this->" . $this->className['lower'] . " = \$" . $this->className['lower'] . ";\n";
        array_push($functions, ['name' => $functionNames['constructor'], 'content' => $functionContents, 'args' => $this->className['upper'] . "RepositoryInterface \$" . $this->className['lower']]);

        $functionContents = "    \t\$" . $this->className['plural'] . " = \$this->" . $this->className['lower'] . "->all();\n";
        $functionContents .= "        \$this->layout->content = \\View::make('" . $this->className['lower'] . ".all', compact('" . $this->className['plural'] . "'));\n";
        array_push($functions, ['name' => $functionNames['index'], 'content' => $functionContents]);

        $functionContents = "        \$this->layout->content = \\View::make('" . $this->className['lower'] . ".new');\n";
        array_push($functions, ['name' => $functionNames['create'], 'content' => $functionContents]);

        $functionContents = "        \$this->" . $this->className['lower'] . "->store(\\Input::only(" . $this->propertiesStr . "));\n";
        $functionContents .= "        return \\Redirect::to('" . $this->className['lower'] . "');\n";
        array_push($functions, ['name' => $functionNames['store'], 'content' => $functionContents]);

        $functionContents = "        \$" . $this->className['lower'] . " = \$this->" . $this->className['lower'] . "->find(\$id);\n";
        $functionContents .= "        \$this->layout->content = \\View::make('" . $this->className['lower'] . ".view')->with('" . $this->className['lower'] . "', \$" . $this->className['lower'] . ");\n";
        $functionContents .= "        //return Response::json(['" . $this->className['lower'] . "' => \$" . $this->className['lower'] . "]);\n";
        array_push($functions, ['name' => $functionNames['show'], 'content' => $functionContents, 'args' => "\$id"]);

        $functionContents = "        \$" . $this->className['lower'] . " = \$this->" . $this->className['lower'] . "->find(\$id);\n";
        $functionContents .= "        \$this->layout->content = \\View::make('" . $this->className['lower'] . ".edit')->with('" . $this->className['lower'] . "', \$" . $this->className['lower'] . ");\n";
        array_push($functions, ['name' => $functionNames['edit'], 'content' => $functionContents, 'args' => "\$id"]);

        $functionContents = "        \$" . $this->className['lower'] . " = \$this->" . $this->className['lower'] . "->update(\$id, \\Input::only([" . $this->propertiesStr . "]));\n";
        $functionContents .= "        return \\Redirect::to('" . $this->className['lower'] . "/'.\$id);\n";
        array_push($functions, ['name' => $functionNames['update'], 'content' => $functionContents, 'args' => "\$id"]);

        $functionContents = "        \$this->" . $this->className['lower'] . "->destroy(\$id);\n";
        array_push($functions, ['name' => $functionNames['destroy'], 'content' => $functionContents, 'args' => "\$id"]);

        $fileContents = $this->createFunctions($functions);

        $this->createClass($fileName, $fileContents, ["name"=>"\\BaseController"], ['protected' => $this->className['lower']]);

        $this->info($this->className['upper'] . 'Controller created!');
    }

    /**
     * @return array
     */
    private function createTestsFile()
    {
        $functions = array();

        $functionContents = "\t\t\$this->call('GET', '" . $this->className['lower'] . "');\n";
        $functionContents .= "\t\t\$this->assertResponseOk();\n";
        array_push($functions, ['name' => 'testIndex', 'content' => $functionContents]);

        $getPath = $this->isResource ? "" : "details/";

        $functionContents = "\t\t\$this->call('GET', '" . $this->className['lower'] . "/" . $getPath . "1');\n";
        $functionContents .= "\t\t\$this->assertResponseOk();\n";
        array_push($functions, ['name' => 'testShow', 'content' => $functionContents]);

        $functionContents = "\t\t\$this->call('GET', '" . $this->className['lower'] . "/create');\n";
        $functionContents .= "\t\t\$this->assertResponseOk();\n";
        array_push($functions, ['name' => 'testCreate', 'content' => $functionContents]);

        $functionContents = "\t\t\$this->call('GET', '" . $this->className['lower'] . "/edit/1');\n";
        $functionContents .= "\t\t\$this->assertResponseOk();\n";
        array_push($functions, ['name' => 'testEdit', 'content' => $functionContents]);

        $fileContents = $this->createFunctions($functions);

        $fileName = "app/tests/controller/" . $this->className['upperPlural'] . "ControllerTest.php";

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

        $namespace = $this->namespace ? $this->namespace . "\\" : "";

        $fileContents = "\nApp::bind('" . $namespace . $this->className['upper'] . "RepositoryInterface','" . $namespace . "Eloquent" . $this->className['upper'] . "Repository');\n";

        $routeType = $this->isResource ? "resource" : "controller";

        $fileContents .= "Route::" . $routeType . "('" . $this->className['lower'] . "', '" . $namespace . $this->className['upper'] . "Controller');\n";

        $content = \File::get($routeFile);
        if (preg_match("/" . $this->className['lower'] . "/", $content) !== 1) {
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
        $dir = "app/views/" . $this->className['lower'] . "/";
        if (!\File::isDirectory($dir))
            \File::makeDirectory($dir);
        $fileName = $dir . "view.blade.php";
        $fileContents = "@section('content')\n";
        $fileContents .= "<div class=\"row\">\n";
        $fileContents .= "    <h1>Viewing " . $this->className['lower'] . "</h1>\n";
        $fileContents .= "    <a class=\"btn\" href=\"{{ url('" . $this->className['lower'] . "/'.\$" . $this->className['lower'] . "->id.'/edit') }}\">Edit</a>\n";
        $fileContents .= "</div>\n";
        $fileContents .= "<div class=\"row\">\n";
        $fileContents .= "    <ul>\n";
        if ($this->propertiesArr) {
            foreach ($this->propertiesArr as $property) {
                $upper = ucfirst($property);
                $fileContents .= "        <li>$upper: {{ \$" . $this->className['lower'] . "->" . $property . " }}</li>";
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
        $fileContents .= "    <h2>Edit " . $this->className['lower'] . "</h2>\n";
        $fileContents .= "</div>\n";
        $fileContents .= "<div class=\"row\">\n";
        $fileContents .= "    <form class=\"form-horizontal\" method=\"POST\" action=\"{{ url('" . $this->className['lower'] . "/'.\$" . $this->className['lower'] . "->id) }}\">\n";
        $fileContents .= "    <input type=\"hidden\" name=\"_method\" value=\"PUT\">\n";
        if ($this->propertiesArr) {
            foreach ($this->propertiesArr as $property) {
                $upper = ucfirst($property);
                $fileContents .= "    <div class=\"form-group\">\n";
                $fileContents .= "        <label class=\"control-label\" for=\"$property\">$upper</label>\n";
                $fileContents .= "        <input type=\"text\" name=\"$property\" id=\"$property\" placeholder=\"$upper\" value=\"{{ \$" . $this->className['lower'] . "->$property }}\">\n";
                $fileContents .= "    </div>\n";
            }
        }
        $fileContents .= "    <div class=\"form-group\">\n";
        $fileContents .= "        <label class=\"control-label\"></label>\n";
        $fileContents .= "        <input class=\"btn\" type=\"reset\" value=\"Reset\">\n";
        $fileContents .= "        <input class=\"btn\" type=\"submit\" value=\"Edit " . $this->className['lower'] . "\">\n";
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
        $fileContents .= "    <h1>All " . $this->className['upperPlural'] . "</h1>\n";
        $fileContents .= "    <a class=\"btn\" href=\"{{ url('".$this->className['lower']."/create') }}\">New</a>\n";
        $fileContents .= "</div>\n";
        $fileContents .= "<div class=\"row\">\n";
        $fileContents .= "<table class=\"table\">\n";
        $fileContents .= "<thead>\n";
        if ($this->propertiesArr) {
            foreach ($this->propertiesArr as $property) {
                $fileContents .= "\t<th>" . ucfirst($property) . "</th>\n";
            }
        }
        $fileContents .= "</thead>\n";
        $fileContents .= "<tbody>\n";
        $fileContents .= "@foreach(\$" . $this->className['plural'] . " as \$" . $this->className['lower'] . ")\n";
        $fileContents .= "\t<tr>\n\t\t";
        if ($this->propertiesArr) {
            foreach ($this->propertiesArr as $property) {
                $fileContents .= "<td><a href=\"{{ url('" . $this->className['lower'] . "/'.\$" . $this->className['lower'] . "->id) }}\">{{ \$" . $this->className['lower'] . "->$property }}</a></td>";
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
        $fileContents .= "    <h2>New " . $this->className['upper'] . "</h2>\n";
        $fileContents .= "</div>\n";
        $fileContents .= "<div class=\"row\">\n";
        $fileContents .= "    <form class=\"form-horizontal\" method=\"POST\" action=\"{{ url('" . $this->className['lower'] . "') }}\">\n";
        if ($this->propertiesArr) {
            foreach ($this->propertiesArr as $property) {
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
        $fileContents .= "        <input class=\"btn\" type=\"submit\" value=\"Add New " . $this->className['lower'] . "\">\n";
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

            curl_exec($ch);
            curl_close($ch);
            fclose($fp);

            $this->fileContents .= "<script src=\"/$type/$assetName.$type\"></script>\n";
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
                $this->downloadAsset("handlebars", "http://builds.emberjs.com/tags/v1.3.2/ember.min.js");
                $this->downloadAsset("angular", "https://ajax.googleapis.com/ajax/libs/angularjs/1.2.12/angular.min.js");
                $this->downloadAsset("ember", "https://raw.github.com/emberjs/ember.js/release-builds/ember-1.0.0-rc.1.min.js");
                $this->downloadAsset("backbone", "http://backbonejs.org/backbone-min.js");

                $this->fileContents .= "<script src=\"/js/main.js\"></script>\n";
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

            $fileReplace = "\t<link href=\"/bootstrap/css/bootstrap.css\" rel=\"stylesheet\">\n";
            $fileReplace .= "\t<style>\n";
            $fileReplace .= "\t\tbody {\n";
            $fileReplace .= "\t\tpadding-top: 60px;\n";
            $fileReplace .= "\t\t}\n";
            $fileReplace .= "\t</style>\n";
            $fileReplace .= "\t<link href=\"/bootstrap/css/bootstrap-responsive.css\" rel=\"stylesheet\">\n";
            $this->fileContents = preg_replace('/<!-- CSS -->/',  $fileReplace, $this->fileContents);
            $this->fileContents .= "<script src=\"/js/bootstrap/bootstrap.js\"></script>\n";
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
                $fileReplace = "\t<link href=\"/css/foundation.min.css\" rel=\"stylesheet\">\n";
                $this->fileContents = preg_replace('/<!-- CSS -->/', $fileReplace, $this->fileContents);
                $this->fileContents .= "<script src=\"/js/foundation.js\"></script>\n";
                $this->info('Foundation successfully set up (v4.0.5)!');
            }
        }
    }
}
