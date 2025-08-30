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
    public function store(Request $request): RedirectResponse
    {
        // ---------------------------------------------------------------------
        // 1) Validate incoming data
        // ---------------------------------------------------------------------
        // Notes:
        //  - 'url' accepts letters/digits/dots/dashes (no scheme); unique in "sites.url".
        //  - backup_file mimes expanded to match the UI: .zip, .tar, .gz, .tgz, .tar.gz
        //  - big_file_route is an absolute path to an existing, readable file.
        $data = $request->validate([
            'url'            => [
                'required',
                'max:255',
                'regex:/^[a-z0-9\-\.]+$/i', // only host-like tokens (no protocol, no slashes)
                Rule::unique('sites', 'url'),
            ],
            'backup_file'    => ['nullable', 'file', 'mimes:zip,tar,gz,tgz'], // UI accepts these
            'big_file_route' => ['nullable', 'string'],
        ]);

        /** @var UploadedFile|null $uploaded */
        $uploaded  = $request->file('backup_file');              // null if not provided
        $serverRaw = $data['big_file_route'] ?? null;            // null if not provided

        // Enforce XOR: exactly one source must be provided.
        if (blank($uploaded) && blank($serverRaw)) {
            return back()
                ->withInput()
                ->withErrors('Upload a backup file or specify a server path.');
        }

        if (!blank($uploaded) && !blank($serverRaw)) {
            return back()
                ->withInput()
                ->withErrors('Choose either the upload OR the server path — not both.');
        }

        // ---------------------------------------------------------------------
        // 2) Resolve absolute template path (the archive we will import)
        // ---------------------------------------------------------------------
        $templateFile = null;

        try {
            if ($uploaded instanceof UploadedFile) {
                // Persist the uploaded file under storage/app/wordpress-imports/<random>.<ext>
                $ext      = (string) $uploaded->getClientOriginalExtension(); // keep original extension
                $filename = sprintf('%s.%s', Str::random(40), $ext);
                $stored   = $uploaded->storeAs(self::UPLOAD_DIR, $filename);

                // Resolve to an absolute filesystem path (e.g., /var/www/.../storage/app/wordpress-imports/...)
                $templateFile = Storage::path($stored);

                // Quick guard: ensure file really exists and is readable
                if (!is_file($templateFile) || !is_readable($templateFile)) {
                    // Extremely rare; storage misconfig, permissions, or FS error
                    Log::error('Uploaded archive is not readable after store.', [
                        'path' => $templateFile,
                        'user_id' => Auth::id(),
                    ]);

                    return back()
                        ->withInput()
                        ->withErrors('The uploaded file could not be accessed. Please try again.');
                }
            } else {
                // The user provided a server path; normalize and verify.
                $serverPath   = trim((string) $serverRaw);
                $real         = realpath($serverPath); // canonicalize symlinks/relative segments
                $templateFile = $real ?: $serverPath;  // fall back to raw if realpath fails (e.g., permissions)

                // Check existence, that it is a file (not a dir), and readability
                if (!is_file($templateFile) || !is_readable($templateFile)) {
                    Log::warning('Server path not readable or not a file.', [
                        'path'    => $serverPath,
                        'real'    => $real,
                        'user_id' => Auth::id(),
                    ]);

                    return back()
                        ->withInput()
                        ->withErrors('The specified server file does not exist or is not readable.');
                }
            }
        } catch (\Throwable $e) {
            // Catch any unexpected filesystem issues early and report gracefully.
            Log::error('Failed to resolve template archive for import.', [
                'exception' => $e,
                'user_id'   => Auth::id(),
            ]);

            return back()
                ->withInput()
                ->withErrors('Failed to access the archive. Please check permissions and try again.');
        }

        // ---------------------------------------------------------------------
        // 3) Create Site model and kick off the import
        // ---------------------------------------------------------------------
        try {
            // Create a new site; set_url() is assumed to handle domain templates internally.
            $site = new Site();
            $site->set_url($data['url']);

            // Start the import. Expected to:
            //  - Unpack/process the archive
            //  - Provision vhost/containers
            //  - Apply Pete/WordPress configurations
            //  - Run any migration steps
            $site->import_wordpress([
                'template'    => $templateFile,     // absolute path to the archive
                'theme'       => 'custom',          // retained from your original behavior
                'user_id'     => Auth::id(),
                'site_url'    => $site->url,        // destination URL
                'action_name' => 'Import',          // for logs/auditing
            ]);

            // After provisioning, reload services so new vhost/containers are live immediately.
            OServer::reload_server();

            Log::info('WordPress import started.', [
                'site_id' => $site->id ?? null,
                'site_url' => $site->url ?? null,
                'user_id' => Auth::id(),
                'source' => $uploaded instanceof UploadedFile ? 'upload' : 'path',
            ]);
        } catch (\Throwable $e) {
            // Surface a friendly error to the user and keep details in logs.
            Log::error('WordPress import failed to start.', [
                'exception' => $e,
                'user_id'   => Auth::id(),
            ]);

            return back()
                ->withInput()
                ->withErrors('The import could not be started. Please check the server logs and try again.');
        }

        // ---------------------------------------------------------------------
        // 4) Redirect the user to the per-site logs page
        // ---------------------------------------------------------------------
        // The Vue form uses XHR and will follow the responseURL, but this also
        // works fine for non-XHR submissions.
        return redirect()
            ->route('sites.logs', $site)
            ->with('status', 'Import started — check the logs for progress.');
    }
}
