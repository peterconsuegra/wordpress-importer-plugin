@extends('layout')

@section('header')
<div class="page-header">
	<h4>Import WordPress</h4>
	
	<div id="loading_area"></div>
	
</div>
	
@endsection

@section('content')
@include('error')
	
<form action="/import_wordpress/store" id ="SiteForm" method="POST" enctype="multipart/form-data">

	<input type="hidden" name="_token" value="{{ csrf_token() }}">						 
	
	
	<div class="row">
		<div class="col-md-12">
												
			<div class="form-group" id="name_div">
				<p> Project name</p>
				<input type="text" id="name-field" name="name" class="form-control" value="{{ old("name") }}" required/>
                   
				<div id="name_error_area"> 
				</div>
			</div>
				
		</div>
	</div>
	
	
	@if($pete_options->get_meta_value('domain_template'))
							
						    
	<div class="row">
		<div class="col-md-12">
						
			<div id="url_div">
				<p>URL</p>
				<input type="text" id="url-field" name="url" class="inline_class url_wordpress_laravel" required/>
				<div id="url_wordpress_helper" class="inline_class">.{{$pete_options->get_meta_value('domain_template')}}</div>
				 
			</div>
			<br />
		</div>
				
	</div>
							
	@else
						  
	<div class="row">
		<div class="col-md-12">
									
			<div class="form-group" id="url_div">
				<p>URL</p>
				<input type="text" id="url-field" name="url" class="form-control " value="{{ old("url") }}" required/>
					   
				<div id="url_error_area"> 
				</div>
					   
			</div>
		</div>
	</div>
						  
	@endif
	
	
	<div class="row">
		<div class="col-md-12">
									
									
			<div class="form-group" id="zip_file_url_div" >
				<label for="zip_file_url-field">Upload Pete tar.gz file</label>
				<input type="file" id="filem" name="filem">
			</div>
						
			<div id="big_file_container"><input type="checkbox" id="big_file" name="url_template"  value="true"> &nbsp; File path for large files (Optional)</div>
							
			<label id="label_big_file_container" style="display: none;">Insert the complete route to Pete tar.gz file</label>
			<input type="text" id="big_file_route" name="big_file_route" style="display: none;" class="form-control"/>
				
			<br/>
					
				            
				
		</div>
	</div>
	
	                
               
	<button type="submit" id="create_button" style="width:100%;" class="btnpete">Create</button>
	<br /><br />
</form>
			

<script>
	
	$("#big_file").click(function() {
		
		//alert("hi big");
		$("#big_file_route").toggle();
	});
	
</script>		
			
	
@endsection