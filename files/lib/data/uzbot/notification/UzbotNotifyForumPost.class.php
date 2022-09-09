<?php 
namespace wbb\data\uzbot\notification;
use wbb\data\board\Board;
use wbb\data\post\PostAction;
use wbb\data\thread\Thread;
use wcf\data\uzbot\Uzbot;
use wcf\data\uzbot\UzbotEditor;
use wcf\data\uzbot\log\UzbotLogEditor;
use wcf\system\exception\SystemException;
use wcf\system\html\input\HtmlInputProcessor;
use wcf\system\language\LanguageFactory;
use wcf\system\WCF;
use wcf\util\MessageUtil;

/**
 * Creates posts for Bot
 * 
 * @author		2014-2022 Zaydowicz
 * @license		GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package		com.uz.wcf.bot3.wbb
 */
class UzbotNotifyForumPost {
	public function send(Uzbot $bot, $content, $subject, $teaser, $language, $receiver, $tags) {
		// prepare texts and data
		$defaultLanguage = LanguageFactory::getInstance()->getLanguage(LanguageFactory::getInstance()->getDefaultLanguageID());
		
		$content = MessageUtil::stripCrap($content);
		
		// ufn no subject
		//$subject = MessageUtil::stripCrap(StringUtil::stripHTML($subject));
		//if (mb_strlen($subject) > 255) $subject = mb_substr($subject, 0, 250) . '...';
		$subject = '';
		
		// set publication time
		$publicationTime = TIME_NOW;
		if (isset($bot->publicationTime) && $bot->publicationTime) {
			$publicationTime = $bot->publicationTime;
		}
		
		if (!$bot->testMode) {
			$htmlInputProcessor = new HtmlInputProcessor();
			$htmlInputProcessor->process($content, 'com.woltlab.wbb.post', 0);
			
			// get post data and create
			try {
				$wbbPostData = unserialize($bot->wbbPostData);
				
				// preset thread, maybe 0 on automatic post in affected thread
				if (!$wbbPostData['postNotifyThreadID']) {
					$wbbPostData['postNotifyThreadID'] = $bot->threadID;
				}
				
				$thread = new Thread($wbbPostData['postNotifyThreadID']);
				$board = $thread->getBoard();
				
				$htmlInputProcessor = new HtmlInputProcessor();
				$htmlInputProcessor->process($content, 'com.woltlab.wbb.post', 0);
				
				$postData = [
						'threadID' => $thread->threadID,
						'subject' => $subject,
						'message' => $content,
						'time' => $publicationTime,
						'userID' => $bot->senderID,
						'username' => $bot->sendername,
						'enableTime' => 0,
						'isClosed' => $wbbPostData['postNotifyIsClosed'],
						'isDisabled' => $wbbPostData['postNotifyIsDisabled'],
						'ipAddress' => '',
						'isOfficial' => $bot->postIsOfficial,
						'isUzbot' => 1
				];
				
				$postCreateParameters = [
						'data' => $postData,
						'thread' => $thread,
						'board' => $board,
						'isFirstPost' => false,
						'attachmentHandler' => null,
						'htmlInputProcessor' => $htmlInputProcessor
				];
				
				$postAction = new PostAction([], 'create', $postCreateParameters);
				$postAction->executeAction();
			}
			catch (SystemException $e) {
				// users may get lost; check sender again to abort
				if (!$bot->checkSender(true, true)) return false;
				
				// thread must exist / disable
				if (!$thread->threadID) {
					$editor = new UzbotEditor($bot);
					$editor->update(['isDisabled' => 1]);
					UzbotEditor::resetCache();
					
					if ($bot->enableLog) {
						UzbotLogEditor::create([
								'bot' => $bot,
								'status' => 2,
								'additionalData' => 'wcf.acp.uzbot.notify.post.threadID.error.notValid'
						]);
						
						UzbotLogEditor::create([
								'bot' => $bot,
								'status' => 2,
								'additionalData' => 'wcf.acp.uzbot.error.disabled'
						]);
					}
					return false;
				}
				
				// report any other error und continue
				if ($bot->enableLog) {
					$error = $defaultLanguage->get('wcf.acp.uzbot.log.notify.error') . ' ' . $e->getMessage();
					
					UzbotLogEditor::create([
							'bot' => $bot,
							'status' => 1,
							'count' => 1,
							'additionalData' => $error
					]);
				}
			}
		}
		else {
			$teaser = '';
			if (mb_strlen($content) > 63500) $content = mb_substr($content, 0, 63500) . ' ...';
			$result = serialize([$subject, $teaser, $content]);
			
			UzbotLogEditor::create([
					'bot' => $bot,
					'count' => 1,
					'testMode' => 1,
					'additionalData' => $result
			]);
		}
	}
}
