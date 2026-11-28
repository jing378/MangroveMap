{{--
  Welcome walkthrough: a step-by-step dialog that explains the flow for the user's role (resident, expert, admin).
  It opens by itself on the first page after login until the user finishes or skips it (users.onboarded_at,
  set by onboarding.complete), so it shows once per account, not once per browser. Any [data-onboarding-open]
  button opens it again (Help & Guide). The per-page "?" quick guides on the maps stay separate.
  Included in layouts/enduser and layouts/admin.
--}}
@php
    $tourUser = auth()->user();
@endphp
@if ($tourUser)
@php
    $firstName = trim((string) ($tourUser->first_name ?: strtok((string) $tourUser->name, ' '))) ?: 'there';
    $helpStep = [
        'icon' => 'bi-life-preserver',
        'title' => 'Help is always close by',
        'text' => 'You don\'t have to remember all of this.',
        'points' => [
            '<i class="bi bi-question-lg"></i> The <strong>?</strong> button on each map opens a short quick guide for that page.',
            '<i class="bi bi-book"></i> <strong>Help &amp; Guide</strong> in the sidebar explains everything step by step.',
            '<i class="bi bi-arrow-repeat"></i> You can replay this tour from Help &amp; Guide at any time.',
        ],
        'link' => [route('guide'), 'Open Help & Guide'],
    ];

    if ($tourUser->isAdmin()) {
        $tourSteps = [
            [
                'logo' => true,
                'title' => "Welcome, {$firstName}!",
                'text' => 'As an administrator you keep MangroveMap running: accounts, map data and the AI models. Drawing, uploading and reviewing stay with residents and experts.',
                'points' => [
                    '<i class="bi bi-people"></i> Residents map mangrove areas and upload drone imagery.',
                    '<i class="bi bi-patch-check"></i> Experts approve or reject them.',
                    '<i class="bi bi-tools"></i> You approve experts, keep reviews moving and clean up.',
                ],
            ],
            [
                'icon' => 'bi-speedometer2',
                'title' => 'Start on the Dashboard',
                'text' => '<strong>Needs attention</strong> lists what is waiting on someone.',
                'points' => [
                    'Missing AI model, failed drone surveys, new feedback.',
                    'Reviews waiting too long, and AI drafts their owners haven\'t confirmed.',
                    '<strong>Send reminder</strong> notifies the experts or owners, at most once a day.',
                ],
                'link' => [route('admin.dashboard'), 'Go to Dashboard'],
            ],
            [
                'icon' => 'bi-person-check',
                'title' => 'Approve expert accounts',
                'text' => 'People who apply as experts wait at the top of <strong>Users</strong>.',
                'points' => [
                    'Read their organization and qualifications, then approve or reject.',
                    'A rejection reason is optional and is shared with the applicant.',
                    'You can also edit a user, change a role or reset a password.',
                ],
                'link' => [route('admin.users'), 'Go to Users'],
            ],
            [
                'icon' => 'bi-layers',
                'title' => 'Keep the map clean',
                'text' => '<strong>Map Layers</strong> has every delineation and drone survey.',
                'points' => [
                    'Click a row to see it on the preview map; filter by status, stage or source.',
                    'Export as CSV, or delineations as GeoJSON.',
                    'Cleanup only: <strong>Delete</strong>, or <strong>Retry processing</strong> for a failed survey.',
                ],
                'link' => [route('admin.map-layers.index'), 'Go to Map Layers'],
            ],
            [
                'icon' => 'bi-cpu',
                'title' => 'Look after the AI models',
                'text' => 'Drone surveys are outlined and classified by AI.',
                'points' => [
                    'Upload a U-Net <code>.pth</code> (mangrove areas) or a YOLO <code>.pt</code> (genus).',
                    'The green check activates or deactivates a model.',
                    'The hosted <strong>Roboflow workflows</strong> show whether they are in use.',
                ],
                'link' => [route('admin.models'), 'Go to AI Models'],
            ],
            $helpStep,
        ];
    } elseif ($tourUser->isExpert()) {
        $tourSteps = [
            [
                'logo' => true,
                'title' => "Welcome, {$firstName}!",
                'text' => 'As an expert you check what residents submit, so the mangrove map stays accurate.',
                'points' => [
                    '<i class="bi bi-pencil-square"></i> Residents draw areas or upload drone imagery.',
                    '<i class="bi bi-check2-circle"></i> You approve them, or send them back with feedback.',
                    '<i class="bi bi-lightning-charge"></i> What you draw or upload yourself is approved right away.',
                ],
            ],
            [
                'icon' => 'bi-bell',
                'title' => 'Submissions come to you',
                'text' => 'You get a notification for every new or revised submission.',
                'points' => [
                    'It shows who submitted it, with their <strong>email and phone</strong>, so you can contact them.',
                    '<strong>View</strong> opens the area on the map.',
                ],
                'link' => [route('notifications.index'), 'Go to Notifications'],
            ],
            [
                'icon' => 'bi-clipboard-check',
                'title' => 'Approve or reject',
                'text' => 'Click a pending area to see its genera, notes, location and submitter.',
                'points' => [
                    'Compare the boundary with the satellite map.',
                    '<strong>Approve</strong> if it\'s accurate.',
                    'Or <strong>Reject</strong> and say what to fix. The resident has 7 days to revise it.',
                ],
                'link' => [route('expert.delineate'), 'Go to Delineate'],
            ],
            [
                'icon' => 'bi-bounding-box-circles',
                'title' => 'Fix boundaries and record change',
                'text' => '<strong>Edit Delineation</strong> lets you move points, add or remove areas.',
                'points' => [
                    '<strong>Correction</strong>: you only fix how it was drawn.',
                    '<strong>Observed change</strong>: the mangroves really grew or shrank. It becomes that year\'s entry in <em>Changes over time</em>.',
                ],
            ],
            [
                'icon' => 'bi-tree',
                'title' => 'Recommend where to plant',
                'text' => 'Mark a site, rate how suitable it is, and say which genus to plant.',
                'points' => [
                    'Draw a new site, or select an approved delineation.',
                    'Residents see your recommendations on the map.',
                ],
                'link' => [route('expert.recommendations.index'), 'Go to Planting Recommendation'],
            ],
            $helpStep,
        ];
    } else {
        $tourSteps = [
            [
                'logo' => true,
                'title' => "Welcome, {$firstName}!",
                'text' => 'MangroveMap maps the mangrove forests of Southern Leyte, together with the people who know them.',
                'points' => [
                    '<i class="bi bi-pencil-square"></i> You map mangrove areas you know.',
                    '<i class="bi bi-patch-check"></i> Experts check them.',
                    '<i class="bi bi-globe2"></i> Approved areas go on the public map, with your name.',
                ],
            ],
            [
                'icon' => 'bi-map',
                'title' => 'Explore the map',
                'text' => 'See every approved mangrove area in the province.',
                'points' => [
                    'Click an area for its size, place and genera.',
                    'Tap a genus to see what it looks like.',
                    'The year slider shows what was mapped each year.',
                ],
                'link' => [route('map'), 'Go to Map'],
            ],
            [
                'icon' => 'bi-pencil-square',
                'title' => 'Draw a mangrove area',
                'text' => 'Open <strong>Delineate</strong> and trace the mangroves on the satellite map.',
                'points' => [
                    'Click <strong>Draw area</strong>, then click along the edge of the mangroves.',
                    'Click the first point to finish, or <strong>Import shapefile</strong> if you have one.',
                    'Choose the <strong>genus</strong>, add notes, then <strong>Save delineation</strong>.',
                ],
                'link' => [route('delineate'), 'Go to Delineate'],
            ],
            [
                'icon' => 'bi-hourglass-split',
                'title' => 'An expert reviews it',
                'text' => 'You get a notification when it\'s checked.',
                'points' => [
                    '<span class="onboarding-pill is-pending">Pending</span> Waiting for an expert. You can still edit it.',
                    '<span class="onboarding-pill is-approved">Approved</span> On the public map with your name.',
                    '<span class="onboarding-pill is-rejected">Rejected</span> Read the feedback and revise it within 7 days.',
                ],
            ],
            [
                'icon' => 'bi-cloud-arrow-up',
                'title' => 'Have drone imagery? Let the AI help',
                'text' => 'Upload a drone orthomosaic or photos on <strong>Drone Upload</strong>.',
                'points' => [
                    'The AI outlines the mangroves and suggests the genus.',
                    'You check and fix its areas, choose the genus, and confirm.',
                    'An expert then reviews it, like a drawn area.',
                ],
                'link' => [route('drone-imagery.page'), 'Go to Drone Upload'],
            ],
            $helpStep,
        ];
    }
    $autoOpen = $tourUser->onboarded_at === null && $tourUser->hasVerifiedEmail();
@endphp

{{-- After a "Go to ..." link: the page shows as normal, with this button to pick the tour up at the same step. --}}
<div class="onboarding-resume" id="onboardingResume" role="region" aria-label="Welcome tour" hidden>
    <button type="button" class="onboarding-resume-go" id="onboardingResumeGo">
        <img src="/icon-192.png" alt="" aria-hidden="true">
        <span>Continue tour <small id="onboardingResumeStep"></small></span>
    </button>
    <button type="button" class="onboarding-resume-close" id="onboardingResumeClose" aria-label="End the tour"><i class="bi bi-x-lg"></i></button>
</div>

<dialog class="onboarding-dialog" id="onboardingDialog" aria-labelledby="onboardingTitle0"
    data-auto-open="{{ $autoOpen ? '1' : '0' }}" data-user="{{ $tourUser->id }}">
    <div class="onboarding-top">
        <span class="onboarding-kicker"><i class="bi bi-signpost-split" aria-hidden="true"></i> Getting started</span>
        <span class="onboarding-count" id="onboardingCount" aria-live="polite">Step 1 of {{ count($tourSteps) }}</span>
        <button type="button" class="onboarding-close" data-onboarding-skip aria-label="Close the tour"><i class="bi bi-x-lg"></i></button>
    </div>

    @foreach ($tourSteps as $index => $step)
        <section class="onboarding-step" data-onboarding-step="{{ $index }}" @if ($index > 0) hidden @endif>
            @if (! empty($step['logo']))
                <div class="onboarding-icon is-logo" aria-hidden="true"><img src="/icon-192.png" alt=""></div>
            @else
                <div class="onboarding-icon" aria-hidden="true"><i class="bi {{ $step['icon'] }}"></i></div>
            @endif
            <h2 class="onboarding-title" id="onboardingTitle{{ $index }}">{{ $step['title'] }}</h2>
            <p class="onboarding-text">{!! $step['text'] !!}</p>
            @if (! empty($step['points']))
                <ul class="onboarding-points">
                    @foreach ($step['points'] as $point)
                        <li>{!! $point !!}</li>
                    @endforeach
                </ul>
            @endif
            @if (! empty($step['link']))
                <a class="onboarding-link" href="{{ $step['link'][0] }}" data-onboarding-goto>
                    {{ $step['link'][1] }} <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </a>
            @endif
        </section>
    @endforeach

    <div class="onboarding-foot">
        <div class="onboarding-dots" role="group" aria-label="Tour steps">
            @foreach ($tourSteps as $index => $step)
                <button type="button" class="onboarding-dot {{ $index === 0 ? 'is-active' : '' }}" data-onboarding-dot="{{ $index }}"
                    aria-label="Step {{ $index + 1 }}: {{ $step['title'] }}" @if ($index === 0) aria-current="step" @endif></button>
            @endforeach
        </div>
        <div class="onboarding-actions">
            <button type="button" class="onboarding-btn is-ghost" data-onboarding-skip id="onboardingSkip">Skip tour</button>
            <button type="button" class="onboarding-btn" data-onboarding-back hidden><i class="bi bi-arrow-left" aria-hidden="true"></i> Back</button>
            <button type="button" class="onboarding-btn is-primary" data-onboarding-next>Next <i class="bi bi-arrow-right" aria-hidden="true"></i></button>
        </div>
    </div>
</dialog>

<style>
    .onboarding-dialog {
        /* Explicit centering: Tailwind's preflight (admin layout) zeroes the native margin:auto. */
        position: fixed;
        inset: 0;
        margin: auto;
        height: fit-content;
        max-height: calc(100% - 32px);
        width: calc(100% - 32px);
        max-width: 500px;
        padding: 0;
        border: 0;
        border-radius: 18px;
        background: var(--bg-ffffff, #ffffff);
        color: var(--fg-1a2e1a, #1a2e1a);
        box-shadow: 0 24px 48px -12px rgba(0, 0, 0, .3);
        font-family: 'Manrope', sans-serif;
        overflow: auto;
        overscroll-behavior: contain;
    }

    .onboarding-dialog::backdrop {
        background: rgba(15, 23, 42, .6);
        backdrop-filter: blur(4px);
        -webkit-backdrop-filter: blur(4px);
    }

    .onboarding-top {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px 14px 0 22px;
    }

    .onboarding-kicker {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: var(--fg-1e7a4f, #1e7a4f);
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .06em;
        text-transform: uppercase;
    }

    .onboarding-count {
        margin-left: auto;
        color: var(--fg-64748b, #64748b);
        font-size: 12px;
        font-weight: 600;
    }

    .onboarding-close {
        display: grid;
        place-items: center;
        width: 32px;
        height: 32px;
        border: 0;
        border-radius: 8px;
        background: transparent;
        color: var(--fg-64748b, #64748b);
        cursor: pointer;
    }

    .onboarding-close:hover,
    .onboarding-close:focus-visible {
        background: var(--bg-f1f5f2, #f1f5f2);
    }

    .onboarding-step {
        padding: 10px 26px 6px;
        animation: onboardingIn .28s ease;
    }

    .onboarding-step[hidden] {
        display: none;
    }

    @keyframes onboardingIn {
        from { opacity: 0; transform: translateX(var(--onboarding-from, 14px)); }
        to { opacity: 1; transform: none; }
    }

    .onboarding-icon {
        display: grid;
        place-items: center;
        width: 56px;
        height: 56px;
        margin: 4px 0 14px;
        border-radius: 16px;
        background: linear-gradient(135deg, #1e9e62 0%, #14734a 100%);
        color: #ffffff;
        font-size: 26px;
        box-shadow: 0 8px 18px rgba(30, 158, 98, .28);
    }

    /* Welcome step: the MangroveMap logo instead of an icon. */
    .onboarding-icon.is-logo {
        padding: 0;
        overflow: hidden;
        background: none;
    }

    .onboarding-icon.is-logo img {
        display: block;
        width: 100%;
        height: 100%;
    }

    .onboarding-resume {
        position: fixed;
        right: 20px;
        bottom: 20px;
        z-index: 2500;
        display: flex;
        align-items: center;
        gap: 2px;
        padding: 5px;
        border: 1px solid var(--bd-bce6cf, #bce6cf);
        border-radius: 999px;
        background: var(--bg-ffffff, #ffffff);
        box-shadow: 0 10px 26px rgba(15, 40, 25, .22);
        font-family: 'Manrope', sans-serif;
        animation: onboardingIn .3s ease;
    }

    .onboarding-resume[hidden] {
        display: none;
    }

    .onboarding-resume-go {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 5px 14px 5px 5px;
        border: 0;
        border-radius: 999px;
        background: #1e9e62;
        color: #ffffff;
        font: inherit;
        font-size: 13px;
        font-weight: 800;
        cursor: pointer;
    }

    .onboarding-resume-go:hover {
        background: #16824f;
    }

    .onboarding-resume-go img {
        width: 28px;
        height: 28px;
        border-radius: 50%;
    }

    .onboarding-resume-go small {
        font-weight: 600;
        opacity: .85;
    }

    .onboarding-resume-close {
        display: grid;
        place-items: center;
        width: 32px;
        height: 32px;
        border: 0;
        border-radius: 50%;
        background: transparent;
        color: var(--fg-64748b, #64748b);
        cursor: pointer;
    }

    .onboarding-resume-close:hover {
        background: var(--bg-f1f5f2, #f1f5f2);
    }

    .onboarding-resume-go:focus-visible,
    .onboarding-resume-close:focus-visible {
        outline: 3px solid rgba(30, 158, 98, .4);
        outline-offset: 2px;
    }

    @media (max-width: 768px) {
        /* Clear of the map's bottom sheet and phone browser bars; centred. */
        .onboarding-resume {
            left: 0;
            right: 0;
            width: fit-content;
            margin: 0 auto;
            bottom: calc(16px + env(safe-area-inset-bottom, 0px));
        }
    }

    .onboarding-title {
        margin: 0 0 6px;
        font-size: 20px;
        font-weight: 800;
        letter-spacing: -.01em;
        color: var(--fg-10301c, #10301c);
    }

    .onboarding-text {
        margin: 0 0 12px;
        font-size: 14px;
        line-height: 1.55;
        color: var(--fg-3a5a4a, #3a5a4a);
    }

    .onboarding-points {
        display: grid;
        gap: 8px;
        margin: 0 0 14px;
        padding: 0;
        list-style: none;
    }

    .onboarding-points li {
        position: relative;
        padding: 9px 12px 9px 14px;
        border: 1px solid var(--bd-e0ebe2, #e0ebe2);
        border-left: 3px solid #1e9e62;
        border-radius: 10px;
        background: var(--bg-f8fbf9, #f8fbf9);
        font-size: 13px;
        line-height: 1.45;
        color: var(--fg-2f4a3a, #2f4a3a);
    }

    .onboarding-points li .bi {
        margin-right: 4px;
        color: var(--fg-1e7a4f, #1e7a4f);
    }

    .onboarding-points strong,
    .onboarding-text strong {
        color: var(--fg-10301c, #10301c);
    }

    .onboarding-points code {
        font-size: 12px;
    }

    .onboarding-pill {
        display: inline-block;
        margin-right: 6px;
        padding: 1px 8px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
    }

    .onboarding-pill.is-pending {
        background: var(--bg-fdf3e2, #fdf3e2);
        color: var(--fg-9a5b0b, #9a5b0b);
    }

    .onboarding-pill.is-approved {
        background: var(--bg-e4f5eb, #e4f5eb);
        color: var(--fg-14734a, #14734a);
    }

    .onboarding-pill.is-rejected {
        background: var(--bg-fdeaea, #fdeaea);
        color: var(--fg-b0392b, #b0392b);
    }

    .onboarding-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-bottom: 8px;
        color: var(--fg-1e7a4f, #1e7a4f);
        font-size: 13px;
        font-weight: 800;
        text-decoration: none;
    }

    .onboarding-link:hover {
        text-decoration: underline;
    }

    .onboarding-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
        padding: 14px 22px 18px;
        border-top: 1px solid var(--bd-edf3ef, #edf3ef);
    }

    .onboarding-dots {
        display: flex;
        gap: 6px;
    }

    .onboarding-dot {
        width: 8px;
        height: 8px;
        padding: 0;
        border: 0;
        border-radius: 999px;
        background: var(--bg-cfdcd3, #cfdcd3);
        cursor: pointer;
        transition: width .2s ease, background .2s ease;
    }

    .onboarding-dot.is-active {
        width: 22px;
        background: #1e9e62;
    }

    .onboarding-dot:focus-visible {
        outline: 3px solid rgba(30, 158, 98, .35);
        outline-offset: 2px;
    }

    .onboarding-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-left: auto;
    }

    .onboarding-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-height: 40px;
        padding: 8px 16px;
        border: 1px solid var(--bd-d4e0d4, #d4e0d4);
        border-radius: 10px;
        background: var(--bg-ffffff, #ffffff);
        color: var(--fg-1a2e1a, #1a2e1a);
        font: inherit;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
    }

    .onboarding-btn[hidden] {
        display: none;
    }

    .onboarding-btn:focus-visible,
    .onboarding-close:focus-visible,
    .onboarding-link:focus-visible {
        outline: 3px solid rgba(30, 158, 98, .4);
        outline-offset: 2px;
    }

    .onboarding-btn:hover {
        border-color: #1e9e62;
        color: var(--fg-1e9e62, #1e9e62);
    }

    .onboarding-btn.is-primary {
        border-color: #1e9e62;
        background: #1e9e62;
        color: #ffffff;
    }

    .onboarding-btn.is-primary:hover {
        background: #16824f;
        color: #ffffff;
    }

    .onboarding-btn.is-ghost {
        border-color: transparent;
        background: transparent;
        color: var(--fg-64748b, #64748b);
    }

    .onboarding-btn.is-ghost:hover {
        color: var(--fg-1a2e1a, #1a2e1a);
    }

    /* "Show the welcome tour" (Help & Guide). */
    .onboarding-replay {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 8px 14px;
        border: 1px solid var(--bd-bce6cf, #bce6cf);
        border-radius: 10px;
        background: var(--bg-edf7f2, #edf7f2);
        color: var(--fg-14734a, #14734a);
        font: inherit;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
    }

    .onboarding-replay:hover {
        border-color: #1e9e62;
    }

    /* Beside the "Showing the guide for" chip in the guide's header. */
    .guide-hero .onboarding-replay {
        margin: 12px 0 0 6px;
        padding: 5px 12px;
        font-size: 12px;
    }

    @media (max-width: 480px) {
        .onboarding-step {
            padding: 8px 18px 4px;
        }

        .onboarding-top {
            padding-left: 18px;
        }

        .onboarding-foot {
            padding: 12px 16px 16px;
        }

        /* Dots on their own line above the buttons, which share the width. */
        .onboarding-dots {
            width: 100%;
            justify-content: center;
        }

        .onboarding-actions {
            width: 100%;
        }

        .onboarding-actions .onboarding-btn:not(.is-ghost) {
            flex: 1;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .onboarding-step {
            animation: none;
        }
    }
</style>

<script>
    (() => {
        const dialog = document.getElementById('onboardingDialog');
        if (!dialog || typeof dialog.showModal !== 'function') return;

        const steps = [...dialog.querySelectorAll('[data-onboarding-step]')];
        const dots = [...dialog.querySelectorAll('[data-onboarding-dot]')];
        const back = dialog.querySelector('[data-onboarding-back]');
        const next = dialog.querySelector('[data-onboarding-next]');
        const skip = document.getElementById('onboardingSkip');
        const count = document.getElementById('onboardingCount');
        const completeUrl = @json(route('onboarding.complete'));
        const resumeBar = document.getElementById('onboardingResume');
        const resumeStep = document.getElementById('onboardingResumeStep');
        let current = 0;
        let completed = dialog.dataset.autoOpen !== '1';

        // Where the user is in the tour, per account in this browser: {step, resume}. "resume" is set when a
        // "Go to ..." link leaves the page, so the next page offers to continue at that same step.
        const storageKey = `mangrovemap-onboarding:${dialog.dataset.user}`;
        function readProgress() {
            try {
                const saved = JSON.parse(localStorage.getItem(storageKey) || 'null');
                return saved && (saved.done === true || Number.isInteger(saved.step)) ? saved : null;
            } catch (e) {
                return null;
            }
        }
        function saveProgress(progress) {
            try {
                if (progress) localStorage.setItem(storageKey, JSON.stringify(progress));
                else localStorage.removeItem(storageKey);
            } catch (e) {
                // Storage blocked: the tour still works, it just starts at the first step next time.
            }
        }

        // Remember on the account that the tour is done (finished, skipped or ended), so it stops opening by itself.
        // The browser also notes "done" first, so a failed save (offline, server error) never brings the tour back:
        // the note keeps it closed and the next page sends the save again. It is cleared once the server has it.
        function markComplete() {
            if (completed) return;
            completed = true;
            saveProgress({ done: true });
            sendComplete();
        }

        function sendComplete() {
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            fetch(completeUrl, {
                method: 'POST',
                keepalive: true, // still sent if the user leaves the page right away
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token || '' },
            }).then(response => {
                if (response.ok && readProgress()?.done) saveProgress(null);
            }).catch(() => {});
        }

        function show(index) {
            const target = Math.max(0, Math.min(steps.length - 1, index));
            dialog.style.setProperty('--onboarding-from', target >= current ? '14px' : '-14px');
            current = target;
            steps.forEach((step, i) => { step.hidden = i !== current; });
            dots.forEach((dot, i) => {
                dot.classList.toggle('is-active', i === current);
                if (i === current) dot.setAttribute('aria-current', 'step');
                else dot.removeAttribute('aria-current');
            });
            dialog.setAttribute('aria-labelledby', `onboardingTitle${current}`);
            count.textContent = `Step ${current + 1} of ${steps.length}`;
            back.hidden = current === 0;
            const last = current === steps.length - 1;
            next.innerHTML = last ? 'Finish <i class="bi bi-check2" aria-hidden="true"></i>' : 'Next <i class="bi bi-arrow-right" aria-hidden="true"></i>';
            skip.hidden = last;
            if (dialog.open) saveProgress({ step: current, resume: false });
        }

        function open(step = 0) {
            document.querySelector('.sidebar')?.classList.remove('active'); // mobile slide-out menu
            resumeBar.hidden = true;
            if (!dialog.open) dialog.showModal();
            show(step);
            next.focus();
        }

        // Finished, skipped or ended: done for this account, and nothing left to resume.
        function close() {
            resumeBar.hidden = true;
            if (dialog.open) dialog.close();
            saveProgress(null); // nothing left to resume
            if (completed) return; // a replay of a tour the account already finished
            markComplete();
        }

        function offerResume(step) {
            const index = Math.max(0, Math.min(steps.length - 1, step));
            resumeStep.textContent = `(Step ${index + 1} of ${steps.length})`;
            resumeBar.dataset.step = String(index);
            resumeBar.hidden = false;
        }

        next.addEventListener('click', () => (current === steps.length - 1 ? close() : show(current + 1)));
        back.addEventListener('click', () => show(current - 1));
        dots.forEach((dot, i) => dot.addEventListener('click', () => show(i)));
        dialog.querySelectorAll('[data-onboarding-skip]').forEach(button => button.addEventListener('click', close));
        // "Go to ...": not done yet. The next page opens as normal and offers to continue at this same step.
        dialog.querySelectorAll('[data-onboarding-goto]').forEach(link => link.addEventListener('click', () => {
            saveProgress({ step: current, resume: true });
        }));
        document.getElementById('onboardingResumeGo').addEventListener('click', () => open(Number(resumeBar.dataset.step) || 0));
        document.getElementById('onboardingResumeClose').addEventListener('click', close);
        // Esc counts as skipping it.
        dialog.addEventListener('cancel', event => {
            event.preventDefault();
            close();
        });
        dialog.addEventListener('keydown', event => {
            if (event.target.closest('a, input, textarea')) return;
            if (event.key === 'ArrowRight') show(current + 1);
            if (event.key === 'ArrowLeft') show(current - 1);
        });

        document.addEventListener('click', event => {
            if (event.target.closest('[data-onboarding-open]')) {
                event.preventDefault();
                open();
            }
        });

        const progress = readProgress();
        if (progress?.done) {
            // Finished in this browser but the server didn't confirm it yet: stay closed and save it again.
            if (dialog.dataset.autoOpen === '1') sendComplete();
        } else if (progress?.resume) {
            // Came here through a "Go to ..." link (also while replaying it): let them look, then continue.
            offerResume(progress.step);
        } else if (dialog.dataset.autoOpen === '1') {
            // Not done yet: open by itself, at the step they were on. After the page has settled, and over the
            // map pages' own quick guide card.
            const start = () => setTimeout(() => open(progress?.step ?? 0), 350);
            if (document.readyState === 'complete') start();
            else window.addEventListener('load', start, { once: true });
        }
    })();
</script>
@endif
