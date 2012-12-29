function intraInitializeForm(){
   
    $('input.intra_date, input.intra_datetime').each(function() {
        $(this).datepicker({
			changeMonth: true,
			changeYear: true,
            dateFormat: 'dd.mm.yy',
            constrainInput: false,
            firstDay: 1
            , yearRange: 'c-7:c+7'
        })
    });
    
    $('input.intra_ajax_dropdown').each(function(){
        var data = $(this).attr('src');
		eval ("var arrData="+data+";");
		var table = arrData.table;
		var prefix = arrData.prefix;
		var url = 'ajax_dropdownlist.php?table='+table+"&prefix="+prefix;
		
		$(this).autocomplete(url, {
			width: 300,
			multiple: false,
			matchContains: true,
			minChars: 3,
            dataType: 'json',
            formatResult: function(row) {return row[0].replace(/(<.+?>)/gi, '');},
            parse: function(data) {
                var parsed = [];
                arrParse = data.data;
                if (arrParse===null) {
                       arrParse = [];
                }
                for (var i = 0; i < arrParse.length; i++) {
                    parsed[parsed.length] = {
                            data: arrParse[i],
                            value: arrParse[i].optText,
                           result: arrParse[i].optText
                    };
                }
               return parsed;
            },
            formatItem: function(item) { return item.optText; }
		});
		$(this).result(function(event, data, formatted) {
			if (data){
				$(this).prev("input").val(data.optValue);
            }
		});
    })

}

function eiseIntraAdjustPane(){
    
    var oPane = $("#pane");
    //var height = oPane.parents().first().outerHeight();
    var height = ($(window).height() - $('#header').outerHeight());
    
    var divTOC = $('#toc');
    
    divTOC.css("height", height+"px");
    divTOC.css("max-height", height+"px");
    
    
    height = height - (oPane.outerHeight(true) - oPane.height()) - 3;
    //height = height - 2;
    
    oPane.css("height", height+"px");
    oPane.css("min-height", height+"px");
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

    
function showModalWindow(idDivContents, strTitle){ //requires jquery
    
    // thanks god for impromtu source cod
    var ie6 = (jQuery.browser.msie && jQuery.browser.version < 7);	
    var b = jQuery(document.body);
    var w = jQuery(window);
	
    if(ie6) $('select').css('visibility','hidden'); //hide all fuck <selects> 
	
	var msgbox = '<div class="mwBox" id="mwBox"><div class="mwFade" id="mwFade"></div>';		
    msgbox += '<div class="mw" id="mw"><div class="mwTitle">'+strTitle+'</div>';
    msgbox += '<div class="mwContainer"><div class="mwClose" id="mwClose">X</div>';
    msgbox +=   '<div class="mwContents" id="mwContents"></div>';
    msgbox += '</div></div></div>';
		
    var jqib =b.append(msgbox).children('#mwBox');
    var jqi = jqib.children('#mw');
    var jqif = jqib.children('#mwFade');
    
    
    
    var getWindowScrollOffset = function(){ 
        return (document.documentElement.scrollTop || document.body.scrollTop) + 'px'; 
    };		
    
    var getWindowSize = function(){ 
        var size = {
            width: window.innerWidth || (window.document.documentElement.clientWidth || window.document.body.clientWidth),
            height: window.innerHeight || (window.document.documentElement.clientHeight || window.document.body.clientHeight)
        };
        return size;
    };
    
    var ie6scroll = function(){ 
        jqib.css({ top: getWindowScrollOffset() }); 
    };
    
    var flashPrompt = function(){
        var i = 0;
        jqib.addClass('mwWarning');
        var intervalid = setInterval(function(){ 
            jqib.toggleClass('mwWarning');
            if(i++ > 1){
                clearInterval(intervalid);
                jqib.removeClass('mwWarning');
            }
        }, 100);			
    };		
    

    var escapeKeyClosePrompt = function(e){
        var kC = (window.event) ? event.keyCode : e.keyCode; // MSIE or Firefox?
        var Esc = (window.event) ? 27 : e.DOM_VK_ESCAPE; // MSIE : Firefox
        if(kC==Esc) removePrompt();
    };

    var positionPrompt = function(){
        var wsize = getWindowSize();
        jqib.css({ position: (ie6)? "absolute" : "fixed", height: wsize.height, width: "100%", top: (ie6)? getWindowScrollOffset():0, left: 0, right: 0, bottom: 0 });
        jqif.css({ position: "absolute", height: wsize.height, width: "100%", top: 0, left: 0, right: 0, bottom: 0 });
        jqi.css({ position: "absolute", top: "100px", left: "50%", marginLeft: ((((jqi.css("paddingLeft").split("px")[0]*1) + jqi.width())/2)*-1) });					
    };
    
    var stylePrompt = function(){
        jqif.css({ zIndex: 999, display: "none", opacity: 0.6 });
        jqi.css({ zIndex: 1000, display: "none" });
    }
    
    var divToAppend = $("#"+idDivContents);
    var removePrompt = function(msg){
        divToAppend.append($("#mwContents").children());
        $("#mwContents").empty();
        jqi.remove(); 
        if(ie6)b.unbind('scroll',ie6scroll);//ie6, remove the scroll event
        w.unbind('resize',positionPrompt);			
        jqif.fadeOut('slow',function(){
            jqif.unbind('click',flashPrompt);
            jqif.remove();
            jqib.unbind('keypress',escapeKeyClosePrompt);
            jqib.remove();
            if(ie6) $('select').css('visibility','visible'); //return back fcuking comboz
        });
    }
    
    positionPrompt();
    stylePrompt();	

    //Events
    if(ie6) w.scroll(ie6scroll);//ie6, add a scroll event to fix position:fixed
    jqif.click(flashPrompt);
    w.resize(positionPrompt);
    jqib.keypress(escapeKeyClosePrompt);
    jqi.find('.mwClose').click(removePrompt);
    
    //Show it
    jqif.fadeIn('slow');
    jqi['show']('fast');
    $("#mwContents").append(divToAppend.children());
    divToAppend.empty();
    //jqi.find('input').focus();//focus the default button
    
}

function showDropDownWindow(o, divID) {
//   alert(o.nodeName+" "+o.offsetLeft+" "+o.offsetTop);
    var isVisible;
    var div = document.getElementById(divID);

    if (div.style.visibility == "hidden") {
        /* hiding all selects
        var colSelects = document.getElementsByTagName("SELECT");
        for (var i=0; i<colSelects.length; i++) {
            var strId = colSelects[i].id;
            colSelects[i].style.visibility = "hidden";
        }
        */
        div.style.left = o.offsetLeft + "px";
        div.style.top = "28px";
        div.style.visibility = "visible";

        isVisible = true;

    } else {
        div.style.visibility = "hidden";

        /* showing all selects
        var colSelects = document.getElementsByTagName("SELECT");
        for(var i=0; i<colSelects.length; i++){
            var strId = colSelects[i].id;
            colSelects[i].style.visibility = "visible";
        }*/
        isVisible = false;
    }
    
    return isVisible;
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