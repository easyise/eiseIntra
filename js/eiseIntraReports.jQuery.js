/**
 * eiseIntraReports jQuery plug-in 
 *
 * @version 1.00
 * @author Ilya S. Eliseev http://e-ise.com
 * @requires jquery
 * @requires jqueryUI
 */
(function( $ ){

var conf = {
    url : 'about:blank'
}

var methods = {

init: function(arg){
    

},

load: function(arg, fn){

    if(typeof arg === 'string' ){
            
        conf.url = arg;
        $.extend(conf,arguments[1] || {}); 
        
    } else if( typeof arg==='object' ){
        $.extend(conf,arg); 
    }

    var $elem = this;

    $elem[0].dataset.url = conf.url;

    $elem.append('<div class="eif_spinner" style="width:400px;"><div>Loading...</div></div>');

    $elem.load(conf.url, fn)

}



}


$.fn.eiseIntraReports = function( method ) {  
    if ( methods[method] ) {
        return methods[method].apply( this, Array.prototype.slice.call( arguments, 1 ));
    } else {
        return methods.init.apply( this, arguments );
    } 

};


})( jQuery );