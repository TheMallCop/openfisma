/**
 * Copyright (c) 2008 Endeavor Systems, Inc.
 *
 * This file is part of OpenFISMA.
 *
 * OpenFISMA is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * OpenFISMA is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with OpenFISMA.  If not, see {@link http://www.gnu.org/licenses/}.
 *
 * @fileoverview This file contains related javascript code about the feature finding remediation
 *
 * @author    Jackson Yang <yangjianshan@users.sourceforge.net>
 * @copyright (c) Endeavor Systems, Inc. 2009 {@link http://www.endeavorsystems.com}
 * @license   http://www.openfisma.org/content/license
 */

Fisma.Remediation = {
    /**
     * Popup a panel for upload evidence
     * 
     * @return {Boolean} False to interrupt consequent operations
     */
    upload_evidence : function() {

        Fisma.UrlPanel.showPanel(
            'Upload Evidence', 
            '/finding/remediation/upload-form', 
            Fisma.Remediation.upload_evidence_form_init);

        return false;
    },

    /**
     * Initialize another form finding_detail_upload_evidence after panel loaded
     */
    upload_evidence_form_init : function() {
        // Initialize form action from finding_detail.action since they are separated forms and the form from
        // from the panel belongs to document body rather than the form document.finding_detail.But they should
        // have same target action. So set the latter`s action with the former`s.
        document.finding_detail_upload_evidence.action = document.finding_detail.action;
    },

   /**
     * To approve or deny mitigation strategy or evidence with comment
     * 
     * @param {String} action The action name: APPROVED or DENIED
     * @param {String} formId 
     * @param {String} panelTitle the text shows on the panel.
     * @param {int} findingId the id of the current finding.
     */
    remediationAction : function(event, args) {
        var action = args.action;
        var formId = args.formId;
        var panelTitle = args.panelTitle;
        var findingId = args.findingId;
        
        if ('REJECTED' === action) {
            var panel = Fisma.UrlPanel.showPanel(
                panelTitle,
                '/finding/remediation/reject-evidence/id/' + findingId,
                function(){
                    document.finding_detail_reject_evidence.action = document.finding_detail.action;
                    document.getElementById('dialog_close').onclick = function (){
                        panel.destroy();
                        return false;
                    }
                }
            );
        } else {
            var content = document.createElement('div');
            var warning = document.createElement('div');
            warning.className = 'messageBox attention';
            var warn_message = 'WARNING: This action cannot be undone.';
            warning.appendChild(document.createTextNode(warn_message));
            content.appendChild(warning);
            var p = document.createElement('p');
            var c_title;
            if ('APPROVED' === action) {
                c_title = document.createTextNode('Comments (OPTIONAL):');
            } else {
                c_title = document.createTextNode('Comments:');
            }
            p.appendChild(c_title);
            content.appendChild(p);
            var textarea = document.createElement('textarea');
            textarea.id = 'dialog_comment';
            textarea.name = 'comment';
            textarea.rows = 5;
            textarea.cols = 60;
            content.appendChild(textarea);
            var div = document.createElement('div');
            div.className = 'buttonBar';
            content.appendChild(div);
            var confirmButton = document.createElement('button');
            confirmButton.id = 'dialog_continue';
            confirmButton.appendChild(document.createTextNode('Confirm'));
            div.appendChild(confirmButton);
            var cancelButton = document.createElement('button');
            cancelButton.id = 'dialog_close';
            cancelButton.style.marginLeft = '5px';
            cancelButton.appendChild(document.createTextNode('Cancel'));
            div.appendChild(cancelButton);

            var panel = Fisma.HtmlPanel.showPanel(panelTitle, content.innerHTML);

            document.getElementById('dialog_continue').onclick = function (){
                var form2 = document.getElementById(formId);
                var comment = document.getElementById('dialog_comment').value;

                if ('DENIED' === action) { 
                    if (comment.match(/^\s*$/)) {
                        var alertMessage = 'Comments are required.';
                        var config = {zIndex : 10000};
                        Fisma.Util.showAlertDialog(alertMessage, config);
                        return;
                    }
                }

                form2.elements['comment'].value = comment;
                form2.elements['decision'].value = action;

                var sub = document.createElement('input');
                sub.type = 'hidden';
                sub.name = 'submit_msa';
                sub.value = action;
                form2.appendChild(sub);
                form2.submit();
                return;
            };

            document.getElementById('dialog_close').onclick = function (){
                panel.destroy();
                return false;
            }
        }
        return true;
    },

    /**
     * Handle onclick event of the button on the Evidence upload form 
     * to attach one more file
     */
    add_upload_evidence : function() {
        var file_list = document.getElementById('evidence_upload_file_list');

        var new_upload = document.createElement('input');
        new_upload.type = 'file';
        new_upload.name = 'evidence[]';
        new_upload.multiple = true;
        file_list.appendChild(new_upload);

        return false; // to avoid form submission
    },

    /**
     * Handle onclick event of the link on the Evidence list view 
     * to show rejected evidence(s)
     */
    show_rejected_evidences : function() {
        var container = document.getElementById('rejectedEvidencesContainer');
        var trigger = document.getElementById('rejectedEvidencesTrigger-button');
        if (container.style.display != 'block') {
            container.style.display = 'block';
            trigger.innerHTML = 'Click to hide';
        } else {
            container.style.display = 'none';
            trigger.innerHTML = 'Click to display';
        }
    },

    /**
     * Validate the reject_evidence form for required field(s)
     */
    reject_evidence_validate : function() {
        if (document.finding_detail_reject_evidence.comment.value.match(/^\s*$/)) {
            var alertMessage = 'Comments are required.';
            var config = {zIndex : 10000};
            Fisma.Util.showAlertDialog(alertMessage, config);
            return false;
        }
        return true;
    },

    /**
     * Validate the upload_evidence form to check for duplicated uploads
     */
    upload_evidence_validate : function() {
        if (document.finding_detail_upload_evidence.forceSubmit) {
            return true;
        }
        var duplicationDetected = false;
        var message = "WARNING: The following file(s) will be replaced: <ul>";
        
        for (var i = 0; i < document.links.length; i++) {
            var link = document.links[i];
            
            if (link.href.indexOf('downloadevidence') >= 0 && link.firstChild.nodeName == 'DIV') {
                var files = document.finding_detail_upload_evidence['evidence[]'].files;
                if (!files) // this ugly chunk is the workaround for IE7
                {
                    var elements = document.finding_detail_upload_evidence.elements;
                    for (var j = 0; j < elements.length; j++) {
                        if (elements[j].name == 'forceSubmit') {
                            return true;
                        }
                        if (elements[j].name == 'evidence[]') {
                            var fileName = elements[j].value;
                            fileName = fileName.slice(fileName.lastIndexOf('\\')+1);
                            if (fileName == link.firstChild.innerHTML) {
                                duplicationDetected = true;
                                message += "<li>" + fileName + "</li>";
                                break;
                            }
                        }
                    }
                } else {
                    for (var j = 0; j < files.length; j++) {
                        if (files[j].fileName == link.firstChild.innerHTML) {
                            duplicationDetected = true;
                            message += "<li>" + files[j].fileName + "</li>";
                            break;
                        }
                    }
                }
            }
        }

        message += "</ul>Do you want to continue?";
        if (duplicationDetected) {
            Fisma.Util.showConfirmDialog(
                event,
                {
                    text:message,
                    func:'Fisma.Remediation.upload_evidence_confirm',
                    args:[true]
                }
            );
            return false;
        } else {
            return true;
        }
    },

    /**
     * Force the submission of upload_evidence form
     */
    upload_evidence_confirm : function() {
        var forcedIndicator = document.createElement('input');
        forcedIndicator.type = 'hidden';
        forcedIndicator.name = 'forceSubmit';
        forcedIndicator.value = true;
        document.finding_detail_upload_evidence.appendChild(forcedIndicator);
        document.finding_detail_upload_evidence.upload_evidence.click();
    }
};
