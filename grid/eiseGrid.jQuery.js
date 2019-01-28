/********************************************************/
/*  
eiseGrid jQuery wrapper

requires jQuery UI 1.8: 
http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/jquery-ui.min.js


Published under GPL version 2 license
(c)2006-2019 Ilya S. Eliseev ie@e-ise.com, easyise@gmail.com

Contributors:
Pencho Belneiski
Dmitry Zakharov
Igor Zhuravlev

eiseGrid reference:
http://e-ise.com/eiseGrid/

*/
/********************************************************/
(function( $ ) {
var settings = {
    
};

function eiseGrid(gridDIV){

    this.id = gridDIV.attr('id');
    this.div = gridDIV;

    this.conf = $.parseJSON(this.div[0].dataset['config']);

    this.thead = this.div.find('table thead');
    this.tableMain = this.div.find('table.eg-table');
    this.tableContainer = this.div.find('table.eg-container');

    this.tbodyTemplate = this.tableContainer.find('tbody.eg-template');

    this.tbodies = this.tableContainer.find('tbody.eg-data');

    this.tbodyFirst = this.tbodies.first();

    this.tfoot = gridDIV.find('table tfoot');
    
    this.activeRow = {};
    this.lastClickedRowIx = null;
    this.selectedRowIx = [];


    this.onChange = []; // on change selector arrays
    this.goneIDs = []; // IDs of deleted rows

    this.arrTabs = [];
    this.selectedTab = null;
    this.selectedTabIx = 0;

    this.flagHardWidth = true; //when all columns has defined width in px
    
    var oGrid = this;

    this.tbodies.each(function(){
        oGrid.initRow( $(this) );
    });

    this.recalcAllTotals();

    if (this.tbodies.length==0)
        this.tableContainer.find('.eg-no-rows').css('display', 'table-row-group');

    __initControlBar.call(this);


    this.initLinesStructure();

    //clickable TH 
    this.thead.find('th').each(function(){
        var $th = $(this),
            strField = oGrid.getFieldName($th);
        $.each(oGrid.conf.fields, function(field, props){
            if(strField!=field)
                return true;
            if(props.type=='checkbox' && props.headerClickable){
                $th.click(function(){
                    oGrid.div.find('.eg-data .'+oGrid.id+'-'+field+' input[type="checkbox"]').click();
                });
                return true;
            }
            if(props.sortable){
                $th.click(function(ev){
                    oGrid.sort( field );
                })
                return true;
            }
        })
    });

    //tabs 3d
    this.div.find('#'+this.id+'-tabs3d').each(function(){
        oGrid.selectedTab = document.cookie.replace(new RegExp("(?:(?:^|.*;\\s*)"+oGrid.conf.Tabs3DCookieName+"\\s*\\=\\s*([^;]*).*$)|^.*$"), "$1");

        $(this).find('a').each(function(ix, obj){
            var tabID = $(obj).attr('href').replace('#'+oGrid.id+'-tabs3d-', '');
            oGrid.arrTabs[ix] = tabID;
            if (tabID==oGrid.selectedTab){
                oGrid.selectedTabIx = ix;
                return false; //break
            }
        })


        $(this).tabs({
            active: oGrid.selectedTabIx
            , selected: oGrid.selectedTabIx
            , activate: function(event, ui){
                var ID = ui.newPanel[0].id.replace(oGrid.id+'-tabs3d-', '');
                oGrid.sliceByTab3d(ID);
            }
            , select: function(event, ui){
                var ID = ui.panel.id.replace(oGrid.id+'-tabs3d-', '');
                oGrid.sliceByTab3d(ID);
            }
        });

        oGrid.sliceByTab3d(oGrid.arrTabs[oGrid.selectedTabIx]);

    });

}


eiseGrid.prototype.initLinesStructure = function(){
    var oGrid = this,
        $colgroup = oGrid.tableContainer.find('colgroup col'),
        linesStruct = [];

    $colgroup.each(function(){
        var colName = oGrid.getFieldName($(this)),
            linesStructCol = {column: colName
                , width: $(this).css('width')
                , fields: []
                };

        oGrid.tbodyTemplate.find('td.'+oGrid.id+'-'+colName).each(function(){
            linesStructCol.fields[linesStructCol.fields.length] = $(this).find('input').first().attr('name').replace('[]', '');
        });

        linesStruct[linesStruct.length] = linesStructCol;

    });

    oGrid.linesStruct = linesStruct;
    
}

eiseGrid.prototype.toggleMultiLine = function(o){

    var oGrid = this,
        ls = oGrid.linesStruct,
        lsl = ls.length,
        nLines = 1,
        $thead = oGrid.thead,
        $tfoot = oGrid.tfoot,
        $thColgroup = oGrid.div.find('table.eg-table > colgroup'),
        flagHasEITS = (typeof oGrid.div.find('table.eg-table > tbody.eits-container')[0] != 'undefined'),
        $tcColgroup = (flagHasEITS ? oGrid.tableContainer.find('colgroup') : oGrid.div.find('table.eg-table > colgroup')),
        $tcBodies = oGrid.tableContainer.find('.eg-template,.eg-data'),
        $tfTotalsPrev = null,

        fieldSequence = ( typeof o == 'object' ? (o.fieldSequence ? o.fieldSequence : (o[0] ? o : [])) : [] ),
        fieldWidths = ( typeof o == 'object' ? (o.fieldWidths ? o.fieldWidths : {}) : {} );

    $tcBodies.css('display', 'none');
    oGrid.spinner();

    $thColgroup.detach();
    $tcColgroup.detach();

    // if grid has multiple subrows
    if( oGrid.tableMain.hasClass('multiple-lines') ){

        // run thru columns 
        for( var nCol=0; nCol<lsl; nCol++ ){
            var columnName = ls[nCol].column,
                className = oGrid.id+'-'+columnName, 
                $th = $thead.find('th.'+className),
                $colTh = $thColgroup.find('.'+className)
                columnAfter = columnName;

            // run thru titles
            for( var nField=0;nField<ls[nCol].fields.length;nField++ ){
                var fieldToRestore = ls[nCol].fields[nField],
                    title = oGrid.conf.fields[fieldToRestore].title;
                if(nField===0){
                    if(title){
                        $th[0].dataset.singleLineTitle = $th.text();
                        $th.text(title)
                    };
                    continue;
                }
                var classNameNew = oGrid.id+'-'+fieldToRestore,
                    classAfter = oGrid.id+'-'+columnAfter,
                    $thAfter = $thead.find('.'+classAfter),
                    $tfAfter = ($tfoot ? $tfoot.find('.'+classAfter) : null)
                    $colAfter = $thColgroup.find('.'+classAfter),
                    $thNew = $('<th class="'+classNameNew+'">'+title+'</th>'),
                    $colNew  = $colTh.clone().attr('class', classNameNew);

                $thAfter.after($thNew);
                $colAfter.after($colNew);
                if($tfAfter)
                    $tfAfter.after('<td class="'+classNameNew+'">&nbsp;</td>');

                $tcBodies.each(function(){
                    var $tbody = $(this),
                        $tdAfter = $tbody.find('td.'+classAfter),
                        $td = $tbody.find('input[name="'+fieldToRestore+'[]"]').parents('td').first();
                    $td.removeClass(className);
                    $td.attr('class', classNameNew+' '+$td.attr('class'));
                    $td.detach();
                    $td.insertAfter($tdAfter);
                })

            }

        }

        // set them in proper sequence
        for( var nField=0; nField<fieldSequence.length; nField++ ){

            if( !(fieldSequence[nField] && fieldSequence[nField-1]) )
                continue;

            var selectorToMove = oGrid.id+'-'+fieldSequence[nField],
                selectorPred = oGrid.id+'-'+fieldSequence[nField-1];

            $thColgroup.find('col.'+selectorToMove).insertAfter( $thColgroup.find('col.'+selectorPred) );

            $thead.find('th.'+selectorToMove).insertAfter( $thead.find('th.'+selectorPred) );

            $tcBodies.each(function(){
                $(this).find('td.'+selectorToMove).insertAfter( $(this).find('td.'+selectorPred) );
            });
        }

        // set column widths
        for(var col in fieldWidths){
            if(!fieldWidths.hasOwnProperty(col))
                continue;
            var sel = oGrid.id+'-'+col,
                $col = $thColgroup.find('col.'+sel);
            if($col[0]){
                $col[0].dataset['styleMultiline'] = $col.attr('style'); // save old style
                $col.css('width', fieldWidths[col]); // set new width
            }
        }

        $tcBodies.each(function(ix){
            $(this).find('tr').each(function(ix){
                if(ix===0)
                    return true;
                $(this).remove();
            })
        });

        oGrid.tableMain.removeClass('multiple-lines').addClass('single-line');

    } else { 

        var $tbodyTemplate = oGrid.tbodyTemplate;

        // calculate line numbers
        for( var nCol=0; nCol<lsl; nCol++ ){ var nl = ls[nCol].fields.length; nLines =  (nl > nLines ? nl : nLines); }

        // for each tbodies add tr
        $tcBodies.each(function(){
            for(var i=1;i<nLines;i++){
                $('<tr/>').appendTo(this);
            }
        });


        // run thru columns 
        for( var nCol=0; nCol<lsl; nCol++ ){
            var columnName = ls[nCol].column,
                className = oGrid.id+'-'+columnName, 
                $th = $thead.find('th.'+className),
                $colTh = $thColgroup.find('.'+className),
                nFields = ls[nCol].fields.length;

            // run thru fields
            for( var nField=0;nField<nLines;nField++ ){
                var fieldToPutDown = ls[nCol].fields[nField],
                    classToRemove = oGrid.id+'-'+fieldToPutDown;

                if(nField==0)
                    continue;

                if(fieldToPutDown){
                    $thColgroup.find('col.'+classToRemove).remove();
                    $thead.find('th.'+classToRemove).remove();
                } 

                // for each tbodies add tr
                $tcBodies.each(function(){
                    var $tdToPutDown = $(this).find('td.'+classToRemove),
                        $tr = $($(this).find('tr')[nField]),
                        $tdTarget = (fieldToPutDown ? $(this).find('td.'+classToRemove) : $('<td>&nbsp;</td>'));

                    $tdTarget.appendTo($tr);

                    $tdTarget.removeClass(classToRemove);

                    $tdTarget.attr('class', className+' '+$tdTarget.attr('class'));

                });

            }

            // colTH original width
            if($colTh[0].dataset['styleMultiline']){
                $colTH.attr('style', $colTh[0].dataset['styleMultiline']);
                delete $colTh[0].dataset['styleMultiline'];
            }
                
        }

        oGrid.tableMain.removeClass('single-line').addClass('multiple-lines');

    }
    

    // update colspans at tfoot/totals
    if(oGrid.tfoot[0]){
        var nSpan = 0;
        $thColgroup.find('col').each(function(){
            var $tfootTD = oGrid.tfoot.find( 'td.'+oGrid.id+'-'+oGrid.getFieldName($(this)) );
            if( $tfootTD[0] ){
                if($tfootTD.prev()[0] && nSpan>1)
                    $tfootTD.prev().attr('colspan', nSpan);
                nSpan = 0;
                return true; // continue
            }
            nSpan++;
        })
    }

    $thead.before($thColgroup);
    if(flagHasEITS){
        oGrid.div.find('table.eg-table > tbody.eits-container > tr > td').attr('colspan', $thColgroup.find('col').length);
        oGrid.tableContainer.find('tbody').first().before($thColgroup.clone());
    }

    oGrid.tableContainer.find('.eg-spinner').css('display', 'none');

    $tcBodies.each(function(ix){

        $(this).css('display', '');

    });

    if(flagHasEITS){
        var lastCol = oGrid.tableContainer.find('colgroup col').last(),
            lastTH = oGrid.thead.find('th').last();
        lastCol.width(lastTH.outerWidth(true)-oGrid.div.eiseTableSizer('getScrollWidth'));
    }    
}


eiseGrid.prototype.initRow = function( $tbody ){

    var oGrid = this;

    __attachDatepicker.call(oGrid, $tbody ); // attach datepicker to corresponding fields, if any
    __attachAutocomplete.call(oGrid, $tbody ); // attach autocomplete/typeahaed to corresponding fields, if any
    __attachFloatingSelect.call(oGrid, $tbody ); // attach floating <select> element to appear on corresponding fields, if any
    __attachCheckboxHandler.call(oGrid, $tbody ); // attach checkbox checkmark handler to corresponding fields, if any
    __attachRadioHandler.call(oGrid, $tbody ); // attach radio box checkmark handler to corresponding fields, if any
    __attachTotalsRecalculator.call( oGrid, $tbody ) // attach totals recalculator

    $tbody.bind("click", function(event){ //row select binding
        oGrid.selectRow($(this), event);
    });
    
    if(typeof(oGrid.dblclickCallback)==='function'){ // doubleclick custom function binding
        $tbody.bind("click", function(event){
            oGrid.dblclickCallback.call($tbody, oGrid.getRowID($tbody), event);
        });
    }

    $.each(oGrid.conf.fields, function(fld){ // change evend on eiseGrid input should cause row marked as changed
        $tbody.find("input[name='"+fld+"[]']").bind('change', function(){ 
            oGrid.updateRow( $tbody ); 
            var $inp = $(this),
                arrFnOnChange = oGrid.onChange[fld];
            if(arrFnOnChange ){
                for(var ifn=0; ifn<arrFnOnChange.length; ifn++){
                    var fn_onChange = arrFnOnChange[ifn];
                    if( typeof fn_onChange === 'function' ) {
                        fn_onChange.call(oGrid, $tbody, $inp);
                    }
                }
            }
        })
    });

    $tbody.find('input.eg-3d').bind('change', function(){ // input change bind to mark row updated
        oGrid.updateRow( $tbody ); 
    })
    
    $tbody.find('.eg-editor').bind("blur", function(){ //bind contenteditable=true div save to hidden input
        if ($(this).prev('input').val()!=$(this).text()){
            oGrid.updateRow( $tbody ); 
        }
        $(this).prev('input').val($(this).text());
    });

    $tbody.find('input[type=text], input[type=checkbox]').each(function(){
        oGrid.bindKeyPress($(this));
    })

}

var __attachTotalsRecalculator = function( $tbody ){

    var oGrid = this;

    $.each(oGrid.conf.fields, function(field, props){ //bind totals recalculation to totals columns
        if (props.totals==undefined)
            return true; // continue
        $tbody.find('td.'+oGrid.id+'-'+field+' input').bind('change', function(){
            oGrid.recalcTotals(field);
        })
    })
}

var __attachFloatingSelect = function( $tbody ){

    var oGrid = this;

    $tbody.find('td.eg-combobox input, td.eg-select input').bind('focus', function(){

        var oSelectSelector = '#select-'+($(this).attr('name').replace(/_text(\[\S+\]){0,1}\[\]/, ''));

        var oSelect = oGrid.tbodyTemplate.find(oSelectSelector).clone();
        var oInp = $(this);
        var oInpValue = $(this).prev('input');
        var opts = oSelect[0].options;

        $(this).parent('td').append(oSelect);
        
        oSelect.css('display', 'block');
        oSelect.offset({
            left: $(this).offset().left
            , top: $(this).offset().top
            });

        oSelect.width($(this).outerWidth(true)+$(this).outerHeight(true));

        for(var ix=0;ix<opts.length;ix++){
            var option = opts[ix];
            if (option.value == $(oInpValue).val())
                opts.selectedIndex = ix;
        }

        oSelect.bind('change', function(){
            oInpValue.val($(this).val());
            var si = opts.selectedIndex ? opts.selectedIndex : 0;
            if(opts[si])
                oInp.val(opts[si].text);
            oGrid.updateRow( $tbody );
            oInp.change();
            oInpValue.change();
        });
         
        oSelect.bind('blur', function(){
            oInpValue.val($(this).val());
            var si = opts.selectedIndex ? opts.selectedIndex : 0;
            if(opts[si])
                oInp.val(opts[si].text);
            $(this).css('display', 'none');
            $(this).remove();
        });
        
        oSelect.focus();

        oGrid.bindKeyPress(oSelect);
                
    });

}

var __attachDatepicker = function(oTr){
    var grid = this;
    $(oTr).find('.eg-datetime input[type=text], .eg-date input[type=text]').each(function(){
        try {
            $(this).datepicker({
                changeMonth: true,
                changeYear: true,
                dateFormat: grid.conf.dateFormat.replace('d', 'dd').replace('m', 'mm').replace('Y', 'yy'),
                constrainInput: false,
                firstDay: 1
                , yearRange: 'c-7:c+7'
            });
        }catch(e) {alert('err')};
    });
}

var __attachAutocomplete = function(oTr) {
    try {
      $(oTr).find(".eg-ajax_dropdown input[type=text]").each(function(){

        var initComplete, 
            inp = this, 
            $inp = $(inp),
            $inpVal = $inp.prev("input"),
            source = $.parseJSON(inp.dataset['source']),
            url = (source.scriptURL 
                ? source.scriptURL
                : 'ajax_dropdownlist.php')+
                '?'+
                'table='+(source.table ? encodeURIComponent( source.table ) : '')+
                (source.prefix ? '&prefix='+encodeURIComponent( source.prefix ) : '')+
                (source.showDeleted ? '&d='+source.showDeleted : '');

        if (typeof(jQuery.ui) != 'undefined') { // jQuery UI autocomplete conflicts with old-style BGIframe autocomplete
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
                        return;
                    }

                    var extra = ($inp.attr('extra') ? $inp.attr('extra') : $.parseJSON(inp.dataset['source'])['extra']);
                    var urlFull = url+"&q="+encodeURIComponent(request.term)+(typeof extra!== 'undefined' ? '&e='+encodeURIComponent(extra) : '');
                    
                    $.getJSON(urlFull, function(response_json){
                        
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
                        $inpVal.val(ui.item.value);
                        $inpVal.change();
                    } else 
                        $inpVal.val("");
                }
            });
        }
    });
    } catch (e) {}
}

var __attachCheckboxHandler = function( $tbody ){

    var oGrid = this;

    $tbody.find('.eg-checkbox input, .eg-boolean input').bind('change', function(){
        if(this.checked)
            $(this).prev('input').val('1');
        else 
            $(this).prev('input').val('0');
        oGrid.updateRow( $tbody ); 
    });
    
}

var __attachRadioHandler = function( $tbody ){
    var oGrid = this;

    $tbody.find('.eg-checkbox input, .eg-boolean input').bind('change', function(){
        if(this.checked)
            $(this).prev('input').val('1');
        else 
            $(this).prev('input').val('0');
        oGrid.updateRow( $tbody ); 
    });
}

var _getCaretPos = function(oField){
    var iCaretPos = 0;

    if (document.selection) { //IE

        // Set focus on the element
        oField.focus();

        // To get cursor position, get empty selection range
        var oSel = document.selection.createRange();

        // Move selection start to 0 position
        oSel.moveStart('character', -oField.value.length);

        // The caret position is selection length
        iCaretPos = oSel.text.length;
    } else if (typeof oField.selectionStart==='number') // Firefox support
        iCaretPos = oField.selectionStart;

    // Return results
    return iCaretPos;
}



var _setCaretPos = function(oField, posToSet){

    if(oField.nodeName.toLowerCase()!='input' || $(oField).attr('type')!='text' ||  posToSet=='all'){
        oField.focus();
        if(posToSet=='all')
            oField.select();
        return;
    }

    var iCaretPos = (posToSet=='last' ? oField.value.length : 0);

    oField.focus();
    oField.setSelectionRange(iCaretPos, iCaretPos);

}

var __initControlBar = function(){

    var oGrid = this;

    // control bar buttons
    this.div.find('.eg-button-add').bind('click', function(){
        oGrid.addRow(null);
    });
    this.div.find('.eg-button-edit').bind('click', function(){
        var selectedRow = oGrid.activeRow[oGrid.lastClickedRowIx];
        if (!selectedRow)
            return;
        var id = oGrid.getRowID(selectedRow);
        if(typeof(oGrid.dblclickCallback)!='undefined'){
            oGrid.dblclickCallback.call(id, event);
        }
    });
    this.div.find('.eg-button-insert').bind('click', function(){
        oGrid.insertRow();
    });
    this.div.find('.eg-button-moveup').bind('click', function(){
        oGrid.moveUp();
    });
    this.div.find('.eg-button-movedown').bind('click', function(){
        oGrid.moveDown();
    });
    this.div.find('.eg-button-delete').bind('click', function(event){
        oGrid.deleteSelectedRows(event);
            
    });
    this.div.find('.eg-button-save').bind('click', function(event){
        oGrid.save(event);
    });
    this.div.find('.eg-button-excel').bind('click', function(){
        oGrid.excel();
    });

    //controlbar margin adjust to begin of 2nd TH
    this.div.find('.eg-controlbar').each(function(){
        if($(this).css('margin-top')==='0px'){
            var cblm = parseFloat($(this).css('margin-left').replace(/px$/i, ''))
                , th1 = oGrid.thead.find('th').first()
                , th2 = th1.next()
                , th1w = th1.outerWidth()
                , th2w = (th2[0] ? th2.outerWidth() : 0)
                , th2pl = (th2[0] ? parseFloat(th2.css('padding-left').replace(/px$/i, '')) : 0)
                , th1textw = th1.find('span').outerWidth()
                , th2textw = (th2[0] ? th2.find('span').outerWidth() : 0)
                , cbw = $(this).outerWidth()
                , th1textmargin = (th1.css('text-align')==='center' 
                    ? (th1w-th1textw)/2
                    :  (th1.css('text-align')==='right' 
                        ? th1w-th1textw
                        : 0) )
                , th2textmargin = (th2[0] 
                    ? (th2.css('text-align')==='center' 
                        ? (th2w-th2textw)/2
                        :  (th2.css('text-align')==='right' 
                            ? th2w-th2textw
                            : 0) )
                    : 0
                    );
            if(cblm<th1w && cbw<th2textmargin){
                $(this).css('margin-left', th1w+th2pl+'px')
            }

        }
    })

}

eiseGrid.prototype.bindKeyPress = function ( $o ){

    var grid = this;
    $o.keydown( function( event ){

        var flagTextInput = ($o[0].nodeName.toLowerCase()=='input' && $o.attr('type')=='text');

        var $td = $o.parent('td')
            , $tr = $td.parent('tr')
            , $tbody = $td.parents('tbody').first();
        var tdClass = grid.id+'-'+grid.getFieldName($td);
        var $inpToFocus = null;
        var posToSet = 'all';

        switch(event.keyCode){
            case 37: //arrow left
                if( flagTextInput && _getCaretPos($o[0])>0 ){
                    return;
                }

                while($td = $td.prev('td')){
                    if($inpToFocus = $td.find('input[type=text], input[type=checkbox]')){
                        break;
                    }
                }

                posToSet = 'last';

                break;
            case 39: //arrow right

                if( flagTextInput && _getCaretPos($o[0])!=$o.val().length ){
                    return;
                }

                while($td = $td.next('td')){
                    if($inpToFocus = $td.find('input[type=text], input[type=checkbox]')){
                        break;
                    }
                }
                break;
            case 38: // arrow up

                if($td.hasClass('eg-ajax_dropdown'))
                    return;
                
                while($tr = $tr.prev(':visible')){
                    $td = $tr.find('td.'+tdClass);
                    if($inpToFocus = $td.find('input[type=text], input[type=checkbox]')){
                        break;
                    }
                }
                if(!$inpToFocus[0]){
                    while($tbody = $tbody.prev(':visible')){
                        $td = $tbody.find('td.'+tdClass).last();
                        if($inpToFocus = $td.find('input[type=text], input[type=checkbox]')){
                            break;
                        }
                    }
                }
                break;

            case 40: // arrow down
            case 13: // enter

                if($td.hasClass('eg-ajax_dropdown'))
                    return;

                while($tr = $tr.next(':visible')){
                    $td = $tr.find('td.'+tdClass);
                    if($inpToFocus = $td.find('input[type=text], input[type=checkbox]')){
                        break;
                    }
                }
                if(!$inpToFocus[0]){
                    while($tbody = $tbody.next(':visible')){
                        $td = $tbody.find('td.'+tdClass).first();
                        if($inpToFocus = $td.find('input[type=text], input[type=checkbox]')){
                            break;
                        }
                    }
                }
                break;

        }
        if( $inpToFocus && $inpToFocus[0]){
            _setCaretPos($inpToFocus[0], posToSet);
        }

    });
}


eiseGrid.prototype.getFieldName = function ( oField ){

    if(oField[0].dataset['field'])
        return oField[0].dataset['field'];

    var arrClasses = oField.attr("class").split(/\s+/);
    var colID = arrClasses[0].replace(this.id+"-", "");
    return colID;
}


eiseGrid.prototype.getRowID = function(oTbody){
    return oTbody.find('td input').first().val();
}

eiseGrid.prototype.newRow = function($trAfter){

    var $newTbody = this.tbodyTemplate.clone(true, true)
            .css("display", "none")
            .removeClass('eg-template')
            .addClass('eg-data');
    $newTbody.find('.eg-floating-select').remove();

    if($trAfter)
        $newTbody.insertAfter($trAfter);

    return $newTbody;
}

eiseGrid.prototype.addRow = function(oTrAfter, callback, conf){
    
    this.tableContainer.find('.eg-no-rows').css('display', 'none');
    this.tableContainer.find('.eg-spinner').css('display', 'none');
    
    var $newTbody = this.newRow(( oTrAfter 
        ? oTrAfter 
        : this.tableContainer.find('tbody').last() )
    );

    this.tbodies = this.tableContainer.find('tbody.eg-data');

    $newTbody.slideDown();
    this.recalcOrder();

    this.initRow( $newTbody );

    this.recalcAllTotals();


    this.selectRow($newTbody);
    
    if(typeof(this.addRowCallback)=='function'){
        this.addRowCallback.call(this, $newTbody);
    }
    if(typeof(callback)=='function'){
        callback.call(this, $newTbody);
    }

    //this.updateRow($newTbody);
    
    var firstInput = $($newTbody).find('input[type=text]').first()[0];
    if (typeof(firstInput)!='undefined' && !(conf && conf.noFocus) )
        firstInput.focus();
    
    return $newTbody;

}

eiseGrid.prototype.insertRow = function(callback){
    var newTr = this.addRow(this.activeRow[this.lastClickedRowIx], callback);
}

eiseGrid.prototype.selectRow = function(oTbody, event){

    var grid = this

    if(typeof(oTbody)!='undefined'){
        
        var selector = '#'+grid.id+' tbody.eg-data'
            ix = oTbody.index(selector),
            strIx = ix+'';

        if(event){
            if (event.shiftKey){

                var ixStart, ixEnd;
                if (grid.lastClickedRowIx){
                    if(grid.lastClickedRowIx < ix){
                        ixStart = grid.lastClickedRowIx;
                        ixEnd = ix;
                    } else {
                        ixEnd = grid.lastClickedRowIx;
                        ixStart = ix;
                    } 
                }
                grid.activeRow = {};
                this.tbodies.each(function(){
                    if ($(this).index(selector)>=ixStart && $(this).index(selector)<=ixEnd)
                        grid.activeRow[$(this).index(selector)+''] = $(this);
                })
            } else if (event.ctrlKey || event.metaKey)  {
                if(!grid.activeRow[strIx])
                    grid.activeRow[strIx] = oTbody;
                else 
                    grid.activeRow[strIx] = null;
            } else {
                grid.activeRow = {};
                grid.activeRow[strIx] = oTbody;
            }
        } else {
            grid.activeRow = {};
            grid.activeRow[strIx] = oTbody;
        }


        grid.lastClickedRowIx = ix;

        grid.selectedRowIx = []
        grid.selectedRowIx = $.map(grid.activeRow, function(v, i){
            return parseInt(i)
        })
        
    } else {
        grid.activeRow = {};
        grid.lastClickedRowIx = null;
        grid.selectedRowIx = []
    }

    grid.tbodies.each(function(){
        $(this).removeClass('eg-selected');
    })

    $.each(grid.activeRow, function(){
        $(this).addClass('eg-selected');
    })

}

eiseGrid.prototype.deleteRow = function(oTr, callback){
    
    var oGrid = this,
        goneID = this.getRowID(oTr);

    if (goneID) {
        var inpDel = oGrid.div.find('#inp_'+this.id+'_deleted');
        inpDel.val(inpDel.val()+(inpDel.val()!="" ?  "|" : "")+goneID);
        this.goneIDs.push(goneID);
    }

    oTr.remove();
    delete oTr;

    oGrid.tbodies = this.tableContainer.find('.eg-data');

    oGrid.recalcOrder();
    $.each(oGrid.conf.fields, function(field, props){
        if (props.totals!=undefined) oGrid.recalcTotals(field);
    });
    
    
    if (oGrid.tbodies.length==0)
        this.tableContainer.find('.eg-no-rows').css('display', 'table-row-group');

    if (typeof this.onDeleteCallback === 'function')
        this.onDeleteCallback(goneID);

    if(typeof callback === 'function')
        callback.call(oGrid, goneID);

}

eiseGrid.prototype.deleteSelectedRows = function(event, callback){
    var grid = this;
    var allowDelete = true;

    if(typeof grid.beforeDeleteCallback === 'function'){
        if(!grid.beforeDeleteCallback (event))
            return false;
    }

    $.each(grid.activeRow, function(ix, $tr){
        if(!$tr)
            return true;

        if(typeof callback === 'function'){
            allowDelete = callback.call(grid, $tr);
        }

        if(allowDelete && !$tr.hasClass('eg-row-disabled'))
            grid.deleteRow($tr);
    });

    if(typeof grid.afterDeleteCallback === 'function'){
        grid.afterDeleteCallback (event);
    }
}

eiseGrid.prototype.updateRow = function(oTr){
    
    oTr.find("input")[1].value="1";
    oTr.addClass('eg-updated');
}

eiseGrid.prototype.recalcOrder = function(){
    var oThis = this;
    var iCounter = 1;

    this.tbodies = this.tableContainer.find('tbody.eg-data');

    this.tbodies.find('.eg-order').each(function (){

        $(this).find('div span').html(iCounter).parent('div').prev('input').val(iCounter);
        //console.log(iCounter, $(this).find('div span').html())
        iCounter++;
    })
}

eiseGrid.prototype.moveUp = function(flagDontUpdateRows){

    var grid = this;

    $.each(grid.activeRow, function(ix, $rw){
        if ($rw){
            if ($rw.prev().hasClass('eg-template'))
                return false; // break, nothing to move, upper limit reached 
            $rw.insertBefore($rw.prev());
            if(!flagDontUpdateRows){
                grid.updateRow($rw);
                grid.updateRow($rw.next());
            }
            
        }
    });

    this.recalcOrder();

}
eiseGrid.prototype.moveDown = function(flagDontUpdateRows){

    var grid = this;

    $.each(grid.activeRow, function(ix, $rw){
        
        if ($rw.next().html()==null)
            return false; // break, nothing to move, upper limit reached 
        $rw.insertAfter($rw.next());
        if(!flagDontUpdateRows){
            grid.updateRow($rw);
            grid.updateRow($rw.prev());
        }

    })

    this.recalcOrder();

}

eiseGrid.prototype.recalcTotals = function(field, flagReturn){
    var oGrid = this;
    var nTotals = 0.0;
    var nCount = 0;
    var nValue = 0.0;
    oGrid.tableContainer.find('td.'+this.id+'-'+field+' input').each(function(){
        var strVal = $(this).val()
            .replace(new RegExp("\\"+oGrid.conf.decimalSeparator, "g"), '.')
            .replace(new RegExp("\\"+oGrid.conf.thousandsSeparator, "g"), '');
        var nVal = parseFloat(strVal);
        if (!isNaN(nVal)) {
            nTotals += nVal;
            nCount++;
        }
    });
    switch(String(this.conf.fields[field].totals).toLowerCase()){
        case "avg":
            nValue = nTotals/nCount;
            break;
        case "sum":
        default:
            nValue = nTotals;
            break;
        
    }
    
    var decimalPlaces = 2;
    switch(this.conf.fields[field].type){
        case "int":
        case "integer":
            decimalPlaces = 0;
            break;
        default:
            decimalPlaces  = this.conf.fields[field].decimalPlaces!=undefined ? this.conf.fields[field].decimalPlaces : this.conf.decimalPlaces;
            break;
    }

    if(flagReturn)
        return nValue;
    
    this.tfoot.find('.'+this.id+'-'+field+' div').html(
        this.number_format(nValue, decimalPlaces)
    );
}

eiseGrid.prototype.totals  = function(field){
    return this.recalcTotals(field, true);
}

eiseGrid.prototype.recalcAllTotals = function(){

    var oGrid = this;

    $.each(oGrid.conf.fields, function(field, props){ //bind totals recalculation to totals columns

        if (props.totals==undefined)
            return true; // continue
        oGrid.recalcTotals(field);
    })
}

eiseGrid.prototype.number_format = function(arg, decimalPlaces){
/* adapted by Ilya Eliseev e-ise.com
 Made by Mathias Bynens <http://mathiasbynens.be/> */
    var minus = (parseFloat(arg)<0 ? '-' : '');

    var a = arg;
    var b = decimalPlaces;
    var c = this.conf.decimalSeparator;
    var d = this.conf.thousandsSeparator;
    
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

eiseGrid.prototype.change = function(strFields, fn){

    var fields = strFields.split(/[^a-z0-9\_]+/i),
        oGrid = this;

    $.each(oGrid.conf.fields, function(fld){

        for(var i=0; i<fields.length; i++){

            if(fld===fields[i]){

                if(!oGrid.onChange[fld])
                    oGrid.onChange[fld] = [];

                oGrid.onChange[fld].push(fn);
                /* deleted to prevent double binding
                var sel = '.eg-data input[name="'+fld+'[]"]';
                oGrid.tableContainer.find(sel).bind('change', function(){
                    fn.call(oGrid, $(this).parents('tbody').first(), $(this));
                })
                */
                return true; //break
            }
        }
    });

    return;
}

eiseGrid.prototype.value = function(oTr, strFieldName, val, text){

    if (!this.conf.fields[strFieldName]){
        $.error( 'Field ' +  strFieldName + ' does not exist in eiseGrid ' + this.id );
    }
        
    
    var strType = this.conf.fields[strFieldName].type;
    var strTitle = this.conf.fields[strFieldName].title;

    var oGrid = this;
    
    if (val==undefined){
        var inpSel = 'input[name="'+strFieldName+'[]"]',
            inp = oTr.find(inpSel).first(),
            strValue = inp.val(); 
        switch(strType){
            case "integer":
            case "int":
            case "numeric":
            case "real":
            case "double":
            case "money":
               strValue = strValue
                .replace(new RegExp("\\"+this.conf.decimalSeparator, "g"), '.')
                .replace(new RegExp("\\"+this.conf.thousandsSeparator, "g"), '');
                return parseFloat(strValue);
            default:
                return strValue;
        }
    } else {
        var strValue = val;
        switch(strType){
            case "integer":
            case "int": 
                strValue = (isNaN(strValue) ? '' : this.number_format(strValue, 0));
                break;
            case "numeric":
            case "real":
            case "double":
            case "money":
                if(typeof(strValue)=='number'){
                    strValue = isNaN(strValue) 
                        ? ''
                        : this.number_format(strValue, 
                            this.conf.fields[strFieldName].decimalPlaces!=undefined ? this.conf.fields[strFieldName].decimalPlaces : this.conf.decimalPlaces
                            )

                }
                break;
            default:
                break;
        }
        oInp = oTr.find('input[name="'+strFieldName+'[]"]').first();
        oInp.val(strValue);
        if (strTitle && oInp.next()[0]!=undefined){
            switch(strType){
                case "checkbox":
                case "boolean":
                    if(strValue=="1"){
                        oInp.next().attr("checked", "checked");
                    } else 
                        oInp.next().removeAttr("checked");
                    return;
                case 'combobox':
                    var oSelectSelector = '#select-'+(oInp.attr('name').replace(/(\[\S+\]){0,1}\[\]/, ''))
                        , oSelect = oGrid.tbodyTemplate.find(oSelectSelector)[0]
                        , options = oSelect.options;
                    for (var i = options.length - 1; i >= 0; i--) {
                        if(options[i].value == strValue){
                            text = options[i].text
                            break;
                        }
                    };
                    
                            
                default:
                    if (oInp.next()[0].tagName=="INPUT")
                        oInp.next().val((text!=undefined ? text : strValue));
                    else 
                        oInp.next().html((text!=undefined ? text : strValue));
                    break;
            }
        }
        this.recalcTotals(strFieldName);
    }
}

eiseGrid.prototype.text = function(oTr, strFieldName, text){
    if(this.conf.fields[strFieldName].static !=undefined
        || this.conf.fields[strFieldName].disabled !=undefined
        || (this.conf.fields[strFieldName].href !=undefined && this.value(oTr, strFieldName)!="")
        ){
            return (oTr.find('.'+this.id+'-'+strFieldName)[0]
                    ? oTr.find('.'+this.id+'-'+strFieldName).text()
                    : (oTr.find('input[name="'+strFieldName+'_text[]"]')[0]
                        ? oTr.find('input[name="'+strFieldName+'_text[]"]').val()
                        : oTr.find('input[name="'+strFieldName+'[]"]').val())
                    );
        } else {
            switch (this.conf.fields[strFieldName].type){
                case "order":
                case "textarea":
                    return oTr.find('.'+this.id+'-'+strFieldName).text();
                case "text":
                case "boolean":
                case "checkbox":
                    return this.value(oTr, strFieldName);
                case "combobox":
                case "select":
                case "ajax_dropdown":
                    return (oTr.find('input[name="'+strFieldName+'_text[]"]')[0]
                        ? oTr.find('input[name="'+strFieldName+'_text[]"]').val()
                        : oTr.find('input[name="'+strFieldName+'[]"]').val());
                default: 
                    return oTr.find('input[name="'+strFieldName+'[]"]').val();
            }
            
        }
}

eiseGrid.prototype.focus = function(oTr, strFieldName){
    oTr.find('.'+this.id+'-'+strFieldName+' input[type=text]').focus().select();
}

eiseGrid.prototype.verifyInput = function (oTr, strFieldName) {
    
    var selector = '.'+this.id+'-'+strFieldName+' input[name="'+strFieldName+'[]"]';
    var $inp = oTr.find(selector).first(),
        strValue = $inp.val();
    if (strValue!=undefined){ //input mask compliance

        if(this.conf.validators){
            var validator = this.conf.validators[strFieldName];
            if(validator)
                return validator.call($inp[0], strValue);

        }
        
        switch (this.conf.fields[strFieldName].type){
            case "money":
            case "numeric":
            case "real":
            case "float":
            case "double":
                var nValue = parseFloat(strValue
                    .replace(new RegExp("\\"+this.conf.decimalSeparator, "g"), '.')
                    .replace(new RegExp("\\"+this.conf.thousandsSeparator, "g"), ''));
                if (strValue!="" && isNaN(nValue)){
                    alert(this.conf.fields[strFieldName].title+" should be numeric");
                    this.focus(oTr, strFieldName);
                    return false;
                }
                break;
            case 'date':
            case 'time':
            case 'datetime':
                 
                var strRegExDate = this.conf.dateFormat
                    .replace(new RegExp('\\.', "g"), "\\.")
                    .replace(new RegExp("\\/", "g"), "\\/")
                    .replace("d", "[0-9]{1,2}")
                    .replace("m", "[0-9]{1,2}")
                    .replace("Y", "[0-9]{4}")
                    .replace("y", "[0-9]{1,2}");
                var strRegExTime = this.conf.timeFormat
                    .replace(new RegExp("\.", "g"), "\\.")
                    .replace(new RegExp("\:", "g"), "\\:")
                    .replace(new RegExp("\/", "g"), "\\/")
                    .replace("h", "[0-9]{1,2}")
                    .replace("i", "[0-9]{1,2}")
                    .replace("s", "[0-9]{1,2}");
                
                var strRegEx = "^"+(this.conf.fields[strFieldName].type.match(/date/) ? strRegExDate : "")+
                    (this.conf.fields[strFieldName].type=="datetime" ? " " : "")+
                    (this.conf.fields[strFieldName].type.match(/time/) ? strRegExTime : "")+"$";
                
                if (strValue!="" && strValue.match(new RegExp(strRegEx))==null){
                    alert ("Field '"+this.conf.fields[strFieldName].type+"' should contain date value formatted as "+this.conf.dateFormat+".");
                    this.focus(oTr, strFieldName);
                    return false;
                }
                break;
            default:
                 break;
         }
    }
    
    return true;
    
}

eiseGrid.prototype.verify = function( options ){
    
    var oGrid = this;
    var flagError = false;

    $.extend(oGrid.conf, options);
    
    this.tableContainer.find('.eg-data').each(function(){ // y-iterations
        var oTr = $(this);
        $.each(oGrid.conf.fields, function(strFieldName, col){ // x-itearations
            
            if (col.static!=undefined || col.disabled!=undefined){ //skip readonly fields{
                return true; //continue
            }
                
                
            
            if (col.mandatory != undefined){ //mandatoriness
                if (oGrid.value(oTr, strFieldName)==""){
                    alert("Field "+col.title+" is mandatory");
                    oGrid.focus(oTr, strFieldName);
                    flagError = true;
                    return false; //break
                }
            }
            
            if (!oGrid.verifyInput(oTr, strFieldName)){
                flagError = true;
                return false; //break
            }
                
        }) 
        if(flagError)
            return false;
    })
    
    return !flagError;

}

eiseGrid.prototype.save = function(event){

    var grid = this, 
        oForm = $('#form_eg_'+this.id)

    if(!oForm[0]){
        this.div.wrap('<form action="'+this.conf.urlToSubmit+'" id="form_eg_'+this.id+'" method="POST" />');
        oForm = $('#form_eg_'+this.id);
        $.each(this.conf.extraInputs, function(name, value){
            oForm.append('<input type="hidden" name="'+name+'" value="'+value+'">');
        });
        oForm = $('#form_eg_'+this.id);
    }

    if(typeof grid.onSaveCallback === 'function'){
        if(!grid.onSaveCallback.call(oForm[0], event))
            return false;
    }
    
    if (!this.verify())
        return false;

    if(typeof onSubmit === 'function'){
        oForm.submit(onSubmit);
    } else {
        oForm.find('#inp_'+this.id+'_config').remove();
        oForm.submit();
    }
    
}


eiseGrid.prototype.sliceByTab3d = function(ID){
    document.cookie = this.conf.Tabs3DCookieName+'='+ID;

    var grid = this;

    this.selectedTab = ID;
    $.each(this.arrTabs, function(ix, tab){
        if(tab==ID){
            this.selectedTabIx = ix;
            return false;//break
        }
    })

    //eg_3d eg_3d_20DC
    this.tableContainer.find('td .eg-3d').css('display', 'none');
    this.tableContainer.find('td .eg-3d-'+ID).css('display', 'block');

}

eiseGrid.prototype.height = function(nHeight, callback){

    var grid = this;

    var hBefore = this.div.outerHeight(),
        hBodies = 0;

    $.each(grid.tbodies, function(ix, tbody){
        hBodies += $(tbody).outerHeight();
    })

    if(!nHeight)
        return hBefore;

    if(typeof nHeight==='object'){
        var obj = nHeight
            , offsetTop = grid.div.offset().top
            , margin = offsetTop - grid.div.parents().first().offset().top;
        nHeight = (obj===window 
            ? window.innerHeight - $('.ei-action-menu').outerHeight(true) 
            : $(obj).outerHeight(true)) - offsetTop - 2*margin;
    }

    if( nHeight < (hBodies/grid.tbodies.length)*3 ) // if nHeight is not specified or height is less than height of 3 rows, we do nothing
        return hBefore;

    

    if(typeof($.fn.eiseTableSizer)=='undefined'){
        $.getScript(this.conf.eiseIntraRelativePath+'js/eiseTableSizer.jQuery.js', function(data, textStatus, jqxhr){

            grid.height(nHeight, callback);

        });

    } else {

        grid.div.find('table').first().eiseTableSizer({height: nHeight
            , class: 'eg-container'
            , callback: (typeof callback==='function' ? callback : null)
        });
        grid.tableContainer = grid.div.find('table.eg-container');

    }

    return hBefore;


}

eiseGrid.prototype.reset = function(fn){
    
    var oGrid = this;

    this.tableContainer.find('tbody.eg-data').each(function(){ // delete visible rows
        oGrid.deleteRow($(this));
    });
    this.tableContainer.find('tbody.eg-no-rows').css('display', 'table-row-group');

    if (typeof(fn)!='undefined'){
        fn.call(this);
    }
}

eiseGrid.prototype.spinner = function(arg){
    
    var oGrid = this;

    if(arg!==false){

        this.tableContainer.find('.eg-no-rows').css('display', 'none');
        this.tableContainer.find('.eg-spinner').css('display', 'table-row-group');

        if (typeof arg ==='function'){
            fn.call( this.div );
        }

    } else {

        this.tableContainer.find('.eg-spinner').css('display', 'none');
        if(this.tableContainer.find('.eg-data').length==0)
            this.tableContainer.find('.eg-no-rows').css('display', 'table-row-group');
        

    }
}

eiseGrid.prototype.fill = function(data, fn){

    var oGrid = this,
        rowsAdded = [],
        __getHREF = function(href, data){
            $.each(data, function(field, value){
                href = href.replace('['+field+']', value);
            })
            return href;
        },
        __doHREF = function(props, $parent, href){
            var $elem = $('<a>').appendTo($parent);
            $elem[0].href = href;
            if(props.target)
                $elem[0].target = props.target
            return $elem;
        };



    this.tableContainer.find('.eg-spinner').css('display', 'none');

    if ((!data || data.length==0) && this.tableContainer.find('.eg-data').length==0){
        
        this.tableContainer.find('.eg-no-rows').css('display', 'table-row-group');

    } else {

        var $trAfter = oGrid.tableContainer.find('tbody').last();

        oGrid.tableContainer.find('.eg-no-rows').css('display', 'none');

        $.each(data, function(ix, row){

            var $tr = oGrid.newRow($trAfter)
                .css('display', 'table-row-group');

            $.each(oGrid.conf.fields, function(field, props){

                var $td = $tr.find('td[data-field="'+field+'"]'),
                    $div = $td.find('div'),
                    $inp = $tr.find('input[name="'+field+'[]"]'),
                    $inpText = $td.find('input[type="text"]');

                if(!$td[0] && !$inp[0])
                    return true; // continue

                if( props.type == 'order' && !row[field] ){
                    var ord = ($trAfter.hasClass('eg-data')
                            ? parseInt($trAfter.find('.eg-order').text().replace(/[^0-9]+/gi, '')) 
                            : 0)+1;
                    if($div[0])
                        $div.text(ord);
                    if($inp[0])
                        $inp.val(ord);
                }


                if ( !row[field] )
                    return true; // continue

                var val = (typeof(row[field])=='object' ? row[field].v : row[field]),
                    text = (row[field].t 
                        ? row[field].t 
                        : (typeof row[field+'_text'] !== 'undefined'
                            ? row[field+'_text']
                            : (props.type=='combobox' && props.source && props.source[val]
                                ? props.source[val]
                                : val)
                        )),
                    href = (row[field].h
                        ? row[field].h
                        : (row[field+'_href'] 
                            ? row[field+'_href']
                            : (props.href 
                                ? __getHREF(props.href, row)
                                : ''
                                )
                            )
                        ),
                    theClass = (row[field].c
                        ? row[field].c
                        : row[field+'_class'])
                    ;

                if($inp[0])
                    $inp.val(val);
                if(theClass){
                    $.each(theClass.split(/\s+/), function(ix, cls){
                        $td.addClass(cls)
                    })
                }

                switch(props.type){
                    case 'boolean':
                    case 'checkbox':
                        if(val==1)
                            $td.find('input[type=checkbox]')[0].checked = true;
                        break;
                    case 'date':
                    case 'datetime':
                    case 'time':
                        val = text = $('body').eiseIntra('formatDate', val, props.type);
                    default:
                        var textInput = $td.find('input[type=text]')[0];
                        if(textInput){
                            if(!href)
                                $(textInput).val(text);
                            else {
                                $(textInput).remove();
                                __doHREF(props, $td, href).text(text)
                            }

                        } else {         
                            var $elem = $div;
                            if(href){
                                $elem = __doHREF(props, $div, href)
                            }
                            $elem.text(text);
                        }
                        break;
                }

            });

            $trAfter = $tr;

            oGrid.initRow( $tr );

            rowsAdded.push($tr);

        });
    
        oGrid.selectRow(); //reset row selection caused by addRow()

    }

    $.each(oGrid.conf.fields, function(field, props){ // recalc totals, if any
        if (props.totals!=undefined) oGrid.recalcTotals(field);
    });

    oGrid.trFirst = oGrid.tableContainer.find('.eg-data').first();

    if(typeof fn === 'function')
        fn.call(oGrid)

    return rowsAdded;

}

eiseGrid.prototype.getData = function(rows, cols, colsToExclude){

    var grid = this,
        retVal = [],
        oData = {}

    rows = (rows ? rows : grid.tableContainer.find('tbody.eg-data'))
    cols = (cols ? cols : grid.conf.fieldIndex)
    colsToExclude = (colsToExclude ? colsToExclude : [])

    $.each(rows, function(ix, $row){
        oData = {}
        for(var i=0;i<cols.length;i++){
            var field = cols[i],
                oField = grid.conf.fields[field];
            if(colsToExclude.indexOf(field)!==-1)
                continue;
            oData[field] = grid.value($row, field)
            if(['combobox', 'select', 'ajax_dropdown'].indexOf(oField.type)!==-1){
                oData[field+'_text'] = grid.text($row, field)
            }
        }
        retVal.push(oData)
    })

    return retVal
}

eiseGrid.prototype.copyRows = function(rowsToCopy, fn){

    var grid = this;

    if(!rowsToCopy)
        rowsToCopy = grid.activeRow

    if (Object.keys(rowsToCopy).length==0)
        return;

    var colsToExclude = []

    $.each(grid.conf.fields, function(field, opts){
        if(['row_id', 'order'].indexOf(opts.type)!==-1)
            colsToExclude.push(field)
    })

    data = grid.getData(rowsToCopy, null, colsToExclude);

    var rows = grid.fill(data);
    for (var i = rows.length - 1; i >= 0; i--) {
        grid.updateRow(rows[i]);
    };

    if(typeof fn === 'function')
        fn.call(oGrid)
}

eiseGrid.prototype.excel = function(options){

    var grid = this;

    options = $.extend(grid.conf, options)

    if(!grid.sa){
        if(typeof saveAs==='undefined'){
            $.getScript(this.conf.eiseIntraRelativePath+'lib/FileSaver.js/FileSaver.js', function(data, textStatus, jqxhr){

                grid.sa = saveAs;
                grid.excel();

            });
            return;
        } else {
            grid.sa = saveAs;
        }
    }

    try {
        var isSupported = !!new Blob;
    } catch (e) {}

    if(!isSupported){
        alert("You browser doesn't support file saving");
        return;
    }

    var strTH = '', rows = '';

    grid.div.find('.eg-data').each(function(ix){
        var $tr = $(this);
        rows += '<Row>\n';
        for(var i=0;i<grid.conf.fieldIndex.length;i++){
            
            if(ix===0){
                var type = grid.conf.fields[grid.conf.fieldIndex[i]].type;
                grid.conf.fields[grid.conf.fieldIndex[i]].typeExcel = (
                    ['order', 'money', 'checkbox', 'number', 'numeric', 'int'].indexOf(type)!==-1
                    ? 'Number'
                    : (['date', 'datetime'].indexOf(type)!==-1
                        ? 'DateTime'
                        : 'String'
                        )
                    );
                strTH += '<Cell><Data ss:Type="String">'+grid.conf.fields[grid.conf.fieldIndex[i]].title+'</Data></Cell>\n';
            }
            var val = grid.value($tr, grid.conf.fieldIndex[i]),
                isDateTime = grid.conf.fields[grid.conf.fieldIndex[i]].typeExcel=='DateTime' && val.match(/^([0-9]{4}\-[0-9]{2}\-[0-9]{2})(T[0-9]{2}\:[0-9]{2}(\:[0-9]{2}\:[0-9]*){0,1}){0,1}$/),
                typeExcel = (grid.conf.fields[grid.conf.fieldIndex[i]].typeExcel=='DateTime' 
                    ? (isDateTime ? 'DateTime' : 'String')
                    : grid.conf.fields[grid.conf.fieldIndex[i]].typeExcel);
            rows += '<Cell'+(isDateTime
                    ? ' ss:StyleID="s22"'
                    : ''
                )+'><Data ss:Type="'+typeExcel+'">'+val+'</Data></Cell>\n';
        
                
        }
        rows += '</Row>\n';
    })
    if(!rows){
        alert('Table is empty')
        return;
    }

    strTH = '<Row ss:StyleID="Hdr">\n'+strTH+'</Row>\n';

    var strSheet = '<?xml version="1.0" encoding="utf-8"?>\n<?mso-application progid="Excel.Sheet"?>\n'
        strSheet += '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:html="http://www.w3.org/TR/REC-html40">\n';
        strSheet += '<Styles><Style ss:ID="Hdr"><Font ss:Bold="1"/></Style>';
        strSheet += '<Style ss:ID="s22"><NumberFormat ss:Format="Short Date"/></Style>';
        strSheet += '</Styles>\n';
        strSheet += '\n<Worksheet ss:Name="'+options.excelSheetName+'">\n<Table>\n';
        strSheet += strTH;
        strSheet += rows;
        strSheet += "</Table>\n</Worksheet>\n";
        strSheet += "</Workbook>";
    var b = new Blob([strSheet], {type:'application/x-msexcel;charset=utf-8;'});
    grid.sa(b, options.excelFileName);

}

eiseGrid.prototype.sort = function( field, fnCallback ){
    
    var grid = this,
        $th = grid.thead.find('th[data-field="'+field+'"]'),
        orderNew = -1 * (this.conf.fields[field].order ? this.conf.fields[field].order : -1);

    if($th[0]){
        setTimeout(function(){
            $th.addClass('eg-sortable')
                .addClass('eg-wait');    
        }, 10)
    }

    grid.doSort( field, orderNew, function( order ){

        grid.thead.find('th').removeClass('eg-asc eg-desc')

        if($th[0]){
            window.setTimeout(function(){
                $th
                    .removeClass('eg-wait')
                    .removeClass('eg-'+(order<0 ? 'asc' : 'desc'))
                    .addClass('eg-'+(order>0 ? 'asc' : 'desc'));
            }, 10)
        }
            

        if(typeof fnCallback == 'function'){
            fnCallback.call(grid, order)
        }
    });

}

eiseGrid.prototype.doSort = function( field, order, fnCallback ){
    var grid = this,
        tbodies = grid.tbodies
        type = grid.conf.fields[field].type;

    tbodies.sort( function(tbodyA, tbodyB){ 
        return grid._sortFunction([tbodyA, tbodyB], field, order, type) 
    } );

    for (var i = tbodies.length - 1; i >= 0; i--) {
        if(i>0)
            $(tbodies[i]).before(tbodies[i-1]);
    };

    grid.tbodies = grid.tableContainer.find('tbody.eg-data');

    grid.conf.fields[field].order = order;

    fnCallback.call(grid, order);

}

eiseGrid.prototype._sortFunction = function(tbodies, field, order, type){

    var grid = this,
        values = [];

    for (var i = tbodies.length - 1; i >= 0; i--) {
        var $tbody = $(tbodies[i]);

        switch (type){
            case 'ajax_dropdown':
            case 'combobox':
            case 'select':
                values[i] = grid.text($tbody, field);
            default:
                values[i] = grid.value($tbody, field);        
        }        

    };

    return order * (values[0] > values[1] ? 1 : -1); 

}

var methods = {
init: function( conf ) {

    this.each(function() {
        var data, dataId, conf_,
                $this = $(this);

        $this.eiseGrid('conf', conf);
        data = $this.data('eiseGrid') || {};
        conf_ = data.conf;

        // If the plugin hasn't been initialized yet
        if ( !data.eiseGrid ) {
            dataId = +new Date;

            data = {
                eiseGrid_data: true
                , conf: conf_
                , id: dataId
                , eiseGrid : new eiseGrid($this)
            };

            // create element and append to body
            var $eiseGrid_data = $('<div />', {
                'class': 'eiseGrid_data'
                , 'id': this.id+'_data'
            }).appendTo( 'body' );

            // Associate created element with invoking element
            $eiseGrid_data.data( 'eiseGrid', {target: $this, id: dataId} );
            // And vice versa
            data.eiseGrid_data = $eiseGrid_data;

            $this.data('eiseGrid', data);
        } // !data.eiseGrid

        if(typeof(data.conf.onDblClick)=='function'){
            data.eiseGrid.dblclickCallback = data.conf.onDblClick;    
        }
        if(typeof(data.conf.onAddRow)=='function'){
            data.eiseGrid.addRowCallback = data.conf.onAddRow;    
        }
        

    });

    return this;
},
destroy: function( ) {

    this.each(function() {

        var $this = $(this),
                data = $this.data( 'eiseGrid' );

        // Remove created elements, unbind namespaced events, and remove data
        $(document).unbind( '.eiseGrid_data' );
        data.eiseGrid.remove();
        $this.unbind( '.eiseGrid_data' )
        .removeData( 'eiseGrid_data' );

    });

    return this;
},
conf: function( conf ) {

    this.each(function() {
        var $this = $(this),
            data = $this.data( 'eiseGrid' ) || {},
            conf_ = data.conf || {};

        // deep extend (merge) default settings, per-call conf, and conf set with:
        // html10 data-eiseGrid conf JSON and $('selector').eiseGrid( 'conf', {} );
        conf_ = $.extend( true, {}, $.fn.eiseGrid.defaults, conf_, conf || {} );
        data.conf = conf_;
        $.data( this, 'eiseGrid', data );
    });

    return this;
},
addRow: function ($trAfter, callback, conf){
    //Adds a row after specified trAfter row. If not set, adds a row to the end of the grid.
    this.each(function(){
        var grid = $(this).data('eiseGrid').eiseGrid;
        grid.addRow($trAfter, callback, conf);

    });
    return this;

}, 
selectRow: function ($tr, event){
    //Selects a row specified by tr parameter.
    this.each(function(){
        var grid = $(this).data('eiseGrid').eiseGrid;
        grid.selectRow($tr, event);

    });
    return this;
}, 
copyRows: function(rowsToCopy){
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    grid.copyRows(rowsToCopy);
    return this;
},
getSelectedRow: function (){
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    var $lastSelectedRow = grid.activeRow[grid.lastClickedRowIx];
    return $lastSelectedRow;
}, 
getSelectedRows: function (){
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    return grid.activeRow;
}, 
getSelectedRowID: function ($tr){
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    var $lastSelectedRow = grid.activeRow[grid.lastClickedRowIx];
    if(!$lastSelectedRow)
        return null;
    else 
        return grid.getRowID($lastSelectedRow);
},
getSelectedRowIDs: function ($tr){
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    var arrRet = [];

    var i=0;
    $.each(grid.activeRow, function(ix, $tr){
        if($tr){
            arrRet[i] = grid.getRowID($tr);
            i++;
        }
    });

    return arrRet; 
},

deleteRow: function ($tr, callback){
    //Removes a row specified by tr parameter. If not set, removes selected row
    this.each(function(){
        var grid = $(this).data('eiseGrid').eiseGrid;
        grid.deleteRow($tr, callback);

    });
    return this;
},

deleteSelectedRows: function(event, callback){
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    return grid.deleteSelectedRows(event, callback);
},

updateRow: function ($tr){
    //It marks specified row as updated
    this.each(function(){
        var grid = $(this).data('eiseGrid').eiseGrid;
        grid.updateRow($tr);

    });
    return this;

}, 
recalcOrder: function(){
    //recalculates row order since last changed row
    this.each(function(){
        var grid = $(this).data('eiseGrid').eiseGrid;
        grid.recalcOrder();

    });
    return this;
},

moveUp: function(flagDontUpdateRows){
    //Moves selected row up by 1 step, if possible
    this.each(function(){
        var grid = $(this).data('eiseGrid').eiseGrid;
        grid.moveUp(flagDontUpdateRows);

    });
    return this;
},

moveDown: function(flagDontUpdateRows){
    //Moves selected row down by 1 step, if possible
    this.each(function(){
        var grid = $(this).data('eiseGrid').eiseGrid;
        grid.moveDown(flagDontUpdateRows);

    });
    return this;
},

sliceByTab3d: function(ID){ 
    //brings data that correspond to tab ID to the front
    this.each(function(){
        var grid = $(this).data('eiseGrid').eiseGrid;
        grid.sliceByTab3d(ID);
    });
    return this;
},

recalcTotals: function (strField){
    //Recalculates totals for given field.
    this.each(function(){
        var grid = $(this).data('eiseGrid').eiseGrid;
        grid.recalcTotals(strField);

    });
    return this;
},

totals: function(strField){
    
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    return grid.totals(strField);

},

change:  function(strFields, callback){
    //Assigns “change” event callback for fields enlisted in strFields parameter.
    this.each(function(){

        var grid = $(this).data('eiseGrid').eiseGrid;

        grid.change(strFields, callback);

    });
    return this;
},

value: function ($tr, strField, value, text){
    //Sets or gets value for field strField in specified row, if there’s a complex field 
    //(combobox, ajax_dropdown), it can also set text representation of data.
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    return grid.value($tr, strField, value, text);
},

text: function($tr, strField, text) {
    //Returns text representation of data for field strField in specified row tr.
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    return grid.text($tr, strField, text);
},

focus: function($tr, strField){
    //Sets focus to field strField in specified row tr.
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    grid.focus($tr, strField);
    return this;
},

validateInput: function ($tr, strField){
    //Validates data for field strField in row tr. Returns true if valid.
    this.each(function(){

        var grid = $(this).data('eiseGrid').eiseGrid;

        grid.verifyInput($tr, strField);

    });
    return this;
},

validate: function( options ){
    //Validates entire contents of eiseGrids matching selectors. Returns true if all data in all grids is valid
    var flagOK = true;
    this.each(function(){

        var grid = $(this).data('eiseGrid').eiseGrid;
        flagOK = flagOK && grid.verify( options );

    });

    return flagOK;
},

save: function(onSubmit){
    //Wraps whole grid with FORM tag and submits it to script specified in settings.
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    grid.save(onSubmit);
    return this;
},

height: function(nHeight, callback){
    //Wraps whole grid with FORM tag and submits it to script specified in settings.
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    return grid.height(nHeight, callback);
},

dblclick: function(dblclickCallback){
    if(!$(this[0]).data('eiseGrid'))
        return this;
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    grid.dblclickCallback = dblclickCallback;
    grid.tbodies.bind('dblclick', function(event){
        dblclickCallback.call( $(this), grid.getRowID($(this)), event );
    })
    return this;
},

beforeDelete: function(callback){
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    grid.beforeDeleteCallback = callback;
    return this;
},


afterDelete: function(callback){
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    grid.afterDeleteCallback = callback;
    return this;
},

onDelete: function(callback){
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    grid.onDeleteCallback = callback;
    return this;
},


beforeSave: function(onSaveCallback){
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    grid.onSaveCallback = onSaveCallback;
    return this;
},


getGridObject: function(){
    return $(this[0]).data('eiseGrid').eiseGrid;
},

reset: function(fn){
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    grid.reset(fn);
    return this;
},

spinner: function(arg){
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    grid.spinner(arg);
    return this;
},

fill: function(data, fn){
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    grid.fill(data, fn);
    return this;
},

toggleMultiLine: function(fieldSequence){
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    grid.toggleMultiLine(fieldSequence);
    return this;
},

/**
 * eiseGrid('dragNDrop', function(event){} ) method allows drag-n-drop operations on eiseGrid.
 * It shows the target over eiseGrid when user start drag over document body. When user finishes drag it removes the target.
 * Callback function is bound to 'drop' event over eiseGrid elements. Before the call grid shows the spinner.
 * Callback is called in the context of eiseGrid object, not the main <DIV> or jQuery object.
 * After you handle the updload with XHR or whatever you can call eiseGrid('fill', [{...}, {...}] ) method to add some rows to the grid.
 * 
 * @param function fnCallback(event) - the function that executes right after 'drop' event occured, target is hidden and spinner is shown. Context is current eiseGrid object.
 * 
 * @return jQuery
 */
dragNDrop: function(fnCallback){

    var grids = this;

    $('body').bind('drop', function(event) {
        event.preventDefault();
    }).bind('dragover', function(event) {
        grids.each(function(){ $(this).addClass('eg-ready-to-drop') });  
        return false;
    }).bind("dragleave", function(event) {
        grids.each(function(){ $(this).removeClass('eg-ready-to-drop') });  
        return false;
    });

    grids.each(function(){

        var grid = $(this).data('eiseGrid').eiseGrid;

        grid.div.find('*')
            .bind('drop', function(event) {
                event.preventDefault(); 
                event.stopImmediatePropagation();
                grid.div.removeClass('eg-ready-to-drop');
                grid.spinner();
                if(typeof fnCallback === 'function'){
                    fnCallback.call(grid, event);
                }
            })
            .bind('dragover', function(event){  })
            .bind('dragleave', function(event){ event.preventDefault(); event.stopImmediatePropagation(); })
    });

    return this;

},

/**
 * This method helps to struggle with large amount of <input> elements and PHP limits to handle them. This limit is set in max_input_vars php.ini setting and it is 1000 by default. eiseGrid('disableUnchanged') disables inputs in the rows that wasn't changed so browser doesn't include its contents into POST. Keep your php.ini safe, call this method before your main form submits.
 *
 * @return jQuery
 */
disableUnchanged: function(){

    this.each(function(){

        var grid = $(this).data('eiseGrid').eiseGrid,
            egData = grid.tableContainer.find('.eg-data');

        egData.each(function(){
            if(!$(this).hasClass('eg-updated'))
                $(this).find('input,select,button').prop('disabled', true);
        })


    })

    return this;
},

excel: function(options){
    var grid = $(this[0]).data('eiseGrid').eiseGrid;
    grid.excel(options);
    return this;
},

};



var protoSlice = Array.prototype.slice;

$.fn.eiseGrid = function( method ) {

    if ( methods[method] ) {
        return methods[method].apply( this, protoSlice.call( arguments, 1 ) );
    } else if ( typeof method === 'object' || ! method ) {
        return methods.init.apply( this, arguments );
    } else {
        $.error( 'Method ' +  method + ' does not exist on jQuery.fn.eiseGrid' );
    }

};

$.extend($.fn.eiseGrid, {
    defaults: settings
});

})( jQuery );
