function intraInitializeEntityForm(){
    $(".intraActionButton").bind("click", function(){
        actionButtonClick(this);
    });
    
    $(".intraHistoryTable .unattach").click(function(){
    
        var filGUID = $(this).attr("id").replace("fil_", "");
        
        if(confirm('Are you sure you\'d like to unattach?')){
            location.href = location.href+'&DataAction=deleteFile&filGUID='+filGUID+'&referer='+encodeURIComponent(location.href);
        }
    });
    
    $('#entForm').submit(function(event){
        
        if(!checkFormWithRadios()){
            event.preventDefault(true);
            return false;
        }
            
        if(!checkForm()){
            event.preventDefault(true);
            return false;
        }
            
        return true;
    });
    
}

function actionButtonClick(oBtn){
    
    var arrID = $(oBtn).attr("id").split("_");

    var aclToDo = arrID[0];
    var aclGUID = arrID[1];

    if (aclToDo=="finish"){
        var ata = document.getElementById("aclATA_"+aclGUID);
        if (ata.value==""){
            alert ("ATA for action '"+$("#aclTitle_"+aclGUID).text()+"' should be set");
            ata.focus();
            return false;
        }
        var atd = document.getElementById("aclATD_"+aclGUID);
        if (atd.value==""){
            alert ("ATD for action '"+$("#aclTitle_"+aclGUID).text()+"' should be set");
            atd.focus();
            return false;
        }
    }

    document.getElementById("aclGUID").value = aclGUID;
    document.getElementById("aclToDo").value = aclToDo;

    window.setTimeout("document.getElementById(\"btnsubmit\").disabled = true;", 1);
    $('#entForm').submit();

}

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

function checkFormAccuracy(){
   for (var i=0;i<arrAtr.length;i++){
      var inp = document.getElementById("atr_"+arrAtr[i][0]);
      eval("var regexp = "+arrAtr[i][1]+";");
      if (inp.value!="") {
         var val = inp.value;
         if (!val.match(regexp)){
            alert ("Incorrect data for "+arrAtr[i][0]+".");
            inp.focus();
            return false;
         }
      }
   }
   return true;
}

function checkFormWithRadios(){
    
    var entID = $('#entID').val();
    
    //checking if there are not entItemID
    var entItemID = document.getElementById(entID+"ID");
    var flagUpdateMultiple = false;
    if(entItemID.value==""){
        $("input[name='"+entID+"ID[]']").each(function(){
        if ($(this).attr("checked")){
          entItemID.value += "|"+$(this).attr("value");
        }
        })
        flagUpdateMultiple = true;
    }

    /*
    if (entItemID.value==""){
        alert ("ID is not specified, action cannot proceed");
        return false;
    }
    */

    var arrAct = getActionArray(entID);

    var actID = arrAct[1];
    var aclOldStatusID = arrAct[2];
    var aclNewStatusID = arrAct[3];
    var FlagAutocomplete = arrAct[4];
    var allowSubmit = true;

    var strURL = "ajax_details.php?table=svw_action_attribute&aatActionID="+actID;
    //alert (strURL);
    $.getJSON(strURL,
        function(data){



            if (!flagUpdateMultiple && FlagAutocomplete!="false") {

                $.each(data, function(i, item){
                    var inp = $("#"+item.aatAttributeID);

                    if (inp==null) return;

                    var title = $("#title_"+item.aatAttributeID).text().replace(/((\*){0,1}\:)$/, "");
                    if(item.aatFlagMandatory=="1"){
                        if (inp.val()==""){
                             alert ("Field '"+title+"' is mandatory. Please fill-in.");
                             inp.focus();
                             allowSubmit=false;
                        }
                    }
                    if(item.aatFlagToChange=="1"){
                        if (inp.val()==inp.attr("old_val")){
                             alert ("Field '"+title+"' should be other than '"+inp.attr("old_val")+"'. Please change.");
                             inp.focus();
                             allowSubmit=false;
                        }
                    }



                });

            }

           try {
                allowSubmit = allowSubmit && checkForm();
           } catch(x) {}

            if (allowSubmit) {
                    document.getElementById("actID").value = actID;
                if (aclOldStatusID!="") {
                    document.getElementById("aclOldStatusID").value = aclOldStatusID;
                }
                document.getElementById("aclNewStatusID").value = aclNewStatusID;
                window.setTimeout("document.getElementById(\"btnsubmit\").disabled = true;", 1);
                document.forms.entForm.submit();
            }

        });

    return false;

}

function getActionArray(entID){
    var RadioInputID = "";

    $("input[id^='rad_']").each(function(){
        if ($(this).attr("checked")){
            RadioInputID = ($(this).attr("id"));
            FlagAutocomplete = ($(this).attr("autocomplete"));
        }
    })

    if (RadioInputID!=""){
        var arrAct = RadioInputID.split("_");
    } else {
        var arrAct = ["", "2", "", ""];
    }

    arrAct.push(FlagAutocomplete);

    return arrAct;
}

function promptSuperaction(actID, entID, ID){
    if (actID!="4") { return true; }

    if(document.getElementById("actComments").value!="" &&
       document.getElementById(entID+"StatusID").value!=""
      )
        return true;

    var strURL = "ajax_details.php?table=stbl_status&staEntityID="+entID+"&staFlagDeleted=0";
    $.getJSON(strURL,
        function(data){

            html = '<span>You are executing SuperAction</span><br><br>';
            html += 'Select new status:<br>\r\n<select id="newStatusID">';

            $.each(data, function(i, item){
                html += '<option value="'+item.staID+'">'+item.staTitle+"</option>";
            });

            html += "</select>\r\n<br><br>";

            html += 'Please make a comment:<br><textarea id="comment" rows="4"></textarea>';

            $.prompt(html,{
                buttons: { Ok: true, Cancel: false } ,
                callback: function(v, m, f) {
                    if (v==true) {
                        document.getElementById("actComments").value = inpComment.val();
                        document.getElementById(entID+"StatusID").value=inpNewStatus.val();
                        document.getElementById("aclToDo").value  = "finish";
                        document.getElementById("btnsubmit").click();
                    }

                },
                submit: function(v, m, f) {
                    if (v==true){
                        inpNewStatus = m.children('#newStatusID');
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


        });

    return;


}

function actionChecked(o){

   var arrActSelected = o.id.split("_");
   var actID = arrActSelected[1];

   // remove asterix * from previously selected field titles
   $("div [id^='title']").each(function(){
       $(this).text($(this).text().replace(/\*\:$/, ":"));
   });

   var strURL = "ajax_details.php?table=stbl_action_attribute&aatActionID="+actID;
    $.getJSON(strURL,
        function(data){

            $.each(data, function(i, item){
                var divTitle = $("#title_"+item.aatAttributeID);
                var strTitleText = divTitle.html();
                if (strTitleText==null) return;

                //mark mandatory fields with asterixes *
                if(item.aatFlagMandatory=="1"){
                    strTitleText = strTitleText.replace(/\:$/, "*:");
                }
                $("#title_"+item.aatAttributeID).html(strTitleText);
            });

        });

    return;

}

function intializeComments(entID){
    var divControl = document.getElementById("intra_comment_contols");
    if (divControl==null)
        return false;

    var divLastComment  = document.getElementById("intra_comment");

    /*
    document.getElementById("intra_comment_attach").onclick=function(){
        commentAttachFile(entID);
    }
    */
    document.getElementById("intra_comment_add").onclick=function(){
        commentAdd(entID, this);
    }

    divLastComment.onfocus = function(){
        
        $(divControl).offset({
            left: $(this).width(true)+$(this).offset().left
            , top: $(this).offset().top
            });
        divControl.style.visibility = "visible";
        /*
        var arrXY = getNodeXY(this);
        divControl.style.left = divLastComment.offsetWidth+arrXY[0]+"px";
        divControl.style.top = arrXY[1]+"px";
        
        */
    }
    divLastComment.onblur = function(){
        //divControl.style.visibility = "hidden";
        return false;
    }

}

function commentAdd(entID, oControl){
    var divLastComment  = document.getElementById("intra_comment");
    var entItemID = document.getElementById(entID+"ID").value;

    strURL = location.href;
    strPost = "DataAction=add_comment&scmEntityItemID="+encodeURIComponent(entItemID)+
       "&scmContent="+encodeURIComponent($(divLastComment).text());
    
    $.getJSON(strURL, strPost, function(data){
        if (data.length==0){
            return;
        }
        var scmGUID = data.scmGUID;
        var strUserName = data.user;

        var newDiv = divLastComment.cloneNode(true);
        $(newDiv).text("");
        divLastComment.parentNode.insertBefore(newDiv, divLastComment);
        newDiv.style.display = "block";
        newDiv.onfocus = divLastComment.onfocus;
        newDiv.onblur = divLastComment.onblur;

        divLastComment.id = "scm_"+scmGUID;
        divLastComment.contentEditable = false;


        var oDIVText = document.createElement("DIV");
        $(oDIVText).text($(divLastComment).text());
        var oDIVUser = document.createElement("DIV");
        oDIVUser.className = "intra_comment_userstamp";
        var cd = new Date();
        $(oDIVUser).text(strUserName+" at "+cd.getDate()+"."+(cd.getMonth()+1)+"."+cd.getFullYear()+":");
        $(divLastComment).text("");
        divLastComment.appendChild(oDIVUser);
        divLastComment.appendChild(oDIVText);
        divLastComment.onfocus = null;
        divLastComment.onclick = function(){ showCommentDelete(this) };

        oControl.parentNode.style.visibility = "hidden";
        
    });

}

function showCommentDelete(oDiv){
    var divControl = document.getElementById("intra_comment_delete");
    
    $(divControl).offset({
            left: $(oDiv).width(true)+$(oDiv).offset().left
            , top: $(oDiv).offset().top
            });
    divControl.style.visibility = "visible";
    
    divControl.onclick = function(){
       commentDelete(oDiv, this);
    }

}

function commentDelete(oDivToDelete, oDivControl){
    var scmID = oDivToDelete.id.replace("scm_", "");
    if (!confirm("Are you sure you'd like to delete this comment?")){
        return false;
    }

    strURL = location.href;
    strPost = "DataAction=delete_comment&scmGUID="+encodeURIComponent(scmID);

    oHttp = GetXmlHttpObject();
    oHttp.open("POST", strURL , true);
    oHttp.setRequestHeader("Content-Type",  "application/x-www-form-urlencoded");
    oHttp.send(strPost);
    oHttp.onreadystatechange = function () {
     if (oHttp.readyState == 4) {
            strPost = "";
            oDivControl.style.visibility = "hidden";
            $(oDivToDelete).remove();
     }
    }
}

function GetXmlHttpObject()
{
  var xmlHttp=null;
  try
    {
    // Firefox, Opera 8.0+, Safari
    xmlHttp=new XMLHttpRequest();
    }
  catch (e)
    {
    // Internet Explorer
    try
      {
      xmlHttp=new ActiveXObject("Msxml2.XMLHTTP");
      }
    catch (e)
      {
      xmlHttp=new ActiveXObject("Microsoft.XMLHTTP");
      }
    }
  return xmlHttp;
}

function checkForm(){
    return true;
};