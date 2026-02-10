/**
 * eiseIntra jQuery plug-in 
 *
 * @version 1.00
 * @author Ilya S. Eliseev http://e-ise.com
 * @requires jquery
 * @requires jqueryUI
 */
(function( $ ){

var conf = {};

var renderMenu = function(){

    var $simpleTreeMenu = $('.ei-sidebar-menu .simpleTree.ei-menu'),
        $sidebarMenu = $('.ei-sidebar-menu .sidebar-menu.ei-menu'),
        $menuContainer = $('.ei-sidebar-menu'),
        pinnedKey = $('body').eiseIntra('conf').menuKey+'_pinned',
        flagPinned = sessionStorage[pinnedKey];

    if(flagPinned){
        $menuContainer.addClass('visible').addClass('pinned');
    }

    if($simpleTreeMenu[0] && typeof $simpleTreeMenu.simpleTree == 'function')
        $simpleTreeMenu.simpleTree({
                autoclose: false,
                drag:false,
                animate:true,
                docToFolderConvert:true
                , afterNodeToggle: function(){
                    window.setTimeout(function(){$(window).resize()}, 100);
                }
                
            });

    if ($sidebarMenu && typeof $.sidebarMenu == 'function')
        $.sidebarMenu($('.sidebar-menu.ei-menu'));

    sideBarMenuChanged();

    $('.ei-sidebar-menu').click(function(){
       sideBarMenuChanged();
    });

    $('.sidebar-toggle').click(function(ev){

        $('.ei-sidebar-menu').toggleClass('visible');

        sideBarMenuChanged();
        ev.stopImmediatePropagation();
    })

    $('.sidebar-pin').click(function(){
        $menuContainer.toggleClass('pinned');
        if( !sessionStorage[pinnedKey] )
            sessionStorage[pinnedKey] = true;
        else 
            sessionStorage.removeItem(pinnedKey);
    }) 

    window.setTimeout(function(){$(window).resize()}, 100);

}

var sideBarMenuChanged = function(){

    this.pane_padding_left = (this.pane_padding_left 
        ? this.pane_padding_left 
        : parseFloat($('.ei-pane').css('padding-left'))
        );

    var $iframe = $('.ei-pane-frame iframe'),
        padding = ($('.ei-sidebar-menu.visible')[0] 
            ? $('.ei-sidebar-menu').outerWidth(true) + this.pane_padding_left
            : this.pane_padding_left);

    $('.ei-pane-frame').css('left', padding );
    $('.ei-pane').css('padding-left', padding );

}

var adjustIframe = function(){
    var ifr = $('.ei-pane iframe')[0];
    if(ifr){
        $(window).resize(function(){
            sideBarMenuChanged();
            //$(ifr).css('width', $('.ei-pane').width()+'px')
            //    .css('height', $('.ei-pane').height()+'px');
        })
    }   
}

var __initRex = function(conf_){

    conf_.rex = {}
    conf_.rexISO = {}
    conf_.rex_replace = {}
    conf_.rex_replace2loc = {}

    var __getRexNReplacement = function(orig, dest, type){

        var reg = orig
                    .replace(new RegExp('\\.', "g"), "\\.")
                    .replace(new RegExp("\\/", "g"), "\\/")
                    .replace(new RegExp("\\:", "g"), "\\:")
                    .replace(new RegExp("\\-", "g"), "\\-")
                    .replace("d", "([0-9]{1,2})")
                    .replace("m", "([0-9]{1,2})")
                    .replace("Y", "([0-9]{4})")
                    .replace("y", "([0-9]{2})")
                    .replace("H", "([0-9]{1,2})")
                    .replace("i", "([0-9]{2})")
                    .replace("s", "([0-9]{2})")
                    .replace('T', '[T ]')
            , a = []
            , replacements = {};
        ['Y', 'm', 'd', 'y', 'H', 'i', 's'].forEach(function(key){
            var o = {'key': key, 'io': orig.indexOf(key)};
            if( o.io>=0 ) {
                a.push(o);
            }
        });
        a.sort(function(elem_a, elem_b){
            return (elem_a.io - elem_b.io);
        })
        a.forEach(function(elem, ix){
            replacements[elem.key] = '$'+(ix+1);
        });

        (['Y', 'm', 'd', 'y', 'H', 'i', 's', ]).forEach(function(key){
            if(replacements[key]){
                dest = dest.replace(key, (key=='y'
                    ? '20'+replacements[key] 
                    : replacements[key])
                );
            } else 
                if(dest.indexOf(key)<0)
                    dest = dest.replace(key, '');
                
        });

        return {rex: new RegExp('^'+reg+'$')
            , replacement: dest.replace(/[^0-9]+$/, '')
        };

    };

    var types = ['date', 'time', 'datetime']
        , aStrFormat = {'date':conf_.dateFormat
            , 'time': conf_.timeFormat
            , 'datetime': conf_.dateFormat+' '+conf_.timeFormat 
        }
        , aStrFormatISO = {'date':'Y-m-d', 'time': 'H:i:s', 'datetime': 'Y-m-dTH:i:s' }

    types.forEach(function(type){

        var oLocale2ISO =  __getRexNReplacement(aStrFormat[type], aStrFormatISO[type], type),
            oISO2Locale =  __getRexNReplacement(aStrFormatISO[type], aStrFormat[type], type);

        conf_.rex[type] = oLocale2ISO.rex
        conf_.rexISO[type] = oISO2Locale.rex
        conf_.rex_replace[type] = oLocale2ISO.replacement;
        conf_.rex_replace2loc[type] = oISO2Locale.replacement;

    })
}

var _showUserInfo = function(userInfoHTML, $initiator){
    $(userInfoHTML).dialog({
        dialogClass: 'ei-current-user-info-wrapper', 
        position: {
            my: "left top",
            at: 'left bottom',
            of: $initiator
          },
        show: 'slideDown',
        hide: 'slideUp',
        resizable: false,
        width: ($initiator.outerWidth() > 300 ? $initiator.outerWidth()+'px' : 300),
        
    });
}

var methods = {

/**
 * Default method, it keeps configuration options object and calls menu renderer function. Normally it is called from js/intra_execute.js in $(document).ready() event handler.
 * Default config options are read from <body data-conf="{..json...}"> or <input type="hidden" id="eiseIntraConf" value="{..json...}">.
 * Second option is kept for backward compatibility.
 * Menu HTML is loaded via AJAX query after user log on and then it is stored in sessionStorage object. So next time when you visit this system it picks up menu from browser cache.
 * Menu HTML is generated by $intra->menu() method called in ajax_details.php with 'getMenu' data read key.
 * 
 * @param object options Configuration options object, to extend the default one.
 */
init: function(options){

    // initialize configuration    
    if($('body')[0].dataset.conf){
        conf = $.parseJSON($('body')[0].dataset.conf);
    } else 
        if($('#eiseIntraConf')[0]){
            conf = $.parseJSON($('#eiseIntraConf').val());
        }

    var $this = $(this),
        data = $this.data('eiseIntra');
        
    if ( ! data ) {

        __initRex(conf);

        conf.menuKey = conf.system+'_menu';
        conf.tlMenuKey = conf.system+'_tlmenu';

        $(this).data('eiseIntra', {
            conf: $.extend( conf, options)
        });

    }

    // pick up top level menu
    if($('.ei-top-level-menu-container')[0]){

        if(typeof sessionStorage[conf.tlMenuKey] == 'undefined'){
            $.ajax({'url': 'ajax_details.php?'+conf.dataReadKey+'=getTopLevelMenu',
                'cache': false,
                'dataType': "html"
            })
                .done(function(data){

                    $('.ei-top-level-menu-container').html(data);

                    sessionStorage[conf.tlMenuKey] = data;
                    $this.eiseIntra('adjustTopLevelMenu');

                })
                .fail(function( jqXHR, textStatus ) {
                    console.log( "Top-level menu request failed: " + textStatus );
                });
        } else {
            $('.ei-top-level-menu-container').html(sessionStorage[conf.tlMenuKey]);
            $this.eiseIntra('adjustTopLevelMenu');
        }
        
    }

    var userInfoHTML = ($('.ei-current-user-info')[0] ? $('.ei-current-user-info')[0].outerHTML :  null);
    if(userInfoHTML)
        $('.ei-current-user-info').remove();
    $('.ei-login-info')
        .css('cursor', 'pointer')
        .click(function(){
            var $initiator = $(this);
            if($('.ei-current-user-info-wrapper')[0]){
                $('.ei-current-user-info').dialog('close').remove();
                return false;
            }
            if(userInfoHTML)
                _showUserInfo(userInfoHTML, $initiator);
            else {
                $.get('ajax_details.php?DataAction=getCurrentUserInfo', function(response){
                    _showUserInfo(response, $initiator);
                })
            }

            
        });

    // render menu
    if($('.ei-sidebar-menu')[0]){
        if(!conf.flagDontGetMenu){
            var flagStorageChangeOnDownload = false;
            if(typeof sessionStorage[conf.menuKey] == 'undefined'){
            //if(true){
                $.ajax('ajax_details.php?'+conf.dataReadKey+'=getMenu')
                    .done(function(data){

                        $('.ei-sidebar-menu-content').html(data);

                        flagStorageChangeOnDownload = true;
                        sessionStorage[conf.menuKey] = data;
                        renderMenu();

                    });
            } else {
                $('.ei-sidebar-menu-content').html(sessionStorage[conf.menuKey]);
                renderMenu();
            }

            // if menu was changed in some window, we update all other windows
            addEventListener('storage', function(event){
                if(event.key==conf.menuKey && !flagStorageChangeOnDownload){
                    //$('.ei-sidebar-menu').html(event.newValue);         
                }
            });
        
            //sessionStorage.removeItem(conf.menuKey);
        } else {
            renderMenu();
        }

    }

    // if ei-pane contains iframe
    adjustIframe();

    // clean storage when user changes language
    $('.language-selector').click(function(){
        $this.eiseIntra('cleanStorage');
    });

    $('.menubutton > a[href="#dashboard"]').click(function(){
        var $dlg = $('.ei-dashboard').dialog({modal: true
            , title: $(this).text()
            , width: '420'
            , open: function(){
                $(this).find('a').click(function(){
                    $dlg.dialog('close');
                })
            }
            , buttons: [{text: 'Close', click: function(){
                $(this).dialog('close');
            }}]
            });
        return false;
    })

    return this;

}

, doVisualAdjustments: function(){

    // adjust side menu
    if( window.parent.document ){
        var $parentMenu = $('.ei-sidebar-menu', window.parent.document)
        if($parentMenu[0] && !$parentMenu.hasClass('pinned')){
            $parentMenu.removeClass('visible');
            $parentMenu.click();
        }
    }

    // ajdust top padding
    var hActionMenu = 0;
    if($('.ei-action-menu')[0]){
        hActionMenu = $('.ei-action-menu').outerHeight(true);
        $("#frameContent").css ("padding-top", hActionMenu+"px");

        if($('.ei-pane')[0]){
            $('.ei-action-menu').first().css('margin-top', '-'+hActionMenu+'px');
            $('.ei-pane').css('padding-top', (parseInt($('.ei-pane').css('padding-top').replace('px', '')) + hActionMenu)+'px');            
        }

        $('.ei-action-menu a.confirm').click(function(event){
            
            if (!confirm('Are you sure you want to execute "'+$(this).text()+'"?')){
                event.preventDefault();
                return false;
            } else {
                return true;
            }

        });
    }

    return this;
}

, adjustTopLevelMenu: function(){

    $('.ei-top-level-menu').find('.menu-item').each(function(){
        $(this).removeClass('menu-item-selected');
    })

    var selItemTopLevelMenu = $(this).data('eiseIntra').conf.selItemTopLevelMenu;
    if(selItemTopLevelMenu){

        $('.ei-top-level-menu').find('#ei-top-level-menu-'+selItemTopLevelMenu.replace(/[^a-z0-9]/i, '')).each(function(){
            $(this).addClass('menu-item-selected');
            if(this.nodeName.toLowerCase()==='option')
                $(this).attr('selected', 'selected');
        })
            
    }

    var callback = $(this).data('eiseIntra').conf.onTopLevelMenuChange
        , menuElem = $('.ei-top-level-menu')[0];

    if(callback && menuElem){
        if(menuElem.nodeName.toLowerCase()=='select'){
            $(menuElem).change(function(){
                callback.call($(this), $(menuElem).val());    
            })
            
        }
    }

}

/**
 * This method cleans local storage, in case when user session ends, for example.
 */
, cleanStorage: function(){

    var conf = $(this).data('eiseIntra').conf;

    sessionStorage.removeItem(conf.menuKey);
    sessionStorage.removeItem(conf.tlMenuKey);

}  

/**
 * This method shows user message box with text.
 * @param string text Text message to be shown.
 */
, showMessage: function(text, options){

    if(text)
        msgText = text;
    else {
        if(this[0].dataset.message){
            msgText = this[0].dataset.message; 
            this[0].dataset.message = ''; 
        } else {
            return this;
        }
    }

    if(!msgText)
        return this;

    var $sysmsg = $('<div id="ei-sysmsg" />')
        .append('<i />')
        .appendTo(this)
        , errorConditions = /^ERROR(\:){0,1}\s*/i;

    if(msgText.match(errorConditions)){ // show alert box with buttons
        
        msgText = msgText.replace(errorConditions, '');

        $sysmsg.css('white-space', 'pre-wrap');

        $sysmsg.append('<p>'+msgText+'</p>')
            .find('i')
                .addClass('ui-icon ui-icon-alert');

        $sysmsg.dialog({
            dialogClass: "ui-state-error ei-sysmsg-error"
            , resizable: false
            , title: 'ERROR'
            , buttons: {Ok: function(){ $sysmsg.dialog('close').remove() } }
            , open: function(){
                window.setTimeout(function(){
                    if($sysmsg[0])
                        $sysmsg.dialog('close').remove()
                }, 10000)
            }
            , close: function(){
                if(options && typeof options.onclose === 'function'){
                    onclose.call($sysmsg);
                }
            }
            , hide: 'fade'
        })

    } else { // show infobox

        $sysmsg.append('<p>'+msgText+'</p>').find('i').addClass('ui-icon ui-icon-info');

        $sysmsg.css('white-space', 'nowrap');

        var h = ($('#menubar')[0] && $('#menubar').outerHeight(true) ? $('#menubar').outerHeight(true) : 28)

        $sysmsg
        .dialog({
            dialogClass: "ei_dialog_notitle ei-sysmsg-info"
            , resizable: false
            , height: h
            , width: 'auto'
            , position: ($('.ei-header')[0]
                    ? {my: 'right top', at: 'right bottom', of: $('.ei-header')}
                    : {my: 'right top', at: 'right top', of: window}
                    )
            , open: function(){
                window.setTimeout(function(){
                    if($sysmsg[0])
                        $sysmsg.dialog('close').remove()
                }, 7000)
            }
            , close: function(){
                if(options && typeof options.onclose === 'function'){
                    onclose.call($sysmsg);
                }
            }
            , hide: 'fade'
        })
        .css('height', h+'px');

    }

}

, hideMessage: function(){
   $("#ei-sysmsg").fadeOut("slow").remove();
}

/**
 * This method reads or sets new data on eiseIntra configuration object. When it's called with no args, it returns whole configuration array.
 * When @property parameter is set, it returns corrsponding value from configuration object.
 * When @value is set, it also updates config object property with new value, but returns the old one.
 *
 * @param string @property (optional) Configuration object property name.
 * @param mixed @value (optional) New value for this property.
 *
 * @return mixed Configuration object property value, whatever it is, or configuration object itself.
 */ 
, conf: function(property, value){

    var conf = $(this).data('eiseIntra').conf;

    if(typeof property!=='undefined'){
        
        var retVal = conf[property];

        if(typeof value!=='undefined'){
            conf[property] = value;
            $(this).data('eiseIntra', {conf: conf} )
        }

        return retVal;

    } else {
        return conf;
    }

}

, getPaneHeight: function(){
    return $('.ei-pane').height() - $('#menubar').outerHeight(true);
}

, initRex: function(conf_){

    if(!conf_)
        conf_ = this.conf

    __initRex(conf_)

}

, formatDate: function(value, format){

    var conf = $(this).data('eiseIntra').conf;

    value = value.replace(/\.[0-9]+Z$/, ''); // replace microseconds and "useful" Z letter with nothing

    return (value.match(conf.rexISO[format]) ? value.replace(conf.rexISO[format], conf.rex_replace2loc[format]) :  value);

}

, parseDate: function(strDateTime, options){

    var conf = $(this).data('eiseIntra').conf,
        aDateTime = strDateTime.split(/[T\s]+/),
        strDateSrc = aDateTime[0],
        strTimeSrc = aDateTime[1];

    if(strDateSrc.match(conf.rex['date'])){
        strDateSrc = strDateSrc.replace(conf.rex['date'], conf.rex_replace['date']);
    } else {
        if(!strDateSrc.match(conf.rexISO['date']))
            return null;
    }
        

    var aDate = strDateSrc.match(conf.rexISO['date']),
        aTime = (strTimeSrc ? strTimeSrc.match(conf.rex['time']) : null),
        strDate = (aDate ? aDate[1]+'-'+aDate[2].padStart(2, '0')+'-'+aDate[3].padStart(2, '0') : ''),
        strToParse = strDate+'T'+(aTime ? aTime[1].padStart(2, '0')+':'+aTime[2].padStart(2, '0')+(aTime[3] ? ':'+aTime[3].padStart(2, '0') : '') : '00:00'),
        dtRet = new Date(strToParse);

    if(!aDate)
        return null;

    if( options && options['OperationDayShift'] && conf['stpOperationDayStart']){
        var strOpsDayStart = strDate+'T'+conf['stpOperationDayStart'],
            dtOpsDayStart = new Date(strOpsDayStart);

        if(dtRet < dtOpsDayStart){
            var dtNextDay = new Date(dtRet);
            dtNextDay.setDate(dtRet.getDate()+1);
            dtRet = dtNextDay;
        }

    }

    return dtRet
    
}

, adjustLegendHeight: function(selector){

    var $flds = $(selector)
        , flds = ($flds[0] && $flds[0].nodeName.toLowerCase()=='fieldset' ? $flds[0] : null)


    if(!flds)
        return;

    var $legend = $flds.find('legend').first()
        , legend = $legend[0];

    if(!legend)
        return;

    var bias = $flds.hasClass('has-subtitle') ? 2 : 5;

    $flds.attr('style', 'margin-top: '+(legend.scrollHeight + bias+5)+'px !important');
    $legend.css('top', -(legend.scrollHeight + bias)+'px');

}

}


$.fn.eiseIntra = function( method ) {  
    if ( methods[method] ) {
        return methods[method].apply( this, Array.prototype.slice.call( arguments, 1 ));
    } else {
        return methods.init.apply( this, arguments );
    } 

};


})( jQuery );


/**
 * @fileOverview eiseIntraForm jQuery plugin
 *               <p>License GNU 3
 *               <br />Copyright 2008-2015 Ilya S. Eliseev <a href="http://e-ise.com">http://e-ise.com</a>
 * @version 1.00
 * @author Ilya S. Eliseev http://e-ise.com
 * @requires jquery
 * @requires jqueryui
 */

(function( $ ){

var pluginName = 'eiseIntraForm';

var isDateInputSupported = function(){
    var elem = document.createElement('input');
    elem.setAttribute('type','date');
    elem.value = 'foo';
    return (elem.type == 'date' && elem.value != 'foo');
}

var convertDateForDateInput = function($eiForm, inp){
    
    var conf = $eiForm.data('eiseIntraForm').conf;
    
    var arrVal = inp.getAttribute('value').match(conf.strRegExDate);
    if (arrVal)
        $(inp).val(arrVal[3]+'-'+arrVal[2]+'-'+arrVal[1]);
    
    return;

}

var getInput = function(strFieldName){

    if(!strFieldName)
        return null;

    var ret = this.find('[name="'+strFieldName+'"]');
    if( !ret[0] )
        ret = this.find('#'+strFieldName);

    return ret;

}

var getAllInputs = function($form){

    return $form.find('.eif-input, .eiseIntraField input,.eiseIntraField select,.eiseIntraField textarea');

}

var getInputType = function($inp){
    if($inp[0] && $inp[0].dataset && $inp[0].dataset['type'])
        return $inp[0].dataset['type'];
    var strType = ($inp.attr('type') ? $inp.attr('type') : 'text');
    if(strType=='text'){
        var classList = ($inp.attr('class') ? $inp.attr('class').split(/\s+/) : []);
        for(var i=0;i<classList.length;i++){
            var item = classList[i];
            if (item.match(/^eiseIntra_/)) {
                switch(item){
                    case 'eiseIntra_date':
                    case 'eiseIntra_time':
                    case 'eiseIntra_datetime':
                    case 'eiseIntra_money':
                    case 'eiseIntra_real':
                    case 'eiseIntra_integer':
                    case 'eiseIntra_int':
                    case 'eiseIntra_decimal':
                        return item.replace('eiseIntra_', '');
                    default:
                        break;
                }
            }
        };
    }
    return strType;
}

var setCurrentDate = function(oInp){
    
    var today = new Date();
    var dd = today.getDate();
    var mm = today.getMonth()+1; //January is 0!
    var hh = today.getHours();
    var mn = today.getMinutes();
    var yyyy = today.getFullYear();
    
    if(dd<10){dd='0'+dd} if(mm<10){mm='0'+mm} 
    if(hh<10){hh='0'+hh} if(mn<10){mn='0'+mn} 
    
    var date = dd+'.'+mm+'.'+yyyy;
    var time = hh+':'+mn;
    if ($(oInp).hasClass('eiseIntra_datetime')){
        $(oInp).val(date+' '+time);
    } else {
        $(oInp).val(date);
    }
}

var getFieldLabel = function(oInp){
    return oInp.parents('.eiseIntraField, .eif-field').first().find('label').last();
}
var getFieldLabelText = function(oInp){
    return getFieldLabel(oInp).text().replace(/[\:\*]+$/, '');
}
var setTextAsync = function($inp, value){
    $inp.val(value);
    return;

    // get data from ajax_details or whatever
}

var __ajaxDropdownHref = function(inp, $inpVal, href){
    
    if(!href){
        return;
    }

    var $inp = $(inp)
        , name = $inpVal.attr('name')
        , $a = $inp.next('.ei-autocomplete-href')
        , hrefKey = $inpVal.val();

    if( $a[0] && hrefKey=='' ){
        $a.css('display', 'none');
        return;
    } else {
        $inp.css('padding-right', '40px');
    }
            

    var nameToReplace = name.replace(/\[\]/, '') // for php-handleable double-square-brackets input names
        , hrefToSet = href.replace(new RegExp('\\['+nameToReplace+'\\]'),hrefKey);
    $a = ($a[0]
        ? $a 
        : $('<a>').addClass('ei-autocomplete-href')
            .appendTo($inp.parents()[0])
            .text(' ')
        ).attr('href', hrefToSet)
            .css('z-index', (isNaN(parseInt($inp.css('z-index'))) ? 0 : parseInt($inp.css('z-index'))) +1)
            .css('display', 'inline-block')

}

var arrInitiallyRequiredFields = [];

var methods = {

init: function( options ) {

    return this.each(function(){
         
        var $this = $(this),
            data = $this.data('eiseIntraForm'),
            conf = (data ? data.conf : {});
        
        // Если плагин ещё не проинициализирован
        if ( ! data ) {

            conf = $('body').eiseIntra('conf');

            //conf.isDateInputSupported = isDateInputSupported();
            conf.isDateInputSupported = false;

            conf.strRegExDate = conf.dateFormat
                        .replace(new RegExp('\\.', "g"), "\\.")
                        .replace(new RegExp("\\/", "g"), "\\/")
                        .replace("d", "([0-9]{1,2})")
                        .replace("m", "([0-9]{1,2})")
                        .replace("Y", "([0-9]{4})")
                        .replace("y", "([0-9]{2})");
            
            conf.strRegExDate_dateInput = "([0-9]{4})\-([0-9]{1,2})\-([0-9]{1,2})";
            conf.dateFormat_dateInput = "Y-m-d";

            conf.strRegExTime = conf.timeFormat
                .replace(new RegExp("\\.", "g"), "\\.")
                .replace(new RegExp("\\:", "g"), "\\:")
                .replace(new RegExp("\\/", "g"), "\\/")
                .replace("h", "([0-9]{1,2})")
                .replace("H", "([0-9]{1,2})")
                .replace("i", "([0-9]{1,2})")
                .replace("s", "([0-9]{1,2})");

            $(this).data('eiseIntraForm', {
                form : $this,
                conf: $.extend( conf, options)
            });

        }
        
        getAllInputs($this).each(function() {
            switch ($(this).attr('type')){ 
                case "date":
                    if (conf.isDateInputSupported){
                        $(this).css('width', 'auto');
                        convertDateForDateInput($this, this);
                    } else {
                        $(this).addClass('eiseIntra_'+$(this).attr('type'));
                    }
                    $(this).attr('autocomplete', 'off');
                    break;
                case "datetime":        //not supported yet by any browser
                case "datetime-local":  //not supported yet by any browser
                    $(this).addClass('eiseIntra_'+$(this).attr('type'));
                    $(this).attr('autocomplete', 'off');
                    break;
                case "number":
                    $(this).css('width', 'auto');
                    $(this).attr('autocomplete', 'off');
                    break;
                default:
                    break;
            }
            $(this).change(function(){
                $(this).addClass('eif_changed');
            });
            if( $(this).attr('required')=='required' && $(this).attr('id')!=''){
                arrInitiallyRequiredFields.push($(this).attr('id'));
            }
            
        });

        $this.find('input.eiseIntra_date, input.eiseIntra_datetime').each(function() {
            $(this).attr('autocomplete', 'off');
            $(this).datepicker({
                    changeMonth: true,
                    changeYear: true,
                    dateFormat: conf.dateFormat.replace('d', 'dd').replace('m', 'mm').replace('Y', 'yy'),
                    constrainInput: false,
                    firstDay: 1
                    , yearRange: 'c-7:c+7'
                });
            
            $(this).bind("dblclick", function(){
                setCurrentDate(this);
            })
        });
    
        $this.find('input.eiseIntra_ajax_dropdown').each(function(){

            $(this).attr('autocomplete', 'off');
            var initComplete,
                inp = this,
                $inp = $(this),
                $inpVal = $inp.prev("input"),
                source = $.parseJSON(inp.dataset['source']),
                href = inp.dataset['href'],
                url = (source.scriptURL 
                    ? source.scriptURL
                    : 'ajax_dropdownlist.php')+
                    '?'+
                    'table='+(source.table ? encodeURIComponent( source.table ) : '')+
                    (source.prefix ? '&prefix='+encodeURIComponent( source.prefix ) : '')+
                    (source.showDeleted ? '&d='+source.showDeleted : '');
            
            __ajaxDropdownHref(inp, $inpVal, href);

            setTimeout(function(){initComplete=true;}, 1000);
            $(this)
                .each(function(){  this.addEventListener('input', function(ev){   if( typeof initComplete === 'undefined'){  ev.stopImmediatePropagation();  }     }, false);      }) // IE11 hack
                .autocomplete({
                source: function(request,response) {
                    
                    // reset old value
                    if(request.term.length<3){
                        response({});
                        $inpVal.val('');
                        $inpVal.change();
                        __ajaxDropdownHref(inp, $inpVal, href);
                        return;
                    }

                    var extra = ($inp.attr('extra') ? $inp.attr('extra') : $.parseJSON(inp.dataset['source'])['extra']);
                    var urlFull = url+"&q="+encodeURIComponent(request.term)+(typeof extra!== 'undefined' ? '&e='+encodeURIComponent(extra) : '');
                    
                    $.getJSON(urlFull, function(response_json){
                        
                        // reset old value - we got new JSON!
                        if(response_json.data.length)
                            $inpVal.val('');
                        $inpVal.change();
                        __ajaxDropdownHref(inp, $inpVal, href);

                        response($.map(response_json.data, function(item) {
                                return {  label: item.optText, value: item.optValue  }
                            }));
                        });
                        
                    },
                minLength: 0,
                focus: function(event,ui) {
                    event.preventDefault();
                    if (ui.item){
                        $(inp).val(ui.item.label);
                    }
                },
                select: function(event,ui) {
                    event.preventDefault();
                    if (ui.item){
                        $(inp).val(ui.item.label);
                        $(inp).prev("input").val(ui.item.value);
                    } else 
                        $(inp).prev("input").val("");
                    $(inp).prev("input").change();
                    __ajaxDropdownHref(inp, $inpVal, href);
                },
                change: function(event, ui){
                }
    		})
            .autocomplete( "instance" )._renderItem  = function( ul, item ) {
                var liClass = ( item['class'] ?  ' class="'+item['class']+'"' : '');
                return $( "<li"+liClass+">" ).text( item.label ).appendTo( ul );
            };
            
        });
        
        $this.find('.eiseIntra_unattach').click(function(){
            
            var filName = $($(this).parents('tr')[0]).find('a').text();
            var filGUID = $(this).attr('id').replace('fil_','');
        
            if (confirm('Are you sure you\'d like to unattach file ' + filName + '?')) {
                    location.href = 
                        location.href
                        + '&DataAction=deleteFile&filGUID=' + filGUID
                        + '&referer=' + encodeURIComponent(location.href);
            }

        });

        $this.find('.eiseIntraDelete').click(function(ev){
                if (confirm("Are you sure you'd like to delete?")){
                    $this.find('input,select,textarea').removeAttr('required');
                    $this.find('input[name="DataAction"]').val('delete');
                    $this.off('submit').submit();
                    return false;
                } 
                ev.stopImmediatePropagation();
                return false;
        });
    });
},

/** 
 * options can bu used here to set validation callback functions (validators)
 */
validate: function( options ) {

    if ($(this).find('#DataAction')=='delete')
        return true;
    
    var canSubmit = true,
        conf = $.extend( $(this).data('eiseIntraForm').conf, options ),
        $this = $(this);

    getAllInputs($this).each(function() {

        var strValue = $(this).val()
            , strType = getInputType($(this))
            , strRegExDateToUse = ''
            , $inpToCheck=$(this)
            , inpName = this.id
            , validator = (conf.validators ? conf.validators[inpName] : null);

        if ($inpToCheck.attr('required')==='required'){
            if ($inpToCheck.val()===""){
                alert(getFieldLabelText($inpToCheck)+" is mandatory");
                $(this).focus();
                canSubmit = false;
                return false; //break;
            }
        }

        if(validator){
            canSubmit = validator.call(this, strValue)
        }
        
        switch (strType){
            case "number":
            case "int":
            case "money":
            case "real":
            case "decimal":
                nValue = parseFloat(strValue
                    .replace(new RegExp("\\"+conf.decimalSeparator, "g"), '.')
                    .replace(new RegExp("\\"+conf.thousandsSeparator, "g"), ''));
                if (strValue!="" && isNaN(nValue)){
                    alert(getFieldLabelText($(this))+" should be numeric");
                    $(this).focus();
                    canSubmit = false;
                    return false;
                }
                break;
            case 'date':
                if (conf.isDateInputSupported){
                    strRegExDateToUse = conf.strRegExDate_dateInput;
                } else {
                    strRegExDateToUse = conf.strRegExDate;
                }
            case 'time':
            case 'datetime':

                strRegExDateToUse = (strRegExDateToUse!='' ? strRegExDateToUse : conf.strRegExDate);

                var strRegEx = "^"+(strType.match(/date/) ? strRegExDateToUse : "")+
                    (strType=="datetime" ? " " : "")+
                    (strType.match(/time/) ? conf.strRegExTime : "")+"$";

                if (strValue!="" && strValue.match(new RegExp(strRegEx))==null){
                    alert ("Field '"+getFieldLabelText($(this))+"' should contain "+(strType)+" value formatted as \""+conf.dateFormat+
                    (strType.match(/time/) ? ' '+conf.timeFormat.replace('i', 'm') : "")+
                    "\".");
                    $(this).focus();
                    canSubmit = false;
                    return false;
                }
                break;

            case 'ajax_dropdown':
                if ($inpToCheck.attr('required')==='required' && $inpToCheck.prev('input').val()==''){
                    alert(getFieldLabelText($inpToCheck)+" is mandatory");
                    $(this).focus();
                    canSubmit = false;
                    return false;
                }
            default:
                 break;
        }
    });
    
    return canSubmit;

},

makeMandatory: function( obj ) {  

    return this.each(function(){
    
    getAllInputs($(this)).each(function(){

        if ($(this).attr('type')=='hidden')
            return true; // continue
        
        var label = getFieldLabel($(this));
        $(this).parents('.eif-field').first().removeClass('required');
        if(label[0] && $.inArray( $(this).attr('id'), arrInitiallyRequiredFields ) < 0){
            label.text(label.text().replace(/\*\:$/, ":"));
            $(this).removeAttr('required');    
        }
        
    });
    
    if ( obj.strMandatorySelector=='')
        return;
    
    $(this).find( obj.strMandatorySelector ).each(function(){

       var label = getFieldLabel($(this)),
            $parent = $(this).parents('.eif-field').first().addClass('required');
       label.text(label.text().replace(/\:$/, "*:"));
       if (!obj.flagDontSetRequired){
            $(this).attr('required', 'required');
            $parent.find('input[name="'+this.name+'_text"]').attr('required', 'required');
       }
    });
    
    })
    
},

focus: function(strFieldName, onFocus ){

    var $field = getInput(this, strFieldName);

    if(typeof($field[0])=='undefined')
        $field = this.find('input[type!=hidden],select').first();

    if(typeof(onFocus)!='undefined')
        $field.focus( onFocus );
    else {

        $field.select();
        $field.focus();

    }

},

value: function(strFieldName, val, decimalPlaces){

    var conf = this.data('eiseIntraForm').conf;

    var strRegExDate = conf.strRegExDate
        , strDateFormat = conf.dateFormat
        , $inp = getInput.call(this, strFieldName)
        , strType = getInputType.call(this, $inp);

    if(!$inp[0])
        return undefined;

    if (val==undefined){ // if we get value
        var strValue = $inp.val();
        switch(strType){
            case 'checkbox':
                return $inp[0].checked
            case "integer":
            case "int":
            case "numeric":
            case "real":
            case "double":
            case "money":
               strValue = strValue
                .replace(new RegExp("\\"+conf.decimalSeparator, "g"), '.')
                .replace(new RegExp("\\"+conf.thousandsSeparator, "g"), '');
                nVal = parseFloat(strValue);
                return isNaN(nVal) ? null : nVal;
            case "date":
            case "datetime":
                if($inp.attr('type') && $inp.attr('type')=='date' && conf.isDateInputSupported){
                    strRegExDate = conf.strRegExDate_dateInput;
                    strDateFormat = conf.dateFormat_dateInput;
                } else {
                    strRegExDate = conf.strRegExDate + (strType=='datetime' ? '\s+'+conf.strRegExTime : '');
                    strDateFormat = conf.dateFormat + (strType=='datetime' ? '\s+'+conf.timeFormat : '');
                }
                var arrMatch = strValue.match(strRegExDate);

                if (arrMatch){
                    strDateFormat = ' '+strDateFormat.replace(/[^dmyhis]/gi, '');
                    var year = (strDateFormat.indexOf('y')>=0 ? '20'+arrMatch[strDateFormat.indexOf('y')] : arrMatch[strDateFormat.indexOf('Y')]);
                    return new Date( new Date(year, arrMatch[strDateFormat.indexOf('m')]-1, +arrMatch[strDateFormat.indexOf('d')]) - ((new Date()).getTimezoneOffset() * 60000) );
                } else {
                    return null;
                }
            default:
                return strValue;
        }
    } else { // if we set value
        var strValue = val;
        switch(strType){
            case "integer":
            case "int": 
                strValue = isNaN(strValue) ? '' : number_format(strValue, 0, conf.decimalSeparator, conf.thousandsSeparator);
                break;
            case "numeric":
            case "real":
            case "double":
            case "money":
                if(typeof(strValue)=='number'){
                    strValue = isNaN(strValue) 
                        ? '' 
                        : number_format(strValue, 
                            (decimalPlaces!=undefined 
                                ? decimalPlaces 
                                : ($inp[0].dataset['decimals'] 
                                    ? parseInt($inp[0].dataset['decimals'])
                                    : conf.decimalPlaces 
                                )
                            )
                            , conf.decimalSeparator, conf.thousandsSeparator
                        )
                }
                break;
            case 'combobox':
            case 'select':
                if($inp[0].nodeName.toLowerCase()==='select'){
                    $inp.val(strValue);
                } else {
                    setTextAsync.call(this, $inp, strValue);
                }
                break;
            case 'ajax_dropdown':
                setTextAsync.call(this, $inp, strValue);
                break;
            default:
                break;
        }
        if(strType != 'checkbox')
            $inp.val(strValue);
        else 
            $inp[0].checked = (strValue && strValue!='0');

        if($inp[0].nodeName.toLowerCase()==='input' && $inp.attr('type').toLowerCase()==='hidden'){
            $inp.parent().find('#span_'+strFieldName).text(strValue);
        }
        return this;
    }
},

fill: function(data, options){
    
    return this.each(function(){
    
        var $form = $(this);

        $.each(data, function(field, fieldData){

            if( typeof(fieldData)=='object' && (fieldData && typeof(fieldData.v)=='undefined') )
                return true; // skip objects without data

            var fData = (typeof(fieldData)=='object' && (fieldData && typeof(fieldData.v)!='undefined')
                ? fieldData
                : {v: fieldData});

            if(!fData.t && data[field+'_text']!==null)
                fData.t = data[field+'_text'];

            var $inp = $form.find('#'+field) 
                , $span = $form.find('#span_'+field)
                , html = '';

            if (!$inp[0]){
                if(options && options.createMissingAsHidden){
                    $inp = $('<input type="hidden">').attr('id', field).attr('name', field).appendTo($form);
                } else {
                    return true; // continue
                }
            }

            switch($inp[0].nodeName){
                case 'INPUT':
                case 'SELECT':
                    switch($inp.attr('type')){
                        case 'checkbox':
                            if(parseInt(fData.v)==1)
                                $inp[0].checked = true;
                            else 
                                $inp[0].checked = false;
                            break;
                        case 'radio':
                            // not delivered yet
                            break;
                        default:
                            $inp.val(fData.v);
                            html = fData.t ? fData.t : fData.v;
                            break;
                    } 
                    
                    $inpNext = $inp.next('input#'+field+'_text');

                    if ($inpNext && fData.t){
                        $inpNext.val(fData.t);
                    }
                    if(fData.rw=='r'){
                        if($inp.attr('type')!='hidden'){
                            $inp.attr('disabled', 'disabled');
                        }
                        if($inpNext){
                            $inpNext.attr('disabled', 'disabled');
                        }
                    }
                    break;
                default:
                    if(fData.h && fData.v!=''){
                        html = '<a href="'+fData.h+'"'
                            +(fData.tr 
                                ? ' target="'+fData.tr+'"'
                                : '')
                            +'>'+fData.v+'</a>';
                    } else
                        html = fData.v;
                    $inp.html(html);
                    break;
            }
            if($span[0])
                $span.html(html);

            $inp.addClass('eif_filled');



        })
    
    })


},

reset: function(obj){
    
    return this.each(function(){
    
        var $form = $(this);

        $form.find('.eif_filled, .eif_changed').each(function(){
            switch(this.nodeName){
                case 'INPUT':
                case 'SELECT':
                    switch($(this).attr('type')){
                        case 'button':
                        case 'submit':
                            break;
                        default:
                            $(this).val('');
                            break;
                    }
                    $(this).removeAttr('disabled').removeAttr('checked');
                    break;
                default:
                    $(this).html('');
                    break;
            }
            $(this).removeClass('eif_filled').removeClass('eif_changed');
        })
    
    })

},

change: function(strInputIDs, callback){
    return this.each(function(){
        
        var $form = $(this);
        var fields = strInputIDs.split(/[^a-z0-9\_]+/i);

        var strSelector = ""; $.each(fields, function (ix, val){ strSelector+=(ix==0 ? "" : ", ")+"#"+val});

        $form.find(strSelector).bind('change', function(e){
            callback($(this));
            e.stopPropagation();
        })
    })
},

conf: function(varName, value){

    if (typeof(varName)=='undefined')
        return $(this[0]).data('eiseIntraForm').conf;
    
    if (typeof(value)=='undefined'){
        return $(this[0]).data('eiseIntraForm').conf[varName];
    } else {
        $(this).each(function(){
            $(this).data('eiseIntraForm').conf[varName] = value;
        })
        return $(this);
    }
},

encodeAuthString: function(){

    var frm = this[0];

    var authinput=this.find('#authstring');

    var login = this.find('#login').val();
    var password = this.find('#password').val();
    
    var authstr = login+":"+password;

    if (login.match(/^[a-z0-9_\\\/\@\.\-]{1,50}$/i)==null){
      alert("You should specify your login name");
      this.find('#login').focus();
      return (false);
    }

    if (password.match(/^[\S ]+$/i)==null){
      alert("You should specify your password");
      this.find('#password').focus();
      return (false);
    }
    this.find('#login').val("");
    this.find('#password').val("");
    this.find('#btnsubmit').attr('disabled', 'disabled');
    this.find('#btnsubmit').val("Logging on...");

    authstr = base64Encode(authstr);
    authinput.val(authstr);

    return authstr;

},

dropzone: function(fnCallback){

    var $form = this
        , $dropzones = $form.find('.ei-dropzone');

    $form.bind('drop', function(event) {
        event.preventDefault();
    }).bind('dragover', function(event) {
        $dropzones.each(function(){ $(this).addClass('ei-ready-to-drop') });  
        return false;
    }).bind("dragleave", function(event) {
        $dropzones.each(function(){ $(this).removeClass('ei-ready-to-drop') });  
        return false;
    });

    $dropzones.each(function(){
        var dropzone = this;
        dropzone.addEventListener('dragenter', function(e){e.preventDefault();});
        dropzone.addEventListener('dragover', function(e){e.preventDefault();});
        dropzone.addEventListener('drop', _dropped);
    })
    
    function _dropped(e) {
        e.preventDefault(); 
        e.stopImmediatePropagation();

        $form.find('.ei-dropzone').removeClass('ei-ready-to-drop');
        $(this).addClass('ei-spinner')
        if(typeof fnCallback === 'function'){
            fnCallback.call(this, e);
        }
    }
   

},

/**
 * Creates dialog box basing on jQueryUI dialog() widget. It adds FORM element to the DOM, fills it with fields/data and shows it with jqueryui .dialog(), with buttons "OK" and "Cancel".
 * When user press "OK", it collects all data to the object and pass it to conf.onsubmit() function as the parameter. If this function returns true or not set at all, FROM is being submitted to the URL specified at conf.action using method specified at conf.method. Otherwise dialog box is closed and FORM element removed.
 * When user press "Cancel", dialog box is closed and FORM element removed.
 *
 * @example $(element).eiseIntraForm('createDialog', {fields: {} });
 * @param {Object} conf
 * @param {String} conf.action Form "action" acttribute
 * @param {String} conf.method Form "method" acttribute
 * @param {Boolean} conf.flagUnsubmittable =true, if form should not be submitted 
 * @param {Function} conf.onsubmit Callback for onSubmit form event
 * @param {Array} conf.fields Fields array, see "addField()" method for details.
 * @return {jQuery} jQuery object with created FORM element.
 */
createDialog: function( conf ){

    if(!conf.fields)
        return null;

    var $frm = $('<form/>').appendTo('body').addClass('eiseIntraForm eif-form eif-form-dialog');

    if(conf.action)
        $frm.attr('action', conf.action);

    if(conf.method)
        $frm.attr('method', conf.method);

    $frm.append('<span class="ui-helper-hidden-accessible"><input type="text"></span>');

    $.each(conf.fields, function(ix, field){

        $frm.eiseIntraForm('addField', field );

        if(field.type=='file'){
            $frm.attr('method', 'POST')
            $frm.attr('enctype', 'multipart/form-data')
        }

    });

    var btnCloseTitle = (conf.flagUnsubmittable ? 'Close' : 'Cancel');

    $frm.append('<div class="eif-actionButtons">'
        +(conf.flagUnsubmittable ? '' : '<input type="submit" value="OK">')
        +'<input type="button" value="'+btnCloseTitle+'" class="eif_btnClose">'
        +'</div>');

    if(conf.id){
        $frm.attr('id', conf.id);
    }

    $frm.eiseIntraForm('init').submit(function(){

        var objVals = {};
        $frm.find('input,select,textarea').each(function(ix, inp){
            if($(inp).attr('name')){
                objVals[$(inp).attr('name')] = {v: ($(inp).attr('type')=='checkbox' 
                    ? (inp.checked ? 1 : 0)
                    : $(inp).val())
                };
                if(inp.nodeName.toUpperCase()=='SELECT'){
                    objVals[$(inp).attr('name')]['t'] = inp.options[inp.options.selectedIndex].text;
                } 
                var strFieldSafe = $(inp).attr('name').replace(/[^\-\w]+/, ''); //eiseGrid elems fixe
                if(typeof($frm.find('#'+strFieldSafe+'_text')[0])!='undefined'){
                    objVals[$(inp).attr('name')]['t'] = $frm.find('#'+strFieldSafe+'_text').val();
                }
            }
                
        })
        

        if(conf.onsubmit)
            return conf.onsubmit.call($frm[0], objVals, $frm);

        window.setTimeout(function(){
            $frm.dialog('close').remove();
        }, 100);
        
        return true;

    });

    var dlgConf= {
        modal: true
        , title: conf.title
        , resize: "auto"
    };

    if(conf.onclose)
        dlgConf.close = conf.onclose;
    if(conf.oncreate)
        dlgConf.create = conf.oncreate;

    if(conf.width)
        dlgConf.width = conf.width;

    $frm.dialog(dlgConf);

    if(conf.class)
        $frm.addClass(conf.class);

    $frm.find('.eif_btnClose').click(function(){ $frm.dialog('close').remove(); })

    return $frm;

},
/**
 * Adds field to the form
 *
 * @example $(element).eiseIntraForm('addField', {type: 'text', name: 'First Name', value: 'John Doe'});
 * @example $(element).eiseIntraForm('addField', {type: 'hr'});
 * @example $(element).eiseIntraForm('addField', {type: 'p', value: 'Lorem ipsum doler si amet...'});
 * @param {Object} field
 * @param {String} field.type Field type. To add INPUT element, specify input type here. Type 'p' adds paragraph to the form with content specified in field.value option. Type 'hr' adds HR to the form.
 * @param {String} field.value Value for the field. Ignored when type set to 'hr'.
 * @param {Function} conf.onsubmit Callback for onSubmit form event
 * @param {Array} conf.fields Fields array, see "addField()" method for details.
 * @return {jQuery} jQuery object with created FORM element.
 */
addField: function( field ){

    var element;

    var type = field.type.toLowerCase();

    switch(type){
        case 'textarea':
            element = $('<textarea>');
            element.addClass('eif-input');
            if(field['rows'])
                element.attr('rows', field['rows'])
            break;
        case 'combobox':
        case 'select':
            element = $('<select>');
            if(field.defaultText)
                element.append($('<option value="">'+field.defaultText+'</option>'));
            if(typeof field.options == 'string' && field.options.match(/^\s*\<option/i)){
                element.append($(field.options));

            } else {
                $.each(field.options, function(ix, item){
                    $opt = $('<option>');
                    $opt.prop('value', item.v);
                    $opt.text(item.t);
                    $opt.appendTo(element);
                })    
            }
            
            break;
        case 'password':
            element = $('<input type="password">');
            element.addClass('eif-input');
            break;
        case 'file':
            element = $('<input type="file">');
            field.valueWidth = '49%'
            element.addClass('eif-input');
            break;
        case 'checkbox':
            element = $('<input type="checkbox">');
            element.addClass('eif-input');
            break;
        case 'radio':
            element = $('<input type="radio">');
            element.addClass('eif-input');
            break;
        case 'hidden':
            element = $('<input type="hidden">');
            element.addClass('eif-input');
            break;
        case 'hr':
            element = $('<hr>');
            break;
        case 'p':
            element = $('<p>');
            break;
        default:
            element = $('<input type="text">');
            element.addClass('eif-input');
            if(field.type!='text'){
                element.addClass('eiseIntra_'+field.type);
            }
            break;
    }

    if(field.name)
        element.prop('name', field.name);

    if(field.type=='ajax_dropdown'){
        element[0].dataset['source'] = JSON.stringify({table: field.source, prefix: field.source_prefix, scriptURL: field.sourceURL, extra: field.extra});
        element[0].name = element[0].name+'_text';
    }

    if(field.value && $.inArray(type, ['hr', 'p', 'checkbox']) < 0 )
        element.val(field.type=='ajax_dropdown' && field.text 
            ? field.text 
            : field.value);

    if(field.value && type=='p')
        element.html(field.value);

    if((field.value===true || field.checked) && type=='checkbox')
        element.prop('checked', true);


    var $field = null;

    if( field.type!='hidden' && field.title && $.inArray(type, ['hr', 'p'])<0 ){

        if(field.required){
            element.prop('required', true);
        }

        $field = ( (type=='checkbox' || type=='radio') && (field.labelLayout && field.labelLayout!='left')
            ? $('<div>')
                .append('<label></label>')
                .append( $('<label>'+field.title+'</label>').prepend(element).addClass('eif-value eiseIntraValue') )
            : $('<div><label>'+field.title+':</label></div>').append(
                (field.type=='ajax_dropdown'
                    ? $('<input type="hidden" name="'+field.name+'">').addClass('eif-input').val(field.value)
                    :  null)
                ).append(element.addClass('eif-value eiseIntraValue'))
            ).addClass('eiseIntraField');

        if(field.valueWidth){
            $field.find('.eif-value').first().css('width', field.valueWidth)
            if(field.valueWidth.match(/%$/)){
                var valueWidthPercent = parseFloat(field.valueWidth.replace(/%$/, ''))
                if(valueWidthPercent)
                    $field.find('label').first().css('width', ''+(100-valueWidthPercent-3)+'%')
            }
        }

    } else {
        
        $field = element;
            
    }

    if(field.class)
        $field.addClass(field.class);
    
    $field.appendTo(this);

    return $field;

},

/**
 * This function  adjusts fieldset heights by maximum height, within the range specified in ixStart and ixFinish indexes
 * WARNING: works under box-sizing: border-box
 *
 */
adjustFieldsetHeights: function(ixStart, ixFinish){

    ixStart = typeof(ixStart)=='undefined' ? 0 : ixStart;
    ixFinish = typeof(ixFinish)=='undefined' ? 1 : ixFinish;

    return this.each(function(){

        var hMax = 0;
        $(this).find('fieldset').each(function(ix){
            if(ix<ixStart || ix>ixFinish)
                return true; //continue
            hMax = $(this).outerHeight(true) > hMax ? $(this).outerHeight(true) : hMax;
        });


        $(this).find('fieldset').each(function(ix){

            if(ix<ixStart || ix>ixFinish)
                return true; //continue

            if($(this).outerHeight(true)<hMax){
                var margins = $(this).outerHeight(true)-$(this).outerHeight(false);
                $(this).height(hMax - margins);
            }
        });

    });

    
},

upload2batch: function( options ){
    
    var defaultOptions = {'maxFileSize': 100000000, 
            'target': 'batch', 
            },
        title = $(this).text(),
        fields = [{name: 'DataAction'
                , type: 'hidden'
                , value: (options['DataAction'] ? options['DataAction'] : 'upload')}
            , {name: (options['fileFieldName'] ? options['fileFieldName'] : 'the_file')
                , type: 'file'
                , title: (options['fileFieldTitle'] ? options['fileFieldTitle'] : 'Choose file')}];

    options = $.extend(defaultOptions, (options ? options : {}))

    if(options['fields']){
        var fieldsBefore = [], fieldsAfter = []
        for (var i = 0; i < options['fields'].length; i++) {
            if(options.fields[i].position=='end')
                fieldsAfter.push(options.fields[i])
            else 
                fieldsBefore.push(options.fields[i])
        };
        fields = fieldsBefore.concat(fields).concat(fieldsAfter)
    }
    

    $(this).eiseIntraForm('createDialog', {
        title: (options['title'] ? options['title'] : title)
        , action: (options['action'] ? options['action'] : location.pathname+'?nocache=true')
        , method: 'POST'
        , fields: fields
        , onsubmit: function(ev){

                var form = this
                    , $form = $(this)
                    , fileInput = $form.find('input[type="file"]')[0]
                    , file = fileInput.files[0]
                    , ext = file.name.split('.').pop().toLowerCase()
                    , $dialog = $form;

                if(file.name.length < 1) {
                }
                else if(file.size > options['maxFileSize']) {
                    alert("The file is too big: "+file.size);
                }
                else if( options['allowedExts'] && Array.isArray(options['allowedExts']) && options['allowedExts'].indexOf(ext) === -1 ) {
                    alert("It should be file with extension "+options['allowedExts'].join(', '));
                }
                else { 

                    $form.find('input[type=submit]').val('Please wait...')
                        .addClass('btn_spinner')
                        .prop('disabled', true);

                    if(options['target']==='batch'){
                        $form.eiseIntraBatch('submit', {
                            timeoutTillAutoClose: null
                            , flagAutoReload: true
                            , title: title
                            , onload: function(){
                                $dialog.dialog('close').remove();
                            }
                        });
                    } else {
                        $form.prop('target', options['target']);
                        // console.log('qq')
                        // $form.submit();

                        window.setTimeout(function(){ $dialog.dialog('close'); }, 3000);
                        return true;
                        
                    }

                }

                return false;

            }
    })

    //$(this).eiseIntraBatch({url: 'job_form.php?DataAction=recalcJIT&jobIDs='+strSel, timeoutTillAutoClose: null});
    return false;
}

};


$.fn.eiseIntraForm = function( method ) {  


    if ( methods[method] ) {
        return methods[method].apply( this, Array.prototype.slice.call( arguments, 1 ));
    } else if ( typeof method === 'object' || ! method ) {
        return methods.init.apply( this, arguments );
    } else {
        $.error( 'Method ' +  method + ' not exists for jQuery.eiseIntraForm' );
    } 

};


})( jQuery );

/**
 * eiseIntraAJAX jQuery plug-in 
 *
 * @version 1.00
 * @author Ilya S. Eliseev http://e-ise.com
 * @requires jquery
 */
(function( $ ){

var displayMode='block';
var $template;

var _clear = function($body){

    $body.find('.eif_loaded').remove();

}

var _fill = function($body, data, conf){

    $body.find('.eif_spinner').css("display", 'none');

    var $template = $body.find('.eif_template');

    if(conf && conf['flagClear'])
        _clear($body);

    if(!data || data.length==0){
        $body.find('.eif_notfound').css("display", displayMode);
    } else {
        $body.find('.eif_notfound').css("display", 'none');
    }

    $.each(data, function(i, rw){
                  
        // 1. clone elements of .eif_temlplate, append them to tbody
        var $newItem = $template.clone(true);

        $newItem.each(function(){
            // 2. fill-in data to cloned elements
            var $subItem = $(this);
            $.each(rw, function (field, value){

                // set data
                var v = (value && typeof(value.v)!='undefined' ? value.v : value),
                    t = (value && typeof(value.t)!='undefined' ? value.t : v);

                var $elem = $subItem.find('.eif_'+field+', .eif-'+field);
                if (!$elem[0])
                    return true; //continue
                switch ($elem[0].nodeName){
                    case "INPUT":
                        if($elem.attr('type').toLowerCase()=='checkbox' ){
                            $elem[0].checked = (parseInt(v))
                        }
                    case "SELECT":
                        $elem.val(v);
                        break;
                    case "A":
                        if(value && typeof(value.h)!='undefined'){
                            if(value.h!='' && value.v!=''){
                                $elem.attr('href', value.h);
                                if(!$elem.hasClass('eif_nofill'))
                                    $elem.html(value.v);
                            } else {
                                $elem.remove();
                            }
                        } else {
                            $elem.remove();
                        }
                        break;
                    default:
                        if(!$elem.hasClass('eif_nofill'))
                            $elem.html(t);
                        if(t!=v)
                            $elem[0].dataset['v'] = (v===null ? '' : v);
                        break;
                }
                

                // 3. make eif_invisible fields visible if data is set
                if ($elem[0] && v && v!='' && !$elem.hasClass('eif_nofill')){
                    var invisible = $elem.parents('.eif_invisible')[0];
                    if(invisible)
                        $(invisible).removeClass('eif_invisible');
                }

            })

            // 4. paint eif_evenodd accordingly
            if($(this).hasClass('eif_evenodd')){
                $(this).addClass('tr'+i%2);
            }

            $(this).addClass('eif_loaded');

            if(rw.rowClass && rw.rowClass.v){
                $(this).addClass(rw.rowClass.v);
            }

        })
        $newItem.first().addClass('eif_startblock');
        $newItem.last().addClass('eif_endblock');
          
        // 5. TADAM! make it visible!
        $newItem.removeClass('eif_template');
        
        $body.append($newItem);
            
    });

    if(conf && conf.afterFill)
        conf.afterFill.call($body[0], data);
    
}

var methods = {

do: function(arg){

    var $caller = this
        , url = (typeof arg === 'object' ? arg.url : arg)
        , text = $caller.text()
        , clickHandler = $caller[0].onclick;

    $caller.eiseIntraAJAX('actionSpinner', true);

    $.getJSON(url, function(response){
        $caller.eiseIntraAJAX('actionSpinner', false);
        if(response.message){
            $('body').eiseIntra('showMessage', response.message);
        }
    })

    

},

actionSpinner: function(showOrHide){

    var $actionItem = this;

    if(showOrHide){

        this[0].dataset['savedText'] = $actionItem.text()
        this.savedClickHandler = $actionItem[0].onclick;

        $actionItem.text('Please wait...');
        $actionItem.addClass('ei-spinner');
        $actionItem[0].onclick = function(){
            return false;
        }
    } else {
        $actionItem.text(this[0].dataset['savedText']);
        $actionItem.removeClass('ei-spinner');
        $actionItem[0].onclick = this.savedClickHandler;
    }
},

showSpinner: function(){
    
    var $body = this;
    
    // hide "no events"
    var curDisplayMode = $body.find('.eif_notfound').css("display")
    displayMode = (curDisplayMode=='none' ? displayMode : curDisplayMode);

    $body.find('.eif_notfound').css("display", "none");

    // remove loaded items
    $body.find('.eif_loaded').remove();

    // show spinner
    $body.find('.eif_spinner').css("display", displayMode);


}, 

/**
 * This method fills the table with data obtained from the URL as JSON or passed as array/object.
 * Configuration object (conf) may contain callbacks to be run before fill and after it. It might be usable when you need to modify received data and/or apply CSS styles after data is filled.
 * 
 * @param variant URLorObj Data source URL or data as object or array.
 * @param object conf The object that may contain two callbacks:
 * * beforeFill(response) - this function is to be called after data is received with AJAX and before table fill. Data could be modified in any way. Parameter response is full response object according to eiseIntraAJAX specification.
 * * afterFill(data) - this function is called after table is filled. Data parameter contains response.data object/array.
 */

fillTable: function(URLorObj, conf){
    
    var $body = this;

    if(typeof(URLorObj)=='object'){
        
        if(conf && conf.beforeFill)
        conf.beforeFill.call($body, URLorObj);

        _fill($body, URLorObj, conf);

    } else {

        $body.eiseIntraAJAX('showSpinner');

        var strURL = URLorObj;

        $.getJSON(strURL,
            function(response){

                if(conf && conf.beforeFill)
                    conf.beforeFill.call($body, response);

                if ((response.status && response.status!='ok')
                    || response.ERROR // backward-compatibility
                    )
                {
                    $('body').eiseIntra('showMessage', (response.ERROR ? response.ERROR : response.message));
                    return;
                }
                _fill($body, response.data, conf);

        });  

    }

    
},

initFileUpload: function(conf){

    var $dropzone = this.find('.eif-file-dropzone')
        , $inpFile = this.find('.eif-attachment')
        , inpName = $inpFile.attr('name')
        , $form = $inpFile.parents('form').first()
        , $btnSubmit = $form.find('input[type=submit],button')
        , $tbody = this.find('tbody')
        , $hrefFileDelete = $tbody.find('.eif_filUnattach')
        , formData = new FormData($form[0]);

    if(!$dropzone[0] || !formData || !('draggable' in $dropzone[0]) || !('ondragstart' in $dropzone[0] && 'ondrop' in $dropzone[0]) ) {
        $dropzone.css('display', 'none');
        $inpFile.css('display', 'block');
        $btnSubmit.css('display', 'block');
        return this;
    } else 
        $btnSubmit.css('display', 'none');

    $dropzone.click(function(){
        $inpFile.click();
    });

    $inpFile.change (function(e) {
        for(var i=0;i<this.files.length;i++){
            formData.append( inpName, this.files[i] );
        }
        _do_upload();
    });

    $dropzone[0].addEventListener('dragenter', function(e){e.preventDefault();});
    $dropzone[0].addEventListener('dragover', function(e){e.preventDefault();});
    $dropzone[0].addEventListener('drop', _dropped);

    _init_delete();

    function _dropped(e){
        e.preventDefault();
        var files = e.dataTransfer.files;

        for(var i=0;i<files.length;i++){
            formData.append( inpName, files[i] );
        }

        _do_upload();
    }

    function _form_reset(){
        formData.delete( inpName );
    }

    function _init_delete(){
        $hrefFileDelete.off('click').click(function(){
            var $idField = $(this).find('.eif_filGUID');

            if($idField[0] && confirm('Are you sure you\'d like to delete?')){
                $.getJSON(location.pathname+location.search+'&DataAction=deleteFile&filGUID='+encodeURIComponent($idField.val())
                    , function(response){
                        $('body').eiseIntra('showMessage', response.message);
                        $tbody.eiseIntraAJAX('fillTable', response.data, $.extend({flagClear: true}, conf));
                        _init_delete();
                    });
            }
        })
    }

    function _do_upload(){

        $dropzone.addClass('uploading');

        $.ajax({
            url: location.pathname+location.search,  //server script to process data
            type: 'POST',
            xhr: function() {  // custom xhr
                myXhr = $.ajaxSettings.xhr();
                if(myXhr.upload){ // if upload property exists
                    myXhr.upload.addEventListener('progress', _fileUploadProgress, false); // progressbar
                }
                return myXhr;
            },
            // Ajax events
            success: completeHandler = function(response) {

                $dropzone.removeClass('uploading');

                if($tbody[0] && response.status=='ok'){
                    $tbody.eiseIntraAJAX('fillTable', response.data,  $.extend({flagClear: true}, conf));
                    _init_delete();
                }

                $('body').eiseIntra('showMessage', response.message);

                _form_reset();

            },
            error: errorHandler = function(xhr, status, text) {
                $dropzone.removeClass('uploading');
                $('body').eiseIntra('showMessage', status+text);
                _form_reset();
            },
            // Form data
            data: formData,
            // Options to tell jQuery not to process data or worry about the content-type
            cache: false,
            contentType: false,
            processData: false
        }, 'json');
    }

    function _fileUploadProgress(){

    }

    return this;
}

}


$.fn.eiseIntraAJAX = function( method ) {  

    if ( methods[method] ) {
        return methods[method].apply( this, Array.prototype.slice.call( arguments, 1 ));
    } else if ( method.match(/[^a-z0-9]/i) || typeof method === 'object' || ! method ) {
        return methods.do.apply( this, arguments );
    } else {
        $.error( 'Method ' +  method + ' not exists for jQuery.eiseIntraAJAX' );
    } 

};


})( jQuery );


function intraInitializeForm(){eiseIntraInitializeForm()}

function eiseIntraInitializeForm(){
    
    $('.eiseIntraForm').eiseIntraForm().submit(function(ev){
        return $(this).eiseIntraForm("validate");
    });

}

function eiseIntraAdjustPane(){
    
    var oPane = $("#pane");
    //var height = oPane.parents().first().outerHeight();
    var height = ($(window).height() - $('#header').outerHeight());
    
    var divTOC = $('#toc');
    
    //MBP = Margin+Border+Padding
    
    var divTocMBP = divTOC.outerHeight(true) - divTOC.height();
    
    divTOC.css("height", (height-divTocMBP)+"px");
    divTOC.css("max-height", (height-divTocMBP)+"px");
    
    divPaneMBP = oPane.outerHeight(true) - oPane.height();
    height = height - (oPane.outerHeight(true) - oPane.height()) - 3;
    //height = height - 2;
    
    oPane.css("height", height+"px");
    oPane.css("min-height", height+"px");
    
    //adjust toc width, actual for IE
    //$('#td_toc').css("width", (divTOC.outerWidth(true)+"px"));
}

function MsgClose(){
    $('body').eiseIntra('hideMessage');
    }
function MsgShow(text){
    $('body').eiseIntra('showMessage');
}


/* backward compatibilty stuff */    
function initialize_inputs(){
    intraInitializeForm();
}

function showModalWindow(idDivContents, strTitle, width){ //requires jquery
    
    var selDiv = '#'+idDivContents;
    
    $(selDiv).attr('title', strTitle);
    $(selDiv).dialog({
            modal: true
            , width: width!=undefined ? width : 300
        });
    
}

function showDropDownWindow(o, divID) {
    showModalWindow(divID, $(o).text());
}
    
    
    
   /* Made by Mathias Bynens <http://mathiasbynens.be/> */
function number_format(a, b, c, d) {

    var minus = (parseFloat(a)<0 ? '-' : '');

 a = Math.abs(Math.round(a * Math.pow(10, b)) / Math.pow(10, b));

 e = a + '';
 f = e.split('.');
 if (!f[0]) {
  f[0] = '0';
 }
 if (!f[1]) {
  f[1] = '';
 }
 if (f[1].length < b) {
  g = f[1];
  for (i=f[1].length + 1; i <= b; i++) {
   g += '0';
  }
  f[1] = g;
 }
 if(d != '' && f[0].length > 3) {
  h = f[0];
  f[0] = '';
  for(j = 3; j < h.length; j+=3) {
   i = h.slice(h.length - j, h.length - j + 3);
   f[0] = d + i +  f[0] + '';
  }
  j = h.substr(0, (h.length % 3 == 0) ? 3 : (h.length % 3));
  f[0] = j + f[0];
 }
 c = (b <= 0) ? '' : c;
    
    return minus + f[0] + c + f[1];
}

function replaceCyrillicLetters(str){
    var arrRepl = [['410', '430','A']
       , ['412', '432','B']
       , ['415', '435','E']
       , ['41A', '43A', 'K']
       , ['41C', '43C', 'M']
       , ['41D', '43D', 'H']
       , ['41E', '43E', 'O']
       , ['420', '440', 'P']
       , ['421','441', 'C']
       , ['422','442', 'T']
       , ['423','443', 'Y']
       , ['425','445', 'X']
       ];
    str = escape(str);
    for (var i=0;i<arrRepl.length;i++){
       eval("str = str.replace(/\%u0"+arrRepl[i][0]+"/g, arrRepl[i][2]);");
       eval("str = str.replace(/\%u0"+arrRepl[i][1]+"/g, arrRepl[i][2]);");
    }
    return unescape(str);
}

function getNodeXY(oNode){
    return [$(oNode).offset().left, $(oNode).offset().top];
}

function span2href(span, key, href){
    if (span==null) return;
    var oHiddenInput = document.getElementById(span.id.replace(/^span_/, ""));
    span.innerHTML = '<a href="'+href+'?'+key+'='+encodeURIComponent(oHiddenInput.value)+'">'+$(span).text()+'</a>';
}
/* /backward compatibilty stuff */


function eiseIntraLayout(oConf){
    
    this.conf = { menushown: false
        , menurootHeight: 16
        , menuwidth: $('#toc ul.simpleTree').outerWidth(true)       
        };
    
    var oThis = this;
    
    $.extend(oThis.conf,oConf);
    
    $('#toc .simpleTree').append('<div id="toc_button"></div>');
    
    $('#toc_button').css("position", "absolute")
        .css("left", (this.conf.menuwidth - $('#toc_button').width())+"px")
        .css("top", ((this.conf.menurootHeight -$('#toc_button').height()) / 2) + "px")
    if (this.conf.menushown)
        $('#toc_button').addClass("toc_menushown");
        
    $('#toc_button').click(function(){ oThis.toggleMenu() });
    
}

eiseIntraLayout.prototype.toggleMenu = function(){
    
    var layout = this;
    
    $('#toc_button').toggleClass("toc_menushown");
    
    if (this.conf.menushown){
    
        layout.conf.menushown = false;
        layout.adjustPane();
        
        // slowly hide menu
        $('#toc').animate({
            opacity: 0.25,
            height: this.conf.menurootHeight+'px'
            }, 400, function() {
                
            });
    } else {
        // slowly show menu
        $('#toc').animate({
            opacity: 1,
            height: this.getPaneHeight(),
            maxHeight: this.getPaneHeight()
            }, 400, function() {
                layout.conf.menushown = true;
                layout.adjustPane();
            });
    }
    
}

eiseIntraLayout.prototype.getPaneHeight = function(){
    
    var oPane = $("#pane");
    
    var height = ($(window).height() - oPane.offset().top);
    
    //MBP = Margin+Border+Padding
    divPaneMBP = oPane.outerHeight(true) - oPane.height();
    height = height - divPaneMBP;
    
    return height;
    
}
eiseIntraLayout.prototype.getTOCHeight = function(){
    
    var oPane = $("#pane");
    
    var height = ($(window).height() - $('#header').outerHeight());
    
    //MBP = Margin+Border+Padding
    var divTocMBP = divTOC.outerHeight(true) - divTOC.height();
    height = height - divTocMBP;
    
    return height;
    
}

eiseIntraLayout.prototype.adjustPane = function(){
    
    var oPane = $("#pane");
    
    var divTOC = $('#toc');
    
    var divPaneMBP = oPane.outerHeight(true) - oPane.height();
    var paneHeight = this.getPaneHeight();
    
    if (this.conf.menushown){
        
        var divTocMBP = divTOC.outerHeight(true) - divTOC.height();
        
        divTOC.css("overflow-y", "auto");
        divTOC.css("overflow-x", "hidden");
        divTOC.css("width", this.conf.menuwidth);
        divTOC.css("height", (this.getPaneHeight()-divTocMBP)+"px");
        divTOC.css("max-height", (this.getPaneHeight()-divTocMBP)+"px");
        
        oPane.css("left", divTOC.outerWidth(true)+"px");
        oPane.css("width", ($(window).width()-divTOC.outerWidth(true))+"px");
        
    } else {
        
        divTOC.css("overflow-y", "hidden");
        divTOC.css("overflow-x", "hidden");
        oPane.css("left", "0px");
        oPane.css("width", $(window).width()+"px");
        
    }
    
    oPane.css("height", paneHeight+"px");
    oPane.css("min-height", paneHeight+"px");

}


/***********
Auth-related routines 
***********/


function base64ToAscii(c)
{
    var theChar = 0;
    if (0 <= c && c <= 25){
        theChar = String.fromCharCode(c + 65);
    } else if (26 <= c && c <= 51) {
        theChar = String.fromCharCode(c - 26 + 97);
    } else if (52 <= c && c <= 61) {
        theChar = String.fromCharCode(c - 52 + 48);
    } else if (c == 62) {
        theChar = '+';
    } else if( c == 63 ) {
        theChar = '/';
    } else {
        theChar = String.fromCharCode(0xFF);
    } 
    return (theChar);
}

function base64Encode(str) {
    var result = "";
    var i = 0;
    var sextet = 0;
    var leftovers = 0;
    var octet = 0;

    for (i=0; i < str.length; i++) {
         octet = str.charCodeAt(i);
         switch( i % 3 )
         {
         case 0:
                {
                    sextet = ( octet & 0xFC ) >> 2 ;
                    leftovers = octet & 0x03 ;
                    // sextet contains first character in quadruple
                    break;
                }
          case 1:
                {
                    sextet = ( leftovers << 4 ) | ( ( octet & 0xF0 ) >> 4 );
                    leftovers = octet & 0x0F ;
                    // sextet contains 2nd character in quadruple
                    break;
                }
          case 2:

                {

                    sextet = ( leftovers << 2 ) | ( ( octet & 0xC0 ) >> 6 ) ;
                    leftovers = ( octet & 0x3F ) ;
                    // sextet contains third character in quadruple
                    // leftovers contains fourth character in quadruple
                    break;
                }

         }
         result = result + base64ToAscii(sextet);
         // don't forget about the fourth character if it is there

         if( (i % 3) == 2 )
         {
               result = result + base64ToAscii(leftovers);
         }
    }

    // figure out what to do with leftovers and padding
    switch( str.length % 3 )
    {
    case 0:
        {
             // an even multiple of 3, nothing left to do
             break ;
        }

    case 1:
        {
            // one 6-bit chars plus 2 leftover bits
            leftovers =  leftovers << 4 ;
            result = result + base64ToAscii(leftovers);
            result = result + "==";
            break ;
        }

    case 2:
        {
            // two 6-bit chars plus 4 leftover bits
            leftovers = leftovers << 2 ;
            result = result + base64ToAscii(leftovers);
            result = result + "=";
            break ;
        }

    }

    return (result);

}
