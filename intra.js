(function( $ ){

var isDateInputSupported = function(){
    var elem = document.createElement('input');
    elem.setAttribute('type','date');
    elem.value = 'foo';
    return (elem.type == 'date' && elem.value != 'foo');
}

var convertDateForDateInput = function($eiForm, inp){
    
    var conf = $eiForm.data('eiseIntraForm').conf;
    var strRegExDate = conf.dateFormat
        .replace(new RegExp('\\.', "g"), "\\.")
        .replace(new RegExp("\\/", "g"), "\\/")
        .replace("d", "([0-9]{1,2})")
        .replace("m", "([0-9]{1,2})")
        .replace("Y", "([0-9]{4})")
        .replace("y", "([0-9]{1,2})");
    
    var arrVal = inp.getAttribute('value').match(strRegExDate);
    if (arrVal)
        $(inp).val(arrVal[3]+'-'+arrVal[2]+'-'+arrVal[1]);
    
    return;

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
    return oInp.prev('label');
}
var getFieldLabelText = function(oInp){
    return getFieldLabel(oInp).text().replace(/[\:\*]+$/, '');
}

var methods = {

init: function( options ) {

    return this.each(function(){
         
        var $this = $(this),
            data = $this.data('eiseIntraForm');
        
        // Если плагин ещё не проинициализирован
        if ( ! data ) {

            var conf = $.parseJSON($('#eiseIntraConf').val());
            
            $(this).data('eiseIntraForm', {
                form : $this,
                conf: $.extend( conf, options)
            });
        }
        
        $this.find('input.eiseIntraValue').each(function() {
            switch ($(this).attr('type')){ 
                case "date":
                    if (isDateInputSupported()){
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
            
        });
    
        $this.find('input[type="submit"]').each(function(){
            $(this).addClass('eiseIntraSubmit');
        });
    
        $this.find('select.eiseIntraValue').each(function() {
            $(this).css('width', 'auto');
        });
    
        $this.find('input.eiseIntra_date, input.eiseIntra_datetime').each(function() {
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
            
            var data = $(this).attr('src');
    		eval ("var arrData="+data+";");
    		var table = arrData.table;
    		var prefix = arrData.prefix;
    		var url = 'ajax_dropdownlist.php?table='+table+"&prefix="+prefix+(arrData.showDeleted!=undefined ? '&d=1' : '');
            
            var inp = this;
            
    		$(this).autocomplete({
                source: function(request,response) {
                
                    var extra = $(inp).attr('extra');
                    var urlFull = url+"&q="+encodeURIComponent(request.term)+(extra!=undefined ? '&e='+encodeURIComponent(extra) : '');
                    
                    $.getJSON(urlFull, function(response_json){
                        
                        response($.map(response_json.data, function(item) {
                                return {  label: item.optText, value: item.optValue  }
                            }));
                        });
                        
                    },
                minLength: 3,
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
        
    });
},

validate: function( ) {
    
    var canSubmit = true;
    
    var conf = $(this).data('eiseIntraForm').conf;
    
    var strRegExDate = conf.dateFormat
        .replace(new RegExp('\\.', "g"), "\\.")
        .replace(new RegExp("\\/", "g"), "\\/")
        .replace("d", "[0-9]{1,2}")
        .replace("m", "[0-9]{1,2}")
        .replace("Y", "[0-9]{4}")
        .replace("y", "[0-9]{1,2}");
    var strRegExTime = conf.timeFormat
        .replace(new RegExp("\\.", "g"), "\\.")
        .replace(new RegExp("\\:", "g"), "\\:")
        .replace(new RegExp("\\/", "g"), "\\/")
        .replace("h", "[0-9]{1,2}")
        .replace("H", "[0-9]{1,2}")
        .replace("i", "[0-9]{1,2}")
        .replace("s", "[0-9]{1,2}");
    
    $(this).find('input.eiseIntraValue').each(function() {
        
        var strValue = $(this).val();
        var strType = $(this).attr('type');
        
        var $inpToCheck = $(this).hasClass("eiseIntra_ajax_dropdown") ? $(this).prev("input") : $(this);
        
        if ($inpToCheck.attr('required')==='required' && $inpToCheck.val()===""){
            alert(getFieldLabelText($inpToCheck)+" is mandatory");
            $(this).focus();
            canSubmit = false;
            return false; //break;
        }
        
        switch (strType){
            case "number":
                strValue = parseFloat(strValue
                    .replace(new RegExp("\\"+conf.decimalSeparator, "g"), '.')
                    .replace(new RegExp("\\"+conf.thousandsSeparator, "g"), ''));
                if (strValue!="" && isNaN(strValue)){
                    alert(getFieldLabelText($(this))+" should be numeric");
                    $(this).focus();
                    canSubmit = false;
                    return false;
                }
                break;
            case 'date':
                if (isDateInputSupported()){
                    strRegExDate = "[0-9]{4}\-[0-9]{1,2}\-[0-9]{1,2}";
                }
            case 'time':
            case 'datetime':
                 
                var strRegEx = "^"+(strType.match(/date/) ? strRegExDate : "")+
                    (strType=="datetime" ? " " : "")+
                    (strType.match(/time/) ? strRegExTime : "")+"$";
                
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
    
    $(this).find('input.eiseIntraValue').each(function(){
        if ($(this).attr('type')=='hidden')
            return true; // continue
        
        var label = getFieldLabel($(this));
        label.text(label.text().replace(/\*\:$/, ":"));
        $(this).removeAttr('required');
    });
    
    if ( obj.strIDs==='')
        return;
    
    $(this).find( obj.strIDs ).each(function(){
        
       var label = getFieldLabel($(this));
       label.text(label.text().replace(/\:$/, "*:"));
       if (!obj.flagDontSetRequired){
            $(this).attr('required', 'required');
       }
    });
    
    })
    
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


function intraInitializeForm(){eiseIntraInitializeForm()}

function eiseIntraInitializeForm(){
    
    $('.eiseIntraForm').eiseIntraForm().submit(function(){
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
        var mrg = $("#frameContent").outerHeight(true) - $("#frameContent").height();
        var height = $(window).height() - mrg;
        
        //alert ($(window).height()+' - '+mrg);
        //$("#frameContent").css("height", height+"px");
        //$("#frameContent").css("min-height", height+"px");
        
    }   
}

function MsgClose(){
       $("#sysmsg").fadeOut("slow");
    }
function MsgShow(){
	var msg = $('#sysmsg').html();
	if (msg !=""){
       $("#sysmsg").slideDown("slow", function(){ window.setTimeout("MsgClose()", 10000);});
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
 a = Math.round(a * Math.pow(10, b)) / Math.pow(10, b);
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
 return f[0] + c + f[1];
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

