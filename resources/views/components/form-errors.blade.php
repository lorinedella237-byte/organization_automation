@props(['errors'])

{{--
    Validation summary.

    Server-side validation is the only authority; this renders whatever the
    Laravel error bag contains. Messages come from lang/en/validation.php.
--}}

@if ($errors->any())
    <x-alert type="danger"
             :title="trans_choice('{1}There is 1 problem with your submission.|[2,*]There are :count problems with your submission.', $errors->count(), ['count' => $errors->count()])">
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </x-alert>
@endif
