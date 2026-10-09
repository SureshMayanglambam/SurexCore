@if($status === 'published')
    <span class="badge text-bg-success">{{ lang('Admin.published') }}</span>
@else
    <span class="badge text-bg-secondary">{{ lang('Admin.draft') }}</span>
@endif
