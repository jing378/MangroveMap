<?php
// EXAMPLE: How to use notifications in ClassifyController

namespace App\Http\Controllers;

use App\Models\Analysis;
use App\Models\User;
use App\Notifications\AnalysisCompleted;
use App\Notifications\AdminAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;

class ClassifyController extends Controller
{
    public function create()
    {
        return view('classify.upload');
    }

    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240'
        ]);

        // Store image
        $path = $request->file('image')->store('classifications', 'public');

        // Create analysis record
        $analysis = Analysis::create([
            'user_id' => Auth::id(),
            'image_url' => Storage::url($path),
            'analysis_type' => 'classification',
            'status' => 'processing'
        ]);

        // ========== NOTIFY ADMINS OF NEW ANALYSIS ==========
        $admins = User::where('role', 'admin')->get();
        if ($admins->count() > 0) {
            Notification::send($admins, new AdminAlert(
                title: 'New Analysis Submitted',
                message: 'User ' . Auth::user()->name . ' has submitted a new image for classification.',
                type: 'new_analysis',
                actionUrl: '/admin/analyses/' . $analysis->id,
                actionLabel: 'View Analysis'
            ));
        }

        return redirect()->route('classify.results', $analysis->id);
    }

    public function results(Analysis $analysis)
    {
        return view('classify.results', ['analysis' => $analysis]);
    }

    /**
     * This method should be called by your AI processing job/queue
     * When the analysis is complete
     */
    public function completeAnalysis(Analysis $analysis, array $results)
    {
        $analysis->update([
            'results' => $results,
            'status' => 'completed'
        ]);

        // ========== NOTIFY USER OF COMPLETION ==========
        $analysis->user->notify(new AnalysisCompleted(
            'Image Classification',
            $analysis->id
        ));

        // ========== NOTIFY ADMINS OF COMPLETION ==========
        $admins = User::where('role', 'admin')->get();
        if ($admins->count() > 0) {
            Notification::send($admins, new AdminAlert(
                title: 'Analysis Completed',
                message: 'The classification analysis submitted by ' . $analysis->user->name . ' has been completed.',
                type: 'analysis_completed',
                actionUrl: '/admin/analyses/' . $analysis->id,
                actionLabel: 'View Result'
            ));
        }
    }

    /**
     * If analysis processing fails
     */
    public function failAnalysis(Analysis $analysis, string $errorMessage)
    {
        $analysis->update([
            'status' => 'failed',
            'results' => ['error' => $errorMessage]
        ]);

        // ========== NOTIFY USER OF FAILURE ==========
        $analysis->user->notify(new AdminAlert(
            title: 'Analysis Failed',
            message: 'Sorry, your analysis could not be processed. Error: ' . $errorMessage,
            type: 'analysis_failed'
        ));

        // ========== NOTIFY ADMINS OF ERROR ==========
        $admins = User::where('role', 'admin')->get();
        if ($admins->count() > 0) {
            Notification::send($admins, new AdminAlert(
                title: 'Analysis Processing Error',
                message: 'Analysis #' . $analysis->id . ' failed to process. Error: ' . $errorMessage,
                type: 'error',
                actionUrl: '/admin/analyses/' . $analysis->id
            ));
        }
    }
}

// ============================================
// EXAMPLE: Usage in AuthController or UserController
// ============================================

/**
 * When a new user registers
 */
public function register(Request $request)
{
    // ... validation and user creation code ...
    
    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'password' => bcrypt($request->password),
        'role' => 'end_user'
    ]);

    // ========== NOTIFY ADMINS OF NEW USER ==========
    $admins = User::where('role', 'admin')->get();
    if ($admins->count() > 0) {
        Notification::send($admins, new AdminAlert(
            title: 'New User Registration',
            message: $user->name . ' (' . $user->email . ') has registered on the platform.',
            type: 'new_user',
            actionUrl: '/admin/users/' . $user->id,
            actionLabel: 'View User'
        ));
    }

    return redirect('/dashboard')->with('success', 'Account created!');
}

// ============================================
// EXAMPLE: Usage in AdminController
// ============================================

/**
 * Send custom notification to all users
 */
public function broadcastNotification(Request $request)
{
    $request->validate([
        'message' => 'required|string',
        'title' => 'required|string',
        'target_users' => 'required|in:all,end_users,admins'
    ]);

    $users = match ($request->target_users) {
        'end_users' => User::where('role', 'end_user')->get(),
        'admins' => User::where('role', 'admin')->get(),
        'all' => User::all(),
    };

    Notification::send($users, new AdminAlert(
        title: $request->title,
        message: $request->message,
        type: 'system_message'
    ));

    return back()->with('success', 'Notification sent to ' . $users->count() . ' users!');
}

// ============================================
// EXAMPLE: Sending notifications from a Job/Queue
// ============================================

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

class ProcessImageAnalysis implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Analysis $analysis)
    {
    }

    public function handle()
    {
        // Do your heavy processing here
        $results = $this->callAIModel($this->analysis->image_url);

        if ($results['success']) {
            $this->analysis->update([
                'results' => $results['data'],
                'status' => 'completed'
            ]);

            // ========== NOTIFY USER ==========
            $this->analysis->user->notify(new AnalysisCompleted(
                'Image Analysis Complete',
                $this->analysis->id
            ));
        } else {
            $this->analysis->update(['status' => 'failed']);

            // ========== NOTIFY USER OF FAILURE ==========
            $this->analysis->user->notify(new AdminAlert(
                title: 'Analysis Failed',
                message: 'We were unable to process your analysis. Please try again.',
                type: 'analysis_failed'
            ));
        }
    }

    private function callAIModel(string $imagePath): array
    {
        // Your AI model logic here
        return ['success' => true, 'data' => []];
    }
}
