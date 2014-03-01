<?php

return array(

    /*
	|--------------------------------------------------------------------------
	| Repository Pattern
	|--------------------------------------------------------------------------
	|
	| This is where you define if you want to use the repository pattern
	|
	*/

    'repository' => true,

    /*
	|--------------------------------------------------------------------------
	| Downloads
	|--------------------------------------------------------------------------
	|
	| Set to "true" for those which you would like downloaded with your application.
    | They will also be automatically included in your layout file
	|
	*/

    'downloads' => array(

        'jquery1' => true,
        'jquery2' => false,
        'bootstrap' => true,
        'foundation' => false,
        'underscore' => false,
        'handlebars' => false,
        'angular' => true,
        'ember' => false,
        'backbone' => false

    ),

    /*
	|--------------------------------------------------------------------------
	| Paths
	|--------------------------------------------------------------------------
	|
	| Specify the path to the following folders
    |
	*/

    'paths' => array(

        'templates' => app_path().'/templates',
        'controllers' => app_path().'/controllers',
        'migrations' => app_path().'/database/migrations',
        'seeds' => app_path().'/database/seeds',
        'models' => app_path().'/models',
        'repositories' => app_path().'/repositories',
        'repositoryInterfaces' => app_path().'/repositories/interfaces',
        'tests' => app_path().'/tests',
        'views' => app_path().'/views',
        'routes' => app_path()

    ),

    /*
	|--------------------------------------------------------------------------
	| Dynamic Names
	|--------------------------------------------------------------------------
	|
	| Create your own named variable and include in it the templates!
    |
	| Eg: 'myName' => '[Model] is super fantastic!'
    |
	| Then place [myName] in your template file and it will output "Book is super fantastic!"
    |
    | [model], [models], [Model], or [Models] are valid in the dynamic name
	|
	*/

    'names' => array(

        'controller' => '[Model]Controller',
        'modelName' => '[Model]',
        'test' => '[Models]ControllerTest',
        'repository' => 'Eloquent[Model]Repository',
        'repositoryInterface' => '[Model]RepositoryInterface',
        'viewFolder' => '[model]',

    ),

    /*
	|--------------------------------------------------------------------------
	| Views
	|--------------------------------------------------------------------------
	|
	| Specify the names of your views.
    |
	| ***IMPORTANT** Whatever you change the name to, you need to make sure you
    |   have a file with the same name.txt in your templates folder, under
    |   resource and/or restful, depending on the type of controller.
	|
	*/

    'views' => array(

        'view',
        'edit',
        'create',
        'all'

    )
);
