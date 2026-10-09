@if(!empty($notice))
    <div class="alert alert-{{ $notice[0] === 'error' ? 'danger' : $notice[0] }} alert-dismissible fade show">
        {{ $notice[1] }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ lang('Admin.aria.close') }}"></button>
    </div>
@endif
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ lang('Admin.aria.close') }}"></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle me-1"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ lang('Admin.aria.close') }}"></button>
    </div>
@endif
@if(session('errors'))
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach(session('errors') as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
