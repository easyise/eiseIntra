
var simpleTreeCollection;
$(document).ready(function(){
	simpleTreeCollection = $('.simpleTree').simpleTree({
		autoclose: true,
		drag: true,
		afterClick:function(node){
			//alert("text-"+$('span:first',node).text());
		},
		afterDblClick:function(node){
			location.href='page_form.php?pagID='+node.attr("id")+'&dbName='+$("#dbName").attr("value");
		},
		afterMove:function(dd, ds, pos)
      {
         
         var pagParentID = $(dd).attr("id");
         var pagID = $(ds).attr("id");
		 var dbName = $("#dbName").attr("value");
		 return (moveTree(dbName, pagID, pagParentID, pos))
         
     },
		afterAjax:function()
		{
			//alert('Loaded');
		},
		animate:true
		,docToFolderConvert:true
		});
	
});

function moveTree(dbName, pagID, pagParentID, pos){
	
    var url="page_form.php";
	url=url+"?dbName="+dbName+"&pagID="+pagID+"&pagParentID="+pagParentID+"&pos="+pos+"&DataAction=move";
    
    $.ajax({
        type: "GET",
        url: url
    }).done(function( msg ) {
        //alert( msg );
    });
    
}
