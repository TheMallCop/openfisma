Fisma.Search.Panel=function(b,f){var h=b;if(0==h.length){throw"Field array cannot be empty"}h.sort(function(j,i){if(j.label<i.label){return -1}else{if(j.label>i.label){return 1}else{return 0}}});this.searchableFields={};for(var c in h){var d=h[c];if(d.hidden!==true){this.searchableFields[c]=d}}this.defaultQueryTokens=null;if(f){var e=f.split("/");for(var c in e){var a=e[c];var g=parseInt(c);if("advanced"==a&&e.length>(g+1)){e.splice(0,g+1);this.defaultQueryTokens=e;break}}}};Fisma.Search.Panel.prototype={container:null,criteria:[],render:function(b){this.container=b;if(this.defaultQueryTokens){var g=0;while(this.defaultQueryTokens.length>g){var i=this.defaultQueryTokens[g];g++;var c=this.defaultQueryTokens[g];g++;var e=this.getFieldDefinition(i);var j=new Fisma.Search.Criteria(this,this.searchableFields);var d=j.getCriteriaDefinition(e);var k=this.getNumberOfOperands(e,c,d);operands=[];for(;k>0;k--){operands.push(this.defaultQueryTokens[g]);g++}var f=j.render(i,c,operands);this.container.appendChild(j.container);this.criteria.push(j)}Fisma.Search.toggleAdvancedSearchPanel();Fisma.Search.onSetTable(function(){var l=document.getElementById("searchForm");setTimeout(function(){Fisma.Search.handleSearchEvent(l)},1)})}else{var h=new Fisma.Search.Criteria(this,this.searchableFields);this.criteria.push(h);var a=h.render(this.searchableFields[0].name);h.setRemoveButtonEnabled(false);this.container.appendChild(a)}},addCriteria:function(b){if(1==this.criteria.length){this.criteria[0].setRemoveButtonEnabled(true)}var d=new Fisma.Search.Criteria(this,this.searchableFields);this.criteria.push(d);var a=this.criteria.length-1;var c=d.render(this.searchableFields[a].name);this.container.insertBefore(c,b.nextSibling)},removeCriteria:function(b){for(var a in this.criteria){var c=this.criteria[a];if(c.container==b){this.criteria.splice(a,1);break}}if(1==this.criteria.length){this.criteria[0].setRemoveButtonEnabled(false)}this.container.removeChild(b)},getQuery:function(){var c=new Array();for(var a in this.criteria){var b=this.criteria[a];queryPart=b.getQuery();c.push(queryPart)}return c},getFieldDefinition:function(b){for(var a in this.searchableFields){if(this.searchableFields[a].name==b){return this.searchableFields[a]}}throw"No definition for field: "+b},getNumberOfOperands:function(e,b,d){var a=d[b];if(!a){throw"No criteria defined for field ("+e.name+") and operator ("+b+")"}var c=a.query;switch(c){case"noInputs":return 0;break;case"enumSelect":case"oneInput":return 1;break;case"twoInputs":return 2;break;default:throw"Number of operands not defined for query function: "+c;break}}};