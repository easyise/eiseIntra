$(document).ready(function(){
    
    $('body').eiseIntra();
    
})

$(window).load(function(){  

    eiseIntraAdjustFrameContent();

    $('#menubar a.confirm').click(function(event){
        
        if (!confirm('Are you sure you want to execute "'+$(this).text()+'"?')){
            event.preventDefault();
            return false;
        } else {
            return true;
        }

    });
    
});