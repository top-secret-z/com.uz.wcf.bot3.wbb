<script data-relocate="true">
	require(['WoltLabSuite/Core/Ui/User/Search/Input'], function(UiUserSearchInput) {
		new UiUserSearchInput(elBySel('input[name="threadModificationExecuter"]'));
	});
</script>

<div class="section wbb_threadNew">
	<header class="sectionHeader">
		<h2 class="sectionTitle">{lang}wcf.acp.uzbot.setting{/lang}</h2>
	</header>
	
	<dl{if $errorField == 'threadNewBoardIDs'} class="formError"{/if}>
		<dt><label for="threadNewBoardIDs">{lang}wcf.acp.uzbot.wbb.threadNew.threadNewBoardIDs{/lang}</label></dt>
		<dd>
			<select  id="selectThreadNewBoardIDs" name="threadNewBoardIDs[]" multiple size="10">
				{foreach from=$boardNodeList item=boardNode}
					<option value="{@$boardNode->getBoard()->boardID}"{if $boardNode->getBoard()->boardID|in_array:$threadNewBoardIDs} selected{/if}>{if $boardNode->getDepth() > 1}{@'&nbsp;&nbsp;&nbsp;&nbsp;'|str_repeat:-1+$boardNode->getDepth()}{/if}{$boardNode->getBoard()->title|language}</option>
				{/foreach}
			</select>
			
			{if $errorField == 'threadNewBoardIDs'}
				<small class="innerError">
					{lang}wcf.acp.uzbot.wbb.threadNew.threadNewBoardIDs.error.{@$errorType}{/lang}
				</small>
			{/if}
		</dd>
	</dl>
</div>

<div class="section wbb_postCount">
	<header class="sectionHeader">
		<h2 class="sectionTitle">{lang}wcf.acp.uzbot.setting{/lang}</h2>
	</header>
	
	<dl>
		<dt>{lang}wcf.acp.uzbot.wbb.postCount.action{/lang}</dt>
		<dd>
			<label><input type="radio" name="postCountAction" value="postTotal"{if $postCountAction == 'postTotal'} checked{/if} /> {lang}wcf.acp.uzbot.wbb.postCount.postTotal{/lang}</label>
			<label><input type="radio" name="postCountAction" value="postX"{if $postCountAction == 'postX'} checked{/if} /> {lang}wcf.acp.uzbot.wbb.postCount.postX{/lang}</label>
			<label><input type="radio" name="postCountAction" value="postTop"{if $postCountAction == 'postTop'} checked{/if} /> {lang}wcf.acp.uzbot.wbb.postCount.postTop{/lang}</label>
		</dd>
	</dl>
</div>

<div class="section wbb_postChange">
	<header class="sectionHeader">
		<h2 class="sectionTitle">{lang}wcf.acp.uzbot.setting{/lang}</h2>
	</header>
	
	<dl{if $errorField == 'postChangeAction'} class="formError"{/if}>
		<dt>{lang}wcf.acp.uzbot.wbb.postChange.action{/lang}</dt>
		<dd>
			<label><input type="checkbox" name="postChangeUpdate" value="1"{if $postChangeUpdate} checked{/if}> {lang}wcf.acp.uzbot.wbb.postChange.update{/lang}</label>
			<label><input type="checkbox" name="postChangeDelete" value="1"{if $postChangeDelete} checked{/if}> {lang}wcf.acp.uzbot.wbb.postChange.delete{/lang}</label>
			
			{if $errorField == 'postChangeAction'}
				<small class="innerError">
					{lang}wcf.acp.uzbot.wbb.postChange.action.error.notConfigured{/lang}
				</small>
			{/if}
		</dd>
	</dl>
</div>

<div class="section wbb_postModeration">
	<header class="sectionHeader">
		<h2 class="sectionTitle">{lang}wcf.acp.uzbot.setting{/lang}</h2>
	</header>
	
	<dl{if $errorField == 'postModerationAction'} class="formError"{/if}>
		<dt>{lang}wcf.acp.uzbot.wbb.postModeration.action{/lang}</dt>
		<dd>
			<label><input type="checkbox" name="postModerationEnable" value="1"{if $postModerationEnable} checked{/if}> {lang}wcf.acp.uzbot.wbb.postModeration.enable{/lang}</label>
			<label><input type="checkbox" name="postModerationDisable" value="1"{if $postModerationDisable} checked{/if}> {lang}wcf.acp.uzbot.wbb.postModeration.disable{/lang}</label>
			<label><input type="checkbox" name="postModerationEdit" value="1"{if $postModerationEdit} checked{/if}> {lang}wcf.acp.uzbot.wbb.postModeration.edit{/lang}</label>
			<label><input type="checkbox" name="postModerationTrash" value="1"{if $postModerationTrash} checked{/if}> {lang}wcf.acp.uzbot.wbb.postModeration.trash{/lang}</label>
			<label><input type="checkbox" name="postModerationRestore" value="1"{if $postModerationRestore} checked{/if}> {lang}wcf.acp.uzbot.wbb.postModeration.restore{/lang}</label>
			<label><input type="checkbox" name="postModerationDelete" value="1"{if $postModerationDelete} checked{/if}> {lang}wcf.acp.uzbot.wbb.postModeration.delete{/lang}</label>
			<label><input type="checkbox" name="postModerationOpen" value="1"{if $postModerationOpen} checked{/if}> {lang}wcf.acp.uzbot.wbb.postModeration.open{/lang}</label>
			<label><input type="checkbox" name="postModerationClose" value="1"{if $postModerationClose} checked{/if}> {lang}wcf.acp.uzbot.wbb.postModeration.close{/lang}</label>
			<label><input type="checkbox" name="postModerationMove" value="1"{if $postModerationMove} checked{/if}> {lang}wcf.acp.uzbot.wbb.postModeration.move{/lang}</label>
			<label><input type="checkbox" name="postModerationMerge" value="1"{if $postModerationMerge} checked{/if}> {lang}wcf.acp.uzbot.wbb.postModeration.merge{/lang}</label>
			
			{if $errorField == 'postModerationAction'}
				<small class="innerError">
					{lang}wcf.acp.uzbot.wbb.postModeration.action.error.notConfigured{/lang}
				</small>
			{/if}
		</dd>
	</dl>
</div>

<div class="section wbb_threadModeration">
	<header class="sectionHeader">
		<h2 class="sectionTitle">{lang}wcf.acp.uzbot.setting{/lang}</h2>
	</header>
	
	<dl{if $errorField == 'threadModerationAction'} class="formError"{/if}>
		<dt>{lang}wcf.acp.uzbot.wbb.threadModeration.action{/lang}</dt>
		<dd>
			<label><input type="checkbox" name="threadModerationEnable" value="1"{if $threadModerationEnable} checked{/if}> {lang}wcf.acp.uzbot.wbb.threadModeration.enable{/lang}</label>
			<label><input type="checkbox" name="threadModerationDisable" value="1"{if $threadModerationDisable} checked{/if}> {lang}wcf.acp.uzbot.wbb.threadModeration.disable{/lang}</label>
			<label><input type="checkbox" name="threadModerationDone" value="1"{if $threadModerationDone} checked{/if}> {lang}wcf.acp.uzbot.wbb.threadModeration.done{/lang}</label>
			<label><input type="checkbox" name="threadModerationUndone" value="1"{if $threadModerationUndone} checked{/if}> {lang}wcf.acp.uzbot.wbb.threadModeration.undone{/lang}</label>
			<label><input type="checkbox" name="threadModerationSetLabel" value="1"{if $threadModerationSetLabel} checked{/if}> {lang}wcf.acp.uzbot.wbb.threadModeration.setLabel{/lang}</label>
			<label><input type="checkbox" name="threadModerationTrash" value="1"{if $threadModerationTrash} checked{/if}> {lang}wcf.acp.uzbot.wbb.threadModeration.trash{/lang}</label>
			<label><input type="checkbox" name="threadModerationRestore" value="1"{if $threadModerationRestore} checked{/if}> {lang}wcf.acp.uzbot.wbb.threadModeration.restore{/lang}</label>
			<label><input type="checkbox" name="threadModerationSticky" value="1"{if $threadModerationSticky} checked{/if}> {lang}wcf.acp.uzbot.wbb.threadModeration.sticky{/lang}</label>
			<label><input type="checkbox" name="threadModerationScrape" value="1"{if $threadModerationScrape} checked{/if}> {lang}wcf.acp.uzbot.wbb.threadModeration.scrape{/lang}</label>
			<label><input type="checkbox" name="threadModerationOpen" value="1"{if $threadModerationOpen} checked{/if}> {lang}wcf.acp.uzbot.wbb.threadModeration.open{/lang}</label>
			<label><input type="checkbox" name="threadModerationClose" value="1"{if $threadModerationClose} checked{/if}> {lang}wcf.acp.uzbot.wbb.threadModeration.close{/lang}</label>
			<label><input type="checkbox" name="threadModerationChangeTopic" value="1"{if $threadModerationChangeTopic} checked{/if}> {lang}wcf.acp.uzbot.wbb.threadModeration.changeTopic{/lang}</label>
			<label><input type="checkbox" name="threadModerationMove" value="1"{if $threadModerationMove} checked{/if}> {lang}wcf.acp.uzbot.wbb.threadModeration.move{/lang}</label>
			<label><input type="checkbox" name="threadModerationMerge" value="1"{if $threadModerationMerge} checked{/if}> {lang}wcf.acp.uzbot.wbb.threadModeration.merge{/lang}</label>
			<label><input type="checkbox" name="threadModerationSetAsAnnouncement" value="1"{if $threadModerationSetAsAnnouncement} checked{/if}> {lang}wcf.acp.uzbot.wbb.threadModeration.setAsAnnouncement{/lang}</label>
			<label><input type="checkbox" name="threadModerationUnsetAsAnnouncement" value="1"{if $threadModerationUnsetAsAnnouncement} checked{/if}> {lang}wcf.acp.uzbot.wbb.threadModeration.unsetAsAnnouncement{/lang}</label>
			
			{if $errorField == 'threadModerationAction'}
				<small class="innerError">
					{lang}wcf.acp.uzbot.wbb.threadModeration.action.error.notConfigured{/lang}
				</small>
			{/if}
		</dd>
	</dl>
	
	<dl>
		<dt>{lang}wcf.acp.uzbot.wbb.threadModeration.author{/lang}</dt>
		<dd>
			<label><input type="checkbox" name="threadModerationAuthorOnly" value="1"{if $threadModerationAuthorOnly} checked{/if}> {lang}wcf.acp.uzbot.wbb.threadModeration.authorOnly{/lang}</label>
		</dd>
	</dl>
</div>

<div class="section wbb_threadModification">
	<header class="sectionHeader">
		<h2 class="sectionTitle">{lang}wcf.acp.uzbot.wbb.threadModification.setting{/lang}</h2>
	</header>
	
	<dl{if $errorField == 'threadModificationExecuter'} class="formError"{/if}>
		<dt><label for="threadModificationExecuter">{lang}wcf.acp.uzbot.wbb.threadModification.executer{/lang}</label></dt>
		<dd>
			<input type="text" id="threadModificationExecuter" name="threadModificationExecuter" value="{$threadModificationExecuter}" class="medium" maxlength="255">
			<small>{lang}wcf.acp.uzbot.wbb.threadModification.executer.description{/lang}</small>
			
			{if $errorField == 'threadModificationExecuter'}
				<small class="innerError">
					{if $errorField == 'threadModificationExecuter'}
						{lang}wcf.acp.uzbot.wbb.threadModification.executer.error.{@$errorType}{/lang}
					{/if}
				</small>
			{/if}
		</dd>
	</dl>
	
	<dl{if $errorField == 'threadModificationAction'} class="formError"{/if}>
		<dt>{lang}wcf.acp.uzbot.wbb.threadModification.action{/lang}</dt>
		<dd>
			<label><input type="checkbox" name="threadModificationEnable" value="1"{if $threadModificationEnable} checked{/if}> {lang}wcf.acp.uzbot.wbb.threadModeration.enable{/lang}</label>
			<label><input type="checkbox" name="threadModificationDisable" value="1"{if $threadModificationDisable} checked{/if}> {lang}wcf.acp.uzbot.wbb.threadModeration.disable{/lang}</label>
			<label><input type="checkbox" name="threadModificationDone" value="1"{if $threadModificationDone} checked{/if}> {lang}wcf.acp.uzbot.wbb.threadModeration.done{/lang}</label>
			<label><input type="checkbox" name="threadModificationUndone" value="1"{if $threadModificationUndone} checked{/if}> {lang}wcf.acp.uzbot.wbb.threadModeration.undone{/lang}</label>
			<label><input type="checkbox" name="threadModificationUnannounce" value="1"{if $threadModificationUnannounce} checked{/if}> {lang}wcf.acp.uzbot.wbb.threadModeration.unsetAsAnnouncement{/lang}</label>
			{if $labelGroups|count && $availableLabels|count}
				<label><input type="checkbox" name="threadModificationSetLabel" value="1"{if $threadModificationSetLabel} checked{/if}> {lang}wcf.acp.uzbot.wbb.threadModeration.setLabel{/lang}</label>
			{/if}
			<label><input type="checkbox" name="threadModificationTrash" value="1"{if $threadModificationTrash} checked{/if}> {lang}wcf.acp.uzbot.wbb.threadModeration.trash{/lang}</label>
			<label><input type="checkbox" name="threadModificationRestore" value="1"{if $threadModificationRestore} checked{/if}> {lang}wcf.acp.uzbot.wbb.threadModeration.restore{/lang}</label>
			<label><input type="checkbox" name="threadModificationSticky" value="1"{if $threadModificationSticky} checked{/if}> {lang}wcf.acp.uzbot.wbb.threadModeration.sticky{/lang}</label>
			<label><input type="checkbox" name="threadModificationScrape" value="1"{if $threadModificationScrape} checked{/if}> {lang}wcf.acp.uzbot.wbb.threadModeration.scrape{/lang}</label>
			<label><input type="checkbox" name="threadModificationOpen" value="1"{if $threadModificationOpen} checked{/if}> {lang}wcf.acp.uzbot.wbb.threadModeration.open{/lang}</label>
			<label><input type="checkbox" name="threadModificationClose" value="1"{if $threadModificationClose} checked{/if}> {lang}wcf.acp.uzbot.wbb.threadModeration.close{/lang}</label>
			<label><input type="checkbox" name="threadModificationMove" value="1"{if $threadModificationMove} checked{/if}> {lang}wcf.acp.uzbot.wbb.threadModeration.move{/lang}</label>
			
			{if $errorField == 'threadModificationAction'}
				<small class="innerError">
					{lang}wcf.acp.uzbot.wbb.threadModification.action.error.notConfigured{/lang}
				</small>
			{/if}
		</dd>
	</dl>
	
	<dl{if $errorField == 'threadModificationBoardID'} class="formError"{/if}>
		<dt><label for="threadModificationBoardID">{lang}wcf.acp.uzbot.wbb.threadModification.action.boardID{/lang}</label></dt>
		<dd>
			<select name="threadModificationBoardID" id="threadModificationBoardID">
				<option value="0">{lang}wcf.global.noSelection{/lang}</option>
				{foreach from=$boardNodeList item=boardNode}
					{if !$boardNode->getBoard()->isExternalLink()}
						<option value="{@$boardNode->getBoard()->boardID}"{if $boardNode->getBoard()->boardID == $threadModificationBoardID} selected="selected"{/if}>{if $boardNode->getDepth() > 1}{@"&nbsp;&nbsp;&nbsp;&nbsp;"|str_repeat:($boardNode->getDepth() - 1)}{/if}{$boardNode->getBoard()->title|language}</option>
					{/if}
				{/foreach}
			</select>
			{if $errorField == 'threadModificationBoardID'}
				<small class="innerError">
					{lang}wcf.acp.uzbot.wbb.threadModification.action.boardID.error.notValid{/lang}
				</small>
			{/if}
		</dd>
	</dl>
	
	<dl>
		<dt>{lang}wcf.acp.uzbot.wbb.threadModeration.author{/lang}</dt>
		<dd>
			<label><input type="checkbox" name="threadModificationAuthorOnly" value="1"{if $threadModificationAuthorOnly} checked{/if}> {lang}wcf.acp.uzbot.wbb.threadModeration.authorOnly{/lang}</label>
		</dd>
	</dl>
</div>

<div class="section wbb_topPoster">
	<header class="sectionHeader">
		<h2 class="sectionTitle">{lang}wcf.acp.uzbot.setting{/lang}</h2>
	</header>
	
	<dl>
		<dt><label for="topPosterCount">{lang}wcf.acp.uzbot.wbb.topPoster.count{/lang}</label></dt>
		<dd>
			<input type="number" name="topPosterCount" id="topPosterCount" value="{$topPosterCount}" class="small" min="0" max="100" />
		</dd>
	</dl>
	
	<dl>
		<dt>{lang}wcf.acp.uzbot.wbb.topPoster.interval{/lang}</dt>
		<dd>
			<label><input type="radio" name="topPosterInterval" value="1"{if $topPosterInterval == 1} checked{/if} /> {lang}wcf.acp.uzbot.wbb.topPoster.interval.week{/lang}</label>
			<label><input type="radio" name="topPosterInterval" value="2"{if $topPosterInterval == 2} checked{/if} /> {lang}wcf.acp.uzbot.wbb.topPoster.interval.month{/lang}</label>
			<label><input type="radio" name="topPosterInterval" value="3"{if $topPosterInterval == 3} checked{/if} /> {lang}wcf.acp.uzbot.wbb.topPoster.interval.quarter{/lang}</label>
		</dd>
	</dl>
</div>

<div class="section wbb_bestAnswer">
	<header class="sectionHeader">
		<h2 class="sectionTitle">{lang}wcf.acp.uzbot.setting{/lang}</h2>
	</header>
	
</div>

<div class="section wbb_statistics">
	<header class="sectionHeader">
		<h2 class="sectionTitle">{lang}wcf.acp.uzbot.setting{/lang}</h2>
	</header>
	
	<p>{lang}wcf.acp.uzbot.type.description.notifyOnly{/lang}</p>
</div>

<div class="section">
	<dl{if $errorField == 'uzbotBoardIDs'} class="formError wbbUzbotBoardIDs"{else} class="wbbUzbotBoardIDs"{/if}>
		<dt><label for="uzbotBoardIDs">{lang}wcf.acp.uzbot.wbb.uzbotBoardIDs{/lang}</label></dt>
		<dd>
			<select id="selectUzbotBoardIDs" name="uzbotBoardIDs[]" multiple size="10">
				{foreach from=$boardNodeList item=boardNode}
					<option value="{@$boardNode->getBoard()->boardID}"{if $boardNode->getBoard()->boardID|in_array:$uzbotBoardIDs} selected{/if}>{if $boardNode->getDepth() > 1}{@'&nbsp;&nbsp;&nbsp;&nbsp;'|str_repeat:-1+$boardNode->getDepth()}{/if}{$boardNode->getBoard()->title|language}</option>
				{/foreach}
			</select>
			
			{if $errorField == 'uzbotBoardIDs'}
				<small class="innerError">
					{lang}wcf.acp.uzbot.wbb.uzbotBoardIDs.error.{@$errorType}{/lang}
				</small>
			{/if}
		</dd>
	</dl>
</div>

<div class="section wbbConditionSettings">
	<header class="sectionHeader">
		<h2 class="sectionTitle">{lang}wcf.acp.uzbot.wbb.thread.condition{/lang}</h2>
		<p class="sectionDescription">{lang}wcf.acp.uzbot.wbb.thread.condition.description{/lang}</p>
	</header>
	
	<section>
		{foreach from=$wbbConditions key='conditionGroup' item='conditionObjectTypes'}
			<div id="wbb_{$conditionGroup}">
				<section class="section">
					{foreach from=$conditionObjectTypes item='condition'}
						{@$condition->getProcessor()->getHtml()}
					{/foreach}
				</section>
			</div>
		{/foreach}
	</section>
</div>
