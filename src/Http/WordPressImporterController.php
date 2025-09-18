<?php

declare(strict_types=1);

namespace Pete\WordPressImporter\Http;

use App\Models\Site;
use App\Services\OServer;
use App\Services\PeteOption;
use App\Services\PeteService;
use Illuminate\Contracts\View\View as View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;

/**
 * Handles the "Import WordPress" flow (form + submission).
 *
 * Responsibilities:
 *  - Gate access behind authentication.
 *  - Show the import form.
 *  - Validate inputs and normalize the chosen source (upload or server path).
 *  - Kick off the import using Site::import_wordpress().
 *  - Bounce the user to the live logs page for progress tracking.
 */
final class WordPressImporterController extends Controller
{
    /**
     * Where uploaded archives are stored (relative to storage/app).
     */
    private const UPLOAD_DIR = 'wordpress-imports';

    /**
     * Pete core services available for import routines and orchestration.
     */
    private PeteService $pete;

    /**
     * Ensure routes using this controller are authenticated and inject PeteService.
     */
    public function __construct(PeteService $pete)
    {
        // Protect every action with the "auth" middleware.
        $this->middleware('auth')->except(['status']); 
        $this->pete = $pete;
    }

    /**
     * Stores chunks to storage/app/wordpress-imports/. On finish returns the ABSOLUTE PATH
     * so the importer can use the "Server path" branch (big_file_route).
     */
    public function upload(Request $request)
    {
        // 0) Make sure the chunk root exists (avoid mkdir race)
        $disk = config('chunk-upload.storage.disk', 'local');
        $root = config('chunk-upload.storage.chunks', 'chunks');
        try {
            if (!Storage::disk($disk)->exists($root)) {
                Storage::disk($disk)->makeDirectory($root);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed ensuring chunk root', ['message' => $e->getMessage()]);
        }

        // 1) Create the receiver
        $receiver = new FileReceiver('file', $request, HandlerFactory::classFromRequest($request));

        if (!$receiver->isUploaded()) {
            return response()->json(['error' => 'No file uploaded.'], 400);
        }

        // 2) Receive with a tiny retry to dodge mkdir() race
        $save = null;
        for ($i = 0; $i < 3; $i++) {
            try {
                $save = $receiver->receive();
                break; // success
            } catch (\Throwable $e) {
                if (str_contains($e->getMessage(), 'File exists')) {
                    usleep(100_000); // 100ms backoff
                    continue;
                }
                throw $e; // different error – bubble up
            }
        }
        if (!$save) {
            Log::error('Chunk receive failed after retries');
            return response()->json(['error' => 'Upload failed while preparing chunk.'], 500);
        }

        if ($save->isFinished()) {
            $file = $save->getFile();
            $ext  = $file->getClientOriginalExtension() ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION);
            $name = \Illuminate\Support\Str::random(40) . ($ext ? ('.' . strtolower($ext)) : '');

            $storedPath = Storage::disk('local')->putFileAs('wordpress-imports', $file, $name);
            @unlink($file->getPathname());

            return response()->json([
                'done'      => true,
                'filename'  => $name,
                'path'      => Storage::path($storedPath),
                'size'      => (int) ($request->input('resumableTotalSize') ?? 0),
                'stored_as' => $storedPath
            ]);
        }

        $handler = $save->handler();
        return response()->json([
            'done'       => false,
            'percentage' => $handler->getPercentageDone(),
        ]);
    }

    /**
     * Optional: allow client to abort and clean partial data (best-effort).
     */
    public function abort(Request $request)
    {
        // You can also remove partial temp files if your handler leaves any on disk.
        return response()->json(['ok' => true]);
    }

    /**
     * Show the "Import WordPress" form.
     *
     * @return ViewContract
     */
    public function create(): View
    {
        // These vars are used by the Blade view (vendor/.../views/create.blade.php).
        $currentUser  = Auth::user();
        $viewsw       = '/wordpress-importer'; // Keeps parity with your existing layout usage.
        $pete_options = app(PeteOption::class); // Blade references $pete_options->get_meta_value('domain_template').

        return view('wordpress-importer-plugin::create', compact('currentUser', 'viewsw', 'pete_options'));
    }

    /**
     * Handle the import submission.
     *
     * Validates inputs, resolves the archive source (upload vs server path),
     * creates the Site, triggers the import, reloads the server (vhosts/containers),
     * and redirects to the per-site logs page.
     *
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        // 0) Normalize URL with domain template before validating
        $tpl     = app(PeteOption::class)->get_meta_value('domain_template');
        $fullUrl = $this->pete->normalizeUrlWithTemplate($request->input('url', ''), (string) $tpl);
        $input   = array_replace($request->all(), ['url' => $fullUrl]);

        // 1) Validate (mirror your current rules)
        $v = Validator::make($input, [
            'url'            => ['required', 'max:255', 'regex:/^[a-z0-9\-\.]+$/i', Rule::unique('sites','url')],
            'backup_file'    => ['nullable', 'file', 'mimes:zip,tar,gz,tgz'],
            'big_file_route' => ['nullable', 'string'],
        ]);
        if ($v->fails()) {
            return $this->pete->fail($request, 'Validation failed.', $v->errors()->toArray(), 422);
        }

        // 2) Enforce XOR: upload OR server path
        /** @var UploadedFile|null $uploaded */
        $uploaded  = $request->file('backup_file');
        $serverRaw = $input['big_file_route'] ?? null;

        if (blank($uploaded) && blank($serverRaw)) {
            return $this->pete->fail($request, 'Upload a backup file or specify a server path.', [
                'backup_file'    => ['Provide a file or use a server path.'],
                'big_file_route' => ['Provide a server path or upload a file.'],
            ], 422);
        }
        if (!blank($uploaded) && !blank($serverRaw)) {
            return $this->pete->fail($request, 'Choose either the upload OR the server path — not both.', [
                'backup_file'    => ['Remove this if using a server path.'],
                'big_file_route' => ['Remove this if uploading a file.'],
            ], 422);
        }

        // 3) Resolve archive file path
        $templateFile = $this->resolveArchivePath($uploaded, (string) $serverRaw);
        if (!is_string($templateFile)) {
            return $this->pete->fail($request, 'Failed to access the archive. Please check permissions and try again.', [], 422);
        }

        // 4) Business guard
        if ($this->pete->isTheURLForbidden($fullUrl)) {
            return $this->pete->fail($request, 'URL forbidden.', [
                'url' => ['This URL is not allowed.'],
            ], 422);
        }

        // ==== fire-and-return (like clone) ====================================
        $jobId     = (string) \Illuminate\Support\Str::uuid();
        $statusDir = storage_path('app/import-jobs');
        @mkdir($statusDir, 0775, true);

        // seed queued
        @file_put_contents("{$statusDir}/{$jobId}.json", json_encode([
            'status'     => 'queued',
            'progress'   => 0,
            'message'    => 'Queued',
            'created_at' => now()->toISOString(),
        ], JSON_PRETTY_PRINT));

        // Build detached command
        $php = trim((string) @shell_exec('command -v php 2>/dev/null')) ?: '/usr/bin/php';
        if (!is_executable($php)) $php = 'php';

        $runner = base_path('bootstrap/run_import.php');
        $dest   = $fullUrl;
        $userId = (int) \Auth::id();

        $cmd = sprintf(
            'cd %s && nohup %s %s --dest=%s --user=%d --template=%s --job=%s > /dev/null 2>&1 &',
            escapeshellarg(base_path()),
            escapeshellcmd($php),
            escapeshellarg($runner),
            escapeshellarg($dest),
            $userId,
            escapeshellarg($templateFile),
            escapeshellarg($jobId)
        );
        @shell_exec($cmd);

        $payload = [
            'error'      => false,
            'message'    => 'Import enqueued.',
            'job_id'     => $jobId,
            'status_url' => route('wpimport.status', $jobId),
        ];

        // Prefer JSON for XHR
        if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
            return response()->json($payload, 202);
        }

        // Non-AJAX fallback
        return redirect()->route('sites.logs', 0)->with('status', $payload['message']);
    }

    /**
     * Poller endpoint: GET /wordpress-importer/status/{id}
     */
    public function status(string $id): \Illuminate\Http\JsonResponse
    {
        $path = storage_path("app/import-jobs/{$id}.json");
        if (!is_file($path)) {
            return response()->json(['error' => true, 'message' => 'Not found'], 404);
        }
        $data = json_decode((string) file_get_contents($path), true) ?: [];
        // Optional: add ownership checks if desired.
        return response()->json($data + ['job_id' => $id]);
    }


    /**
     * Returns absolute readable path to the archive (string) or false on failure.
     */
    private function resolveArchivePath(?\Illuminate\Http\UploadedFile $uploaded, ?string $serverPath)
    {
        try {
            if ($uploaded) {
                $ext      = (string) $uploaded->getClientOriginalExtension();
                $filename = sprintf('%s.%s', Str::random(40), $ext);
                $stored   = $uploaded->storeAs(self::UPLOAD_DIR, $filename);
                $path     = Storage::path($stored);

                if (!is_file($path) || !is_readable($path)) {
                    Log::error('Uploaded archive is not readable after store.', ['path' => $path, 'user_id' => Auth::id()]);
                    return false;
                }
                return $path;
            }

            $serverPath = trim((string) $serverPath);
            $real       = @realpath($serverPath);
            $path       = $real ?: $serverPath;

            if (!is_file($path) || !is_readable($path)) {
                Log::warning('Server path not readable or not a file.', ['path' => $serverPath, 'real' => $real, 'user_id' => Auth::id()]);
                return false;
            }
            return $path;

        } catch (\Throwable $e) {
            Log::error('Failed to resolve template archive for import.', ['exception' => $e, 'user_id' => Auth::id()]);
            return false;
        }
    }
}
