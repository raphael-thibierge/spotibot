@extends('layouts.app')

@section('title')
    Login -- @parent
@endsection

@section('description', 'Log in ' . config('app.name') . ' to get some new music stuff')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
<div class="panel panel-default">
    <div class="panel-heading">Account linking confirmation</div>
    <div class="panel-body">
        <form class="form-horizontal" role="form" method="POST" action="{{ route('botman.confirm') }}">
            {{ csrf_field() }}

            @if(isset($_REQUEST['redirect_uri']))
            <input type="hidden" name="redirect_uri" value="{{ $_REQUEST['redirect_uri'] }}">
            @endif
            @if(isset($_REQUEST['account_linking_token']))
            <input type="hidden" name="account_linking_token" value="{{ $_REQUEST['account_linking_token'] }}">
            @endif

            <div class="form-group">
                <div class="col-md-8 col-md-offset-4">
                    <button type="submit" class="btn btn-success">
                        Confirm
                    </button>

                    <a class="btn btn-default" href="{{ isset($_REQUEST['redirect_uri']) ? $_REQUEST['redirect_uri'] : '#'}}">
                        Cancel
                    </a>

                </div>
            </div>
        </form>
    </div>
</div>
        </div>
    </div>
</div>
@endsection
