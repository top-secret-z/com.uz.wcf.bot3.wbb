<div class="section notifyWbbThreadSettings">
	<header class="sectionHeader">
		<h2 class="sectionTitle">{lang}wcf.acp.uzbot.notify.thread.setting{/lang}</h2>
	</header>
	
	<dl{if $errorField == 'threadNotifyBoardID'} class="formError"{/if}>
		<dt><label for="threadNotifyBoardID">{lang}wcf.acp.uzbot.notify.thread.boardID{/lang}</label></dt>
		<dd>
			<select name="threadNotifyBoardID" id="threadNotifyBoardID">
				<option value="0">{lang}wcf.global.noSelection{/lang}</option>
				{foreach from=$boardNodeList item=boardNode}
					{if !$boardNode->getBoard()->isExternalLink()}
						<option value="{@$boardNode->getBoard()->boardID}"{if $boardNode->getBoard()->boardID == $threadNotifyBoardID} selected="selected"{/if}>{if $boardNode->getDepth() > 1}{@"&nbsp;&nbsp;&nbsp;&nbsp;"|str_repeat:($boardNode->getDepth() - 1)}{/if}{$boardNode->getBoard()->title|language} </option>
					{/if}
				{/foreach}
			</select>
			
			{if $errorField == 'threadNotifyBoardID'}
				<small class="innerError">
					{lang}wcf.acp.uzbot.notify.thread.error.{@$errorType}{/lang}
				</small>
			{/if}
		</dd>
	</dl>
	
	<dl>
		<dt>{lang}wcf.acp.uzbot.notify.thread.status{/lang}</dt>
		<dd>
			<label><input name="threadNotifyIsSticky" type="checkbox" value="1"{if $threadNotifyIsSticky} checked{/if}> {lang}wcf.acp.uzbot.notify.thread.status.isSticky{/lang}</label>
			<label><input name="threadNotifyIsDisabled" type="checkbox" value="1"{if $threadNotifyIsDisabled} checked{/if}> {lang}wcf.acp.uzbot.notify.thread.status.isDisabled{/lang}</label>
			<label><input name="threadNotifyIsDone" type="checkbox" value="1"{if $threadNotifyIsDone} checked{/if}> {lang}wcf.acp.uzbot.notify.thread.status.isDone{/lang}</label>
			<label><input name="threadNotifyIsClosed" type="checkbox" value="1"{if $threadNotifyIsClosed} checked{/if}> {lang}wcf.acp.uzbot.notify.thread.status.isClosed{/lang}</label>
			<label><input name="threadIsOfficial" type="checkbox" value="1"{if $threadIsOfficial} checked{/if}> {lang}wcf.acp.uzbot.notify.thread.status.threadIsOfficial{/lang}</label>
		</dd>
	</dl>
</div>

<div class="section notifyWbbPostSettings">
	<header class="sectionHeader">
		<h2 class="sectionTitle">{lang}wcf.acp.uzbot.notify.post.setting{/lang}</h2>
	</header>
	
	<dl{if $errorField == 'postNotifyThreadID'} class="formError"{/if}>
		<dt><label for="postNotifyThreadID">{lang}wcf.acp.uzbot.notify.post.threadID{/lang}</label></dt>
		<dd>
			<input type="number" name="postNotifyThreadID" id="postNotifyThreadID" value={$postNotifyThreadID} class="small" min="0" />
			<small>{lang}wcf.acp.uzbot.notify.post.threadID.description{/lang}</small>
			{if $errorField == 'postNotifyThreadID'}
				<small class="innerError">
					{lang}wcf.acp.uzbot.notify.post.threadID.error.{@$errorType}{/lang}
				</small>
			{/if}
		</dd>
	</dl>
	
	<dl>
		<dt>{lang}wcf.acp.uzbot.notify.post.status{/lang}</dt>
		<dd>
			<label><input name="postNotifyIsClosed" type="checkbox" value="1"{if $postNotifyIsClosed} checked{/if}> {lang}wcf.acp.uzbot.notify.post.status.isClosed{/lang}</label>
			<label><input name="postNotifyIsDisabled" type="checkbox" value="1"{if $postNotifyIsDisabled} checked{/if}> {lang}wcf.acp.uzbot.notify.post.status.isDisabled{/lang}</label>
			<label><input name="postIsOfficial" type="checkbox" value="1"{if $postIsOfficial} checked{/if}> {lang}wcf.acp.uzbot.notify.post.status.postIsOfficial{/lang}</label>
		</dd>
	</dl>
</div>
