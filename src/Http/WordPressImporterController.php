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
		
		$validator = Validator::make($request_array, [
			'url' => 'required|unique:sites',
		]);
		
		
		if(isset($request_array["big_file_route"])){
			$template_file = $request_array["big_file_route"];
		}else{
			if($request->file('filem')!= ""){
				$file = $request->file('filem');
				// SET UPLOAD PATH
				$destinationPath = 'uploads';
				 // GET THE FILE EXTENSION
				$extension = $file->getClientOriginalExtension();
				 // RENAME THE UPLOAD WITH RANDOM NUMBER
				//$fileName = rand(11111, 99999) . '.' . $extension;
				 // MOVE THE UPLOADED FILES TO THE DESTINATION DIRECTORY
				$originalName =  $file->getClientOriginalName();
				$upload_success = $file->move($destinationPath, $originalName);
			}
			
			$base_path = base_path();
			$template_file = $base_path . "/public/uploads/" . $originalName;
			Log::info("template_file:");
			Log::info($template_file);
		}
		
		$site = new Site();
		$site->set_url($request->input("url"));
		
		$import_params = array_merge(
		["template" => $template_file,
		"theme" => "custom", 
		"user_id" => $current_user->id, 
		"site_url" => $site->url
		],$request_array);
		
		$site->import_wordpress($import_params);
		
		return Redirect::to('/sites/'.$site->id .'/edit' .'?success=' . 'true');
		
	}	
	
}
