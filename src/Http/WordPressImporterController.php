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
        $this->middleware('auth');

        $this->pete = $pete;
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
    public function store(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        // Helper to return JSON when requested (XHR/Accept: application/json), else redirect back
        $fail = function (string $message, array $errors = [], int $status = 422) use ($request) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'error'   => true,
                    'message' => $message,
                    'errors'  => $errors,
                ], $status);
            }
            return back()->withInput()->withErrors($message);
        };

        // ---- 0) Normalize + append domain template BEFORE validation -----------------
        $peteOptions    = app(PeteOption::class);
        $domainTemplate = (string) ($peteOptions->get_meta_value('domain_template') ?? '');

        // Normalize raw input to a host-like token (no scheme/path), lowercase
        $rawUrl = (string) $request->input('url', '');
        $host   = strtolower(trim(preg_replace('#^https?://#i', '', $rawUrl))); // strip scheme
        $host   = trim($host, " \t\n\r\0\x0B/"); // trim whitespace & trailing slashes

        // If a template exists (and not 'none'), treat the user's input as a subdomain
        // unless they already provided the full host ending with the template.
        if ($domainTemplate !== '' && $domainTemplate !== 'none') {
            $needsAppend = !str_ends_with($host, '.'.$domainTemplate);
            // Only auto-append when the user typed just a subdomain (no dot present)
            if ($needsAppend && strpos($host, '.') === false) {
                $host = "{$host}.{$domainTemplate}";
            }
        }

        // Build a payload overriding the incoming 'url' with our normalized/templated host
        $payload          = $request->all();
        $payload['url']   = $host;

        // ---- 1) Validate (no auto-redirects) ----------------------------------------
        $validator = \Illuminate\Support\Facades\Validator::make($payload, [
            'url'            => [
                'required',
                'max:255',
                'regex:/^[a-z0-9\-\.]+$/i',      // host-like (no scheme/slashes)
                Rule::unique('sites', 'url'),
            ],
            'backup_file'    => ['nullable', 'file', 'mimes:zip,tar,gz,tgz'],
            'big_file_route' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $fail('Validation failed.', $validator->errors()->toArray(), 422);
        }

        $data      = $validator->validated();
        /** @var UploadedFile|null $uploaded */
        $uploaded  = $request->file('backup_file');
        $serverRaw = $data['big_file_route'] ?? null;

        // ---- 2) Enforce XOR for source (upload vs path) ------------------------------
        if (blank($uploaded) && blank($serverRaw)) {
            return $fail('Upload a backup file or specify a server path.', [
                'backup_file'    => ['Provide a file or use a server path.'],
                'big_file_route' => ['Provide a server path or upload a file.'],
            ], 422);
        }
        if (!blank($uploaded) && !blank($serverRaw)) {
            return $fail('Choose either the upload OR the server path — not both.', [
                'backup_file'    => ['Remove this if using a server path.'],
                'big_file_route' => ['Remove this if uploading a file.'],
            ], 422);
        }

        // ---- 3) Resolve absolute archive path ---------------------------------------
        try {
            if ($uploaded instanceof UploadedFile) {
                $ext         = (string) $uploaded->getClientOriginalExtension();
                $filename    = sprintf('%s.%s', Str::random(40), $ext);
                $stored      = $uploaded->storeAs(self::UPLOAD_DIR, $filename);
                $templateFile = Storage::path($stored);

                if (!is_file($templateFile) || !is_readable($templateFile)) {
                    Log::error('Uploaded archive is not readable after store.', [
                        'path'    => $templateFile,
                        'user_id' => Auth::id(),
                    ]);
                    return $fail('The uploaded file could not be accessed. Please try again.', [
                        'backup_file' => ['Stored file is not readable.'],
                    ], 422);
                }
            } else {
                $serverPath   = trim((string) $serverRaw);
                $real         = @realpath($serverPath);
                $templateFile = $real ?: $serverPath;

                if (!is_file($templateFile) || !is_readable($templateFile)) {
                    Log::warning('Server path not readable or not a file.', [
                        'path'    => $serverPath,
                        'real'    => $real,
                        'user_id' => Auth::id(),
                    ]);
                    return $fail('The specified server file does not exist or is not readable.', [
                        'big_file_route' => ['Path does not exist or is not readable.'],
                    ], 422);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Failed to resolve template archive for import.', [
                'exception' => $e,
                'user_id'   => Auth::id(),
            ]);
            return $fail('Failed to access the archive. Please check permissions and try again.', [], 422);
        }

        // ---- 4) Create site & kick off import ---------------------------------------
        try {
            $site = new Site();
            // We already normalized/templated the URL, so just set it
            $site->url = $data['url'];

            $site->import_wordpress([
                'template'    => $templateFile,
                'theme'       => 'custom',
                'user_id'     => Auth::id(),
                'site_url'    => $site->url,
                'action_name' => 'Import',
            ]);

            OServer::reload_server();

            Log::info('WordPress import started.', [
                'site_id'  => $site->id ?? null,
                'site_url' => $site->url ?? null,
                'user_id'  => Auth::id(),
                'source'   => $uploaded instanceof UploadedFile ? 'upload' : 'path',
            ]);

            $payload = [
                'error'    => false,
                'message'  => 'Import started — check the logs for progress.',
                'site_id'  => $site->id,
                'redirect' => route('sites.logs', $site),
            ];

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json($payload, 201);
            }
            return redirect()->route('sites.logs', $site)->with('status', $payload['message']);

        } catch (\Throwable $e) {
            Log::error('WordPress import failed to start.', [
                'exception' => $e,
                'user_id'   => Auth::id(),
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'error'   => true,
                    'message' => 'The import could not be started. Please check server logs and try again.',
                ], 500);
            }
            return back()->withInput()->withErrors('The import could not be started. Please check the server logs and try again.');
        }
    }

}
