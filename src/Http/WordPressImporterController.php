<?php


namespace Pete\WordPressImporter\Http;

use App\Http\Controllers\PeteController;
use Illuminate\Support\Facades\Auth;
use App\Site;
use Illuminate\Http\Request;
use Validator;
use Illuminate\Support\Facades\Redirect;
use Log;
use View;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Http\UploadedFile;

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
		/** ----------------------------------------------------------------
		 * 1. Validate input
		 * ---------------------------------------------------------------- */
		$data = $request->validate([
			'url'            => [
				'required',
				'max:255',
				// allow letters, numbers, dots & dashes (no protocol)
				'regex:/^[a-z0-9\-\.]+$/i',
				Rule::unique('sites', 'url'),
			],
			'backup_file'    => ['nullable', 'file', 'mimes:gz,tgz', 'max:102400'], // 100 MB
			'big_file_route' => ['nullable', 'string'],
		]);

		// enforce “one source only”
		if (blank($data['backup_file']) && blank($data['big_file_route'])) {
			return back()->withErrors('Upload a backup file or specify a server path.');
		}
		if (!blank($data['backup_file']) && !blank($data['big_file_route'])) {
			return back()->withErrors('Choose either the upload *or* the server path— not both.');
		}

		/** ----------------------------------------------------------------
		 * 2. Resolve backup location
		 * ---------------------------------------------------------------- */
		if ($file = ($data['backup_file'] ?? null)) {                       // user uploaded a file
			/** @var UploadedFile $file */
			$filename = Str::random(40).'.'.$file->getClientOriginalExtension();
			$stored   = $file->storeAs('wordpress-imports', $filename);      // storage/app/wordpress-imports
			$templateFile = Storage::path($stored);                          // absolute path
		} else {                                                            // user gave a server path
			$templateFile = $data['big_file_route'];

			if (! is_readable($templateFile)) {
				return back()->withErrors('The specified server file is not readable.');
			}
		}

		/** ----------------------------------------------------------------
		 * 3. Create the Site model & kick off import
		 * ---------------------------------------------------------------- */
		$site = new Site();
		$site->set_url($data['url']);                                       // handles domain template etc.

		$site->import_wordpress([
			'template'    => $templateFile,
			'theme'       => 'custom',
			'user_id'     => auth()->id(),
			'site_url'    => $site->url,
			'action_name' => 'Import',
		]);

		Site::reload_server();                                              // refresh Apache/Nginx conf

		return redirect()
			->route('sites.logs', $site)                                    // straight to log view
			->with('status', 'Import kicked off — check logs for progress.');
	}
	
}
