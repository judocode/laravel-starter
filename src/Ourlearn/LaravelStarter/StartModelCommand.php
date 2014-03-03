<?php namespace Ourlearn\LaravelStarter;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;

class StartModelCommand extends Command
{
    protected $name = 'start:model';

    protected $description = "Makes table, controller, model, views, seeds, and repository";

    public function __construct()
    {
        parent::__construct();
    }

    public function fire()
    {
        $start = new Start($this);

        $start->createModels();

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
}
