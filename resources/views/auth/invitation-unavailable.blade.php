@extends('layouts.guest')

@section('content')
<main class="container py-5" style="max-width: 680px">
    <h1 class="h3">Invitation unavailable</h1>
    <p>
        @switch($state)
            @case('expired') This invitation has expired. Ask a workspace administrator to send a new one. @break
            @case('revoked') This invitation was withdrawn. Ask a workspace administrator for a new invitation. @break
            @case('accepted') This invitation has already been used. Sign in with the invited account. @break
            @case('suspended') This workspace is currently unavailable. Contact its administrator. @break
            @default This invitation is no longer available. Ask a workspace administrator for help.
        @endswitch
    </p>
</main>
@endsection
