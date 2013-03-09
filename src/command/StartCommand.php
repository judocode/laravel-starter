<?php namespace Ourlearn\LaravelStarter;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class StartCommand extends Command 
{
    protected $name = 'start';

    protected $description = "Makes table, controller, model, views, seeds, and repository";

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        
        $this->generateLayoutFiles();

        $moreTables = $this->confirm('Do you want to add more tables [y/n]? ', true);
        while( $moreTables )
        {
            // Get the name of the model
            $name = strtolower($this->ask('Model/table name? '));
            $namePlural = str_plural($name);
            $nameUpper = ucfirst($name);
            $nameUpperPlural = str_plural($nameUpper);

            $this->createModel($nameUpper);

            $propertiesArr = array();
            $propertiesStr = "";

            $additionalFields = $this->confirm('Do you want more fields in the '.$namePlural.' table other than id [y/n]? ', true);
            if( $additionalFields )
            {
                $fieldNames = $this->ask('Please specify the field names in name:type format: ');
                $fieldNames = explode(' ', $fieldNames);
                $file = new GenerateMigration($name, $fieldNames);

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
                $file = new GenerateMigration($name);

            $app = $this->getLaravel();
            $app['composer']->dumpAutoloads();

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
                    $fileContents .= "\t\t\t'$property' => 'Testing $name $property',\n";
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
            $fileContents = "<?php\n\n";
            $fileContents .= "interface ".$nameUpper."RepositoryInterface {\n";
            $fileContents .= "\tpublic function all();\n";
            $fileContents .= "\tpublic function find(\$id);\n";
            $fileContents .= "\tpublic function store(\$input);\n";
            $fileContents .= "\tpublic function update(\$id, \$input);\n";
            $fileContents .= "\tpublic function destroy(\$id);\n";
            $fileContents .= "}\n";
            $this->fileExists($fileName, $fileContents);

            $fileContents = "<?php\n\n";
            $fileContents .= "class ".$nameUpper."Repository implements ".$nameUpper."RepositoryInterface\n";
            $fileContents .= "{\n";
            $fileContents .= "\tpublic function all()\n";
            $fileContents .= "\t{\n";
            $fileContents .= "\t\treturn ".$nameUpper."::all();\n";
            $fileContents .= "\t}\n\n";
            $fileContents .= "\tpublic function find(\$id)\n";
            $fileContents .= "\t{\n";
            $fileContents .= "\t\treturn ".$nameUpper."::find(\$id);\n";
            $fileContents .= "\t}\n\n";
            $fileContents .= "\tpublic function store(\$input)\n";
            $fileContents .= "\t{\n";
            $fileContents .= "        \$$name = new $nameUpper;\n";
            if($propertiesArr)
            {
                foreach($propertiesArr as $property)
                {
                    $fileContents .= "        \$".$name."->".$property." = \$input['".$property."'];\n";
                }
            }
            $fileContents .= "        \$".$name."->save();\n";
            $fileContents .= "\t}\n\n";
            $fileContents .= "\tpublic function update(\$id, \$input)\n";
            $fileContents .= "\t{\n";
            $fileContents .= "\t\t\$$name = \$this->find(\$id);\n";
            if($propertiesArr)
            {
                foreach($propertiesArr as $property)
                {
                    $fileContents .= "        \$".$name."->".$property." = \$input['".$property."'];\n";
                }
            }
            $fileContents .= "        \$".$name."->save();\n";
            $fileContents .= "\t}\n\n";
            $fileContents .= "\tpublic function destroy(\$id)\n";
            $fileContents .= "\t{\n";
            $fileContents .= "\t\t\$this->find(\$id)->delete();\n";
            $fileContents .= "\t}\n";
            $fileContents .= "}\n";

            $this->fileExists('app/repositories/'.$nameUpper.'Repository.php', $fileContents);

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
            $fileName = "app/controllers/" . $nameUpperPlural . "Controller.php";
            $fileContents = "<?php\n\n";
            $fileContents .= "class ".$nameUpperPlural."Controller extends BaseController\n";
            $fileContents .= "{\n";
            $fileContents .= "\tprotected \$$namePlural;\n\n";
            $fileContents .= "\tfunction __construct(".$nameUpper."RepositoryInterface \$$namePlural)\n";
            $fileContents .= "\t{\n";            
            $fileContents .= "\t\t\$this->$namePlural = \$$namePlural;\n";
            $fileContents .= "\t}\n\n";
            $fileContents .= "    public function index()\n";
            $fileContents .= "    {\n";
            $fileContents .= "    \t\$$namePlural = \$this->".$namePlural."->all();\n";
            $fileContents .= "        \$this->layout->content = View::make('$namePlural.all', compact('$namePlural'));\n";
            $fileContents .= "    }\n\n";
            $fileContents .= "    public function create()\n";
            $fileContents .= "    {\n";
            $fileContents .= "        \$this->layout->content = View::make('$namePlural.new');\n";
            $fileContents .= "    }\n\n";
            $fileContents .= "    public function store()\n";
            $fileContents .= "    {\n";
            $fileContents .= "        \$this->".$namePlural."->store(Input::only([".$propertiesStr."]));\n";

            $fileContents .= "        return Redirect::to('$namePlural');\n";
            $fileContents .= "    }\n\n";
            $fileContents .= "    public function show( \$id )\n";
            $fileContents .= "    {\n";
            $fileContents .= "        \$$name = \$this->".$namePlural."->find(\$id);\n";
            $fileContents .= "        \$this->layout->content = View::make('$namePlural.view')->with('$name', \$$name);\n";
            $fileContents .= "        //return Response::json(['$name' => \$$name]);\n";
            $fileContents .= "    }\n\n";
            $fileContents .= "    public function edit( \$id )\n";
            $fileContents .= "    {\n";
            $fileContents .= "        \$$name = \$this->".$namePlural."->find(\$id);\n";
            $fileContents .= "        \$this->layout->content = View::make('$namePlural.edit')->with('$name', \$$name);\n";
            $fileContents .= "    }\n\n";
            $fileContents .= "    public function update( \$id )\n";
            $fileContents .= "    {\n";
            $fileContents .= "        \$$name = \$this->".$namePlural."->update(\$id, Input::only([".$propertiesStr."]));\n";
            $fileContents .= "        return Redirect::to('$namePlural/'.\$id);\n";
            $fileContents .= "    }\n\n";
            $fileContents .= "    public function destroy( \$id )\n";
            $fileContents .= "    {\n";
            $fileContents .= "        \$this->".$namePlural."->destroy(\$id);\n";
            $fileContents .= "    }\n";
            $fileContents .= "}\n";
            $this->fileExists($fileName, $fileContents);
            $this->info($nameUpper.'Controller created!');

            /*******************************************************************
            *
            *                   Create views - view.blade.php
            *
            ********************************************************************/
            $dir = "app/views/" . $namePlural ."/";
            if(!\File::isDirectory($dir))
                \File::makeDirectory($dir);
            $fileName = $dir . "view.blade.php";
            $fileContents = "@section('content')\n";
            $fileContents .= "<div class=\"toolbar\">\n";
            $fileContents .= "    <h1>Viewing $name</h1>\n";
            $fileContents .= "    <a class=\"btn\" href=\"{{ url('$namePlural/'.\$".$name."->id.'/edit') }}\">Edit</a>\n";
            $fileContents .= "</div>\n";
            $fileContents .= "<div class=\"inner-main-content\">\n";
            $fileContents .= "    <ul>\n";
            if($propertiesArr)
            {
                foreach($propertiesArr as $property)
                {
                    $upper = ucfirst($property);
                    $fileContents .= "        <li>$upper: {{ \$$name->".$property." }}</li>";
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
            $fileContents .= "<div class=\"toolbar\">\n";
            $fileContents .= "    <h2>Edit $name</h2>\n";
            $fileContents .= "</div>\n";
            $fileContents .= "<div class=\"inner-main-content\">\n";
            $fileContents .= "    <form class=\"form-horizontal\" method=\"POST\" action=\"{{ url('$namePlural/'.\$".$name."->id) }}\">\n";
            $fileContents .= "    <input type=\"hidden\" name=\"_method\" value=\"PUT\">\n";
            if($propertiesArr)
            {
                foreach($propertiesArr as $property)
                {
                    $upper = ucfirst($property);
                    $fileContents .= "    <div class=\"control-group\">\n";
                    $fileContents .= "        <label class=\"control-label\" for=\"$property\">$upper</label>\n";
                    $fileContents .= "        <div class=\"controls\">\n";
                    $fileContents .= "            <input type=\"text\" name=\"$property\" id=\"$property\" placeholder=\"$upper\" value=\"{{ \$".$name."->$property }}\">\n";
                    $fileContents .= "        </div>\n";
                    $fileContents .= "    </div>\n";
                }
            }
            $fileContents .= "    <div class=\"control-group\">\n";
            $fileContents .= "        <label class=\"control-label\"></label>\n";
            $fileContents .= "        <div class=\"controls\">\n";
            $fileContents .= "            <input class=\"btn\" type=\"reset\" value=\"Reset\">\n";
            $fileContents .= "            <input class=\"btn\" type=\"submit\" value=\"Edit $name\">\n";
            $fileContents .= "        </div>\n";
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
            $fileContents .= "<div class=\"toolbar\">\n";
            $fileContents .= "    <h1>All $nameUpperPlural</h1>\n";
            $fileContents .= "    <a class=\"btn\" href=\"{{ url('$namePlural/create') }}\">New</a>\n";
            $fileContents .= "</div>\n";
            $fileContents .= "<div class=\"inner-main-content\">\n";
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
            $fileContents .= "@foreach(\$$namePlural as \$$name)\n";
            $fileContents .= "\t<tr>\n\t\t";
            if($propertiesArr)
            {
                foreach($propertiesArr as $property)
                {
                    $fileContents .= "<td><a href=\"{{ url('$namePlural"."/'.\$".$name."->id) }}\">{{ \$".$name."->$property }}</a></td>";
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
            $fileContents .= "<div class=\"toolbar\">\n";
            $fileContents .= "    <h2>New $nameUpper</h2>\n";
            $fileContents .= "</div>\n";
            $fileContents .= "<div class=\"inner-main-content\">\n";
            $fileContents .= "    <form class=\"form-horizontal\" method=\"POST\" action=\"{{ url('$namePlural') }}\">\n";
            if($propertiesArr)
            {
                foreach($propertiesArr as $property)
                {
                    $upper = ucfirst($property);
                    $fileContents .= "    <div class=\"control-group\">\n";
                    $fileContents .= "        <label class=\"control-label\" for=\"$property\">$upper</label>\n";
                    $fileContents .= "        <div class=\"controls\">\n";
                    $fileContents .= "            <input type=\"text\" name=\"$property\" id=\"$property\" placeholder=\"$upper\">\n";
                    $fileContents .= "        </div>\n";
                    $fileContents .= "    </div>\n";
                }
            }
            $fileContents .= "    <div class=\"control-group\">\n";
            $fileContents .= "        <label class=\"control-label\"></label>\n";
            $fileContents .= "        <div class=\"controls\">\n";
            $fileContents .= "            <input class=\"btn\" type=\"reset\" value=\"Reset\">\n";
            $fileContents .= "            <input class=\"btn\" type=\"submit\" value=\"Add New $name\">\n";
            $fileContents .= "        </div>\n";
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
            $fileContents = "\nApp::bind('".$nameUpper."RepositoryInterface','".$nameUpper."Repository');\n";
            $fileContents .= "Route::resource('".$namePlural."', '".$nameUpperPlural. "Controller');\n";
            $content = \File::get($routeFile);
            if(preg_match("/$name/", $content) !== 1)
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
            $fileContents .= "\t    \$response = \$this->call('GET', '$namePlural');\n";
            $fileContents .= "\t    \$this->assertTrue(\$response->isOk());\n";
            $fileContents .= "\t}\n\n";
            $fileContents .= "\tpublic function testShow()\n";
            $fileContents .= "\t{\n";
            $fileContents .= "\t    \$response = \$this->call('GET', '$namePlural/1');\n";
            $fileContents .= "\t    \$this->assertTrue(\$response->isOk());\n";
            $fileContents .= "\t}\n\n";
            $fileContents .= "\tpublic function testCreate()\n";
            $fileContents .= "\t{\n";
            $fileContents .= "\t    \$response = \$this->call('GET', '$namePlural/create');\n";
            $fileContents .= "\t    \$this->assertTrue(\$response->isOk());\n";
            $fileContents .= "\t}\n\n";
            $fileContents .= "\tpublic function testEdit()\n";
            $fileContents .= "\t{\n";
            $fileContents .= "\t    \$response = \$this->call('GET', '$namePlural/1/edit');\n";
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
    *   @nameUpper model name
    */
    private function createModel($nameUpper)
    {
        $fileName = "app/models/" . $nameUpper . ".php";
        $fileContents = "<?php\n\n";
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
                $fileContents = "<!DOCTYPE html>\n";
                $fileContents .= "<html lang=\"en\">\n";
                $fileContents .= "<head>\n";
                $fileContents .= "\t<meta charset=\"utf-8\">\n";
                $fileContents .= "\t<meta name=\"description\" content=\"\">\n";
                $fileContents .= "\t<meta name=\"author\" content=\"\">\n";
                $fileContents .= "\t<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
                $fileContents .= "\t<title>Untitled</title>\n";
                $fileContents .= "<!-- CSS -->\n";
                $fileContents .= "\t<link rel=\"stylesheet\" href=\"{{ url('css/style.css') }}\">\n";
                $fileContents .= "</head>\n";
                $fileContents .= "<body>\n";
                $fileContents .= "\t@yield('content')\n";

                $jquery = $this->confirm('Do you want jquery [y/n]? ', true);
                if( $jquery )
                {
                    $fileContents .= "<script src=\"{{ url('js/jquery.js') }}\"></script>\n";
                    $ch = curl_init("http://code.jquery.com/jquery-1.9.1.min.js");
                    $fp = fopen("public/js/jquery.js", "w");

                    curl_setopt($ch, CURLOPT_FILE, $fp);
                    curl_setopt($ch, CURLOPT_HEADER, 0);

                    curl_exec($ch);
                    curl_close($ch);
                    fclose($fp);
                    $this->info("public/js/jquery.js (v1.9.1) created!");
                }

                $bootstrap = $this->confirm('Do you want twitter bootstrap [y/n]? ', true);
                if( $bootstrap )
                {
                    $ch = curl_init("http://twitter.github.com/bootstrap/assets/bootstrap.zip");
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

                    $fileReplace = "\t<link href=\"{{ url('bootstrap/css/bootstrap.css') }}\" rel=\"stylesheet\">\n";
                    $fileReplace .= "\t<style>\n";
                    $fileReplace .= "\t\tbody {\n";
                    $fileReplace .= "\t\tpadding-top: 60px;\n";
                    $fileReplace .= "\t\t}\n";
                    $fileReplace .= "\t</style>\n";
                    $fileReplace .= "\t<link href=\"{{ url('bootstrap/css/bootstrap-responsive.css') }}\" rel=\"stylesheet\">\n";
                    $fileContents = preg_replace('/<!-- CSS -->/',  $fileReplace, $fileContents);
                    $fileContents .= "<script src=\"{{ url('js/bootstrap/bootstrap.js') }}\"></script>\n";
                    $this->info("Bootstrap files loaded to public/bootstrap!");
                }
                else
                {
                    $foundation = $this->confirm('Do you want foundation [y/n]? ', true);
                    if( $foundation )
                    {
                        $ch = curl_init("http://foundation.zurb.com/files/foundation-4.0.4.zip");
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
                        $fileReplace = "\t<link href=\"{{ url('css/foundation.min.css') }}\" rel=\"stylesheet\">\n";
                        $fileContents = preg_replace('/<!-- CSS -->/', $fileReplace, $fileContents);
                        $fileContents .= "<script src=\"{{ url('js/foundation.js') }}\"></script>\n";
                        $this->info('Foundation successfully set up (v4.0.4)!');
                    }
                }

                $underscore = $this->confirm('Do you want underscore [y/n]? ', true);
                if( $underscore )
                {
                    $ch = curl_init("http://underscorejs.org/underscore-min.js");
                    $fp = fopen("public/js/underscore.js", "w");

                    curl_setopt($ch, CURLOPT_FILE, $fp);
                    curl_setopt($ch, CURLOPT_HEADER, 0);

                    curl_exec($ch);
                    curl_close($ch);
                    fclose($fp);
                    $fileContents .= "<script src=\"{{ url('js/underscore.js') }}\"></script>\n";
                    $this->info("public/js/underscore.js created!");
                }

                $angular = $this->confirm('Do you want angular [y/n]? ', true);
                if( $angular )
                {
                    $ch = curl_init("https://ajax.googleapis.com/ajax/libs/angularjs/1.0.5/angular.min.js");
                    $fp = fopen("public/js/angular.js", "w");

                    curl_setopt($ch, CURLOPT_FILE, $fp);
                    curl_setopt($ch, CURLOPT_HEADER, 0);

                    curl_exec($ch);
                    curl_close($ch);
                    fclose($fp);
                    $fileContents .= "<script src=\"{{ url('js/angular.js') }}\"></script>\n";
                    $this->info("public/js/angular.js (v1.0.5) created!");
                }
                else
                {
                    $ember = $this->confirm('Do you want ember [y/n]? ', true);
                    if( $ember )
                    {
                        $ch = curl_init("https://raw.github.com/emberjs/ember.js/release-builds/ember-1.0.0-rc.1.min.js");
                        $fp = fopen("public/js/ember.js", "w");

                        curl_setopt($ch, CURLOPT_FILE, $fp);
                        curl_setopt($ch, CURLOPT_HEADER, 0);

                        curl_exec($ch);
                        curl_close($ch);
                        fclose($fp);
                        $fileContents .= "<script src=\"{{ url('js/ember.js') }}\"></script>\n";
                        $this->info("public/js/ember.js (v1.0.0-rc1) created!");
                    }
                    else
                    {
                        $backbone = $this->confirm('Do you want backbone [y/n]? ', true);
                        if( $backbone )
                        {
                            $ch = curl_init("http://backbonejs.org/backbone-min.js");
                            $fp = fopen("public/js/backbone.js", "w");

                            curl_setopt($ch, CURLOPT_FILE, $fp);
                            curl_setopt($ch, CURLOPT_HEADER, 0);

                            curl_exec($ch);
                            curl_close($ch);
                            fclose($fp);
                            $fileContents .= "<script src=\"{{ url('js/backbone.js') }}\"></script>\n";
                            $this->info("public/js/backbone.js created!");
                        }
                    }
                }
                $fileContents .= "<script src=\"{{ url('js/main.js') }}\"></script>\n";
                $fileContents .= "</body>\n";
                $fileContents .= "</html>\n";
                \File::put($layoutPath, $fileContents);
            }
            else
            {
                $this->error('Layout file already exists!');
            }
        }
    }
}
