$(document).ready(function(){

	$('body').eiseIntra('conf', 'onTopLevelMenuChange', function(newVal){

		$('body').eiseIntra('cleanStorage');
		location.href='database_form.php?dbName='+$(this).val();

	});

	$('body').eiseIntra('adjustTopLevelMenu');
	
})

function dumpSelectedTables(dbName, what, entID, extra){

    if (typeof(what)=='undefined')
        what = 'tables';

    var strTablesToDump = '';
    if (what=='tables'){
        $("input[name='chk_chk[]']").each(function(){
            if (this.checked){
                strTablesToDump += (strTablesToDump!='' ? '|' : '')+$(this).parent().find('input[name="Name[]"]').val();
            }
        });

        if (strTablesToDump==''){
            alert('Nothing\'s selected');
            return;
        }
    }

    var flagDonwloadAsDBSV = ($('#flagDonwloadAsDBSV')[0] && $('#flagDonwloadAsDBSV')[0].checked)
    	, flagNoData = ($('#flagNoData')[0] && $('#flagNoData')[0].checked)

    var strURL = "database_act.php?DataAction=dump&what="+what+"&dbName="+dbName
        +(what=='tables' ? "&strTables="+encodeURIComponent(strTablesToDump) : '')
        +(what=='entity' && extra ? "&extra="+encodeURIComponent(extra) : '')
        +(flagNoData ? '&flagNoData=1' : '')
        +(flagDonwloadAsDBSV ? '&flagDonwloadAsDBSV=1' : '')
        +(entID!==null ? '&entID='+encodeURIComponent(entID) : '')
        ;
    if( flagDonwloadAsDBSV ){
        location.href=strURL;
    } else {
        $('body').eiseIntraBatch({url: strURL, title: 'Dump', timeoutTillAutoClose: null, flagAutoReload: false})
    }
    

}