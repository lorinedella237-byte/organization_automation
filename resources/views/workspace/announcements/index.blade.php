{{--
    Announcements. P7.02-FE.

    The server returns published announcements that have not expired, for
    everyone with announcements.view. Writing one needs announcements.manage,
    so the composer is gated separately from the reading.

    A saved announcement with no publish date is a draft, and the list endpoint
    returns published ones only, so a draft written here would vanish from the
    page that wrote it. The form says that plainly rather than letting someone
    discover it, and the tracker carries it as backend work.
--}}
@extends('layouts.app')

@section('title', __('Announcements'))

@php
    $composeFailed = $errors->any();
@endphp

@section('content')
    <x-page-header
        :title="__('Announcements')"
        :subtitle="__('What the organization has told everyone, newest first.')"
        :breadcrumbs="[__('Workspace') => null, __('Announcements') => null]">
        <x-slot:actions>
            @can('announcements.manage')
                <button class="btn btn-primary btn-sm"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#new-announcement"
                        aria-expanded="{{ $composeFailed ? 'true' : 'false' }}"
                        aria-controls="new-announcement">
                    <i class="bi bi-megaphone me-1" aria-hidden="true"></i>{{ __('Write one') }}
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @can('announcements.manage')
        <div class="collapse @if ($composeFailed) show @endif mb-4" id="new-announcement">
            <div class="card">
                <div class="card-body">
                    <h2 class="h6 mb-3">{{ __('New announcement') }}</h2>

                    <form method="POST" action="{{ route('announcements.store') }}" novalidate>
                        @csrf

                        <x-input name="title" :label="__('Title')" :value="old('title')" required />

                        <x-textarea
                            name="body"
                            :label="__('Message')"
                            :value="old('body')"
                            rows="6"
                            required
                            :help="__('Plain text. It is shown to everyone in the organization who can read announcements.')" />

                        <div class="row">
                            <div class="col-12 col-md-6">
                                <x-input
                                    name="published_at"
                                    type="datetime-local"
                                    :label="__('Publish at')"
                                    :value="old('published_at', now()->format('Y-m-d\TH:i'))"
                                    :help="__('Clear this to save a draft. Drafts are not listed on this page yet, so a cleared date puts it out of reach for now.')" />
                            </div>
                            <div class="col-12 col-md-6">
                                <x-input
                                    name="expires_at"
                                    type="datetime-local"
                                    :label="__('Stop showing at')"
                                    :value="old('expires_at')"
                                    :help="__('Optional. After this it drops off the list.')" />
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">{{ __('Post announcement') }}</button>
                    </form>
                </div>
            </div>
        </div>
    @endcan

    @if ($page->total() === 0)
        <x-empty-state
            icon="megaphone"
            :title="__('Nothing has been announced')"
            :description="__('Announcements are for things everyone needs to know. They appear here once published and drop off when they expire.')" />
    @else
        <div class="d-flex flex-column gap-3">
            @foreach ($page as $announcement)
                <article class="card">
                    <div class="card-body">
                        <h2 class="h6 mb-1">{{ $announcement->title }}</h2>

                        <p class="text-secondary small mb-3">
                            {{ $announcement->author?->user?->name ?: __('The organization') }}
                            @if ($announcement->published_at)
                                <span class="font-monospace ms-1">{{ \Illuminate\Support\Carbon::parse($announcement->published_at)->format('Y-m-d H:i') }}</span>
                            @endif
                        </p>

                        {{-- Escaped and printed as written. An announcement is
                             typed by a person and is never treated as markup. --}}
                        <p class="mb-0" style="white-space: pre-line">{{ $announcement->body }}</p>

                        @if ($announcement->expires_at)
                            <p class="text-secondary small mb-0 mt-3">
                                {{ __('Shown until :when', ['when' => \Illuminate\Support\Carbon::parse($announcement->expires_at)->format('Y-m-d')]) }}
                            </p>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
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
