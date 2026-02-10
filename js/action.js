/**
 * eiseIntraEntityItemForm jQuery plugin
 */
(function( $ ){

var ajaxActionURL = "ajax_details.php";

var actionChoosen = function($initiator, $form, fnCallback){

    var entID = $form.eiseIntraForm('value', 'entID'),
        entItemID_field = entID+'ID',
        entItemID = $form.eiseIntraForm('value', entItemID_field),
        strActData = $initiator[0].dataset.action,
        actData = (strActData 
            ? $.parseJSON(strActData) 
            : {actID: ( $initiator.attr('type')=='radio' ? $initiator.val() : $initiator.attr('act_id') ),
                aclOldStatusID: $initiator.attr('orig'),
                aclNewStatusID: $initiator.attr('dest'),
            });

    var strURL = ( strActData ? location.pathname+location.search+'&' : ajaxActionURL+'?' )+"DataAction=getActionDetails&actID="+encodeURIComponent(actData.actID)
        +'&entID='+encodeURIComponent(entID)
        +'&'+entItemID_field+'='+encodeURIComponent(entItemID);

    var flagAutocomplete = $initiator.attr('autocomplete')==undefined ? true : false;
    
    $.getJSON(strURL,
        function(response){

            var data = (response.data || response);

            if (data.ERROR || (response.status && response.status != 'ok')){
                alert(data.ERROR || response.message);
                return;
            }
            
            var strMandatorySelector = "";

            if (data.act.aatFlagMandatory)
                $.each(data.act.aatFlagMandatory, function(i, item){
                    
                    strMandatorySelector += (strMandatorySelector=='' ? '' : ', ')+ ("#"+i);
                        
                });

            var oRet = $.extend(actData, {
                //mandatory: strMandatorySelector,
                //flagAutocomplete: flagAutocomplete,
                act: $.extend(data.act, {mandatorySelector: strMandatorySelector}),
                atr: data.atr,
            });

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

var eiseIntraActionMultiple = function(event, $form, callback){
                
    event.preventDefault(true);

    var $initiatorButton = $(this);

    actionChoosen($(this), $form, function(o){

        if(o.act.actID=='3'){
            if(!confirm("Are you sure you'd like to delete?")){
                return;
            }
        }

        var arrFieldsToFill = [];
        if (o.act.actFlagComment=='1'){
            arrFieldsToFill.push({name: 'aclComments', title: 'Comments', type: 'textarea', required: true});
        }

        if( arrFieldsToFill.length>0 ){
            var $frm = $.fn.eiseIntraForm('createDialog', {fields: arrFieldsToFill
                , title: $initiatorButton.val() || $initiatorButton.text()
                , onsubmit: function(newValues){

                    $form.eiseIntraForm('fill', $.extend(o, newValues), {createMissingAsHidden: true});
                    callback.call($form, o, $initiatorButton);

                    return false;

                }
            });

            return;

        }

        $form.eiseIntraForm('fill', o, {createMissingAsHidden: true});

        callback.call($form, o, $initiatorButton);

    });



}

var eiseIntraActionSubmit = function(event, $form){
                
        event.preventDefault(true);

        var $initiatorButton = $(this);

        actionChoosen($(this), $form, function(o){

            if(o.act.actID=='3'){
                if(confirm("Are you sure you'd like to delete?")){
                    location.href=location.pathname+location.search+"&DataAction=delete";
                }
                return;
            }

            $form.eiseIntraForm("makeMandatory", {
                strMandatorySelector: o.act.mandatorySelector
                , flagDontSetRequired : ($form.data('eiseIntraForm').conf.flagUpdateMultiple || o.act.actFlagAutocomplete!='1')});
            
            $form.find("#aclGUID").val(o.aclGUID);

            var arrFieldsToFill = [];
            if(o.act.aatFlagMandatory)
                $.each(o.act.aatFlagMandatory, function(fieldName, arrFlags){
                    $inp = $form.find('#'+fieldName);
                    if((typeof($inp[0])=='undefined' || $inp.attr('readonly'))
                        && ($inp.length && $inp.attr('type')!='combobox')
                    ){
                        arrFieldsToFill.push({name: o.atr[fieldName].atrID
                            , title: ($form.data('eiseIntraForm').conf.local ? o.atr[fieldName].atrTitleLocal : o.atr[fieldName].atrTitle)
                            , type: (o.atr[fieldName].atrType=='boolean' ? 'checkbox' : o.atr[fieldName].atrType)
                            , defaultValue: o.atr[fieldName].atrDefault
                            , required: ['boolean', 'checkbox'].includes(o.atr[fieldName].atrType) ? false : true});
                    }
                });

            if (o.act.actFlagComment=='1' || ((event.originalEvent.ctrlKey || event.originalEvent.metaKey))){
                arrFieldsToFill.push({name: 'aclComments', title: 'Comments', type: 'textarea', required: true});
            }

            if( arrFieldsToFill.length>0 ){
                var $frm = $.fn.eiseIntraForm('createDialog', {fields: arrFieldsToFill
                    , title: $initiatorButton.val() || $initiatorButton.text()
                    , onsubmit: function(newValues){

                        $form.eiseIntraForm('fill', $.extend(o, newValues), {createMissingAsHidden: true});
                        $form.off('submit').submit();

                        return false;
                    }
                });

                return;

            }

            $form.eiseIntraForm('fill', o, {createMissingAsHidden: true});
            var inpSubmit = $('<input type="submit" style="visibility:hidden" />').appendTo($form);
            $form.off('submit');
            inpSubmit.click();

            return;

        });

}

var fillActionLogAJAX = function($form, extra_entID){

    var entID = (extra_entID ?  extra_entID : $form.data('eiseIntraForm').entID);
    var entItemID = $form.data('eiseIntraForm').entItemID;
    var strActionLogSelectors = (this.dataset && this.dataset.actionLogSelector ? this.dataset.actionLogSelector : '#eiseIntraActionLog, #eif_actionLog'),
        strAjaxActionURL = ajaxActionURL+"?DataAction=getActionLog&entItemID="+encodeURIComponent(entItemID)+
                "&entID="+encodeURIComponent(entID),
        strLocalURL = location.pathname+location.search+"&DataAction=getActionLog";

    if($(this).hasClass('non-clickable')){

        var $div = $(strActionLogSelectors),
            strURL = ($div.hasClass('eif-action-log') ? strAjaxActionURL : strLocalURL)+'&order=reverse',
            conf = {};
        conf.afterFill = function(){
            $div.find('.eif_startblock').each(function(){
                // console.log( $(this).next('.eif_tr_traced')[0] )
            })
        }
        $div.find('.eif_ActionLog')
            .eiseIntraAJAX('fillTable', strURL, conf);

        return;
    } 

    if(!this.htmlStatusLog){
        var $f = $(strActionLogSelectors);
        if($f[0]){
            this.htmlStatusLog = $f[0].outerHTML;
            $f.remove();
        }
    }

    if(!this.htmlStatusLog)
        return

    var $div = $(this.htmlStatusLog),
        strURL = $div.hasClass('eif-action-log') ? strAjaxActionURL : strLocalURL;

    $div.dialog({
                modal: true
                , width: '40%'
            })
    .find('.eif_ActionLog')
    .eiseIntraAJAX('fillTable', strURL);

}

var fillChecklistAJAX = function($form, extra_entID){

    var selector = '.eif-checklist table tbody',
        $tbody = $(selector);
        
    if(!$tbody[0])
        return;

    var entID = (extra_entID ?  extra_entID : $form.data('eiseIntraForm').entID);
    var entItemID = $form.data('eiseIntraForm').entItemID;
    var url = ajaxActionURL+"?DataAction=getChecklist&entItemID="+encodeURIComponent(entItemID)+
                "&entID="+encodeURIComponent(entID),
        urlLocal = location.pathname+location.search+"&DataAction=getChecklist";

    var conf = {};
    conf.afterFill = function(){
        $div.find('.eif_startblock').each(function(){
            // console.log( $(this).next('.eif_tr_traced')[0] )
        })
    }

    $tbody.eiseIntraAJAX('fillTable', urlLocal, conf);

}

var showMessages = function($form, options={}){

    var entID = $form.data('eiseIntraForm').entID;
    var entItemID = $form.data('eiseIntraForm').entItemID;

    if(!this.htmlMsgForm){
        var $f = $('#eiseIntraMessageForm, #ei_message_form');
        if($f[0]){
            this.htmlMsgForm = $f[0].outerHTML;
            $f.remove();
        }
    }
    if(!this.htmlMsgList){
        var $f = $('#eiseIntraMessages, #ei_messages');
        if($f[0]){
            if($f[0].id=='ei_messages')
                this.flagURLSelf = true;
            this.htmlMsgList = $f[0].outerHTML;
            $f.remove();    
        }
    }

    var msgmng = this;

    var showNewMessageForm = function(fnCallback){
        var $frm = $(msgmng.htmlMsgForm).dialog({modal: true
                    , width: '400px'})
                    .eiseIntraForm();
        $frm.submit(function(ev){
            return $frm.eiseIntraForm('validate')
        })
        $frm.find('#msgClose')[0]
            .onclick = function(){
                $frm.dialog('close').remove();
            };
        if(fnCallback && typeof(fnCallback)=='function'){
            fnCallback.call($frm);
        }
    }

    if(options && options.showNewMessageForm){
        return showNewMessageForm(options.fnCallback);
    }

    var strURL = (msgmng.flagURLSelf 
        ? location.pathname+location.search 
        : "ajax_details.php?entItemID="+encodeURIComponent(entItemID)+"&entID="+encodeURIComponent(entID)
    ) + "&DataAction=getMessages";
    
    $.getJSON(strURL, function(response){
        if(response.data && response.data.length==0){
            
                showNewMessageForm();

        }
        else {
            var $list = $(msgmng.htmlMsgList).dialog({
                modal: true
                , width: '600px'
                })
                .eiseIntraForm();

            $list.eiseIntraAJAX('fillTable', response.data);

            $list.find('#msgNew')[0]
                    .onclick = function(){
                        $list.dialog('close').remove();
                        showNewMessageForm();
                    };
        }
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
            $form = $(this),
            data = $this.data('eiseIntraForm');
        
        var entID = $this.find('#entID').val();
        var $entItemID = ($this.find('#'+entID+'ID')[0] 
                ? $this.find('#'+entID+'ID') 
                : ( $form.find('input[data-pk="1"]')[0]
                    ? $form.find('input[data-pk="1"]')
                    : null)
                ),
            entItemID = $entItemID ? $entItemID.val() : null;
        var conf = $('body').eiseIntra('conf');

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
        var $radios = $this.find('fieldset.eiseIntraActions input.eiseIntraRadio');
        $radios.click(function(){
                       
            actionChoosen($(this), $form, function(o){
                $this.eiseIntraForm("makeMandatory", {
                    strMandatorySelector: o.act.mandatorySelector
                    , flagDontSetRequired : (data.conf.flagUpdateMultiple || o.act.actFlagAutocomplete!='1')});
            });

        })
        /********** initialize submit buttons ***********/
        $this.find('input.eiseIntraActionSubmit, button.eiseIntraActionSubmit').click( function(ev) { 
            var $initiator = $(this);
            if($this.hasClass('ei-form-multiple')){
                eiseIntraActionMultiple.call(this, ev, $this, function(o){
                    $('.eiseList').eiseIntraEntityItemList( 'submitForm', $this, $initiator.val() || $initiator.text() );
                });
                return false;
            }
            eiseIntraActionSubmit.call(this, ev, $this) 

        });

        $('a[href="#ei_action"]').click( function(ev) { eiseIntraActionSubmit.call(this, ev, $this); return false; });
        $('a[href="#ei_messages"]').click( function(ev) {
            $this.eiseIntraEntityItemForm('showMessages');
            return false;
        });
        $('a[href="#ei_files"]').on('click', function(ev) { 
                $('#ei_files').dialog({modal: true, width: '40%'})
                    .eiseIntraAJAX('initFileUpload')
                    .find('tbody')
                    .eiseIntraAJAX('fillTable', location.pathname+location.search+"&DataAction=getFiles");  
                return false; 
            });

        $('a[href="#ei_get_whos_next"]').click( function(ev) { 

                var $initiator = $(this);
                $.get(location.pathname+location.search+'&DataAction=get_whos_next', function(response){
                    $(response).dialog({
                        title: $initiator.text(),
                        close: function(){
                            $(this).remove();
                        },
                        position: {
                            my: "left top",
                            at: 'left top',
                            of: $initiator
                          },
                        width: 'auto',
                        height: 'auto',
                        modal: false,
                        open: function(){
                            $('.ei-whos-next .whos-next-status .actions > li ul li.users .user-info.clickable').click(function(){
                                var usrID = $(this)[0].dataset.usrid,
                                    userName = $(this).text().replace(/\([^\)]*\)[,\s]*$/, '').trim();
                                var action = $(this).parents('.ei-whosnext-action ')[0].dataset.actTitle;
                                showMessages($form, {showNewMessageForm: true, 
                                        fnCallback: function(){
                                            var $frm = this;    
                                            $frm.find('#msgToUserID').val(usrID);
                                            $frm.find('#msgToUserID_text').val(userName);
                                            $frm.find('#msgSubject').val('Прошу '+action);
                                            $frm.find('#msgText').val('Здравствуйте, '+userName+'!\n\nПрошу Вас выполнить действие: '+action+'\n\nСпасибо!\n');
                                        }
                                    });
                            });
                        },
                        buttons: [{text: 'Ok', click: function(){
                            $(this).dialog('close').remove();
                        }}]
                    });
                })  
                return false; 
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
        $form.find('.eif_curStatusTitle.clickable').click(function(){
            fillActionLogAJAX.call(this, $form, this.dataset.entID);
        });

        var statusFieldNonClickable = $this.find('.eif_curStatusTitle.non-clickable')[0];
        if(statusFieldNonClickable){
            fillActionLogAJAX.call(statusFieldNonClickable, $form, statusFieldNonClickable.dataset.entID);
        }

        fillChecklistAJAX($form, this.dataset.entID);

        $form.submit(function(event) {

            if($radios.length>0){
                $form.eiseIntraEntityItemForm("checkAction", function(){
                    if ($form.eiseIntraForm("validate")){
                        window.setTimeout(function(){$form.find('input[type="submit"], input[type="button"]').each(function(){this.disabled = true;})}, 1);
                        $form[0].submit();
                    } else {
                        $form.eiseIntraEntityItemForm("reset");
                    }
                })
            }
            return false;
        
        })

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
    var $this = $(this),
        $form = $(this);
    
    // 1. determine what action is called
    // if old action 
    if (!flagUpdateMultiple && $this.find('#aclGUID').val()!=''){
        callback();
    } else { // if new action - check mandatory fields
        
        var $radios= $this.find('fieldset.eiseIntraActions input.eiseIntraRadio');
        if ($radios.length>0){
            $radios.each(function(){
                if (this.checked){
                    oRadio = $(this);
                    actionChoosen($(this), $form, function(o){
                        
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

fillFileListAJAX: function(conf){

    var $form = this, 
        entID = $form.data('eiseIntraForm').entID,
        entItemID = $form.data('eiseIntraForm').entItemID;

    var strURL = "ajax_details.php?DataAction=getFiles&entItemID="+encodeURIComponent(entItemID)+
        "&entID="+encodeURIComponent(entID)
        , fl = $('.eif-file-dialog')[0];

    if( fl ){
        $(fl.outerHTML).dialog({
                    modal: true
                    , width: (conf && conf.width ? conf.width : '500px')
                })
        .eiseIntraAJAX('initFileUpload', conf)
        .find('tbody')
        .eiseIntraAJAX('fillTable', strURL, conf)
    }

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
},

bookmark: function($elem, callback){

    var strURL = location.href;
    var strPost = {DataAction: 'bookmark'};

    var entID = this.find('#entID').val()
        , entItemID = this.find('#'+entID+'ID').val();

    $.getJSON(strURL, strPost, function(response){
        
                if (response.length==0){
                    return;
                }
                
                if (response.ERROR ){
                    alert(response.ERROR);
                    return;
                }
                
                $elem.addClass(response.data.addClass).removeClass(response.data.removeClass).text(response.data.title).fadeIn(1000);
            }
        );

},

eiseIntraActionSubmit: function(btn, event){
    eiseIntraActionSubmit.call(btn, event, this);
},
eiseIntraActionMultiple: function(btn, event, callback){
    eiseIntraActionMultiple.call(btn, event, this, callback);
}

};


$.fn.eiseIntraEntityItemForm = function( method ) {  


    if ( methods[method] ) {
        return methods[method].apply( this, Array.prototype.slice.call( arguments, 1 ));
    } else if ( typeof method === 'object' || ! method ) {
        return methods.init.apply( this, arguments );
    } else {
        $.error( 'Method ' +  method + ' not exists for jQuery.eiseIntraEntityItemForm' );
    } 

};

})( jQuery );

/**
 * eiseIntraEntityItemList jQuery plugin
 */
(function( $ ){

var entID,
    entIDField,
    entIDsField,
    entIDs;

var getEntIDs = function(){

    entIDs = this.eiseList('getRowSelection');

    if(!entIDs){
        alert("No items selected");
    }

    return entIDs;

}

var methods = {

init: function( options ) {

    var $list = this,
        list = this.eiseList('getListObject');

    $('a[href="#create"]').click(function(){
        if (confirm("Are you sure you\'d like to create new "+list.conf['entTitle']+"?")){
           location.href = list.conf['form']+"?DataAction=insert";
        }
    });

    $('a[href="#form"]').click(function(){
        $list.eiseIntraEntityItemList('multipleEditForm', $(this).text())
    })

    $('a[href="#ei_action_mul"]').click(function(ev){
        $list.eiseIntraEntityItemList('actionButtonClick', $(this), ev);
    });

    entID = list['conf']['prefix'] || list['conf']['entID'];
    entIDField = list['conf']['PK'];
    entIDsField = entIDField+'s';

},

submitForm: function( $form, title ){

    $form.find('input[name="'+entIDField+'"]').remove();
    $form.find('input[name="'+$('body').eiseIntra('conf')['dataActionKey']+'"]').val('updateMultiple');
    $('<input type="hidden" name="'+entIDsField+'" value="'+entIDs+'">').appendTo($form);
    window.setTimeout(function(){$form.find('input[type="submit"], input[type="button"]').each(function(){this.disabled = true;})}, 1);

    $form.eiseIntraBatch('submit', {
        flagAutoReload: true
        , timeoutTillAutoClose: null
        , title: title
        , onload: function(){
            if($form.hasClass('ui-dialog-content'))
                $form.dialog('close')
            $form.remove();
        }
    });

},

actionButtonClick: function($initiator, ev){
    
    if(!getEntIDs.call(this))
        return;

    var dataset = JSON.parse($initiator[0].dataset['action']);

    var $list = this;

    var $form = $('<form/>')
        .attr('method', 'POST')
        .attr('action', this.eiseList('getListObject').conf['form']);
    $('<input type="hidden">').attr('name', $('body').eiseIntra('conf')['dataActionKey']).val('updateMultiple').appendTo($form);
    // $('<input type="hidden">').attr('name', 'entID').val(entID).appendTo($form);
    $('<input type="hidden">').attr('name', entIDsField).val(entIDs).appendTo($form);
    $.each(dataset, function(key, value){
        $('<input type="hidden">').attr('name', key).val(value).appendTo($form);    
    })

    $form.appendTo('body');

    $form.eiseIntraEntityItemForm().eiseIntraEntityItemForm('eiseIntraActionMultiple', $initiator, ev, function(){
        $list.eiseIntraEntityItemList('submitForm', $form, $initiator.text());
    })
        
},

multipleEditForm: function(title){

    var $list = this;

    if(!this.formHTML){
        var selForm = '.eiseIntraMultiple',
            $formTemplate = $(selForm);    
        this.formHTML = (this.formHTML ? this.formHTML : ($formTemplate[0] ? $formTemplate[0].outerHTML : ''));    
        $formTemplate.remove(); 
    }

    if(!getEntIDs.call(this))
        return;

    var $form = $(this.formHTML).appendTo('body');

    $form
        .prop('title', title)
        .dialog({
            modal: false
            , width: $(window).width()*0.80
            , close: function(){
                $form.remove();
            }
        })
        .eiseIntraForm()
        .eiseIntraEntityItemForm({flagUpdateMultiple: true});

    if (typeof multipleEditFormInit === 'function'){
        multipleEditFormInit.call($form);
    }

    $form.off('submit')
        .on('submit', function(event){

            $form.eiseIntraEntityItemForm("checkAction", function(){
                if ($form.eiseIntraForm("validate")){
                    
                    $list.eiseIntraEntityItemList('submitForm', $form, title);
                    
                } else {
                    $form.eiseIntraEntityItemForm("reset");
                }
            })
        
            return false;
        
        });
}

}

$.fn.eiseIntraEntityItemList = function( method ) {  


    if ( methods[method] ) {
        return methods[method].apply( this, Array.prototype.slice.call( arguments, 1 ));
    } else if ( typeof method === 'object' || ! method ) {
        return methods.init.apply( this, arguments );
    } else {
        $.error( 'Method ' +  method + ' not exists for jQuery.eiseIntraEntityItemList' );
    } 

};

})( jQuery );


function showMultipleEditForm(strTitle){

    if(!this.formHTML){
        var selForm = '.eiseIntraMultiple',
            $formTemplate = $(selForm);    
        this.formHTML = (this.formHTML ? this.formHTML : ($formTemplate[0] ? $formTemplate[0].outerHTML : ''));    
        $formTemplate.remove(); 
    }

    var $form = $(this.formHTML).appendTo('body'),
        entIDs = $('.eiseList').eiseList('getRowSelection'),
        entIDField = $form.find('input[name="entID"]').val()+'ID';

    if(!entIDs){
        alert("No items selected");
        return;
    }
    
    $form
        .prop('title', strTitle)
        .dialog({
            modal: false
            , width: $(window).width()*0.80
        })
        .eiseIntraForm()
        .eiseIntraEntityItemForm({flagUpdateMultiple: true})
        .off('submit')
        .on('submit', function(event) {

            $form.eiseIntraEntityItemForm("checkAction", function(){
                if ($form.eiseIntraForm("validate")){
                    window.setTimeout(function(){$form.find('input[type="submit"], input[type="button"]').each(function(){this.disabled = true;})}, 1);
                    $form.find('input[name="'+entIDField+'"]').val(entIDs);

                    $form.eiseIntraBatch('submit', {
                        flagAutoReload: true
                        , title: strTitle
                        , onload: function(){
                            $form.dialog('close').remove();
                        }
                    });
                } else {
                    $form.eiseIntraEntityItemForm("reset");
                }
            })
        
            return false;
        
        });

}