$(function(){

    $('body').eiseIntra();

    if( window.parent.document ){
        var $parentMenu = $('.ei-sidebar-menu', window.parent.document)
        if($parentMenu[0] && !$parentMenu.hasClass('pinned')){
            $parentMenu.removeClass('visible');
            $parentMenu.click();
        }
    }

    window.setTimeout(eiseIntraAdjustFrameContent, 500);

    $('#menubar a.confirm').click(function(event){
        
        if (!confirm('Are you sure you want to execute "'+$(this).text()+'"?')){
            event.preventDefault();
            return false;
        } else {
            return true;
        }

    });
    
});