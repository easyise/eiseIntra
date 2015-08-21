var explainerConf = {
	modal: true, width: '90%'
	, title: 'Query Analyzer'
};

$(window).load(function(){
	
	$('.eiseGrid').eiseGrid();

	$('#prc').eiseGrid('dblclick', function($tr){
		getProcInfo($tr);		
	})
})

function getProcInfo($tr){
	var procID = $('#prc').eiseGrid('value', $tr, 'ID');
		var procDatabase = $('#prc').eiseGrid('value', $tr, 'DB');
		var procQuery = $('#prc').eiseGrid('text', $tr, 'INFO').trim();

		if(procQuery=='')
			return;
		
		$('#query_explainer #Info').text(procQuery);
		$('#query_explainer #DB').text(procDatabase);
		$('#query_explainer #ID').text(' - ');
		$('#query_explainer #Time').text('0.00');

		$('#query_explainer').dialog(explainerConf);
		$('#expl').eiseGrid('reset');

		

		

		var url = location.href+'?DataAction=getProcInfo&procID='+encodeURIComponent(procID);

		$.getJSON(url, function(d){

            if (d.status=='error') 
            	if (d.code!='404'){
	                alert(d.message);
	                return;
            	} else {
					$('#query_explainer #ID').text('(process not found)');
					$('#query_explainer #Time').text('(process not found)');
					explainQuery();
					return;
            	}

            $('#expl')
                .eiseGrid('fill', d.explain.data);

        });

}

function explainQuery(){
	var q = $('#query_explainer #Info').text();
	var db = $('#query_explainer #DB').text();
	var url = location.href+'?DataAction=explainQuery&db='+encodeURIComponent(db)+'&q='+encodeURIComponent(q);
	
	$.getJSON(url, function(d){

        if (d.status=='error') {
    	    alert(d.message);
            return;
        }
        
        $('#expl')
            .eiseGrid('fill', d.data);


    });
}