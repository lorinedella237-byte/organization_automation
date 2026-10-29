{{--
    Document library. P4.01-FE to P4.03-FE.

    Uploads are quarantined and scanned before anyone can read them, and the
    server refuses a download until a version comes back clean. That wait is
    shown as a state on the row rather than hidden behind a download button
    that fails, because the honest answer to "why can I not open this" is
    "it has not been scanned yet".

    The list itself is confidential, so the server returns only the documents
    the reader may actually read. Nothing here widens that.
--}}
@extends('layouts.app')

@section('title', __('Library'))

@php
    $uploadFailed = $errors->has('file') || $errors->has('folder_id');
    $folderFailed = $errors->has('name') || $errors->has('parent_id');

    $scanStates = [
        'pending' => __('Waiting to be scanned'),
        'clean' => __('Scanned'),
        'infected' => __('Rejected by the scanner'),
        'failed' => __('Scan failed'),
    ];
@endphp

@section('content')
    <x-page-header
        :title="__('Library')"
        :subtitle="__('Files you have been given access to.')"
        :breadcrumbs="[__('Workspace') => null, __('Library') => null]" />

    <div class="row g-4">
        <div class="col-12 col-lg-4">
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="h6 mb-3">{{ __('Upload a file') }}</h2>

                    <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" novalidate>
                        @csrf

                        <div class="mb-3">
                            <label class="form-label" for="file">
                                {{ __('File') }}
                                <span class="text-danger" aria-hidden="true">*</span>
                                <span class="visually-hidden">({{ __('required') }})</span>
                            </label>
                            <input type="file"
                                   class="form-control @error('file') is-invalid @enderror"
                                   id="file"
                                   name="file"
                                   required
                                   aria-describedby="file-help">
                            <div class="form-text" id="file-help">
                                {{ __('PDF, Word, plain text, JPEG or PNG. Up to 25 MB.') }}
                            </div>
                            @error('file')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                        </div>

                        <x-select
                            name="folder_id"
                            :label="__('Folder')"
                            :options="$folders->all()"
                            :selected="old('folder_id')"
                            :placeholder="__('No folder')" />

                        <button type="submit" class="btn btn-primary">{{ __('Upload') }}</button>
                    </form>
                </div>
            </div>

            @can('files.share')
                <div class="card">
                    <div class="card-body">
                        <h2 class="h6 mb-3">{{ __('New folder') }}</h2>

                        <form method="POST" action="{{ route('folders.store') }}" novalidate>
                            @csrf

                            <x-input name="name" :label="__('Name')" :value="old('name')" required />

                            <x-select
                                name="parent_id"
                                :label="__('Inside')"
                                :options="$folders->all()"
                                :selected="old('parent_id')"
                                :placeholder="__('At the top level')" />

                            <button type="submit" class="btn btn-outline-secondary">{{ __('Create folder') }}</button>
                        </form>
                    </div>
                </div>
            @endcan
        </div>

        <div class="col-12 col-lg-8">
            @if ($page->total() === 0)
                <x-empty-state
                    icon="folder2-open"
                    :title="__('No files you can open')"
                    :description="__('A file appears here once it is uploaded and shared with you, your department, your team or the whole organization.')" />
            @else
                <div class="table-responsive">
                    <table class="table table-data align-middle">
                        <caption class="visually-hidden">{{ __('Documents you can read') }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ __('File') }}</th>
                                <th scope="col">{{ __('Size') }}</th>
                                <th scope="col">{{ __('State') }}</th>
                                <th scope="col"><span class="visually-hidden">{{ __('Actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($page as $document)
                                @php
                                    $version = $document->versions->sortByDesc('version')->first();
                                    // The server allows a download only for an
                                    // available document with a clean version.
                                    $readable = $document->status === 'available' && $version?->scan_status === 'clean';
                                @endphp

                                <tr>
                                    <th scope="row" class="fw-semibold">
                                        {{ $document->name }}
                                        <span class="d-block text-secondary small fw-normal font-monospace">
                                            {{ optional($document->created_at)->format('Y-m-d') }}
                                            @if ($version)
                                                <span class="ms-1">{{ __('v:version', ['version' => $version->version]) }}</span>
                                            @endif
                                        </span>
                                    </th>
                                    <td class="font-monospace small text-nowrap">
                                        @if ($version?->size)
                                            {{ number_format($version->size / 1024, 0) }} KB
                                        @else
                                            <span class="text-secondary">{{ __('Not known') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($readable)
                                            <x-status-badge status="approved" :label="__('Available')" />
                                        @else
                                            {{-- Named, not merely greyed, so the
                                                 reason is readable. --}}
                                            <x-status-badge
                                                :status="$version?->scan_status === 'infected' ? 'rejected' : 'pending'"
                                                :label="$scanStates[$version?->scan_status] ?? __('Not available')" />
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex flex-wrap gap-1 justify-content-end">
                                            @if ($readable)
                                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('documents.download', $document) }}">
                                                    {{ __('Download') }}
                                                </a>
                                            @endif

                                            @can('files.share')
                                                <form method="POST" action="{{ route('documents.trash', $document) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Trash') }}</button>
                                                </form>
                                            @endcan
                                        </div>

                                        @can('files.share')
                                            <details class="mt-1 text-start">
                                                <summary class="small">{{ __('Give access') }}</summary>

                                                <form method="POST" action="{{ route('documents.shares.store', $document) }}" class="mt-2" novalidate>
                                                    @csrf

                                                    {{-- Only the organization-wide target is
                                                         offered, because naming a person or a
                                                         department needs an option list the
                                                         server does not supply to this page. --}}
                                                    <input type="hidden" name="target_type" value="organization">

                                                    <x-select
                                                        name="permission"
                                                        :label="__('Everyone in this organization may')"
                                                        :options="['view' => __('View it'), 'download' => __('Download it')]"
                                                        :selected="old('permission', 'view')"
                                                        required />

                                                    <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Share') }}</button>
                                                </form>
                                            </details>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

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
        </div>
    </div>
@endsection
