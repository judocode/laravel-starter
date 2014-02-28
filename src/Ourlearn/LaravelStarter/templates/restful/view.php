@section('content')
<div class="row">
    <h1>Viewing [model]</h1>
    <a class="btn btn-primary" href="{{ url('[model]/edit/'.$[model]->id) }}">Edit</a>
    <a class="btn btn-danger" href="{{ url('[model]/delete/'.$[model]->id) }}">Delete</a>
</div>
<div class="row">
    <table class="table">
    	[repeat]
        <tr>
        	<td>[Property]:</td> 
        	<td>{{ $[model]->[property] }}</td>
        </tr>     
        [/repeat] 
    </table>
</div>
@stop
