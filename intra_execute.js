$(document).ready(function(){  
    
    eiseIntraAdjustFrameContent();
    MsgShow();	

    $('#menubar a.confirm').click(function(event){
        
        if (!confirm('Are you sure you want to execute "'+$(this).text()+'"?')){
            event.preventDefault();
            return false;
        } else {
            return true;
        }

    });
    
});