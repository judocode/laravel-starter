<?php namespace Ourlearn\LaravelStarter;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class StartCommand extends Command 
{
    protected $name = 'start';

    protected $description = "Makes table, controller, model, views, seeds, and repository";

    private $fileContents;

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        
        $this->generateLayoutFiles();

        $moreTables = $this->confirm('Do you want to add more tables [y/n]? ', true);

        $isNamespaced = $this->confirm('Do you want to apply namespacing? ');

        $namespace = "";

        if($isNamespaced)
        {
            $namespace = $this->ask('Please provide the full namespace (eg "Illuminate\Foundation"): ');
        }
        
        while( $moreTables )
        {
        	$nameLower = strtolower($this->ask('Model/table name? '));
	        $namePlural = str_plural($nameLower);
	        $nameUpper = ucfirst($nameLower);
	        $nameUpperPlural = str_plural($nameUpper);

            $this->createModel($nameUpper, $namespace);

            $propertiesArr = array();
            $propertiesStr = "";

            $additionalFields = $this->confirm('Do you want more fields in the '.$namePlural.' table other than id [y/n]? ', true);
            
            if( $additionalFields )
            {
                $fieldNames = $this->ask('Please specify the field names in name:type format: ');
                $fieldNames = explode(' ', $fieldNames);
                $file = new GenerateMigration($nameLower, $fieldNames);

                foreach($fieldNames as $field)
                {
                    $pos = strrpos($field, ":");
                    if ($pos !== false) 
                    {
                        $field = substr($field, 0, $pos);
                    }

                    array_push($propertiesArr, $field);
                    $propertiesStr .= "'".$field ."',";
                }

                $propertiesStr = substr($propertiesStr, 0, strlen($propertiesStr)-1);
            }
            else
                $file = new GenerateMigration($nameLower);

            $app = $this->getLaravel();
            $app['composer']->dumpAutoloads();

            $resource = $this->confirm('Do you want resource (y) or restful (n) controllers? ');

            /*******************************************************************
            *
            *                       Run #migrations
            *
            ********************************************************************/
            $editMigrations = $this->confirm('Would you like to edit your migrations file before running it [y/n]? ', true);
            if( $editMigrations )
            {
                $this->error('Remember to run "php artisan migrate" after editing your migration file');
            }
            else
            {
                while(true)
                {
                    try {
                        $this->call('migrate');
                        break;
                    } 
                    catch (\Exception $e)
                    {
                        $this->info('Error: ' . $e->getMessage());
                        $this->error('This table already exists and/or you have duplicate migration files.');
                        $this->confirm('Fix the error and enter "yes" ', true);
                    }
                }
            }

            /*******************************************************************
            *
            *                         Create #seeds
            *
            ********************************************************************/
            $fileName = "app/database/seeds/" . $nameUpperPlural . "TableSeeder.php";
            $fileContents = "<?php\n\n";
            $fileContents .= "class ". $nameUpperPlural ."TableSeeder extends Seeder {\n";
            $fileContents .= "\tpublic function run()\n";
            $fileContents .= "\t{\n";
            $fileContents .= "\t\t\$$namePlural = array(\n";
            if($propertiesArr)
            {
                foreach($propertiesArr as $property)
                {
                    $fileContents .= "\t\t\t'$property' => 'Testing $nameLower $property',\n";
                }
            }
            $fileContents .= "\t\t);\n";
            $fileContents .= "\t\tDB::table('$namePlural')->insert(\$$namePlural);\n";
            $fileContents .= "\t}\n";
            $fileContents .= "}\n";
            $this->fileExists($fileName, $fileContents);

            $databaseSeederPath = app_path() . '/database/seeds/DatabaseSeeder.php';
            $tableSeederClassName = $nameUpperPlural . 'TableSeeder';

            $content = \File::get($databaseSeederPath);
            if(preg_match("/$tableSeederClassName/", $content) !== 1)
            {
                $content = preg_replace("/(run\(\).+?)}/us", "$1\t\$this->call('{$tableSeederClassName}');\n\t}", $content);
                \File::put($databaseSeederPath, $content);
            }

            $this->info('Database seed file created!');

            //$app['composer']->dumpAutoloads();
            //$this->call('db:seed');

            /*******************************************************************
            *
            *                       Create #repository
            *
            ********************************************************************/
            $repositoryDir = "app/repositories";
            if(!\File::isDirectory($repositoryDir))
                \File::makeDirectory($repositoryDir);

            $fileName = "app/repositories/" . $nameUpper . "RepositoryInterface.php";
            if($isNamespaced)
            {
                $fileContents = "<?php namespace $namespace;\n\n";
            }
            else
            {
                $fileContents = "<?php\n\n";
            }

            $fileContents .= "interface ".$nameUpper."RepositoryInterface {\n";
            $fileContents .= "\tpublic function all();\n";
            $fileContents .= "\tpublic function find(\$id);\n";
            $fileContents .= "\tpublic function store(\$input);\n";
            $fileContents .= "\tpublic function update(\$id, \$input);\n";
            $fileContents .= "\tpublic function destroy(\$id);\n";
            $fileContents .= "}\n";
            $this->fileExists($fileName, $fileContents);


            if($isNamespaced)
            {
                $fileContents = "<?php namespace $namespace;\n\n";
            }
            else
            {
                $fileContents = "<?php\n\n";
            }
            $fileContents .= "class Eloquent".$nameUpper."Repository implements ".$nameUpper."RepositoryInterface\n";
            $fileContents .= "{\n";
            $fileContents .= "\tprivate \$$nameLower;\n\n";
            $fileContents .= "\tpublic function __construct($nameUpper \$$nameLower)\n";
            $fileContents .= "\t{\n";
            $fileContents .= "\t\t\$this->$nameLower = \$$nameLower;\n";
            $fileContents .= "\t}\n\n";
            $fileContents .= "\tpublic function all()\n";
            $fileContents .= "\t{\n";
            $fileContents .= "\t\treturn \$this->".$nameLower."->all();\n";
            $fileContents .= "\t}\n\n";
            $fileContents .= "\tpublic function find(\$id)\n";
            $fileContents .= "\t{\n";
            $fileContents .= "\t\treturn \$this->".$nameLower."->find(\$id);\n";
            $fileContents .= "\t}\n\n";
            $fileContents .= "\tpublic function store(\$input)\n";
            $fileContents .= "\t{\n";
            $fileContents .= "        \$$nameLower = new $nameUpper;\n";
            if($propertiesArr)
            {
                foreach($propertiesArr as $property)
                {
                    $fileContents .= "        \$".$nameLower."->".$property." = \$input['".$property."'];\n";
                }
            }
            $fileContents .= "        \$".$nameLower."->save();\n";
            $fileContents .= "\t}\n\n";
            $fileContents .= "\tpublic function update(\$id, \$input)\n";
            $fileContents .= "\t{\n";
            $fileContents .= "\t\t\$$nameLower = \$this->find(\$id);\n";
            if($propertiesArr)
            {
                foreach($propertiesArr as $property)
                {
                    $fileContents .= "        \$".$nameLower."->".$property." = \$input['".$property."'];\n";
                }
            }
            $fileContents .= "        \$".$nameLower."->save();\n";
            $fileContents .= "\t}\n\n";
            $fileContents .= "\tpublic function destroy(\$id)\n";
            $fileContents .= "\t{\n";
            $fileContents .= "\t\t\$this->find(\$id)->delete();\n";
            $fileContents .= "\t}\n";
            $fileContents .= "}\n";

            $this->fileExists('app/repositories/Eloquent'.$nameUpper.'Repository.php', $fileContents);

            $content = \File::get('app/start/global.php');
            if(preg_match("/repositories/", $content) !== 1)
                $content = preg_replace("/app_path\(\).'\/controllers',/", "app_path().'/controllers',\n\tapp_path().'/repositories',", $content);

            \File::put('app/start/global.php', $content);

            $this->info($nameUpper.'Repository created!');


            /*******************************************************************
            *
            *                       Create controller
            *
            ********************************************************************/
            $fileName = "app/controllers/" . $nameUpper . "Controller.php";
            if($isNamespaced)
            {
                $fileContents = "<?php namespace $namespace;\n\n";
            }
            else
            {
                $fileContents = "<?php\n\n";
            }
            $fileContents .= "class ".$nameUpper."Controller extends BaseController\n";
            $fileContents .= "{\n";
            $fileContents .= "\tprotected \$$nameLower;\n\n";
            $fileContents .= "\tfunction __construct(".$nameUpper."RepositoryInterface \$$nameLower)\n";
            $fileContents .= "\t{\n";            
            $fileContents .= "\t\t\$this->$nameLower = \$$nameLower;\n";
            $fileContents .= "\t}\n\n";
            if($resource)
            {
                $fileContents .= "    public function index()\n";
                $fileContents .= "    {\n";
                $fileContents .= "    \t\$$namePlural = \$this->".$nameLower."->all();\n";
                $fileContents .= "        \$this->layout->content = View::make('$nameLower.all', compact('$namePlural'));\n";
                $fileContents .= "    }\n\n";
            }
            else
            {
                $fileContents .= "    public function getIndex(\$id = 0)\n";
                $fileContents .= "    {\n";
                $fileContents .= "        if(\$id != 0)\n";
                $fileContents .= "        {\n";
                $fileContents .= "            \$$nameLower = \$this->".$nameLower."->find(\$id);\n";
                $fileContents .= "            \$this->layout->content = View::make('$nameLower.view')->with('$nameLower', \$$nameLower);\n";
                $fileContents .= "            //return Response::json(['$nameLower' => \$$nameLower]);\n";
                $fileContents .= "        }\n";
                $fileContents .= "        else\n";
                $fileContents .= "        {\n";
                $fileContents .= "        \t\$$namePlural = \$this->".$nameLower."->all();\n";
                $fileContents .= "        \t\$this->layout->content = View::make('$nameLower.all', compact('$namePlural'));\n";
                $fileContents .= "        }\n";
                $fileContents .= "    }\n\n";
            }

            if($resource)
            {
                $fileContents .= "    public function create()\n";
            }
            else
            {
                $fileContents .= "    public function getCreate()\n";
            }
            $fileContents .= "    {\n";
            $fileContents .= "        \$this->layout->content = View::make('$nameLower.new');\n";
            $fileContents .= "    }\n\n";
            if($resource)
            {
                $fileContents .= "    public function store()\n";
            }
            else
            {
                $fileContents .= "    public function postIndex()\n";
            }
            $fileContents .= "    {\n";
            $fileContents .= "        \$this->".$nameLower."->store(Input::only(".$propertiesStr."));\n";

            $fileContents .= "        return Redirect::to('$nameLower');\n";
            $fileContents .= "    }\n\n";
            if($resource)
            {
                $fileContents .= "    public function show( \$id )\n";
                $fileContents .= "    {\n";
                $fileContents .= "        \$$nameLower = \$this->".$nameLower."->find(\$id);\n";
                $fileContents .= "        \$this->layout->content = View::make('$nameLower.view')->with('$nameLower', \$$nameLower);\n";
                $fileContents .= "        //return Response::json(['$nameLower' => \$$nameLower]);\n";
                $fileContents .= "    }\n\n";
            }

            if($resource)
            {
                $fileContents .= "    public function edit( \$id )\n";
            }
            else
            {
                $fileContents .= "    public function getEdit( \$id )\n";
            }
            $fileContents .= "    {\n";
            $fileContents .= "        \$$nameLower = \$this->".$nameLower."->find(\$id);\n";
            $fileContents .= "        \$this->layout->content = View::make('$nameLower.edit')->with('$nameLower', \$$nameLower);\n";
            $fileContents .= "    }\n\n";
            if($resource)
            {
                $fileContents .= "    public function update( \$id )\n";
            }
            else
            {
                $fileContents .= "    public function putIndex( \$id )\n";
            }
            $fileContents .= "    {\n";
            $fileContents .= "        \$$nameLower = \$this->".$nameLower."->update(\$id, Input::only([".$propertiesStr."]));\n";
            $fileContents .= "        return Redirect::to('$nameLower/'.\$id);\n";
            $fileContents .= "    }\n\n";
            if($resource)
            {
                $fileContents .= "    public function destroy( \$id )\n";
            }
            else
            {
                $fileContents .= "    public function deleteIndex( \$id )\n";
            }
            $fileContents .= "    {\n";
            $fileContents .= "        \$this->".$nameLower."->destroy(\$id);\n";
            $fileContents .= "    }\n";
            $fileContents .= "}\n";
            $this->fileExists($fileName, $fileContents);
            $this->info($nameUpper.'Controller created!');

            /*******************************************************************
            *
            *                   Create views - view.blade.php
            *
            ********************************************************************/
            $dir = "app/views/" . $nameLower ."/";
            if(!\File::isDirectory($dir))
                \File::makeDirectory($dir);
            $fileName = $dir . "view.blade.php";
            $fileContents = "@section('content')\n";
            $fileContents .= "<div class=\"row\">\n";
            $fileContents .= "    <h1>Viewing $nameLower</h1>\n";
            $fileContents .= "    <a class=\"btn\" href=\"{{ url('$nameLower/'.\$".$nameLower."->id.'/edit') }}\">Edit</a>\n";
            $fileContents .= "</div>\n";
            $fileContents .= "<div class=\"container\">\n";
            $fileContents .= "    <ul>\n";
            if($propertiesArr)
            {
                foreach($propertiesArr as $property)
                {
                    $upper = ucfirst($property);
                    $fileContents .= "        <li>$upper: {{ \$$nameLower->".$property." }}</li>";
                }
            }
            $fileContents .= "    <ul>\n";
            $fileContents .= "</div>\n";
            $fileContents .= "@stop\n";     
            $this->fileExists($fileName, $fileContents);

            /*******************************************************************
            *
            *                  Create views - edit.blade.php
            *
            ********************************************************************/
            $fileName = $dir . "edit.blade.php";
            $fileContents = "@section('content')\n";
            $fileContents .= "<div class=\"row\">\n";
            $fileContents .= "    <h2>Edit $nameLower</h2>\n";
            $fileContents .= "</div>\n";
            $fileContents .= "<div class=\"container\">\n";
            $fileContents .= "    <form class=\"form-horizontal\" method=\"POST\" action=\"{{ url('$nameLower/'.\$".$nameLower."->id) }}\">\n";
            $fileContents .= "    <input type=\"hidden\" name=\"_method\" value=\"PUT\">\n";
            if($propertiesArr)
            {
                foreach($propertiesArr as $property)
                {
                    $upper = ucfirst($property);
                    $fileContents .= "    <div class=\"form-group\">\n";
                    $fileContents .= "        <label class=\"control-label\" for=\"$property\">$upper</label>\n";
                    $fileContents .= "        <input type=\"text\" name=\"$property\" id=\"$property\" placeholder=\"$upper\" value=\"{{ \$".$nameLower."->$property }}\">\n";
                    $fileContents .= "    </div>\n";
                }
            }
            $fileContents .= "    <div class=\"form-group\">\n";
            $fileContents .= "        <label class=\"control-label\"></label>\n";
            $fileContents .= "        <input class=\"btn\" type=\"reset\" value=\"Reset\">\n";
            $fileContents .= "        <input class=\"btn\" type=\"submit\" value=\"Edit $nameLower\">\n";
            $fileContents .= "    </div>\n"; 
            $fileContents .= "    </form>\n"; 
            $fileContents .= "</div>\n";
            $fileContents .= "@stop\n";
            $this->fileExists($fileName, $fileContents);

            /*******************************************************************
            *
            *                  Create views - all.blade.php
            *
            ********************************************************************/
            $fileName = $dir . "all.blade.php";
            $fileContents = "@section('content')\n";
            $fileContents .= "<div class=\"row\">\n";
            $fileContents .= "    <h1>All $nameUpperPlural</h1>\n";
            $fileContents .= "    <a class=\"btn\" href=\"{{ url('$nameLower/create') }}\">New</a>\n";
            $fileContents .= "</div>\n";
            $fileContents .= "<div class=\"container\">\n";
            $fileContents .= "<table class=\"table\">\n";
            $fileContents .= "<thead>\n";
            if($propertiesArr)
            {
                foreach($propertiesArr as $property)
                {
                    $fileContents .= "\t<th>".ucfirst($property)."</th>\n";
                }
            }
            $fileContents .= "</thead>\n";
            $fileContents .= "<tbody>\n";
            $fileContents .= "@foreach(\$$namePlural as \$$nameLower)\n";
            $fileContents .= "\t<tr>\n\t\t";
            if($propertiesArr)
            {
                foreach($propertiesArr as $property)
                {
                    $fileContents .= "<td><a href=\"{{ url('$nameLower"."/'.\$".$nameLower."->id) }}\">{{ \$".$nameLower."->$property }}</a></td>";
                }
            }
            $fileContents .= "\n\t</tr>\n";
            $fileContents .= "@endforeach\n";
            $fileContents .= "</tbody>\n";
            $fileContents .= "</table>\n";
            $fileContents .= "</div>\n";
            $fileContents .= "@stop\n";     
            $this->fileExists($fileName, $fileContents);

            /*******************************************************************
            *
            *                   Create views - new.blade.php
            *
            ********************************************************************/
            $fileName = $dir . "new.blade.php";
            $fileContents = "@section('content')\n";
            $fileContents .= "<div class=\"row\">\n";
            $fileContents .= "    <h2>New $nameUpper</h2>\n";
            $fileContents .= "</div>\n";
            $fileContents .= "<div class=\"container\">\n";
            $fileContents .= "    <form class=\"form-horizontal\" method=\"POST\" action=\"{{ url('$nameLower') }}\">\n";
            if($propertiesArr)
            {
                foreach($propertiesArr as $property)
                {
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
            $fileContents .= "        <input class=\"btn\" type=\"submit\" value=\"Add New $nameLower\">\n";
            $fileContents .= "    </div>\n"; 
            $fileContents .= "</div>\n";
            $fileContents .= "@stop\n";     
            $this->fileExists($fileName, $fileContents);
            $this->info('Views created!');

            /*******************************************************************
            *
            *                       Update routes file
            *
            ********************************************************************/
            $routeFile = "app/routes.php";
            $fileContents = "\nApp::bind('".$nameUpper."RepositoryInterface','Eloquent".$nameUpper."Repository');\n";
            if($resource)
            {
                $fileContents .= "Route::resource('".$nameLower."', '".$nameUpper. "Controller');\n";
            }
            else
            {
                $fileContents .= "Route::controller('".$nameLower."', '".$nameUpper. "Controller');\n";
            }

            $content = \File::get($routeFile);
            if(preg_match("/$nameLower/", $content) !== 1)
            {
                \File::append($routeFile, $fileContents);
            }
            
            $this->info('Routes file updated!');

            /*******************************************************************
            *
            *                       Create tests file
            *
            ********************************************************************/
            $testDir = 'app/tests/controller';
            if(!\File::isDirectory($testDir))
                    \File::makeDirectory($testDir);
            $fileName = $testDir."/".$nameUpperPlural."ControllerTest.php";
            $fileContents = "<?php\n\n";
            $fileContents .= "class ". $nameUpperPlural ."ControllerTest extends TestCase {\n";
            $fileContents .= "\tpublic function testIndex()\n";
            $fileContents .= "\t{\n";
            $fileContents .= "\t    \$response = \$this->call('GET', '$nameLower');\n";
            $fileContents .= "\t    \$this->assertTrue(\$response->isOk());\n";
            $fileContents .= "\t}\n\n";
            $fileContents .= "\tpublic function testShow()\n";
            $fileContents .= "\t{\n";
            $fileContents .= "\t    \$response = \$this->call('GET', '$nameLower/1');\n";
            $fileContents .= "\t    \$this->assertTrue(\$response->isOk());\n";
            $fileContents .= "\t}\n\n";
            $fileContents .= "\tpublic function testCreate()\n";
            $fileContents .= "\t{\n";
            $fileContents .= "\t    \$response = \$this->call('GET', '$nameLower/create');\n";
            $fileContents .= "\t    \$this->assertTrue(\$response->isOk());\n";
            $fileContents .= "\t}\n\n";
            $fileContents .= "\tpublic function testEdit()\n";
            $fileContents .= "\t{\n";
            $fileContents .= "\t    \$response = \$this->call('GET', '$nameLower/1/edit');\n";
            $fileContents .= "\t    \$this->assertTrue(\$response->isOk());\n";
            $fileContents .= "\t}\n";
            $fileContents .= "}\n";
            $this->fileExists($fileName, $fileContents);

            $this->info('Tests created!');

            $moreTables = $this->confirm('Do you want to add more tables [y/n]? ', true);
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
    private function createModel($nameUpper, $namespace)
    {
        $fileName = "app/models/" . $nameUpper . ".php";
        if($namespace)
        {
            $fileContents = "<?php namespace $namespace;\n\n";
        }
        else
        {
            $fileContents = "<?php\n\n";
        }
        $fileContents .= "use LaravelBook\Ardent\Ardent;\n\n";
        $fileContents .= "class " . $nameUpper . " extends Ardent {\n\n";
        $fileContents .= "\tpublic static \$rules = array(\n";
        $fileContents .= "\t);\n";
        $fileContents .= "}\n";
        $this->fileExists($fileName, $fileContents);

        $this->info('Model "'.$nameUpper.'" created!');
    }

    /*
    *   Checks if file exists, and then prompts to overwrite
    */
    private function fileExists($fileName, $fileContents)
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
                $this->fileContents .= "\t@yield('content')\n";

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
