Fisma.Highlighter=function(){return{highlightDelimitedText:function(a,b){var h=Fisma.Util.escapeRegexValue(b);var j=new RegExp("^(.*?)"+h+"(.*?)"+h+"(.*?)$");for(var d in a){var e=a[d];if(!e.firstChild||!e.firstChild.firstChild){continue}var g=e.firstChild;if(g&&g.firstChild&&g.firstChild.nodeType!=3){continue}var c=g.firstChild;var k=c.nodeValue;var f=this._getDelimitedRegexMatches(k,j);this._highlightMatches(g,f)}},_getDelimitedRegexMatches:function(h,d){var f=[];var g=null;var e=h;do{g=e.match(d);if(g&&g.length==4){var c=g[1];var a=g[2];var b=g[3];f.push(c);f.push(a);e=b}else{f.push(e);break}}while(g);return f},_highlightMatches:function(a,f){if((f.length>1)&&(f.length%2==1)){a.removeChild(a.firstChild);for(var d in f){var c=f[d];var e=document.createTextNode(c);if(d%2==0){a.appendChild(e)}else{var b=document.createElement("span");b.className="highlight";b.appendChild(e);a.appendChild(b)}}}}}}();