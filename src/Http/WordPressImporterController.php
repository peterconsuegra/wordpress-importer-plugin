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
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        // 0) Normalize URL with domain template before validating
        $tpl     = app(PeteOption::class)->get_meta_value('domain_template');
        $fullUrl = $this->pete->normalizeUrlWithTemplate($request->input('url', ''), (string) $tpl);
        $input   = array_replace($request->all(), ['url' => $fullUrl]);

        // 1) Validate inputs (no auto-redirects)
        $v = Validator::make($input, [
            'url'            => ['required', 'max:255', 'regex:/^[a-z0-9\-\.]+$/i', Rule::unique('sites','url')],
            'backup_file'    => ['nullable', 'file', 'mimes:zip,tar,gz,tgz'],
            'big_file_route' => ['nullable', 'string'],
        ]);

        if ($v->fails()) {
            return $this->pete->fail($request, 'Validation failed.', $v->errors()->toArray(), 422);
        }

        // 2) Enforce XOR: upload OR server path
        /** @var \Illuminate\Http\UploadedFile|null $uploaded */
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

        // 3) Resolve archive path (uploaded or server file)
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

        try {
            // 5) Persist site FIRST so we always have an ID for routing/redirects
            $site       = new Site();
            $site->url  = $fullUrl;
            $site->user_id = Auth::id(); // optional if your schema has this
            $site->save();

            // 6) Start import
            $site->import_wordpress([
                'template'    => $templateFile,
                'theme'       => 'custom',
                'user_id'     => Auth::id(),
                'site_url'    => $site->url,
                'action_name' => 'Import',
            ]);

            // Optionally reload vhosts/containers
            OServer::reload_server();

            $redirectUrl = route('sites.logs', $site->id);

            // Prefer JSON for XHR (Vue sets Accept + X-Requested-With)
            if ($request->expectsJson() || $request->ajax() || $request->wantsJson()) {
                return response()
                    ->json([
                        'error'    => false,
                        'message'  => 'Import started — check the logs for progress.',
                        'site_id'  => $site->id,
                        'redirect' => $redirectUrl,
                    ], 200)
                    ->header('X-Redirect', $redirectUrl); // helpful for frontends
            }

            // Non-AJAX fallback
            return redirect()
                ->route('sites.logs', $site->id, absolute: false) // relative path => stable in tests/containers
                ->setStatusCode(303);  

        } catch (\Throwable $e) {
            Log::error('WordPress import failed to start.', [
                'exception' => $e,
                'user_id'   => Auth::id(),
            ]);

            $msg = 'The import could not be started. Please check server logs and try again.';

            return ($request->expectsJson() || $request->ajax() || $request->wantsJson())
                ? response()->json(['error' => true, 'message' => $msg], 500)
                : back()->withInput()->withErrors($msg);
        }
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
