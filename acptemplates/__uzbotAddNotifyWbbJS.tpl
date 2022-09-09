$('.notifyWbbPostSettings, .notifyWbbThreadSettings').hide();

if ($value == 10) {
	$('.notifyWbbThreadSettings, .notifySubject, .notifyTags').show();
	$('#notifyLabelContainer').show();
}

if ($value == 11) {
	$('.notifyWbbPostSettings').show();
}
