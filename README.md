## Laravel 4 Starter Command

Automatically generates the files you need to get up and running. Generates a default layout, sets up bootstrap or foundation, prompts for javascript files (options are ember, angular, backbone, underscore, and jquery), creates model, controller, and views, runs migration, updates routes, and seeds your new table with mock data - all in one command.

## Installation

Begin by installing this package through Composer. Edit your project's `composer.json` file to require `ourlearn/laravel-starter`

    "require-dev": {
		"ourlearn/laravel-starter": "dev-master"
	}

Next, update Composer from the Terminal:

    composer update

Once this operation completes, the final step is to add the service provider. Open `app/config/app.php`, and add a new item to the providers array.

    'Ourlearn\LaravelStarter\LaravelStarterServiceProvider'

That's it! You're all set to go. Run the `artisan` command from the Terminal to see the new `start` command.

    php artisan

## Usage

Run `php artisan start` and the guided setup will help you with the rest!

## Cool stuff

Within the command, there is a prompt to ask if you want to add tables, which now supports adding a relationship. So, you can type:

`Book belongsTo Author title:string published:integer`

... and this will automatically add the "author" method to your Book model, and add "author_id" to your migration table.

## Future ideas

- Automatically create js file based on js framework that is specified.
- Automatically generate fake seed data using faker