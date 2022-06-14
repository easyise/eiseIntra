/**
 * eiseTable2Excel jQuery plug-in 
 *
 * @version 1.00
 * @author Ilya S. Eliseev http://e-ise.com
 * @requires jquery
 */

var eiseTable2Excel_scripts = document.getElementsByTagName("script"),
    eiseTable2Excel_src = eiseTable2Excel_scripts[eiseTable2Excel_scripts.length-1].src;

(function( $ ){

var conf = {
    homedir: 'eiseIntra',
    imageURL: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABGdBTUEAAK/INwWK6QAAABl0RVh0U29mdHdhcmUAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAALDSURBVBgZBcFNiFVVAADg75x777z50RmdDJG0phpTIwq1cqP9IBqlLaxNpYVSVIvahLVLCqFFoGEZQkQhgdGilUghaqRNIKgUZEmQlCBlmmOm772Zd+85fV/IOVuz7ejmgeHWxhgsRz8CCMiBnNQp/Xbln3w4XJ18/die9dMAIefssXcmjn326vIlMYZZmUIGIGfILl7r2Xfiir/OTbV//unM6Hd71k9BCbEIi/rKYtbpvxUxBAI50eSkrrNOr/HQwplW3FE6ni4O5rR48sFXDsz+dve6qQghhBk556KviKpIGSgiRSAEooBk3nCf9ffNMzbeGiiHhz6F8NSO1WdTHh2bNZhCk4Nl44+7fP2Sb37cK6NVzdCk2rplz9j0wEtaVandnbbpvZP1wbdXVSVOvfzI5ls7rT/9fvmMUyf3q1PbsoX3mG5q7XZHMmp8wdOOn6ulNG3VbS2hjDVEbPzw64PNDXnc8NCwRXfNU8ZBl65e1m53lcVcW9a8b3hoRH9fob+vkkVCBPHz1w5NtZsne19M7LVkYLWZ/QPGF92i2+mq69ILa3caqFqqMuorCq0ySsgZiNBuHy6+//WIXQe2u3/OBk3ZceeSu031Jp3+45CyoCqCMgZlETWJJgHx3jduevFa5+NqxeKVchXs3P+WRxc8a9Il88du99WJDzy/a0zIQRmDIgb9VdDUGURsI5s4fcQvZ3/QmW58cuQjT4w9Z2TmbKM3L7D01pUyUiajKqJ6ugbliXfPz3/4zYnOvq3L+y9eq8C/1y/4cmK7691JIUQjgzeqIlUMIOWsN5VACXXdaBoARobm2rJ2NwAAgJyyXrcGEeqplOqUMgAAAABAWcZUN6mGEnrd5sJQXzFH6A3lnKNMAowMlCBnBqooBKkqwn9Nnc5DCSHkHWu3Ht0QQlia5UEAmYwsAxl0U0qnymgf/A8eWStYAg6kAQAAAABJRU5ErkJggg==',
    heightWeight: 22,
    positionTop: 4,
    positionLeft: 4,
    excelSheetName: 'Sheet1',
}

var sa = null

var addButton = function(){

    var table = this,
        style = `position:absolute; background-image: url("`+conf.imageURL+`");
    background-position: center center;
    background-color: #f0f0f0;
    background-repeat: no-repeat;
    width: `+conf.heightWeight+`px;
    height: `+conf.heightWeight+`px;
    border-radius: 2px;
    border-width: 1px;
    border-style: outset;
    color: transparent;
    overflow: hidden;
    padding: 1px;
    top: `+conf.positionTop+`px;
    left: `+conf.positionLeft+`px;`;

    $('<button class="btn-excel"/>')
        .attr('style', style)
        .appendTo($(table).find('thead tr:first-of-type th:first-of-type ').css('height', conf.heightWeight + 2*conf.positionTop))
        .click(function(){

            $(this).parents('th').first().find('.btn-excel').remove();
            gimmeExcel.call(table);
            addButton.call(table);

        }
    );
}

var entityMap = {
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&#39;',
  '/': '&#x2F;',
  '`': '&#x60;',
  '=': '&#x3D;'
};

var escapeHtml = function (string) {
  return String(string).replace(/[&<>"'`=\/]/g, function (s) {
    return entityMap[s];
  });
}

var getCell = function(elem){

    var $elem = $(elem)
        , text = $elem.text()
        , val = ''
        , style = '';

    switch(elem.dataset['type']){
        case 'datetime':
        case 'date':
            val = $('body').eiseIntra('parseDate', $elem.text());
            val = val ? val.toISOString() : '';
            typeExcel = 'DateTime';
            style = ' ss:StyleID="s22"';
            break;
        case 'float':
            val = parseFloat($elem.text().replace(',', ''));
            val = isNaN(val) ?  '' : val;
            typeExcel = 'Number';
            break;
        case 'int':
            val = parseInt($elem.text().replace(',', ''));
            val = isNaN(val) ?  '' : val;
            typeExcel = 'Number';
            break;
        default:
            typeExcel = 'String';
            val = $elem.text();
            break;
    }

    
    return '<Cell'+style+'><Data ss:Type="'+typeExcel+'">'+escapeHtml(val)+'</Data></Cell>\n';

}

var gimmeExcel = function(options = {}){
    
    var $table = this
        , strTH = ''
        , rows = '';

    $.extend(options, conf)

    $table.find('thead tr').each(function(){

        strTH += '<Row ss:StyleID="Hdr">\n';

        $(this).find('th').each(function(){
            var th = this
                , $th = $(this)
                ;

            strTH += getCell(th);

        })
        
        strTH += '</Row>\n';

    })

    $table.find('tbody tr').each(function(){

        var $tr = $(this);
        rows += '<Row>\n';

        $tr.find('td').each(function(){
            rows += getCell(this)
        })


        rows += '</Row>\n';

    })

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
    sa(b, options.excelFileName);

    return;

}

var methods = {

/**
 *  This default method shows batch script dialog 
 */
init: function(arg){

    if( typeof arg==='object' ){
        $.extend(conf,arg); 
    }

    if(!conf.excelFileName){
        var aFullPath = location.pathname.split('/'),
            scriptFileName = aFullPath[aFullPath.length-1].split('.')[0]
        conf.excelFileName = scriptFileName+'.xls'
    }

    var table = this;

    if(!sa){

        if(typeof saveAs==='undefined'){
            var urlFileSaver = eiseTable2Excel_src.split(conf.homedir)[0]+conf.homedir+'/lib/FileSaver.js/FileSaver.js';
            $.getScript(urlFileSaver, function(data, textStatus, jqxhr){

                sa = saveAs;
                addButton.call(table);

            });
            return;
        } else {

            sa = saveAs;

        }
    }

    addButton.call(table);

    return this

}

}


$.fn.eiseTable2Excel = function( method ) {  

    if ( methods[method] ) {
        return methods[method].apply( this, Array.prototype.slice.call( arguments, 1 ));
    } else {
        return methods.init.apply( this, arguments );
    } 

};


})( jQuery );