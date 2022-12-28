var searchURL = 'ajax_search.php';

$(document).ready(function(){

	var $searchInp = $('#headerSearch input[type="text"]');

	if(!$searchInp[0])
		return;
	
	$searchInp.addClass('searchInactive');

	var defSearch = $searchInp.val();	

	$searchInp.focus(function(){
		$searchInp.val('');
		$searchInp.addClass('searchActive');
		$searchInp.removeClass('searchInactive');
	}).blur(function(){
		$searchInp.val(defSearch);
		$searchInp.addClass('searchInactive');
		$searchInp.removeClass('searchActive');
	});

	$searchInp.autocomplete({
	    minLength: 0,
	    source: function(request,response) {
                    
                    if(request.term.length<3){
                        response({});
                        return;
                    }

                    var urlFull = searchURL+"?q="+encodeURIComponent(request.term);
                    
                    $.getJSON(urlFull, function(response_json){
                        

                        if(response_json.data)
	                        response(response_json.data);
	                        
	                    return true;

                    });
                        
            },
	    focus: function( event, ui ) {
		        $searchInp.val( ui.item.label );
		        return false;
	      	},
	    select: function( event, ui ) {
			$('#pane').attr('src', ui.item.href);	 
	        return false;
	    }
    })
	.autocomplete( "instance" )._renderItem = function( ul, item ) {
      	return $( '<li class="search-result">' )
        	.append( '<i class="fa '+item.status_class+'"> </i><a href="'+item.href+'"><span class="search-title">' + item.label + ' '+ "</span>"
        		+ "<br><span class=\"search-descr\">" + item.description + "</span></a>"
        		)
        	.appendTo( ul );
    };

});