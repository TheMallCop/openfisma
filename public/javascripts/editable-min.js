function setupEditFields(){var a=YAHOO.util.Selector.query(".editable");YAHOO.util.Event.on(a,"click",function(e){var i=this.getAttribute("target");YAHOO.util.Dom.removeClass(this,"editable");this.removeAttribute("target");if(i){var j=document.getElementById(i);var b=j.getAttribute("name");var k=j.getAttribute("type");var c=j.getAttribute("href");var g=j.className;var h=j.offsetWidth;var m=j.offsetHeight;var d=j.innerText?j.innerText:j.textContent;var n=j.innerHTML;if(k=="text"){j.outerHTML='<input length="50" name="'+b+'" id="'+i+'" class="'+g+'" type="text" />';textEl=document.getElementById(i);textEl.setAttribute("value",d.trim());if(h<200){h=200}textEl.style.width=(h-10)+"px";if(g=="date"){var j=document.getElementById(i);j.onfocus=function(){showCalendar(i,i+"_show")}}}else{if(k=="textarea"){var p=j.getAttribute("rows");var f=j.getAttribute("cols");j.outerHTML='<textarea id="'+b+'" rows="'+p+'" cols="'+f+'" name="'+b+'"></textarea>';var l=document.getElementById(b);l.value=n;l.style.width=h+"px";l.style.height=m+"px";tinyMCE.execCommand("mceAddControl",true,b)}else{if(val=j.getAttribute("value")){d=val}YAHOO.util.Connect.asyncRequest("GET",c+"value/"+d.trim(),{success:function(q){if(k=="select"){j.outerHTML='<select name="'+b+'">'+q.responseText+"</select>"}},failure:function(q){alert("Failed to load the specified panel.")}},null)}}}})}function validateEcd(){var e=document.getElementById("expectedCompletionDate");var c=e.value;var d=new Date();var a=d.getFullYear();var b=d.getMonth();b=b+1;if(b<10){b="0"+b}var f=d.getDate();if(f<10){f="0"+f}if(c.replace(/\-/g,"")<=parseInt(""+a+""+b+""+f)){alert("Warning: You entered an ECD date in the past.")}}if(window.HTMLElement){HTMLElement.prototype.__defineSetter__("outerHTML",function(b){var a=this.ownerDocument.createRange();a.setStartBefore(this);var c=a.createContextualFragment(b);this.parentNode.replaceChild(c,this);return b});HTMLElement.prototype.__defineGetter__("outerHTML",function(){var a;var b=this.attributes;var d="<"+this.tagName.toLowerCase();for(var c=0;c<b.length;c++){a=b[c];if(a.specified){d+=" "+a.name+'="'+a.value+'"'}}if(!this.canHaveChildren){return d+">"}return d+">"+this.innerHTML+"</"+this.tagName.toLowerCase()+">"});HTMLElement.prototype.__defineGetter__("canHaveChildren",function(){switch(this.tagName.toLowerCase()){case"area":case"base":case"basefont":case"col":case"frame":case"hr":case"img":case"br":case"input":case"isindex":case"link":case"meta":case"param":return false}return true})};