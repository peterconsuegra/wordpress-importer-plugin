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
									
			<p>File path</p>				
			<input type="text" id="big_file_route" placeholder="/var/www/html/mysite.tar.gz" name="big_file_route" class="form-control"/>
				
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