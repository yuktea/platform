<?php defined('SYSPATH') or die('No direct script access');

/**
 * Ushahidi PostSet Listener
 *
 * Listens for new posts that are added to a set
 *
 * @author     Ushahidi Team <team@ushahidi.com>
 * @package    Ushahidi\Application
 * @copyright  2014 Ushahidi
 * @license    https://www.gnu.org/licenses/agpl-3.0.html GNU Affero General Public License Version 3 (AGPL3)
 */

use League\Event\AbstractListener;
use League\Event\EventInterface;
use \Ushahidi\Core\Entity\FormRepository;
use \Ushahidi\Core\Entity\ContactRepository;
use \Ushahidi\Core\Entity\PostRepository;
use \Ushahidi\Core\Entity\MessageRepository;
use \Ushahidi\Core\Entity\FormAttributeRepository;
use \Ushahidi\Core\Entity\TargetedSurveyStateRepository;

class Ushahidi_Listener_ContactListener extends AbstractListener
{
	protected $repo;
	protected $post_repo;
	protected $form_repo;
	protected $message_repo;
	protected $form_attribute_repo;
	protected $targeted_survey_state;

	public function setRepo(ContactRepository $repo)
	{
		$this->repo = $repo;
	}


	public function setPostRepo(PostRepository $repo)
	{
		$this->post_repo = $repo;
	}

	public function setFormRepo(FormRepository $repo)
	{
		$this->form_repo = $repo;
	}

	public function setMessageRepo(MessageRepository $repo)
	{
		$this->message_repo = $repo;
	}

	public function setFormAttributeRepo(FormAttributeRepository $repo)
	{
		$this->form_attribute_repo = $repo;
	}

	public function setTargetedSurveyStateRepo(TargetedSurveyStateRepository $repo)
	{
		$this->targeted_survey_state = $repo;
	}

	public function handle(EventInterface $event, $contactIds = null , $form_id = null, $event_type = null)
	{
		$result = [];
		foreach ($contactIds as $contactId) {
			$formEntity = $this->form_repo->get($form_id);
			$contactEntity = $this->repo->get($contactId);

			/**
			 * Create a new Post record per contact (related to the current survey/form_id).
			 * Each post has an autogenerated Title+Description based on contact and survey name
			 */
			$post = $this->post_repo->getEntity();
			$postState = array(
				'title' => "{$formEntity->name} - {$contactEntity->contact}",
				'content' => "{$formEntity->name} - {$contactEntity->contact}",
				'form_id' => $form_id,
				'status' => 'draft'
			);
			$post->setState($postState);
			$postId = $this->post_repo->create($post);
			/**
			 *  Create the first message (first survey question) for each contact.
			 *  Use the message status to mark it as "pending" (ready for delivery via SMS)
			 */
			$message = $this->message_repo->getEntity();
			$firstAttribute = $this->form_attribute_repo->getFirstByForm($form_id);
			if (!$firstAttribute->id) {
				Kohana::$log->add(
					Log::ERROR,
					'Could not find attributes in form id :form. Messages for contact :contact in this form will not be sent',
					array(':form' => $form_id, ':contact' => $contactId)
				);
				throw new Exception(
					sprintf(
						'Could not find attributes in form id %s. Messages for contact %s in this form will not be sent',
						$form_id, $contactId
					)
				);
			}

			// MESSAGE TYPE SHOULD BE CONFIGURABLE
			// FOR NOW IT IS RESTRICTED TO SMS
			$message_type = Message_Type::SMS;
			$data_provider = \DataProvider::getEnabledProviderForType($message_type);
			
			$messageState = array(
				'contact_id' => $contactId,
				'post_id' => $postId,
				'title' => $firstAttribute->label,
				'message' => $firstAttribute->label,
				'status' => 'pending',
				'type' => $message_type,
				'data_provider' => $data_provider,
			);
			$message->setState($messageState);
			$messageId = $this->message_repo->create($message);
			if (!$messageId) {
				Kohana::$log->add(
					Log::ERROR,
					'Could not create message for contact id :contact,  post id :post, and form id :form',
					array(':contact' => $contactId, ':post' => $postId, ':form' => $form_id)
				);
			}
			//contact post state
			$targetedSurveyStatus = $this->targeted_survey_state->getEntity();
			$targetedSurveyStatus->setState(
				array('message_id'=> $messageId, 'form_attribute_id'=> $firstAttribute->id, 'form_id' => $form_id, 'post_id' => $postId, 'contact_id' => $contactId, 'survey_status' => 'PENDING RESPONSE')
			);

			$targetedSurveyStatusId = $this->targeted_survey_state->create($targetedSurveyStatus);
			$result[] = $targetedSurveyStatusId;
		}
		return $result;
	}

}
