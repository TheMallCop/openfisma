Fisma.Search.CriteriaRenderer=function(){return{betweenDate:function(a,c){var b=document.createElement("input");if(c&&c.length>0){b.value=c[0]}b.type="text";b.className="date";a.appendChild(b);Fisma.Calendar.addCalendarPopupToTextField(b);var d=document.createTextNode(" and ");a.appendChild(d);var e=document.createElement("input");if(c&&c.length>1){e.value=c[1]}e.type="text";e.className="date";a.appendChild(e);Fisma.Calendar.addCalendarPopupToTextField(e)},betweenInteger:function(a,c){var b=document.createElement("input");if(c&&c.length>0){b.value=c[0]}b.type="text";b.className="integer";a.appendChild(b);var d=document.createTextNode(" and ");a.appendChild(d);var e=document.createElement("input");if(c&&c.length>1){e.value=c[1]}e.type="text";e.className="integer";a.appendChild(e)},none:function(a,b){},singleDate:function(a,b){var c=document.createElement("input");c.type="text";c.className="date";if(b&&b.length>0){c.value=b[0]}a.appendChild(c);Fisma.Calendar.addCalendarPopupToTextField(c)},singleInteger:function(a,b){var c=document.createElement("input");c.type="text";c.className="integer";if(b&&b.length>0){c.value=b[0]}a.appendChild(c)},text:function(a,b){var c=document.createElement("input");c.type="text";if(b&&b.length>0){c.value=b[0]}a.appendChild(c)},enumSelect:function(b,g,c){var d=function(l,j,m){var k=m.cfg.getProperty("text");i.set("label",k)};var a=new Array();for(var f in c){var e=c[f];menuItem={text:e,value:e,onclick:{fn:d}};a.push(menuItem)}var h=(g&&g.length>0)?g[0]:c[0];var i=new YAHOO.widget.Button({type:"menu",label:h,menu:a,container:b})}}}();