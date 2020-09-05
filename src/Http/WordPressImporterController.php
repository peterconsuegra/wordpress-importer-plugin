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
			$input = $request->all();
			$dashboard_url = env("DASHBOARD_URL");
			$viewsw = "/import_wordpress";
			View::share(compact('dashboard_url','viewsw'));	
	 }
	
	public function create(){
		
		$num = substr(PHP_VERSION, 0, 3);
		$float_version = (float)$num;
		
		if($float_version < 7.1){
        	return redirect('sites/create')->withErrors("The PHP version must be >= 7.1 to activate WordPress Plus Laravel functionality.");
		}
		
		//return view("wordpress-importer-plugin::create")->with('viewsw',$viewsw);
		return view("wordpress-importer-plugin::create");
	}
	
	
	
	public function store(Request $request)
	{
		Log::info("entro en store de WordPressImporterController");
		
		$pete_options = new PeteOption();
		$user = Auth::user();
		$fields_to_validator = $request->all();
		
		$site = new Site();
		$site->output = "";
		$site->user_id = $user->id;
		$site->app_name = $request->input("app_name");
		$site->action_name = "Import";
		
		$site->name = $request->input("name");
		$site->to_import_project = $request->input("to_import_project");
		$site->user_id = $user->id;
		$site->url = $request->input("url");
		$site->big_file_route = $request->input("big_file_route");
		$site->laravel_version = $request->input("selected_version");	
		
		$app_root = $pete_options->get_meta_value('app_root');
		if($pete_options->get_meta_value('domain_template')){
	
			$site->url = $site->url . "." . $pete_options->get_meta_value('domain_template');
		}
		
		$validator = Validator::make($fields_to_validator, [
			'name' =>  array('required', 'regex:/^[a-zA-Z0-9-_]+$/','unique:sites'),
			'url' => 'required|unique:sites',
		]);
		
     	if ($validator->fails()) {
			
	        return redirect('import_wordpress')
	        		->withErrors($validator)
	        			->withInput();
     	 }
		
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
			$site->zip_file_url = $fileName;
		
		}
		
	
		$site->import_wordpress();
			
		return Redirect::to('/sites/'.$site->id .'/edit' .'?success=' . 'true');
	}	
	
}
