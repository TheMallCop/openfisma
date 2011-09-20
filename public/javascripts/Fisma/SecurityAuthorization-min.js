Fisma.SecurityAuthorization={selectControlsTable:null,addControlPanel:null,selectControlsDialog:null,tabView:null,overview:null,confirmImportBaselineControls:function(b,a){confirmation={func:Fisma.SecurityAuthorization.importBaselineControls,args:[a],text:"Are you sure you want to import the baseline controls for this information system?"};Fisma.Util.showConfirmDialog(null,confirmation)},importBaselineControls:function(a){var d="<img src='/images/loading_bar.gif'>";var b={width:"20em",modal:true,close:false};var c=Fisma.HtmlPanel.showPanel("Importing baseline controls…",d,null,b);YAHOO.util.Connect.asyncRequest("GET","/sa/security-authorization/import-baseline-security-controls/id/"+a+"/format/json",{success:function(j){try{var f=YAHOO.lang.JSON.parse(j.responseText).response;if(f.success){var e=document.getElementById("no-security-controls-warning");if(e){e.style.display="none"}var h=Fisma.SecurityAuthorization.selectControlsTable;h.showTableMessage("Loading baseline controls...");h.getDataSource().sendRequest("",{success:h.onDataReturnInitializeTable,scope:h});h.on("dataReturnEvent",function(){c.destroy()});var i=Fisma.SecurityAuthorization.overview;if(YAHOO.lang.isValue(i)){i.updateStepProgress(3,null,f.payload.controlCount);i.updateStepProgress(4,null,f.payload.controlCount)}}else{Fisma.Util.showAlertDialog("An error occurred: "+f.message);c.destroy()}}catch(g){Fisma.Util.showAlertDialog("An unexpected error occurred: "+g);c.destroy()}},failure:function(e){Fisma.Util.showAlertDialog("An unexpected error occurred.");c.destroy()}},null)},showPanel:function(b){var a=new YAHOO.widget.Panel("panel",{modal:true,close:true});a.setHeader("Select System");a.setBody("Loading...");a.render(document.body);a.center();a.show();a.hideEvent.subscribe(function(){Fisma.SecurityAuthorization.cancelPanel.call(Fisma.SecurityAuthorization)});Fisma.SecurityAuthorization.yuiPanel=a;var c="/system/create/format/html";YAHOO.util.Connect.asyncRequest("GET",c,{success:function(d){d.argument.setBody(d.responseText);d.argument.center()},failure:function(d){d.argument.setBody("The content for this panel could not be loaded.");d.argument.center()},argument:a},null)},completeForm:function(a){document.getElementById("completeForm").submit()},addControl:function(b){var a=Fisma.SecurityAuthorization.addControlPanel;YAHOO.util.Connect.setForm(b);YAHOO.util.Connect.asyncRequest("POST","/sa/security-authorization/add-control/format/json",{success:function(g){try{var d=YAHOO.lang.JSON.parse(g.responseText).response;if(d.success){var c=document.getElementById("no-security-controls-warning");if(c){c.style.display="none"}var f=Fisma.SecurityAuthorization.selectControlsTable;f.showTableMessage("Updating list of controls…");f.getDataSource().sendRequest("",{success:f.onDataReturnInitializeTable,scope:f});f.on("dataReturnEvent",function(){a.destroy()});if(YAHOO.lang.isValue(Fisma.SecurityAuthorization.overview)){Fisma.SecurityAuthorization.overview.incrementStepDenominator(3);Fisma.SecurityAuthorization.overview.incrementStepDenominator(4)}}else{Fisma.Util.showAlertDialog("An error occurred: "+d.message);a.destroy()}}catch(e){Fisma.Util.showAlertDialog("An unexpected error occurred: "+e);a.destroy()}},failure:function(c){Fisma.Util.showAlertDialog("An unexpected error occurred.");a.destroy()}},null)},showAddControlForm:function(d,e){var f=e,a=Fisma.HtmlPanel.showPanel("Add Security Control",null,null,{modal:true}),b="/sa/security-authorization/show-add-control-form/format/html/id/"+f;var c={success:function(h){var g=h.argument;g.setBody(h.responseText);g.center()},failure:function(h){alert('Error getting "add control" form: '+h.statusText);var g=h.argument;g.destroy()},argument:a};Fisma.SecurityAuthorization.addControlPanel=a;YAHOO.util.Connect.asyncRequest("GET",b,c,null)},tableFormatEnhancements:function(e,a,d){var g=a.getData();var c=g.selectedEnhancements_selectedEnhancements;var f=g.definedEnhancements_availableEnhancements;if(Number(f)===0){e.innerHTML="<i>N/A</i>"}else{e.innerHTML=c+" / "+f+" ";var b=document.createElement("a");b.innerHTML="Edit";b.href="#";e.appendChild(b);YAHOO.util.Event.addListener(b,"click",Fisma.SecurityAuthorization.editEnhancements,{elem:e,record:a,column:d},this)}},editEnhancements:function(d,a){var c=a.record.getData().instance_securityAuthorizationId;var e=a.record.getData().definition_id;var b=new Fisma.SecurityAuthorization.EditEnhancementsDialog(c,e);b.show()},showInformationTypes:function(){document.getElementById("addInformationTypes").style.display="block"},addInformationType:function(b,a,c,d){b.innerHTML="<a href='/system/add-information-type/id/"+a.getData("system")+"/sitId/"+d+"'>Add</a>"},removeInformationType:function(b,a,c,d){b.innerHTML="<a href='/system/remove-information-type/id/"+a.getData("system")+"/sitId/"+d+"'>Remove</a>"},handleAvailableInformationTypesTableClick:function(d,f){var e=d.target;var b=Fisma.SecurityAuthorization.availableInformationTypesTable.getRecord(e);var c=b.getData("id");var a="id="+f+"&sitId="+c;YAHOO.util.Connect.asyncRequest("POST","/sa/information-type/add-information-type/format/json",{success:function(k){try{var h=YAHOO.lang.JSON.parse(k.responseText).response;if(h.success){var g=document.getElementById("addInformationTypes");if(g){g.style.display="none"}var j=Fisma.SecurityAuthorization.assignedInformationTypesTable;document.getElementById("addInformationTypes").style.display="block";j.showTableMessage("Updating list of information types…");j.getDataSource().sendRequest("",{success:j.onDataReturnInitializeTable,scope:j});j.on("dataReturnEvent",function(){})}else{Fisma.Util.showAlertDialog("An error occurred: "+h.message)}}catch(i){Fisma.Util.showAlertDialog("An unexpected error occurred: "+i)}},failure:function(g){Fisma.Util.showAlertDialog("An unexpected error occurred.")}},a)},tableFormatEnhancements:function(e,a,d){var g=a.getData();var c=g.selectedEnhancements_selectedEnhancements;var f=g.definedEnhancements_availableEnhancements;if(Number(f)===0){e.innerHTML="<i>N/A</i>"}else{e.innerHTML=c+" / "+f+" ";var b=document.createElement("a");b.innerHTML="Edit";b.href="#";e.appendChild(b);YAHOO.util.Event.addListener(b,"click",Fisma.SecurityAuthorization.editEnhancements,{elem:e,record:a,column:d},this)}},editEnhancements:function(d,a){var c=a.record.getData().instance_securityAuthorizationId;var e=a.record.getData().definition_id;var b=new Fisma.SecurityAuthorization.EditEnhancementsDialog(c,e);b.show()}};Fisma.SecurityAuthorization.EditEnhancementsDialog=function(a,c){var b="/sa/security-authorization/edit-enhancements/id/"+a+"/controlId/"+c+"/format/json";YAHOO.widget.Panel.superclass.constructor.call(this,YAHOO.util.Dom.generateId(),{modal:true});this._showLoadingMessage();this._requestForm(b)};YAHOO.extend(Fisma.SecurityAuthorization.EditEnhancementsDialog,YAHOO.widget.Panel,{_showLoadingMessage:function(){this.setBody("Loading…");this.render(document.body);this.center();this.show()},_requestForm:function(a){var b={success:this._loadForm,failure:function(c){Fisma.Util.showAlertDialog("An unexpected error occurred.");this.destroy()},scope:this};YAHOO.util.Connect.asyncRequest("GET",a,b,null)},_loadForm:function(c){try{var a=YAHOO.lang.JSON.parse(c.responseText);console.log(a);this.setBody(a);this.center()}catch(b){Fisma.Util.showAlertDialog("An unexpected error occurred: "+b);this.hide()}}});