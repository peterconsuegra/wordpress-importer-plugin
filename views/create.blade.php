@extends('layout')

@section('header')
<div class="page-header">
	<h4>Create WordPress Plus Laravel Integration</h4>
	
	<div id="loading_area"></div>
	
</div>
	
@endsection

@section('content')
@include('error')
	
<form action="/wordpress_plus_laravel/store" id ="SiteForm" method="POST" enctype="multipart/form-data">

	<input type="hidden" name="_token" value="{{ csrf_token() }}">						 
		
	<div class="row">
		<div class="col-md-12">
									
							
			<select class="form-control" id="action_name-field" name="action_name">
				<option value="">Select Action</option>
				<option value="new_wordpress_laravel">New</option>
				<option value="import_wordpress_laravel">Import</option>
							
			</select>
								
			<br />
							
		</div>
							
	</div>
	
	<div class="row">
		<div class="col-md-12">
									
					
			<div class="form-group" id="selected_version_div" style="display: none;">
				<p> Select Laravel version</p>
				<select class="form-control" id="selected_version" name="selected_version">
					
					<option value="5.5">5.5</option>
					<option value="5.6">5.6</option>
					<option value="5.7">5.7</option>
					<option value="5.8">5.8</option>
				</select>	
				
                   
				<div id="name_error_area"> 
				</div>
			</div>
				
		</div>
	</div>
							
							
				
	<div class="row">
		<div class="col-md-12">
									
			<div id="project_to_clone">
					
			</div>
					
			<div class="form-group" id="name_div" style="display: none;">
				<p> Project name</p>
				<input type="text" id="name-field" name="name" class="form-control" value="{{ old("name") }}" />
                   
				<div id="name_error_area"> 
				</div>
			</div>
				
		</div>
	</div>
				
				
	<div class="row">
		<div class="col-md-12">
			<div class="form-group" id="import_from_git" style="display: none;">
				
				<p><input type="checkbox" id="import_from_git_checkbox" name="import_from_git_checkbox"> Import from GIT</p>
 			   
			</div>
				
		</div>
	</div>
	
			
	<div class="row">
		<div class="col-md-12">
					
			<div class="form-group" id="wordpress_laravel_git" style="display: none;">
				<p>Git URL.</p>
			<ul>
				<li>Please take into account that WordPress Plus Laravel is only available for Laravel 5.5+</li>
				<li>If you are going to import a repository that starts with https:// please make sure it's public</li>
				<li>if you are going to import a repository that starts with @git please make sure you have added the ssh keys of your machine to the repository account</li>
			</ul>
				<input type="text" id="wordpress_laravel_git-field" name="wordpress_laravel_git" class="form-control" />
                   
				<div id="wordpress_laravel_git_error_area"> 
				</div>
			</div>
				
		</div>
				
	</div>    
	
	<div class="row">
	<div class="col-md-12">
				
		<div class="form-group" id="wordpress_laravel_git_branch" style="display: none;">
			<p>Git branch</p>
			
			<input type="text" id="wordpress_laravel_git_branch-field" name="wordpress_laravel_git_branch" class="form-control"  />
               
			<div id="wordpress_laravel_git_error_area"> 
			</div>
		</div>
			
	</div>
	</div>
	  
	<div class="row">
		<div class="col-md-12">
									
			<div class="form-group" id="url_div" style="display: none;">
				<p>URL</p>
				<input type="text" id="url-field" name="url" class="form-control " value="{{ old("url") }}" />
					   
				<div id="url_error_area"> 
				</div>
					   
			</div>
		</div>
	</div>
				
	<div class="row">
		<div class="col-md-12">
						
			<div id="url_div_wordpress_laravel" style="display: none;">
				<p>URL</p>
				<input type="text" id="wordpress_laravel_name-field" name="wordpress_laravel_name" class="inline_class url_wordpress_laravel" />
				<div id="url_wordpress_laravel_helper" class="inline_class">.</div>
				 
			</div>
			<br />
		</div>
				
	</div>
				
							
	<div id="massive_form">
		
	</div>
                    
               
	<button type="submit" id="create_button" style="width:100%;" class="btnpete">Create</button>
	<br /><br />
</form>
			
			
			

        
	
<script>
	
	var patt = new RegExp(/^[a-z0-9]+$/i);
	var patturl = new RegExp(/^[a-z0-9\.]+$/i);
	
	$("#url_template").change(function() {
		if($("#url-field").prop("readonly")){
			$("#url-field").prop("readonly", false);
		}else{
			$("#url-field").prop("readonly", true);
		}
		//alert("hola");
	});
	
	$("#big_file").click(function() {
		
		//alert("hi big");
		$("#big_file_route").toggle();
	});
	
	$("#action_name-field").change(function() {
		form_logic($("#app_name-field").val(),$("#action_name-field").val());
	});
	
	$("#import_from_git_checkbox").change(function() {
		$("#wordpress_laravel_git").toggle();
		$("#wordpress_laravel_git_branch").toggle();
	});
	
	function wordpress_laravel_select(){
		$("#wordpress_laravel_target-field").change(function() {
			$("#url_wordpress_laravel_helper").html("."+$(this).find('option:selected').text());
		});
	}
				
	function hide_all(){
		$("#url_div_wordpress_laravel").hide();
		$("#url_div_wordpress_helper").hide();
		$("#name_div").hide();
		$("#wordpress_laravel_git").hide();
		$("#wordpress_laravel_git_branch").hide();
		$("#big_file_container").hide();
		$("#big_file").hide();
		$("#big_file_route").hide();
		$("#name_div").hide();
		$("#url_div").hide();
		$("#wordpress_laravel_target-field").hide();
	}
	
	function form_logic(app,action){
		if((app != 0) && (action != 0)){
			hide_fields();
			//$("#name_div").show();
			
			console.log(app);
			console.log(action);
			if((app == "Wordpress") || (app == "Drupal")){
				
				if(action=="new_wordpress_laravel"){
					$("#name_div").show();
					$("#url_div").show();	
					$("#massive_form").html("");
				}
				else if (action=="Clone") {
				  
					if(app == "Wordpress"){
  				  
  				 
						$.ajax({
							url: "/sites/get_sites",
							type: "get",
							datatype: 'json',
							data: { app_name: "Wordpress"},
							success: function(data){
								$("#loading_area").html('');
								console.log(data);
								select_aux = "<p>Project to clone</p>" ;
								select_aux += '<select id="to_clone_project-field" name="to_clone_project" class = "form-control">';	
							
								var arrayLength = data.length;
								for (var i = 0; i < arrayLength; i++) {	
									select_aux +='<option value="'+data[i].name+'">'+data[i].url+'</option>';
								}
							
								select_aux +='</select><br/>';
								$("#name_div").show();
								$("#url_div").show();
								$("#project_to_clone").show();
								$("#project_to_clone").html(select_aux);
								$("#massive_form").show();
							}
						
						});
					}else if(app == "Drupal"){
				
 				    
						$.ajax({
							url: "/sites/get_sites",
							type: "get",
							datatype: 'json',
							data: { app_name: "Drupal"},
							success: function(data){
								$("#loading_area").html('');
								console.log(data);
								select_aux = "" ;
								select_aux = "<label for='to_clone_project-field'>Project to clone</label>" ;
								select_aux += '<select id="to_clone_project-field" name="to_clone_project" class = "form-control">';
 							
								var arrayLength = data.length;
								for (var i = 0; i < arrayLength; i++) {	
									select_aux +='<option value="'+data[i].name+'">'+data[i].url+'</option>';
								}
							
								select_aux +='</select><br/>';
								$("#project_to_clone").html(select_aux);
								$("#name_div").show();
								$("#url_div").show();
								$("#massive_form").show();
							}
						});	
				  
					}		
								 
				}else if (action=="Import") {
					$("#name_div").show();
					$("#url_div").show();
					$("#zip_file_url_div").show();
					$("#big_file").show();
					$("#big_file_container").show();
				  
				}
				
			}
			
			
  			 
				if(action=="new_wordpress_laravel"){
				  
					$("#loading_area").html('<div id="loading_div"></div>');
					$.ajax({
						url: "/sites/get_sites",
						type: "get",
						datatype: 'json',
						data: { app_name: "Wordpress+laravel"},
						success: function(data){
							$("#loading_area").html('');
							console.log(data);
							select_aux = '<div class="row">';
							select_aux += '<div class="col-md-12">';
							select_aux += '<select id="wordpress_laravel_target-field" name="wordpress_laravel_target" class = "form-control" >';	
							select_aux +='<option value="">Select WordPress project</option>';
							var arrayLength = data.length;
							for (var i = 0; i < arrayLength; i++) {	
								select_aux +='<option value="'+data[i].id+'">'+data[i].url+'</option>';
							}
						
							select_aux +='</select><br/>';
							select_aux +='</div>';
							select_aux +='</div>';
	    				    
							$("#selected_version_div").show();
							$("#url_div_wordpress_laravel").show();
							$("#url_div_wordpress_helper").show();
							$("#name_div").show();
							//$("#wordpress_laravel_git").show();
							//$("#wordpress_laravel_git_branch").show();
							$("#massive_form").html(select_aux);
							$("#massive_form").show();
							$("#big_file_container").hide();
							$("#big_file").hide();
							$("#big_file_route").hide();
							wordpress_laravel_select();
						}
					
					});
				
				}else if (action=="import_wordpress_laravel"){
				  
			  
					$("#loading_area").html('<div id="loading_div"></div>');
					$.ajax({
						url: "/sites/get_sites",
						type: "get",
						datatype: 'json',
						data: { app_name: "Wordpress+laravel"},
						success: function(data){
							$("#loading_area").html('');
							console.log(data);
							select_aux = '<div class="row">';
							select_aux += '<div class="col-md-12">';
							select_aux += '<select id="wordpress_laravel_target-field" name="wordpress_laravel_target" class = "form-control" >';	
							select_aux +='<option value="">Select a Wordpress project</option>';
							var arrayLength = data.length;
							for (var i = 0; i < arrayLength; i++) {	
								select_aux +='<option value="'+data[i].id+'">'+data[i].url+'</option>';
							}
						
							select_aux +='</select><br/>';
							select_aux +='</div>';
							select_aux +='</div>';
	    				
							$("#url_div_wordpress_laravel").show();
							$("#url_div_wordpress_helper").show();
							$("#name_div").show();
							
							//$("#wordpress_laravel_git").show();
							//$("#wordpress_laravel_git_branch").show();
							$("#massive_form").html(select_aux);
							$("#massive_form").show();
							$("#selected_version_div").hide();		  
									
							$("#wordpress_laravel_git").show();
							$("#wordpress_laravel_git_branch").show();
						  
							wordpress_laravel_select();
						}
					
					});
					
					
				}
			
		}
	}
	
	
	
	function hide_fields(){
				
		$("#url_div_wordpress_laravel").hide();
		$("#url_div_wordpress_helper").hide();
		$("#name_div").hide();
		$("#wordpress_laravel_git").hide();
		$("#wordpress_laravel_git_branch").hide();
		$("#big_file_container").hide();
		$("#big_file").hide();
		$("#big_file_route").hide();
		$("#name_div").hide();
		$("#url_div").hide();
		$("#wordpress_laravel_target-field").hide();
				
		$("#massive_form").hide();
		$("#name_div").hide();
		$("#url_div").hide();
		$("#zip_file_url_div").hide();
		$("#db_root_pass_div").hide();
	  
		$("#url_div_wordpress_laravel").hide();
		$("#url_div_wordpress_helper").hide();
		$("#wordpress_laravel_git").hide();
		$("#wordpress_laravel_git_branch").hide();
				  
		$("#project_to_clone").hide();
				  
	}
				
			
				
	var createsw = false;
	 
	jQuery( document ).ready(function( $ ) {

		//$("#SiteForm").validate();
	 
	});
	  
	  
	$("#create_button").click(function() {
	  	  
		//Aditional javascript validations for import action
		   
		app_name = $("#app_name-field").val();
		action_name = $("#action_name-field").val();
		  
		//Import CMS case
		if(((app_name == "Wordpress") || (app_name == "Drupal")) & (action_name == "Import")){
		  	  
			console.log("validations CMS");
			console.log($("#import_from_git_checkbox").is(":checked"));
			console.log($('input[type=file]').val());
			  
			if(($('input[type=file]').val() == "") & ($("#big_file_route").val() == "")){
				alert("Please select a pete .tar.gz file")
				return false
			}

			//Import wordpress+laravel case
		}else if((app_name == "Wordpress+laravel") & ((action_name == "Import") || (action_name == "Clone"))){
			console.log("Validations wordpress+laravel");
			console.log($('input[type=file]').val());
			 
						 
		}
	});
				  
				  
				  
	//Wordpress+laravel input logic
	$(document).ready(function(){
					  
		$('#name-field').keyup(function(e){
			$('#wordpress_laravel_name-field').val($('#name-field').val());
		});
					  
		@if($pete_options->get_meta_value('environment') == "development")
					  
		$('#name-field').keyup(function(e){
			$('#url-field').val($('#name-field').val());
		});
					  
		@endif
					  
		$('#wordpress_laravel_git-field').keyup(function(e){
						  
			var n = $(this).val().startsWith("https://");
			var n2 = $(this).val().startsWith("git@");
			if((n==true) || (n2 ==true)){
				$(this).removeClass("text_area_fancy_error").addClass("text_area_fancy_done");
				console.log("nice");
			}else{
				$(this).removeClass("text_area_fancy_done").addClass("text_area_fancy_error");
							 
				console.log("bad");
			}
		});
					  
	});
		
	
	</script>


	
	@endsection