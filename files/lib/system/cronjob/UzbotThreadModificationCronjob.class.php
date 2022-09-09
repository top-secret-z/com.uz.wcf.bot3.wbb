<?php 
namespace wbb\system\cronjob;
use wbb\data\post\PostList;
use wbb\data\thread\Thread;
use wbb\data\thread\ThreadAction;
use wbb\data\thread\ThreadEditor;
use wbb\data\thread\ThreadList;
use wbb\system\log\modification\ThreadModificationLogHandler;
use wcf\data\cronjob\Cronjob;
use wcf\data\user\User;
use wcf\data\uzbot\UzbotEditor;
use wcf\data\uzbot\log\UzbotLogEditor;
use wcf\system\background\BackgroundQueueHandler;
use wcf\system\background\uzbot\NotifyScheduleBackgroundJob;
use wcf\system\cache\builder\UzbotValidBotCacheBuilder;
use wcf\system\condition\ConditionHandler;
use wcf\system\cronjob\AbstractCronjob;
use wcf\system\database\util\PreparedStatementConditionBuilder;
use wcf\system\label\LabelHandler;
use wcf\system\label\object\UzbotActionLabelObjectHandler;
use wcf\system\label\object\UzbotConditionLabelObjectHandler;
use wcf\system\language\LanguageFactory;
use wcf\system\WCF;

/**
 * WBB thread modification cronjob for Bot.
 * 
 * @author		2014-2022 Zaydowicz
 * @license		GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package		com.uz.wcf.bot3.wbb
 */
class UzbotThreadModificationCronjob extends AbstractCronjob {
	/**
	 * list with threads to be modified
	 */
	protected $threadList = null;
	
	// thread limit per action
	// made configurable: UZBOT_DATA_LIMIT_THREAD
	// const THREAD_LIMIT = 200;
	
	/**
	 * @see	wcf\system\cronjob\ICronjob::execute()
	 */
	public function execute(Cronjob $cronjob) {
		parent::execute($cronjob);
		
		if (!MODULE_UZBOT) return;
		
		// Read all active, valid bots, abort if none
		$bots = UzbotValidBotCacheBuilder::getInstance()->getData(array('typeDes' => 'wbb_threadModification'));
		if (empty($bots)) return;
		
		$defaultLanguage = LanguageFactory::getInstance()->getLanguage(LanguageFactory::getInstance()->getDefaultLanguageID());
		
		// Step through all bots and get threads to be modified
		foreach ($bots as $bot) {
			// set data
			$modifications = unserialize($bot->wbbThreadModificationData);
			$userData = [];
			
			// check executer
			$user = new User($modifications['threadModificationExecuterID']);
			if (!$user->userID) {
				$editor = new UzbotEditor($bot);
				$editor->update(['isDisabled' => 1]);
				UzbotEditor::resetCache();
				
				if ($bot->enableLog) {
					UzbotLogEditor::create([
							'bot' => $bot,
							'count' => 0,
							'status' => 2,
							'additionalData' => $defaultLanguage->get('wcf.acp.uzbot.wbb.error.executerInvalid')
					]);
				}
				continue;
			}
			
			// change user to fix for some plugin modifying ThreadList :-(
			$oldUser = WCF::getUser();
			WCF::getSession()->changeUser(new User($modifications['threadModificationExecuterID']), true);
			
			// get all threadIDs matching conditions
			$conditions = ConditionHandler::getInstance()->getConditions('com.uz.wcf.bot.condition.wbb', $bot->botID);
			$conditionThreadIDs = [];
			if (count($conditions)) {
				$threadList = new ThreadList();
				foreach ($conditions as $condition) {
					$condition->getObjectType()->getProcessor()->addObjectListCondition($threadList, $condition->conditionData);
				}
				$threadList->readObjectIDs();
				$conditionThreadIDs = $threadList->getObjectIDs();
				if (empty($conditionThreadIDs)) $conditionThreadIDs[] = 0;
			}
			$conditionCount = count($conditionThreadIDs);
			
			// same for labels
			$labelThreadIDs = [];
			$useLabels = 0;
			$labels = UzbotConditionLabelObjectHandler::getInstance()->getAssignedLabels([$bot->botID], false);
			if (!empty($labels)) {
				$useLabels = 1;
				$labelIDs = [];
				foreach ($labels as $temp) {
					foreach ($temp as $labelID => $label) {
						$labelIDs[] = $labelID;
					}
				}
				
				$objectType = LabelHandler::getInstance()->getObjectType('com.woltlab.wbb.thread');
				$threadList = new ThreadList();
				foreach ($labelIDs as $labelID) {
					$threadList->getConditionBuilder()->add('thread.threadID IN (SELECT objectID FROM wcf'.WCF_N.'_label_object WHERE objectTypeID = ? AND labelID = ?)', [$objectType->objectTypeID, $labelID]);
				}
				$threadList->readObjectIDs();
				$labelThreadIDs = $threadList->getObjectIDs();
				if (empty($labelThreadIDs)) $labelThreadIDs[] = 0;
			}
			$labelCount = count($labelThreadIDs);
			
			// merge threadIDs
			if (!$conditionCount && !$labelCount) {	// all threads
				$threadList = new ThreadList();
				$threadList->readObjectIDs();
				$threadIDs = $threadList->getObjectIDs();
			}
			elseif ($conditionCount && $labelCount) {
				$threadIDs = array_intersect($labelThreadIDs, $conditionThreadIDs);
			}
			else {
				$threadIDs = array_merge($labelThreadIDs, $conditionThreadIDs);
			}
			
			// if no threads, log and abort
			$threadCount = count($threadIDs);
			
			// log found threads (not action)
			if ($bot->enableLog) {
				if ($threadCount == 1 && isset($threadIDs[0]) && $threadIDs[0] == 0) $count = 0;
				else $count = $threadCount;
				$result = $defaultLanguage->getDynamicVariable('wcf.acp.uzbot.log.wbb.thread.affected', ['count' => $count]);
				
				if (!$bot->testMode) {
					UzbotLogEditor::create([
							'bot' => $bot,
							'count' => $count,
							'additionalData' => $result
					]);
				}
				else {
					UzbotLogEditor::create([
							'bot' => $bot,
							'count' => $count,
							'testMode' => 1,
							'additionalData' => serialize(['', '', $result])
					]);
				}
			}
			
			// abort if no threads
			if ($threadCount == 1 && isset($threadIDs[0]) && $threadIDs[0] == 0) {
				// Reset to old user
				WCF::getSession()->changeUser($oldUser, true);
				continue;
			}
			
			// get actionLabelIDs and related data
			$actionLabelIDs = [];
			$actionLabels = UzbotActionLabelObjectHandler::getInstance()->getAssignedLabels([$bot->botID], false);
			if (count($actionLabels)) {
				foreach ($actionLabels as $temp) {
					foreach ($temp as $label) {
						$actionLabelIDs[] = $label->labelID;
					}
				}
			}
			$objectType = LabelHandler::getInstance()->getObjectType('com.woltlab.wbb.thread');
			$labelObjectTypeID = $objectType->objectTypeID;
			
			// step through threads until at least one thread was modified
			$found = 0;
			for ($i = 0; $i < $threadCount; $i += UZBOT_DATA_LIMIT_THREAD) {
				$ids = array_slice($threadIDs, $i, UZBOT_DATA_LIMIT_THREAD);
				
				// step through action in sequence of add form
				if ($modifications['threadModificationEnable']) {
					$threadList = new ThreadList();
					$threadList->getConditionBuilder()->add('thread.threadID IN (?)', [$ids]);
					$threadList->getConditionBuilder()->add('thread.isDisabled = ?', [1]);
					$threadList->readObjects();
					$threads = $threadList->getObjects();
					if (count($threads)) {
						$found = 1;
						$this->executeBot($bot, $threads, 'enable', $modifications['threadModificationAuthorOnly'], $defaultLanguage);
					}
				}
				
				if ($modifications['threadModificationDisable']) {
					$threadList = new ThreadList();
					$threadList->getConditionBuilder()->add('thread.threadID IN (?)', [$ids]);
					$threadList->getConditionBuilder()->add('thread.isDisabled = ?', [0]);
					$threadList->readObjects();
					$threads = $threadList->getObjects();
					if (count($threads)) {
						$found = 1;
						$this->executeBot($bot, $threads, 'disable', $modifications['threadModificationAuthorOnly'], $defaultLanguage);
					}
				}
				
				if ($modifications['threadModificationDone']) {
					$threadList = new ThreadList();
					$threadList->getConditionBuilder()->add('thread.threadID IN (?)', [$ids]);
					$threadList->getConditionBuilder()->add('thread.isDone = ?', [0]);
					$threadList->readObjects();
					$threads = $threadList->getObjects();
					if (count($threads)) {
						$found = 1;
						$this->executeBot($bot, $threads, 'markAsDone', $modifications['threadModificationAuthorOnly'], $defaultLanguage);
					}
				}
				
				if ($modifications['threadModificationUndone']) {
					$threadList = new ThreadList();
					$threadList->getConditionBuilder()->add('thread.threadID IN (?)', [$ids]);
					$threadList->getConditionBuilder()->add('thread.isDone = ?', [1]);
					$threadList->readObjects();
					$threads = $threadList->getObjects();
					if (count($threads)) {
						$found = 1;
						$this->executeBot($bot, $threads, 'markAsUndone', $modifications['threadModificationAuthorOnly'], $defaultLanguage);
					}
				}
				
				if (isset($modifications['threadModificationUnannounce'])) {
					if ($modifications['threadModificationUnannounce']) {
						$threadList = new ThreadList();
						$threadList->getConditionBuilder()->add('thread.threadID IN (?)', [$ids]);
						$threadList->getConditionBuilder()->add('thread.isAnnouncement = ?', [1]);
						$threadList->readObjects();
						$threads = $threadList->getObjects();
						if (count($threads)) {
							$found = 1;
							$this->executeBot($bot, $threads, 'unannounce', $modifications['threadModificationAuthorOnly'], $defaultLanguage);
						}
					}
				}
				
				if ($modifications['threadModificationSetLabel']) {
					// user wants to delete labels
					if (!count($actionLabelIDs)) {
						$threadList = new ThreadList();
						$threadList->getConditionBuilder()->add('thread.threadID IN (?)', [$ids]);
						$threadList->getConditionBuilder()->add('thread.hasLabels = ?', [1]);
						$threadList->readObjects();
						$threads = $threadList->getObjects();
						if (count($threads)) {
							$found = 1;
							$this->executeBot($bot, $threads, 'deleteLabels', $modifications['threadModificationAuthorOnly'], $defaultLanguage);
						}
					}
					
					// user wants to add / change labels
					else {
						// read actual label assignment and get threads to be modified
						$assign = $modifyID = [];
						$conditionBuilder = new PreparedStatementConditionBuilder();
						$conditionBuilder->add('objectTypeID = ?', [$labelObjectTypeID]);
						$conditionBuilder->add('objectID IN (?)', [$ids]);
						$sql = "SELECT 	objectID, labelID
								FROM	wcf".WCF_N."_label_object
								".$conditionBuilder;
						$statement = WCF::getDB()->prepareStatement($sql);
						$statement->execute($conditionBuilder->getParameters());
						while ($row = $statement->fetchArray()) {
							$assign[$row['objectID']][] = $row['labelID'];
						}
						
						$modifyID = [];
						foreach ($ids as $threadID) {
							if (!isset($assign[$threadID])) {
								$modifyID[] = $threadID;
								continue;
							}
							if (count($assign[$threadID]) != count($actionLabelIDs)) {
								$modifyID[] = $threadID;
								continue;
							}
							if (!empty(array_diff($actionLabelIDs, $assign[$threadID]))) {
								$modifyID[] = $threadID;
							}
						}
						
						if (count($modifyID)) {
							$threadList = new ThreadList();
							$threadList->getConditionBuilder()->add('thread.threadID IN (?)', [$modifyID]);
							$threadList->readObjects();
							$threads = $threadList->getObjects();
							if (count($threads)) {
								$found = 1;
								$this->executeBot($bot, $threads, 'setLabels', $modifications['threadModificationAuthorOnly'], $defaultLanguage, $actionLabels);
							}
						}
					}
				}
				
				if ($modifications['threadModificationTrash']) {
					$threadList = new ThreadList();
					$threadList->getConditionBuilder()->add('thread.threadID IN (?)', [$ids]);
					$threadList->getConditionBuilder()->add('thread.isDeleted = ?', [0]);
					$threadList->readObjects();
					$threads = $threadList->getObjects();
					if (count($threads)) {
						$found = 1;
						$this->executeBot($bot, $threads, 'trash', $modifications['threadModificationAuthorOnly'], $defaultLanguage);
					}
				}
				
				if ($modifications['threadModificationRestore']) {
					$threadList = new ThreadList();
					$threadList->getConditionBuilder()->add('thread.threadID IN (?)', [$ids]);
					$threadList->getConditionBuilder()->add('thread.isDeleted = ?', [1]);
					$threadList->readObjects();
					$threads = $threadList->getObjects();
					if (count($threads)) {
						$found = 1;
						$this->executeBot($bot, $threads, 'restore', $modifications['threadModificationAuthorOnly'], $defaultLanguage);
					}
				}
				
				if ($modifications['threadModificationSticky']) {
					$threadList = new ThreadList();
					$threadList->getConditionBuilder()->add('thread.threadID IN (?)', [$ids]);
					$threadList->getConditionBuilder()->add('thread.isSticky = ?', [0]);
					$threadList->readObjects();
					$threads = $threadList->getObjects();
					if (count($threads)) {
						$found = 1;
						$this->executeBot($bot, $threads, 'sticky', $modifications['threadModificationAuthorOnly'], $defaultLanguage);
					}
				}
				
				if ($modifications['threadModificationScrape']) {
					$threadList = new ThreadList();
					$threadList->getConditionBuilder()->add('thread.threadID IN (?)', [$ids]);
					$threadList->getConditionBuilder()->add('thread.isSticky = ?', [1]);
					$threadList->readObjects();
					$threads = $threadList->getObjects();
					if (count($threads)) {
						$found = 1;
						$this->executeBot($bot, $threads, 'scrape', $modifications['threadModificationAuthorOnly'], $defaultLanguage);
					}
				}
				
				if ($modifications['threadModificationOpen']) {
					$threadList = new ThreadList();
					$threadList->getConditionBuilder()->add('thread.threadID IN (?)', [$ids]);
					$threadList->getConditionBuilder()->add('thread.isClosed = ?', [1]);
					$threadList->readObjects();
					$threads = $threadList->getObjects();
					if (count($threads)) {
						$found = 1;
						$this->executeBot($bot, $threads, 'open', $modifications['threadModificationAuthorOnly'], $defaultLanguage);
					}
				}
				
				if ($modifications['threadModificationClose']) {
					$threadList = new ThreadList();
					$threadList->getConditionBuilder()->add('thread.threadID IN (?)', [$ids]);
					$threadList->getConditionBuilder()->add('thread.isClosed = ?', [0]);
					$threadList->readObjects();
					$threads = $threadList->getObjects();
					if (count($threads)) {
						$found = 1;
						$this->executeBot($bot, $threads, 'close', $modifications['threadModificationAuthorOnly'], $defaultLanguage);
					}
				}
				
				if ($modifications['threadModificationMove']) {
					$threadList = new ThreadList();
					$threadList->getConditionBuilder()->add('thread.threadID IN (?)', [$ids]);
					$threadList->getConditionBuilder()->add('thread.boardID <> ?', [$modifications['threadModificationBoardID']]);
					$threadList->readObjects();
					$threads = $threadList->getObjects();
					if (count($threads)) {
						$found = 1;
						$this->executeBot($bot, $threads, 'move', $modifications['threadModificationAuthorOnly'], $defaultLanguage);
					}
				}
				
				// break if thread was found
				if ($found) break;
			}
			
			// Reset to old user
			WCF::getSession()->changeUser($oldUser, true);
		}
	}
	
	protected function executeBot($bot, $threads, $action, $authorOnly, $defaultLanguage, $labels = []) {
		$affectedUserIDs = $countToUserID = $placeholders = $threadIDs = $threadToUser = [];
		
		if (!count($threads)) {
			if ($bot->enableLog) {
				if (!$bot->testMode) {
					UzbotLogEditor::create([
							'bot' => $bot,
							'count' => 0,
							'additionalData' => $defaultLanguage->getDynamicVariable('wcf.acp.uzbot.log.wbb.thread.modified', [
									'action' => $defaultLanguage->get('wcf.acp.uzbot.wbb.threadModification.action.' . $action),
									'threadIDs' => ''
							])
					]);
					
					UzbotLogEditor::create([
							'bot' => $bot,
							'count' => count($affectedUserIDs),
							'additionalData' => $defaultLanguage->getDynamicVariable('wcf.acp.uzbot.log.user.affected', [
									'total' => 0,
									'userIDs' => ''
							])
					]);
				}
				else {
					$result = $defaultLanguage->getDynamicVariable('wcf.acp.uzbot.log.test', [
							'objects' => 0,
							'users' => 0,
							'userIDs' => ''
					]);
					if (mb_strlen($result) > 64000) $result = mb_substr($result, 0, 64000) . ' ...';
					UzbotLogEditor::create([
							'bot' => $bot,
							'count' => count($threadIDs),
							'testMode' => 1,
							'additionalData' => serialize(['', '', $result])
					]);
				}
			}
			return;
		}
		
		foreach ($threads as $thread) {
			$threadIDs[] = $thread->threadID;
			
			if ($authorOnly) {
				if (!$thread->userID) continue;
				
				$threadToUser[$thread->threadID] = $thread->userID;
				
				$affectedUserIDs[] = $thread->userID;
				if (isset($countToUserID[$thread->userID])) $countToUserID[$thread->userID] ++;
				else $countToUserID[$thread->userID] = 1;
			}
			else {
				$postList = new PostList();
				$postList->getConditionBuilder()->add('post.threadID = ?', [$thread->threadID]);
				$postList->getConditionBuilder()->add('post.userID > ?', [0]);
			//	$postList->getConditionBuilder()->add('post.isDeleted = ?', [0]);
			//	$postList->getConditionBuilder()->add('post.isDisabled = ?', [0]);
				$postList->readObjects();
				$posts = $postList->getObjects();
				foreach ($posts as $post) {
					$affectedUserIDs[] = $post->userID;
					if (isset($countToUserID[$post->userID])) $countToUserID[$post->userID] ++;
					else $countToUserID[$post->userID] = 1;
				}
			}
		}
		
		$affectedUserIDs = array_unique($affectedUserIDs);
		
		// change user for action + execute unless test mode
		if (!$bot->testMode) {
			if ($action != 'setLabels' && $action != 'deleteLabels' && $action != 'move' && $action != 'unannounce') {
				$threadAction = new ThreadAction($threads, $action);
				$threadAction->executeAction();
			}
			else if ($action == 'move') {
				$modifications = unserialize($bot->wbbThreadModificationData);
				$threadAction = new ThreadAction($threads, 'move', [
						'boardID' => $modifications['threadModificationBoardID'],
						'isBulkProcessing' => false,
						'showMoveNotice' => false
				]);
				$threadAction->executeAction();
			}
			else if ($action == 'unannounce') {
				$threadAction = new ThreadAction($threads, 'update', [
						'data' => ['isAnnouncement' => 0],
						'announcementBoardIDs' => [],
						'isBulkProcessing' => false
				]);
				$threadAction->executeAction();
			}
			else {
				// label object type
				$objectType = LabelHandler::getInstance()->getObjectType('com.woltlab.wbb.thread');
				$objectTypeID = $objectType->objectTypeID;
				
				// delete ...
				if ($action == 'deleteLabels') {
					$objectTypeID = LabelHandler::getInstance()->getObjectType('com.woltlab.wbb.thread')->objectTypeID;
					$oldLabels = LabelHandler::getInstance()->getAssignedLabels($objectTypeID, $threadIDs, false);
					
					// remove labels
					foreach ($threads as $thread) {
						LabelHandler::getInstance()->setLabels([], $objectTypeID, $thread->threadID, false);
						
						// update hasLabels flag
						$editor = new ThreadEditor($thread);
						$editor->update(['hasLabels' => 0]);
					}
					
					$assignedLabels = LabelHandler::getInstance()->getAssignedLabels($objectTypeID, $threadIDs, false);
					$labelList = null;
					
					// clear log
					WCF::getDB()->beginTransaction();
					foreach ($threads as $thread) {
						$groupedOldLabels = [];
						if (!empty($oldLabels[$thread->threadID])) {
							foreach ($oldLabels[$thread->threadID] as $oldLabel) {
								$groupedOldLabels[$oldLabel->groupID] = $oldLabel;
							}
						}
							
						if ($labelList !== null) {
							foreach ($labelList as $label) {
								if (!isset($groupedOldLabels[$label->groupID]) || $label->labelID != $groupedOldLabels[$label->groupID]->labelID) {
									ThreadModificationLogHandler::getInstance()->setLabel($thread, $label, (isset($groupedOldLabels[$label->groupID]) ? $groupedOldLabels[$label->groupID] : null));
								}
								if (isset($groupedOldLabels[$label->groupID])) unset($groupedOldLabels[$label->groupID]);
							}
						}
						foreach ($groupedOldLabels as $groupID => $label) {
							ThreadModificationLogHandler::getInstance()->setLabel($thread, null, $label);
						}
					}
					WCF::getDB()->commitTransaction();
					
				}
				
				// set ...
				if ($action == 'setLabels') {
					foreach ($labels as $temp) {
						foreach ($temp as $label) {
							$labelIDs[] = $label->labelID;
						}
					}
					$botLabels = $labels;
					$botLabelIDs = $labelIDs;
					
					// almost same as above
					$objectTypeID = LabelHandler::getInstance()->getObjectType('com.woltlab.wbb.thread')->objectTypeID;
					$oldLabels = LabelHandler::getInstance()->getAssignedLabels($objectTypeID, $threadIDs, false);
					
					foreach ($threads as $thread) {
						LabelHandler::getInstance()->setLabels($botLabelIDs, $objectTypeID, $thread->threadID, false);
						
						$editor = new ThreadEditor($thread);
						$editor->update(['hasLabels' => !empty($botLabelIDs) ? 1 : 0]);
					}
					
					$assignedLabels = LabelHandler::getInstance()->getAssignedLabels($objectTypeID, $threadIDs, false);
					$labelList = null;
					if (!empty($assignedLabels)) {
						$labelList = reset($assignedLabels);
					}
					
					// log changes
					WCF::getDB()->beginTransaction();
					foreach ($threads as $thread) {
						$groupedOldLabels = [];
						if (!empty($oldLabels[$thread->threadID])) {
							foreach ($oldLabels[$thread->threadID] as $oldLabel) {
								$groupedOldLabels[$oldLabel->groupID] = $oldLabel;
							}
						}
						
						if ($labelList !== null) {
							foreach ($labelList as $label) {
								if (!isset($groupedOldLabels[$label->groupID]) || $label->labelID != $groupedOldLabels[$label->groupID]->labelID) {
									ThreadModificationLogHandler::getInstance()->setLabel($thread, $label, (isset($groupedOldLabels[$label->groupID]) ? $groupedOldLabels[$label->groupID] : null));
								}
								if (isset($groupedOldLabels[$label->groupID])) unset($groupedOldLabels[$label->groupID]);
							}
						}
						foreach ($groupedOldLabels as $groupID => $label) {
							ThreadModificationLogHandler::getInstance()->setLabel($thread, null, $label);
						}
					}
					WCF::getDB()->commitTransaction();
				}
			}
		}
		
		if ($bot->enableLog) {
			$result1 = $defaultLanguage->getDynamicVariable('wcf.acp.uzbot.log.wbb.thread.modified', [
					'action' => $defaultLanguage->get('wcf.acp.uzbot.wbb.threadModification.action.' . $action),
					'threadIDs' => implode(', ', $threadIDs)
			]);
			if (mb_strlen($result1) > 64000) $result1 = mb_substr($result1, 0, 64000) . ' ...';
			
			$result2 = $defaultLanguage->getDynamicVariable('wcf.acp.uzbot.log.user.affected', [
					'total' => count($affectedUserIDs),
					'userIDs' => implode(', ', $affectedUserIDs)
			]);
			if (mb_strlen($result2) > 64000) $result2 = mb_substr($result2, 0, 64000) . ' ...';
			
			UzbotLogEditor::create([
					'bot' => $bot,
					'testMode' => !$bot->testMode ? 0 : 1,
					'count' => count($threadIDs),
					'additionalData' => !$bot->testMode ? $result1 : serialize(['', '', $result1])
			]);
			
			UzbotLogEditor::create([
					'bot' => $bot,
					'testMode' => !$bot->testMode ? 0 : 1,
					'count' => count($affectedUserIDs),
					'additionalData' => !$bot->testMode ? $result2 : serialize(['', '', $result2])
			]);
		}
		
		// check for and prepare notification
		if ($bot->notifyID) {
			$notify = $bot->checkNotify(true, true);
			if ($notify === null) return;
			
			$placeholders['count'] = count($threadIDs);
			$placeholders['object-ids'] = implode(', ', $threadIDs);
			$placeholders['action'] = $defaultLanguage->get('wcf.acp.uzbot.wbb.threadModification.action.' . $action);
			
			// test mode
			$testUserIDs = $testToUserIDs = [];
			if (count($affectedUserIDs)) {
				$userID = reset($affectedUserIDs);
				$testUserIDs[] = $userID;
				$testToUserIDs[$userID] = $countToUserID[$userID];
			}
			
			// send to scheduler, if not test mode
			if ($bot->testMode) {
				// only one notification
				$data = [
						'bot' => $bot,
						'placeholders' => $placeholders,
						'affectedUserIDs' => !$bot->testMode ? $affectedUserIDs : $testUserIDs,
						'countToUserID' => !$bot->testMode ? $countToUserID : $testToUserIDs
				];
				
				$job = new NotifyScheduleBackgroundJob($data);
				BackgroundQueueHandler::getInstance()->performJob($job);
			}
			else {
				
				// not post - only one
				if ($bot->notifyDes != 'post') {
					$data = [
							'bot' => $bot,
							'placeholders' => $placeholders,
							'affectedUserIDs' => !$bot->testMode ? $affectedUserIDs : $testUserIDs,
							'countToUserID' => !$bot->testMode ? $countToUserID : $testToUserIDs
					];
					
					$job = new NotifyScheduleBackgroundJob($data);
					BackgroundQueueHandler::getInstance()->performJob($job);
				}
				else {
					$wbbPostData = unserialize($bot->wbbPostData);
					
					// threadID given - only one
					if ($wbbPostData['postNotifyThreadID']) {
						$data = [
								'bot' => $bot,
								'placeholders' => $placeholders,
								'affectedUserIDs' => !$bot->testMode ? $affectedUserIDs : $testUserIDs,
								'countToUserID' => !$bot->testMode ? $countToUserID : $testToUserIDs
						];
						
						$job = new NotifyScheduleBackgroundJob($data);
						BackgroundQueueHandler::getInstance()->performJob($job);
					}
					// threadID = 0 - one post in each thread
					else {
						// there must be threads
						if (count($threadIDs)) {
							// preset
							$data = [
									'bot' => $bot,
									'placeholders' => $placeholders,
									'affectedUserIDs' => !$bot->testMode ? $affectedUserIDs : $testUserIDs,
									'countToUserID' => !$bot->testMode ? $countToUserID : $testToUserIDs
							];
							
							foreach ($threadIDs as $threadID) {
								$bot->threadID = $threadID;
								
								// limit affected user to one if author only
								if ($authorOnly && !$bot->testMode) {
									$affectedUserIDs = $countToUserID = [];
									
									$affectedUserIDs[] = $threadToUser[$threadID];
									$countToUserID[$threadToUser[$threadID]] = 1;
									
									$data = [
											'bot' => $bot,
											'placeholders' => $placeholders,
											'affectedUserIDs' => $affectedUserIDs,
											'countToUserID' => $countToUserID
									];
								}
								else {
									$data['bot'] = $bot;
								}
								
								$job = new NotifyScheduleBackgroundJob($data);
								BackgroundQueueHandler::getInstance()->performJob($job);
							}
						}
					}
				}
			}
		}
	}
}
