/**
 * eiseList jQuery wrapper
 * ===
 *
 * requires jQuery UI 1.8: 
 * http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/jquery-ui.min.js
 *
 *
 * Published under GPL version 2 license
 * (c)2005-2015 Ilya S. Eliseev ie@e-ise.com, easyise@gmail.com
 *
 * Contributors:
 *  - Pencho Belneiski
 *  - Dmitry Zakharov
 *  - Igor Zhuravlev
 *
 *  eiseList reference [http://russysdev.com/eiseIntra/docs/list/]()
 */
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
    list.parent = this.div.parent();
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

    list.filters_default = {field: null,
            value: null,
            filters: {},
        }

    // Init tabs, if any
    list.tabsInitialized = false
    this.tabs = this.div.find('#'+list.id+'_tabs').tabs({
            activate: function(event, ui){
                list.filterByTab(ui.newTab.find('a')[0]);
            }
        });

    // Fitting the list inside the container
    list.fitContainer();
    //attach onResize event handling
    $(window).resize(function(){
        
        list.fitContainer();
        
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
    
    this.form.submit(function(){
        list.saveFilters();
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
    
    this.div.find('#btnReset').click(function (ev){
        list.reset(ev)
    });
    
    this.div.find('.sel_'+list.id+'_all').click(function (){
        list.toggleRowSelection(this);
    });

    // set minimal list column width while data not loaded
    this.setMinimalColumnWidth();

    this.initFilters();

    this.thead.find('input.el_special_filter').each(function(){
        list.initSpecialFilter(this);
    });

    var selectedTab = this.initTabs();

    if(!selectedTab){
        this.getData(0,null,true);
    }

}

/**
 * This function reads local storage for last filters used on this list and set filters keeping in mind tab settings
 *
 */
eiseList.prototype.initFilters = function(){

    var list = this
    
    var filters = list.getFilters()
        , defaultFilters = filters[0]
        , getFilters = list._qs2obj( location.search.replace('?', '') )
        , filtersToApply = {};

    // merge LS filters with GET filters, only GET can override LS filters
    defaultFilters.filters = $.extend(defaultFilters.filters, getFilters)

    // if there's a tab to be selected accoring to storage - get all filters saved for this tab
    var tabData = null
    $.each(defaultFilters.filters, function(field, value) {
        list.tabs.find('a').each(function(tabIx){
            tabData = list._getTabData(this)
            if(tabData.field==field && tabData.value==value){
                return false //break
            }
            tabData = null
        });
        if(tabData)
            return false;
    });

    filtersToApply = defaultFilters.filters

    if(tabData)  { // if we found a tab
        filtersForTab = list.getFiltersForTab(tabData, filters)
        filtersToApply = $.extend(filtersForTab, getFilters)
        filtersToApply[tabData.field] = tabData.value
    }

    list.setFilters(filtersToApply)

}

/**
 * This function returns filters object for given **tabData** {field: , value: } from **filters** filter set of the list
 */
eiseList.prototype.getFiltersForTab = function(tabData, filters){
    for (var i = filters.length - 1; i >= 0; i--) {
        var oTabFilters = filters[i]
        if(oTabFilters.field==tabData.field && oTabFilters.value==tabData.value){
            return oTabFilters.filters
        }
    }
    return filters[0].filters
}

/**
 * This method sets filter values according to object supplied as parameter
 */
eiseList.prototype.setFilters = function(filtersToApply){
    var list = this

    $.each(filtersToApply, function(field, value){
        
        if(!field)
            return true // continue

        var selectorInp = '[name='+field+']',
            $inp = list.form.find(selectorInp)
        if( $inp[0] ){
            if(list.checkFilterVisible(field,value)){
                $inp.val(value)
                if(value!='' && value!=$inp.val() && $inp[0].nodeName=='SELECT'){
                    $inp.append('<option value='+value+' selected>'+value)
                }
            } else 
                $inp.val('')
        }
        if(field==list.id+'OB'){
            list.thead.find('th').removeClass('el_sorted_asc').removeClass('el_sorted_desc')
            var $th = list.thead.find('.'+list.id+'_'+value)
            if($th[0])
                if(filtersToApply[list.id+'ASC_DESC']=='DECS')
                    $th.addClass('el_sorted_desc')
                else
                    $th.addClass('el_sorted_asc')
        }
    })

}

/**
 * To avoid unobvious filters it checks whether filter field presents in filter fields, tabs or query string. 
 * It may happen when the list with same name (id) serves various statuses with various tab sets
 *
 */
eiseList.prototype.checkFilterVisible = function(field, value){
    var list = this,
        flagFound = false

    // check visible fields
    var $inp = list.form.find('[name='+field+']');
    if ($inp[0] && $inp.attr('type') && $inp.attr('type').toLowerCase()!='hidden') return true

    // check location.search
    $.each(list._qs2obj( location.search.replace(/^\?/, '') ), function(f,v){
        if(f==field){
            flagFound = true
            return false // break
        }
    } )
    if(flagFound) return true

    // check tabs
    if(list.tabs[0])
        list.tabs.find('a').each(function(){
            var tabData = list._getTabData(this)
            if(tabData.field==field && tabData.value==value){
                flagFound = true
                return false
            }
        })
    return flagFound

}


/**
 * This function returns object from query string like "a=b&c=d" => {a: 'b', c: 'd'}
 * @category Filters
 */
eiseList.prototype._qs2obj = function(qs, options){

    var defaultOptions = {
            removeNonNative: false
        },
        list = this

    var aqs = qs.split('&'),
        ret = {};

    options = $.extend(defaultOptions, (options ? options: {}) )

    for (var i = aqs.length - 1; i >= 0; i--) {
        var arg = aqs[i].split('='),
            field = arg[0],
            value = arg[1];

            if(!field)
                continue;

            if(options.removeNonNative && !field.match(new RegExp('^'+list.id))){
                continue;
            }

        ret[field] = decodeURIComponent(value).replace(/\+/g, ' ');
    }
    return ret
}

/**
 * This function returns array of current filters
 * @category Filters
 * @return array with filters data
 */
eiseList.prototype.getFilters = function(){
    
    var list = this
        , lsFiltersKey = this.conf.cookieName
        , jsonFilters = localStorage[lsFiltersKey];

    return (jsonFilters ? JSON.parse(jsonFilters) : [list.filters_default])

}

/**
 * This function saves filters
 */
eiseList.prototype.saveFilters = function(){

    var list = this

    list.queryString = list.getQueryString();

    var oFilters = list._qs2obj(list.queryString, {removeNonNative: true}),
        oActiveTab = list._getTabData( list.getActiveTab() ),
        filters = list.getFilters(),
        ixFilter = 0

    if(oActiveTab && oActiveTab.field && oActiveTab.value){
        for (var i = filters.length - 1; i >= 0; i--) {
            var flt = filters[i]
            if(oActiveTab.field==flt.field && oActiveTab.value==flt.value){
                ixFilter = i
                break
            }
        }
        if(ixFilter==0){
            ixFilter = filters.length;
            filters.push( $.extend(list.filters_default, oActiveTab) );
        }
    }

    if(oActiveTab)
        filters[0].filters[oActiveTab.field] = oActiveTab.value
    filters[ixFilter].filters = oFilters

    localStorage[this.conf.cookieName] = JSON.stringify(filters)

}

/**
 * This functions returns object with field name and field value for given tab, supports both LI and A DOM objects
 * @category tabs
 * @param DOMobject obj - LI or A element of tab
 * @return object {field: listID+fieldName, value: filter value}
 */
eiseList.prototype._getTabData = function(obj){

    if(!obj)
        return null;

    var list = this
    
    var $a = (obj.nodeName=='A' ? $(obj) : $(obj).find('a').first()),
        tabKeyValue = ($a[0] ? $a.attr('href').replace('#'+list.id+'_tabs_', '') : null);

    return (tabKeyValue 
        ? {field: tabKeyValue.split('|')[1], value: decodeURIComponent(tabKeyValue.split('|')[0]).replace(/\+/g, ' ')} 
        : null)
}

/**
 * This function returns cell field name basing on its class.
 */
eiseList.prototype.getFieldName = function(cell){
        var arrClass = $(cell).attr('class').split(/\s+/)
            , list = this
            , retVal = false;
        $.each(arrClass, function(ix, strClass){
            if(strClass.match(new RegExp('^'+list.id+'\_')) ) {
                retVal = strClass.replace(list.id+'_', '');
            }
        })
        return retVal;
    }


/**
 * In addidiion to $.ui.tabs() initialization, this method looks for any occurance of tab-based keys in the list filters. In case when it's foud it returns selected tab title.
 */
eiseList.prototype.initTabs = function(){
    
    var selectedTab = null,
        list = this;

    if( list.tabs[0] ) {
        
        var $tabs = list.tabs,
            selectedTabIx = 0,
            tabAnyIx = 0,
            tabAny = null;

        $tabs.find('a').each(function(ix, obj){ // looking for matching tabs
            var tabData = list._getTabData(this),
                selectorFilter = '[name='+tabData.field+']',
                $filter = list.form.find(selectorFilter);

            if($filter[0] && $filter.val()==tabData.value){
                selectedTab = this;
                selectedTabIx = ix;
            }
            
            if(selectedTab){
                return false; //break
            }
            
            if(tabData.value==''){
                tabAnyIx = ix;
                tabAny = this
            }

        });

        if(!selectedTab){
            selectedTabIx = tabAnyIx;
            selectedTab = tabAny;
        }

        if(selectedTab){
            if(selectedTabIx>0)
                list.tabs.tabs({ active: selectedTabIx });
            else 
                list.filterByTab(selectedTab);
        }

    }

    list.tabsInitialized = true

    return selectedTab;

}

/**
 * This method returns A element of active tab
 */
eiseList.prototype.getActiveTab = function(){
    var list = this
    if(list.tabs[0]){
        var ixTab = list.tabs.tabs('option', 'active'),
            a = list.tabs.find('li:nth-of-type('+(ixTab+1)+') > a')
        return a[0]
    } else {
        return null
    }
}

/**
 * This method filters by tab {field: XX, value: YY} specified in **tab** parameter 
 */

eiseList.prototype.filterByTab = function(tab, conf){

    var list = this
    
    if(tab){
        var tabData = this._getTabData(tab)

        var $inp = this.form.find('[name='+tabData.field+']');

        if (!$inp[0]){
            
            $inp = this.form.append('<input type=hidden id="'+tabData.field+'" value="'+tabData.value+'" name="'+tabData.field+'" class="el_filter">');

        } 
        
        $inp.val(tabData.value);  

        if(list.tabsInitialized){
            var tabFilters = this.getFiltersForTab(tabData, list.getFilters())
            tabFilters[tabData.field] = tabData.value
            list.setFilters(tabFilters)
        }

    }
    
    this.form.submit()

}

/**
 * This function fits list to the container
 */
 eiseList.prototype.fitContainer = function() {
    var list = this,
        minRows = 5,
        rowH = 22,
        positionTop = list.div.position().top,
        offsetTop = list.div.offset().top,
        parentHeight = list.parent.outerHeight(),
        parentPaddingBottom = parseInt(this.parent.css('padding-bottom').replace('px', '')),
        hToSet = parentHeight-positionTop-parentPaddingBottom;


    this.h = hToSet;
    this.divTableHeight = this.h - this.header.outerHeight(true);
    this.bodyHeight = this.divTableHeight - this.thead.outerHeight(true) - (this.tfoot[0] ? this.tfoot.outerHeight(true) : 0);

    if( this.bodyHeight<minRows*rowH ){
        var viewportHeight = document.body.clientHeight,
            parentOffsetTop = this.parent.offset().top,
            parentParentPaddingBottom = parseInt(this.parent.parent().css('padding-bottom').replace('px', '')) 
            hParent = viewportHeight - parentOffsetTop-(isNaN(parentParentPaddingBottom) ? 0 : parentParentPaddingBottom);
        //console.log(hParent, viewportHeight, parentOffsetTop, this.div.css('padding-top'), this.div.css('padding-bottom'));
        if(hParent < viewportHeight){
            this.parent.height(Math.floor(hParent));
            list.fitContainer();
        } else {
            this.bodyHeight = minRows*rowH;
            this.divTableHeight = this.bodyHeight + this.thead.outerHeight(true) + (this.tfoot[0] ? this.tfoot.outerHeight(true) : 0);
            this.h = this.divTableHeight + this.header.outerHeight(true);
        }
    }

    this.div.height(this.h);
    this.divTable.height(this.divTableHeight);
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
        list.header.find('.el_foundRows').css("display", "inline");
    } else {
        list.header.find('.el_span_foundRows').text('');
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

    tr.find('.el_editable').each(function(){
        list.initEditable(this);
    })
    
    tr.removeClass('el_template');
    tr.addClass('el_data');
    tr.addClass('el_tr'+index%2);
    if (rw.c)
        tr.addClass(rw.c);
    list.tbody.append(tr);
    
}

/**
 * This function initialize cell for being editable. It can be called for any field in the list. Basically it is called from list.appendRow() function for '.el_editable' fields.
 * It binds list.showInput() on click event and organizes data save process with POST $.ajax request to the list script. It comes with DataAction=updateCell.
 * 
 * @param DOMObject cell - <td> element to become editable
 * 
 * @return nothing
 */
eiseList.prototype.initEditable = function(cell){
    
    var list = this;

    cell.onclick = function(){
        var conf = {
                callback: function(cell, oldVal, newVal){
                    //save data with ajax:
                    // determine PK
                    var pk = $(cell).parent('tr').attr('id').replace(list.id+'_', '');
                    var field = $(cell).attr('class')
                        .split(/\s+/)[0]
                        .replace(list.id+'_','');
                    $.ajax({ url: location.pathname
                        , type: 'POST'
                        , data: {
                            DataAction: 'updateCell'
                            , pk: pk
                            , field: field
                            , value: newVal
                        }
                        , success: function(data, text){
                            if(jQuery().eiseIntra && data) { $('body').eiseIntra('showMessage', (data.status=='ok' ? data.message : 'ERROR:'+data.message))}
                        }
                        , error: function(o, error, errorThrown){
                            if(jQuery().eiseIntra) { $('body').eiseIntra('showMessage', 'ERROR:'+list.conf['titleERRORBadResponse']+'\r\n'+list.conf['titleTryReload']+'\r\n'+errorThrown)}
                        }
                        , dataType: "json"
                        
                    });
                    
                    
                    list.hideInput(cell, newVal);
                }
            }

            var $cellInput = list.showInput(this, conf);
    }
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

/** 
 * This method resets list filters and/or sort order, but keeps initial selecton set by query string and tabs
 * 
 * @param object options - The dictionary that contains the following options:
 * - 'sort': when true, sort field and order are subject to reset also
 * - 'keepQueryStringFilters': when true, all fields from initial query string of this list are kept. Default: true
 * - 'keepFilters': list (array) of field names to keep
 * - 'reloadPage': when true, list form submits to itself. Default: false
 */
eiseList.prototype.reset = function (ev, options){

    var defaults = {'sort': true,
            'keepAllFilters': false,
            'keepQueryStringFilters': true,
            'keepFilters': [],
            'reloadPage': false,
            'clearAllStorage': false
        },
        list = this,
        activeTabField = (list.tabs[0] 
            ? list._getTabData( list.getActiveTab() ).field
            : null);

    options = $.extend(defaults, options)

    if (activeTabField )
        options.keepFilters.push(activeTabField)

    if(ev.shiftKey)
        options.clearAllStorage = true

    if(options.sort){
        list.form.find('input[name='+list.id+'OB]').val('')
        list.form.find('input[name='+list.id+'ASC_DESC]').val('')
    }

    if(options.keepQueryStringFilters){
        var oFlt = list._qs2obj(location.search.replace(/^\?/, ''))
        $.each(oFlt, function(field, value){
            options.keepFilters.push(field);
        })
    }

    this.form.find(".el_filter").each( function(idx, oInp){
        if(options.keepFilters.indexOf(oInp.name)==-1){
            $(oInp).val('')
        } 
    });

    if(options.reloadPage)
        list.conf.doNotSubmitForm = false;

    if(options.clearAllStorage)
        localStorage.removeItem(list.conf.cookieName)

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

            var dates = this.getDatesRange($initiator.val(), type),
                $inpDateFrom = $divFilterDropdown.find('.el_dateFrom input'),
                $inpDateTill = $divFilterDropdown.find('.el_dateTill input');
            $inpDateFrom.val(dates.from);
            $inpDateTill.val(dates.till);

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

eiseList.prototype.getDatesRange = function(val, type){

    var list = this,
        strRexData = list.conf.dateRegExs[1].rex+(type=='datetime' 
            ? ' '+list.conf.dateRegExs[1].rexTime
            : ''),
        rexData  = new RegExp('([\<\>]{0,1}\=)'+strRexData, 'gi'),
        rexDataSingle  = new RegExp('([\<\>]{0,1}\=)('+strRexData+')'),
        ret = {from: null, till: null};

    var arrMatch = val.match(rexData);
    if(arrMatch)
        $.each(arrMatch, function(i,text){
            var m = text.match(rexDataSingle);
            switch(m[1]){
                case '>=':
                    ret.from = m[2];
                    break;
                case '<=':
                    ret.till = m[2];
                    break;
                case '=':
                    ret.from = m[2];
                    ret.till = m[2];
                    break;
                default:
                    break;
            }
        });
    return ret;
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

getDatesRange: function(val){
    var list = $(this[0]).data('eiseList').eiseList;
    return list.getDatesRange();
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




