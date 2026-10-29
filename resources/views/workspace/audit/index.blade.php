{{--
    Audit viewer. P1.07-FE.

    Read only by design. The audit trail is evidence, so there is no edit and no
    delete control anywhere on this page, and there never should be.

    Rows arrive already constrained to the viewer's organization by the server.
    Metadata is rendered as escaped text rather than interpreted, because it is
    arbitrary recorded data and must never be treated as markup.
--}}
@extends('layouts.app')

@section('title', __('Audit trail'))

@section('content')
    <x-page-header
        :title="__('Audit trail')"
        :subtitle="__('Every recorded change in this workspace, newest first.')"
        :breadcrumbs="[__('Workspace') => null, __('Audit trail') => null]" />

    @if ($page->total() === 0)
        <x-empty-state
            icon="clock-history"
            :title="__('Nothing recorded yet')"
            :description="__('Changes to staff, roles, departments and organization settings are recorded here as they happen.')" />
    @else
        <div class="table-responsive">
            <table class="table table-data align-middle">
                <caption class="visually-hidden">{{ __('Recorded changes in this workspace') }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __('When') }}</th>
                        <th scope="col">{{ __('Action') }}</th>
                        <th scope="col">{{ __('Record') }}</th>
                        <th scope="col">{{ __('By') }}</th>
                        <th scope="col">{{ __('Details') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($page as $entry)
                        <tr>
                            <td class="font-monospace small text-nowrap">
                                {{ $entry->created_at ?: __('Not recorded') }}
                            </td>
                            <td><code class="text-body">{{ $entry->action }}</code></td>
                            <td class="small">
                                {{ $entry->entity_type }}
                                @if ($entry->entity_id)
                                    <span class="text-secondary font-monospace">#{{ $entry->entity_id }}</span>
                                @endif
                            </td>
                            <td class="font-monospace small text-secondary">
                                {{ $entry->actor_membership_id ? '#'.$entry->actor_membership_id : __('System') }}
                            </td>
                            <td class="small">
                                @if (filled($entry->metadata))
                                    {{-- Escaped, never rendered as markup. --}}
                                    <span class="text-secondary">{{ Str::limit($entry->metadata, 120) }}</span>
                                @else
                                    <span class="text-secondary">{{ __('None') }}</span>
                                @endif
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
@endsection
