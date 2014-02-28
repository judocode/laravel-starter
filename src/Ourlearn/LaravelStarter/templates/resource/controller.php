<?php 

class [Model]Controller extends \BaseController 
{
	protected $[model];

	public function __construct([Model]RepositoryInterface $[model])
	{
		$this->[model] = $[model];
	}

	public function index()
	{
    	$[models] = $this->[model]->all();
        $this->layout->content = \View::make('[model].all', compact('[models]'));
	}

	public function create()
	{
        $this->layout->content = \View::make('[model].create');
	}

	public function store()
	{
        $this->[model]->store(\Input::only([repeat]'[property]',[/repeat]));
        return \Redirect::to('[model]');
	}

	public function show($id)
	{
        $[model] = $this->[model]->find($id);
        $this->layout->content = \View::make('[model].view')->with('[model]', $[model]);
	}

	public function edit($id)
	{
        $[model] = $this->[model]->find($id);
        $this->layout->content = \View::make('[model].edit')->with('[model]', $[model]);
	}

	public function update($id)
	{
        $this->[model]->update($id, \Input::only([repeat]'[property]',[/repeat]));
        return \Redirect::to('[model]/'.$id);
	}

	public function destroy($id)
	{
        $this->[model]->destroy($id);
        return \Redirect::to('[model]');
	}

}
