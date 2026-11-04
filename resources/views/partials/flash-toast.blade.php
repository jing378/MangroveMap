{{-- Shows the session's success/error flash (and, if asked, the first validation error) with the
     shared toast from partials.action-feedback. Include it in a page's scripts section. --}}
@php($flashError = session('error') ?? (($withValidationErrors ?? false) && $errors->any() ? $errors->first() : null))
@if (session('success') || $flashError)
<script>
    @if (session('success'))
    window.AppActions?.notify(@json(session('success')));
    @endif
    @if ($flashError)
    window.AppActions?.notify(@json($flashError), 'error');
    @endif
</script>
@endif
