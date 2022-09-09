<?php 
namespace wbb\data\uzbot\notification;
use wbb\data\board\BoardEditor;
use wbb\data\board\BoardCache;
use wbb\data\thread\ThreadAction;
use wcf\data\uzbot\Uzbot;
use wcf\data\uzbot\log\UzbotLogEditor;
use wcf\system\exception\SystemException;
use wcf\system\html\input\HtmlInputProcessor;
use wbb\system\label\object\ThreadLabelObjectHandler;
use wcf\system\label\object\UzbotNotificationLabelObjectHandler;
use wcf\system\language\LanguageFactory;
use wcf\system\WCF;
use wcf\util\MessageUtil;
use wcf\util\StringUtil;

/**
 * Creates threads for Bot
 * 
 * @author		2014-2022 Zaydowicz
 * @license		GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package		com.uz.wcf.bot3.wbb
 */
class UzbotNotifyForumThread {
	public function send(Uzbot $bot, $content, $subject, $teaser, $language, $receiver, $tags) {
		// prepare text and data
		$defaultLanguage = LanguageFactory::getInstance()->getLanguage(LanguageFactory::getInstance()->getDefaultLanguageID());
		
		$content = MessageUtil::stripCrap($content);
		$subject = MessageUtil::stripCrap(StringUtil::stripHTML($subject));
		if (mb_strlen($subject) > 255) $subject = mb_substr($subject, 0, 250) . '...';
		
		// set publication time
		$publicationTime = TIME_NOW;
		if (isset($bot->publicationTime) && $bot->publicationTime) {
			$publicationTime = $bot->publicationTime;
		}
		
		if (!$bot->testMode) {
			$htmlInputProcessor = new HtmlInputProcessor();
			$htmlInputProcessor->process($content, 'com.woltlab.wbb.post', 0);
			
			// get thread / notification data
			$wbbThreadData = unserialize($bot->wbbThreadData);
			$assignedLabels = UzbotNotificationLabelObjectHandler::getInstance()->getAssignedLabels([$bot->botID], false);
			
			// tags to include feedreader
			if (!MODULE_TAGGING || !WBB_THREAD_ENABLE_TAGS) {
				$tags = [];
			}
			else {
				if (isset($bot->feedreaderUseTags) && $bot->feedreaderUseTags) {
					if (isset($bot->feedreaderTags) && !empty($bot->feedreaderTags)) {
						$tags = array_unique(array_merge($tags, $bot->feedreaderTags));
					}
				}
			}
			
			if (!WBB_MODULE_THREAD_MARKING_AS_DONE) {
				$wbbThreadData['threadNotifyIsDone'] = 0;
			}
			
			// consider multilingualism for threads
			if (!LanguageFactory::getInstance()->multilingualismEnabled() || !$language->languageID) {
				$languageID = null;
			}
			else {
				$languageID = $language->languageID;
			}
			
			$data = [
					'boardID' => $wbbThreadData['threadNotifyBoardID'],
					'languageID' => $languageID,
					'topic' => $subject,
					'time' => $publicationTime,
					'userID' => $bot->senderID,
					'username' => $bot->sendername,
					'isClosed' => $wbbThreadData['threadNotifyIsClosed'],
					'isDisabled' => $wbbThreadData['threadNotifyIsDisabled'],
					'isDone' => $wbbThreadData['threadNotifyIsDone'],
					'isSticky' => $wbbThreadData['threadNotifyIsSticky'],
					'hasLabels' => !empty($assignedLabels) ? 1 : 0,
					'isUzbot' => 1
			];
			
			// create thread
			try {
				// official post since 5.4
				$postData['isOfficial'] = $bot->threadIsOfficial;
				
				$action = new ThreadAction([], 'create', [
						'data' => $data,
						'postData' => $postData,
						'tags' => $tags,
						'subscribeThread' => false,
						'htmlInputProcessor' => $htmlInputProcessor
				]);
				$resultValues = $action->executeAction();
				
				// set labels
				if (!empty($assignedLabels)) {
					$labelIDs = [];
					foreach ($assignedLabels as $labels) {
						foreach ($labels as $label) {
							$labelIDs[] = $label->labelID;
						}
					}
					ThreadLabelObjectHandler::getInstance()->setLabels($labelIDs, $resultValues['returnValues']->threadID);
				}
				
				// update last post
				$boardEditor = new BoardEditor(BoardCache::getInstance()->getBoard($wbbThreadData['threadNotifyBoardID']));
				$boardEditor->updateLastPost();
			}
			catch (SystemException $e) {
				// users may get lost; check sender again to abort
				if (!$bot->checkSender(true, true)) return false;
				
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
