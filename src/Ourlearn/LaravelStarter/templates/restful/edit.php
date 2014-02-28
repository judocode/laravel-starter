@section('content')
<div class="row">
    <h2>Edit [model]</h2>
</div>
<div class="row">
    <form class="form-horizontal" role="form" method="POST" action="{{ url('[model]/update/'.$[model]->id) }}">
        [repeat]
        <div class="form-group">
            <label class="control-label" for="[property]">[Property]</label>
            <input class="form-control" type="text" name="[property]" id="[property]" placeholder="[property]" value="{{ $[model]->[property] }}">
        </div>
        [/repeat]
        <div class="form-group">
            <label class="control-label"></label>
            <input class="btn btn-warning" type="reset" value="Reset">
            <input class="btn btn-success" type="submit" value="Edit [Model]">
        </div>
    </form>
</div>
@stop
