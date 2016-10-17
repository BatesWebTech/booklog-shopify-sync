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

	$('.js-dynamic-rows').on('blur','.js-dynamic-row',function(){

		var $myrow = $(this).parents('tr').eq(0);
		var $mytable = $(this).parents('table').eq(0);

		if( $(this).val() == '')
			return false;

		$myrow.removeClass('empty');

		if( $mytable.find('tr.empty').length > 0)
			return false;

		var $row = $myrow;
		
			// debugging
			// $row.find('td').css('border','1px solid red');

		$row.removeClass('empty');
		var $newrow = $row.clone(true,true);
		var rownum = $row.data('rownum');
		var newrownum = rownum + 1;
		$newrow.addClass('empty').attr('data-rownum',newrownum).find('input,textarea').each(function(){
			$(this).val('');
			var attr = $(this).attr('name');
			attr = attr.replace('blacklist['+rownum+']','blacklist['+newrownum+']');
			$(this).attr('name',attr);
		});

		// increment ids
		$newrow.find('input').each(function(){
			var name = $(this).attr('name');
			name = name.replace(rownum,newrownum);
			$(this).attr('name',name);
		});

		$mytable.append($newrow);

	});

	$('.js-dynamic-rows').on('click','.js-delete-dynamic-row',function(e){

		e.preventDefault();
		//if( window.confirm('Do you want to delete this? You can\'t recover it.')) {
			$(this).parents('tr').eq(0).remove();
		// }

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