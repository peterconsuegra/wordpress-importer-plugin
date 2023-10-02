@extends('layout')

@section('header')

	
@endsection

@section('content')
@include('error')


	<div class="row">
		<div class="col-md-12">
				<div class="page-header">
						<h3>Import WordPress Instance</h3>
						
						<i>Use PHP 8.0 or 8.1 with WordPress 5.9 or 6.0 for compatibility and better performance</i>
	
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
				<input type="text" id="url-field" name="url" class="inline_class url_wordpress_laravel"/>
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
													
			@if($pete_options->get_meta_value('os_distribution') == "docker")		
				<p><i id="label_big_file_container">
					
					Please copy the WordPress Pete file and paste it into the following directory that's shared with Docker: wordpress-pete-docker/public_html/my_site.tar.gz. After doing so, restart Docker. Note that the file path within Docker will be: /var/www/html/my_site.tar.gz.
					
				</i></p>
			@else
			
			<p id="label_big_file_container">Enter the path where the WordPress Pete format file is located</p>
			
			@endif
			
			<input type="text" id="big_file_route" placeholder="/var/www/html/" name="big_file_route" class="form-control"/>
				
			<br/>
					
				            
				
		</div>
	</div>
	
	                
               
	<button type="submit" id="create_button" style="width:100%;" class="btnpete">Create</button>
	<br /><br />
</form>
			

<script>
	
	
	
</script>		
			
	
@endsection