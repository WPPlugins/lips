// $Id: lips.dev.js 790803 2013-10-20 09:30:50Z bastb $
jQuery(document).ready(function($) {
	var wrapped = $(".wrap h3:not(.nav-tab-wrapper)").wrap("<div class=\"ui-tabs-panel\">");
	wrapped.each(function() {
		$(this).parent().append($(this).parent().nextUntil("div.ui-tabs-panel"));
	});

	$(".ui-tabs-panel").each(function(index) {
		$(this).attr("id", sections[$(this).children("h3").text()]);
		if (index > 0)
			$(this).addClass("ui-tabs-hide");
	});

	$(".ui-tabs").tabs({
		fx: { opacity: "toggle", duration: "fast" }
	});

	$("input[type=text], textarea").each(function() {
		if ($(this).val() == $(this).attr("placeholder") || $(this).val() == "")
			$(this).css("color", "#999");
	});

	$("input[type=text], textarea").focus(function() {
		if ($(this).val() == $(this).attr("placeholder") || $(this).val() == "") {
			$(this).val("");
			$(this).css("color", "#000");
		}
	}).blur(function() {
		if ($(this).val() == "" || $(this).val() == $(this).attr("placeholder")) {
			$(this).val($(this).attr("placeholder"));
			$(this).css("color", "#999");
		}
	});

	var allLanguages = [];
	jQuery("#profile_lang").find('option').each(function(index, option) {
		allLanguages.push(option);
	});

	if (errors != null) {
		$.each(errors, function(key, value) {
			var dlg = $("<div id='" + key + "' />")
			.html(value[1])
			.appendTo("body");

			dlg.dialog({
				dialogClass : "wp-dialog",
				modal: true,
				autoOpen: false,
				closeOnEscape: true,
				title: value[0],
				buttons : [{
					'text' : 'Close',
				    "class": "button-primary",
					'click' : function() {
						$(this).dialog('close');
					}
				}
			]
			}).dialog('open');
		});
	}

	jQuery("#update_profile").change(function() {
		visibility = "none";
		if ( $(this).is(":checked"))  {
			visibility = "block";
		}
		jQuery(".update_profile").css("display", visibility);
	}).change();

	jQuery("#stackexchange_site").change(function() {
		if (jQuery("#stackexchange_site").val() == no_service) {
			jQuery("#stackexchange_login").attr("disabled", true);
		}
		else {
			jQuery("#stackexchange_login").removeAttr("disabled");
		}
	}).change();

	jQuery("#profile_source0, #profile_source1").change(function() {
		var $el = jQuery("#profile_lang").empty();
		if ($(this).val() == "li_profile") {
			jQuery("#keep_local_copy")
				.removeAttr("disabled");
			jQuery.each(allLanguages, function(index, option) {
				$el.append(option);
			});
		}
		else {
			jQuery("#keep_local_copy")
				.attr("disabled", "disabled");
			jQuery.each(language_specific, function(key, value) {
				$el.append($("<option></option>")
				  .attr("value", key).text(value));
			});
		}
	})

	jQuery("#installed_page_template").change(function() {
		meta_id = this.id + "-meta";
		meta_text = "";
		id = jQuery("#installed_page_template :selected").val();
		if ($(this).val() == "custom") {
			jQuery(".custom_page_template").css("display", "block");
			jQuery(".statics_container").css("display", "none");
		}
		else {
			jQuery("#statics-description").html(statics.replace("%tpl", jQuery("#installed_page_template :selected").text()));
			meta_text = '<a class="lips-ext-ref" href="' + sample_links[$(this).val()] + '" target="lips_review">' + sample_link_text + '</a>';
			jQuery(".custom_page_template, .lips-static").css("display", "none");
			jQuery(".statics_container").css("display", "block");
			jQuery(".statics_container, .lips-static."+id).css("display", "block");
			visibility = "block";
			if(0 == jQuery(".lips-static."+id).length) {
				visibility = "none";
			}
			jQuery(".statics_container").css("display", visibility);
		}
		jQuery("#"+meta_id).html(meta_text);
	}).change();

	jQuery("#have_posts").change(function() {
		if ($(this).is(":checked")) {
			jQuery(".has_posts").css("display", "block");
			handlePostContentChange();
		}
		else {
			jQuery(".has_posts").css("display", "none");
		}
	}).change();

	jQuery("#page_template0, #page_template1").change(function() {
		jQuery.each(
			[".page_use_installed_template", ".custom_page_template"],
			function (e, f) {
				jQuery(f).css('display', 'none');
			}
		);
		if (this.id == "page_template1" && $(this).is(":checked")) {
			jQuery(".custom_page_template").css('display', 'block');
		}
		else if (this.id == "page_template0" && $(this).is(":checked")) {
			jQuery(".page_use_installed_template").css('display', 'inline');
		}
	});

	jQuery("#post_template0, #post_template1, #post_template2").change(function() {
		jQuery.each(
			[".post_use_installed_template", ".custom_post_template"],
			function (e, f) {
				jQuery(f).css('display', 'none');
			}
		);
		if (jQuery(".has_posts").css("display") == "block") {
			if (this.id == "post_template1" && $(this).is(":checked")) {
				jQuery(".custom_post_template").css('display', 'block');
			}
			else if (this.id == "post_template2" && $(this).is(":checked")) {
				jQuery(".post_use_installed_template").css('display', 'block');
			}
		}
	});

	jQuery("#enable_profile_data_debug").change(function() {
		visibility = "none";
		if ($(this).is(":checked"))  {
			visibility = "block";
			if (jQuery("#profile_debug_data_page").val() == jQuery("#profile_page").val()) {
				displayPageDuplicatePurposeError(jQuery("#profile_page option:selected").text());
			}
		}
		jQuery(".has_profile_debug").css("display", visibility);
	}).change();

	jQuery("a.nav-tab").on("click", function() {
		// Hide every tab and display only the one being shown
		jQuery("div.ui-tabs-panel").addClass("ui-tabs-hide");
		jQuery($(this).context.hash).removeClass("ui-tabs-hide");
		// Reset classes on the sections and mark the one being shown active
		jQuery("a.nav-tab").removeClass("nav-tab-active");
		jQuery("a.lips-tab-" + $(this).context.hash.substring(1)).addClass("nav-tab-active");
	});

	function handlePostContentChange() {
		jQuery.each([0,1,2], function(index, element) {
			element_id = "#post_template" + index;
			if (jQuery(element_id).is(":checked")) {
				jQuery(element_id).change();
			}
		});
	}

	function submitForm() {
		// The control value is not submitted when the object is disabled.
		// This hack removes the disabled property from the control, including
		// it to the post data.
		jQuery("#keep_local_copy")
			.removeAttr("disabled");
		jQuery("#lips-form").submit();
	}

	function hideStatusFeedback() {
		jQuery("#save").attr('disabled', false);
		jQuery("#lips-saving").css('visibility', 'hidden');
	}

	function handleTimeout() {
		jQuery("#lips-err-detail").removeClass('lips-err-monospace');
		jQuery("#lips-err-detail").text("Timeout");
		jQuery("#lips-err-box").dialog("open", title, "Problem contacting LinkedIn&reg;");
	}

	function handlePageCreationResult(data, on_success) {
		sep_pos = data.indexOf(":");
		if (sep_pos > -1 && "0" == data.substring(0, sep_pos)) {
			on_success();
		}
		else {
			jQuery("#lips-err-text").html("WordPress&trade; was unable to create the page.");
			jQuery("#lips-err-additional-detail").html("");
			jQuery("#lips-err-box").dialog({title: "Unable to create page"});
			if (-1 == sep_pos) {
				jQuery("#lips-err-detail").html("Unexpected result: <code>" + data + "</code>");
			}
			else {
				jQuery("#lips-err-detail").html(data.substring(sep_pos+1));
			}
			jQuery("#lips-err-box").dialog("open");
		}
	}

	function handlePostBack(result) {
		// Only successful when the first byte of this thing is a 0.
		sep_pos = result.indexOf(":");
		if (-1 == sep_pos) {
			jQuery("#lips-err-detail").html("Unexpected result: <code>" + result + "</code>");
			jQuery("#lips-err-box").dialog("open");
		} else if (result.substring(0, sep_pos) == "0") {
			jQuery("#pin").val("");
			jQuery("#oalink").html(result.substring(sep_pos + 1));
			jQuery("#lips-pin-box").dialog("open");
			jQuery("#pin").focus();
			setTimeout(function() { jQuery("#lips-pin-box").dialog("close") },300000);
		}
		else {
			jQuery("#lips-err-detail").addClass('lips-err-monospace');
			jQuery("#lips-err-detail").text(result.substring(sep_pos + 1));
			jQuery("#lips-err-box").dialog("open");
		}
		jQuery("#lips-reset-button").attr('disabled', false);
	}

	// Displays a modal dialog asking for a LinkedIn PIN
	jQuery("#lips-pin-box").dialog({
		dialogClass : "wp-dialog",
		autoOpen: false,
		modal: true,
		closeOnEscape: true,
		title: "Authorization required",
		open: function(event, ui) {
			$(this).dialog("option", "height", 400);
			$(this).dialog("option", "width", 340);
		},
		buttons: [{
		    "text": "Fetch",
		    "class": "button-primary",
		    "click": function() {
				if (jQuery("#pin").val().length>0) {
					// This is one dirty hack... webkit does not appear to "see" data
					// after the dialog visibility thing. The answer is here
					// http://stackoverflow.com/questions/3092866/chrome-safari-webkit-does-not-post-values-when-submitting-via-javascript-sub
					// Hide the pin box and add it to the form.
					$(this).dialog("close");
					displayUploadingData();
					jQuery("#lips-form").append(jQuery("#pin"));
					jQuery("#pin").css("visibility", "hidden");
					submitForm();
				}
		    }
		   },
		   {
			"text": "Cancel",
			"class": "button-secondary",
			"click": function() {
				$(this).dialog("close");
			}
		   }
		],
		"close": function() {
			jQuery("#lips-saving").css('visibility', 'hidden');
			jQuery("#save").attr('disabled', false);
		}
	});

	// Displays the error box, in case of a failure
	jQuery("#lips-err-box").dialog({
		dialogClass: "wp-dialog",
		title: "Problem contacting LinkedIn",
		open: function(event, ui) {
			$(this).dialog("option", "height", 300);
			$(this).dialog("option", "width", 500);
		},
		autoOpen: false,
		modal: true,
		closeOnEscape: true,
		buttons: [{
			"text": "Close",
		    "class": "button-primary",
			"click": function() {
				$(this).dialog("close");
			}
		}],
		"close": function() {
			hideStatusFeedback();
		}
	});

	// Displays the create new page box
	jQuery("#lips-page-box").dialog({
		dialogClass: "wp-dialog",
		autoOpen: false,
		modal: true,
		closeOnEscape: true,
		title: "Create a new page",
		open: function(event, ui) {
			$(this).dialog("option", "height", 260);
			$(this).dialog("option", "width", 400);
		},
		buttons: [{
		    "text": "Create",
		    "class": "button-primary",
		    "click": function() {
		    	if (jQuery("#lips-page").val().length > 0) {
					jQuery.ajax({
						type: "POST",
						url: ajaxurl,
						data: { "action": "lips", "request": "create_page", "page-usage": jQuery("#lips-page").data("page-usage"), "specific": jQuery("#lips-page").val() },
						timeout: 10000,
						success: function(result) {
							handlePageCreationResult(result, function() { window.location.reload(true); });
						},
						error: function(request, status, err){
							if ("timeout" == status) {
								handlePageCreationResult("1:Timeout while trying to create a page");
							}
						}
					});
					$(this).dialog('close');
		    	}
		    	else {
		    		jQuery("#lips-page").focus();
		    	}
		    }
		},
		{
			"text": "Cancel",
			"class": "button-secondary",
			"click": function() { $(this).dialog("close"); }
		},
		],
		"close": function() {
		}
	});

	jQuery("#save").click(function() {
		// First see if a profile page is selected
		if (jQuery("#profile_page").val() == no_page_selection["page"]) {
			jQuery("#lips-err-text").html("You did not select a profile page yet.");
			jQuery("#lips-err-additional-detail").html("Select a Profile Page on the <em>LinkedIn Data Access and Profile Synchronization</em> tab. It's the first option.");
			jQuery("#lips-err-box").dialog({title: "Unable to save profile"});
			jQuery("#lips-err-box").dialog("open");
		}
		else if (jQuery("#enable_profile_data_debug").is(":checked") && jQuery("#profile_debug_data_page").val() == no_page_selection["dbg"]) {
			jQuery("#lips-err-text").html("You enabled the <em>Debug Data On-a-Page</em> option, but you did not select a page to store your debug profile on.");
			jQuery("#lips-err-additional-detail").html("Select a <em>Debug Data On-a-Page Title</em> from the <em>Development Setting</em> tab or disable the <em>Debug Data On-a-Page</em> option.");
			jQuery("#lips-err-box").dialog({title: "Unable to save profile"});
			jQuery("#lips-err-box").dialog("open");
		}
		else {
			// This profile_source0 option exists only when a profile is stored,
			// in which case there is a selection to be made.
			var download_profile = true;
			if (jQuery("#profile_source0").length > 0) {
				download_profile = jQuery("#profile_source0").is(":checked");
			}

			if ("t" == oauth_initialized && download_profile) {
				jQuery("#lips-saving").css('visibility', 'visible');
				jQuery("#lips-reset-button").attr('disabled', true);
				$(this).attr('disabled', true);
				jQuery.ajax({
						type: "POST",
						url: ajaxurl,
						data: { "action": "lips", "request": "oalink" },
						timeout: 10000,
						success: function(data) {
							handlePostBack(data);
						},
						error: function(request, status, err){
							if ("timeout" == status) {
								handleTimeout();
							}
						}
				})
			}
			else {
				submitForm();
			}
		}
	})

	function displayPageDuplicatePurposeError(page) {
		var dlg = $("<div id='lips-duplicate-page-use' />")
		.html(dialog["duplicate"]["body"] + "<strong>" + page + "</strong>")
		.appendTo("body");

		dlg.dialog({
			dialogClass: "wp-dialog",
			modal: true,
			autoOpen: false,
			closeOnEscape: true,
			title: dialog["duplicate"]["title"],
			buttons: [{
				'text' : 'Close',
			    'class': 'button-primary',
				'click' : function() {
					$(this).dialog('close');
				}
			}
		]
		}).dialog('open');
	}

	function displayUploadingData() {
		var dlg = $("<div id='lips-submitting' />")
		.html(dialog["submit"]["body"])
		.appendTo("body");

		dlg.dialog({
			dialogClass: "wp-dialog",
			modal: true,
			autoOpen: false,
			closeOnEscape: false,
			title: dialog["submit"]["title"],
			open: function(event, ui) { $(".ui-dialog-titlebar-close", this.parentNode).hide(); }
		}).dialog('open');
	}

	jQuery("#profile_debug_data_page, #profile_page").change(function() {
		page = $("option:selected", this).text();
		if (jQuery("#enable_profile_data_debug").is(":checked") && jQuery("#profile_debug_data_page").val() == jQuery("#profile_page").val()) {
			displayPageDuplicatePurposeError(page);
			if ($(this).data("previous_page") != "") {
				$(this).val($(this).data("previous_page"));
				$(this).change();
			}
		}
		$(this).data("previous_page", $(this).val());
	})

	jQuery("#lips-about").click(function() {
		cur = $(".lips-help").css("display");
		req = "block";
		if ("block" == cur) {
			req = "none";
		}
		$(".lips-help").css("display", req);
	})

	jQuery("#lips-close").click(function() {
		$(this).parent().css("display", "none");
	});

	$(".wrap h3, .wrap table").show();

	jQuery("a.lips-tab-section").click(function() {
		if ($(this).attr("href") == "#li") {
			jQuery("#lips-reset-button").show();
		}
		else {
			jQuery("#lips-reset-button").hide();
		}
	});

	$(".warning").change(function() {
		if ($(this).is(":checked"))
			$(this).parent().css("background", "#c00").css("color", "#fff").css("fontWeight", "bold");
		else
			$(this).parent().css("background", "none").css("color", "inherit").css("fontWeight", "normal");
	});

	$(".lips-with-meta").each(function(index,element) {
		jQuery("#"+element.id).change();
	});

	if (jQuery("#profile_source1").is(":checked")) {
		jQuery("#profile_source1").change();
	}

	update_profile_class = jQuery("#update_profile").attr("class");
	if (update_profile_class !== undefined && update_profile_class.indexOf("lips-identified-never-synced") > -1) {
		jQuery("#lips-speech-copy").css("display", "block");
	}

	if (autorun != null) {
		if (autorun == "autoCreatePageTask") {
			handleRelates("lips-page-box");
		}
	}

	if ($.browser.mozilla)
	         $("form").attr("autocomplete", "off");

	handlePostContentChange();
});

function handleRelates(relation) {
	display_box = true;
	if ("lips-profile-page-box" == relation) {
		jQuery("#lips-page").data("page-usage", page_usage["rt"]);
	}
	else if ("lips-debug-page-box" == relation) {
		jQuery("#lips-page").data("page-usage", page_usage["dev"]);
	}
	else {
		display_box = false;
	}

	if (true == display_box) {
		jQuery("#lips-page").val("");
		jQuery("#lips-page-box").dialog("open");
	}
}
