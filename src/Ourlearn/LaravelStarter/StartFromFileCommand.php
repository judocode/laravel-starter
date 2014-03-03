<?php namespace Ourlearn\LaravelStarter;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class StartFromFileCommand extends Command
{
    protected $name = 'start:file';

    protected $description = "Makes table, controller, model, views, seeds, and repository from file";

    protected $app;

    public function __construct($app)
    {
        parent::__construct();
        $this->app = $app;
    }

    public function fire()
    {
        $start = new Start($this);

        $this->info('Please wait while all your files are generated...');

        $start->createModelsFromFile($this->argument('file'));

        $this->info('Finishing...');

        $this->call('clear-compiled');

        $this->call('optimize');

        $this->info('Done!');
    }

    protected function getArguments()
    {
        return array(
            array('file', InputArgument::REQUIRED, 'Path to the file'),
        );
    }

}
