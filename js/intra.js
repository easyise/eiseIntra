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

var getInput = function($form, strFieldName){

    return $form.find('#'+strFieldName);

}

var getAllInputs = function($form){

    return $form.find('.eiseIntraField input,.eiseIntraField select,.eiseIntraField textarea');

}

var getInputType = function($inp){
    var strType = ($inp.attr('type') ? $inp.attr('type') : 'text');
    if(strType=='text'){
        var classList = ($inp.attr('class') ? $inp.attr('class').split(/\s+/) : []);
        $.each( classList, function(index, item){
            if (item.match(/^eiseIntra_/)) {
                switch(item){
                    case 'eiseIntra_date':
                    case 'eiseIntra_datetime':
                        return item.replace('eiseIntra_', '');
                    default:
                        break;
                }
            }
        });
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
    return oInp.parents('.eiseIntraField').first().find('label').last();
}
var getFieldLabelText = function(oInp){
    return getFieldLabel(oInp).text().replace(/[\:\*]+$/, '');
}

var arrInitiallyRequiredFields = [];

var methods = {

init: function( options ) {

    return this.each(function(){
         
        var $this = $(this),
            data = $this.data('eiseIntraForm');
        
        // Если плагин ещё не проинициализирован
        if ( ! data ) {

            var conf = $.parseJSON($('#eiseIntraConf').val());

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

        $this.find('input[type="submit"]').each(function(){
            $(this).addClass('eiseIntraSubmit');
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
            
            var data = $(this).attr('src');
    		eval ("var arrData="+data+";");
    		var table = arrData.table;
    		var prefix = arrData.prefix;
    		var url = 'ajax_dropdownlist.php?table='+table+"&prefix="+prefix+(arrData.showDeleted!=undefined ? '&d=1' : '');
            
            var inp = this;
            var $inpVal = $(inp).prev("input");
            
    		$(this).autocomplete({
                source: function(request,response) {
                    
                    // reset old value
                    if(request.term.length<3){
                        response({});
                        $inpVal.val('');
                        $inpVal.change();
                        return;
                    }

                    var extra = $(inp).attr('extra');
                    var urlFull = url+"&q="+encodeURIComponent(request.term)+(extra!=undefined ? '&e='+encodeURIComponent(extra) : '');
                    
                    $.getJSON(urlFull, function(response_json){
                        
                        // reset old value - we got new JSON!
                        $inpVal.val('');
                        $inpVal.change();

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
                },
                change: function(event, ui){
                }
    		});
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

        })
        
        $this.find('input.eiseIntraDelete').click(function(ev){
                if (confirm("Are you sure you'd like to delete?")){
                    $this.find('input,select').removeAttr('required');
                    $this.find('#DataAction').val('delete');
                    $this.submit();
                } 
        });
        
    });
},

validate: function( ) {
    
    if ($(this).find('#DataAction')=='delete')
        return true;
    
    var canSubmit = true,
        conf = $(this).data('eiseIntraForm').conf,
        $this = $(this);
    
    getAllInputs($this).each(function() {

        var strValue = $(this).val()
            ,strType = getInputType($(this))
            ,strRegExDateToUse = ''
            ,$inpToCheck=$(this);

        if ($inpToCheck.attr('required')==='required'){
            if ($inpToCheck.val()===""){
                alert(getFieldLabelText($inpToCheck)+" is mandatory");
                $(this).focus();
                canSubmit = false;
                return false; //break;
            }
        }
        

        switch (strType){
            case "number":
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
        if(label[0] && $.inArray( $(this).attr('id'), arrInitiallyRequiredFields ) < 0){
            label.text(label.text().replace(/\*\:$/, ":"));
            $(this).removeAttr('required');    
        }
        
    });
    
    if ( obj.strMandatorySelector=='')
        return;
    
    $(this).find( obj.strMandatorySelector ).each(function(){
        
       var label = getFieldLabel($(this));
       label.text(label.text().replace(/\:$/, "*:"));
       if (!obj.flagDontSetRequired){
            $(this).attr('required', 'required');
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

value: function(strFieldName, strType, val, decimalPlaces){

    var conf = this.data('eiseIntraForm').conf;

    var strRegExDate = conf.strRegExDate;
    var strDateFormat = conf.dateFormat;
    var $inp = this.find('#'+strFieldName);

    if (val==undefined){
        var strValue = $inp.val();
        switch(strType){
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
                return isNaN(nVal) ? 0 : nVal;
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
                    return new Date(year, arrMatch[strDateFormat.indexOf('m')]-1, +arrMatch[strDateFormat.indexOf('d')]);
                } else {
                    return null;
                }
            default:
                return strValue;
        }
    } else {
        var strValue = val;
        switch(strType){
            case "integer":
            case "int": 
                strValue = number_format(strValue, 0, conf.decimalSeparator, conf.thousandsSeparator);
                break;
            case "numeric":
            case "real":
            case "double":
            case "money":
                if(typeof(strValue)=='number'){
                    strValue = number_format(strValue, 
                        decimalPlaces!=undefined ? decimalPlaces : conf.decimalPlaces
                        , conf.decimalSeparator, conf.thousandsSeparator
                    )
                }
                break;
            default:
                break;
        }
        oInp = this.find('#'+strFieldName+'').first();
        oInp.val(strValue);
        return this;
    }
},

fill: function(data, options){
    
    return this.each(function(){
    
        var $form = $(this);

        $.each(data, function(field, fieldData){

            if( typeof(fieldData)=='object' && typeof(fieldData.v)=='undefined' )
                return true; // skip objects without data

            var fData = (typeof(fieldData)=='object' && typeof(fieldData.v)!='undefined' 
                ? fieldData
                : {v: fieldData});

            var $inp = $form.find('#'+field);
            if (!$inp[0]){
                if(options && options.createMissingAsHidden){
                    $inp = $('<input type="hidden">').attr('id', field).attr('name', field).appendTo($form);
                } else 
                    return true; // continue
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
                    var html = '';
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

        $form.find(strSelector).bind('change', function(){
            callback($(this));
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

    var $frm = $('<form/>').appendTo('body').addClass('eiseIntraForm');

    if(conf.action)
        $frm.attr('action', conf.action);

    if(conf.method)
        $frm.attr('method', conf.method);

    $frm.append('<span class="ui-helper-hidden-accessible"><input type="text"></span>');

    $.each(conf.fields, function(ix, field){

        $frm.eiseIntraForm('addField', field );

        if(field.type=='file')
            $frm.attr('enctype', 'multipart/form-data')

    });

    var btnCloseTitle = (conf.flagUnsubmittable ? 'Close' : 'Cancel');

    $frm.append('<div class="eif_actionButtons">'
        +(conf.flagUnsubmittable ? '' : '<input type="submit" value="OK" class="eiseIntraSubmit">')
        +'<input type="button" value="'+btnCloseTitle+'" class="eif_btnClose">'
        +'</div>');

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
                if(typeof($frm.find('#'+$(inp).attr('name')+'_text')[0])!='undefined'){
                    objVals[$(inp).attr('name')]['t'] = $frm.find('#'+$(inp).attr('name')+'_text').val();
                }
            }
                
        })
        

        if(conf.onsubmit)
            return conf.onsubmit(objVals, $frm);
        
        $frm.dialog('close').remove();

        return true;

    });

    var dlgConf= {
        modal: true
        , title: conf.title
        , resize: "auto"
    };

    if(conf.onclose)
        dlgConf.close = conf.onclose;

    $frm.dialog(dlgConf);

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
            break;
        case 'combobox':
        case 'select':
            element = $('<select>');
            $.each(field.options, function(ix, item){
                $opt = $('<option>');
                $opt.attr('value', item.v);
                $opt.text(item.t);
                $opt.appendTo(element);
            })
            break;
        case 'password':
            element = $('<input type="password">');
            break;
        case 'file':
            element = $('<input type="file">');
            break;
        case 'checkbox':
            element = $('<input type="checkbox">');
            break;
        case 'hidden':
            element = $('<input type="hidden">');
            break;
        case 'hr':
            element = $('<hr>');
            break;
        case 'p':
            element = $('<p>');
            break;
        default:
            element = $('<input type="text">');
            if(field.type!='text'){
                element.addClass('eiseIntra_'+field.type);
            }
            break;
    }

    if(field.name)
        element.attr('name', field.name);

    if(field.value && $.inArray(type, ['hr', 'p', 'checkbox']) < 0 )
        element.val(field.value);

    if(field.value && type=='p')
        element.html(field.value);

    if((field.value===true || field.checked) && type=='checkbox')
        element.attr('checked', 'checked');

    if( field.type!='hidden' && field.title && $.inArray(type, ['hr', 'p'])<0 ){

        if(field.required){
            element.attr('required', 'required');
        }


        var $field = (type=='checkbox' 
            ? $('<div>')
                .append('<label></label>')
                .append( $('<label>'+field.title+'</label>').prepend(element).addClass('eiseIntraValue') )
            : $('<div><label>'+field.title+':</label></div>').append(element.addClass('eiseIntraValue'))
            ).addClass('eiseIntraField');

    } else {
        
        $field = element;
            
    }
    
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

var displayMode;
var $template;

var _fill = function($body, data, conf){

    $body.find('.eif_spinner').css("display", 'none');

    var $template = $body.find('.eif_template');

    if(!data || data.length==0){
        $body.find('.eif_notfound').css("display", displayMode);
    }

    $.each(data, function(i, rw){
                  
        // 1. clone elements of .eif_temlplate, append them to tbody
        var $newItem = $template.clone(true);

        $newItem.each(function(){
            // 2. fill-in data to cloned elements
            var $subItem = $(this);
            $.each(rw, function (field, value){

                // set data
                var v = (value && typeof(value.v)!='undefined' ? value.v : value);
                var $elem = $subItem.find('.eif_'+field);
                if (!$elem[0])
                    return true; //continue
                switch ($elem[0].nodeName){
                    case "INPUT":
                    case "SELECT":
                        $elem.val(v);
                        break;
                    case "A":
                        if(value && typeof(value.h)!='undefined'){
                            if(value.h!='' && value.v!=''){
                                $elem.attr('href', value.h);
                                $elem.html(value.v);
                            } else {
                                $elem.remove();
                            }
                        } else {
                            $elem.remove();
                        }
                        break;
                    default:
                        $elem.html(v);
                        break;
                }
                

                // 3. make eif_invisible fields visible if data is set
                if ($elem[0] && v && v!=''){
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
        conf.afterFill(data);
    
}

var methods = {

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

fillTable: function(URLorObj, conf){
    
    var $body = this;

    if(typeof(URLorObj)=='object'){
        
        _fill($body, URLorObj, conf);

    } else {

        $body.eiseIntraAJAX('showSpinner');

        var strURL = URLorObj;


        $.getJSON(strURL,
            function(data){

                if(conf && conf.beforeFill)
                    conf.beforeFill(data);

                if ((data.status && data.status!='ok')
                    || data.ERROR // backward-compatibility
                    )
                {
                    alert(data.ERROR ? data.ERROR : data.message);
                    return;
                }
                _fill($body, data.data, conf);

        });  

    }

    
}

}


$.fn.eiseIntraAJAX = function( method ) {  

    if ( methods[method] ) {
        return methods[method].apply( this, Array.prototype.slice.call( arguments, 1 ));
    } else if ( typeof method === 'object' || ! method ) {
        return methods.init.apply( this, arguments );
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

function eiseIntraAdjustFrameContent(){
    
    var oMenubarHeight = $(".menubar").outerHeight(true);

    if (oMenubarHeight!=null) {
        $("#frameContent").css ("padding-top", oMenubarHeight+"px");
    }   
}

function MsgClose(){
       $("#sysmsg").fadeOut("slow");
    }
function MsgShow(text){
    var $sysmsg = $('#sysmsg');
    
    if(text)
        $sysmsg.html(text);

   
    var msg = $sysmsg.html();
	var msgText = $sysmsg.text();

    if (msg !=""){

        if(!$sysmsg.find('#sysmsgicon')[0])
            $sysmsg.html('').append('<span class="ui-icon" style="float: left; margin-right: .3em;" id="sysmsgicon"></span>');

        if(msgText.match(/^ERROR(\:){0,1}\s*/i)){ // show alert box with buttons
            
            if(typeof($sysmsg.dialog)=='function'){
                
                msg = msg.replace(/(^ERROR(\:){0,1}\s*)/i, '');

                $sysmsg.css('white-space', 'pre');

                $sysmsg.append('<p style="margin-left: 20px; margin-top:0">'+msg+'</p>').find('#sysmsgicon').addClass('ui-icon-alert');

                $sysmsg.dialog({
                    dialogClass: "ui-state-error ei_sysmsg_error"
                    , resizable: false
                    , title: 'ERROR'
                    , buttons: {Ok: function(){ $sysmsg.dialog('close') } }
                    , open: function(){
                        window.setTimeout(function(){
                            $sysmsg.dialog('close')
                        }, 10000)
                    }
                    , hide: 'fade'
                })
            } else {
                $("#sysmsg").addClass('error').slideDown("slow", function(){ window.setTimeout("MsgClose()", 10000);});
            }
        } else { // show infobox

            if(typeof($sysmsg.dialog)=='function'){
                
                $sysmsg.append('<p style="margin-left: 20px; margin-top:0">'+msg+'</p>').find('#sysmsgicon').addClass('ui-icon-info');

                $sysmsg.css('white-space', 'nowrap');

                $sysmsg.dialog({
                    dialogClass: "ei_dialog_notitle ei_sysmsg_info"
                    , resizable: false
                    , height: $('#menubar').height()
                    , width: 'auto'
                    , position: {my: 'right top', at: 'right top', of: window}
                    , open: function(){
                        window.setTimeout(function(){
                            $sysmsg.dialog('close')
                        }, 7000)
                    }
                    , hide: 'fade'
                })
            } else {
                $("#sysmsg").addClass('normal').slideDown("slow", function(){ window.setTimeout("MsgClose()", 10000);});
            }

        }

    }
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
