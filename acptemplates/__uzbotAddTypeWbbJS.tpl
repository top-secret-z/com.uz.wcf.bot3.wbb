$('.wbb_threadNew, .wbb_postCount, .wbb_postChange, .wbb_postModeration, .wbb_bestAnswer').hide();
$('.wbb_threadModification, .wbb_threadModeration, .wbb_topPoster, .wbb_statistics').hide();
$('.wbbUzbotBoardIDs, .wbbConditionSettings, .wbbBoardIDs').hide();

if (value == 30) {
	$('.wbb_postChange, .wbbUzbotBoardIDs, .uzbotUserConditions').show();
	$('#receiverAffected').show();
}

if (value == 31) {
	$('.wbb_postCount, .uzbotUserConditions, .user_count').show();
	$('#receiverAffected').show();
}

if (value == 32) {
	$('.wbb_postModeration, .wbbUzbotBoardIDs, .uzbotUserConditions, .affectedSetting').show();
	$('#receiverAffected').show();
}

if (value == 40) {
	$('.wbb_threadModeration, .wbbUzbotBoardIDs, .uzbotUserConditions, .affectedSetting').show();
	$('#receiverAffected').show();
}

if (value == 41) {
	$('.wbb_threadModification, .wbbConditionSettings').show();
	$('#receiverAffected, #actionLabelContainer, #conditionLabelContainer').show();
}

if (value == 42) {
	$('.wbb_threadNew, .uzbotUserConditions').show();
	$('#receiverAffected').show();
}

if (value == 43) {
	$('.wbb_topPoster, .wbbUzbotBoardIDs, .condenseSetting, .uzbotUserConditions').show();
	$('#receiverAffected').show();
	if ($('#condenseEnable').is(':checked')) { $('.notifyCondense').show(); }
}

if (value == 44) {
	$('.wbb_statistics').show();
}

if (value == 45) {
	$('.wbb_bestAnswer, .wbbUzbotBoardIDs').show();
	$('#receiverAffected').show();
}
