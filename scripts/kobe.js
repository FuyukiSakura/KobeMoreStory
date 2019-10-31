//Global Variable
var textChanged = true;
const NOTI_HTML = '<div class="notification">[text]</div>';

$(document).ready(function(){

	$("#notification-area").on("click", ".notification", function(){
		$(this).slideUp('slow');
	});

	//Mode selection
	$("#mode-selection button").on('click', function(){
		var mode = $(this).data('mode');
		$("#mode-selection button.btn-active").removeClass('btn-active');
		$(this).addClass('btn-active');
		$("#kobe-mode").prop('value', mode);
		if(mode === "text"){
			$(".preview-box").hide();
			$("#kobe-msg").prop('maxlength', 1000);
		}else if(mode === "image"){
			$(".preview-box").show();
			$("#kobe-msg").prop('maxlength', 255);
		}
	});

	//Color selection
	$("#color-selection button").on('click', function(){
		var mode = $(this).data('color');
		$("#color-selection button.btn-active").removeClass('btn-active');
		$(this).addClass('btn-active');
		$("#kobe-color").prop('value', mode);
		textChanged = true;
	});

	//Kobes Handling
	$("#kobe-msg").keyup(function(){
		textChanged = true;
	});

	$("#kobe-msg").keypress(function(){
		if(this.value.length > $(this).prop('maxlength')){
			 return false;
	 }
	 $("#kobe-remain").html(this.value.length+"/"+$(this).prop('maxlength'));
	});

	setInterval(function(){
		if(textChanged && $("#kobe-mode").prop('value') === "image"){
			$.ajax({
				type        : 'POST',
				url         : 'kobegen/',
				data        : {
								text:encodeURIComponent($("#kobe-msg").val()), 
								color:$("#kobe-color").prop('value'),
								newYear:$("#kobe-imagemode").is(':checked')
							  },
				encode      : true
			}).success(function(data){
				$("#kobe-image").attr('src', data);
			}).error(function(e) {
				//error
				console.log(e);
			});
			textChanged = false;
		}
	}, 3000);

	//Image Upload
	$(":file").change(function(){
		$("#loading").show();
		var reader = new FileReader();
		reader.onload = function(e) {
		   //this next line separates url from data
			var iurl = e.target.result.substr(e.target.result.indexOf(",") + 1, e.target.result.length);
			var clientId = "b76ad12ff380303";               
			$.ajax({
				url: "https://api.imgur.com/3/upload",
				type: "POST",
				datatype: "json",
				data: {
					'image': iurl,
					'type': 'base64'
				},
				success: function(data){
					$('#kobe-msg').append('![image]('+data.data.link+')\n');
					$('#file-upload').replaceWith($('#file-upload').val('').clone(true));
				},//calling function which displays url
				error: function(){
					showNotification("failed");
				},
				beforeSend: function (xhr) {
					xhr.setRequestHeader("Authorization", "Client-ID " + clientId);
				},
				complete: function(){
					$("#loading").hide();
				}
			});
		};
		reader.readAsDataURL(this.files[0]);	
	});	
	
	//Submissions
	$("#terms-toggle").on('click', function(){
		event.preventDefault();
		$("#terms").show("200");
	});

	$("#submit").on( "click", function( event ) {
		event.preventDefault();
		$("#loading").show();
		sendData = $("#kobe-form").serialize();
		$.ajax({
			type        : 'POST',
			url         : 'inc/form.php',
			data        : sendData,
			dataType    : 'json',
			encode      : true
		}).success(function(data){
			if(data.status === "Success"){
				$("#modal-message").text(data.info.message);
				$("#modal-posted").modal();
			}else{
				showNotification(data.info.message);
			}
			console.log(data);
		}).error(function(e) {
			//error
			console.log(e);
			console.log("err");
		}).complete(function(){
			$("#loading").hide();
		});
	});

	//Refresh on success
	$('#modal-posted').on('hidden.bs.modal', function () {
	  location.reload();
	});
});

//Notification Handle
function showNotification(notiMessage){
	$(NOTI_HTML.replace(/\[text]/g, notiMessage)).appendTo($("#notification-area")).slideDown("slow");
}
