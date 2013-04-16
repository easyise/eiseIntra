(function( $ ){

var actionRadioClick = function(oRadio, fnCallback){
    
    var strURL = "ajax_details.php?actID="+oRadio.val();
    
    var flagAutocomplete = oRadio.attr('autocomplete')==undefined ? true : false;
    
    $.getJSON(strURL,
        function(data){
            
            if (data.ERROR){
                alert(data.ERROR);
                return;
            }
            
            var strIDs = "";
            $.each(data.aat, function(i, item){
                    
                if(item.aatFlagMandatory=="1"){
                    strIDs += (strIDs=='' ? '' : ', ')+ ("#"+item.aatAttributeID);
                }
                    
            });
            
            fnCallback({mandatory: strIDs
                , flagAutocomplete: flagAutocomplete
                , actID: oRadio.val()
                , aclOldStatusID: oRadio.attr('orig')
                , aclNewStatusID: oRadio.attr('dest')
            });
    });
}


var actionButtonClick = function (oBtn, fnCallback){
    
    var arrID = $(oBtn).attr("id").split("_");

    var aclToDo = arrID[0];
    var aclGUID = arrID[1];

    var strURL = "ajax_details.php?aclGUID="+encodeURIComponent(aclGUID);
    
    $.getJSON(strURL,
        function(data){
            
            if (data.ERROR){
                alert(data.ERROR);
                return;
            }
            
            var strIDs = "#aclATA_"+aclGUID+", #aclATD_"+aclGUID;
            $.each(data.aat, function(i, item){
                    
                if(item.aatFlagMandatory=="1"){
                    strIDs += (strIDs=='' ? '' : ', ')+ (
                        item.aatFlagToTrack 
                        ? "#"+item.aatAttributeID+'_'+aclGUID
                        : "#"+item.aatAttributeID
                        );
                }
                    
            });
            
            fnCallback({mandatory: strIDs
                , actID: data.act.actID
                , aclGUID: aclGUID
                , aclOldStatusID: data.act.aclOldStatusID
                , aclNewStatusID: data.act.aclNewStatusID
                , aclToDo: aclToDo
            });
    });
    
}

function showCommentControls($controls, $parent, options){

    $controls
        .css("display", "block")
        .offset({
            left: $parent.outerWidth()+$parent.offset().left
            , top: $parent.offset().top
        });
    
    $controls.find('input').css('display', 'none');
    
    if (options.add!=undefined) {
        $controls.find('.eiseIntraComment_add')
            .css("display", "block")[0].onclick = null;
            
        $controls.find('.eiseIntraComment_add')[0].onclick = 
            function(){options.add()};
    }
     
    if (options.remove!=undefined){
        $controls.find('.eiseIntraComment_remove')
            .css("display", "block")[0].onclick = null;
            
        $controls.find('.eiseIntraComment_remove')[0].onclick =  
            function(){
                var scmID = $parent[0].id.replace("scm_", "");
                if (!confirm("Are you sure you'd like to delete this comment?")){
                    return false;
                }
                window.setTimeout(function(){$controls.slideUp()}, 3000);
                options.remove(scmID);
            };
    }

}

var methods = {

init: function( options ) {

    return this.each(function(){
         
        var $this = $(this),
            data = $this.data('eiseIntraForm');
        
        var entID = $this.find('#entID').val();
        var entItemID = $this.find('#'+entID+'ID').val();
        var conf = $.parseJSON($('#eiseIntraConf').val());
        
        if ( ! data ) {
            
            $(this).data('eiseIntraForm', {
                form : $this,
                conf: $.extend( conf, options),
                entID: entID,
                entItemID: entItemID
            });
            
        } else {
            $this.data('eiseIntraForm').conf = $.extend( conf, options);
            $this.data('eiseIntraForm').entID = entID;
            $this.data('eiseIntraForm').entItemID = entItemID;
        }
        
        $this.find('fieldset.eiseIntraActions input.eiseIntraRadio').click(function(){
            actionRadioClick($(this), function(obj){
                $this.eiseIntraForm("makeMandatory", {
                    strIDs: obj.mandatory
                    , flagDontSetRequired : (data.conf.flagUpdateMultiple || !obj.flagAutocomplete)});
            });
        })
    
        $this.find(".eiseIntraActionButton").bind("click", function(){
            actionButtonClick(this, function(o){
                $this.eiseIntraForm("makeMandatory", {
                    strIDs: o.mandatory
                    , flagDontSetRequired: (o.aclToDo!='finish')});
                $this.find("#aclGUID").val(o.aclGUID);
                $this.find("#aclToDo").val(o.aclToDo);
                $this.submit();
            });
        });
        
        //comments
        var $controls = $this.find('.eiseIntraComment_contols');
        var $inpComment = $this.find("textarea.eiseIntraComment")
            .focus(function(){
                $textarea = $(this);
                showCommentControls($controls, $textarea, {
                    add: function(){
                        $this.eiseIntraEntityItemForm("commentAdd", {
                            entItemID: entItemID
                            , text: $inpComment.val()
                            , success : function(data){
                                var scmGUID = data.scmGUID;
                                var strUserStamp = data.user;
                    
                                var newCommentHTML = '<div id="scm_'+scmGUID+'" class="eiseIntraComment">'+
                                    '<div class="eiseIntraComment_userstamp">'+data.user+'</div>'+
                                    '<div>'+data.text+'</div>'+
                                    '</div>';
                                
                                $newComment = $inpComment.after(newCommentHTML).next();
                                
                                $newComment.click(function(){
                                    showCommentControls($controls, $newComment, {remove: function(scmID){
                                        $this.eiseIntraEntityItemForm("commentRemove", {scmID: scmID, success: function(){
                                            $newComment.slideUp().remove();$controls.slideUp();
                                        }})
                                    }});
                                });
                                
                                $inpComment.val('');
                            }
                        })
                    }
                });
                })
            .blur(function(){
                window.setTimeout(function(){$controls.slideUp()}, 3000);
            });
        $this.find('div.eiseIntraComment_removable').click(function(){
            var $divComment = $(this);
            showCommentControls($controls, $divComment
            , {remove:function(scmID){
                $this.eiseIntraEntityItemForm("commentRemove", {scmID: scmID, success:function(){
                    window.setTimeout(function(){$divComment.slideUp().remove();$controls.slideUp();}, 2000);
                }})
            }});
        });
        
        //files
        $this.find(".intraHistoryTable .unattach").click(function(){
    
            var filGUID = $(this).attr("id").replace("fil_", "");
            
            if(confirm('Are you sure you\'d like to unattach?')){
                location.href = location.href+'&DataAction=deleteFile&filGUID='+filGUID+'&referer='+encodeURIComponent(location.href);
            }
        });
        
    });
},

checkAction: function(callback){
    
    return this.each(function(){
    
    var conf = $(this).data('eiseIntraForm').conf;
    var flagUpdateMultiple = conf.flagUpdateMultiple;
    var entID = $(this).data('eiseIntraForm').entID;
    var entItemIDInput = $(this).find('#'+entID+'ID');
    var $this = $(this);
    
    // 1. determine what action is called
    // if old action 
    if (!flagUpdateMultiple && $this.find('#aclGUID').val()!=''){
        callback();
    } else { // if new action - check mandatory fields
        
        if (flagUpdateMultiple){
        
            var entIDs = '';
            
            $("input[name='sel_"+entID+"[]']").each(function(){
                if ($(this)[0].checked){
                    entIDs += "|"+$(this).attr("value");
                }
                })
                
            if(entIDs==""){
                alert("Nothing selected");
                return false;
            }
            entItemIDInput.val(entIDs);
        }
        
        $this.find('fieldset.eiseIntraActions input.eiseIntraRadio').each(function(){
            if (this.checked){
                oRadio = $(this);
                actionRadioClick($(this), function(obj){
                    $this.find('#actID').val(oRadio.val());
                    $this.find('#aclOldStatusID').val(oRadio.attr('orig'));
                    $this.find('#aclNewStatusID').val(oRadio.attr('dest'));
                    
                    if (!obj.flagAutocomplete){
                        $this.find('#aclToDo').val('start');
                    } else {
                        $this.eiseIntraForm("makeMandatory", {
                            strIDs: obj.mandatory
                            , flagDontSetRequired : (flagUpdateMultiple || !obj.flagAutocomplete)
                            });
                    }
                    callback();
                });
                return false; // break
            }
        })
            
    }
    
    });
    
},

reset: function(  ) {  
    return this.each(function(){
        $(this).find('#actID').val('');
        $(this).find('#aclOldStatusID').val('');
        $(this).find('#aclNewStatusID').val('');
        $(this).find('#aclToDo').val('');
    })
},

commentAdd: function(options){
    
    
    var strURL = location.href;
    var strPost = "DataAction=add_comment&scmEntityItemID="+encodeURIComponent(options.entItemID)+
       "&scmContent="+encodeURIComponent(options.text);
    
    var $form = $(this);
    
    $.getJSON(strURL, strPost, function(data){
        if (data.length==0){
            return;
        }
        
        if (data.ERROR ){
            alert(data.ERROR);
            return;
        }
        
        data.text = options.text;
        
        options.success(data);
        
    });

},

commentRemove: function(options){
    var scmID = options.scmID;

    strURL = location.href;
    strPost = "DataAction=delete_comment&scmGUID="+encodeURIComponent(scmID);

     $.getJSON(strURL, strPost, function(data){
        
        if (data.length==0){
            return;
        }
        
        if (data.ERROR ){
            alert(data.ERROR);
            return;
        }
        
        options.success(data);
        
    });
}

};


$.fn.eiseIntraEntityItemForm = function( method ) {  


    if ( methods[method] ) {
        return methods[method].apply( this, Array.prototype.slice.call( arguments, 1 ));
    } else if ( typeof method === 'object' || ! method ) {
        return methods.init.apply( this, arguments );
    } else {
        $.error( 'Method ' +  method + ' not exists for jQuery.eiseIntraForm' );
    } 

};

})( jQuery );




function checkForComment(actID){
    for(var i=0;i<arrActAtr.length;i++)
        if (arrActAtr[i][0]==actID){
            if (arrActAtr[i][3]=="0")
                return true;
            break;
        }

    if(document.getElementById("actComments").value!="")
        return true;

    $.prompt('Please make a comment:<br><textarea id="comment" rows="4"></textarea>',{
        buttons: { Ok: true, Cancel: false } ,
        callback: function(v, m, f) {
            if (v==true) {
                document.getElementById("actComments").value = inpComment.val();
                document.getElementById("btnsubmit").click();
            }

        },
        submit: function(v, m, f) {
            if (v==true){
                inpComment = m.children('#comment');
                if(inpComment.val() == ""){
                    alert ("Add comment or click cancel");
                    inpComment.css("border","solid #ff0000 1px");
                    inpComment.focus();
                    return false;
                } else
                return true;

            } else
                return true;
        },
        prefix: 'cleanblue'
        });

    return false;
}

function showMultipleEditForm(strTitle){

    var form = '.eiseIntraMultiple';
    
    $(form).attr('title', strTitle);
    $(form).dialog({
            modal: false
            , width: $(window).width()*0.80
        });
        
}