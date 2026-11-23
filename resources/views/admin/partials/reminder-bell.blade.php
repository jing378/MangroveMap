{{--
  Bell in a Map Layers row that opens the "Send reminder" dialog (admin.partials.reminder-dialog) for one record.
  @include('admin.partials.reminder-bell', ['kind' => ..., 'target' => id, 'title' => ..., 'who' => ..., 'recent' => ?AdminReminder])
  While the last reminder is less than a day old it is shown filled and disabled, saying when it was sent.
--}}
@if($recent)
    <button type="button" class="action-btn reminder-bell is-sent" disabled
        title="Reminded {{ $recent->created_at->diffForHumans() }} by {{ $recent->sender?->name ?? 'an admin' }}. It can be sent again {{ $recent->availableAgainAt()->diffForHumans() }}."
        aria-label="Reminded {{ $recent->created_at->diffForHumans() }}"><i class="bi bi-bell-fill" aria-hidden="true"></i></button>
@else
    <button type="button" class="action-btn reminder-bell" data-reminder
        data-reminder-kind="{{ $kind }}" data-reminder-target="{{ $target }}"
        data-title="{{ $title }}" data-who="{{ $who }}"
        title="{{ $title }}" aria-label="{{ $title }}"><i class="bi bi-bell" aria-hidden="true"></i></button>
@endif
