@section('content')
<div class="row">
    <h2>New [Model]</h2>
</div>
<div class="row">
    {{ Form::open(array('url' => '[model]')) }}

    [repeat]
    <div class="form-group">
        {{ Form::label('[property]', 'Name', array('class'=>'control-label') }}
        {{ Form::text('[property]', Input::old('[property]'), array('class' => 'form-control')) }}
    </div>
    [/repeat]

    {{ Form::submit('Add [Model]', array('class' => 'btn btn-success')) }}

    {{ Form::close() }}
</div>
@stop