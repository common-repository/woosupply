(function($){$.fn.lwsNoticesFlagSave=function(){var wrapper=this;var markClass="seen";var type=wrapper.closest(".top-menu-item").data("type");if(undefined==type)type="undefined";var seen=JSON.parse(localStorage.getItem("lws_seen_notices_"+type)||"[]");wrapper.children().each(function(){$(this).removeClass(markClass);var md5=$.lwsMD5(this.innerHTML);if(!seen.includes(md5))seen.push(md5);$(this).addClass(markClass)});localStorage.setItem("lws_seen_notices_"+type,JSON.stringify(seen))};$.fn.lwsNoticesFlagMark=function(){var wrapper=this;var markClass="seen";var type=wrapper.closest(".top-menu-item").data("type");if(undefined==type)type="undefined";var seen=JSON.parse(localStorage.getItem("lws_seen_notices_"+type)||"[]");var notices=[];wrapper.children().each(function(){var md5=$.lwsMD5(this.innerHTML);notices.push(md5);if(seen.includes(md5))$(this).addClass(markClass)});seen=$.grep(seen,function(md5){return notices.includes(md5)});localStorage.setItem("lws_seen_notices_"+type,JSON.stringify(seen))};$.fn.lwsNoticesFlagRemove=function(){var wrapper=this.closest(".lws-adm-notices-wrapper");var markClass="seen";var type=wrapper.closest(".top-menu-item").data("type");if(undefined==type)type="undefined";var seen=JSON.parse(localStorage.getItem("lws_seen_notices_"+type)||"[]");this.removeClass(markClass);var md5=$.lwsMD5(this[0].innerHTML);seen=$.grep(seen,function(i){return i!=md5});localStorage.setItem("lws_seen_notices_"+type,JSON.stringify(seen))}})(jQuery);jQuery(function($){$("#discard_changes").click(function(e){window.onbeforeunload=undefined;document.lwsForceConfirm=undefined;$("body").css("opacity",.5);document.location.reload(true)});var minTabsSize=0;var expandedGroups=0;$("#lws_am_top_item").on("click",function(e){if(!$(this).hasClass("menu-open")){$(this).addClass("menu-open");$("#lws_am_top_menu").css("display","flex");event.stopPropagation();return false}});$(document).on("click",function(e){if(!$(e.target).closest("#lws_am_top_menu").length){if($("#lws_am_top_item").hasClass("menu-open")){$("#lws_am_top_item").removeClass("menu-open");$("#lws_am_top_menu").css("display","none")}}});$(document).on("click",function(e){if(!$(e.target).closest(".lws-adm-notices-wrapper").length){$(".lws-adm-notices-wrapper").css("display","none")}});$(document).on("click",".lws-notice .dismiss-btn",function(e){e.preventDefault();var notice=$(e.target).closest(".lws-notice");notice.lwsNoticesFlagRemove();notice.addClass("closed").hide();var forget=$(e.target).data("forget");if(undefined!=forget){$.ajax({url:lws_ajax_url,data:{action:"lws_adminpanel_forget_notice",key:forget}})}var wrapper=notice.closest(".lws-adm-notices-wrapper");if(!wrapper.children(":not(.closed)").length)wrapper.remove();return false});$(document).on("click",".lws-adminpanel-transient-notices .lws-notice-dismiss",function(e){e.preventDefault();var notice=$(e.target).closest(".lws-adminpanel-notice").addClass("closed").hide();var wrapper=notice.closest(".lws-adminpanel-transient-notices");if(!wrapper.children(":not(.closed)").length)wrapper.remove();return false});$(".check_notices_count_at_start").each(function(){var item=$(this).closest(".top-menu-item");var wrapper=item.find(".lws-adm-notices-wrapper");wrapper.children(".lws-adminpanel-notice").remove();wrapper.lwsNoticesFlagMark();var counter=item.find(".notif-counter");if(counter.length){var c=wrapper.children(":not(.seen)").length;counter.html(c).toggle(c>0)}if(!wrapper.children().length){item.find(".top-menu-item-icon").removeClass("lws-icon-notifs-on").addClass("lws-icon-notifs-off");wrapper.remove()}});$("#lws_am_top_notif_counter").on("update",function(){var c=0;var topCounter=$(this);topCounter.closest(".admin-menu").find(".top-menu-item").each(function(){if($(this).children(".lws-icon-notifs-on").length){var counter=$(this).find(".notif-counter");if(counter.length&&counter.text().length)c+=parseInt(counter.text(),10)}});topCounter.html(c);topCounter.toggle(c>0)}).trigger("update");$(".show_notices").on("click",function(e){$(".lws-adm-notices-wrapper").css("display","none");var item=$(this).closest(".top-menu-item");var wrapper=item.find(".lws-adm-notices-wrapper");wrapper.css("display","grid").lwsNoticesFlagSave();item.find(".top-menu-item-icon").removeClass("lws-icon-notifs-on").addClass("lws-icon-notifs-off");item.find(".notif-counter").hide();$("#lws_am_top_notif_counter").trigger("update");e.preventDefault();return false});$(".bt-field-help").on("click",function(e){var help=$(this).parent().prev(".field-help");if($(help).hasClass("displayed")){$(help).removeClass("displayed");$(this).removeClass("in-use")}else{$(help).addClass("displayed");$(this).addClass("in-use")}});$(".second-row-button.shrinked").on("mouseover",function(e){var size=$(this).find(".button-icon").width()+$(this).find(".button-text").width()+40;$(this).css("flex-basis",size+"px")});$(".second-row-button.shrinked").on("mouseout",function(e){$(this).css("flex-basis","45px")});$(".small-screen-tabs").on("click",function(e){$(this).find(".vertical-wrapper").css("display","flex")});$(".small-screen-tabs").on("mouseleave",function(e){$(this).find(".vertical-wrapper").css("display","none")});function calculateminTabsSize(){$(".tab-menu-item").each(function(){iconSize=$(this).find(".menu-item-icon").width();textSize=$(this).find(".menu-item-text").width();if(iconSize!=null)minTabsSize+=iconSize;if(textSize!=null)minTabsSize+=textSize})}function setContentStraight(){if(minTabsSize==0)calculateminTabsSize();tabMenu=$(".tab-menu");if(tabMenu.width()<minTabsSize&&!tabMenu.hasClass("minified")){tabMenu.addClass("minified");smallMenu=$(".small-screen-tabs");smallContainer=smallMenu.find(".vertical-wrapper");$(".tab-menu-item").each(function(){$(this).detach().appendTo(smallContainer)});smallMenu.css("display","flex")}if(tabMenu.width()>=minTabsSize&&tabMenu.hasClass("minified")){tabMenu.removeClass("minified");smallMenu=$(".small-screen-tabs");smallContainer=smallMenu.find(".vertical-wrapper");$(".tab-menu-item").each(function(){$(this).detach().appendTo(tabMenu)});smallMenu.css("display","none")}groupgrid=$(".groups-grid");if(groupgrid.length){width=$(groupgrid).width();if(width<500){$(groupgrid).addClass("vertical")}else{$(groupgrid).removeClass("vertical")}if(width<1e3){$(groupgrid).addClass("onecol")}else{$(groupgrid).removeClass("onecol")}}}$("#expand_groups").on("click",function(e){newvalue=$(button).find(".button-icon").hasClass("lws-icon-minus")?"folded":"unfolded";$(".group-item").each(function(){localStorage.setItem($(this).attr("id"),newvalue)});handleCollapsedGroups()});function setExpandAllButton(){button=$("#expand_groups");icon=$(button).find(".button-icon");text=$(button).find(".button-text");if(expandedGroups>0){$(icon).removeClass("lws-icon-plus").addClass("lws-icon-minus");$(text).html(button_texts.collapse)}else{$(icon).removeClass("lws-icon-minus").addClass("lws-icon-plus");$(text).html(button_texts.expand)}}$(".group-title-line .group-icon, .group-title-line .group-title, .group-title-line .group-expand").on("click",function(e){group=$(this).closest(".group-item");button=group.find(".group-expand");if(localStorage.getItem($(group).attr("id"))=="folded"){$(button).removeClass("lws-icon-plus").addClass("lws-icon-minus");$(group).find(".group-content").removeClass("collapsed");localStorage.setItem($(group).attr("id"),"unfolded");expandedGroups+=1}else{$(button).removeClass("lws-icon-minus").addClass("lws-icon-plus");$(group).find(".group-content").addClass("collapsed");localStorage.setItem($(group).attr("id"),"folded");expandedGroups-=1}setExpandAllButton()});function handleCollapsedGroups(){expandedGroups=0;$(".group-item").each(function(){button=$(this).find(".group-expand");if(localStorage.getItem($(this).attr("id"))){state=localStorage.getItem($(this).attr("id"))}else{state=$(button).hasClass("lws-icon-minus")?"unfolded":"folded";localStorage.setItem($(this).attr("id"),state)}if(state=="unfolded"){$(button).removeClass("lws-icon-plus").addClass("lws-icon-minus");$(this).find(".group-content").removeClass("collapsed");expandedGroups+=1}else{$(button).removeClass("lws-icon-minus").addClass("lws-icon-plus");$(this).find(".group-content").addClass("collapsed")}});setExpandAllButton()}$(window).resize(function(){setContentStraight()});setContentStraight();handleCollapsedGroups();$(".lws-sticky-panel ul.breadcrumb li a[data-id]").each(function(){var link=$(this);var filters=localStorage.getItem("lws_backward_filters_"+link.data("id"));if(filters){var params=lwsBase64.toObj(filters);var url=link.data("href");var questionIndex=url.indexOf("?");if(questionIndex>=0){url.substring(questionIndex).replace(/[?&]+([^=&]+)=([^&]*)/gi,function(str,key,value){params[key]=value});url=url.substring(0,questionIndex)}link.attr("href",url+"?"+$.param(params))}})});
