<?php


namespace Pete\WordPressImporter\Http;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Site;
use Input;
use Illuminate\Http\Request;
use App\PeteOption;
use Validator;
use Illuminate\Support\Facades\Redirect;
use Log;
use View;

class WordPressImporterController extends Controller
{
	
	public function __construct(Request $request){
	    
	    $this->middleware('auth');
		
		$dashboard_url = env("PETE_DASHBOARD_URL");
		$viewsw = "/sites";
		
		//DEBUGING PARAMS
		$debug = env('PETE_DEBUG');
		if($debug == "active"){
			$inputs = $request->all();
			Log::info($inputs);
		}
		
		$system_vars = parent::__construct();
		$pete_options = $system_vars["pete_options"];
		$sidebar_options = $system_vars["sidebar_options"];
		
		View::share(compact('dashboard_url','viewsw','pete_options','system_vars','sidebar_options'));
		
	}
  	
	public function create(){
		
		$current_user = Auth::user(); 
		$viewsw = "/import_wordpress";
		return view("wordpress-importer-plugin::create",compact('viewsw','current_user'));
	}
	
	
	
	public function store(Request $request)
	{
		
		$current_user = Auth::user();
		$request_array = $request->all();
		$new_site = new Site();
		$new_site->set_url($request->input("url"));
		$new_site->set_project_name($new_site->url);
		
		$errors = $new_site->can_this_site_be_create();
		if($errors["error"]){
			 $result = array_merge($errors, $request_array);
			 return redirect('/import_wordpress')->withErrors("Forbidden project name");
		}else{
				
			/*
			1) Create Snapshot
			2) Import Snapshot
			3) Delete Snapshot
			*/
			$new_site->big_file_route = $request->input("big_file_route");
			
			if($request->file('filem')!= ""){
				$file = $request->file('filem');
		        // SET UPLOAD PATH
		        $destinationPath = 'uploads';
		         // GET THE FILE EXTENSION
		        $extension = $file->getClientOriginalExtension();
		         // RENAME THE UPLOAD WITH RANDOM NUMBER
		        $fileName = rand(11111, 99999) . '.' . $extension;
		         // MOVE THE UPLOADED FILES TO THE DESTINATION DIRECTORY
		        $upload_success = $file->move($destinationPath, $fileName);
				$new_site->zip_file_url = $fileName;
			}
			
			$current_user = Auth::user();
			
			$import_params = array_merge(
			["template" => "none",
			"action_name" => "Import", 
			"user_id" => $current_user->id, 
			],$request_array);
			
			$new_site->import_wordpress($import_params);
	
			return Redirect::to('/sites/'.$new_site->id .'/edit' .'?success=' . 'true');
		}
		
	}	
	
}
