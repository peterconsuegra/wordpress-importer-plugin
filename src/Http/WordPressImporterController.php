<?php


namespace Pete\WordPressImporter\Http;

use App\Http\Controllers\PeteController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Http\UploadedFile;
use App\Site;
use App\OServer;
use Log;
use View;

class WordPressImporterController extends PeteController
{
	
	public function __construct(Request $request)
    {
		//Ensure system vars are loaded
        parent::__construct();          

        $this->middleware('auth');

        View::share([
            'dashboard_url' => env('PETE_DASHBOARD_URL'),
            'viewsw'        => '/import_wordpress'
        ]);
    }
  	
	public function create(){	
		$current_user = Auth::user(); 
		return view("wordpress-importer-plugin::create",compact('current_user'));
	}
	
	public function store(Request $request)
	{
		/* -----------------------------------------------------------------
		| 1. Validate incoming data
		* ----------------------------------------------------------------- */
		$data = $request->validate([
			'url'            => [
				'required',
				'max:255',
				// Allow letters, digits, dots and dashes (no protocol)
				'regex:/^[a-z0-9\-\.]+$/i',
				Rule::unique('sites', 'url'),
			],
			'backup_file'    => ['nullable', 'file', 'mimes:gz,tgz', 'max:102400'], // ≤100 MB
			'big_file_route' => ['nullable', 'string'],
		]);

		/* -----------------------------------------------------------------
		| 2. Determine which source was provided
		* ----------------------------------------------------------------- */
		/** @var \Illuminate\Http\UploadedFile|null $backupFile */
		$backupFile = $request->file('backup_file');          // null if not uploaded
		$serverPath = $data['big_file_route'] ?? null;        // null if not typed

		// Ensure *exactly one* source
		if (blank($backupFile) && blank($serverPath)) {
			return back()->withErrors(
				'Upload a backup file or specify a server path.'
			);
		}
		if (!blank($backupFile) && !blank($serverPath)) {
			return back()->withErrors(
				'Choose either the upload *or* the server path — not both.'
			);
		}

		/* -----------------------------------------------------------------
		| 3. Resolve absolute template path
		* ----------------------------------------------------------------- */
		if ($backupFile) {                                    // user uploaded a file
			$filename     = Str::random(40).'.'.$backupFile->getClientOriginalExtension();
			$stored       = $backupFile->storeAs('wordpress-imports', $filename);
			$templateFile = Storage::path($stored);           // storage/app/wordpress-imports/…
		} else {                                              // user typed server path
			$templateFile = $serverPath;

			if (! is_readable($templateFile)) {
				return back()->withErrors('The specified server file is not readable.');
			}
		}

		/* -----------------------------------------------------------------
		| 4. Create Site model & kick off import
		* ----------------------------------------------------------------- */
		$site = new Site();
		$site->set_url($data['url']);                         // handles domain template

		$site->import_wordpress([
			'template'    => $templateFile,
			'theme'       => 'custom',
			'user_id'     => auth()->id(),
			'site_url'    => $site->url,
			'action_name' => 'Import',
		]);

		OServer::reload_server();                                // refresh vhosts / containers

		return redirect()
			->route('sites.logs', $site)                      // jump to live logs
			->with('status', 'Import started — check the logs for progress.');
	}
	
}
