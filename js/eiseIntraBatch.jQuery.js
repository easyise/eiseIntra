/**
 * eiseIntraBatch jQuery plug-in 
 *
 * @version 1.00
 * @author Ilya S. Eliseev http://e-ise.com
 * @requires jquery
 * @requires jqueryUI
 */
(function( $ ){

var $dlg;

var conf = {
    url : 'about:blank'
    , timeoutTillAutoClose: 10000 // milliseconds, 10000=10 secs
    , flagAutoReload: true // reload currently open page after batch run
    , rows: 30
    , cols: 80
    , scrollDownInterval: 500 // 1/2 of second
    , onloadcomplete : function(){
        _onBatchFinish();
    }
    , buttons: []
    , formToSubmit: null
    , onload: function() {}
    , onclose: function() {return true;}
    , title: null
}

var confSimple = {
    timeoutTillAutoClose: null // no autoclose
    , flagAutoReload: false // do not reload currently open page after batch run
}

var methods = {

/**
 *  This default method shows batch script dialog 
 */
init: function(arg){
    if(typeof arg === 'string' ){
        conf.url = arg;
        if($('body')[0].dataset['conf'] && ($.parseJSON($('body')[0].dataset['conf']).flagBatchNoAutoclose) ){
            $.extend(conf, confSimple);
        } else {
            $.extend(conf,arguments[1] || {}); 
        }
    } else if( typeof arg==='object' ){
        $.extend(conf,arg); 
    }

    var interval;

    $dlg = $('<div class="ei-batch-window"><iframe name="ei-batch"></iframe><div class="buttons"><button class="btn-close">Close</button></div></div>').dialog({
            modal: true
            , width: '80%'
            , title: (!conf.title ? this.text() : conf.title)
            , close: function(event, ui){

                if(typeof   conf.onclose === 'function')
                    if(!conf.onclose.call($dlg))
                        return;

                clearInterval(interval);

                if(conf.flagAutoReload){
                    location.reload();
                }
                
            }
        });
    $dlg.find('button.btn-close').click(function(){
        $dlg.dialog('close').remove();
    });

    var ifr = $dlg.find('iframe')[0];

    interval = setInterval(function(){
        if(ifr)
            ifr.contentWindow.scrollTo(0,999999);
    }, 500);

    ifr.onload = function(){

        if(typeof   conf.onload === 'function'){
            conf.onload.call($dlg)
        }

        if(ifr)
            ifr.contentWindow.scrollTo(0,999999);

        clearInterval(interval);

        if(conf.timeoutTillAutoClose)
            window.setTimeout(function(){
                $dlg.dialog('close');
            }, conf.timeoutTillAutoClose)

    }

    if(conf.formToSubmit && $(conf.formToSubmit)[0] && $(conf.formToSubmit)[0].nodeName.toLowerCase()=='form'){

        $(conf.formToSubmit)
            .attr('target', 'ei-batch')
            .unbind('submit')
            .submit();

    } else {
        ifr.src = conf.url;
    }

    var lh = parseFloat($(ifr).css('line-height').replace(/px$/i, ''));
    $(ifr).css('height', lh*conf.rows+'px');
    $(ifr).css('width', '100%');

    return $dlg;

}

, submit: function(arg){
    this.each(function(){
        if(this.nodeName.toLowerCase()!='form')
            return true; // continue
        
        $.extend(arg, {formToSubmit: this}); 
        

        $(this).eiseIntraBatch(arg)
    })
    
}

}


$.fn.eiseIntraBatch = function( method ) {  
    if ( methods[method] ) {
        return methods[method].apply( this, Array.prototype.slice.call( arguments, 1 ));
    } else {
        return methods.init.apply( this, arguments );
    } 

};


})( jQuery );