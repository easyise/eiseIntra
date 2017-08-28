/********************************************************/
/*  
eiseList jQuery wrapper

requires jQuery UI 1.8: 
http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/jquery-ui.min.js


Published under GPL version 2 license
(c)2005-2015 Ilya S. Eliseev ie@e-ise.com, easyise@gmail.com

Contributors:
Pencho Belneiski
Dmitry Zakharov
Igor Zhuravlev

eiseList reference:
http://e-ise.com/eiseList/

*/
/********************************************************/
var eiseLists = [];

(function( $ ) {
var settings = {
    
};

function eiseList(divEiseList){
    
    var list = this;
    
    list.id = divEiseList.attr('id');
    eiseLists[list.id] = list;
    
    list.div = divEiseList;
    list.form = divEiseList.find('form');
    list.header = list.div.find('.el_header');
    list.divTable = list.div.find('.el_table');
    list.mainTable = list.div.find('.el_table > table');
    list.thead = list.mainTable.find('thead').first();
    list.trHead = this.thead.find('tr').last();
    list.body = list.div.find('.el_body');
    list.table = list.body.find('table');
    list.tbody = list.body.find('table tbody');
    list.tfoot = list.div.find('.el_tfoot');
    list.divFieldChooser = list.div.find('.el_fieldChooser');
    list.divFilterDropdowns = [];

    list.colgroupHead = list.div.find('.el_table > table > colgroup > col');
    list.colgroupBody = list.table.find('colgroup');
    
    list.systemScrollWidth = this.getScrollWidth()
    
    list.scrollBarHeight = null;
    
    
    list.activeRow = null;
    
    list.conf = $.parseJSON(list.div.find('#inp_'+list.id+'_config').val());
    
    list.activeRow = null;
    
    list.nTotalRows = null;
    list.currentOffset = 0;
    
    list.transferInProgress = false;
    
    list.getFieldName = function(cell){
        var arrClass = $(cell).attr('class').split(/\s+/)
            , retVal = false;
        $.each(arrClass, function(ix, strClass){
            if(strClass.match(new RegExp('^'+list.id+'\_')) ) {
                retVal = strClass.replace(list.id+'_', '');
            }
        })
        return retVal;
    }
    
    //attach onResize event handling
    $(window).resize(function(){
        
        //avoid stupid resource consuming by IE!11
        var windowHeight = $(window).height();
        var windowWidth = $(window).width();
     
        if (list.currentHeight == undefined || list.currentHeight != windowHeight
            || list.currentWidth == undefined || list.currentWidth != windowWidth) {
     
            list.adjustColumnsWidth();
            list.adjustHeight();
     
            list.currentHeight = windowHeight;
            list.currentWidth = windowWidth;
       }
        
    });
    
    
    
    // attach scroll event
    var oldScroll = 0;
    var newScroll = 0;
    this.body.scroll(function(){
        newScroll = $(this).scrollTop();
        if (newScroll > oldScroll)
            list.handleScroll();
        oldScroll = newScroll;
    });
    
    this.tbody.find('tr').bind("click", function(){ //row select binding
        list.selectRow($(this));
    });
    
    this.thead.find('.el_sortable').click(function(){
        list.sort($(this));
        list.setMinimalColumnWidth();
    })
    
    this.thead.find('select.el_filter').change(function(){
        list.form.submit();
    })
    
    this.thead.find('input.el_special_filter').each(function(){
        list.initSpecialFilter(this);
    });

    this.form.submit(function(){
        if (list.conf.doNotSubmitForm==true){
            list.refreshList();
            return false;
        }
    })        
        
    this.div.find('#btnFieldChooser').click(function (){
        list.fieldChooser();
    });
    
    this.div.find('#btnOpenInExcel').click(function (){
        list.openInExcel();
    });
    
    this.div.find('#btnReset').click(function (){
        list.reset();
    });
    
    this.div.find('.sel_'+list.id+'_all').click(function (){
        list.toggleRowSelection(this);
    });

    //tabs
    this.div.find('#'+list.id+'_tabs').tabs({
            activate: function(event, ui){
                var href = ui.newTab.find('a').attr('href');
                var ValKey = href.replace('#'+list.id+'_tabs_', '');
                list.filterByTab(ValKey);
            }
        });



    var selectedTab = this.initTabs();
    

    // set minimal list column width while data not loaded
    this.setMinimalColumnWidth();

    // aquire data
    if(selectedTab=='')
        this.getData(0,null,true);

}

/**
 * In addidiion to $.ui.tabs() initialization, this method looks for any occurance of tab-based keys in the list filters. In case when it's foud it returns selected tab title.
 */
eiseList.prototype.initTabs = function(){
    
    var selectedTab = '',
        list = this;

    this.div.find('#'+list.id+'_tabs').each(function(){
        
        var $tabs = $(this);

        var selectedTabIx = 0;
        var tabAnyIx = 0;

        $tabs.find('a').each(function(ix, obj){ // looking for matching tabs
            var tabKeyValue = $(obj).attr('href').replace('#'+list.id+'_tabs_', '');
            var tabKey = tabKeyValue.split('|')[1];
            var tabValue = tabKeyValue.split('|')[0];

            //looking for hidden inputs
            list.form.find('input.el_filter[type=hidden]').each(function(){
                var val = $(this).val();
                var key = this.id.replace(list.id+'_', '');
                if(tabKey==key && tabValue==val){
                    selectedTab = val+'|'+key;
                    selectedTabIx = ix;
                    return false; //break;
                }
            });
            if(selectedTab!=''){
                return false; //break
            }
                
            
            if(tabValue=='')
                tabAnyIx = ix;

        });

        if(selectedTab=='')
            selectedTabIx = tabAnyIx;

        $(this).tabs({ active: selectedTabIx });

        if(selectedTab!='')
            list.filterByTab(selectedTab);

    });

    return selectedTab;
}

eiseList.prototype.filterByTab = function(IDfilter){
    
    var filter = IDfilter.split('|')[1];
    var key = IDfilter.split('|')[0];

    if(!filter)
        this.form.find('input.el_filter[type=hidden]').remove();

    var inpID = this.id+'_'+filter;
    var $inp = this.form.find('input#'+inpID);

    key = decodeURIComponent(key).replace(/\+/g, ' ');

    if (!$inp[0]){
        
        $inp = this.form.append('<input type=hidden id="'+inpID+'" value="'+key+'" name="'+inpID+'" class="el_filter">');

    } else 
        $inp.val(key); 

    this.form.submit();

}

/**
 * This method adjusts list height in the following way: it takes all the rest of parent element including its bottom padding (and in case of "box-model: border-box", the border)
 * So in perfect condition the parent element should be the container that holds the list entirely, without any other elements. Existance of any block elements after $list->Execute() call will break its display.
 */
eiseList.prototype.adjustHeight = function(){

    var offsetTop = this.div.position().top,
        $parent = this.div.parent(),
        parentHeight = $parent.outerHeight(),
        initialListH = this.div.height(),
        initialTableH = this.divTable.height(),
        initialListHeight = this.div.outerHeight(true),
        initialTableHeight = this.divTable.outerHeight(true),
        parentPaddingBottom = parseFloat($parent.css('padding-bottom').replace('px', ''))
        newListH = parentHeight-offsetTop-parentPaddingBottom,
        deltaH = newListH-initialListH,
        newTableH = initialTableH + deltaH;

    //return;

    //this.div.height(newListH);
    this.divTableHeight = newTableH;
    this.divTable.height(this.divTableHeight);

    this.bodyHeight = this.divTableHeight - this.thead.outerHeight(true) - this.tfoot.outerHeight(true);
    this.body.height(this.bodyHeight);

}

/**
 * Returns system scrollbar width. If there're no constant scrollbars in the system it returns 0.
 *
 * @return int
 */
eiseList.prototype.getScrollWidth = function(){
    
    if (this.systemScrollWidth==null){
    
        var $inner = jQuery('<div style="width: 100%; height:200px;">test</div>'),
            $outer = jQuery('<div style="width:200px;height:150px; position: absolute; top: 0; left: 0; visibility: hidden; overflow:hidden;"></div>').append($inner),
            inner = $inner[0],
            outer = $outer[0];
         
        jQuery('body').append(outer);
        var width1 = inner.offsetWidth;
        $outer.css('overflow', 'scroll');
        var width2 = outer.clientWidth;
        $outer.remove();
    
        this.systemScrollWidth = (width1 - width2);
    } 
    return   this.systemScrollWidth;
}

/**
 * This method makes query string basing on filter/sort inputs in the upper 'form' section of the list. Difference from serizalize() is that it skips some elements like DataAction, button, checkboxes, etc
 *
 * @return string 
 */ 
eiseList.prototype.getQueryString = function(){
    
    var strARG = "";
    
    this.form.find('input, select').each(function(){
        if($(this).val()!=undefined 
            && ( $(this).val()!="" || ($(this).attr('type')=='hidden' && $(this).hasClass('el_filter')) )
            && $(this).attr('name')!='DataAction' 
            && $(this).attr('type')!='button'
            && $(this).attr('type')!='submit'
            && $(this).attr('type')!='checkbox'
        ){
            strARG += '&'+$(this).attr("name")+'='+encodeURIComponent($(this).val());
            $(this).parent().addClass('el_filterset');
        }
        if ($(this).val()=="" && $(this).parent().hasClass('el_filterset')){
            strARG += '&'+$(this).attr("name")+'=';
            $(this).parent().removeClass('el_filterset');
        }
        
    });
    return strARG;
}

/**
 * One of the basic internal methods of list. It queries data JSON from the server and fills in the table. For performance optimization eiseList caches query on the server side in the $_SESSION. When it retrieves next portion of data on scroll it queries only data offset and number of records to be retrieved. In order to avoid multiple queries it sets list property transferInProgress in True state.
 * 
 * @param int iOffset Offset of the first record to be obtained (0 for the first record)
 * @param int recordCount Number of records to be retrieved
 * @param boolean flagResetCache If true it says to the server side to drop cached data and prevents client side caching.
 * @param callback The function to be called after data is retrieved and placed.
 *
 */
eiseList.prototype.getData = function(iOffset, recordCount, flagResetCache, callback){
    
    var list = this;
    
    if (list.transferInProgress){
        return;
    }
    
    //collect filter data and compose GET query
    var ajaxURL = list.conf['dataSource'];
    
    list.currentOffset = iOffset;
    
    list.showNotFound(false); //hide 'not found' message
    
    list.body.find('.el_spinner')
        .css("display", "block")
        .css("width", list.div.outerWidth() - (list.body.find('.el_spinner').outerWidth()-list.body.find('.el_spinner').width()));
    if (iOffset > 0){
        list.body.find('.el_spinner').css("margin-top", "10px");
    }
    
    var strARG = "";
    if (list.conf.cacheSQL!=true || flagResetCache==true){
        
        strARG = list.getQueryString();
    
    }
    
    strARG = "DataAction=json&offset="+iOffset+(recordCount!=undefined ? "&recordCount="+recordCount : "")+
        (flagResetCache==true ? "&noCache=1&rnd="+Math.round(Math.random()*1000000) : "")+
        strARG;
    
    var strURL = ajaxURL+'?'+strARG;
    
    list.transferInProgress = true; //only one ajax-request at a time
    
    $.ajax({ url: strURL
        , success: function(data, text){
            
            if (data.error!=undefined){
                alert (list.conf['titleERRORBadResponse']+'\r\n'+list.conf['titleTryReload']+'\r\n'+data.error+'\r\n'+strARG);
                list.body.find('.el_spinner').css("display", "none");
                list.transferInProgress = false;
                list.showNotFound();
                return;
            }
            
            //append found rows
            for(var i=0;i<data.rows.length;i++){
                list.appendRow(i, data.rows[i]);
                list.currentOffset += 1;
            }
            
            if (list.currentOffset==iOffset && list.conf.calcFoundRows==false){
                list.nTotalRows = list.currentOffset;
            }
            
            
            list.body.find('.el_spinner').css("display", "none");
            
            if (iOffset==0 && data.rows.length==0){
                list.showNotFound();
            }
            
            if (iOffset==0){
                list.body.scrollTop(0);
            }
            
            list.adjustColumnsWidth();
            list.adjustHeight();

            if (iOffset == 0 && list.conf.calcFoundRows!=false){
                if(data.nTotalRows<list.conf.rowsFirstPage){
                    list.nTotalRows = data.nTotalRows;
                    list.showFoundRows();
                } else {
                    var args = "DataAction=get_aggregate&rnd="+Math.round(Math.random()*1000000)+list.getQueryString();
                    $.getJSON(ajaxURL+'?'+args, function(response){
                        if (response.error){
                            alert (list.conf['titleERRORBadResponse']+'\r\n'+list.conf['titleTryReload']+'\r\n'+response.error+'\r\n'+strARG);
                            return;
                        }
                        //display count information
                        list.nTotalRows = response.data.nTotalRows;
                        list.showFoundRows();

                    })
                }
                                    
            }
            
            list.transferInProgress = false;
            
            if (typeof(callback)!='undefined'){
                callback();
            }
            
            if (typeof(list.onLoadComplete)!='undefined'){
                list.onLoadComplete();
            }


            
        }
        , error: function(o, error, errorThrown){
            alert (list.conf['titleERRORBadResponse']+'\r\n'+list.conf['titleTryReload']+'\r\n'+errorThrown+'\r\n'+strARG);
            list.body.find('.el_spinner').css("display", "none");
            list.transferInProgress = false;
        }
        , dataType: "json"
        
    });
    

    
}

/**
 * This method updates on the screen total number of rows found by the query.
 */
eiseList.prototype.showFoundRows = function(){
    var list = this;
    if(list.nTotalRows){
        list.header.find('.el_span_foundRows').text(list.nTotalRows);
        list.header.find('.el_foundRows').css("display", "inline-block");
    } else {
        list.header.find('.el_span_foundRows').text('-');
        list.header.find('.el_foundRows').css("display", "none");
    }
        
}

eiseList.prototype.showNotFound = function(show){
    
    if (typeof(show)=='undefined' || show==true) {
        this.body.find('.el_notfound')
            .css("display", "block")
            .css("width", this.div.outerWidth() - (this.body.find('.el_notfound').outerWidth()-this.body.find('.el_notfound').width()));
    } else {
        this.body.find('.el_notfound')
            .css("display", "none");
    }
    
}

eiseList.prototype.openInExcel = function(){
    
    var strARG = this.getQueryString();
    
    strARG = "DataAction=excelXML&offset=0&noCache=1"+strARG;
    var strURL = this.conf['dataSource']+'?'+strARG;
    
    window.open(strURL, "_blank");
     
}


eiseList.prototype.refreshList = function(){
    
    this.tbody.children('tr:not(.el_template)').remove();
    this.getData(0,null,true);
    
}

eiseList.prototype.appendRow = function (index, rw){
    
    var list = this;
    
    //clone template row
    var tr = this.tbody.find('.el_template').clone(true);
    
    tr.find('td').each(function(){
        var fieldName = $(this).attr('class')
            .split(/\s+/)[0]
            .replace(list.id+'_','');
        
        if (rw.r[fieldName]==undefined)
            return true; //continue
        
        var text = rw.r[fieldName].t;
        
        if (rw.r[fieldName].c!=null  && rw.r[fieldName].c!="")
            $(this).addClass(rw.r[fieldName].c);
        
        if ($(this).hasClass('el_checkbox')){
            
            $(this).find('input')
                .attr("id", $(this).find('input').attr("id")+rw.PK)
                .val(rw.PK);
            
        } else {
        
            if (rw.r[fieldName].v!=null && rw.r[fieldName].v!="")
                $(this).attr("value", rw.r[fieldName].v);
            
            if ($(this).hasClass('el_boolean') && text=="1"){
                $(this).addClass('el_boolean_s');
            }
            
            var html = (rw.r[fieldName].h!=null && rw.r[fieldName].h!=""
                ? '<a href="'+rw.r[fieldName].h+'"'+($(this).attr('target')!=undefined ? ' target="'+$(this).attr('target')+'"' : '')+'>'+
                    text+'</a>'
                : text);
            
            $(this).html(html!=null && html!=undefined ? html : '');
        }
    });
    
    tr.attr('id', this.id+'_'+rw.PK);
    
    tr.removeClass('el_template');
    tr.addClass('el_data');
    tr.addClass('el_tr'+index%2);
    if (rw.c)
        tr.addClass(rw.c);
    list.tbody.append(tr);
    
}

/**
 * This function sets minimal column width for TH elements of wrapper table
 *
 */
eiseList.prototype.setMinimalColumnWidth = function(){

    var list = this;

    var iTotalWidth = 0;

    list.colgroupHead.each(function(){

        var classToFind = $(this).attr('class');
        var $th = list.thead.find('th.'+classToFind);
        var $colBody = list.colgroupBody.find('.'+classToFind);

        var originalWidth = this.dataset.width;

        if(originalWidth && !originalWidth.match(/\%$/)){
            $th.css('width', originalWidth)
                .css('min-width', originalWidth);
            $colBody.css('width', originalWidth);
            $colBody[0].dataset.fixedwidth = true;
        } else {
            if(originalWidth.match(/\%$/)){
                $th.css('width', originalWidth);
                $colBody.css('width', originalWidth);
            }
        }

    })
    list.colgroupHead.each(function(){

        var classToFind = $(this).attr('class');
        var $th = list.thead.find('th.'+classToFind);
        var $colBody = list.colgroupBody.find('.'+classToFind);

        var thWidth = $th.outerWidth(true);

        $th.css('width', thWidth+'px')
            .css('min-width', thWidth+'px');
        $colBody.css('min-width', thWidth+'px');

        $colBody.css('width', thWidth+'px');

        iTotalWidth += thWidth;

    })

    list.body.width(iTotalWidth);
    //list.table.width(iTotalWidth);

}

eiseList.prototype.adjustColumnsWidth = function(){
    
    /* box-sizing for any TD in list should be set to 'border-box'! */
    /* every eiseList table should have 'table-layout: fixed'! */
    
    var list = this;

    var $trFirst = this.tbody.find('.el_data');
    var iTotalWidth = 0;
    
    // compare min-width with actual width
    list.colgroupBody.find('col').each(function(){
 
        var classToFind = $(this).attr('class');
        var tdWidth = $trFirst.find('td.'+classToFind).outerWidth(true);

        var minWidth = parseFloat($(this).css('min-width').replace('px',''));

        var flagFixedWidth = this.dataset.fixedwidth;

        var colWidth = (flagFixedWidth 
            ? minWidth 
            : (tdWidth >  minWidth
                ? tdWidth
                : minWidth)
            );

        $(this).css('width', colWidth+'px');
        list.thead.find('th.'+classToFind).css('width', colWidth+'px')

        iTotalWidth += colWidth;

    })

    list.body.width(iTotalWidth);
    list.table.width(iTotalWidth);

    if(iTotalWidth > list.divTable.width())
        list.table.css('margin-bottom', this.systemScrollWidth)
    
}

eiseList.prototype.adjustHeaderColumnByBody = function(fieldName){
    
    var className = this.id+'_'+fieldName;
    
    var trBody = this.tbody.children('tr:not(.el_template)').first();
        
    var tdHead = $(this.thead.find('.'+className).first());
    var wH = tdHead.outerWidth();
    var tdBody = $(trBody.find('.'+className).first());
    var wB = tdBody.outerWidth();
    
    if (wH < wB && !tdBody.hasClass('el_fixedWidth')){
        var tdHeadW = wB;
        tdHead.css('min-width', tdHeadW+"px").css('max-width', tdHeadW+"px").css('width', tdHeadW+"px");
    }
    
}

eiseList.prototype.handleScroll = function(){
    
    var list = this;
    
    var cellHeight = this.tbody.children('tr:not(.el_template)').first().outerHeight(true);
    var windowHeight = this.body.height();
    
    var nCells = Math.ceil(windowHeight/cellHeight);
    
    if (list.body[0].scrollHeight - windowHeight <= list.body.scrollTop() 
        && (list.nTotalRows===null ? true : list.currentOffset<list.nTotalRows)){
        
        list.getData(list.currentOffset, nCells);
        
    }
    
}

eiseList.prototype.selectRow = function(oTr){
    
    if (this.activeRow!=null) {
        this.activeRow.removeClass('el_selected');
    }
    oTr.addClass('el_selected');
    this.activeRow = oTr;
}


eiseList.prototype.getFieldName = function ( oField ){
    var arrClasses = oField.attr("class").split(/\s+/);
    var colID = arrClasses[0].replace(this.id+"_", "");
    return colID;
}

eiseList.prototype.sort = function(oTHClicked){

    var colID = this.getFieldName(oTHClicked);

    var classToAdd = "";
    
    this.form.find("#"+this.id+"OB").val(colID);
    if (oTHClicked.hasClass('el_sorted_asc')){
        this.form.find("#"+this.id+"ASC_DESC").val("DESC");
        oTHClicked.removeClass('el_sorted_asc');
         classToAdd = 'el_sorted_desc';
    } else if(oTHClicked.hasClass('el_sorted_desc')){
        this.form.find("#"+this.id+"ASC_DESC").val("ASC");
        oTHClicked.removeClass('el_sorted_desc');
         classToAdd = 'el_sorted_asc';
    } else {
        this.form.find("#"+this.id+"ASC_DESC").val("ASC");
         classToAdd = 'el_sorted_asc';
    }
    
    
    this.thead.find("th").each(function(){
        $(this).removeClass('el_sorted_asc');
        $(this).removeClass('el_sorted_desc');
    })
    oTHClicked.addClass(classToAdd);
    
    this.form.submit();
    
    
}

eiseList.prototype.fieldChooser = function(){
    
    var oList = this;
    
    $(this.divFieldChooser).dialog({
        width: $(window).width()/2,
        title: "Choose Fields",
        buttons: {
            "OK": function() {
                oList.fieldsChosen();
            },
            "Cancel": function() {
                $(this).dialog("close");
            }
        },
        modal: true,
    });
    
    $(this.divFieldChooser).dialog("open");
}

eiseList.prototype.fieldsChosen = function(){
    var strHiddenFields = "";
    $(this.divFieldChooser).find("input").each(function(){
       // alert (this.id+" "+this.checked);
        if(!this.checked){
           var o=this;
            var arrFldID = o.id.split("_");
            var strListName = arrFldID[1];
            var strFieldName = o.id.replace("flc_"+strListName+"_", "");
            strHiddenFields += (strHiddenFields=="" ? "" : ",")+strFieldName;
        }
    });
    $('#'+this.id+"HiddenCols").val(strHiddenFields);
    //alert (document.getElementById(lstName+"HiddenCols").value);
    this.conf.doNotSubmitForm=false;
    this.form.submit();
}

eiseList.prototype.reset = function (){
    
    var list = this;

     this.form.find(".el_filter").each( function(idx, oInp){
        switch(oInp.nodeName){
            case "SELECT":
                if (oInp.name.replace(list.id+"_", "")=="staID"){
                    //INTRA2's staID exception
                    break;
                }
                oInp.selectedIndex=0;
                break;
            case "INPUT":
            default:
                oInp.value = "";
                break;
        }
    });

    list.conf.doNotSubmitForm = false;
    list.form.submit();
      
}

eiseList.prototype.toggleRowSelection = function(sel){
    
    var field = this.getFieldName($(sel).parents('td')[0]);
    
    var list = this;
    
    //1. check that we loaded all elements that match our selection
    //1a. no calcFoundRows - no selections
    if (!list.conf.calcFoundRows){
        alert("Function is not supported when calcFoundRows option is off.");
        return;
    }
    
    if (list.currentOffset < list.nTotalRows) {
        if (list.nTotalRows - list.nRowsLoaded > list.conf.maxRowsForSelection){
            alert("Number of rows to be loaded exceeds "+list.conf.maxRowsForSelection+".");
            return;
        } else {
            //2. if not, we download the rest (no more than specified in the config)    
            list.getData(list.currentOffset, list.nTotalRows - list.currentOffset, false, function(){
                list.toggleRowSelection(sel);
            });
        }
    } else {
    
        //3. loop thru matched elements
        list.tbody.find('tr:not(.el_template) td.'+this.id+'_'+field+' input[name="sel_'+list.id+'[]"]').each(function(){
            this.checked = ($(this).attr('disabled')=='disabled' ? this.checked : !this.checked);
        });
    
    }
    
    
}

eiseList.prototype.getRowSelection = function(){
    var list = this;
    var entIDs = '';
    $("input[name='sel_"+list.id+"[]']").each(function(){
        if (this.checked){
            entIDs += (entIDs!='' ? "|" : '')+$(this).attr("value");
        }
    })
    return entIDs;
}

eiseList.prototype.showInput = function(cell, conf){

    var inpID = this.id+'_'+this.getFieldName(cell);
    var cellText = $(cell).text();
    
    var list = this;
    
    var w = $(cell).innerWidth();
    var h = $(cell).innerHeight();

    //remove cell inner HTML
    $(cell).html('');
    $(cell).append((typeof(conf.inpHTML)!="undefined" 
        ? conf.inpHTML
        : '<input type="text" class="el_cellInput" autocomplete="off">')
        );
    
    $(cell).css('overflow', 'visible');
    
    $(cell).css('padding', 0).css('margin', 0);
    
    $inp = $(cell).find('input[type!=hidden],select');

    if(!$inp[0])
        return;
    
    $inp.attr('id', inpID);
    
    if($inp[0].nodeName=='SELECT'){
        $inp.find('option').each(function(){
            if($(this).text().replace(/\s+$/, '').replace(/^\s+/, '')==cellText){
                $(this).attr('selected', 'selected');
                return false; // break
            }
        })
    } else {
        $inp.val(cellText);    
    }
    
    
    $inp.css('display', 'block');
    if( $(cell).css('box-sizing') == 'border-box'){
        $inp.width(w)
            .height(h);    
    } else {
        $inp.width(w - ($inp.innerWidth()-$inp.width()))
            .height(h - ($inp.innerHeight()-$inp.height()));    
    }
    
    
    $inp.focus();
    
    //list.adjustBodyColumnByHeader(this.getFieldName(cell));
    
    //default input behaviour:
    // blur - leave data intact
    // enter key press - call data save callback and put new value inside a cell
    $inp
        .click(function(e){
            e.stopPropagation();
        })
        .blur(function(){
            list.hideInput(cell, cellText);
        })
        .keydown(function(event){
            switch(event.which){
                case 13:
                    if (typeof(conf.callback)=='undefined')
                        list.hideInput(cell, $(this).val())
                    else {
                        conf.callback( cell, cellText, $(this).val(), (this.nodeName=='SELECT' ? $(this).find('option:selected').text() : '') );
                    }
                    event.preventDefault(true);
                    break;
                case 27:
                    list.hideInput(cell, cellText);
                    break;
                default:
                    break;
            }
        });
    
    return $inp;
    
}

eiseList.prototype.hideInput = function(cell, newValue){
    
    var $inp = $(cell).find('input,select');
    
    $inp.remove();
    
    $(cell).css('padding', '').css('margin', '');
    
    $(cell).text(newValue);
    
    this.adjustColumnsWidth();
    
    //this.adjustBodyColumnByHeader(this.getFieldName(cell));
}

// extended search input
eiseList.prototype.initSpecialFilter = function(initiator) {

    var list = this;

    var $initiator = $(initiator);
    var field = $initiator.attr('name').replace(list.id+'_', '');
    var $divFilterDropdown = $('div#flt_'+$initiator.attr('name'));

    if(!$divFilterDropdown[0])
        return;

    var dialogOptions = {
                dialogClass: 'el_dialog_notitle', 
                position: {
                    my: "left top",
                    at: 'left bottom',
                    of: $initiator
                  },
                show: 'slideDown',
                hide: 'slideUp',
                autoOpen: false,
                resizable: false,
                width: ($initiator.outerWidth() > 200 ? $initiator.outerWidth()+'px' : 200),
                height: 85
            };

    list.divFilterDropdowns.push($divFilterDropdown);

    var type = $divFilterDropdown.attr('class').split(/\s+/)[0].replace('el_div_filter_', '');

    switch (type) {
        case 'date':
        case 'datetime':
            $divFilterDropdown.find('.el_input_date, .el_input_datetime').each(function(){
                try {
                    $(this).datepicker({
                        changeMonth: true,
                        changeYear: true,
                        dateFormat: list.conf.dateFormat.replace('d', 'dd').replace('m', 'mm').replace('Y', 'yy'),
                        constrainInput: false,
                        firstDay: 1
                        , yearRange: 'c-7:c+7'
                    });
                }catch(e) { console.log('Datepicker not found') };
            });
            var $inpDateFrom = $divFilterDropdown.find('.el_dateFrom input');
            var $inpDateTill = $divFilterDropdown.find('.el_dateTill input');

            var strRexData = list.conf.dateRegExs[1].rex+(type=='datetime' 
                ? ' '+list.conf.dateRegExs[1].rexTime
                : '');
            var rexData  = new RegExp('([\<\>]{0,1}\=)'+strRexData, 'gi');
            var rexDataSingle  = new RegExp('([\<\>]{0,1}\=)('+strRexData+')');

            var arrMatch = $initiator.val().match(rexData);
            if(arrMatch)
                $.each(arrMatch, function(i,text){
                    var m = text.match(rexDataSingle);
                    switch(m[1]){
                        case '>=':
                            $inpDateFrom.val(m[2]);
                            break;
                        case '<=':
                            $inpDateTill.val(m[2]);
                            break;
                        case '=':
                            $inpDateFrom.val(m[2]);
                            $inpDateTill.val(m[2]);
                            break;
                        default:
                            break;
                    }
                })

            $divFilterDropdown.dialog(dialogOptions);

            $divFilterDropdown.find('.el_btn_filter_apply').click(function(){
                var dateFrom = $inpDateFrom.val();
                var dateTill = $inpDateTill.val();

                var operator = (dateFrom!='' && dateTill!='' && dateFrom!=dateTill ? '&' : '');

                $initiator.val((dateFrom!='' 
                    ? (dateFrom==dateTill
                        ? '='+dateFrom
                        : '>='+dateFrom) 
                    : '')
                    +operator
                    +(dateTill!='' && dateTill!=dateFrom ? '<='+dateTill : ''));

                $initiator.parents('form').submit();

                $divFilterDropdown.dialog('close');

            })

            break;
        case "multiline":
            var valToSet = $initiator.val().replace(/(\s*[\,\|\;]\s*)/g, "\r\n");
            var $textarea = $divFilterDropdown.find('.el_textarea textarea');

            $textarea.val(valToSet);

            $divFilterDropdown.dialog($.extend(dialogOptions, {height: 'auto'}));

            $divFilterDropdown.find('.el_btn_filter_apply').click(function(){
                
                var text = $textarea.val().replace(/(\r?\n)/g, ",");

                $initiator.val(text);

                $initiator.parents('form').submit();

                $divFilterDropdown.dialog('close');

            });

            break;
        default:
            break;
    }


    $initiator
        .attr('autocomplete', 'off')
        .click(function(ev){
            if ( $divFilterDropdown.dialog('isOpen') )
                $divFilterDropdown.dialog('close');
            else {
                if(ev.shiftKey){
                    $.each(list.divFilterDropdowns, function(k, $d){ 
                        $d.dialog('close'); }); // close all open lists
                    $divFilterDropdown.dialog('open');
                    if($textarea){
                        $textarea.height( $divFilterDropdown.height() - $divFilterDropdown.find('.el_btn_filter_apply').height() );
                    }    
                }
                
            }
        })


    $divFilterDropdown.find('.el_btn_filter_clear').click(function(){
        $divFilterDropdown.find('input[type!=button],textarea').val('');
        $initiator.val('');
        $initiator.parents('form').submit();
        $divFilterDropdown.dialog('close');
    })
    $divFilterDropdown.find('.el_btn_filter_close').click(function(){
        $divFilterDropdown.dialog('close');
    })

    
}

eiseList.prototype.debug = function(msg){
    console.log(msg);
}

var methods = {
init: function( conf ) {

    this.each(function() {
        var data, dataId, conf_,
                $this = $(this);

        $this.eiseList('conf', conf);
        data = $this.data('eiseList') || {};
        conf_ = data.conf;

        // If the plugin hasn't been initialized yet
        if ( !data.eiseList ) {
            dataId = +new Date;

            data = {
                eiseList_data: true
                , conf: conf_
                , id: dataId
                , eiseList : new eiseList($this)
            };

            // create element and append to body
            var $eiseList_data = $('<div />', {
                'class': 'eiseList_data'
            }).appendTo( 'body' );

            // Associate created element with invoking element
            $eiseList_data.data( 'eiseList', {target: $this, id: dataId} );
            // And vice versa
            data.eiseList_data = $eiseList_data;

            $this.data('eiseList', data);
        } // !data.eiseList

        

    });

    return this;
},
destroy: function( ) {

    this.each(function() {

        var $this = $(this),
                data = $this.data( 'eiseList' );

        // Remove created elements, unbind namespaced events, and remove data
        $(document).unbind( '.eiseList_data' );
        data.eiseList.remove();
        $this.unbind( '.eiseList_data' )
        .removeData( 'eiseList_data' );

    });

    return this;
},
conf: function( conf ) {

    this.each(function() {
        var $this = $(this),
            data = $this.data( 'eiseList' ) || {},
            conf_ = data.conf || {};

        // deep extend (merge) default settings, per-call conf, and conf set with:
        // html10 data-eiseList conf JSON and $('selector').eiseList( 'conf', {} );
        conf_ = $.extend( true, {}, $.fn.eiseList.defaults, conf_, conf || {} );
        data.conf = conf_;
        $.data( this, 'eiseList', data );
    });

    return this;
},

refresh: function(){

    return this.each(function(){
        $(this).data('eiseList').eiseList.refreshList();
    });

},

getEiseListObject: function(){

    var list = $(this[0]).data('eiseList').eiseList;
    return list;

},

getRowSelection:  function(){
    var list = $(this[0]).data('eiseList').eiseList;
    return list.getRowSelection();
},

editField: function(){
    /*TODO*/
}


};

var protoSlice = Array.prototype.slice;

$.fn.eiseList = function( method ) {

    if ( methods[method] ) {
        return methods[method].apply( this, protoSlice.call( arguments, 1 ) );
    } else if ( typeof method === 'object' || ! method ) {
        return methods.init.apply( this, arguments );
    } else {
        $.error( 'Method ' +  method + ' does not exist on jQuery.fn.eiseList' );
    }

};

$.extend($.fn.eiseList, {
    defaults: settings
});

})( jQuery );

$(window).load(function(){
    
    $('.eiseList').eiseList();

});




