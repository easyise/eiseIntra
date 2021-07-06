/**
 * eiseIntraReports jQuery plug-in 
 *
 * @version 1.00
 * @author Ilya S. Eliseev http://e-ise.com
 * @requires jquery
 * @requires jqueryUI
 */
(function( $ ){


var init_copytable = function(){

    var $tab = $(this),
        $tables = $tab.find('table.budget');

    $tables.each(function(){

        bdAddCopyButton.call(this);
        
    })

}

var bdAddCopyButton = function(){

    var table = this;

    $('<button class="bd-copytable"/>').appendTo($(table).find('thead tr:first-of-type th:first-of-type ')).click(function(){

        $(this).find('.bd-copytable').remove();
        bdCopyContent.call(table);
        bdAddCopyButton.call(table);

    });
}

var bdCopyContent = function(){

    var elemToSelect = this;

    if (window.getSelection) {  // all browsers, except IE before version 9
        var selection = window.getSelection ();
        var rangeToSelect = document.createRange ();
        rangeToSelect.selectNodeContents (elemToSelect);

        selection.removeAllRanges();
        selection.addRange (rangeToSelect);

    } else       // Internet Explorer before version 9
        if (document.body.createTextRange) {    // Internet Explorer
                var rangeToSelect = document.body.createTextRange ();
                rangeToSelect.moveToElementText (elemToSelect);
                rangeToSelect.select ();
                
    } else if (document.createRange && window.getSelection) {         
              range = document.createRange();             
              range.selectNodeContents(id);             
        sel = window.getSelection();     
        sel.removeAllRanges();             
        sel.addRange(range);   

    }

    try {
        var successful = document.execCommand('copy');
        var msg = successful ? 'successful' : 'unsuccessful';
        console.log('Copying text command was ' + msg);
        console.log(event);
        $target = $(event.target);
        
        $('<span>',{html:'Copied to clipboard',
                    css:{'padding-left':'10px','color':'red'}})
                    .insertAfter($target)
                    .fadeOut('800', function(){
                        $(this).remove();
                        selection.removeAllRanges();
                    });
        
    } catch (err) {
        // console.log('Oops, unable to copy');
    }

}

var breakdown_success = function(data){

    $dataDialog = $(this)

    $dataDialog.removeClass('spinner')
    $dataDialog.html(data)
    init_copytable.call($dataDialog)

    $('.breakdown-by').off('change').change(function(){

        $dataDialog.html('');
        $dataDialog.addClass('spinner').text('Please wait...');

        $.ajax({url: 'intrapy/breakdown'+location.search+(location.search ? '&' : '?')+'breakdown_by='+encodeURIComponent($(this).val()),
                        data: this.dataset['filters'],
                        contentType: 'application/json',
                        method: 'POST',
                        success: function(data){
                            
                            breakdown_success.call($dataDialog, data)

                        },
                        error: function(){
                            $dataDialog
                                .removeClass('spinner')
                                .addClass('error')
                                .text('Error occured')
                        }
                    });

    })

}

var conf = {
    url : 'about:blank'
}

var methods = {

init: function(arg){
    
    init_copytable.call(this)

    this.find('.budget tr.breakdownable .bd-title').click(function(){

        var strFilters = $(this).parents('tr').first()[0].dataset['filters']

        $('<div class="breakdown"/>').dialog({title: $(this).text()
            , width: '90%'
            , modal: true
            , position: {'my': 'top', 'at': 'top+100', 'of': window}
            , open: function(){

                var $dataDialog = $(this);

                $dataDialog.addClass('spinner').text('Please wait...');

                $.ajax({url: 'intrapy/breakdown'+location.search,
                        data: strFilters,
                        contentType: 'application/json',
                        method: 'POST',
                        success: function(data){
                            
                            breakdown_success.call($dataDialog, data)

                        },
                        error: function(){
                            $dataDialog
                                .removeClass('spinner')
                                .addClass('error')
                                .text('Error occured')
                        }
                    })
            }
            , close: function(){
                    $(this).remove()          
                }
            , buttons: [{text: 'Ok',
                click: function(){
                    $(this).dialog('close').remove()
                }
                 }]})
    })

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