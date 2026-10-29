@extends('layouts.guest')

@section('content')
<main class="container py-5" style="max-width: 720px">
    <h1 class="h3">Create workspace</h1>
    <p class="text-body-secondary">Create a workspace and send its first owner an invitation.</p>
    <form method="POST" action="{{ route('platform.organizations.store') }}" class="vstack gap-3">
        @csrf
        <label>Organization name<input class="form-control" name="name" value="{{ old('name') }}" required maxlength="255"></label>
        <label>Slug<input class="form-control" name="slug" value="{{ old('slug') }}" required pattern="[a-z0-9]+(?:-[a-z0-9]+)*" maxlength="100"></label>
        <label>Owner email<input class="form-control" type="email" name="owner_email" value="{{ old('owner_email') }}" required></label>
        <label>Timezone<input class="form-control" name="timezone" value="{{ old('timezone', 'UTC') }}" required></label>
        <label>Currency<select class="form-select" name="currency">@foreach($currencies as $currency)<option value="{{ $currency }}" @selected(old('currency', 'NGN') === $currency)>{{ $currency }}</option>@endforeach</select></label>
        <button class="btn btn-primary" type="submit">Create workspace</button>
    </form>
</main>
@endsection
