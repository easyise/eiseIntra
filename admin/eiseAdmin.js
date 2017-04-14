$(document).ready(function(){

	$('body').eiseIntra('conf', 'onTopLevelMenuChange', function(newVal){

		$('body').eiseIntra('cleanStorage');
		location.href='database_form.php?dbName='+$(this).val();

	});

	$('body').eiseIntra('adjustTopLevelMenu');
	
})