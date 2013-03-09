<?php namespace Ourlearn\LaravelStarter;

class GenerateMigration {

    public static $content;

    public function __construct($className, $args = null)
    {
        $tableName = str_plural($className);

        $createTable = "create_" . $tableName . "_table";
        $file = \App::make('Illuminate\Database\Migrations\MigrationCreator')->create($createTable, "app/database/migrations", $tableName, true);

        // Capitalize where necessary: a_simple_string => A_Simple_String
        $className = implode('_', array_map('ucwords', explode('_', $className)));

        // Let's create the path to where the migration will be stored.
        //$filePath = "app/database/migrations/" . date('Y_m_d_His') . strtolower("_create_".$className."s_table.php");

        $this->generateMigration($className, $tableName, $args);

        $this->writeToFile($file);

        require $file;
    }

    protected function writeToFile($filePath,  $success = '')
    {
        //$success = $success ?: "Create: $filePath.\n";

        // if ( \File::exists($filePath) ) 
        // {
        //     // we don't want to overwrite it
        //     echo "Warning: File already exists at $filePath\n";
        //     return;
        // }

        // As a precaution, let's see if we need to make the folder.
        //\File::makeDirectory(dirname($filePath));

        $success = \File::put($filePath, self::$content);
        if ( !$success )
            echo "Whoops - something...erghh...went wrong!\n";
    }

    protected function parseTableName($className)
    {
        // Try to figure out the table name
        // We'll use the word that comes immediately before "_table"
        // create_users_table => users
        preg_match('/([a-zA-Z]+)_table/', $className, $matches);

        if ( empty($matches) ) 
        {
            // Or, if the user doesn't write "table", we'll just use
            // the text at the end of the string
            // create_users => users
            preg_match('/_([a-zA-Z]+)$/', $className, $matches);
        }

        // Hmm - I'm stumped. Just use a generic name.
        return empty($matches)
            ? "TABLE"
            : $matches[1];
    }

    protected function generateMigration($className, $tableName, $args)
    {
        // Figure out what type of event is occuring. Create, Delete, Add?
        list($tableAction, $tableEvent) = $this->parseActionType($className);

        // Now, we begin creating the contents of the file.
        Template::newClass($className);

        /* The Migration Up Function */
        $up = $this->migrationUp($tableEvent, $tableAction, $tableName, $args);
       
        /* The Migration Down Function */
        $down = $this->migrationDown($tableEvent, $tableAction, $tableName, $args);

        // Add both the up and down function to the migration class.
        Content::addAfter('{', $up . $down);

        return $this->prettify();
    }

    protected function parseActionType($className)
    {
         // What type of action? Creating a table? Adding a column? Deleting?
        if ( preg_match('/delete|update|add(?=_)/i', $className, $matches) ) 
        {
            $tableAction = 'table';
            $tableEvent = strtolower($matches[0]);
        } 
        else 
        {
            $tableAction = $tableEvent = 'create';
        }

        return array($tableAction, $tableEvent);
    }

    protected function migrationUp($tableEvent, $tableAction, $tableName, $args)
    {
        $up = Template::func('up');

        // Insert a new schema function into the up function.
        $up = $this->addAfter('{', Template::schema($tableAction, $tableName), $up);

        // Create the field rules for for the schema
        if ( $tableEvent === 'create' ) 
        {
            $fields = $this->setColumn('increments', 'id') . ';';
            $fields .= $this->addColumns($args);
            $fields .= $this->setColumn('timestamps', null) . ';';
        }

        else if ( $tableEvent === 'delete' ) 
        {
            $fields = $this->dropColumns($args);
        }

        else if ( $tableEvent === 'add' || $tableEvent === 'update' ) 
        {
            $fields = $this->addColumns($args);
        }

        // Insert the fields into the schema function
        return $this->addAfter('function($table) {', $fields, $up);
    }


    protected function migrationDown($tableEvent, $tableAction, $tableName, $args)
    {
        $down = Template::func('down');

        if ( $tableEvent === 'create' ) 
        {
           $schema = Template::schema('drop', $tableName, false);

           // Add drop schema into down function
            $down = $this->addAfter('{', $schema, $down);
        } 
        else 
        {
            // for delete, add, and update
            $schema = Template::schema('table', $tableName);
        }

        if ( $tableEvent === 'delete' ) 
        {
            $fields = $this->addColumns($args);

            // add fields to schema
            $schema = $this->addAfter('function($table) {', $fields, $schema);
            
            // add schema to down function
            $down = $this->addAfter('{', $schema, $down);
        }
        else if ( $tableEvent === 'add' ) 
        {
            $fields = $this->dropColumns($args);

            // add fields to schema
            $schema = $this->addAfter('function($table) {', $fields, $schema);

            // add schema to down function
            $down = $this->addAfter('{', $schema, $down);

        }
        else if ( $tableEvent === 'update' ) 
        {
            // add schema to down function
            $down = $this->addAfter('{', $schema, $down);
        }

        return $down;
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


    protected function addOption($option)
    {
        return "->{$option}()";
    }


    /**
     * Add columns
     *
     * Filters through the provided args, and builds up the schema text.
     *
     * @param  $args array  
     * @return string
     */
    protected function addColumns($args)
    {
        $content = '';

        if($args)
        {
            // Build up the schema
            foreach( $args as $arg ) {
                // Like age, integer, and nullable
                @list($field, $type, $setting) = explode(':', $arg);

                if ( !$type ) 
                {
                    echo "There was an error in your formatting. Please try again. Did you specify both a field and data type for each? age:int\n";
                    die();
                }

                // Primary key check
                if ( $field === 'id' and $type === 'integer' ) 
                {
                    $rule = $this->increment();
                } else {
                    $rule = $this->setColumn($type, $field);

                    if ( !empty($setting) ) {
                        $rule .= $this->addOption($setting);
                    }
                }

                $content .= $rule . ";";
            }
        }

        return $content;
    }


    /**
     * Drop Columns
     *
     * Filters through the args and applies the "dropColumn" syntax
     *
     * @param $args array  
     * @return string
     */
    protected function dropColumns($args)
    {
        $fields = array_map(function($val) 
        {
            $bits = explode(':', $val);
            return "'$bits[0]'";
        }, $args);
       
        if ( count($fields) === 1 ) {
            return "\$table->dropColumn($fields[0]);";
        } else {
            return "\$table->dropColumn(array(" . implode(', ', $fields) . "));";
        }
    }
    

    public function path($dir)
    {
        return path('app') . "$dir/";
    }


    /**
     * Crazy sloppy prettify. TODO - Cleanup
     *
     * @param  $content string  
     * @return string
     */
    protected function prettify()
    {
        $content = self::$content;

        $content = str_replace('<?php ', "<?php\n\n", $content);
        $content = str_replace('{}', "\n{\n\n}", $content);
        $content = str_replace('public', "\n\n\tpublic", $content);
        $content = str_replace("() \n{\n\n}", "()\n\t{\n\n\t}", $content);
        $content = str_replace('}}', "}\n\n}", $content);

        // Migration-Specific
        $content = preg_replace('/ ?Schema::/', "\n\t\tSchema::", $content);
        $content = preg_replace('/\$table(?!\))/', "\n\t\t\t\$table", $content);
        $content = str_replace('});}', "\n\t\t});\n\t}", $content);
        $content = str_replace(');}', ");\n\t}", $content);
        $content = str_replace("() {", "()\n\t{", $content);

        self::$content = $content;
    }


    public function addAfter($where, $to_add, $content)
    {
        // return preg_replace('/' . $where . '/', $where . $to_add, $content, 1);
        return str_replace($where, $where . $to_add, $content);
    }
}

class Content {
    public static function addAfter($where, $to_add)
    {
        GenerateMigration::$content = str_replace($where, $where . $to_add, GenerateMigration::$content);

    }
}


class Template {
    public static function test($className, $test)
    {
        return <<<EOT
    public function test_{$test}()
    {
        \$response = Controller::call('{$className}@$test'); 
        \$this->assertEquals('200', \$response->foundation->getStatusCode());
        \$this->assertRegExp('/.+/', (string)\$response, 'There should be some content in the $test view.');
    }
EOT;
    }


    public static function func($func_name)
    {
        return <<<EOT
    public function {$func_name}()
    {

    }
EOT;
    }


    public static function newClass($name)
    {
        $nameUpperPlural = ucfirst(str_plural(strtolower($name)));
        $content = "<?php\n\n";
        $content .= "use Illuminate\Database\Migrations\Migration;\n\n";
        $content .= "class Create".$nameUpperPlural."Table extends Migration";
        $content .= ' {}';

        GenerateMigration::$content = $content;
    }


    public static function schema($tableAction, $tableName, $cb = true)
    {
        $content = "Schema::$tableAction('$tableName'";

        return $cb
            ? $content . ', function($table) {});'
            : $content . ');';
    }

}