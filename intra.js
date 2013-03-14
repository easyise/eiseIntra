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
        
        var inp = this;
        
		$(this).autocomplete({
            source: function(request,response) {
                
                url = url+"&q="+encodeURIComponent(request.term);
                
                $.getJSON(url, function(response_json){
                    
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

function showModalWindow(idDivContents, strTitle){ //requires jquery
    
    var selDiv = '#'+idDivContents;
    
    $(selDiv).attr('title', strTitle);
    $(selDiv).dialog({
            modal: true
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