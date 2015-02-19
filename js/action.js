(function( $ ){

var ajaxActionURL = "ajax_details.php";

var actionChoosen = function($initiator, fnCallback){

    var entID = $initiator.parents('form').first().eiseIntraForm('value', 'entID');
        
    var actID = ( $initiator.attr('type')=='radio' ? $initiator.val() : $initiator.attr('act_id') );

    var strURL = ajaxActionURL+"?DataAction=getActionDetails&actID="+encodeURIComponent(actID)+'&entID='+encodeURIComponent(entID);
    
    var flagAutocomplete = $initiator.attr('autocomplete')==undefined ? true : false;
    
    $.getJSON(strURL,
        function(data){
            
            if (data.ERROR){
                alert(data.ERROR);
                return;
            }
            
            var strMandatorySelector = "";

            if (data.act.aatFlagMandatory)
                $.each(data.act.aatFlagMandatory, function(i, item){
                    
                    strMandatorySelector += (strMandatorySelector=='' ? '' : ', ')+ ("#"+i);
                        
                });

            var oRet = {
                actID: actID
                , aclOldStatusID: $initiator.attr('orig')
                , aclNewStatusID: $initiator.attr('dest')
                //, mandatory: strMandatorySelector
                //, flagAutocomplete: flagAutocomplete
                , act: $.extend(data.act, {mandatorySelector: strMandatorySelector})
                , atr: data.atr
                
            };

            fnCallback(oRet);
    });
}

var actionButtonClick = function (oBtn, fnCallback){
    
    var arrID = $(oBtn).attr("id").split("_");

    var aclToDo = arrID[0];
    var aclGUID = arrID[1];

    var strURL = ajaxActionURL+"?DataAction=getActionDetails&aclGUID="+encodeURIComponent(aclGUID);
    
    $.getJSON(strURL,
        function(data){
            
            if (data.ERROR){
                alert(data.ERROR);
                return;
            }
            
            var strMandatorySelector = "#aclATA_"+aclGUID+", #aclATD_"+aclGUID;

            if (data.act.aatFlagMandatory)
                $.each(data.act.aatFlagMandatory, function(field, item){
                    strMandatorySelector += (strMandatorySelector=='' ? '' : ', ')+ (
                        typeof(data.act.aatFlagToTrack[field])!='undefined'
                        ? "#"+field+'_'+aclGUID
                        : "#"+field
                        );
                        
                });
            
            fnCallback({mandatory: strMandatorySelector
                , actID: data.act.actID
                , aclGUID: aclGUID
                , aclOldStatusID: data.act.aclOldStatusID
                , aclNewStatusID: data.act.aclNewStatusID
                , aclToDo: aclToDo
            });
    });
    
}

var fillActionLogAJAX = function($form){
    
    var entID = $form.data('eiseIntraForm').entID;
    var entItemID = $form.data('eiseIntraForm').entItemID;

    var strURL = ajaxActionURL+"?DataAction=getActionLog&entItemID="+encodeURIComponent(entItemID)+
        "&entID="+encodeURIComponent(entID);

    $('#eiseIntraActionLog').dialog({
                modal: true
                , width: '40%'
            })
    .find('.eif_ActionLog')
    .eiseIntraAJAX('fillTable', strURL);

}

var fillFileListAJAX = function($form){

    var entID = $form.data('eiseIntraForm').entID;
    var entItemID = $form.data('eiseIntraForm').entItemID;

    var strURL = "ajax_details.php?DataAction=getFiles&entItemID="+encodeURIComponent(entItemID)+
        "&entID="+encodeURIComponent(entID);

    $('#eiseIntraFileList').dialog({
                modal: true
                , width: '40%'
            })
    .find('tbody')
    .eiseIntraAJAX('fillTable', strURL);   

}

var showMessages = function($form){
    var entID = $form.data('eiseIntraForm').entID;
    var entItemID = $form.data('eiseIntraForm').entItemID;

    var strURL = "ajax_details.php?DataAction=getMessages&entItemID="+encodeURIComponent(entItemID)+
        "&entID="+encodeURIComponent(entID);

    $('#eiseIntraMessages, #eiseIntraMessageForm').eiseIntraForm('init');

    $('#eiseIntraMessages #msgNew')[0].onclick = function(){
        $('#eiseIntraMessages').dialog('close');
        $('#eiseIntraMessageForm').dialog({modal: true
                    , width: '400px'});
    };
    $('#eiseIntraMessageForm #msgClose')[0].onclick = function(){
        $('#eiseIntraMessageForm').dialog('close');
    };

    $('#eiseIntraMessages')
        .eiseIntraAJAX('fillTable', strURL, {afterFill: function(data){
            if(data.data.length==0){
                $('#eiseIntraMessageForm').dialog({modal: true
                    , width: '400px'});
                
            }
            else {
                $('#eiseIntraMessages').dialog({
                    modal: true
                    , width: '600px'
                    });
                
            }
        }}); 
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
            var existingConf = $this.data('eiseIntraForm').conf;
            if (existingConf)
                conf = $.extend( conf, existingConf);

            $this.data('eiseIntraForm').conf = $.extend( conf, options);
            $this.data('eiseIntraForm').entID = entID;
            $this.data('eiseIntraForm').entItemID = entItemID;
        }
        
        /********** initialize radio buttons ***********/
        $this.find('fieldset.eiseIntraActions input.eiseIntraRadio').click(function(){
            
            actionChoosen($(this), function(o){

                $this.eiseIntraForm("makeMandatory", {
                    strMandatorySelector: o.act.mandatorySelector
                    , flagDontSetRequired : (data.conf.flagUpdateMultiple || o.act.actFlagAutocomplete!='1')});
            });

        })
        /********** initialize submit buttons ***********/
        $this.find('input.eiseIntraActionSubmit').click(function(event){
                
                event.preventDefault(true);

                var $initiatorButton = $(this);
                
                actionChoosen($(this), function(o){

                    $this.eiseIntraForm("makeMandatory", {
                        strMandatorySelector: o.act.mandatorySelector
                        , flagDontSetRequired : (data.conf.flagUpdateMultiple || o.act.actFlagAutocomplete!='1')});
                    
                    $this.find("#aclGUID").val(o.aclGUID);

                    var arrFieldsToFill = [];
                    if(o.act.aatFlagMandatory)
                        $.each(o.act.aatFlagMandatory, function(fieldName, arrFlags){
                            if(typeof($this.find('#'+fieldName)[0])=='undefined')
                                arrFieldsToFill.push({name: o.atr[fieldName].atrID
                                    , title: ($this.data('eiseIntraForm').conf.local ? o.atr[fieldName].atrTitleLocal : o.atr[fieldName].atrTitle)
                                    , type: o.atr[fieldName].atrType
                                    , defaultValue: o.atr[fieldName].atrDefault
                                    , required: true});
                        });

                    if (o.act.actFlagComment=='1'){
                        arrFieldsToFill.push({name: 'aclComments', title: 'Comments', type: 'textarea', required: true});
                    }

                    if( arrFieldsToFill.length>0 ){
                        var $frm = $.fn.eiseIntraForm('createDialog', {fields: arrFieldsToFill
                            , title: $initiatorButton.val()
                            , onsubmit: function(newValues){

                                $this.eiseIntraForm('fill', $.extend(o, newValues), {createMissingAsHidden: true});
                                $this.submit();

                                return false;
                            }
                        });

                        return;

                    }
                                        
/*
                    $this.find("#actID").val(o.actID);
                    $this.find("#aclToDo").val(o.aclToDo);
                    $this.find("#aclOldStatusID").val(o.aclOldStatusID);
                    $this.find("#aclNewStatusID").val(o.aclNewStatusID);
*/

                    $this.eiseIntraForm('fill', o, {createMissingAsHidden: true});
                    $this.submit();

                });

        });

        /********** initialize Start/Finish/Cancel buttons ***********/
        $this.find(".eiseIntraActionButton").bind("click", function(){
            actionButtonClick(this, function(o){

                $this.eiseIntraForm("makeMandatory", {
                    strMandatorySelector: o.mandatory
                    , flagDontSetRequired: (o.aclToDo!='finish')});
                $this.find("#aclGUID").val(o.aclGUID);
                $this.find("#aclToDo").val(o.aclToDo);
                $this.find("#aclOldStatusID").val(o.aclOldStatusID);
                $this.find("#aclNewStatusID").val(o.aclNewStatusID);
                $this.submit();

            });
        });
        
        //current status title: clickable and shows history by AJAX
        $this.find('.eif_curStatusTitle').click(function(){
            fillActionLogAJAX($this);
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
        //new files
        $(".eif_filUnattach").click(function(){
    
            var filGUID = $(this).find('.eif_filGUID').val();
            
            if(confirm('Are you sure you\'d like to unattach?')){
                var href = location.href.replace(/\#[a-z0-9\%]+$/, '');
                location.href = href+'&DataAction=deleteFile&filGUID='+filGUID+'&referer='+encodeURIComponent(href);
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
                    entIDs += (entIDs!='' ? "|" : '')+$(this).attr("value");
                }
                })
                
            if(entIDs==""){
                alert("Nothing selected");
                return false;
            }
            entItemIDInput.val(entIDs);
        }
        
        var $radios= $this.find('fieldset.eiseIntraActions input.eiseIntraRadio');
        if ($radios.length>0){
            $radios.each(function(){
                if (this.checked){
                    oRadio = $(this);
                    actionChoosen($(this), function(o){
                        
                        if (o.act.actFlagComment=='1'){
                            var aclComments = prompt('Please comment', '');
                            if (aclComments==null)
                                return;
                            $this.find('#aclComments').val(aclComments);
                        }
                        
                        $this.find('#actID').val(oRadio.val());
                        $this.find('#aclOldStatusID').val(oRadio.attr('orig'));
                        $this.find('#aclNewStatusID').val(oRadio.attr('dest'));
                        
                        if (o.act.actFlagAutocomplete!='1'){
                            $this.find('#aclToDo').val('start');
                        } else {
                            $this.eiseIntraForm("makeMandatory", {
                                strMandatorySelector: o.act.mandatorySelector
                                , flagDontSetRequired : (flagUpdateMultiple || o.act.actFlagAutocomplete!='1')
                                });
                        }
                        callback();
                    });
                    return false; // break
                }
            })
        } else {
            callback();
        }
            
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
},

fillFileListAJAX: function(){
    return fillFileListAJAX(this);
},

showMessages: function(){

    return showMessages(this);

},

sendMessages: function(callback){
    var strURL = location.href;
    var strPost = "DataAction=send_messages";

    $.getJSON(strURL, strPost, function(data){
        
        if (data.length==0){
            return;
        }
        
        if (data.ERROR ){
            alert(data.ERROR);
            return;
        }
        
        callback(data);
        
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


function showMultipleEditForm(strTitle){

    var form = '.eiseIntraMultiple';
    
    $(form).attr('title', strTitle);
    $(form).dialog({
            modal: false
            , width: $(window).width()*0.80
        });
        
}