ShopifyApp.ready(function(){
	ShopifyApp.Bar.loadingOff();
});

jQuery(function($){
	$('form.upload-csv').on('submit',function(e){
		// e.preventDefault();
		$(this).addClass('loading');

		if( $(this).find('input[type="file"]').val() == '' ) {
			e.preventDefault();
			alert('you didn\t pick a csv file to upload');
			$(this).removeClass('submitted');
		}

		ShopifyApp.Bar.loadingOn();

	});
});