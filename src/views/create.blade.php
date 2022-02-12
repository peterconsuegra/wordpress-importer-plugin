@extends('layout')

@section('header')

	
@endsection

@section('content')
@include('error')


	<div class="row">
		<div class="col-md-12">
				<div class="page-header">
						<h3>Import WordPress Instance</h3>
	
				</div>
		</div>
	</div>
	
<form action="/import_wordpress/store" id ="SiteForm" method="POST" enctype="multipart/form-data">

	<input type="hidden" name="_token" value="{{ csrf_token() }}">						 
	
	
	
	
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
				<label for="zip_file_url-field">WordPress Pete file</label>
				<input type="file" id="filem" name="filem">
			</div>
						
			<div id="big_file_container"><input type="checkbox" id="big_file" name="url_template"  value="true"> &nbsp; File path for large files (Optional)</div>
							
			
			
			@if($pete_options->get_meta_value('os_distribution') == "docker")		
				<p><i id="label_big_file_container" style="display: none; font-size:14px">Copy and paste the WordPress Pete file in the path of the volume shared with docker: wordpress-pete-docker/public_html/my_site.tar.gz, after this restart the docker and note that the path in this field will be: /var/www/html/my_site.tar.gz</i></p>
			@else
			
			<p id="label_big_file_container" style="display: none; font-size:14px">Enter the path where the WordPress Pete format file is located</p>
			
			@endif
			
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
		$("#label_big_file_container").toggle();
		$("#big_file_route").toggle();
	});
	
</script>		
			
	
@endsection