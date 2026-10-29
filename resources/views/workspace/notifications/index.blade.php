{{--
    Notifications. P1.07-FE.

    The viewer's own notifications only. The controller constrains rows to the
    acting membership, so this page never needs to filter and must never try.

    Unread is carried by weight and a marker, not by colour alone, so it
    survives a monochrome display and colour blindness.

    There is one write here: mark a single notification read. The server has no
    mark-all endpoint, so this page does not offer one. A control that posts to
    a route that does not exist is worse than its absence.

    read_at comes from the query builder, not Eloquent, so it arrives as a
    string or null rather than a Carbon instance. It is only ever tested for
    emptiness here, never formatted.
--}}
@extends('layouts.app')

@section('title', __('Notifications'))

@section('content')
    <x-page-header
        :title="__('Notifications')"
        :subtitle="__('What has happened in this workspace that involves you, newest first.')"
        :breadcrumbs="[__('Workspace') => null, __('Notifications') => null]" />

    @if ($page->total() === 0)
        <x-empty-state
            icon="bell"
            :title="__('Nothing to read')"
            :description="__('When something needs your attention, such as an invitation being accepted, it appears here.')" />
    @else
        <ul class="list-unstyled mb-4">
            @foreach ($page as $entry)
                @php($unread = blank($entry->read_at))

                <li class="border-bottom py-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <div class="flex-grow-1">
                            <p class="mb-1 @if ($unread) fw-semibold @endif">
                                @if ($unread)
                                    <span class="visually-hidden">{{ __('Unread.') }}</span>
                                @endif
                                {{ $entry->message }}
                            </p>
                            <p class="label-mono text-secondary mb-0">
                                {{ $entry->event_key }}
                                <span class="ms-2">{{ $entry->created_at }}</span>
                            </p>
                        </div>

                        <div class="text-nowrap">
                            @if ($unread)
                                <form method="POST" action="{{ route('notifications.read', $entry->id) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                                        {{ __('Mark as read') }}
                                    </button>
                                </form>
                            @else
                                <span class="text-secondary small">{{ __('Read') }}</span>
                            @endif
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <p class="text-secondary small mb-0">
                {{ __('Showing :from to :to of :total', [
                    'from' => $page->firstItem(),
                    'to' => $page->lastItem(),
                    'total' => $page->total(),
                ]) }}
            </p>
            {{ $page->withQueryString()->links() }}
        </div>
    @endif
@endsection
