/**
 * @fileOverview eiseTableSizer jQuery plugin
 * Sets height to the table, with fixation of header and footer.
 * Applicable only for <table> elements.
 * Table header and footer should be wrapped in <thead> and <tfoot> tags.
 * Table header cells should use <th> tags.
 * For best and accurate perfomance: add <colgroup> and <col> tags with style="width: NN[%|px|em]" attribute. Otherwise it takes width from THs and propagate it to the inner table.
 * Table rows in <tbody> tag are copined into newly created table wrapped into <div> with "overlow-y: scroll" style.
 * Origin table is being wrapped into <div> with "overflow-x: scroll" style.
 * 			
 *               <p>License GNU 3
 *               <br />Copyright 2008-2015 Ilya S. Eliseev <a href="http://russysdev.com">http://russysdev.com</a>
 *
 * @version 1.00
 * @author Ilya S. Eliseev http://russysdev.com
 * $license GNU public license v3
 * @requires jquery
 */
(function( $ ){

var pluginName = 'eiseTableSizer';

var getScrollWidth = function(){
    
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

var methods = {

/**
 * It is the root method that sets height (and width - in future versions) to the <table> element with fixation of table header (<thead>) and footer <tfoot>.
 * Requirements:
 * - origin element should be the <table>
 * - <table> should have <thead> and <tbody>
 * - <colgroup> element is highly recommended for optimal perfomance
 *
 * TODO: allow to fix table width by wrapping it into <div class="overflow-x: scroll; width: XX">
 *
 * @example $(element).eiseTableSizer({height: '150' });
 * 
 * @param {Object} arg
 * @param {String} arg.height - table height to be set, in pixels
 */
init: function(arg) {
	
	var $o = this;

	this.each(function(){

		if(this.nodeName!='TABLE')
			return true; //continue loop;

		var $this = $(this)
			, $th = $this.find('thead')
			, $tf = $this.find('tfoot')
			, $tbody = $this.find('tbody')
			, $colgroup_origin = $this.find('colgroup')
			, $innerDiv = $tbody.find('div.eits-overflow-y');

		if(!$innerDiv[0]){
			
			var scrollWidth = getScrollWidth();

			var sumSpan = 0, colgroup = '';
			$tbody.find('tr').first().find('td').each(function(){
				var span = parseInt($(this).attr('colspan'));
				sumSpan +=  (!isNaN(span) ? span : 1) 
			});

			sumSpan = ($colgroup_origin[0] ? $colgroup_origin.find('col').length : sumSpan);

			if($colgroup_origin[0]){
				colgroup = $colgroup_origin[0].outerHTML;
				$colgroup_origin.find('col').last().css('width', $th.find('tr > th').last().outerWidth(false)+scrollWidth+'px');
			}
			else {
				colgroup = '<colgroup>';
				$th.find('tr > th').each(function(){
					var span = parseInt($(this).attr('colspan'));
					span +=  (!isNaN(span) ? span : 1);
					for(var i=0; i<span; i++){
						var w =  $(this).outerWidth(false) / span;
						colgroup += '<col style="width: '+w+'px;"></col>'	
					}
					colgroup += '</colgroup>';
					
				})
			}

			$tbody.detach();

			var $newTBody = $('<tbody class="eits-container"><tr><td colspan="'+sumSpan+'" style="border:0;padding:0;margin:0"><div class="eits-overflow-y"><table'+
				(arg.class ? ' class="'+arg.class+'"' : '')+
				'>'+colgroup+'</table></div></td></tr></tbody>');

			if(arg.class)
				$this.removeClass(arg.class);

			if($th)
				$th.after($newTBody);
			else 
				$this.append($newTBody);

			$newTBody.find('table').append($tbody)
				.css('width', '100%')
				.css('table-layout', 'fixed');

			$innerDiv = $newTBody.find('div.eits-overflow-y')
				.css('overflow-x', 'hidden')
				.css('overflow-y', 'scroll'); // as we cannot handle overflow event, overwlow-y should be set to SCROLL always.
		}

		var hBefore = $this.outerHeight();

	    if (typeof(arg.height)!='undefined'){
	    	var newHeight = parseFloat(arg.height);
	    	if(isNaN(newHeight))
	    		return true; // continue loop
	        var nTableExtraz = $this.outerHeight(true)-$innerDiv.height()
	        	, hToSet = newHeight-nTableExtraz;
	        if(newHeight < hBefore){
    	        $innerDiv.animate( {"height": hToSet }, function() {  

    	        	$innerDiv.css('max-height', hToSet+'px');

    	        	if(typeof arg.callback==='function')
    			     	arg.callback.call($this);

    			    $innerDiv.css('overflow-x', 'hidden')
						.css('overflow-y', 'scroll');

    			})
	        } else {
	        	$innerDiv.css('max-height', hToSet+'px');
	        	if(typeof arg.callback==='function')
			     	arg.callback.call($this);
	        }
	        
	    }
	    
	})

	return $o;

},

getScrollWidth: function(){
	return getScrollWidth();
},

reset: function(){

}

    
}


$.fn.eiseTableSizer = function( method ) {  

    if ( methods[method] ) {
        return methods[method].apply( this, Array.prototype.slice.call( arguments, 1 ));
    } else if ( typeof method === 'object' || ! method ) {
        return methods.init.apply( this, arguments );
    } else {
        $.error( 'Method ' +  method + ' not exists for jQuery.eiseIntraAJAX' );
    } 

};


})( jQuery );