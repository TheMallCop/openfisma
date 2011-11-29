(function(){var a=function(e,c,d,b){a.superclass.constructor.call(this,e,c);this._msApprovals=d;this._evApprovals=b;this._columnLabels=Array();this._columnLabels=this._columnLabels.concat(a.MITIGATION_COLUMNS,d,a.EVIDENCE_COLUMNS,b,a.AGGREGATE_COLUMNS)};a.MITIGATION_COLUMNS=["NEW","DRAFT"];a.EVIDENCE_COLUMNS=["EN"];a.AGGREGATE_COLUMNS=["OPEN","CLOSED","TOTAL"];a.SUMMARY_TYPES={organizationHierarchy:"Organization Hierarchy",pointOfContact:"Point Of Contact",systemAggregation:"System Aggregation"};a.DEFAULT_VIEW="organizationHierarchy";YAHOO.lang.extend(a,Fisma.TreeTable,{_msApprovals:null,_evApprovals:null,_columnLabels:null,_currentViewType:null,onViewTypeChange:new YAHOO.util.CustomEvent("onViewTypeChange"),_tooltips:{viewBy:null,mitigationStrategy:null,remediation:null},_getDataUrl:function(){var b=a.superclass._getDataUrl.call(this)+"/summaryType/"+(this._currentViewType||a.DEFAULT_VIEW);return b},_renderHeader:function(b){this._renderHeaderRow1(b[0]);this._renderHeaderRow2(b[1]);this._renderHeaderRow3(b[2])},_renderHeaderRow1:function(p){var l=this;var m=document.createElement("th");p.appendChild(m);m.style.borderBottom="none";m.rowSpan=3;var h=document.createElement("span");m.appendChild(h);h.appendChild(document.createTextNode("View By: "));if(YAHOO.lang.isValue(this._tooltips.viewBy)){h.className="tooltip";var c=new YAHOO.widget.Tooltip("viewByTooltip",{context:h,showdelay:150,hidedelay:150,autodismissdelay:25000,text:this._tooltips.viewBy,effect:{effect:YAHOO.widget.ContainerEffect.FADE,duration:0.25},width:"50%"})}var n=document.createElement("select");n.onchange=function(q){l.changeViewType.call(l,q,n)};for(var k in a.SUMMARY_TYPES){var i=new Option(a.SUMMARY_TYPES[k],k);if(i.value==this._currentViewType){i.selected=true}if(YAHOO.env.ua.ie==7){n.add(i,n.options[null])}else{n.add(i,null)}}m.appendChild(n);var j=document.createElement("th");j.colSpan=a.MITIGATION_COLUMNS.length+this._msApprovals.length;j.style.borderBottom="none";p.appendChild(j);var d=document.createElement("span");j.appendChild(d);d.appendChild(document.createTextNode("Mitigation Strategy"));if(YAHOO.lang.isValue(this._tooltips.mitigationStrategy)){d.className="tooltip";var b=new YAHOO.widget.Tooltip("msTooltip",{context:d,showdelay:150,hidedelay:150,autodismissdelay:25000,text:this._tooltips.mitigationStrategy,effect:{effect:YAHOO.widget.ContainerEffect.FADE,duration:0.25},width:"50%"})}var o=document.createElement("th");o.colSpan=a.EVIDENCE_COLUMNS.length+this._evApprovals.length;o.style.borderBottom="none";p.appendChild(o);var f=document.createElement("span");o.appendChild(f);f.appendChild(document.createTextNode("Remediation"));if(YAHOO.lang.isValue(this._tooltips.remediation)){f.className="tooltip";var g=new YAHOO.widget.Tooltip("remediationTooltip",{context:f,showdelay:150,hidedelay:150,autodismissdelay:25000,text:this._tooltips.remediation,effect:{effect:YAHOO.widget.ContainerEffect.FADE,duration:0.25},width:"50%"})}var e=document.createElement("th");e.colSpan=a.AGGREGATE_COLUMNS.length;e.rowSpan=2;p.appendChild(e)},_renderHeaderRow2:function(f){var d=document.createElement("th");d.colSpan=a.MITIGATION_COLUMNS.length;d.style.borderTop="none";f.appendChild(d);var b=document.createElement("th");b.appendChild(document.createTextNode("Approval"));b.colSpan=this._msApprovals.length;f.appendChild(b);var c=document.createElement("th");c.style.borderTop="none";d.style.colSpan=a.EVIDENCE_COLUMNS.length;f.appendChild(c);var e=document.createElement("th");e.appendChild(document.createTextNode("Approval"));e.colSpan=this._evApprovals.length;f.appendChild(e)},_renderHeaderRow3:function(f){var d;var b;var e;for(var c in this._columnLabels){b=document.createElement("th");b.style.borderBottom="none";d=this._columnLabels[c];e=document.createElement("a");b.appendChild(e);if("OPEN"==d){e.href="/finding/remediation/list?q=/denormalizedStatus/textNotExactMatch/CLOSED"}else{if("TOTAL"==d){e.href="/finding/remediation/list?q=/denormalizedStatus/textContains/"}else{e.href="/finding/remediation/list?q=/denormalizedStatus/textExactMatch/"+encodeURIComponent(d)}}e.appendChild(document.createTextNode(d));f.appendChild(b)}},_preprocessTreeData:function(f){var b=f.nodeData;b.aggregate={total:b.total||0,closed:b.closed||0};for(var c in this._columnLabels){var e=$P.urlencode(this._columnLabels[c]);if(e==="CLOSED"||e==="TOTAL"){continue}b.aggregate["ontime_"+e]=b["ontime_"+e]||0;b.aggregate["overdue_"+e]=b["overdue_"+e]||0}if(YAHOO.lang.isValue(f.children)&&f.children.length>0){for(var c in f.children){var g=f.children[c];var d=g.nodeData;this._preprocessTreeData(g);b.aggregate.total+=d.aggregate.total;b.aggregate.closed+=d.aggregate.closed;for(var c in this._columnLabels){var e=$P.urlencode(this._columnLabels[c]);if(e==="CLOSED"||e==="TOTAL"){continue}b.aggregate["ontime_"+e]+=d.aggregate["ontime_"+e];b.aggregate["overdue_"+e]+=d.aggregate["overdue_"+e]}}}},_renderCell:function(b,c,d,m){if(d==0){b.style.minWidth="15em";b.style.height="2.5em";b.style.overflow="hidden";var f=document.createElement("img");f.className="icon";f.src="/images/"+c.icon+".png";b.appendChild(f);b.appendChild(document.createTextNode(c.label));b.appendChild(document.createElement("br"));b.appendChild(document.createTextNode(c.typeLabel))}else{var i;b.style.textAlign="center";b.style.padding="0px";var e=$P.urlencode(this._columnLabels[d-1]);var k=document.createElement("a");var l=this._makeUrl(true,e,m,c.rowLabel,c.searchKey);var g=this._makeUrl(false,e,m,c.rowLabel,c.searchKey);if(m==Fisma.TreeTable.NodeState.COLLAPSED){c=c.aggregate}if(e=="CLOSED"){k.href=l;b.appendChild(k);k.appendChild(document.createTextNode(c.closed||0))}else{if(e=="TOTAL"){k.href=l;b.appendChild(k);k.appendChild(document.createTextNode(c.total||0))}else{var j=c["ontime_"+e]||0;var h=c["overdue_"+e]||0;if(j==0&&h==0){b.className="ontime";b.appendChild(document.createTextNode("-"))}else{if(j>0&&h==0){b.className="ontime";b.appendChild(k);k.href=l;k.appendChild(document.createTextNode(j||0))}else{if(j==0&&h>0){b.className="overdue";b.appendChild(k);k.href=g;k.appendChild(document.createTextNode(h||0))}else{this._renderSplitCell(b,j,l,h,g)}}}}}}},_renderSplitCell:function(c,l,m,j,h){var i=document.createElement("table");i.style.width="100%";i.style.height="100%";i.style.marginBottom="0px";c.appendChild(i);var k=i.insertRow(i.rows.length);var f=document.createElement("td");k.appendChild(f);f.style.borderWidth="0px";f.style.borderBottomWidth="1px";f.className="ontime";var d=document.createElement("a");f.appendChild(d);d.appendChild(document.createTextNode(l));d.href=m;var g=i.insertRow(i.rows.length);var e=document.createElement("td");g.appendChild(e);e.style.border="none";e.className="overdue";var b=document.createElement("a");e.appendChild(b);b.appendChild(document.createTextNode(j));b.href=h},changeViewType:function(c,b){this.setViewType(b.options[b.selectedIndex].value);this.onViewTypeChange.fire(this._currentViewType);this.reloadData()},setViewType:function(b){if(a.SUMMARY_TYPES.hasOwnProperty(b)){this._currentViewType=b}else{throw"Unexpected view type: ("+b+")"}},_getNumberHeaderRows:function(){return 3},_makeUrl:function(h,c,f,i,g){var e="/finding/remediation/list?q=";c=$P.urldecode(c);if(c!="TOTAL"&&c!="OPEN"){e+="/denormalizedStatus/textExactMatch/"+encodeURIComponent(c)}else{if(c=="OPEN"){e+="/denormalizedStatus/textNotExactMatch/CLOSED"}}if(f==Fisma.TreeTable.NodeState.COLLAPSED){if(this._currentViewType=="systemAggregation"){e+="/"+g+"/systemAggregationSubtree/"+encodeURIComponent(i)}else{e+="/"+g+"/organizationSubtree/"+encodeURIComponent(i)}}else{e+="/"+g+"/textExactMatch/"+encodeURIComponent(i)}if(c!="TOTAL"&&c!="CLOSED"){var d=new Date();var b=d.getFullYear()+"-"+(d.getMonth()+1)+"-"+d.getDate();if(h){e+="/nextDueDate/dateAfter/"+b}else{e+="/nextDueDate/dateBefore/"+b}}msSelect=this._filters.mitigationType.select;msValue=msSelect.options[msSelect.selectedIndex].value;if(msValue!="none"){e+="/type/enumIs/"+encodeURIComponent(msValue)}sourceSelect=this._filters.findingSource.select;sourceValue=sourceSelect.options[sourceSelect.selectedIndex].value;sourceLabel=sourceSelect.options[sourceSelect.selectedIndex].text;if(sourceValue!="none"){e+="/source/textExactMatch/"+encodeURIComponent(sourceLabel)}return e},setTooltip:function(b,c){this._tooltips[b]=c}});Fisma.FindingSummary=a})();