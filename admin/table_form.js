function tableGridChanged(){

    $('#generateALTER').removeAttr('disabled');

}

function AddField(){
    easyGridAddRowAfterCurrent("tbl");
}

function generateALTER(){
    var strGridName = "tbl";
    var tbl_name = $("#tblName").attr("value");
    var strScript = "";
    
    //for deleted items
    var inpDeleted = document.getElementById("inp_"+strGridName+"_deleted");
    var arrDeleted = inpDeleted.value.split("|");
    for (var i = 0; i<arrDeleted.length; i++)
        if (arrDeleted[i]!=""){
            strScript += (strScript!=""  ? "\r\n\r\n" : "");
            strScript += "ALTER TABLE "+tbl_name;
            strScript += " DROP COLUMN "+arrDeleted[i]+";";
        }
    
    
    // for changed and updated fields
    var colInpUpdated = document.getElementsByName("inp_"+strGridName+"_updated[]"); 
    var colInpID = document.getElementsByName("Field_id[]");
    var colField = document.getElementsByName("Field[]");
    var colType = document.getElementsByName("Type[]");
    var colNull = document.getElementsByName("Null[]");
    var colDefault = document.getElementsByName("Default[]");
    var colComment = document.getElementsByName("Comments[]");
    for (var i=0;i<colInpUpdated.length;i++){
         if (colInpUpdated[i].value=="1"){

            if(colType[i].value==''){
                alert('Type not set for '+colField[i].value);
                colType[i].focus();
                return;
            }

             strScript += (strScript!=""  ? "\r\n\r\n" : "");
             
             strScript += "ALTER TABLE "+tbl_name;
             strType = colField[i].value+" "+colType[i].value+(colNull[i].value=="YES" ? " NULL " : " NOT NULL DEFAULT '"+colDefault[i].value+"'")+
                (colComment[i].value!="" ? " COMMENT '"+colComment[i].value.replace("'","''")+"'" : "");
             if (colInpID[i].value==""){
                //ADD COLUMN
                strScript += " ADD COLUMN "+strType;
             } else {
                //CHANGE COLUMN
                strScript += " CHANGE COLUMN "+colInpID[i].value+" "+strType;
             }
             strScript += 
                   (i>0 ? " AFTER "+colField[i-1].value : "")+";";
         }
    }
    
    $('#textarea_source textarea').text(strScript);

    $('#textarea_source').dialog({modal: true, width:'62%', title: 'ALTER TABLE'})    
    
}