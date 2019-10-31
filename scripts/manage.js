//Global Variable
const NOTI_HTML = '<div class="notification">[text]</div>';
var kobeBox = '';
var targetStatus = 0;

$(document).ready(function(){
  //Load Table Structure
  $.get('ajax/admin_table.html', function(data){
    kobeBox = data;
  }, 'html');

  //Get Kobes on load
  initializePage();
  /* $.ajax({
    type        : 'POST',
    url         : 'inc/manage.inc.php',
    data        : {action:'getKobes'},
    dataType    : 'json',
    encode      : true,
    success: function(data){
      if(data.status === "Success"){
        //Kobes
        var kobes = data.info.extra.kobes;
        refreshTable(kobes);

        //Update Pagination
        $('.pagination').empty();
        var pages = Math.ceil(data.info.extra.countKobes / 10);
        $('.pagination').append('<li class="active"><a id="page-one" href="#">1</a></li>'); //First Page Active
        for(i=2;i<=pages;i++){
          $('.pagination').append('<li><a href="#">'+i+'</a></li>');
        }
      }else{
        showNotification(data.info.message);
      }
      console.log(data);
    },
    error: function(e) {
      //error
      console.log(e);
      console.log("err");
    }
  }); */

  /* ---------- Nav Bar Behavior ---------- */
  $('ul.nav').on('click', 'li a', function(e){
	targetStatus = $(this).data('status');
	initializePage();
	$("ul.nav li").removeClass("active");
	$(this).parent().addClass("active");	
  });
  
  /* ---------- Pagination Behavior ---------- */
  $('ul.pagination').on('click', 'li a', function(e){
    var targetPage = $(this).text();
    //Get Kobes on load
    $.ajax({
      type        : 'POST',
      url         : 'inc/manage.inc.php',
      data        : {action:'getKobes', page:targetPage, kobeStatus:targetStatus},
      dataType    : 'json',
      encode      : true,
      success: function(data){
        if(data.status === "Success"){
          //Kobes
          var kobes = data.info.extra.kobes;
          refreshTable(kobes);

          //Update Pagination
          $("ul.pagination li").removeClass("active");
          $("ul.pagination li:nth-child("+targetPage+")").addClass('active');
        }else{
          showNotification(data.info.message);
        }
      },
      error: function(e) {
        //error
        showNotification("Fail to Connect");
        console.log(e);
      }
    });
  });

  /* ---------- Admin Action Behavior ---------- */
  $('#kobe-data').on('click', 'button', function(e){
    e.preventDefault();
	var caller = this;
    $(".btn-control-"+$(caller).data('kid')).prop('disabled', true);
	$("#loading-"+$(caller).data('kid')).show();
	
    $.ajax({
      type        : 'POST',
      url         : 'inc/manage.inc.php',
      data        : {action:$(caller).data('action'), kid:$(caller).data('kid')},
      dataType    : 'json',
      encode      : true,
      success: function(data){
        if(data.status === "Success"){
          $(caller).closest('tr').remove();
        }else{
          showNotification(data.info.message);
        }
        $(caller).prop('disabled', false);
      },
      error: function(e) {
        //error
        showNotification("Fail to Connect");
        console.log(e);
      },
	  complete:function(){
		$("#loading-"+$(caller).data('kid')).hide();
		$(".btn-control-"+$(caller).data('kid')).prop('disabled', false);
	  }
    });
  });
});

//Notification Handle
function showNotification(notiMessage){
	$(NOTI_HTML.replace(/\[text]/g, notiMessage)).appendTo($("#notification-area")).slideDown("slow");
}

//Load Data to Kobe Table
function refreshTable(kobes){
	$('#kobe-data table tbody').empty();
	if(kobes.length === 0){
		$('#kobe-data table tbody').append('<tr><td colspan="6">沒有新靠北需要審核 - <a href="/">發布新靠北</a></td></tr>');
		return;
	}
	$.each(kobes, function(key, value){
	$('#kobe-data table tbody').append(kobeBox.replace(/\[kobe-id]/g, value.id)
	  .replace(/\[kobe-type]/g, value.type)
	  .replace(/\[kobe-msg]/g, value.message)
	  .replace(/\[kobe-time]/g, value.time)
	  .replace(/\[kobe-ip]/g, value.ip)
	);
	});
}

function initializePage(){
	$.ajax({
		type        : 'POST',
		url         : 'inc/manage.inc.php',
		data        : {action:'getKobes', kobeStatus:targetStatus},
		dataType    : 'json',
		encode      : true,
		success: function(data){
		  if(data.status === "Success"){
			//Kobes
			var kobes = data.info.extra.kobes;
			refreshTable(kobes);

			//Update Pagination
			$('.pagination').empty();
			var pages = Math.ceil(data.info.extra.countKobes / 10);
			$('.pagination').append('<li class="active"><a id="page-one" href="#">1</a></li>'); //First Page Active
			for(i=2;i<=pages;i++){
			  $('.pagination').append('<li><a href="#">'+i+'</a></li>');
			}
		  }else{
			showNotification(data.info.message);
		  }
		},
		error: function(e) {
		  //error
		  console.log(e);
		  console.log("err");
		  return false;
		}
	});
}
