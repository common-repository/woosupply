jQuery(function($){function lws_woosupply_country_select_changed(){var states=$("#"+$(this).data("stateid"));if(states.length>0){if(states.hasClass("lws_woosupply_states_autocomplete")){states.removeClass("lws_woosupply_states_autocomplete");states.val("");states.lac_select("destroy")}if(states.closest(".lws_state_line_removable").length>0){states.closest(".lws_state_line_removable").hide();states.closest(".lws_state_line_removable").prev().hide()}else{states.hide()}var countries=lwsBase64.toObj($(this).data("source"));var value=$(this).val();if(value.length>0&&countries[value]!=undefined&&countries[value]["states"]!=undefined&&Object.keys(countries[value]["states"]).length>0){states.data("source",lwsBase64.fromObj(countries[value]["states"]));states.show().lac_select();states.addClass("lws_woosupply_states_autocomplete");if(states.closest(".lws_state_line_removable").length>0){states.closest(".lws_state_line_removable").show();states.closest(".lws_state_line_removable").prev().show()}}}}$(".lws_woosupply_country_select").each(lws_woosupply_country_select_changed);$(".lws_woosupply_country_select").change(lws_woosupply_country_select_changed)});
