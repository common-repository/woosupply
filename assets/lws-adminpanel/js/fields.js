(function($){$.fn.lwsFieldValue=function(){if(this.prop("tagName").toLowerCase()=="input"){if(this.attr("type")=="checkbox")return this.prop("checked")?this.val():"";else if(this.attr("type")=="radio")return this.prop("checked")?this.val():"";else return this.val()}else return this.text()}})(jQuery);jQuery(function($){$("form").keypress(function(e){e=e||event;var txtArea=/textarea/i.test((e.target||e.srcElement).tagName);var noenter=txtArea||(e.keyCode||e.which||e.charCode||0)!==13;if(!noenter){e.preventDefault();$(e.target||e.srcElement).trigger("change");return true}});$("select.lws-select-input").each(function(){var me=$(this);me.selectmenu({change:function(event,data){if(me.val()!=data.item.value)me.val(data.item.value);me.trigger("change")}});me.change(function(){try{me.selectmenu("refresh")}catch(e){}});if(me.data("class")!=undefined)me.next(".ui-selectmenu-button").addClass(me.data("class"))});$(".lws-group-descr").each(function(){if($(this).outerHeight()>50){var conteneur=$(this);var text=$(this).find(".lws-group-descr-text");var button=$(this).find(".lws-group-descr-button");var shadow=$(this).find(".lws-group-descr-shadow");conteneur.css("height","40px");text.css("height","40px");button.css("display","flex");shadow.css("display","block");button.on("click",function(event){if(button.hasClass("lws-icon lws-icon-nav-down")){conteneur.css("height","auto");text.css("height","auto");button.removeClass("lws-icon lws-icon-nav-down").addClass("lws-icon lws-icon-nav-up");shadow.css("display","none")}else{button.removeClass("lws-icon lws-icon-nav-up").addClass("lws-icon lws-icon-nav-down");conteneur.css("height","40px");text.css("height","40px");shadow.css("display","block")}})}});$("body").on("click",".lws_adm_btn_trigger",function(){var main=$(this);if(main.hasClass("disabled"))return false;main.addClass("disabled");if(!main.find(".lws-loader").length)main.append($("<div>",{class:"lws-loader"}).append($("<div>",{class:"animation"})));var obj=$(this).parents(".lws-form-div").lwsReadForm();var data={action:"lws_adminpanel_field_button",button:$(this).attr("id"),form:lwsBase64.fromObj(obj)};var page=$('input[name="option_page"]');if(page.length)data.option_page=page.val();var tab=$('input[name="tab"]');if(tab.length)data.tab=tab.val();$.ajax({dataType:"json",method:"POST",url:lws_ajax_url,data:data,success:function(response){main.removeClass("disabled");main.find(".lws-loader").remove();if(0!=response&&response.status){if(response.data!=undefined)main.next(".lws-adm-btn-trigger-response").html(response.data)}else{alert(lws_adminpanel.triggerError)}}}).fail(function(d,textStatus,error){main.removeClass("disabled");main.find(".lws-loader").remove();main.replaceWith("<p class='lws-error'>Trigger error, status: "+textStatus+", error: "+error+"</p>").show()});return false});$("body").on("click",".lws_adm_btn_group_submit",function(e){e.preventDefault();var main=$(this);if(main.hasClass("disabled"))return false;main.addClass("disabled");if(!main.find(".lws-loader").length)main.append($("<div>",{class:"lws-loader"}).append($("<div>",{class:"animation"})));setTimeout(function(){main.removeClass("disabled");main.find(".lws-loader").remove()},5e3);var form=$("<form>",{method:main.data("method"),action:"get"==main.data("method")?lws_ajax_url:main.data("action"),style:"display: none;"});if("get"==main.data("method"))form.append($("<input>",{name:"action",value:main.data("action")}));form.append($(this).closest(".lws-form-div").clone());form.appendTo("body").submit();setTimeout(function(){form.remove()},1e3);return false});$(document).click(function(e){if($(e.target).closest(".lwss-disable-on-clic-out").length==0){if($(e.target).closest(".lwss-hide-on-clic-out").length==0)$(".lwss-hide-on-clic-out").fadeOut();if($(e.target).closest(".lwss-fold-on-clic-out").length==0)$(".lwss-fold-on-clic-out").slideUp()}});$(document).keyup(function(e){if((e.keyCode||e.which||e.charCode||0)===27){$(".lwss-hide-on-clic-out").fadeOut();$(".lwss-fold-on-clic-out").slideUp()}});$("form").on("change","input, textarea, select, .gizmo",function(){var elt=$(this);if(!elt.hasClass("lws-force-confirm")){if(elt.hasClass("lws-ignore-confirm"))return;var data=elt.data("class");if(undefined!=data&&data.includes("lws-ignore-confirm"))return}if(!$(this).closest(".lws_editlist").length)window.lwsInputchanged=true;window.onbeforeunload=function(){return lws_adminpanel.confirmLeave}});$("form").submit(function(){if(window.onbeforeunload!=undefined&&($(".lws-editlist-btn-disabled").length>0||$(".lws_editlist .lws_editlist_modal_form:visible").length>0)){alert(lws_adminpanel.editlistOnHold);return false}if(document.lwsForceConfirm==undefined){window.onbeforeunload=undefined;document.lwsForceConfirm=undefined}});$(".lws-adminpanel-singular-delete-button").click(function(){var origin=$(this);$(".lws-adminpanel-singular-delete-confirmation").dialog({autoOpen:true,height:200,width:480,modal:true,buttons:[{text:origin.data("yes")!=undefined?origin.data("yes"):"Confirm",icon:"ui-icon-alert",click:function(){document.location=origin.attr("href")}},{text:origin.data("no")!=undefined?origin.data("no"):"Cancel",icon:"ui-icon-cancel",click:function(){$(this).dialog("close")}}],open:function(){$(".ui-dialog :button").blur();$(".ui-dialog :button:last").focus()}});return false});$("body").on("mouseover",".lws_tooltips_button",function(event){$(".lws_tooltips_wrapper").hide();var content=$(event.target).find(".lws_tooltips_wrapper");content.toggle(!content.is(":visible"));return false});$("body").on("mouseout",".lws_tooltips_button",function(event){$(".lws_tooltips_wrapper").hide();var content=$(event.target).find(".lws_tooltips_wrapper");content.toggle(content.is(":visible"));return false});$("body").on("click",".lws_ui_value_copy .copy",function(event){var content=$(event.target).closest(".lws_ui_value_copy").find(".content");window.getSelection().selectAllChildren(content.get(0));document.execCommand("copy")});$(".lws_adm_field_require").each(function(){var me=$(this);var selector=me.data("selector");var value=me.data("value");var cmp=me.data("operator");var origin=$(selector);if(origin.length>0){var fct=function(){if("!="==cmp)me.toggleClass("lws_adm_field_hidden",origin.lwsFieldValue()==value);else if("match"==cmp)me.toggleClass("lws_adm_field_hidden",!origin.lwsFieldValue().match(value));else me.toggleClass("lws_adm_field_hidden",origin.lwsFieldValue()!=value);if(me.hasClass("lws_adm_field_hidden")){var helpBtn=me.find(".bt-field-help.in-use");if(helpBtn.length)helpBtn.trigger("click")}};origin.change(fct);fct()}})});
