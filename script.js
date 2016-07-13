ShopifyApp.ready(function(){
	ShopifyApp.Bar.loadingOff();
});

jQuery(function($){
	$('form.upload-csv').on('submit',function(e){
		// e.preventDefault();
		$(this).addClass('loading');

		if( $(this).find('input[type="file"]').val() == '' ) {
			e.preventDefault();
			alert('You didn\'t pick a csv file to upload');
			$(this).removeClass('loading');
		}

		ShopifyApp.Bar.loadingOn();

	});


	// $('#saveResultsReport').on('click',function(e){
		// window.print();
	// 	e.preventDefault();
	// 	var results = $('.results-table').html();
	// 	$.post(ajaxurl,
	// 	{
	// 		action: 
	// 	});
	// });
});