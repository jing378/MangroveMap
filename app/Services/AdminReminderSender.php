<?php

namespace App\Services;

use App\Models\AdminReminder;
use App\Models\Delineation;
use App\Models\DroneImageryLayer;
use App\Models\User;
use App\Models\UserActivity;
use App\Notifications\ReminderFromAdmin;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Reminders an admin sends from the dashboard ("Needs attention") or Map Layers (one record):
 *  - pending_reviews: every expert, about all delineations waiting for review;
 *  - delineation:     every expert, about one pending delineation;
 *  - stale_drafts:    each owner of a drone survey whose AI draft waited over STALE_DRAFT_DAYS, about theirs;
 *  - drone_layer:     the owner of one drone survey waiting for them.
 * The same reminder goes out at most once per AdminReminder::COOLDOWN_HOURS; each one is recorded
 * (admin_reminders) and logged in the activity log.
 */
class AdminReminderSender
{
    private const MODULE = 'Reminders';

    /**
     * @return array{ok: bool, message: string}
     */
    public function send(User $admin, string $kind, ?int $targetId = null, ?string $note = null): array
    {
        $note = trim((string) $note) ?: null;
        $targetId = in_array($kind, [AdminReminder::DELINEATION, AdminReminder::DRONE_LAYER], true) ? $targetId : null;

        if ($recent = AdminReminder::recent($kind, $targetId)) {
            return ['ok' => false, 'message' => 'This reminder was already sent '.$recent->created_at->diffForHumans()
                .'. It can be sent again '.$recent->availableAgainAt()->diffForHumans().'.'];
        }

        [$deliveries, $subject] = match ($kind) {
            AdminReminder::PENDING_REVIEWS => $this->pendingReviews(),
            AdminReminder::DELINEATION => $this->oneDelineation($targetId),
            AdminReminder::STALE_DRAFTS => $this->staleDrafts(),
            AdminReminder::DRONE_LAYER => $this->oneDroneLayer($targetId),
        };

        if ($deliveries === null) {
            return ['ok' => false, 'message' => $subject];
        }

        foreach ($deliveries as [$recipients, $title, $message, $url, $action]) {
            Notification::send($recipients, new ReminderFromAdmin($title, $message, $url, $action, $note, $admin->name));
        }
        $count = $deliveries->sum(fn (array $delivery) => $delivery[0]->count());

        AdminReminder::create([
            'kind' => $kind,
            'target_id' => $targetId,
            'sent_by' => $admin->id,
            'recipients' => $count,
            'note' => $note,
        ]);
        UserActivity::record($admin, 'Sent a reminder: '.$subject.' ('.$count.' '.Str::plural('person', $count).')', self::MODULE, 'success');

        return ['ok' => true, 'message' => 'Reminder sent to '.$count.' '.Str::plural('person', $count).': '.$subject.'.'];
    }

    /** @return array{0: ?Collection, 1: string} deliveries [recipients, title, message, url, action], or null and why */
    private function pendingReviews(): array
    {
        $pending = Delineation::pending()->count();
        if ($pending === 0) {
            return [null, 'No delineations are waiting for review.'];
        }
        $experts = User::where('role', User::ROLE_EXPERT)->get();
        if ($experts->isEmpty()) {
            return [null, 'There are no experts to remind.'];
        }
        $oldest = Delineation::pending()->min('created_at');

        return [collect([[
            $experts,
            Str::plural('delineation', $pending).' waiting for review',
            $pending.' '.Str::plural('delineation', $pending).' '.($pending === 1 ? 'is' : 'are').' waiting for expert review'
                .($oldest ? '; the oldest was submitted '.Carbon::parse($oldest)->diffForHumans() : '').'.',
            route('expert.delineate', [], false),
            'Review delineations',
        ]]), $pending.' pending '.Str::plural('delineation', $pending).' (experts)'];
    }

    private function oneDelineation(?int $id): array
    {
        $delineation = $id ? Delineation::pending()->find($id) : null;
        if (! $delineation) {
            return [null, 'That delineation is not waiting for review any more.'];
        }
        $experts = User::where('role', User::ROLE_EXPERT)->get();
        if ($experts->isEmpty()) {
            return [null, 'There are no experts to remind.'];
        }
        $name = $delineation->name ?: 'Delineation #'.$delineation->id;

        return [collect([[
            $experts,
            'please review "'.$name.'"',
            '"'.$name.'" has been waiting for expert review since '.$delineation->created_at?->diffForHumans().'.',
            route('expert.delineate', ['delineation' => $delineation->id], false),
            'Review it',
        ]]), '"'.$name.'" waiting for review (experts)'];
    }

    private function staleDrafts(): array
    {
        $layers = DroneImageryLayer::staleOwnerDraft()->with('user')->get()->filter(fn ($layer) => $layer->user !== null);
        if ($layers->isEmpty()) {
            return [null, 'No drone surveys have waited over '.DroneImageryLayer::STALE_DRAFT_DAYS.' days for their owner.'];
        }

        // One reminder per owner, naming their surveys.
        $deliveries = $layers->groupBy('user_id')->map(function (Collection $own) {
            $names = $own->pluck('name')->map(fn ($name) => '"'.$name.'"');
            $listed = $names->take(3)->implode(', ').($names->count() > 3 ? ' and '.($names->count() - 3).' more' : '');

            return [
                collect([$own->first()->user]),
                'confirm the mangrove areas of your drone '.Str::plural('survey', $own->count()),
                'The AI found mangrove areas in '.$listed.'. Check them, choose the genus and confirm so '
                    .($own->count() === 1 ? 'it' : 'they').' can go on the map.',
                route('drone-imagery.page', $own->count() === 1 ? ['layer' => $own->first()->id] : [], false),
                'Open Drone Upload',
            ];
        })->values();

        return [$deliveries, $layers->count().' AI '.Str::plural('draft', $layers->count()).' waiting for '.$deliveries->count().' '.Str::plural('owner', $deliveries->count())];
    }

    private function oneDroneLayer(?int $id): array
    {
        $layer = $id ? DroneImageryLayer::inStage('waiting_owner')->with('user')->find($id) : null;
        if (! $layer || ! $layer->user) {
            return [null, 'That drone survey is not waiting for its owner any more.'];
        }

        return [collect([[
            collect([$layer->user]),
            'confirm the mangrove areas of "'.$layer->name.'"',
            'The AI found mangrove areas in your drone survey "'.$layer->name.'". Check them, choose the genus and confirm so it can go on the map.',
            route('drone-imagery.page', ['layer' => $layer->id], false),
            'Open Drone Upload',
        ]]), '"'.$layer->name.'" waiting for '.$layer->user->name];
    }
}
