@section('content')
<div class="row">
    <h2>Edit author</h2>
</div>
<div class="row">
    {{ Form::model($[model], array('route' => array('[model].update', $[model]->id), 'method' => 'PUT')) }}

    <div class="form-group">
        {{ Form::label('[property]', '[Property]') }}
        {{ Form::text('[property]', null, array('class' => 'form-control')) }}
    </div>

    {{ Form::submit('Edit [Model]', array('class' => 'btn btn-success')) }}

    {{Form::close()}}
</div>
@stop

